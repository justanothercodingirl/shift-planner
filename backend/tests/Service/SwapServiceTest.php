<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Dto\CreateSwapRequest;
use App\Entity\Assignment;
use App\Entity\Availability;
use App\Entity\Employee;
use App\Entity\Roster;
use App\Entity\Shift;
use App\Entity\SwapRequest;
use App\Entity\User;
use App\Enum\AvailabilityType;
use App\Enum\ContractType;
use App\Enum\Position;
use App\Enum\SwapStatus;
use App\Enum\UserRole;
use App\Exception\AssignmentNotFoundException;
use App\Exception\EmployeeInactiveException;
use App\Exception\EmployeeNotFoundException;
use App\Exception\RosterNotPublishedException;
use App\Exception\RuleViolationException;
use App\Exception\SwapAlreadyOpenException;
use App\Exception\SwapAssignmentChangedException;
use App\Exception\SwapDeclinedException;
use App\Exception\SwapNotInStateException;
use App\Exception\SwapRequestNotFoundException;
use App\Exception\SwapShiftStartedException;
use App\Exception\SwapWithYourselfException;
use App\Message\SendRosterChangedMail;
use App\Repository\AssignmentRepository;
use App\Repository\AvailabilityRepository;
use App\Repository\EmployeeRepository;
use App\Repository\RosterRepository;
use App\Repository\SwapRequestRepository;
use App\Repository\UserRepository;
use App\Service\AssignmentService;
use App\Service\ShiftValidator;
use App\Service\SwapService;
use App\Tests\Mail\RosterTestData;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Anna works a barista shift on Tuesday 2031-02-04 (08:00 to 14:00, one place) and asks
 * Bea, another barista, to take it. The roster is published and far in the future.
 */
final class SwapServiceTest extends KernelTestCase
{
    use RosterTestData;

    private EntityManagerInterface $em;
    private SwapService $service;
    private Roster $roster;
    private Shift $shift;
    private Assignment $assignment;
    private Employee $anna;
    private Employee $ben;
    private Employee $clara;
    private Employee $bea;
    private User $manager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->service = $container->get(SwapService::class);
        $this->em->getConnection()->beginTransaction();

        $data = $this->createRoster();
        $this->roster = $data['roster'];
        $this->anna = $data['anna'];
        $this->ben = $data['ben'];
        $this->clara = $data['clara'];
        $this->bea = $this->createEmployee('Bea', Position::Barista);
        $this->shift = $this->createShift($this->roster, '2031-02-04', '08:00', '14:00', Position::Barista, 1);
        $this->assignment = $this->saveAssignment($this->shift, $this->anna);
        $this->manager = new User('boss-swap@example.com', 'hash', UserRole::Manager);
        $container->get(UserRepository::class)->save($this->manager);
        $this->signInAs($this->anna->getUser());
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    // ---- asking -----------------------------------------------------------------

    public function testAnEmployeeAsksAColleagueToTakeTheirShift(): void
    {
        $request = $this->ask($this->bea);

        self::assertNotNull($request->getId());
        self::assertSame(SwapStatus::Pending, $request->getStatus());
        self::assertSame($this->anna, $request->getRequester());
        self::assertSame($this->bea, $request->getTarget());
        self::assertSame($this->shift, $request->getShift());
        self::assertSame($this->assignment, $request->getAssignment());
        self::assertEqualsWithDelta(time(), $request->getCreatedAt()->getTimestamp(), 5);
    }

    public function testOnlyTheOwnerOfTheAssignmentMayAsk(): void
    {
        $this->signInAs($this->ben->getUser());

        $this->expectException(AccessDeniedException::class);
        $this->ask($this->bea);
    }

    public function testAnUnknownAssignmentIsNotFound(): void
    {
        $this->expectException(AssignmentNotFoundException::class);

        $this->service->request(999999, new CreateSwapRequest((int) $this->bea->getId()));
    }

    public function testAShiftInADraftRosterCannotBeSwapped(): void
    {
        $draft = new Roster(new \DateTimeImmutable('2031-03-03'));
        self::getContainer()->get(RosterRepository::class)->save($draft);
        $shift = $this->createShift($draft, '2031-03-04', '08:00', '14:00', Position::Barista, 1);
        $assignment = $this->saveAssignment($shift, $this->anna);

        $this->expectException(RosterNotPublishedException::class);
        $this->service->request((int) $assignment->getId(), new CreateSwapRequest((int) $this->bea->getId()));
    }

