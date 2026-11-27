<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Employee;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Employee> */
class EmployeeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Employee::class);
    }

    public function save(Employee $employee): void
    {
        $this->getEntityManager()->persist($employee);
        $this->getEntityManager()->flush();
    }

    /** The Employee that belongs to a login, or null when the user has none (a user has at most one). */
    public function findOneByUser(User $user): ?Employee
    {
        return $this->findOneBy(['user' => $user]);
    }

    /** @return list<Employee> */
    public function findAllOrderedByName(): array
    {
        return $this->findBy([], ['name' => 'ASC']);
    }

    /** @return list<Employee> everyone who is not deactivated, by name */
    public function findActiveOrderedByName(): array
    {
        return $this->findBy(['active' => true], ['name' => 'ASC']);
    }

    public function remove(Employee $employee): void
    {
        $this->getEntityManager()->remove($employee);
        $this->getEntityManager()->flush();
    }
}
