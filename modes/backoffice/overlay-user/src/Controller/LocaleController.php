<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Security\BackofficeLocales;
use App\Service\LocalePreference;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * La bascule de langue.
 *
 * ⚠️ Volontairement PUBLIQUE : on choisit sa langue AVANT d'avoir un compte, sur l'écran de
 * connexion. La route s'écrit donc comme les autres — `#[IsGranted('PUBLIC_ACCESS')]` — et non
 * sans décision (règle n°1 : `RouteAccessDecisionTest` refuserait une route muette).
 *
 * Elle rend 204 et non une redirection : le contrôleur Stimulus `ui--locale-switcher` d'
 * `admin-bundle` recharge la page lui-même après le `fetch()`.
 */
final class LocaleController extends AbstractController
{
    public function __construct(
        private readonly LocalePreference $preference,
    ) {
    }

    #[Route('/locale', name: 'admin_locale_switch', methods: ['POST'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function switch(Request $request, #[CurrentUser] ?User $user): Response
    {
        // ⚠️ La langue se lit dans le CORPS, pas dans l'URL : c'est le contrat du contrôleur
        // Stimulus `ui--locale-switcher` d'`admin-bundle`, qui poste `locale` en `FormData` vers
        // une URL FIXE. Une route `/locale/{locale}` compilait, répondait 204, et rebasculait sur
        // la langue courante — un no-op parfait, que seul l'écran montre (vu le 2026-08-24 : le
        // menu s'ouvrait, on cliquait « Anglais », la page revenait en français).
        $locale = (string) $request->request->get('locale', '');

        // ⚠️ `AccessDeniedHttpException` (HTTP) et NON `createAccessDeniedException()` (sécurité).
        // Sur une route publique, l'exception de sécurité est rattrapée par le pare-feu, qui la
        // traduit en redirection vers la connexion pour un visiteur anonyme : un jeton invalide
        // répondrait alors « connectez-vous » au lieu de « refusé ».
        if (!$this->isCsrfTokenValid('locale_switch', (string) $request->request->get('_token'))) {
            throw new AccessDeniedHttpException();
        }

        if (!BackofficeLocales::isSupported($locale)) {
            throw $this->createNotFoundException();
        }

        $this->preference->remember($request, $user, $locale);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
