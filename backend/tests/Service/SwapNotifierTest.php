<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Assignment;
use App\Entity\Employee;
use App\Entity\Roster;
use App\Entity\SwapRequest;
use App\Entity\User;
use App\Enum\Position;
use App\Enum\UserRole;
use App\Mail\SwapMailEvent;
use App\Message\SendSwapMail;
use App\Repository\AssignmentRepository;
use App\Repository\SwapRequestRepository;
use App\Repository\UserRepository;
use App\Service\SwapNotifier;
use App\Tests\Mail\RosterTestData;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class SwapNotifierTest extends KernelTestCase
{
    use RosterTestData;

    private EntityManagerInterface $em;
    private SwapNotifier $notifier;
    private Roster $roster;
    private Employee $anna;
    private Employee $bea;
    private User $managerOne;
    private User $managerTwo;
    private SwapRequest $request;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->notifier = $container->get(SwapNotifier::class);
        $this->em->getConnection()->beginTransaction();

        $data = $this->createRoster();
        $this->roster = $data['roster'];
        $this->anna = $data['anna'];
        $this->bea = $this->createEmployee('Bea', Position::Barista);
        $this->managerOne = $this->saveUser('mgr-one-swap@example.com', UserRole::Manager);
        $this->managerTwo = $this->saveUser('mgr-two-swap@example.com', UserRole::Manager);
        $this->request = $this->swap($this->anna, $this->bea);
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testAskingTellsTheColleague(): void
    {
        $this->notifier->requested($this->request);

        self::assertSame([[$this->bea->getUser()->getId(), SwapMailEvent::Requested]], $this->queuedPairs());
        self::assertSame($this->request->getId(), $this->queued()[0]->swapRequestId);
    }

    public function testAcceptingTellsEveryManager(): void
    {
        $this->notifier->accepted($this->request);

        self::assertEqualsCanonicalizing(
            [
                [$this->managerOne->getId(), SwapMailEvent::Accepted],
                [$this->managerTwo->getId(), SwapMailEvent::Accepted],
            ],
            array_values(array_filter($this->queuedPairs(), fn (array $pair): bool => in_array($pair[0], [$this->managerOne->getId(), $this->managerTwo->getId()], true))),
        );
        self::assertNotContains($this->bea->getUser()->getId(), array_column($this->queuedPairs(), 0));
        self::assertNotContains($this->anna->getUser()->getId(), array_column($this->queuedPairs(), 0));
    }

    public function testDecliningTellsTheRequester(): void
    {
        $this->notifier->declined($this->request);

        self::assertSame([[$this->anna->getUser()->getId(), SwapMailEvent::Declined]], $this->queuedPairs());
    }

    public function testCancellingAPendingRequestTellsOnlyTheColleague(): void
    {
        $this->notifier->cancelled($this->request, wasAccepted: false);

        self::assertSame([[$this->bea->getUser()->getId(), SwapMailEvent::Cancelled]], $this->queuedPairs());
    }

    public function testCancellingAnAcceptedRequestAlsoTellsTheManagers(): void
    {
        $this->notifier->cancelled($this->request, wasAccepted: true);

        $recipients = array_column($this->queuedPairs(), 0);
        self::assertContains($this->bea->getUser()->getId(), $recipients);
        self::assertContains($this->managerOne->getId(), $recipients);
        self::assertContains($this->managerTwo->getId(), $recipients);
        self::assertNotContains($this->anna->getUser()->getId(), $recipients);
        foreach ($this->queuedPairs() as [, $event]) {
            self::assertSame(SwapMailEvent::Cancelled, $event);
        }
    }

    public function testACancellationBecauseTheShiftChangedTellsBothPeople(): void
    {
        $this->notifier->cancelledBecauseShiftChanged($this->request);

        self::assertEqualsCanonicalizing(
            [
                [$this->anna->getUser()->getId(), SwapMailEvent::ShiftChanged],
                [$this->bea->getUser()->getId(), SwapMailEvent::ShiftChanged],
            ],
            $this->queuedPairs(),
        );
    }

    public function testApprovingAndRejectingTellBothPeople(): void
    {
        $this->notifier->approved($this->request);
        $this->notifier->rejected($this->request);

        self::assertEqualsCanonicalizing(
            [
                [$this->anna->getUser()->getId(), SwapMailEvent::Approved],
                [$this->bea->getUser()->getId(), SwapMailEvent::Approved],
                [$this->anna->getUser()->getId(), SwapMailEvent::Rejected],
                [$this->bea->getUser()->getId(), SwapMailEvent::Rejected],
            ],
            $this->queuedPairs(),
        );
    }

    public function testADeactivatedColleagueIsNotMailed(): void
    {
        $this->bea->deactivate();

        $this->notifier->requested($this->request);
        $this->notifier->approved($this->request);

        self::assertSame([[$this->anna->getUser()->getId(), SwapMailEvent::Approved]], $this->queuedPairs());
    }

    private function swap(Employee $requester, Employee $target): SwapRequest
    {
        $shift = $this->createShift($this->roster, '2031-02-04', '08:00', '14:00', Position::Barista, 1);
        $assignment = new Assignment($shift, $requester);
        self::getContainer()->get(AssignmentRepository::class)->save($assignment);
        $request = new SwapRequest($shift, $assignment, $requester, $target, new \DateTimeImmutable());
        self::getContainer()->get(SwapRequestRepository::class)->save($request);

        return $request;
    }

    private function saveUser(string $email, UserRole $role): User
    {
        $user = new User($email, 'hash', $role);
        self::getContainer()->get(UserRepository::class)->save($user);

        return $user;
    }

    /** @return list<SendSwapMail> */
    private function queued(): array
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return array_values(array_filter(
            array_map(static fn ($envelope): object => $envelope->getMessage(), $transport->getSent()),
            static fn (object $message): bool => $message instanceof SendSwapMail,
        ));
    }

    /** @return list<array{int|null, SwapMailEvent}> recipient user id and event of every queued job */
    private function queuedPairs(): array
    {
        return array_map(static fn (SendSwapMail $m): array => [$m->recipientUserId, $m->event], $this->queued());
    }
}
