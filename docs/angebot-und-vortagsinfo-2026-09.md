# Angebotsdokument, PDF und Vortags-Info (Stand 17.09.2026)

Gebaut am 17.09.2026 anhand der ersten echten Buchung FG-26-165 (Kanzlei Blumenau, Schnupperkurs Golfpark Weidenhof, 30.09.2026, 6 Personen, 52 € netto p.P.).

## Angebot (1.9.262 bis 1.9.263)

- Eine Vorlage für Angebotsseite (`/angebot/<token>/`), PDF (`/angebot/<token>/pdf/`) und Angebotsmail: `includes/offer-document.php`. Aufbau wie ein klassisches Angebot: Kopf (Empfänger, Angebotsnummer, Datum, Gültig bis, Ansprechpartner), Positionstabelle, Summenblock netto / USt. / Gesamtbetrag mit exakten Beträgen, Konditionen, Pflichtangaben.
- Position 1 ist das Event mit Termin, Ort, Ablauf und Leistungen zusammen; jede Zusatzleistung ist eine eigene Position (auf der Webseite abwählbar, JS rechnet exakt nach).
- Steuer steht nur im Summenblock, Begriff durchgehend „USt.". Unter der Tabelle nur der Teilnehmerhinweis („Berechnungsbasis 6 Teilnehmer. Falls sich an der Teilnehmerzahl etwas ändert, gebt uns bitte frühzeitig Bescheid.") und der AGB-Satz. Die verbindliche Regel (Storno, Fristen) steht allein in den AGB (Julius, 17.09.).
- PDF über Dompdf 3.1.6 (`lib/dompdf`, LGPL, 317 Dateien, Deploy mit `deploy-plugin-dir.sh lib/dompdf`). Font-Cache und temporäre PDFs liegen in `uploads/fge-pdf-cache/` (per .htaccess gesperrt). Das PDF hängt an der Angebotsmail (`Angebot-FG-26-165.pdf`). Fehlt die Bibliothek, fallen Button und Anhang still weg.
- Schritt 2 im Admin hat drei neue Felder unter dem Preis-Override: Veranstaltungsort, Ablauf und Zeiten, Leistungen (eine je Zeile). Sie übersteuern die Angaben des zugeordneten Events im Snapshot (`_fge_offer_location`, `_fge_offer_schedule`, `_fge_offer_includes`). Nötig bei Platzhalter-Events wie „Golf-Schnupperkurs für Teams in Hamburg".
- Straße und PLZ des Kunden erscheinen im Angebotskopf, wenn sie in den Unternehmensdaten der Anfrage stehen.

## Bugfix Speichern der Anfrage (1.9.263)

Die Metaboxen „Schritt 1: Terminabstimmung" (Termin bestätigen, Koordination übernehmen) und „Schritt 3: Angebot" (Angebot jetzt senden) hatten eigene `<form>`-Elemente innerhalb des WordPress-Formulars. Der Browser verwirft verschachtelte Formulare, die versteckten Felder (`action`, `request_id`, Nonce) landeten im Hauptformular und überschrieben `action=editpost`. Folge: „Aktualisieren" leitete auf die Beitragsliste um und speicherte nichts, die Buttons selbst griffen nicht. Jetzt: `fge_admin_post_button()` in `helpers.php` baut beim Klick per JS ein eigenes Formular außerhalb von `#post` und schickt es an `admin-post.php`. Regel: nie ein `<form>` in einer Metabox.

## Vortags-Info (1.9.264)

`includes/event-day-info.php`. Täglicher Cron `fge_event_day_info_cron` um 07:00 Site-Zeit: für angenommene Angebote, deren Termin morgen ist (Nachholer: heute), gehen einmalig drei Mails raus, Gate `_fge_day_info_sent`.

- Kunde: „Morgen ist es soweit: <Event> am <Termin>" mit Start, Treffpunkt, Ort und Adresse des Platzes, Ansprechpartner vor Ort, Golflehrer, Ablauf, Teilnehmer, Hinweise, gebuchte Leistungen, Kontakt für den Tag (Julius mobil), Link auf die Buchungsübersicht.
- Golfplatz (Event- oder Hauptkontakt des Partners, nur wenn zugeordnet): „Morgen: Firmengolf-Event <Firma> am <Termin>", Sie-Form, mit „Die Gruppe meldet sich bei", Kundenkontakt, gebucht und bestätigt.
- events@: Zusammenfassung, welche Mails rausgingen, fehlende Angaben rot markiert.

Box „Schritt 4: Event-Tag (Vortags-Info)" in der Anfrage: Startzeit, Treffpunkt, Ansprechpartner vor Ort (Name, Telefon), Golflehrer / Pro, Hinweise. Vorbelegt aus dem Partnerprofil (Event-Ansprechpartner, Golfschule) und der Startzeit im Ablauf. Button „Vortags-Info jetzt senden" für manuellen oder erneuten Versand. Die interne „Auftrag steht"-Mail erinnert daran, die Box auszufüllen.

## Ablauf FG-26-165 (Stand 17.09.)

1. Schritt 2 gefüllt: 52 € netto p.P., Ort „Golfpark Weidenhof, Pinneberg", Ablauf, Leistungen (17.09.).
2. Erledigt 18.09.: Termin „Mi, 30.09.2026" bestätigt, Angebot mit PDF an Frau Bolten (BCC Julius), Frist 25.09., Erinnerung nach 3 Tagen.
3. Erledigt 18.09.: Golfpark Weidenhof zugeordnet (nach der Bestätigung).
4. Nach Annahme: Schritt 4 ausfüllen (Treffpunkt, Ansprechpartner vor Ort), Vortags-Info läuft am 29.09. um 07:00 automatisch.
5. Nach dem Event: Status „event_durchgefuehrt" (Bewertungsbitte), Rechnung in Lexoffice (312 € netto, 371,28 € brutto), Weidenhof stellt 294 € brutto in Rechnung.
