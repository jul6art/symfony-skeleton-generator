<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ResetPasswordRequestRepository;
use Doctrine\ORM\Mapping as ORM;
use Jul6Art\CoreBundle\Entity\Traits\IdTrait;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordRequestInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordRequestTrait;

/**
 * Une demande de réinitialisation en cours.
 *
 * Le trait du bundle porte le jeton haché, sa date d'expiration et celle de consommation ; ce qui
 * reste à écrire, c'est le lien vers le compte — le bundle ne connaît pas l'entité `User`.
 */
#[ORM\Entity(repositoryClass: ResetPasswordRequestRepository::class)]
class ResetPasswordRequest implements ResetPasswordRequestInterface
{
    use IdTrait;
    use ResetPasswordRequestTrait;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    public function __construct(User $user, \DateTimeInterface $expiresAt, string $selector, string $hashedToken)
    {
        $this->user = $user;
        $this->initialize($expiresAt, $selector, $hashedToken);
    }

    #[\Override]
    public function getUser(): User
    {
        \assert($this->user instanceof User);

        return $this->user;
    }
}
