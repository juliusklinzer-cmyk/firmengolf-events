# Onboarding-Konzept: Golflehrer & Indoor-Golfsimulatoren

Stand: 27.08.2026, Rev. 2 (Entscheidungen Julius eingearbeitet) · Status: **Plan, noch nicht umgesetzt**
Umsetzung folgt in Claude Code im Projekt `firmengolf-events` (Plugin `firmengolf-events`,
Wizard in `includes/onboarding.php`).

Ziel: Neben Golfplätzen können sich künftig auch **Golflehrer/Pros** und **Indoor-Golfanlagen
mit Simulatoren** als Partner registrieren und eigene Angebote hinterlegen. Dieses Dokument
definiert, **was auf welchem Schritt abgefragt wird und warum**.

> **Abgrenzung:** Dieses Dokument betrifft ausschließlich **Firmengolf Events**
> (firmengolf-events.de, Repo `firmengolf-events`): die Vermittlung von Firmenevents an
> Golfanlagen. Es hat nichts mit **Firmengolf Benefits** (firmengolf.app, Repos
> `Firmengolf Webseite` und `firmengolf-app`) zu tun, also dem Mitarbeiter-Benefit mit
> Mitgliedschaftsmodellen und Punktekonto. Partner, Preise, Design und Kommunikation sind
> zwei getrennte Welten. Nichts aus diesem Plan wird nach firmengolf.app übertragen.

---

## Entscheidungen Julius, 27.08.2026 (bindend für die Umsetzung)

| # | Entscheidung | Auswirkung |
|---|---|---|
| 1 | **Ein Golflehrer ist ein vollwertiger Partner wie ein Golfplatz.** Ist die Anlage, auf der er unterrichtet, kein Firmengolf-Partner, trägt er deren Rahmenbedingungen selbst ein und kann damit auch große Events inklusive Gastronomie anbieten. Er führt vor Ort aus und stellt Firmengolf die Rechnung. | Neues bedingtes Kapitel im Coach-Wizard, das die Platz-Slides wiederverwendet. Siehe Abschnitt 1, Kapitel 2. |
| 2 | **Preisglättung wird umgestellt.** 5er- und 50er-Endungen wirken unecht. Neu: immer auf eine Endziffer **4 oder 9** aufrunden. Betrifft ausschließlich firmengolf-events.de (dort liegt die Preislogik), auch für bereits veröffentlichte Angebote. | Siehe Abschnitt 4. |
| 3 | **Provision bleibt 20 Prozent**, auch auf Trainerhonorare und Simulatoren. | `FGE_MARKUP_PERCENT` bleibt unverändert. |
| 4 | **Indoor bei bestehenden Partnern:** kein zweiter Datensatz. Bestehende Golfplätze bekommen im Angebotsportal einen Reiter „Indoor-Golf" und im Onboarding einen bedingten Detailblock, sobald „Indoor Simulator" als Ausstattung gewählt ist. | Siehe Abschnitt 3. |
| 5 | **Keine Schlechtwetter-Garantie und keine Schlechtwetter-Kommunikation.** Golf ist kein Schönwettersport. Indoor ist ein eigenständiges Winter- und Ganzjahresangebot, kein Ersatz für ein verregnetes Outdoor-Event. | Slide B14 komplett neu gefasst, Format `schlechtwetter-ersatz` gestrichen. Siehe Abschnitt 5. |
| 6 | **Qualifikation wird abgefragt und klein im Profil angezeigt, ist aber keine Voraussetzung.** | A3 bleibt, kein Gate, keine Ablehnung wegen fehlender Lizenz. |
| 7 | **Keine Vermittlungsvereinbarung.** | Checkbox aus dem Review-Slide entfernt, bei allen drei Typen. |

---

## 0. Ausgangslage und Grundentscheidung

### Was heute existiert

Der bestehende Partner-Onboarding-Wizard (`fge_onboarding_manifest()`) hat 17 Slides in
3 Kapiteln und ist auf einen Golfplatz zugeschnitten:

| Kapitel | Slides | Inhalt |
|---|---|---|
| 1 Wer und wo | intro-1, golftype, basics, location, arrival, main, contacts | Platztyp, Name, Adresse mit Map-Pin, Anfahrt, Hauptkontakt, weitere Kontakte |
| 2 Was ihr könnt | intro-2, infra, gastro, capacity, formats | Ausstattungs-Katalog, Gastronomie, Kapazitäten, anbietbare Formate |
| 3 Rahmen | intro-3, avail, pricing, media, review | Saison, Wunschtage, Vorlauf, Preis-Info, Fotos, Zusammenfassung |

Kataloge in `includes/catalogs.php`, Preismodell in `includes/event-pricing.php`.

### Grundentscheidung: ein Partner-Typ-Feld statt drei Systeme

**Kein neuer Custom Post Type.** `firmengolf_partner` bekommt `_fge_partner_type` mit
`course` (Bestand, Default für alle 20 vorhandenen Anlagen), `coach` und `indoor`.

Das trägt jetzt zusätzlich Entscheidung 1: Weil ein Golflehrer dieselben Standort-,
Ausstattungs- und Gastronomie-Felder befüllen kann wie ein Platz, ist ein gemeinsames
Datenmodell nicht nur bequem, sondern Voraussetzung. Anfrage-Routing, Angebots-Engine,
Portal und Abrechnung bleiben eine Codebasis; das Manifest wird typabhängig
(`fge_onboarding_manifest( string $type )`), der Render- und Save-Dispatch bleibt bei der
Slide-id.

Kapitel-Gerüst in allen drei Varianten gleich:

1. **Wer ihr seid und wo**
2. **Was ihr anbieten könnt**
3. **Rahmenbedingungen**

### Neuer gemeinsamer Block: Abrechnung

Heute fehlt ein sauberer Rechnungsblock. Da Firmengolf die Partnerrechnung bezahlt und das
Geld beim Unternehmen eintreibt, gehört das ins Onboarding, für alle Typen gleich.
Details in **Abschnitt 6**.

---

## 1. Formular A: Golflehrer und Golf-Pros

