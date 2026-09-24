# Auftrag: Das Golfmarkt Universum aufbauen und füllen

Du baust eine Stammdatenbank über den deutschen Golfmarkt und füllst sie danach mit recherchierten Daten. Sie heißt **Golfmarkt Universum** und ist die gemeinsame Wahrheit für alle Golf-Unternehmungen von Julius Klinzer: Firmengolf Events, Benefit, Spotee Golf und Fair-Way-Golf. Jedes dieser Projekte bezieht seine Golfplatzdaten von hier, keines hält eigene Kopien.

Dieses Dokument ist dein vollständiger Auftrag. Lies es ganz, bevor du anfängst. Bei Widersprüchen zwischen diesem Dokument und einer Vorlage gilt dieses Dokument.

Neben diesem Auftrag bekommst du:

- `00-briefing.md`: die Feldbeschreibungen und Rechercheregeln
- `01-plaetze.csv` bis `07-marktzahlen.csv`: die Tabellenvorlagen, nur Kopfzeilen

## 1 Warum das gebaut wird

Bisher wurden Daten über Golfplätze für jede Auswertung neu erhoben, und die Ergebnisse widersprachen sich. Das ist kein Mangel an Daten, sondern ein Mangel an Stammdaten: es gab keine stabile Kennung je Platz und keine Geschichte, wann sich welcher Wert warum geändert hat.

Das Golfmarkt Universum löst genau das. Es ist die eine Wahrheit über Golfplätze, Betreiber, Angebote, Menschen und Marktzahlen. Vier Projekte lesen daraus, keines schreibt zurück:

| Projekt | Was es braucht |
|---|---|
| Firmengolf Events | Plätze im Umkreis einer Anfrage mit Kurspreisen, Gastronomie, Räumen, Ansprechpartnern |
| Benefit | Plätze und Angebote, die Mitarbeitende von Firmen nutzen können |
| Spotee Golf | Plätze mit Lage, Anlage und Ausstattung |
| Fair-Way-Golf | Plätze, Golflehrer und Kurse |

Erste konkrete Anwendung: Kommt bei Firmengolf Events eine Anfrage herein, zeigt die Plattform die vier nächstgelegenen Plätze mit Kurspreis, Gastronomie und Räumen, und Julius fragt sie per Knopfdruck an.

Das Schema ist bewusst gemeinsam. Ein Platz hat einen Simulator oder nicht, unabhängig davon, welches Projekt fragt. Felder, die nur ein Projekt braucht, gehören trotzdem hierher und nicht in das Projekt, sonst entstehen wieder zwei Wahrheiten.

## 2 Die vier Grundregeln

Diese Regeln stehen über allem anderen und kommen wörtlich in die README des Repositories.

1. **Die `platz_id` ändert sich nie.** Sie ist ein Kleinbuchstaben-Slug aus Name und Ort, etwa `golfpark-weidenhof-pinneberg`, ohne Umlaute, nach dem Muster `^[a-z0-9]+(-[a-z0-9]+)*$`. Ändert sich der Name eines Platzes, ändert sich das Feld `name`, nie die Kennung. Sie ist das Band zu allen Projekten.
2. **Jede Änderung ist ein Git-Commit mit Grund.** Die Historie ist das Gedächtnis. Eine Commit-Nachricht sagt, was sich geändert hat und woher die neue Information stammt.
3. **Nichts kommt ohne Quelle, Abrufdatum und Sicherheitsstufe hinein.** Das Prüfskript weist Zeilen ohne diese drei Felder ab.
4. **Der Datenfluss geht nur hinaus.** Projekte importieren von hier. Nie umgekehrt.

## 3 Was gebaut wird

Ein Git-Repository mit dem Namen `golfmarkt-universum`, lokal unter `~/projects/golfmarkt-universum`. Dieser Aufbau, genau so:

