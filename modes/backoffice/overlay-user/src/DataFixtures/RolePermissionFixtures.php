<?php

declare(strict_types=1);

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Jul6Art\AclBundle\Doctrine\PermissionSeeder;

/**
 * Ce que chaque rôle a le droit de faire, tel que `DefaultRolePermissions` le décrit.
 *
 * ⚠️ Cette classe existe parce que `doctrine:fixtures:load` PURGE : elle efface `role_permission` comme le
 * reste. Sans ces lignes, un administrateur fraîchement chargé n'a AUCUNE permission — le moteur refuse tout
 * ce que le stockage n'accorde pas — et chaque écran gardé répond 403 sans rien dire. La graine d'`acl-bundle`
 * (≥ 1.2) fait ce que `acl:permissions:seed` fait au déploiement.
 *
 * ⚠️ Le super-admin n'y figure pas, et c'est `DefaultRolePermissions` qui le décide : le moteur le laisse passer
 * AVANT de consulter le stockage — des lignes ne lui donneraient rien, et deviendraient retirables à l'écran.
 */
final class RolePermissionFixtures extends Fixture
{
    public function __construct(
        private readonly PermissionSeeder $seeder,
    ) {
    }

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        $this->seeder->seed();
    }
}
