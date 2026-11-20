<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CreateEmployeeRequest;
use App\Dto\EmployeeResponse;
use App\Dto\UpdateEmployeeRequest;
use App\Service\EmployeeService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/employees')]
#[IsGranted('ROLE_MANAGER')]
final class EmployeeController
{
    public function __construct(private readonly EmployeeService $employees)
    {
    }

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse(array_map(EmployeeResponse::from(...), $this->employees->list()));
    }

    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        return new JsonResponse(EmployeeResponse::from($this->employees->get($id)));
    }

    #[Route('', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateEmployeeRequest $request): JsonResponse
    {
        return new JsonResponse(
            EmployeeResponse::from($this->employees->create($request)),
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function update(int $id, #[MapRequestPayload] UpdateEmployeeRequest $request): JsonResponse
    {
        return new JsonResponse(EmployeeResponse::from($this->employees->update($id, $request)));
    }

    #[Route('/{id}/deactivate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deactivate(int $id): JsonResponse
    {
        return new JsonResponse(EmployeeResponse::from($this->employees->deactivate($id)));
    }
}