```
golfmarkt-universum/
  README.md                 Zweck, die vier Regeln, die Projekte, wie man abfragt, wie man ändert
  CHANGELOG.md              menschenlesbar, neueste Einträge oben
  daten/
    plaetze.csv             aus 01-plaetze.csv
    kurse.csv               aus 02-kurse.csv
    pros.csv                aus 03-pros.csv
    raeume.csv              aus 04-raeume.csv
    gastro.csv              aus 05-gastro.csv
    betreiber.csv           aus 06-betreiber.csv
    marktzahlen.csv         aus 07-marktzahlen.csv
  schema/
    felder.md               jedes Feld mit Bedeutung, Typ, Pflicht ja/nein, erlaubten Werten
    werte.md                alle Wertelisten an einer Stelle
    projekte.md             welches Projekt welche Felder nutzt, siehe Abschnitt 6
  werkzeuge/
    pruefen.py              Validierung, siehe Abschnitt 4
    sqlite.py               baut daten/golfmarkt.sqlite aus den CSVs, siehe Abschnitt 5
    export.py               liefert Teilmengen je Projekt, siehe Abschnitt 6
    profile.json            Exportprofile je Projekt
  auswertungen/
    README.md               wie eine Auswertung abgelegt wird
  .gitignore                daten/golfmarkt.sqlite und export/ (abgeleitet, nie eingecheckt)
```

Die CSV-Vorlagen übernimmst du unverändert: Kopfzeilen, Reihenfolge, Schreibweise der Spaltennamen. Keine Spalte umbenennen, keine hinzufügen, keine streichen. Die Wertelisten stammen aus der Event-Plattform von Firmengolf und sind damit die verbindlichen Begriffe für alle vier Projekte. Wer sie ändern will, ändert sie hier, nie in einem Projekt.

Werkzeuge in **Python 3 ohne Fremdbibliotheken**. Sie müssen auf jedem Rechner laufen, auf dem Python installiert ist, ohne Installation weiterer Pakete.

## 4 Das Prüfskript

`werkzeuge/pruefen.py` läuft ohne Argumente über alle CSVs in `daten/` und gibt am Ende `OK` oder eine Liste von Fehlern mit Datei, Zeile und Spalte aus. Es prüft:

**Struktur**
- Zeichensatz UTF-8, Trennzeichen Semikolon, Kopfzeile identisch mit der Vorlage
- Jede Zeile hat genau so viele Felder wie die Kopfzeile
- Kein Semikolon und kein Zeilenumbruch innerhalb eines Feldes

**Kennungen**
- `platz_id` entspricht dem Slug-Muster und ist in `plaetze.csv` eindeutig
- Jede `platz_id` in `kurse`, `pros`, `raeume`, `gastro`, `marktzahlen` existiert in `plaetze.csv`
- Jede `betreiber_id` in `betreiber.csv` ist eindeutig, und jede in `anlagen_platz_ids` genannte `platz_id` existiert
- `kurs_id`, `pro_id`, `raum_id`, `angebot_id` sind je Datei eindeutig

**Pflichtfelder**
- In `plaetze.csv`: `platz_id`, `name`, `plz`, `ort`, `lat`, `lng`, `quelle_url`, `stand_datum`, `sicherheit`, `datenstatus`
- In allen anderen Dateien: `platz_id` (beziehungsweise `betreiber_id`), `quelle_url`, `stand_datum`, `sicherheit`
- Ein Pflichtfeld darf nicht leer sein. `unbekannt` ist in `lat`, `lng`, `plz`, `ort`, `quelle_url`, `stand_datum` nicht erlaubt.

**Werte**
- Ja-Nein-Felder enthalten nur `ja`, `nein` oder `unbekannt`
- Felder mit Werteliste (siehe `00-briefing.md`, etwa `golf_typen`, `event_formate`, `rechtsform`, `qualifikation`, `sicherheit`, `datenstatus`) enthalten nur erlaubte Werte, bei Mehrfachwerten mit `|` getrennt
- Zahlen mit Komma als Dezimaltrennzeichen, ohne Währungszeichen, ohne Tausenderpunkt
- `lat` und `lng` mit Punkt, `lat` zwischen 45 und 56, `lng` zwischen 5 und 16 (Deutschland und Nachbarn), sonst Fehler
- Datumsfelder im Format `JJJJ-MM-TT`, nicht in der Zukunft
- `datenstatus` ist `ki_recherche` oder `platz_bestaetigt`
- URLs beginnen mit `https://` oder `http://`

