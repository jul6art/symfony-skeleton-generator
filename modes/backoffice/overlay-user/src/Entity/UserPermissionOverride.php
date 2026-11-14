<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\AclBundle\Doctrine\AbstractUserPermissionOverride;
use Jul6Art\CoreBundle\Entity\Traits\IdTrait;

/**
 * Une décision par personne, qui l'emporte sur ce que ses rôles disent — dans les deux sens.
 *
 * Les colonnes viennent d'`acl-bundle` (≥ 1.2, son ADR-0001) : le compte
 * (`user_id`, résolu vers `App\Entity\User`), `permission`, `granted`. Cette classe ne porte que ce qui est au
 * projet : la table, son unicité, l'identifiant.
 */
#[ORM\Entity]
#[ORM\Table(name: 'user_permission_override')]
#[ORM\UniqueConstraint(name: 'uniq_user_permission', columns: ['user_id', 'permission'])]
class UserPermissionOverride extends AbstractUserPermissionOverride
{
    use IdTrait;
}
