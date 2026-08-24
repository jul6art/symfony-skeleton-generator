<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Acl\DoctrinePermissionStore;
use App\Entity\User;
use App\Security\DefaultRolePermissions;
use App\Security\UserRoles;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function sprintf;

/**
 * Changer de langue — dans l'administration ET sur les pages publiques.
 *
 * ⚠️ La langue voyage dans le CORPS de la requête, pas dans l'URL : c'est le contrat du
 * contrôleur Stimulus du bundle, et une route qui la prendrait en paramètre d'URL serait un
 * no-op silencieux.
 *
 * ⚠️ Le test qui compte est le second : il vérifie que la page RENDUE change de langue. Une
 * assertion sur la colonne `locale` ou sur la session ne prouverait rien — l'écueil de ce lot est
 * ailleurs, dans l'ordre des écouteurs. Le pare-feu remplit `TokenStorage` à la priorité 8, donc
 * tout ce qui a besoin de `$user` doit tourner EN DESSOUS ; mais `LocaleAwareListener` diffuse la
 * locale aux services (le traducteur en tête) à la priorité 15 et ne repasse jamais. Poser
 * `$request->setLocale()` après 15 laisse donc le traducteur bloqué sur la langue par défaut :
 * la colonne est écrite, la session aussi, et l'écran reste en anglais.
 */
#[CoversNothing]
final class LocaleSwitchTest extends WebTestCase
{
    public function testAnAuthenticatedAccountSwitchesTheWholeInterface(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();

        $user = $this->createUser();
        $client->loginUser($user);

        $client->request('POST', '/locale', ['locale' => 'fr', '_token' => $this->token($client)]);
        self::assertResponseStatusCodeSame(204);

        $crawler = $client->request('GET', '/admin/account/profile');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Mon profil', $crawler->filter('h1')->text(), 'La page rendue doit être en français.');

        // Et le choix est DURABLE : il survit à la requête, donc il est en base.
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $reloaded = $entityManager->getRepository(User::class)->find($user->getId());
        self::assertInstanceOf(User::class, $reloaded);
        self::assertSame('fr', $reloaded->getLocale());
    }

    /** Les pages d'authentification aussi : c'est là qu'on choisit sa langue avant d'avoir un compte. */
    public function testAnAnonymousVisitorSwitchesTheSignInPage(): void
    {
        $client = static::createClient();

        $client->request('POST', '/locale', ['locale' => 'fr', '_token' => $this->token($client)]);
        self::assertResponseStatusCodeSame(204);

        $crawler = $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Connexion', $crawler->filter('h1')->text());
    }

    public function testAnUnsupportedLocaleIsRefused(): void
    {
        $client = static::createClient();

        $client->request('POST', '/locale', ['locale' => 'de', '_token' => $this->token($client)]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testTheSwitchRequiresItsCsrfToken(): void
    {
        $client = static::createClient();

        $client->request('POST', '/locale', ['locale' => 'fr', '_token' => 'faux']);

        self::assertResponseStatusCodeSame(403);
    }

    /** Le sélecteur est dans la barre du haut, et il propose les deux langues. */
    public function testTheSwitcherIsOfferedInTheTopBar(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();

        $client->loginUser($this->createUser());
        $crawler = $client->request('GET', '/admin');

        self::assertSame(1, $crawler->filter('[data-controller="ui--locale-switcher"]')->count());
        self::assertSame(2, $crawler->filter('button[data-locale]')->count(), 'Une entrée par langue déclarée.');
    }

    /** Le jeton se lit sur une page rendue : le stockage CSRF est la session. */
    private function token(KernelBrowser $client): string
    {
        $crawler = $client->request('GET', '/login');
        $token = $crawler->filter('meta[name="locale-switch-token"]')->attr('content');

        self::assertNotNull($token, 'Les pages doivent exposer le jeton de bascule.');

        return $token;
    }

    private function createSchema(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $store = static::getContainer()->get(DoctrinePermissionStore::class);
        foreach (DefaultRolePermissions::map() as $role => $permissions) {
            foreach ($permissions as $permission) {
                $store->grantToRole($role, $permission, null);
            }
        }
        $entityManager->flush();
    }

    private function createUser(): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User()
            ->setEmail(sprintf('%s@example.test', uniqid('locale', false)))
            ->setFirstName('Ada')
            ->setLastName('Lovelace')
            ->setRoles([UserRoles::ROLE_ADMIN])
            ->setIsActive(true);
        $user->setPassword($hasher->hashPassword($user, 'correct-horse-battery'));

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
