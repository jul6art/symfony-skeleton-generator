<?php

declare(strict_types=1);

namespace App\Twig;

use App\Security\BackofficeLocales;
use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * `backoffice_locales()` — les langues que le sélecteur propose.
 *
 * Une fonction plutôt qu'un paramètre Twig global : la liste appartient à `BackofficeLocales`,
 * et la recopier dans `twig.globals` en ferait une seconde source de vérité.
 */
final class LocaleExtension extends AbstractExtension
{
    /**
     * @return list<TwigFunction>
     */
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('backoffice_locales', static fn (): array => BackofficeLocales::SUPPORTED),
        ];
    }
}
