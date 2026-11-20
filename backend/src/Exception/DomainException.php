<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Base class for business-rule errors thrown by services
 * (for example RosterAlreadyPublishedException). The exception listener
 * turns them into the shared JSON error shape.
 */
abstract class DomainException extends \RuntimeException
{
    /**
     * @param list<array{code: string, reason: string}> $violations
     */
    public function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly string $errorCode,
        private readonly array $violations = [],
    ) {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /** @return list<array{code: string, reason: string}> */
    public function getViolations(): array
    {
        return $this->violations;
    }
}
