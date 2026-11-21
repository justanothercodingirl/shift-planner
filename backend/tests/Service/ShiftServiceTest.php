<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Dto\CreateRosterRequest;
use App\Dto\ShiftRequest;
use App\Entity\Roster;
use App\Enum\Position;
use App\Exception\InvalidShiftTimesException;
use App\Exception\RosterNotFoundException;
use App\Exception\ShiftNotFoundException;
use App\Exception\ShiftOutsideRosterWeekException;
use App\Service\RosterService;
use App\Service\ShiftService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ShiftServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ShiftService $service;
    private Roster $roster;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->service = $container->get(ShiftService::class);
        $this->em->getConnection()->beginTransaction();

        // Week of Monday 2031-02-03 to Sunday 2031-02-09.
        $this->roster = $container->get(RosterService::class)->create(new CreateRosterRequest('2031-02-03'));
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testCreateStoresTheShiftInItsRoster(): void
    {
        $shift = $this->service->create((int) $this->roster->getId(), $this->request());

        self::assertNotNull($shift->getId());
        self::assertSame($this->roster, $shift->getRoster());
        self::assertSame('2031-02-05', $shift->getDate()->format('Y-m-d'));
        self::assertSame('09:00', $shift->getStartTime()->format('H:i'));
        self::assertSame('15:00', $shift->getEndTime()->format('H:i'));
        self::assertSame(Position::Barista, $shift->getRequiredPosition());
        self::assertSame(2, $shift->getHeadcount());
    }

    public function testFirstAndLastDayOfTheWeekAreAllowed(): void
    {
        $monday = $this->service->create((int) $this->roster->getId(), $this->request(date: '2031-02-03'));
        $sunday = $this->service->create((int) $this->roster->getId(), $this->request(date: '2031-02-09'));

        self::assertSame('2031-02-03', $monday->getDate()->format('Y-m-d'));
        self::assertSame('2031-02-09', $sunday->getDate()->format('Y-m-d'));
    }

    public function testDayBeforeTheWeekIsRejected(): void
    {
        $this->expectException(ShiftOutsideRosterWeekException::class);

        $this->service->create((int) $this->roster->getId(), $this->request(date: '2031-02-02'));
    }

    public function testDayAfterTheWeekIsRejected(): void
    {
        $this->expectException(ShiftOutsideRosterWeekException::class);

        $this->service->create((int) $this->roster->getId(), $this->request(date: '2031-02-10'));
    }

    public function testEqualStartAndEndAreRejected(): void
    {
        $this->expectException(InvalidShiftTimesException::class);

        $this->service->create((int) $this->roster->getId(), $this->request(start: '08:00', end: '08:00'));
    }

    public function testOvernightShiftIsAccepted(): void
    {
        $shift = $this->service->create((int) $this->roster->getId(), $this->request(start: '18:00', end: '02:00'));

        self::assertSame('18:00', $shift->getStartTime()->format('H:i'));
        self::assertSame('02:00', $shift->getEndTime()->format('H:i'));
    }

    public function testUnknownRosterThrowsNotFound(): void
    {
        $this->expectException(RosterNotFoundException::class);

        $this->service->create(999999, $this->request());
    }

    public function testUpdateChangesTheFields(): void
    {
        $shift = $this->service->create((int) $this->roster->getId(), $this->request());

        $updated = $this->service->update(
            (int) $shift->getId(),
            $this->request(date: '2031-02-06', start: '10:00', end: '18:00', position: Position::Kitchen, headcount: 1),
        );

        self::assertSame('2031-02-06', $updated->getDate()->format('Y-m-d'));
        self::assertSame('10:00', $updated->getStartTime()->format('H:i'));
        self::assertSame('18:00', $updated->getEndTime()->format('H:i'));
        self::assertSame(Position::Kitchen, $updated->getRequiredPosition());
        self::assertSame(1, $updated->getHeadcount());
        self::assertSame($this->roster, $updated->getRoster());
    }

    public function testUpdateKeepsTheSameRules(): void
    {
        $shift = $this->service->create((int) $this->roster->getId(), $this->request());

        $this->expectException(ShiftOutsideRosterWeekException::class);
        $this->service->update((int) $shift->getId(), $this->request(date: '2031-02-12'));
    }

    public function testUpdateOfUnknownShiftThrowsNotFound(): void
    {
        $this->expectException(ShiftNotFoundException::class);

        $this->service->update(999999, $this->request());
    }

    public function testDeleteRemovesTheShift(): void
    {
        $shift = $this->service->create((int) $this->roster->getId(), $this->request());
        $id = (int) $shift->getId();

        $this->service->delete($id);

        $this->expectException(ShiftNotFoundException::class);
        $this->service->get($id);
    }

    public function testDeleteOfUnknownShiftThrowsNotFound(): void
    {
        $this->expectException(ShiftNotFoundException::class);

        $this->service->delete(999999);
    }

    private function request(
        string $date = '2031-02-05',
        string $start = '09:00',
        string $end = '15:00',
        Position $position = Position::Barista,
        int $headcount = 2,
    ): ShiftRequest {
        return new ShiftRequest($date, $start, $end, $position, $headcount);
    }
}
