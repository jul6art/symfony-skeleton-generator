<?php

declare(strict_types=1);

namespace App\Controller\Dev;

use LogicException;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\HttpFoundation\Response;

/**
 * La cible du lien de connexion de développement — un contrôleur qui n'est JAMAIS exécuté.
 *
 * L'authentificateur `login_link` intercepte la requête sur cette route, valide la signature, ouvre
 * la session et redirige. Le contrôleur n'existe que parce qu'une route en réclame un ; si son
 * corps s'exécute, c'est que le pare-feu ne couvre pas cette URL — d'où l'exception plutôt qu'une
 * page vide, qui laisserait croire que tout va bien.
 *
 * ⚠️ **Aucun attribut `#[Route]` ici, volontairement.** `config/routes.yaml` scanne tout
 * `src/Controller/` dans TOUS les environnements : un attribut de route ferait exister cette URL
 * en production. La route est donc déclarée en YAML, sous `when@dev`
 * (`config/routes/dev_login_link.yaml`), et `#[When('dev')]` retire le service partout ailleurs.
 * Les deux verrous disent la même chose, et `DevLoginLinkTest` vérifie le résultat.
 */
#[When('dev')]
final class LoginLinkController
{
    public function __invoke(): Response
    {
        throw new LogicException('Cette route est interceptée par l\'authentificateur login_link du pare-feu. Si vous lisez ceci, la route est sortie du pare-feu « main ».');
    }
}
