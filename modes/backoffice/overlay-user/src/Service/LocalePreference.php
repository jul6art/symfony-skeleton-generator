<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

use function is_string;

/**
 * Où vit le choix de langue, et dans quel ordre on le relit.
 *
 * DEUX canaux, et les deux sont nécessaires :
 *
 * - la **session**, seul canal disponible pour un visiteur anonyme sur `/login` ;
 * - la **colonne `locale`** du compte, qui rend le choix durable d'un appareil à l'autre.
 *
 * La session gagne pour la requête en cours : c'est le choix que la personne vient de faire.
 */
final readonly class LocalePreference
{
    public const string SESSION_KEY = '_locale';

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function remember(Request $request, ?User $user, string $locale): void
    {
        if ($request->hasSession()) {
            $request->getSession()->set(self::SESSION_KEY, $locale);
        }

        if ($user instanceof User) {
            $user->setLocale($locale);
            $this->entityManager->flush();
        }
    }

    /** @return non-empty-string|null */
    public function resolve(Request $request, ?User $user): ?string
    {
        if ($request->hasSession() && $request->getSession()->isStarted()) {
            $fromSession = $request->getSession()->get(self::SESSION_KEY);
            if (is_string($fromSession) && '' !== $fromSession) {
                return $fromSession;
            }
        }

        $fromUser = $user?->getLocale();

        return null !== $fromUser && '' !== $fromUser ? $fromUser : null;
    }
}
