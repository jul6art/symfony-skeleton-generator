<?php

declare(strict_types=1);

namespace App\Controller;

use App\Event\UserEvent;
use App\Form\RegistrationFormType;
use Jul6Art\AuthBundle\Factory\UserFactory;
use Jul6Art\AuthBundle\Manager\Interfaces\UserManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class RegistrationController extends AbstractController
{
    public function __construct(
        #[Autowire('%app.registration_enabled%')]
        private readonly bool $registrationEnabled,
        private readonly UserManagerInterface $userManager,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function __invoke(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        Security $security,
    ): Response {
        // Inscription fermée : la route n'existe pas, plutôt qu'un 403 qui
        // confirmerait qu'il y a quelque chose derrière.
        if (!$this->registrationEnabled) {
            throw $this->createNotFoundException('L\'inscription publique est désactivée.');
        }

        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        // Les comptes se créent par la fabrique du auth-bundle, jamais avec new.
        $user = UserFactory::create();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));

            $this->userManager->save($user);
            $this->eventDispatcher->dispatch(new UserEvent($user), UserEvent::CREATED);

            $this->addFlash('success', 'flash.account.created');

            // Connexion immédiate ; l'authentificateur est nommé explicitement
            // car le pare-feu en compte plusieurs (form_login + remember_me).
            return $security->login($user, 'form_login') ?? $this->redirectToRoute('app_home');
        }

        return $this->render(
            'registration/register.html.twig',
            ['registrationForm' => $form],
            // Turbo attend un 4xx quand un formulaire est renvoyé en erreur.
            new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK),
        );
    }
}
