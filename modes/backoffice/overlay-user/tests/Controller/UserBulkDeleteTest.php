<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Acl\DoctrinePermissionStore;
use App\Entity\User;
use App\Security\DefaultRolePermissions;
use App\Security\PermissionCodes;
use App\Security\UserRoles;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * La suppression de masse des comptes.
 *
 * ⚠️ Ce test existe parce que la table OFFRAIT l'action sans que la route existe :
 * `UserDataTableConfigProvider` déclarait `bulkRoute: '/admin/users/bulk-delete'` alors que
 * `debug:router` ne connaissait que `bulk-activate` et `bulk-deactivate`. Cocher des lignes et
 * lancer la suppression postait donc sur un **404** — un bouton visible qui ne peut pas aboutir,
 * c'est-à-dire le bug d'interface que `claude_project.md` interdit nommément.
 */
#[CoversNothing]
final class UserBulkDeleteTest extends WebTestCase
{
    public function testTheBulkRouteSoftDeletesTheSelectionButNeverTheActor(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();

        $actor = $this->createUser(UserRoles::ROLE_SUPER_ADMIN);
        $victim = $this->createUser(UserRoles::ROLE_ADMIN);
        $client->loginUser($actor);

        $token = $this->bulkTokenFromTable($client);
        $client->request('POST', '/admin/users/bulk-delete', [
            'ids' => [$victim->getId(), $actor->getId()],
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/admin/users');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $repository = $entityManager->getRepository(User::class);

        // ⚠️ La suppression est DOUCE : on interroge `deletedAt`, pas la disparition de la ligne.
        // Asserter que `find()` rend `null` mesurerait en réalité si le filtre Doctrine
        // `SoftDeleteFilter` est actif dans l'environnement de test — pas si la route a fait son
        // travail.
        $deleted = $repository->find($victim->getId());
        $kept = $repository->find($actor->getId());

        self::assertInstanceOf(User::class, $deleted);
        self::assertInstanceOf(User::class, $kept);
        self::assertTrue($deleted->isDeleted(), 'Le compte sélectionné doit être supprimé.');
        self::assertFalse($kept->isDeleted(), 'On ne se supprime jamais soi-même.');

        // L'adresse est libérée : sans ça, recréer un compte supprimé échoue sur la contrainte
        // UNIQUE, ce qui est incompréhensible depuis l'écran.
        self::assertNotSame($victim->getEmail(), $deleted->getEmail());
    }

    public function testTheBulkRouteIsClosedToARoleWithoutTheDeletePermission(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();

        // `DefaultRolePermissions` n'accorde PAS `user:delete` à un administrateur ordinaire.
        $client->loginUser($this->createUser(UserRoles::ROLE_ADMIN));

        $token = $this->bulkTokenFromTable($client);
        $client->request('POST', '/admin/users/bulk-delete', [
            'ids' => [1],
            '_token' => $token,
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * ⚠️ Non-régression du flush manquant. `BulkActionRunner::run()` ouvre une transaction,
     * applique l'action et commite — sans jamais flusher. Les routes de masse répondaient donc
     * 302 avec un flash de succès et n'écrivaient RIEN : découvert le 2026-08-24 en testant la
     * suppression, et vrai depuis le premier jour pour l'activation aussi.
     */
    public function testTheBulkActivationActuallyWrites(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();

        $actor = $this->createUser(UserRoles::ROLE_SUPER_ADMIN);
        $target = $this->createUser(UserRoles::ROLE_ADMIN);
        $target->setIsActive(false);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->flush();

        $client->loginUser($actor);
        $token = $this->bulkTokenFromTable($client);
        $client->request('POST', '/admin/users/bulk-activate', [
            'ids' => [$target->getId()],
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/admin/users');

        $entityManager->clear();
        $reloaded = $entityManager->getRepository(User::class)->find($target->getId());

        self::assertInstanceOf(User::class, $reloaded);
        self::assertTrue($reloaded->isActive(), 'Le flash annonçait le succès et rien n\'était écrit.');
    }

    /** La colonne des rôles : c'est l'information qu'on vient chercher dans une liste de comptes. */
    public function testTheUserTableCarriesTheRolesColumn(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();

        $client->loginUser($this->createUser(UserRoles::ROLE_ADMIN));
        $crawler = $client->request('GET', '/admin/users');

        $columns = json_decode(
            (string) $crawler->filter('table[data-controller="core--datatable"]')->attr('data-core--datatable-columns-value'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($columns);

        $byData = [];
        foreach ($columns as $column) {
            self::assertIsArray($column);
            $byData[$column['data'] ?? ''] = $column;
        }

        self::assertArrayHasKey('roles', $byData, 'La table doit dire de quoi chaque compte a le droit.');

        // ⚠️ `roles` est une colonne JSON : la déclarer triable ferait ignorer le tri côté serveur
        // sans que rien ne le signale (piège documenté du mode backoffice). L'aide du bundle
        // exprime ça par `orderable: false` — c'est le nom que DataTables attend.
        self::assertFalse($byData['roles']['orderable'] ?? true, 'Une colonne JSON n\'est pas triable.');
    }

    /**
     * Le jeton se LIT sur la page, il ne se fabrique pas.
     *
     * ⚠️ `csrf_token_manager->getToken()` hors requête lève `SessionNotFoundException` : le
     * stockage est la session. Passer par la page rendue est aussi ce qui prouve que le partial
     * `@Datatable/datatable/_csrf.html.twig` est bien inclus — sans lui, toute action POST du
     * tableau répond 419 et rien ne le signale.
     */
    private function bulkTokenFromTable(KernelBrowser $client): string
    {
        $crawler = $client->request('GET', '/admin/users');
        $token = $crawler->filter('table[data-controller="core--datatable"]')->attr('data-core--datatable-bulk-csrf-value');

        self::assertNotNull($token, 'Le partial CSRF du tableau doit être inclus DANS la balise <table>.');

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
        // La suppression n'est dans les défauts d'aucun rôle : on l'accorde explicitement au
        // super-admin ? Non — le moteur le laisse déjà passer. Rien à semer de plus.
        self::assertNotContains(PermissionCodes::USER_DELETE, DefaultRolePermissions::for(UserRoles::ROLE_ADMIN));

        $entityManager->flush();
    }

    private function createUser(string $role): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User()
            ->setEmail(sprintf('%s@example.test', uniqid('bulk', false)))
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
