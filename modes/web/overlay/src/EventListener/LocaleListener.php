<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

use function in_array;
use function is_string;

/**
 * Choix de langue : `?_locale=fr` bascule et se mémorise en session, sinon on
 * reprend la dernière langue choisie. Sans session ni paramètre, la locale par
 * défaut du framework s'applique.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 15)]
final class LocaleListener
{
    /**
     * @param list<string> $enabledLocales
     */
    public function __construct(
        #[Autowire('%kernel.enabled_locales%')]
        private readonly array $enabledLocales,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $locale = $request->query->get('_locale');

        // Une bascule explicite gagne, et se retient pour les requêtes suivantes.
        if (is_string($locale) && in_array($locale, $this->enabledLocales, true)) {
            $request->setLocale($locale);

            if ($request->hasSession()) {
                $request->getSession()->set('_locale', $locale);
            }

            return;
        }

        if (!$request->hasPreviousSession()) {
            return;
        }

        $stored = $request->getSession()->get('_locale');

        if (is_string($stored) && in_array($stored, $this->enabledLocales, true)) {
            $request->setLocale($stored);
        }
    }
}
