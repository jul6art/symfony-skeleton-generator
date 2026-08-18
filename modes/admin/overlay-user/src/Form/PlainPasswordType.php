<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Champ « mot de passe + confirmation », avec les règles de robustesse maison.
 *
 * Option `optional` : le champ peut rester vide (édition d'un compte existant),
 * les contraintes ne s'appliquent alors que si quelque chose est saisi.
 *
 * @extends AbstractType<string>
 */
final class PlainPasswordType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefault('optional', false)
            ->setAllowedTypes('optional', 'bool')
            ->setDefaults([
                'type' => PasswordType::class,
                // Le mot de passe en clair ne doit jamais être écrit sur l'entité.
                'mapped' => false,
                'invalid_message' => 'password.mismatch',
                'invalid_message_parameters' => [],
                'first_options' => [
                    'label' => 'user.password',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'second_options' => [
                    'label' => 'user.password_confirm',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'constraints' => static function (Options $options): array {
                    $rules = [
                        new Assert\Length(
                            min: 8,
                            max: 4096,
                            minMessage: 'password.too_short',
                        ),
                        new Assert\PasswordStrength(
                            minScore: Assert\PasswordStrength::STRENGTH_MEDIUM,
                            message: 'password.too_weak',
                        ),
                    ];

                    if (true === $options['optional']) {
                        return [new Assert\When(
                            expression: 'value !== null and value !== ""',
                            constraints: $rules,
                        )];
                    }

                    return [new Assert\NotBlank(message: 'password.not_blank'), ...$rules];
                },
            ]);
    }

    public function getParent(): string
    {
        return RepeatedType::class;
    }
}
