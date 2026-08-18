<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Event\UserEvent;
use App\Form\UserType;
use Jul6Art\AuthBundle\Entity\User;
use Jul6Art\AuthBundle\Factory\UserFactory;
use Jul6Art\AuthBundle\Manager\Interfaces\UserManagerInterface;
use Jul6Art\AuthBundle\Repository\Interfaces\UserRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

use function is_string;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly UserManagerInterface $userManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'app_admin_user_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/user/index.html.twig', [
            'users' => $this->users->findBy([], ['email' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'app_admin_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = UserFactory::create();
        $form = $this->createForm(UserType::class, $user, ['is_new' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

            $this->userManager->save($user);
            $this->eventDispatcher->dispatch(new UserEvent($user), UserEvent::CREATED);

            $this->addFlash('success', $this->translator->trans('flash.account.admin_created', ['%email%' => (string) $user->getEmail()]));

            return $this->redirectToRoute('app_admin_user_index');
        }

        return $this->render('admin/user/new.html.twig', ['user' => $user, 'form' => $form]);
    }

    #[Route('/{id}', name: 'app_admin_user_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(User $user): Response
    {
        return $this->render('admin/user/show.html.twig', ['user' => $user]);
    }

    #[Route('/{id}/edit', name: 'app_admin_user_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, User $user): Response
    {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            if (is_string($plainPassword) && '' !== $plainPassword) {
                $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
            }

            $this->userManager->save($user);
            $this->eventDispatcher->dispatch(new UserEvent($user), UserEvent::EDITED);

            $this->addFlash('success', $this->translator->trans('flash.account.updated', ['%email%' => (string) $user->getEmail()]));

            return $this->redirectToRoute('app_admin_user_index');
        }

        return $this->render('admin/user/edit.html.twig', ['user' => $user, 'form' => $form]);
    }

    #[Route('/{id}/delete', name: 'app_admin_user_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, User $user): Response
    {
        if ($user === $this->getUser()) {
            $this->addFlash('error', 'flash.account.self_delete');

            return $this->redirectToRoute('app_admin_user_index');
        }

        if (!$this->isCsrfTokenValid('delete-user-'.(string) $user->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'flash.csrf');

            return $this->redirectToRoute('app_admin_user_index');
        }

        $this->eventDispatcher->dispatch(new UserEvent($user), UserEvent::DELETED);
        $this->userManager->delete($user);

        $this->addFlash('success', 'flash.account.deleted');

        return $this->redirectToRoute('app_admin_user_index');
    }
}
