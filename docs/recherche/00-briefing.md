# Briefing: Golfplatz-Daten für Firmengolf recherchieren

Dieses Dokument bekommt die recherchierende KI zusammen mit den fünf CSV-Vorlagen. Es beschreibt, wofür die Daten gebraucht werden, welche Regeln gelten und was jedes Feld bedeutet.

## 1 Wofür das gebraucht wird

Firmengolf vermittelt Golf-Events für Unternehmen. Kommt eine Anfrage herein, soll das System sofort die drei bis vier passenden Golfplätze in der Nähe des anfragenden Unternehmens anzeigen, und direkt darunter, was dort ein Schnupperkurs kostet, welche Gastronomie es gibt und welcher Raum für die Nachbesprechung frei wäre. Julius soll diese Plätze auf Knopfdruck anfragen können, statt jedes Mal zu recherchieren und zu telefonieren.

Daraus folgen drei Dinge, die die Recherche prägen:

- **Entfernung entscheidet.** Ohne saubere Adresse und Koordinaten ist ein Datensatz für die Zuordnung wertlos. Diese Felder haben Vorrang vor allem anderen.
- **Preise entscheiden über die Nutzbarkeit.** Ein Platz ohne Preisangabe kann nur angefragt werden. Ein Platz mit belegten Preisen kann direkt angeboten werden. Deshalb ist jede Preisangabe so wertvoll wie zehn Ausstattungsdetails.
- **Falsche Daten sind schlimmer als fehlende.** Ein erfundener Preis führt zu einem Angebot, das der Platz nicht hält. Lieber `unbekannt` als geraten.

## 2 Die zwei Datenstufen

Jeder Platz hat in `datenstatus` genau einen von zwei Werten:

| Wert | Bedeutung | Folge im System |
|---|---|---|
| `ki_recherche` | Von dir zusammengetragen, nicht vom Platz bestätigt | Wird intern als Recherchestand gekennzeichnet. Preise sind Anhaltspunkte, nie verbindlich, und gehen so nie in ein Kundenangebot. |
| `platz_bestaetigt` | Der Platz hat uns diese Angaben selbst geschickt | Preise dürfen fest angeboten werden, es braucht nur noch eine Terminabfrage. |

**Du vergibst immer `ki_recherche`.** Die zweite Stufe entsteht später, wenn der Platz uns eine Preisliste schickt. Setze `preisliste_vorhanden` auf `ja`, wenn du eine öffentlich abrufbare Preisliste oder ein PDF gefunden hast, und trag die URL in `quelle_url` ein.

## 3 Harte Regeln

1. **Nichts erfinden.** Kein Feld raten, nicht aus einem anderen Platz ableiten, nicht aus dem Namen schließen. Unbekannt heißt `unbekannt`.
2. **Jede Zeile braucht Herkunft.** `quelle_url` ist die Seite, von der die Mehrzahl der Angaben stammt. `quelle_zusatz` nennt weitere Quellen im Klartext. `stand_datum` ist der Tag des Abrufs im Format `JJJJ-MM-TT`.
3. **Sicherheit einstufen** in `sicherheit`:
   - `belegt` — steht so auf der offiziellen Website oder in einem offiziellen PDF
   - `abgeleitet` — aus mehreren offiziellen Angaben erschlossen, etwa Entfernung aus der Adresse
   - `geschaetzt` — plausibel, aber nicht belegt. Nur verwenden, wenn der Wert für die Zuordnung wirklich gebraucht wird.
4. **Was unsicher blieb, gehört in `luecken`** als kurzer Klartext: „Kurspreise nur auf Anfrage", „keine Raumangaben gefunden".
5. **Format:**
   - Spaltentrennzeichen ist das Semikolon, Zeichensatz UTF-8.
   - Mehrere Werte in einem Feld werden mit dem senkrechten Strich getrennt: `schnupperkurs|teamevent`. **Niemals Semikolon innerhalb eines Feldes.**
   - Kein Semikolon, kein Zeilenumbruch in Freitextfeldern. Kommas sind erlaubt.
   - Zahlen mit Komma als Dezimaltrennzeichen, ohne Währungszeichen: `49,00`.
   - Ja-Nein-Felder: ausschließlich `ja`, `nein` oder `unbekannt`.
   - Datumsangaben: `JJJJ-MM-TT`.
