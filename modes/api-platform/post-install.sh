#!/usr/bin/env bash
#
# Hook du mode api-platform : assets d'API Platform (Swagger UI, polices,
# feuille de style). Sans eux, /api s'affiche sans aucune mise en forme.

set -uo pipefail

cd "${PROJECT_DIR:?}" || exit 1

printf '    Assets API Platform…\n'
if ! symfony console assets:install public >/dev/null 2>&1; then
    printf '/!\\ installation des assets impossible — relancer « make assets ».\n' >&2
fi
