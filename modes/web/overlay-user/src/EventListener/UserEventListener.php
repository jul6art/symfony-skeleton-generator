<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Event\UserEvent;
use Jul6Art\AdminBundle\Account\Event\UserRegisteredEvent;
use Jul6Art\AuthBundle\Entity\User;
use Jul6Art\CoreBundle\EventListener\AbstractEventListener;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Trace les mouvements de comptes : qui a fait quoi, sur qui.
 *
 * AbstractEventListener (core-bundle) apporte le TokenStorage, donc l'auteur de
 * l'action, sans avoir à l'injecter ici.
 *
 * L'inscription publique est l'écran d'admin-bundle : il annonce le compte écrit
 * par `UserRegisteredEvent`, et non par `UserEvent::CREATED`.
 */
#[AsEventListener(event: UserEvent::CREATED, method: 'onCreated')]
#[AsEventListener(event: UserRegisteredEvent::class, method: 'onRegistered')]
#[AsEventListener(event: UserEvent::EDITED, method: 'onEdited')]
#[AsEventListener(event: UserEvent::DELETED, method: 'onDeleted')]
final class UserEventListener extends AbstractEventListener
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function onCreated(UserEvent $event): void
    {
        $this->log('créé', $event->getUser());
    }

    public function onRegistered(UserRegisteredEvent $event): void
    {
        if ($event->account instanceof User) {
            $this->log('inscrit', $event->account);
        }
    }

    public function onEdited(UserEvent $event): void
    {
        $this->log('modifié', $event->getUser());
    }

    public function onDeleted(UserEvent $event): void
    {
        $this->log('supprimé', $event->getUser());
    }

    private function log(string $action, User $user): void
    {
        $this->logger->info('Compte {action}', [
            'action' => $action,
            'user' => $user->getEmail(),
            'by' => $this->getCurrentUserOrNull()?->getUserIdentifier(),
        ]);
    }
}
