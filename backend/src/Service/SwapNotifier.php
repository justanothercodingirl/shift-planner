<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Employee;
use App\Entity\SwapRequest;
use App\Mail\SwapMailEvent;
use App\Message\SendSwapMail;
use App\Repository\UserRepository;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Tells people about each step of a swap, by queueing one mail job per recipient.
 * Deactivated employees get nothing. SwapService calls this after saving a step.
 */
class SwapNotifier
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly UserRepository $users,
    ) {
    }

    /** The colleague is asked. */
    public function requested(SwapRequest $request): void
    {
        $this->tellEmployees([$request->getTarget()], $request, SwapMailEvent::Requested);
    }

    /** The colleague agreed: every manager has a request to decide. */
    public function accepted(SwapRequest $request): void
    {
        $this->tellManagers($request, SwapMailEvent::Accepted);
    }

    public function declined(SwapRequest $request): void
    {
        $this->tellEmployees([$request->getRequester()], $request, SwapMailEvent::Declined);
    }

    /** The requester withdrew; managers only hear about it if they had already been asked to decide. */
    public function cancelled(SwapRequest $request, bool $wasAccepted): void
    {
        $this->tellEmployees([$request->getTarget()], $request, SwapMailEvent::Cancelled);
        if ($wasAccepted) {
            $this->tellManagers($request, SwapMailEvent::Cancelled);
        }
    }

    public function cancelledBecauseShiftChanged(SwapRequest $request): void
    {
        $this->tellEmployees([$request->getRequester(), $request->getTarget()], $request, SwapMailEvent::ShiftChanged);
    }

    public function approved(SwapRequest $request): void
    {
        $this->tellEmployees([$request->getRequester(), $request->getTarget()], $request, SwapMailEvent::Approved);
    }

    public function rejected(SwapRequest $request): void
    {
        $this->tellEmployees([$request->getRequester(), $request->getTarget()], $request, SwapMailEvent::Rejected);
    }

    /** @param list<Employee> $employees */
    private function tellEmployees(array $employees, SwapRequest $request, SwapMailEvent $event): void
    {
        foreach ($employees as $employee) {
            if ($employee->isActive()) {
                $this->queue($request, (int) $employee->getUser()->getId(), $event);
            }
        }
    }

    private function tellManagers(SwapRequest $request, SwapMailEvent $event): void
    {
        foreach ($this->users->findManagers() as $manager) {
            $this->queue($request, (int) $manager->getId(), $event);
        }
    }

    private function queue(SwapRequest $request, int $recipientUserId, SwapMailEvent $event): void
    {
        $this->bus->dispatch(new SendSwapMail((int) $request->getId(), $recipientUserId, $event));
    }
}
