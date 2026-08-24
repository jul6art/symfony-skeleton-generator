<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Entity\DatatablePreference;
use App\Entity\User;
use App\Repository\DatatablePreferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Jul6Art\DatatableBundle\Preference\DatatablePreferenceStoreInterface;
use Override;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Où ce projet garde l'arrangement d'un tableau par compte.
 *
 * Le bundle interprète les préférences et sert `GET` / `PUT` / `DELETE` sur
 * `/admin/datatable/preferences/{key}` ; il ne stocke rien, donc cette classe est toute la moitié
 * qui appartient au projet. Elle est branchée sur le port dans `config/services.yaml` — sans cet
 * alias, le pass du bundle RETIRE le contrôleur et la fonctionnalité n'existe pas, silencieusement
 * et à dessein.
 *
 * ## Le compte vient du jeton, et c'est tout le modèle de sécurité
 *
 * Le contrat prend un `UserInterface` parce que le bundle ne laisse jamais le client envoyer un
 * identifiant. Lire ou écrire les préférences d'un autre n'est donc pas « interdit » : c'est
 * irreprésentable, ce qui est la raison pour laquelle aucun voter ni aucun `PermissionCodes::*` ne
 * garde ceci. Qui peut ouvrir l'écran peut l'arranger ; ce qu'il a le droit de VOIR est décidé par
 * la collection API que la table lit.
 */
final readonly class DatatablePreferenceStore implements DatatablePreferenceStoreInterface
{
    public function __construct(
        private DatatablePreferenceRepository $preferences,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function read(UserInterface $user, string $key): ?string
    {
        if (!$user instanceof User) {
            return null;
        }

        return $this->preferences->findOneForUser($user, $key)?->getPayload();
    }

    /**
     * Upsert, jamais un `INSERT` aveugle.
     *
     * L'endpoint est un `PUT` et le navigateur renvoie tout l'état à chaque sauvegarde : la
     * seconde sur le même tableau heurterait `uniq_datatable_preference_owner_key` et sortirait en
     * 500 — au SECOND clic, ce qui est le genre de défaut qui part en production.
     */
    #[Override]
    public function write(UserInterface $user, string $key, string $json): void
    {
        if (!$user instanceof User) {
            return;
        }

        $preference = $this->preferences->findOneForUser($user, $key);

        if (null === $preference) {
            $this->entityManager->persist(new DatatablePreference($user, $key, $json));
        } else {
            $preference->setPayload($json);
        }

        $this->entityManager->flush();
    }

    #[Override]
    public function delete(UserInterface $user, string $key): void
    {
        if (!$user instanceof User) {
            return;
        }

        $preference = $this->preferences->findOneForUser($user, $key);
        if (null !== $preference) {
            $this->entityManager->remove($preference);
            $this->entityManager->flush();
        }
    }
}
