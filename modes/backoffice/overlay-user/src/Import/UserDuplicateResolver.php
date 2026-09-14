<?php

declare(strict_types=1);

namespace App\Import;

use App\Repository\UserRepository;
use Jul6Art\CoreBundle\Util\Strings;
use Jul6Art\DataflowBundle\Import\DuplicateResolverInterface;
use Override;

/**
 * Ce qui fait que deux lignes désignent le même compte dans ce produit : l'adresse électronique.
 *
 * ⚠️ **Une requête pour le LOT, jamais par ligne.** Le contrat de `dataflow-bundle` prend le lot
 * entier précisément pour rendre la version par ligne impossible à écrire — un fichier de cinq
 * mille lignes vaudrait sinon cinq mille `SELECT`.
 *
 * ⚠️ **`keyOf()` n'est pas une commodité.** Sans elle, deux lignes portant la même adresse dans le
 * MÊME fichier sont toutes les deux neuves : ni l'une ni l'autre n'est en base quand le lot est
 * interrogé, donc les deux sont persistées et le `flush` meurt sur l'index unique de `email` — ce
 * qui ferme l'`EntityManager` et emporte le reste du fichier. Le moteur du bundle retient les
 * clefs déjà vues dans le lot et ignore la seconde occurrence.
 *
 * ⚠️ **En minuscules.** Une adresse électronique est insensible à la casse dans son domaine :
 * `Ada@example.com` et `ada@example.com` sont le même compte, et les traiter autrement produirait
 * exactement la panne ci-dessus. `UserRepository::findOneByEmail()` normalise déjà de la même
 * façon — c'est la même règle, à deux endroits qui doivent s'accorder.
 *
 * ⚠️ **Une ligne sans adresse rend `null`, donc n'est jamais un doublon** — mais `UserRowMapper`
 * refuse déjà une ligne sans adresse avant que ce résolveur n'entre en jeu, donc ce cas ne se
 * présente pas ici en pratique. Rendre `null` reste le comportement correct si l'ordre changeait un
 * jour.
 */
final readonly class UserDuplicateResolver implements DuplicateResolverInterface
{
    public function __construct(
        private UserRepository $users,
    ) {
    }

    #[Override]
    public function keyOf(array $row): ?string
    {
        // ⚠️ La même normalisation que `User::setEmail()`, via la même fonction : c'est ce qui
        // garantit que cette clef retrouve un compte que l'entité a normalisé à l'écriture.
        $email = Strings::lowerEmail($row['email'] ?? null);

        return '' === $email || null === $email ? null : $email;
    }

    #[Override]
    public function findExisting(array $rows): array
    {
        $emails = [];

        foreach ($rows as $index => $row) {
            $key = $this->keyOf($row);

            if (null !== $key) {
                $emails[$index] = $key;
            }
        }

        if ([] === $emails) {
            return [];
        }

        // Une seule requête pour le lot entier.
        $stored = $this->users->findByEmails(array_values($emails));

        $existing = [];

        foreach ($emails as $index => $email) {
            if (isset($stored[$email])) {
                $existing[$index] = $stored[$email];
            }
        }

        return $existing;
    }
}
