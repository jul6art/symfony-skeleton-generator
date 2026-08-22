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
    // Exemple à garder ou à remplacer :
    //
    // quoteStatusBadge: badge('datatable.quote_status', {
    //     draft: 'slate', sent: 'sky', accepted: 'emerald', rejected: 'red',
    // }),
});
