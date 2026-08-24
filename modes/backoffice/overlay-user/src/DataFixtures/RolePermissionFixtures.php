<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\RolePermission;
use App\Security\DefaultRolePermissions;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Override;

/**
 * Ce que chaque rôle a le droit de faire, tel que `DefaultRolePermissions` le décrit.
 *
 * ⚠️ Cette classe existe parce que `doctrine:fixtures:load` PURGE : elle efface `role_permission`
 * comme le reste. Sans ces lignes, un `ROLE_ADMIN` fraîchement chargé n'a AUCUNE permission — le
 * moteur refuse tout ce que le stockage n'accorde pas — et chaque écran gardé répond 403 sans que
 * rien n'explique pourquoi. C'est exactement ce que `app:permissions:seed` fait au premier
 * déploiement ; les fixtures doivent le refaire à chaque chargement.
 *
 * ⚠️ `ROLE_SUPER_ADMIN` n'y figure pas, et c'est `DefaultRolePermissions` qui le décide : le
 * moteur le laisse passer AVANT de consulter le stockage, donc des lignes en base ne lui
 * donneraient rien de plus tout en devenant retirables depuis l'écran — on pourrait s'enfermer
 * dehors.
 */
final class RolePermissionFixtures extends Fixture
{
    #[Override]
    public function load(ObjectManager $manager): void
    {
        foreach (DefaultRolePermissions::map() as $role => $permissions) {
            foreach ($permissions as $permission) {
                $manager->persist(
                    new RolePermission()
                        ->setRoleCode($role)
                        ->setPermission($permission)
                        ->setGranted(true),
                );
            }
        }

        $manager->flush();
    }
}
