<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Base for API tests: a rolled-back transaction, a manager and an employee login,
 * and small helpers to call the API with a token and read the JSON answer.
 */
abstract class ApiTestCase extends WebTestCase
{
    protected const PASSWORD = 'correct-horse';
    protected const MANAGER_EMAIL = 'api-manager@example.com';
    protected const EMPLOYEE_EMAIL = 'api-employee@example.com';

    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();

        $this->saveUser(self::MANAGER_EMAIL, UserRole::Manager);
        $this->saveUser(self::EMPLOYEE_EMAIL, UserRole::Employee);
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    protected function managerToken(): string
    {
        return $this->login(self::MANAGER_EMAIL);
    }

    protected function employeeToken(): string
    {
        return $this->login(self::EMPLOYEE_EMAIL);
    }

    protected function login(string $email, string $password = self::PASSWORD): string
    {
        $this->call('POST', '/api/login', ['email' => $email, 'password' => $password]);
        $token = $this->body()['token'] ?? '';
        self::assertIsString($token);

        return $token;
    }

    /** @param array<string, mixed>|null $json */
    protected function call(string $method, string $uri, ?array $json = null, ?string $token = null): void
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }

        $this->client->request(
            $method,
            $uri,
            server: $server,
            content: null === $json ? null : json_encode($json, JSON_THROW_ON_ERROR),
        );
    }

    /** @return array<mixed> */
    protected function body(): array
    {
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);

        return is_array($data) ? $data : [];
    }

    protected function saveUser(string $email, UserRole $role): User
    {
        $container = self::getContainer();
        $hash = $container->get(UserPasswordHasherInterface::class)
            ->hashPassword(new User($email, '', $role), self::PASSWORD);
        $user = new User($email, $hash, $role);
        $container->get(UserRepository::class)->save($user);

        return $user;
    }
}
