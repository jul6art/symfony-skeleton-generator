#!/usr/bin/env bash
#
# Hook du mode web : met en place les ressources front *locales*.
#   - Font Awesome Free (CSS + polices) dans assets/fontawesome/
#   - binaire Tailwind + première compilation
#
# Les deux étapes ne sont pas bloquantes : sans réseau, le projet reste
# utilisable et les commandes sont rejouables (make fontawesome / make tailwind).

set -uo pipefail

cd "${PROJECT_DIR:?}" || exit 1

printf '    Font Awesome (local)…\n'
if ! make fontawesome >/dev/null 2>&1; then
    printf '/!\\ téléchargement de Font Awesome impossible — relancer « make fontawesome ».\n' >&2
    mkdir -p assets/fontawesome/css
    printf '/* Font Awesome non installé : lancer « make fontawesome ». */\n' \
        > assets/fontawesome/css/all.min.css
fi

printf '    Tailwind (binaire + première compilation)…\n'
if ! symfony console tailwind:build --minify >/dev/null 2>&1; then
    printf '/!\\ compilation Tailwind impossible — relancer « make tailwind ».\n' >&2
fi

printf '    Sass (binaire + thème du back-office)…\n'
if ! symfony console sass:build >/dev/null 2>&1; then
    printf '/!\\ compilation Sass impossible — relancer « make sass ».\n' >&2
fi

# `composer remove symfony/ux-turbo` déconfigure le bundle mais laisse son entrée
# dans l'importmap : sans ça, Turbo continue d'être téléchargé et servi.
if [ -f importmap.php ] && grep -q '@hotwired/turbo' importmap.php; then
    printf '    importmap.php → retrait de @hotwired/turbo\n'
    symfony console importmap:remove @hotwired/turbo >/dev/null 2>&1 \
        || printf '/!\\ retrait de @hotwired/turbo impossible — le faire à la main dans importmap.php\n' >&2
fi
# Le back-office charge son thème par AssetMapper : il lui faut un point
# d'entrée « admin » dans l'importmap.
if [ -f importmap.php ] && ! grep -q "'admin'" importmap.php; then
    printf "    importmap.php → point d'entrée « admin »\n"
    symfony php -r '
$f = "importmap.php";
$c = file_get_contents($f);
$entry = "    \x27admin\x27 => [\n        \x27path\x27 => \x27./assets/admin.js\x27,\n        \x27entrypoint\x27 => true,\n    ],\n";
$c = preg_replace("/(\x27app\x27 => \[\n.*?\n    \],\n)/s", "$1".$entry, $c, 1);
file_put_contents($f, $c);
' || printf '/!\\ ajout du point d entrée « admin » impossible — le faire à la main dans importmap.php\n' >&2
fi
