<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Jul6Art\AuthBundle\Entity\User;
use Jul6Art\AuthBundle\Repository\Interfaces\UserRepositoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Point d'entrée du back-office.
 *
 * La palette d'EasyAdmin est alignée sur celle du front dans
 * assets/styles/admin.css : le back-office doit ressembler au site.
 */
#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
#[IsGranted(User::ROLE_ADMIN)]
final class DashboardController extends AbstractDashboardController
{
    public function __construct(private readonly UserRepositoryInterface $users)
    {
    }

    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'user_count' => $this->users->count([]),
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('{{PROJECT_TITLE}}')
            ->setFaviconPath('favicon.ico')
            ->renderContentMaximized();
    }

    public function configureAssets(): Assets
    {
        return Assets::new()->addAssetMapperEntry('admin');
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        return parent::configureUserMenu($user)
            ->setName($user->getUserIdentifier())
            ->addMenuItems([
                MenuItem::linkToRoute('profile.title', 'fa fa-user', 'app_profile'),
                MenuItem::linkToRoute('profile.password.title', 'fa fa-key', 'app_change_password'),
            ]);
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('admin.dashboard', 'fa fa-gauge');

        yield MenuItem::section('admin.section.accounts');
        yield MenuItem::linkTo(UserCrudController::class, 'nav.users', 'fa fa-users');

        yield MenuItem::section('admin.section.site');
        yield MenuItem::linkToRoute('admin.back_to_site', 'fa fa-arrow-left', 'app_home');
    }
}
