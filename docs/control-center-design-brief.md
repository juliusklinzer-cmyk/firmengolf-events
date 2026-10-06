# Control Center: Design-Briefing

Stand 06.10.2026, Live-Version 1.9.295. Dieses Dokument ist die Vorgabe für die Design-Überarbeitung des Control Centers (`/control/`). Es beschreibt Aufbau, Seiten, Inhalte und Aktionen. Die Datenanbindung (welche Zahl woher kommt, was ein Knopf technisch auslöst) wird nach der Design-Übergabe im Repo umgesetzt. Das Design muss sie also nur sinnvoll darstellen, nicht selbst bauen.

**Ziel:** Julius steuert die komplette Plattform aus dem Control Center: Anfragen, Partner und deren Events, Leads, Finanzen, Dienstleister, Partnercodes und Nutzer. Das WordPress-Backend soll für den Alltag nicht mehr nötig sein.

---

## 1. Rahmen

### Charakter
- Internes Arbeitswerkzeug, kein Kundenauftritt. Ein Backend soll wie ein Backend aussehen: ruhig, dicht, schnell erfassbar.
- Dunkle Seitenleiste, helle Arbeitsfläche, Tabellen mit hoher Informationsdichte, klare Statusfarben.
- Die Website-Komponenten aus `DESIGN.md` gelten hier **nicht** (bewusste Ausnahme, entschieden 22.09.2026). Eigene Tokens mit Präfix `--cc-`.

### Bestehende Tokens (`assets/css/fge-cc.css`)
| Token | Wert | Verwendung |
|---|---|---|
| `--cc-bg` | #F5F6F4 | Arbeitsfläche |
| `--cc-card` | #FFFFFF | Karten, Tabellen |
| `--cc-ink` / `--cc-ink-2` / `--cc-muted` | #1A1D1A / #454C47 / #6C736E | Text |
| `--cc-line` / `--cc-line-soft` | #E3E6E1 / #EFF1ED | Linien |
| `--cc-accent` | #3B6FD4 | Links, Primäraktion |
| `--cc-side` / `--cc-side-2` / `--cc-side-ink` | #171C19 / #232B25 / #A9B2AB | Seitenleiste |
| `--cc-good` / `--cc-warn` / `--cc-bad` / `--cc-info` | #2C7A3D / #9A6B12 / #B4332B / #3B6FD4 | Status |
| `--cc-radius` | 8px | Radius |
| `--cc-sans` | Systemschrift | Schrift |

Die Tokens dürfen verfeinert werden (z. B. Abstufungen für Statusflächen). Dann bitte vollständig als Token-Liste mitliefern, keine Hex-Werte im Markup.

### Technischer Rahmen für die Umsetzung
Wo das Control Center liegt, alles unter `wordpress/wp-content/plugins/firmengolf-events/`:

| Was | Datei |
|---|---|
| Rahmen: Seitengerüst, Seitenleiste, Topbar, Seitenwahl | `includes/cc-core.php` (`fge_cc_render()`, `fge_cc_pages()`) |
| Seiten Dashboard, Anfragen, Anfrage-Detail | `includes/cc-core.php` |
| Seiten Angebote, Aufgaben, Geld, Postausgang | `includes/cc-views.php` |
| Plätze, Kunden | `includes/cc-directories.php` |
| Dienstleister | `includes/cc-providers.php` |
| Kalender | `includes/cc-calendar.php` |
| Anfrage-Detail: Plätze, Kalkulation, Optionen, Positionen | `includes/cc-venues-ui.php`, `cc-positions.php`, `cc-offer-options.php` |
| Gesamtes Aussehen | `assets/css/fge-cc.css` |

**Grenzen beim Umbau bestehender Seiten:** Markup und CSS ja. Nicht ändern: `name`-Attribute von Formularfeldern, Nonces, `admin-post`-Actions, URLs, PHP-Logik. Sonst brechen Speichern und Mailversand.

**Neue Seiten** (Partner-Detail, Leads, Partnercodes, Finanzen, Nutzer) bitte als statisches Markup mit Platzhalterdaten liefern, gern als eigene Render-Funktion mit Beispielwerten. Die Daten schließen wir danach an.

**Lieferung:** eigener Branch bzw. Pull Request, nicht direkt auf `main`. Dazu eine kurze Liste der neuen oder geänderten Komponenten.

