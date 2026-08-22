<?php

declare(strict_types=1);

namespace App\Controller;

use Jul6Art\AclBundle\Attribute\CheckPermission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Connexion et déconnexion.
 *
 * Les deux gabarits viennent d'`admin-bundle` : ils portent le logo et le nom du produit d'après
 * `admin.branding`, et le lien d'inscription n'apparaît que si `admin.routes.register` est
 * renseignée. Un projet qui veut sa propre page remplace le `render()` par le sien.
 */
final class SecurityController extends AbstractController
{
    #[Route('/login', name: 'admin_security_login', methods: ['GET', 'POST'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        return $this->render('@Admin/security/login.html.twig', [
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'last_username' => $authenticationUtils->getLastUsername(),
        ]);
    }

    /**
     * Interceptée par le pare-feu : le corps n'est jamais exécuté.
     *
     * C'est la seule exception documentée à la règle « chaque route porte une décision d'accès » —
     * ici la décision EST le pare-feu, et `RouteAccessDecisionTest` la liste à ce titre.
     */
    #[Route('/logout', name: 'admin_security_logout', methods: ['GET', 'POST'])]
    #[CheckPermission('auth:logout')]
    public function logout(): never
    {
        throw new \LogicException('Interceptée par le pare-feu : cette méthode ne doit jamais être exécutée.');
    }
}
