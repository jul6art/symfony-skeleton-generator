<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Acl\DoctrinePermissionStore;
use App\Entity\User;
use App\Security\DefaultRolePermissions;
use App\Security\UserRoles;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * Le filtre de plage de dates de la table, vérifié là où il agit : dans l'API.
 *
 * ⚠️ Ce test existe parce que le filtre ne filtrait RIEN. Il s'affichait parfaitement — champ,
 * icône calendrier, bouton de reset, libellé « depuis le … » —, le contrôleur Stimulus postait
 * bien `createdAt[after]` et `createdAt[before]`, et l'entité ne déclarait aucun `DateFilter`.
 * API Platform jette en silence un paramètre qu'aucun filtre ne réclame : 103 lignes avant, 103
 * après un `before=2020-01-01` (mesuré contre l'API le 2026-08-24).
 *
 * Un test de configuration n'aurait rien vu : les deux configurations étaient correctes, c'est
 * leur CORRESPONDANCE qui manquait. D'où des assertions sur des DÉCOMPTES, seuls capables de
 * distinguer « filtre appliqué » de « paramètre ignoré ».
 */
#[CoversNothing]
final class UserDateFilterTest extends WebTestCase
{
    public function testTheBeforeBoundActuallyRemovesRows(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();
        $actor = $this->createUser(UserRoles::ROLE_ADMIN);
        $this->createUsersCreatedAt(['2020-01-15', '2023-06-01', '2026-08-01']);

        self::assertSame(4, $this->countUsers($client, $actor), 'Les trois comptes datés, plus celui qui interroge.');
        self::assertSame(1, $this->countUsers($client, $actor, ['createdAt[before]' => '2021-01-01']), 'Seul celui de 2020.');
        self::assertSame(2, $this->countUsers($client, $actor, ['createdAt[before]' => '2024-01-01']), '2020 et 2023.');
    }

    public function testTheAfterBoundActuallyRemovesRows(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();
        $actor = $this->createUser(UserRoles::ROLE_ADMIN);
        $this->createUsersCreatedAt(['2020-01-15', '2023-06-01', '2026-08-01']);

        // Le compte qui interroge est créé maintenant : il passe donc toutes les bornes basses.
        self::assertSame(2, $this->countUsers($client, $actor, ['createdAt[after]' => '2026-01-01']), '2026-08 et le lecteur.');
        self::assertSame(0, $this->countUsers($client, $actor, ['createdAt[after]' => '2099-01-01']), 'Aucun compte dans le futur.');
    }

    /** Les deux bornes ensemble décrivent une plage, ce que l'utilisateur saisit réellement. */
    public function testBothBoundsDescribeARange(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();
        $actor = $this->createUser(UserRoles::ROLE_ADMIN);
        $this->createUsersCreatedAt(['2020-01-15', '2023-06-01', '2026-08-01']);

        self::assertSame(1, $this->countUsers($client, $actor, [
            'createdAt[after]' => '2023-01-01',
            'createdAt[before]' => '2024-01-01',
        ]));
    }

    /**
     * ⚠️ Un JWT en en-tête, et non `loginUser()`. Le pare-feu `api` est `stateless` : il vide le
     * stockage de jetons à la fin de CHAQUE requête, donc un `loginUser()` ne vaut que pour la
     * première — la deuxième repart en 401 « JWT Token not found », ce qui ressemble à un défaut
     * d'autorisation alors que c'est un défaut de méthode de test.
     *
     * C'est aussi la façon dont la vraie table interroge l'API : le layout expose `jwt_token()`
     * dans `window.jwtToken`, et le contrôleur Stimulus signe chaque appel avec.
     *
     * @param array<string, string> $filters
     */
    private function countUsers(KernelBrowser $client, User $actor, array $filters = []): int
    {
        $jwtManager = static::getContainer()->get(JWTTokenManagerInterface::class);

        $client->request(
            'GET',
            '/api/users'.([] === $filters ? '' : '?'.http_build_query($filters)),
            server: [
                'HTTP_ACCEPT' => 'application/ld+json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$jwtManager->create($actor),
            ],
        );

        self::assertResponseIsSuccessful();

        /** @var array{totalItems?: int} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return $payload['totalItems'] ?? -1;
    }

    /**
     * ⚠️ Les dates se posent en SQL : `createdAt` vient de `TimestampableTrait`, colonne
     * `updatable: false` remplie en `#[ORM\PrePersist]`, sans `setCreatedAt()`. Même méthode que
     * `UserFixtures::spreadCreationDates()`.
     *
     * @param list<string> $dates
     */
    private function createUsersCreatedAt(array $dates): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();

        foreach ($dates as $date) {
            $user = $this->createUser(UserRoles::ROLE_USER);
            $connection->executeStatement(
                'UPDATE "user" SET created_at = :createdAt WHERE id = :id',
                [
                    'createdAt' => new DateTimeImmutable($date.' 12:00:00')->format('Y-m-d H:i:s'),
                    'id' => $user->getId(),
                ],
            );
        }
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

    private function createUser(string $role): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User()
            ->setEmail(sprintf('%s@example.test', uniqid('date', false)))
            ->setFirstName('Ada')
            ->setLastName('Lovelace')
            ->setRoles(UserRoles::ROLE_USER === $role ? [] : [$role])
            ->setIsActive(true);
        $user->setPassword($hasher->hashPassword($user, 'correct-horse-battery'));

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
