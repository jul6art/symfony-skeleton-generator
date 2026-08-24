<?php

declare(strict_types=1);

namespace App\Security;

use function in_array;

/**
 * Les langues dans lesquelles l'interface est RÉELLEMENT traduite.
 *
 * Distincte de `framework.enabled_locales`, qui dit ce que le routeur accepte : cette liste
 * engage l'équipe à traduire chaque domaine (`translations/*.<code>.yaml`) dans la langue
 * ajoutée. Un code ici sans catalogue derrière donne une interface à moitié anglaise, sans que
 * rien ne le signale — le traducteur retombe silencieusement sur le repli.
 *
 * ⚠️ `LocaleCatalogueTest` fige l'égalité avec `kernel.enabled_locales` : les deux listes
 * divergent sinon à la première langue ajoutée d'un seul côté.
 */
final class BackofficeLocales
{
    /** Aligné sur `framework.default_locale`. */
    public const string DEFAULT = 'en';

    /** @var list<string> */
    public const array SUPPORTED = ['en', 'fr'];

    public static function isSupported(string $locale): bool
    {
        return in_array($locale, self::SUPPORTED, true);
    }
}
