## Spécificités du mode web

- Front géré par **AssetMapper** : pas de build Node. Les dépendances JS
  s'ajoutent avec `symfony console importmap:require <paquet>` et
  `importmap.php` est versionné.
- `make assets` compile les assets (`asset-map:compile`) ; `public/assets/` et
  `assets/vendor/` ne sont pas versionnés.
- Templates dans `templates/`, `base.html.twig` porte les blocs `title`,
  `stylesheets`, `javascripts`, `body`.
- Turbo est actif : attention aux scripts qui supposent un rechargement complet
  de page ; préférer des contrôleurs Stimulus dans `assets/controllers/`.
