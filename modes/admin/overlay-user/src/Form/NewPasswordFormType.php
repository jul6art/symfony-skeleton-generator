<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Formulaire de fin de parcours « mot de passe oublié » : le compte est déjà
 * identifié par le jeton, il n'y a que le nouveau mot de passe à saisir.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class NewPasswordFormType extends AbstractType
{
    /**
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('plainPassword', PlainPasswordType::class, [
            'first_options' => [
                'label' => 'user.password_new',
                'attr' => ['autocomplete' => 'new-password'],
            ],
        ]);
    }
}
