#!/usr/bin/env bash
# =====================================================================
# ARKYL — transfert de l'ancienne base PostgreSQL vers Supabase
#
# Ce que fait le script :
#   1. vérifie les deux connexions ;
#   2. sauvegarde l'ancienne base dans un fichier (filet de sécurité) ;
#   3. copie TOUTES les tables et données du schéma « public » vers Supabase
#      (mots de passe déjà chiffrés, commandes, œuvres, compteurs d'identifiants…) ;
#   4. applique supabase/schema.sql (colonnes manquantes, sécurité RLS) ;
#   5. compare le nombre de lignes de chaque table, ancienne base ↔ Supabase.
#
# L'ancienne base n'est JAMAIS modifiée (lecture seule).
#
# Utilisation :
#   OLD_DATABASE_URL='postgresql://utilisateur:motdepasse@hote:5432/base' \
#   SUPABASE_DB_URL='postgresql://postgres.xxxx:MOTDEPASSE@aws-0-region.pooler.supabase.com:5432/postgres' \
#   ./scripts/migrate_to_supabase.sh
#
# Options :  --yes    ne pas demander de confirmation
#            --force  continuer même si Supabase contient déjà des tables ARKYL
# Prérequis : les outils PostgreSQL (psql, pg_dump, pg_restore) en version >= celle de l'ancienne base.
# =====================================================================
set -euo pipefail

YES=0; FORCE=0
for a in "$@"; do
  case "$a" in
    --yes) YES=1 ;;
    --force) FORCE=1 ;;
    -h|--help) sed -n 2,26p "$0"; exit 0 ;;
    *) echo "Option inconnue : $a"; exit 2 ;;
  esac
done

red()   { printf '\033[31m%s\033[0m\n' "$*"; }
green() { printf '\033[32m%s\033[0m\n' "$*"; }
step()  { printf '\n\033[1m── %s\033[0m\n' "$*"; }

: "${OLD_DATABASE_URL:?Définissez OLD_DATABASE_URL (adresse de l’ancienne base)}"
: "${SUPABASE_DB_URL:?Définissez SUPABASE_DB_URL (adresse « Session pooler » de Supabase)}"
for tool in psql pg_dump pg_restore; do
  command -v "$tool" >/dev/null || { red "Outil manquant : $tool (installez les outils clients PostgreSQL)"; exit 1; }
done

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SCHEMA_SQL="$HERE/../supabase/schema.sql"
[ -f "$SCHEMA_SQL" ] || { red "Fichier introuvable : $SCHEMA_SQL"; exit 1; }

STAMP="$(date +%Y%m%d-%H%M%S)"
DUMP="$HERE/../backup-ancienne-base-$STAMP.dump"
LIST="$(mktemp)"; trap 'rm -f "$LIST"' EXIT
Q() { psql "$1" -v ON_ERROR_STOP=1 -X -tAq -c "$2"; }   # requête rapide : Q <url> "<sql>"

step "1/5 Vérification des connexions"
Q "$OLD_DATABASE_URL" "select 'ancienne base : '||current_database()||' (PostgreSQL '||current_setting('server_version')||')'" \
  || { red "Connexion à l'ancienne base impossible."; exit 1; }
Q "$SUPABASE_DB_URL" "select 'Supabase       : '||current_database()||' (PostgreSQL '||current_setting('server_version')||')'" \
  || { red "Connexion à Supabase impossible (voir docs/MIGRATION_SUPABASE.md, section Dépannage)."; exit 1; }

EXISTING="$(Q "$SUPABASE_DB_URL" "select count(*) from information_schema.tables where table_schema='public' and table_name in ('orders','artworks','artists')")"
if [ "$EXISTING" != "0" ] && [ "$FORCE" != "1" ]; then
  red "Supabase contient déjà des tables ARKYL ($EXISTING). Pour éviter d'écraser des données, le script s'arrête."
  echo "  → Si c'est voulu : relancez avec --force (les tables existantes seront remplacées)."
  exit 1
fi

if [ "$YES" != "1" ]; then
  echo; echo "Les données de l'ancienne base vont être copiées dans Supabase."
  read -r -p "Continuer ? (oui/non) " ans
  [ "$ans" = "oui" ] || { echo "Annulé."; exit 0; }
fi

step "2/5 Sauvegarde de l'ancienne base → $DUMP"
pg_dump "$OLD_DATABASE_URL" --schema=public --format=custom --no-owner --no-privileges --file="$DUMP"
green "Sauvegarde créée ($(du -h "$DUMP" | cut -f1)). Gardez ce fichier précieusement."

step "3/5 Copie vers Supabase"
# On exclut la création du schéma « public » et les extensions (déjà gérés par Supabase)
pg_restore -l "$DUMP" | grep -v -E ' SCHEMA - public| COMMENT - SCHEMA public| EXTENSION | COMMENT - EXTENSION ' > "$LIST" || true
CLEAN=(); [ "$FORCE" = "1" ] && CLEAN=(--clean --if-exists)
pg_restore --dbname="$SUPABASE_DB_URL" --use-list="$LIST" --no-owner --no-privileges --exit-on-error "${CLEAN[@]}" "$DUMP"
green "Données copiées."

step "4/5 Mise à jour du schéma et sécurité (supabase/schema.sql)"
psql "$SUPABASE_DB_URL" -v ON_ERROR_STOP=1 -X -q -f "$SCHEMA_SQL" 2>&1 | grep -v "NOTICE:" || true
green "Schéma à jour, sécurité RLS activée."

step "5/5 Contrôle : nombre de lignes par table"
BAD=0
printf '  %-26s %10s %10s\n' "table" "ancienne" "supabase"
for t in $(Q "$OLD_DATABASE_URL" "select table_name from information_schema.tables where table_schema='public' and table_type='BASE TABLE' order by 1"); do
  a="$(Q "$OLD_DATABASE_URL" "select count(*) from public.\"$t\"")"
  b="$(Q "$SUPABASE_DB_URL" "select count(*) from public.\"$t\"" 2>/dev/null || echo "absente")"
  if [ "$a" = "$b" ]; then mark="✅"; else mark="❌"; BAD=1; fi
  printf '  %-26s %10s %10s  %s\n' "$t" "$a" "$b" "$mark"
done

echo
if [ "$BAD" = "0" ]; then
  green "Migration terminée : toutes les tables correspondent."
  echo "Étape suivante : définir SUPABASE_DB_URL chez votre hébergeur (voir docs/MIGRATION_SUPABASE.md)."
else
  red "Des différences existent (❌). Ne basculez pas le site : relisez les messages ci-dessus."
  exit 1
fi
