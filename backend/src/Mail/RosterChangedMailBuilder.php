<?php

declare(strict_types=1);

namespace App\Mail;

use App\Entity\Employee;
use App\Entity\Roster;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/** Makes the "your roster changed" email: what changed for this person, and the updated whole roster as a PDF. */
class RosterChangedMailBuilder
{
    public function __construct(
        private readonly RosterPdfRenderer $pdf,
        #[Autowire('%app.mail_from%')]
        private readonly string $from,
    ) {
    }

    public function build(Roster $roster, Employee $employee, string $description): Email
    {
        $week = WeekLabel::of($roster->getWeekStart());

        return (new Email())
            ->from($this->from)
            ->to(new Address($employee->getUser()->getEmail(), $employee->getName()))
            ->subject(sprintf('Your roster for %s changed', $week))
            ->text(sprintf(
                "Hi %s,\n\n%s\n\nThe attached PDF shows the current roster for %s.\n",
                $employee->getName(),
                $description,
                $week,
            ))
            ->attach($this->pdf->render($roster), sprintf('roster-%s.pdf', $roster->getWeekStart()->format('Y-m-d')), 'application/pdf');
    }
}
