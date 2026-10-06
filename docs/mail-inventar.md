# Mail-Inventar Firmengolf

Erzeugt aus `includes/mail-registry.php` (eine Quelle für Folgen-Vorschau, Postausgang, Phasenleiste und dieses Dokument). Stand: 06.10.2026, Version 1.9.295, 32 Mails. Neu erzeugen nach jeder Änderung an der Registry: `bash tests/mail-inventar.sh`.

Gemeinsamer Stil: Rahmen `fge_email_wrap()`, Absender events@firmengolf-events.de (mail-config.php, auch Reply-To), Buttons `fge_email_button()`, Versand über WP Mail SMTP und Brevo, Protokoll je Versand in `wp_fge_mail_log` (mail-log.php, Zuordnung über die Vorgangsnummer im Betreff). Keine Gedankenstriche, Preise als „XX € p.P.“ oder „XX € netto“.

Spalte „Auslöser“: automatisch heißt Hook oder Cron ohne Klick, sonst löst jemand die Mail am Knopf aus.

## An Firmenkunden (8)

| Schlüssel | Mail | Betreff | Auslöser | Inhalt |
|---|---|---|---|---|
| `request_customer_ack` | Eingangsbestätigung | Deine Anfrage bei Firmengolf ist eingegangen | Anfrage abgeschickt (automatisch) | Vorgangsnummer, wie es weitergeht, Link zur Statusseite |
| `date_confirmed_customer` | Termin steht (nur wenn das Angebot hängt) | Euer Termin steht | Termin bestätigt, Angebot zurückgehalten (automatisch) | Termin ist fix, Angebot folgt |
| `offer_customer` | Angebot an den Kunden | Euer Angebot für {Event} | Termin bestätigt und bepreist, oder Knopf „Angebot jetzt senden" (Knopf) | Positionen und Summen, Partnercode-Rabatt, Frist, Annehmen-Link, PDF im Anhang, BCC an dich |
| `offer_reminder` | Angebotserinnerung | Erinnerung: euer Angebot | Cron, drei Tage ohne Reaktion (automatisch) | Frist läuft, Termin noch reserviert |
| `booking_customer` | Buchungsbestätigung an den Kunden | Buchung bestätigt | Kunde nimmt an (automatisch) | Dank und Termin, PDF der Buchungsbestätigung, Link zur Buchung |
| `day_plan_customer` | Ablauf für den Eventtag an den Kunden | Der Ablauf für euren Eventtag steht | Startzeit und Treffpunkt erstmals vollständig, oder Knopf (Knopf) | Wann und wo mit Adresse, Treffpunkt, Ansprechpartner vor Ort, was mitzubringen ist, Ablauf folgt am Vortag, Mobilnummer |
| `day_info_customer` | Vortags-Info an den Kunden | Morgen ist es soweit | Cron 07:00 am Vortag, oder Knopf (automatisch) | Start, Treffpunkt, Adresse, Ansprechpartner vor Ort, Golflehrer und Ablauf, gebuchte Leistungen, Mobilnummer |
| `review_customer` | Bewertungsbitte | Danke für euer Event mit Firmengolf | Status „Event durchgeführt" (automatisch) | Bitte um Google-Bewertung |

## An Golfplätze und Simulatoren (13)

