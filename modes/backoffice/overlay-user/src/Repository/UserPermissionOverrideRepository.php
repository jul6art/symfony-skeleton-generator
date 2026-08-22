<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserPermissionOverride;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserPermissionOverride>
 */
class UserPermissionOverrideRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserPermissionOverride::class);
    }

    /**
     * Les décisions personnelles d'un compte, code → accordé.
     *
     * Une requête, comme pour les rôles : le moteur la fait une fois par requête HTTP.
     *
     * @return array<string, bool>
     */
    public function findMapForUser(User $user): array
    {
        /** @var list<array{permission: string, granted: bool}> $rows */
        $rows = $this->createQueryBuilder('o')
            ->select('o.permission', 'o.granted')
            ->andWhere('o.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'granted', 'permission');
    }
}
