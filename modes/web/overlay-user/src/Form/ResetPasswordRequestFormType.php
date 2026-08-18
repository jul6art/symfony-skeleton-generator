<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<array<string, mixed>>
 */
final class ResetPasswordRequestFormType extends AbstractType
{
    /**
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('email', EmailType::class, [
            'label' => 'user.email',
            'attr' => ['autocomplete' => 'email', 'placeholder' => 'vous@exemple.com'],
            'constraints' => [
                new Assert\NotBlank(message: 'user.email.not_blank'),
                new Assert\Email(message: 'user.email.invalid'),
            ],
        ]);
    }
}
