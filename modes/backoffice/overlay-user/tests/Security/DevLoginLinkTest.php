<?php

declare(strict_types=1);

namespace App\Tests\Security;

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;

use function sprintf;

/**
 * Le lien de connexion de DÉVELOPPEMENT n'existe qu'en développement.
 *
 * Il permet d'ouvrir une session sans mot de passe, à partir d'une URL signée produite en console.
 * C'est un outil d'outillage local — pour regarder les écrans tourner sans saisir d'identifiants —
 * et **il n'a rien à faire ailleurs qu'en dev** : une route qui authentifie sur simple présentation
 * d'une signature est exactement ce qu'on ne veut pas voir en production.
 *
 * ⚠️ Ce test est le garde-fou de cette promesse, et il la vérifie par ce qui compte : **l'absence
 * de la route** dans un environnement qui n'est pas `dev`. La suite tourne en `test`, donc ce que
 * ce test constate ici vaut aussi pour `prod` — les deux passent par le même `when@dev`. Une
 * configuration déplacée hors de ce bloc le fera rougir.
 *
 * Le pendant positif — « le lien connecte réellement » — se vérifie en dev, à la main : c'est le
 * seul endroit où la route existe.
 */
#[CoversNothing]
final class DevLoginLinkTest extends KernelTestCase
{
    /** @var list<string> */
    private const array DEV_ONLY_ROUTES = ['dev_login_link_check'];

    public function testTheDevLoginLinkRouteDoesNotExistOutsideDev(): void
    {
        self::bootKernel();

        self::assertNotSame('dev', static::getContainer()->getParameter('kernel.environment'), 'Ce test ne prouve rien s\'il tourne en dev.');

        $router = static::getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);

        foreach (self::DEV_ONLY_ROUTES as $route) {
            self::assertNull(
                $router->getRouteCollection()->get($route),
                sprintf('La route « %s » authentifie SANS mot de passe : elle ne doit exister qu\'en dev.', $route),
            );
        }
    }

    /** Et le service qui fabrique ces liens n'est pas non plus dans le conteneur. */
    public function testTheLinkCommandIsNotRegisteredOutsideDev(): void
    {
        self::bootKernel();

        self::assertFalse(
            static::getContainer()->has(\App\Command\DevLoginLinkCommand::class),
            'La commande qui fabrique un lien de connexion ne doit pas exister hors dev.',
        );
    }
}
