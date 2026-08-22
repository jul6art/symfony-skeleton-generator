const fs = require('fs');
const path = require('path');

/**
 * Chemin des assets front livrés par un bundle maison — `vendor/jul6art/<paquet>/assets`.
 *
 * Le repli sur `../BUNDLES/<paquet>/assets` sert à une boucle de développement locale, où
 * `vendor/jul6art/<paquet>` est un lien symbolique vers un chemin qui n'existe que dans le
 * conteneur PHP : `fs.existsSync()` suit les liens, donc il répond `false` sur un lien cassé.
 *
 * Utilisé par `webpack.config.js` (alias) ET par `tailwind.config.js` (chemins scannés) : une
 * classe qui n'apparaît que dans le JS d'un bundle serait purgée du CSS si le second oubliait ce
 * que le premier résout — et seulement en production.
 */
function bundleAssets(pkg) {
    const vendor = path.resolve(__dirname, 'vendor/jul6art', pkg, 'assets');

    return fs.existsSync(vendor) ? vendor : path.resolve(__dirname, '../BUNDLES', pkg, 'assets');
}

const FRONT_BUNDLES = ['datatable-bundle', 'admin-bundle'];

function bundleAliases() {
    return Object.fromEntries(FRONT_BUNDLES.map((pkg) => [`@jul6art/${pkg}`, bundleAssets(pkg)]));
}

function bundleContentPaths() {
    return FRONT_BUNDLES.flatMap((pkg) => [
        `${bundleAssets(pkg)}/**/*.js`,
        `${path.dirname(bundleAssets(pkg))}/Resources/views/**/*.html.twig`,
    ]);
}

module.exports = { FRONT_BUNDLES, bundleAssets, bundleAliases, bundleContentPaths };
