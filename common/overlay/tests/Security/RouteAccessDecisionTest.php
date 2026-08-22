<?php

declare(strict_types=1);

namespace App\Tests\Security;

use ReflectionMethod;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function array_slice;
use function in_array;
use function is_string;
use function sprintf;
use function str_contains;
use function str_starts_with;

/**
 * Garde-fou de la règle n°1 : aucune route sans décision d'accès.
 *
 * Chaque action déclarée dans `App\` doit porter soit un `#[IsGranted]` sur la
 * méthode, soit un `denyAccessUnlessGranted()` dans son corps. Ce test échoue
 * donc dès qu'une route est ajoutée sans décision — c'est son seul métier.
 */
final class RouteAccessDecisionTest extends KernelTestCase
{
    /**
     * Routes interceptées par le pare-feu : leur action n'est jamais exécutée,
     * la règle d'accès y est le pare-feu lui-même. Une route absente du projet
     * ne gêne pas, elle ne sera simplement jamais rencontrée. Toute autre
     * exception se discute — elle ne s'ajoute pas ici par commodité.
     *
     * @var list<string>
     */
    private const FIREWALL_ROUTES = [
        'app_logout',            // clé `logout` du pare-feu (modes web et admin)
        'api_login',             // clé `json_login` du pare-feu (modes api)
        'admin_security_logout', // clé `logout` du pare-feu (mode backoffice)
    ];

    public function testEveryRouteCarriesAnAccessDecision(): void
    {
        self::bootKernel();

        $router = static::getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);

        $missing = [];

        foreach ($router->getRouteCollection() as $name => $route) {
            $controller = $route->getDefault('_controller');

            if (!is_string($controller) || in_array($name, self::FIREWALL_ROUTES, true)) {
                continue;
            }

            // « Classe::méthode », ou la classe seule pour un contrôleur invocable.
            [$class, $method] = str_contains($controller, '::')
                ? explode('::', $controller, 2)
                : [$controller, '__invoke'];

            if (!str_starts_with($class, 'App\\') || !method_exists($class, $method)) {
                continue;
            }

            $action = new ReflectionMethod($class, $method);

            // Action héritée d'une classe de vendor (EasyAdmin…) : sa décision se
            // déclare avec les outils du bundle, pas dans une méthode du projet.
            if (!str_starts_with($action->getDeclaringClass()->getName(), 'App\\')) {
                continue;
            }

            if (!$this->carriesDecision($action)) {
                $missing[] = sprintf('%s → %s::%s()', $name, $class, $method);
            }
        }

        self::assertSame([], $missing, "Ces routes n'ont pas de décision d'accès (règle n°1) : ".implode(', ', $missing));
    }

    private function carriesDecision(ReflectionMethod $action): bool
    {
        if ([] !== $action->getAttributes(IsGranted::class)) {
            return true;
        }

        $lines = file((string) $action->getFileName());

        if (false === $lines) {
            return false;
        }

        $body = implode('', array_slice($lines, $action->getStartLine() - 1, $action->getEndLine() - $action->getStartLine() + 1));

        return str_contains($body, 'denyAccessUnlessGranted');
    }
}
