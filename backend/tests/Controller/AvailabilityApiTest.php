<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Availability;
use App\Entity\Employee;
use App\Enum\AvailabilityType;
use App\Enum\ContractType;
use App\Enum\Position;
use App\Enum\UserRole;
use App\Enum\Weekday;
use App\Repository\AvailabilityRepository;
use App\Repository\EmployeeRepository;
use App\Repository\UserRepository;

final class AvailabilityApiTest extends ApiTestCase
{
    private const BEN_EMAIL = 'ben-avail-api@example.com';

    private Employee $anna;
    private Employee $ben;

    protected function setUp(): void
    {
        parent::setUp();

        // Everything is created before the first request: a request resets the entity manager.
        $container = self::getContainer();
        $annaUser = $container->get(UserRepository::class)->findOneBy(['email' => self::EMPLOYEE_EMAIL]);
        self::assertNotNull($annaUser);
        $this->anna = new Employee($annaUser, 'Anna', Position::Barista, ContractType::PartTime, 20);
        $container->get(EmployeeRepository::class)->save($this->anna);
        $this->ben = new Employee($this->saveUser(self::BEN_EMAIL, UserRole::Employee), 'Ben', Position::Service, ContractType::PartTime, 20);
        $container->get(EmployeeRepository::class)->save($this->ben);
    }

    public function testRequestWithoutTokenIsUnauthenticated(): void
    {
        $this->call('GET', $this->url($this->anna));

        self::assertResponseStatusCodeSame(401);
        self::assertSame('UNAUTHENTICATED', $this->body()['error']['code']);
    }

    public function testAnEmployeeCreatesAWeeklyEntryForThemselves(): void
    {
        $this->call('POST', $this->url($this->anna), $this->weekly(), $this->employeeToken());

        self::assertResponseStatusCodeSame(201);
        $body = $this->body();
        self::assertSame(
            [
                'id' => $body['id'],
                'employeeId' => $this->anna->getId(),
                'weekday' => 'monday',
                'date' => null,
                'startTime' => '08:00',
                'endTime' => '16:00',
                'type' => 'unavailable',
            ],
            $body,
        );
    }

    public function testAnEmployeeCreatesAOneOffEntry(): void
    {
        $this->call('POST', $this->url($this->anna), ['date' => '2031-02-05', 'startTime' => '00:00', 'endTime' => '23:59', 'type' => 'unavailable'], $this->employeeToken());

        self::assertResponseStatusCodeSame(201);
        self::assertSame('2031-02-05', $this->body()['date']);
        self::assertNull($this->body()['weekday']);
    }

    public function testEmployeeAndManagerCanListButAColleagueCannot(): void
    {
        $this->entry($this->anna, Weekday::Monday);
        $annaToken = $this->employeeToken();
        $managerToken = $this->managerToken();
        $benToken = $this->login(self::BEN_EMAIL);

        $this->call('GET', $this->url($this->anna), null, $annaToken);
        self::assertResponseStatusCodeSame(200);
        self::assertCount(1, $this->body());

        $this->call('GET', $this->url($this->anna), null, $managerToken);
        self::assertResponseStatusCodeSame(200);
        self::assertCount(1, $this->body());

        $this->call('GET', $this->url($this->anna), null, $benToken);
        self::assertResponseStatusCodeSame(403);
        self::assertSame('FORBIDDEN', $this->body()['error']['code']);
    }

    public function testAManagerCannotChangeAnEmployeesAvailability(): void
    {
        $entry = $this->entry($this->anna, Weekday::Monday);
        $token = $this->managerToken();

        $this->call('POST', $this->url($this->anna), $this->weekly(), $token);
        self::assertResponseStatusCodeSame(403);

        $this->call('PUT', '/api/availabilities/'.$entry->getId(), $this->weekly(), $token);
        self::assertResponseStatusCodeSame(403);

        $this->call('DELETE', '/api/availabilities/'.$entry->getId(), null, $token);
        self::assertResponseStatusCodeSame(403);
    }

    public function testAColleagueCannotChangeAnotherEmployeesAvailability(): void
    {
        $entry = $this->entry($this->anna, Weekday::Monday);
        $token = $this->login(self::BEN_EMAIL);

        $this->call('POST', $this->url($this->anna), $this->weekly(), $token);
        self::assertResponseStatusCodeSame(403);

        $this->call('PUT', '/api/availabilities/'.$entry->getId(), $this->weekly(), $token);
        self::assertResponseStatusCodeSame(403);
        self::assertSame('FORBIDDEN', $this->body()['error']['code']);

        $this->call('DELETE', '/api/availabilities/'.$entry->getId(), null, $token);
        self::assertResponseStatusCodeSame(403);
    }

