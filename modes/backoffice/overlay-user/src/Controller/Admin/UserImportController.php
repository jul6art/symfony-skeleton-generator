<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Import\UserRowMapper;
use App\Security\PermissionCodes;
use App\Service\UserImportService;
use DomainException;
use Jul6Art\CoreBundle\Controller\AbstractController;
use Jul6Art\DataflowBundle\Exception\ImportFailedException;
use Jul6Art\DataflowBundle\Exception\UnreadableFileException;
use Jul6Art\DataflowBundle\Io\Guard\SpreadsheetSignature;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function count;
use function in_array;
use function is_string;

use const DIRECTORY_SEPARATOR;

/**
 * L'import de comptes en deux temps — l'exemple livré avec le mode `backoffice`
 * (`jul6art/dataflow-bundle`) :
 *
 *  1. **Téléversement** — le fichier est déplacé dans un répertoire temporaire ; un jeton
 *     aléatoire, seul visible du navigateur, est associé au chemin RÉEL côté serveur, dans la
 *     session.
 *  2. **Correspondance** — l'utilisateur associe chaque en-tête du fichier à un champ de compte et
 *     soumet le jeton. Le contrôleur le résout, vérifie que le chemin reste sous
 *     `sys_get_temp_dir()` une fois canonicalisé, puis lance l'import.
 *
 * ⚠️ **Pourquoi un jeton de session plutôt qu'un champ caché.** Exposer le chemin réel au
 * navigateur permettrait à un client de le remplacer (`/tmp/../etc/passwd` passe un contrôle
 * `str_starts_with('/tmp')`). Le garder côté serveur, associé à un jeton aléatoire, ferme
 * entièrement cette voie.
 *
 * ⚠️ **`USER_CREATE`, pas un code inventé.** Importer des comptes EST créer des comptes — le même
 * pouvoir que le formulaire de création, par un autre chemin. Un code séparé donnerait à
 * quelqu'un le droit d'importer sans le droit de créer, ce qui n'a pas de sens ici.
 *
 * ⚠️ **La garde de rôle est répétée sur CHAQUE méthode, pas seulement sur la classe.**
 * `RouteAccessDecisionTest` — la règle n°1 de ce squelette — l'exige : un attribut de classe
 * protège bien à l'exécution, mais il disparaît du champ de vision dès qu'on lit une action
 * isolée, et une méthode ajoutée plus tard hériterait d'une décision que personne n'aurait prise
 * pour elle.
 */
