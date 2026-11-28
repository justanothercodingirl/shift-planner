<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Assignment;
use App\Entity\Employee;
use App\Entity\SwapRequest;
use App\Enum\SwapStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SwapRequest> */
class SwapRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SwapRequest::class);
    }

    public function save(SwapRequest $request): void
    {
        $this->getEntityManager()->persist($request);
        $this->getEntityManager()->flush();
    }

    /** The request for this assignment that is still waiting for someone (pending or accepted), if any. */
    public function findOpenForAssignment(Assignment $assignment): ?SwapRequest
    {
        return $this->createQueryBuilder('r')
            ->where('r.assignment = :assignment')
            ->andWhere('r.status IN (:open)')
            ->setParameter('assignment', $assignment)
            ->setParameter('open', [SwapStatus::Pending->value, SwapStatus::Accepted->value], ArrayParameterType::STRING)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * The requests an employee made or received, newest first.
     *
     * @return list<SwapRequest>
     */
    public function findVisibleTo(Employee $employee): array
    {
        return array_values($this->newestFirst()
            ->where('r.requester = :employee OR r.target = :employee')
            ->setParameter('employee', $employee)
            ->getQuery()
            ->getResult());
    }

    /**
     * Every request, newest first, optionally only those in one status.
     *
     * @return list<SwapRequest>
     */
    public function findAllNewestFirst(?SwapStatus $status = null): array
    {
        $query = $this->newestFirst();
        if (null !== $status) {
            $query->where('r.status = :status')->setParameter('status', $status);
        }

        return array_values($query->getQuery()->getResult());
    }

    private function newestFirst(): \Doctrine\ORM\QueryBuilder
    {
        return $this->createQueryBuilder('r')
            ->addSelect('s', 'q', 't')
            ->join('r.shift', 's')
            ->join('r.requester', 'q')
            ->join('r.target', 't')
            ->orderBy('r.createdAt', 'DESC')
            ->addOrderBy('r.id', 'DESC');
    }
}
