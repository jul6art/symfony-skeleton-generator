<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\ProfileFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Le profil du compte connecté : son identité affichée et son avatar.
 *
 * Aucun code de permission : un compte gère toujours le sien. C'est aussi pourquoi ce contrôleur
 * ne touche NI à l'e-mail NI aux rôles — cf. `ProfileFormType`.
 */
#[Route('/admin/account/profile', name: 'admin_account_profile_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class AccountProfileController extends AbstractController
{
    public function __construct(
        private readonly string $avatarDirectory,
    ) {
    }

    #[Route('', name: 'edit', methods: ['GET', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function edit(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        #[CurrentUser]
        User $user,
    ): Response {
        $form = $this->createForm(ProfileFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uploaded = $form->get('avatarFile')->getData();

            if ($uploaded instanceof UploadedFile) {
                // Nom reconstruit, jamais celui du client : un nom d'origine porte le chemin et
                // l'extension que l'envoyeur a choisis, et `guessExtension()` lit le contenu.
                $name = $slugger->slug(pathinfo($uploaded->getClientOriginalName(), \PATHINFO_FILENAME))
                    ->lower()->truncate(40)->toString();
                $filename = sprintf('%s-%s.%s', $name ?: 'avatar', bin2hex(random_bytes(8)), $uploaded->guessExtension() ?? 'bin');

                try {
                    $uploaded->move($this->avatarDirectory, $filename);
                } catch (FileException) {
                    $this->addFlash('error', 'user.profile.flash.upload_failed');

                    return $this->redirectToRoute('admin_account_profile_edit');
                }

                $user->setAvatarPath('uploads/avatars/'.$filename);
            }

            $entityManager->flush();
            $this->addFlash('success', 'user.profile.flash.saved');

            return $this->redirectToRoute('admin_account_profile_edit');
        }

        return $this->render('admin/account/profile.html.twig', ['form' => $form]);
    }
}
