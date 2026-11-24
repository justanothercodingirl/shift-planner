<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Assignment;
use App\Entity\Employee;
use App\Entity\Roster;
use App\Entity\Shift;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Assignment> */
class AssignmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Assignment::class);
    }

    public function save(Assignment $assignment): void
    {
        $this->getEntityManager()->persist($assignment);
        $this->getEntityManager()->flush();
    }

    public function remove(Assignment $assignment): void
    {
        $this->getEntityManager()->remove($assignment);
        $this->getEntityManager()->flush();
    }

    public function isAssigned(Shift $shift, Employee $employee): bool
    {
        return null !== $this->findOneBy(['shift' => $shift, 'employee' => $employee]);
    }

    public function countForShift(Shift $shift): int
    {
        return $this->count(['shift' => $shift]);
    }

    /** @return list<Assignment> */
    public function findByShift(Shift $shift): array
    {
        return $this->createQueryBuilder('a')
            ->addSelect('e')
            ->join('a.employee', 'e')
            ->where('a.shift = :shift')
            ->setParameter('shift', $shift)
            ->orderBy('e.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Every shift the employee is assigned to whose date is inside the range
     * (both ends included), by date and start time. Looks across roster weeks:
     * it never filters by roster.
     *
     * @return list<Shift>
     */
    public function findShiftsOfEmployeeBetweenDates(
        Employee $employee,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('s')
            ->from(Shift::class, 's')
            ->join(Assignment::class, 'a', 'WITH', 'a.shift = s')
            ->where('a.employee = :employee')
            ->andWhere('s.date >= :from')
            ->andWhere('s.date <= :to')
            ->setParameter('employee', $employee)
            ->setParameter('from', $from, Types::DATE_IMMUTABLE)
            ->setParameter('to', $to, Types::DATE_IMMUTABLE)
            ->orderBy('s.date', 'ASC')
            ->addOrderBy('s.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Assignment> all assignments of all shifts in the roster, in one query */
    public function findByRoster(Roster $roster): array
    {
        return $this->createQueryBuilder('a')
            ->addSelect('e', 's')
            ->join('a.employee', 'e')
            ->join('a.shift', 's')
            ->where('s.roster = :roster')
            ->setParameter('roster', $roster)
            ->orderBy('e.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
