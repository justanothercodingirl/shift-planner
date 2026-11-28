<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\Assignment;
use App\Entity\Employee;
use App\Entity\SwapRequest;
use App\Enum\Position;
use App\Mail\SwapMailBuilder;
use App\Mail\SwapMailEvent;
use App\Message\SendSwapMail;
use App\MessageHandler\SendSwapMailHandler;
use App\Repository\AssignmentRepository;
use App\Repository\SwapRequestRepository;
use App\Repository\UserRepository;
use App\Tests\Mail\RosterTestData;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;

final class SendSwapMailHandlerTest extends KernelTestCase
{
    use RosterTestData;

    private EntityManagerInterface $em;
    private SwapRequest $request;
    private Employee $anna;
    private Employee $bea;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();

        $data = $this->createRoster();
        $this->anna = $data['anna'];
        $this->bea = $this->createEmployee('Bea', Position::Barista);
        $shift = $this->createShift($data['roster'], '2031-02-04', '08:00', '14:00', Position::Barista, 1);
        $assignment = new Assignment($shift, $this->anna);
        $container->get(AssignmentRepository::class)->save($assignment);
        $this->request = new SwapRequest($shift, $assignment, $this->anna, $this->bea, new \DateTimeImmutable());
        $container->get(SwapRequestRepository::class)->save($this->request);
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testSendsTheMailToTheRecipient(): void
    {
        $handler = $this->handler(self::getContainer()->get(MailerInterface::class));

        $handler(new SendSwapMail((int) $this->request->getId(), (int) $this->bea->getUser()->getId(), SwapMailEvent::Requested));

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailAddressContains($email, 'To', 'bea-mail@example.com');
        self::assertEmailTextBodyContains($email, 'Anna asks you to take over this shift');
        self::assertEmailAttachmentCount($email, 0);
    }

    public function testAFailingMailServerIsLoggedAndRethrownSoMessengerCanRetry(): void
    {
        $mailer = $this->createStub(MailerInterface::class);
        $mailer->method('send')->willThrowException(new TransportException('Connection refused'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(
            self::stringContains('swap mail'),
            self::callback(fn (array $context): bool => ($context['swapRequestId'] ?? null) === $this->request->getId()),
        );

        $this->expectException(TransportException::class);
        $this->handler($mailer, $logger)(new SendSwapMail((int) $this->request->getId(), (int) $this->bea->getUser()->getId(), SwapMailEvent::Requested));
    }

    public function testARequestThatNoLongerExistsIsSkippedWithAWarning(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $this->handler($mailer, $logger)(new SendSwapMail(999999, (int) $this->bea->getUser()->getId(), SwapMailEvent::Requested));
    }

    public function testARecipientWhoNoLongerExistsIsSkippedWithAWarning(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $this->handler($mailer, $logger)(new SendSwapMail((int) $this->request->getId(), 999999, SwapMailEvent::Requested));
    }

    private function handler(MailerInterface $mailer, ?LoggerInterface $logger = null): SendSwapMailHandler
    {
        $container = self::getContainer();

        return new SendSwapMailHandler(
            $container->get(SwapRequestRepository::class),
            $container->get(UserRepository::class),
            $container->get(SwapMailBuilder::class),
            $mailer,
            $logger ?? $this->createStub(LoggerInterface::class),
        );
    }
}
