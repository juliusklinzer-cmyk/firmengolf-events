# Control Center: Inhalte, Felder und Funktionen

Stand 06.10.2026, Live-Version 1.9.295. Grundlage für die Neugestaltung des Control Centers (`/control/`).

Dieses Dokument enthält **keine Design-Vorgaben**. Es beschreibt vollständig, **welche Seiten, Inhalte, Felder, Auswahlwerte, Status und Aktionen** es gibt bzw. geben soll, damit das neue Design alles abdeckt. Die Datenanbindung erfolgt nach der Design-Übergabe im Repo.

**Zielbild:** Julius steuert die komplette Plattform aus dem Control Center: Anfragen, Partner und deren Events, Leads, Partnercodes, Finanzen, Rechnungen, Dienstleister und Nutzer. Das WordPress-Backend soll im Alltag nicht mehr nötig sein.

Kennzeichnung: **[besteht]** = gibt es heute schon im Control Center, **[neu]** = kommt dazu, **[WP]** = gibt es heute nur im WordPress-Backend und soll ins Control Center.

---

## Inhalt

1. Navigation
2. Seitenübergreifende Funktionen
3. Dashboard
4. Anfragen
5. Angebote, Kalender, Aufgaben, Postausgang
6. Partner
7. Events
8. Verzeichnis (Stammdaten-Plätze)
9. Kunden
10. Leads (Budget-Rechner)
11. Partnercodes
12. Finanzen
13. Rechnungen
14. Dienstleister
15. Nutzer
16. Einstellungen
17. Kataloge (alle Auswahlwerte)
18. Hinweise zur Umsetzung

---

## 1. Navigation

| Bereich | Eintrag | Zweck | Zähler am Eintrag |
|---|---|---|---|
| Arbeit | Dashboard [besteht] | Was heute zu tun ist | Aufgaben, die auf Julius warten |
| | Anfragen [besteht] | Alle Kundenanfragen bis zum Abschluss | neue, unbearbeitete |
| | Angebote [besteht] | Versendete Angebote und Fristen | offene, Frist läuft ab |
| | Kalender [besteht] | Termine aller Events | |
| | Aufgaben [besteht] | Eigene und abgeleitete Aufgaben | offene |
| Partner | Partner [neu aufgebaut, heute „Plätze“] | Alle echten Partner, Anmeldungen, Freigaben | neue Anmeldungen |
| | Events [neu, heute WP] | Alle Events aller Partner | Events zur Prüfung |
| | Verzeichnis [neu, heute Filter in „Plätze“] | Stammdaten-Plätze, keine Partner | |
| Kunden | Kunden [besteht] | Firmen und Ansprechpartner | |
| | Leads [neu] | Budget-Rechner und seine Leads | neue Leads |
| | Partnercodes [WP] | Multiplikatoren, Codes, Provisionen | offene Provisionen |
| Geld | Finanzen [neu, ersetzt „Geld“] | Plattform-Kennzahlen | |
| | Rechnungen [neu] | Ausgangs- und Eingangsrechnungen | offene, überfällige |
| Stamm | Dienstleister [besteht, ausbauen] | Externe Leistungserbringer | |
| | Postausgang [besteht] | Alle verschickten Mails | fehlgeschlagene |
| System | Nutzer [neu, heute WP] | Logins, Rollen, Passwörter | |
| | Einstellungen [neu] | Firmendaten, Preise, Verbindungen | |

---

## 2. Seitenübergreifende Funktionen

- **Globale Suche** über: Vorgangsnummer (FG-26-165), Partner-Referenz (FG-P-26-012), Firma, Ansprechpartner, Mailadresse, Partnername, Ort, PLZ, Eventtitel, Rechnungsnummer, Partnercode.
- **Benutzerbereich**: angemeldeter Nutzer, Abmelden, Link zur Website.
- **Aktionen mit Folgen-Vorschau**: Jede Aktion, die Mails auslöst, zeigt vor dem Ausführen, wer welche Mail bekommt (aus der Mail-Registry). Fehlt ein Empfänger (keine Mailadresse), wird das vor der Ausführung angezeigt. Optionales oder verpflichtendes Freitextfeld für eine persönliche Nachricht, wo vorgesehen.
- **Rückmeldung nach Aktionen**: Erfolg oder Fehler, inkl. „Mail an X ist raus“ bzw. „Mail an X fehlgeschlagen“.
- **Verlauf/Aktivität** an Anfragen, Partnern, Events, Nutzern: Typ (Status, Telefonat, Notiz, Platz, Angebot, System), Text, Person, Zeitpunkt.
- **Notizen** an Anfragen, Partnern, Kunden, Dienstleistern (intern).
- **Zeitraumwahl** auf allen Kennzahlen-Ansichten: diese Woche, dieser Monat, letzter Monat, Quartal, Jahr, gesamt, frei. Mit Vergleich zum Vorzeitraum.
- **Listen**: Sortierung je Spalte, Filter, Suche, Mehrfachauswahl mit Sammelaktionen, Export als CSV (neu).
- **Leere Zustände** und **Fehlerzustände** je Liste.

---

## 3. Dashboard [besteht, erweitern]

**Arbeitslisten** (Tabs bzw. Bereiche):
| Liste | Inhalt |
|---|---|
| Wartet auf mich | Anfragen mit einer Aufgabe für Julius |
| Wartet auf andere | Anfragen, bei denen Platz, Kunde oder Dienstleister am Zug ist |
| Heute und morgen | Events mit Termin heute oder morgen |
| Verstaubt | Anfragen älter als 45 Tage ohne künftigen Termin |

Je Zeile: Vorgangsnummer, Firma, Phase, nächste Aufgabe, Dringlichkeit (jetzt / bald / warten), Eventdatum, Alter in Tagen. Aktionen: öffnen, Aufgabe erledigen, schlummern (Datum).

