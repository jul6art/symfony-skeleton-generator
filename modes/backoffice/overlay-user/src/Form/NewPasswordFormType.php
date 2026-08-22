<?php

declare(strict_types=1);

namespace App\Form;

use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Le nouveau mot de passe, derrière un jeton à usage unique.
 *
 * @extends AbstractType<array{plainPassword: string}>
 */
final class NewPasswordFormType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('plainPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'invalid_message' => 'security.register.password_mismatch',
            'first_options' => ['label' => 'security.reset_password.reset.password'],
            'second_options' => ['label' => 'security.reset_password.reset.password_confirm'],
            'constraints' => [
                new Assert\NotBlank(message: 'security.password.not_blank'),
                new Assert\Length(min: 12, max: 4096, minMessage: 'security.password.too_short'),
            ],
        ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => 'security']);
    }
}
