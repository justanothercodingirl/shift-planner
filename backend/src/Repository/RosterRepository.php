<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Roster;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Roster> */
class RosterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Roster::class);
    }

    public function save(Roster $roster): void
    {
        $this->getEntityManager()->persist($roster);
        $this->getEntityManager()->flush();
    }

    public function remove(Roster $roster): void
    {
        $this->getEntityManager()->remove($roster);
        $this->getEntityManager()->flush();
    }
}
