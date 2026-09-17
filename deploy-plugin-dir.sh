#!/bin/bash
# Lädt ein ganzes Verzeichnis des Plugins per FTPS hoch (eine curl-Sitzung je 60 Dateien,
# Zielordner werden angelegt). Pfad relativ zum Plugin, z. B.:
#   bash deploy-plugin-dir.sh lib/dompdf
set -e
cd "$(dirname "$0")"
PASS=$(grep -m1 '^Pass:' .deploy-creds.txt | sed 's/^Pass:[[:space:]]*//')
USER_=$(grep -m1 '^User:' .deploy-creds.txt | sed 's/^User:[[:space:]]*//')
HOST=$(grep -m1 '^Server:' .deploy-creds.txt | sed 's/^Server:[[:space:]]*//; s/ .*//')
DIR="$1"
[ -z "$DIR" ] && { echo "Verzeichnis fehlt"; exit 1; }
BASE="wp-content/plugins/firmengolf-events/$DIR"
[ -d "wordpress/$BASE" ] || { echo "wordpress/$BASE nicht gefunden"; exit 1; }
mapfile -t FILES < <(cd wordpress && find "$BASE" -type f | sort)
echo "Dateien: ${#FILES[@]}"
n=0; args=()
for rel in "${FILES[@]}"; do
  args+=( -T "wordpress/$rel" "ftp://$HOST/$rel" )
  n=$((n+1))
  if [ $((n % 60)) -eq 0 ]; then
    curl -s --ssl-reqd --ftp-create-dirs --user "$USER_:$PASS" "${args[@]}" && echo "hochgeladen: $n"
    args=()
  fi
done
if [ ${#args[@]} -gt 0 ]; then
  curl -s --ssl-reqd --ftp-create-dirs --user "$USER_:$PASS" "${args[@]}" && echo "hochgeladen: $n"
fi
echo "fertig: $n Dateien"
