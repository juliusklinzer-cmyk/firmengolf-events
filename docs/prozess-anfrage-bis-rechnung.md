# Prozess: von der Anfrage bis zur Rechnung

Stand 18.09.2026, Plugin 1.9.265. Was das System automatisch macht und was Julius von Hand macht, mit allen Mails. Beispiel am Ende: FG-26-165, Kanzlei Blumenau, Golfpark Weidenhof, 30.09.2026.

## Überblick

| Phase | Auslöser | Automatisch | Von Hand (Julius) |
|---|---|---|---|
| 1 Eingang | Kunde schickt Anfrage (Eventseite, Anfrage-Wizard, Kontaktformular) | Vorgangsnummer FG-JJ-NNN, Status, Bestätigung an Kunden, Info an events@, Termin-Links an Platz-Kontakte | nichts, außer bei „Von Firmengolf organisiert" |
| 2 Termin | Platz-Kontakte stimmen ab, oder Julius bestätigt in Schritt 1 | Erinnerungen, Eskalation, Termin-Bestätigung | Selbstplaner: Preis beim Platz holen, Schritt 2 füllen, Termin bestätigen |
| 3 Angebot | Termin bestätigt | Angebot mit PDF an Kunden, Frist 7 Tage, Erinnerung, Eskalation | nur bei unbepreisten Zusatzwünschen: Positionen bepreisen, „Angebot jetzt senden" |
| 4 Annahme | Kunde klickt „Angebot annehmen" | Auftrag steht (intern), Event gebucht (Platz), Buchung bestätigt mit PDF (Kunde), Dienstleister-Aufträge | Platz zuordnen (falls noch nicht), Schritt 4 ausfüllen, Details abstimmen |
| 5 Vortag | Cron 07:00 am Tag vor dem Termin | Vortags-Info an Kunde, Platz, events@ | nichts, Kontrolle über die interne Mail |
| 6 Event | Eventtag | nichts | Erreichbarkeit |
| 7 Nachlauf | Status „Event durchgeführt" | Bewertungsbitte mit Google-Link | Rechnung in Lexoffice, Status setzen, Eingangsrechnung des Platzes prüfen, abschließen |

## Phase 1: Eingang

**Wege ins System:** Anfrage-Dialog auf einer Eventseite (Wunschtermine, Teilnehmer, Startzeit, Zusatzwünsche, Kontaktwunsch), allgemeiner Anfrage-Wizard, Kontaktformular, Rückruf-Widget. Budget-Rechner erzeugt nur einen Lead.

**System:** legt die Anfrage an (Nummer FG-26-NNN, Status `neu`, dann `eingangsbestaetigung_gesendet`), Kunden-Token für die Statusseite `/angebot/<token>/`.

**Mails:**
- Kunde: „Deine Anfrage bei Firmengolf ist eingegangen" mit Nummer und Status-Link.
- events@: „Neue Event-Anfrage: <Firma>" mit allen Feldern, Kontaktwunsch, Wünschen, Admin-Link. Rote Warnung, wenn ein Platz zugeordnet ist, aber kein Kontakt mit Mailadresse existiert.
- Platz (nur bei Event mit Partner): jeder Ansprechpartner bekommt seinen persönlichen Termin-Link `/termin/<token>/`; ohne Kontakte eine einfache Verfügbarkeitsanfrage an die Platz-Mailadresse.

**Julius:** Bei Platzhalter-Events („Golf-Schnupperkurs für Teams in Hamburg", „Von Firmengolf organisiert") gibt es keinen Platz. Julius sucht den Platz, holt den Preis und macht weiter mit Phase 2b.

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
- Kunde nach 3 Tagen ohne Reaktion: „Erinnerung: euer Angebot" (nur solange die Frist läuft).
- events@ nach Fristablauf: „Angebot überfällig", bei Rückfrage des Kunden „Rückfrage zum Angebot" (nach 2 Tagen unbeantwortet erneut intern).
- events@ falls die Angebotsmail nicht zugestellt werden konnte: „Angebot nicht zugestellt".

**Julius:** Rückfragen beantworten (Antwort per Mail, ggf. Positionen ändern; ein neues Angebot ist derzeit nur über eine neue Anfrage möglich). Nach Fristablauf: Termin beim Platz prüfen, dann den Kunden erneut zum Link schicken.

## Phase 4: Annahme

**Kunde** klickt mit AGB-Häkchen „Angebot annehmen", wählt Zusatzleistungen ab oder an. Status `angebot_angenommen`. Nach Fristablauf wird eine Annahme zur Rückfrage (Termin nicht mehr garantiert), Julius prüft und bestätigt manuell.

**Mails:**
- events@: „Auftrag steht: FG-…" mit Termin, Firma, Event, Platz, Abrechnungsübersicht der Zusatzleistungen (Einkauf, Marge, Dienstleister, nur intern), Hinweis auf Schritt 4.
- Platz: „Event gebucht: FG-…" (ohne Preise).
- Kunde: „Buchung bestätigt: FG-…" mit Link zur Buchungsübersicht und PDF.
- Dienstleister je Position: Auftrag oder Absage mit dem vereinbarten Einkaufspreis.

**Julius:**
1. Platz zuordnen, falls noch nicht (Anfrage Basis).
2. Schritt 4 „Event-Tag": Startzeit, Treffpunkt, Ansprechpartner vor Ort mit Telefon, Golflehrer, Hinweise. Vorbelegt aus dem Partnerprofil.
3. Details mit dem Platz abstimmen (Telefon oder Mail, außerhalb des Systems).

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

**Sonderfälle:** Kunde sagt ab → Status `angebot_abgelehnt`, interne Mail, Dienstleister bekommen Absagen. Kein Termin möglich → `nicht_verfuegbar`. Anfrage versandet → `verloren` (von Hand).

## Beispiel FG-26-165 (Kanzlei Blumenau, Stand 18.09.)

| Schritt | Stand |
|---|---|
| Eingang 15.09. über die Eventseite „Golf-Schnupperkurs für Teams in Hamburg" | erledigt, Bestätigung und interne Mail raus |
| Preis beim Weidenhof geholt (49 € brutto p.P.), Kundin telefonisch 52 € netto p.P. zugesagt | erledigt |
| Schritt 2: 52,00 € netto p.P., Ort Golfpark Weidenhof, Ablauf, Leistungen; Adresse der Kanzlei | erledigt 17./18.09. |
| Schritt 1: „Mi, 30.09.2026" bestätigen, Angebot geht mit PDF an Frau Bolten | offen, wartet auf Julius' Freigabe |
| Weidenhof zuordnen | danach |
| Annahme durch die Kundin (bis 25.09.) | offen |
| Schritt 4: Startzeit 12:00 Uhr, Treffpunkt, Ansprechpartner Weidenhof, Pro | nach der Annahme |
| Vortags-Info 29.09. um 07:00 | automatisch |
| Event 30.09. mittags, 3 Stunden, 6 Personen | |
| Status „Event durchgeführt", Bewertungsbitte, Rechnung 371,28 € brutto, Eingangsrechnung 294 € brutto, abschließen | danach |
