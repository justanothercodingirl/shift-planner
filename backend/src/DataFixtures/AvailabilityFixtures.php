<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Availability;
use App\Entity\Employee;
use App\Entity\Roster;
use App\Enum\AvailabilityType;
use App\Enum\Weekday;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * A few demo availability entries. None of them clashes with the demo
 * assignments: Anna works Monday and Saturday morning, Ben Monday evening,
 * Clara on Tuesday.
 */
class AvailabilityFixtures extends Fixture implements DependentFixtureInterface
{
    /** @var list<array{string, Weekday|null, int|null, string, string, AvailabilityType}> employee, weekday, or day offset from the demo roster's Monday for a one-off entry, from, to, type */
    private const ENTRIES = [
        ['anna', Weekday::Wednesday, null, '08:00', '16:00', AvailabilityType::Unavailable],
        ['anna', Weekday::Saturday, null, '08:00', '14:00', AvailabilityType::Preferred],
        ['ben', null, 3, '00:00', '23:59', AvailabilityType::Unavailable], // one specific Thursday
        ['clara', Weekday::Tuesday, null, '10:00', '18:00', AvailabilityType::Preferred],
    ];

    public function load(ObjectManager $manager): void
    {
        $monday = $this->getReference('roster-demo', Roster::class)->getWeekStart();

        foreach (self::ENTRIES as [$employee, $weekday, $dayOffset, $from, $to, $type]) {
            $manager->persist(new Availability(
                $this->getReference('employee-'.$employee, Employee::class),
                $weekday,
                null === $dayOffset ? null : $monday->modify(sprintf('+%d days', $dayOffset)),
                new \DateTimeImmutable($from),
                new \DateTimeImmutable($to),
                $type,
            ));
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [EmployeeFixtures::class, RosterFixtures::class];
    }
}
