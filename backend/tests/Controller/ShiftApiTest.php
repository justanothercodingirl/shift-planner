<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Roster;
use App\Repository\RosterRepository;

final class ShiftApiTest extends ApiTestCase
{
    private Roster $roster;

    protected function setUp(): void
    {
        parent::setUp();

        // Week of Monday 2031-02-03 to Sunday 2031-02-09.
        $this->roster = new Roster(new \DateTimeImmutable('2031-02-03'));
        self::getContainer()->get(RosterRepository::class)->save($this->roster);
    }

    public function testRequestWithoutTokenIsUnauthenticated(): void
    {
        $this->call('POST', $this->shiftsUrl(), $this->payload());

        self::assertResponseStatusCodeSame(401);
        self::assertSame('UNAUTHENTICATED', $this->body()['error']['code']);
    }

    public function testEmployeeRoleIsForbidden(): void
    {
        $this->call('POST', $this->shiftsUrl(), $this->payload(), $this->employeeToken());

        self::assertResponseStatusCodeSame(403);
        self::assertSame('FORBIDDEN', $this->body()['error']['code']);
    }

    public function testManagerCreatesAShiftThatShowsUpInTheRoster(): void
    {
        $token = $this->managerToken();

        $this->call('POST', $this->shiftsUrl(), $this->payload(), $token);

        self::assertResponseStatusCodeSame(201);
        $created = $this->body();
        self::assertSame([
            'id' => $created['id'],
            'rosterId' => $this->roster->getId(),
            'date' => '2031-02-05',
            'startTime' => '09:00',
            'endTime' => '15:00',
            'requiredPosition' => 'barista',
            'headcount' => 2,
            'assignments' => [],
        ], $created);

        $this->call('GET', '/api/rosters/'.$this->roster->getId(), token: $token);
        self::assertSame([$created], $this->body()['shifts']);
    }

    public function testOvernightShiftIsCreated(): void
    {
        $this->call('POST', $this->shiftsUrl(), $this->payload(['startTime' => '18:00', 'endTime' => '02:00']), $this->managerToken());

        self::assertResponseStatusCodeSame(201);
        self::assertSame('18:00', $this->body()['startTime']);
        self::assertSame('02:00', $this->body()['endTime']);
    }

    public function testInvalidInputReturnsViolations(): void
    {
        $bad = $this->payload(['headcount' => 0, 'startTime' => '25:00', 'endTime' => '9am', 'date' => '5 Feb']);

        $this->call('POST', $this->shiftsUrl(), $bad, $this->managerToken());

        self::assertResponseStatusCodeSame(422);
        $error = $this->body()['error'];
        self::assertSame('VALIDATION_FAILED', $error['code']);
        $fields = array_column($error['violations'], 'code');
        foreach (['headcount', 'startTime', 'endTime', 'date'] as $field) {
            self::assertContains($field, $fields);
        }
    }

    public function testUnknownPositionAndMissingFieldsAre422(): void
    {
        $token = $this->managerToken();

        $this->call('POST', $this->shiftsUrl(), $this->payload(['requiredPosition' => 'astronaut']), $token);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('VALIDATION_FAILED', $this->body()['error']['code']);

        $this->call('POST', $this->shiftsUrl(), [], $token);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('VALIDATION_FAILED', $this->body()['error']['code']);
    }

    public function testDateOutsideTheRosterWeekIs422(): void
    {
        $this->call('POST', $this->shiftsUrl(), $this->payload(['date' => '2031-02-10']), $this->managerToken());

        self::assertResponseStatusCodeSame(422);
        self::assertSame('SHIFT_OUTSIDE_ROSTER_WEEK', $this->body()['error']['code']);
    }

    public function testEqualStartAndEndTimesAre422(): void
    {
        $this->call('POST', $this->shiftsUrl(), $this->payload(['startTime' => '08:00', 'endTime' => '08:00']), $this->managerToken());

        self::assertResponseStatusCodeSame(422);
        self::assertSame('SHIFT_TIMES_INVALID', $this->body()['error']['code']);
    }

    public function testUnknownRosterIs404(): void
    {
        $this->call('POST', '/api/rosters/999999/shifts', $this->payload(), $this->managerToken());

        self::assertResponseStatusCodeSame(404);
        self::assertSame('ROSTER_NOT_FOUND', $this->body()['error']['code']);
    }

    public function testManagerUpdatesAShift(): void
    {
        $token = $this->managerToken();
        $this->call('POST', $this->shiftsUrl(), $this->payload(), $token);
        $id = $this->body()['id'];

        $changed = $this->payload(['date' => '2031-02-06', 'startTime' => '10:00', 'endTime' => '18:00', 'requiredPosition' => 'kitchen', 'headcount' => 1]);
        $this->call('PUT', '/api/shifts/'.$id, $changed, $token);

        self::assertResponseStatusCodeSame(200);
        self::assertSame([
            'id' => $id,
            'rosterId' => $this->roster->getId(),
            'date' => '2031-02-06',
            'startTime' => '10:00',
            'endTime' => '18:00',
            'requiredPosition' => 'kitchen',
            'headcount' => 1,
            'assignments' => [],
        ], $this->body());
    }

    public function testUpdateOutsideTheWeekIs422AndUnknownShiftIs404(): void
    {
        $token = $this->managerToken();
        $this->call('POST', $this->shiftsUrl(), $this->payload(), $token);
        $id = $this->body()['id'];

        $this->call('PUT', '/api/shifts/'.$id, $this->payload(['date' => '2031-02-12']), $token);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('SHIFT_OUTSIDE_ROSTER_WEEK', $this->body()['error']['code']);

        $this->call('PUT', '/api/shifts/999999', $this->payload(), $token);
        self::assertResponseStatusCodeSame(404);
        self::assertSame('SHIFT_NOT_FOUND', $this->body()['error']['code']);
    }

    public function testManagerDeletesAShift(): void
    {
        $token = $this->managerToken();
        $this->call('POST', $this->shiftsUrl(), $this->payload(), $token);
        $id = $this->body()['id'];

        $this->call('DELETE', '/api/shifts/'.$id, token: $token);
        self::assertResponseStatusCodeSame(204);

        $this->call('GET', '/api/rosters/'.$this->roster->getId(), token: $token);
        self::assertSame([], $this->body()['shifts']);

        $this->call('DELETE', '/api/shifts/'.$id, token: $token);
        self::assertResponseStatusCodeSame(404);
        self::assertSame('SHIFT_NOT_FOUND', $this->body()['error']['code']);
    }

    private function shiftsUrl(): string
    {
        return '/api/rosters/'.$this->roster->getId().'/shifts';
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'date' => '2031-02-05',
            'startTime' => '09:00',
            'endTime' => '15:00',
            'requiredPosition' => 'barista',
            'headcount' => 2,
        ], $overrides);
    }
}
