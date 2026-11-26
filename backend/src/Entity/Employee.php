<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ContractType;
use App\Enum\Position;
use App\Repository\EmployeeRepository;
use Doctrine\DBAL\Types\Types;
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

    /**
     * Extra positions besides the main one (for example a barista who can also
     * work service). Never contains the main position or a duplicate.
     *
     * @var list<Position>
     */
    #[ORM\Column(type: Types::JSON, enumType: Position::class, options: ['default' => '[]'])]
    private array $additionalPositions;

    /** @param list<Position> $additionalPositions */
    public function __construct(
        #[ORM\OneToOne(targetEntity: User::class, cascade: ['persist'])]
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
        array $additionalPositions = [],
    ) {
        $this->additionalPositions = self::withoutMainAndDuplicates($position, $additionalPositions);
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

    /** @return list<Position> */
    public function getAdditionalPositions(): array
    {
        return $this->additionalPositions;
    }

    /** @return list<Position> the main position first, then the additional ones */
    public function getPositions(): array
    {
        return [$this->position, ...$this->additionalPositions];
    }

    /** True when the position is the main one or one of the additional ones. */
    public function canWorkAs(Position $position): bool
    {
        return in_array($position, $this->getPositions(), true);
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

    /** @param list<Position> $additionalPositions */
    public function update(
        string $name,
        Position $position,
        ContractType $contractType,
        int $maxWeeklyHours,
        array $additionalPositions = [],
    ): void {
        $this->name = $name;
        $this->position = $position;
        $this->contractType = $contractType;
        $this->maxWeeklyHours = $maxWeeklyHours;
        $this->additionalPositions = self::withoutMainAndDuplicates($position, $additionalPositions);
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    /**
     * @param list<Position> $positions
     *
     * @return list<Position> the positions in their order, without the main position and without repeats
     */
    private static function withoutMainAndDuplicates(Position $main, array $positions): array
    {
        $cleaned = [];
        foreach ($positions as $position) {
            if ($position !== $main && !in_array($position, $cleaned, true)) {
                $cleaned[] = $position;
            }
        }

        return $cleaned;
    }
}
