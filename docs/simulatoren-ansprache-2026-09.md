# Simulator-Ansprache, Start September 2026

Stand 07.09.2026. Ziel: alle Golfsimulator-Betreiber in Deutschland als Partner für Firmen-Weihnachtsfeiern gewinnen, solange das Buchungsfenster offen ist (Dezember-Termine werden ab jetzt angefragt). Datenbasis: Julius' Marktanalyse vom 24.08. (157 Anlagen, `golf_simulatoren_records.json`), Arbeitsliste mit Status-Spalten in `docs/simulatoren-ansprache-2026-09.csv`.

## 1. Warum jetzt ein guter Moment ist

- Die Weihnachtsfeier-Seite `/firmenevent/weihnachtsfeier/` ist live (1.9.237) und zeigt alle 157 Anlagen auf der Karte. Jede Anlage lässt sich schon heute anfragen, auch ohne Partnerzugang. Das ist der Aufhänger: „Ihr steht schon drauf, Firmen können euch anfragen, nur euer Angebot fehlt noch dahinter."
- Onboarding-Strecke für Indoor existiert: `/partner-onboarding/?ob_type=indoor` (ca. 10 Minuten, kostenlos).
- Sobald ein Simulator angemeldet ist und eine Weihnachtsfeier anlegt, erscheint er auf der Karte groß mit Ring („Bei Firmengolf buchbar") und in den acht Angebotskarten der Seite. Kein Zusatzaufwand.

## 2. Wellen

| Welle | Wer | Anzahl | Start |
|---|---|---|---|
| 1 | Score A+, A, B (Fit hoch oder mittel, Eventlocation bestätigt) | 27 | sofort, persönlich, gern mit Anruf nach Mail 1 |
| 2 | Score C plus D mit bestätigter Eventlocation | 28 | 3 bis 4 Tage nach Welle 1 |
| 3 | Rest (D, Fit „Prüfen": Golfshops, Hotel-Boxen, Freizeitzentren) | 102 | ab Mitte September, Serienmail, keine Anrufe |

Welle 1 im Detail (alle mit Kontakt vollständig):

- **A+:** Golf Loft 44 (Ebersbach), GOLFHUB Chemnitz, KINETO Meerbusch, MatchPlay Göttingen, PATO Golf München, Seven Tees München, Seven Tees Olching, TrackMe Hamburg
- **A:** APEX Bonn, ForeYou Karlsdorf-Neuthard, Pitching Zone Lübeck, TrackMe Beach Hamburg
- **B:** Birdies Nürnberg, Clubhouse Nürnberg, EvoGolf Berlin, Fairway Lounge Neustadt/WN, GOLFINN Wuppertal, Golfzentrum Berlin Schöneberg, Indoor-Golf-Ruhrpott Mülheim, LAZY GOLF Hamburg, RUFF Golf Dreieich, RUFF Golf Düsseldorf, RUFF Ostsee, Seven Tees Brunnthal, Seven Tees Köln/Hürth, Shank Brothers Prutting, Tap Inn Wasserburg

Ketten bündeln: Seven Tees (4 Standorte), RUFF Golf (3), TrackMe (2) bekommen eine Mail an die Zentrale mit Nennung aller Standorte, nicht vier einzelne.

Datenlage, die beim Personalisieren zählt: 104 von 157 Adressen sind Sammeladressen (info@, kontakt@), nur eine Anlage hat einen Ansprechpartner. Anrede daher „Hallo liebes Team von …". Bei Welle 1 lohnt ein Blick auf die Website nach dem Inhabernamen, bevor die Mail rausgeht.

## 3. Mail 1, Erstkontakt

Ton wie die bestehenden Partner-Mails (Du/ihr, klar, ohne Gedankenstriche). Platzhalter in geschweiften Klammern kommen aus der CSV. {Boxen} nur einsetzen, wenn eine Zahl vorliegt, sonst „euren Boxen".

**Betreff:** Firmen-Weihnachtsfeiern für {Anlage}: kostenlos dabei sein

Alternativ-Betreff für den A/B-Test: Weihnachtsfeier am Simulator: Firmen aus {Ort} suchen gerade

---

Hallo liebes Team von {Anlage},

ich bin Julius von Firmengolf Events. Wir vermitteln Firmenevents an Golfanlagen, seit Juni live, und im Winter suchen Unternehmen vor allem eins: eine Weihnachtsfeier am Simulator.

Eure Anlage in {Ort} steht bereits auf unserer Simulator-Karte, Firmen sehen euch also schon und können euch anfragen. Was noch fehlt, ist euer Angebot dahinter: ein Abend mit {Boxen} Boxen, Longest-Drive-Challenge und Getränken, zu eurem Preis.

Was ihr davon habt:

- Anfragen von Firmen aus eurer Region, gebündelt in einem Portal.
- Kostenlos für euch. Ihr gebt euren Preis an und bekommt ihn exakt ausgezahlt, die Vermittlung zahlt der Kunde.
- In 10 Minuten online. Ihr stellt Eventformate ohne festen Termin ein, Firmen fragen den Termin an. Weihnachtsfeiern werden jetzt geplant.

[Button: Indoor Golf jetzt eintragen] → https://firmengolf-events.de/partner-onboarding/?ob_type=indoor

So sieht die Seite aus, auf der Firmen euch finden: https://firmengolf-events.de/firmenevent/weihnachtsfeier/

Wenn ihr lieber erst kurz sprechen wollt: antwortet auf diese Mail oder ruft mich an, +49 89 1225 1010.

Viele Grüße
Julius Klinzer
Gründer Firmengolf

---

## 4. Nachfass 1, Tag 5

**Betreff:** Kurze Nachfrage: Weihnachtsfeiern bei {Anlage}

Hallo liebes Team von {Anlage},

vor ein paar Tagen habe ich euch geschrieben, weil Firmen bei uns nach Weihnachtsfeiern am Simulator suchen. Eure Anlage steht auf der Karte, ein Angebot dahinter fehlt noch.

Falls die Mail untergegangen ist: Eintragen dauert 10 Minuten und kostet nichts. https://firmengolf-events.de/partner-onboarding/?ob_type=indoor

Wenn es bei euch gerade nicht passt, sagt mir kurz Bescheid, dann höre ich auf zu nerven.

Viele Grüße
Julius

## 5. Nachfass 2, Tag 12, letzte Mail

**Betreff:** Letzte Mail zu Firmen-Weihnachtsfeiern bei {Anlage}

Hallo liebes Team von {Anlage},

das ist meine letzte Nachricht dazu, danach lasse ich euch in Ruhe. Die Weihnachtsfeier-Anfragen laufen, und ich würde sie lieber an euch weitergeben als an eine Anlage 30 Kilometer weiter.

Der Zugang bleibt offen: https://firmengolf-events.de/partner-onboarding/?ob_type=indoor

Ein Anruf reicht auch: +49 89 1225 1010.

Viele Grüße
Julius

## 6. Versandweg, zwei Optionen

**Option A, sofort ohne Code:** Serienmail aus M365 (Outlook Seriendruck) oder Brevo mit den Spalten der CSV. Nachfass manuell nach Status-Spalte. Vorteil: heute startklar. Nachteil: kein Magic-Link, kein automatischer Nachfass, Rückmeldungen laufen als Mail-Antwort.

**Option B, über das Einladungssystem (Code-Paket, wartet auf Go):** Die 27 Anlagen aus Welle 1 werden als vorbereitete Partner angelegt (Typ indoor-sim, Name, Ort, Adresse, Website, System, Boxen aus der Marktanalyse). Dann läuft die bestehende Einladungsstrecke: persönlicher Magic-Link, Nachfass automatisch an Tag 5, 10 und 15, Annahme wird erkannt, Aktivierungs-Nudge nach der Annahme. Nötig sind drei Bausteine:

1. Import-Skript Marktanalyse → Partner-Entwürfe (wie `import-bilder.php`, einmalig).
2. Indoor-Variante der Einladungsmail in `fge_send_partner_invite_email()` (Text aus Abschnitt 3, die Platz-Formulierungen passen nicht), gleiches für die drei Nachfass-Stufen.
3. Das Einladungs-Modal zeigt bei Indoor-Partnern die Indoor-Vorschau.

Aufwand grob ein halber Tag, dann läuft die Serie für alle Wellen ohne Handarbeit. Empfehlung: Welle 1 heute per Option A starten und parallel Option B bauen, damit Welle 2 und 3 automatisch laufen.

## 7. Offene Entscheidungen

- **Anrede:** Universum sagt „Sie" für Golfanlagen, die bestehenden Einladungsmails im Events-Projekt sind Du/ihr. Der Entwurf folgt den Einladungsmails. Wenn Sie gewünscht, ist das eine Textänderung, keine Struktur.
- **Absender:** `partner@firmengolf-events.de` (Antworten landen im Partner-Postfach) oder Julius direkt. Für Welle 1 empfehle ich Julius persönlich.
- **Öffentliche Indoor-Seite für Betreiber:** Es gibt `/golfplatz-partner/` für Plätze, aber keine Betreiber-Seite für Simulatoren. Die Mail verlinkt deshalb direkt ins Onboarding. Eine kurze `/simulator-partner/`-Seite (Nutzen, Ablauf, Preisbeispiel, Karte) würde die Conversion aus Welle 3 stützen. Idee, kein Muss.
- **Datenschutz:** Erstkontakt an geschäftliche Sammeladressen mit Bezug zum Betrieb ist B2B-üblich. Jede Mail hat den Satz „sagt kurz Bescheid, dann höre ich auf", Nachfass endet nach Mail 3.

## 8. Tracking

Status-Spalten in der CSV: `Ansprache-Status` (offen, Mail 1, Nachfass 1, Nachfass 2, angemeldet, abgesagt, keine Rückmeldung), Datumsspalten je Mail, `Rückmeldung` als Freitext. Wöchentlich zählen: angeschrieben, angemeldet, Weihnachtsfeier angelegt. Ziel für September: 10 Simulatoren mit buchbarer Weihnachtsfeier auf der Karte.
