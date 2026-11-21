<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Shift;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Shift> */
class ShiftRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Shift::class);
    }

    public function save(Shift $shift): void
    {
        $this->getEntityManager()->persist($shift);
        $this->getEntityManager()->flush();
    }

    public function remove(Shift $shift): void
    {
        $this->getEntityManager()->remove($shift);
        $this->getEntityManager()->flush();
    }
}
