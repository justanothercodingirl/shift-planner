<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Dto\CreateSwapRequest;
use App\Entity\Assignment;
use App\Entity\Employee;
use App\Entity\Shift;
use App\Entity\SwapRequest;
use App\Entity\User;
use App\Enum\Position;
use App\Enum\UserRole;
use App\Exception\RuleViolationException;
use App\Exception\SwapAlreadyOpenException;
use App\Mail\SwapMailEvent;
use App\Message\SendRosterChangedMail;
use App\Message\SendSwapMail;
use App\Repository\AssignmentRepository;
use App\Repository\UserRepository;
use App\Service\AssignmentService;
use App\Service\SwapService;
use App\Tests\Mail\RosterTestData;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/** Every step of a swap queues its mail; a refused step queues nothing. Anna asks Bea to take Tuesday 2031-02-04. */
final class SwapMailsTest extends KernelTestCase
{
    use RosterTestData;

    private EntityManagerInterface $em;
    private SwapService $service;
    private Shift $shift;
    private Assignment $assignment;
    private Employee $anna;
    private Employee $bea;
    private Employee $clara;
    private User $managerOne;
    private User $managerTwo;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->service = $container->get(SwapService::class);
        $this->em->getConnection()->beginTransaction();

        $data = $this->createRoster();
        $this->anna = $data['anna'];
        $this->clara = $data['clara'];
        $this->bea = $this->createEmployee('Bea', Position::Barista);
        $this->shift = $this->createShift($data['roster'], '2031-02-04', '08:00', '14:00', Position::Barista, 1);
        $this->assignment = new Assignment($this->shift, $this->anna);
        $container->get(AssignmentRepository::class)->save($this->assignment);
        $this->managerOne = $this->saveManager('mgr-one-mails@example.com');
        $this->managerTwo = $this->saveManager('mgr-two-mails@example.com');
        $this->signInAs($this->anna->getUser());
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testAskingMailsTheColleague(): void
    {
        $this->ask($this->bea);

        self::assertSame([[$this->bea->getUser()->getId(), SwapMailEvent::Requested]], $this->pairs());
    }

    public function testAcceptingMailsTheManagers(): void
    {
        $request = $this->ask($this->bea);
        $this->clearQueue();
        $this->signInAs($this->bea->getUser());

        $this->service->accept((int) $request->getId());

        self::assertEqualsCanonicalizing(
            [[$this->managerOne->getId(), SwapMailEvent::Accepted], [$this->managerTwo->getId(), SwapMailEvent::Accepted]],
            $this->pairs(),
        );
    }

    public function testDecliningMailsTheRequester(): void
    {
        $request = $this->ask($this->bea);
        $this->clearQueue();
        $this->signInAs($this->bea->getUser());

        $this->service->decline((int) $request->getId());

        self::assertSame([[$this->anna->getUser()->getId(), SwapMailEvent::Declined]], $this->pairs());
    }

    public function testCancellingAPendingRequestMailsTheColleagueAndAnAcceptedOneAlsoTheManagers(): void
    {
        $pending = $this->ask($this->bea);
        $this->clearQueue();
        $this->service->cancel((int) $pending->getId());
        self::assertSame([[$this->bea->getUser()->getId(), SwapMailEvent::Cancelled]], $this->pairs());

        $accepted = $this->acceptedRequest();
        $this->clearQueue();
        $this->service->cancel((int) $accepted->getId());
        self::assertEqualsCanonicalizing(
            [
                [$this->bea->getUser()->getId(), SwapMailEvent::Cancelled],
                [$this->managerOne->getId(), SwapMailEvent::Cancelled],
                [$this->managerTwo->getId(), SwapMailEvent::Cancelled],
            ],
            $this->pairs(),
        );
    }

    public function testApprovingMailsBothPeopleAndNotTheGenericChangeMails(): void
    {
        $request = $this->acceptedRequest();
        $this->clearQueue();
        $this->signInAs($this->managerOne);

        $this->service->approve((int) $request->getId());

        self::assertEqualsCanonicalizing(
            [[$this->anna->getUser()->getId(), SwapMailEvent::Approved], [$this->bea->getUser()->getId(), SwapMailEvent::Approved]],
            $this->pairs(),
        );
        self::assertSame([], array_filter($this->allQueued(), static fn (object $m): bool => $m instanceof SendRosterChangedMail));
    }