    public function testAShiftThatAlreadyStartedCannotBeSwapped(): void
    {
        [, $assignment] = $this->pastShiftOf($this->anna);

        $this->expectException(SwapShiftStartedException::class);
        $this->service->request((int) $assignment->getId(), new CreateSwapRequest((int) $this->bea->getId()));
    }

    public function testSwappingWithYourselfIsRefused(): void
    {
        $this->expectException(SwapWithYourselfException::class);

        $this->ask($this->anna);
    }

    public function testADeactivatedColleagueCannotBeAsked(): void
    {
        $gone = $this->createEmployee('Gone', Position::Barista, active: false);

        $this->expectException(EmployeeInactiveException::class);
        $this->ask($gone);
    }

    public function testAnUnknownColleagueIsNotFound(): void
    {
        $this->expectException(EmployeeNotFoundException::class);

        $this->service->request((int) $this->assignment->getId(), new CreateSwapRequest(999999));
    }

    public function testASecondOpenRequestForTheSameShiftIsRefusedWhilePendingOrAccepted(): void
    {
        $first = $this->ask($this->bea);
        try {
            $this->ask($this->ben);
            self::fail('A pending request is already open.');
        } catch (SwapAlreadyOpenException) {
        }

        $this->signInAs($this->bea->getUser());
        $this->service->accept((int) $first->getId());
        $this->signInAs($this->anna->getUser());

        $this->expectException(SwapAlreadyOpenException::class);
        $this->ask($this->ben);
    }

    public function testAfterADeclineANewRequestIsAllowed(): void
    {
        $first = $this->ask($this->bea);
        $this->signInAs($this->bea->getUser());
        $this->service->decline((int) $first->getId());
        $this->signInAs($this->anna->getUser());

        $second = $this->ask($this->ben);

        self::assertSame(SwapStatus::Pending, $second->getStatus());
    }

    // ---- the colleague answers --------------------------------------------------

    public function testTheColleagueAccepts(): void
    {
        $request = $this->ask($this->bea);
        $this->signInAs($this->bea->getUser());

        $accepted = $this->service->accept((int) $request->getId());

        self::assertSame(SwapStatus::Accepted, $accepted->getStatus());
    }

    public function testTheShiftBeingFullDoesNotBlockAHandover(): void
    {
        // The one place is taken by Anna, so the rules alone would say SHIFT_FULL for Bea ...
        $codes = array_map(
            static fn ($violation): string => $violation->code,
            self::getContainer()->get(ShiftValidator::class)->validate($this->bea, $this->shift),
        );
        self::assertContains('SHIFT_FULL', $codes);

        // ... but a handover keeps the headcount, so accepting works.
        $request = $this->ask($this->bea);
        $this->signInAs($this->bea->getUser());

        self::assertSame(SwapStatus::Accepted, $this->service->accept((int) $request->getId())->getStatus());
    }

    public function testOnlyTheColleagueMayAcceptOrDecline(): void
    {
        $request = $this->ask($this->bea);
        $id = (int) $request->getId();

        foreach (['accept', 'decline'] as $action) {
            try {
                $this->service->{$action}($id); // still signed in as Anna
                self::fail('Only the colleague may '.$action.'.');
            } catch (AccessDeniedException) {
            }
        }

        $this->addToAssertionCount(1);
    }

    public function testAcceptingTwiceOrAfterADeclineIsRefused(): void
    {
        $request = $this->ask($this->bea);
        $this->signInAs($this->bea->getUser());
        $this->service->accept((int) $request->getId());

        $this->expectException(SwapNotInStateException::class);
        $this->service->accept((int) $request->getId());
    }

    public function testTheRulesRunForTheColleagueWhenAccepting(): void
    {
        $request = $this->ask($this->clara); // Clara works in the kitchen, the shift needs a barista
        $this->signInAs($this->clara->getUser());

        try {
            $this->service->accept((int) $request->getId());
            self::fail('Clara must fail the position rule.');
        } catch (RuleViolationException $exception) {
            self::assertSame(['POSITION_MISMATCH'], array_column($exception->getViolations(), 'code'));
        }

        self::assertSame(SwapStatus::Pending, $request->getStatus());
    }

    public function testTheColleagueDeclines(): void
    {
        $request = $this->ask($this->bea);
        $this->signInAs($this->bea->getUser());

        self::assertSame(SwapStatus::Declined, $this->service->decline((int) $request->getId())->getStatus());
    }

