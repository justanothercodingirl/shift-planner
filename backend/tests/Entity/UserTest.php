<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\User;
use App\Enum\UserRole;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testManagerGetsManagerSecurityRole(): void
    {
        $user = new User('m@example.com', 'hash', UserRole::Manager);

        self::assertSame(['ROLE_MANAGER'], $user->getRoles());
    }

    public function testEmployeeGetsEmployeeSecurityRole(): void
    {
        $user = new User('e@example.com', 'hash', UserRole::Employee);

        self::assertSame(['ROLE_EMPLOYEE'], $user->getRoles());
    }

    public function testIdentifierIsEmail(): void
    {
        $user = new User('e@example.com', 'hash', UserRole::Employee);

        self::assertSame('e@example.com', $user->getUserIdentifier());
    }
}