| Schlüssel | Mail | Betreff | Auslöser | Inhalt |
|---|---|---|---|---|
| `request_contact_dates` | Terminabstimmung an die Platz-Kontakte | Eine Firmenanfrage wartet auf deine Rückmeldung | Anfrage mit zugeordnetem Platz (automatisch) | Wunschtermine, persönlicher Zusage-Link, kein Login nötig |
| `request_partner_availability` | Verfügbarkeitsanfrage (Fallback) | Neue Verfügbarkeitsanfrage für ein Firmengolf Event | Platz ohne Terminkontakte (automatisch) | Bitte um Prüfung der Verfügbarkeit |
| `venue_request` | Anfrage an einen Platz | Passt das bei euch? Firmenanfrage | Knopf in der Platz-Pipeline (Knopf) | Wunschtermine, Gruppe mit Personenzahl und Niveau, Liste der Positionen mit Preisfrage, bei uns notierte Vorpreise |
| `venue_decline` | Absage an einen nicht gewählten Platz | Doch woanders: Firmenanfrage | Knopf nach der Platzwahl (Knopf) | persönliche Absage, ehrlicher Grund, Einladung für die nächste Anfrage, Einladung, das Angebot dauerhaft anzubieten |
| `venue_summary` | Absprache bestätigt: so haben wir es notiert | Danke für die Rückmeldung: Firmenanfrage | Knopf in der Pipeline-Zeile nach der erfassten Antwort (Knopf) | freie Termine und Alternativvorschlag, Preise je Position wie besprochen, nicht angebotene Positionen, Bitte, die Termine vorläufig freizuhalten |
| `venue_reservation` | Reservierungsbitte: Angebot ist beim Kunden | Bitte reservieren bis <Frist>: Firmenanfrage | Versand eines Angebots mit Optionen, an jeden Platz im Angebot (automatisch) | Entscheidungsfrist des Kunden, Termine, die reserviert bleiben sollen, Preise je Position wie besprochen |
| `venue_release` | Termin wird frei: der Kunde hat abgesagt | Termin wird frei: Firmenanfrage | Knopf nach der Absage des Kunden (Knopf) | Vorgangsnummer und Termin, kein Auftrag, Dank und Einladung für die nächste Anfrage |
| `date_reminder` | Erinnerung an die Platz-Kontakte | Erinnerung: kurze Rückmeldung zu einer Firmenanfrage | Cron, nach zwei Tagen ohne Antwort (automatisch) | Erinnerung, derselbe Link |
| `date_all_responded` | Alle Rückmeldungen da | Alle Rückmeldungen da, Termin bestätigen | alle Kontakte haben geantwortet (automatisch) | bitte final bestätigen |
| `booking_partner` | Auftragsbestätigung an den Platz | Buchung bestätigt | Kunde nimmt an (automatisch) | Termin und Startzeit, Gruppe, Personenzahl, Niveau, Ansprechpartner der Gruppe, Paket und Leistungen, vereinbarter Betrag, unsere Rechnungsadresse, Verwendungszweck |
| `venue_details_reminder` | Erinnerung an den Platz: Details für den Eventtag | Kurze Bitte: Details für den Eventtag | Cron, drei Tage nach der Buchung ohne Eintrag, nur vor dem Termin (automatisch) | Termin und Gruppe, welche Angaben fehlen, Link zum Formular ohne Login, bis wann |
| `day_info_partner` | Vortags-Info an den Platz | Morgen: Firmengolf-Event | Cron 07:00 am Vortag, oder Knopf (automatisch) | Firma und Personenzahl, Start und Treffpunkt, Kontakt beim Kunden, gebuchte Leistungen, keine Preise |
| `catalog_proposal` | Vorschlag: Event dauerhaft anbieten | Wollt ihr das dauerhaft anbieten? | Knopf nach dem Event (Knopf) | Titel, Ort und Ablauf wie durchgeführt, Gruppengröße, euer Preis, Inklusivleistungen, Antwortlink ohne Login |

## An Dienstleister (2)

| Schlüssel | Mail | Betreff | Auslöser | Inhalt |
|---|---|---|---|---|
| `provider_confirm` | Auftrag an den Dienstleister | Auftragsbestätigung Firmengolf | Kunde nimmt an, Position gebucht (automatisch) | Leistung, Termin, Ort, vereinbarter Einkaufspreis, Rechnungsadresse, nie Verkaufspreis |
| `provider_cancel` | Absage an den Dienstleister | Absage Firmengolf | Position abgewählt oder Angebot abgelehnt (automatisch) | kein Auftrag, freundliche Absage |

## Intern an Firmengolf (9)

