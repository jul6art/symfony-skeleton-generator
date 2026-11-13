<?php

declare(strict_types=1);

namespace App\Security;

use Jul6Art\AclBundle\Contract\PermissionCatalogInterface;

/**
 * Les permissions du produit, telles que le stockage d'`acl-bundle` les lit (≥ 1.2, son ADR-0001) : la graine
 * (`acl:permissions:seed`, les fixtures) et la grille des rôles. Les tables restent celles du produit,
 * `PermissionCodes` et `DefaultRolePermissions`.
 */
final class PermissionCatalog implements PermissionCatalogInterface
{
    #[\Override]
    public function all(): array
    {
        return PermissionCodes::all();
    }

    #[\Override]
    public function grouped(): array
    {
        return PermissionCodes::grouped();
    }

    #[\Override]
    public function defaults(): array
    {
        return DefaultRolePermissions::map();
    }

    #[\Override]
    public function superAdminOnly(): array
    {
        return [];
    }
}
