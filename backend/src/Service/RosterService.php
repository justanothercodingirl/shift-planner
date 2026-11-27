<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\CreateRosterRequest;
use App\Entity\Assignment;
use App\Entity\Roster;
use App\Entity\Shift;
use App\Exception\RosterAlreadyExistsException;
use App\Exception\RosterAlreadyPublishedException;
use App\Exception\RosterHasNoShiftsException;
use App\Exception\RosterNotFoundException;
use App\Exception\WeekStartNotMondayException;
use App\Repository\AssignmentRepository;
use App\Repository\RosterRepository;
use App\Repository\ShiftRepository;

class RosterService
{
    public function __construct(
        private readonly RosterRepository $rosters,
        private readonly ShiftRepository $shifts,
        private readonly AssignmentRepository $assignments,
        private readonly RosterNotifier $notifier,
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

    /**
     * Publishes a draft roster. Understaffed shifts are only a warning: the
     * manager may knowingly run short (DECISIONS.md).
     */
    public function publish(int $id): PublishResult
    {
        $roster = $this->get($id);
        if ($roster->isPublished()) {
            throw new RosterAlreadyPublishedException($id);
        }

        $shifts = $this->shiftsOf($roster);
        if ([] === $shifts) {
            throw new RosterHasNoShiftsException($id);
        }

        $warnings = $this->understaffedWarnings($shifts, $this->assignmentsOf($roster));
        $roster->publish();
        $this->rosters->save($roster);
        // After saving: the mails are queued, so no mail problem can undo or delay the publishing.
        $this->notifier->notifyPublished($roster);

        return new PublishResult($roster, $warnings);
    }

    /**
     * @param list<Shift>                  $shifts
     * @param array<int, list<Assignment>> $assignmentsByShift
     *
     * @return list<array{code: string, reason: string}> one warning per shift with fewer people than it needs
     */
    private function understaffedWarnings(array $shifts, array $assignmentsByShift): array
    {
        $warnings = [];
        foreach ($shifts as $shift) {
            $filled = count($assignmentsByShift[(int) $shift->getId()] ?? []);
            if ($filled >= $shift->getHeadcount()) {
                continue;
            }

            $warnings[] = ['code' => 'SHIFT_UNDERSTAFFED', 'reason' => sprintf(
                '%s %s to %s, %s: %d of %d filled',
                $shift->getDate()->format('D j M'),
                $shift->getStartTime()->format('H:i'),
                $shift->getEndTime()->format('H:i'),
                $shift->getRequiredPosition()->label(),
                $filled,
                $shift->getHeadcount(),
            )];
        }

        return $warnings;
    }

    /** @return list<Shift> */
    public function shiftsOf(Roster $roster): array
    {
        return $this->shifts->findByRosterOrdered($roster);
    }

    /** @return array<int, list<Assignment>> the roster's assignments, keyed by shift id */
    public function assignmentsOf(Roster $roster): array
    {
        $byShift = [];
        foreach ($this->assignments->findByRoster($roster) as $assignment) {
            $byShift[(int) $assignment->getShift()->getId()][] = $assignment;
        }

        return $byShift;
    }
}
