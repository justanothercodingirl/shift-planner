<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\AvailabilityRequest;
use App\Dto\AvailabilityResponse;
use App\Service\AvailabilityService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/** Who may do what is decided in AvailabilityVoter, through the service. */
final class AvailabilityController
{
    public function __construct(private readonly AvailabilityService $availabilities)
    {
    }

    #[Route('/api/employees/{employeeId}/availabilities', requirements: ['employeeId' => '\d+'], methods: ['GET'])]
    public function list(int $employeeId): JsonResponse
    {
        return new JsonResponse(array_map(AvailabilityResponse::from(...), $this->availabilities->list($employeeId)));
    }

    #[Route('/api/employees/{employeeId}/availabilities', requirements: ['employeeId' => '\d+'], methods: ['POST'])]
    public function create(int $employeeId, #[MapRequestPayload] AvailabilityRequest $request): JsonResponse
    {
        return new JsonResponse(
            AvailabilityResponse::from($this->availabilities->create($employeeId, $request)),
            Response::HTTP_CREATED,
        );
    }

    #[Route('/api/availabilities/{id}', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function update(int $id, #[MapRequestPayload] AvailabilityRequest $request): JsonResponse
    {
        return new JsonResponse(AvailabilityResponse::from($this->availabilities->update($id, $request)));
    }

    #[Route('/api/availabilities/{id}', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id): Response
    {
        $this->availabilities->delete($id);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
