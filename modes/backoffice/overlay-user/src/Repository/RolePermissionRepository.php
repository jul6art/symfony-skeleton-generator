<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RolePermission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RolePermission>
 */
class RolePermissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RolePermission::class);
    }

    /**
     * Les permissions accordées à un jeu de rôles, dédoublonnées.
     *
     * ⚠️ Une seule requête, pas une par rôle : le moteur appelle ceci UNE fois par compte et par
     * requête HTTP, et met le résultat en mémoire. C'est ce qui garde une page à vingt
     * vérifications de permission à deux requêtes plutôt qu'à quarante.
     *
     * @param list<string> $roles
     *
     * @return list<string>
     */
    public function findGrantedForRoles(array $roles): array
    {
        if ([] === $roles) {
            return [];
        }

        /** @var list<array{permission: string}> $rows */
        $rows = $this->createQueryBuilder('rp')
            ->select('rp.permission')
            ->andWhere('rp.roleCode IN (:roles)')
            ->andWhere('rp.granted = true')
            ->setParameter('roles', $roles)
            ->getQuery()
            ->getArrayResult();

        return array_values(array_unique(array_column($rows, 'permission')));
    }

    public function findOneForRole(string $roleCode, string $permission): ?RolePermission
    {
        return $this->findOneBy(['roleCode' => $roleCode, 'permission' => $permission]);
    }
}
