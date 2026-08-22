<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Jul6Art\PushBundle\Mercure\GlobalFeedTopicResolver;
use Jul6Art\PushBundle\Mercure\SubscriberCookieFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Frappe le cookie et le jeton Mercure, et rend la liste de canaux qui fait autorité.
 *
 * Le JavaScript traite `subscribed` comme la liste d'autorisation du JWT **et** comme la liste
 * d'abonnement de l'`EventSource` : une seule source de vérité, au lieu d'un jeu de balises Twig
 * qui dérive de ce que le jeton autorise.
 *
 * Mono-locataire : un seul canal, celui du résolveur global. Le jour où l'application gagne des
 * locataires, c'est ICI que la liste devient une décision d'autorisation — un abonné reçoit tout ce
 * qui passe sur un canal qu'il a le droit d'écouter.
 */
final class MercureTokenController extends AbstractController
{
    public function __construct(
        private readonly SubscriberCookieFactory $cookieFactory,
    ) {
    }

    #[Route('/admin/mercure-token', name: 'admin_mercure_token', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(): Response
    {
        $topics = [GlobalFeedTopicResolver::TOPIC];
        $token = $this->cookieFactory->createToken($topics);

        $response = new JsonResponse(['subscribed' => $topics, 'token' => $token]);
        $response->headers->setCookie($this->cookieFactory->createCookie($token));

        return $response;
    }
}
