const Encore = require('@symfony/webpack-encore');
const path = require('path');
const { bundleAliases } = require('./bundle-assets');

if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    .setOutputPath('public/build/')
    .setPublicPath('/build')
    .addEntry('app', './assets/app.js')
    .splitEntryChunks()
    .enableSingleRuntimeChunk()
    .cleanupOutputBeforeBuild()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning()
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = 'usage';
        config.corejs = '3.38';
    })
    .enablePostCssLoader()

    // Le front des bundles maison. `@jul6art/core-bundle/...`, `@jul6art/datatable-bundle/...` et
    // `@jul6art/admin-bundle/...` pointent sur le répertoire `assets/` du paquet.
    //
    // ⚠️ `@symfony/ux-translator` s'ajoute à la main : le bundle ne déclare ses chemins tout seul
    // que sous AssetMapper, et ce mode compile avec Encore. Le paquet composer contient bien
    // `assets/dist/`, donc aucune dépendance npm en `file:` n'est nécessaire — et c'est heureux,
    // celle que la recette Flex ajoute fait échouer `npm install` (arborist, npm 10.2.4).
    .addAliases({
        ...bundleAliases(),
        '@symfony/ux-translator': path.resolve(__dirname, 'vendor/symfony/ux-translator/assets/dist/translator_controller.js'),
    })
;

const config = Encore.getWebpackConfig();

// ⚠️ Les assets d'un bundle vivent dans `vendor/`, hors de l'arborescence que la résolution Node
// remonte depuis `assets/`. Sans ce chemin forcé, Stimulus et surtout les polyfills que Babel
// injecte lui-même (`useBuiltIns: 'usage'`) sont introuvables — des centaines d'erreurs pour un
// seul répertoire mal placé.
config.resolve.modules = [
    path.resolve(__dirname, 'node_modules'),
    ...(config.resolve.modules ?? ['node_modules']),
];

module.exports = config;
