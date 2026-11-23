<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ShiftRequest;
use App\Dto\ShiftResponse;
use App\Service\ShiftService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_MANAGER')]
final class ShiftController
{
    public function __construct(private readonly ShiftService $shifts)
    {
    }

    #[Route('/api/rosters/{rosterId}/shifts', requirements: ['rosterId' => '\d+'], methods: ['POST'])]
    public function create(int $rosterId, #[MapRequestPayload] ShiftRequest $request): JsonResponse
    {
        return new JsonResponse(
            ShiftResponse::from($this->shifts->create($rosterId, $request)),
            Response::HTTP_CREATED,
        );
    }

    #[Route('/api/shifts/{id}', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function update(int $id, #[MapRequestPayload] ShiftRequest $request): JsonResponse
    {
        $shift = $this->shifts->update($id, $request);

        return new JsonResponse(ShiftResponse::from($shift, $this->shifts->assignmentsOf($shift)));
    }

    #[Route('/api/shifts/{id}', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id): Response
    {
        $this->shifts->delete($id);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