**Plausibilität, als Warnung, nicht als Fehler**
- Greenfee unter 10 oder über 250 Euro
- Kurspreis pro Person unter 5 oder über 500 Euro
- `max_teilnehmer` kleiner als `min_teilnehmer`
- Zwei Plätze mit gleichem Namen und gleicher PLZ

Das Skript gibt bei Fehlern den Exit-Code 1 zurück, damit es sich als Commit-Hook eignet. Richte es als `pre-commit`-Hook ein, sodass fehlerhafte Daten nicht committet werden können.

## 5 Die Abfrageschicht

`werkzeuge/sqlite.py` liest alle CSVs und schreibt `daten/golfmarkt.sqlite`, eine Tabelle je Datei, Spaltennamen identisch. Typen: Zahlen als REAL, Ja-Nein als TEXT, alles andere TEXT. Die Datei wird bei jedem Lauf neu erzeugt und ist nie eingecheckt, sie ist eine Wegwerfkopie zum Fragenstellen.

Lege zusätzlich diese Sichten (Views) an, weil sie die häufigsten Fragen beantworten:

- `v_firmenevent_faehig`: Plätze mit `firmenevents_moeglich = ja` und `gaeste_ohne_platzreife_erlaubt = ja`, mit `lat`, `lng`, `ort`, `max_teilnehmer_event`
- `v_schnupperkurse`: alle Kurse mit `format = schnupperkurs`, verbunden mit Platzname und Ort, sortiert nach `preis_pro_person`
- `v_betreiber_mehrere`: Betreiber mit mehr als einer Anlage
- `v_marktlage`: Plätze mit `mitglieder_trend`, `warteliste`, `offen_fuer_firmenkunden`
- `v_datenluecken`: je Platz die Anzahl der Felder mit `unbekannt`, absteigend, damit man sieht, wo Recherche fehlt

Dokumentiere in der README drei Beispielabfragen, darunter: „Welche Plätze im Umkreis von 40 Kilometern um eine gegebene Koordinate sind firmenevent-fähig", mit der Haversine-Formel in SQL.

## 6 Der Export für Projekte

`werkzeuge/export.py --projekt firmengolf-events --plz 20095 --radius 50` schreibt `export/firmengolf-events-20095.json` mit allen Plätzen im Umkreis, je Platz die Kurse, Gastro-Angebote, Räume und Golflehrer eingebettet. Ein Export ohne `--plz` liefert alles.

Welche Felder ein Projekt bekommt, steht in `werkzeuge/profile.json`, ein Eintrag je Projekt: `firmengolf-events`, `benefit`, `spotee-golf`, `fair-way-golf`. Jedes Profil nennt die Tabellen und Spalten, die es erhält. Lege alle vier an; für `firmengolf-events` mit allen Tabellen und Spalten, für die drei anderen zunächst ebenfalls vollständig. Julius grenzt die Profile später ein, sobald klar ist, was jedes Projekt wirklich braucht. Neue Projekte bekommen einen neuen Eintrag, ohne dass sich am Schema etwas ändert.

`schema/projekte.md` ist die lesbare Fassung davon: eine Tabelle, welches Feld von welchem Projekt genutzt wird. Sie wird aus `profile.json` erzeugt, nicht von Hand gepflegt.

Der Export enthält nur Felder mit Wert, keine `unbekannt`-Einträge, und je Datensatz `platz_id`, `datenstatus`, `stand_datum` und `sicherheit`, damit das Zielprojekt weiß, wie belastbar der Datensatz ist.

## 7 Die README

Sie enthält in dieser Reihenfolge: Zweck in drei Sätzen, die vier Grundregeln wörtlich, die vier Projekte und was jedes bezieht, den Aufbau, wie man prüft (`python3 werkzeuge/pruefen.py`), wie man abfragt (`python3 werkzeuge/sqlite.py` und dann `sqlite3 daten/golfmarkt.sqlite`), wie man je Projekt exportiert, wie man einen Platz ändert (Feld ändern, `stand_datum` und `quelle_url` mitziehen, Commit mit Grund), wie ein neues Feld dazukommt (siehe Abschnitt 10), und die zwei Datenstufen `ki_recherche` und `platz_bestaetigt` mit ihrer Bedeutung.

