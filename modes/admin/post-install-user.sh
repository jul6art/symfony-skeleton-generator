#!/usr/bin/env bash
#
# Brique « comptes » du mode web : interrupteur d'inscription publique.

set -uo pipefail

cd "${PROJECT_DIR:?}" || exit 1

# Inscription publique : pilotée par .env, fermée d'entrée avec --no-registration.
if [ -f .env ] && ! grep -q '^APP_REGISTRATION_ENABLED=' .env; then
    printf '    .env → APP_REGISTRATION_ENABLED=%s\n' "${REGISTRATION:-1}"
    cat >> .env <<EOF

###> app ###
# Inscription publique : 0 pour la fermer (/register en 404, liens masqués).
APP_REGISTRATION_ENABLED=${REGISTRATION:-1}
###< app ###
EOF
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
