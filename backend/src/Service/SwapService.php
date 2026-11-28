<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\CreateSwapRequest;
use App\Entity\Shift;
use App\Entity\SwapRequest;
use App\Entity\User;
use App\Enum\SwapStatus;
use App\Exception\AssignmentNotFoundException;
use App\Exception\EmployeeInactiveException;
use App\Exception\RosterNotPublishedException;
use App\Exception\RuleViolationException;
use App\Exception\SwapAlreadyOpenException;
use App\Exception\SwapAssignmentChangedException;
use App\Exception\SwapDeclinedException;
use App\Exception\SwapNotInStateException;
use App\Exception\SwapRequestNotFoundException;
use App\Exception\SwapShiftStartedException;
use App\Exception\SwapWithYourselfException;
use App\Repository\AssignmentRepository;
use App\Repository\EmployeeRepository;
use App\Repository\SwapRequestRepository;
use App\Rule\Violation;
use App\Security\SwapRequestVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * A swap is a handover: an employee asks a colleague to take over one of their
 * shifts, the colleague accepts, and the manager approves (SPEC section 5). The
 * rules are checked for the colleague twice, when accepting and again when approving.
 */
class SwapService
{
    public function __construct(
        private readonly SwapRequestRepository $swaps,
        private readonly AssignmentRepository $assignments,
        private readonly EmployeeService $employees,
        private readonly EmployeeRepository $employeeRepository,
        private readonly ShiftValidator $validator,
        private readonly Security $security,
        private readonly ClockInterface $clock,
        private readonly SwapNotifier $notifier,
    ) {
    }

    /** An employee asks a colleague to take over one of their own shifts. */
    public function request(int $assignmentId, CreateSwapRequest $input): SwapRequest
    {
        $assignment = $this->assignments->find($assignmentId) ?? throw new AssignmentNotFoundException($assignmentId);
        $this->assertAllowed(SwapRequestVoter::REQUEST, $assignment);

        $shift = $assignment->getShift();
        if (!$shift->getRoster()->isPublished()) {
            throw new RosterNotPublishedException();
        }
        $this->assertNotStarted($shift);

        $target = $this->employees->get($input->targetEmployeeId);
        if ($target->getId() === $assignment->getEmployee()->getId()) {
            throw new SwapWithYourselfException();
        }
        if (!$target->isActive()) {
            throw new EmployeeInactiveException($target->getName());
        }
        if (null !== $this->swaps->findOpenForAssignment($assignment)) {
            throw new SwapAlreadyOpenException();
        }

        $request = new SwapRequest($shift, $assignment, $assignment->getEmployee(), $target, $this->clock->now());
        $this->swaps->save($request);
        $this->notifier->requested($request);

        return $request;
    }

    /** The colleague agrees. The rules run for them now, so they hear about a problem at once. */
    public function accept(int $id): SwapRequest
    {
        $request = $this->get($id);
        $this->assertAllowed(SwapRequestVoter::ANSWER, $request);
        $this->assertCanBecome($request, SwapStatus::Accepted);
        $this->assertStillValid($request);

        $request->accept();
        $this->swaps->save($request);
        $this->notifier->accepted($request);

        return $request;
    }

    public function decline(int $id): SwapRequest
    {
        $request = $this->get($id);
        $this->assertAllowed(SwapRequestVoter::ANSWER, $request);
        $this->assertCanBecome($request, SwapStatus::Declined);

        $request->decline();
        $this->swaps->save($request);
        $this->notifier->declined($request);

        return $request;
    }

    /** The requester withdraws a request that is still open. */
    public function cancel(int $id): SwapRequest
    {
        $request = $this->get($id);
        $this->assertAllowed(SwapRequestVoter::CANCEL, $request);
        $this->assertCanBecome($request, SwapStatus::Cancelled);

        $wasAccepted = SwapStatus::Accepted === $request->getStatus();
        $request->cancel();
        $this->swaps->save($request);
        $this->notifier->cancelled($request, $wasAccepted);

        return $request;
    }

