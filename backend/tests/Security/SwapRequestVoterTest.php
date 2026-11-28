<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\Assignment;
use App\Entity\Employee;
use App\Entity\Roster;
use App\Entity\Shift;
use App\Entity\SwapRequest;
use App\Entity\User;
use App\Enum\ContractType;
use App\Enum\Position;
use App\Enum\UserRole;
use App\Security\SwapRequestVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class SwapRequestVoterTest extends TestCase
{
    private Employee $anna;
    private Employee $bea;
    private Employee $cara;
    private Assignment $assignment;
    private SwapRequest $request;

    protected function setUp(): void
    {
        $this->anna = $this->employee('anna@example.com', 'Anna');
        $this->bea = $this->employee('bea@example.com', 'Bea');
        $this->cara = $this->employee('cara@example.com', 'Cara');
        $shift = new Shift(
            new Roster(new \DateTimeImmutable('2031-02-03')),
            new \DateTimeImmutable('2031-02-04'),
            new \DateTimeImmutable('08:00'),
            new \DateTimeImmutable('14:00'),
            Position::Barista,
            1,
        );
        $this->assignment = new Assignment($shift, $this->anna);
        $this->request = new SwapRequest($shift, $this->assignment, $this->anna, $this->bea, new \DateTimeImmutable());
    }

    public function testOnlyTheOwnerOfAnAssignmentMayAskForASwap(): void
    {
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($this->anna->getUser(), SwapRequestVoter::REQUEST, $this->assignment));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->bea->getUser(), SwapRequestVoter::REQUEST, $this->assignment));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->manager(), SwapRequestVoter::REQUEST, $this->assignment));
    }

    public function testOnlyTheColleagueMayAnswerARequest(): void
    {
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($this->bea->getUser(), SwapRequestVoter::ANSWER, $this->request));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->anna->getUser(), SwapRequestVoter::ANSWER, $this->request));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->cara->getUser(), SwapRequestVoter::ANSWER, $this->request));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->manager(), SwapRequestVoter::ANSWER, $this->request));
    }

    public function testOnlyTheRequesterMayCancel(): void
    {
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($this->anna->getUser(), SwapRequestVoter::CANCEL, $this->request));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->bea->getUser(), SwapRequestVoter::CANCEL, $this->request));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($this->manager(), SwapRequestVoter::CANCEL, $this->request));
    }

    public function testOtherAttributesAndSubjectsAreNotDecided(): void
    {
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($this->anna->getUser(), 'SOMETHING_ELSE', $this->request));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($this->anna->getUser(), SwapRequestVoter::CANCEL, new \stdClass()));
        // a request is judged with ANSWER or CANCEL, an assignment only with REQUEST
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($this->anna->getUser(), SwapRequestVoter::REQUEST, $this->request));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($this->anna->getUser(), SwapRequestVoter::CANCEL, $this->assignment));
    }

    private function vote(User $user, string $attribute, object $subject): int
    {
        $token = new UsernamePasswordToken($user, 'api', $user->getRoles());

        return (new SwapRequestVoter())->vote($token, $subject, [$attribute]);
    }

    private function manager(): User
    {
        return new User('boss@example.com', 'hash', UserRole::Manager);
    }

    private function employee(string $email, string $name): Employee
    {
        return new Employee(new User($email, 'hash', UserRole::Employee), $name, Position::Barista, ContractType::PartTime, 20);
    }
}
