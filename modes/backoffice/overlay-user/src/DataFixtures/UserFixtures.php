<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use App\Security\UserRoles;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Override;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function sprintf;

/**
 * Les comptes de démonstration : trois nommés, et une centaine de volume.
 *
 * ⚠️ `doctrine:fixtures:load` PURGE. Le premier administrateur créé par `make user-create`
 * disparaît donc à chaque chargement : c'est ICI qu'il renaît, sinon l'application se retrouve
 * sans aucun compte et il faut le recréer à la main.
 *
 * ⚠️ Le hachage est calculé UNE FOIS et réutilisé. Un hacheur moderne embarque son sel dans le
 * digest, donc un seul suffit pour cent comptes — et ça compte : cinquante comptes hachés
 * individuellement prenaient 42 s, contre 0,85 s avec un digest unique (leçon du 2026-08-23).
 *
 * Les rôles sont ceux qui EXISTENT (`UserRoles`). Les rôles métier du cahier des charges
 * (`MANAGER`, `DISPATCHER`, `TECHNICIAN`, `ACCOUNTANT`) arrivent avec ADR-0002, qui est encore
 * `Proposed` : les inventer ici les rendrait sans permissions, donc sans effet, et contournerait
 * ADR-0000. Le jour venu, seule la table `DISTRIBUTION` change.
 */
final class UserFixtures extends Fixture
{
    public const string USER_SUPER_ADMIN = 'user.super_admin';
    public const string USER_ADMIN = 'user.admin';
    public const string USER_STANDARD = 'user.standard';

    /** Le mot de passe de démonstration, écrit dans le README : les fixtures ne sont chargées qu'en dev et en test. */
    private const string PASSWORD = 'demo123456789';

    /** Un compte sur sept est inactif : sans eux, ni le badge « Inactive » ni l'action « Activer » ne se voient. */
    private const int INACTIVE_EVERY = 7;

    /**
     * Rôle → nombre de comptes de volume. Le total fait la centaine demandée.
     *
     * @var array<string, int>
     */
    private const array DISTRIBUTION = [
        UserRoles::ROLE_ADMIN => 10,
        '' => 90, // compte ordinaire : `ROLE_USER` vient de la hiérarchie, on ne le stocke pas
    ];

    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    #[Override]
    public function load(ObjectManager $manager): void
    {
        $digest = $this->hasher->hashPassword(new User(), self::PASSWORD);

        $named = [
            self::USER_SUPER_ADMIN => $this->makeUser('admin@example.test', 'Super', 'Admin', [UserRoles::ROLE_SUPER_ADMIN], $digest),
            self::USER_ADMIN => $this->makeUser('manager@example.test', 'Alice', 'Bertrand', [UserRoles::ROLE_ADMIN], $digest),
            self::USER_STANDARD => $this->makeUser('user@example.test', 'Bruno', 'Carvalho', [], $digest),
        ];

        foreach ($named as $reference => $user) {
            $manager->persist($user);
            $this->addReference($reference, $user);
        }

        $index = 0;
        foreach (self::DISTRIBUTION as $role => $count) {
            for ($i = 0; $i < $count; ++$i) {
                ++$index;

                $user = $this->makeUser(
                    sprintf('user%d@example.test', $index),
                    sprintf('Prénom%d', $index),
                    sprintf('Nom%d', $index),
                    '' === $role ? [] : [$role],
                    $digest,
                );
                $user->setIsActive(0 !== $index % self::INACTIVE_EVERY);

                $manager->persist($user);
                $this->addReference(sprintf('user.volume.%d', $index), $user);
            }
        }

        $manager->flush();

        $this->spreadCreationDates($manager);
    }

    /**
     * Étale les dates de création sur dix-huit mois.
     *
     * ⚠️ En SQL et pas par un setter : `createdAt` vient de `TimestampableTrait`, qui la pose en
     * `#[ORM\PrePersist]` sur une colonne `updatable: false` et n'expose aucun `setCreatedAt()`.
     * Sans cet étalement, les cent comptes naissent à la même seconde et le filtre de plage de
     * dates de la table n'a rien à filtrer.
     */
    private function spreadCreationDates(ObjectManager $manager): void
    {
        if (!$manager instanceof EntityManagerInterface) {
            return;
        }

        $connection = $manager->getConnection();
        $reference = new DateTimeImmutable('-18 months');

        foreach ($manager->getRepository(User::class)->findAll() as $offset => $user) {
            $connection->executeStatement(
                'UPDATE "user" SET created_at = :createdAt WHERE id = :id',
                [
                    'createdAt' => $reference->modify(sprintf('+%d days', $offset * 5))->format('Y-m-d H:i:s'),
                    'id' => $user->getId(),
                ],
            );
        }
    }

    /**
     * @param list<string> $roles
     */
    private function makeUser(string $email, string $firstName, string $lastName, array $roles, string $digest): User
    {
        return new User()
            ->setEmail($email)
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setRoles($roles)
            ->setIsActive(true)
            ->setPassword($digest);
    }
}