**Leitgedanke nach Entscheidung 1:** Der Pro ist nicht der kleine Bruder des Golfplatzes,
sondern eine zweite Art von Vollpartner. Der Unterschied ist nur, **ob die Anlage, auf der er
arbeitet, schon Firmengolf-Partner ist oder nicht**. Daraus ergibt sich eine Weiche, die den
gesamten Wizard steuert.

```
A4 Hauptstandort
 ├─ ist bereits Firmengolf-Partner  → Kapitel 2 "kurz": nur eigene Leistungen (A8 bis A11)
 └─ ist kein Partner                → Kapitel 2 "voll": zusätzlich Anlage beschreiben
                                       (A5a Ausstattung, A5b Gastronomie, A5c Kapazität,
                                        A5d Anfahrt, A5e Berechtigung)
```

Im vollen Pfad übernimmt der Pro faktisch die Rolle des Platzes: Er kann Teamevents,
Kundenevents, Turniere und Verpflegung anbieten, führt vor Ort aus und rechnet alles
gegenüber Firmengolf ab. Im kurzen Pfad bleibt er Leistungserbringer neben einem
bestehenden Platzpartner.

### Kapitel 1: Wer du bist

#### A1 · Intro
„Schritt 1 · Erzähl uns von dir" · „Werde als *Golf-Pro* Teil von Firmengolf."

#### A2 · Wer meldet sich an (Single-Select) — `_fge_coach_kind`

| Option | Wirkung |
|---|---|
| Einzelner Golf-Pro oder Trainer | Standardpfad |
| Golfschule oder Pro-Team | A7 (weitere Trainer) wird Pflicht |
| Trainer fest angestellt bei einem Club | A4 wird auf eine Anlage vorbelegt, Rechnungsfrage in A13 wird angeschärft |

#### A3 · Profil und Qualifikation

| Feld | Typ | Pflicht | Schlüssel | Warum |
|---|---|---|---|---|
| Vorname, Nachname | Text | ja | `_fge_coach_first/last` | |
| Öffentlicher Titel | Text | ja | `_fge_public_golfclub_name` (wiederverwendet als Anzeigename) | „PGA Golf Professional", „Head Pro" |
| Qualifikation | Multi + Freitext | nein | `_fge_coach_quali[]` | PGA of Germany (Level A/B/C), DGV-B-Trainer, DGV-C-Trainer, DOSB-Lizenz, Kindertrainer-Lizenz, Sonstige |
| Jahre Unterrichtserfahrung | Select (unter 3 / 3–5 / 6–10 / über 10) | nein | `_fge_coach_years` | |
| Sprachen | Multi | ja | `_fge_coach_langs[]` | Internationale Teams sind ein echter Filter |
| Kurzprofil | Textarea max. 400 Zeichen | ja | `_fge_public_short_description` | |
| Website / Social | URL | nein | `_fge_website_url` | |
| Erfahrung mit Firmengruppen | Ja/Nein + ungefähre Anzahl pro Jahr | ja | `_fge_coach_corp_exp` | interne Einstufung |

**Entscheidung 6 in der Umsetzung:** Qualifikation ist optional und blockiert nichts. Sie
erscheint auf der öffentlichen Profilseite als kleine Zeile unter dem Namen, im Stil
„PGA Golf Professional · seit 2014 · Deutsch, Englisch". Keine Lizenzprüfung, keine
Ablehnung, kein Badge, das andere abwertet.

#### A4 · Wo du unterrichtest (Kernstück, steuert den weiteren Wizard) — `_fge_coach_venues[]`

Repeater, pro Eintrag:

| Feld | Typ | Pflicht | Warum |
|---|---|---|---|
| Anlage | Autocomplete gegen bestehende Firmengolf-Partner, sonst Freitext + PLZ/Ort | ja | Setzt die Weiche Partner/Nicht-Partner |
| Deine Rolle dort | Select: Haus-Pro / fest angestellt / freier Trainer mit Vereinbarung / gelegentlich nach Absprache | ja | |
| Was du dort nutzen darfst | Multi: Driving Range, Kurzspielbereich, Übungsgrün, Übungsbunker, 9-Loch, 18-Loch, Indoor-Simulator, Schulungsraum, Clubhaus, Gastronomie | ja | Basis jeder Kalkulation |
| Firmengruppen dort möglich | Select: ja / ja nach Absprache / nein | ja | Wichtigste Einzelfrage |
| Range-Bälle und Greenfee | Select: in meinem Preis enthalten / rechnet die Anlage separat ab / je nach Format | ja | Verhindert Preisüberraschungen |
| Hauptstandort | Radio, genau einer | ja | Karte, Umkreissuche, Städteseiten |

**Zusatzblock: mobiles Angebot**