**Neu dazu, „Wartet auf dich“ über die Anfragen hinaus:**
- neue Partner-Anmeldungen (Status In Prüfung)
- Events zur Prüfung und Änderungen in Prüfung
- neue Leads aus dem Budget-Rechner
- Angebote, deren Frist in 2 Tagen abläuft
- überfällige Rechnungen
- fehlgeschlagene Mails
- Partner ohne Kontaktmail

**Kennzahlen des Monats**: Anfragen, Angebote, Buchungen (in % der Anfragen), gebucht netto, Marge.

**Seit gestern**: letzte 12 Aktivitäten, bis zu 5 nicht zugestellte Mails.

**Nächste Events (7 Tage)**: Datum, Uhrzeit, Firma, Partner, Personen, Vortags-Info gesendet ja/nein, fehlende Eventtag-Angaben.

---

## 4. Anfragen [besteht]

### 4.1 Liste
Spalten: Vorgangsnummer, Eingangsdatum, Firma, Ansprechpartner, Anfrageart, Event/Anlass, Teilnehmer, Wunschtermin(e), Partner, Status, Phase, Angebotswert netto, Quelle, Partnercode, letzte Änderung.
Filter: Phase, Status, Anfrageart, Quelle, Partner, Zeitraum, mit/ohne Partnercode, Suche.

### 4.2 Status und Phasen
**Status** (`request_status`): Neu · Eingang bestätigt · Verfügbarkeit wird geprüft · Platz angefragt · Teilweise verfügbar · Vollständig verfügbar · Nicht verfügbar · In Übernahme · Termin bestätigt · Telefonat offen · Telefonat erledigt · Angebot in Lexoffice · Angebot versendet · Rückfrage offen · Gebucht · Abgelehnt · Event durchgeführt · Rechnung geschrieben · Abgeschlossen · Verloren.
Abgeschlossene Zustände (nichts mehr zu tun): Abgeschlossen, Verloren, Abgelehnt, Nicht verfügbar.

**Phasen** (abgeleitet): Eingang · Termin · Angebot · Angebot läuft · Vorbereitung · Eventtag · Nachlauf · Abgelehnt · Verloren · Nicht verfügbar.

**Angebotsstatus**: Versendet, Antwort offen · Angenommen · Abgelehnt.

### 4.3 Detail
**Kopf**: Vorgangsnummer, Firma, Status, Phase, Eventdatum, Teilnehmer, Partner, nächster Schritt, Aktionen (Status setzen, Telefonat erfassen, Notiz, Angebot senden, Termin bestätigen, Vortags-Info senden, Event durchgeführt, Verloren).

**Abschnitt „Angefragt“** (nur lesen): Event, Wunschtermine, Teilnehmer, Niveau/Golf-Erfahrung, Startzeit, Leistungen am Platz, über Firmengolf, weitere Wünsche, Preis auf der Eventseite, Budget, Nachricht.

**Unternehmen**: Unternehmensname, Branche, Website, Straße und Hausnummer, PLZ, Ort, Bundesland/Region. (Eine abweichende Rechnungsadresse gibt es heute nicht, siehe 18.)

**Ansprechpartner**: Vorname, Nachname, E-Mail, Telefon, Rolle im Unternehmen, bevorzugte Kontaktart (Telefon / E-Mail / Egal).

**Event-Rahmen**: Teilnehmer erwartet, Teilnehmerspanne, Eventziel (Eventtypen), Anlass, gewünschte Region, konkreter Platz, Budgetrahmen, Golf-Erfahrung, gewünschter Startzeitpunkt, Verpflegung/Diät, Nachricht.

**Termine**: Wunschtermin 1 bis 3, alternativer Zeitraum, Uhrzeit-Wunsch (Vormittag / Nachmittag / After Work / Ganztägig / Offen), fixierter Termin.

**Zusatzwünsche**: Am Platz (Liste), Über Firmengolf (Liste), Häkchen: Golflehrer, Meetingraum, Frühstück, Lunch, Abendessen, Shuttle, Branding, Turniermodus, Schlechtwetter-Alternative, Individuelle Anpassung; sonstige Wünsche.

**Plätze (Venue-Pipeline)** je angefragtem Platz: Partner, Kontakt, Status (Idee / Angefragt / Zugesagt / Abgesagt / Gewählt), Kanal (Mail / Telefon / Portal), angefragt am, geantwortet am, Absagegrund (Termin belegt, Keine Kapazität für Gruppen, Kein Pro verfügbar, Preis passt nicht, Keine Rückmeldung, frei), Preis, Preisbasis (p. P. / pauschal), brutto/netto, Notiz, Aufschlag %, Verkaufspreis, im Angebot ja/nein, Optionstext, Termine je Wunschtermin (verfügbar / nicht verfügbar / keine Antwort, Alternativvorschlag), Leistungen je Wunsch (verfügbar, Preis, Basis, brutto/netto, Notiz, Verkaufspreis, Organisation Platz/extern), Eventtag-Details eingetragen am, Bestätigung gesendet am, Reservierung bis.
Aktionen: Platz hinzufügen, „Plätze in der Nähe“, anfragen (Mail), telefonisch erfassen, Zusage/Absage erfassen, als gewählt markieren, Katalog-Einladung, Detailabfrage senden.

**Kalkulation und Positionen**:
- Hauptposition: Eventpreis netto (leer = Preis des Events), Basis (pauschal / p. P.), Einkauf beim Platz, brutto/netto, Einkauf-Basis.
- Zusatzpositionen je Zeile: Leistung, Einkauf, brutto/netto, Basis (pauschal / p. P. / nach Verbrauch), Marge %, Organisation (Golfplatz selbst / externer Dienstleister), Dienstleister, Dienstleister-Mail, Beschreibung für den Kunden, Richtwert bei Verbrauch, Status (steht im Angebot / am Platz nicht möglich), Verkaufspreis überschreiben.
- Summen: Verkauf netto, USt., brutto, Einkauf, Marge absolut und %.

