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
                'invalid_message' => 'Les deux mots de passe doivent être identiques.',
                'first_options' => [
                    'label' => 'Mot de passe',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'second_options' => [
                    'label' => 'Confirmer le mot de passe',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'constraints' => static function (Options $options): array {
                    $rules = [
                        new Assert\Length(
                            min: 8,
                            max: 4096,
                            minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
                        ),
                        new Assert\PasswordStrength(
                            minScore: Assert\PasswordStrength::STRENGTH_MEDIUM,
                            message: 'Ce mot de passe est trop faible : allongez-le ou variez les caractères.',
                        ),
                    ];

                    if (true === $options['optional']) {
                        return [new Assert\When(
                            expression: 'value !== null and value !== ""',
                            constraints: $rules,
                        )];
                    }

                    return [new Assert\NotBlank(message: 'Merci de saisir un mot de passe.'), ...$rules];
                },
            ]);
    }

    public function getParent(): string
    {
        return RepeatedType::class;
    }
}