Kommst du auch zum Unternehmen (Ja/Nein) · Reiseradius km · Reisekosten (enthalten /
Pauschale / pro km) · was du mitbringst (Schläger-Sets, Abschlagmatten, Fangnetz,
Putting-Matten, Zielscheiben, Launch Monitor, Beamer) · Platzbedarf für dein Setup
(z. B. „6 x 4 m, 3,5 m Deckenhöhe").

#### A5 · Hauptansprechpartner und Zugang
Wie im Platz-Wizard. Zusatz: Checkbox „Das bin ich selbst" mit Prefill aus A3.

### Kapitel 2: Deine Anlage (nur wenn der Hauptstandort kein Partner ist)

Dieses Kapitel setzt Entscheidung 1 um. Es wird **übersprungen**, wenn der in A4 markierte
Hauptstandort bereits ein Firmengolf-Partner ist. Die Slides sind technisch dieselben wie im
Platz-Wizard, nur mit angepasster Überschrift und Erklärtext.

| Slide | Wiederverwendet | Angepasster Text |
|---|---|---|
| A5a Ausstattung | `infra` (Katalog unverändert) | „Was steht dir auf der Anlage für Firmenevents zur Verfügung?" |
| A5b Gastronomie | `gastro` | „Was ist bei der Verpflegung möglich, und mit wem sprichst du das ab?" plus Feld **Ansprechpartner Gastronomie** (Name, Rolle, Telefon), weil der Pro hier vermittelt statt selbst zu kochen |
| A5c Kapazitäten | `capacity` | unverändert, bedingte Zeilen nach gewählter Ausstattung |
| A5d Anfahrt | `arrival` | unverändert |
| A5e Berechtigung | **neu** | siehe unten |

#### A5e · Berechtigung und Zuständigkeit (neuer Slide, nur im vollen Pfad)

Kein Vertrag (Entscheidung 7), sondern eine Selbstauskunft, damit klar ist, wer am Eventtag
wofür geradesteht:

| Feld | Typ | Warum |
|---|---|---|
| Ich bin berechtigt, diese Anlage und die genannten Leistungen für Firmenevents anzubieten | Checkbox, Pflicht | Ohne das kann Firmengolf nichts zusagen |
| Wer bestätigt die Verfügbarkeit der Anlage | Select: ich selbst / Sekretariat / Clubmanagement / Gastronomie | Steuert das Nachfassen bei Anfragen |
| Wer stellt Firmengolf die Rechnung für Platz und Gastronomie | Select: ich, alles in einer Rechnung / die Anlage separat / geteilt (Freitext) | Ein Rechnungsempfänger ist Firmengolf, aber es können zwei Rechnungssteller sein |
| Weiß die Anlage von deiner Anmeldung bei Firmengolf | Select: ja / noch nicht / nicht nötig | Rein intern, verhindert peinliche Überraschungen beim ersten Event |

Der Erklärtext im Slide, kundenfertig:

> Du beschreibst hier die Anlage, auf der du deine Events durchführst. Damit kannst du auch
> größere Formate mit Verpflegung anbieten, nicht nur Kurse. Wichtig ist nur, dass du die
> Leistungen wirklich anbieten darfst und alles über eine Abrechnung mit uns läuft.

#### A6 · Weitere Trainer im Team — optional, Pflicht bei „Golfschule / Pro-Team"
Name, Qualifikation, kann eigenständig eine Gruppe führen (Ja/Nein), eigener Portalzugang
gewünscht (Ja/Nein). Speist die Kapazitätsrechnung in A9.

#### A7 · Weitere Ansprechpartner ohne Zugang — optional
Wie `contacts` beim Platz. Im vollen Pfad besonders relevant (Sekretariat, Gastronomie).

### Kapitel 3: Was du anbieten kannst

#### A8 · Formate (Multi-Select) — `_fge_coach_formats[]`

Neuer Katalog `fge_catalog_coach_formats()`. Die Spalte „Voraussetzung" steuert, ob das
Format überhaupt angeboten wird:

| id | Label | Voraussetzung |
|---|---|---|
| `schnupper-team` | Schnupperkurs für Teams | Range |
| `platzreife-kompakt` | Platzreife kompakt (1 bis 2 Tage) | Range + Platz |
| `platzreife-serie` | Platzreife über mehrere Termine | Range + Platz |
| `firmenkurs-fortgeschritten` | Firmenkurs für Fortgeschrittene | Range + Platz |
| `einzeltraining` | Einzeltraining und Coaching-Gutscheine | Range |
| `turnierbegleitung` | Turnierbegleitung, Pro am Abschlag | Platz |
| `station-longest-drive` | Longest Drive oder Closest to Pin betreuen | Range/Platz |
| `station-kurzspiel` | Putting- und Kurzspiel-Station | Übungsgrün |
| `indoor-training` | Training am Simulator | Indoor |
| `theorie-regeln` | Regel- und Etikette-Theorie | Raum |
| `golf-fitness` | Golf-Fitness und Mobility-Einheit | Raum |
| `teambuilding-golf` | Teambuilding-Format mit Golfanteil | Range |
| `inhouse-golf` | In-House Golf beim Unternehmen | mobil |
| `familientag` | Kinder- und Familientag | Range |

**Im vollen Pfad kommen zusätzlich die Platz-Formate dazu** (`fge_catalog_partner_formats()`:
Teamevent, Firmenturnier, Kundenevent, Networking, After-Work, Sommerfest, Offsite,
Gesundheitstag, Charity, Nacht-Event). Das ist der praktische Kern von Entscheidung 1: Ein
Pro mit eigener Anlage soll dasselbe verkaufen dürfen wie ein Platzpartner.

#### A9 · Gruppengrößen und Betreuung — `_fge_coach_cap`

Mindest-Teilnehmerzahl · maximal, die du allein betreust · Richtwert Teilnehmer pro Trainer ·
maximal mit zusätzlichen Kollegen · Kollegen kurzfristig organisierbar (ja/nein/auf Anfrage) ·
nötiger Vorlauf dafür (3/7/14 Tage) · wie du große Gruppen aufteilst (Textarea).

Im vollen Pfad greift zusätzlich die Anlagen-Kapazität aus A5c. Die kleinere der beiden Zahlen
begrenzt die Sichtbarkeit bei Anfragen.

#### A10 · Was im Preis enthalten ist — `_fge_coach_includes[]`

Leihschläger (Anzahl Sets, Herren/Damen/Kinder/Linkshänder) · Range-Bälle · Trainingsmaterial ·
Video-Analyse oder Launch Monitor (+ System) · Urkunde oder Teilnahmebestätigung.

Bedingt bei Platzreife-Formaten: Prüfungsgebühr enthalten (Ja/Nein + Betrag) · DGV-Ausweis und
Registrierung enthalten (Ja/Nein + Betrag) · Clubmitgliedschaft Voraussetzung (Ja/Nein +
Erläuterung).

*Warum so detailliert:* Genau hier entstehen Nachverhandlungen. Der Fall Schwäbisch Hall
(Power-Paket, 12 Monate Bindung oder 499 € Eigenanteil) zeigt, dass Platzreife-Angebote ohne
diese Felder nicht vergleichbar sind.

### Kapitel 4: Rahmenbedingungen

#### A11 · Verfügbarkeit
Bevorzugte Tage · bevorzugte Tageszeiten (Vormittag, Nachmittag, Abend, Wochenende) ·
Abendtermine möglich · Outdoor-Saison von/bis · **ich unterrichte im Winter auch indoor
(Ja/Nein + wo)** · Mindest-Vorlauf (7/14/20/30 Tage) · übliche Antwortzeit (24 h / 48 h /
3 Tage) · bekannte Blocker (Textarea).

#### A12 · Preise
Erklärtext wie im Platz-Wizard (netto hinterlegen, Firmengolf schlägt 20 Prozent auf, Kunde
sieht den Endpreis), dann:

- Primäre Abrechnungseinheit: pro Stunde / pro Kurseinheit / pro Teilnehmer / Tagespauschale
- Netto-Satz Gruppentraining, Netto-Satz Einzeltraining, Netto-Tagessatz
- Aufschlag je zusätzlichem Trainer (netto)
- Anfahrtspauschale (falls mobil)
- Stornofrist: kostenfrei bis X Tage vorher, danach Y Prozent
- Im vollen Pfad zusätzlich: Netto-Preise für Platznutzung, Greenfee, Cartmiete, Verpflegung

Zur neuen Glättung siehe Abschnitt 4.

#### A13 · Abrechnung
Gemeinsamer Block, siehe Abschnitt 6. Für Pros zusätzlich: Rechnungssteller (ich selbst /
meine Firma / die Anlage rechnet mit ab / geteilt) und bei „geteilt" ein Freitext, welcher
Teil über wen läuft.

#### A14 · Medien

- **Portraitfoto: Pflicht.** Bei Personen der stärkste Konversionsfaktor.
- 3 bis 8 Fotos aus dem Training oder von der Anlage. Im vollen Pfad sind Anlagenfotos Pflicht,
  weil sie die Coverbilder der öffentlichen Seite stellen.
- Logo optional · Videolink optional · Bildrechte-Bestätigung wie im Bestand.

#### A15 · Review und Abschluss

- Zusammenfassung mit Sprungmarken
- Betriebs- oder Berufshaftpflicht vorhanden: Ja/Nein + Versicherer. **Abfrage, kein Gate**,
  analog zur Qualifikation. Dient nur der internen Einschätzung.
- Bedingt bei `familientag`: erweitertes Führungszeugnis vorhanden (Ja/Nein/beantragt)
- Datenschutzhinweis gelesen, Angaben sind korrekt
- **Keine Vermittlungsvereinbarung** (Entscheidung 7)

---

## 2. Formular B: Indoor-Golf und Simulatoren

**Leitgedanke:** Eine Indoor-Anlage ist eine Location wie ein Golfplatz, folgt aber einer
anderen Physik:

1. **Boxen statt Löcher.** Kapazität = Anzahl Simulatoren mal Personen pro Box.
2. **Öffnungszeiten statt Saison.** Ganzjährig, mit Hochsaison genau dann, wenn draußen
   Nebensaison ist. Das ist der Grund für das Projekt, nicht das Wetter (siehe Abschnitt 5).
3. **Personal ist nicht selbstverständlich.** Viele Anlagen laufen unbemannt mit Zugangscode.
   Für ein Firmenevent ist Betreuung Pflicht.
4. **Exklusivbuchung ist das eigentliche Produkt.**

### Kapitel 1: Wer ihr seid und wo

#### B1 · Intro
„Schritt 1 · Erzählt uns von eurer Anlage" · „Macht eure *Indoor-Anlage* zur Eventlocation."

#### B2 · Anlagentyp (Single-Select) — `_fge_indoor_kind`

| Option | Besonderheit |
|---|---|
| Indoor-Golfclub oder Simulator-Lounge | Standardfall |
| Indoor-Bereich eines bestehenden Golfclubs | **Sonderweg:** verweist auf den bestehenden Partner statt neuem Datensatz (Entscheidung 4) |
| Indoor-Range mit Ballflug-Tracking (Toptracer, Trackman) | größere Kapazität, anderes Preismodell |
| Golf-Bar oder Entertainment-Location | Gastro im Vordergrund |
| Event-Location mit mobilem Simulator | Simulator wird aufgebaut |
| Trainingszentrum oder Golfschule mit Simulator | naheliegende Kombi mit einem `coach`-Partner |

#### B3 · Basisdaten
Öffentlicher Name, Website, Kurzbeschreibung (max. 400 Zeichen), Betreiber/Rechtsträger.
Feldnamen identisch zum Platz.

#### B4 · Standort
Wie im Bestand plus: Etage · Aufzug vorhanden · Hinweis zum Eingang (Textarea, weil
Indoor-Anlagen oft im Gewerbepark liegen und der Eingang schwer zu finden ist).

#### B5 · Anfahrt und Parken
Parkplätze (Anzahl, kostenfrei) · Haltestelle + Gehminuten · E-Ladesäule · Barrierefreiheit ·
Taxi-/Shuttle-Hinweis. Nutzt die vorhandenen `_fge_poi_*`-Felder plus zwei neue.

#### B6 · Hauptansprechpartner · B7 · Weitere Ansprechpartner
Unverändert aus dem Platz-Wizard.

### Kapitel 2: Was ihr anbieten könnt

#### B8 · Simulatoren und Technik — `_fge_indoor_sim`

| Feld | Typ | Pflicht | Warum |
|---|---|---|---|
| Anzahl Simulator-Boxen | Stepper | ja | Basis jeder Kapazitätsrechnung |
| System / Hersteller | Multi: Trackman, Foresight GCQuad/GCHawk, Uneekor, Full Swing, SkyTrak, Garmin Approach, Toptracer, X-Golf, Golfzon, TruGolf, sonstige + Freitext | ja | Marken sind ein Verkaufsargument |
| Software-Features | Multi: Turniermodus/Scramble, **Live-Leaderboard über alle Boxen**, Longest Drive, Closest to Pin, Zielschießen, Mini-Games (Fußballgolf, Darts-Golf, Bowling), berühmte Plätze, Multiplayer je Box | ja | Das Leaderboard ist das zentrale Firmenevent-Feature |
| Personen pro Box, komfortabel | Stepper | ja | realistisch 4 |
| Personen pro Box, maximal | Stepper | ja | technisch bis 6 |
| Linkshänder möglich | Select: alle Boxen / einzelne Boxen / nein | ja | bei 20 Gästen fast immer relevant |
| Sitzgelegenheit und Tisch je Box | Ja/Nein | ja | entscheidet, ob nebenbei gegessen werden kann |
| Indoor-Puttinggrün | Ja/Nein + Fläche | nein | zweite Station bei Rotationsformaten |
| Chipping-Bereich oder Übungsbunker indoor | Ja/Nein | nein | |
| Leihschläger | Ja/Nein + Sets + Aufteilung | ja | Firmengruppen bringen nichts mit |
| Schuhwerk | Select: Straßenschuhe erlaubt / Wechselschuhe nötig / Golfschuhe ohne Spikes | ja | muss in die Einladung an die Teilnehmer |
| Umkleide und Schließfächer | Ja/Nein | nein | relevant für After-Work |

#### B9 · Räume, Fläche und Nebenaktivitäten
Neuer Katalog `fge_catalog_indoor_infra_groups()`:

- **Simulatorbereich:** Boxen, Puttinggrün, Chipping, Lounge am Simulator, Präsentations-Screen
- **Aufenthalt:** Loungebereich, Bar, Sitzbereich, Terrasse, Raucherbereich
- **Tagen und Arbeiten:** Meetingraum, Seminarraum, Workshopraum, WLAN, Beamer, Screen,
  Flipchart, Whiteboard, Mikrofonanlage, Moderationsmaterial
- **Weitere Aktivitäten:** Dart, Billard, Shuffleboard, Kicker, Bowling, Kegeln, Sim-Racing,
  Tischtennis
- **Sonstiges:** Gesamtfläche m², Musikanlage, eigene Playlist erlaubt, Deko erlaubt

Kapazitäten wie im Bestand nur für ausgewählte Bereiche (`fge_catalog_cap_rows()`-Mechanik).

#### B10 · Gastronomie

Gastronomie-Modell (eigene Küche / kleine Karte / nur Getränke / Selbstbedienung / externes
Catering erlaubt / Catering wird vermittelt / keine) · Speisen-Formate (Fingerfood, Flying
Buffet, Buffet, Menü, BBQ, Frühstück, Snacks) · Getränkepauschale möglich + Zeitfenster ·
vegetarisch, vegan, Allergien · eigene Speisen mitbringen (erlaubt / gegen Gebühr / nein) ·
Sitzplätze Gastronomie · Ausschank bis (Uhrzeit).

#### B11 · Betreuung und Personal

| Feld | Typ | Warum |
|---|---|---|
| Personal während der Öffnungszeiten | Select: immer vor Ort / zu Stoßzeiten / unbemannt mit Zugangscode | Die Frage, die Indoor von Outdoor unterscheidet |
| Betreuung bei Firmenevents | Select: inklusive / gegen Aufpreis / nicht möglich | Ohne Betreuung kein Teamevent mit Anfängern |
| Technik-Einweisung für Anfänger | Ja/Nein, inklusive Ja/Nein | |
| Golflehrer vor Ort buchbar | Select: fest im Haus / auf Anfrage / nein | Verknüpfung zu `coach`-Partnern |
| Benannter Event-Ansprechpartner am Tag | Ja/Nein | |
| Eigenes Turniermanagement (Ergebnisse, Siegerehrung) | Ja/Nein/gegen Aufpreis | hebt das Format von „Boxen mieten" ab |

#### B12 · Kapazität und Exklusivität

Maximal Personen bei Teilbuchung · Exklusivbuchung möglich (ja / ja ab X Personen / nein) ·
maximal Personen bei Exklusiv · Mindestabnahme bei Exklusiv · empfohlene Gruppengröße ·
Boxen parallel für eine Gruppe reservierbar · Rotationsempfehlung (Textarea, z. B. „4 Boxen,
4er-Teams, 25 Minuten je Runde").

#### B13 · Formate — `_fge_indoor_formats[]`

`indoor-teamevent` Indoor-Teamevent · `weihnachtsfeier` Weihnachtsfeier und Jahresabschluss ·
`longest-drive` Longest Drive oder Closest to Pin Turnier · `sim-turnier`
Simulator-Firmenturnier (Scramble, Texas) · `afterwork-indoor` After-Work Indoor Golf ·
`schnupper-indoor` Schnupperkurs Indoor · `platzreife-theorie` Platzreife-Theorie mit
Indoor-Praxis · `kundenevent-indoor` Kundenevent und Netzwerkabend · `kickoff-workshop`
Kick-off oder Workshop mit Golfteil · `gesundheitstag-indoor` Gesundheitstag und
Bewegungspause · `wintertraining` Wintertraining für Golfer im Team

**Gestrichen (Entscheidung 5):** das ursprünglich geplante Format „Schlechtwetter-Ersatz für
ein geplantes Outdoor-Event".

#### B14 · Wintersaison und kurzfristige Anfragen (ersetzt den früheren Schlechtwetter-Slide)

Die Fragen bleiben fachlich nützlich, die Verpackung ist eine andere: Es geht um
Wintergeschäft und Reaktionsfähigkeit, nicht um Wetterrettung.

| Feld | Typ | Warum |
|---|---|---|
| Wann ist bei euch Hochsaison | Monat von / bis | Wann ihr selbst schon voll seid |
| Betriebsferien oder Schließzeiten | Textarea | |
| Nehmt ihr kurzfristige Anfragen an | Select: ja bis 72 h / bis 48 h / bis 24 h / nur mit regulärem Vorlauf | Neutral formuliert, ohne Wetterbezug |
| Haltet ihr Kapazität für kurzfristige Anfragen frei | Select: ja, feste Slots / nein, nur nach Verfügbarkeit | |
| Golfanlagen in eurer Nähe | Autocomplete gegen die Partnerliste, mehrfach | Regionale Bündelung im Winterangebot, gemeinsame Städteseiten |
| Maximale Entfernung, die ihr abdeckt | Zahl km | Umkreissuche |

Was **nicht** abgefragt und **nicht** kommuniziert wird: Ausweichpreise bei Umbuchung,
Schlechtwetter-Bereitschaft, Umbuchungsfristen wegen Wetter. Begründung in Abschnitt 5.

### Kapitel 3: Rahmenbedingungen

#### B15 · Öffnungszeiten und Verfügbarkeit
Öffnungszeiten je Wochentag (von/bis, sieben Zeilen, ersetzt das Saisonfeld) · ganzjährig
geöffnet + Ausnahmen · bevorzugte Event-Tage und Zeitfenster · frühester und spätester
Event-Start · Events außerhalb der Öffnungszeiten (ja / gegen Aufpreis / nein) · Mindestdauer
einer Buchung in Stunden · Mindest-Vorlauf (7/14/20/30 Tage).

#### B16 · Preise

- Primäres Preismodell (Multi, eines als Standard): pro Box und Stunde · pro Person und
  Stunde · Personenpauschale für ein Zeitfenster · Exklusivpauschale je Zeitblock
- Netto-Preise je gewähltem Modell · Mindestabnahme netto
- Aufpreise: außerhalb der Öffnungszeiten, Feiertag, Betreuung, Leihschläger, Turniermanagement
- Storno: kostenfrei bis X Tage, danach Y Prozent · Kaution oder Anzahlung (Ja/Nein + Betrag)

**Technischer Hinweis:** `fge_event_pricing_calc()` kennt die Basis `person` und `pauschal`.
Box mal Stunde wird als Einzelposten mit Basis `pauschal` geführt (Menge im Label, z. B.
„4 Boxen à 3 Stunden"), damit die 20-Prozent-Logik unverändert greift.

#### B17 · Abrechnung
Gemeinsamer Block, Abschnitt 6.

#### B18 · Medien
Logo, Coverbild, 3 bis 10 Fotos. Hinweistext im Slide: Innenaufnahmen mit eingeschaltetem
Licht und Menschen im Bild wirken deutlich besser als leere Boxen, das ist bei Indoor der
häufigste Qualitätsmangel. Optional Videolink oder 360-Grad-Tour, Grundriss. Bildrechte.

#### B19 · Review und Abschluss
Betriebshaftpflicht (Abfrage, kein Gate) · Schankerlaubnis falls Alkoholausschank ·
Musiknutzung und GEMA geklärt (relevant bei Weihnachtsfeiern) · Datenschutzhinweis · Angaben
korrekt. **Keine Vermittlungsvereinbarung.**

---

## 3. Indoor bei bestehenden Golfplatz-Partnern (Entscheidung 4)

### Prüfergebnis zum bestehenden Formular

Ja, Indoor lässt sich heute schon angeben, aber nur als Häkchen:

| Ort | Was heute existiert |
|---|---|
| `fge_catalog_golf_types()` | `'indoor-sim' => 'Indoor-Simulator'` als Platztyp (Slide 2, Single-Select) |
| `fge_catalog_infra_groups()`, Gruppe „Auf dem Platz" | `'indoor' => 'Indoor Simulator'`, dazu `'trackman' => 'TrackMan Range'` und `'toptracer' => 'Toptracer Range'` |
| `fge_catalog_cap_rows()` | eine einzige Zeile: `'indoor'` → „Kapazität Indoor Simulator", Hinweis „Personen gleichzeitig." |

Mehr nicht. Es fehlt also alles, was ein Indoor-Angebot verkaufbar macht: Anzahl Boxen,
System, Software-Features, Linkshänder, Leihschläger, Betreuung, Exklusivbuchung, Preislogik.

### Was ergänzt wird

**a) Bedingter Detailblock im Platz-Onboarding.** Sobald in Slide `infra` das Feld
`indoor` (oder `trackman` / `toptracer`) gewählt ist, wird nach `capacity` ein zusätzlicher
Slide `indoor-detail` eingeblendet. Inhalt: die Kernfelder aus B8, B11 und B12 in gekürzter
Form:

- Anzahl Simulator-Boxen
- System / Hersteller (gleicher Katalog wie B8)
- Software-Features (gleicher Katalog, gekürzt auf die Event-relevanten)
- Personen pro Box, komfortabel und maximal
- Linkshänder möglich
- Leihschläger vorhanden
- Betreuung bei Firmenevents (inklusive / Aufpreis / nicht möglich)
- Exklusivbuchung des Indoor-Bereichs möglich (ja / ab X Personen / nein)
- Maximal Personen im Indoor-Bereich
- Indoor auch außerhalb der Golfsaison nutzbar (Ja/Nein) plus Öffnungszeiten im Winter

Gleiche Felder und Speicherschlüssel wie beim `indoor`-Partnertyp, damit Suche, Filter und
Angebotsanlage nicht zwischen zwei Datenmodellen unterscheiden müssen.

**b) Reiter „Indoor-Golf" im Partnerportal.** Bestehende Partner können dort eigene
Indoor-Angebote anlegen, mit derselben Angebotsmaske wie Outdoor-Formate, aber:
Zeitfenster statt Startzeit, Boxenanzahl als Kapazitätsgrenze, Preis wahlweise pro Box und
Stunde oder als Pauschale. Der Reiter erscheint nur, wenn der Partner Indoor in der
Ausstattung hat.

**c) Bestehende Partner nachträglich abholen.** Von den 20 Anlagen haben mehrere Indoor
in der Ausstattung, aber keine Detaildaten. Sinnvoll ist eine kurze Nachfass-Mail mit einem
Direktlink auf den neuen Detailblock im Portal, statt die Daten zu raten.

