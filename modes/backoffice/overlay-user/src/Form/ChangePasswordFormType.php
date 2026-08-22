<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Validator\Constraints as SecurityAssert;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Changement de son propre mot de passe.
 *
 * ⚠️ L'ancien mot de passe est exigé et vérifié par `UserPassword` — pas par courtoisie : sans lui,
 * une session volée suffit à s'approprier définitivement le compte.
 *
 * @extends AbstractType<array{currentPassword: string, plainPassword: string}>
 */
final class ChangePasswordFormType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => 'security.change_password.current',
                'mapped' => false,
                'constraints' => [
                    new Assert\NotBlank(message: 'security.password.not_blank'),
                    new SecurityAssert\UserPassword(message: 'security.change_password.wrong_current'),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'security.register.password_mismatch',
                'first_options' => ['label' => 'security.change_password.new'],
                'second_options' => ['label' => 'security.change_password.confirm'],
                'constraints' => [
                    new Assert\NotBlank(message: 'security.password.not_blank'),
                    new Assert\Length(min: 12, max: 4096, minMessage: 'security.password.too_short'),
                ],
            ]);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults(['translation_domain' => 'security'])
            ->setRequired('current_user')
            ->setAllowedTypes('current_user', User::class);
    }
}
