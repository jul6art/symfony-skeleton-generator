/**
 * Ré-export du contrôleur `dropzone` de `jul6art/admin-bundle` (identifiant Stimulus
 * `form--dropzone`, donné par le CHEMIN de ce fichier).
 *
 * Le bundle publie les styles `.dropzone-*` ET, depuis la v1.3.0, le comportement qui les anime.
 * Sans ce ré-export, la zone se dessine et le clic n'ouvre que le sélecteur natif : l'aperçu et
 * le nom du fichier ne s'affichent jamais, sans qu'aucune erreur ne le dise.
 */
export { default } from '@jul6art/admin-bundle/controllers/dropzone_controller';
