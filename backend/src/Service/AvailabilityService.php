<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\AvailabilityRequest;
use App\Entity\Availability;
use App\Entity\Employee;
use App\Exception\AvailabilityNotFoundException;
use App\Exception\AvailabilityOverlapException;
use App\Exception\AvailabilityTimesInvalidException;
use App\Repository\AvailabilityRepository;
use App\Security\AvailabilityVoter;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class AvailabilityService
{
    public function __construct(
        private readonly AvailabilityRepository $availabilities,
        private readonly EmployeeService $employees,
        private readonly AuthorizationCheckerInterface $authorization,
    ) {
    }

    /** @return list<Availability> */
    public function list(int $employeeId): array
    {
        $employee = $this->employees->get($employeeId);
        $this->assertAllowed(AvailabilityVoter::VIEW, $employee);

        return $this->availabilities->findByEmployee($employee);
    }

    public function create(int $employeeId, AvailabilityRequest $request): Availability
    {
        $employee = $this->employees->get($employeeId);
        $this->assertAllowed(AvailabilityVoter::MANAGE, $employee);

        $entry = $this->build($employee, $request);
        $this->assertNoOverlap($entry, null);
        $this->availabilities->save($entry);

        return $entry;
    }

    public function update(int $id, AvailabilityRequest $request): Availability
    {
        $entry = $this->get($id);
        $this->assertAllowed(AvailabilityVoter::MANAGE, $entry->getEmployee());

        $changed = $this->build($entry->getEmployee(), $request);
        $this->assertNoOverlap($changed, $entry);
        $entry->update($changed->getWeekday(), $changed->getDate(), $changed->getStartTime(), $changed->getEndTime(), $changed->getType());
        $this->availabilities->save($entry);

        return $entry;
    }

    public function delete(int $id): void
    {
        $entry = $this->get($id);
        $this->assertAllowed(AvailabilityVoter::MANAGE, $entry->getEmployee());

        $this->availabilities->remove($entry);
    }

    private function get(int $id): Availability
    {
        return $this->availabilities->find($id) ?? throw new AvailabilityNotFoundException($id);
    }

    /** Turns the request into an entry that is not saved yet, after checking that it ends after it starts. */
    private function build(Employee $employee, AvailabilityRequest $request): Availability
    {
        $start = $this->parse('!H:i', $request->startTime);
        $end = $this->parse('!H:i', $request->endTime);
        if ($end <= $start) {
            throw new AvailabilityTimesInvalidException($request->startTime, $request->endTime);
        }

        $date = null === $request->date ? null : $this->parse('!Y-m-d', $request->date);

        return new Availability($employee, $request->weekday, $date, $start, $end, $request->type);
    }

    /** Entries of the same kind (same weekday, or same date) may not overlap in time, whatever their type. */
    private function assertNoOverlap(Availability $candidate, ?Availability $ignore): void
    {
        foreach ($this->availabilities->findByEmployee($candidate->getEmployee()) as $existing) {
            if ($this->isSame($existing, $ignore) || !$existing->isSameKind($candidate) || !$existing->overlapsInTime($candidate)) {
                continue;
            }

            throw new AvailabilityOverlapException(sprintf('%s %s to %s (%s)', $existing->getDayLabel(), $existing->getStartTime()->format('H:i'), $existing->getEndTime()->format('H:i'), $existing->getType()->value));
        }
    }

    private function isSame(Availability $a, ?Availability $b): bool
    {
        return null !== $b && ($a === $b || (null !== $a->getId() && $a->getId() === $b->getId()));
    }

    private function assertAllowed(string $attribute, Employee $employee): void
    {
        if (!$this->authorization->isGranted($attribute, $employee)) {
            throw new AccessDeniedException();
        }
    }

    private function parse(string $format, string $value): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromFormat($format, $value)
            ?: throw new \InvalidArgumentException(sprintf('"%s" does not match the format %s.', $value, $format));
    }
}
