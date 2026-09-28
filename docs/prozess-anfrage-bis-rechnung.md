# Prozess: von der Anfrage bis zur Rechnung

Stand 22.09.2026, Plugin 1.9.267. Was das System automatisch macht und was Julius von Hand macht, mit allen Mails. Beispiel am Ende: FG-26-165, Kanzlei Blumenau, Golfpark Weidenhof, 30.09.2026.

## Überblick

| Phase | Auslöser | Automatisch | Von Hand (Julius) |
|---|---|---|---|
| 1 Eingang | Kunde schickt Anfrage (Eventseite, Anfrage-Wizard, Kontaktformular) | Vorgangsnummer FG-JJ-NNN, Status, Bestätigung an Kunden, Info an events@, Termin-Links an Platz-Kontakte | nichts, außer bei „Von Firmengolf organisiert" |
| 2 Termin | Platz-Kontakte stimmen ab, oder Julius bestätigt in Schritt 1 | Erinnerungen, Eskalation, Termin-Bestätigung | Selbstplaner: Preis beim Platz holen, Schritt 2 füllen, Termin bestätigen |
| 3 Angebot | Termin bestätigt | Angebot mit PDF an Kunden, Frist 7 Tage, Erinnerung, Eskalation | nur bei unbepreisten Zusatzwünschen: Positionen bepreisen, „Angebot jetzt senden" |
| 4 Annahme | Kunde klickt „Angebot annehmen" | Auftrag steht (intern), Auftragsbestätigung mit Betrag und Rechnungsadresse (Platz), Buchung bestätigt mit PDF (Kunde), Dienstleister-Aufträge | Platz zuordnen (falls noch nicht), Einkaufspreis eintragen, Schritt 4 ausfüllen, Details abstimmen |
| 4b Ablauf | Startzeit und Treffpunkt gefüllt | Ablauf-Info an den Kunden | nichts |
| 5 Vortag | Cron 07:00 am Tag vor dem Termin | Vortags-Info an Kunde, Platz, events@ | nichts, Kontrolle über die interne Mail |
| 6 Event | Eventtag | nichts | Erreichbarkeit |
| 7 Nachlauf | Status „Event durchgeführt" | Bewertungsbitte mit Google-Link | Rechnung in Lexoffice, Status setzen, Eingangsrechnung des Platzes prüfen, abschließen |

## Das Control Center (seit 1.9.267, noch nicht deployt)

Bedient wird dieser Prozess ab jetzt unter `/control/`, nicht mehr über die verstreuten Metaboxen. Das WordPress-Backend bleibt als Werkstatt für Sonderfälle bestehen, das Control Center führt. Zugriff nur mit `manage_options`, sonst 404.

| Bereich | Wofür |
|---|---|
| Dashboard | Wartet auf mich, wartet auf andere, heute und morgen, Ereignisse seit gestern, Monatstrichter |
| Anfragen | Liste mit Phasenfiltern, dahinter das Anfrage-Cockpit mit Kontakten, Phasenleiste, Aktionen, Zeitleiste und Postausgang |
| Angebote | Laufend, überfällig, angenommen, abgelehnt, mit Beträgen |
| Kalender | Monatsraster, Warnung bei Doppelbelegung, Outlook-Abo über einen geheimen ICS-Link |
| Aufgaben | Alles über alle Vorgänge, abgeleitet plus eigene |
| Plätze, Kunden, Dienstleister | Verzeichnisse mit Kontakten, Preisen und Datenqualität |
| Geld | Offene Angebote, gebuchter Umsatz, Marge, Eingangsrechnungen, Provisionen |
| Postausgang | Jede verschickte Mail mit Zustellstatus |

**Zwei Grundsätze:** Aufgaben werden aus dem Zustand abgeleitet, nicht gepflegt. Und kein Knopf ohne sichtbare Folge: unter jedem Knopf steht mit echten Namen, wer gleich welche Mail bekommt, und fehlt ein Empfänger, steht das vorher in Rot.

