<?php

declare(strict_types=1);

namespace App\Mail;

use App\Entity\Assignment;
use App\Entity\Employee;
use App\Entity\Roster;
use App\Entity\Shift;
use App\Service\RosterService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/** Makes the "your roster is published" email for one employee: their own shifts in the text, the whole roster as a PDF. */
class RosterMailBuilder
{
    public function __construct(
        private readonly RosterService $rosters,
        private readonly RosterPdfRenderer $pdf,
        #[Autowire('%app.mail_from%')]
        private readonly string $from,
    ) {
    }

    public function build(Roster $roster, Employee $employee): Email
    {
        $week = WeekLabel::of($roster->getWeekStart());

        return (new Email())
            ->from($this->from)
            ->to(new Address($employee->getUser()->getEmail(), $employee->getName()))
            ->subject(sprintf('Roster for %s is published', $week))
            ->text($this->text($roster, $employee, $week))
            ->attach($this->pdf->render($roster), sprintf('roster-%s.pdf', $roster->getWeekStart()->format('Y-m-d')), 'application/pdf');
    }

    private function text(Roster $roster, Employee $employee, string $week): string
    {
        $lines = $this->ownShiftLines($roster, $employee);

        return sprintf("Hi %s,\n\nThe roster for %s has been published.\n\n", $employee->getName(), $week)
            .([] === $lines ? "You have no shifts this week.\n" : "Your shifts:\n".implode("\n", $lines)."\n")
            ."\nThe attached PDF shows the whole roster. If you would like to swap a shift or need someone to cover,"
            ." you can ask your colleagues directly.\n";
    }

    /** @return list<string> one line per shift the employee works, in shift order */
    private function ownShiftLines(Roster $roster, Employee $employee): array
    {
        $assignments = $this->rosters->assignmentsOf($roster);
        $lines = [];
        foreach ($this->rosters->shiftsOf($roster) as $shift) {
            if ($this->works($employee, $assignments[(int) $shift->getId()] ?? [])) {
                $lines[] = $this->lineOf($shift);
            }
        }

        return $lines;
    }

    /** @param list<Assignment> $assignments */
    private function works(Employee $employee, array $assignments): bool
    {
        foreach ($assignments as $assignment) {
            if ($assignment->getEmployee()->getId() === $employee->getId()) {
                return true;
            }
        }

        return false;
    }

    private function lineOf(Shift $shift): string
    {
        $overnight = $shift->getEndTime()->format('H:i') < $shift->getStartTime()->format('H:i');

        return sprintf(
            '- %s, %s–%s%s, %s',
            $shift->getDate()->format('D j M'),
            $shift->getStartTime()->format('H:i'),
            $shift->getEndTime()->format('H:i'),
            $overnight ? ' (+1 day)' : '',
            $shift->getRequiredPosition()->label(),
        );
    }
}
