<?php

declare(strict_types=1);

namespace App\Service;

use App\Import\UserDuplicateResolver;
use App\Import\UserRowMapper;
use Jul6Art\DataflowBundle\Import\HeaderInspection;
use Jul6Art\DataflowBundle\Import\HeaderInspector;
use Jul6Art\DataflowBundle\Import\ImportReport;
use Jul6Art\DataflowBundle\Import\ImportRunner;
use Jul6Art\DataflowBundle\Import\Spec\ImportSpec;
use Jul6Art\DataflowBundle\Io\Reader\CsvReader;

/**
 * L'import de comptes par fichier — l'exemple livré avec le mode `backoffice`
 * (`jul6art/dataflow-bundle`).
 *
 * ## Ce qui appartient à ce produit, et rien d'autre
 *
 * La lecture du fichier, la correspondance des colonnes, la résolution de doublons par LOT, le
 * flux en générateur : tout cela vient du bundle. Ce qui reste ici est la politique — quels champs
 * un compte comprend ({@see UserRowMapper}) et ce qui fait que deux lignes désignent le même
 * compte ({@see UserDuplicateResolver}).
 *
 * ⚠️ **Pas de plafond ni de limiteur de débit ici**, contrairement à un produit multi-tenant : ce
 * mode est mono-locataire, il n'y a ni offre ni organisation à qui appliquer un quota. Un projet
 * qui grandit vers plusieurs locataires ajoutera cette politique à son tour — cf. `docs/analyse` de
 * l'écosystème `jul6art` pour la forme qu'elle prend chez un consommateur qui en a besoin.
 */
final readonly class UserImportService
{
    public function __construct(
        private ImportRunner $runner,
        private HeaderInspector $inspector,
        private CsvReader $reader,
        private UserRowMapper $mapper,
        private UserDuplicateResolver $duplicateResolver,
    ) {
    }

    /**
     * Les en-têtes du fichier, confrontés aux champs que le mapper comprend.
     *
     * ⚠️ Rend une `HeaderInspection` et pas une liste de chaînes : l'écran a besoin de savoir
     * quelles colonnes se disputent un champ (le bundle les REFUSE au lieu de choisir) et
     * lesquelles ne correspondent à rien.
     */
    public function inspectHeaders(string $filePath): HeaderInspection
    {
        return $this->inspector->inspect(
            $this->inspector->peek($this->reader, $filePath),
            UserRowMapper::FIELDS,
        );
    }

    /**
     * @param array<int, string> $mapping position de colonne → champ
     */
    public function import(string $filePath, array $mapping): ImportReport
    {
        return $this->runner->run(
            new ImportSpec($filePath, $mapping),
            $this->mapper,
            $this->reader,
            $this->duplicateResolver,
        );
    }
}
