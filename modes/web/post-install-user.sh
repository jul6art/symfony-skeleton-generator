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

