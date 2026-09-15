#!/bin/bash
# Lädt einzelne Dateien per FTPS auf firmengolf-events.de und prüft die Live-Version.
# Pfade relativ zum Plugin (Standard) oder als voller wp-content-Pfad:
#   bash deploy-plugin-files.sh firmengolf-events.php includes/emails.php
#   bash deploy-plugin-files.sh wp-content/themes/firmengolf-child/front-page.php
set -e
cd "$(dirname "$0")"
PASS=$(grep -m1 '^Pass:' .deploy-creds.txt | sed 's/^Pass:[[:space:]]*//')
USER_=$(grep -m1 '^User:' .deploy-creds.txt | sed 's/^User:[[:space:]]*//')
HOST=$(grep -m1 '^Server:' .deploy-creds.txt | sed 's/^Server:[[:space:]]*//; s/ .*//')
for f in "$@"; do
  case "$f" in
    wp-content/*) rel="$f" ;;
    *)            rel="wp-content/plugins/firmengolf-events/$f" ;;
  esac
  curl -s --ssl-reqd --user "$USER_:$PASS" -T "wordpress/$rel" "ftp://$HOST/$rel" && echo "hochgeladen: $rel"
done
sleep 2
echo -n "lokal:  "; grep -oE "FGE_VERSION', '[0-9.]+" wordpress/wp-content/plugins/firmengolf-events/firmengolf-events.php | grep -oE "[0-9.]+$"
echo -n "live:   "; curl -s https://firmengolf-events.de/ | grep -oE "ver=1\.9\.[0-9]+" | sort -u | head -1
