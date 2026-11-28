<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Mail\SwapMailBuilder;
use App\Message\SendSwapMail;
use App\Repository\SwapRequestRepository;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Sends one swap mail. A failure is logged and thrown again, so Messenger
 * retries the job and finally parks it in the failed queue (same as the other
 * mails); nobody in the web request ever sees a mail problem.
 */
#[AsMessageHandler]
final class SendSwapMailHandler
{
    public function __construct(
        private readonly SwapRequestRepository $swaps,
        private readonly UserRepository $users,
        private readonly SwapMailBuilder $builder,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendSwapMail $message): void
    {
        $request = $this->swaps->find($message->swapRequestId);
        $recipient = $this->users->find($message->recipientUserId);
        if (null === $request || null === $recipient) {
            $this->logger->warning('Skipped a swap mail: the request or the recipient no longer exists.', [
                'swapRequestId' => $message->swapRequestId,
                'recipientUserId' => $message->recipientUserId,
            ]);

            return;
        }

        try {
            $this->mailer->send($this->builder->build($request, $recipient, $message->event));
        } catch (\Throwable $error) {
            $this->logger->error('Could not send the swap mail.', [
                'swapRequestId' => $message->swapRequestId,
                'recipientUserId' => $message->recipientUserId,
                'error' => $error->getMessage(),
            ]);

            throw $error;
        }
    }
}
