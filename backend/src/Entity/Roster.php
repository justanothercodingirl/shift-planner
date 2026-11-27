<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\RosterStatus;
use App\Repository\RosterRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** One roster per week. Weeks start on Monday (enforced when a roster is created). */
#[ORM\Entity(repositoryClass: RosterRepository::class)]
#[ORM\Table(name: 'roster')]
class Roster
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine)

    #[ORM\Column(length: 20, enumType: RosterStatus::class)]
    private RosterStatus $status = RosterStatus::Draft;

    public function __construct(
        #[ORM\Column(type: Types::DATE_IMMUTABLE, unique: true)]
        private \DateTimeImmutable $weekStart,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWeekStart(): \DateTimeImmutable
    {
        return $this->weekStart;
    }

    public function getStatus(): RosterStatus
    {
        return $this->status;
    }

    public function isPublished(): bool
    {
        return RosterStatus::Published === $this->status;
    }

    /** Moves the roster from draft to published. The checks (not empty, not published twice) are in RosterService. */
    public function publish(): void
    {
        $this->status = RosterStatus::Published;
    }
}
