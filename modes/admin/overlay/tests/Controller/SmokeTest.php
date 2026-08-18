<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Filet de sécurité minimal : la page d'accueil répond.
 */
final class SmokeTest extends WebTestCase
{
    public function testHomePageIsReachable(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
    }
}
