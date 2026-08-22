<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * L'accueil du back-office : trois compteurs et rien d'autre, à remplacer par ce que le produit a
 * de plus utile à montrer.
 */
#[Route('/admin', name: 'admin_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class DashboardController extends AbstractController
{
    #[Route('', name: 'dashboard', methods: ['GET'])]
    public function __invoke(UserRepository $users): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'total_users' => $users->count([]),
            'active_users' => $users->count(['isActive' => true]),
        ]);
    }
}
