<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Assignment;
use App\Entity\Employee;
use App\Entity\Roster;
use App\Entity\Shift;
use App\Entity\User;
use App\Enum\ContractType;
use App\Enum\Position;
use App\Enum\UserRole;
use App\Repository\AssignmentRepository;
use App\Repository\EmployeeRepository;
use App\Repository\RosterRepository;
use App\Repository\ShiftRepository;
use App\Repository\UserRepository;
use App\Rule\Violation;
use App\Service\ShiftValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The validator built by the real container: it must find every rule class by
 * itself (they are tagged through the ShiftRule interface), with no configuration.
 */
final class ShiftValidatorContainerTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testTheContainerValidatorFindsTheRulesOnItsOwn(): void
    {
        $container = self::getContainer();
        $roster = new Roster(new \DateTimeImmutable('2031-02-03'));
        $container->get(RosterRepository::class)->save($roster);
        $shift = new Shift(
            $roster,
            new \DateTimeImmutable('2031-02-05'),
            new \DateTimeImmutable('09:00'),
            new \DateTimeImmutable('15:00'),
            Position::Barista,
            1,
        );
        $container->get(ShiftRepository::class)->save($shift);
        $user = new User('anna@example.com', 'hash', UserRole::Employee);
        $container->get(UserRepository::class)->save($user);
        $anna = new Employee($user, 'Anna', Position::Barista, ContractType::PartTime, 20);
        $container->get(EmployeeRepository::class)->save($anna);
        $container->get(AssignmentRepository::class)->save(new Assignment($shift, $anna));

        $violations = $container->get(ShiftValidator::class)->validate($anna, $shift);

        // Anna is already on the only slot: both rules fail, reported together and sorted.
        self::assertSame(
            ['ALREADY_ASSIGNED', 'SHIFT_FULL'],
            array_map(static fn (Violation $violation): string => $violation->code, $violations),
        );
    }
}
