<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Validator\Constraints as SecurityAssert;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Changement de mot de passe par l'utilisateur connecté : le mot de passe
 * courant est exigé pour éviter le détournement d'une session ouverte.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class ChangePasswordFormType extends AbstractType
{
    /**
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Mot de passe actuel',
                'mapped' => false,
                'attr' => ['autocomplete' => 'current-password'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Merci de saisir votre mot de passe actuel.'),
                    new SecurityAssert\UserPassword(message: 'Ce mot de passe ne correspond pas à votre mot de passe actuel.'),
                ],
            ])
            ->add('plainPassword', PlainPasswordType::class, [
                'first_options' => [
                    'label' => 'Nouveau mot de passe',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
            ]);
    }
}