    public function testAcceptingAShiftThatNoLongerBelongsToTheRequesterCancelsTheRequest(): void
    {
        $request = $this->ask($this->bea);
        $id = (int) $request->getId();
        $this->managerRemovesTheAssignment();
        $this->signInAs($this->bea->getUser());

        try {
            $this->service->accept($id);
            self::fail('The assignment is gone.');
        } catch (SwapAssignmentChangedException) {
        }

        $this->em->clear();
        self::assertSame(SwapStatus::Cancelled, self::getContainer()->get(SwapRequestRepository::class)->find($id)?->getStatus());
    }

    public function testAcceptingAShiftThatAlreadyStartedIsRefused(): void
    {
        [$shift, $assignment] = $this->pastShiftOf($this->anna);
        $request = $this->saveSwap($shift, $assignment, $this->anna, $this->bea);
        $this->signInAs($this->bea->getUser());

        $this->expectException(SwapShiftStartedException::class);
        $this->service->accept((int) $request->getId());
    }

    // ---- the requester cancels --------------------------------------------------

    public function testTheRequesterCancelsAPendingOrAcceptedRequest(): void
    {
        $pending = $this->ask($this->bea);
        self::assertSame(SwapStatus::Cancelled, $this->service->cancel((int) $pending->getId())->getStatus());

        $second = $this->ask($this->bea);
        $this->signInAs($this->bea->getUser());
        $this->service->accept((int) $second->getId());
        $this->signInAs($this->anna->getUser());

        self::assertSame(SwapStatus::Cancelled, $this->service->cancel((int) $second->getId())->getStatus());
    }

    public function testOnlyTheRequesterMayCancel(): void
    {
        $request = $this->ask($this->bea);
        $this->signInAs($this->bea->getUser());

        $this->expectException(AccessDeniedException::class);
        $this->service->cancel((int) $request->getId());
    }

    public function testAFinishedRequestCannotBeCancelled(): void
    {
        $request = $this->ask($this->bea);
        $this->signInAs($this->bea->getUser());
        $this->service->decline((int) $request->getId());
        $this->signInAs($this->anna->getUser());

        $this->expectException(SwapNotInStateException::class);
        $this->service->cancel((int) $request->getId());
    }

    // ---- the manager decides ----------------------------------------------------

    public function testApprovingHandsTheSameAssignmentToTheColleague(): void
    {
        $request = $this->acceptedRequest();
        $assignmentId = $this->assignment->getId();
        $this->signInAs($this->manager);

        $approved = $this->service->approve((int) $request->getId());

        self::assertSame(SwapStatus::Approved, $approved->getStatus());
        self::assertSame($this->bea, $this->assignment->getEmployee());
        self::assertSame($assignmentId, $this->assignment->getId());
        $people = array_map(
            static fn (Assignment $a): string => $a->getEmployee()->getName(),
            self::getContainer()->get(AssignmentRepository::class)->findByShift($this->shift),
        );
        self::assertSame(['Bea'], $people);
    }

    public function testApprovingDoesNotSendTheGenericChangeMails(): void
    {
        $request = $this->acceptedRequest();
        $this->signInAs($this->manager);

        $this->service->approve((int) $request->getId());

        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        $changeMails = array_filter(
            array_map(static fn ($envelope): object => $envelope->getMessage(), $transport->getSent()),
            static fn (object $message): bool => $message instanceof SendRosterChangedMail,
        );
        self::assertSame([], array_values($changeMails));
    }

    public function testApprovingARequestTheColleagueHasNotAcceptedYetIsRefused(): void
    {
        $request = $this->ask($this->bea);
        $this->signInAs($this->manager);

        $this->expectException(SwapNotInStateException::class);
        $this->service->approve((int) $request->getId());
    }

    public function testApprovingADeclinedRequestSaysTheColleagueDeclined(): void
    {
        $request = $this->ask($this->bea);
        $this->signInAs($this->bea->getUser());
        $this->service->decline((int) $request->getId());
        $this->signInAs($this->manager);

        $this->expectException(SwapDeclinedException::class);
        $this->service->approve((int) $request->getId());
    }

    public function testApprovingTwiceIsRefused(): void
    {
        $request = $this->acceptedRequest();
        $this->signInAs($this->manager);
        $this->service->approve((int) $request->getId());

        $this->expectException(SwapNotInStateException::class);
        $this->service->approve((int) $request->getId());
    }

