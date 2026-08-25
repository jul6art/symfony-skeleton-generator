/**
 * Ré-export du contrôleur `collection` de `jul6art/admin-bundle` (identifiant Stimulus
 * `form--collection`, donné par le CHEMIN de ce fichier).
 *
 * Il ajoute et retire les entrées d'un `CollectionType` en place : tout écran qui édite une
 * collection courte — les contacts d'un client, les étapes d'une checklist — s'en sert.
 *
 * ⚠️ Sans ce ré-export, le bouton « Ajouter » se dessine et le clic ne fait RIEN : un contrôleur
 * Stimulus introuvable ne lève pas. C'est le défaut de `form--dropzone` à l'identique, que le
 * bundle a fini par fermer en publiant le comportement avec son vocabulaire.
 *
 * Le gabarit qui l'utilise doit rendre le PROTOTYPE et les lignes existantes par le même partial,
 * et déclarer sa collection `allow_delete: true` + `by_reference: false`.
 */
export { default } from '@jul6art/admin-bundle/controllers/collection_controller';