**Angebot**: Veranstaltungsort, Ablauf und Zeiten, Leistungen (je Zeile), Gültigkeit/Frist, Optionen-Modus (bis zu 3 Optionen), empfohlene Option mit Begründung, Fassungen (Version, Archiv), Kundenlink, Rabatt aus Partnercode. Aktionen: Vorschau, senden, neue Fassung, Frist verlängern, Rückfrage beantworten.

**Eventtag**: Startzeit, Treffpunkt, Ansprechpartner vor Ort, Telefon vor Ort, Golflehrer/Pro, Mitbringen, Hinweise. Status: Ablauf an Kunden gesendet, Vortags-Info gesendet (Kunde / Platz / intern), Fehler.

**Lexoffice**: Angebot erstellt (ja/nein), Angebotsnummer, Rechnung erstellt (ja/nein, setzt Status „Rechnung geschrieben“), Rechnungsnummer, Notiz.

**Quelle und Tracking** (nur lesen): Quelle, UTM Quelle/Medium/Kampagne, Partnercode, Provisionsbetrag und -status, Eingangsdatum, Einwilligung, Kit/HubSpot-Status.

**Mails**: alle Mails zu dieser Anfrage aus dem Mail-Protokoll (Mail, Empfänger, Betreff, Status, Zeitpunkt, Fehler).

---

## 5. Angebote, Kalender, Aufgaben, Postausgang [besteht]

- **Angebote**: Vorgangsnummer, Firma, Event, Termin, Wert netto, versendet am, Frist, Status (offen / angenommen / abgelehnt / abgelaufen), Rückfrage offen. Aktionen: öffnen, Frist verlängern, erinnern.
- **Kalender**: Monats-/Wochenansicht aller Events mit Termin. Eintrag: Firma, Partner, Personen, Uhrzeit, Status. Filter Partner.
- **Aufgaben**: Aufgabe, Vorgang, fällig, wer ist dran (ich / andere), erledigt. Eigene Aufgaben anlegen (Text, Vorgang, Fälligkeit).
- **Postausgang**: Zeitpunkt, Mail (Klarname aus der Registry), Empfänger, Betreff, Vorgang, Status (gesendet / fehlgeschlagen), Fehler. Filter Status, Mailart, Zeitraum. Aktion: erneut senden (wo möglich).

---

## 6. Partner [neu aufgebaut]

Echte Partner haben drei Typen: **Golfplatz**, **Golflehrer**, **Indoor-Anlage**.

### 6.1 Liste
- **Neue Anmeldungen** (Status In Prüfung) gesondert oben: Name, Typ, Ort, Anmeldedatum (`submitted_at`), eingereichte Events, Vollständigkeit (Sichtbarkeits-Checkliste erfüllt x von y), Hinweis des Partners an das Team. Aktionen: Ansehen, Freischalten, Rückfrage stellen, Ablehnen.
- **Alle Partner**: Name, Partner-Referenz, Typ, Ort, Bundesland, Status, Events live, Events in Prüfung, Aufrufe (Zeitraum), Anfragen, Buchungen, Umsatz, Hauptkontakt (Name, Mail, Telefon), Login vorhanden ja/nein, Partner seit, letzte Aktivität.
- Filter: Status, Typ, Bundesland, ohne Kontaktmail, ohne Login, mit Events in Prüfung, Suche.
- Sammelaktionen: Einladung erneut senden, exportieren.

### 6.2 Status und Aktionen
**Partner-Status**: In Prüfung · Rückfragen · Aktiv · Pausiert · Abgelehnt. (Stammdaten gehört ins Verzeichnis, siehe 8.)

| Aktueller Status | Mögliche Aktionen |
|---|---|
| In Prüfung | Freischalten, Rückfrage stellen (Text Pflicht), Ablehnen (Grund Pflicht), Bearbeiten |
| Rückfragen | Freischalten, Erinnerung senden, Ablehnen, Bearbeiten |
| Aktiv | Bearbeiten, Pausieren (Grund), öffentliche Seite ansehen, Portal-Ansicht des Partners öffnen |
| Pausiert | Wieder aktivieren, Bearbeiten, Ablehnen |
| Abgelehnt | Wieder in Prüfung nehmen |

Regeln: Pausieren pausiert automatisch alle freigegebenen Events des Partners. Freischalten setzt die Rechte Events anlegen, Events bearbeiten, Anfragen sehen, Statistik sehen.

### 6.3 Detail: Kopf
Name, Typ, Status, Partner-Referenz, Ort, Partner seit, Hauptkontakt, Login-Status, Aktionen laut 6.2.

### 6.4 Detail: Übersicht (Kennzahlen)
Zeitraum wählbar.
| Kennzahl | Quelle |
|---|---|
| Events live | Anzahl Events mit Status Freigegeben |
| Events in Prüfung / pausiert / Entwurf | Anzahl je Status |
| Aufrufe | Summe Aufrufe aller Events (Detailseiten) |
| Anfragen | Summe Anfragen aller Events, dazu Anfragen pro Monat (6-Monats-Verlauf, Veränderung zum Vormonat) |
| Buchungen | angenommene Angebote, Jahr und Verlauf |
| Umsatz | Summe netto der Buchungen über diesen Partner |
| Einkauf und Marge | Summe Einkauf beim Partner, Marge |
| Aufruf zu Anfrage | Anfragen / Aufrufe in % |
| Anfrage zu Buchung | Buchungen / Anfragen in % |
| Nächster Termin | nächstes gebuchtes Event |
| Sichtbarkeit | Checkliste mit erfüllt / fehlt (siehe 6.6) |

Tabelle „Events im Detail“: Event, Status, Aufrufe, Anfragen, Buchungen, Aufruf zu Anfrage %.

### 6.5 Detail: Events
Alle Events des Partners wie in 7.1, mit Aktionen in der Zeile (Freigeben, Ablehnen, Pausieren, Bearbeiten, Vorschau). Neues Event für den Partner anlegen.

### 6.6 Detail: Profil (bearbeitbar)
Felder je Partnertyp. Auswahlwerte in Abschnitt 17.

