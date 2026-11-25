<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Dto\AvailabilityRequest;
use App\Entity\Employee;
use App\Entity\User;
use App\Enum\AvailabilityType;
use App\Enum\ContractType;
use App\Enum\Position;
use App\Enum\UserRole;
use App\Enum\Weekday;
use App\Exception\AvailabilityNotFoundException;
use App\Exception\AvailabilityOverlapException;
use App\Exception\AvailabilityTimesInvalidException;
use App\Exception\EmployeeNotFoundException;
use App\Repository\EmployeeRepository;
use App\Repository\UserRepository;
use App\Service\AvailabilityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class AvailabilityServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private AvailabilityService $service;
    private Employee $anna;
    private Employee $ben;
    private User $manager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->service = $container->get(AvailabilityService::class);
        $this->em->getConnection()->beginTransaction();

        $this->anna = $this->employee('Anna');
        $this->ben = $this->employee('Ben');
        $this->manager = new User('boss-avail@example.com', 'hash', UserRole::Manager);
        $container->get(UserRepository::class)->save($this->manager);
        $this->signInAs($this->anna->getUser());
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testCreateStoresAWeeklyEntry(): void
    {
        $entry = $this->service->create((int) $this->anna->getId(), $this->weekly());

        self::assertNotNull($entry->getId());
        self::assertSame($this->anna, $entry->getEmployee());
        self::assertSame(Weekday::Monday, $entry->getWeekday());
        self::assertNull($entry->getDate());
        self::assertSame('08:00', $entry->getStartTime()->format('H:i'));
        self::assertSame('16:00', $entry->getEndTime()->format('H:i'));
        self::assertSame(AvailabilityType::Unavailable, $entry->getType());
    }

    public function testCreateStoresAOneOffEntry(): void
    {
        $entry = $this->service->create((int) $this->anna->getId(), $this->oneOff('2031-02-05'));

        self::assertNull($entry->getWeekday());
        self::assertSame('2031-02-05', $entry->getDate()?->format('Y-m-d'));
    }

    public function testEndBeforeStartIsRejected(): void
    {
        $this->expectException(AvailabilityTimesInvalidException::class);

        $this->service->create((int) $this->anna->getId(), $this->weekly(from: '16:00', to: '08:00'));
    }

    public function testEqualStartAndEndIsRejected(): void
    {
        $this->expectException(AvailabilityTimesInvalidException::class);

        $this->service->create((int) $this->anna->getId(), $this->weekly(from: '08:00', to: '08:00'));
    }

    public function testAnOverlappingWeeklyEntryIsRejectedWhateverItsType(): void
    {
        $this->service->create((int) $this->anna->getId(), $this->weekly(from: '08:00', to: '12:00'));

        $this->expectException(AvailabilityOverlapException::class);

        $this->service->create((int) $this->anna->getId(), $this->weekly(from: '11:00', to: '14:00', type: AvailabilityType::Preferred));
    }

    public function testAnOverlappingOneOffEntryIsRejected(): void
    {
        $this->service->create((int) $this->anna->getId(), $this->oneOff('2031-02-05', '08:00', '12:00'));

        $this->expectException(AvailabilityOverlapException::class);

        $this->service->create((int) $this->anna->getId(), $this->oneOff('2031-02-05', '10:00', '11:00'));
    }

    public function testEntriesThatOnlyTouchAreAllowed(): void
    {
        $this->service->create((int) $this->anna->getId(), $this->weekly(from: '08:00', to: '12:00'));

        $second = $this->service->create((int) $this->anna->getId(), $this->weekly(from: '12:00', to: '14:00'));

        self::assertNotNull($second->getId());
    }

    public function testAOneOffEntryMayLieOnTopOfAWeeklyOne(): void
    {
        // 2031-02-03 is a Monday.
        $this->service->create((int) $this->anna->getId(), $this->weekly(from: '08:00', to: '16:00'));

        $exception = $this->service->create((int) $this->anna->getId(), $this->oneOff('2031-02-03', '08:00', '16:00', AvailabilityType::Preferred));

        self::assertNotNull($exception->getId());
    }

    public function testOtherEmployeesEntriesNeverClash(): void
    {
        $this->service->create((int) $this->anna->getId(), $this->weekly());
        $this->signInAs($this->ben->getUser());

        $entry = $this->service->create((int) $this->ben->getId(), $this->weekly());

        self::assertNotNull($entry->getId());
    }

    public function testUpdateChangesTheEntryAndIgnoresItselfInTheOverlapCheck(): void
    {
        $entry = $this->service->create((int) $this->anna->getId(), $this->weekly(from: '08:00', to: '12:00'));

        $updated = $this->service->update((int) $entry->getId(), $this->weekly(from: '09:00', to: '13:00', type: AvailabilityType::Preferred));

        self::assertSame($entry->getId(), $updated->getId());
        self::assertSame('09:00', $updated->getStartTime()->format('H:i'));
        self::assertSame(AvailabilityType::Preferred, $updated->getType());
    }

    public function testUpdateCannotOverlapAnotherEntry(): void
    {
        $this->service->create((int) $this->anna->getId(), $this->weekly(from: '08:00', to: '12:00'));
        $second = $this->service->create((int) $this->anna->getId(), $this->weekly(from: '13:00', to: '16:00'));

        $this->expectException(AvailabilityOverlapException::class);

        $this->service->update((int) $second->getId(), $this->weekly(from: '11:00', to: '16:00'));
    }

    public function testDeleteRemovesTheEntry(): void
    {
        $entry = $this->service->create((int) $this->anna->getId(), $this->weekly());

        $this->service->delete((int) $entry->getId());

        self::assertSame([], $this->service->list((int) $this->anna->getId()));
    }

    public function testListReturnsTheEmployeesEntries(): void
    {
        $this->service->create((int) $this->anna->getId(), $this->weekly());

        self::assertCount(1, $this->service->list((int) $this->anna->getId()));
    }

    public function testAManagerMayListButNotCreateOrDelete(): void
    {
        $entry = $this->service->create((int) $this->anna->getId(), $this->weekly());
        $this->signInAs($this->manager);

        self::assertCount(1, $this->service->list((int) $this->anna->getId()));

        try {
            $this->service->create((int) $this->anna->getId(), $this->weekly(from: '17:00', to: '18:00'));
            self::fail('A manager must not create availability for someone else.');
        } catch (AccessDeniedException) {
        }

        $this->expectException(AccessDeniedException::class);
        $this->service->delete((int) $entry->getId());
    }

    public function testAColleagueCannotListUpdateOrDelete(): void
    {
        $entry = $this->service->create((int) $this->anna->getId(), $this->weekly());
        $this->signInAs($this->ben->getUser());

        foreach ([
            fn () => $this->service->list((int) $this->anna->getId()),
            fn () => $this->service->update((int) $entry->getId(), $this->weekly()),
            fn () => $this->service->delete((int) $entry->getId()),
        ] as $call) {
            try {
                $call();
                self::fail('A colleague must be refused.');
            } catch (AccessDeniedException) {
            }
        }

        $this->addToAssertionCount(1);
    }

    public function testUnknownEmployeeAndEntryAreNotFound(): void
    {
        try {
            $this->service->list(999999);
            self::fail('Expected EmployeeNotFoundException.');
        } catch (EmployeeNotFoundException) {
        }

        $this->expectException(AvailabilityNotFoundException::class);
        $this->service->delete(999999);
    }

    private function weekly(string $from = '08:00', string $to = '16:00', AvailabilityType $type = AvailabilityType::Unavailable): AvailabilityRequest
    {
        return new AvailabilityRequest($from, $to, $type, weekday: Weekday::Monday);
    }

    private function oneOff(string $date, string $from = '08:00', string $to = '16:00', AvailabilityType $type = AvailabilityType::Unavailable): AvailabilityRequest
    {
        return new AvailabilityRequest($from, $to, $type, date: $date);
    }

    private function employee(string $name): Employee
    {
        $user = new User(strtolower($name).'-avail-svc@example.com', 'hash', UserRole::Employee);
        self::getContainer()->get(UserRepository::class)->save($user);
        $employee = new Employee($user, $name, Position::Barista, ContractType::PartTime, 20);
        self::getContainer()->get(EmployeeRepository::class)->save($employee);

        return $employee;
    }

    private function signInAs(User $user): void
    {
        self::getContainer()->get(TokenStorageInterface::class)
            ->setToken(new UsernamePasswordToken($user, 'api', $user->getRoles()));
    }
}
