<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Assignment;
use App\Entity\Roster;
use App\Entity\User;
use App\Enum\Position;
use App\Enum\UserRole;
use App\Repository\RosterRepository;
use App\Repository\UserRepository;
use App\Service\MyShiftsService;
use App\Tests\Mail\RosterTestData;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class MyShiftsServiceTest extends KernelTestCase
{
    use RosterTestData;

    private EntityManagerInterface $em;
    private MyShiftsService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->service = $container->get(MyShiftsService::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testASignedInEmployeeSeesTheirOwnUpcomingShifts(): void
    {
        $data = $this->createRoster(); // published, week of 2031-02-03: Anna Monday morning, Ben Monday night
        $this->signInAs($data['anna']->getUser());

        $mine = $this->service->upcoming();

        self::assertSame(['Anna'], array_unique(array_map(static fn (Assignment $a): string => $a->getEmployee()->getName(), $mine)));
        self::assertCount(1, $mine);
        self::assertSame('2031-02-03', $mine[0]->getShift()->getDate()->format('Y-m-d'));
    }

    public function testNobodyElsesShiftsAreIncluded(): void
    {
        $data = $this->createRoster();
        $this->signInAs($data['clara']->getUser()); // Clara has no shift at all

        self::assertSame([], $this->service->upcoming());
    }

    public function testShiftsThatAreAlreadyOverAreLeftOut(): void
    {
        $data = $this->createRoster();
        $past = new Roster(new \DateTimeImmutable('2020-02-03'));
        $past->publish();
        self::getContainer()->get(RosterRepository::class)->save($past);
        $this->assign($this->createShift($past, '2020-02-04', '08:00', '14:00', Position::Barista, 1), $data['anna']);
        $this->signInAs($data['anna']->getUser());

        $dates = array_map(static fn (Assignment $a): string => $a->getShift()->getDate()->format('Y'), $this->service->upcoming());

        self::assertSame(['2031'], $dates);
    }

    public function testShiftsOfADraftRosterAreLeftOut(): void
    {
        $data = $this->createRoster();
        $draft = new Roster(new \DateTimeImmutable('2031-03-03'));
        self::getContainer()->get(RosterRepository::class)->save($draft);
        $this->assign($this->createShift($draft, '2031-03-04', '08:00', '14:00', Position::Barista, 1), $data['anna']);
        $this->signInAs($data['anna']->getUser());

        self::assertCount(1, $this->service->upcoming()); // only the published Monday
    }

    public function testALoginWithoutAnEmployeeProfileSeesNothing(): void
    {
        $this->createRoster();
        $plain = new User('plain-my-shifts@example.com', 'hash', UserRole::Employee);
        self::getContainer()->get(UserRepository::class)->save($plain);
        $this->signInAs($plain);

        self::assertSame([], $this->service->upcoming());
    }

    public function testTheAssignmentCarriesWhatTheScreenNeeds(): void
    {
        $data = $this->createRoster();
        $this->signInAs($data['anna']->getUser());

        $assignment = $this->service->upcoming()[0];

        self::assertNotNull($assignment->getId());
        self::assertSame(Position::Barista, $assignment->getShift()->getRequiredPosition());
        self::assertSame('2031-02-03', $assignment->getShift()->getRoster()->getWeekStart()->format('Y-m-d'));
    }

    private function signInAs(User $user): void
    {
        self::getContainer()->get(TokenStorageInterface::class)
            ->setToken(new UsernamePasswordToken($user, 'api', $user->getRoles()));
    }
}