**Alle Typen, Basis**
- Öffentlicher Anzeigename (Pflicht)
- Website
- Öffentliche Kurzbeschreibung (Textarea)
- Partner seit (Datum)
- Sterne-Bewertung (0 bis 5)
- Kundenstimme: Zitat, Name, Rolle · Firma
- Rechtlicher Betreibername (intern)
- Interne Notiz (intern)

**Alle Typen, Standort und Anfahrt**
- Straße, Hausnummer, PLZ, Ort, Bundesland (Auswahl), Region (frei)
- Karte: Breitengrad, Längengrad (nur Deutschland, sonst PLZ-Mittelpunkt), Google-Place-ID (intern), Google-Maps-Link
- Anfahrt & Lage (Textarea)
- Mit dem Auto, Parken, Mit der Bahn, Shuttle-Service, Hotel-Tipp in der Nähe
- E-Ladestation (keine Angabe / Ja / Nein)
- Nur Indoor: Hinweis zum Eingang

**Golfplatz**
- Platztyp (Auswahl, Pflicht)
- Ausstattung (Mehrfachauswahl in 6 Gruppen: Auf dem Platz, Im Clubhaus, Tagungstechnik, Golfschule, Gastronomie, Übernachtung & Freizeit), weitere Ausstattung (Textarea)
- Kapazität: Teilnehmer min. und max. (Pflicht), dazu je nach Ausstattung Kapazität Driving Range, Indoor Simulator, Meetingraum, Seminarraum, Konferenzraum, Workshopraum, Eventraum, Restaurant, Terrasse, Außenbereich, Lounge, max. Teilnehmer Schnupperkurs, Platzreifekurs, Firmenkurs
- Veranstaltungstypen (Mehrfachauswahl Eventtypen)
- Verfügbarkeit: bevorzugte Event-Wochentage, Abend-Events (keine Angabe / Ja / Nein), Mindest-Vorlauf (7 / 14 / 20 / 30 Tage), Saison von/bis (Monat)
- Golflehrer: verknüpfte Golflehrer-Partner, Golflehrer neu anlegen (Vorname, Nachname, E-Mail, verschickt Einladung)
- Falls Indoor-Ausstattung vorhanden: Indoor-Block wie bei Indoor-Anlage

**Indoor-Anlage**
- Anlagentyp (Indoor Golf Lounge oder Studio / Simulator auf einem Golfplatz)
- Simulatoren: Anzahl Boxen (Pflicht), Personen pro Box komfortabel und maximal (Pflicht), System und Hersteller (Mehrfachauswahl), anderes System, Event-Features (Mehrfachauswahl), Linkshänder (alle Boxen / einzelne Boxen / nein), Betrieb (betreut / Self-Service mit Code / gemischt), maximal Personen gleichzeitig (Pflicht)
- Räume und Flächen (Mehrfachauswahl in 4 Gruppen), eigene Räume (bis 12 Freitext), Gesamtfläche m²
- Gastronomie (Mehrfachauswahl)
- Formate (Mehrfachauswahl Indoor-Formate)
- Öffnungszeiten je Wochentag (geöffnet ja/nein, von, bis), ganzjährig geöffnet ja/nein, geöffnet von/bis (Monat), Events außerhalb der Öffnungszeiten (ja / nein), Mindest-Vorlauf

**Golflehrer**
- Art (einzelner Golflehrer / Golfschule)
- Vorname, Nachname (Pflicht), öffentlicher Titel (Pflicht), Golfschule oder Team
- Sprachen (Mehrfachauswahl), weitere Sprachen
- Qualifikation (Mehrfachauswahl), sonstige Qualifikation, zertifiziert für Gesundheitsförderung (§ 20 SGB V) ja/nein
- Kurzprofil (max. 400 Zeichen), Über dich und deinen Unterricht, Jahre als Golflehrer (unter 3 / 3 bis 5 / 6 bis 10 / über 10)
- Heimatplatz (Pflicht, mit Verknüpfung zu einem Golfplatz-Partner), weitere Locations (Name, Bild, verknüpfter Partner), E-Mail des Clubmanagements (lädt den Club ein)
- Formate (Mehrfachauswahl Coach-Formate, Pflicht)
- Gruppengröße min./max., Anzahl Lehrer (Golfschule), Teilnehmer pro Lehrer, Leihschläger für wie viele Personen
- Gastronomie der Anlage einbinden (ja / nein / offen)
- Verfügbarkeit wie Golfplatz

**Medien**
- Galerie (Reihenfolge, erstes Bild = Titelbild), Logo bzw. Profilbild (Golflehrer), Location-Bilder
- Bildnachweis je Bild (max. 120 Zeichen)
- Bildrechte bestätigt (ja/nein, Pflicht), Hinweis zu Bildrechten

**Sichtbarkeits-Checkliste** (nur lesen):
- Golfplatz: Beschreibung, Logo, mind. 3 Fotos, mind. 5 Ausstattungsmerkmale, Anfahrt, mind. 2 Event-Kategorien
- Indoor: Beschreibung, Logo, mind. 3 Fotos, Boxen ausgefüllt, Anfahrt, mind. 1 Angebot
- Golflehrer: Kurzprofil, Profilbild, Titelbild, mind. 3 Kursfotos, Heimatplatz, mind. 1 Angebot

**Hinweis aus der Anmeldung** an das Team (nur lesen).

### 6.7 Detail: Kontakte & Logins
- **Hauptkontakt**: Name, Rolle/Funktion, E-Mail (= Login-Adresse), Telefon. Änderung der Mail nur mit Bestätigung an die neue Adresse.
- **Kontakt für Terminanfragen**: Name, E-Mail, Telefon.
- **Weitere Ansprechpartner** (Tabelle): Name, E-Mail (Pflicht), Rolle (Rollen-Katalog), Berechtigung (Standard nach Rolle / Nur informieren / Terminabstimmung), Status (aktiv / entfernt), angelegt am. Aktionen: anlegen, bearbeiten, entfernen, Magic-Link neu erzeugen.
- **Logins** (nur lesen hier): verknüpfte Nutzerkonten mit Mail, letzter Login, primärer Login ja/nein. Verwaltung in 15.
- **Einladungen**: eingeladene Personen (Mail, Name, gesendet am, Erinnerungen, angenommen am, Status gesendet / angenommen / keine Reaktion). Aktionen: einladen, erneut senden.

