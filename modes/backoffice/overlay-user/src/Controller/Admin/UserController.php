<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\DataTable\UserDataTableConfigProvider;
use App\Entity\User;
use App\Form\UserType;
use App\Security\PermissionCodes;
use Doctrine\ORM\EntityManagerInterface;
use Jul6Art\CoreBundle\Controller\BulkActionRunner;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Le CRUD des comptes.
 *
 * La liste est une datatable : elle lit `GET /api/users`, donc la pagination, le tri, la recherche
 * et les filtres sont ceux d'API Platform. Ce contrôleur ne rend que la coquille et la
 * configuration — il ne charge aucune ligne.
 *
 * ## Les trois gardes, dans cet ordre
 *
 * 1. `#[IsGranted(PermissionCodes::…)]` sur la MÉTHODE. `IS_AUTHENTICATED_FULLY` de classe ne prouve
 *    que la connexion.
 * 2. Le même attribut sur les routes `/bulk-*`, au niveau AGRÉGAT : la boucle par entité est la
 *    seconde garde, pas la première — une route de masse doit échouer vite.
 * 3. Le jeton CSRF, `datatable_action` par ligne et `bulk_action` en masse, aux identifiants que
 *    `config/packages/datatable.yaml` déclare.
 */
#[Route('/admin/users', name: 'admin_user_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserDataTableConfigProvider $dataTable,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    #[IsGranted(PermissionCodes::USER_READ)]
    public function index(#[CurrentUser] User $actor): Response
    {
        return $this->render('admin/user/index.html.twig', [
            'columns_config' => $this->dataTable->getColumns(),
            'filters_config' => $this->dataTable->getFilters(),
            'actions_config' => $this->dataTable->getActions($actor),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    #[IsGranted(PermissionCodes::USER_CREATE)]
    public function new(Request $request, UserPasswordHasherInterface $hasher): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user, ['require_password' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($hasher->hashPassword($user, (string) $user->getPlainPassword()));
            $user->eraseCredentials();
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->addFlash('success', 'user.flash.created');

            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/user/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(PermissionCodes::USER_READ)]
    public function show(User $user): Response
    {
        return $this->render('admin/user/show.html.twig', ['user' => $user]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(PermissionCodes::USER_UPDATE)]
    public function edit(Request $request, User $user, UserPasswordHasherInterface $hasher): Response
    {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Mot de passe laissé vide = inchangé. Le hacher quand même écraserait le compte avec
            // le hachage d'une chaîne vide, ce qui se voit à la connexion suivante et pas avant.
            if (null !== $user->getPlainPassword() && '' !== $user->getPlainPassword()) {
                $user->setPassword($hasher->hashPassword($user, $user->getPlainPassword()));
            }
            $user->eraseCredentials();
            $this->entityManager->flush();

            $this->addFlash('success', 'user.flash.updated');

            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/user/edit.html.twig', ['form' => $form, 'user' => $user]);
    }

    #[Route('/{id}/activate', name: 'activate', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(PermissionCodes::USER_ACTIVATE)]
    public function activate(Request $request, User $user): Response
    {
        return $this->toggle($request, $user, true);
    }

    #[Route('/{id}/deactivate', name: 'deactivate', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(PermissionCodes::USER_ACTIVATE)]
    public function deactivate(Request $request, User $user): Response
    {
        return $this->toggle($request, $user, false);
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(PermissionCodes::USER_DELETE)]
    public function delete(Request $request, User $user, #[CurrentUser] User $actor): Response
    {
        if (!$this->isCsrfTokenValid('datatable_action', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'flash.invalid_csrf');

            return $this->redirectToRoute('admin_user_index');
        }

        if ($user->getId() === $actor->getId()) {
            $this->addFlash('error', 'user.flash.cannot_delete_self');

            return $this->redirectToRoute('admin_user_index');
        }

        // Suppression douce : la ligne reste, `deletedAt` est posé. La colonne UNIQUE `email` est
        // libérée pour que l'adresse soit réutilisable — sans ça, recréer un compte supprimé
        // échoue sur une contrainte, ce qui est incompréhensible côté écran.
        $user->softDelete();
        $user->setEmail(\Jul6Art\CoreBundle\Util\Strings::markDeleted($user->getEmail()));
        $this->entityManager->flush();

        $this->addFlash('success', 'user.flash.deleted');

        return $this->redirectToRoute('admin_user_index');
    }

    #[Route('/bulk-activate', name: 'bulk_activate', methods: ['POST'])]
    #[IsGranted(PermissionCodes::USER_ACTIVATE)]
    public function bulkActivate(Request $request, BulkActionRunner $runner): Response
    {
        return $this->runBulk($request, $runner, true);
    }

    #[Route('/bulk-deactivate', name: 'bulk_deactivate', methods: ['POST'])]
    #[IsGranted(PermissionCodes::USER_ACTIVATE)]
    public function bulkDeactivate(Request $request, BulkActionRunner $runner): Response
    {
        return $this->runBulk($request, $runner, false);
    }

    private function toggle(Request $request, User $user, bool $active): Response
    {
        if (!$this->isCsrfTokenValid('datatable_action', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'flash.invalid_csrf');

            return $this->redirectToRoute('admin_user_index');
        }

        $user->setIsActive($active);
        $this->entityManager->flush();

        $this->addFlash('success', $active ? 'user.flash.activated' : 'user.flash.deactivated');

        return $this->redirectToRoute('admin_user_index');
    }

    /**
     * Le lanceur du core-bundle fait tout ce qu'une route de masse doit faire, et dans cet ordre :
     * le jeton CSRF, la lecture des `ids[]`, UN chargement pour toutes les lignes, le voter ligne
     * par ligne, une seule transaction. Écrire la boucle à la main, c'est en oublier un.
     */
    private function runBulk(Request $request, BulkActionRunner $runner, bool $active): Response
    {
        $runner->run(
            $request,
            User::class,
            PermissionCodes::USER_ACTIVATE,
            static fn (User $user): User => $user->setIsActive($active),
        );

        // La clé, pas une phrase : le partial des toasts la passe au traducteur. Un contrôleur qui
        // pose du texte tout fait marche — et casse le jour où l'application gagne une langue.
        $this->addFlash('success', $active ? 'user.flash.activated' : 'user.flash.deactivated');

        return $this->redirectToRoute('admin_user_index');
    }
}
