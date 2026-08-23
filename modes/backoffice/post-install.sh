#!/usr/bin/env bash
#
# Hook du mode « backoffice ».
#
# ⚠️ Ce mode est le SEUL du squelette à compiler ses assets avec Webpack Encore, donc à exiger
# Node. La raison est écrite dans CLAUDE.append.md : le socle de tableau repose sur DataTables 2,
# son greffon Responsive, jQuery et Select2 — la pile exacte dont ces bundles ont été extraits, et
# la seule éprouvée de bout en bout à ce jour.
#
# Aucune étape n'est bloquante : sans réseau, le projet reste utilisable et chaque commande est
# rejouable (`make assets`, `make jwt-keypair`).

set -uo pipefail

cd "${PROJECT_DIR:?}" || exit 1

printf '    Assets (npm install + build)…\n'
if ! (npm install --silent && npm run build --silent) >/dev/null 2>&1; then
    printf '/!\\ compilation des assets impossible — relancer « make assets ».\n' >&2
fi

# `composer remove symfony/ux-turbo` déconfigure le bundle mais laisse son entrée dans
# l'importmap quand AssetMapper est encore là au moment du retrait.
if [ -f importmap.php ]; then
    printf '    importmap.php retiré (ce mode compile avec Encore)\n'
    rm -f importmap.php
fi

# ⚠️ Le host des URLs générées HORS requête. Une commande console n'a pas de requête : `url()` et
# `absolute_url()` y retombent sur `DEFAULT_URI`. Laissé à « http://localhost », un mail envoyé
# depuis le terminal part avec des liens qui ne mènent nulle part — et un logo en « http://:/ ».
# La recipe de Flex pose la clé ; on la commente pour que le premier déploiement la voie.
if [ -f .env ] && ! grep -q '# DEFAULT_URI — le host' .env; then
    printf '    .env : DEFAULT_URI commenté\n'
    printf '\n# DEFAULT_URI — le host des URLs générées hors requête (mails envoyés par une\n# commande, notifications). À régler sur l'"'"'URL publique du projet.\n' >> .env
fi
