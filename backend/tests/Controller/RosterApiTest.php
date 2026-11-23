<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Roster;
use App\Entity\Shift;
use App\Enum\Position;
use App\Repository\RosterRepository;
use App\Repository\ShiftRepository;

final class RosterApiTest extends ApiTestCase
{
    public function testRequestWithoutTokenIsUnauthenticated(): void
    {
        $this->call('GET', '/api/rosters');

        self::assertResponseStatusCodeSame(401);
        self::assertSame('UNAUTHENTICATED', $this->body()['error']['code']);
    }

    public function testEmployeeRoleIsForbidden(): void
    {
        $this->call('POST', '/api/rosters', ['weekStart' => '2031-02-03'], $this->employeeToken());

        self::assertResponseStatusCodeSame(403);
        self::assertSame('FORBIDDEN', $this->body()['error']['code']);
    }

    public function testManagerCreatesADraftRoster(): void
    {
        $this->call('POST', '/api/rosters', ['weekStart' => '2031-02-03'], $this->managerToken());

        self::assertResponseStatusCodeSame(201);
        $body = $this->body();
        self::assertNotNull($body['id']);
        self::assertSame('2031-02-03', $body['weekStart']);
        self::assertSame('draft', $body['status']);
        self::assertSame([], $body['shifts']);
    }

    public function testWeekStartThatIsNotAMondayIs422(): void
    {
        $this->call('POST', '/api/rosters', ['weekStart' => '2031-02-04'], $this->managerToken());

        self::assertResponseStatusCodeSame(422);
        self::assertSame('WEEK_START_NOT_MONDAY', $this->body()['error']['code']);
    }

    public function testSecondRosterForTheSameWeekIs409(): void
    {
        $token = $this->managerToken();
        $this->call('POST', '/api/rosters', ['weekStart' => '2031-02-03'], $token);

        $this->call('POST', '/api/rosters', ['weekStart' => '2031-02-03'], $token);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('ROSTER_ALREADY_EXISTS', $this->body()['error']['code']);
    }

    public function testMalformedOrMissingDateIs422WithViolations(): void
    {
        $token = $this->managerToken();

        $this->call('POST', '/api/rosters', ['weekStart' => '03/02/2031'], $token);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('VALIDATION_FAILED', $this->body()['error']['code']);
        self::assertContains('weekStart', array_column($this->body()['error']['violations'], 'code'));

        $this->call('POST', '/api/rosters', [], $token);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('VALIDATION_FAILED', $this->body()['error']['code']);
    }

    public function testListShowsNewestWeekFirstWithoutShifts(): void
    {
        $rosters = self::getContainer()->get(RosterRepository::class);
        $rosters->save(new Roster(new \DateTimeImmutable('2031-02-03')));
        $rosters->save(new Roster(new \DateTimeImmutable('2031-02-10')));

        $this->call('GET', '/api/rosters', token: $this->managerToken());

        self::assertResponseStatusCodeSame(200);
        $list = array_slice($this->body(), 0, 2);
        self::assertSame(['2031-02-10', '2031-02-03'], array_column($list, 'weekStart'));
        self::assertSame(['id', 'weekStart', 'status'], array_keys($list[0]));
        self::assertSame('draft', $list[0]['status']);
    }

    public function testGetReturnsTheRosterWithItsShiftsInOrder(): void
    {
        $roster = new Roster(new \DateTimeImmutable('2031-03-03'));
        self::getContainer()->get(RosterRepository::class)->save($roster);
        $shifts = self::getContainer()->get(ShiftRepository::class);
        $late = $this->shift($roster, '2031-03-04', '10:00', '18:00', Position::Kitchen, 1);
        $early = $this->shift($roster, '2031-03-03', '08:00', '14:00', Position::Barista, 2);
        $shifts->save($late);
        $shifts->save($early);

        $this->call('GET', '/api/rosters/'.$roster->getId(), token: $this->managerToken());

        self::assertResponseStatusCodeSame(200);
        $body = $this->body();
        self::assertSame('2031-03-03', $body['weekStart']);
        self::assertSame('draft', $body['status']);
        self::assertSame([
            [
                'id' => $early->getId(),
                'rosterId' => $roster->getId(),
                'date' => '2031-03-03',
                'startTime' => '08:00',
                'endTime' => '14:00',
                'requiredPosition' => 'barista',
                'headcount' => 2,
                'assignments' => [],
            ],
            [
                'id' => $late->getId(),
                'rosterId' => $roster->getId(),
                'date' => '2031-03-04',
                'startTime' => '10:00',
                'endTime' => '18:00',
                'requiredPosition' => 'kitchen',
                'headcount' => 1,
                'assignments' => [],
            ],
        ], $body['shifts']);
    }

    public function testUnknownRosterIs404(): void
    {
        $this->call('GET', '/api/rosters/999999', token: $this->managerToken());

        self::assertResponseStatusCodeSame(404);
        self::assertSame('ROSTER_NOT_FOUND', $this->body()['error']['code']);
    }

    private function shift(Roster $roster, string $date, string $start, string $end, Position $position, int $headcount): Shift
    {
        return new Shift(
            $roster,
            new \DateTimeImmutable($date),
            new \DateTimeImmutable($start),
            new \DateTimeImmutable($end),
            $position,
            $headcount,
        );
    }
}
