<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Mail\RosterChangedMailBuilder;
use App\Message\SendRosterChangedMail;
use App\Repository\EmployeeRepository;
use App\Repository\RosterRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Sends one "your roster changed" email. A failure is logged and thrown again,
 * so Messenger retries the job and finally parks it in the failed queue (same
 * as the publish mail); nobody in the web request ever sees a mail problem.
 */
#[AsMessageHandler]
final class SendRosterChangedMailHandler
{
    public function __construct(
        private readonly RosterRepository $rosters,
        private readonly EmployeeRepository $employees,
        private readonly RosterChangedMailBuilder $builder,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendRosterChangedMail $message): void
    {
        $roster = $this->rosters->find($message->rosterId);
        $employee = $this->employees->find($message->employeeId);
        if (null === $roster || null === $employee) {
            $this->logger->warning('Skipped a roster change mail: the roster or the employee no longer exists.', [
                'rosterId' => $message->rosterId,
                'employeeId' => $message->employeeId,
            ]);

            return;
        }

        try {
            $this->mailer->send($this->builder->build($roster, $employee, $message->description));
        } catch (\Throwable $error) {
            $this->logger->error('Could not send the roster change mail.', [
                'rosterId' => $message->rosterId,
                'employeeId' => $message->employeeId,
                'error' => $error->getMessage(),
            ]);

            throw $error;
        }
    }
}