---

## 4. Preisglättung neu (Entscheidung 2)

### Heute

`fge_price_smooth()` in `includes/event-pricing.php` rundet immer auf: pro Person auf volle
5 Euro, pauschal auf volle 50 Euro. Ergebnisse wie 65, 70, 650, 700 wirken gesetzt statt
kalkuliert.

### Neu: Endziffer 4 oder 9

Der Kundenpreis wird auf die nächste Zahl aufgerundet, die auf 4 oder 9 endet. Das ist
dasselbe 5er-Raster wie bisher, nur um eins nach unten versetzt.

```php
function fge_price_smooth( float $gross, string $unit ): float {
    if ( $gross <= 0 ) {
        return 0.0;
    }
    $step = ( 'pro Person' === $unit ) ? 5 : 50;
    return (float) ( ceil( ( $gross + 1 ) / $step ) * $step - 1 );
}
```

| Netto Partner | + 20 % | alt | **neu** |
|---|---|---|---|
| 51,00 € pro Person | 61,20 € | 65 € | **64 €** |
| 40,00 € pro Person | 48,00 € | 50 € | **49 €** |
| 62,00 € pro Person | 74,40 € | 75 € | **79 €** |
| 510,00 € pauschal | 612,00 € | 650 € | **649 €** |
| 850,00 € pauschal | 1.020,00 € | 1.050 € | **1.049 €** |

