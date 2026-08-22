<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Inscription publique.
 *
 * Fermable par `APP_REGISTRATION_ENABLED=0` : la route répond alors 404 — et pas 403, qui
 * confirmerait qu'elle existe. Le lien disparaît aussi de la carte de connexion, puisqu'il vient de
 * `admin.routes.register` : vider cette clé suffit à retirer l'entrée sans toucher au gabarit.
 *
 * ⚠️ Un compte créé ici arrive **inactif**. C'est le comportement par défaut assumé d'un
 * back-office : quelqu'un valide. Pour ouvrir l'inscription en libre-service, mettre
 * `setIsActive(true)` ici — et savoir que c'est ce qu'on fait.
 */
final class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly bool $registrationEnabled,
    ) {
    }

    #[Route('/register', name: 'admin_security_register', methods: ['GET', 'POST'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function __invoke(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $hasher,
    ): Response {
        if (!$this->registrationEnabled) {
            throw new NotFoundHttpException();
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user
                ->setPassword($hasher->hashPassword($user, (string) $user->getPlainPassword()))
                ->setIsActive(false);
            $user->eraseCredentials();

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'security.register.flash.pending');

            return new RedirectResponse($this->generateUrl('admin_security_login'));
        }

        return $this->render('@Admin/security/register.html.twig', ['form' => $form]);
    }
}
