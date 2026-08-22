<?php

declare(strict_types=1);

namespace App\Security;

use ReflectionClass;

/**
 * Le catalogue des permissions de l'application.
 *
 * Il vit ici et non dans `acl-bundle`, et ce n'est pas un oubli : un catalogue est de la POLITIQUE,
 * pas de l'infrastructure. Publier `user:delete` dans un paquet vendor reviendrait à publier le
 * plan de site du projet, et à demander à toute autre application de vivre avec.
 *
 * ## Le format
 *
 * `ressource:action`, en minuscules, `[a-z0-9._-]`. Le DERNIER segment est l'action, tout ce qui
 * précède est la ressource — donc `cms:page:read` marche aussi, sans que le moteur ait à connaître
 * le nombre de niveaux.
 *
 * ## Le piège
 *
 * ⚠️ **Un code mal orthographié OUVRE l'écran, il ne le ferme pas.** Le voter s'abstient sur ce que
 * son parseur ne sait pas lire, et sous la stratégie par défaut de Symfony une décision où tous les
 * voters s'abstiennent vaut « accordé ». D'où le test `AclWiringTest`, qui passe chaque constante
 * d'ici au parseur du bundle.
 */
final class PermissionCodes
{
    // ── Comptes ──────────────────────────────────────────────────────────────
    public const string USER_READ = 'user:read';
    public const string USER_CREATE = 'user:create';
    public const string USER_UPDATE = 'user:update';
    public const string USER_DELETE = 'user:delete';
    public const string USER_ACTIVATE = 'user:activate';
    public const string USER_IMPERSONATE = 'user:impersonate';

    // ── Permissions ──────────────────────────────────────────────────────────
    public const string PERMISSION_READ = 'permission:read';
    public const string PERMISSION_UPDATE = 'permission:update';

    /**
     * Toutes les constantes, pour les fixtures, l'écran de gestion des rôles et le test de câblage.
     *
     * Lues par réflexion plutôt que recopiées : une liste tenue à la main oublie toujours la
     * dernière constante ajoutée, et c'est celle-là qui n'apparaît pas dans l'écran.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        /** @var list<string> $codes */
        $codes = array_values(new ReflectionClass(self::class)->getConstants());

        sort($codes);

        return $codes;
    }

    /**
     * Les codes groupés par ressource, tels que l'écran des rôles les affiche.
     *
     * @return array<string, list<string>>
     */
    public static function grouped(): array
    {
        $grouped = [];
        foreach (self::all() as $code) {
            $segments = explode(':', $code);
            array_pop($segments);
            $grouped[implode(':', $segments)][] = $code;
        }

        return $grouped;
    }
}
