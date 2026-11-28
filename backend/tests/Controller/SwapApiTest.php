<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Assignment;
use App\Entity\Employee;
use App\Entity\Roster;
use App\Entity\Shift;
use App\Enum\ContractType;
use App\Enum\Position;
use App\Enum\UserRole;
use App\Repository\AssignmentRepository;
use App\Repository\EmployeeRepository;
use App\Repository\RosterRepository;
use App\Repository\ShiftRepository;
use App\Repository\UserRepository;

/** Anna (the test employee login) works Tuesday 2031-02-04 08:00 to 14:00 as a barista and asks Bea, another barista, to take it. */
final class SwapApiTest extends ApiTestCase
{
    private const BEA_EMAIL = 'bea-swap-api@example.com';
    private const BEN_EMAIL = 'ben-swap-api@example.com';

    private Roster $roster;
    private Shift $shift;
    private Assignment $assignment;
    private Employee $anna;
    private Employee $bea;
    private Employee $ben;

    protected function setUp(): void
    {
        parent::setUp();

        // Everything is created before the first request: a request resets the entity manager.
        $container = self::getContainer();
        $annaUser = $container->get(UserRepository::class)->findOneBy(['email' => self::EMPLOYEE_EMAIL]);
        self::assertNotNull($annaUser);
        $this->anna = $this->save(new Employee($annaUser, 'Anna', Position::Barista, ContractType::PartTime, 20));
        $this->bea = $this->save(new Employee($this->saveUser(self::BEA_EMAIL, UserRole::Employee), 'Bea', Position::Barista, ContractType::PartTime, 20));
        $this->ben = $this->save(new Employee($this->saveUser(self::BEN_EMAIL, UserRole::Employee), 'Ben', Position::Service, ContractType::PartTime, 20));

        $this->roster = new Roster(new \DateTimeImmutable('2031-02-03'));
        $this->roster->publish();
        $container->get(RosterRepository::class)->save($this->roster);
        $this->shift = new Shift($this->roster, new \DateTimeImmutable('2031-02-04'), new \DateTimeImmutable('08:00'), new \DateTimeImmutable('14:00'), Position::Barista, 1);
        $container->get(ShiftRepository::class)->save($this->shift);
        $this->assignment = new Assignment($this->shift, $this->anna);
        $container->get(AssignmentRepository::class)->save($this->assignment);
    }

