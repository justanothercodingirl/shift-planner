<?php

declare(strict_types=1);

namespace App\Mail;

use App\Entity\SwapRequest;
use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Makes the email for one step of a swap, for one recipient. Plain text, no
 * attachment. The wording avoids "his" and "her": people are named instead.
 */
class SwapMailBuilder
{
    public function __construct(
        #[Autowire('%app.mail_from%')]
        private readonly string $from,
    ) {
    }

    public function build(SwapRequest $request, User $recipient, SwapMailEvent $event): Email
    {
        $requester = $request->getRequester()->getName();
        $colleague = $request->getTarget()->getName();
        $name = $this->nameOf($request, $recipient);

        return (new Email())
            ->from($this->from)
            ->to(new Address($recipient->getEmail(), $name ?? ''))
            ->subject($this->subject($event, $requester, $colleague))
            ->text(sprintf(
                "%s\n\n%s\n",
                null === $name ? 'Hello,' : sprintf('Hi %s,', $name),
                $this->body($event, $requester, $colleague, ShiftDescription::of($request->getShift())),
            ));
    }

    /** The name of the recipient when they are one of the two people in the swap; managers get a plain greeting. */
    private function nameOf(SwapRequest $request, User $recipient): ?string
    {
        foreach ([$request->getRequester(), $request->getTarget()] as $employee) {
            if ($employee->getUser()->getUserIdentifier() === $recipient->getUserIdentifier()) {
                return $employee->getName();
            }
        }

        return null;
    }

    private function subject(SwapMailEvent $event, string $requester, string $colleague): string
    {
        return match ($event) {
            SwapMailEvent::Requested => sprintf('%s asks you to take over a shift', $requester),
            SwapMailEvent::Accepted => sprintf("Swap waiting for your approval: %s takes over %s's shift", $colleague, $requester),
            SwapMailEvent::Declined => sprintf('%s declined your swap request', $colleague),
            SwapMailEvent::Cancelled => sprintf('Swap request withdrawn by %s', $requester),
            SwapMailEvent::ShiftChanged => 'Swap request cancelled: the shift changed',
            SwapMailEvent::Approved => sprintf("Swap approved: %s now has %s's shift", $colleague, $requester),
            SwapMailEvent::Rejected => sprintf('Swap rejected: the shift stays with %s', $requester),
        };
    }

    private function body(SwapMailEvent $event, string $requester, string $colleague, string $shift): string
    {
        return match ($event) {
            SwapMailEvent::Requested => sprintf("%s asks you to take over this shift:\n%s\n\nOpen the app to accept or decline.", $requester, $shift),
            SwapMailEvent::Accepted => sprintf("%s agreed to take over %s's shift:\n%s\n\nThe request is waiting for your approval. Open the app to approve or reject it.", $colleague, $requester, $shift),
            SwapMailEvent::Declined => sprintf("%s declined to take over your shift:\n%s\n\nThe shift stays yours.", $colleague, $shift),
            SwapMailEvent::Cancelled => sprintf("%s withdrew the swap request for this shift:\n%s", $requester, $shift),
            SwapMailEvent::ShiftChanged => sprintf("The swap request for this shift was cancelled, because the shift was changed in the meantime:\n%s", $shift),
            SwapMailEvent::Approved => sprintf("The swap was approved: %s now works this shift instead of %s:\n%s", $colleague, $requester, $shift),
            SwapMailEvent::Rejected => sprintf("The swap request for this shift was rejected:\n%s\n\nThe shift stays with %s.", $shift, $requester),
        };
    }
}
