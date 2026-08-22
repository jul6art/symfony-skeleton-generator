<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Acl\DoctrinePermissionStore;
use App\Repository\RolePermissionRepository;
use App\Security\DefaultRolePermissions;
use App\Security\PermissionCodes;
use App\Security\UserRoles;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function in_array;

/**
 * Qui a le droit de faire quoi : une grille rôles × permissions.
 *
 * `ROLE_SUPER_ADMIN` n'y figure pas, à dessein — le moteur le laisse passer avant de regarder le
 * stockage, donc décocher une case ne lui retirerait rien, mais laisserait croire le contraire. Et
 * si l'on rendait ses permissions réellement retirables, on pourrait s'enfermer dehors.
 */
#[Route('/admin/role-permissions', name: 'admin_role_permission_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class RolePermissionController extends AbstractController
{
    /** Les rôles que la grille présente. Le super-admin en est absent : voir le docblock. */
    private const array EDITABLE_ROLES = [UserRoles::ROLE_ADMIN, UserRoles::ROLE_USER];

    public function __construct(
        private readonly RolePermissionRepository $rolePermissions,
        private readonly DoctrinePermissionStore $store,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    #[IsGranted(PermissionCodes::PERMISSION_READ)]
    public function index(): Response
    {
        return $this->render('admin/role_permission/index.html.twig', [
            'roles' => self::EDITABLE_ROLES,
            'grouped' => PermissionCodes::grouped(),
            'granted' => $this->grantedMatrix(),
            'defaults' => DefaultRolePermissions::map(),
        ]);
    }

    #[Route('', name: 'save', methods: ['POST'])]
    #[IsGranted(PermissionCodes::PERMISSION_UPDATE)]
    public function save(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('role_permissions', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'flash.invalid_csrf');

            return $this->redirectToRoute('admin_role_permission_index');
        }

        /** @var array<string, array<string, string>> $submitted */
        $submitted = $request->request->all('permissions');

        // Une seule transaction pour toute la grille : cinquante cases ne doivent pas produire
        // cinquante écritures indépendantes, dont la moitié survivrait à une erreur au milieu.
        foreach (self::EDITABLE_ROLES as $role) {
            $checked = array_keys($submitted[$role] ?? []);
            foreach (PermissionCodes::all() as $permission) {
                in_array($permission, $checked, true)
                    ? $this->store->grantToRole($role, $permission, null)
                    : $this->store->revokeFromRole($role, $permission, null);
            }
        }

        $this->entityManager->flush();
        $this->addFlash('success', 'permission.flash.saved');

        return $this->redirectToRoute('admin_role_permission_index');
    }

    /**
     * @return array<string, list<string>>
     */
    private function grantedMatrix(): array
    {
        $matrix = [];
        foreach (self::EDITABLE_ROLES as $role) {
            $matrix[$role] = $this->rolePermissions->findGrantedForRoles([$role]);
        }

        return $matrix;
    }
}