### 6.8 Detail: Konditionen und Abrechnung
- Steuerstatus (Regelbesteuert 19 % / Kleinunternehmer § 19)
- Standard-Aufschlag % (Default 20)
- Rechnungssteller (Rechtsträger), Rechnungsadresse (Straße und Hausnummer, PLZ, Ort, Land), Steuernummer, USt-ID, Kleinunternehmer ja/nein, IBAN, Kontoinhaber, Rechnungs-E-Mail, Zahlungsziel an uns (sofort / 7 / 14 / 30 Tage), wer stellt die Rechnung (wir selbst / andere Partei / geteilt), Hinweis zur Rechnungsstellung
- Interne Abrechnungsnotiz
- **Stornobedingungen des Platzes** [neu]: Fristen und Prozentsätze (Freitext plus strukturierte Staffel), Frist für Teilnehmer-Reduzierung, Notiz
- Vertrag/Kooperationsvereinbarung [neu]: vorhanden ja/nein, Datum, Datei

### 6.9 Detail: Verlauf
Statuswechsel, Freigaben, Mails an den Partner, Profiländerungen, Einladungen, Logins.

---

## 7. Events [neu, heute WP]

### 7.1 Liste
Spalten: Titel, Partner (oder „Firmengolf“), Eventtyp, Ort/Region, Preis (öffentliches Preislabel), Gruppengröße min.–max., Dauer, Status, Featured, Aufrufe, Anfragen, Buchungen, zuletzt geändert.
Filter: Status, Eventtyp, Partner, Partnertyp, Anbieter (Firmengolf / Partner), Region, Suche.
Oben gesondert: **Zur Prüfung** und **Änderung in Prüfung**, je Zeile Ansehen, Freigeben, Ablehnen (Grund), bei Änderungen Vergleich der geänderten Felder (vorher / nachher).
Sammelaktionen: freigeben, pausieren.

### 7.2 Status
Entwurf · Zur Prüfung · Freigegeben · Änderung in Prüfung · Pausiert · Abgelehnt.
Öffentlich sichtbar sind Freigegeben und Änderung in Prüfung (sofern Partner nicht pausiert). Partner dürfen selbst zwischen Freigegeben und Pausiert wechseln, freigeben darf nur Firmengolf.

### 7.3 Event bearbeiten
**Grunddaten**: Eventtyp (Pflicht), Anbieter (Firmengolf / Golfplatz-Partner), zugeordneter Partner, Status, Eventtitel (Pflicht, max. 80 Zeichen), Kurzbeschreibung (max. 200 Zeichen).
**Bilder**: Titelbild, Galerie (Reihenfolge).
**Preis, Dauer, Teilnehmer**: Preislogik (Gesamtpreis / Einzelauflistung), Endkundenpreis brutto, Basis (pro Person / pauschal), Einzelposten (Bezeichnung, Betrag, Basis), Dauer, Teilnehmer min./max. Berechnet: Kundenpreis netto inkl. Aufschlag, öffentliches Preislabel.
**Leistungen**: inkludierte Leistungen (Liste mit Vorschlägen aus der Ausstattung des Partners, siehe 17), daraus abgeleitet: Golflehrer, Range-Nutzung, Leihschläger, Range-Bälle, Putting/Kurzspiel, Meetingraum, Frühstück, Lunch, Abendessen, Shuttle möglich, Branding möglich.
**Ablauf**: „So läuft der Tag ab“ (Blöcke: Überschrift und Text).
**Verfügbarkeit**: verfügbare Wochentage.
**Terminabstimmung**: Modus (nur wir am Platz / Team stimmt mit ab), wer stimmt ab (Kontakte des Partners).
**Marktplatz**: Featured ja/nein, Bewertung (0 bis 5), Anzahl Bewertungen.
**SEO**: SEO-Titel, Meta-Description, Fokus-Keyword, FAQ.
**Intern**: Prüfnotiz.
**Kennzahlen** (nur lesen): Aufrufe, Anfragen, Buchungen, letzte Anfrage, Aufruf zu Anfrage %, Anfrage zu Buchung %.
Aktionen: speichern, Vorschau der öffentlichen Seite, freigeben, ablehnen, pausieren, duplizieren [neu], löschen (Papierkorb).

---

## 8. Verzeichnis (Stammdaten-Plätze) [neu als eigene Seite]

Alle Golfanlagen (721, DGV) und Simulatoren (143), die keine Partner sind.
Spalten: Name, Typ (Golfanlage / Simulator), Ort, PLZ, Bundesland, Kontaktmail, Telefon, Website, Löcher bzw. Boxen/System, Eventlocation (Ja / Eingeschränkt / Nein / Geplant), Bar, Quelle, schon angefragt (Anzahl Anfragen), Katalog-Einladung beantwortet.
Filter: Typ, Bundesland, mit/ohne Mail, Suche, Umkreis um PLZ.
Aktionen: in Anfrage als Platz vorschlagen, als Partner einladen, Kontakt bearbeiten, als „existiert nicht mehr“ markieren.

---

## 9. Kunden [besteht, ausbauen]

Kunden werden heute aus den Anfragen gebildet (Firma, sonst Mailadresse).
**Liste**: Firma, Ort, Ansprechpartner, Telefon, Mail, Anfragen, Buchungen, Umsatz netto, letzte Anfrage, Dubletten-Hinweis (gleiche Mail bei verschiedenen Firmennamen).
**Detail** [neu]: Firmendaten (Name, Branche, Website, Adresse), Ansprechpartner (alle aus den Anfragen), Anfragen, Buchungen, Rechnungen, Umsatz, Notizen, Partnercode (falls über einen Multiplikator gekommen). Aktionen: Dubletten zusammenführen, neue Anfrage für diesen Kunden anlegen.

