import { registerRenderers, badge } from '@jul6art/datatable-bundle/renderers';

/**
 * Les rendus de cellule propres à ce projet.
 *
 * Le bundle en livre vingt, génériques : dates, nombres, IRI, badge actif/inactif, avatar, lien.
 * Tout ce qui nomme une entité ou une énumération métier vient ici — `draft / sent / accepted`
 * est le vocabulaire d'un domaine, pas de l'infrastructure.
 *
 * Les libellés que `badge()` lit sont posés par `datatable_status_map()`, dont la table est dans
 * `config/packages/datatable.yaml`. Les deux vont par paires : `badge('datatable.foo_status', …)`
 * lit ce que l'entrée `foo_status` a écrit.
 */
registerRenderers({
    /**
     * Les rôles d'un compte, en pastilles.
     *
     * ⚠️ Un rendu est CURRIFIÉ : `(contexte) => (valeur) => html`. Le contexte porte le
     * traducteur (`c.t()`), et c'est lui qu'on reçoit en premier — pas la valeur de la cellule.
     * Appeler `badge(…)(valeur)` à plat rend donc une FONCTION là où DataTables attend une
     * chaîne : `TypeError: i is not a function` dans le `drawCallback`, qui avorte le dessin
     * ENTIER de la table — la ligne de filtres avec. Une colonne vide et des filtres disparus
     * pour une seule parenthèse mal placée (vu à l'écran le 2026-08-24).
     *
     * ⚠️ `ROLE_USER` est retiré : tout compte l'obtient par la hiérarchie déclarée dans
     * `security.yaml`, personne ne peut le retirer, et l'afficher laisse croire le contraire.
     * Un compte ordinaire affiche donc « — » plutôt qu'une cellule vide.
     */
    roleBadges: (c) => {
        const one = badge('datatable.user_role', {
            ROLE_ADMIN: 'violet',
            ROLE_SUPER_ADMIN: 'amber',
        })(c);

        return (data) => {
            const roles = (Array.isArray(data) ? data : []).filter((role) => role !== 'ROLE_USER');

            if (roles.length === 0) {
                return '<span class="text-slate-400">&mdash;</span>';
            }

            return roles.map(one).join(' ');
        };
    },
});