    public function testApprovingAfterTheAssignmentWasRemovedCancelsTheRequest(): void
    {
        $request = $this->acceptedRequest();
        $id = (int) $request->getId();
        $this->managerRemovesTheAssignment();
        $this->signInAs($this->manager);

        try {
            $this->service->approve($id);
            self::fail('The assignment is gone.');
        } catch (SwapAssignmentChangedException) {
        }

        $this->em->clear();
        self::assertSame(SwapStatus::Cancelled, self::getContainer()->get(SwapRequestRepository::class)->find($id)?->getStatus());
    }

    public function testApprovingAfterTheShiftWasGivenToSomeoneElseCancelsTheRequest(): void
    {
        $request = $this->acceptedRequest();
        $cleo = $this->createEmployee('Cleo', Position::Barista);
        $this->assignment->reassignTo($cleo);
        self::getContainer()->get(AssignmentRepository::class)->save($this->assignment);
        $this->signInAs($this->manager);

        $this->expectException(SwapAssignmentChangedException::class);
        $this->service->approve((int) $request->getId());
    }

    public function testTheRulesRunAgainForTheColleagueWhenApproving(): void
    {
        $request = $this->acceptedRequest();
        // Meanwhile Bea was given an overlapping shift on the same day.
        $this->saveAssignment($this->createShift($this->roster, '2031-02-04', '10:00', '16:00', Position::Barista, 1), $this->bea);
        $this->signInAs($this->manager);

        try {
            $this->service->approve((int) $request->getId());
            self::fail('Bea now overlaps.');
        } catch (RuleViolationException $exception) {
            self::assertContains('OVERLAP', array_column($exception->getViolations(), 'code'));
        }

        self::assertSame(SwapStatus::Accepted, $request->getStatus());
        self::assertSame($this->anna, $this->assignment->getEmployee());
    }

    public function testApprovingAShiftThatAlreadyStartedIsRefused(): void
    {
        [$shift, $assignment] = $this->pastShiftOf($this->anna);
        $request = $this->saveSwap($shift, $assignment, $this->anna, $this->bea);
        $request->accept();
        self::getContainer()->get(SwapRequestRepository::class)->save($request);
        $this->signInAs($this->manager);

        $this->expectException(SwapShiftStartedException::class);
        $this->service->approve((int) $request->getId());
    }

    public function testTheManagerRejectsAPendingOrAcceptedRequest(): void
    {
        $pending = $this->ask($this->bea);
        $this->signInAs($this->manager);
        self::assertSame(SwapStatus::Rejected, $this->service->reject((int) $pending->getId())->getStatus());

        $this->signInAs($this->anna->getUser());
        $accepted = $this->acceptedRequest();
        $this->signInAs($this->manager);
        self::assertSame(SwapStatus::Rejected, $this->service->reject((int) $accepted->getId())->getStatus());
        self::assertSame($this->anna, $this->assignment->getEmployee());
    }

    public function testRejectingAFinishedRequestIsRefused(): void
    {
        $request = $this->acceptedRequest();
        $this->signInAs($this->manager);
        $this->service->approve((int) $request->getId());

        $this->expectException(SwapNotInStateException::class);
        $this->service->reject((int) $request->getId());
    }

    public function testAnUnknownRequestIsNotFound(): void
    {
        $this->signInAs($this->manager);

        $this->expectException(SwapRequestNotFoundException::class);
        $this->service->approve(999999);
    }

    // ---- who sees what ----------------------------------------------------------

    public function testEmployeesSeeOnlyTheRequestsTheyMadeOrReceived(): void
    {
        $mine = $this->ask($this->bea); // Anna to Bea
        $other = $this->saveSwap($this->shiftOfEmployee($this->ben, '2031-02-05'), null, $this->ben, $this->clara);

        $this->signInAs($this->anna->getUser());
        self::assertSame([$mine->getId()], $this->ids($this->service->list()));
        $this->signInAs($this->bea->getUser());
        self::assertSame([$mine->getId()], $this->ids($this->service->list()));
        $this->signInAs($this->clara->getUser());
        self::assertSame([$other->getId()], $this->ids($this->service->list()));
    }

    public function testTheManagerSeesEverythingAndCanFilterByStatus(): void
    {
        $first = $this->ask($this->bea);
        $second = $this->saveSwap($this->shiftOfEmployee($this->ben, '2031-02-05'), null, $this->ben, $this->clara);
        $second->accept();
        self::getContainer()->get(SwapRequestRepository::class)->save($second);
        $this->signInAs($this->manager);

        self::assertEqualsCanonicalizing([$first->getId(), $second->getId()], $this->ids($this->service->list()));
        self::assertSame([$second->getId()], $this->ids($this->service->list(SwapStatus::Accepted)));
    }

