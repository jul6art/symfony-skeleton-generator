import { createTranslator } from '@symfony/ux-translator';
import { messages, localeFallbacks } from '../var/translations/index.js';

/*
 * Le traducteur du navigateur.
 *
 * `var/translations/index.js` est ÉCRIT par le préchauffage du cache (`bin/console cache:warmup`)
 * et gitignoré. Sous AssetMapper, `symfony/ux-translator` déclare tout seul ses chemins d'assets :
 * il n'y a ni alias ni dépendance npm à poser — c'est le mode `backoffice`, qui compile avec
 * Webpack Encore, qui doit les écrire à la main.
 *
 * ⚠️ Le domaine est fixé ICI, une fois. `core.js_translations.domain: javascript` restreint ce qui
 * est DÉPOSÉ ; il ne change pas le domaine par défaut de `trans()`, qui reste `messages`. Un appel
 * non qualifié ne trouverait rien et rendrait la clé brute — sans rien lever.
 *
 * ```js
 * import { trans } from '../translator';
 *
 * this.element.textContent = trans('confirm.delete.title');
 * ```
 *
 * ⚠️ Ce mode n'installe aucun bundle qui livre des contrôleurs Stimulus, donc rien n'a besoin du
 * registre de `jul6art/core-bundle` : le code du projet importe `trans` directement. Le jour où un
 * bundle front entre dans le projet, il lit à travers ce registre — il ne peut pas importer ce
 * fichier-ci, le chemin depuis `vendor/` n'existe pas — et l'amorce devient :
 *
 * ```js
 * import { registerTranslator } from '@jul6art/core-bundle/i18n/registry';
 * registerTranslator(trans);
 * ```
 */
const translator = createTranslator({
    messages,
    localeFallbacks,
});

export const trans = (key, parameters = {}) => translator.trans(key, parameters, 'javascript');
