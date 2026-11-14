<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\AclBundle\Doctrine\AbstractRolePermission;
use Jul6Art\CoreBundle\Entity\Traits\IdTrait;

/**
 * Une permission accordée — ou explicitement refusée — à un rôle.
 *
 * Les colonnes et leur sens viennent d'`acl-bundle` (≥ 1.2, son ADR-0001) :
 * `role_code`, `permission`, `granted` — une ligne à `false`, c'est « décidé, et refusé », et la graine ne la
 * rouvre jamais. Cette classe ne porte que ce qui est au projet : la table, son unicité, son index,
 * l'identifiant.
 */
#[ORM\Entity]
#[ORM\Table(name: 'role_permission')]
#[ORM\UniqueConstraint(name: 'uniq_role_permission', columns: ['role_code', 'permission'])]
#[ORM\Index(name: 'idx_role_permission_role', columns: ['role_code'])]
class RolePermission extends AbstractRolePermission
{
    use IdTrait;
}
