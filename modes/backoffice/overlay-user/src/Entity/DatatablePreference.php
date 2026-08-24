<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DatatablePreferenceRepository;
use Doctrine\ORM\Mapping as ORM;
use Jul6Art\CoreBundle\Entity\Traits\IdTrait;
use Jul6Art\CoreBundle\Entity\Traits\TimestampableTrait;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * L'arrangement d'UN tableau par UN compte : colonnes affichées, leur ordre, vues enregistrées.
 *
 * `jul6art/datatable-bundle` interprète le contenu et sert les trois opérations HTTP ; il ne stocke
 * rien, à dessein. Ceci est la moitié du contrat qui appartient au projet — cf.
 * {@see \App\DataTable\DatatablePreferenceStore}.
 *
 * ## Ni `#[ApiResource]`, ni `#[Auditable]`
 *
 * L'endpoint du bundle est la seule entrée, et il lit le compte sur le jeton de sécurité : une
 * collection API n'ajouterait qu'une surface sur laquelle un compte pourrait demander les lignes
 * d'un autre. Quant à journaliser « quelqu'un a masqué une colonne » : le journal existe pour les
 * changements qui ont des conséquences.
 */
#[ORM\Entity(repositoryClass: DatatablePreferenceRepository::class)]
#[ORM\Table(name: 'datatable_preference')]
// L'UNIQUE est la sémantique, pas une optimisation : une ligne par (compte, tableau). C'est aussi
// ce qui fait de `DatatablePreferenceStore::write()` un upsert — une seconde sauvegarde sur le même
// tableau doit REMPLACER la première, et l'index est ce qui le dit tout haut.
#[ORM\UniqueConstraint(name: 'uniq_datatable_preference_owner_key', columns: ['owner_id', 'datatable_key'])]
#[ORM\HasLifecycleCallbacks]
class DatatablePreference
{
    use IdTrait;
    use TimestampableTrait;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    /**
     * La clé du tableau, telle que le gabarit l'a passée au partial du bundle. Elle nomme un
     * TABLEAU et pas une entité : deux écrans listant les mêmes comptes avec des colonnes
     * différentes sont deux clés.
     *
     * 64 caractères, comme le `requirements` de la route du bundle
     * (`[a-z0-9][a-z0-9_.-]{0,63}`) : la colonne ne peut pas être atteinte avec plus long.
     */
    #[ORM\Column(name: 'datatable_key', length: 64)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    private string $datatableKey;

    /**
     * Le JSON produit par l'interpréteur du bundle, stocké tel quel.
     *
     * `text` et non `json` : la valeur est OPAQUE pour ce projet. Un type `json` ferait décoder
     * Doctrine à la lecture et ré-encoder à l'écriture, donc les octets rendus ne seraient plus
     * ceux reçus — et le contrat du bundle est que le magasin restitue exactement ce qu'on lui a
     * donné. L'interpréteur le borne déjà à 16 Ko.
     */
    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private string $payload;

    public function __construct(User $owner, string $datatableKey, string $payload)
    {
        $this->owner = $owner;
        $this->datatableKey = $datatableKey;
        $this->payload = $payload;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function getDatatableKey(): string
    {
        return $this->datatableKey;
    }

    public function getPayload(): string
    {
        return $this->payload;
    }

    public function setPayload(string $payload): static
    {
        $this->payload = $payload;

        return $this;
    }
}
