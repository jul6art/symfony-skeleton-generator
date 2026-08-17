<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ResetPasswordRequest;
use DateTimeInterface;
use Doctrine\Persistence\ManagerRegistry;
use InvalidArgumentException;
use Jul6Art\AuthBundle\Entity\User;
use Jul6Art\CoreBundle\Repository\AbstractRepository;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordRequestInterface;
use SymfonyCasts\Bundle\ResetPassword\Persistence\Repository\ResetPasswordRequestRepositoryTrait;
use SymfonyCasts\Bundle\ResetPassword\Persistence\ResetPasswordRequestRepositoryInterface;

use function sprintf;

/**
 * Dépôt maison : il hérite d'AbstractRepository (core-bundle) pour disposer de
 * save/delete/flush/clear, et complète le contrat du bundle de réinitialisation.
 *
 * @extends AbstractRepository<ResetPasswordRequest>
 */
final class ResetPasswordRequestRepository extends AbstractRepository implements ResetPasswordRequestRepositoryInterface
{
    // persist / find / purge des demandes, fournis par le bundle.
    use ResetPasswordRequestRepositoryTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ResetPasswordRequest::class);
    }

    public function createResetPasswordRequest(object $user, DateTimeInterface $expiresAt, string $selector, string $hashedToken): ResetPasswordRequestInterface
    {
        if (!$user instanceof User) {
            throw new InvalidArgumentException(sprintf('Attendu une instance de "%s", reçu "%s".', User::class, $user::class));
        }

        return new ResetPasswordRequest($user, $expiresAt, $selector, $hashedToken);
    }
}
