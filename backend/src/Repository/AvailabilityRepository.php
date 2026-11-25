<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Availability;
use App\Entity\Employee;
use App\Enum\AvailabilityType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Availability> */
class AvailabilityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Availability::class);
    }

    public function save(Availability $availability): void
    {
        $this->getEntityManager()->persist($availability);
        $this->getEntityManager()->flush();
    }

    public function remove(Availability $availability): void
    {
        $this->getEntityManager()->remove($availability);
        $this->getEntityManager()->flush();
    }

    /**
     * All entries of one employee: weekly entries first (Monday to Sunday), then
     * one-off entries by date, each by start time.
     *
     * @return list<Availability>
     */
    public function findByEmployee(Employee $employee): array
    {
        $entries = $this->findBy(['employee' => $employee]);
        usort($entries, static fn (Availability $a, Availability $b): int => [
            null === $a->getWeekday() ? 1 : 0,
            $a->getWeekday()?->isoNumber() ?? 0,
            $a->getDate()?->format('Y-m-d') ?? '',
            $a->getStartTime()->format('H:i'),
        ] <=> [
            null === $b->getWeekday() ? 1 : 0,
            $b->getWeekday()?->isoNumber() ?? 0,
            $b->getDate()?->format('Y-m-d') ?? '',
            $b->getStartTime()->format('H:i'),
        ]);

        return $entries;
    }

    /**
     * Only the entries that block an assignment, in the same order as findByEmployee().
     *
     * @return list<Availability>
     */
    public function findUnavailableByEmployee(Employee $employee): array
    {
        return array_values(array_filter(
            $this->findByEmployee($employee),
            static fn (Availability $a): bool => AvailabilityType::Unavailable === $a->getType(),
        ));
    }
}
