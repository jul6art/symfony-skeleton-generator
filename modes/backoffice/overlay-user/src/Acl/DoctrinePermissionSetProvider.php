<?php

declare(strict_types=1);

namespace App\Acl;

use App\Entity\User;
use App\Repository\RolePermissionRepository;
use App\Repository\UserPermissionOverrideRepository;
use Jul6Art\AclBundle\Contract\AclUserInterface;
use Jul6Art\AclBundle\Contract\PermissionSetProviderInterface;
use Override;

/**
 * D'où le moteur de permissions lit — la seule chose qu'`acl-bundle` ne peut pas fournir, parce que
 * le stockage appartient à l'application.
 *
 * ⚠️ **Sans cette classe enregistrée, le moteur REFUSE tout sauf un super-admin.** C'est le sens de
 * défaillance voulu : une ACL qui autorise quand son stockage manque est une faille invisible dans
 * une suite verte.
 *
 * ⚠️ Les deux méthodes sont appelées une fois par compte et par requête HTTP, pas une fois par
 * vérification : `AclPermissionReadService` met le résultat en mémoire. Une implémentation peut
 * donc coûter une lecture complète, mais elle ne doit surtout pas être paresseuse par permission.
 */
final readonly class DoctrinePermissionSetProvider implements PermissionSetProviderInterface
{
    public function __construct(
        private RolePermissionRepository $rolePermissions,
        private UserPermissionOverrideRepository $overrides,
    ) {
    }

    /**
     * @return array<string, bool>
     */
    #[Override]
    public function overridesFor(AclUserInterface $user): array
    {
        return $user instanceof User ? $this->overrides->findMapForUser($user) : [];
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function grantedByRolesFor(AclUserInterface $user): array
    {
        return $this->rolePermissions->findGrantedForRoles($user->getRoles());
    }
}
