<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class LoginTest extends WebTestCase
{
    private const EMAIL = 'login-test@example.com';
    private const PASSWORD = 'correct-horse';

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();

        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();

        $hasher = $container->get(UserPasswordHasherInterface::class);
        $blank = new User(self::EMAIL, '', UserRole::Manager);
        $container->get(UserRepository::class)->save(
            new User(self::EMAIL, $hasher->hashPassword($blank, self::PASSWORD), UserRole::Manager),
        );
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testValidCredentialsReturnToken(): void
    {
        $this->login(self::EMAIL, self::PASSWORD);

        self::assertResponseStatusCodeSame(200);
        self::assertNotEmpty($this->responseData()['token'] ?? null);
    }

    public function testWrongPasswordIsRejected(): void
    {
        $this->login(self::EMAIL, 'wrong-password');

        self::assertResponseStatusCodeSame(401);
    }

    public function testUnknownEmailIsRejected(): void
    {
        $this->login('nobody@example.com', self::PASSWORD);

        self::assertResponseStatusCodeSame(401);
    }

    public function testTokenCarriesTheUserAndExpiresInOneHour(): void
    {
        $this->login(self::EMAIL, self::PASSWORD);
        $token = $this->responseData()['token'] ?? '';

        $payload = self::getContainer()->get(JWTTokenManagerInterface::class)->parse($token);

        self::assertSame(self::EMAIL, $payload['username']);
        self::assertSame(3600, $payload['exp'] - $payload['iat']);
    }

    private function login(string $email, string $password): void
    {
        $this->client->request(
            'POST',
            '/api/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => $email, 'password' => $password], JSON_THROW_ON_ERROR),
        );
    }

    /** @return array<string, mixed> */
    private function responseData(): array
    {
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);

        return is_array($data) ? $data : [];
    }
}
