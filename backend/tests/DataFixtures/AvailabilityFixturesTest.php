<?php

declare(strict_types=1);

namespace App\Tests\DataFixtures;

use App\DataFixtures\AssignmentFixtures;
use App\DataFixtures\AvailabilityFixtures;
use App\DataFixtures\EmployeeFixtures;
use App\DataFixtures\RosterFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Assignment;
use App\Entity\Availability;
use App\Enum\AvailabilityType;
use App\Repository\AssignmentRepository;
use App\Repository\AvailabilityRepository;
use App\Rule\UnavailableRule;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AvailabilityFixturesTest extends KernelTestCase
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
        $loader->addFixture(new AvailabilityFixtures());
        (new ORMExecutor($this->em))->execute($loader->getFixtures(), true);
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testCreatesFourDemoEntriesForAnnaBenAndClara(): void
    {
        $byEmployee = [];
        /** @var Availability $entry */
        foreach (self::getContainer()->get(AvailabilityRepository::class)->findAll() as $entry) {
            $byEmployee[$entry->getEmployee()->getName()][] = $entry->getType()->value;
        }
        ksort($byEmployee);
        foreach ($byEmployee as &$types) {
            sort($types);
        }

        self::assertSame(
            ['Anna' => ['preferred', 'unavailable'], 'Ben' => ['unavailable'], 'Clara' => ['preferred']],
            $byEmployee,
        );
    }

    public function testBensOneOffEntryFallsInTheDemoRosterWeek(): void
    {
        $oneOffs = array_values(array_filter(
            self::getContainer()->get(AvailabilityRepository::class)->findAll(),
            static fn (Availability $a): bool => null !== $a->getDate(),
        ));

        self::assertCount(1, $oneOffs);
        self::assertSame('Ben', $oneOffs[0]->getEmployee()->getName());
        $monday = (new \DateTimeImmutable('today'))->modify('monday this week')->modify('+1 week');
        self::assertSame($monday->modify('+3 days')->format('Y-m-d'), $oneOffs[0]->getDate()->format('Y-m-d'));
        self::assertSame(AvailabilityType::Unavailable, $oneOffs[0]->getType());
    }

    public function testNoDemoAssignmentBreaksTheUnavailableRule(): void
    {
        $rule = self::getContainer()->get(UnavailableRule::class);

        /** @var Assignment $assignment */
        foreach (self::getContainer()->get(AssignmentRepository::class)->findAll() as $assignment) {
            self::assertNull($rule->check($assignment->getEmployee(), $assignment->getShift()));
        }
    }
}