Wichtige Eigenschaften, die erhalten bleiben:

- Es wird **immer aufgerundet**, das Ergebnis liegt nie unter dem Bruttopreis. Der Partner
  bekommt weiterhin exakt sein Netto, die Rundungsdifferenz ist zusätzliche Firmengolf-Marge,
  der Aufschlag beträgt mindestens 20 Prozent.
- Preise, die schon auf 4 oder 9 enden, bleiben unverändert.
- Die Umsatzsteuer kommt weiterhin oben drauf, geglättet wird der ausgewiesene Nettopreis.

**Bei der Umsetzung nicht vergessen:** In `partner-portal.php` liegt ein JS-Zwilling der
Funktion für die Live-Summenbox. Der Kommentar in `event-pricing.php` weist darauf hin. Beide
müssen identisch bleiben, sonst rechnet die Vorschau anders als das Angebot. Nach der
Umstellung sollten außerdem die bereits auf firmengolf-events.de veröffentlichten Angebote
einmal neu durchgerechnet werden. **Nur dieses Projekt ist betroffen:** firmengolf.app
(Firmengolf Benefits) hat keine Vermittlungspreise und keine Glättung.

**Variante, falls 5er-Schritte pro Person zu fein wirken:** dasselbe mit `$step = 10`
ergibt 59, 69, 79, 89. Gröber, aber noch deutlicher als Preis erkennbar. Kann später über
einen Filter umgestellt werden.

