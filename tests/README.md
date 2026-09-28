# Regressionslauf

Fährt den Soll-Prozess über die echte Oberfläche (Control Center, Angebotsseite, Platz-Links) und prüft die Mails in MailHog.

Voraussetzungen: `docker compose up -d` (WordPress auf localhost:8080, MailHog auf localhost:8025), Python 3 mit `requests`, die Testplätze Golfclub Schönbuch e.V. (Partner 1613) und Golf Club Hammetweil (1200) als Stammdaten mit Kontaktmail, Platzhalter-Event 428.

```bash
python3 tests/regression.py
```

Der Lauf legt einen Admin `audit-bot` an, erzeugt zwei Testanfragen und räumt beides am Ende wieder weg. Ergebnis „grün" heißt: alle Prüfpunkte bestanden. Vor jedem Aufräum- oder Umbauschritt einmal laufen lassen, danach noch einmal.

`fge_driver.py` ist die Bibliothek (Login, Formulare aus dem Cockpit-HTML parsen, per admin-post.php posten, MailHog lesen). Neue Szenarien folgen dem Muster in `regression.py`.

## Mail-Inventar

`bash tests/mail-inventar.sh` erzeugt `docs/mail-inventar.md` neu: Teil 1 aus `includes/mail-registry.php` (Skript `tests/mail-inventar.php`), Anhang aus `tests/mail-inventar-anhang.md` (Onboarding, Portal, System, Cron-Eskalationen, von Hand gepflegt). Nach jeder Änderung an der Registry laufen lassen.