    public function testRequestsNeedALogin(): void
    {
        $this->call('GET', '/api/swap-requests');
        self::assertResponseStatusCodeSame(401);

        $this->call('POST', $this->askUrl(), ['targetEmployeeId' => $this->bea->getId()]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testAnEmployeeAsksAColleagueToTakeTheirShift(): void
    {
        $this->call('POST', $this->askUrl(), ['targetEmployeeId' => $this->bea->getId()], $this->employeeToken());

        self::assertResponseStatusCodeSame(201);
        $body = $this->body();
        self::assertSame('pending', $body['status']);
        self::assertSame($this->assignment->getId(), $body['assignmentId']);
        self::assertSame(['id' => $this->anna->getId(), 'name' => 'Anna'], $body['requester']);
        self::assertSame(['id' => $this->bea->getId(), 'name' => 'Bea'], $body['target']);
        self::assertSame([
            'id' => $this->shift->getId(),
            'rosterId' => $this->roster->getId(),
            'date' => '2031-02-04',
            'startTime' => '08:00',
            'endTime' => '14:00',
            'requiredPosition' => 'barista',
        ], $body['shift']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $body['createdAt']);
    }

    public function testOnlyTheOwnerOfTheShiftMayAsk(): void
    {
        $this->call('POST', $this->askUrl(), ['targetEmployeeId' => $this->ben->getId()], $this->login(self::BEA_EMAIL));

        self::assertResponseStatusCodeSame(403);
        self::assertSame('FORBIDDEN', $this->body()['error']['code']);
    }

    public function testUnknownThingsAre404AndABadBodyIs422(): void
    {
        $token = $this->employeeToken();

        $this->call('POST', '/api/assignments/999999/swap-requests', ['targetEmployeeId' => $this->bea->getId()], $token);
        self::assertResponseStatusCodeSame(404);
        self::assertSame('ASSIGNMENT_NOT_FOUND', $this->body()['error']['code']);

        $this->call('POST', $this->askUrl(), ['targetEmployeeId' => 999999], $token);
        self::assertResponseStatusCodeSame(404);
        self::assertSame('EMPLOYEE_NOT_FOUND', $this->body()['error']['code']);

        $this->call('POST', $this->askUrl(), [], $token);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('VALIDATION_FAILED', $this->body()['error']['code']);

        $this->call('POST', '/api/swap-requests/999999/accept', null, $token);
        self::assertResponseStatusCodeSame(404);
        self::assertSame('SWAP_REQUEST_NOT_FOUND', $this->body()['error']['code']);
    }

    public function testSwappingWithYourselfIs422AndASecondOpenRequestIs409(): void
    {
        $token = $this->employeeToken();

        $this->call('POST', $this->askUrl(), ['targetEmployeeId' => $this->anna->getId()], $token);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('SWAP_WITH_YOURSELF', $this->body()['error']['code']);

        $this->call('POST', $this->askUrl(), ['targetEmployeeId' => $this->bea->getId()], $token);
        self::assertResponseStatusCodeSame(201);

        $this->call('POST', $this->askUrl(), ['targetEmployeeId' => $this->ben->getId()], $token);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('SWAP_ALREADY_OPEN', $this->body()['error']['code']);
    }

    public function testTheWholeFlowFromAskingToTheManagersApproval(): void
    {
        $annaToken = $this->employeeToken();
        $beaToken = $this->login(self::BEA_EMAIL);
        $managerToken = $this->managerToken();

        $this->call('POST', $this->askUrl(), ['targetEmployeeId' => $this->bea->getId()], $annaToken);
        $id = $this->body()['id'];

        $this->call('POST', "/api/swap-requests/$id/accept", null, $beaToken);
        self::assertResponseStatusCodeSame(200);
        self::assertSame('accepted', $this->body()['status']);

        $this->call('POST', "/api/swap-requests/$id/approve", null, $managerToken);
        self::assertResponseStatusCodeSame(200);
        self::assertSame('approved', $this->body()['status']);

        $this->call('GET', '/api/rosters/'.$this->roster->getId(), null, $managerToken);
        $shifts = array_values(array_filter($this->body()['shifts'], fn (array $s): bool => $s['id'] === $this->shift->getId()));
        self::assertSame(['Bea'], array_column($shifts[0]['assignments'], 'employeeName'));
    }

    public function testOnlyTheManagerMayApproveOrRejectAndOnlyTheColleagueMayAnswer(): void
    {
        $annaToken = $this->employeeToken();
        $beaToken = $this->login(self::BEA_EMAIL);
        $managerToken = $this->managerToken();
        $this->call('POST', $this->askUrl(), ['targetEmployeeId' => $this->bea->getId()], $annaToken);
        $id = $this->body()['id'];

        foreach (['approve', 'reject'] as $action) {
            $this->call('POST', "/api/swap-requests/$id/$action", null, $beaToken);
            self::assertResponseStatusCodeSame(403, $action);
        }
        foreach (['accept', 'decline'] as $action) {
            $this->call('POST', "/api/swap-requests/$id/$action", null, $annaToken);
            self::assertResponseStatusCodeSame(403, $action);
            $this->call('POST', "/api/swap-requests/$id/$action", null, $managerToken);
            self::assertResponseStatusCodeSame(403, $action);
        }
    }

    public function testDecliningThenApprovingIs409Declined(): void
    {
        $annaToken = $this->employeeToken();
        $beaToken = $this->login(self::BEA_EMAIL);
        $managerToken = $this->managerToken();
        $this->call('POST', $this->askUrl(), ['targetEmployeeId' => $this->bea->getId()], $annaToken);
        $id = $this->body()['id'];

        $this->call('POST', "/api/swap-requests/$id/decline", null, $beaToken);
        self::assertResponseStatusCodeSame(200);
        self::assertSame('declined', $this->body()['status']);

        $this->call('POST', "/api/swap-requests/$id/approve", null, $managerToken);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('SWAP_DECLINED', $this->body()['error']['code']);
    }

    public function testApprovingBeforeTheColleagueAnsweredIs409(): void
    {
        $this->call('POST', $this->askUrl(), ['targetEmployeeId' => $this->bea->getId()], $this->employeeToken());
        $id = $this->body()['id'];

        $this->call('POST', "/api/swap-requests/$id/approve", null, $this->managerToken());

        self::assertResponseStatusCodeSame(409);
        self::assertSame('SWAP_NOT_IN_STATE', $this->body()['error']['code']);
    }

    public function testAColleagueWhoBreaksARuleCannotAcceptAndSeesEveryReason(): void
    {
        $this->call('POST', $this->askUrl(), ['targetEmployeeId' => $this->ben->getId()], $this->employeeToken());
        $id = $this->body()['id'];

        $this->call('POST', "/api/swap-requests/$id/accept", null, $this->login(self::BEN_EMAIL));

        self::assertResponseStatusCodeSame(422);
        $error = $this->body()['error'];
        self::assertSame('RULE_VIOLATION', $error['code']);
        self::assertSame(['POSITION_MISMATCH'], array_column($error['violations'], 'code'));
    }

    public function testTheRequesterCancelsAndNobodyElseMay(): void
    {
        $annaToken = $this->employeeToken();
        $beaToken = $this->login(self::BEA_EMAIL);
        $this->call('POST', $this->askUrl(), ['targetEmployeeId' => $this->bea->getId()], $annaToken);
        $id = $this->body()['id'];

        $this->call('POST', "/api/swap-requests/$id/cancel", null, $beaToken);
        self::assertResponseStatusCodeSame(403);

        $this->call('POST', "/api/swap-requests/$id/cancel", null, $annaToken);
        self::assertResponseStatusCodeSame(200);
        self::assertSame('cancelled', $this->body()['status']);

        $this->call('POST', "/api/swap-requests/$id/cancel", null, $annaToken);
        self::assertResponseStatusCodeSame(409);
    }

    public function testTheManagerRejects(): void
    {
        $this->call('POST', $this->askUrl(), ['targetEmployeeId' => $this->bea->getId()], $this->employeeToken());
        $id = $this->body()['id'];

        $this->call('POST', "/api/swap-requests/$id/reject", null, $this->managerToken());

        self::assertResponseStatusCodeSame(200);
        self::assertSame('rejected', $this->body()['status']);
    }

    public function testEachRoleSeesTheRightRequests(): void
    {
        $annaToken = $this->employeeToken();
        $beaToken = $this->login(self::BEA_EMAIL);
        $benToken = $this->login(self::BEN_EMAIL);
        $managerToken = $this->managerToken();
        $this->call('POST', $this->askUrl(), ['targetEmployeeId' => $this->bea->getId()], $annaToken);
        $id = $this->body()['id'];

        foreach ([$annaToken, $beaToken, $managerToken] as $token) {
            $this->call('GET', '/api/swap-requests', null, $token);
            self::assertResponseStatusCodeSame(200);
            self::assertSame([$id], array_column($this->body(), 'id'));
        }

        $this->call('GET', '/api/swap-requests', null, $benToken);
        self::assertSame([], $this->body());

        $this->call('GET', '/api/swap-requests?status=accepted', null, $managerToken);
        self::assertSame([], $this->body());
        $this->call('GET', '/api/swap-requests?status=pending', null, $managerToken);
        self::assertSame([$id], array_column($this->body(), 'id'));

        $this->call('GET', '/api/swap-requests?status=bogus', null, $managerToken);
        self::assertResponseStatusCodeSame(422);
    }

    private function askUrl(): string
    {
        return '/api/assignments/'.$this->assignment->getId().'/swap-requests';
    }

    private function save(Employee $employee): Employee
    {
        self::getContainer()->get(EmployeeRepository::class)->save($employee);

        return $employee;
    }
}
