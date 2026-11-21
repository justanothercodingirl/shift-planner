<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CreateRosterRequest;
use App\Dto\RosterResponse;
use App\Service\RosterService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/rosters')]
#[IsGranted('ROLE_MANAGER')]
final class RosterController
{
    public function __construct(private readonly RosterService $rosters)
    {
    }

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse(array_map(RosterResponse::from(...), $this->rosters->list()));
    }

    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $roster = $this->rosters->get($id);

        return new JsonResponse(RosterResponse::withShifts($roster, $this->rosters->shiftsOf($roster)));
    }

    #[Route('', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateRosterRequest $request): JsonResponse
    {
        return new JsonResponse(
            RosterResponse::withShifts($this->rosters->create($request), []),
            Response::HTTP_CREATED,
        );
    }
}
