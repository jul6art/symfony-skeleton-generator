<?php

declare(strict_types=1);

namespace App\Controller;

use App\Security\Voter\UserVoter;
use Jul6Art\AuthBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Compte associé au JWT présenté : sert de sonde d'authentification côté client.
 *
 * Les groupes de sérialisation de l'entité du auth-bundle sont déclarés dans
 * config/serializer/user.yaml.
 */
final class MeController extends AbstractController
{
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function __invoke(#[CurrentUser] ?User $user): JsonResponse
    {
        if (null === $user) {
            return $this->json(['error' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
        }

        // Lire son propre compte est une action nommée, décidée par le voter.
        $this->denyAccessUnlessGranted(UserVoter::VIEW, $user);

        return $this->json($user, Response::HTTP_OK, [], ['groups' => ['user:read']]);
    }
}
