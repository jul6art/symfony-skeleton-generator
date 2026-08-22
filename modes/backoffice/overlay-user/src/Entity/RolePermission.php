<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RolePermissionRepository;
use Doctrine\ORM\Mapping as ORM;
use Jul6Art\CoreBundle\Entity\Traits\IdTrait;

/**
 * Une permission accordée — ou explicitement refusée — à un rôle.
 *
 * Une ligne par couple (rôle, permission). Le booléen `granted` permet de distinguer « pas de
 * ligne » (jamais décidé, donc refusé) de « ligne à false » (décidé, et refusé) : la nuance ne
 * change rien à la décision, mais elle change ce que l'écran affiche à qui vient de décocher.
 */
#[ORM\Entity(repositoryClass: RolePermissionRepository::class)]
#[ORM\Table(name: 'role_permission')]
#[ORM\UniqueConstraint(name: 'uniq_role_permission', columns: ['role_code', 'permission'])]
#[ORM\Index(name: 'idx_role_permission_role', columns: ['role_code'])]
class RolePermission
{
    use IdTrait;

    #[ORM\Column(name: 'role_code', length: 120)]
    private string $roleCode = '';

    #[ORM\Column(length: 120)]
    private string $permission = '';

    #[ORM\Column]
    private bool $granted = true;

    public function getRoleCode(): string
    {
        return $this->roleCode;
    }

    public function setRoleCode(string $roleCode): static
    {
        $this->roleCode = $roleCode;

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
