<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ErrorShapeTest extends WebTestCase
{
    public function testUnknownApiPathReturnsSharedErrorShape(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/does-not-exist');

        self::assertResponseStatusCodeSame(404);
        $body = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertSame('NOT_FOUND', $body['error']['code']);
    }
}
