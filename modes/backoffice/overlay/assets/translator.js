import { createTranslator } from '@symfony/ux-translator';
import { messages, localeFallbacks } from '../var/translations/index.js';

/*
 * Le traducteur du navigateur.
 *
 * `var/translations/index.js` est ÉCRIT par le préchauffage du cache (`bin/console cache:warmup`).
 * Il est gitignoré : sur une machine neuve, en CI et au déploiement, l'ordre est
 * `composer install` → `cache:warmup` → `npm run build`. C'est ce que fait `make assets`.
 *
 * ⚠️ Le domaine est fixé ICI, une fois. `core.js_translations.domain: javascript` restreint ce qui
 * est DÉPOSÉ ; il ne change pas le domaine par défaut de `trans()`, qui reste `messages`. Un appel
 * non qualifié ne trouverait rien et rendrait la clé brute — sans rien lever.
 *
 * ⚠️ Le dump porte TOUTES les locales de `framework.enabled_locales`. Un produit qui en déclare
 * plus qu'il n'en traduit s'appuie sur `localeFallbacks`, exactement comme le traducteur le fait
 * côté serveur — et son garde de traduction éprouve alors la liste des langues RÉELLEMENT
 * traduites, pas celle des langues acceptées.
 */
const translator = createTranslator({
    messages,
    localeFallbacks,
});

export const trans = (key, parameters = {}) => translator.trans(key, parameters, 'javascript');
