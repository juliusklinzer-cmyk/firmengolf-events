# Domain-Migration — visionpunch.de → firmengolf-events.de

**Stand:** 2026-07-02 · **Entscheidungen (Julius):**
- Neue Haupt-Domain: **firmengolf-events.de** (frisch erworben)
- Rechtsträger bleibt **Visionpunch UG (haftungsbeschränkt)** → Impressum/Footer/AGB unverändert
- **visionpunch.de bleibt bestehen**: leitet per 301 aufs jeweilige Projekt weiter, dient später separat als Onepager
- **firmengolf.app**: reserviert für den Benefit-Bereich (später) — hier NICHT verwenden
- **firmen.golf**: separates Projekt (Corporate Benefit) — Verweise im Code bleiben unangetastet

Zugang One.com = File-Manager + phpMyAdmin + SFTP (Port 22), **kein WP-CLI**.

## Phase 1 · Vorbereitung (vor dem Umzug — sonst Tag-1-Chaos)

- [ ] **Postfächer bei One.com anlegen** (alle 6): `hallo@`, `events@`, `partner@`,
      `presse@`, `jobs@`, `datenschutz@firmengolf-events.de`
- [ ] **Weiterleitungen** der alten `@visionpunch.de`-Adressen auf die neuen einrichten
      (Partner/Kunden haben alte Adressen in Mail-Verläufen)
- [ ] **Brevo**: firmengolf-events.de als Absender-Domain anlegen + verifizieren,
      **SPF/DKIM/DMARC-DNS-Einträge** für die neue Domain setzen — sonst Spam ab Tag 1
- [ ] **Google Maps API-Key**: HTTP-Referrer-Beschränkung in der Cloud Console um
      `firmengolf-events.de/*` erweitern (sonst Onboarding-Karte tot)
- [ ] DNS: firmengolf-events.de auf den One.com-Webspace zeigen lassen + SSL-Zertifikat

## Phase 2 · Lokale Anpassungen abschließen

**Entscheidung (Julius, 2026-07-02): KEINE Migration der Live-Site — frisches Aufsetzen
vom lokalen Stand.** Auf visionpunch.de gab es seit dem Launch keine Änderungen oder
Anmeldungen; alles Inhaltliche liegt lokal (verifiziert 2026-07-02: 12 Blog-Artikel,
48 Events, 20 Partner, 15 Seiten in der lokalen DB). Der WinSCP-Stapel für
visionpunch.de entfällt damit — alles geht gesammelt mit dem Duplicator-Paket raus.

- [ ] Julius' geplante Anpassungen fertigstellen (inkl. Seed-Events-Inhalte, s. Memory)
- [ ] Mail-Umstellung auf @firmengolf-events.de ist im Code erledigt (2026-07-02)
- [ ] Lokal `blog_public=0` LASSEN (wird erst auf der Live-Site auf 1 gesetzt)

## Phase 3 · Frisch aufsetzen (wie Go-Live, Duplicator)

Ablauf analog `docs/go-live-runbook.md`, Ziel diesmal firmengolf-events.de:

- [ ] Duplicator-Paket vom lokalen Stand bauen (`installer.php` + `archive.zip`)
- [ ] Ziel-Webroot bei One.com leeren, leere DB (Host `localhost`!)
- [ ] Installer: URL-Tausch `http://localhost:8080` → `https://firmengolf-events.de`
- [ ] Installer-Aufräumung bestätigen; Duplicator danach deinstallieren

**Auf der frischen Install neu einrichten (lag nur auf der alten Live, nicht im Paket):**
- [ ] `wp-config.php`: `FS_METHOD=direct`, `WP_DEBUG_DISPLAY=false`,
      `DISALLOW_FILE_EDIT=true`, `WP_HOME`/`WP_SITEURL=https://firmengolf-events.de`,
      `FGE_GA4_ID` (G-7G0V3CKHYR), `DISABLE_WP_CRON=true`, Salts (macht der Installer)
- [ ] `.htaccess`: Cache-Header-Block (mod_expires/mod_headers) VOR `# BEGIN WordPress`
- [ ] WP Mail SMTP installieren: Brevo-Mailer + API-Key + Force From
      `events@firmengolf-events.de` / „Firmengolf" (⚠️ Brevo-Authorised-IPs prüfen)
- [ ] Lokales MailHog-mu-plugin (`fge-local-mail.php`) darf NICHT mit (gitignored,
      aber im Duplicator-Paket enthalten → auf dem Server löschen!)
- [ ] `blog_public=1` (Einstellungen → Lesen), Permalinks speichern (Rewrites+Sitemap)
- [ ] WP Admin-E-Mail auf neue Adresse
- [ ] cron-job.org: Cron-URL auf `https://firmengolf-events.de/wp-cron.php?doing_wp_cron=1`
- [ ] GA4: Datenstream-URL auf neue Domain
- [ ] Aufräumen wie beim Go-Live (Duplicator-Reste, Hello Dolly falls dabei)

## Phase 4 · Redirects (SEO-kritisch!)

Site ist seit 2026-06-21 indexiert (Blog + Landingpages) — ohne 301 ist das weg.

- [ ] **301-Redirect visionpunch.de → firmengolf-events.de**, Pfad-erhaltend
      (`visionpunch.de/xyz` → `firmengolf-events.de/xyz`), via One.com-Weiterleitung
      oder `.htaccess` im alten Webroot. KEIN Redirect nur auf die Startseite!
- [ ] Redirect testen: Startseite, ein Blog-Artikel, eine Landingpage, /firmenevents/
- [ ] Alte WP-Install auf visionpunch.de stilllegen (Redirect ersetzt sie; DB/Dateien
      erst nach Übergangszeit endgültig löschen — Backup behalten)
- [ ] Hinweis: der geplante Visionpunch-Onepager kommt erst NACH einer Übergangszeit —
      solange die 301 stehen, überträgt Google die Rankings. Onepager frühestens
      nach einigen Monaten, sonst SEO-Verlust.

## Phase 5 · Google & externe Dienste

- [ ] Search Console: Property für firmengolf-events.de anlegen (Domain-Property)
- [ ] Search Console: **„Adressänderung"-Tool** von der visionpunch.de-Property aus
- [ ] Sitemap `https://firmengolf-events.de/wp-sitemap.xml` einreichen
- [ ] GA4 (falls aktiv): Datenstream-URL aktualisieren
- [ ] Google Business Profile / Social-Profile / Signaturen: URL + Mail aktualisieren
- [ ] Brevo-Templates/Automationen: alte Links prüfen

## Phase 6 · Smoke-Test

- [ ] Test-Anfrage → Mail kommt an (Gmail + GMX, nicht Spam), Absender neue Domain
- [ ] Antwort auf die Mail → landet im neuen `events@`-Postfach
- [ ] Klaro-Banner + Maps-Consent funktionieren auf neuer Domain
- [ ] Onboarding-Karte lädt (Maps-Key-Referrer!)
- [ ] SSL / Mixed-Content / http→https auf neuer Domain
- [ ] Alte URLs (Blog-Artikel) leiten korrekt per 301 um
- [ ] Datenschutz/Impressum: zeigen neue Mails, weiter Visionpunch UG als Rechtsträger

## Offen / später

- [ ] Datenschutztext: Domain-Nennungen prüfen (war eh offen, s. Cookie-Consent)
- [ ] Visionpunch-Onepager (separates Projekt, erst nach SEO-Übergangszeit)
- [ ] AVVs (One.com/Google/HubSpot/Kit) — laufen auf die UG, prüfen ob Domain relevant
