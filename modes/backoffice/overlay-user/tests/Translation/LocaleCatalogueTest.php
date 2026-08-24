<?php

declare(strict_types=1);

namespace App\Tests\Translation;

use App\Security\BackofficeLocales;
use App\Twig\LocaleExtension;
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

    /**
     * Chaque langue offerte a son DRAPEAU. Une liste de langues et une table de drapeaux qui
     * divergent donnent un sélecteur à trou : deux entrées illustrées, la troisième nue — un défaut
     * que seul l'écran montre, et seulement pour qui parle la langue ajoutée.
     */
    public function testEveryOfferedLocaleHasAFlag(): void
    {
        self::bootKernel();
        $extension = static::getContainer()->get(LocaleExtension::class);

        foreach (BackofficeLocales::SUPPORTED as $locale) {
            $flag = $extension->flag($locale);

            self::assertNotSame('', $flag, sprintf('Aucun drapeau pour « %s ».', $locale));
            self::assertStringContainsString('<svg', $flag, sprintf('Le drapeau de « %s » doit être un SVG.', $locale));

            // ⚠️ SVG et non emoji : Windows ne possède pas les glyphes de drapeaux nationaux, et
            // 🇫🇷 y rend « FR » en lettres. Le même écran serait illustré chez les uns, textuel
            // chez les autres, sans que rien ne le signale côté serveur.
            self::assertStringNotContainsString('🇫', $flag);
            self::assertStringContainsString('aria-hidden="true"', $flag, 'Décoratif : la langue est déjà nommée à côté.');
        }
    }

    /** Une langue inconnue ne fait pas tomber le sélecteur : elle perd son drapeau, rien de plus. */
    public function testAnUnknownLocaleDegradesToNoFlag(): void
    {
        self::bootKernel();

        self::assertSame('', static::getContainer()->get(LocaleExtension::class)->flag('zz'));
    }
}
