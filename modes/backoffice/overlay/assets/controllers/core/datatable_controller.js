/**
 * Le contrôleur de datatable vit dans `jul6art/datatable-bundle`.
 *
 * Ce fichier-relais existe pour son CHEMIN : `bootstrap.js` en dérive l'identifiant Stimulus
 * `core--datatable`, celui que `datatable.stimulus_identifier` déclare côté Twig. Les deux doivent
 * dire la même chose — sinon les gabarits écrivent des attributs que le contrôleur ne lit pas, et
 * un tableau vide s'affiche sans la moindre erreur.
 */
export { default } from '@jul6art/datatable-bundle/controllers/datatable_controller';
