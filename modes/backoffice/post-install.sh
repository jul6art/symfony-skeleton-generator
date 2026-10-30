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

# ⚠️ Le préchauffage AVANT le build : `assets/translator.js` importe `var/translations/index.js`,
# que le préchauffage écrit et que `/var/` gitignore. Sans lui, le premier build échoue sur un
# import introuvable — sur une machine neuve, donc à chaque génération.
printf '    Assets (npm install + cache:warmup + build)…\n'
if ! (npm install --silent \
        && symfony console cache:warmup \
        && npm run build --silent) >/dev/null 2>&1; then
    printf '/!\\ compilation des assets impossible — relancer « make assets ».\n' >&2
fi

# `composer remove symfony/ux-turbo` déconfigure le bundle mais laisse son entrée dans
# l'importmap quand AssetMapper est encore là au moment du retrait.
if [ -f importmap.php ]; then
    printf '    importmap.php retiré (ce mode compile avec Encore)\n'
    rm -f importmap.php
fi

# ⚠️ Le host des URLs générées HORS requête. Une commande console n'a pas de requête : `url()` et
# `absolute_url()` y retombent sur `DEFAULT_URI`. Laissé au défaut de la recipe Flex
# (« http://localhost »), un mail envoyé depuis le terminal part avec des liens qui ne mènent nulle
# part — et un logo en « http://:/ » ; l'URL de `app:dev:login-link`, elle, sort inutilisable.
#
# ⚠️ On POSE la valeur au lieu de seulement la commenter. Un commentaire laisse la clé fausse, et
# une clé fausse ne se voit pas : elle est syntaxiquement correcte, le conteneur compile, et le
# défaut n'apparaît qu'au premier lien cliqué. Constaté deux fois : corrigé à la main dans wovex le
# 2026-08-23, et encore au défaut de la recipe dans superp le 2026-08-25. C'est exactement la règle
# du journal d'apprentissage : « ce qui a été ajouté à un projet à la main doit remonter dans le
# hook, ou le défaut attend le prochain projet généré ».
#
# La convention de cet écosystème est `https://<projet>.localhost` (Traefik). Un projet qui sert
# ailleurs corrige une ligne visible, plutôt que de découvrir une ligne fausse.
if [ -f .env ] && grep -q '^DEFAULT_URI=' .env; then
    printf '    .env : DEFAULT_URI réglé sur https://%s.localhost\n' "${PROJECT_NAME:-app}"
    sed -i.bak "s#^DEFAULT_URI=.*#DEFAULT_URI=https://${PROJECT_NAME:-app}.localhost#" .env && rm -f .env.bak
fi

# Mercure 1.0 (push-bundle 3) : les jetons d'accès portent l'émetteur dont le hub a la confiance et
# l'audience qu'il fixe (config/packages/mercure.yaml, claims). La recette de mercure-bundle écrit
# MERCURE_JWT_ISSUER=https://localhost ; sa valeur suit la même convention que DEFAULT_URI, et
# l'audience s'y ajoute — le hub de la pile locale doit dire les mêmes (MERCURE_TRUSTED_ISSUERS,
# resource_identifier).
if [ -f .env ]; then
    printf '    .env : MERCURE_JWT_ISSUER et MERCURE_JWT_AUDIENCE (https://%s.localhost)\n' "${PROJECT_NAME:-app}"
    if grep -q '^MERCURE_JWT_ISSUER=' .env; then
        sed -i.bak "s#^MERCURE_JWT_ISSUER=.*#MERCURE_JWT_ISSUER=https://${PROJECT_NAME:-app}.localhost#" .env && rm -f .env.bak
    else
        printf 'MERCURE_JWT_ISSUER=https://%s.localhost\n' "${PROJECT_NAME:-app}" >> .env
    fi
    # Juste sous l'émetteur, dans le bloc de la recette : awk plutôt que `sed a\`, que GNU et BSD
    # n'écrivent pas pareil.
    if ! grep -q '^MERCURE_JWT_AUDIENCE=' .env; then
        awk -v aud="MERCURE_JWT_AUDIENCE=https://${PROJECT_NAME:-app}.localhost/.well-known/mercure" \
            '{ print } /^MERCURE_JWT_ISSUER=/ { print "# The audience of the JWTs, the resource identifier of the hub"; print aud }' \
            .env > .env.tmp && mv .env.tmp .env
    fi
fi
