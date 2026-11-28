<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Assignment;
use App\Entity\Employee;
use App\Entity\Roster;
use App\Entity\Shift;
use App\Entity\SwapRequest;
use App\Enum\Position;
use App\Enum\SwapStatus;
use App\Repository\AssignmentRepository;
use App\Repository\SwapRequestRepository;
use App\Tests\Mail\RosterTestData;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SwapRequestRepositoryTest extends KernelTestCase
{
    use RosterTestData;

    private EntityManagerInterface $em;
    private SwapRequestRepository $swaps;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->swaps = $container->get(SwapRequestRepository::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testSavedRequestCanBeFoundWithItsValues(): void
    {
        $data = $this->createRoster();
        [$shift, $assignment] = $this->shiftOf($data['roster'], $data['anna'], '2031-02-04');
        $request = $this->swap($shift, $assignment, $data['anna'], $data['ben'], '2031-01-01 10:00');
        $id = $request->getId();
        $this->em->clear();

        $found = $this->swaps->find($id);

        self::assertNotNull($found);
        self::assertSame(SwapStatus::Pending, $found->getStatus());
        self::assertSame('Anna', $found->getRequester()->getName());
        self::assertSame('Ben', $found->getTarget()->getName());
        self::assertSame($shift->getId(), $found->getShift()->getId());
        self::assertSame($assignment->getId(), $found->getAssignment()?->getId());
        self::assertSame('2031-01-01 10:00', $found->getCreatedAt()->format('Y-m-d H:i'));
    }

    public function testOnlyPendingAndAcceptedRequestsCountAsOpenForAnAssignment(): void
    {
        $data = $this->createRoster();
        [$shift, $assignment] = $this->shiftOf($data['roster'], $data['anna'], '2031-02-04');
        self::assertNull($this->openRequestIdFor($assignment));

        $declined = $this->swap($shift, $assignment, $data['anna'], $data['ben'], '2031-01-01 10:00');
        $declined->decline();
        $this->swaps->save($declined);
        self::assertNull($this->openRequestIdFor($assignment));

        $pending = $this->swap($shift, $assignment, $data['anna'], $data['clara'], '2031-01-02 10:00');
        self::assertSame($pending->getId(), $this->openRequestIdFor($assignment));

        $pending->accept();
        $this->swaps->save($pending);
        self::assertSame($pending->getId(), $this->openRequestIdFor($assignment));
    }

    public function testAnEmployeeSeesRequestsTheyMadeOrReceivedNewestFirst(): void
    {
        $data = $this->createRoster();
        [$shiftA, $assignmentA] = $this->shiftOf($data['roster'], $data['anna'], '2031-02-04');
        [$shiftB, $assignmentB] = $this->shiftOf($data['roster'], $data['ben'], '2031-02-05');
        $made = $this->swap($shiftA, $assignmentA, $data['anna'], $data['clara'], '2031-01-01 10:00');
        $received = $this->swap($shiftB, $assignmentB, $data['ben'], $data['anna'], '2031-01-02 10:00');
        $this->swap($shiftB, $assignmentB, $data['ben'], $data['clara'], '2031-01-03 10:00');

        $seen = $this->swaps->findVisibleTo($data['anna']);

        self::assertSame([$received->getId(), $made->getId()], array_map(static fn (SwapRequest $r): ?int => $r->getId(), $seen));
    }

    public function testEveryRequestNewestFirstOptionallyByStatus(): void
    {
        $data = $this->createRoster();
        [$shift, $assignment] = $this->shiftOf($data['roster'], $data['anna'], '2031-02-04');
        $first = $this->swap($shift, $assignment, $data['anna'], $data['ben'], '2031-01-01 10:00');
        $second = $this->swap($shift, $assignment, $data['anna'], $data['clara'], '2031-01-02 10:00');
        $second->accept();
        $this->swaps->save($second);

        self::assertSame(
            [$second->getId(), $first->getId()],
            array_map(static fn (SwapRequest $r): ?int => $r->getId(), $this->swaps->findAllNewestFirst()),
        );
        self::assertSame(
            [$second->getId()],
            array_map(static fn (SwapRequest $r): ?int => $r->getId(), $this->swaps->findAllNewestFirst(SwapStatus::Accepted)),
        );
    }

    public function testDeletingTheAssignmentKeepsTheRequestWithoutIt(): void
    {
        $data = $this->createRoster();
        [$shift, $assignment] = $this->shiftOf($data['roster'], $data['anna'], '2031-02-04');
        $request = $this->swap($shift, $assignment, $data['anna'], $data['ben'], '2031-01-01 10:00');
        $id = $request->getId();

        self::getContainer()->get(AssignmentRepository::class)->remove($assignment);
        $this->em->clear();

        $found = $this->swaps->find($id);
        self::assertNotNull($found);
        self::assertNull($found->getAssignment());
    }

    public function testDeletingTheShiftDeletesItsRequests(): void
    {
        $data = $this->createRoster();
        [$shift, $assignment] = $this->shiftOf($data['roster'], $data['anna'], '2031-02-04');
        $request = $this->swap($shift, $assignment, $data['anna'], $data['ben'], '2031-01-01 10:00');
        $id = $request->getId();

        self::getContainer()->get(\App\Repository\ShiftRepository::class)->remove($shift);
        $this->em->clear();

        self::assertNull($this->swaps->find($id));
    }

    private function openRequestIdFor(Assignment $assignment): ?int
    {
        return $this->swaps->findOpenForAssignment($assignment)?->getId();
    }

    /** @return array{Shift, Assignment} */
    private function shiftOf(Roster $roster, Employee $employee, string $date): array
    {
        $shift = $this->createShift($roster, $date, '08:00', '14:00', Position::Barista, 1);

        return [$shift, $this->saveAssignment($shift, $employee)];
    }

    private function swap(Shift $shift, Assignment $assignment, Employee $requester, Employee $target, string $createdAt): SwapRequest
    {
        $request = new SwapRequest($shift, $assignment, $requester, $target, new \DateTimeImmutable($createdAt));
        $this->swaps->save($request);

        return $request;
    }

    private function saveAssignment(Shift $shift, Employee $employee): Assignment
    {
        $assignment = new Assignment($shift, $employee);
        self::getContainer()->get(AssignmentRepository::class)->save($assignment);

        return $assignment;
    }
}
