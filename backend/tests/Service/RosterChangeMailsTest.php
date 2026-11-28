<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Dto\ShiftRequest;
use App\Entity\Roster;
use App\Enum\Position;
use App\Message\SendRosterChangedMail;
use App\Repository\AssignmentRepository;
use App\Repository\RosterRepository;
use App\Service\AssignmentService;
use App\Service\ShiftService;
use App\Tests\Mail\RosterTestData;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/** The real services queue a change mail at the right moments (and only for published rosters). */
final class RosterChangeMailsTest extends KernelTestCase
{
    use RosterTestData;

    private EntityManagerInterface $em;
    private AssignmentService $assignments;
    private ShiftService $shifts;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->assignments = $container->get(AssignmentService::class);
        $this->shifts = $container->get(ShiftService::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testAssigningInAPublishedRosterTellsThePerson(): void
    {
        $data = $this->createRoster();
        $shift = $this->createShift($data['roster'], '2031-02-06', '09:00', '15:00', Position::Kitchen, 1);

        $this->assignments->assign((int) $shift->getId(), (int) $data['clara']->getId());

        self::assertSame(
            [[$data['clara']->getId(), 'You were added to Thu 6 Feb 09:00 to 15:00 (kitchen).']],
            $this->queuedPairs(),
        );
    }

    public function testAssigningInADraftRosterTellsNobody(): void
    {
        $dora = $this->createEmployee('Dora', Position::Kitchen);
        $shift = $this->createShift($this->draftRoster(), '2031-03-06', '09:00', '15:00', Position::Kitchen, 1);

        $this->assignments->assign((int) $shift->getId(), (int) $dora->getId());

        self::assertSame([], $this->queued());
    }

    public function testRemovingSomeoneFromAPublishedRosterTellsThePerson(): void
    {
        $data = $this->createRoster();
        $shift = $this->createShift($data['roster'], '2031-02-06', '09:00', '15:00', Position::Kitchen, 1);
        $assignment = $this->assignments->assign((int) $shift->getId(), (int) $data['clara']->getId());
        $this->clearQueue();

        $this->assignments->unassign((int) $assignment->getId());

        self::assertSame(
            [[$data['clara']->getId(), 'You were taken off Thu 6 Feb 09:00 to 15:00 (kitchen).']],
            $this->queuedPairs(),
        );
    }

    public function testRemovingSomeoneFromADraftRosterTellsNobody(): void
    {
        $dora = $this->createEmployee('Dora', Position::Kitchen);
        $shift = $this->createShift($this->draftRoster(), '2031-03-06', '09:00', '15:00', Position::Kitchen, 1);
        $assignment = $this->assignments->assign((int) $shift->getId(), (int) $dora->getId());

        $this->assignments->unassign((int) $assignment->getId());

        self::assertSame([], $this->queued());
    }

    public function testMovingAShiftTellsEveryoneOnIt(): void
    {
        $data = $this->createRoster();
        $shift = $this->createShift($data['roster'], '2031-02-06', '09:00', '15:00', Position::Kitchen, 1);
        $this->assign($shift, $data['clara']);

        $this->shifts->update((int) $shift->getId(), new ShiftRequest('2031-02-06', '10:00', '16:00', Position::Kitchen, 1));

        self::assertSame(
            [[$data['clara']->getId(), 'Your shift Thu 6 Feb 09:00 to 15:00 (kitchen) changed to Thu 6 Feb 10:00 to 16:00 (kitchen).']],
            $this->queuedPairs(),
        );
    }

    public function testChangingTheDayOrThePositionAlsoCounts(): void
    {
        $data = $this->createRoster();
        $shift = $this->createShift($data['roster'], '2031-02-06', '09:00', '15:00', Position::Kitchen, 1);
        $this->assign($shift, $data['clara']);

        $this->shifts->update((int) $shift->getId(), new ShiftRequest('2031-02-07', '09:00', '15:00', Position::Bar, 1));

        self::assertSame(
            [[$data['clara']->getId(), 'Your shift Thu 6 Feb 09:00 to 15:00 (kitchen) changed to Fri 7 Feb 09:00 to 15:00 (bar).']],
            $this->queuedPairs(),
        );
    }

    public function testChangingOnlyTheHeadcountTellsNobody(): void
    {
        $data = $this->createRoster();
        $shift = $this->createShift($data['roster'], '2031-02-06', '09:00', '15:00', Position::Kitchen, 1);
        $this->assign($shift, $data['clara']);

        $this->shifts->update((int) $shift->getId(), new ShiftRequest('2031-02-06', '09:00', '15:00', Position::Kitchen, 3));

        self::assertSame([], $this->queued());
    }

    public function testMovingAShiftInADraftRosterTellsNobody(): void
    {
        $dora = $this->createEmployee('Dora', Position::Kitchen);
        $shift = $this->createShift($this->draftRoster(), '2031-03-06', '09:00', '15:00', Position::Kitchen, 1);
        $this->assign($shift, $dora);

        $this->shifts->update((int) $shift->getId(), new ShiftRequest('2031-03-06', '10:00', '16:00', Position::Kitchen, 1));

        self::assertSame([], $this->queued());
    }

    public function testDeletingAShiftTellsEveryoneOnItAndStillRemovesIt(): void
    {
        $data = $this->createRoster();
        $shift = $this->createShift($data['roster'], '2031-02-06', '09:00', '15:00', Position::Kitchen, 1);
        $this->assign($shift, $data['clara']);
        $id = (int) $shift->getId();

        $this->shifts->delete($id);

        self::assertSame(
            [[$data['clara']->getId(), 'Your shift Thu 6 Feb 09:00 to 15:00 (kitchen) was cancelled.']],
            $this->queuedPairs(),
        );
        self::assertSame([], self::getContainer()->get(AssignmentRepository::class)->findBy(['employee' => $data['clara']]));
    }

    public function testCreatingAnEmptyShiftTellsNobody(): void
    {
        $data = $this->createRoster();

        $this->shifts->create((int) $data['roster']->getId(), new ShiftRequest('2031-02-06', '09:00', '15:00', Position::Kitchen, 1));

        self::assertSame([], $this->queued());
    }

    private function draftRoster(): Roster
    {
        $draft = new Roster(new \DateTimeImmutable('2031-03-03'));
        self::getContainer()->get(RosterRepository::class)->save($draft);

        return $draft;
    }

    private function clearQueue(): void
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        $transport->reset();
    }

    /** @return list<SendRosterChangedMail> */
    private function queued(): array
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return array_values(array_filter(
            array_map(static fn ($envelope): object => $envelope->getMessage(), $transport->getSent()),
            static fn (object $message): bool => $message instanceof SendRosterChangedMail,
        ));
    }

    /** @return list<array{int|null, string}> */
    private function queuedPairs(): array
    {
        return array_map(
            static fn (SendRosterChangedMail $m): array => [$m->employeeId, $m->description],
            $this->queued(),
        );
    }
}