    public function testRejectingMailsBothPeople(): void
    {
        $request = $this->ask($this->bea);
        $this->clearQueue();
        $this->signInAs($this->managerOne);

        $this->service->reject((int) $request->getId());

        self::assertEqualsCanonicalizing(
            [[$this->anna->getUser()->getId(), SwapMailEvent::Rejected], [$this->bea->getUser()->getId(), SwapMailEvent::Rejected]],
            $this->pairs(),
        );
    }

    public function testARequestCancelledBecauseTheShiftChangedMailsBothPeople(): void
    {
        $request = $this->ask($this->bea);
        $id = (int) $request->getId();
        $this->signInAs($this->managerOne);
        self::getContainer()->get(AssignmentService::class)->unassign((int) $this->assignment->getId());
        $this->em->clear();
        $this->clearQueue();
        $this->signInAs($this->bea->getUser());

        try {
            $this->service->accept($id);
            self::fail('The assignment is gone.');
        } catch (\App\Exception\SwapAssignmentChangedException) {
        }

        self::assertEqualsCanonicalizing(
            [[$this->anna->getUser()->getId(), SwapMailEvent::ShiftChanged], [$this->bea->getUser()->getId(), SwapMailEvent::ShiftChanged]],
            $this->pairs(),
        );
    }

    public function testRefusedStepsMailNobody(): void
    {
        $this->ask($this->bea);
        $this->clearQueue();

        try {
            $this->ask($this->clara); // a request is already open
            self::fail('Expected SwapAlreadyOpenException.');
        } catch (SwapAlreadyOpenException) {
        }

        $second = $this->ask2OnAnotherShift();
        $this->clearQueue();
        $this->signInAs($this->clara->getUser());
        try {
            $this->service->accept((int) $second->getId()); // Clara is kitchen, the shift needs a barista
            self::fail('Expected a rule violation.');
        } catch (RuleViolationException) {
        }

        self::assertSame([], $this->pairs());
    }

    private function ask(Employee $target): SwapRequest
    {
        return $this->service->request((int) $this->assignment->getId(), new CreateSwapRequest((int) $target->getId()));
    }

    private function acceptedRequest(): SwapRequest
    {
        $request = $this->ask($this->bea);
        $this->signInAs($this->bea->getUser());
        $this->service->accept((int) $request->getId());
        $this->signInAs($this->anna->getUser());

        return $request;
    }

    /** Anna asks Clara (a kitchen worker) to take another barista shift of hers. */
    private function ask2OnAnotherShift(): SwapRequest
    {
        $shift = $this->createShift($this->shift->getRoster(), '2031-02-06', '08:00', '14:00', Position::Barista, 1);
        $assignment = new Assignment($shift, $this->anna);
        self::getContainer()->get(AssignmentRepository::class)->save($assignment);
        $this->signInAs($this->anna->getUser());

        return $this->service->request((int) $assignment->getId(), new CreateSwapRequest((int) $this->clara->getId()));
    }

    private function saveManager(string $email): User
    {
        $user = new User($email, 'hash', UserRole::Manager);
        self::getContainer()->get(UserRepository::class)->save($user);

        return $user;
    }

    private function signInAs(User $user): void
    {
        self::getContainer()->get(TokenStorageInterface::class)
            ->setToken(new UsernamePasswordToken($user, 'api', $user->getRoles()));
    }

    private function clearQueue(): void
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        $transport->reset();
    }

    /** @return list<object> */
    private function allQueued(): array
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return array_map(static fn ($envelope): object => $envelope->getMessage(), $transport->getSent());
    }

    /** @return list<array{int|null, SwapMailEvent}> recipient user id and event of every queued swap mail */
    private function pairs(): array
    {
        return array_values(array_map(
            static fn (SendSwapMail $m): array => [$m->recipientUserId, $m->event],
            array_filter($this->allQueued(), static fn (object $m): bool => $m instanceof SendSwapMail),
        ));
    }
}