| Schlüssel | Mail | Betreff | Auslöser | Inhalt |
|---|---|---|---|---|
| `request_internal` | Neue Anfrage (intern) | Neue Firmengolf Event Anfrage | Anfrage abgeschickt (automatisch) | alle Anfragefelder, Wünsche, Partnercode, Admin-Link |
| `venue_catalog_internal` | Platz will dauerhaft anbieten | Platz will dauerhaft anbieten | Platz klickt Ja auf dem Link aus der Absage-Mail (automatisch) | Platz und Vorgangsnummer, Link zum Event-Entwurf, Hinweis auf Einladungsmail bei Stammdaten-Platz |
| `date_confirmed_internal` | Termin bestätigt (intern) | Termin bestätigt | Termin bestätigt (automatisch) | Termin, Platz, ob das Angebot rausgeht oder hängt |
| `offer_query_internal` | Rückfrage des Kunden (intern) | Rückfrage zum Angebot | Kunde stellt eine Rückfrage (automatisch) | Rückfragetext |
| `order_internal` | Auftrag steht (intern) | Auftrag steht | Kunde nimmt an (automatisch) | Termin, Firma, Platz, Abrechnung der Zusatzleistungen, Einkauf und Marge, Partnercode |
| `offer_declined_internal` | Angebot abgelehnt (intern) | Angebot abgelehnt | Kunde lehnt ab (automatisch) | Absage mit Kontext |
| `venue_details_internal` | Platzdetails eingetragen (intern) | Platzdetails eingetragen | Platz speichert das Formular aus der Auftragsbestätigung (automatisch) | alle sieben Eventtag-Felder, fehlende in Rot, ob die Ablauf-Info an den Kunden raus ist, Link zur Anfrage |
| `day_info_internal` | Vortags-Info verschickt (intern) | Vortags-Info verschickt | Cron 07:00 am Vortag, oder Knopf (automatisch) | Zusammenfassung, fehlende Angaben in Rot |
| `daily_digest` | Tagesmail | Heute bei Firmengolf | Cron 07:00, nur wenn es etwas zu melden gibt (automatisch) | fällige Aufgaben mit Vorgangsnummer, Termine heute und morgen, Link ins Control Center |


# Anhang (von Hand gepflegt, nicht in der Registry): Onboarding, Portal, System

Diese Mails liegen außerhalb des Anfrage-Prozesses und stehen noch nicht in `mail-registry.php`. Quelle: email-verification.php, partner-invite.php, partner-portal.php, login-branding.php. Stand 1.9.83 mit Nachträgen bis 1.9.269.

## D · Onboarding und Einladung (Partner-Gewinnung)

| # | Betreff | Auslöser | An wen | Inhalt |
|---|---------|----------|--------|--------|
| 18 | Dein Firmengolf-Bestätigungscode | E-Mail-Eingabe in Onboarding, Einladung oder Portal-Mailwechsel (`fge_ev_send_code`) | Partner | 6-stelliger Code, 15 Min gültig, max. 5 Versuche |
| 19 | Dein Golfplatz wurde zur Prüfung eingereicht | Onboarding abgeschlossen | Partner | Eingang bestätigt, Vorgangsnummer, Portal-Button |
| 20 | Neuer Partner eingereicht: {Platz} | Onboarding abgeschlossen | intern | Kontaktdaten + Admin-Button |
| 21 | Willkommen bei Firmengolf: Dein Zugang zum Partner-Portal | neues Konto im Onboarding erstellt | Partner | Passwort-setzen-Link, Portal-Intro |
| 22 | Dein Firmengolf-Konto wurde mit {Platz} verknüpft | bestehendes Konto verknüpft (Admin) | Partner | Info über die Verknüpfung |
| 23 | Dein Firmengolf-Onboarding: Hier geht es weiter | „Speichern und später fortsetzen" im Onboarding | Partner | Fortsetzungs-Link (Resume-Token) |
| 24 | Partner-Zugang aktiviert: {Platz} | Einladungslink eingelöst | intern | Wer hat eingelöst (Name + Mail) |
| 24a | Firmenkunden für {Platz}: Euer Profil bei Firmengolf ist vorbereitet | Versand-Button im Einladungs-Modal (Partner-Liste, `fge_send_partner_invite_email`) | Club (kalt/warm) | Standardisierte Start-Mail: Vorstellung Julius, Profil vorbereitet, kostenlos/provisionsbasiert, Magic-Link-CTA, Telefon, Opt-out-Satz |
| 24b | Kurze Erinnerung: Euer vorbereitetes Profil bei Firmengolf wartet | Einladung nach 5 Tagen nicht eingelöst (Cron, Filter `fge_invite_reminder_days`, genau 1×) | Club | Freundlicher Nachfass mit Link + Telefon |
| 24c | Dein Zugang zum Firmengolf Partner-Portal ist aktiv | Einladungslink eingelöst (`fge_send_invite_access_email`) | Partner | Schriftliche Zugangs-Bestätigung: Login-Mail, Passwort-vergessen-Hinweis, Portal-Button, nächste Schritte |

