<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Mail\RosterChangedMailBuilder;
use App\Message\SendRosterChangedMail;
use App\MessageHandler\SendRosterChangedMailHandler;
use App\Repository\EmployeeRepository;
use App\Repository\RosterRepository;
use App\Tests\Mail\RosterTestData;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;

final class SendRosterChangedMailHandlerTest extends KernelTestCase
{
    use RosterTestData;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testSendsTheChangeWithTheRosterPdfToTheEmployee(): void
    {
        $data = $this->createRoster();
        $handler = $this->handler(self::getContainer()->get(MailerInterface::class));

        $handler($this->message($data, 'Your shift was cancelled.'));

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailAddressContains($email, 'To', 'anna-mail@example.com');
        self::assertEmailTextBodyContains($email, 'Your shift was cancelled.');
        self::assertEmailAttachmentCount($email, 1);
    }

    public function testAFailingMailServerIsLoggedAndRethrownSoMessengerCanRetry(): void
    {
        $data = $this->createRoster();
        $mailer = $this->createStub(MailerInterface::class);
        $mailer->method('send')->willThrowException(new TransportException('Connection refused'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(
            self::stringContains('roster change mail'),
            self::callback(static fn (array $context): bool => ($context['employeeId'] ?? null) === $data['anna']->getId()),
        );

        $this->expectException(TransportException::class);
        $this->handler($mailer, $logger)($this->message($data, 'x'));
    }

    public function testAnEmployeeWhoNoLongerExistsIsSkippedWithAWarning(): void
    {
        $data = $this->createRoster();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $this->handler($mailer, $logger)(new SendRosterChangedMail((int) $data['roster']->getId(), 999999, 'x'));
    }

    public function testARosterThatNoLongerExistsIsSkippedWithAWarning(): void
    {
        $data = $this->createRoster();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $this->handler($mailer, $logger)(new SendRosterChangedMail(999999, (int) $data['anna']->getId(), 'x'));
    }

    /** @param array<string, mixed> $data */
    private function message(array $data, string $description): SendRosterChangedMail
    {
        return new SendRosterChangedMail((int) $data['roster']->getId(), (int) $data['anna']->getId(), $description);
    }

    private function handler(MailerInterface $mailer, ?LoggerInterface $logger = null): SendRosterChangedMailHandler
    {
        $container = self::getContainer();

        return new SendRosterChangedMailHandler(
            $container->get(RosterRepository::class),
            $container->get(EmployeeRepository::class),
            $container->get(RosterChangedMailBuilder::class),
            $mailer,
            $logger ?? $this->createStub(LoggerInterface::class),
        );
    }
}
