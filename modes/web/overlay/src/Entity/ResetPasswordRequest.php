<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ResetPasswordRequestRepository;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Jul6Art\AuthBundle\Entity\User;
use Jul6Art\CoreBundle\Entity\Traits\IdTrait;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordRequestInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordRequestTrait;

#[ORM\Entity(repositoryClass: ResetPasswordRequestRepository::class)]
class ResetPasswordRequest implements ResetPasswordRequestInterface
{
    // id + getId(), fournis par le core-bundle.
    use IdTrait;
    // selector, hashedToken, requestedAt, expiresAt + accesseurs.
    use ResetPasswordRequestTrait;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    public function __construct(User $user, DateTimeInterface $expiresAt, string $selector, string $hashedToken)
    {
        $this->user = $user;
        $this->initialize($expiresAt, $selector, $hashedToken);
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
