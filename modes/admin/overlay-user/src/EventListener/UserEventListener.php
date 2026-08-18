<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Event\UserEvent;
use Jul6Art\CoreBundle\EventListener\AbstractEventListener;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Trace les mouvements de comptes : qui a fait quoi, sur qui.
 *
 * AbstractEventListener (core-bundle) apporte le TokenStorage, donc l'auteur de
 * l'action, sans avoir à l'injecter ici.
 */
#[AsEventListener(event: UserEvent::CREATED, method: 'onCreated')]
#[AsEventListener(event: UserEvent::EDITED, method: 'onEdited')]
#[AsEventListener(event: UserEvent::DELETED, method: 'onDeleted')]
final class UserEventListener extends AbstractEventListener
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function onCreated(UserEvent $event): void
    {
        $this->log('créé', $event);
    }

    public function onEdited(UserEvent $event): void
    {
        $this->log('modifié', $event);
    }

    public function onDeleted(UserEvent $event): void
    {
        $this->log('supprimé', $event);
    }

    private function log(string $action, UserEvent $event): void
    {
        $this->logger->info('Compte {action}', [
            'action' => $action,
            'user' => $event->getUser()->getEmail(),
            'by' => $this->getCurrentUserOrNull()?->getUserIdentifier(),
        ]);
    }
}
