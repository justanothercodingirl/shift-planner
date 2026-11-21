<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Roster;
use App\Entity\Shift;
use App\Enum\Position;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/** One draft roster for next week, so the week view has something to show. */
class RosterFixtures extends Fixture
{
    /** @var list<array{int, string, string, Position, int}> day offset from Monday, start, end, position, headcount */
    private const SHIFTS = [
        [0, '08:00', '14:00', Position::Barista, 2],
        [0, '16:00', '22:00', Position::Service, 1],
        [1, '10:00', '18:00', Position::Kitchen, 1],
        [4, '18:00', '02:00', Position::Bar, 1], // overnight: ends the next day
        [5, '08:00', '14:00', Position::Barista, 2],
    ];

    public function load(ObjectManager $manager): void
    {
        $weekStart = (new \DateTimeImmutable('today'))->modify('monday this week')->modify('+1 week');
        $roster = new Roster($weekStart);
        $manager->persist($roster);

        foreach (self::SHIFTS as [$dayOffset, $start, $end, $position, $headcount]) {
            $manager->persist(new Shift(
                $roster,
                $weekStart->modify(sprintf('+%d days', $dayOffset)),
                new \DateTimeImmutable($start),
                new \DateTimeImmutable($end),
                $position,
                $headcount,
            ));
        }

        $manager->flush();
    }
}
