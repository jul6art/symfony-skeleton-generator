const fs = require('fs');
const path = require('path');

/**
 * Racine d'un bundle maison — `vendor/jul6art/<paquet>`.
 *
 * Le repli sur `../BUNDLES/<paquet>` sert à une boucle de développement locale, où
 * `vendor/jul6art/<paquet>` est un lien symbolique vers un chemin qui n'existe que dans le
 * conteneur PHP : `fs.existsSync()` suit les liens, donc il répond `false` sur un lien cassé.
 */
function bundleRoot(pkg) {
    const vendor = path.resolve(__dirname, 'vendor/jul6art', pkg);

    return fs.existsSync(vendor) ? vendor : path.resolve(__dirname, '../BUNDLES', pkg);
}

/**
 * Chemin des assets front livrés par un bundle maison — `<racine>/assets`.
 *
 * ⚠️ La résolution se fait sur la RACINE et non sur `assets` : un bundle qui n'expose que des
 * gabarits n'a pas de répertoire `assets/`, et tester son existence faisait retomber TOUS ses
 * chemins sur `../BUNDLES/` — présent sur la machine du développeur, absent partout ailleurs.
 * Le build passait ici et purgeait les classes en CI, ce qui est le pire des deux mondes.
 *
 * Utilisé par `webpack.config.js` (alias) ET par `tailwind.config.js` (chemins scannés) : une
 * classe qui n'apparaît que dans le JS d'un bundle serait purgée du CSS si le second oubliait ce
 * que le premier résout — et seulement en production.
 */
function bundleAssets(pkg) {
    return path.join(bundleRoot(pkg), 'assets');
}

/** Les bundles qui livrent du JS et du CSS : alias webpack, imports postcss, contenu Tailwind. */
const FRONT_BUNDLES = ['datatable-bundle', 'admin-bundle'];

/**
 * Les bundles dont les GABARITS écrivent des classes Tailwind, en plus des précédents.
 *
 * ⚠️ `ui-bundle` ne livre ni JS ni CSS — il n'a donc rien à faire dans `FRONT_BUNDLES` — mais son
 * thème de formulaire (`Resources/views/form/`) est le seul endroit où passent les classes des
 * `Custom*Type`. Sans lui ici, Tailwind ne les voit pas et les purge ; le symptôme n'est pas une
 * erreur, c'est une icône NOIRE au lieu de grise sur chaque champ à add-on (vu le 2026-08-24 sur
 * /register, /reset-password et /admin/account/password).
 */
const TEMPLATE_BUNDLES = [...FRONT_BUNDLES, 'ui-bundle'];

function bundleAliases() {
    return Object.fromEntries(FRONT_BUNDLES.map((pkg) => [`@jul6art/${pkg}`, bundleAssets(pkg)]));
}

function bundleContentPaths() {
    return [
        ...FRONT_BUNDLES.map((pkg) => `${bundleAssets(pkg)}/**/*.js`),
        ...TEMPLATE_BUNDLES.map((pkg) => `${bundleRoot(pkg)}/Resources/views/**/*.html.twig`),
    ];
}

module.exports = { FRONT_BUNDLES, TEMPLATE_BUNDLES, bundleRoot, bundleAssets, bundleAliases, bundleContentPaths };
