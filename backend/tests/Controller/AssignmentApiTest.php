<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Employee;
use App\Entity\Roster;
use App\Entity\Shift;
use App\Enum\ContractType;
use App\Enum\Position;
use App\Enum\UserRole;
use App\Repository\EmployeeRepository;
use App\Repository\RosterRepository;
use App\Repository\ShiftRepository;

final class AssignmentApiTest extends ApiTestCase
{
    private Roster $roster;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roster = new Roster(new \DateTimeImmutable('2031-02-03'));
        self::getContainer()->get(RosterRepository::class)->save($this->roster);
    }

    public function testRequestWithoutTokenIsUnauthenticated(): void
    {
        $shift = $this->shift(2);

        $this->call('POST', $this->url($shift), ['employeeId' => 1]);

        self::assertResponseStatusCodeSame(401);
        self::assertSame('UNAUTHENTICATED', $this->body()['error']['code']);
    }

    public function testEmployeeRoleIsForbidden(): void
    {
        $shift = $this->shift(2);

        $this->call('POST', $this->url($shift), ['employeeId' => 1], $this->employeeToken());

        self::assertResponseStatusCodeSame(403);
        self::assertSame('FORBIDDEN', $this->body()['error']['code']);
    }

    public function testManagerAssignsAnEmployeeToAShift(): void
    {
        $shift = $this->shift(2);
        $anna = $this->employee('Anna');

        $this->call('POST', $this->url($shift), ['employeeId' => $anna->getId()], $this->managerToken());

        self::assertResponseStatusCodeSame(201);
        $body = $this->body();
        self::assertSame(
            ['id' => $body['id'], 'shiftId' => $shift->getId(), 'employeeId' => $anna->getId()],
            $body,
        );
    }

    public function testUnknownShiftAndUnknownEmployeeAre404(): void
    {
        // Create everything before the first request: a request resets the entity manager.
        $token = $this->managerToken();
        $anna = $this->employee('Anna');
        $shift = $this->shift(1);

        $this->call('POST', '/api/shifts/999999/assignments', ['employeeId' => $anna->getId()], $token);
        self::assertResponseStatusCodeSame(404);
        self::assertSame('SHIFT_NOT_FOUND', $this->body()['error']['code']);

        $this->call('POST', $this->url($shift), ['employeeId' => 999999], $token);
        self::assertResponseStatusCodeSame(404);
        self::assertSame('EMPLOYEE_NOT_FOUND', $this->body()['error']['code']);
    }

    public function testDeactivatedEmployeeIs422(): void
    {
        $anna = $this->employee('Anna');
        $anna->deactivate();
        self::getContainer()->get(EmployeeRepository::class)->save($anna);

        $this->call('POST', $this->url($this->shift(1)), ['employeeId' => $anna->getId()], $this->managerToken());

        self::assertResponseStatusCodeSame(422);
        self::assertSame('EMPLOYEE_INACTIVE', $this->body()['error']['code']);
    }

    public function testAssigningTwiceReturnsAlreadyAssignedViolation(): void
    {
        $token = $this->managerToken();
        $shift = $this->shift(2);
        $anna = $this->employee('Anna');
        $this->call('POST', $this->url($shift), ['employeeId' => $anna->getId()], $token);

        $this->call('POST', $this->url($shift), ['employeeId' => $anna->getId()], $token);

        self::assertResponseStatusCodeSame(422);
        $error = $this->body()['error'];
        self::assertSame('RULE_VIOLATION', $error['code']);
        self::assertSame(['ALREADY_ASSIGNED'], array_column($error['violations'], 'code'));
        self::assertStringContainsString('Anna', $error['violations'][0]['reason']);
    }

    public function testFullShiftReturnsShiftFullViolation(): void
    {
        $token = $this->managerToken();
        $shift = $this->shift(1);
        $this->call('POST', $this->url($shift), ['employeeId' => $this->employee('Anna')->getId()], $token);

        $this->call('POST', $this->url($shift), ['employeeId' => $this->employee('Ben')->getId()], $token);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['SHIFT_FULL'], array_column($this->body()['error']['violations'], 'code'));
    }

    public function testBothViolationsAreReportedTogether(): void
    {
        $token = $this->managerToken();
        $shift = $this->shift(1);
        $anna = $this->employee('Anna');
        $this->call('POST', $this->url($shift), ['employeeId' => $anna->getId()], $token);

        $this->call('POST', $this->url($shift), ['employeeId' => $anna->getId()], $token);

        self::assertResponseStatusCodeSame(422);
        $codes = array_column($this->body()['error']['violations'], 'code');
        sort($codes);
        self::assertSame(['ALREADY_ASSIGNED', 'SHIFT_FULL'], $codes);
    }

    public function testInvalidBodyIs422WithViolations(): void
    {
        $token = $this->managerToken();
        $shift = $this->shift(1);

        $this->call('POST', $this->url($shift), ['employeeId' => 0], $token);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('VALIDATION_FAILED', $this->body()['error']['code']);
        self::assertContains('employeeId', array_column($this->body()['error']['violations'], 'code'));

        $this->call('POST', $this->url($shift), [], $token);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('VALIDATION_FAILED', $this->body()['error']['code']);
    }

    public function testAssignmentsAppearInTheRosterAndCanBeRemoved(): void
    {
        $token = $this->managerToken();
        $shift = $this->shift(2);
        $anna = $this->employee('Anna');
        $this->call('POST', $this->url($shift), ['employeeId' => $anna->getId()], $token);
        $assignmentId = $this->body()['id'];

        $this->call('GET', '/api/rosters/'.$this->roster->getId(), token: $token);
        self::assertSame(
            [['id' => $assignmentId, 'employeeId' => $anna->getId(), 'employeeName' => 'Anna']],
            $this->body()['shifts'][0]['assignments'],
        );

        $this->call('DELETE', '/api/assignments/'.$assignmentId, token: $token);
        self::assertResponseStatusCodeSame(204);

        $this->call('GET', '/api/rosters/'.$this->roster->getId(), token: $token);
        self::assertSame([], $this->body()['shifts'][0]['assignments']);

        $this->call('DELETE', '/api/assignments/'.$assignmentId, token: $token);
        self::assertResponseStatusCodeSame(404);
        self::assertSame('ASSIGNMENT_NOT_FOUND', $this->body()['error']['code']);
    }

    private function url(Shift $shift): string
    {
        return '/api/shifts/'.$shift->getId().'/assignments';
    }

    private function shift(int $headcount): Shift
    {
        $shift = new Shift(
            $this->roster,
            new \DateTimeImmutable('2031-02-05'),
            new \DateTimeImmutable('09:00'),
            new \DateTimeImmutable('15:00'),
            Position::Barista,
            $headcount,
        );
        self::getContainer()->get(ShiftRepository::class)->save($shift);

        return $shift;
    }

    private function employee(string $name): Employee
    {
        $user = $this->saveUser(strtolower($name).'-api@example.com', UserRole::Employee);
        $employee = new Employee($user, $name, Position::Barista, ContractType::PartTime, 20);
        self::getContainer()->get(EmployeeRepository::class)->save($employee);

        return $employee;
    }
}
