<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ContractType;
use App\Enum\Position;
use App\Repository\EmployeeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmployeeRepository::class)]
#[ORM\Table(name: 'employee')]
class Employee
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine)

    #[ORM\Column]
    private bool $active = true;

    public function __construct(
        #[ORM\OneToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(nullable: false, unique: true)]
        private User $user,
        #[ORM\Column(length: 100)]
        private string $name,
        #[ORM\Column(length: 20, enumType: Position::class)]
        private Position $position,
        #[ORM\Column(length: 20, enumType: ContractType::class)]
        private ContractType $contractType,
        #[ORM\Column]
        private int $maxWeeklyHours,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPosition(): Position
    {
        return $this->position;
    }

    public function getContractType(): ContractType
    {
        return $this->contractType;
    }

    public function getMaxWeeklyHours(): int
    {
        return $this->maxWeeklyHours;
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
