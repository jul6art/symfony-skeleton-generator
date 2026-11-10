<?php

declare(strict_types=1);

namespace App\Tests\Translation;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function array_unique;
use function array_values;
use function dirname;
use function explode;
use function file_get_contents;
use function implode;
use function is_dir;
use function preg_match;
use function preg_match_all;
use function sprintf;
use function strlen;
use function substr;
use function substr_count;
use function trim;

use const PREG_OFFSET_CAPTURE;
use const PREG_SET_ORDER;

/**
 * Toute valeur passée à une phrase traduite rendue `|raw` est échappée d'abord.
 *
 * ⚠️ `'clé'|trans({'%x%': valeur})|raw` est la façon dont une phrase garde son propre `<strong>`. Or
 * `trans` n'échappe PAS ses paramètres : la valeur sort en HTML. Sur un projet issu de ce squelette,
 * `/reset-password/sent?email=<img onerror=…>` s'exécutait chez le visiteur, et le prénom d'un candidat
 * partait en balises dans un courriel (audit du 2026-11-10, S1). La règle : un tel paramètre est un
 * littéral, un nombre, un `path()` / `url()` / `asset()`, un `'clé'|trans`, ou finit par `|e` /
 * `|escape`.
 *
 * Une lecture du source, pas un analyseur Twig : la table de paramètres est lue jusqu'à sa première
 * `}`, chaque valeur prise entière à travers les chaînes entre guillemets (un `:` dans une classe CSS
 * ne coupe rien), puis découpée sur `~`. Une table imbriquée dans une autre n'est pas lue.
 */
final class TransRawEscapingTest extends TestCase
{
    private const string RAW_SENTENCE = '/\|\s*trans\(\s*\{(?<params>[^}]*)\}[^)]*\)\s*\|\s*raw\b/s';

    /** Un couple `clé: valeur` de la table ; une chaîne entre guillemets est lue entière. */
    private const string PAIR = '/(?:^|,)\s*(?:\'[^\']*\'|"[^"]*"|[\w%]+)\s*:\s*((?:\'[^\']*\'|"[^"]*"|[^,\'"])+)/';

    private const string SAFE_PART = '/^(?:\'[^\']*\'(?:\s*\|\s*trans(?:\([^)]*\))?)?|"[^"]*"|\d+(?:\.\d+)?|(?:path|url|asset)\(.*\)|.+\|\s*(?:e|escape)(?:\([^)]*\))?)$/s';

    public function testEveryParameterOfARawSentenceIsEscaped(): void
    {
        $root = dirname(__DIR__, 2).'/templates';

        // Les modes sans Twig (api) n'ont pas de gabarits : rien à garder.
        if (!is_dir($root)) {
            $this->addToAssertionCount(1);

            return;
        }

        $unsafe = [];
        $scanned = 0;

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
            if (!$file instanceof SplFileInfo || 'twig' !== $file->getExtension()) {
                continue;
            }
            ++$scanned;
            $source = (string) file_get_contents($file->getPathname());

            preg_match_all(self::RAW_SENTENCE, $source, $sentences, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
            foreach ($sentences as $sentence) {
                preg_match_all(self::PAIR, $sentence['params'][0], $values);
                foreach ($values[1] as $value) {
                    foreach (explode('~', $value) as $part) {
                        if (1 !== preg_match(self::SAFE_PART, trim($part))) {
                            $line = substr_count(substr($source, 0, $sentence[0][1]), "\n") + 1;
                            $unsafe[] = sprintf('%s:%d  %s', substr($file->getPathname(), strlen($root) + 1), $line, trim($value));
                        }
                    }
                }
            }
        }

        self::assertNotSame(0, $scanned, 'Le dossier templates/ existe mais aucun gabarit n\'est lu : le parcours est cassé.');
        self::assertSame([], array_values(array_unique($unsafe)), "Une valeur atteint une phrase `|trans(…)|raw` sans être échappée — ajoutez `|e` :\n  ".implode("\n  ", array_unique($unsafe)));
    }
}
