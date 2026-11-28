<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Assignment;
use App\Entity\Employee;
use App\Entity\Roster;
use App\Entity\Shift;
use App\Mail\ShiftDescription;
use App\Message\SendRosterChangedMail;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Tells employees about changes made after a roster was published, by queueing
 * one mail job per affected person. Draft rosters and deactivated employees
 * never get a mail. The services call this after they have saved their change.
 */
class RosterChangeNotifier
{
    public function __construct(private readonly MessageBusInterface $bus)
    {
    }

    public function assigned(Assignment $assignment): void
    {
        $this->tell(
            $assignment->getShift()->getRoster(),
            $assignment->getEmployee(),
            sprintf('You were added to %s.', ShiftDescription::of($assignment->getShift())),
        );
    }

    public function unassigned(Assignment $assignment): void
    {
        $this->tell(
            $assignment->getShift()->getRoster(),
            $assignment->getEmployee(),
            sprintf('You were taken off %s.', ShiftDescription::of($assignment->getShift())),
        );
    }

    /**
     * @param string           $before      the shift in words before the change
     * @param string           $after       the shift in words after the change
     * @param list<Assignment> $assignments the people on the shift
     */
    public function shiftChanged(Shift $shift, string $before, string $after, array $assignments): void
    {
        if ($before === $after) {
            return; // for example only the headcount changed: nobody's own shift is different
        }

        foreach ($assignments as $assignment) {
            $this->tell($shift->getRoster(), $assignment->getEmployee(), sprintf('Your shift %s changed to %s.', $before, $after));
        }
    }

    /**
     * @param string           $description the shift in words, taken before it was deleted
     * @param list<Assignment> $assignments the people who were on it
     */
    public function shiftDeleted(Shift $shift, string $description, array $assignments): void
    {
        foreach ($assignments as $assignment) {
            $this->tell($shift->getRoster(), $assignment->getEmployee(), sprintf('Your shift %s was cancelled.', $description));
        }
    }

    private function tell(Roster $roster, Employee $employee, string $description): void
    {
        if (!$roster->isPublished() || !$employee->isActive()) {
            return;
        }

        $this->bus->dispatch(new SendRosterChangedMail((int) $roster->getId(), (int) $employee->getId(), $description));
    }
}
