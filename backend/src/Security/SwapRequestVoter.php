<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Assignment;
use App\Entity\Employee;
use App\Entity\SwapRequest;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Who may do what in a swap: the owner of a shift asks for a swap and may cancel
 * their own request; only the colleague who was asked answers (accept or decline).
 * Approving and rejecting is for managers and is checked with #[IsGranted] on the routes.
 *
 * @extends Voter<string, Assignment|SwapRequest>
 */
final class SwapRequestVoter extends Voter
{
    /** Subject: an Assignment. Allowed for the employee the assignment belongs to. */
    public const REQUEST = 'REQUEST_SWAP';
    /** Subject: a SwapRequest. Allowed for the colleague who was asked. */
    public const ANSWER = 'ANSWER_SWAP';
    /** Subject: a SwapRequest. Allowed for the employee who asked. */
    public const CANCEL = 'CANCEL_SWAP';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return (self::REQUEST === $attribute && $subject instanceof Assignment)
            || (in_array($attribute, [self::ANSWER, self::CANCEL], true) && $subject instanceof SwapRequest);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if ($subject instanceof Assignment) {
            return $this->belongsTo($subject->getEmployee(), $user);
        }

        return match ($attribute) {
            self::ANSWER => $this->belongsTo($subject->getTarget(), $user),
            self::CANCEL => $this->belongsTo($subject->getRequester(), $user),
            default => false,
        };
    }

    private function belongsTo(Employee $employee, User $user): bool
    {
        return $employee->getUser()->getUserIdentifier() === $user->getUserIdentifier();
    }
}
