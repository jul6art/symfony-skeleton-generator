#!/usr/bin/env bash
#
# Lanceur parallèle de la suite de tests (ParaTest).
#
# ## Ce qu'il n'y a PAS ici, et pourquoi
#
# Les lanceurs parallèles de projets sur PostgreSQL construisent une base MODÈLE, la clonent pour
# chaque worker (`CREATE DATABASE … TEMPLATE`), balaient les bases résiduelles d'un run tué et
# nettoient à la fin. Tout cela existe parce que les workers y partagent un serveur de bases : sans
# isolation, ils se marchent dessus, et le symptôme est un test rouge au hasard, jamais le même.
#
# ⚠️ **Dans ce mode, il n'y a rien à isoler.** La suite tourne sur **SQLite EN MÉMOIRE**
# (`config/packages/test/doctrine.yaml`) et chaque test crée son schéma par `SchemaTool` : chaque
# processus a déjà sa base, qui naît et meurt avec lui. Recopier cette mécanique reviendrait à
# écrire cent lignes pour un problème que ce projet n'a pas.
#
# Le jour où la suite passera sur PostgreSQL — le jour où une requête que seule la production
# exécute devra être testée —, ce script devra l'écrire : `dbname_suffix:
# '_test%env(default::TEST_TOKEN)%'` dans le `when@test` de Doctrine, une base modèle sous le jeton
# 0, un clone par worker, et le nettoyage sous `trap`. C'est noté ici pour être relu ce jour-là.
#
# ## Usage
#
#   ./paratest.sh                      # toute la suite
#   THREADS=4 ./paratest.sh            # impose le nombre de processus
#   ./paratest.sh tests/Controller     # tout argument est transmis à paratest
#   ./paratest.sh --filter UserCrud    # idem
#
set -euo pipefail

cd "$(dirname "$0")"

# Le nombre de cœurs MOINS deux : la machine doit rester utilisable pendant que la suite tourne, et
# au-delà des cœurs disponibles les processus se disputent le CPU sans rien gagner.
detect_threads() {
    local cores
    cores=$(sysctl -n hw.ncpu 2>/dev/null || nproc 2>/dev/null || echo 4)
    local threads=$((cores - 2))
    ((threads < 2)) && threads=2
    echo "$threads"
}

THREADS="${THREADS:-$(detect_threads)}"

PHP=(php)
if command -v symfony > /dev/null 2>&1; then
    PHP=(symfony php)
fi

# ⚠️ Le cache de test est réchauffé UNE fois, avant les workers. Sans cela, N processus compilent
# le conteneur en même temps, dans le même dossier : la course est rare, silencieuse, et se
# manifeste par une erreur de classe introuvable dans un seul worker — un rouge qu'on n'arrive pas
# à reproduire. Une seconde de préchauffage vaut mieux qu'une heure à chercher.
echo "♻️  Préchauffage du cache de test…"
if command -v symfony > /dev/null 2>&1; then
    symfony console cache:warmup --env=test --quiet
else
    php bin/console cache:warmup --env=test --quiet
fi

echo "🚀 Suite lancée sur $THREADS processus…"
"${PHP[@]}" -d memory_limit=-1 vendor/bin/paratest \
    --runner=WrapperRunner \
    --processes="$THREADS" \
    "$@"
