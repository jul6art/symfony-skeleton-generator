<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Override;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Un compte désactivé ne s'authentifie pas — nulle part, et un super-administrateur pas plus qu'un
 * autre.
 *
 * ## Ce que cela ferme
 *
 * ⚠️ Sans vérificateur déclaré, rien ne lit `isActive()` à l'authentification : celui de Symfony par
 * défaut (`InMemoryUserChecker`) n'inspecte que les `InMemoryUser`. Un compte inscrit en attente
 * d'activation (« un administrateur activera votre compte ») ou désactivé par un administrateur se
 * connectait par le formulaire, le lien de connexion et le cookie « se souvenir de moi » — vu dans
 * Chrome sur un projet généré le 2026-11-13 (les applications avaient reçu ce vérificateur le
 * 2026-11-11, le squelette pas).
 *
 * ⚠️ Le moteur de permissions n'est pas le filet qu'il semble : il laisse passer le
 * super-administrateur avant toute autre règle, et se pose permission par permission. Seule
 * l'authentification peut dire « ce compte est fermé » une fois pour toutes.
 *
 * ## Pourquoi câblé GLOBALEMENT, et pas par pare-feu
 *
 * Enregistré comme alias `security.user_checker` (`config/services.yaml`) : chaque pare-feu en
 * hérite, y compris celui qu'on ajoutera demain.
 *
 * ## Pourquoi `checkPreAuth`
 *
 * Il passe AVANT la vérification du mot de passe : le compte est refusé sans que le mot de passe soit
 * jugé, donc sans confirmer, par le moment du refus, qu'il était le bon — ce que ferait
 * `checkPostAuth`. En échange, le message distingue un compte fermé d'une adresse inconnue.
 */
final class UserChecker implements UserCheckerInterface
{
    #[Override]
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof User && !$user->isActive()) {
            // ⚠️ Le message dit que le compte ne peut pas se connecter, jamais POURQUOI. Il apprend
            // tout de même qu'un compte fermé existe à cette adresse, quel que soit le mot de passe
            // tapé : Symfony ne masque jamais une `CustomUserMessageAccountStatusException`. C'est le
            // prix de `checkPreAuth` — voir le docbloc.
            throw new CustomUserMessageAccountStatusException('security.account.cannot_sign_in');
        }
    }

    #[Override]
    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        // Rien : tout ce que ce projet refuse se sait avant la vérification du mot de passe.
    }
}