### Sprache und Texte
- Deutsch. Intern, aber freundlich-knapp: „Freischalten“, „Rückfrage stellen“, „Pausieren“.
- **Keine Gedankenstriche oder Halbgeviertstriche** in sichtbaren Texten. Komma, Punkt oder „bis“ verwenden.
- Zahlen deutsch formatiert (1.234,56 €), Datum `Mi, 30.09.2026`, Vorgangsnummern `FG-26-165`.

### Geräte
Desktop zuerst (Arbeitsplatz). Tablet muss voll funktionieren. Smartphone: lesen und einzelne Aktionen auslösen (Status ändern, Anfrage ansehen), keine Kalkulation. Keine horizontale Scrollleiste der Seite, breite Tabellen scrollen in ihrem Container oder klappen auf Karten um.

---

## 2. Navigation (neu)

Seitenleiste mit Gruppen. Bestehende Seiten bleiben, neue kommen dazu. Zähler-Badges an den Einträgen, wo etwas auf Julius wartet.

| Gruppe | Eintrag | Status | Badge |
|---|---|---|---|
| **Arbeit** | Dashboard | besteht | |
| | Anfragen | besteht | neue, unbeantwortete |
| | Angebote | besteht | offene, Frist läuft ab |
| | Kalender | besteht | |
| | Aufgaben | besteht | offene Aufgaben für Julius |
| **Partner** | Partner | **neu aufgebaut** (heute „Plätze“) | neue Anmeldungen, Events zur Prüfung |
| | Events | **neu** | Events zur Prüfung |
| | Verzeichnis | aus „Plätze“ ausgelagert (Stammdaten: 721 Golfplätze, 143 Simulatoren) | |
| **Kunden** | Kunden | besteht | |
| | Leads | **neu** (Budget-Rechner) | neue Leads |
| | Partnercodes | **neu** | |
| **Geld** | Finanzen | **neu**, ersetzt „Geld“ | |
| | Rechnungen | **neu** | offene, überfällige |
| **Stamm** | Dienstleister | besteht, ausbauen | |
| | Postausgang | besteht | Fehler |
| **System** | Nutzer | **neu** | |
| | Einstellungen | **neu**, später | |

Topbar: Seitentitel, globale Suche (Vorgangsnummer, Firma, Platz, Person, Mail), Benutzer.

---

## 3. Gemeinsame Bausteine

Bitte einmal sauber als Komponenten gestalten, alle Seiten bauen daraus.

1. **Seitenkopf**: Titel, Untertitel (z. B. „143 Partner, davon 4 neu“), rechts Primäraktion.
2. **Detail-Kopf** für Partner, Anfrage, Kunde, Nutzer: Name, Status-Pill, Kurzfakten in einer Zeile (Ort, Typ, seit wann), rechts **Aktionsleiste** mit den Statusaktionen. Die wichtigste Aktion ist primär, der Rest sekundär oder im „Mehr“-Menü.
3. **Status-Pill** in vier Tönen: gut (`--cc-good`), wartet auf Julius (`--cc-warn`), Problem/abgelehnt (`--cc-bad`), neutral/info (`--cc-info` oder grau).
4. **KPI-Kachel**: Zahl groß, Label klein, Vergleich zum Vorzeitraum (▲ ▼ mit Farbe), optional Mini-Verlauf.
5. **Trichter** (Funnel): Aufrufe, Anfragen, Angebote, Zusagen, Umsatz mit Umwandlungsquote zwischen den Stufen.
6. **Tabelle**: kompakte Zeilen, sortierbare Spalten, Zeile anklickbar, Mehrfachauswahl mit Sammelaktion (z. B. mehrere Events freigeben), fixierter Kopf.
7. **Filterleiste**: Status-Chips mit Zählern, Typ, Zeitraum, Suche.
8. **Tabs** innerhalb einer Detailseite.
9. **Bestätigungsdialog mit Folgen-Vorschau**: Vor jeder Aktion, die Mails auslöst, steht im Dialog, wer welche Mail bekommt („Der Platz bekommt: Freischaltung mit Login-Link“). Diese Vorschau gibt es schon (Mail-Registry), sie braucht eine gute Darstellung. Fehlt ein Empfänger, muss das vor dem Klick rot sichtbar sein.
10. **Formular** in Karten-Abschnitten, Speichern-Leiste unten rechts, Hinweis bei ungespeicherten Änderungen.
11. **Leerzustände** mit einem Satz und einer Aktion („Noch keine Leads. Der Budget-Rechner liegt auf …“).
12. **Toast** nach Aktionen („Event freigegeben, Mail an den Platz ist raus“).
13. **Verlauf/Timeline**: wer hat wann was gemacht, welche Mail ging raus.

