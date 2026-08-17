<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Filet de sécurité minimal : la sonde et la documentation répondent, les
 * ressources exposées sont fermées sans jeton.
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

    public function testDocumentationIsServed(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/docs', server: ['HTTP_ACCEPT' => 'text/html']);

        self::assertResponseIsSuccessful();
    }

    public function testApiRequiresAToken(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/users', server: ['HTTP_ACCEPT' => 'application/ld+json']);

        self::assertResponseStatusCodeSame(401);
    }
}
