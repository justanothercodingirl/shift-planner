<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CreateSwapRequest;
use App\Dto\SwapCandidateResponse;
use App\Dto\SwapListQuery;
use App\Dto\SwapRequestResponse;
use App\Service\SwapService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Who may do what is decided in SwapRequestVoter (through the service) and by ROLE_MANAGER on approve and reject. */
final class SwapRequestController
{
    public function __construct(private readonly SwapService $swaps)
    {
    }

    #[Route('/api/assignments/{id}/swap-requests', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function request(int $id, #[MapRequestPayload] CreateSwapRequest $input): JsonResponse
    {
        return new JsonResponse(SwapRequestResponse::from($this->swaps->request($id, $input)), Response::HTTP_CREATED);
    }

    #[Route('/api/assignments/{id}/swap-candidates', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function candidates(int $id): JsonResponse
    {
        return new JsonResponse(array_map(SwapCandidateResponse::from(...), $this->swaps->candidates($id)));
    }

    #[Route('/api/swap-requests', methods: ['GET'])]
    public function list(#[MapQueryString] SwapListQuery $query = new SwapListQuery()): JsonResponse
    {
        return new JsonResponse(array_map(SwapRequestResponse::from(...), $this->swaps->list($query->status)));
    }

    #[Route('/api/swap-requests/{id}/accept', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function accept(int $id): JsonResponse
    {
        return new JsonResponse(SwapRequestResponse::from($this->swaps->accept($id)));
    }

    #[Route('/api/swap-requests/{id}/decline', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function decline(int $id): JsonResponse
    {
        return new JsonResponse(SwapRequestResponse::from($this->swaps->decline($id)));
    }

    #[Route('/api/swap-requests/{id}/cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(int $id): JsonResponse
    {
        return new JsonResponse(SwapRequestResponse::from($this->swaps->cancel($id)));
    }

    #[Route('/api/swap-requests/{id}/approve', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_MANAGER')]
    public function approve(int $id): JsonResponse
    {
        return new JsonResponse(SwapRequestResponse::from($this->swaps->approve($id)));
    }

    #[Route('/api/swap-requests/{id}/reject', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_MANAGER')]
    public function reject(int $id): JsonResponse
    {
        return new JsonResponse(SwapRequestResponse::from($this->swaps->reject($id)));
    }
}
