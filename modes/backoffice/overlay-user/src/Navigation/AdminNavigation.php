<?php

declare(strict_types=1);

namespace App\Navigation;

use App\Security\PermissionCodes;
use Jul6Art\AdminBundle\Navigation\NavigationProviderInterface;
use Jul6Art\AdminBundle\Navigation\NavItem;
use Jul6Art\AdminBundle\Navigation\NavSection;

/**
 * Le menu de la barre latérale.
 *
 * Un fournisseur par module plutôt qu'une grande liste : un module retiré emporte son menu, un
 * module ajouté n'oblige pas à éditer un fichier qu'il ne possède pas. `AdminBundle` autoconfigure
 * le tag — déclarer la classe en service suffit.
 *
 * ⚠️ La garde de chaque entrée est ICI, à côté du lien, et pas répétée en `{% if is_granted %}`
 * dans le gabarit. C'est la dérive entre les deux qui produit un lien visible répondant 403 —
 * un défaut d'interface qu'aucun test de contrôleur ne voit, puisque le contrôleur a raison.
 */
final class AdminNavigation implements NavigationProviderInterface
{
    #[\Override]
    public function sections(): iterable
    {
        yield new NavSection('admin.main', 'nav.section.main', 'fa-solid fa-gauge-high', [
            new NavItem('admin_dashboard', 'nav.dashboard', 'fa-solid fa-house'),
        ], priority: 100);

        yield new NavSection('admin.access', 'nav.section.access', 'fa-solid fa-shield-halved', [
            new NavItem('admin_user_index', 'nav.users', 'fa-solid fa-users', permission: PermissionCodes::USER_READ),
            new NavItem('admin_role_permission_index', 'nav.role_permissions', 'fa-solid fa-key', permission: PermissionCodes::PERMISSION_READ),
        ], priority: 90);
    }
}
