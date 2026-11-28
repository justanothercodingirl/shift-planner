<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ColleagueResponse;
use App\Service\ColleagueService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/** Open to every login: it only lists names and positions, so employees can pick someone for a swap. */
final class ColleagueController
{
    public function __construct(private readonly ColleagueService $colleagues)
    {
    }

    #[Route('/api/colleagues', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse(array_map(ColleagueResponse::from(...), $this->colleagues->list()));
    }
}
