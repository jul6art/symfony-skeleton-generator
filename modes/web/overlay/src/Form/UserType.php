<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Formulaire d'administration d'un compte.
 *
 * Option `is_new` : à la création le mot de passe est obligatoire, à l'édition
 * il n'est changé que s'il est saisi.
 *
 * @extends AbstractType<User>
 */
final class UserType extends AbstractType
{
    /**
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isNew = (bool) $options['is_new'];

        $builder
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail',
                'attr' => ['autocomplete' => 'off'],
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'Rôles',
                // ROLE_USER est implicite : il est ajouté par User::getRoles().
                'choices' => ['Administrateur' => User::ROLE_ADMIN],
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'help' => 'Tout compte dispose au minimum de ROLE_USER.',
            ])
            ->add('plainPassword', PlainPasswordType::class, [
                'optional' => !$isNew,
                'required' => $isNew,
                'first_options' => [
                    'label' => $isNew ? 'Mot de passe' : 'Nouveau mot de passe',
                    'help' => $isNew ? null : 'Laisser vide pour conserver le mot de passe actuel.',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'data_class' => User::class,
                'is_new' => false,
            ])
            ->setAllowedTypes('is_new', 'bool');
    }
}
