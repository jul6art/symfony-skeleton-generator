<?php

declare(strict_types=1);

namespace App\Controller;

use App\Event\UserEvent;
use App\Form\ChangePasswordFormType;
use App\Security\Voter\UserVoter;
use Jul6Art\AuthBundle\Entity\User;
use Jul6Art\AuthBundle\Manager\Interfaces\UserManagerInterface;
use Jul6Art\CoreBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

// Le rôle ferme la porte, le voter décide action par action : les deux, pas l'un
// à la place de l'autre.
#[Route('/profile')]
#[IsGranted(User::ROLE_USER)]
final class ProfileController extends AbstractController
{
    public function __construct(
        private readonly UserManagerInterface $userManager,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[Route('', name: 'app_profile', methods: ['GET'])]
    public function show(): Response
    {
        $this->denyAccessUnlessGranted(UserVoter::VIEW, $this->getUser());

        return $this->render('profile/show.html.twig');
    }

    #[Route('/password', name: 'app_change_password', methods: ['GET', 'POST'])]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        Security $security,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $this->denyAccessUnlessGranted(UserVoter::EDIT, $user);

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));

            $this->userManager->save($user);
            $this->eventDispatcher->dispatch(new UserEvent($user), UserEvent::EDITED);

            $this->addSuccessFlash('flash.password.changed');

            // Le jeton de session porte l'ancien hash : on le régénère pour
            // éviter une déconnexion au prochain appel.
            $security->login($user, 'form_login');

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/change_password.html.twig', ['changePasswordForm' => $form]);
    }
}
