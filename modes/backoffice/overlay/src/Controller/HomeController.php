<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * La racine du site : un back-office n'a pas de page d'accueil publique, elle renvoie donc là où
 * l'utilisateur a affaire.
 *
 * La décision d'accès est explicite, comme sur toute route de ce squelette : celle-ci est
 * délibérément ouverte, parce que rediriger un visiteur vers la connexion est ce qu'elle fait.
 */
final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function __invoke(TokenStorageInterface $tokenStorage): RedirectResponse
    {
        return $this->redirectToRoute(
            null !== $tokenStorage->getToken() ? 'admin_dashboard' : 'admin_security_login',
        );
    }
}
