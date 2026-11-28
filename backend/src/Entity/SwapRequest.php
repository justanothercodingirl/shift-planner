<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\SwapStatus;
use App\Repository\SwapRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * An employee asks a colleague to take over one of their shifts (a handover).
 * The assignment is kept as a link that is cleared when the assignment is
 * deleted, so a request can notice that the shift was changed meanwhile; the
 * shift is stored too, so the request can still be shown then.
 */
#[ORM\Entity(repositoryClass: SwapRequestRepository::class)]
#[ORM\Table(name: 'swap_request')]
class SwapRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine)

    #[ORM\Column(length: 20, enumType: SwapStatus::class)]
    private SwapStatus $status = SwapStatus::Pending;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Shift::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Shift $shift,
        #[ORM\ManyToOne(targetEntity: Assignment::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
        private ?Assignment $assignment,
        #[ORM\ManyToOne(targetEntity: Employee::class)]
        #[ORM\JoinColumn(nullable: false)]
        private Employee $requester,
        #[ORM\ManyToOne(targetEntity: Employee::class)]
        #[ORM\JoinColumn(nullable: false)]
        private Employee $target,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
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

    public function getAssignment(): ?Assignment
    {
        return $this->assignment;
    }

    public function getRequester(): Employee
    {
        return $this->requester;
    }

    public function getTarget(): Employee
    {
        return $this->target;
    }

    public function getStatus(): SwapStatus
    {
        return $this->status;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function accept(): void
    {
        $this->moveTo(SwapStatus::Accepted);
    }

    public function decline(): void
    {
        $this->moveTo(SwapStatus::Declined);
    }

    public function cancel(): void
    {
        $this->moveTo(SwapStatus::Cancelled);
    }

    public function approve(): void
    {
        $this->moveTo(SwapStatus::Approved);
    }

    public function reject(): void
    {
        $this->moveTo(SwapStatus::Rejected);
    }

    /** The service checks first and answers with a 409; this only guards against a programming mistake. */
    private function moveTo(SwapStatus $next): void
    {
        if (!$this->status->canMoveTo($next)) {
            throw new \LogicException(sprintf('A swap request that is %s cannot become %s.', $this->status->value, $next->value));
        }

        $this->status = $next;
    }
}
