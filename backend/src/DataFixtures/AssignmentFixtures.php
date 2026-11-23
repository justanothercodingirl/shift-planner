<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Assignment;
use App\Entity\Employee;
use App\Entity\Shift;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * A few demo assignments, chosen so that every future rule would accept them
 * (matching positions, no overlaps, within weekly hours). They are written
 * directly and do not go through the rule checks.
 */
class AssignmentFixtures extends Fixture implements DependentFixtureInterface
{
    /** @var list<array{string, int}> employee reference, shift index in RosterFixtures */
    private const ASSIGNMENTS = [
        ['anna', 0], // Monday morning, barista (1 of 2 slots)
        ['anna', 4], // Saturday morning, barista (1 of 2 slots)
        ['ben', 1],  // Monday evening, service
        ['clara', 2], // Tuesday, kitchen
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::ASSIGNMENTS as [$employee, $shiftIndex]) {
            $manager->persist(new Assignment(
                $this->getReference('shift-'.$shiftIndex, Shift::class),
                $this->getReference('employee-'.$employee, Employee::class),
            ));
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [EmployeeFixtures::class, RosterFixtures::class];
    }
}
