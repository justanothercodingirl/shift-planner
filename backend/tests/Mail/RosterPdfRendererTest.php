<?php

declare(strict_types=1);

namespace App\Tests\Mail;

use App\Enum\Position;
use App\Mail\RosterPdfRenderer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RosterPdfRendererTest extends KernelTestCase
{
    use RosterTestData;

    private EntityManagerInterface $em;
    private RosterPdfRenderer $renderer;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->renderer = $container->get(RosterPdfRenderer::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    public function testTheHeadingNamesTheWeek(): void
    {
        $html = $this->renderer->html($this->createRoster()['roster']);

        self::assertStringContainsString('Roster 3 Feb – 9 Feb 2031', $html);
    }

    public function testEveryDayOfTheWeekIsListedEvenWithoutShifts(): void
    {
        $html = $this->renderer->html($this->createRoster()['roster']);

        foreach (['Mon 3 Feb', 'Tue 4 Feb', 'Wed 5 Feb', 'Thu 6 Feb', 'Fri 7 Feb', 'Sat 8 Feb', 'Sun 9 Feb'] as $day) {
            self::assertStringContainsString($day, $html);
        }
    }

    public function testEveryShiftShowsItsTimesPositionAndPeople(): void
    {
        $html = $this->renderer->html($this->createRoster()['roster']);

        self::assertStringContainsString('08:00–14:00', $html);
        self::assertStringContainsString('Barista', $html);
        self::assertStringContainsString('Anna', $html);
        self::assertStringContainsString('18:00–02:00', $html);
        self::assertStringContainsString('Service', $html);
        self::assertStringContainsString('Ben', $html);
        self::assertStringContainsString('10:00–16:00', $html);
        self::assertStringContainsString('Kitchen', $html);
    }

    public function testOvernightShiftsAreMarked(): void
    {
        $html = $this->renderer->html($this->createRoster()['roster']);

        self::assertStringContainsString('+1 day', $html);
    }

    public function testOpenPlacesAreShown(): void
    {
        $html = $this->renderer->html($this->createRoster()['roster']);

        // Monday morning: one of two places is open; Wednesday kitchen: its only place.
        self::assertStringContainsString('1 open', $html);
        self::assertSame(2, substr_count($html, '1 open'));
    }

    public function testNamesAreEscapedSoTheyCannotInjectMarkup(): void
    {
        $data = $this->createRoster();
        $shift = $this->createShift($data['roster'], '2031-02-04', '09:00', '12:00', Position::Bar, 1);
        $this->assign($shift, $this->createEmployee('Zoe <script>alert(1)</script> & Co', Position::Bar));

        $html = $this->renderer->html($data['roster']);

        self::assertStringContainsString('Zoe &lt;script&gt;alert(1)&lt;/script&gt; &amp; Co', $html);
        self::assertStringNotContainsString('<script>', $html);
    }

    public function testRenderProducesARealPdf(): void
    {
        $pdf = $this->renderer->render($this->createRoster()['roster']);

        self::assertStringStartsWith('%PDF', $pdf);
        self::assertGreaterThan(1000, strlen($pdf));
    }
}
