<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use Jul6Art\CoreBundle\Security\Voter\AbstractVoter;
use Override;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Le compte d'un super-administrateur n'appartient qu'aux super-administrateurs (2026-10-25) : un
 * administrateur qui détient `user:update` ou `user:activate` modifie, désactive ou supprime les autres
 * comptes — jamais l'un des leurs. Sans lui, un administrateur changeait l'adresse et le mot de passe du
 * super-administrateur, ou désactivait son compte. La permission fine dit CE QU'on peut faire aux comptes ;
 * celui-ci dit à LESQUELS.
 *
 * @extends AbstractVoter<string, User>
 */
final class UserAccountVoter extends AbstractVoter
{
    public const string MANAGE = 'USER_ACCOUNT_MANAGE';

    #[Override]
    protected function attributes(): array
    {
        return [self::MANAGE];
    }

    #[Override]
    protected function subjects(): array
    {
        return [User::class];
    }

    #[Override]
    protected function decide(string $attribute, mixed $subject, UserInterface $user): bool
    {
        return $subject instanceof User && $user instanceof User && ($user->isSuperAdmin() || !$subject->isSuperAdmin());
    }
}
