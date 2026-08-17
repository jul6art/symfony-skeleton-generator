<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Filet de sécurité minimal : les pages publiques répondent et l'administration
 * est fermée. Aucune de ces requêtes ne touche la base de données.
 */
final class SmokeTest extends WebTestCase
{
    #[DataProvider('publicUrls')]
    public function testPublicPageIsReachable(string $url): void
    {
        $client = static::createClient();

        if ('/register' === $url && false === static::getContainer()->getParameter('app.registration_enabled')) {
            self::markTestSkipped("L'inscription publique est désactivée (APP_REGISTRATION_ENABLED).");
        }

        $client->request('GET', $url);

        self::assertResponseIsSuccessful();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function publicUrls(): iterable
    {
        yield 'accueil' => ['/'];
        yield 'connexion' => ['/login'];
        yield 'inscription' => ['/register'];
        yield 'mot de passe oublié' => ['/reset-password'];
    }

    public function testAdminAreaRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/users');

        self::assertResponseRedirects();
    }
}
