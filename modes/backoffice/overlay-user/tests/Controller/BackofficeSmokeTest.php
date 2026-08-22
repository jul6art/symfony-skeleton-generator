<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Acl\DoctrinePermissionStore;
use App\Entity\User;
use App\Security\DefaultRolePermissions;
use App\Security\UserRoles;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function sprintf;

/**
 * Le parcours minimal : les pages publiques répondent, les pages d'administration sont fermées, et
 * une fois connecté, la liste des comptes s'affiche.
 *
 * ⚠️ Deux assertions valent plus que les autres et méritent d'être lues :
 *
 * 1. **La datatable rendue par un compte NON super-admin.** C'est le seul endroit où le piège de
 *    `acl.multi_tenant` se voit : avec le défaut du bundle, `/api/users` refuse tout ce qui n'est
 *    pas super-admin, et la table s'affiche vide sans erreur.
 * 2. **Aucune clé de traduction brute dans le HTML.** Une clé oubliée ne casse rien : elle
 *    s'affiche. C'est la seule façon de l'attraper autrement qu'à l'œil.
 */
final class BackofficeSmokeTest extends WebTestCase
{
    public function testTheSignInPageIsPublicAndCarriesTheBranding(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSame(1, $crawler->filter('input[name="_username"]')->count());
        self::assertSame(1, $crawler->filter('input[name="_csrf_token"]')->count());
    }

    public function testTheBackofficeIsClosedToAnonymousVisitors(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin');

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testAnAdministratorSeesTheUserTable(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();

        $client->loginUser($this->createUser($client, UserRoles::ROLE_ADMIN));
        $crawler = $client->request('GET', '/admin/users');

        self::assertResponseIsSuccessful();

        $table = $crawler->filter('table[data-controller="core--datatable"]');
        self::assertSame(1, $table->count(), 'La table doit être montée sous l\'identifiant que datatable.stimulus_identifier déclare.');

        // Les deux partials sont obligatoires, et invisibles à l'œil : sans le premier aucune
        // action POST n'est autorisée, sans le second la barre de filtres rend des clés.
        self::assertNotSame('', $table->attr('data-core--datatable-single-csrf-value'));
        self::assertNotSame('', $table->attr('data-core--datatable-translations-value'));
    }

    /**
     * ⚠️ Le test qui attrape le piège du multi-locataire. Un administrateur ORDINAIRE — pas un
     * super-admin — doit obtenir des lignes de `/api/users`. Avec `acl.multi_tenant` laissé à
     * `true`, le moteur refuse toute vérification derrière `/api/` faute de locataire résolu, et
     * cette requête répond 403 pendant que la page, elle, s'affiche parfaitement.
     */
    public function testTheApiAnswersAPlainAdministrator(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();

        $client->loginUser($this->createUser($client, UserRoles::ROLE_ADMIN));
        $client->request('GET', '/api/users', server: ['HTTP_ACCEPT' => 'application/ld+json']);

        self::assertResponseIsSuccessful('Un administrateur non super-admin doit lire la collection : voir acl.multi_tenant.');
    }

    public function testNoRawTranslationKeyReachesTheUserTable(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();

        $client->loginUser($this->createUser($client, UserRoles::ROLE_ADMIN));
        $client->request('GET', '/admin/users');

        $html = (string) $client->getResponse()->getContent();
        foreach (['user.field.', 'user.list.', 'datatable.filter.', 'nav.'] as $prefix) {
            self::assertStringNotContainsString($prefix, $html, sprintf('Clé de traduction brute « %s… » dans la page.', $prefix));
        }
    }

    private function createSchema(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        // Drop puis create, et pas create seul : selon le pilote et la façon dont le kernel est
        // rebooté, la connexion — donc la base — peut survivre d'un test à l'autre, et un
        // `createSchema` sur un schéma déjà en place échoue sur `TableAlreadyExists`. Le drop est
        // un no-op sur une base vierge, et rend le helper idempotent partout.
        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        // Les permissions par défaut des rôles — ce que `app:permissions:seed` fait au premier
        // déploiement. Sans elles, un `ROLE_ADMIN` tout neuf n'a AUCUNE permission : le moteur
        // refuse tout ce que le stockage n'accorde pas, et ces tests vérifieraient un 403 qui ne
        // dit rien du câblage.
        $store = static::getContainer()->get(DoctrinePermissionStore::class);
        foreach (DefaultRolePermissions::map() as $role => $permissions) {
            foreach ($permissions as $permission) {
                $store->grantToRole($role, $permission, null);
            }
        }
        $entityManager->flush();
    }

    private function createUser(KernelBrowser $client, string $role): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User()
            ->setEmail(uniqid('admin', false).'@example.test')
            ->setFirstName('Ada')
            ->setLastName('Lovelace')
            ->setRoles([$role])
            ->setIsActive(true);
        $user->setPassword($hasher->hashPassword($user, 'correct-horse-battery'));

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