---

## 5. Wetter: Haltung und Sprachregelung (Entscheidung 5)

Das ist keine Formularfrage, sondern eine Marken- und Textregel. Sie gilt projektübergreifend,
weil sie nicht von einem Produkt handelt, sondern von Golf: firmengolf-events.de,
firmengolf.app und die App, dazu Angebote und Partnerkommunikation.

**Grundhaltung:** Golf ist kein Schönwettersport. Es gibt keine schlechte Witterung, nur
falsche Kleidung. Bei Regen wird ein Golfevent eher zum Abenteuer, an das sich das Team
erinnert. Ein Event wird ausschließlich bei Gewitter oder vergleichbarer Gefahr unterbrochen.
Üblich ist, die Phase abzuwarten und den Ablauf nach hinten zu schieben, nicht abzusagen.
Bei starkem Regen kann meist aus überdachten Abschlaghütten gespielt werden.

**Praktische Konsequenzen für Texte:**

- Keine Schlechtwetter-Garantie, keine Ausweichlocation, keine Umbuchungsversprechen wegen
  Regen. Nirgends, in keinem der drei Projekte, nicht im Angebot, nicht in der
  Partnerkommunikation.
- Indoor wird als **eigenständiges Winter- und Ganzjahresangebot** verkauft, nicht als
  Plan B. Die Botschaft lautet „Golf geht auch im Januar", nicht „falls es regnet".
