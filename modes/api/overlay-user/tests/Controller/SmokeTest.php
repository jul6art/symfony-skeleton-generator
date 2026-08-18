<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Filet de sécurité minimal : la sonde répond, l'API est fermée sans jeton.
 * Aucune de ces requêtes ne touche la base de données.
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

    public function testApiRequiresAToken(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/me');

        self::assertResponseStatusCodeSame(401);
    }
}