    public function testTheOwnerUpdatesAndDeletesAnEntry(): void
    {
        $entry = $this->entry($this->anna, Weekday::Monday);
        $token = $this->employeeToken();

        $this->call('PUT', '/api/availabilities/'.$entry->getId(), ['weekday' => 'tuesday', 'startTime' => '09:00', 'endTime' => '11:30', 'type' => 'preferred'], $token);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('tuesday', $this->body()['weekday']);
        self::assertSame('11:30', $this->body()['endTime']);
        self::assertSame('preferred', $this->body()['type']);

        $this->call('DELETE', '/api/availabilities/'.$entry->getId(), null, $token);
        self::assertResponseStatusCodeSame(204);

        $this->call('GET', $this->url($this->anna), null, $token);
        self::assertSame([], $this->body());
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidBodies(): iterable
    {
        yield 'weekday and date together' => [['weekday' => 'monday', 'date' => '2031-02-05', 'startTime' => '08:00', 'endTime' => '16:00', 'type' => 'unavailable'], 'weekday'];
        yield 'neither weekday nor date' => [['startTime' => '08:00', 'endTime' => '16:00', 'type' => 'unavailable'], 'weekday'];
        yield 'bad time' => [['weekday' => 'monday', 'startTime' => '8am', 'endTime' => '16:00', 'type' => 'unavailable'], 'startTime'];
        yield 'bad date' => [['date' => 'next tuesday', 'startTime' => '08:00', 'endTime' => '16:00', 'type' => 'unavailable'], 'date'];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidBodies')]
    public function testInvalidInputIsRejectedWithTheFieldName(array $body, string $field): void
    {
        $this->call('POST', $this->url($this->anna), $body, $this->employeeToken());

        self::assertResponseStatusCodeSame(422);
        $error = $this->body()['error'];
        self::assertSame('VALIDATION_FAILED', $error['code']);
        self::assertContains($field, array_column($error['violations'], 'code'));
    }

    public function testAnUnknownWeekdayOrTypeIsRejected(): void
    {
        $token = $this->employeeToken();

        $this->call('POST', $this->url($this->anna), ['weekday' => 'funday', 'startTime' => '08:00', 'endTime' => '16:00', 'type' => 'unavailable'], $token);
        self::assertResponseStatusCodeSame(422);

        $this->call('POST', $this->url($this->anna), ['weekday' => 'monday', 'startTime' => '08:00', 'endTime' => '16:00', 'type' => 'maybe'], $token);
        self::assertResponseStatusCodeSame(422);
    }

    public function testEndBeforeStartIsRejected(): void
    {
        $this->call('POST', $this->url($this->anna), $this->weekly('16:00', '08:00'), $this->employeeToken());

        self::assertResponseStatusCodeSame(422);
        self::assertSame('AVAILABILITY_TIMES_INVALID', $this->body()['error']['code']);
    }

    public function testAnOverlappingEntryIsRejected(): void
    {
        $this->entry($this->anna, Weekday::Monday);

        $this->call('POST', $this->url($this->anna), $this->weekly('10:00', '12:00'), $this->employeeToken());

        self::assertResponseStatusCodeSame(422);
        self::assertSame('AVAILABILITY_OVERLAP', $this->body()['error']['code']);
    }

    public function testUnknownEmployeeAndEntryAre404(): void
    {
        $token = $this->managerToken();

        $this->call('GET', '/api/employees/999999/availabilities', null, $token);
        self::assertResponseStatusCodeSame(404);
        self::assertSame('EMPLOYEE_NOT_FOUND', $this->body()['error']['code']);

        $this->call('PUT', '/api/availabilities/999999', $this->weekly(), $token);
        self::assertResponseStatusCodeSame(404);
        self::assertSame('AVAILABILITY_NOT_FOUND', $this->body()['error']['code']);
    }

    private function entry(Employee $employee, Weekday $weekday): Availability
    {
        $entry = new Availability($employee, $weekday, null, new \DateTimeImmutable('08:00'), new \DateTimeImmutable('16:00'), AvailabilityType::Unavailable);
        self::getContainer()->get(AvailabilityRepository::class)->save($entry);

        return $entry;
    }

    /** @return array<string, mixed> */
    private function weekly(string $from = '08:00', string $to = '16:00'): array
    {
        return ['weekday' => 'monday', 'startTime' => $from, 'endTime' => $to, 'type' => 'unavailable'];
    }

    private function url(Employee $employee): string
    {
        return '/api/employees/'.$employee->getId().'/availabilities';
    }
}
