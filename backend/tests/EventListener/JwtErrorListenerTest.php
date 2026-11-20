<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\EventListener\JwtErrorListener;
use App\Http\ErrorResponseFactory;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTExpiredEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTInvalidEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTNotFoundEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class JwtErrorListenerTest extends TestCase
{
    /** @return iterable<string, array{0: string, 1: string}> */
    public static function failures(): iterable
    {
        yield 'bad login' => ['onAuthenticationFailure', 'INVALID_CREDENTIALS'];
        yield 'token missing' => ['onJwtNotFound', 'UNAUTHENTICATED'];
        yield 'token invalid' => ['onJwtInvalid', 'INVALID_TOKEN'];
        yield 'token expired' => ['onJwtExpired', 'TOKEN_EXPIRED'];
    }

    #[DataProvider('failures')]
    public function testFailureBecomesSharedShape401(string $method, string $expectedCode): void
    {
        $event = match ($method) {
            'onAuthenticationFailure' => new AuthenticationFailureEvent(new AuthenticationException(), null),
            'onJwtNotFound' => new JWTNotFoundEvent(new AuthenticationException(), new JsonResponse()),
            'onJwtInvalid' => new JWTInvalidEvent(new AuthenticationException(), new JsonResponse()),
            default => new JWTExpiredEvent(new AuthenticationException(), new JsonResponse()),
        };

        (new JwtErrorListener(new ErrorResponseFactory()))->$method($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(401, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertSame($expectedCode, $body['error']['code']);
        self::assertArrayHasKey('message', $body['error']);
        self::assertArrayNotHasKey('violations', $body['error']);
    }
}
