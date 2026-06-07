# Firmengolf — Datenmodell & WordPress-Mapping (Handoff)

> Übergabe-Spezifikation für die Umsetzung in WordPress + eigenem Plugin.
> Abgeleitet aus den Prototyp-Mockdaten: `app/data.js`, `partner-portal/data.jsx`,
> `admin/store.js`, `partner-onboarding/Steps.jsx`, `partner-portal/EditApp.jsx`.
> Stand: Juni 2026 (rev. 2 — Onboarding-Felder, Event-Lifecycle, Preis-Logik, Termin-Freigabe).

---

## 0. Leseanleitung

Der Prototyp besteht aus **drei getrennten Apps mit je eigenen Mockdaten**. In WordPress wird daraus
**eine** Datenquelle, die alle drei Sichten bedient:

| Prototyp-App | Sicht | Mockdaten-Datei |
|---|---|---|
| `index.html` (öffentlich) | Firma / Besucher | `app/data.js` |
| `partner-portal/` | Golfplatz | `partner-portal/data.jsx` |
| `admin.html` | Firmengolf-Team | `admin/store.js` |

Wichtig: **Anfragen** existieren im Prototyp doppelt (Portal + Admin), weil es getrennte Apps sind.
In WP ist es **ein** Datensatz, der je nach Rolle anders dargestellt wird.

**Empfohlene Architektur:**
- Inhaltsentitäten (Partner, Events, Standorte, Magazin) → **Custom Post Types + ACF**
- Transaktionsdaten (Anfragen, Termin-Abstimmung) → **eigene DB-Tabellen** (nicht Post-Meta)
- Benutzer & Rechte → **native WP-Rollen + Capabilities**

---

## 1. Entität: Partnerplatz (Golfclub)

**WP:** CPT `fg_partner` · **Quelle:** `admin/store.js → seedPartners()`

| Feld | Typ | Beispiel | Hinweis |
|---|---|---|---|
| `id` | post_id | `p1` | im Prototyp String-ID |
| `name` | title | „GC München West" | |
| `city` | text (ACF) | „München" | |
| `region` | select (ACF) | `Nord \| Ost \| Süd \| West` | Filterdimension |
| `holes` | number (ACF) | `18` | |
| `contact` | text (ACF) | „Sabine Roth" | Hauptansprechpartner |
| `role` | text (ACF) | „Inhaberin" | |
| `email` | email (ACF) | event@… | |
| `phone` | text (ACF) | +49 … | |
| `address` | text (ACF) | „Münchner Str. 57, …" | für Map/LocalBusiness |
| `status` | select (ACF) | `aktiv \| pausiert \| in-pruefung` | **steuert Sichtbarkeit** |
| `rating` | number (ACF) | `4.8` | 0 = noch keine |
| `joined` | date (ACF) | `2024-04-08` | |

**Geschäftsregel — Pausieren-Kaskade:**
`status = pausiert` ⇒ **alle Events dieses Partners gelten als offline** und sind öffentlich
nicht buchbar (im Marktplatz ausgeblendet). Effektiver Event-Status = `event.status`, aber wenn
der zugehörige Partner pausiert ist, überschreibt das zu „offline". Siehe `admin/Sections.jsx → eff()`.

**Relation:** 1 Partner → n Events. 1 Partner → n Benutzer (Platz-Verwalter).
**Relation:** 1 Partner → n Ansprechpartner (§1.2). 1 Partner → 1 Onboarding-Stammsatz (§1.1).

### 1.1 Onboarding-Stammdaten (Partner-Anmeldung)
**WP:** ACF-Felder auf `fg_partner` · **Quelle:** `partner-onboarding/Steps.jsx`, `Onboarding.jsx → initialData()`

