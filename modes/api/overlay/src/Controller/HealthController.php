<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class HealthController
{
    // Sonde ouverte à tous, et elle le dit : aucune route ne reste sans décision
    // d'accès, une route publique la déclare comme les autres.
    #[Route('/health', name: 'api_health', methods: ['GET'])]
    #[IsGranted(AuthenticatedVoter::PUBLIC_ACCESS)]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'app' => '{{PROJECT_SLUG}}',
            'symfony' => Kernel::VERSION,
        ]);
    }
}
