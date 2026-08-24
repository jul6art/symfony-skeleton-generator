<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DatatablePreference;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DatatablePreference>
 */
class DatatablePreferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DatatablePreference::class);
    }

    /**
     * La seule lecture de cette entité. `findOneBy` plutôt qu'un `QueryBuilder` : l'UNIQUE
     * `(owner_id, datatable_key)` en fait une lecture par clé, et il n'y a rien à composer.
     */
    public function findOneForUser(User $owner, string $datatableKey): ?DatatablePreference
    {
        return $this->findOneBy(['owner' => $owner, 'datatableKey' => $datatableKey]);
    }
}
