<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\NewPasswordFormType;
use App\Form\ResetPasswordRequestFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

/**
 * Mot de passe oublié, en deux temps : une demande par adresse, puis un formulaire derrière un
 * jeton à usage unique.
 *
 * ⚠️ **La réponse ne doit jamais dire si l'adresse existe.** Les deux cas mènent à la même page de
 * confirmation, avec le même texte : un message différent transformerait ce formulaire en oracle
 * d'énumération de comptes. C'est aussi pourquoi l'exception du helper est avalée.
 */
final class ResetPasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $mailerFrom,
    ) {
    }

    #[Route('/reset-password', name: 'admin_reset_password_request', methods: ['GET', 'POST'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function request(Request $request, MailerInterface $mailer): Response
    {
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{email?: string} $data */
            $data = $form->getData();

            return $this->sendEmail($data['email'] ?? '', $mailer);
        }

        return $this->render('@Admin/security/reset_password_request.html.twig', ['form' => $form]);
    }

    #[Route('/reset-password/check-email', name: 'admin_reset_password_check_email', methods: ['GET'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function checkEmail(): Response
    {
        // Un jeton factice quand il n'y en a pas : la page se rend, sans dire si un envoi a eu lieu.
        if (null === $this->getTokenObjectFromSession()) {
            $this->resetPasswordHelper->generateFakeResetToken();
        }

        return $this->render('@Admin/security/check_email.html.twig');
    }

    #[Route('/reset-password/reset/{token}', name: 'admin_reset_password_reset', methods: ['GET', 'POST'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function reset(Request $request, UserPasswordHasherInterface $hasher, ?string $token = null): Response
    {
        if (null !== $token) {
            // Le jeton passe en session et sort de l'URL : sans ça il part dans le `Referer` de la
            // première ressource externe chargée par la page.
            $this->storeTokenInSession($token);

            return $this->redirectToRoute('admin_reset_password_reset');
        }

        $token = $this->getTokenFromSession();
        if (null === $token) {
            throw $this->createNotFoundException('Aucun jeton de réinitialisation en session.');
        }

        try {
            /** @var User $user */
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface $e) {
            $this->addFlash('error', 'security.reset_password.flash.invalid_token');

            return $this->redirectToRoute('admin_reset_password_request');
        }

        $form = $this->createForm(NewPasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->resetPasswordHelper->removeResetRequest($token);

            /** @var array{plainPassword?: string} $data */
            $data = $form->getData();
            $user->setPassword($hasher->hashPassword($user, (string) ($data['plainPassword'] ?? '')));
            $this->entityManager->flush();

            $this->cleanSessionAfterReset();
            $this->addFlash('success', 'security.reset_password.flash.changed');

            return $this->redirectToRoute('admin_security_login');
        }

        return $this->render('@Admin/security/reset_password.html.twig', ['form' => $form]);
    }

    private function sendEmail(string $email, MailerInterface $mailer): RedirectResponse
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => mb_strtolower(trim($email))]);

        // Adresse inconnue : on redirige exactement comme pour une adresse connue. Le silence est
        // la fonctionnalité.
        if (null !== $user) {
            try {
                $resetToken = $this->resetPasswordHelper->generateResetToken($user);

                $mailer->send(
                    new TemplatedEmail()
                        ->from(new Address($this->mailerFrom))
                        ->to((string) $user->getEmail())
                        ->subject('security.reset_password.email.subject')
                        ->htmlTemplate('security/reset_password_email.html.twig')
                        ->context(['resetToken' => $resetToken]),
                );

                $this->setTokenObjectInSession($resetToken);
            } catch (ResetPasswordExceptionInterface) {
                // Trop de demandes, ou une demande encore valide : même réponse, toujours.
            }
        }

        return $this->redirectToRoute('admin_reset_password_check_email');
    }
}