---

## 4. Seiten

### 4.1 Dashboard
- **Oben „Wartet auf dich“**: neue Anfragen, neue Partner-Anmeldungen, Events zur Prüfung, Angebote kurz vor Ablauf, überfällige Rechnungen. Jede Zeile mit direkter Aktion.
- KPI-Reihe für den laufenden Monat: Anfragen, Angebote, Zusagen, Umsatz netto, Marge.
- Nächste Events (7 Tage) mit Platz, Firma, Personen, Status der Vortags-Info.
- Neueste Aktivität (Timeline).

### 4.2 Anfragen (besteht)
Liste und Detail bleiben inhaltlich. Ziel der Überarbeitung: Auf einen Blick sehen, **in welcher Phase** die Anfrage ist und **was als Nächstes zu tun ist**. Phasen: Eingang, Termin, Angebot, Annahme, Ablauf, Vortag, Eventtag, Nachlauf. Phasenleiste oben im Detail, darunter der nächste Schritt als Karte. Der Rest (Kunde, Plätze, Kalkulation, Positionen, Mails) in Tabs oder Abschnitten.

### 4.3 Partner (neu aufgebaut)

**Liste**
- Oben getrennt: **„Neue Anmeldungen“** (Status In Prüfung) als hervorgehobene Karten mit Name, Typ, Ort, Anmeldedatum, Anzahl eingereichter Events und den Knöpfen **Ansehen / Freischalten / Ablehnen**.
- Darunter alle Partner als Tabelle: Name, Typ (Golfplatz, Golflehrer, Indoor), Ort, Status, Events live (Zahl), Aufrufe 30 Tage, Anfragen, Buchungen, Hauptkontakt, letzte Aktivität.
- Filter: Status-Chips, Typ, Bundesland, Suche. Warnhinweis „X Partner ohne Kontaktmail“ bleibt, mit Filter.

**Partner-Status** (bestehende Werte): In Prüfung, Aktiv, Rückfragen, Pausiert, Abgelehnt. Stammdaten-Plätze sind keine Partner und gehören ins Verzeichnis.

**Detail-Kopf mit Aktionen je Status**

| Aktueller Status | Aktionen |
|---|---|
| In Prüfung | **Freischalten** (primär), Rückfrage stellen, Ablehnen, Bearbeiten |
| Rückfragen | Freischalten, Erinnerung senden, Ablehnen, Bearbeiten |
| Aktiv | Bearbeiten (primär), Pausieren, Profil ansehen (öffentliche Seite), Als Partner ansehen (Portal-Ansicht) |
| Pausiert | **Wieder aktivieren** (primär), Bearbeiten, Ablehnen |
| Abgelehnt | Wieder in Prüfung nehmen |

Jede Statusaktion öffnet den Bestätigungsdialog mit Folgen-Vorschau und optionalem Freitext an den Partner (bei Rückfrage und Ablehnung Pflicht).

**Tabs im Partner-Detail**
1. **Übersicht**: KPI-Kacheln wie im Partnerportal unter „Kennzahlen“: Aufrufe der Partnerseite und der Events, Anfragen, Angebote, Buchungen, Umwandlungsquote, Umsatz über diesen Partner, Events live / in Prüfung / pausiert. Zeitraum wählbar (30 Tage, 90 Tage, Jahr, gesamt). Darunter Verlauf als einfaches Diagramm.
2. **Events**: Tabelle aller Events dieses Partners (siehe 4.4), mit Status und Aktionen direkt in der Zeile.
3. **Profil**: alle Angaben aus der Anmeldung (Name, Beschreibung, Bilder, Standort mit Karte, Anfahrt, Ausstattung, Gastronomie, bei Indoor Boxen und System, bei Golflehrern Qualifikation). Bearbeitbar.
4. **Kontakte & Logins**: Hauptkontakt, weitere Ansprechpartner mit Rolle und Recht (informieren / Terminabstimmung), verknüpfte Logins. Die Verknüpfung Login ↔ Partner wird angezeigt, aber nicht in diesem Tab geändert (Login-Verwaltung siehe 4.10).
5. **Konditionen**: Einkaufspreise, Provision bzw. Aufschlag, **Stornobedingungen des Platzes** (neu, Freitext plus Fristen), Rechnungsadresse des Platzes, Bankverbindung.
6. **Verlauf**: Statuswechsel, Mails an den Partner, Änderungen am Profil.

