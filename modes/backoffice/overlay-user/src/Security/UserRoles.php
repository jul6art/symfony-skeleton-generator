<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Les rôles de l'application, en un seul endroit.
 *
 * Un rôle est de la MATIÈRE de décision, pas son expression : il se lit dans un voter ou dans
 * `security.yaml`, jamais dans un `is_granted()` de gabarit — sinon la règle fine se retrouve
 * recopiée dans les vues, et diverge.
 *
 * La hiérarchie est déclarée dans `security.yaml`, et c'est elle qui fait foi : `ROLE_SUPER_ADMIN`
 * hérite d'`ROLE_ADMIN`, qui hérite d'`ROLE_USER`.
 */
final class UserRoles
{
    public const string ROLE_USER = 'ROLE_USER';
    public const string ROLE_ADMIN = 'ROLE_ADMIN';
    public const string ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN';

    /** Posé par Symfony pendant une usurpation d'identité ; jamais stocké. */
    public const string ROLE_PREVIOUS_ADMIN = 'ROLE_PREVIOUS_ADMIN';

    /**
     * Les rôles qu'un formulaire propose. `ROLE_USER` n'y est pas : tout compte l'obtient par la
     * hiérarchie, l'offrir à cocher laisserait croire qu'on peut le retirer.
     *
     * @return array<string, string> libellé de traduction → rôle
     */
    public static function assignable(): array
    {
        return [
            'user.role.admin' => self::ROLE_ADMIN,
            'user.role.super_admin' => self::ROLE_SUPER_ADMIN,
        ];
    }
}
