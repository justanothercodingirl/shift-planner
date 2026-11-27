<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Roster;
use App\Message\SendRosterPublishedMail;
use App\Repository\EmployeeRepository;
use Symfony\Component\Messenger\MessageBusInterface;

/** Tells the employees about a published roster, by queueing one mail job each. */
class RosterNotifier
{
    public function __construct(
        private readonly EmployeeRepository $employees,
        private readonly MessageBusInterface $bus,
    ) {
    }

    /** Queues one job per active employee; a worker sends the mails later. */
    public function notifyPublished(Roster $roster): void
    {
        if (!$roster->isPublished()) {
            throw new \LogicException('Only a published roster can be announced.');
        }

        foreach ($this->employees->findActiveOrderedByName() as $employee) {
            $this->bus->dispatch(new SendRosterPublishedMail((int) $roster->getId(), (int) $employee->getId()));
        }
    }
}
