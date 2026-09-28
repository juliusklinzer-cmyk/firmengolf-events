# Doku-Übersicht (Stand 28.09.2026)

**Prozess und Betrieb**
- `prozess-anfrage-bis-rechnung.md`: das Leitdokument, Soll-Prozess von der Anfrage bis zur Rechnung, mit allen Nachträgen.
- `angebot-und-vortagsinfo-2026-09.md`: Angebotsdokument, PDF, Vortags-Info, Partnercodes.
- `control-center-test.md`: Testanleitung fürs Control Center von Hand. Automatisch: `python3 tests/regression.py` (siehe `tests/README.md`).
- `mail-inventar.md`: alle transaktionalen Mails. Der erste Teil kommt aus `includes/mail-registry.php`, der Anhang (Onboarding, Portal, System) aus `tests/mail-inventar-anhang.md`. Neu erzeugen nach Änderungen an der Registry: `bash tests/mail-inventar.sh` (Docker-Stack muss laufen).

- `server-htaccess.md`: die .htaccess auf Live (nicht in git).
- `bilder-leitfaden.md`, `bildnachweise-pool.md`: Bildpool und Nachweise.
- `ki-sichtbarkeit-2026-09.md`: KI-Sichtbarkeit und Seiteninhalte.

**Vertrieb und Daten**
- `golfplaetze-ansprache-bayern-2026-09.md`, `golfplaetze-bayern-emails.tsv`, `golfplaetze-bayern-export.tsv`: Bayern-Ansprache, Quelle der Kontaktmails im Stammdaten-Import.
- `simulatoren-ansprache-2026-09.md` plus `.csv` und die drei Wellen-Mails: Simulator-Akquise, Quelle der Simulator-Kontakte.
- `multiplikatoren-2026-09-03.tsv`, `multiplikatoren-ansprache-2026-09.md`: Verbände und Netzwerke.
- `partner-uebersicht.csv`: Partnerstatus im Überblick.
- `onboarding-golflehrer-indoor.md`: Onboarding-Konzept Golflehrer und Indoor.
- `budget-rechner-preisrecherche-2026-09.md`, `seo-keyword-map.md`: Preise und Keywords.
- `recherche/`: Vorlagen für das Golfmarkt-Universum, `blog-entwuerfe/`: Blog-Entwürfe.

**Archiv** (`archive/`): abgeschlossene Audits, Runbooks (Domain-Migration, Go-live), Briefings, Analysen und der alte Simulator-Plan. Nachschlagen ja, pflegen nein.