| Feld | Typ | Beispiel | Hinweis |
|---|---|---|---|
| `golf_type` | select (ACF) | `course-18` | **erste Frage**, Single-Select: 18-/9-/A-B-C-/Kurzplatz, Driving-Range, Indoor-Sim, Pitch&Putt, Mini-Golf, Leading/Links. Speist die „Golfplatz"-Eckdaten-Zeile |
| `public_name` / `legal_name` | text | „GC München West" | öffentlicher + rechtl. Name |
| `website` / `short_desc` | text/textarea | | |
| `street`/`houseNo`/`zip`/`city`/`state` | text | | Adresse (keine „Region" mehr — wird abgeleitet) |
| `maps_url` | url | | optional, Pin-Feinjustierung |
| **Anfahrt & Location** | group (ACF) | | eigener Onboarding-Schritt |
| `arrival.car` | text | „15 Min ab Stadtzentrum" | |
| `arrival.parking` | text | „100 kostenfreie Parkplätze" | |
| `arrival.eStation` | bool | `true` | Ladestation E-Auto |
| `arrival.train` | text | „S2 Riem, 10 Gehminuten" | |
| `arrival.shuttle` | text | „Abholung nach Absprache" | |
| `infra` | checkbox[] (ACF) | [`driving-range`,`meeting-room`,…] | **Infrastruktur-Katalog §1.3** |
| `cap` | group (ACF) | `{min,max, …}` | Kapazitäten, **nur für gewählte `infra`** abgefragt (§1.4) |
| `formats` | checkbox[] (ACF) | [`teamevent`,…] | anbietbare Veranstaltungstypen (= Taxonomie §2.1) |
| `avail` | group | weekdays/leadTime/season | Verfügbarkeit; `leadTime` = Vorlaufzeit für **Anfragen** (nicht „Buchungen") |
| `finalNote` | textarea | | „Willst du uns noch etwas mitteilen?" (letztes Feld) |

> **Preis & Abrechnung im Onboarding hat KEINE Eingabefelder mehr** — nur Infotext: Partner hinterlegt
> Netto-Preise **pro Event** (nicht global), Firmengolf-Aufschlag kommt obendrauf, Abrechnung direkt an
> Firmengolf mit Anfragenummer. Keine USt-/Bankdaten-Abfrage im Onboarding (Rechnungsdaten kommen aus dem Portal).

### 1.2 Ansprechpartner `fg_partner_contacts`
**WP:** eigene Tabelle **oder** ACF-Repeater auf Partner · **Quelle:** `partner-portal/TeamApp.jsx`, Onboarding `contacts[]`

| Spalte | Typ | Beispiel | Hinweis |
|---|---|---|---|
| `id` | PK | | |
| `partner_id` | FK→fg_partner | `p1` | |
| `name` | varchar | „Markus Pfeiffer" | |
| `role` | varchar | „Head Pro" | aus fester Rollen-Liste (30 Golfclub-Rollen, §1.2.1) |
| `email` | varchar | pro@… | |
| `is_owner` | bool | `false` | Kontoinhaber, nicht löschbar |

> Verwaltbar im Portal (Tab **Ansprechpartner**: Hinzufügen/Bearbeiten/Entfernen) und im Onboarding.
> Diese Personen speisen die **Termin-Freigabe** (§2.3) und die **Antworten je Person** (§3.3).
> **DSGVO:** nur zur Bearbeitung von Firmenanfragen, Hinweis im UI.

#### 1.2.1 Rollen-Liste (Ansprechpartner)
Clubmanager · Geschäftsführer · Vorstand · Präsident · Schatzmeister · Sekretariat · Rezeption ·
Mitgliederverwaltung · Buchhaltung · Head Pro · Golfprofessional · Golflehrer · Golfschule · Sportwart ·
Spielleitung · Turnierleitung · Marshal · Starter · Head Greenkeeper · Greenkeeper · Course Manager ·
Gastronomiebetreiber · Restaurantleitung · Eventmanager · Pro Shop Mitarbeiter · Caddiemaster ·
Cart Verantwortlicher · Jugendwart · Captain · Mannschaftsführer · Sonstige.

### 1.3 Infrastruktur-Katalog (`infra`)
**Quelle:** `partner-onboarding/Steps.jsx → StepInfra groups`. Fünf Gruppen, Mehrfachauswahl:
- **Auf dem Platz:** 18-/9-Loch, A-B-C, Kurzplatz, Driving Range (+ überdacht/beheizt/Flutlicht/TrackMan/Toptracer), Kurzspielbereich, Übungsbunker, Indoor Simulator, Barrierearm.
- **Im Clubhaus:** Meeting-/Seminar-/Konferenz-/Workshop-/Eventraum, Golf-Shop, Duschen & Umkleiden.
- **Tagungstechnik:** Beamer, Bildschirm, Mikrofonanlage, WLAN, Flipchart, Whiteboard, Moderationsmaterial, Cateringfläche.
- **Gastronomie:** Restaurant, Clubrestaurant, Bistro, Café, Bar, Halfway-Verpflegung, Terrasse, Außenbereich, Lounge, Catering, Frühstück, Lunch, Abendessen, BBQ, Getränkepauschale, Kaffeepause.
- **Golfschule:** Golflehrer, Schnupperkurs, Platzreifekurs, Firmenkurs, Fortgeschrittenenkurs, Leihschläger, Range-Bälle.

### 1.4 Kapazitäten (`cap`)
Pflicht: `min` / `max` Teilnehmer. Zusätzlich **bedingte** Kapazitätsfelder — nur abgefragt, wenn das
zugehörige `infra` gewählt wurde: Driving Range, Indoor Simulator, Meeting-/Seminar-/Konferenz-/Workshop-/
Eventraum, Restaurant, Terrasse, Außenbereich, Lounge, Schnupperkurs, Platzreifekurs, Firmenkurs.
Zweck: bei individuellen Anfragen sofortige Eignungsprüfung. Siehe `Steps.jsx → CAP_ROWS` (`infra`→`key`-Mapping).

---

## 2. Entität: Event / Angebot

**WP:** CPT `fg_event` · **Quelle:** `app/data.js → events[]` (+ Admin-Felder aus `admin/store.js`)

| Feld | Typ | Beispiel | Hinweis |
|---|---|---|---|
| `id` | post_id | `e1` | |
| `slug` | post_name | `schnupperkurs-hamburg-wendlohe` | URL |
| `title` | title | „Schnupperkurs an einem Nachmittag" | |
| `eyebrow` | text (ACF) | „Teamevent · 3 h" | Micro-Label |
| `format` | select (ACF) | `teamevent` | → Taxonomie, siehe §2.1 |
| `formatLabel` | abgeleitet | „Teamevent" | aus Taxonomie |
| `venue` | relation (ACF) | → `fg_partner` | **Pflicht bei owner=partner** |
| `region` | abgeleitet | aus Partner | redundanzfrei aus Relation ziehen |
| `owner` | select (ACF) | `partner \| firmengolf` | siehe §2.2 |
| `duration` | text (ACF) | „3 h" / „Ganztägig" | |
| `groupMin` / `groupMax` | number (ACF) | `6` / `24` | Gruppengröße |
| `pricePerPerson` | number (ACF) | `89` | **oder** … |
| `pricePerGroup` | number (ACF) | `null` | … Gruppenpreis (eins von beiden) |
| `rating` / `reviews` | number (ACF) | `4.9` / `142` | |
| `heroImage` | image (ACF) | … | |
| `gallery` | gallery (ACF) | […] | |
| `tags` | text[] (ACF/repeater) | [„Einsteigerfreundlich", …] | Chips |
| `summary` | textarea (ACF) | „Noch nie einen Schläger…" | |
| `includes` | repeater (ACF) | [„90 Min. Coaching", …] | Leistungs-Liste |
| `spotsLeft` | number (ACF) | `4` | „nur noch X Plätze" |
| `featured` | bool (ACF) | `true` | Startseite |
| `status` | select (ACF) | `entwurf \| in-pruefung \| published \| paused` | **Lifecycle §2.3** |
| `views` | number (meta) | `1240` | **Analytics, kein Inhalt** |
| `bookings` | number (meta) | `18` | **Analytics, kein Inhalt** |

### 2.3 Angebots-Lifecycle & Termin-Freigabe
**Quelle:** `partner-portal/App.jsx → OFFER_STATUS/offerActions`, `EditApp.jsx → estatusActions`.

- **Status-Werte:** `entwurf → in-pruefung → published → paused` (+ Löschen).
  Nach „Veröffentlichen" geht ein Angebot zuerst in **`in-pruefung`** (Prüfung durch Firmengolf), dann
  `published`. Vom Platz aus: published ↔ paused (Pausieren/Reaktivieren), zurück auf entwurf, löschen.
- **Termin-Freigabe-Konfiguration** (`release_mode`, pro Angebot, beim Veröffentlichen abgefragt):
  - `us` → Anfragen gibt nur der Platz selbst frei.
  - `approve` → zusätzlich definierte Personen (aus §1.2 schnell wählbar **oder** ad-hoc Name+E-Mail).
    Diese Personen werden bei jeder Anfrage zur Terminabstimmung benachrichtigt (§3.3/§7).

### 2.4 Angebots-Inhalt (Editor)
**Quelle:** `partner-portal/EditApp.jsx`.

| Feld | Typ | Hinweis |
|---|---|---|
| `includes` | repeater | **Inkludierte Leistungen** (Chips) — vorbelegt aus `infra`, per Dropdown erweiterbar |
| `dayflow` | textarea | **„So läuft der Tag ab"** — Ablauf-Freitext (Ankunft → Heimfahrt) |
| `price_mode` | enum | `gesamt \| einzel` |
| `gesamt_amount` + `gesamt_basis` | number + enum | Gesamtpreis · `person \| pauschal` |
| `line_items` | repeater | bei `einzel`: {label, cost} (z. B. Golflehrer 80 €, Meetingraum 50 €) |
| `net_sum` | abgeleitet | Summe netto (B2B) |
| `firmengolf_markup` | konstant | **+20 %** Vermittlung → Gesamtpreis fürs Unternehmen |
| `duration_h` / `minP` / `maxP` | number | Gesamtdauer (Std.), Min/Max Teilnehmer |

> **Preis-Modell:** Partner hinterlegt **Netto** (gesamt oder Einzelposten). Firmengolf-Aufschlag (20 %)
> wird **zusätzlich** kalkuliert, nie vom Partner-Anteil abgezogen. Voller Netto-Betrag wird mit Firmengolf
> abgerechnet (Anfragenummer angeben).

### 2.1 Taxonomie `fg_event_format` (Veranstaltungstyp)
Einheitliche, kanonische Liste (Prototyp `SITE_DATA.formats`):
`teamevent · afterwork · kundenevent · gesundheitstag · offsite · networking · andere`
→ `formatLabel`: Teamevent, After-Work Golf, Kundenevent, Gesundheitstag, Offsite, Networking, Andere.

Zusätzlich „nur auf Anfrage geplante" Typen (`SITE_DATA.onRequestFormats`), die im Anfrage-Wizard
als Anlass wählbar sind, aber keine festen Listings haben:
Sommerfest, Tagung, Firmenjubiläum, Kick-off, Firmenturnier, Incentive, Charity-Event, Sponsoring, CSR.

### 2.2 `owner` — der Kern der Listing-Logik
- `owner = partner` → festes Angebot eines Golfplatzes (hat `venue`-Relation). Wird vom Platz im
  Partner-Portal gepflegt (`partner-portal/data.jsx → CATEGORIES`). **Diese Pflege ist die Quelle
  der öffentlichen Listings.**
- `owner = firmengolf` → von uns auf Anfrage geplantes Format, kein fester Platz (`venue = null`,
  Status `auf-anfrage`).

> **Konsolidierungs-Hinweis:** Im Prototyp pflegt das Portal „GC München West" 5 Angebote
> (`partner-portal/data.jsx`), die wir manuell deckungsgleich in `app/data.js` (e13–e17) gespiegelt
> haben. In WP gibt es nur **eine** Quelle: der Platz bearbeitet `fg_event`-Posts → erscheinen
> automatisch öffentlich, im Admin und im Portal.

---

## 3. Entität: Anfrage (Request) — Kernstück

**WP:** eigene Tabelle `fg_requests` (NICHT CPT) · **Quelle:** `admin/store.js → seedRequests()`
+ `partner-portal/data.jsx → REQUESTS`

### 3.1 Haupttabelle `fg_requests`

| Spalte | Typ | Beispiel | Hinweis |
|---|---|---|---|
| `id` | varchar PK | `FG-26-001` | menschenlesbare Referenz |
| `kind` | enum | `Event-Anfrage \| Individuelles Event` | **bestimmt Routing, §3.4** |
| `event_id` | FK→fg_event | `e2` / null | bei Event-Anfrage gesetzt |
| `event_label` | varchar | „Das große Firmenturnier" | Snapshot |
| `event_type` | varchar | „Firmenturnier" | Veranstaltungstyp |
| `partner_id` | FK→fg_partner | `p2` / null | null bei Individuell |
| `company` | varchar | „Quartz Labs GmbH" | |
| `contact` | varchar | „Lena Hoffmann" | |
| `email` | varchar | … | |
| `phone` | varchar | … | optional |
| `city` | varchar | „Hamburg" | |
| `group_size` | int | `24` | Teilnehmerzahl |
| `daypart` | varchar | „Ganztägig" | |
| `budget` | varchar | „€20.000 – €50.000" | aus Wizard-Bändern |
| `services` | json | [„Catering","Fotograf"] | gewünschte Leistungen |
| `note` | text | „Jubiläum, gerne mit Branding." | |
| `value` | int | `7680` | kalkulierter Richtwert (Budget-Rechner) |
| `status` | enum | `neu \| in-pruefung \| angebot \| gewonnen \| verloren \| abgeschlossen` | Pipeline. Portal-Postfach nutzt zusätzlich `bestaetigt`/`abgelehnt`/`abgeschlossen` als Anzeige-Status |
| `created` | date | `2026-05-28` | |
| `deadline` | date | `2026-05-30` | Reaktionsfrist |
| `overdue` | bool | `true` | abgeleitet: Frist überschritten & noch offen |
| `taken_over` | bool | `false` | Firmengolf hat Koordination übernommen, §3.5 |

### 3.2 Wunschtermine `fg_request_dates`
**Quelle:** `request.wishDates[]`

| Spalte | Typ | Beispiel |
|---|---|---|
| `id` | PK | |
| `request_id` | FK→fg_requests | `FG-26-001` |
| `date_label` | varchar | „Do, 18. Juni 2026" / „KW 30 (20.–24. Juli)" |
| `slot` | varchar | „Ganztägig" / „After Work" |
| `is_final` | bool | `false` | der bestätigte Termin |

> Firmen schlagen **1–3 Wunschtermine** vor (Anfrage-Wizard). Einer wird am Ende `is_final`.

### 3.3 Antworten je Termin & Person `fg_request_responses`
**Quelle:** `wishDates[].course.votes` (Portal) + `wishDates[].responses` (Portal-data.jsx)

| Spalte | Typ | Beispiel | Hinweis |
|---|---|---|---|
| `id` | PK | | |
| `request_date_id` | FK→fg_request_dates | | welcher Termin |
| `responder_role` | enum | `pro \| office \| owner \| gastro` | Rolle beim Platz |
| `responder_user_id` | FK→users | | konkrete Person |
| `response` | enum | `confirmed \| declined \| pending` | |
| `alt_date_label` | varchar | „Fr, 21. Juni 2026" / null | Alternativvorschlag |
| `responded_at` | datetime | | |

**Aggregat je Termin:** „2/3 verfügbar" = Anzahl `confirmed` / Anzahl Beteiligte.
**Person hat reagiert** = keine `pending`-Antwort dieser Person auf irgendeinem Termin offen.

### 3.4 Routing — `kind` bestimmt, wer benachrichtigt wird
**Quelle:** `admin/Sections.jsx` Routing-Banner + EventDetail/RequestWizard Erfolgsmeldungen.

- **`Event-Anfrage`** (vom Golfplatz-Angebot):
  geht an **Golfplatz** (Terminfreigabe durch alle hinterlegten Rollen: Platz · Pro · Gastro)
  **und** an **Firmengolf**. Jede beteiligte Person bekommt eine **Mail mit persönlichem Link**
  (`Termin.html?req=<id>&as=<role>`). Sobald **alle** reagiert haben und ein Termin bestätigt ist,
  wird Firmengolf benachrichtigt → Angebot & Buchung.
- **`Individuelles Event`:**
  geht **nur an Firmengolf**. Firma bekommt sofort eine **Eingangsbestätigung per Mail**.
  Wir planen selbst und wählen den passenden Platz.

### 3.5 Übernehmen (Eskalation)
Reagiert eine Partei nicht bis `deadline` (`overdue = true`), kann Firmengolf die Koordination
**übernehmen** (`taken_over = true`) und direkt mit der Firma einen Termin festlegen.
Siehe `admin/Sections.jsx → takeOver()`.

### 3.6 Beteiligte Parteien (Anzeige-Aggregat)
Der Prototyp zeigt `request.parties[]` (firma / platz / firmengolf) mit Status. Das ist **kein
eigener Speicher**, sondern eine **abgeleitete Sicht** aus `fg_request_responses` + Request-Status.
In WP als View/Query bauen, nicht als Tabelle.

### 3.7 Anfragenummer
Jede Anfrage hat eine menschenlesbare Nummer **`FG-26-001`** (Jahr + laufende Nr.). Im Portal-Postfach
oben rechts in der Detail-Ansicht **kopierbar** angezeigt. Pflicht-Referenz auf jeder Partner-Rechnung
an Firmengolf (§2.4).

---

## 4. Entität: Benutzer & Rollen

**WP:** native `wp_users` + Custom Roles · **Quelle:** `admin/store.js → seedUsers()`

| Prototyp-Rolle | WP-Rolle | Capabilities |
|---|---|---|
| `superadmin` | (Administrator / Super Admin) | alles inkl. Benutzerverwaltung & Abrechnung |
| `admin` | `fg_admin` (Custom) | Anfragen, Events, Partner verwalten |
| `redakteur` | Editor (nativ) | nur Magazin |
| `platzmanager` | `fg_partner_manager` (Custom) | nur eigener Platz im Partner-Portal |

| Feld | Typ | Hinweis |
|---|---|---|
| `name` / `email` | nativ | |
| `role` | WP-Rolle | s. o. |
| `org` | user_meta | `firmengolf` **oder** `partner_id` (z. B. `p1`) |
| `status` | user_meta | `aktiv \| eingeladen \| deaktiviert` |
| `lastActive` | nativ/meta | |

**Regel:** Ein `platzmanager` ist über `org = <partner_id>` mit genau **einem** Club verbunden und
sieht im Portal nur dessen Daten. **Firmen (Anfragende) haben KEINEN Account** — sie fragen nur an.

---

## 5. Entität: Magazin (Blog)

**WP:** native `post` (habt ihr schon) · **Quelle:** `app/data.js → posts`, `admin/store.js → posts`

| Feld | WP | Hinweis |
|---|---|---|
| `title` / `slug` / `author` / `date` | nativ | |
| `tag` | category/tag | |
| `published` | post_status | `publish`/`draft` |
| `featured` | meta/sticky | Top-Story |

Im Admin nur **aktivieren/deaktivieren** (Sichtbarkeit) — Erstellung läuft über WP-Editor.

---

## 6. Entität: SEO-Standortseiten

**WP:** CPT `fg_standort` **oder** programmatisch generiert · **Quelle:** `app/CityLanding.jsx → CITIES`

| Feld | Typ | Hinweis |
|---|---|---|
| `slug` | post_name | `muenchen` → `/golf-events/muenchen` |
| `name` / `inCity` | text | „München" / „in München" |
| `region` | select | zieht passende Partner/Events |
| `metaTitle` / `metaDesc` | text | **SEO**, pro Stadt |
| `hero` | image | |
| `sub` / `intro` | text/wysiwyg | lokaler Fließtext |
| `reasons` | repeater | 4 lokale Argumente |
| `faqs` | repeater | speist FAQPage-Schema |

**Auto-Verknüpfung:** Partnerplätze & Events werden über `region`/`venue` automatisch eingebunden
(nicht doppelt pflegen). **JSON-LD:** Service (areaServed = Stadt) + FAQPage.
**Achtung Doorway-Pages:** jede Stadt braucht echten lokalen Inhalt, sonst Google-Risiko.

---

## 7. E-Mail-Trigger (Plugin-Logik)

| Auslöser | Empfänger | Inhalt |
|---|---|---|
| Event-Anfrage eingegangen | alle Platz-Rollen (Pro/Office/Gastro) | Mail + persönlicher Termin-Link |
| Event-Anfrage eingegangen | Firma | Eingangsbestätigung |
| Individuelles Event eingegangen | Firmengolf | interne Benachrichtigung |
| Individuelles Event eingegangen | Firma | Eingangsbestätigung |
| Alle Parteien haben reagiert | Firmengolf | „bereit für Angebot" |
| Termin bestätigt | Firma + Platz | Bestätigung |
| Frist überschritten | Firmengolf | Eskalation („übernehmen") |

---

## 8. Statuswerte — Referenz (alle Enums an einem Ort)

- **Partner:** `aktiv · pausiert · in-pruefung`
- **Event:** `entwurf · in-pruefung · published · paused` (+ effektiv `offline` wenn Partner pausiert)
- **Termin-Freigabe (Angebot):** `us · approve`
- **Anfrage (Pipeline):** `neu · in-pruefung · angebot · gewonnen · verloren · abgeschlossen`
- **Termin-Antwort (Person):** `confirmed · declined · pending`
- **Benutzer:** `aktiv · eingeladen · deaktiviert`
- **Magazin:** `publish · draft`

---

## 9. Design-Tokens & Assets (für Theme)

- **Tokens:** `css/colors_and_type.css` → `theme.json` / CSS-Variablen. 128 `--*`-Tokens.
  Kernfarben: `--fairway-700 #2C5036`, `--paper-100 #FBFAF6`, `--ink-900 #0E1310`.
- **Fonts (lokal, mit ins Theme):** Bricolage Grotesque (Display), Instrument Sans (Body),
  Instrument Serif Italic (Akzent), JetBrains Mono. Alle in `fonts/`.
- **Logos:** `assets/logo/` (Wortmarke + Bildmarke, hell/dunkel).

---

## 10. Umsetzungs-Reihenfolge (Empfehlung)

1. **Theme-Foundation** — Tokens → `theme.json`, Fonts, Basis-Styles.
2. **Inhaltstypen** — CPTs `fg_partner`, `fg_event` (+ Taxonomie), `fg_standort`; ACF-Felder.
3. **Rollen** — Custom Roles + Capabilities; `org`-Bindung Platzmanager↔Partner.
4. **Anfrage-Engine** — Tabellen `fg_requests` / `_dates` / `_responses`; Routing-Logik (§3.4);
   Termin-Link-Seite; E-Mail-Trigger (§7).
5. **Templates** — öffentliche Seiten aus DB rendern (Marktplatz, Detail, Stadtseiten, Presse,
   Karriere, Partner-FAQ).
6. **Portal & Admin** — Backoffice-Views auf dieselben Daten (rollenabhängig).
7. **Seed/Migration** — Prototyp-Inhalte als Startdatensatz importieren.

---

*Referenz-Screenshots der Flows liegen in `screenshots/` — besonders `anf-coord.png`,
`anf-wishdates.png`, `termin-landing.png`, `admin-req-drawer.png`.*
