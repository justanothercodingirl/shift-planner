<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\CreateRosterRequest;
use App\Entity\Roster;
use App\Entity\Shift;
use App\Exception\RosterAlreadyExistsException;
use App\Exception\RosterNotFoundException;
use App\Exception\WeekStartNotMondayException;
use App\Repository\RosterRepository;
use App\Repository\ShiftRepository;

class RosterService
{
    public function __construct(
        private readonly RosterRepository $rosters,
        private readonly ShiftRepository $shifts,
    ) {
    }

    public function create(CreateRosterRequest $request): Roster
    {
        $weekStart = \DateTimeImmutable::createFromFormat('!Y-m-d', $request->weekStart)
            ?: throw new \InvalidArgumentException('weekStart must be a date in Y-m-d format.');

        if ('1' !== $weekStart->format('N')) {
            throw new WeekStartNotMondayException($request->weekStart);
        }

        if (null !== $this->rosters->findOneByWeekStart($weekStart)) {
            throw new RosterAlreadyExistsException($request->weekStart);
        }

        $roster = new Roster($weekStart);
        $this->rosters->save($roster);

        return $roster;
    }

    /** @return list<Roster> */
    public function list(): array
    {
        return $this->rosters->findAllNewestFirst();
    }

    public function get(int $id): Roster
    {
        return $this->rosters->find($id) ?? throw new RosterNotFoundException($id);
    }

    /** @return list<Shift> */
    public function shiftsOf(Roster $roster): array
    {
        return $this->shifts->findByRosterOrdered($roster);
    }
}