**Neu im Prozess durch das Control Center:**
- **Platz-Pipeline** (Phase 2): mehrere Plätze je Anfrage anfragen, Preise je Position vergleichen, einen wählen, den übrigen persönlich absagen. Jeder erfasste Preis steht bei der nächsten Anfrage an denselben Platz als Vorschlag bereit.
- **Angebot neu auflegen** (Phase 3): zurückziehen und als Fassung 2 senden, ohne eine neue Anfrage. Angenommene Angebote nie.
- **Katalog-Event** (Phase 7): den Platz fragen, ob er das durchgeführte Event dauerhaft anbieten will. Bei Ja entsteht ein Entwurf im Status `zur_pruefung`, den du freigibst.
- **Tagesmail** um 07:00 mit allem, was heute ansteht.

Testanleitung: `docs/control-center-test.md`.

## Phase 1: Eingang

**Wege ins System:** Anfrage-Dialog auf einer Eventseite (Wunschtermine, Teilnehmer, Startzeit, Zusatzwünsche, Kontaktwunsch), allgemeiner Anfrage-Wizard, Kontaktformular, Rückruf-Widget. Budget-Rechner erzeugt nur einen Lead.

**System:** legt die Anfrage an (Nummer FG-26-NNN, Status `neu`, dann `eingangsbestaetigung_gesendet`), Kunden-Token für die Statusseite `/angebot/<token>/`.

**Mails:**
- Kunde: „Deine Anfrage bei Firmengolf ist eingegangen" mit Nummer und Status-Link.
- events@: „Neue Event-Anfrage: <Firma>" mit allen Feldern, Kontaktwunsch, Wünschen, Admin-Link. Rote Warnung, wenn ein Platz zugeordnet ist, aber kein Kontakt mit Mailadresse existiert.
- Platz (nur bei Event mit Partner): jeder Ansprechpartner bekommt seinen persönlichen Termin-Link `/termin/<token>/`; ohne Kontakte eine einfache Verfügbarkeitsanfrage an die Platz-Mailadresse.

**Julius:** Bei Platzhalter-Events („Golf-Schnupperkurs für Teams in Hamburg", „Von Firmengolf organisiert") gibt es keinen Platz. Julius sucht den Platz, holt den Preis und macht weiter mit Phase 2b.

**Partnercode (seit 1.9.266):** Der Kunde kann im Event-Dialog, im Wizard oder über einen Link `?pc=CODE` einen Partnercode angeben (Codes von Multiplikatoren wie Content Creatorn, DGV, GMVD; keine Golfplätze). Der Code wird im Browser gemerkt und vorausgefüllt, live geprüft und mit der Anfrage gespeichert. Ein ungültiger Code blockiert die Anfrage nicht. Die interne Mail zeigt die Zeile „Partnercode" (mit Link zum Code), die Kundenbestätigung den Satz „Partnercode X erkannt, 5 % Rabatt im Angebot." bzw. den Hinweis, dass der Code nicht gültig war. In der Anfrage steht der Code in der Box „Quelle und Tracking", in der Anfrageliste in der Spalte „Partnercode".

## Phase 2: Termin

**2a Partner-Weg (Event mit Platz):** Kontakte sagen je Wunschtermin zu oder ab. Nach 2 Tagen Erinnerung an alle, die nicht reagiert haben; nach Ablauf der Frist Eskalation an events@. Haben alle reagiert, bekommt der Hauptkontakt „Alle Rückmeldungen da" und bestätigt im Portal den Termin. Julius kann jederzeit „Koordination übernehmen" (Status `in_uebernahme`) und dann selbst bestätigen.

**2b Selbstplaner-Weg (kein Platz, telefonisch verkauft):**
1. Schritt 2 „Angebots-Positionen": Eventpreis überschreiben (netto, pauschal oder p.P.), Veranstaltungsort, Ablauf und Zeiten, Leistungen (eine je Zeile). Zusatzleistungen mit Einkauf, Marge, Dienstleister als Positionen. „Aktualisieren".
2. Schritt 1 „Terminabstimmung": Termin wählen, „Termin bestätigen & Angebot senden". Ohne Preis ist der Button gesperrt.
3. Erst danach den Platz in „Anfrage Basis" zuordnen (sonst starten Erinnerungen an die Platz-Kontakte).

