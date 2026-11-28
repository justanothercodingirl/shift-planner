<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\SwapStatus;
use PHPUnit\Framework\TestCase;

final class SwapStatusTest extends TestCase
{
    public function testTheValuesAreTheSixAgreedStatuses(): void
    {
        self::assertSame(
            ['pending', 'accepted', 'declined', 'approved', 'rejected', 'cancelled'],
            array_map(static fn (SwapStatus $s): string => $s->value, SwapStatus::cases()),
        );
    }

    public function testOnlyPendingAndAcceptedRequestsAreOpen(): void
    {
        self::assertTrue(SwapStatus::Pending->isOpen());
        self::assertTrue(SwapStatus::Accepted->isOpen());
        foreach ([SwapStatus::Declined, SwapStatus::Approved, SwapStatus::Rejected, SwapStatus::Cancelled] as $status) {
            self::assertFalse($status->isOpen(), $status->value);
        }
    }

    public function testAPendingRequestCanBeAcceptedDeclinedRejectedOrCancelled(): void
    {
        foreach ([SwapStatus::Accepted, SwapStatus::Declined, SwapStatus::Rejected, SwapStatus::Cancelled] as $next) {
            self::assertTrue(SwapStatus::Pending->canMoveTo($next), $next->value);
        }
        self::assertFalse(SwapStatus::Pending->canMoveTo(SwapStatus::Approved), 'the colleague must agree first');
    }

    public function testAnAcceptedRequestCanBeApprovedRejectedOrCancelled(): void
    {
        foreach ([SwapStatus::Approved, SwapStatus::Rejected, SwapStatus::Cancelled] as $next) {
            self::assertTrue(SwapStatus::Accepted->canMoveTo($next), $next->value);
        }
        self::assertFalse(SwapStatus::Accepted->canMoveTo(SwapStatus::Declined));
        self::assertFalse(SwapStatus::Accepted->canMoveTo(SwapStatus::Accepted));
    }

    public function testFinishedRequestsCannotMoveAnywhere(): void
    {
        foreach ([SwapStatus::Declined, SwapStatus::Approved, SwapStatus::Rejected, SwapStatus::Cancelled] as $finished) {
            foreach (SwapStatus::cases() as $next) {
                self::assertFalse($finished->canMoveTo($next), $finished->value.' to '.$next->value);
            }
        }
    }
}
