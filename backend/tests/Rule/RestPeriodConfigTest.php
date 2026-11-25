<?php

declare(strict_types=1);

namespace App\Tests\Rule;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/** The minimum rest is a parameter (RULES backend 9), not a number hard-coded in the rule. */
final class RestPeriodConfigTest extends KernelTestCase
{
    public function testTheDefaultMinimumRestIsElevenHours(): void
    {
        self::bootKernel();

        self::assertSame(11, self::getContainer()->getParameter('app.rest_period_hours'));
    }
}
