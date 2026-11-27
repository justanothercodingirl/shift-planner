<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Dto\CreateRosterRequest;
use App\Entity\Assignment;
use App\Entity\Employee;
use App\Entity\Roster;
use App\Entity\Shift;
use App\Entity\User;
use App\Enum\ContractType;
use App\Enum\Position;
use App\Enum\RosterStatus;
use App\Enum\UserRole;
use App\Exception\RosterAlreadyExistsException;
use App\Exception\RosterAlreadyPublishedException;
use App\Exception\RosterHasNoShiftsException;
use App\Exception\RosterNotFoundException;
use App\Exception\WeekStartNotMondayException;
use App\Message\SendRosterPublishedMail;
use App\Repository\AssignmentRepository;
use App\Repository\EmployeeRepository;
use App\Repository\ShiftRepository;
use App\Repository\UserRepository;
use App\Service\RosterService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class RosterServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private RosterService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->service = $container->get(RosterService::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testCreateMakesADraftRosterForTheWeek(): void
    {
        $roster = $this->service->create(new CreateRosterRequest('2031-02-03'));

        self::assertNotNull($roster->getId());
        self::assertSame('2031-02-03', $roster->getWeekStart()->format('Y-m-d'));
        self::assertSame(RosterStatus::Draft, $roster->getStatus());
    }

    public function testWeekStartMustBeAMonday(): void
    {
        $this->expectException(WeekStartNotMondayException::class);

        $this->service->create(new CreateRosterRequest('2031-02-04'));
    }

    public function testOnlyOneRosterPerWeek(): void
    {
        $this->service->create(new CreateRosterRequest('2031-02-03'));

        $this->expectException(RosterAlreadyExistsException::class);
        $this->service->create(new CreateRosterRequest('2031-02-03'));
    }

    public function testListIsNewestWeekFirst(): void
    {
        $this->service->create(new CreateRosterRequest('2031-02-03'));
        $this->service->create(new CreateRosterRequest('2031-02-17'));
        $this->service->create(new CreateRosterRequest('2031-02-10'));

        $weeks = array_map(static fn ($r): string => $r->getWeekStart()->format('Y-m-d'), $this->service->list());

        self::assertSame(['2031-02-17', '2031-02-10', '2031-02-03'], array_slice($weeks, 0, 3));
    }

    public function testGetReturnsTheRosterAndUnknownIdThrows(): void
    {
        $created = $this->service->create(new CreateRosterRequest('2031-02-03'));

        self::assertSame($created->getId(), $this->service->get((int) $created->getId())->getId());

        $this->expectException(RosterNotFoundException::class);
        $this->service->get(999999);
    }

    public function testShiftsOfARosterAreOrderedByDateThenStartTime(): void
    {
        $roster = $this->service->create(new CreateRosterRequest('2031-02-03'));
        $shifts = self::getContainer()->get(ShiftRepository::class);
        $shifts->save($this->shift($roster, '2031-02-04', '10:00', '18:00'));
        $shifts->save($this->shift($roster, '2031-02-03', '16:00', '22:00'));
        $shifts->save($this->shift($roster, '2031-02-03', '08:00', '14:00'));

        $ordered = array_map(
            static fn (Shift $s): string => $s->getDate()->format('m-d').' '.$s->getStartTime()->format('H:i'),
            $this->service->shiftsOf($roster),
        );

        self::assertSame(['02-03 08:00', '02-03 16:00', '02-04 10:00'], $ordered);
    }

    public function testPublishMakesTheRosterPublishedAndSavesIt(): void
    {
        $roster = $this->service->create(new CreateRosterRequest('2031-02-03'));
        $shift = $this->shift($roster, '2031-02-03', '08:00', '14:00');
        self::getContainer()->get(ShiftRepository::class)->save($shift);
        $this->assign($shift, 'Anna');

        $result = $this->service->publish((int) $roster->getId());

        self::assertSame(RosterStatus::Published, $result->roster->getStatus());
        $this->em->clear();
        self::assertSame(RosterStatus::Published, $this->service->get((int) $roster->getId())->getStatus());
    }

    public function testPublishQueuesOneMailJobPerActiveEmployee(): void
    {
        $roster = $this->rosterWithOneShift();
        $this->assign($this->service->shiftsOf($roster)[0], 'Anna');
        $gone = new Employee(new User('gone-pub@example.com', 'hash', UserRole::Employee), 'Gone', Position::Barista, ContractType::PartTime, 20);
        $gone->deactivate();
        self::getContainer()->get(EmployeeRepository::class)->save($gone);

        $this->service->publish((int) $roster->getId());

        $queued = $this->queuedMessages();
        self::assertContains(
            self::getContainer()->get(EmployeeRepository::class)->findOneBy(['name' => 'Anna'])?->getId(),
            array_map(static fn (SendRosterPublishedMail $m): int => $m->employeeId, $queued),
        );
        self::assertNotContains($gone->getId(), array_map(static fn (SendRosterPublishedMail $m): int => $m->employeeId, $queued));
        foreach ($queued as $message) {
            self::assertSame($roster->getId(), $message->rosterId);
        }
    }

    public function testARefusedPublishQueuesNothing(): void
    {
        $roster = $this->service->create(new CreateRosterRequest('2031-02-03'));

        try {
            $this->service->publish((int) $roster->getId());
        } catch (RosterHasNoShiftsException) {
        }

        self::assertSame([], $this->queuedMessages());
    }

    public function testPublishingTwiceIsRefused(): void
    {
        $roster = $this->rosterWithOneShift();
        $this->service->publish((int) $roster->getId());

        $this->expectException(RosterAlreadyPublishedException::class);
        $this->service->publish((int) $roster->getId());
    }

    public function testAnEmptyRosterCannotBePublished(): void
    {
        $roster = $this->service->create(new CreateRosterRequest('2031-02-03'));

        try {
            $this->service->publish((int) $roster->getId());
            self::fail('Expected RosterHasNoShiftsException.');
        } catch (RosterHasNoShiftsException) {
        }

        self::assertSame(RosterStatus::Draft, $roster->getStatus());
    }

    public function testUnknownRosterCannotBePublished(): void
    {
        $this->expectException(RosterNotFoundException::class);

        $this->service->publish(999999);
    }

    public function testUnderstaffedShiftsAreAWarningAndPublishingStillHappens(): void
    {
        $roster = $this->service->create(new CreateRosterRequest('2031-02-03'));
        $shifts = self::getContainer()->get(ShiftRepository::class);
        $twoPeople = $this->shift($roster, '2031-02-03', '08:00', '14:00', 2);
        $shifts->save($twoPeople);
        $this->assign($twoPeople, 'Anna');

        $result = $this->service->publish((int) $roster->getId());

        self::assertSame(RosterStatus::Published, $result->roster->getStatus());
        self::assertSame(
            [['code' => 'SHIFT_UNDERSTAFFED', 'reason' => 'Mon 3 Feb 08:00 to 14:00, barista: 1 of 2 filled']],
            $result->warnings,
        );
    }

    public function testAShiftWithNobodyIsReportedAsZeroFilled(): void
    {
        $roster = $this->rosterWithOneShift();

        $result = $this->service->publish((int) $roster->getId());

        self::assertSame('Mon 3 Feb 09:00 to 15:00, barista: 0 of 1 filled', $result->warnings[0]['reason']);
    }

    public function testFullyStaffedShiftsGiveNoWarnings(): void
    {
        $roster = $this->service->create(new CreateRosterRequest('2031-02-03'));
        $shift = $this->shift($roster, '2031-02-03', '08:00', '14:00', 2);
        self::getContainer()->get(ShiftRepository::class)->save($shift);
        $this->assign($shift, 'Anna');
        $this->assign($shift, 'Ben');

        self::assertSame([], $this->service->publish((int) $roster->getId())->warnings);
    }

    public function testEveryUnderstaffedShiftGetsItsOwnWarningInShiftOrder(): void
    {
        $roster = $this->service->create(new CreateRosterRequest('2031-02-03'));
        $shifts = self::getContainer()->get(ShiftRepository::class);
        $shifts->save($this->shift($roster, '2031-02-04', '10:00', '18:00'));
        $shifts->save($this->shift($roster, '2031-02-03', '08:00', '14:00'));

        $warnings = $this->service->publish((int) $roster->getId())->warnings;

        self::assertSame(
            ['Mon 3 Feb 08:00 to 14:00, barista: 0 of 1 filled', 'Tue 4 Feb 10:00 to 18:00, barista: 0 of 1 filled'],
            array_column($warnings, 'reason'),
        );
    }

    /** @return list<SendRosterPublishedMail> */
    private function queuedMessages(): array
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return array_map(static fn ($envelope): SendRosterPublishedMail => $envelope->getMessage(), $transport->getSent());
    }

    private function rosterWithOneShift(): Roster
    {
        $roster = $this->service->create(new CreateRosterRequest('2031-02-03'));
        self::getContainer()->get(ShiftRepository::class)->save($this->shift($roster, '2031-02-03', '09:00', '15:00'));

        return $roster;
    }

    private function assign(Shift $shift, string $name): void
    {
        $user = new User(strtolower($name).'-pub@example.com', 'hash', UserRole::Employee);
        self::getContainer()->get(UserRepository::class)->save($user);
        $employee = new Employee($user, $name, Position::Barista, ContractType::PartTime, 20);
        self::getContainer()->get(EmployeeRepository::class)->save($employee);
        self::getContainer()->get(AssignmentRepository::class)->save(new Assignment($shift, $employee));
    }

    private function shift(Roster $roster, string $date, string $start, string $end, int $headcount = 1): Shift
    {
        return new Shift(
            $roster,
            new \DateTimeImmutable($date),
            new \DateTimeImmutable($start),
            new \DateTimeImmutable($end),
            Position::Barista,
            $headcount,
        );
    }
}
