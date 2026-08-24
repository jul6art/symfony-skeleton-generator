<?php

declare(strict_types=1);

namespace App\Tests\Translation;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function dirname;
use function sprintf;

/**
 * Les flashes sortent des contrôleurs **déjà traduits** : `addSuccessFlash('flash.x')` passe par
 * `Jul6Art\CoreBundle\Service\FlashTranslator`.
 *
 * Une clé absente du catalogue ne lève rien — le traducteur rend la clé, et c'est la clé que
 * l'utilisateur lit. Ce test compare donc les clés écrites dans les contrôleurs au catalogue.
 */
final class FlashKeyTest extends KernelTestCase
{
    public function testEveryFlashKeyExistsInTheCatalogue(): void
    {
        self::bootKernel();

        $translator = static::getContainer()->get(TranslatorInterface::class);
        self::assertInstanceOf(TranslatorBagInterface::class, $translator);

        // Pas d'`assertIsString` : l'extension Symfony de PHPStan lit le conteneur compilé et
        // connaît déjà le type du paramètre — l'assertion serait toujours vraie, donc du bruit.
        $locale = (string) static::getContainer()->getParameter('kernel.default_locale');

        $catalogue = $translator->getCatalogue($locale);
        $missing = [];

        foreach ($this->flashKeys() as $file => $keys) {
            foreach ($keys as $key) {
                if (!$catalogue->has($key, 'messages')) {
                    $missing[] = sprintf('%s : « %s »', $file, $key);
                }
            }
        }

        // Un squelette d'API n'émet aucun flash : rien à vérifier n'est un succès, pas un échec.
        self::assertSame([], $missing, "Clés de flash absentes du catalogue :\n".implode("\n", $missing));
    }

    /**
     * @return array<string, list<string>> fichier → clés littérales
     */
    private function flashKeys(): array
    {
        $root = dirname(__DIR__, 2);

        // ⚠️ Les contrôleurs des BUNDLES posent eux aussi des flashes, et c'est au PROJET de les
        // traduire : le partial de toasts d'`admin-bundle` résout dans `messages`, quel que soit
        // le domaine où la clé est déclarée. Ne scanner que `src/` laissait passer
        // `appearance.flash.saved` — émise par `Jul6Art\AdminBundle\Controller\AppearanceController`,
        // déclarée dans le domaine `appearance`, donc affichée BRUTE à l'écran (vu le 2026-08-23,
        // et superp l'affichait brute aussi, pour la même raison).
        $directories = array_values(array_filter([
            $root.'/src/Controller',
            $root.'/vendor/jul6art/admin-bundle/Controller',
        ], is_dir(...)));

        if ([] === $directories) {
            return [];
        }

        $keys = [];

        foreach (Finder::create()->files()->in($directories)->name('*.php') as $file) {
            $source = $file->getContents();

            // La quote fermante suivie d'une virgule ou d'une parenthèse : une clé concaténée
            // ('advertisement.'.$status) n'est pas lisible statiquement et reste hors de portée.
            // Les deux API coexistent : `addSuccessFlash('clé')` (core-bundle) et le
            // `addFlash('type', 'clé')` natif de Symfony — la clé est alors le DEUXIÈME argument.
            // Ne scanner que la première rendait ce test aveugle sur un projet qui n'utilise que
            // la seconde : zéro clé trouvée, suite verte, clé brute à l'écran (vu le 2026-08-23).
            preg_match_all(
                '/\$this->add(?:Success|Error|Warning)Flash\(\s*\'([^\']+)\'\s*[,)]/',
                $source,
                $matches,
            );
            preg_match_all(
                '/\$this->addFlash\(\s*\'[^\']+\'\s*,\s*\'([^\']+)\'\s*\)/',
                $source,
                $nativeMatches,
            );

            $found = array_merge($matches[1], $nativeMatches[1]);
            if ([] !== $found) {
                $keys[$file->getRelativePathname()] = array_values(array_unique($found));
            }
        }

        return $keys;
    }
}
