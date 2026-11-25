<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Employee;
use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Who may see and change an employee's availability: the employee may do both;
 * a manager may only look (decided with the developer, see DECISIONS.md).
 *
 * @extends Voter<string, Employee>
 */
final class AvailabilityVoter extends Voter
{
    public const VIEW = 'VIEW_AVAILABILITY';
    public const MANAGE = 'MANAGE_AVAILABILITY';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::MANAGE], true) && $subject instanceof Employee;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $isOwner = $subject->getUser()->getUserIdentifier() === $user->getUserIdentifier();

        return self::MANAGE === $attribute ? $isOwner : $isOwner || UserRole::Manager === $user->getRole();
    }
}
