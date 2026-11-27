<?php

declare(strict_types=1);

namespace App\Tests\Mail;

use App\Mail\RosterMailBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RosterMailBuilderTest extends KernelTestCase
{
    use RosterTestData;

    private EntityManagerInterface $em;
    private RosterMailBuilder $builder;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->builder = $container->get(RosterMailBuilder::class);
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

        $email = $this->builder->build($data['roster'], $data['anna']);

        self::assertSame('anna-mail@example.com', $email->getTo()[0]->getAddress());
        self::assertSame('Anna', $email->getTo()[0]->getName());
        self::assertSame(self::getContainer()->getParameter('app.mail_from'), $email->getFrom()[0]->getAddress());
        self::assertCount(1, $email->getTo());
    }

    public function testTheSubjectNamesTheWeek(): void
    {
        $data = $this->createRoster();

        $email = $this->builder->build($data['roster'], $data['anna']);

        self::assertSame('Roster for 3 Feb – 9 Feb 2031 is published', $email->getSubject());
    }

    public function testTheTextListsOnlyTheEmployeesOwnShifts(): void
    {
        $data = $this->createRoster();

        $text = (string) $this->builder->build($data['roster'], $data['anna'])->getTextBody();

        self::assertStringContainsString('Hi Anna,', $text);
        self::assertStringContainsString('- Mon 3 Feb, 08:00–14:00, barista', $text);
        self::assertStringNotContainsString('18:00', $text);
        self::assertStringNotContainsString('Ben', $text);
        self::assertStringNotContainsString('You have no shifts this week.', $text);
    }

    public function testAnOvernightShiftIsMarked(): void
    {
        $data = $this->createRoster();

        $text = (string) $this->builder->build($data['roster'], $data['ben'])->getTextBody();

        self::assertStringContainsString('- Mon 3 Feb, 18:00–02:00 (+1 day), service', $text);
    }

    public function testSomeoneWithoutShiftsIsToldSo(): void
    {
        $data = $this->createRoster();

        $text = (string) $this->builder->build($data['roster'], $data['clara'])->getTextBody();

        self::assertStringContainsString('You have no shifts this week.', $text);
        self::assertStringNotContainsString('- Mon', $text);
    }

    public function testTheTextExplainsTheAttachedRoster(): void
    {
        $data = $this->createRoster();

        $text = (string) $this->builder->build($data['roster'], $data['clara'])->getTextBody();

        self::assertStringContainsString('attached PDF', $text);
        self::assertStringContainsString('swap', $text);
    }

    public function testTheWholeRosterIsAttachedAsAPdf(): void
    {
        $data = $this->createRoster();

        $email = $this->builder->build($data['roster'], $data['clara']);

        $attachments = $email->getAttachments();
        self::assertCount(1, $attachments);
        self::assertSame('roster-2031-02-03.pdf', $attachments[0]->getFilename());
        self::assertSame('application/pdf', $attachments[0]->getMediaType().'/'.$attachments[0]->getMediaSubtype());
        self::assertStringStartsWith('%PDF', $attachments[0]->getBody());
    }
}
