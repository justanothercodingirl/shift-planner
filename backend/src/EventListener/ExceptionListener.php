<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Exception\DomainException;
use App\Http\ErrorResponseFactory;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Turns every exception on an /api request into the shared JSON error shape.
 * Symfony's own error listener already logs the exception, so we do not log again.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION)]
final class ExceptionListener
{
    private const HTTP_CODES = [
        401 => 'UNAUTHENTICATED',
        403 => 'FORBIDDEN',
        404 => 'NOT_FOUND',
        405 => 'METHOD_NOT_ALLOWED',
    ];

    public function __construct(private readonly ErrorResponseFactory $errors)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
            return;
        }

        $event->setResponse($this->toResponse($event->getThrowable()));
    }

    private function toResponse(\Throwable $exception): Response
    {
        if ($exception instanceof DomainException) {
            return $this->errors->create(
                $exception->getStatusCode(),
                $exception->getErrorCode(),
                $exception->getMessage(),
                $exception->getViolations(),
            );
        }

        $validation = $exception instanceof ValidationFailedException ? $exception : $exception->getPrevious();
        if ($validation instanceof ValidationFailedException) {
            return $this->validationResponse($validation);
        }

        if ($exception instanceof AccessDeniedException) {
            return $this->errors->create(403, 'FORBIDDEN', Response::$statusTexts[403]);
        }

        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();

            return $this->errors->create(
                $status,
                self::HTTP_CODES[$status] ?? 'HTTP_ERROR',
                Response::$statusTexts[$status] ?? 'Error',
            );
        }

        return $this->errors->create(500, 'INTERNAL_ERROR', 'Something went wrong on our side.');
    }

    private function validationResponse(ValidationFailedException $exception): Response
    {
        $violations = [];
        foreach ($exception->getViolations() as $violation) {
            $violations[] = [
                'code' => '' !== $violation->getPropertyPath() ? $violation->getPropertyPath() : 'value',
                'reason' => (string) $violation->getMessage(),
            ];
        }

        return $this->errors->create(422, 'VALIDATION_FAILED', 'The request data is not valid.', $violations);
    }
}
