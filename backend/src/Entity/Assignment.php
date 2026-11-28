<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AssignmentRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * An employee working a shift. One employee can be on a shift only once, and
 * deleting a shift deletes its assignments (database cascade).
 */
#[ORM\Entity(repositoryClass: AssignmentRepository::class)]
#[ORM\Table(name: 'assignment')]
#[ORM\UniqueConstraint(name: 'uniq_assignment_shift_employee', columns: ['shift_id', 'employee_id'])]
class Assignment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine)

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Shift::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Shift $shift,
        #[ORM\ManyToOne(targetEntity: Employee::class)]
        #[ORM\JoinColumn(nullable: false)]
        private Employee $employee,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getShift(): Shift
    {
        return $this->shift;
    }

    public function getEmployee(): Employee
    {
        return $this->employee;
    }

    /** Hands the shift over to another employee; the assignment itself (and its id) stays. */
    public function reassignTo(Employee $employee): void
    {
        $this->employee = $employee;
    }
}