Ton der README: knapp, deutsch, keine Marketingsprache, keine Gedankenstriche.

## 8 Füllen: Reihenfolge und Umfang

Erst wenn Abschnitt 3 bis 7 fertig sind und `pruefen.py` auf den leeren Tabellen `OK` sagt, beginnt die Recherche. Regeln und Feldbedeutungen stehen in `00-briefing.md`, das gilt vollständig.

**Gebiet:** Zuerst alle Golfanlagen im Umkreis von 60 Kilometern um Hamburg, danach um München. Erst wenn beide vollständig sind, weitere Regionen. Hundert vollständige Datensätze sind mehr wert als tausend halbe.

**Vollständigkeit je Platz:** Ein Platz gilt als aufgenommen, wenn Identität, Adresse, Koordinaten, Firmenevent-Eignung, Leihschläger und mindestens ein Kurs mit Preis oder der Vermerk „Preise nur auf Anfrage" in `luecken` vorhanden sind. Alles Weitere ist wertvoll, aber nicht Bedingung.

**Betreiber:** Sobald bei zwei Plätzen derselbe Betreiber auftaucht, bekommt er eine Zeile in `betreiber.csv`, und beide Plätze verweisen über `betreibergesellschaft_name` darauf.

**Marktzahlen:** Aus den öffentlichen Statistiken des Deutschen Golf Verbands und aus Jahresberichten der Clubs, je Platz und Jahr eine Zeile, so weit zurück wie öffentlich verfügbar, höchstens fünf Jahre.

**Commits:** Nach jedem vollständig recherchierten Platz ein Commit mit Nachricht nach dem Muster `Golfpark Weidenhof, Pinneberg: aufgenommen, Quelle Website und Preis-PDF`. Nie mehrere Plätze in einem Commit.

## 9 Wo die Daten wirklich liegen

Die Erfahrung aus früheren Recherchen: eine KI, die sucht und Ausschnitte liest, findet Kurstermine und Preise meistens nicht, obwohl ein Mensch sie in einer Minute auf der Website findet. Der Grund ist nicht, dass die Daten fehlen, sondern wo sie liegen.

**Buchungsplattformen.** Kurse, Platzreifetermine und Preise stehen bei vielen Clubs nicht im eigenen Seiteninhalt, sondern in einem eingebetteten Buchungsfenster von PC Caddie, Swingworks, Albatros, GolfSuite, Teetime oder ähnlichen Anbietern. Ein reiner Textabruf der Clubseite sieht dort einen leeren Rahmen. Das Fenster hat aber eine eigene, öffentliche Adresse. Du findest sie im Quelltext der Seite als `iframe`-Quelle oder als Link mit Beschriftungen wie „Online buchen", „Kursbuchung", „Termine". Diese Adresse rufst du direkt auf. Was dort steht, sind die öffentlichen Angebote des Clubs, die er zum Verkauf ausstellt. Das darfst du lesen und übernehmen.

Was du dort nicht liest: Startzeiten, Belegungen, Buchungen anderer Menschen. Das sind Kundendaten, keine Angebote. Die Grenze ist einfach: **Was der Club veröffentlicht, um zu verkaufen, ist Quelle. Was Kunden eingetragen haben, ist tabu.**

Trag die verwendete Plattform in `buchungsplattform` ein und die direkte Adresse des Kursangebots in `kursbuchung_url`. Beim nächsten Mal weiß dann jeder sofort, wo er schauen muss.

**PDF-Dateien.** Preislisten, Kursprogramme und Raumbeschreibungen liegen oft als PDF unter „Downloads", „Preise" oder „Golfschule". Lade sie herunter und lies sie vollständig. Die Adresse des PDF ist dann die `quelle_url`.

**Unterseiten.** Folge auf jeder Clubseite mindestens diesen Verweisen, sofern vorhanden: Golfschule, Kurse, Platzreife, Schnupperkurs, Firmen, Events, Gruppen, Preise, Gastronomie, Restaurant, Räume, Tagung, Anfahrt, Downloads, Kontakt, Team, Impressum (für Rechtsform und Betreiber). Die Startseite allein reicht nie.

