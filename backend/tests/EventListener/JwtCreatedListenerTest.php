<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Entity\Employee;
use App\Entity\User;
use App\Enum\ContractType;
use App\Enum\Position;
use App\Enum\UserRole;
use App\EventListener\JwtCreatedListener;
use App\Repository\EmployeeRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class JwtCreatedListenerTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private JwtCreatedListener $listener;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->listener = $container->get(JwtCreatedListener::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testTheTokenCarriesTheEmployeeIdOfTheUser(): void
    {
        $user = $this->user('anna-jwt@example.com');
        $employee = new Employee($user, 'Anna', Position::Barista, ContractType::PartTime, 20);
        self::getContainer()->get(EmployeeRepository::class)->save($employee);

        $event = new JWTCreatedEvent(['username' => 'anna-jwt@example.com'], $user);
        ($this->listener)($event);

        self::assertSame($employee->getId(), $event->getData()['employeeId']);
        self::assertSame('anna-jwt@example.com', $event->getData()['username']);
    }

    public function testAUserWithoutAnEmployeeGetsNull(): void
    {
        $user = $this->user('nobody-jwt@example.com');

        $event = new JWTCreatedEvent(['username' => 'nobody-jwt@example.com'], $user);
        ($this->listener)($event);

        self::assertArrayHasKey('employeeId', $event->getData());
        self::assertNull($event->getData()['employeeId']);
    }

    private function user(string $email): User
    {
        $user = new User($email, 'hash', UserRole::Employee);
        self::getContainer()->get(UserRepository::class)->save($user);

        return $user;
    }
}
