<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Jul6Art\AuthBundle\Entity\User;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Décision d'accès au back-office lui-même : la porte d'entrée du tableau de
 * bord, qui ne porte aucun sujet métier.
 *
 * Les décisions sur les entités administrées restent dans leur propre voter
 * (`UserVoter` pour les comptes) : celui-ci ne répond qu'à « peut-on entrer ? ».
 */
final class AdminVoter extends AbstractVoter
{
    public const ACCESS = 'ADMIN_ACCESS';

    protected function attributes(): array
    {
        return [self::ACCESS];
    }

    protected function decide(string $attribute, mixed $subject, UserInterface $user): bool
    {
        return self::ACCESS === $attribute && $this->hasRole(User::ROLE_ADMIN);
    }
}
