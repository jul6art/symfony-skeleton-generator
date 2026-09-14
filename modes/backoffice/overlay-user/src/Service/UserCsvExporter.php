<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\UserRepository;
use Doctrine\ORM\AbstractQuery;
use Generator;

use function is_array;
use function json_decode;

/**
 * La liste des comptes, en lignes prêtes à écrire — l'exemple d'export livré avec le mode
 * `backoffice` (`jul6art/dataflow-bundle`).
 *
 * ⚠️ **Un `Generator`, jamais un tableau**, et `HYDRATE_SCALAR` plutôt que des entités : un export
 * de dix mille comptes ne doit ni les garder tous en mémoire, ni les faire entrer dans l'unité de
 * travail de Doctrine, qui grandirait d'autant. C'est le même dispositif que le moteur de rapports
 * du bundle utilise pour la même raison.
 *
 * ⚠️ **Ni le mot de passe, ni son hachage, ni aucun jeton n'apparaissent dans les colonnes** — ce
 * fichier peut être ouvert dans un tableur et transiter par courriel.
 *
 * ⚠️ **`HYDRATE_SCALAR` ne convertit pas chaque colonne de la même façon, et ça ne se devine pas —
 * ça se vérifie.** Constaté sur SQLite ET sur PostgreSQL : `isActive` revient en `bool` natif
 * (converti), mais `roles` revient en JSON encore en TEXTE (`'["ROLE_ADMIN"]'`, une chaîne) et
 * `createdAt` en chaîne de date déjà au format `Y-m-d H:i:s` — ni l'un ni l'autre en valeur PHP
 * typée. Une première version de cette classe appelait `$row['createdAt']->format(...)` et
 * `implode(',', $row['roles'])` en confiance : la première ligne exportée aurait fait échouer
 * l'export entier avec une `TypeError`, jamais silencieusement.
 */
final readonly class UserCsvExporter
{
    /** @var list<string> */
    public const array HEADER = ['id', 'email', 'firstName', 'lastName', 'roles', 'isActive', 'createdAt'];

    public function __construct(
        private UserRepository $users,
    ) {
    }

    /**
     * @return Generator<int, list<scalar|null>>
     */
    public function rows(): Generator
    {
        $query = $this->users->createListQueryBuilder()
            ->select('u.id, u.email, u.firstName, u.lastName, u.roles, u.isActive, u.createdAt')
            ->getQuery();

        /** @var iterable<array{id: int, email: string, firstName: string, lastName: string, roles: string, isActive: bool|int, createdAt: string}> $scalars */
        $scalars = $query->toIterable([], AbstractQuery::HYDRATE_SCALAR);

        foreach ($scalars as $row) {
            $roles = json_decode($row['roles'], true);

            yield [
                $row['id'],
                $row['email'],
                $row['firstName'],
                $row['lastName'],
                implode(',', is_array($roles) ? $roles : []),
                $row['isActive'] ? '1' : '0',
                // Déjà au format attendu — pas de `DateTimeImmutable` à construire pour le
                // reformater à l'identique.
                $row['createdAt'],
            ];
        }
    }
}
