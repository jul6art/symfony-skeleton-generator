<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserPermissionOverrideRepository;
use Doctrine\ORM\Mapping as ORM;
use Jul6Art\CoreBundle\Entity\Traits\IdTrait;

/**
 * Une décision par personne, qui l'emporte sur ce que ses rôles disent.
 *
 * Dans les deux sens : `granted = true` accorde à quelqu'un ce que son rôle n'a pas,
 * `granted = false` le lui retire alors que son rôle l'a. C'est le second cas qui justifie la
 * table — sans lui, retirer une permission à une personne obligerait à lui inventer un rôle.
 */
#[ORM\Entity(repositoryClass: UserPermissionOverrideRepository::class)]
#[ORM\Table(name: 'user_permission_override')]
#[ORM\UniqueConstraint(name: 'uniq_user_permission', columns: ['user_id', 'permission'])]
class UserPermissionOverride
{
    use IdTrait;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 120)]
    private string $permission = '';

    #[ORM\Column]
    private bool $granted = true;

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getPermission(): string
    {
        return $this->permission;
    }

    public function setPermission(string $permission): static
    {
        $this->permission = $permission;

        return $this;
    }

    public function isGranted(): bool
    {
        return $this->granted;
    }

    public function setGranted(bool $granted): static
    {
        $this->granted = $granted;

        return $this;
    }
}