### 4.4 Events (neu)
- **Liste aller Events** aller Partner, plus Events, die Firmengolf selbst organisiert.
- Spalten: Titel, Partner, Typ (Teamevent, Turnier, Platzreife, Weihnachtsfeier, Indoor …), Ort, Preis p. P., Gruppengröße, Status, Aufrufe, Anfragen, zuletzt geändert.
- **Event-Status** (bestehend): Entwurf, Zur Prüfung, Freigegeben, Änderung in Prüfung, Pausiert, Abgelehnt.
- Oben hervorgehoben: „Zur Prüfung“ und „Änderung in Prüfung“, jeweils mit **Ansehen / Freigeben / Ablehnen** und bei Änderungen mit einer Vorher-Nachher-Ansicht der geänderten Felder.
- **Event bearbeiten**: eigene Seite mit Abschnitten Grunddaten, Beschreibung und Ablauf, Preise und Leistungen, Bilder, Termine und Saison, Zusatzleistungen, Sichtbarkeit. Vorschau-Knopf auf die öffentliche Eventseite.
- Sammelaktionen: mehrere freigeben, pausieren.

### 4.5 Verzeichnis (aus „Plätze“ ausgelagert)
Alle Stammdaten-Plätze (721 Golfanlagen, 143 Simulatoren), die keine Partner sind. Suche, Filter Typ und Bundesland, Kontaktdaten, „in der Nähe“, Knopf „Als Partner einladen“. Rein informativ, dicht.

### 4.6 Kunden (besteht)
Firmen und Ansprechpartner mit ihren Anfragen und Buchungen, Umsatz je Kunde, letzte Aktivität. Detailseite mit Tabs Übersicht, Anfragen, Rechnungen, Kontakte.

### 4.7 Leads (neu, Budget-Rechner)
- **Oben die Rechner-Vorschau**: der Budget-Rechner, wie Besucher ihn sehen, mit den hinterlegten Preisen je Eventtyp und Staffel. Später soll man die Preise hier pflegen können, die Bearbeitungsansicht bitte schon mitdenken (Tabelle Eventtyp × Preis p. P. × Staffeln).
- **Darunter die Lead-Liste**: Datum, Mail, Anlass/Eventtyp, Personen, Wunschtermin, Ort, Budget, Rückruf gewünscht (ja/nein), Quelle (Seite), Status.
- Lead-Status: Neu, Kontaktiert, Angebot daraus, Verloren.
- Aktionen je Lead: **In Anfrage umwandeln** (öffnet eine vorausgefüllte Anfrage), Kontaktiert markieren, Mail schreiben, Verloren.

### 4.8 Partnercodes (neu, heute nur im WordPress-Backend)
- Liste: Code, Multiplikator (Name, Firma), Provision je Buchung, Anfragen, Buchungen, Provision offen / abgerechnet / storniert, Link zum Weitergeben (Kopieren-Knopf).
- **Code anlegen / bearbeiten**: Code, Name, Kontakt, Provisionsbetrag, aktiv ja/nein.
- Detail: Anfragen mit diesem Code, Provisionen mit Status, Aktion **Als abgerechnet markieren** (einzeln und alle offenen).

### 4.9 Finanzen (neu, ersetzt „Geld“)

**Zahlen zur Plattform**, Zeitraum wählbar (Monat, Quartal, Jahr, frei), Vergleich zum Vorzeitraum:
- **Trichter**: Aufrufe (Website, Eventseiten), Anfragen, Angebote, Zusagen, durchgeführte Events.
- **KPIs**: Umsatz netto, Einkauf, Marge absolut und in Prozent, durchschnittlicher Auftragswert, **durchschnittliche Teilnehmerzahl**, Umwandlung Anfrage zu Zusage, Zeit bis zum Angebot, offene Angebotssumme (Pipeline).
- **Aufschlüsselung**: nach Eventtyp, nach Partner, nach Stadt/Region, nach Quelle (Eventseite, Kurzanfrage, Budget-Rechner, Partnercode).
- **Provisionen** an Multiplikatoren: offen, abgerechnet.

