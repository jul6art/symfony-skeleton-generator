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
