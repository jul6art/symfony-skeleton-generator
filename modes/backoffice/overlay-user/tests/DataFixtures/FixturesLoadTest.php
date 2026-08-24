<?php

declare(strict_types=1);

namespace App\Tests\DataFixtures;

use App\Entity\RolePermission;
use App\Entity\User;
use App\Security\DefaultRolePermissions;
use App\Security\PermissionCodes;
use App\Security\UserRoles;
use Doctrine\Bundle\FixturesBundle\Loader\SymfonyFixturesLoader;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function count;
use function in_array;
use function sprintf;

/**
 * Les fixtures chargent, et ce qu'elles posent est ce dont les écrans ont besoin.
 *
 * ⚠️ `doctrine:fixtures:load` PURGE. Tout ce sans quoi l'application ne démarre pas doit donc
 * vivre DANS les fixtures et pas seulement dans une commande de seed : avant ce lot, un
 * `make fixtures` laissait la base sans aucun compte ET sans aucune permission de rôle — le
 * moteur ACL refusant tout ce que le stockage n'accorde pas, l'application devenait inutilisable
 * jusqu'à ce que quelqu'un rejoue `make user-create` et `make permissions-seed` à la main.
 */
#[CoversNothing]
final class FixturesLoadTest extends KernelTestCase
{
    public function testTheFixturesLoadAndFillEveryScreen(): void
    {
        self::bootKernel();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->createSchema($entityManager);
        $this->loadFixtures($entityManager);

        $users = $entityManager->getRepository(User::class)->findAll();

        self::assertGreaterThanOrEqual(
            100,
            count($users),
            'La liste doit avoir de quoi paginer, filtrer et trier : une seule ligne ne prouve rien.',
        );

        // Un compte par rôle assignable, plus le compte ordinaire — sans quoi le menu d'actions
        // par ligne ne peut pas être vu autrement que sur son propre compte.
        foreach ([UserRoles::ROLE_SUPER_ADMIN, UserRoles::ROLE_ADMIN] as $role) {
            $withRole = array_filter($users, static fn (User $user): bool => in_array($role, $user->getRoles(), true));
            self::assertNotSame([], $withRole, sprintf('Aucun compte « %s ».', $role));
        }

        $inactive = array_filter($users, static fn (User $user): bool => !$user->isActive());
        self::assertNotSame([], $inactive, 'Sans compte inactif, ni le badge de statut ni l\'action « Activer » ne se voient.');
    }

    /**
     * ⚠️ La purge efface `role_permission`. Les permissions par défaut font donc partie des
     * fixtures : sans elles, un `ROLE_ADMIN` fraîchement chargé n'a RIEN et tous les écrans
     * gardés répondent 403.
     */
    public function testTheFixturesRebuildWhatThePurgeErases(): void
    {
        self::bootKernel();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->createSchema($entityManager);
        $this->loadFixtures($entityManager);

        $granted = $entityManager->getRepository(RolePermission::class)->findBy([
            'roleCode' => UserRoles::ROLE_ADMIN,
            'granted' => true,
        ]);

        self::assertCount(
            count(DefaultRolePermissions::for(UserRoles::ROLE_ADMIN)),
            $granted,
            'Les fixtures doivent poser exactement ce que `DefaultRolePermissions` décrit.',
        );

        $codes = array_map(static fn (RolePermission $row): string => $row->getPermission(), $granted);
        self::assertContains(PermissionCodes::USER_READ, $codes);

        // ⚠️ `ROLE_SUPER_ADMIN` ne reçoit JAMAIS de lignes : le moteur le laisse passer avant de
        // consulter le stockage, et des lignes en base seraient retirables depuis l'écran — on
        // pourrait s'enfermer dehors.
        self::assertSame([], $entityManager->getRepository(RolePermission::class)->findBy([
            'roleCode' => UserRoles::ROLE_SUPER_ADMIN,
        ]));
    }

    private function createSchema(EntityManagerInterface $entityManager): void
    {
        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    private function loadFixtures(EntityManagerInterface $entityManager): void
    {
        // Le service porte l'identifiant `doctrine.fixtures.loader` ; sa CLASSE n'est pas un
        // alias public, et `get(SymfonyFixturesLoader::class)` lève ServiceNotFoundException.
        $loader = static::getContainer()->get('doctrine.fixtures.loader');
        self::assertInstanceOf(SymfonyFixturesLoader::class, $loader);
        new ORMExecutor($entityManager, new ORMPurger($entityManager))->execute($loader->getFixtures());
    }
}
