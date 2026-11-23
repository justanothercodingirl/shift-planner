<?php

declare(strict_types=1);

namespace App\Tests\DataFixtures;

use App\DataFixtures\AssignmentFixtures;
use App\DataFixtures\EmployeeFixtures;
use App\DataFixtures\RosterFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Assignment;
use App\Repository\AssignmentRepository;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AssignmentFixturesTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();

        $loader = new Loader();
        $loader->addFixture(new UserFixtures($container->get(UserPasswordHasherInterface::class)));
        $loader->addFixture(new EmployeeFixtures());
        $loader->addFixture(new RosterFixtures());
        $loader->addFixture(new AssignmentFixtures());
        (new ORMExecutor($this->em))->execute($loader->getFixtures(), true);
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testCreatesFourDemoAssignments(): void
    {
        $assignments = self::getContainer()->get(AssignmentRepository::class)->findAll();

        self::assertCount(4, $assignments);
    }

    public function testAnnaWorksTwoBaristaShiftsAndBenOneServiceShift(): void
    {
        $byEmployee = [];
        /** @var Assignment $assignment */
        foreach (self::getContainer()->get(AssignmentRepository::class)->findAll() as $assignment) {
            $byEmployee[$assignment->getEmployee()->getName()][] = $assignment->getShift()->getRequiredPosition()->value;
        }

        self::assertSame(['barista', 'barista'], $byEmployee['Anna']);
        self::assertSame(['service'], $byEmployee['Ben']);
        self::assertSame(['kitchen'], $byEmployee['Clara']);
    }
}
