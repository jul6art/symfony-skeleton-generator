<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\ChangePasswordFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Changement de son propre mot de passe.
 *
 * Aucun code de permission : un compte gère toujours le sien. `IS_AUTHENTICATED_FULLY` et non
 * `IS_AUTHENTICATED_REMEMBERED` — changer un mot de passe depuis une session ressuscitée par un
 * cookie « se souvenir de moi » est exactement ce qu'un vol de cookie exploite.
 */
#[Route('/admin/account/password', name: 'admin_account_password_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class AccountPasswordController extends AbstractController
{
    #[Route('', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $hasher,
        #[CurrentUser] User $user,
    ): Response {
        $form = $this->createForm(ChangePasswordFormType::class, null, ['current_user' => $user]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{plainPassword?: string} $data */
            $data = $form->getData();
            $user->setPassword($hasher->hashPassword($user, (string) ($data['plainPassword'] ?? '')));
            $entityManager->flush();

            $this->addFlash('success', 'security.change_password.flash.changed');

            return $this->redirectToRoute('admin_account_password_edit');
        }

        return $this->render('admin/account/password.html.twig', ['form' => $form]);
    }
}
