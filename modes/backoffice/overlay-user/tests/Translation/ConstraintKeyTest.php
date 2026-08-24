<?php

declare(strict_types=1);

namespace App\Tests\Translation;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\CoversNothing;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Translation\TranslatorBagInterface;

use function dirname;
use function sprintf;

use const PREG_SET_ORDER;

/**
 * Chaque clé de message de contrainte référencée dans src/ doit exister dans le domaine
 * `validators` — pour TOUTES les locales activées.
 *
 * ⚠️ C'est le domaine `validators` que le validateur utilise, jamais celui du formulaire : une
 * clé posée dans `security.*.yaml` compile, passe la gate, et sort BRUTE à l'écran au premier
 * formulaire invalide (« security.password.too_short » affiché tel quel — vu le 2026-08-23).
 * Ce test scanne le code au lieu de fixer une liste : une contrainte ajoutée demain est couverte
 * d'office.
 */
#[CoversNothing]
final class ConstraintKeyTest extends KernelTestCase
{
    public function testEveryConstraintMessageKeyExistsInTheValidatorsDomain(): void
    {
        self::bootKernel();

        $translator = static::getContainer()->get('translator');
        self::assertInstanceOf(TranslatorBagInterface::class, $translator);

        $keys = $this->referencedConstraintKeys();
        self::assertNotEmpty($keys, 'Le scan ne trouve plus aucune clé : le motif est cassé, pas le code.');

        $missing = [];
        foreach (['en', 'fr'] as $locale) {
            $catalogue = $translator->getCatalogue($locale);
            foreach ($keys as $key => $file) {
                if (!$catalogue->has($key, 'validators')) {
                    $missing[] = sprintf('%s (%s, locale %s)', $key, $file, $locale);
                }
            }
        }

        self::assertSame([], $missing, "Clés de contrainte absentes du domaine validators :\n".implode("\n", $missing));
    }

    /**
     * @return array<string, string> clé => fichier où elle apparaît
     */
    private function referencedConstraintKeys(): array
    {
        $keys = [];
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src', FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if ('php' !== $file->getExtension()) {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            // message: '…', minMessage: '…', maxMessage: '…', 'invalid_message' => '…'
            preg_match_all(
                "/(?:message|minMessage|maxMessage)\\s*:\\s*'([^']+)'|'invalid_message'\\s*=>\\s*'([^']+)'/",
                $source,
                $matches,
                PREG_SET_ORDER,
            );
            foreach ($matches as $match) {
                $key = '' !== ($match[2] ?? '') ? $match[2] : ($match[1] ?? '');
                // Une phrase avec espaces est un message en dur (autre bataille), pas une clé.
                if ('' !== $key && !str_contains($key, ' ')) {
                    $keys[$key] = basename($file->getPathname());
                }
            }
        }

        return $keys;
    }
}
