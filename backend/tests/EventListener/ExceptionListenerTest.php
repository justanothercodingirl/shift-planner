<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\EventListener\ExceptionListener;
use App\Exception\DomainException;
use App\Http\ErrorResponseFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class ExceptionListenerTest extends TestCase
{
    public function testDomainExceptionKeepsItsStatusCodeAndViolations(): void
    {
        $exception = new class('Too many hours.', 422, 'WEEKLY_CAP', [['code' => 'WEEKLY_CAP', 'reason' => 'This would bring Anna to 24 of 20 hours']]) extends DomainException {
        };

        [$status, $body] = $this->handle($exception);

        self::assertSame(422, $status);
        self::assertSame([
            'error' => [
                'code' => 'WEEKLY_CAP',
                'message' => 'Too many hours.',
                'violations' => [
                    ['code' => 'WEEKLY_CAP', 'reason' => 'This would bring Anna to 24 of 20 hours'],
                ],
            ],
        ], $body);
    }

    public function testDomainExceptionWithoutViolationsHasNoViolationsKey(): void
    {
        $exception = new class('Roster already published.', 409, 'ROSTER_ALREADY_PUBLISHED') extends DomainException {
        };

        [$status, $body] = $this->handle($exception);

        self::assertSame(409, $status);
        self::assertSame(
            ['error' => ['code' => 'ROSTER_ALREADY_PUBLISHED', 'message' => 'Roster already published.']],
            $body,
        );
    }

    public function testValidationFailureBecomesOneViolationPerBrokenConstraint(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('This value should not be blank.', null, [], null, 'email', ''),
            new ConstraintViolation('This value should be 1 or more.', null, [], null, 'headcount', 0),
        ]);
        $exception = new UnprocessableEntityHttpException('Validation failed', new ValidationFailedException(null, $violations));

        [$status, $body] = $this->handle($exception);

        self::assertSame(422, $status);
        self::assertSame('VALIDATION_FAILED', $body['error']['code']);
        self::assertSame([
            ['code' => 'email', 'reason' => 'This value should not be blank.'],
            ['code' => 'headcount', 'reason' => 'This value should be 1 or more.'],
        ], $body['error']['violations']);
    }

    public function testNotFoundIsMapped(): void
    {
        [$status, $body] = $this->handle(new NotFoundHttpException('No route found for "GET /api/x"'));

        self::assertSame(404, $status);
        self::assertSame('NOT_FOUND', $body['error']['code']);
    }

    public function testAccessDeniedIsMapped(): void
    {
        [$status, $body] = $this->handle(new AccessDeniedHttpException('Access Denied.'));

        self::assertSame(403, $status);
        self::assertSame('FORBIDDEN', $body['error']['code']);
    }

    public function testMethodNotAllowedIsMapped(): void
    {
        [$status, $body] = $this->handle(new MethodNotAllowedHttpException(['POST']));

        self::assertSame(405, $status);
        self::assertSame('METHOD_NOT_ALLOWED', $body['error']['code']);
    }

    public function testUnexpectedExceptionBecomesGeneric500WithoutLeakingDetails(): void
    {
        [$status, $body] = $this->handle(new \RuntimeException('connection to db with password hunter2 failed'));

        self::assertSame(500, $status);
        self::assertSame('INTERNAL_ERROR', $body['error']['code']);
        self::assertStringNotContainsString('hunter2', json_encode($body, JSON_THROW_ON_ERROR));
    }

    public function testUnexpectedExceptionIsLoggedWithItsRealMessage(): void
    {
        $exception = new \RuntimeException('the real cause');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                self::stringContains('the real cause'),
                self::callback(static fn (array $context): bool => $context['exception'] === $exception),
            );

        $this->handle($exception, $logger);
    }

    public function testExpectedErrorsAreNotLoggedAsErrors(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $this->handle(new NotFoundHttpException(), $logger);
        $this->handle(new AccessDeniedHttpException(), $logger);
    }

    public function testNonApiPathsAreLeftAlone(): void
    {
        $event = $this->event(new NotFoundHttpException(), '/some/page');

        (new ExceptionListener(new ErrorResponseFactory(), new NullLogger()))($event);

        self::assertNull($event->getResponse());
    }

    /** @return array{0: int, 1: array<string, mixed>} */
    private function handle(\Throwable $exception, ?LoggerInterface $logger = null): array
    {
        $event = $this->event($exception, '/api/anything');

        (new ExceptionListener(new ErrorResponseFactory(), $logger ?? new NullLogger()))($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        $body = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($body);

        return [$response->getStatusCode(), $body];
    }

    private function event(\Throwable $exception, string $path): ExceptionEvent
    {
        return new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create($path),
            HttpKernelInterface::MAIN_REQUEST,
            $exception,
        );
    }
}
