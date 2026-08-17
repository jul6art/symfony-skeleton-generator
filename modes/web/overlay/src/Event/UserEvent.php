<?php

declare(strict_types=1);

namespace App\Event;

use Jul6Art\AuthBundle\Entity\User;
use Jul6Art\CoreBundle\Event\AbstractEvent;

/**
 * Événement de cycle de vie d'un compte.
 *
 * Les noms sont redéfinis ici : ceux d'AbstractEvent sont génériques et
 * entreraient en collision entre entités.
 */
final class UserEvent extends AbstractEvent
{
    public const string CREATED = 'app.user.created';
    public const string EDITED = 'app.user.edited';
    public const string DELETED = 'app.user.deleted';

    public function __construct(private readonly User $user)
    {
        parent::__construct();

        $this->addData($user);
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