- Wo Wetter angesprochen wird, gehört stattdessen ein praktischer Hinweis hin:
  wetterfeste Kleidung, Regenschirm, Wechselsachen, überdachte Abschläge, ein warmes
  Getränk im Clubhaus danach.
- Bei Gewitter greift die normale Ablaufregel des Platzes. Das gehört in die
  Veranstaltungsinformationen, nicht in die Werbung.

Diese Regel wurde zusätzlich in `firmengolf-universum/marke-und-ton.md` aufgenommen, weil sie
alle drei Projekte betrifft.

---

## 6. Gemeinsamer Abrechnungsblock (alle drei Partnertypen)

Neuer Slide `billing`, auch im Platz-Wizard nachrüstbar.

| Feld | Typ | Pflicht | Schlüssel |
|---|---|---|---|
| Rechnungssteller (Rechtsträger) | Text | ja | `_fge_billing_legal_name` |
| Rechnungsanschrift | Straße, PLZ, Ort, Land | ja | `_fge_billing_address` |
| Steuernummer | Text | eine von beiden | `_fge_billing_tax_number` |
| Umsatzsteuer-ID | Text | eine von beiden | `_fge_billing_vat_id` |
| Kleinunternehmer nach §19 UStG | Ja/Nein | ja | `_fge_billing_small_business` |
| IBAN | Text mit Formatprüfung | ja | `_fge_billing_iban` |
| Kontoinhaber | Text | ja | `_fge_billing_account_holder` |
| Rechnungs-E-Mail | E-Mail | ja | `_fge_billing_email` |
| Zahlungsziel | Select: sofort / 7 / 14 / 30 Tage | ja | `_fge_billing_terms` |
| Wer stellt die Rechnung | Select: dieser Partner / eine andere Partei (Freitext) / geteilt | ja | `_fge_billing_issuer` |
| Hinweis zur Rechnungsstellung | Textarea | nein | `_fge_billing_note` |

Erklärtext im Slide:

> So läuft die Abrechnung: Nach dem Event stellst du deine Rechnung an Firmengolf, nicht an
> das Unternehmen. Wir prüfen, zahlen dich aus und rechnen anschließend mit dem Unternehmen ab.
> Du hast damit genau einen Rechnungsempfänger und ein Zahlungsziel.

**Warum die Kleinunternehmer-Frage wichtig ist:** Bei Golfclubs ist Umsatzsteuer die Regel,
bei einzelnen Pros ist §19 UStG häufig. Eine Eingangsrechnung ohne Umsatzsteuer bei
gleichzeitiger Ausgangsrechnung mit 19 Prozent verändert die Kalkulation. Das Feld muss vor
dem ersten Angebot bekannt sein.

---

