<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Jul6Art\UiBundle\Form\Type\CustomEmailType;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Inscription publique.
 *
 * Les contraintes du mot de passe sont ICI et pas sur l'entité : `plainPassword` est transitoire,
 * et un `Assert` sur une propriété non mappée ne serait évalué que par les formulaires qui la
 * portent — ce qui est le cas, mais rend la règle invisible depuis l'entité. Les mettre au
 * formulaire dit où elles s'appliquent.
 *
 * @extends AbstractType<User>
 */
final class RegistrationFormType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', null, ['label' => 'security.register.first_name'])
            ->add('lastName', null, ['label' => 'security.register.last_name'])
            ->add('email', CustomEmailType::class, ['label' => 'security.register.email'])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => true,
                // ⚠️ Une clé de traduction, jamais la phrase anglaise par défaut de Symfony.
                'invalid_message' => 'password.mismatch',
                'first_options' => ['label' => 'security.register.password'],
                'second_options' => ['label' => 'security.register.password_confirm'],
                'constraints' => [
                    new Assert\NotBlank(message: 'password.not_blank'),
                    new Assert\Length(min: 12, max: 4096, minMessage: 'password.too_short'),
                ],
            ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'translation_domain' => 'security',
        ]);
    }
}
