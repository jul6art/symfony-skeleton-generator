<?php

declare(strict_types=1);

namespace App\Form;

use Jul6Art\UiBundle\Form\Type\CustomEmailType;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * La demande de réinitialisation : une adresse, rien d'autre.
 *
 * Pas de `data_class` : ce formulaire n'écrit sur aucune entité, et lui en donner une le lierait à
 * un compte dont on ne veut justement pas dire s'il existe.
 *
 * @extends AbstractType<array{email: string}>
 */
final class ResetPasswordRequestFormType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('email', CustomEmailType::class, [
            'label' => 'security.reset_password.request.email',
            'constraints' => [
                new Assert\NotBlank(message: 'security.email.not_blank'),
                new Assert\Email(message: 'security.email.invalid'),
            ],
        ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => 'security']);
    }
}
