<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Employee;
use App\Entity\User;
use App\Repository\EmployeeRepository;
use Symfony\Bundle\SecurityBundle\Security;

/** The people an employee can ask to take over a shift: every other active employee. */
class ColleagueService
{
    public function __construct(
        private readonly EmployeeRepository $employees,
        private readonly Security $security,
    ) {
    }

    /**
     * Every active employee except the signed-in one, by name. A login without an
     * employee profile (a manager who does not work shifts) sees everyone active.
     *
     * @return list<Employee>
     */
    public function list(): array
    {
        $user = $this->security->getUser();
        $me = $user instanceof User ? $this->employees->findOneByUser($user) : null;

        return array_values(array_filter(
            $this->employees->findActiveOrderedByName(),
            static fn (Employee $employee): bool => null === $me || $employee->getId() !== $me->getId(),
        ));
    }
}
