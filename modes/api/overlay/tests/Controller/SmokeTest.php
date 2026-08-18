<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Filet de sécurité minimal : la sonde répond, sans toucher la base.
 */
final class SmokeTest extends WebTestCase
{
    public function testHealthIsPublic(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health');

        self::assertResponseIsSuccessful();
        self::assertJson((string) $client->getResponse()->getContent());
    }
}
