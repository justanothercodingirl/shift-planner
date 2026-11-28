<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Assignment;
use App\Entity\User;
use App\Repository\AssignmentRepository;
use App\Repository\EmployeeRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\ClockInterface;

/** What the signed-in employee works from now on: their own shifts in published rosters. */
class MyShiftsService
{
    public function __construct(
        private readonly AssignmentRepository $assignments,
        private readonly EmployeeRepository $employees,
        private readonly Security $security,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * The signed-in employee's assignments that have not ended, in time order.
     * A login without an employee profile has none.
     *
     * @return list<Assignment>
     */
    public function upcoming(): array
    {
        $user = $this->security->getUser();
        $employee = $user instanceof User ? $this->employees->findOneByUser($user) : null;
        if (null === $employee) {
            return [];
        }

        return $this->assignments->findUpcomingOfEmployee($employee, $this->clock->now());
    }
}