**Mails bei Terminbestätigung:** events@ „Termin bestätigt: FG-…" (mit Hinweis, ob das Angebot automatisch rausgeht oder zurückgehalten ist). Kunde bekommt nur dann eine separate „Euer Termin steht"-Mail, wenn das Angebot noch nicht raus kann (Phase 3, Gate).

## Phase 3: Angebot

**Automatisch,** sobald ein Termin bestätigt ist und die Anfrage bepreist ist: Angebots-Snapshot (Preis, Ort, Ablauf, Leistungen, Positionen), Status `angebot_versendet`, Frist 7 Tage.

**Gate:** Hat der Kunde Zusatzwünsche ohne Preis oder gibt es keinen Preis, wird das Angebot zurückgehalten (Status `in_uebernahme`, Kunde bekommt „Euer Termin steht"). Julius bepreist in Schritt 2 und klickt in Schritt 3 „Angebot jetzt senden". Nach 2 Tagen ohne Versand erinnert das System intern.

**Mails:**
- Kunde: „Euer Angebot FG-…: <Event>" mit Positionstabelle, Summen netto / USt. / gesamt, Gültigkeit, Button, PDF im Anhang. Angebotsseite mit Annehmen (AGB-Häkchen), Rückfrage, Absagen, PDF-Download.
- Mit gültigem Partnercode: eigene Zeile im Summenblock „Partnercode CODE (Inhaber), 5 % Rabatt  -21,60 €" zwischen Zwischensumme netto und USt. (Web, PDF, Mail). Der Rabatt gilt auf die Netto-Zwischensumme inklusive gewählter Zusatzleistungen und rechnet beim Abwählen mit. Prozentsatz wird beim Angebotsversand eingefroren; wird der Code später pausiert, bleibt das Angebot wie versendet.
- Kunde nach 3 Tagen ohne Reaktion: „Erinnerung: euer Angebot" (nur solange die Frist läuft).
- events@ nach Fristablauf: „Angebot überfällig", bei Rückfrage des Kunden „Rückfrage zum Angebot" (nach 2 Tagen unbeantwortet erneut intern).
- events@ falls die Angebotsmail nicht zugestellt werden konnte: „Angebot nicht zugestellt".

**Julius:** Rückfragen beantworten (Antwort per Mail, ggf. Positionen ändern; ein neues Angebot ist derzeit nur über eine neue Anfrage möglich). Nach Fristablauf: Termin beim Platz prüfen, dann den Kunden erneut zum Link schicken.

## Phase 4: Annahme

**Kunde** klickt mit AGB-Häkchen „Angebot annehmen", wählt Zusatzleistungen ab oder an. Status `angebot_angenommen`. Nach Fristablauf wird eine Annahme zur Rückfrage (Termin nicht mehr garantiert), Julius prüft und bestätigt manuell.

**Mails:**
- events@: „Auftrag steht: FG-…" mit Termin, Firma, Event, Platz, Einkaufspreis des Platzes, Abrechnungsübersicht der Zusatzleistungen (Einkauf, Marge, Dienstleister, nur intern), Hinweis auf Schritt 4. Rot markiert, wenn der Platz keine Kontaktmail hat oder kein Einkaufspreis hinterlegt ist. Mit Partnercode zusätzlich der Block „Partnercode (intern)": Code, Inhaber, Kontakt, Rabatt in Euro, Provision in Euro (offen). Rabatt und Provision gehen zulasten der Firmengolf-Marge, der Platz bekommt sein volles Netto.
- Platz: „Buchung bestätigt: <Firma>, <Datum> (FG-…)" als vollwertige Auftragsbestätigung an die Kontaktmail und alle Terminabstimmer, BCC an Julius. Enthält Termin mit Startzeit, Gruppe (Firma, Ort, Personenzahl, Niveau), Ansprechpartner der Gruppe mit Telefon, Paket, Ablauf, Treffpunkt, vereinbarten Einkaufspreis mit Brutto- oder Netto-Kennung, Inklusivleistungen, unsere Rechnungsadresse und den Verwendungszweck. Bis 1.9.266 waren das zwei nichtssagende Sätze, die Julius jedes Mal von Hand nachschieben musste.
- Kunde: „Buchung bestätigt: FG-…" mit Link zur Buchungsübersicht und PDF.
- Dienstleister je Position: Auftrag oder Absage mit dem vereinbarten Einkaufspreis.

**Julius:**
1. Platz zuordnen, falls noch nicht (Anfrage Basis).
2. Einkaufspreis des Platzes in Schritt 2 eintragen („Vereinbart mit dem Platz", brutto oder netto, p.P. oder pauschal), sonst nennt die Auftragsbestätigung keinen Betrag.
3. Schritt 4 „Event-Tag": Startzeit, Treffpunkt, Ansprechpartner vor Ort mit Telefon, Golflehrer, Mitbringen, Hinweise. Vorbelegt aus dem Partnerprofil.
4. Details mit dem Platz abstimmen (Telefon oder Mail, außerhalb des Systems).

## Phase 4b: Ablauf an den Kunden (seit 1.9.267)

Zwischen Buchungsbestätigung und Vortags-Info lagen bisher bis zu acht Tage ohne jeden Kontakt zum Kunden, obwohl Treffpunkt und Ansprechpartner oft am selben Tag feststehen.

**Automatisch:** Sobald Startzeit und Treffpunkt in Schritt 4 erstmals gespeichert sind, geht einmalig „Der Ablauf für euren Eventtag" an den Kunden: Wann, Wo mit Adresse, Treffpunkt, Ansprechpartner vor Ort, was mitzubringen ist, die Ankündigung dass Golflehrer und Ablauf am Vortag folgen, Mobilnummer für den Tag. Gate: `_fge_day_plan_sent`. Manuell jederzeit über „Ablauf-Info jetzt senden" in Schritt 4.

## Phase 5: Vortag

**Cron** täglich 07:00 Uhr: für angenommene Angebote mit Termin morgen einmalig drei Mails. Nachholer, falls der Termin schon heute ist. Manuell jederzeit über „Vortags-Info jetzt senden" in Schritt 4.

- Kunde: „Morgen ist es soweit: <Event> am <Termin>" mit Start, Treffpunkt, Ort und Adresse, Ansprechpartner vor Ort, Golflehrer, Ablauf, Teilnehmer, Hinweise, gebuchte Leistungen, Julius mobil.
- Platz: „Morgen: Firmengolf-Event <Firma> am <Termin>" mit Teilnehmern, Start, Treffpunkt, „die Gruppe meldet sich bei", Kundenkontakt, gebuchte Leistungen.
- events@: „Vortags-Info verschickt" mit Zusammenfassung; fehlende Angaben rot.

## Phase 6: Eventtag

Kein Systemschritt. Julius ist telefonisch erreichbar (Nummer steht in beiden Vortags-Mails).

## Phase 7: Nachlauf und Rechnung

1. **Status „event_durchgefuehrt"** setzen (Anfrage Basis, Aktualisieren). Automatisch: Bewertungsbitte an den Kunden „Danke für euer Event mit Firmengolf, zwei Minuten für uns?" mit Google-Link, einmalig.
2. **Rechnung in Lexoffice** schreiben: Positionen wie im Angebot, netto plus 19 % USt. (Beispiel: 6 × 52,00 € = 312,00 € netto, 59,28 € USt., 371,28 € brutto). Zahlungsziel nach AGB. Lexoffice-Box in der Anfrage: „Rechnung erstellt" ankreuzen, Rechnungsnummer eintragen; der Status springt automatisch auf `rechnung_in_lexoffice_erstellt`.
3. **Eingangsrechnung des Platzes** (Beispiel Weidenhof: 6 × 49 € = 294 € brutto) prüfen und bezahlen. Dienstleister-Rechnungen ebenso.
4. **Zahlungseingang** des Kunden prüfen, dann Status `abgeschlossen`. Damit ist die Anfrage fertig; Erinnerungen und Cron-Mails greifen nicht mehr.
5. **Provision Multiplikator** (nur bei Anfragen mit Partnercode): Die Provision entsteht bei Annahme des Angebots als „offen" (fester Betrag je Code) und wird bei Absage, `verloren` oder `nicht_verfuegbar` automatisch storniert. Auszahlen von Hand (Überweisung, Gutschrift in Lexoffice), dann im Menü „Partnercodes" beim Code „Als abgerechnet markieren" (einzeln je Anfrage oder alle offenen auf einmal). Die Statistik-Box zeigt Anfragen, Buchungen, Provision offen/abgerechnet/storniert und den Link zum Weitergeben.

**Sonderfälle:** Kunde sagt ab → Status `angebot_abgelehnt`, interne Mail, Dienstleister bekommen Absagen. Kein Termin möglich → `nicht_verfuegbar`. Anfrage versandet → `verloren` (von Hand).

## Beispiel FG-26-165 (Kanzlei Blumenau, Stand 18.09.)

| Schritt | Stand |
|---|---|
| Eingang 15.09. über die Eventseite „Golf-Schnupperkurs für Teams in Hamburg" | erledigt, Bestätigung und interne Mail raus |
| Preis beim Weidenhof geholt (49 € brutto p.P.), Kundin telefonisch 52 € netto p.P. zugesagt | erledigt |
| Schritt 2: 52,00 € netto p.P., Ort Golfpark Weidenhof, Ablauf, Leistungen; Adresse der Kanzlei | erledigt 17./18.09. |
| Schritt 1: „Mi, 30.09.2026" bestätigen, Angebot geht mit PDF an Frau Bolten | erledigt 18.09., 13:09 Uhr, BCC an Julius, Frist bis 25.09. |
| Weidenhof zuordnen | erledigt 18.09. |
| Annahme durch die Kundin (bis 25.09.) | offen |
| Schritt 4: Startzeit 12:00 Uhr und Treffpunkt eingetragen; Ansprechpartner vor Ort (Weidenhof, Tel. 04101 511830) und Pro nach der Annahme ergänzen | teilweise |
| Vortags-Info 29.09. um 07:00 | automatisch |
| Event 30.09. mittags, 3 Stunden, 6 Personen | |
| Status „Event durchgeführt", Bewertungsbitte, Rechnung 371,28 € brutto, Eingangsrechnung 294 € brutto, abschließen | danach |

## Nachtrag 28.09.2026 (1.9.278, live): Positionen, Organisation, Anonymität

Auslöser FG-26-166 (solutio, Golf-Teamevent Stuttgart, Grillabend nach Verbrauch). Fünf Lücken geschlossen, Control Center zusammen damit erstmals deployt.

- **Positionen haben jetzt**: stabile Zeilen-id (Löschen und Umsortieren nach dem Versand verschiebt Kundenauswahl und Dienstleister-Mails nicht mehr), Organisation „Golfplatz selbst" oder „Externer Dienstleister", Einkauf brutto oder netto (Verkauf rechnet netto), Basis „nach Verbrauch" mit Richtwert, Beschreibung für den Kunden, Status „am Platz nicht möglich".
- **Angebot zeigt je Zusatzposition** Beschreibung, Abrechnung (bei Verbrauch) und „Organisation: über den Golfplatz" bzw. „Firmengolf mit einem externen Dienstleister", nie den Dienstleister-Namen. Verbrauchs-Positionen stehen ohne Betrag in der Tabelle und fehlen in der Summe, mit eigenem Hinweis. Nicht mögliche Wünsche stehen als Hinweis „Am gewählten Platz leider nicht möglich: …".
- **Buchungsbestätigung an den Platz** listet nur noch Positionen mit Organisation Golfplatz, inklusive vereinbartem Einkauf je Position. Dienstleister-Aufträge und -Absagen gehen nur an externe Positionen.
- **Platz-Pipeline füllt das Angebot**: Platzwahl übernimmt die Positionspreise (brutto/netto, Basis, Notiz) und „bietet der Platz nicht an" in die Angebots-Positionen, belegt Veranstaltungsort mit dem Platz vor und bei Anfragen ohne Eventpreis den Eventpreis mit Einkauf netto plus Standardmarge. Wünsche aus dem Event-Dialog (Abendessen, Getränkepauschale) stehen jetzt in der Wunschliste der Preisanfrage.
- **Ablauf bei Platzhaltern**: Das Feld „Ablauf und Zeiten" startet mit den Überschriften des Event-Tagesablaufs, Julius passt an statt neu zu tippen. Ins Angebot kommt nur, was gespeichert ist.
- **Nachträge**: Position im Cockpit speichern, dann direkt darunter „Zurückziehen und als neue Fassung senden". Beim Neuauflegen wird der Einmal-Guard der Dienstleister-Mails mit gelöscht.
- **Kundenname erst nach Buchung**: Preisanfrage, Termin-Link-Mails, Verfügbarkeitsanfrage, Termin-Seite, Partnerportal (Liste, Detail, Inbox, Widget, Kalender) und ICS nennen vor `angebot_angenommen` nur „Firmenanfrage FG-…" und „Ein Unternehmen aus <Stadt>, N Personen, Niveau". Kontaktname, Mail, Telefon, Kundenfreitext und Budget erscheinen beim Platz erst mit der Buchungsbestätigung. Helper: `fge_request_is_booked()`, `fge_request_group_label()`, `fge_request_partner_title()` in helpers.php.

**Nachtrag 1.9.279 (Audit über die Oberfläche, `docs/audit-control-center-2026-09-28.md`):** Nach einer Kundenabsage zeigt das Cockpit „Abgelehnt" mit drei Wegen (neu auflegen, Platz informieren „Termin wird frei", als verloren ablegen); „Verloren" und „Nicht verfügbar" sind eigene Phasen mit Listenfilter. Platzhalter-Events geben ihre generische Leistungsliste nicht mehr ins Angebot (Cockpit warnt, solange Leistungen oder Ablauf fehlen). Die Vortags-Info an den Platz nennt nur seine Positionen, extern Organisiertes als Hinweis. Fehlt das Angebots-PDF, geht eine interne Warnmail raus. Offen: Angebot mit zwei Platz-Varianten zur Wahl des Kunden (Vorschlag im Audit-Dokument).

## Nachtrag 28.09.2026 abends (1.9.280): Stammdaten-Plätze, Plätze in der Nähe, Katalog-Einladung

- **Stammdaten-Plätze**: Alle DGV-Golfplätze aus `wp_fge_golfplaetze` und alle Simulatoren aus `simulatoren-data.php` sind Partner-Posts mit Status `stammdaten` (Import `includes/stammdaten-import.php`, läuft auf Live in Batches beim Öffnen des Control Centers und per Cron, Statuszeile unter Plätze). Sie sind kein Partner: nicht öffentlich, kein Portal, keine Termin-Einladungen, kein Matching, nicht in Admin-Dropdowns (Klausel `fge_stammdaten_exclude_meta_clause()`). Kontaktmails stammen aus den Recherche-Dateien (`includes/stammdaten-mails-2026-09.php`, 133 Bayern-Plätze, 157 Simulatoren), fehlende Mails ergänzt Julius im Cockpit oder unter Plätze über „Kontakt ergänzen".
- **Plätze in der Nähe**: Jedes Cockpit zeigt die fünf nächsten Golfplätze und drei nächsten Simulatoren zum Kundenstandort (Feld „Kundenstandort", Meta `_fge_geo_anchor`, sonst PLZ oder Ort der Anfrage) mit Entfernung und „Aufnehmen". Die Platzsuche ersetzt das große Auswahlfeld.
- **Absage an nicht gewählte Plätze** sagt „Die Gruppe hat sich diesmal für einen anderen Platz entschieden" und lädt ein, das Angebot dauerhaft bei Firmengolf zu zeigen (Link `/platz-vorschlag/<token>/`, `includes/cc-venue-catalog.php`). Bei Ja: Stammdaten-Platz wird `in_pruefung`, Event-Entwurf `zur_pruefung` aus den Pipeline-Preisen, interne Mail, Einladung mit Portalzugang. Das Cockpit zeigt die Antwort in der Pipeline-Zeile.
- **FG-26-166** wurde per Einmal-Seed (`includes/seed-fg-26-166.php`) auf Live nachgetragen: GolfKultur Stuttgart gewählt (33 € brutto p.P., Verkauf 35 € netto), Schönbuch zugesagt, Hammetweil angefragt, Positionen Grillabend nach Verbrauch und Getränkepauschale nicht möglich, Ort, Ablauf, Leistungen, Chronik. Kein Versand durch den Seed.


## Soll-Prozess seit 1.9.281 (28.09.2026 abends): Optionen statt blinder Terminbestätigung

Der Termin wird nie blind bestätigt. Verfügbarkeit kommt vom Platz je Wunschtermin, die Entscheidung vom Kunden.

| # | Schritt | Automatisch | Von Hand (Julius) |
|---|---|---|---|
| 1 | Eingang | Bestätigung an den Kunden („Angebot innerhalb von zwei Werktagen"), interne Mail mit Link ins Control Center; Cockpit-Block „Angefragt" (Termine nummeriert, Teilnehmer, Niveau, Startzeit, Leistungen, Preis der Eventseite) | nichts |
| 2 | Optionen finden | Block „Plätze in der Nähe" (5 Plätze, 3 Simulatoren zum Kundenstandort), Platzsuche, Stammdaten aller Plätze | Kundenstandort prüfen, Plätze aufnehmen |
| 3 | Plätze anfragen | Preisanfrage-Mail mit anonymer Gruppe, Anlass (Niveau, Startzeit), Positionsliste und nummerierten Wunschterminen („Sagt uns je Termin, ob er geht") | telefonisch oder per Knopf; Antwort erfassen: je Termin geht / geht nicht / offen, Alternativtermin, Preise je Position, nicht möglich |
| 4 | Kalkulation je Platz | Vorschlag = Einkauf netto plus Aufschlag (20 %), auf volle Euro aufgerundet | Aufschlag und Verkaufspreise je Position prüfen, Organisation Platz/extern, „Kalkulation speichern" |
| 5 | Angebot mit Optionen | Snapshot mit bis zu 3 Optionen (Platz, freie Termine, Positionen, Summe), Empfehlung, Mail mit PDF, Angebotsseite mit Wahl von Option und Termin | Optionen A/B/C markieren, Empfehlung und Kurztexte, „Angebot mit Optionen senden" |
| 6 | Entscheidung | Kunde wählt Option und Termin: Termin wird final, Platz zugeordnet, Positionen und Einkauf übernommen; Buchungsbestätigung an den Platz mit Detail-Link und „Was die Gruppe erwartet"; übrige Plätze bekommen automatisch die Absage mit Katalog-Einladung; bei Ablehnung Freigabe an alle. Kunden-Erinnerung nach 3 Tagen, intern „überfällig" | Rückfragen beantworten, nachfassen |
| 7 | Nach der Buchung | Platz trägt über `/platz-details/<token>/` Startzeit, Treffpunkt, Ansprechpartner, Golflehrer, Mitbringen ein; Ablauf-Info geht dann automatisch an den Kunden; Erinnerung an den Platz nach 3 Tagen; Aufgabe „Platzdetails fehlen, anrufen" 7 Tage vor dem Termin; Vortags-Info wie bisher | Details prüfen, ergänzen, Nachlauf wie bisher |

Der alte Weg „Termin direkt bestätigen" bleibt im Cockpit als Sonderfall (Partner-Event mit Portal-Abstimmung, telefonisch fixierter Einzeltermin). Ein Optionen-Angebot mit nur einer Option und einem Termin ist der Normalfall dafür.
