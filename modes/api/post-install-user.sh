#!/usr/bin/env bash
#
# Brique « comptes » du mode api :
#   - garantit les variables d'environnement JWT et CORS (au cas où la recette
#     Flex du bundle n'a pas été appliquée) ;
#   - génère la paire de clés JWT.

set -uo pipefail

cd "${PROJECT_DIR:?}" || exit 1

if [ ! -f .env ]; then
    printf '/!\\ pas de fichier .env : configuration JWT/CORS à faire à la main.\n' >&2
    exit 0
fi

if ! grep -q '^JWT_SECRET_KEY=' .env; then
    printf '    .env → variables JWT\n'
    passphrase="$(openssl rand -hex 16 2>/dev/null || date +%s%N)"
    cat >> .env <<EOF

###> lexik/jwt-authentication-bundle ###
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=$passphrase
###< lexik/jwt-authentication-bundle ###
EOF
fi

if ! grep -q '^CORS_ALLOW_ORIGIN=' .env; then
    printf '    .env → CORS_ALLOW_ORIGIN\n'
    cat >> .env <<'EOF'

###> nelmio/cors-bundle ###
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$'
###< nelmio/cors-bundle ###
EOF
fi

# Environnement du client HTTP : le fichier privé porte le mot de passe et
# n'est pas versionné.
if [ -f request/http-client.private.env.json.dist ] && [ ! -f request/http-client.private.env.json ]; then
    printf '    request/http-client.private.env.json\n'
    cp request/http-client.private.env.json.dist request/http-client.private.env.json
fi

printf '    Paire de clés JWT…\n'
if ! symfony console lexik:jwt:generate-keypair --skip-if-exists >/dev/null 2>&1; then
    printf '/!\\ génération des clés JWT impossible — relancer « make jwt-keypair ».\n' >&2
fi
