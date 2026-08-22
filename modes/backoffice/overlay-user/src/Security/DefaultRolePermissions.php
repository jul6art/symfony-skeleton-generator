<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Ce que chaque rôle sait faire, tant que personne n'a rien changé en base.
 *
 * C'est une graine, pas une règle : `RolePermission` est la source de vérité, et l'écran de
 * gestion des rôles l'écrit. Cette table sert à créer les lignes la première fois, et à répondre à
 * « qu'est-ce qui était prévu ? » quand quelqu'un s'est perdu dans les cases à cocher.
 *
 * ⚠️ `ROLE_SUPER_ADMIN` n'y figure pas, et c'est délibéré : le moteur le laisse passer avant même
 * de regarder le stockage. Lui donner toutes les permissions ici les rendrait retirables depuis
 * l'écran, ce qui permettrait de s'enfermer dehors.
 */
final class DefaultRolePermissions
{
    /**
     * @return array<string, list<string>>
     */
    public static function map(): array
    {
        return [
            UserRoles::ROLE_ADMIN => [
                PermissionCodes::USER_READ,
                PermissionCodes::USER_CREATE,
                PermissionCodes::USER_UPDATE,
                PermissionCodes::USER_ACTIVATE,
                PermissionCodes::PERMISSION_READ,
            ],
            // Un compte ordinaire ne gère rien : il se connecte, et règle son apparence — laquelle
            // n'est pas une permission mais une préférence, donc absente d'ici.
            UserRoles::ROLE_USER => [],
        ];
    }

    /**
     * @return list<string>
     */
    public static function for(string $role): array
    {
        return self::map()[$role] ?? [];
    }
}
