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
