<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ShiftRequest;
use App\Entity\Assignment;
use App\Entity\Roster;
use App\Entity\Shift;
use App\Exception\InvalidShiftTimesException;
use App\Exception\ShiftNotFoundException;
use App\Exception\ShiftOutsideRosterWeekException;
use App\Repository\AssignmentRepository;
use App\Repository\ShiftRepository;

class ShiftService
{
    public function __construct(
        private readonly ShiftRepository $shifts,
        private readonly RosterService $rosters,
        private readonly AssignmentRepository $assignments,
    ) {
    }

    public function get(int $id): Shift
    {
        return $this->shifts->find($id) ?? throw new ShiftNotFoundException($id);
    }

    /** @return list<Assignment> the people assigned to the shift */
    public function assignmentsOf(Shift $shift): array
    {
        return $this->assignments->findByShift($shift);
    }

    public function create(int $rosterId, ShiftRequest $request): Shift
    {
        $roster = $this->rosters->get($rosterId);
        [$date, $start, $end] = $this->validated($roster, $request);

        $shift = new Shift($roster, $date, $start, $end, $request->requiredPosition, $request->headcount);
        $this->shifts->save($shift);

        return $shift;
    }

    public function update(int $id, ShiftRequest $request): Shift
    {
        $shift = $this->get($id);
        [$date, $start, $end] = $this->validated($shift->getRoster(), $request);

        $shift->update($date, $start, $end, $request->requiredPosition, $request->headcount);
        $this->shifts->save($shift);

        return $shift;
    }

    public function delete(int $id): void
    {
        $this->shifts->remove($this->get($id));
    }

    /**
     * Checks the shift rules and returns the parsed date, start and end.
     * A start later than the end is fine: the shift ends the next day.
     *
     * @return array{\DateTimeImmutable, \DateTimeImmutable, \DateTimeImmutable}
     */
    private function validated(Roster $roster, ShiftRequest $request): array
    {
        $date = $this->parse('!Y-m-d', $request->date);
        $start = $this->parse('!H:i', $request->startTime);
        $end = $this->parse('!H:i', $request->endTime);

        $weekStart = $roster->getWeekStart();
        $weekEnd = $weekStart->modify('+6 days');
        if ($date < $weekStart || $date > $weekEnd) {
            throw new ShiftOutsideRosterWeekException($request->date, $weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d'));
        }

        if ($start == $end) {
            throw new InvalidShiftTimesException($request->startTime);
        }

        return [$date, $start, $end];
    }

    private function parse(string $format, string $value): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromFormat($format, $value)
            ?: throw new \InvalidArgumentException(sprintf('"%s" does not match the format %s.', $value, $format));
    }
}
