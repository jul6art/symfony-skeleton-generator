<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Jul6Art\AuthBundle\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Alimente GET /api/me : le compte porté par le jeton, sans identifiant dans
 * l'URL. L'opération est déclarée dans config/api_platform/resources.yaml,
 * l'entité venant du auth-bundle.
 *
 * @implements ProviderInterface<User>
 */
final class MeProvider implements ProviderInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }
}
