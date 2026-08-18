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
                'label' => 'profile.password.current',
                'mapped' => false,
                'attr' => ['autocomplete' => 'current-password'],
                'constraints' => [
                    new Assert\NotBlank(message: 'password.not_blank'),
                    new SecurityAssert\UserPassword(message: 'password.current_invalid'),
                ],
            ])
            ->add('plainPassword', PlainPasswordType::class, [
                'first_options' => [
                    'label' => 'profile.password.new',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
            ]);
    }
}
