<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Entity\User;
use App\Security\PermissionCodes;
use Jul6Art\AclBundle\Security\PermissionDecisionService;
use Jul6Art\DatatableBundle\DataTable\AbstractDataTableConfigProvider;
use Override;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Ce que la table des comptes affiche, filtre et propose.
 *
 * Les aides plutôt que des tableaux littéraux : chaque libellé passe par le traducteur, et c'est le
 * seul garde-fou contre un en-tête affiché en clé brute — une configuration de datatable n'a pas de
 * sortie attendue, donc aucun test ne l'attrape autrement.
 */
final class UserDataTableConfigProvider extends AbstractDataTableConfigProvider
{
    public function __construct(
        TranslatorInterface $translator,
        private readonly PermissionDecisionService $permissions,
    ) {
        parent::__construct($translator);
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Override]
    public function getColumns(): array
    {
        return [
            // `responsivePriority: 10` sur l'identifiant : c'est la première colonne que la
            // responsive doit masquer. Sans priorité explicite, tout vaut 5 et elle masque dans
            // l'ordre des index — l'ID reste, les colonnes métier disparaissent.
            $this->column('id', 'datatable.col.id', 'datatable', responsivePriority: 10),
            $this->column('fullName', 'user.field.name', 'user', render: 'userNameWithAvatar', responsivePriority: 1),
            $this->column('email', 'user.field.email', 'user', responsivePriority: 2),
            // ⚠️ `readOnlyColumn` et non `column` : `roles` est une colonne JSON, donc NON
            // triable. La déclarer triable ferait ignorer le tri côté serveur sans que rien ne le
            // signale — le piège documenté du mode backoffice.
            $this->readOnlyColumn('roles', 'user.field.roles', 'user', render: 'roleBadges', responsivePriority: 4),
            $this->readOnlyColumn('isActive', 'user.field.status', 'user', render: 'statusBadge', responsivePriority: 3),
            $this->column('createdAt', 'user.field.created_at', 'user', render: 'date', responsivePriority: 6),
            // Offertes sans être montrées : dans le sélecteur, absentes du premier rendu.
            // `fullName` les concatène déjà et se trie désormais aussi (par `ConcatOrderFilter`,
            // sur les deux colonnes réelles) ; celles-ci restent utiles à qui veut trier sur le
            // seul nom de famille, sans le prénom devant.
            $this->column('lastName', 'user.field.family_name', 'user', responsivePriority: 10, hidden: true),
            $this->column('firstName', 'user.field.first_name', 'user', responsivePriority: 10, hidden: true),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Override]
    public function getFilters(): array
    {
        return [
            $this->staticFilter('isActive', 'isActive', 'user.field.status', [
                ['value' => 'true', 'label' => $this->t('datatable.status.active', 'datatable')],
                ['value' => 'false', 'label' => $this->t('datatable.status.inactive', 'datatable')],
            ], 'user'),
            $this->dateRangeFilter('createdAt', 'createdAt', 'user.filter.created', 'user', granularity: 'datetime'),
        ];
    }

    /**
     * Les actions de ligne, gardées côté SERVEUR : une action absente d'ici n'est pas rendue, donc
     * pas cliquable. La route la re-garde de son côté — le rendu conditionnel est de l'ergonomie,
     * pas de la sécurité.
     *
     * @return list<array<string, mixed>>
     */
    public function getActions(User $actor): array
    {
        $notSelf = 'row.id !== '.(int) $actor->getId();
        $actions = [];

        if ($this->permissions->isGranted($actor, PermissionCodes::USER_READ)) {
            $actions[] = $this->linkAction('show', '/admin/users/{id}', 'eye', 'action.show');
        }

        if ($this->permissions->isGranted($actor, PermissionCodes::USER_UPDATE)) {
            $actions[] = $this->linkAction('edit', '/admin/users/{id}/edit', 'pencil', 'action.edit');
        }

        if ($this->permissions->isGranted($actor, PermissionCodes::USER_ACTIVATE)) {
            $actions[] = array_merge(
                $this->postAction('activate', '/admin/users/{id}/activate', 'check-circle', 'action.activate', 'primary'),
                [
                    // `condition` est évaluée par ligne : elle évite d'offrir une action qui
                    // répondrait 422, ce qui est de l'ergonomie et non une garde.
                    'condition' => "!row.isActive && {$notSelf}",
                    'class' => 'text-emerald-600 hover:text-emerald-700',
                    'bulk' => true,
                    'bulkRoute' => '/admin/users/bulk-activate',
                    'bulkLabel' => $this->t('user.action.bulk_activate', 'user'),
                ],
            );

            $actions[] = array_merge(
                $this->postAction('deactivate', '/admin/users/{id}/deactivate', 'ban', 'action.deactivate', 'warning'),
                [
                    'condition' => "row.isActive && {$notSelf}",
                    'class' => 'text-orange-600 hover:text-orange-700',
                    'bulk' => true,
                    'bulkRoute' => '/admin/users/bulk-deactivate',
                    'bulkLabel' => $this->t('user.action.bulk_deactivate', 'user'),
                ],
            );
        }

        if ($this->permissions->isGranted($actor, PermissionCodes::USER_DELETE)) {
            $actions[] = array_merge(
                $this->bulkDeleteAction('/admin/users/{id}/delete', '/admin/users/bulk-delete'),
                ['condition' => $notSelf],
            );
        }

        return $actions;
    }
}
