<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use App\Security\UserRoles;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Jul6Art\UiBundle\Form\Type\CustomEmailType;
use Jul6Art\UiBundle\Form\Type\CustomPasswordType;

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
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', null, ['label' => 'user.field.first_name'])
            ->add('lastName', null, ['label' => 'user.field.last_name'])
            ->add('email', CustomEmailType::class, ['label' => 'user.field.email'])
            ->add('plainPassword', CustomPasswordType::class, [
                'label' => 'user.field.password',
                'required' => true === $options['require_password'],
                'mapped' => true,
                'help' => true === $options['require_password'] ? null : 'user.help.password_unchanged',
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'user.field.roles',
                'choices' => UserRoles::assignable(),
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

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'data_class' => User::class,
                'translation_domain' => 'user',
                'require_password' => false,
            ])
            ->setAllowedTypes('require_password', 'bool');
    }
}
