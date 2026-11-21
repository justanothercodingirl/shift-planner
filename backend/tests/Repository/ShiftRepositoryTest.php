<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Roster;
use App\Entity\Shift;
use App\Enum\Position;
use App\Repository\RosterRepository;
use App\Repository\ShiftRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ShiftRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ShiftRepository $shifts;
    private Roster $roster;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->shifts = $container->get(ShiftRepository::class);
        $this->em->getConnection()->beginTransaction();

        $this->roster = new Roster(new \DateTimeImmutable('2031-01-06'));
        $container->get(RosterRepository::class)->save($this->roster);
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testSavedShiftKeepsAllItsValues(): void
    {
        $shift = new Shift(
            $this->roster,
            new \DateTimeImmutable('2031-01-11'),
            new \DateTimeImmutable('08:00'),
            new \DateTimeImmutable('14:00'),
            Position::Barista,
            2,
        );
        $this->shifts->save($shift);
        $this->em->clear();

        $found = $this->shifts->find($shift->getId());

        self::assertNotNull($found);
        self::assertSame('2031-01-11', $found->getDate()->format('Y-m-d'));
        self::assertSame('08:00', $found->getStartTime()->format('H:i'));
        self::assertSame('14:00', $found->getEndTime()->format('H:i'));
        self::assertSame(Position::Barista, $found->getRequiredPosition());
        self::assertSame(2, $found->getHeadcount());
        self::assertSame($this->roster->getId(), $found->getRoster()->getId());
    }

    public function testOvernightShiftKeepsEndEarlierThanStart(): void
    {
        $shift = new Shift(
            $this->roster,
            new \DateTimeImmutable('2031-01-10'),
            new \DateTimeImmutable('18:00'),
            new \DateTimeImmutable('02:00'),
            Position::Bar,
            1,
        );
        $this->shifts->save($shift);
        $this->em->clear();

        $found = $this->shifts->find($shift->getId());

        self::assertNotNull($found);
        self::assertSame('18:00', $found->getStartTime()->format('H:i'));
        self::assertSame('02:00', $found->getEndTime()->format('H:i'));
    }

    public function testRemovedShiftIsGone(): void
    {
        $shift = new Shift(
            $this->roster,
            new \DateTimeImmutable('2031-01-07'),
            new \DateTimeImmutable('10:00'),
            new \DateTimeImmutable('18:00'),
            Position::Kitchen,
            1,
        );
        $this->shifts->save($shift);
        $id = $shift->getId();

        $this->shifts->remove($shift);

        self::assertNull($this->shifts->find($id));
    }
}
