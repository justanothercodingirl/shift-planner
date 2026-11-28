<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Employee;
use App\Entity\SwapRequest;

/** The one place that decides what a swap request looks like in API responses. */
final class SwapRequestResponse
{
    /**
     * @return array{id: int|null, status: string, createdAt: string, assignmentId: int|null, shift: array<string, mixed>, requester: array{id: int|null, name: string}, target: array{id: int|null, name: string}}
     */
    public static function from(SwapRequest $request): array
    {
        $shift = $request->getShift();

        return [
            'id' => $request->getId(),
            'status' => $request->getStatus()->value,
            'createdAt' => $request->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'assignmentId' => $request->getAssignment()?->getId(),
            'shift' => [
                'id' => $shift->getId(),
                'rosterId' => $shift->getRoster()->getId(),
                'date' => $shift->getDate()->format('Y-m-d'),
                'startTime' => $shift->getStartTime()->format('H:i'),
                'endTime' => $shift->getEndTime()->format('H:i'),
                'requiredPosition' => $shift->getRequiredPosition()->value,
            ],
            'requester' => self::person($request->getRequester()),
            'target' => self::person($request->getTarget()),
        ];
    }

    /** @return array{id: int|null, name: string} */
    private static function person(Employee $employee): array
    {
        return ['id' => $employee->getId(), 'name' => $employee->getName()];
    }
}
