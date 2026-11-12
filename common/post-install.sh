#!/usr/bin/env bash
#
# Retouches communes à tous les modes, après les recettes Flex et les overlays.
#
set -euo pipefail
cd "$PROJECT_DIR"

# ⚠️ **Les ports publiés par Docker écoutent sur 127.0.0.1**. Une recette
# Flex écrit `- "5432"` (port hôte au hasard) ou `- "8080:80"` : Docker les ouvre sur TOUTES les
# interfaces, et la base de développement, avec son mot de passe d'exemple, répond à qui partage le
# réseau du poste (café, coworking, Wi-Fi d'hôtel). Seules les lignes d'un bloc `ports:` sont touchées.
for file in compose.yaml compose.override.yaml docker-compose.yml docker-compose.override.yml; do
    [ -f "$file" ] || continue
    perl -i -pe '
        if (/^(\s*)ports:\s*$/) { $indent = length($1); next; }
        if (defined $indent) {
            if (/^(\s*)-\s/ && length($1) > $indent) {
                s/^(\s*-\s*)"?(\d+)"?\s*$/$1"127.0.0.1::$2"\n/;
                s/^(\s*-\s*)"?(\d+:\d+(?:\/(?:tcp|udp))?)"?\s*$/$1"127.0.0.1:$2"\n/;
            } elsif (/^(\s*)\S/ && length($1) <= $indent) {
                undef $indent;
            }
        }
    ' "$file"
    printf '    %s : ports publiés sur 127.0.0.1\n' "$file"
done

# Les deux variables que lit `config/packages/trusted_proxies.yaml` en production :
# vides ici, réglées par le `.env.local` du serveur.
if [ -f .env ] && ! grep -q '^TRUSTED_HOSTS=' .env; then
    printf '    .env : TRUSTED_PROXIES et TRUSTED_HOSTS (production)\n'
    cat >> .env <<'ENV'

###> trusted proxies and hosts (config/packages/trusted_proxies.yaml) ###
# Production only. The proxy in front of PHP, if any (Varnish: 127.0.0.1); never REMOTE_ADDR.
TRUSTED_PROXIES=
# The hosts the production answers for, as a regex (^(www\.)?example\.com$); any other Host gets a 400.
TRUSTED_HOSTS=
###< trusted proxies and hosts ###
ENV
fi
