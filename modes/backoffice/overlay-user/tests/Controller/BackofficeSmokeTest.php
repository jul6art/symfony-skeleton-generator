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

use const JSON_THROW_ON_ERROR;

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
     * La table des comptes doit porter ses actions PAR LIGNE : sans elles, la liste est un
     * cul-de-sac — on voit les comptes et on ne peut rien en faire (signalé le 2026-08-23).
     * Le rendu des boutons est fait par le contrôleur Stimulus ; ce qui se vérifie côté serveur
     * est que la configuration part remplie, et l'en-tête de colonne avec.
     */
    public function testTheUserTableCarriesItsPerRowActions(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();

        $client->loginUser($this->createUser($client, UserRoles::ROLE_ADMIN));
        $crawler = $client->request('GET', '/admin/users');

        self::assertResponseIsSuccessful();

        $actions = json_decode((string) $crawler->filter('table[data-controller="core--datatable"]')->attr('data-core--datatable-actions-value'), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($actions);
        self::assertNotSame([], $actions, 'Un administrateur doit recevoir des actions.');

        $types = array_column($actions, 'type');
        foreach (['show', 'edit', 'activate', 'deactivate'] as $expected) {
            self::assertContains($expected, $types, sprintf('L\'action « %s » manque à la table.', $expected));
        }

        // La suppression n'est PAS dans les permissions par défaut d'un administrateur
        // (`DefaultRolePermissions`) : son absence ici est la règle qui s'applique, pas un oubli.
        self::assertNotContains('delete', $types, 'Un rôle sans user:delete ne doit pas voir l\'action.');

        self::assertStringNotContainsString('action.actions', (string) $client->getResponse()->getContent(), 'L\'en-tête des actions doit être traduit.');
    }

    /**
     * La collection doit porter `id`, `isActive` et `createdAt`.
     *
     * ⚠️ Ces trois-là ne sont PAS décoratifs : les colonnes de la table les affichent, et les
     * `condition` des actions les lisent (`row.isActive`, `row.id !== <moi>`). Absents, la
     * réponse reste un 200 parfaitement valide, la colonne ID affiche « ? », le badge dit
     * « Inactive » pour tout le monde et « Activer » s'offre sur son PROPRE compte, puisque
     * `undefined !== 1` est vrai. Constaté à l'écran le 2026-08-23.
     *
     * Deux causes distinctes, d'où ce test : `id` et `createdAt` viennent de traits, dont les
     * groupes se déclarent dans `config/serializer/` ; `isActive` portait son groupe sur la
     * PROPRIÉTÉ, alors que Symfony cherche `getIsActive()` pour la nommer ainsi — le getter
     * s'appelle `isActive()`, qui décrit une propriété « active ».
     */
    public function testTheUserCollectionCarriesWhatTheTableDisplays(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();

        $client->loginUser($this->createUser($client, UserRoles::ROLE_ADMIN));
        $client->request('GET', '/api/users', server: ['HTTP_ACCEPT' => 'application/ld+json']);

        self::assertResponseIsSuccessful();

        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        $members = $payload['member'] ?? $payload['hydra:member'] ?? [];
        self::assertIsArray($members);
        self::assertNotSame([], $members);

        $first = $members[0];
        self::assertIsArray($first);
        foreach (['id', 'isActive', 'createdAt'] as $field) {
            self::assertArrayHasKey($field, $first, sprintf('« %s » manque : la colonne et les conditions d\'action en dépendent.', $field));
        }
    }

    /** La page de profil : ce que l'utilisateur vient corriger en premier, son nom et son avatar. */
    public function testAnAccountCanReachItsProfilePage(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();

        $client->loginUser($this->createUser($client, UserRoles::ROLE_ADMIN));
        $crawler = $client->request('GET', '/admin/account/profile');

        self::assertResponseIsSuccessful();
        self::assertSame(1, $crawler->filter('input[name="profile_form[firstName]"]')->count());
        self::assertSame(1, $crawler->filter('input[name="profile_form[avatarFile]"]')->count(), 'Le profil porte l\'avatar.');
        self::assertStringNotContainsString('profile.', (string) $client->getResponse()->getContent(), 'Aucune clé brute.');
    }

    /**
     * La grille rôles × permissions se rend comme le reste de la coquille : un panneau par
     * ressource, des interrupteurs, pas des cases système au milieu d'un formulaire stylé.
     */
    public function testThePermissionGridUsesTheShellVocabulary(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();

        $client->loginUser($this->createUser($client, UserRoles::ROLE_SUPER_ADMIN));
        $crawler = $client->request('GET', '/admin/role-permissions');

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('.toggle-switch')->count(), 'Les cases doivent être des interrupteurs.');
        self::assertGreaterThan(0, $crawler->filter('.panel')->count());
        self::assertStringNotContainsString('permission.column.', (string) $client->getResponse()->getContent());
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
