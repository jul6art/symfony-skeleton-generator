<?php

declare(strict_types=1);

namespace App\Controller;

use Jul6Art\CoreBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class HomeController extends AbstractController
{
    // Page ouverte à tous, et elle le dit : aucune route ne reste sans décision
    // d'accès, une route publique la déclare comme les autres.
    #[Route('/', name: 'app_home', methods: ['GET'])]
    #[IsGranted(AuthenticatedVoter::PUBLIC_ACCESS)]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }
}
