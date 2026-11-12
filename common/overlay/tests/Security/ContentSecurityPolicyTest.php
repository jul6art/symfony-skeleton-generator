<?php

declare(strict_types=1);

namespace App\Tests\Security;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function array_shift;
use function explode;
use function preg_split;
use function trim;

use const PREG_SPLIT_NO_EMPTY;

/**
 * La politique de sécurité (`core.security_headers`) BLOQUE dès la génération : elle arrête ce
 * qu'elle refuse au lieu de seulement le signaler. Seul le développement garde le mode rapport
 * (`when@dev`), pour la page d'erreur de Symfony.
 *
 * Le listener pose ses en-têtes sur toute réponse principale, page d'erreur comprise : la
 * réponse de `/` suffit, quel que soit le mode, qu'elle soit une page ou un 404.
 */
final class ContentSecurityPolicyTest extends WebTestCase
{
    public function testThePolicyBlocksAndAllowsNoInlineScript(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $headers = $client->getResponse()->headers;
        self::assertFalse($headers->has('Content-Security-Policy-Report-Only'), 'La politique bloque, elle ne se contente pas de rapporter.');
        self::assertTrue($headers->has('Content-Security-Policy'));

        $scriptSources = $this->directives((string) $headers->get('Content-Security-Policy'))['script-src'] ?? ['absente'];
        self::assertNotContains("'unsafe-inline'", $scriptSources, 'Un script inline ne passe que par le nonce de la page.');
    }

    /**
     * @return array<string, list<string>>
     */
    private function directives(string $policy): array
    {
        $directives = [];
        foreach (explode(';', $policy) as $directive) {
            $sources = preg_split('/\s+/', trim($directive), -1, PREG_SPLIT_NO_EMPTY);
            $name = false === $sources ? null : array_shift($sources);
            if (null !== $name) {
                $directives[$name] = $sources;
            }
        }

        return $directives;
    }
}
