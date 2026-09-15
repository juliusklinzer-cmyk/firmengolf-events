#!/bin/bash
# Lädt einzelne Plugin-Dateien per FTPS auf firmengolf-events.de und prüft die Live-Version.
# Aufruf: bash deploy-plugin-files.sh firmengolf-events.php includes/emails.php
set -e
cd "$(dirname "$0")"
PASS=$(grep -m1 '^Pass:' .deploy-creds.txt | sed 's/^Pass:[[:space:]]*//')
USER_=$(grep -m1 '^User:' .deploy-creds.txt | sed 's/^User:[[:space:]]*//')
HOST=$(grep -m1 '^Server:' .deploy-creds.txt | sed 's/^Server:[[:space:]]*//; s/ .*//')
for f in "$@"; do
  curl -s --ssl-reqd --user "$USER_:$PASS" -T "wordpress/wp-content/plugins/firmengolf-events/$f" \
    "ftp://$HOST/wp-content/plugins/firmengolf-events/$f" && echo "hochgeladen: $f"
done
sleep 2
echo -n "lokal:  "; grep -oE "FGE_VERSION', '[0-9.]+" wordpress/wp-content/plugins/firmengolf-events/firmengolf-events.php | grep -oE "[0-9.]+$"
echo -n "live:   "; curl -s https://firmengolf-events.de/ | grep -oE "ver=1\.9\.[0-9]+" | sort -u | head -1