    /**
     * The manager approves: the rules run again for the colleague, then the same
     * assignment is handed over to them. Manager only (checked on the route).
     */
    public function approve(int $id): SwapRequest
    {
        $request = $this->get($id);
        $this->assertCanBecome($request, SwapStatus::Approved);
        $this->assertStillValid($request);

        $assignment = $request->getAssignment() ?? throw new SwapAssignmentChangedException();
        $assignment->reassignTo($request->getTarget());
        $this->assignments->save($assignment);
        $request->approve();
        $this->swaps->save($request);
        $this->notifier->approved($request);

        return $request;
    }

    /** The manager says no to an open request. Manager only (checked on the route). */
    public function reject(int $id): SwapRequest
    {
        $request = $this->get($id);
        $this->assertCanBecome($request, SwapStatus::Rejected);

        $request->reject();
        $this->swaps->save($request);
        $this->notifier->rejected($request);

        return $request;
    }

    /**
     * Managers see every request; an employee sees the ones they made or received.
     *
     * @return list<SwapRequest>
     */
    public function list(?SwapStatus $status = null): array
    {
        if ($this->security->isGranted('ROLE_MANAGER')) {
            return $this->swaps->findAllNewestFirst($status);
        }

        $user = $this->security->getUser();
        $employee = $user instanceof User ? $this->employeeRepository->findOneByUser($user) : null;
        if (null === $employee) {
            return [];
        }

        $requests = $this->swaps->findVisibleTo($employee);

        return null === $status
            ? $requests
            : array_values(array_filter($requests, static fn (SwapRequest $r): bool => $r->getStatus() === $status));
    }

    private function get(int $id): SwapRequest
    {
        return $this->swaps->find($id) ?? throw new SwapRequestNotFoundException($id);
    }

    private function assertAllowed(string $attribute, object $subject): void
    {
        if (!$this->security->isGranted($attribute, $subject)) {
            throw new AccessDeniedException();
        }
    }

    /** A declined request has its own answer when someone tries to approve it. */
    private function assertCanBecome(SwapRequest $request, SwapStatus $next): void
    {
        if ($request->getStatus()->canMoveTo($next)) {
            return;
        }

        throw SwapStatus::Declined === $request->getStatus() && SwapStatus::Approved === $next ? new SwapDeclinedException() : new SwapNotInStateException($request->getStatus(), $next);
    }

    /** What must still be true when the colleague accepts and when the manager approves. */
    private function assertStillValid(SwapRequest $request): void
    {
        $this->assertAssignmentUnchanged($request);
        $this->assertNotStarted($request->getShift());
        $this->assertColleagueMayWork($request);
    }

    /** If the shift was taken off the requester or given to someone else meanwhile, the request is cancelled. */
    private function assertAssignmentUnchanged(SwapRequest $request): void
    {
        $assignment = $request->getAssignment();
        if (null !== $assignment && null !== $assignment->getId() && $assignment->getEmployee()->getId() === $request->getRequester()->getId()) {
            return;
        }

        $request->cancel();
        $this->swaps->save($request);
        $this->notifier->cancelledBecauseShiftChanged($request);

        throw new SwapAssignmentChangedException();
    }

    private function assertNotStarted(Shift $shift): void
    {
        if ($shift->getStartsAt() <= $this->clock->now()) {
            throw new SwapShiftStartedException();
        }
    }

    /**
     * Runs every rule for the colleague. SHIFT_FULL is left out: the requester is
     * still on the shift until the manager approves, and a handover keeps the headcount.
     */
    private function assertColleagueMayWork(SwapRequest $request): void
    {
        $target = $request->getTarget();
        if (!$target->isActive()) {
            throw new EmployeeInactiveException($target->getName());
        }

        $violations = array_values(array_filter(
            $this->validator->validate($target, $request->getShift()),
            static fn (Violation $violation): bool => 'SHIFT_FULL' !== $violation->code,
        ));
        if ([] !== $violations) {
            throw new RuleViolationException($violations);
        }
    }
}
