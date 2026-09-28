#!/usr/bin/env bash
# Erzeugt docs/mail-inventar.md aus der Mail-Registry (lokaler Docker-Stack muss laufen).
set -e
cd "$(dirname "$0")/.."
docker compose cp tests/mail-inventar.php wordpress:/tmp/fge-mail-inventar.php
docker compose exec -T wordpress wp eval-file /tmp/fge-mail-inventar.php --allow-root --skip-themes > docs/mail-inventar.md
echo "docs/mail-inventar.md: $(grep -c '^| `' docs/mail-inventar.md) Mails"
printf '\n' >> docs/mail-inventar.md
cat tests/mail-inventar-anhang.md >> docs/mail-inventar.md