## E · Partnerportal und Events

| # | Betreff | Auslöser | An wen | Inhalt |
|---|---------|----------|--------|--------|
| 25 | Neues Event-Angebot zur Prüfung: {Event} / Event-Änderung zur Prüfung: {Event} | Partner reicht Event ein (`fge_event_submitted`) | intern | Prüfen-und-freigeben-Button |
| 26 | Eingegangen: {Event} / Änderung eingegangen: {Event} | Partner reicht Event ein | Partner | Eingangsbestätigung, „wir prüfen kurz" |
| 27 | Freigegeben: {Event} / Rückmeldung zu deinem Angebot: {Event} | Admin gibt frei oder lehnt ab (`fge_event_reviewed`) | Partner | Live-Info bzw. Überarbeiten-Hinweis |
| 28 | Dein Golfplatz ist jetzt live auf Firmengolf | Partner-Status → aktiv | Partner | Freischaltung + öffentlicher Link |
| 29 | Kurze Rückfragen zu deinem Golfplatz-Profil / Dein Golfplatz-Profil bei Firmengolf | Partner-Status → Rückfrage bzw. abgelehnt | Partner | Was fehlt bzw. Absage |
| 30 | Kontakt-E-Mail eures Firmengolf-Portals geändert | Kontaktmail-Wechsel per Code verifiziert | alte Adresse | Sicherheitsinfo „warst du das nicht, melde dich" |

## E2 · Control Center (seit 1.9.269)

| # | Betreff | Auslöser | An wen | Inhalt |
|---|---------|----------|--------|--------|
| 30a | Heute bei Firmengolf: {n} Aufgaben, {m} Termine | Cron täglich 07:00 Uhr Site-Zeit (`fge_cc_daily_digest`), nur wenn es etwas zu melden gibt | intern | Fällige Aufgaben mit Vorgangsnummer und Firma, Termine heute und morgen aus dem abgeleiteten Kalender, Link ins Control Center. Fängt ab, dass Outlook abonnierte Kalender nur träge aktualisiert |

## F · System

| # | Betreff | Auslöser | An wen | Inhalt |
|---|---------|----------|--------|--------|
| 31 | Passwort-Reset (Firmengolf-gebrandet) | „Passwort vergessen" am Login (`retrieve_password`-Filter) | Nutzer | Reset-Link im Firmengolf-Design |


## Interne Cron-Eskalationen (request-followups.php, täglich)

| Betreff | Auslöser | An wen |
|---------|----------|--------|
| Anfrage ohne Ansprechpartner: {FG-Nr} | Anfrage ohne zugeordneten Kontakt bleibt liegen | intern |
| Feinplanung offen: {FG-Nr} | gebuchtes Event ohne Feinplanung nach 2 Tagen (Filter `fge_offer_hold_reminder_days`) | intern |
| Rückfrage unbeantwortet: {FG-Nr} | Kundenrückfrage 2 Tage offen | intern |
| Angebot überfällig: {FG-Nr} | bestätigter Termin, aber kein Angebot versendet | intern |
| Offene Partner-Einladungen: {n} | mind. 1 versendete Einladung uneingelöst, max. 1× pro Woche (`fge_invite_run_followups`, partner-invite.php) | intern |
