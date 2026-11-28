<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Employee;
use App\Enum\ContractType;
use App\Enum\Position;
use App\Enum\UserRole;
use App\Repository\EmployeeRepository;
use App\Repository\UserRepository;

final class ColleaguesApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Everything is created before the first request: a request resets the entity manager.
        $annaUser = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => self::EMPLOYEE_EMAIL]);
        self::assertNotNull($annaUser);
        $this->save(new Employee($annaUser, 'Anna', Position::Barista, ContractType::PartTime, 20));
        $this->save(new Employee($this->saveUser('ben-colleagues@example.com', UserRole::Employee), 'Ben', Position::Service, ContractType::PartTime, 20, [Position::Bar]));
        $gone = new Employee($this->saveUser('gone-colleagues@example.com', UserRole::Employee), 'Gone', Position::Kitchen, ContractType::PartTime, 20);
        $gone->deactivate();
        $this->save($gone);
    }

    public function testRequestsNeedALogin(): void
    {
        $this->call('GET', '/api/colleagues');

        self::assertResponseStatusCodeSame(401);
        self::assertSame('UNAUTHENTICATED', $this->body()['error']['code']);
    }

    public function testAnEmployeeSeesTheOtherActiveEmployeesWithoutContactDetails(): void
    {
        $this->call('GET', '/api/colleagues', null, $this->employeeToken());

        self::assertResponseStatusCodeSame(200);
        $body = $this->body();
        self::assertCount(1, $body);
        self::assertSame(
            ['id', 'name', 'position', 'additionalPositions'],
            array_keys($body[0]),
        );
        self::assertSame('Ben', $body[0]['name']);
        self::assertSame('service', $body[0]['position']);
        self::assertSame(['bar'], $body[0]['additionalPositions']);
    }

    public function testNeitherTheEmployeeThemselvesNorDeactivatedPeopleAreListed(): void
    {
        $this->call('GET', '/api/colleagues', null, $this->employeeToken());

        self::assertSame(['Ben'], array_column($this->body(), 'name'));
    }

    public function testALoginWithoutAnEmployeeProfileSeesEveryActiveEmployee(): void
    {
        $this->call('GET', '/api/colleagues', null, $this->managerToken());

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['Anna', 'Ben'], array_column($this->body(), 'name'));
    }

    private function save(Employee $employee): void
    {
        self::getContainer()->get(EmployeeRepository::class)->save($employee);
    }
}
