<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\DatatableBundle\Preference\Doctrine\AbstractDatatablePreference;

/**
 * L'arrangement d'UN tableau par UN compte : colonnes affichées, leur ordre, vues enregistrées.
 *
 * Le stockage vient de `jul6art/datatable-bundle` (≥ 3.3, `datatable.preferences.storage` dans
 * `config/packages/datatable_preferences.yaml`) : les colonnes, le compte et le magasin Doctrine. Cette classe ne
 * porte que ce qui est au projet — la table et son unicité, qu'une classe de base ne peut pas
 * déclarer.
 *
 * ## Ni `#[ApiResource]`, ni `#[Auditable]`
 *
 * L'endpoint du bundle est la seule entrée, et il lit le compte sur le jeton de sécurité : une
 * collection API n'ajouterait qu'une surface sur laquelle un compte pourrait demander les lignes
 * d'un autre. Quant à journaliser « quelqu'un a masqué une colonne » : le journal existe pour les
 * changements qui ont des conséquences.
 */
#[ORM\Entity]
#[ORM\Table(name: 'datatable_preference')]
// L'UNIQUE est la sémantique, pas une optimisation : une ligne par (compte, tableau). Une seconde
// sauvegarde sur le même tableau REMPLACE la première.
#[ORM\UniqueConstraint(name: 'uniq_datatable_preference_owner_key', columns: ['owner_id', 'datatable_key'])]
class DatatablePreference extends AbstractDatatablePreference
{
}
