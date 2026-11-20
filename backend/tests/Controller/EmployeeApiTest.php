<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Employee;
use App\Entity\User;
use App\Enum\ContractType;
use App\Enum\Position;
use App\Enum\UserRole;
use App\Repository\EmployeeRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class EmployeeApiTest extends WebTestCase
{
    private const PASSWORD = 'correct-horse';

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Employee $anna;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();

        $this->saveUser('api-manager@example.com', UserRole::Manager);
        $annaUser = $this->saveUser('api-anna@example.com', UserRole::Employee);
        $this->anna = new Employee($annaUser, 'Anna Api', Position::Barista, ContractType::PartTime, 20);
        self::getContainer()->get(EmployeeRepository::class)->save($this->anna);
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testRequestWithoutTokenIsUnauthenticated(): void
    {
        $this->call('GET', '/api/employees');

        self::assertResponseStatusCodeSame(401);
        self::assertSame('UNAUTHENTICATED', $this->body()['error']['code']);
    }

    public function testEmployeeRoleIsForbidden(): void
    {
        $this->call('GET', '/api/employees', token: $this->login('api-anna@example.com'));

        self::assertResponseStatusCodeSame(403);
        self::assertSame('FORBIDDEN', $this->body()['error']['code']);
    }

    public function testManagerListsEmployees(): void
    {
        $this->call('GET', '/api/employees', token: $this->managerToken());

        self::assertResponseStatusCodeSame(200);
        $mine = array_values(array_filter(
            $this->body(),
            static fn (array $e): bool => 'api-anna@example.com' === $e['email'],
        ));
        self::assertSame([[
            'id' => $this->anna->getId(),
            'name' => 'Anna Api',
            'email' => 'api-anna@example.com',
            'position' => 'barista',
            'contractType' => 'part_time',
            'maxWeeklyHours' => 20,
            'active' => true,
        ]], $mine);
    }

    public function testManagerGetsOneEmployeeAndUnknownIdIs404(): void
    {
        $token = $this->managerToken();

        $this->call('GET', '/api/employees/'.$this->anna->getId(), token: $token);
        self::assertResponseStatusCodeSame(200);
        self::assertSame('Anna Api', $this->body()['name']);

        $this->call('GET', '/api/employees/999999', token: $token);
        self::assertResponseStatusCodeSame(404);
        self::assertSame('EMPLOYEE_NOT_FOUND', $this->body()['error']['code']);
    }

    public function testManagerCreatesAnEmployeeWhoCanThenLogIn(): void
    {
        $this->call('POST', '/api/employees', $this->newEmployee(), $this->managerToken());

        self::assertResponseStatusCodeSame(201);
        $created = $this->body();
        self::assertSame('Nina', $created['name']);
        self::assertSame('nina-api@example.com', $created['email']);
        self::assertTrue($created['active']);
        self::assertArrayNotHasKey('password', $created);

        self::assertNotSame('', $this->login('nina-api@example.com', 'initial-pass-1'));
    }

    public function testInvalidBodyReturnsViolations(): void
    {
        $bad = ['email' => 'not-an-email', 'password' => 'short', 'name' => '', 'position' => 'barista',
            'contractType' => 'part_time', 'maxWeeklyHours' => 0];

        $this->call('POST', '/api/employees', $bad, $this->managerToken());

        self::assertResponseStatusCodeSame(422);
        $error = $this->body()['error'];
        self::assertSame('VALIDATION_FAILED', $error['code']);
        $fields = array_column($error['violations'], 'code');
        foreach (['email', 'password', 'name', 'maxWeeklyHours'] as $field) {
            self::assertContains($field, $fields);
        }
    }

    public function testUnknownPositionIsRejectedAs422(): void
    {
        $bad = $this->newEmployee(['position' => 'astronaut']);

        $this->call('POST', '/api/employees', $bad, $this->managerToken());

        self::assertResponseStatusCodeSame(422);
        self::assertSame('VALIDATION_FAILED', $this->body()['error']['code']);
    }

    public function testDuplicateEmailIs409(): void
    {
        $this->call('POST', '/api/employees', $this->newEmployee(['email' => 'api-anna@example.com']), $this->managerToken());

        self::assertResponseStatusCodeSame(409);
        self::assertSame('EMAIL_ALREADY_USED', $this->body()['error']['code']);
    }

    public function testManagerUpdatesAnEmployee(): void
    {
        $payload = ['name' => 'Anna Neu', 'position' => 'bar', 'contractType' => 'full_time', 'maxWeeklyHours' => 38];

        $this->call('PUT', '/api/employees/'.$this->anna->getId(), $payload, $this->managerToken());

        self::assertResponseStatusCodeSame(200);
        $body = $this->body();
        self::assertSame('Anna Neu', $body['name']);
        self::assertSame('bar', $body['position']);
        self::assertSame('full_time', $body['contractType']);
        self::assertSame(38, $body['maxWeeklyHours']);
        self::assertSame('api-anna@example.com', $body['email']);
    }

    public function testManagerDeactivatesAnEmployee(): void
    {
        $this->call('POST', '/api/employees/'.$this->anna->getId().'/deactivate', token: $this->managerToken());

        self::assertResponseStatusCodeSame(200);
        self::assertFalse($this->body()['active']);
    }

    private function saveUser(string $email, UserRole $role): User
    {
        $container = self::getContainer();
        $hash = $container->get(UserPasswordHasherInterface::class)
            ->hashPassword(new User($email, '', $role), self::PASSWORD);
        $user = new User($email, $hash, $role);
        $container->get(UserRepository::class)->save($user);

        return $user;
    }

    private function managerToken(): string
    {
        return $this->login('api-manager@example.com');
    }

    private function login(string $email, string $password = self::PASSWORD): string
    {
        $this->call('POST', '/api/login', ['email' => $email, 'password' => $password]);
        $token = $this->body()['token'] ?? '';
        self::assertIsString($token);

        return $token;
    }

    /** @param array<string, mixed>|null $json */
    private function call(string $method, string $uri, ?array $json = null, ?string $token = null): void
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
    private function body(): array
    {
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);

        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function newEmployee(array $overrides = []): array
    {
        return array_merge([
            'email' => 'nina-api@example.com',
            'password' => 'initial-pass-1',
            'name' => 'Nina',
            'position' => 'service',
            'contractType' => 'mini_job',
            'maxWeeklyHours' => 10,
        ], $overrides);
    }
}
