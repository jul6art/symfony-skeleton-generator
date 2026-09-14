<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Override;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

use function sprintf;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Ré-hache un mot de passe quand l'algorithme a évolué, à la connexion suivante. Sans ça, un
     * changement de coût ou d'algorithme ne s'applique qu'aux comptes créés après.
     */
    #[Override]
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->flush();
    }

    /**
     * ⚠️ Rend un `QueryBuilder`, jamais un tableau.
     *
     * Un fournisseur d'état d'API Platform qui rend un tableau court-circuite la pagination, les
     * filtres et le tri : la collection entière part sur le réseau, et `pagination_enabled` n'y
     * change rien. Le symptôme est une datatable qui affiche tout, à la première page, sans erreur.
     */
    public function createListQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('u');
    }

    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => mb_strtolower(trim($email))]);
    }

    /**
     * Les comptes existants parmi une liste d'adresses, en UNE requête — pour l'import CSV
     * ({@see \App\Import\UserDuplicateResolver}), qui doit résoudre les doublons d'un lot entier
     * sans interroger la base ligne par ligne.
     *
     * @param list<string> $emails déjà normalisées (minuscules, sans espace) par l'appelant
     *
     * @return array<string, User> indexé par l'adresse normalisée
     */
    public function findByEmails(array $emails): array
    {
        if ([] === $emails) {
            return [];
        }

        $rows = $this->createQueryBuilder('u')
            ->andWhere('u.email IN (:emails)')
            ->setParameter('emails', $emails)
            ->getQuery()
            ->getResult();

        $indexed = [];

        foreach ($rows as $user) {
            $indexed[$user->getEmail()] = $user;
        }

        return $indexed;
    }
}