    public function testALoginWithoutAnEmployeeProfileSeesNothing(): void
    {
        $this->ask($this->bea);
        $plain = new User('plain-swap@example.com', 'hash', UserRole::Employee);
        self::getContainer()->get(UserRepository::class)->save($plain);
        $this->signInAs($plain);

        self::assertSame([], $this->service->list());
    }

    // ---- who can be asked -------------------------------------------------------

    public function testOnlyColleaguesQualifiedForTheShiftsPositionAreCandidates(): void
    {
        // Ben works service and Clara in the kitchen: the shift needs a barista. Bea is a barista.
        $cleo = $this->employeeWith('Cleo', Position::Service, [Position::Barista]); // barista as an additional position

        self::assertSame(['Bea', 'Cleo'], $this->candidateNames());
        self::assertNotContains('Ben', $this->candidateNames());
        self::assertNotContains('Clara', $this->candidateNames());
        self::assertNotNull($cleo->getId());
    }

    public function testTheOwnerAndDeactivatedPeopleAreNotCandidates(): void
    {
        $this->createEmployee('Gone', Position::Barista, active: false);

        $names = $this->candidateNames();

        self::assertNotContains('Anna', $names);
        self::assertNotContains('Gone', $names);
    }

    public function testCandidatesAreListedByName(): void
    {
        $this->createEmployee('Aaron', Position::Barista);

        self::assertSame(['Aaron', 'Bea'], $this->candidateNames());
    }

    public function testSomeoneWithAClashingShiftIsNotACandidate(): void
    {
        $this->saveAssignment($this->createShift($this->shift->getRoster(), '2031-02-04', '10:00', '16:00', Position::Barista, 1), $this->bea);

        self::assertNotContains('Bea', $this->candidateNames());
    }

    public function testSomeoneWithTooLittleRestIsNotACandidate(): void
    {
        // Bea works until 23:00 the evening before; the shift starts at 08:00: 9 hours of rest.
        $this->saveAssignment($this->createShift($this->shift->getRoster(), '2031-02-03', '20:00', '23:00', Position::Barista, 1), $this->bea);

        self::assertNotContains('Bea', $this->candidateNames());
    }

    public function testSomeoneWhoWouldGoOverTheirWeeklyHoursIsNotACandidate(): void
    {
        // Bea may work 20 hours a week and already has 18; the shift is 6 more.
        foreach (['2031-02-05', '2031-02-06', '2031-02-07'] as $date) {
            $this->saveAssignment($this->createShift($this->shift->getRoster(), $date, '08:00', '14:00', Position::Barista, 1), $this->bea);
        }

        self::assertNotContains('Bea', $this->candidateNames());
    }

    public function testSomeoneUnavailableThatDayIsNotACandidate(): void
    {
        self::getContainer()->get(AvailabilityRepository::class)->save(new Availability(
            $this->bea,
            \App\Enum\Weekday::Tuesday,
            null,
            new \DateTimeImmutable('07:00'),
            new \DateTimeImmutable('15:00'),
            AvailabilityType::Unavailable,
        ));

        self::assertNotContains('Bea', $this->candidateNames());
    }

    public function testSomeoneAlreadyOnTheShiftIsNotACandidate(): void
    {
        $this->saveAssignment($this->shift, $this->bea); // written directly: the shift now has two people

        self::assertNotContains('Bea', $this->candidateNames());
    }

    public function testAFullShiftDoesNotEmptyTheList(): void
    {
        // The shift has one place and Anna holds it, yet Bea is listed: a handover keeps the headcount.
        self::assertSame(1, $this->shift->getHeadcount());
        self::assertContains('Bea', $this->candidateNames());
    }

    public function testEveryCandidateCanReallyAccept(): void
    {
        foreach ($this->service->candidates((int) $this->assignment->getId()) as $candidate) {
            $this->signInAs($this->anna->getUser());
            $request = $this->ask($candidate);
            $this->signInAs($candidate->getUser());

            self::assertSame(SwapStatus::Accepted, $this->service->accept((int) $request->getId())->getStatus());
            $this->signInAs($this->anna->getUser());
            $this->service->cancel((int) $request->getId());
        }

        self::assertNotSame([], $this->candidateNames());
    }

    public function testOnlyTheOwnerMayListCandidates(): void
    {
        $this->signInAs($this->ben->getUser());

        $this->expectException(AccessDeniedException::class);
        $this->service->candidates((int) $this->assignment->getId());
    }

