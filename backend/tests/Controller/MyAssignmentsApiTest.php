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

final class MyAssignmentsApiTest extends ApiTestCase
{
    private Employee $anna;
    private Employee $ben;
    private Assignment $monday;

    protected function setUp(): void
    {
        parent::setUp();

        // Everything is created before the first request: a request resets the entity manager.
        $container = self::getContainer();
        $annaUser = $container->get(UserRepository::class)->findOneBy(['email' => self::EMPLOYEE_EMAIL]);
        self::assertNotNull($annaUser);
        $this->anna = $this->saveEmployee(new Employee($annaUser, 'Anna', Position::Barista, ContractType::PartTime, 20));
        $this->ben = $this->saveEmployee(new Employee($this->saveUser('ben-my-shifts@example.com', UserRole::Employee), 'Ben', Position::Service, ContractType::PartTime, 20));

        $published = $this->roster('2031-02-03', publish: true);
        $this->monday = $this->assign($this->shift($published, '2031-02-03', '08:00', '14:00', Position::Barista), $this->anna);
        $this->assign($this->shift($published, '2031-02-04', '18:00', '02:00', Position::Bar), $this->anna);
        $this->assign($this->shift($published, '2031-02-05', '08:00', '14:00', Position::Service), $this->ben);

        $draft = $this->roster('2031-02-10', publish: false);
        $this->assign($this->shift($draft, '2031-02-10', '08:00', '14:00', Position::Barista), $this->anna);

        $past = $this->roster('2020-02-03', publish: true);
        $this->assign($this->shift($past, '2020-02-04', '08:00', '14:00', Position::Barista), $this->anna);
    }

    public function testRequestsNeedALogin(): void
    {
        $this->call('GET', '/api/me/assignments');

        self::assertResponseStatusCodeSame(401);
        self::assertSame('UNAUTHENTICATED', $this->body()['error']['code']);
    }

    public function testAnEmployeeSeesOnlyTheirOwnUpcomingShiftsOfPublishedRosters(): void
    {
        $this->call('GET', '/api/me/assignments', null, $this->employeeToken());

        self::assertResponseStatusCodeSame(200);
        $body = $this->body();
        self::assertSame(['2031-02-03', '2031-02-04'], array_column($body, 'date'));
        self::assertSame(
            [
                'id' => $this->monday->getId(),
                'shiftId' => $this->monday->getShift()->getId(),
                'weekStart' => '2031-02-03',
                'date' => '2031-02-03',
                'startTime' => '08:00',
                'endTime' => '14:00',
                'requiredPosition' => 'barista',
            ],
            $body[0],
        );
        self::assertSame('bar', $body[1]['requiredPosition']);
        self::assertSame('02:00', $body[1]['endTime']);
    }

    public function testAnotherEmployeeSeesTheirOwnShiftsOnly(): void
    {
        $this->call('GET', '/api/me/assignments', null, $this->login('ben-my-shifts@example.com'));

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['2031-02-05'], array_column($this->body(), 'date'));
    }

    public function testALoginWithoutAnEmployeeProfileGetsAnEmptyList(): void
    {
        $this->call('GET', '/api/me/assignments', null, $this->managerToken());

        self::assertResponseStatusCodeSame(200);
        self::assertSame([], $this->body());
    }

    private function roster(string $weekStart, bool $publish): Roster
    {
        $roster = new Roster(new \DateTimeImmutable($weekStart));
        if ($publish) {
            $roster->publish();
        }
        self::getContainer()->get(RosterRepository::class)->save($roster);

        return $roster;
    }

    private function shift(Roster $roster, string $date, string $start, string $end, Position $position): Shift
    {
        $shift = new Shift($roster, new \DateTimeImmutable($date), new \DateTimeImmutable($start), new \DateTimeImmutable($end), $position, 1);
        self::getContainer()->get(ShiftRepository::class)->save($shift);

        return $shift;
    }

    private function assign(Shift $shift, Employee $employee): Assignment
    {
        $assignment = new Assignment($shift, $employee);
        self::getContainer()->get(AssignmentRepository::class)->save($assignment);

        return $assignment;
    }

    private function saveEmployee(Employee $employee): Employee
    {
        self::getContainer()->get(EmployeeRepository::class)->save($employee);

        return $employee;
    }
}
