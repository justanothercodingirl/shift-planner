<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Employee;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use App\Service\ColleagueService;
use App\Tests\Mail\RosterTestData;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class ColleagueServiceTest extends KernelTestCase
{
    use RosterTestData;

    private EntityManagerInterface $em;
    private ColleagueService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->service = $container->get(ColleagueService::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testAnEmployeeSeesTheOtherActiveEmployeesByName(): void
    {
        $anna = $this->createEmployee('Anna');
        $this->createEmployee('Zoe');
        $this->createEmployee('Ben');
        $this->createEmployee('Gone', active: false);
        $this->signInAs($anna->getUser());

        $names = $this->names($this->service->list());

        self::assertSame(['Ben', 'Zoe'], $names);
    }

    public function testTheSignedInEmployeeIsNotListed(): void
    {
        $anna = $this->createEmployee('Anna');
        $this->createEmployee('Ben');
        $this->signInAs($anna->getUser());

        self::assertNotContains('Anna', $this->names($this->service->list()));
    }

    public function testALoginWithoutAnEmployeeProfileSeesEveryActiveEmployee(): void
    {
        $this->createEmployee('Anna');
        $this->createEmployee('Ben');
        $manager = new User('boss-colleagues@example.com', 'hash', UserRole::Manager);
        self::getContainer()->get(UserRepository::class)->save($manager);
        $this->signInAs($manager);

        self::assertSame(['Anna', 'Ben'], $this->names($this->service->list()));
    }

    /**
     * @param list<Employee> $employees
     *
     * @return list<string>
     */
    private function names(array $employees): array
    {
        return array_map(static fn (Employee $e): string => $e->getName(), $employees);
    }

    private function signInAs(User $user): void
    {
        self::getContainer()->get(TokenStorageInterface::class)
            ->setToken(new UsernamePasswordToken($user, 'api', $user->getRoles()));
    }
}
