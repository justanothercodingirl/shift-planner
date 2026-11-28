<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Assignment;
use App\Entity\Employee;
use App\Entity\Roster;
use App\Entity\Shift;
use App\Entity\SwapRequest;
use App\Entity\User;
use App\Enum\ContractType;
use App\Enum\Position;
use App\Enum\SwapStatus;
use App\Enum\UserRole;
use PHPUnit\Framework\TestCase;

final class SwapRequestTest extends TestCase
{
    public function testStartsPendingWithItsParties(): void
    {
        $shift = $this->shift();
        $anna = $this->employee('Anna');
        $bea = $this->employee('Bea');
        $assignment = new Assignment($shift, $anna);
        $createdAt = new \DateTimeImmutable('2031-01-01 10:00');

        $request = new SwapRequest($shift, $assignment, $anna, $bea, $createdAt);

        self::assertSame(SwapStatus::Pending, $request->getStatus());
        self::assertSame($shift, $request->getShift());
        self::assertSame($assignment, $request->getAssignment());
        self::assertSame($anna, $request->getRequester());
        self::assertSame($bea, $request->getTarget());
        self::assertSame($createdAt, $request->getCreatedAt());
        self::assertTrue($request->isOpen());
    }

    public function testTheHappyPathIsPendingAcceptedApproved(): void
    {
        $request = $this->request();

        $request->accept();
        self::assertSame(SwapStatus::Accepted, $request->getStatus());
        self::assertTrue($request->isOpen());

        $request->approve();
        self::assertSame(SwapStatus::Approved, $request->getStatus());
        self::assertFalse($request->isOpen());
    }

    public function testTheOtherEndsOfARequest(): void
    {
        $declined = $this->request();
        $declined->decline();
        self::assertSame(SwapStatus::Declined, $declined->getStatus());

        $rejected = $this->request();
        $rejected->reject();
        self::assertSame(SwapStatus::Rejected, $rejected->getStatus());

        $cancelled = $this->request();
        $cancelled->accept();
        $cancelled->cancel();
        self::assertSame(SwapStatus::Cancelled, $cancelled->getStatus());
    }

    public function testApprovingAPendingRequestIsRefused(): void
    {
        $request = $this->request();

        $this->expectException(\LogicException::class);
        $request->approve();
    }

    public function testAFinishedRequestCannotChangeAgain(): void
    {
        $request = $this->request();
        $request->decline();

        $this->expectException(\LogicException::class);
        $request->accept();
    }

    public function testItSurvivesWithoutItsAssignment(): void
    {
        $shift = $this->shift();
        $anna = $this->employee('Anna');

        $request = new SwapRequest($shift, null, $anna, $this->employee('Bea'), new \DateTimeImmutable());

        self::assertNull($request->getAssignment());
    }

    private function request(): SwapRequest
    {
        $shift = $this->shift();
        $anna = $this->employee('Anna');

        return new SwapRequest($shift, new Assignment($shift, $anna), $anna, $this->employee('Bea'), new \DateTimeImmutable());
    }

    private function shift(): Shift
    {
        return new Shift(
            new Roster(new \DateTimeImmutable('2031-02-03')),
            new \DateTimeImmutable('2031-02-04'),
            new \DateTimeImmutable('08:00'),
            new \DateTimeImmutable('14:00'),
            Position::Barista,
            1,
        );
    }

    private function employee(string $name): Employee
    {
        return new Employee(new User(strtolower($name).'@example.com', 'hash', UserRole::Employee), $name, Position::Barista, ContractType::PartTime, 20);
    }
}
