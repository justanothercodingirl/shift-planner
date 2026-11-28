<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Assignment;
use App\Entity\Roster;
use App\Entity\Shift;
use App\Enum\Position;
use App\Message\SendRosterChangedMail;
use App\Repository\AssignmentRepository;
use App\Repository\RosterRepository;
use App\Service\RosterChangeNotifier;
use App\Tests\Mail\RosterTestData;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class RosterChangeNotifierTest extends KernelTestCase
{
    use RosterTestData;

    private EntityManagerInterface $em;
    private RosterChangeNotifier $notifier;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->notifier = $container->get(RosterChangeNotifier::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testAssigningSomeoneTellsThem(): void
    {
        $data = $this->createRoster();
        $shift = $this->createShift($data['roster'], '2031-02-05', '10:00', '16:00', Position::Kitchen, 1);
        $assignment = $this->saveAssignment($shift, $data['clara']);

        $this->notifier->assigned($assignment);

        self::assertSame(
            [[$data['clara']->getId(), 'You were added to Wed 5 Feb 10:00 to 16:00 (kitchen).']],
            $this->queuedPairs(),
        );
        self::assertSame($data['roster']->getId(), $this->queued()[0]->rosterId);
    }

    public function testTakingSomeoneOffTellsThem(): void
    {
        $data = $this->createRoster();
        $shift = $this->createShift($data['roster'], '2031-02-05', '10:00', '16:00', Position::Kitchen, 1);
        $assignment = $this->saveAssignment($shift, $data['clara']);

        $this->notifier->unassigned($assignment);

        self::assertSame(
            [[$data['clara']->getId(), 'You were taken off Wed 5 Feb 10:00 to 16:00 (kitchen).']],
            $this->queuedPairs(),
        );
    }

    public function testAChangedShiftTellsEveryoneOnIt(): void
    {
        $data = $this->createRoster();
        $shift = $this->createShift($data['roster'], '2031-02-05', '10:00', '16:00', Position::Kitchen, 2);
        $assignments = [$this->saveAssignment($shift, $data['clara']), $this->saveAssignment($shift, $data['anna'])];

        $this->notifier->shiftChanged($shift, 'Wed 5 Feb 09:00 to 15:00 (kitchen)', 'Wed 5 Feb 10:00 to 16:00 (kitchen)', $assignments);

        $sentence = 'Your shift Wed 5 Feb 09:00 to 15:00 (kitchen) changed to Wed 5 Feb 10:00 to 16:00 (kitchen).';
        self::assertEqualsCanonicalizing(
            [[$data['clara']->getId(), $sentence], [$data['anna']->getId(), $sentence]],
            $this->queuedPairs(),
        );
    }

    public function testNothingIsSentWhenTheShiftDidNotReallyChange(): void
    {
        $data = $this->createRoster();
        $shift = $this->createShift($data['roster'], '2031-02-05', '10:00', '16:00', Position::Kitchen, 1);
        $assignments = [$this->saveAssignment($shift, $data['clara'])];

        $this->notifier->shiftChanged($shift, 'Wed 5 Feb 10:00 to 16:00 (kitchen)', 'Wed 5 Feb 10:00 to 16:00 (kitchen)', $assignments);

        self::assertSame([], $this->queued());
    }

    public function testADeletedShiftTellsEveryoneOnIt(): void
    {
        $data = $this->createRoster();
        $shift = $this->createShift($data['roster'], '2031-02-05', '10:00', '16:00', Position::Kitchen, 1);
        $assignments = [$this->saveAssignment($shift, $data['clara'])];

        $this->notifier->shiftDeleted($shift, 'Wed 5 Feb 10:00 to 16:00 (kitchen)', $assignments);

        self::assertSame(
            [[$data['clara']->getId(), 'Your shift Wed 5 Feb 10:00 to 16:00 (kitchen) was cancelled.']],
            $this->queuedPairs(),
        );
    }

    public function testNobodyIsToldAboutADraftRoster(): void
    {
        $this->createRoster();
        $draft = new Roster(new \DateTimeImmutable('2031-03-03'));
        self::getContainer()->get(RosterRepository::class)->save($draft);
        $clara = $this->createEmployee('Dora', Position::Kitchen);
        $shift = $this->createShift($draft, '2031-03-05', '10:00', '16:00', Position::Kitchen, 1);
        $assignment = $this->saveAssignment($shift, $clara);

        $this->notifier->assigned($assignment);
        $this->notifier->unassigned($assignment);
        $this->notifier->shiftChanged($shift, 'before', 'after', [$assignment]);
        $this->notifier->shiftDeleted($shift, 'gone', [$assignment]);

        self::assertSame([], $this->queued());
    }

    public function testADeactivatedEmployeeIsNotTold(): void
    {
        $data = $this->createRoster();
        $gone = $this->createEmployee('Gone', Position::Kitchen, active: false);
        $shift = $this->createShift($data['roster'], '2031-02-05', '10:00', '16:00', Position::Kitchen, 1);
        $assignment = $this->saveAssignment($shift, $gone);

        $this->notifier->assigned($assignment);
        $this->notifier->shiftDeleted($shift, 'gone', [$assignment]);

        self::assertSame([], $this->queued());
    }

    private function saveAssignment(Shift $shift, \App\Entity\Employee $employee): Assignment
    {
        $assignment = new Assignment($shift, $employee);
        self::getContainer()->get(AssignmentRepository::class)->save($assignment);

        return $assignment;
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

    /** @return list<array{int|null, string}> employee id and sentence of every queued job */
    private function queuedPairs(): array
    {
        return array_map(
            static fn (SendRosterChangedMail $m): array => [$m->employeeId, $m->description],
            $this->queued(),
        );
    }
}