#[Route('/admin/users/import', name: 'admin_user_import_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class UserImportController extends AbstractController
{
    /** @var list<string> */
    private const array ALLOWED_MIME_TYPES = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'];

    // La taille du fichier n'est qu'un garde-fou : le nombre de lignes est ce que borne réellement
    // `dataflow-bundle` (`ImportSpec::$maxRows`).
    private const int MAX_UPLOAD_BYTES = 5 * 1024 * 1024;

    private const string SESSION_KEY_PREFIX = 'user_import.token.';

    public function __construct(
        private readonly UserImportService $importService,
    ) {
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    #[IsGranted(PermissionCodes::USER_CREATE)]
    public function new(Request $request): Response
    {
        if (!$request->isMethod('POST')) {
            return $this->render('admin/user/import/new.html.twig');
        }

        if (!$this->isCsrfTokenValid('user_import_upload', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $file = $request->files->get('file');

        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return $this->redirectWithError('admin_user_import_new', 'user.import.error.upload_failed');
        }

        if ($file->getSize() > self::MAX_UPLOAD_BYTES) {
            return $this->redirectWithError('admin_user_import_new', 'user.import.error.file_too_large');
        }

        // ⚠️ AVANT le contrôle MIME. Le MIME deviné d'un `.xlsx` n'est de toute façon pas dans la
        // liste autorisée — il serait donc déjà refusé, mais par le message générique « format non
        // reconnu ». Celui d'un `.xls` binaire vaut `application/vnd.ms-excel`, qui DOIT rester
        // autorisé (les navigateurs l'envoient aussi pour un `.csv` produit par Excel) : ce
        // classeur-là traverserait le contrôle MIME, la lecture ligne à ligne échouerait en
        // silence sur du binaire, et l'écran annoncerait « importés : 0 » sans dire pourquoi.
        if ($this->looksLikeSpreadsheet($file)) {
            return $this->redirectWithError('admin_user_import_new', 'user.import.error.binary_spreadsheet');
        }

        if (!in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES, true)) {
            return $this->redirectWithError('admin_user_import_new', 'user.import.error.invalid_mime');
        }

        try {
            $stored = $file->move(sys_get_temp_dir(), uniqid('user_import_', true).'.csv');
        } catch (FileException) {
            return $this->redirectWithError('admin_user_import_new', 'user.import.error.upload_failed');
        }

        try {
            $inspection = $this->importService->inspectHeaders($stored->getPathname());
        } catch (UnreadableFileException|DomainException $refusal) {
            @unlink($stored->getPathname());

            return $this->redirectWithError('admin_user_import_new', $refusal->getMessage());
        }

        $token = bin2hex(random_bytes(16));
        $request->getSession()->set(self::SESSION_KEY_PREFIX.$token, $stored->getPathname());

        return $this->render('admin/user/import/map.html.twig', [
            'inspection' => $inspection,
            'file_token' => $token,
            'recognized_fields' => UserRowMapper::FIELDS,
        ]);
    }

    #[Route('/execute', name: 'execute', methods: ['POST'])]
    #[IsGranted(PermissionCodes::USER_CREATE)]
    public function execute(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('user_import_execute', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $filePath = $this->resolveAndConsumeFilePath($request);

        if (null === $filePath) {
            return $this->redirectWithError('admin_user_import_new', 'user.import.error.upload_failed');
        }

        // ⚠️ Indexée par POSITION de colonne, jamais par nom d'en-tête : un fichier avec deux
        // colonnes `email` n'en produirait qu'une entrée sous un nom, et la seconde disparaîtrait
        // sans un mot — l'import lirait alors une colonne plausible mais fausse pour chaque ligne.
        $mapping = [];

        foreach ((array) $request->request->all('mapping') as $index => $field) {
            $field = is_string($field) ? $field : '';

            if (is_numeric($index) && in_array($field, UserRowMapper::FIELDS, true)) {
                $mapping[(int) $index] = $field;
            }
        }

        // ⚠️ Deux colonnes vers un même champ sont refusées ICI, pas résolues : garder la dernière
        // lirait une colonne que personne n'a délibérément choisie. `ImportSpec` refuserait la
        // charge de toute façon ; le message est rendu utile plutôt qu'une exception non attrapée.
        if ([] === $mapping || count(array_unique($mapping)) !== count($mapping)) {
            @unlink($filePath);

            return $this->redirectWithError('admin_user_import_new', 'user.import.error.mapping_required');
        }

        try {
            $report = $this->importService->import($filePath, $mapping);
        } catch (ImportFailedException $failure) {
            @unlink($filePath);

            // ⚠️ Un lot n'a pas pu être écrit : l'`EntityManager` est fermé, il n'y a pas de
            // « on continue ». Ce que l'opérateur doit voir d'abord est jusqu'où le fichier est
            // allé, puis si ce qui était écrit a été annulé.
            return $this->render('admin/user/import/result.html.twig', [
                'report' => $failure->report(),
                'failed_at' => $failure->record(),
                'rolled_back' => $failure->wasRolledBack(),
            ]);
        } catch (UnreadableFileException|DomainException $refusal) {
            @unlink($filePath);

            return $this->redirectWithError('admin_user_import_new', $refusal->getMessage());
        } finally {
            @unlink($filePath);
        }

        return $this->render('admin/user/import/result.html.twig', ['report' => $report]);
    }

    /**
     * Lit les premiers octets du téléversement et dit s'ils forment un CONTENEUR de classeur
     * plutôt que du texte.
     */
    private function looksLikeSpreadsheet(UploadedFile $file): bool
    {
        $path = $file->getRealPath();

        // ⚠️ Vingt lignes remplacées par une : `SpreadsheetSignature::matchesFile()` porte son
        // propre `finally`, son propre plafond de lecture et son propre test à douze cas.
        return false !== $path && SpreadsheetSignature::matchesFile($path);
    }

    /**
     * Récupère le chemin depuis la session pour le jeton soumis, et le retire — un jeton ne se
     * rejoue pas. Vérifie que le chemin reste sous `sys_get_temp_dir()` une fois canonicalisé,
     * pour déjouer une tentative de traversée (`..`).
     */
    private function resolveAndConsumeFilePath(Request $request): ?string
    {
        $token = (string) $request->request->get('file_token');

        if ('' === $token || !preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }

        $session = $request->getSession();
        $sessionKey = self::SESSION_KEY_PREFIX.$token;
        $stored = $session->get($sessionKey);
        $session->remove($sessionKey);

        if (!is_string($stored) || '' === $stored) {
            return null;
        }

        $real = realpath($stored);
        $tempReal = realpath(sys_get_temp_dir());

        if (false === $real || false === $tempReal) {
            return null;
        }

        if (!str_starts_with($real, $tempReal.DIRECTORY_SEPARATOR)) {
            return null;
        }

        if (!is_file($real)) {
            return null;
        }

        return $real;
    }
}
