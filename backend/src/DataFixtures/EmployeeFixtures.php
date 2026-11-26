<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Employee;
use App\Entity\User;
use App\Enum\ContractType;
use App\Enum\Position;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/** One demo Employee per demo User; hours follow the suggested defaults in SPEC.md. */
class EmployeeFixtures extends Fixture implements DependentFixtureInterface
{
    private const EMPLOYEES = [
        'manager' => ['Mara Keller', Position::ShiftLead, ContractType::FullTime, 40],
        'anna' => ['Anna', Position::Barista, ContractType::PartTime, 20, [Position::Service]],
        'ben' => ['Ben', Position::Service, ContractType::WorkingStudent, 20],
        'clara' => ['Clara', Position::Kitchen, ContractType::MiniJob, 10],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::EMPLOYEES as $reference => $row) {
            [$name, $position, $contractType, $maxWeeklyHours] = $row;
            $employee = new Employee(
                $this->getReference('user-'.$reference, User::class),
                $name,
                $position,
                $contractType,
                $maxWeeklyHours,
                $row[4] ?? [],
            );
            $manager->persist($employee);
            $this->addReference('employee-'.$reference, $employee);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [UserFixtures::class];
    }
}
