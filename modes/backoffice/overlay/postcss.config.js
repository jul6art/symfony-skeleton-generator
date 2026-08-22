const path = require('path');
const { bundleAssets, FRONT_BUNDLES } = require('./bundle-assets');

/**
 * ⚠️ `postcss-import` résout les `@import` LUI-MÊME et ignore les alias de webpack.
 *
 * Il faut donc lui redonner la même table de correspondance, sinon `@jul6art/admin-bundle/...`
 * est cherché à côté d'`app.css` et le build échoue sur « Failed to find ». Les deux
 * résolutions viennent de `bundle-assets.js` : une seule définition, pas deux qui divergent.
 */
function resolveBundleImport(id, basedir) {
    for (const pkg of FRONT_BUNDLES) {
        const prefix = `@jul6art/${pkg}/`;
        if (id.startsWith(prefix)) {
            return path.join(bundleAssets(pkg), id.slice(prefix.length));
        }
    }

    return id;
}

/**
 * Ce que `postcss-import` a le droit d'inliner.
 *
 * ⚠️ Font Awesome doit rester DEHORS. Sa feuille référence ses polices en chemin relatif
 * (`url(../webfonts/…)`) : inlinée dans `app.css`, ces chemins sont réinterprétés depuis
 * `assets/styles/` et les quatre fichiers deviennent introuvables. Laissée à css-loader, elle est
 * résolue depuis son propre répertoire et les URL sont réécrites correctement.
 *
 * On n'inline donc que ce qui en a besoin : les directives Tailwind, les feuilles des bundles
 * maison (elles écrivent `@layer` et `@apply`) et les fichiers du projet.
 */
function shouldInline(id) {
    return id.startsWith('tailwindcss/') || id.startsWith('@jul6art/') || id.startsWith('.') || id.startsWith('components/');
}

module.exports = {
    plugins: {
        // ⚠️ `postcss-import` en premier, et ce n'est pas un détail d'ordre.
        //
        // Sans lui, css-loader résout les `@import` APRÈS que PostCSS a traité chaque fichier
        // séparément : une feuille importée qui écrit `@layer components` ne voit pas le
        // `@tailwind components` d'`app.css`, et Tailwind refuse de la compiler. C'est le cas des
        // feuilles des bundles maison, qui déclarent leurs composants dans un layer.
        //
        // Avec lui, tout est inliné avant Tailwind : `@layer` et `@apply` voient les directives,
        // et l'ordre des couches est celui du fichier d'entrée.
        'postcss-import': { resolve: resolveBundleImport, filter: shouldInline },
        tailwindcss: {},
        autoprefixer: {},
    },
};
