<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public const DEMO_PASSWORD = 'password';

    private const USERS = [
        'manager' => ['manager@example.com', UserRole::Manager],
        'anna' => ['anna@example.com', UserRole::Employee],
        'ben' => ['ben@example.com', UserRole::Employee],
        'clara' => ['clara@example.com', UserRole::Employee],
    ];

    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        foreach (self::USERS as $reference => [$email, $role]) {
            $user = new User($email, '', $role);
            $user = new User($email, $this->hasher->hashPassword($user, self::DEMO_PASSWORD), $role);
            $manager->persist($user);
            $this->addReference('user-'.$reference, $user);
        }

        $manager->flush();
    }
}