### 4.10 Rechnungen (neu)
- **Ausgangsrechnungen an Kunden** (aus Lexoffice): Rechnungsnummer, Vorgang FG-…, Kunde, Datum, Betrag brutto, Fälligkeit, Status **Entwurf / Offen / Bezahlt / Überfällig**. Überfällige oben, rot.
- **Eingangsrechnungen** von Plätzen und Dienstleistern: Absender, Vorgang, Betrag, Fälligkeit, Status offen/bezahlt, PDF.
- Je Vorgang die Gegenüberstellung: Verkauf, Einkauf, Marge.
- Aktionen: Rechnungsentwurf in Lexoffice erzeugen (später automatisch beim Status „Event durchgeführt“), in Lexoffice öffnen, als bezahlt markieren, Eingangsrechnung hochladen.

### 4.11 Dienstleister (besteht, ausbauen)
- Liste: Name, Kategorie (Catering, Fotograf, DJ, Shuttle, Golflehrer, Technik …), Region, Kontakt, Anzahl Aufträge.
- **Dienstleister anlegen / bearbeiten**: Firma, Ansprechpartner, Mail, Telefon, Kategorie, Region/Umkreis, Leistungen mit Einkaufspreisen, Rechnungsadresse, Notizen.
- Detail: Aufträge, Rechnungen, Verlauf.

### 4.12 Nutzer (neu)
- **Liste aller Nutzerkonten**: Name, Mail, Rolle (Admin, Control-Center-Nutzer, Partner-Login, weitere), verknüpfter Partner, letzter Login, Status (aktiv/gesperrt).
- Filter nach Rolle, Suche.
- **Aktionen je Nutzer**: Passwort-Reset-Link senden (ein Knopf), Mail-Adresse ändern (mit Bestätigung an die neue Adresse), Rolle ändern, sperren/entsperren.
- **Neuen Nutzer anlegen**: Name, Mail, Rolle (Admin oder Control-Center-Nutzer), Einladung per Mail.
- **Wichtig für das Design**: Die Verknüpfung eines Partner-Logins mit seinem Platz wird angezeigt. Ändern, Lösen oder Löschen eines Partner-Logins braucht einen deutlichen Warn-Dialog („Der Platz verliert damit seinen Zugang zum Portal“). Bestehende Logins dürfen nie versehentlich kaputtgehen.

### 4.13 Einstellungen (neu, später)
Platzhalter für: Firmendaten (Adresse, Bank, Steuer), Mail-Absender und Vorlagen, Preise des Budget-Rechners, Google-Bewertungslink, Lexoffice-Verbindung, Seiteninhalte. Bitte nur Gerüst und Navigation, Inhalte folgen.

### 4.14 Postausgang, Kalender, Aufgaben, Angebote (bestehen)
Inhaltlich unverändert, optisch an die neuen Bausteine angleichen (Tabelle, Filter, Status-Pills, Leerzustände).

---

## 5. Statuslisten auf einen Blick

| Bereich | Werte | Farbe |
|---|---|---|
| Partner | In Prüfung (warn), Rückfragen (warn), Aktiv (gut), Pausiert (neutral), Abgelehnt (bad) | |
| Event | Entwurf (neutral), Zur Prüfung (warn), Änderung in Prüfung (warn), Freigegeben (gut), Pausiert (neutral), Abgelehnt (bad) | |
| Lead | Neu (warn), Kontaktiert (info), Angebot daraus (gut), Verloren (neutral) | |
| Rechnung | Entwurf (neutral), Offen (info), Bezahlt (gut), Überfällig (bad) | |
| Nutzer | Aktiv (gut), Gesperrt (bad), Eingeladen (info) | |

---

## 6. Was danach hier im Repo passiert
Nach der Design-Übergabe werden die Daten angeschlossen: Kennzahlen je Partner (Aufrufe, Anfragen, Buchungen), Statusaktionen mit Mails, Event-Bearbeitung, Leads aus dem Budget-Rechner, Partnercodes, Finanzzahlen, Lexoffice-Rechnungen, Dienstleister-Formular, Nutzerverwaltung. Inhaltliche Lücken, die beim Design auffallen, bitte als Liste mitgeben statt sie mit erfundenen Daten zu füllen.
