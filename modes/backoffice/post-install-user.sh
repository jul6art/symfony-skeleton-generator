#!/usr/bin/env bash
#
# Hook de la brique « comptes » : la paire de clés JWT, dont l'API et les widgets Select2 ont
# besoin. Sans elle, `/api` répond 500 au premier appel authentifié.

set -uo pipefail

cd "${PROJECT_DIR:?}" || exit 1

printf '    Paire de clés JWT…\n'
if ! symfony console lexik:jwt:generate-keypair --skip-if-exists >/dev/null 2>&1; then
    printf '/!\\ génération de la paire de clés impossible — relancer « make jwt-keypair ».\n' >&2
fi
