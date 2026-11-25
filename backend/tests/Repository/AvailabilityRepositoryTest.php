<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Availability;
use App\Entity\Employee;
use App\Entity\User;
use App\Enum\AvailabilityType;
use App\Enum\ContractType;
use App\Enum\Position;
use App\Enum\UserRole;
use App\Enum\Weekday;
use App\Repository\AvailabilityRepository;
use App\Repository\EmployeeRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AvailabilityRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private AvailabilityRepository $availabilities;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->availabilities = $container->get(AvailabilityRepository::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testSavedEntryGetsAnIdAndCanBeRemoved(): void
    {
        $entry = $this->weekly($this->employee('Anna'), Weekday::Monday, '08:00', '12:00');

        $id = $entry->getId();
        self::assertNotNull($id);

        $this->availabilities->remove($entry);

        self::assertNull($this->availabilities->find($id));
    }

    public function testListsOnlyTheEmployeesOwnEntries(): void
    {
        $anna = $this->employee('Anna');
        $ben = $this->employee('Ben');
        $this->weekly($anna, Weekday::Monday, '08:00', '12:00');
        $this->weekly($ben, Weekday::Monday, '08:00', '12:00');

        self::assertCount(1, $this->availabilities->findByEmployee($anna));
    }

    public function testListsWeeklyEntriesInWeekdayOrderThenOneOffEntriesByDate(): void
    {
        $anna = $this->employee('Anna');
        $this->oneOff($anna, '2031-02-07', '08:00', '12:00');
        $this->weekly($anna, Weekday::Friday, '08:00', '12:00');
        $this->oneOff($anna, '2031-02-05', '14:00', '16:00');
        $this->weekly($anna, Weekday::Monday, '13:00', '15:00');
        $this->weekly($anna, Weekday::Monday, '08:00', '12:00');
        $this->oneOff($anna, '2031-02-05', '08:00', '10:00');

        $found = array_map(
            static fn (Availability $a): string => ($a->getWeekday()->value ?? $a->getDate()?->format('Y-m-d')).' '.$a->getStartTime()->format('H:i'),
            $this->availabilities->findByEmployee($anna),
        );

        self::assertSame(
            ['monday 08:00', 'monday 13:00', 'friday 08:00', '2031-02-05 08:00', '2031-02-05 14:00', '2031-02-07 08:00'],
            $found,
        );
    }

    public function testFindUnavailableSkipsPreferredEntriesAndOtherEmployees(): void
    {
        $anna = $this->employee('Anna');
        $ben = $this->employee('Ben');
        $this->weekly($anna, Weekday::Monday, '08:00', '12:00');
        $this->weekly($anna, Weekday::Tuesday, '08:00', '12:00', AvailabilityType::Preferred);
        $this->weekly($ben, Weekday::Monday, '08:00', '12:00');

        $found = $this->availabilities->findUnavailableByEmployee($anna);

        self::assertCount(1, $found);
        self::assertSame(Weekday::Monday, $found[0]->getWeekday());
    }

    private function weekly(Employee $employee, Weekday $weekday, string $from, string $to, AvailabilityType $type = AvailabilityType::Unavailable): Availability
    {
        $entry = new Availability($employee, $weekday, null, new \DateTimeImmutable($from), new \DateTimeImmutable($to), $type);
        $this->availabilities->save($entry);

        return $entry;
    }

    private function oneOff(Employee $employee, string $date, string $from, string $to): Availability
    {
        $entry = new Availability($employee, null, new \DateTimeImmutable($date), new \DateTimeImmutable($from), new \DateTimeImmutable($to), AvailabilityType::Unavailable);
        $this->availabilities->save($entry);

        return $entry;
    }

    private function employee(string $name): Employee
    {
        $user = new User(strtolower($name).'-avail@example.com', 'hash', UserRole::Employee);
        self::getContainer()->get(UserRepository::class)->save($user);
        $employee = new Employee($user, $name, Position::Barista, ContractType::PartTime, 20);
        self::getContainer()->get(EmployeeRepository::class)->save($employee);

        return $employee;
    }
}
