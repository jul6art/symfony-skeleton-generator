const path = require('path');
const { bundleAssets, bundleContentPaths } = require('./bundle-assets');

module.exports = {
    // ⚠️ Les chemins des bundles maison ne sont pas décoratifs : une classe qui n'apparaît que
    // dans le JS ou les gabarits d'un bundle serait purgée du CSS de production sans eux.
    content: [
        './templates/**/*.html.twig',
        './assets/**/*.js',
        ...bundleContentPaths(),
    ],
    // La police, l'ombre des panneaux et la couleur `accent-*` viennent du préréglage
    // d'`admin-bundle` : ce sont les jetons que ses feuilles de style utilisent.
    presets: [require(path.join(bundleAssets('admin-bundle'), 'tailwind/preset.js'))],
    plugins: [],
};
