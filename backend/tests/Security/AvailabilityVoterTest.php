<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\Employee;
use App\Entity\User;
use App\Enum\ContractType;
use App\Enum\Position;
use App\Enum\UserRole;
use App\Security\AvailabilityVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class AvailabilityVoterTest extends TestCase
{
    public function testTheOwnerMayViewAndManage(): void
    {
        $anna = $this->employee('anna@example.com', UserRole::Employee);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($anna->getUser(), AvailabilityVoter::VIEW, $anna));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($anna->getUser(), AvailabilityVoter::MANAGE, $anna));
    }

    public function testAManagerMayViewButNotManageAnEmployeesEntries(): void
    {
        $anna = $this->employee('anna@example.com', UserRole::Employee);
        $manager = new User('manager@example.com', 'hash', UserRole::Manager);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($manager, AvailabilityVoter::VIEW, $anna));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($manager, AvailabilityVoter::MANAGE, $anna));
    }

    public function testAManagerMayManageTheirOwnEntries(): void
    {
        $mara = $this->employee('manager@example.com', UserRole::Manager);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($mara->getUser(), AvailabilityVoter::MANAGE, $mara));
    }

    public function testAColleagueMayNeitherViewNorManage(): void
    {
        $anna = $this->employee('anna@example.com', UserRole::Employee);
        $ben = new User('ben@example.com', 'hash', UserRole::Employee);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($ben, AvailabilityVoter::VIEW, $anna));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($ben, AvailabilityVoter::MANAGE, $anna));
    }

    public function testOtherAttributesAndSubjectsAreNotDecided(): void
    {
        $anna = $this->employee('anna@example.com', UserRole::Employee);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($anna->getUser(), 'SOMETHING_ELSE', $anna));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($anna->getUser(), AvailabilityVoter::VIEW, new \stdClass()));
    }

    private function vote(User $user, string $attribute, object $subject): int
    {
        $token = new UsernamePasswordToken($user, 'api', $user->getRoles());

        return (new AvailabilityVoter())->vote($token, $subject, [$attribute]);
    }

    private function employee(string $email, UserRole $role): Employee
    {
        return new Employee(new User($email, 'hash', $role), 'Anna', Position::Barista, ContractType::PartTime, 20);
    }
}
