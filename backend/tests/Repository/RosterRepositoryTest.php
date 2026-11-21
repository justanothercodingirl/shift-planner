<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Roster;
use App\Enum\RosterStatus;
use App\Repository\RosterRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RosterRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private RosterRepository $rosters;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->rosters = $container->get(RosterRepository::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testSavedRosterCanBeFound(): void
    {
        $roster = new Roster(new \DateTimeImmutable('2031-01-06'));
        $this->rosters->save($roster);
        $this->em->clear();

        $found = $this->rosters->find($roster->getId());

        self::assertNotNull($found);
        self::assertSame('2031-01-06', $found->getWeekStart()->format('Y-m-d'));
        self::assertSame(RosterStatus::Draft, $found->getStatus());
    }

    public function testRemovedRosterIsGone(): void
    {
        $roster = new Roster(new \DateTimeImmutable('2031-01-06'));
        $this->rosters->save($roster);
        $id = $roster->getId();

        $this->rosters->remove($roster);

        self::assertNull($this->rosters->find($id));
    }

    public function testTwoRostersForTheSameWeekAreRejected(): void
    {
        $this->rosters->save(new Roster(new \DateTimeImmutable('2031-01-06')));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->rosters->save(new Roster(new \DateTimeImmutable('2031-01-06')));
    }
}
