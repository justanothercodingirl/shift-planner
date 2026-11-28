<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Message\SendRosterChangedMail;
use App\Message\SendRosterPublishedMail;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/** Guards the queue settings of DECISIONS.md: retries, a failed queue, and the roster mail on the async queue. */
final class MessengerConfigTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $messenger;

    protected function setUp(): void
    {
        $config = Yaml::parseFile(dirname(__DIR__, 2).'/config/packages/messenger.yaml');
        $this->messenger = $config['framework']['messenger'];
    }

    public function testFailedMailsEndUpInTheFailedQueue(): void
    {
        self::assertSame('failed', $this->messenger['failure_transport']);
        self::assertArrayHasKey('failed', $this->messenger['transports']);
    }

    public function testTheAsyncQueueRetriesThreeTimesWithGrowingDelay(): void
    {
        $retry = $this->messenger['transports']['async']['retry_strategy'];

        self::assertSame(3, $retry['max_retries']);
        self::assertSame(1000, $retry['delay']);
        self::assertSame(2, $retry['multiplier']);
    }

    public function testTheRosterMailJobGoesToTheAsyncQueue(): void
    {
        self::assertSame('async', $this->messenger['routing'][SendRosterPublishedMail::class]);
    }

    public function testTheChangeMailJobGoesToTheAsyncQueueToo(): void
    {
        self::assertSame('async', $this->messenger['routing'][SendRosterChangedMail::class]);
    }
}
