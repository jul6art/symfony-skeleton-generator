<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\UserPermissionOverride;
use App\Security\PermissionCodes;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;

/**
 * Deux dérogations par personne, une dans chaque sens.
 *
 * C'est le sens « REFUSER » qui justifie l'existence de cette table : sans elle, retirer une
 * permission à quelqu'un obligerait à lui inventer un rôle. Une fixture qui ne montrerait que le
 * sens « accorder » laisserait croire à un doublon du rôle.
 */
final class UserPermissionOverrideFixtures extends Fixture implements DependentFixtureInterface
{
    #[Override]
    public function load(ObjectManager $manager): void
    {
        // Un administrateur à qui l'on RETIRE la création de comptes, alors que son rôle l'accorde.
        $manager->persist(
            new UserPermissionOverride()
                ->setUser($this->getReference(UserFixtures::USER_ADMIN, User::class))
                ->setPermission(PermissionCodes::USER_CREATE)
                ->setGranted(false),
        );

        // Un compte ordinaire à qui l'on ACCORDE la lecture des comptes, que son rôle n'a pas.
        $manager->persist(
            new UserPermissionOverride()
                ->setUser($this->getReference(UserFixtures::USER_STANDARD, User::class))
                ->setPermission(PermissionCodes::USER_READ)
                ->setGranted(true),
        );

        $manager->flush();
    }

    /**
     * @return list<class-string>
     */
    #[Override]
    public function getDependencies(): array
    {
        return [UserFixtures::class];
    }
}
