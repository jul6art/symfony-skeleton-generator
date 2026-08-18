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

    /**
     * Le champ CSRF écrit à la main doit porter data-controller="csrf-protection".
     * Sans cet attribut Stimulus ne charge pas le contrôleur, la valeur rendue —
     * le nom du cookie, pas un jeton — part telle quelle, et la connexion est
     * rejetée dès qu'une soumission précédente a utilisé le double envoi.
     */
    public function testLoginCsrfFieldCarriesTheStimulusController(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        self::assertCount(1, $crawler->filter('input[name="_csrf_token"][data-controller="csrf-protection"]'));
    }

    public function testBackOfficeRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin');

        self::assertResponseRedirects();
    }
}
