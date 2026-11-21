<?php

declare(strict_types=1);

namespace App\Tests\DataFixtures;

use App\DataFixtures\RosterFixtures;
use App\Entity\Roster;
use App\Entity\Shift;
use App\Enum\Position;
use App\Enum\RosterStatus;
use App\Repository\RosterRepository;
use App\Repository\ShiftRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RosterFixturesTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private Roster $roster;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();

        (new RosterFixtures())->load($this->em);

        $rosters = $container->get(RosterRepository::class)->findAll();
        self::assertCount(1, $rosters);
        $this->roster = $rosters[0];
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testCreatesOneDraftRosterStartingOnAMonday(): void
    {
        self::assertSame(RosterStatus::Draft, $this->roster->getStatus());
        self::assertSame('1', $this->roster->getWeekStart()->format('N'));
    }

    public function testCreatesFiveShiftsInsideThatWeek(): void
    {
        $shifts = $this->shifts();
        self::assertCount(5, $shifts);

        $weekStart = $this->roster->getWeekStart();
        $weekEnd = $weekStart->modify('+6 days');
        foreach ($shifts as $shift) {
            self::assertGreaterThanOrEqual($weekStart, $shift->getDate());
            self::assertLessThanOrEqual($weekEnd, $shift->getDate());
        }
    }

    public function testIncludesAnOvernightShiftAndTwoShiftsForTwoBaristas(): void
    {
        $shifts = $this->shifts();

        $overnight = array_filter($shifts, static fn (Shift $s): bool => $s->getEndTime() < $s->getStartTime());
        self::assertCount(1, $overnight);
        self::assertSame(Position::Bar, array_values($overnight)[0]->getRequiredPosition());

        $twoBaristas = array_filter(
            $shifts,
            static fn (Shift $s): bool => Position::Barista === $s->getRequiredPosition() && 2 === $s->getHeadcount(),
        );
        self::assertCount(2, $twoBaristas);
    }

    /** @return list<Shift> */
    private function shifts(): array
    {
        return self::getContainer()->get(ShiftRepository::class)->findBy(['roster' => $this->roster]);
    }
}
