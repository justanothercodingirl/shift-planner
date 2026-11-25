<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AvailabilityType;
use App\Enum\Weekday;
use App\Repository\AvailabilityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A window in which an employee cannot (or would prefer to) work. Exactly one of
 * weekday (repeats every week) and date (applies once) is set. The window is a
 * time of day, not a shift. The columns are called start_time / end_time because
 * "from" is reserved in SQL.
 */
#[ORM\Entity(repositoryClass: AvailabilityRepository::class)]
#[ORM\Table(name: 'availability')]
class Availability
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine)

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Employee::class)]
        #[ORM\JoinColumn(nullable: false)]
        private Employee $employee,
        #[ORM\Column(length: 10, nullable: true, enumType: Weekday::class)]
        private ?Weekday $weekday,
        #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $date,
        #[ORM\Column(type: Types::TIME_IMMUTABLE)]
        private \DateTimeImmutable $startTime,
        #[ORM\Column(type: Types::TIME_IMMUTABLE)]
        private \DateTimeImmutable $endTime,
        #[ORM\Column(length: 20, enumType: AvailabilityType::class)]
        private AvailabilityType $type,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmployee(): Employee
    {
        return $this->employee;
    }

    public function getWeekday(): ?Weekday
    {
        return $this->weekday;
    }

    public function getDate(): ?\DateTimeImmutable
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

    public function getType(): AvailabilityType
    {
        return $this->type;
    }

    /** True for a weekly entry on that date's weekday, or a one-off entry on exactly that date. */
    public function appliesOn(\DateTimeImmutable $day): bool
    {
        if (null !== $this->weekday) {
            return Weekday::fromDate($day) === $this->weekday;
        }

        return null !== $this->date && $this->date->format('Y-m-d') === $day->format('Y-m-d');
    }

    /** The start of the window on the given date. */
    public function getStartOn(\DateTimeImmutable $day): \DateTimeImmutable
    {
        return $day->setTime((int) $this->startTime->format('H'), (int) $this->startTime->format('i'));
    }

    /** The end of the window on the given date. */
    public function getEndOn(\DateTimeImmutable $day): \DateTimeImmutable
    {
        return $day->setTime((int) $this->endTime->format('H'), (int) $this->endTime->format('i'));
    }

    /** Two windows overlap when each starts before the other ends; touching does not count. */
    public function overlapsInTime(self $other): bool
    {
        return $this->startTime->format('H:i') < $other->endTime->format('H:i')
            && $other->startTime->format('H:i') < $this->endTime->format('H:i');
    }

    /** Both weekly on the same weekday, or both one-off on the same date. */
    public function isSameKind(self $other): bool
    {
        if (null !== $this->weekday || null !== $other->weekday) {
            return $this->weekday === $other->weekday;
        }

        return null !== $this->date && null !== $other->date
            && $this->date->format('Y-m-d') === $other->date->format('Y-m-d');
    }

    /** The day for messages: "Monday" for a weekly entry, "Wed 5 Feb" for a one-off entry. */
    public function getDayLabel(): string
    {
        return $this->weekday?->label() ?? ($this->date?->format('D j M') ?? '');
    }

    public function update(
        ?Weekday $weekday,
        ?\DateTimeImmutable $date,
        \DateTimeImmutable $startTime,
        \DateTimeImmutable $endTime,
        AvailabilityType $type,
    ): void {
        $this->weekday = $weekday;
        $this->date = $date;
        $this->startTime = $startTime;
        $this->endTime = $endTime;
        $this->type = $type;
    }
}
