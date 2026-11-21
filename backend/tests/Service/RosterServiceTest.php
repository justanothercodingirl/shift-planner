<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Dto\CreateRosterRequest;
use App\Entity\Shift;
use App\Enum\Position;
use App\Enum\RosterStatus;
use App\Exception\RosterAlreadyExistsException;
use App\Exception\RosterNotFoundException;
use App\Exception\WeekStartNotMondayException;
use App\Repository\ShiftRepository;
use App\Service\RosterService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

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

    private function shift(\App\Entity\Roster $roster, string $date, string $start, string $end): Shift
    {
        return new Shift(
            $roster,
            new \DateTimeImmutable($date),
            new \DateTimeImmutable($start),
            new \DateTimeImmutable($end),
            Position::Barista,
            1,
        );
    }
}
