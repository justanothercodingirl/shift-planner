<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Http\ErrorResponseFactory;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * The firewall answers authentication failures before the normal exception
 * listener runs, so the JWT bundle's failure events get their own listener.
 * All four events share one base class, which is why one method body is enough.
 */
final class JwtErrorListener
{
    public function __construct(private readonly ErrorResponseFactory $errors)
    {
    }

    #[AsEventListener(event: Events::AUTHENTICATION_FAILURE)]
    public function onAuthenticationFailure(AuthenticationFailureEvent $event): void
    {
        $this->respond($event, 'INVALID_CREDENTIALS', 'Invalid email or password.');
    }

    #[AsEventListener(event: Events::JWT_NOT_FOUND)]
    public function onJwtNotFound(AuthenticationFailureEvent $event): void
    {
        $this->respond($event, 'UNAUTHENTICATED', 'Authentication is required.');
    }

    #[AsEventListener(event: Events::JWT_INVALID)]
    public function onJwtInvalid(AuthenticationFailureEvent $event): void
    {
        $this->respond($event, 'INVALID_TOKEN', 'The token is not valid.');
    }

    #[AsEventListener(event: Events::JWT_EXPIRED)]
    public function onJwtExpired(AuthenticationFailureEvent $event): void
    {
        $this->respond($event, 'TOKEN_EXPIRED', 'The token has expired. Please log in again.');
    }

    private function respond(AuthenticationFailureEvent $event, string $code, string $message): void
    {
        $event->setResponse($this->errors->create(401, $code, $message));
    }
}