---

## 10. Leads (Budget-Rechner) [neu]

### 10.1 Rechner
Vorschau des Budget-Rechners wie auf der Website, darunter die **Preispflege**:
- Eventtypen (Label je Typ): Teamevent, Weihnachtsfeier (Indoor), Platzreife, Firmenturnier, Kundenevent, Golfreise & Offsite (mit Tagen), Sommerfest, Nachtturnier, Andere. Je Typ: Leistungen, standardmäßig an, Pflicht.
- Leistungen (Label je Leistung, Kategorie, Preis pro Person und Pauschale je Preisniveau Günstig / Standard / Premium, Einheit pro Nacht/Tag, Anzeige bei 0: „inklusive“ / „auf Anfrage“).
- Kategorien: Golfplatz & Greenfee, Programm & Coaching, Catering, Übernachtung, Transport, Technik & Show, Foto & Content, Extras & Branding.
- Rundung (Standard 50 €), Startwerte (Teilnehmer, Typ, Niveau).

### 10.2 Lead-Liste
Spalten: Eingang, Vorgangsnummer, Mail, Eventtyp, Teilnehmer, Tage (bei Reise), Preisniveau, gewählte Leistungen, berechnetes Budget gesamt, Quelle/Seite, Status [neu].
Lead-Status [neu]: Neu · Kontaktiert · In Anfrage umgewandelt · Verloren.
Aktionen: In Anfrage umwandeln (vorausgefüllt), als kontaktiert markieren, Mail schreiben, verloren.

---

## 11. Partnercodes [WP]

**Liste**: Inhaber, Code, Ansprechpartner, Status (aktiv / pausiert), Rabatt für den Kunden %, Provision je Buchung €, gültig bis, Anfragen, Buchungen, Provision offen / abgerechnet / storniert €, Weitergabe-Link (`/?pc=CODE`, kopierbar).
**Anlegen/Bearbeiten**: Inhaber (Pflicht), Code (Vorschlag aus dem Inhaber), Ansprechpartner, E-Mail, Rabatt % (0 bis 100, Standard 5), Provision je Buchung €, Status, gültig bis, interne Notiz.
**Detail**: Anfragen mit diesem Code (Vorgang, Firma, Status, Buchung ja/nein, Provision, Provisionsstatus offen / abgerechnet / storniert, abgerechnet am).
Aktionen: als abgerechnet markieren (einzeln, alle offenen), pausieren, Link kopieren.
Regeln: Provision entsteht bei Angebotsannahme als „offen“, wird bei Abgelehnt, Verloren oder Nicht verfügbar automatisch storniert.

---

## 12. Finanzen [neu, ersetzt „Geld“]

Zeitraum wählbar, Vergleich zum Vorzeitraum.

**Trichter**: Aufrufe Website gesamt [neu] · Aufrufe Eventseiten · Anfragen · Angebote versendet · Zusagen (Buchungen) · Events durchgeführt. Je Stufe Anzahl und Umwandlungsquote zur vorherigen Stufe.

**Kennzahlen**:
- Umsatz netto (gebucht), Umsatz netto (durchgeführt)
- Einkauf (Plätze und Dienstleister), Marge absolut, Marge %
- Buchungen ohne erfassten Einkauf (Anzahl)
- durchschnittlicher Auftragswert
- durchschnittliche Teilnehmerzahl
- Umwandlung Anfrage zu Buchung
- durchschnittliche Zeit von Anfrage bis Angebot, Angebot bis Zusage
- offene Angebotssumme (Pipeline)
- Provisionen offen / abgerechnet
- Rabatte aus Partnercodes

**Aufschlüsselung** (Tabellen): nach Eventtyp, nach Partner, nach Partnertyp, nach Stadt/Region, nach Quelle (Eventseite, Landingpage, Anfrage-Seite, Partnerseite, Kontaktseite, Rückruf, Budget-Rechner, Weihnachtsfeier-Seite, Sommerfest-Seite, Deeplink, LinkedIn, Google, Manuell, Sonstiges), nach Partnercode, nach Monat.

**Tabellen aus der heutigen Geld-Seite**: erwartete Eingangsrechnungen (Vorgang, Firma, Eventdatum, Betrag), offene Provisionen (Vorgang, Inhaber, Betrag).

---

## 13. Rechnungen [neu]

**Ausgangsrechnungen an Kunden** (aus Lexoffice): Rechnungsnummer, Vorgang, Kunde, Rechnungsdatum, Leistungsdatum, netto, USt., brutto, Fälligkeit (14 Tage nach Leistung laut AGB), Status (Entwurf / Offen / Bezahlt / Überfällig), bezahlt am, Mahnstufe.
Aktionen: Rechnungsentwurf in Lexoffice erzeugen, in Lexoffice öffnen, PDF ansehen, als bezahlt markieren.

**Eingangsrechnungen** von Plätzen und Dienstleistern: Absender, Rechnungsnummer, Vorgang, Rechnungsdatum, Betrag brutto/netto, erwarteter Betrag laut Kalkulation, Abweichung, Fälligkeit, Status (erwartet / eingegangen / bezahlt), PDF.
Aktionen: Eingangsrechnung erfassen bzw. hochladen, als bezahlt markieren.

**Je Vorgang**: Verkauf, Einkauf, Marge, Provision, Status beider Rechnungsseiten.

---

## 14. Dienstleister [besteht, ausbauen]

