<?php

declare(strict_types=1);

namespace App\Tests\Mail;

use App\Mail\RosterChangedMailBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RosterChangedMailBuilderTest extends KernelTestCase
{
    use RosterTestData;

    private EntityManagerInterface $em;
    private RosterChangedMailBuilder $builder;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->builder = $container->get(RosterChangedMailBuilder::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testTheMailGoesToTheEmployeeFromTheConfiguredSender(): void
    {
        $data = $this->createRoster();

        $email = $this->builder->build($data['roster'], $data['anna'], 'You were added to something.');

        self::assertSame('anna-mail@example.com', $email->getTo()[0]->getAddress());
        self::assertSame('Anna', $email->getTo()[0]->getName());
        self::assertSame(self::getContainer()->getParameter('app.mail_from'), $email->getFrom()[0]->getAddress());
    }

    public function testTheSubjectNamesTheWeek(): void
    {
        $data = $this->createRoster();

        $email = $this->builder->build($data['roster'], $data['anna'], 'x');

        self::assertSame('Your roster for 3 Feb – 9 Feb 2031 changed', $email->getSubject());
    }

    public function testTheTextHasTheChangeAndPointsAtTheAttachment(): void
    {
        $data = $this->createRoster();

        $text = (string) $this->builder->build($data['roster'], $data['anna'], 'Your shift Mon 3 Feb 08:00 to 14:00 (barista) was cancelled.')->getTextBody();

        self::assertStringContainsString('Hi Anna,', $text);
        self::assertStringContainsString('Your shift Mon 3 Feb 08:00 to 14:00 (barista) was cancelled.', $text);
        self::assertStringContainsString('attached PDF', $text);
        self::assertStringContainsString('current roster', $text);
    }

    public function testTheUpdatedRosterIsAttachedAsAPdf(): void
    {
        $data = $this->createRoster();

        $email = $this->builder->build($data['roster'], $data['anna'], 'x');

        $attachments = $email->getAttachments();
        self::assertCount(1, $attachments);
        self::assertSame('roster-2031-02-03.pdf', $attachments[0]->getFilename());
        self::assertSame('application/pdf', $attachments[0]->getMediaType().'/'.$attachments[0]->getMediaSubtype());
        self::assertStringStartsWith('%PDF', $attachments[0]->getBody());
    }
}
