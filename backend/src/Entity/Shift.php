<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Position;
use App\Repository\ShiftRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A shift belongs to one roster. An end time earlier than the start time means
 * the shift ends the next day (overnight shifts, see DECISIONS.md).
 * The columns are called start_time / end_time because "end" is reserved in SQL.
 */
#[ORM\Entity(repositoryClass: ShiftRepository::class)]
#[ORM\Table(name: 'shift')]
class Shift
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine)

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Roster::class)]
        #[ORM\JoinColumn(nullable: false)]
        private Roster $roster,
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $date,
        #[ORM\Column(type: Types::TIME_IMMUTABLE)]
        private \DateTimeImmutable $startTime,
        #[ORM\Column(type: Types::TIME_IMMUTABLE)]
        private \DateTimeImmutable $endTime,
        #[ORM\Column(length: 20, enumType: Position::class)]
        private Position $requiredPosition,
        #[ORM\Column]
        private int $headcount,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRoster(): Roster
    {
        return $this->roster;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function getStartTime(): \DateTimeImmutable
    {
        return $this->startTime;
    }

    public function getEndTime(): \DateTimeImmutable
    {
        return $this->endTime;
    }

    public function getRequiredPosition(): Position
    {
        return $this->requiredPosition;
    }

    public function getHeadcount(): int
    {
        return $this->headcount;
    }

    /** The real start moment: the shift's date combined with its start time. */
    public function getStartsAt(): \DateTimeImmutable
    {
        return $this->date->setTime((int) $this->startTime->format('H'), (int) $this->startTime->format('i'));
    }

    /** The real end moment; an end time earlier than the start time means the shift ends the next day. */
    public function getEndsAt(): \DateTimeImmutable
    {
        $endsAt = $this->date->setTime((int) $this->endTime->format('H'), (int) $this->endTime->format('i'));

        return $this->endTime->format('H:i') < $this->startTime->format('H:i') ? $endsAt->modify('+1 day') : $endsAt;
    }

    /** The length of the shift in minutes; an overnight shift runs through midnight. */
    public function getDurationInMinutes(): int
    {
        return intdiv($this->getEndsAt()->getTimestamp() - $this->getStartsAt()->getTimestamp(), 60);
    }

    public function update(
        \DateTimeImmutable $date,
        \DateTimeImmutable $startTime,
        \DateTimeImmutable $endTime,
        Position $requiredPosition,
        int $headcount,
    ): void {
        $this->date = $date;
        $this->startTime = $startTime;
        $this->endTime = $endTime;
        $this->requiredPosition = $requiredPosition;
        $this->headcount = $headcount;
    }
}