6. **Nur offizielle Quellen.** Website des Platzes, dessen PDFs, Google Business Profile, Verbandsverzeichnisse. Keine Bewertungsportale für Fakten, keine Foren, keine Social-Media-Posts.
7. **Eine Zeile je Platz** in `01-plaetze.csv`. Die `platz_id` ist ein Kleinbuchstaben-Slug ohne Umlaute, etwa `golfpark-weidenhof`, und verbindet alle fünf Tabellen. Sie muss eindeutig sein und darf sich nie ändern.

## 4 Personenbezogene Daten

Golflehrer sind Privatpersonen, kein Firmendatensatz.

- Übernimm **nur Namen und Kontaktdaten, die der Platz selbst öffentlich auf seiner Website nennt**. Keine privaten Mobilnummern, keine Adressen, nichts aus sozialen Netzwerken oder Bewertungsportalen.
- Setze `veroeffentlichung_erlaubt` auf `ja`, wenn Name und Funktion öffentlich auf der Platz-Website stehen, sonst auf `nein`.
- Im Zweifel die Zeile ohne Namen anlegen und nur Rolle und Qualifikation füllen.

## 5 Umfang und Reihenfolge

Arbeite Platz für Platz vollständig ab, statt alle Plätze halb zu füllen. Innerhalb eines Platzes gilt diese Reihenfolge:

1. Identität, Adresse, Koordinaten
2. Firmenevent-Eignung, Kapazitäten, Vorlaufzeit
3. Kurse mit Preisen (`02-kurse.csv`)
4. Gastronomie mit Preisen und Speisekarten-Link (`05-gastro.csv`)
5. Räume (`04-raeume.csv`)
6. Golflehrer (`03-pros.csv`)
7. Der Rest

Wenn ein Platz keine Firmenevents anbietet, trag ihn trotzdem ein, setze `firmenevents_moeglich` auf `nein` und lass die Detailtabellen leer. Auch das ist eine Information, die uns eine Anfrage erspart.

## 6 Die Tabellen im Einzelnen

### 01-plaetze.csv — ein Platz je Zeile

**Identität und Adresse.** `platz_id` (Slug), `name` (wie der Platz sich selbst nennt), `betreiber_name` (juristische Person, falls abweichend), `typ` (`golfclub`, `golfpark`, `golfresort`, `indoor`, `range`, `sonstige`), `website`, `telefon_zentrale`, `email_zentrale`, `strasse`, `hausnummer`, `plz`, `ort`, `bundesland`, `land` (`DE`, `AT`, `CH`).

**Koordinaten.** `lat` und `lng` als Dezimalgrad mit Punkt, vier Nachkommastellen genügen: `53.6580`. Nimm den Haupteingang oder den Parkplatz, nicht die Platzmitte. `google_maps_url` ist der Kurzlink zum Eintrag.

