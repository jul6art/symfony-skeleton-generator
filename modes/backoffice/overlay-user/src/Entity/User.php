<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\UserRepository;
use App\Security\PermissionCodes;
use App\Security\UserRoles;
use Doctrine\ORM\Mapping as ORM;
use Jul6Art\AclBundle\Contract\AclTenantInterface;
use Jul6Art\AclBundle\Contract\AclUserInterface;
use Jul6Art\AdminBundle\Appearance\ColorMode;
use Jul6Art\AdminBundle\Contract\AdminUserInterface;
use Jul6Art\AdminBundle\Contract\AppearanceAwareInterface;
use Jul6Art\AdminBundle\Entity\Traits\AppearancePreferencesTrait;
use Jul6Art\ApiBundle\Filter\OrSearchFilter;
use Jul6Art\CoreBundle\Entity\Traits\IdTrait;
use Jul6Art\CoreBundle\Entity\Traits\SoftDeletableTrait;
use Jul6Art\CoreBundle\Entity\Traits\TimestampableTrait;
use Jul6Art\CoreBundle\Util\Strings;
use Jul6Art\PushBundle\Attribute\BroadcastableEntity;
use Override;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

use function in_array;

/**
 * Le compte.
 *
 * Il implémente quatre contrats, tous étroits, et c'est ce qui permet à quatre bundles de s'en
 * servir sans qu'aucun ne l'impose :
 *
 * - `UserInterface` — Symfony ;
 * - `AclUserInterface` — le moteur de permissions. `getTenant()` rend `null` : cette application
 *   n'a pas de locataires, et le contrat l'a toujours prévu ;
 * - `AdminUserInterface` — ce que la coquille affiche : un nom, deux initiales, une photo ;
 * - `AppearanceAwareInterface` — les préférences d'affichage, dont cinq colonnes viennent du trait.
 *
 * ⚠️ `getColorMode()` / `setColorMode()` ne sont PAS dans le trait, à dessein : presque toute
 * application a déjà une colonne pour ça sous son propre nom. Ces deux méthodes sont le
 * branchement, pas une redondance.
 *
 * L'exposition en API sert la datatable : elle lit `GET /api/users`, avec la pagination, le tri et
 * les filtres d'API Platform. Le mot de passe n'appartient à aucun groupe de sérialisation, donc
 * il ne peut pas fuir.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\HasLifecycleCallbacks]
#[BroadcastableEntity]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('".PermissionCodes::USER_READ."')"),
        new Get(security: "is_granted('".PermissionCodes::USER_READ."')"),
    ],
    normalizationContext: ['groups' => ['user:read']],
    order: ['id' => 'DESC'],
)]
#[ApiFilter(SearchFilter::class, properties: ['email' => 'partial', 'isActive' => 'exact', 'roles' => 'partial'])]
#[ApiFilter(OrSearchFilter::class, properties: ['email', 'firstName', 'lastName'])]
#[ApiFilter(OrderFilter::class, properties: ['id', 'email', 'firstName', 'lastName', 'createdAt'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface, AclUserInterface, AdminUserInterface, AppearanceAwareInterface
{
    /**
     * Les cinq colonnes `appearance_*`, livrées par `admin-bundle`. Elles ne sont volontairement
     * exposées par aucun groupe de sérialisation : ce sont des préférences, pas des données.
     */
    use AppearancePreferencesTrait;
    use IdTrait;
    use SoftDeletableTrait;

    use TimestampableTrait;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    #[Groups(['user:read'])]
    private string $email = '';

    /**
     * @var list<string>
     */
    #[ORM\Column]
    #[Groups(['user:read'])]
    private array $roles = [];

    #[ORM\Column]
    private string $password = '';

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[Groups(['user:read'])]
    private string $firstName = '';

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[Groups(['user:read'])]
    private string $lastName = '';

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['user:read'])]
    private bool $isActive = true;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['user:read'])]
    private ?string $avatarPath = null;

    /** Le mode de couleur. `admin-bundle` le laisse hors de son trait pour cette raison précise. */
    #[ORM\Column(length: 10, options: ['default' => 'light'])]
    private string $theme = ColorMode::Light->value;

    /** Transitoire : le formulaire l'écrit, le contrôleur le hache. Jamais mappé. */
    private ?string $plainPassword = null;

    public function __toString(): string
    {
        return $this->getDisplayName();
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        // Normalisation à l'écriture : une adresse est insensible à la casse, la colonne UNIQUE ne
        // l'est pas. Sans ça, `Ada@x.test` et `ada@x.test` sont deux comptes.
        $this->email = Strings::lowerEmail($email) ?? '';

        return $this;
    }

    #[Override]
    public function getUserIdentifier(): string
    {
        // `UserInterface` promet une chaîne non vide ; la propriété, elle, a un défaut vide pour
        // qu'un `new User()` soit constructible dans un test ou une fixture.
        return '' === $this->email ? 'anonymous' : $this->email;
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = UserRoles::ROLE_USER;

        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = array_values(array_filter($roles, static fn (string $r): bool => UserRoles::ROLE_USER !== $r));

        return $this;
    }

    #[Override]
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): static
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    /**
     * `UserInterface::eraseCredentials()` a quitté l'interface en Symfony 7.3 — d'où l'absence de
     * `#[\Override]`, qui serait une erreur fatale au chargement de la classe.
     */
    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    /** Exposé en API : c'est la colonne que la datatable affiche et sur laquelle elle trie. */
    #[Groups(['user:read'])]
    public function getFullName(): string
    {
        return trim($this->firstName.' '.$this->lastName);
    }

    #[Override]
    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    #[Override]
    public function isSuperAdmin(): bool
    {
        return in_array(UserRoles::ROLE_SUPER_ADMIN, $this->getRoles(), true);
    }

    /**
     * Pas de locataires dans cette application. Le contrat le prévoit depuis le premier jour, et
     * `acl.multi_tenant: false` dit au moteur de ne pas exiger ce qui n'existe pas.
     */
    #[Override]
    public function getTenant(): ?AclTenantInterface
    {
        return null;
    }

    #[Override]
    public function getColorMode(): ColorMode
    {
        return ColorMode::fromStorage($this->theme);
    }

    #[Override]
    public function setColorMode(ColorMode $mode): static
    {
        $this->theme = $mode->value;

        return $this;
    }

    #[Override]
    public function getDisplayName(): string
    {
        return '' !== $this->getFullName() ? $this->getFullName() : $this->email;
    }

    #[Override]
    public function getInitials(): string
    {
        $initials = mb_substr($this->firstName, 0, 1).mb_substr($this->lastName, 0, 1);

        return '' !== $initials ? mb_strtoupper($initials) : mb_strtoupper(mb_substr($this->email, 0, 1));
    }

    #[Override]
    public function getAvatarPath(): ?string
    {
        return $this->avatarPath;
    }

    public function setAvatarPath(?string $avatarPath): static
    {
        $this->avatarPath = $avatarPath;

        return $this;
    }
}
