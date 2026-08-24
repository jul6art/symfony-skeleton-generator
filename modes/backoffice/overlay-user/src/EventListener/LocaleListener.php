<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Security\BackofficeLocales;
use App\Service\LocalePreference;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;

use function is_string;

/**
 * Applique la langue choisie à la requête ET aux services qui en dépendent.
 *
 * ## Pourquoi la priorité 7, et pourquoi la rediffusion
 *
 * Deux contraintes fixent l'endroit où cet écouteur peut tourner, et elles se contredisent :
 *
 * 1. Le pare-feu remplit `TokenStorage` à la priorité **8**. Tout ce qui a besoin de
 *    `$user` doit donc tourner EN DESSOUS de 8, sinon le stockage est encore vide.
 * 2. `LocaleAwareListener` de Symfony tourne à la priorité **15** pour diffuser
 *    `$request->getLocale()` à chaque service `LocaleAwareInterface` — le traducteur en tête. Il
 *    ne repasse JAMAIS. Un `setLocale()` posé après 15 laisse donc le traducteur bloqué sur la
 *    langue par défaut : la colonne est bien écrite, la session aussi, et l'écran reste en
 *    anglais. C'est le seul symptôme, et aucune configuration ne le montre.
 *
 * On satisfait les deux en tournant à **7** — après le pare-feu — et en refaisant nous-mêmes la
 * diffusion, exactement comme `LocaleAwareListener::setLocale()`.
 */
final readonly class LocaleListener
{
    /**
     * @param iterable<LocaleAwareInterface> $localeAwareServices
     */
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private LocalePreference $preference,
        #[AutowireIterator('kernel.locale_aware')]
        private iterable $localeAwareServices,
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();

        $locale = $this->preference->resolve($request, $user instanceof User ? $user : null);

        if (!is_string($locale) || !BackofficeLocales::isSupported($locale)) {
            return;
        }

        $request->setLocale($locale);
        $this->broadcast($locale, $request->getDefaultLocale());
    }

    /**
     * Reproduit {@see \Symfony\Component\HttpKernel\EventListener\LocaleAwareListener::setLocale()}
     * : sans cette boucle, seule la requête connaîtrait la nouvelle langue, et le traducteur —
     * qui est justement ce qui fait l'écran — resterait sur l'ancienne.
     */
    private function broadcast(string $locale, string $defaultLocale): void
    {
        foreach ($this->localeAwareServices as $service) {
            try {
                $service->setLocale($locale);
            } catch (InvalidArgumentException) {
                $service->setLocale($defaultLocale);
            }
        }
    }
}
