/*
 * Point d'entrée des assets du back-office, chargé par
 * DashboardController::configureAssets() via AssetMapper.
 *
 * La feuille est en SCSS : symfonycasts/sass-bundle la compile (binaire Dart
 * Sass local) et AssetMapper sert le .css correspondant.
 */
import './styles/admin.scss';
