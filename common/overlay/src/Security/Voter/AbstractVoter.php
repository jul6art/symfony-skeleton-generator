<?php

declare(strict_types=1);

namespace App\Security\Voter;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Service\Attribute\Required;

use function in_array;

/**
 * Classe mère de tout voter maison.
 *
 * Règle n°1 du projet : une route = une action de voter. Le contrôleur (ou
 * l'opération API Platform) nomme l'action, le voter décide — et lui seul.
 * Les rôles sont la matière de la décision, ici, jamais son expression dans un
 * contrôleur ou un gabarit.
 *
 * @extends Voter<string, mixed>
 */
abstract class AbstractVoter extends Voter
{
    private Security $security;

    /**
     * Injection par setter : les voters concrets gardent ainsi leur constructeur
     * pour leurs propres dépendances, sans relayer celle-ci (même idiome que les
     * `*AwareTrait` du core-bundle).
     */
    #[Required]
    public function setSecurity(Security $security): void
    {
        $this->security = $security;
    }

    /**
     * Verdict mis en cache par Symfony : le voter n'est plus rappelé pour les
     * attributs qu'il ne porte pas.
     */
    public function supportsAttribute(string $attribute): bool
    {
        return in_array($attribute, $this->attributes(), true);
    }

    /**
     * Les attributs portés par ce voter : un par action exposée.
     *
     * @return list<string>
     */
    abstract protected function attributes(): array;

    /**
     * Décision, une fois le compte connu : un visiteur anonyme est refusé avant
     * d'arriver ici. Une route ouverte à tous ne passe donc pas par un voter
     * maison, elle le dit avec `#[IsGranted(AuthenticatedVoter::PUBLIC_ACCESS)]`.
     */
    abstract protected function decide(string $attribute, mixed $subject, UserInterface $user): bool;

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $this->supportsAttribute($attribute) && $this->supportsSubject($subject);
    }

    /**
     * À redéfinir quand les attributs du voter ne valent que pour un type de
     * sujet donné.
     */
    protected function supportsSubject(mixed $subject): bool
    {
        return true;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        return $user instanceof UserInterface && $this->decide($attribute, $subject, $user);
    }

    /**
     * Rôle du compte connecté, hiérarchie comprise (`role_hierarchy`), là où
     * `getRoles()` ne rend que les rôles réellement stockés. Lu sur le jeton
     * courant — celui-là même que reçoit `voteOnAttribute()` dans une requête.
     */
    protected function hasRole(string $role): bool
    {
        return $this->security->isGranted($role);
    }
}