## 7. Auswirkungen auf den Rest der Plattform

1. **Ein Angebot kann mehrere Partner haben.** Ein Platzreife-Kurs auf einem Partnerplatz =
   Golfplatz + Pro. Bislang hängt eine Anfrage an genau einem Partner. Nötig ist eine Liste
   beteiligter Partner je Buchung, damit zwei Eingangsrechnungen zugeordnet werden können.
   Zwischenschritt: Der Pro wird als Zusatzposition in `extra-services.php` geführt, mit
   Partnerbezug. **Im vollen Coach-Pfad (Entscheidung 1) entfällt das Problem**, weil der Pro
   alles über eine Rechnung abwickelt.
2. **Anfrage-Routing** filtert nach Partnertyp und nach Standort. Ein Coach im vollen Pfad
   erscheint in denselben Ergebnislisten wie ein Platz.
3. **Suche und Filter** brauchen einen Typ-Filter. Kein Filter „wetterunabhängig"
   (Entscheidung 5), stattdessen „Indoor" als eigene Kategorie.
4. **Öffentliche Landingpages:** analog zu `/golfplatz-partner/` je eine Seite
   `/golflehrer-partner/` und `/indoor-partner/` mit eigener Eignungsprüfung.
   `partner-invite.php` ist dafür schon generisch genug.
5. **Portal-Navigation je Typ:** Ein Pro hat „Standorte" statt „Plätze", eine Indoor-Anlage
   hat „Boxen" statt „Startzeiten".
6. **Städteseiten und SEO:** „Indoor Golf Firmenevent München" und „Golflehrer Firmenevent
   Hamburg" sind eigene Suchintentionen mit klarer Wintersaisonalität.
7. **Doppelte Sichtbarkeit vermeiden:** Wenn ein Pro als Hauptstandort einen bestehenden
   Partnerplatz angibt, darf er auf der Platz-Detailseite als Pro auftauchen, aber nicht als
   eigener Treffer neben dem Platz in derselben Ergebnisliste.

---

## 8. Verbleibende offene Punkte

Klein und nicht blockierend, aber irgendwann zu entscheiden:

1. **Haftpflicht bei Pros:** wird abgefragt, blockiert aber nichts. Soll ein fehlender
   Versicherungsnachweis intern eine Warnung im Admin auslösen?
2. **Preisglättung Schrittweite:** 5er-Raster (64, 69, 74) oder 10er-Raster (59, 69, 79) für
   Pro-Person-Preise. Vorschlag im Dokument ist das 5er-Raster.
3. **Bestandsangebote nachrechnen:** Sollen bereits veröffentlichte Angebote automatisch auf
   die neue Glättung umgestellt werden, oder nur neue?
4. **Sichtbarkeit von Pros ohne eigene Anlage:** Erscheinen sie in der Platzsuche, in einer
   eigenen Rubrik, oder nur als Zusatzleistung im Angebot eines Platzes?

---

## 8b. Ergänzungen Julius, 28.08.2026 (bindend, aus dem Review von Schritt 2 bis 4)

1. **Typ-Wahl VOR dem Wizard.** Wer ohne typspezifischen Link auf das Onboarding kommt,
   wählt zuerst, als was er sich anmeldet (Golfplatz / Indoor-Golf / Golflehrer). Die
   Landingpages je Typ verlinken direkt in den passenden Wizard und überspringen die Wahl.
2. **Indoor-Intro-Ton:** „Mach deine Indoor-Golfanlage zur Eventlocation für Unternehmen",
   dann Vorteile, dann der Wizard (Technik, Boxen, Gastronomie, Parken).
3. **Coach-Intro-Ton:** „Erweitere dein Angebot mit Firmenevents", dann Vorteile.
4. **Coach-Pfad zusätzlich:** Zu jedem angegebenen Golfplatz werden auch BILDER
   hochgeladen (nicht nur im vollen Pfad); Portraitfoto Pflicht plus Freitext
   „Über dich und deinen Unterricht"; eigene Frage, ob der Golflehrer die Gastronomie
   der Anlage mit ins Boot holen will (Anfragen laufen dann direkt über die Gastronomie).
5. **Anmeldeprozess im Kern identisch für alle drei Typen:** E-Mail-Verifikation,
   Erstanmeldung, Zwei-Faktor-Authentifizierung, wie beim Golfplatz-Onboarding.
6. **Portal-Ansichten je Typ:** Nach dem Login sieht jeder Typ seine eigene Ansicht.
   Indoor kann z. B. After-Work, Schnupperkurs, Tagesevents und Simulator-Turniere
   anbieten, aber alle Fragen und Masken sind auf Indoor ausgerichtet, nicht auf
   Golfplatz-Ebene.

## 9. Umsetzungsreihenfolge (Vorschlag für Claude Code)

1. **Preisglättung umstellen** (`fge_price_smooth()` plus JS-Zwilling in `partner-portal.php`).
   Kleinste Änderung, sofort sichtbarer Effekt, unabhängig vom Rest.
2. `_fge_partner_type` einführen, alle bestehenden Partner auf `course` migrieren
   (`cli-migrations.php`), Admin-Spalte und Filter ergänzen.
3. `fge_onboarding_manifest()` typabhängig machen, Dispatch bleibt bei der Slide-id.
4. Gemeinsamen `billing`-Slide bauen und in alle Manifeste einhängen.
5. **Indoor-Detailblock im Platz-Onboarding** plus Portal-Reiter (Abschnitt 3). Bringt
   sofort Winterangebote von den 20 bestehenden Partnern.
6. Kataloge ergänzen: `fge_catalog_indoor_kinds()`, `fge_catalog_indoor_systems()`,
   `fge_catalog_indoor_infra_groups()`, `fge_catalog_indoor_formats()`,
   `fge_catalog_coach_formats()`, `fge_catalog_coach_quali()`.
7. Formular B (Indoor) als eigener Partnertyp.
8. Formular A (Golflehrer), zuerst der kurze Pfad, dann das bedingte Anlagen-Kapitel.
9. Landingpages und Eignungsprüfung je Typ.
10. Mehr-Partner-Angebot aus Abschnitt 7.1.