    public function testCandidatesOfAnUnknownAssignmentAreNotFound(): void
    {
        $this->expectException(AssignmentNotFoundException::class);

        $this->service->candidates(999999);
    }

    public function testNoCandidatesForADraftRosterOrAStartedShift(): void
    {
        $draft = new Roster(new \DateTimeImmutable('2031-03-03'));
        self::getContainer()->get(RosterRepository::class)->save($draft);
        $draftAssignment = $this->saveAssignment($this->createShift($draft, '2031-03-04', '08:00', '14:00', Position::Barista, 1), $this->anna);
        [, $pastAssignment] = $this->pastShiftOf($this->anna);

        try {
            $this->service->candidates((int) $draftAssignment->getId());
            self::fail('A draft roster cannot be swapped.');
        } catch (RosterNotPublishedException) {
        }

        $this->expectException(SwapShiftStartedException::class);
        $this->service->candidates((int) $pastAssignment->getId());
    }

    public function testNoCandidatesWhileARequestIsAlreadyOpen(): void
    {
        $this->ask($this->bea);

        $this->expectException(SwapAlreadyOpenException::class);
        $this->service->candidates((int) $this->assignment->getId());
    }

    // ---- helpers ----------------------------------------------------------------

    /** @return list<string> */
    private function candidateNames(): array
    {
        return array_map(
            static fn (Employee $e): string => $e->getName(),
            $this->service->candidates((int) $this->assignment->getId()),
        );
    }

    /** @param list<Position> $additionalPositions */
    private function employeeWith(string $name, Position $position, array $additionalPositions): Employee
    {
        $user = new User(strtolower($name).'-cand@example.com', 'hash', UserRole::Employee);
        self::getContainer()->get(UserRepository::class)->save($user);
        $employee = new Employee($user, $name, $position, ContractType::PartTime, 20, $additionalPositions);
        self::getContainer()->get(EmployeeRepository::class)->save($employee);

        return $employee;
    }

    private function ask(Employee $target): SwapRequest
    {
        return $this->service->request((int) $this->assignment->getId(), new CreateSwapRequest((int) $target->getId()));
    }

    /** A request to Bea that she has already accepted; the signed-in user is Anna again afterwards. */
    private function acceptedRequest(): SwapRequest
    {
        $request = $this->ask($this->bea);
        $this->signInAs($this->bea->getUser());
        $this->service->accept((int) $request->getId());
        $this->signInAs($this->anna->getUser());

        return $request;
    }

    private function managerRemovesTheAssignment(): void
    {
        $this->signInAs($this->manager);
        self::getContainer()->get(AssignmentService::class)->unassign((int) $this->assignment->getId());
        $this->em->clear();
    }

    /** @return array{Shift, Assignment} a shift in a published roster of 2020, long over */
    private function pastShiftOf(Employee $employee): array
    {
        $roster = new Roster(new \DateTimeImmutable('2020-02-03'));
        $roster->publish();
        self::getContainer()->get(RosterRepository::class)->save($roster);
        $shift = $this->createShift($roster, '2020-02-04', '08:00', '14:00', Position::Barista, 1);

        return [$shift, $this->saveAssignment($shift, $employee)];
    }

    private function shiftOfEmployee(Employee $employee, string $date): Shift
    {
        $shift = $this->createShift($this->roster, $date, '08:00', '14:00', Position::Barista, 1);
        $this->saveAssignment($shift, $employee);

        return $shift;
    }

    private function saveAssignment(Shift $shift, Employee $employee): Assignment
    {
        $assignment = new Assignment($shift, $employee);
        self::getContainer()->get(AssignmentRepository::class)->save($assignment);

        return $assignment;
    }

    private function saveSwap(Shift $shift, ?Assignment $assignment, Employee $requester, Employee $target): SwapRequest
    {
        $request = new SwapRequest($shift, $assignment, $requester, $target, new \DateTimeImmutable());
        self::getContainer()->get(SwapRequestRepository::class)->save($request);

        return $request;
    }

    /**
     * @param list<SwapRequest> $requests
     *
     * @return list<int|null>
     */
    private function ids(array $requests): array
    {
        return array_map(static fn (SwapRequest $r): ?int => $r->getId(), $requests);
    }

    private function signInAs(User $user): void
    {
        self::getContainer()->get(TokenStorageInterface::class)
            ->setToken(new UsernamePasswordToken($user, 'api', $user->getRoles()));
    }
}
