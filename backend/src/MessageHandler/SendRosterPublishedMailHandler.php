<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Mail\RosterMailBuilder;
use App\Message\SendRosterPublishedMail;
use App\Repository\EmployeeRepository;
use App\Repository\RosterRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Sends one roster email. A failure is logged and thrown again, so Messenger
 * retries the job and finally parks it in the failed queue; nobody in the web
 * request ever sees a mail problem (SPEC section 7).
 */
#[AsMessageHandler]
final class SendRosterPublishedMailHandler
{
    public function __construct(
        private readonly RosterRepository $rosters,
        private readonly EmployeeRepository $employees,
        private readonly RosterMailBuilder $builder,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendRosterPublishedMail $message): void
    {
        $roster = $this->rosters->find($message->rosterId);
        $employee = $this->employees->find($message->employeeId);
        if (null === $roster || null === $employee) {
            // Nothing to retry: the data is gone, so the job is simply dropped.
            $this->logger->warning('Skipped a roster mail: the roster or the employee no longer exists.', [
                'rosterId' => $message->rosterId,
                'employeeId' => $message->employeeId,
            ]);

            return;
        }

        try {
            $this->mailer->send($this->builder->build($roster, $employee));
        } catch (\Throwable $error) {
            $this->logger->error('Could not send the roster mail.', [
                'rosterId' => $message->rosterId,
                'employeeId' => $message->employeeId,
                'error' => $error->getMessage(),
            ]);

            throw $error;
        }
    }
}
