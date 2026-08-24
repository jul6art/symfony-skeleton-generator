<?php

declare(strict_types=1);

namespace App\Tests\Translation;

use App\Security\BackofficeLocales;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function sprintf;

/**
 * ⚠️ Deux listes de langues coexistent et doivent rester égales : `framework.enabled_locales`
 * (ce que le routeur et le traducteur acceptent) et `BackofficeLocales::SUPPORTED` (ce que le
 * sélecteur propose). Les laisser diverger donne soit une langue offerte sans catalogue —
 * interface à moitié anglaise, sans erreur —, soit une langue traduite que personne ne peut
 * choisir.
 */
#[CoversNothing]
final class LocaleCatalogueTest extends KernelTestCase
{
    public function testTheSwitcherOffersExactlyTheEnabledLocales(): void
    {
        self::bootKernel();

        // Pas d'`assertIsArray` : l'extension Symfony de PHPStan lit le conteneur compilé et
        // connaît déjà le type du paramètre — l'assertion serait toujours vraie, donc du bruit.
        $enabled = static::getContainer()->getParameter('kernel.enabled_locales');

        self::assertSame(BackofficeLocales::SUPPORTED, array_values($enabled));
        self::assertContains(BackofficeLocales::DEFAULT, BackofficeLocales::SUPPORTED);
        self::assertSame(BackofficeLocales::DEFAULT, static::getContainer()->getParameter('kernel.default_locale'));
    }

    /** Chaque langue offerte a le libellé qui la nomme dans le sélecteur — dans TOUTES les langues. */
    public function testEveryOfferedLocaleIsNamedInEveryCatalogue(): void
    {
        self::bootKernel();
        $translator = static::getContainer()->get(TranslatorInterface::class);
        self::assertInstanceOf(TranslatorBagInterface::class, $translator);

        foreach (BackofficeLocales::SUPPORTED as $catalogue) {
            foreach (BackofficeLocales::SUPPORTED as $named) {
                self::assertTrue(
                    $translator->getCatalogue($catalogue)->has(sprintf('user.locale.%s', $named), 'user'),
                    sprintf('« user.locale.%s » manque au catalogue « %s ».', $named, $catalogue),
                );
            }
        }
    }
}
