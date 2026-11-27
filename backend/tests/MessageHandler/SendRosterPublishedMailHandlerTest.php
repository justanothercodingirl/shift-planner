<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Mail\RosterMailBuilder;
use App\Message\SendRosterPublishedMail;
use App\MessageHandler\SendRosterPublishedMailHandler;
use App\Repository\EmployeeRepository;
use App\Repository\RosterRepository;
use App\Tests\Mail\RosterTestData;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;

final class SendRosterPublishedMailHandlerTest extends KernelTestCase
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

    public function testSendsOneMailWithTheRosterPdfToTheEmployee(): void
    {
        $data = $this->createRoster();
        $handler = $this->handler(self::getContainer()->get(MailerInterface::class));

        $handler(new SendRosterPublishedMail((int) $data['roster']->getId(), (int) $data['anna']->getId()));

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailAddressContains($email, 'To', 'anna-mail@example.com');
        self::assertEmailAttachmentCount($email, 1);
    }

    public function testAFailingMailServerIsLoggedAndRethrownSoMessengerCanRetry(): void
    {
        $data = $this->createRoster();
        $mailer = $this->createStub(MailerInterface::class);
        $mailer->method('send')->willThrowException(new TransportException('Connection refused'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(
            self::stringContains('roster'),
            self::callback(static fn (array $context): bool => ($context['employeeId'] ?? null) === $data['anna']->getId()),
        );

        $this->expectException(TransportException::class);
        $this->handler($mailer, $logger)(new SendRosterPublishedMail((int) $data['roster']->getId(), (int) $data['anna']->getId()));
    }

    public function testAnEmployeeWhoNoLongerExistsIsSkippedWithAWarning(): void
    {
        $data = $this->createRoster();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $this->handler($mailer, $logger)(new SendRosterPublishedMail((int) $data['roster']->getId(), 999999));
    }

    public function testARosterThatNoLongerExistsIsSkippedWithAWarning(): void
    {
        $data = $this->createRoster();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $this->handler($mailer, $logger)(new SendRosterPublishedMail(999999, (int) $data['anna']->getId()));
    }

    private function handler(MailerInterface $mailer, ?LoggerInterface $logger = null): SendRosterPublishedMailHandler
    {
        $container = self::getContainer();

        return new SendRosterPublishedMailHandler(
            $container->get(RosterRepository::class),
            $container->get(EmployeeRepository::class),
            $container->get(RosterMailBuilder::class),
            $mailer,
            $logger ?? $this->createStub(LoggerInterface::class),
        );
    }
}
