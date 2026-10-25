<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use App\Security\UserRoles;
use Jul6Art\UiBundle\Form\Type\CustomEmailType;
use Jul6Art\UiBundle\Form\Type\CustomPasswordType;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function array_filter;

/**
 * Création et édition d'un compte, côté administration.
 *
 * `require_password` distingue les deux usages : à la création il faut un mot de passe, à l'édition
 * le laisser vide veut dire « inchangé ». C'est le contrôleur qui applique la règle — le formulaire
 * ne fait qu'exprimer l'obligation.
 *
 * @extends AbstractType<User>
 */
final class UserType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // ⚠️ `ROLE_SUPER_ADMIN` ne s'offre qu'à un super-administrateur (2026-10-25). Offert à quiconque
        // détient `user:update`, il laissait un administrateur le cocher sur son propre compte — et le
        // moteur de permissions fait passer un super-administrateur avant toute autre règle. Retiré des
        // `choices`, un POST forgé qui l'envoie échoue sur la validation du `ChoiceType`.
        $roles = true === $options['offer_super_admin']
            ? UserRoles::assignable()
            : array_filter(UserRoles::assignable(), static fn (string $role): bool => UserRoles::ROLE_SUPER_ADMIN !== $role);

        $builder
            // ⚠️ `empty_data => ''` : un champ laissé vide écrivait `null` dans une propriété `string` non
            // nullable — un 500 à l'enregistrement du compte (2026-10-25 ; la leçon de cereezer). Les
            // contraintes ne s'évaluent qu'APRÈS l'écriture dans l'objet.
            ->add('firstName', null, ['empty_data' => '', 'label' => 'user.field.first_name'])
            ->add('lastName', null, ['empty_data' => '', 'label' => 'user.field.last_name'])
            ->add('email', CustomEmailType::class, ['empty_data' => '', 'label' => 'user.field.email'])
            ->add('plainPassword', CustomPasswordType::class, [
                'label' => 'user.field.password',
                'required' => true === $options['require_password'],
                'mapped' => true,
                'help' => true === $options['require_password'] ? null : 'user.help.password_unchanged',
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'user.field.roles',
                'choices' => $roles,
                'multiple' => true,
                'expanded' => true,
                // `ROLE_USER` n'est pas proposé : tout compte l'obtient par la hiérarchie, et
                // l'offrir à cocher laisserait croire qu'on peut le retirer.
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'user.field.active',
                'required' => false,
            ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'data_class' => User::class,
                'translation_domain' => 'user',
                'require_password' => false,
                'offer_super_admin' => false,
            ])
            ->setAllowedTypes('require_password', 'bool')
            ->setAllowedTypes('offer_super_admin', 'bool');
    }
}
