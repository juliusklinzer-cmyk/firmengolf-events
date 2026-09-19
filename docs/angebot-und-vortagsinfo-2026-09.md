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

## Partnercodes für Multiplikatoren (1.9.266, 19.09.2026)

Entscheidung Julius 18.09.: Codes nur für Multiplikatoren (Content Creator, DGV Jugendförderung, GMVD, Agenturen), keine Golfplätze. Ein Code je Partner, angelegt nur im Backend (Menü „Partnercodes"). Rabatt je Code einstellbar (Standard 5 % auf die Netto-Zwischensumme), Provision fester Eurobetrag je angenommenem Angebot. Beides geht zulasten der Firmengolf-Marge, `FGE_MARKUP_PERCENT` bleibt bei 20.

- Eingabe: Feld „Partnercode (optional)" im Event-Dialog (Schritt 2) und im Wizard (Schnell-, Voll- und Budgetmodus), Livecheck mit grünem/rotem Hinweis und „Entfernen". Link `?pc=CODE` merkt den Code 90 Tage im Browser (localStorage, kein Cookie). Weihnachts-Kurzformular übergibt den gemerkten Code unsichtbar.
- Angebot: Zeile „Partnercode CODE (Inhaber), 5 % Rabatt" im Summenblock (Web, PDF, Mail), Neuberechnung beim Abwählen von Extras. Auf dem Handy bricht die Rabattzeile um, der Betrag bleibt rechts.
- Mails: Zeile in „Neue Event-Anfrage", Satz in der Kundenbestätigung, Rabattzeile und Hinweis „Der Rabatt über euren Partnercode ist bereits abgezogen." in der Angebotsmail, Block „Partnercode (intern)" in „Auftrag steht".
- Provision: bei `fge_offer_accepted` einmalig „offen", bei `angebot_abgelehnt` / `verloren` / `nicht_verfuegbar` storniert, Abrechnung per Button am Code (einzeln oder alle offenen).
- Sicherheit: AJAX-Check mit Nonce und Rate-Limit (20 pro 10 Minuten), jede Fehlerart liefert dieselbe generische Meldung, die Partner-Mailadresse kommt nie ins Frontend.
- Testlauf lokal: Code DGVTEST (5 %, 50 €), Anfrage mit Extra 120 € netto: Zwischensumme 432,00 €, Rabatt 21,60 €, USt. 77,98 €, Gesamt 488,38 €; ohne Extra 312,00 / 15,60 / 56,32 / 352,72 €. Provision offen, nach Absage storniert, nach Abrechnen „abgerechnet".
- Offen: Provisionsgutschrift läuft außerhalb des Systems (Lexoffice), Codes auf Live noch keine angelegt.
