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

    // Le front des bundles maison. `@jul6art/datatable-bundle/...` et
    // `@jul6art/admin-bundle/...` pointent sur le répertoire `assets/` du paquet.
    .addAliases(bundleAliases())
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
