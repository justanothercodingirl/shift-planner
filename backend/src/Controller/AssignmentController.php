<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\AssignmentResponse;
use App\Dto\AssignRequest;
use App\Service\AssignmentService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_MANAGER')]
final class AssignmentController
{
    public function __construct(private readonly AssignmentService $assignments)
    {
    }

    #[Route('/api/shifts/{id}/assignments', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function assign(int $id, #[MapRequestPayload] AssignRequest $request): JsonResponse
    {
        return new JsonResponse(
            AssignmentResponse::from($this->assignments->assign($id, $request->employeeId)),
            Response::HTTP_CREATED,
        );
    }

    #[Route('/api/assignments/{id}', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function unassign(int $id): Response
    {
        $this->assignments->unassign($id);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