**Liste**: Name, Art, Region, Ansprechpartner, E-Mail, Telefon, Standardpreis mit Basis, Aufträge (Anzahl), offene Eingangsrechnungen.
**Anlegen/Bearbeiten**: Name (Pflicht), Art (Golflehrer/Pro, Shuttle, Catering, Fotograf, Technik, Sonstiges), Region, Ansprechpartner, E-Mail, Telefon, Standardpreis, Basis (pro Person / pauschal), brutto/netto, Notiz.
[neu]: mehrere Leistungen mit eigenem Preis und Basis, Umkreis, Rechnungsadresse, Steuerstatus, IBAN.
**Detail**: Aufträge (Vorgang, Leistung, Termin, Einkauf, Status), Mails, Rechnungen.

---

## 15. Nutzer [neu, heute WP]

**Liste**: Name, E-Mail, Rolle, verknüpfter Partner, primärer Login des Partners ja/nein, registriert am, letzter Login, Status (aktiv / gesperrt / eingeladen).
Rollen: Administrator, Control-Center-Nutzer [neu], Partner-Login („Golfplatz Partner“), sonstige WordPress-Rollen.
Filter: Rolle, Partner, Status, Suche.

**Aktionen je Nutzer**: Passwort-Reset-Link senden, E-Mail ändern (Bestätigung an die neue Adresse), Name ändern, Rolle ändern, sperren/entsperren, Partner-Verknüpfung ansehen.
**Neuer Nutzer**: Vorname, Nachname, E-Mail, Rolle (Administrator / Control-Center-Nutzer), Einladung per Mail.
**Partner-Login**: entsteht bei der Anmeldung (Hauptkontakt) oder per Einladung. Verknüpfung Login und Partner wird angezeigt. Lösen oder Löschen eines Partner-Logins nur mit ausdrücklicher Warnung, dass der Partner seinen Portal-Zugang verliert.

---

## 16. Einstellungen [neu]

- Firmendaten: Firma, Adresse, Geschäftsführer, Registergericht, HRB, USt-ID, Bank, Mail-Adressen (events@, intern), Telefon, Google-Bewertungslink
- Preise und Aufschläge: Standard-Aufschlag %, USt. %, Preisglättung
- Budget-Rechner (Verweis auf 10.1)
- Mails: Absendername, Absenderadresse, Mail-Registry (welche Mail wann an wen, Text ansehen)
- Verbindungen: Lexoffice (API-Schlüssel, Status), Google Maps (Schlüssel, Status), Brevo/SMTP (Status), HubSpot, Kit
- Rechtliches: Links auf AGB, Datenschutz, Impressum

---

## 17. Kataloge (alle Auswahlwerte)

**Partnertyp**: Golfplatz · Golflehrer · Indoor-Anlage

**Platztyp**: 18-Loch-Platz · 27-Loch-Platz · Leading Course · Links-Platz · 9-Loch-Platz · Indoor-Simulator · Driving-Range · Kurzplatz · Pitch & Putt · Mini-Golf

**Ausstattung (Golfplatz)**
- Auf dem Platz: 18-Loch-Platz, 9-Loch-Platz, A-B-C Platz, Kurzplatz, Driving Range, Überdachte Driving Range, Beheizte Abschlagplätze, Flutlicht Range, TrackMan Range, Toptracer Range, Kurzspielbereich, Übungsbunker, Indoor Simulator, Barrierearme Anlage
- Im Clubhaus: Meetingraum, Seminarraum, Konferenzraum, Workshopraum, Eventraum, Golf-Shop, Duschen & Umkleiden
- Tagungstechnik: Beamer, Bildschirm, Mikrofonanlage, WLAN, Flipchart, Whiteboard, Moderationsmaterial, Cateringfläche
- Golfschule: Golflehrer, Schnupperkurs, Platzreifekurs, Firmenkurs, Fortgeschrittenenkurs, Leihschläger, Range-Bälle
- Gastronomie: Restaurant, Clubrestaurant, Bistro, Café, Bar, Halfway-Verpflegung, Terrasse, Außenbereich, Lounge Bereich, Catering, Frühstück, Lunch, Abendessen, BBQ, Getränkepauschale, Kaffeepause
- Übernachtung & Freizeit: Hotel oder Zimmer am Platz, Partnerhotels in der Nähe, Wellness & Spa, Sauna, Tennis, Padel, Fitnessbereich

**Eventtypen**
- Standard: Teamevent, Indoor Golf, Weihnachtsfeier, After-Work Golf, Platzreife, Kundenevent, Gesundheitstag, Workshop, Networking, Firmen-Golfturnier, Nacht-Event, Andere
- Auf Anfrage: Sommerfest, Tagung, Firmenjubiläum, Kick-off-Veranstaltung, Incentive, Charity-/CSR-Event, Sponsoring-Event

**Indoor**
- Anlagentyp: Indoor Golf Lounge oder Studio · Simulator auf einem Golfplatz
- Systeme: TrackMan, Garmin R50, Uneekor, Foresight, TruGolf, Anderes System
- Event-Features: Nearest to the Pin, Longest Drive, Minigames, Turniermodus, Berühmte Plätze spielen, Ergebnisanzeige (Leaderboard)
- Räume: Spielmöglichkeiten (Simulatorboxen, Indoor Putting-Grün, Kurzspielbereich, Übungsbunker Indoor, Minigolf) · Ausstattung (Leihschläger, Umkleide und Schließfächer) · Tagen und Arbeiten (Meetingraum, Seminarraum, Workshopraum, WLAN, Beamer, Bildschirm, Flipchart, Whiteboard) · Weitere Aktivitäten (Dart, Billard, Shuffleboard, Kicker, Bowling, Kegeln, Sim-Racing, Tischtennis)
- Formate: Indoor Platzreife, Simulator Firmenturnier, After Work Indoor Golf, Grundlagenkurs Indoor, Tagung und Workshop, Weihnachtsfeier Indoor
- Gastronomie: Bar, Getränkeauswahl, Snacks, Warme Speisen, Kaffee, Catering über Partner möglich, Eigene Speisen und Getränke erlaubt
- Betrieb: Betreut, Team vor Ort · Self-Service mit Code-Zugang · Gemischt, je nach Uhrzeit
- Linkshänder: Ja, in allen Boxen · In einzelnen Boxen · Nein

