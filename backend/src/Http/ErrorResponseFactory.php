<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * The one place that knows the shared error shape:
 * { "error": { "code", "message", "violations": [{ "code", "reason" }] } }.
 */
class ErrorResponseFactory
{
    /**
     * @param list<array{code: string, reason: string}> $violations
     */
    public function create(int $status, string $code, string $message, array $violations = []): JsonResponse
    {
        $error = ['code' => $code, 'message' => $message];

        if ([] !== $violations) {
            $error['violations'] = $violations;
        }

        return new JsonResponse(['error' => $error], $status);
    }
}
