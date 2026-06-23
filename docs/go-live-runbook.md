# Go-Live-Runbook — visionpunch.de

**Methode:** Duplicator-Plugin klont Dateien + DB + URL-Tausch in einem Rutsch.
Zugang One.com = **File-Manager + phpMyAdmin** (kein SSH/WP-CLI).
Bestehende WP auf der Domain wird komplett platt gemacht (nichts zu erhalten).

## Phase A · Lokal vorbereiten
- [ ] Bilder optimieren (`assets/imagery`, ~60 MB → <8 MB)
- [ ] Duplicator in lokaler WP installieren + aktivieren
- [ ] Duplicator → Neues Paket (Full-Site) → Scan → Build
- [ ] `installer.php` + `archive.zip` herunterladen

## Phase B · Ziel-Domain leeren
- [ ] One.com File-Manager: gesamten WP-Webroot löschen
- [ ] phpMyAdmin: DB leeren (alle Tabellen droppen) ODER neue leere DB
- [ ] DB-Zugang notieren: Host, Name, User, Passwort

## Phase C · Hochladen + Installer
- [ ] `installer.php` + `archive.zip` in leeren Webroot hochladen
- [ ] `https://visionpunch.de/installer.php` öffnen
- [ ] DB-Daten eintragen → Import läuft
- [ ] URL: `http://localhost:8080` → `https://visionpunch.de`
- [ ] Abschließen, mit lokalen Admin-Daten einloggen
- [ ] Installer-Aufräumung bestätigen (löscht installer.php + Archiv)

## Phase D · Prod-Härtung in wp-config.php
- [ ] `define('FS_METHOD','direct');`
- [ ] `define('WP_DEBUG_DISPLAY', false);`
- [ ] `define('DISALLOW_FILE_EDIT', true);`

## Phase E · WordPress scharf stellen
- [ ] Einstellungen → Lesen → „Suchmaschinen abhalten" Haken ENTFERNEN (blog_public=1)
- [ ] Einstellungen → Permalinks → Speichern (Rewrites + Sitemap fgelandings)
- [ ] WP Mail SMTP → Brevo + neuer API-Key + Force From events@visionpunch.de / „Firmengolf"
- [ ] GA4 optional: FGE_GA4_ID / Option fge_ga4_id

## Phase F · Aufräumen
- [ ] uploads/2026/06/cookiebot.4.7.1.zip löschen
- [ ] plugins/hello.php löschen
- [ ] Tabelle wp_real_queue droppen
- [ ] Privacy-Draft (Post 3) löschen
- [ ] Duplicator-Paket lokal + Server entfernen

## Phase G · Smoke-Test
- [ ] Test-Anfrage → Mail bei Gmail + GMX, nicht Spam
- [ ] /wp-sitemap.xml → 200, enthält Events + Landingpages → Search Console
- [ ] Klaro-Banner: sichtbar / Maps lädt erst nach Accept / Reject blockt / Widerruf
- [ ] Journeys: Event-Modal, Wizard, Budget-Rechner, Onboarding
- [ ] noindex nur Portal/Onboarding/Angebot/Termin
- [ ] SSL / Mixed-Content / http→https-Redirect

## Phase H · Erste Tage
- [ ] Alten Brevo-API-Key löschen
- [ ] Datenschutz + AGB Rechtstext + 4 AVVs (One.com/Google/HubSpot/Kit)
- [ ] WP-Cron → System-Cron
- [ ] Onboarding-Maps-JS gaten (1 Stelle)
- [ ] Echte Testimonials sammeln
