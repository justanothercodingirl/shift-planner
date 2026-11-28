<?php

declare(strict_types=1);

namespace App\Tests\Mail;

use App\Entity\Assignment;
use App\Entity\Employee;
use App\Entity\Roster;
use App\Entity\Shift;
use App\Entity\SwapRequest;
use App\Entity\User;
use App\Enum\ContractType;
use App\Enum\Position;
use App\Enum\UserRole;
use App\Mail\SwapMailBuilder;
use App\Mail\SwapMailEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/** Anna asks Bea to take Tuesday 2031-02-04 08:00 to 14:00 (barista). */
final class SwapMailBuilderTest extends KernelTestCase
{
    private SwapMailBuilder $builder;
    private Employee $anna;
    private Employee $bea;
    private User $manager;
    private SwapRequest $request;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->builder = self::getContainer()->get(SwapMailBuilder::class);

        $this->anna = $this->employee('Anna');
        $this->bea = $this->employee('Bea');
        $this->manager = new User('boss-mail@example.com', 'hash', UserRole::Manager);
        $shift = new Shift(
            new Roster(new \DateTimeImmutable('2031-02-03')),
            new \DateTimeImmutable('2031-02-04'),
            new \DateTimeImmutable('08:00'),
            new \DateTimeImmutable('14:00'),
            Position::Barista,
            1,
        );
        $this->request = new SwapRequest($shift, new Assignment($shift, $this->anna), $this->anna, $this->bea, new \DateTimeImmutable());
    }

    public function testAskingTellsTheColleagueToAcceptOrDecline(): void
    {
        $email = $this->builder->build($this->request, $this->bea->getUser(), SwapMailEvent::Requested);

        self::assertSame('Anna asks you to take over a shift', $email->getSubject());
        $text = (string) $email->getTextBody();
        self::assertStringContainsString('Hi Bea,', $text);
        self::assertStringContainsString('Anna asks you to take over this shift:', $text);
        self::assertStringContainsString('Tue 4 Feb 08:00 to 14:00 (barista)', $text);
        self::assertStringContainsString('accept or decline', $text);
    }

    public function testAcceptingTellsTheManagerItIsWaitingForApproval(): void
    {
        $email = $this->builder->build($this->request, $this->manager, SwapMailEvent::Accepted);

        self::assertSame("Swap waiting for your approval: Bea takes over Anna's shift", $email->getSubject());
        $text = (string) $email->getTextBody();
        self::assertStringContainsString('Hello,', $text);
        self::assertStringContainsString("Bea agreed to take over Anna's shift:", $text);
        self::assertStringContainsString('waiting for your approval', $text);
    }

    public function testDecliningTellsTheRequesterTheShiftStaysTheirs(): void
    {
        $email = $this->builder->build($this->request, $this->anna->getUser(), SwapMailEvent::Declined);

        self::assertSame('Bea declined your swap request', $email->getSubject());
        $text = (string) $email->getTextBody();
        self::assertStringContainsString('Hi Anna,', $text);
        self::assertStringContainsString('Bea declined to take over your shift:', $text);
        self::assertStringContainsString('The shift stays yours.', $text);
    }

    public function testWithdrawingTellsTheOthers(): void
    {
        $email = $this->builder->build($this->request, $this->bea->getUser(), SwapMailEvent::Cancelled);

        self::assertSame('Swap request withdrawn by Anna', $email->getSubject());
        self::assertStringContainsString('Anna withdrew the swap request for this shift:', (string) $email->getTextBody());
    }

    public function testAShiftThatChangedCancelsTheRequest(): void
    {
        $email = $this->builder->build($this->request, $this->anna->getUser(), SwapMailEvent::ShiftChanged);

        self::assertSame('Swap request cancelled: the shift changed', $email->getSubject());
        self::assertStringContainsString('was changed in the meantime', (string) $email->getTextBody());
    }

    public function testApprovalNamesWhoHasTheShiftNow(): void
    {
        $email = $this->builder->build($this->request, $this->bea->getUser(), SwapMailEvent::Approved);

        self::assertSame("Swap approved: Bea now has Anna's shift", $email->getSubject());
        self::assertStringContainsString('Bea now works this shift instead of Anna', (string) $email->getTextBody());
    }

    public function testRejectionSaysWhoKeepsTheShift(): void
    {
        $email = $this->builder->build($this->request, $this->anna->getUser(), SwapMailEvent::Rejected);

        self::assertSame('Swap rejected: the shift stays with Anna', $email->getSubject());
        self::assertStringContainsString('The shift stays with Anna.', (string) $email->getTextBody());
    }

    /** @return iterable<string, array{SwapMailEvent}> */
    public static function everyEvent(): iterable
    {
        foreach (SwapMailEvent::cases() as $event) {
            yield $event->value => [$event];
        }
    }

    #[DataProvider('everyEvent')]
    public function testEveryMailIsAddressedToTheRecipientFromTheConfiguredSenderWithoutAttachments(SwapMailEvent $event): void
    {
        $email = $this->builder->build($this->request, $this->bea->getUser(), $event);

        self::assertSame('bea-swapmail@example.com', $email->getTo()[0]->getAddress());
        self::assertSame(self::getContainer()->getParameter('app.mail_from'), $email->getFrom()[0]->getAddress());
        self::assertCount(1, $email->getTo());
        self::assertSame([], $email->getAttachments());
    }

    public function testPeopleAreGreetedByNameAndManagersWithoutOne(): void
    {
        $toBea = $this->builder->build($this->request, $this->bea->getUser(), SwapMailEvent::Requested);
        $toManager = $this->builder->build($this->request, $this->manager, SwapMailEvent::Accepted);

        self::assertSame('Bea', $toBea->getTo()[0]->getName());
        self::assertSame('', $toManager->getTo()[0]->getName());
    }

    private function employee(string $name): Employee
    {
        return new Employee(new User(strtolower($name).'-swapmail@example.com', 'hash', UserRole::Employee), $name, Position::Barista, ContractType::PartTime, 20);
    }
}