**Werkzeug.** Für diese Arbeit brauchst du einen echten Browser, der JavaScript ausführt, nicht nur einen Textabruf. Wenn du keinen hast, sag das vor dem Start, dann wird das Werkzeug gewechselt, statt dass die Tabellen voller `unbekannt` werden.

**Wenn du trotzdem nichts findest.** Trag `unbekannt` ein und schreib in `luecken`, wo du gesucht hast. Die Sicht `v_datenluecken` sammelt genau das, und Julius oder ein Mensch mit Browser schließt diese Lücken in einem zweiten Durchgang. Eine ehrlich dokumentierte Lücke ist wertvoller als ein geratener Wert.

## 10 Wie ein Feld dazukommt

Das Schema ist gemeinsam für vier Projekte und wächst nur nach einer Regel: **Ein Feld kommt dazu, wenn jemand eine Frage stellt, die es beantwortet.** Nicht auf Verdacht. Der Ablauf:

1. Die Frage wird in `schema/felder.md` beim neuen Feld wörtlich notiert, etwa „Welche Plätze haben Flutlicht, damit Benefit Abendtermine anbieten kann".
2. Das Feld bekommt Typ, Pflicht ja/nein und erlaubte Werte, in `pruefen.py` wird die Prüfung ergänzt.
3. Die Spalte wird am Ende der jeweiligen CSV angehängt, nie in der Mitte, damit bestehende Zeilen gültig bleiben.
4. Bestehende Zeilen bekommen `unbekannt`, und `v_datenluecken` zeigt, wo nachrecherchiert wird.
5. Ein Commit mit der Frage als Begründung.

Ein Feld wird nie umbenannt und nie entfernt. Braucht es niemand mehr, bleibt es stehen und wird in `felder.md` als „nicht mehr gepflegt" markiert.

## 11 Was du nicht tust

- Keine Werte erfinden, ableiten oder aus ähnlichen Plätzen übernehmen. Unbekannt heißt `unbekannt`.
- Keine Daten aus Buchungssystemen wie PC Caddie, keine Startzeiten, keine Belegungen. Das sind fremde Systeme mit personenbezogenen Daten.
- Keine Namen oder Kontaktdaten von Personen, die der Platz nicht selbst öffentlich nennt. Nichts aus sozialen Netzwerken oder Bewertungsportalen.
- Keine wirtschaftlichen Einschätzungen ohne Beleg. Ein „kämpft ums Überleben" ohne Quelle ist Rufschädigung.
- Keine Bilder herunterladen, nur Quellen notieren.
- Keine Spalten hinzufügen, umbenennen oder entfernen. Fehlt dir eine, notiere es in der Abschlussmeldung.

## 12 Abnahme

Julius prüft in dieser Reihenfolge:

1. `python3 werkzeuge/pruefen.py` sagt `OK`.
2. `python3 werkzeuge/sqlite.py` läuft durch, und `SELECT COUNT(*) FROM plaetze` ergibt die Zahl aus deiner Abschlussmeldung.
3. Die Sicht `v_firmenevent_faehig` liefert für Hamburg mindestens zehn Plätze mit Koordinaten.
4. Drei zufällig gewählte Plätze werden gegen ihre Website geprüft: Adresse, ein Preis, ein Ja-Nein-Feld. Stimmt eines nicht, gilt die Abnahme als nicht bestanden.
5. `git log` zeigt je Platz einen Commit mit sprechender Nachricht.
6. Der Export für PLZ 20095 mit Radius 50 enthält nur Plätze, deren Koordinaten tatsächlich in diesem Umkreis liegen.

## 13 Abschlussmeldung

Am Ende eine kurze Notiz mit: Anzahl Plätze je Region, wie viele davon vollständig nach Abschnitt 8, welche Quellen brauchbar waren und welche nicht, welche Felder fast nie zu finden waren, und welche Spalten dir gefehlt haben. Diese Rückmeldung ist für die nächste Runde wertvoller als zwanzig zusätzliche halbe Datensätze.
