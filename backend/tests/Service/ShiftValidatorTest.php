<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Employee;
use App\Entity\Roster;
use App\Entity\Shift;
use App\Entity\User;
use App\Enum\ContractType;
use App\Enum\Position;
use App\Enum\UserRole;
use App\Exception\RuleViolationException;
use App\Rule\ShiftRule;
use App\Rule\Violation;
use App\Service\ShiftValidator;
use PHPUnit\Framework\TestCase;

final class ShiftValidatorTest extends TestCase
{
    public function testWithoutRulesThereAreNoViolations(): void
    {
        $validator = new ShiftValidator([]);

        self::assertSame([], $validator->validate($this->employee(), $this->shift()));
    }

    public function testPassingRulesProduceNoViolations(): void
    {
        $validator = new ShiftValidator([$this->rule(null), $this->rule(null)]);

        self::assertSame([], $validator->validate($this->employee(), $this->shift()));
    }

    public function testAllViolationsAreReturnedTogetherSortedByCode(): void
    {
        $validator = new ShiftValidator([
            $this->rule(new Violation('WEEKLY_CAP', 'too many hours')),
            $this->rule(null),
            $this->rule(new Violation('OVERLAP', 'already working then')),
        ]);

        $violations = $validator->validate($this->employee(), $this->shift());

        self::assertSame(['OVERLAP', 'WEEKLY_CAP'], array_map(static fn (Violation $v): string => $v->code, $violations));
    }

    public function testAssertValidThrowsWithEveryViolation(): void
    {
        $validator = new ShiftValidator([
            $this->rule(new Violation('OVERLAP', 'already working then')),
            $this->rule(new Violation('WEEKLY_CAP', 'too many hours')),
        ]);

        try {
            $validator->assertValid($this->employee(), $this->shift());
            self::fail('Expected a RuleViolationException.');
        } catch (RuleViolationException $exception) {
            self::assertSame(422, $exception->getStatusCode());
            self::assertSame('RULE_VIOLATION', $exception->getErrorCode());
            self::assertSame(
                [
                    ['code' => 'OVERLAP', 'reason' => 'already working then'],
                    ['code' => 'WEEKLY_CAP', 'reason' => 'too many hours'],
                ],
                $exception->getViolations(),
            );
        }
    }

    public function testAssertValidDoesNothingWhenAllRulesPass(): void
    {
        $validator = new ShiftValidator([$this->rule(null)]);

        $validator->assertValid($this->employee(), $this->shift());

        $this->addToAssertionCount(1);
    }

    private function rule(?Violation $violation): ShiftRule
    {
        return new class($violation) implements ShiftRule {
            public function __construct(private readonly ?Violation $violation)
            {
            }

            public function check(Employee $employee, Shift $shift): ?Violation
            {
                return $this->violation;
            }
        };
    }

    private function employee(): Employee
    {
        return new Employee(
            new User('anna@example.com', 'hash', UserRole::Employee),
            'Anna',
            Position::Barista,
            ContractType::PartTime,
            20,
        );
    }

    private function shift(): Shift
    {
        return new Shift(
            new Roster(new \DateTimeImmutable('2031-02-03')),
            new \DateTimeImmutable('2031-02-05'),
            new \DateTimeImmutable('09:00'),
            new \DateTimeImmutable('15:00'),
            Position::Barista,
            2,
        );
    }
}
