# Mail-Inventar Firmengolf

Alle transaktionalen Mails der Plattform. Quelle der Wahrheit ist der Code
(`includes/emails.php`, `email-verification.php`, `partner-invite.php`,
`partner-portal.php`, `request-followups.php`, `login-branding.php`).
Stand: 2026-07-08 (FGE_VERSION 1.9.83, nach Kern-Audit).

**Gemeinsamer Stil:** Rahmen `fge_email_wrap()` (Firmengolf-Kopf, Impressum-Fußzeile),
Absender `events@firmengolf-events.de` (mail-config.php, inkl. Reply-To),
Buttons über `fge_email_button()` (einheitlich kompakt), Versand via WP Mail SMTP → Brevo.
Keine Gedankenstriche, Preise im Format „XX € p.P./Gesamt netto".

## A · Kundenanfrage

| # | Betreff | Auslöser | An wen | Inhalt |
|---|---------|----------|--------|--------|
| 1 | Deine Anfrage bei Firmengolf ist eingegangen | Anfrage abgeschickt (Event-Modal, Wizard, Kontakt); Hook `fge_request_created` | Kunde | Eingangsbestätigung, wie es weitergeht (Rückmeldung binnen 1 Werktag), Status-Link-Button |
| 2 | Neue Firmengolf Event Anfrage: {Firma} | wie 1 | intern (events@) | Alle Anfrage-Daten, Wunsch-Leistungen, Admin-Button |
| 3 | Eine Firmenanfrage wartet auf deine Rückmeldung ({FG-Nr}) | Anfrage mit Wunschterminen bei zugeordnetem Platz | Partner-Terminkontakte | Wunschtermine + „Jetzt Termine bestätigen"-Button (Freigabe-Link) |
| 4 | Neue Verfügbarkeitsanfrage für ein Firmengolf Event | wie 3, aber Partner ohne Terminkontakte | Partner (Hauptkontakt) | Bitte Verfügbarkeit prüfen |

## B · Terminfindung (Cron täglich + Statuswechsel)

| # | Betreff | Auslöser | An wen | Inhalt |
|---|---------|----------|--------|--------|
| 5 | Erinnerung: kurze Rückmeldung zu einer Firmenanfrage ({FG-Nr}) | Terminkontakt antwortet 2 Tage nicht (Cron, Filter `fge_request_reminder_days`) | Partner-Kontakt | Erinnerung + Link |
| 6 | Überfällig: Anfrage {FG-Nr} braucht Aufmerksamkeit | weiterhin keine Antwort (Cron-Eskalation) | intern | Admin-Link zum Eingreifen |
| 7 | Kein Termin möglich: {FG-Nr} | alle Kontakte geantwortet, Ergebnis nicht verfügbar (`fge_request_all_responded`) | intern | Alternativen anstoßen |
| 8 | Alle Rückmeldungen da, Termin bestätigen ({FG-Nr}) | alle Kontakte geantwortet, Termin machbar | Partner (Hauptkontakt, Fallback Event-Kontakt) | Bitte final bestätigen |
| 9 | Termin bestätigt: {FG-Nr} ({Platz}) | Partner bestätigt Termin (`fge_request_date_confirmed`) | intern | Angebot kann raus |
| 10 | Euer Termin steht: {Event} am {Datum} | Terminbestätigung | Kunde | Termin fix, Angebot folgt |

## C · Angebot und Buchung

| # | Betreff | Auslöser | An wen | Inhalt |
|---|---------|----------|--------|--------|
| 11 | Euer Angebot für {Event} ({FG-Nr}) | Angebot versendet | Kunde | Positionen inkl. bepreister Zusatzleistungen (Verkaufspreis), Summe netto + zzgl. 19 % MwSt + Endpreis (mit „ca." bei p.P.), Hinweis auf Einzelabwahl, Frist, Annehmen-Button |
| 12 | Erinnerung: euer Angebot ({FG-Nr}) | Kunde reagiert 3 Tage nicht (Cron, Filter `fge_offer_reminder_days`) | Kunde | Frist läuft, Termin noch reserviert |
| 13 | Rückfrage zum Angebot: {FG-Nr} | Kunde stellt Rückfrage oder nimmt nach Fristablauf an (`fge_offer_query`, idempotent) | intern | Rückfrage-Text bzw. „Termin prüfen" |
| 14 | Auftrag steht: {FG-Nr} | Kunde nimmt an (`fge_offer_accepted`) | intern | Buchung eingegangen + Abrechnungsübersicht der Zusatzleistungen (Kundenpreis, Einkauf, Marge, Dienstleister; nur intern) |
| 15 | Event gebucht: {FG-Nr} | Kunde nimmt an | Partner | Euer Platz ist gebucht (ohne Zusatzleistungs-Details) |
| 16 | Buchung bestätigt: {FG-Nr} | Kunde nimmt an | Kunde | Verbindliche Buchungsbestätigung |
| 17 | Angebot abgelehnt: {FG-Nr} | Kunde lehnt ab (`fge_offer_declined`, mit Confirm-Dialog) | intern | Absage + Kontext |
| 17a | Auftragsbestätigung Firmengolf: {Leistung} am {Datum} ({FG-Nr}) | Kunde nimmt an, Position gewählt (`fge_xs_provider_confirmation`) | Dienstleister der Zusatzleistung | Beauftragung mit vereinbartem Einkaufspreis, Termin, Ort, Teilnehmer, Rechnungsadresse; nie Verkaufspreis/Marge |
| 17b | Absage Firmengolf: {Leistung} am {Datum} ({FG-Nr}) | Position vom Kunden abgewählt, Angebot abgelehnt oder Anfrage terminal geschlossen (`fge_xs_provider_cancellation`, einmaliger Guard) | Dienstleister der Zusatzleistung | Kein Auftrag, freundliche Absage |

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

## E · Partnerportal und Events

| # | Betreff | Auslöser | An wen | Inhalt |
|---|---------|----------|--------|--------|
| 25 | Neues Event-Angebot zur Prüfung: {Event} / Event-Änderung zur Prüfung: {Event} | Partner reicht Event ein (`fge_event_submitted`) | intern | Prüfen-und-freigeben-Button |
| 26 | Eingegangen: {Event} / Änderung eingegangen: {Event} | Partner reicht Event ein | Partner | Eingangsbestätigung, „wir prüfen kurz" |
| 27 | Freigegeben: {Event} / Rückmeldung zu deinem Angebot: {Event} | Admin gibt frei oder lehnt ab (`fge_event_reviewed`) | Partner | Live-Info bzw. Überarbeiten-Hinweis |
| 28 | Dein Golfplatz ist jetzt live auf Firmengolf | Partner-Status → aktiv | Partner | Freischaltung + öffentlicher Link |
| 29 | Kurze Rückfragen zu deinem Golfplatz-Profil / Dein Golfplatz-Profil bei Firmengolf | Partner-Status → Rückfrage bzw. abgelehnt | Partner | Was fehlt bzw. Absage |
| 30 | Kontakt-E-Mail eures Firmengolf-Portals geändert | Kontaktmail-Wechsel per Code verifiziert | alte Adresse | Sicherheitsinfo „warst du das nicht, melde dich" |

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
