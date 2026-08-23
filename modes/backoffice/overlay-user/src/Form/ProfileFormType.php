<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Le profil : ce qu'un compte corrige lui-même, et rien d'autre.
 *
 * ⚠️ Ni `email` ni `roles` ni `isActive` : les changer relève de l'administration des comptes
 * (`UserType`, gardé par `user:update`). Les exposer ici donnerait à n'importe quel compte le
 * moyen de se renommer en une autre identité de connexion, ou de s'accorder un rôle.
 *
 * @extends AbstractType<User>
 */
final class ProfileFormType extends AbstractType
{
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'profile.first_name',
                'constraints' => [new Assert\Length(max: 100)],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'profile.last_name',
                'constraints' => [new Assert\Length(max: 100)],
            ])
            // `mapped: false` : le champ porte un fichier téléversé, l'entité une CHAÎNE de
            // chemin. C'est le contrôleur qui fait la conversion, après validation.
            ->add('avatarFile', FileType::class, [
                'label' => 'profile.avatar',
                'help' => 'profile.avatar_help',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Assert\Image(
                        maxSize: '2M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'profile.avatar_mime',
                        maxSizeMessage: 'profile.avatar_too_large',
                    ),
                ],
                'attr' => ['accept' => 'image/jpeg,image/png,image/webp'],
            ]);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'translation_domain' => 'user',
        ]);
    }

    /** Utilisé par le contrôleur pour lire le fichier téléversé sans le remapper. */
    public static function uploadedAvatar(mixed $data): ?File
    {
        return $data instanceof File ? $data : null;
    }
}