**Golflehrer**
- Art: Einzelner Golflehrer · Golfschule mit mehreren Lehrern
- Qualifikation: PGA Golfprofessional, PGA Assistant, DOSB A-Trainer Golf, DOSB B-Trainer Golf, DOSB C-Trainer Golf, Internationale PGA-Qualifikation, Sonstige Qualifikation
- Formate: Grundlagenkurs für Teams, Platzreife, Gruppen- und Kleingruppentraining, Platztraining und Course Management, Firmenevent und Teambuilding, Kurzplatz-Runde, Training an der Trackman Range, Flutlicht- und Nacht-Event, Schlägerbau und Fitting, Einzeltraining und Coaching
- Sprachen: Deutsch, Englisch, Französisch, Italienisch, Spanisch, Weitere
- Jahre: Unter 3 Jahre, 3 bis 5 Jahre, 6 bis 10 Jahre, Über 10 Jahre
- Gastronomie einbinden: Ja, Anfragen laufen direkt über die Gastronomie · Nein, ohne Gastronomie · Klären wir später

**Kontakt-Rollen**: Clubmanager, Geschäftsführer, Vorstand, Präsident, Schatzmeister, Sekretariat, Rezeption, Mitgliederverwaltung, Buchhaltung, Head Pro, Golfprofessional, Golflehrer, Golfschule, Sportwart, Spielleitung, Turnierleitung, Marshal, Starter, Head Greenkeeper, Greenkeeper, Course Manager, Gastronomiebetreiber, Gastronom, Restaurantleitung, Eventmanager, Shuttleservice, Shuttle-Unternehmen, Technik, Golfplatz, Pro Shop Mitarbeiter, Caddiemaster, Cart Verantwortlicher, Jugendwart, Captain, Mannschaftsführer, Sonstige

**Kontakt-Berechtigung**: Standard nach Rolle · Nur informieren · Terminabstimmung (Standard „Terminabstimmung“ für operative Rollen wie Clubmanager, Geschäftsführer, Sekretariat, Rezeption, Eventmanager, Gastronomie, Spiel-/Turnierleitung, Pros, Golflehrer)

**Inkludierte Leistungen (Vorschläge im Event)**
- Golf & Training: Schnupperkurs, Platzreifekurs, Firmenkurs, Fortgeschrittenenkurs, Golftraining, Range-Nutzung inkl. Bälle, TrackMan-Session, Toptracer-Session, Indoor-Simulator-Session, 18-Loch-Runde (Greenfee), 9-Loch-Runde (Greenfee), Kurzplatz-Runde, Putting- & Kurzspiel-Challenge, Leihschläger, Range-Bälle
- Räume & Tagung: Meetingraum-, Seminarraum-, Konferenzraum-, Workshopraum-, Eventraum-Nutzung, Tagungstechnik
- Verpflegung: Frühstück, Lunch, Abendessen, BBQ, Catering, Kaffeepause, Getränkepauschale, Halfway-Verpflegung
- Extras: Shuttle-Service, Begrüßungsgetränk, Urkunde & Foto-Erinnerung, Turnierorganisation, Übernachtung

**Anfrage**
- Anfrageart: Konkretes Event · Allgemeine Eventanfrage · Budget-Rechner (Lead)
- Uhrzeit-Wunsch: Vormittag · Nachmittag · After Work · Ganztägig · Offen
- Kontaktart: Telefon · E-Mail · Egal
- Kontaktseiten-Thema: Event anfragen, Individuelles Event, Partner werden, Benefit-Programm, Presse, Etwas anderes

**Dienstleister-Art**: Golflehrer/Pro · Shuttle · Catering · Fotograf · Technik · Sonstiges

**Bundesländer**: alle 16

**Wochentage**: Montag bis Sonntag

**Konstanten**: Standard-Aufschlag 20 %, USt. 19 %, Preisglättung auf Endziffer 4/9, Stufen 5 € p. P. bzw. 50 € pauschal.

---

## 18. Hinweise zur Umsetzung

Für die spätere Datenanbindung, nicht für das Design:
- Am Partner werden die Zähler „Anfragen gesamt“, „veröffentlichte Events“, „Event-Aufrufe gesamt“ und „letzte Anfrage“ heute nicht hochgezählt. Die Kennzahlen je Partner müssen aus den Events und Anfragen berechnet werden.
- Aufrufe der Website insgesamt (nicht nur Eventseiten) werden heute nicht gezählt.
- Eine vom Unternehmen abweichende **Rechnungsadresse** des Kunden gibt es an der Anfrage nicht; für Lexoffice nötig.
- Kunden sind kein eigenes Objekt, sondern werden aus Anfragen gebildet. Für Kunden-Detail, Notizen und Zusammenführen braucht es ein eigenes Kundenobjekt.
- Leads haben heute keinen eigenen Status.
- Eingangsrechnungen werden heute nur als „erwartet“ berechnet, nicht erfasst.
- Die Anmelde-Schritte „Abrechnung“, „Platztyp“ und „Heimatplatz-Details (Golflehrer)“ sind nicht mehr im Ablauf; ihre Felder gibt es noch und sollen im Partner-Detail pflegbar sein.
- Für bestehende Seiten gilt beim Umbau: Formularfelder (`name`-Attribute), Nonces, `admin-post`-Actions, URLs und PHP-Logik nicht ändern, sonst brechen Speichern und Mailversand. Dateien: `includes/cc-core.php` (Rahmen, Dashboard, Anfragen), `cc-views.php` (Angebote, Aufgaben, Geld, Postausgang), `cc-directories.php` (Plätze, Kunden), `cc-providers.php`, `cc-calendar.php`, `cc-venues-ui.php`, `cc-positions.php`, `cc-offer-options.php`, `assets/css/fge-cc.css`.
- Ergebnis bitte als eigener Branch bzw. Pull Request.
