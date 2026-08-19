<?php

declare(strict_types=1);

namespace App\Controller;

use LogicException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Point d'entrée de l'authentification : POST /api/login {"email", "password"}.
 *
 * La requête est interceptée par la clé `json_login` du pare-feu, qui répond
 * avec le JWT ; ce contrôleur n'est jamais exécuté — il n'existe que pour que
 * la route existe.
 *
 * Seule famille de routes sans décision d'accès dans le code, et pour cause :
 * rien de ce qui est écrit ici ne tourne. La règle d'accès, c'est le pare-feu.
 */
final class LoginController
{
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function __invoke(): never
    {
        throw new LogicException('Cette action est interceptée par la clé "json_login" du pare-feu.');
    }
}
