<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Roster;
use App\Message\SendRosterPublishedMail;
use App\Repository\RosterRepository;
use App\Service\RosterNotifier;
use App\Tests\Mail\RosterTestData;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class RosterNotifierTest extends KernelTestCase
{
    use RosterTestData;

    private EntityManagerInterface $em;
    private RosterNotifier $notifier;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->notifier = $container->get(RosterNotifier::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testOneJobIsQueuedForEveryActiveEmployee(): void
    {
        $data = $this->createRoster();

        $this->notifier->notifyPublished($data['roster']);

        $queued = $this->queued();
        self::assertEqualsCanonicalizing(
            [$data['anna']->getId(), $data['ben']->getId(), $data['clara']->getId()],
            array_map(static fn (SendRosterPublishedMail $m): int => $m->employeeId, $queued),
        );
        foreach ($queued as $message) {
            self::assertSame($data['roster']->getId(), $message->rosterId);
        }
    }

    public function testDeactivatedEmployeesGetNothing(): void
    {
        $data = $this->createRoster();
        $gone = $this->createEmployee('Gone', active: false);

        $this->notifier->notifyPublished($data['roster']);

        self::assertNotContains($gone->getId(), array_map(static fn (SendRosterPublishedMail $m): int => $m->employeeId, $this->queued()));
        self::assertCount(3, $this->queued());
    }

    public function testAskingAboutADraftRosterIsAProgrammingErrorAndQueuesNothing(): void
    {
        $this->createRoster();
        $draft = new Roster(new \DateTimeImmutable('2031-03-03'));
        self::getContainer()->get(RosterRepository::class)->save($draft);

        try {
            $this->notifier->notifyPublished($draft);
            self::fail('Expected a LogicException for a roster that is not published.');
        } catch (\LogicException) {
        }

        self::assertSame([], $this->queued());
    }

    /** @return list<SendRosterPublishedMail> */
    private function queued(): array
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return array_map(
            static fn ($envelope): SendRosterPublishedMail => $envelope->getMessage(),
            $transport->getSent(),
        );
    }
}
