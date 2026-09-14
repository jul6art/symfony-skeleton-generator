<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\User;
use App\Security\UserRoles;
use DomainException;
use Jul6Art\CoreBundle\Util\Strings;
use Jul6Art\DataflowBundle\Import\RowMapperInterface;
use Override;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function in_array;

/**
 * Ce qu'une ligne de fichier veut dire pour un compte de ce produit — l'exemple d'import livré
 * avec le mode `backoffice` (`jul6art/dataflow-bundle`).
 *
 * ## Aucun mot de passe ne traverse le fichier
 *
 * ⚠️ **Le compte importé reçoit un mot de passe ALÉATOIRE que personne ne connaît**, haché comme
 * n'importe quel autre. Ce n'est pas un compte inutilisable : c'est un compte que son titulaire
 * active par « mot de passe oublié », le flux que ce mode livre déjà
 * (`symfonycasts/reset-password-bundle`). Accepter un mot de passe EN CLAIR dans une colonne de
 * fichier — ce que la tentation la plus simple aurait fait — le fait transiter par un tableur, un
 * courriel, une pièce jointe : trois endroits où un secret ne devrait jamais être écrit.
 *
 * ## `role`, et pas `roles`
 *
 * ⚠️ **Une seule valeur, pas une liste.** `UserRoles::assignable()` n'offre que deux rôles
 * au-delà du défaut implicite (`ROLE_USER`, jamais stocké — la hiérarchie de `security.yaml` le
 * donne à tout le monde) ; laisser la colonne vide crée un compte standard, `ROLE_ADMIN` ou
 * `ROLE_SUPER_ADMIN` en créent un plus élevé. Une valeur qui n'est ni vide ni l'un des deux est
 * refusée : accepter n'importe quelle chaîne en silence créerait des comptes avec un rôle inconnu
 * de tout voter.
 *
 * ## Ce que ce mapper ne fait pas
 *
 * Il ne lit pas le fichier, ne mappe pas les colonnes, ne détecte pas les doublons et ne compte pas
 * les lots — `dataflow-bundle` fait tout cela. Un doublon (même adresse) est écarté avant même que
 * `map()` soit appelé : {@see UserDuplicateResolver} le résout par lot, pas ligne par ligne.
 */
final readonly class UserRowMapper implements RowMapperInterface
{
    /**
     * Les colonnes qu'un fichier peut alimenter, dans l'ordre où l'écran de correspondance les
     * propose.
     *
     * @var list<string>
     */
    public const array FIELDS = ['firstName', 'lastName', 'email', 'role'];

    public function __construct(
        private UserPasswordHasherInterface $hasher,
    ) {
    }

    #[Override]
    public function fields(): array
    {
        return self::FIELDS;
    }

    #[Override]
    public function map(array $row, ?object $existing = null): object
    {
        // ⚠️ Les trois sont obligatoires : un compte sans nom ni prénom n'est identifiable dans
        // aucune liste, et un compte sans adresse ne peut ni se connecter ni réinitialiser son mot
        // de passe — il serait créé, invisible, et inatteignable.
        if (!isset($row['firstName'], $row['lastName'], $row['email'])
            || '' === trim($row['firstName'])
            || '' === trim($row['lastName'])
        ) {
            throw new DomainException('user.import.error.missing_fields');
        }

        // ⚠️ La même normalisation que `User::setEmail()` — via la même fonction, pas une copie
        // de sa logique — pour que la clef de doublon et la colonne UNIQUE s'accordent toujours.
        $email = Strings::lowerEmail($row['email']) ?? '';

        if ('' === $email || !str_contains($email, '@')) {
            throw new DomainException('user.import.error.invalid_email');
        }

        $role = trim($row['role'] ?? '');
        $roles = '' === $role ? [] : [$role];

        if ([] !== $roles && !in_array($role, array_values(UserRoles::assignable()), true)) {
            throw new DomainException('user.import.error.unknown_role');
        }

        $user = new User()
            ->setFirstName(trim($row['firstName']))
            ->setLastName(trim($row['lastName']))
            ->setEmail($email)
            ->setRoles($roles)
            ->setIsActive(true);

        $user->setPassword($this->hasher->hashPassword($user, bin2hex(random_bytes(32))));

        return $user;
    }
}