**Anfahrt.** `autobahn_name`, `autobahn_km` (nächste Auffahrt), `bahnhof_name`, `bahnhof_km`, `flughafen_name`, `flughafen_km`, `parkplaetze_anzahl`, `parkplatz_kostenlos`, `shuttle_moeglich` (bietet der Platz selbst einen Shuttle an), `anfahrt_hinweis` (Freitext, etwa „letzte 800 Meter Schotterweg").

**Anlage.** `golf_typen` ist eine Mehrfachauswahl aus genau diesen Werten: `course-18`, `course-27`, `course-9`, `short` (Kurzplatz), `pitch-putt`, `links`, `leading`, `range` (Driving Range), `indoor-sim`, `mini-golf`. Dazu `loecher_gesamt`, `par`, `driving_range_plaetze`, `range_ueberdacht_plaetze`, `abschlagmatten_beheizt`, `putting_green`, `chipping_green`, `uebungsbunker`, `kurzplatz_loecher`, `indoor_simulatoren_anzahl`, `simulator_system` (Herstellername, etwa Trackman oder Foresight).

**Betrieb.** `saison_von`, `saison_bis` (Monatsnamen), `oeffnungszeiten` (Freitext), `ruhetag`, `greenfee_wochentag`, `greenfee_wochenende`, `greenfee_brutto_netto` (`brutto` oder `netto`, im Zweifel `brutto`), `mitgliedschaft_noetig`, `platzreife_noetig`, `gaeste_ohne_platzreife_erlaubt`.

Das letzte Feld ist für uns besonders wichtig: Firmengruppen sind fast immer Anfänger ohne Platzreife.

**Firmenevents.** `firmenevents_moeglich`, `event_formate` als Mehrfachauswahl aus `schnupperkurs`, `platzreife`, `teamevent`, `firmenturnier`, `kundenevent`, `networking`, `afterwork`, `sommerfest`, `offsite`, `gesundheitstag`, `charity`, `nacht-event`. Dazu `max_teilnehmer_event`, `bevorzugte_wochentage` (Mehrfach, `Mo` bis `So`), `vorlaufzeit_tage`, `exklusivbuchung_moeglich`, `abendveranstaltung_moeglich`, `turnierbetrieb_moeglich`.

**Gastronomie, Überblick.** `gastro_vorhanden`, `gastro_name`, `gastro_betreiber` (Pächter, falls genannt), `gastro_telefon`, `gastro_email`, `gastro_website`, `speisekarte_url` (direkter Link auf Karte oder PDF), `gastro_plaetze_innen`, `gastro_plaetze_terrasse`, `gastro_ruhetag`, `catering_extern_erlaubt`, `vegetarisch_vegan`, `allergene_info`. Die einzelnen Angebote und Pauschalen gehören in `05-gastro.csv`.

**Räume, Überblick.** `raeume_anzahl`, `groesster_raum_personen`, `tagungspauschale_vorhanden`. Details in `04-raeume.csv`.

**Technik und Ausstattung.** `beamer`, `leinwand`, `flipchart`, `moderationskoffer`, `wlan`, `mikrofon`, `musikanlage`, `buehne`, `klimatisiert`. Gemeint ist: am Platz grundsätzlich verfügbar. Ob im einzelnen Raum, steht in `04-raeume.csv`.

**Für die Gruppe.** `leihschlaeger_verfuegbar`, `leihschlaeger_anzahl`, `leihschlaeger_preis`, `baelle_inklusive`, `umkleiden`, `duschen`, `schliessfaecher`, `barrierefrei`, `hunde_erlaubt`, `kleiderordnung`.

Anfänger bringen nichts mit. Ein Platz ohne Leihschläger für zehn Personen fällt für uns praktisch aus, deshalb ist `leihschlaeger_anzahl` wichtiger, als es aussieht.

**Marketing und Rechtliches.** `auszeichnungen`, `verband_mitglied` (etwa `DGV`), `bildquelle_url`, `bildrechte_hinweis`. Keine Bilder herunterladen, nur die Quelle notieren.

**Wer entscheidet.** Dieser Block bestimmt, wie wir den Platz ansprechen, und ist deshalb so wichtig wie die Preise.

`rechtsform` aus genau diesen Werten:

| Wert | Bedeutung |
|---|---|
| `verein` | Eingetragener Verein, Entscheidungen über Vorstand |
| `verein_mit_gmbh` | Verein mit ausgegliederter Betriebsgesellschaft |
| `gmbh` | Reine Betreibergesellschaft |
| `resort` | Teil eines Hotels oder Resorts |
| `kommunal` | Öffentliche Hand beteiligt |
| `unbekannt` | Nicht ermittelbar |

Dazu `betreibergesellschaft_name` (falls abweichend vom Club), `betreiber_weitere_anlagen` (weitere Anlagen desselben Betreibers, mit `|` getrennt, Namen genügen), `entscheider_rolle` (wer über Firmenevents entscheidet, aus der Rollenliste, etwa `Geschäftsführer` oder `Clubmanager`), `entscheidung_schnell_moeglich` (kann eine Person allein zusagen), `vorstand_noetig`.

**Wie es dem Platz geht.** Ein Club, der Mitglieder verliert, sucht Zusatzgeschäft. Ein Club mit Warteliste braucht uns nicht. Das ist unser bester Priorisierungsfilter.

`mitglieder_anzahl` als Zahl, `mitglieder_jahr` als Jahr der Angabe, `mitglieder_trend` aus `steigend`, `stabil`, `sinkend`, `unbekannt`. Dazu `warteliste` (ja/nein/unbekannt), `auslastung_einschaetzung` aus `hoch`, `mittel`, `niedrig`, `unbekannt`, `wirtschaftliche_lage` als kurzer Klartext mit Quelle, etwa „Mitgliederschwund laut Jahresbericht 2025", und `offen_fuer_firmenkunden`, wenn der Platz Firmenkunden aktiv bewirbt.

Bei diesem Block gilt die Regel gegen Raten besonders streng. Wirtschaftliche Einschätzungen ohne Beleg sind Rufschädigung, nicht Recherche. Nur eintragen, was aus Jahresberichten, Presseartikeln oder Aussagen des Clubs selbst hervorgeht, und die Quelle immer nennen.

**Firmengolf-intern.** `datenstatus` (immer `ki_recherche`), `preisliste_vorhanden`, `letzter_kontakt` (von dir immer `unbekannt`), `interne_notiz`.

**Herkunft.** `quelle_url`, `quelle_zusatz`, `stand_datum`, `sicherheit`, `luecken`.

### 02-kurse.csv — ein Kursangebot je Zeile

Jedes buchbare Format mit eigenem Preis bekommt eine Zeile: Schnupperkurs, Platzreifekurs, Firmenkurs, Teamevent-Paket.

`format` nutzt dieselben Werte wie `event_formate`. `dauer_minuten` als Zahl. `preis_pro_person` und `preis_pauschal` schließen sich nicht aus, aber mindestens eines sollte gefüllt sein. `preis_gilt_ab_personen` hält Staffelpreise fest. `inklusive_leihschlaeger`, `inklusive_baelle`, `inklusive_greenfee`, `inklusive_verpflegung`, `inklusive_urkunde` beschreiben, was im Preis steckt. `pro_erforderlich` und `anzahl_pros_noetig` sagen, ob ein Golflehrer gebraucht wird und wie viele bei voller Gruppe. `platzreife_enthalten` ist bei Platzreifekursen `ja`. `ort_des_kurses` ist `Driving Range`, `Kurzplatz`, `Platz`, `Indoor` oder eine Kombination. `schlechtwetter_alternative` ist für uns wichtig, weil Firmenevents selten verschoben werden können.

### 03-pros.csv — ein Golflehrer je Zeile

`rolle` aus `Head Pro`, `Golfprofessional`, `Golflehrer`, `Golfschule`. `qualifikation` aus `pga-pro`, `pga-assistant`, `dosb-a`, `dosb-b`, `dosb-c`, `pga-intl`, `other`. `fest_am_platz` unterscheidet Angestellte von freien Pros, `eigene_golfschule` zeigt an, ob er unter eigenem Namen auftritt. `firmenkurse_moeglich` und `max_gruppengroesse` entscheiden, ob wir ihn für eine Firmengruppe einplanen können. Preise nur, wenn öffentlich genannt. `veroeffentlichung_erlaubt` siehe Abschnitt 4.

### 04-raeume.csv — ein Raum je Zeile

`art` aus `Meetingraum`, `Seminarraum`, `Konferenzraum`, `Workshopraum`, `Eventraum`, `Restaurant`, `Terrasse`, `Lounge`, `Außenbereich`. Die Bestuhlungsvarianten `personen_reihe`, `personen_parlament`, `personen_ushape`, `personen_block`, `personen_stehend`, `personen_bankett` sind Zahlen. Fehlt eine Variante in der Quelle, bleibt sie `unbekannt`, nicht geschätzt. `blick_auf_platz` und `direkter_terrassenzugang` sind Verkaufsargumente und deshalb erfasst. Preise über `miete_halbtag`, `miete_ganztag`, `miete_stunde` oder `tagungspauschale_pro_person`, dazu `mindestverzehr`.

### 05-gastro.csv — ein Angebot je Zeile

`art` aus `Frühstück`, `Kaffeepause`, `Mittagessen`, `Abendessen`, `BBQ`, `Flying Buffet`, `Fingerfood`, `Getränkepauschale`, `Tagungspauschale`, `Sonstiges`. Bei Getränkepauschalen `getraenke_pauschale_preis` und `getraenke_pauschale_dauer` füllen. `servierart` ist `Menü`, `Buffet`, `Flying`, `à la carte` oder `Selbstbedienung`. `ort` sagt, wo serviert wird. Die Diätfelder `vegetarisch`, `vegan`, `glutenfrei`, `laktosefrei`, `halal` beantworten wir bei jeder zweiten Firmenanfrage, deshalb stehen sie einzeln da.

### 06-betreiber.csv — eine Betreibergesellschaft je Zeile

Nur für Betreiber, die mehr als eine Anlage führen oder als eigene Gesellschaft neben dem Club auftreten. `betreiber_id` ist ein Slug wie die `platz_id`. `rechtsform` nutzt dieselben Werte wie bei den Plätzen. `anlagen_platz_ids` listet die zugehörigen Plätze mit `|` getrennt, jede muss in `01-plaetze.csv` existieren. `zentraler_einkauf` und `zentrale_eventabteilung` sagen, ob Firmenevents zentral verhandelt werden, dann sind `eventabteilung_kontakt_rolle`, `eventabteilung_email` und `eventabteilung_telefon` wichtiger als jeder einzelne Platz. `gruppenkonditionen_bekannt` ist `ja`, wenn öffentlich Gruppen- oder Firmenpreise genannt werden.

Ein Gespräch mit einer Betreibergruppe öffnet mehrere Plätze auf einmal. Deshalb ist diese Tabelle klein, aber für die Akquise die wertvollste.

### 07-marktzahlen.csv — ein Platz und ein Jahr je Zeile

Zahlen aus den öffentlichen Statistiken des Deutschen Golf Verbands und aus Jahresberichten. `jahr` vierstellig, Zahlenfelder ohne Tausenderpunkt. `quelle_art` aus `dgv`, `jahresbericht`, `presse`, `club_website`. Fehlt eine Zahl, bleibt das Feld `unbekannt`, die Zeile wird trotzdem angelegt, wenn mindestens `mitglieder_gesamt` bekannt ist. Höchstens fünf Jahre zurück.

## 7 Beispiel, wie eine gute Zeile aussieht

So sähe ein vollständig recherchierter Platz in Feldform aus. Übertrage das beim Befüllen in die Spaltenreihenfolge der CSV.

```
platz_id            golfpark-weidenhof
name                Golfpark Weidenhof
typ                 golfpark
website             https://www.golfpark-weidenhof.de
strasse             Mühlenstraße
hausnummer          140
plz                 25421
ort                 Pinneberg
lat                 53.6580
lng                 9.7980
bahnhof_name        Pinneberg
bahnhof_km          4
golf_typen          course-9|short|range
gaeste_ohne_platzreife_erlaubt   ja
firmenevents_moeglich            ja
event_formate       schnupperkurs|teamevent|afterwork
max_teilnehmer_event             60
vorlaufzeit_tage    14
gastro_vorhanden    ja
speisekarte_url     https://www.golfpark-weidenhof.de/speisekarte.pdf
leihschlaeger_anzahl             30
datenstatus         ki_recherche
quelle_url          https://www.golfpark-weidenhof.de/firmenevents
stand_datum         2026-09-24
sicherheit          belegt
luecken             Kurspreise nur auf Anfrage, keine Raumgrößen genannt
```

Dazu eine Zeile in `02-kurse.csv`:

```
platz_id            golfpark-weidenhof
kurs_id             weidenhof-schnupper-3h
bezeichnung         Schnupperkurs für Firmengruppen
format              schnupperkurs
dauer_minuten       180
min_teilnehmer      6
max_teilnehmer      12
preis_pro_person    49,00
preis_brutto_netto  brutto
inklusive_leihschlaeger          ja
inklusive_baelle                 ja
inklusive_greenfee               nein
pro_erforderlich                 ja
ort_des_kurses      Driving Range|Kurzplatz
schlechtwetter_alternative       Indoor-Abschlagplätze überdacht
quelle_url          https://www.golfpark-weidenhof.de/firmenevents
stand_datum         2026-09-24
sicherheit          belegt
```

## 8 Abgabe

Fünf CSV-Dateien, UTF-8, Semikolon als Trennzeichen, unveränderte Kopfzeilen. Keine zusätzlichen Spalten, keine umsortierten Spalten. Fehlt eine Information, steht `unbekannt` im Feld, nicht nichts.

Dazu eine kurze Notiz mit: wie viele Plätze bearbeitet, welche Quellen sich als brauchbar erwiesen haben, und bei welchen Feldern die Ausbeute so schlecht war, dass sich die Recherche dort nicht lohnt. Diese Rückmeldung ist für die nächste Runde wertvoller als zwanzig zusätzliche halbe Datensätze.
