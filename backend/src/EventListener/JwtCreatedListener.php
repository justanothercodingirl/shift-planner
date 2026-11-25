<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Repository\EmployeeRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Adds the id of the login's Employee to the token, so the browser knows which
 * availability URLs are "its own". It is only used to build URLs and for display:
 * the server still decides who may do what (AvailabilityVoter).
 */
#[AsEventListener(event: Events::JWT_CREATED)]
final class JwtCreatedListener
{
    public function __construct(private readonly EmployeeRepository $employees)
    {
    }

    public function __invoke(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        $employee = $user instanceof User ? $this->employees->findOneByUser($user) : null;

        $event->setData([...$event->getData(), 'employeeId' => $employee?->getId()]);
    }
}
