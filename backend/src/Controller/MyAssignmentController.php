<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\MyAssignmentResponse;
use App\Service\MyShiftsService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/** The signed-in employee's own data. Any login may call it; it only ever returns their own shifts. */
final class MyAssignmentController
{
    public function __construct(private readonly MyShiftsService $myShifts)
    {
    }

    #[Route('/api/me/assignments', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse(array_map(MyAssignmentResponse::from(...), $this->myShifts->upcoming()));
    }
}
