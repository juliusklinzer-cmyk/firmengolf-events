<?php
/**
 * Mail-Registry: jede transaktionale Mail einmal beschrieben.
 *
 * Eine Quelle für vier Verbraucher, damit nichts auseinanderläuft:
 *   1. die Folgen-Vorschau am Aktionsknopf („wer bekommt gleich was")
 *   2. der Postausgang (Klarnamen statt Betreffzeilen)
 *   3. die Phasenleiste im Control Center
 *   4. docs/mail-inventar.md (erzeugt per bash tests/mail-inventar.sh)
 *
 * Grundsatz aus dem Plan vom 22.09.2026: keine Aktion ohne sichtbare Folge.
 * Fehlt ein Empfänger, muss das VOR dem Klick sichtbar sein, nicht als stille
 * Nichtzustellung hinterher (Befund Golfpark Weidenhof).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Alle Mails. Schlüssel ist stabil und wird im Postausgang mitgeschrieben.
 *
 * party:   kunde | platz | dienstleister | intern
 * auto:    true  = läuft ohne Zutun (Hook oder Cron), false = jemand klickt
 * content: Stichpunkte, bewusst kurz, sie stehen unter dem Knopf
 */
function fge_mail_registry(): array {
	static $reg = null;
	if ( null !== $reg ) {
		return $reg;
	}
	$reg = [
		// ── Phase 1: Eingang ──────────────────────────────────────────────
		'request_customer_ack' => [
			'label'   => 'Eingangsbestätigung',
			'party'   => 'kunde',
			'subject' => 'Deine Anfrage bei Firmengolf ist eingegangen',
			'auto'    => true,
			'trigger' => 'Anfrage abgeschickt',
			'content' => [ 'Vorgangsnummer', 'wie es weitergeht', 'Link zur Statusseite' ],
		],
		'request_internal' => [
			'label'   => 'Neue Anfrage (intern)',
			'party'   => 'intern',
			'subject' => 'Neue Firmengolf Event Anfrage',
			'auto'    => true,
			'trigger' => 'Anfrage abgeschickt',
			'content' => [ 'alle Anfragefelder', 'Wünsche', 'Partnercode', 'Admin-Link' ],
		],
		'request_contact_dates' => [
			'label'   => 'Terminabstimmung an die Platz-Kontakte',
			'party'   => 'platz',
			'subject' => 'Eine Firmenanfrage wartet auf deine Rückmeldung',
			'auto'    => true,
			'trigger' => 'Anfrage mit zugeordnetem Platz',
			'content' => [ 'Wunschtermine', 'persönlicher Zusage-Link', 'kein Login nötig' ],
		],
		'request_partner_availability' => [
			'label'   => 'Verfügbarkeitsanfrage (Fallback)',
			'party'   => 'platz',
			'subject' => 'Neue Verfügbarkeitsanfrage für ein Firmengolf Event',
			'auto'    => true,
			'trigger' => 'Platz ohne Terminkontakte',
			'content' => [ 'Bitte um Prüfung der Verfügbarkeit' ],
		],

		// ── Platz-Pipeline ────────────────────────────────────────────────
		// Diese beiden gehören zu einem einzelnen angefragten Platz, nicht zur
		// Anfrage als Ganzes. Die Empfänger löst die Pipeline selbst auf
		// (per_venue), deshalb liefert fge_mail_recipients() hier nichts.
		'venue_request' => [
			'label'     => 'Anfrage an einen Platz',
			'party'     => 'platz',
			'per_venue' => true,
			'subject'   => 'Passt das bei euch? Firmenanfrage',
			'auto'      => false,
			'trigger'   => 'Knopf in der Platz-Pipeline',
			'content'   => [ 'Wunschtermine', 'Gruppe mit Personenzahl und Niveau', 'Liste der Positionen mit Preisfrage', 'bei uns notierte Vorpreise' ],
		],
		'venue_decline' => [
			'label'     => 'Absage an einen nicht gewählten Platz',
			'party'     => 'platz',
			'per_venue' => true,
			'subject'   => 'Doch woanders: Firmenanfrage',
			'auto'      => false,
			'trigger'   => 'Knopf nach der Platzwahl',
			'content'   => [ 'persönliche Absage', 'ehrlicher Grund', 'Einladung für die nächste Anfrage', 'Einladung, das Angebot dauerhaft anzubieten' ],
		],
		'venue_catalog_internal' => [
			'label'     => 'Platz will dauerhaft anbieten',
			'party'     => 'intern',
			'per_venue' => true,
			'subject'   => 'Platz will dauerhaft anbieten',
			'auto'      => true,
			'trigger'   => 'Platz klickt Ja auf dem Link aus der Absage-Mail',
			'content'   => [ 'Platz und Vorgangsnummer', 'Link zum Event-Entwurf', 'Hinweis auf Einladungsmail bei Stammdaten-Platz' ],
		],
		'venue_summary' => [
			'label'     => 'Absprache bestätigt: so haben wir es notiert',
			'party'     => 'platz',
			'per_venue' => true,
			'subject'   => 'Danke für die Rückmeldung: Firmenanfrage',
			'auto'      => false,
			'trigger'   => 'Knopf in der Pipeline-Zeile nach der erfassten Antwort',
			'content'   => [ 'freie Termine und Alternativvorschlag', 'Preise je Position wie besprochen', 'nicht angebotene Positionen', 'Bitte, die Termine vorläufig freizuhalten' ],
		],
		'venue_reservation' => [
			'label'     => 'Reservierungsbitte: Angebot ist beim Kunden',
			'party'     => 'platz',
			'per_venue' => true,
			'subject'   => 'Bitte reservieren bis <Frist>: Firmenanfrage',
			'auto'      => true,
			'trigger'   => 'Versand eines Angebots mit Optionen, an jeden Platz im Angebot',
			'content'   => [ 'Entscheidungsfrist des Kunden', 'Termine, die reserviert bleiben sollen', 'Preise je Position wie besprochen' ],
		],
		'venue_release' => [
			'label'     => 'Termin wird frei: der Kunde hat abgesagt',
			'party'     => 'platz',
			'per_venue' => true,
			'subject'   => 'Termin wird frei: Firmenanfrage',
			'auto'      => false,
			'trigger'   => 'Knopf nach der Absage des Kunden',
			'content'   => [ 'Vorgangsnummer und Termin', 'kein Auftrag', 'Dank und Einladung für die nächste Anfrage' ],
		],

		// ── Phase 2: Termin ───────────────────────────────────────────────
		'date_reminder' => [
			'label'   => 'Erinnerung an die Platz-Kontakte',
			'party'   => 'platz',
			'subject' => 'Erinnerung: kurze Rückmeldung zu einer Firmenanfrage',
			'auto'    => true,
			'trigger' => 'Cron, nach zwei Tagen ohne Antwort',
			'content' => [ 'Erinnerung', 'derselbe Link' ],
		],
		'date_all_responded' => [
			'label'   => 'Alle Rückmeldungen da',
			'party'   => 'platz',
			'subject' => 'Alle Rückmeldungen da, Termin bestätigen',
			'auto'    => true,
			'trigger' => 'alle Kontakte haben geantwortet',
			'content' => [ 'bitte final bestätigen' ],
		],
		'date_confirmed_internal' => [
			'label'   => 'Termin bestätigt (intern)',
			'party'   => 'intern',
			'subject' => 'Termin bestätigt',
			'auto'    => true,
			'trigger' => 'Termin bestätigt',
			'content' => [ 'Termin', 'Platz', 'ob das Angebot rausgeht oder hängt' ],
		],
		'date_confirmed_customer' => [
			'label'   => 'Termin steht (nur wenn das Angebot hängt)',
			'party'   => 'kunde',
			'subject' => 'Euer Termin steht',
			'auto'    => true,
			'trigger' => 'Termin bestätigt, Angebot zurückgehalten',
			'content' => [ 'Termin ist fix', 'Angebot folgt' ],
		],

		// ── Phase 3: Angebot ──────────────────────────────────────────────
		'offer_customer' => [
			'label'   => 'Angebot an den Kunden',
			'party'   => 'kunde',
			'subject' => 'Euer Angebot für {Event}',
			'auto'    => false,
			'trigger' => 'Termin bestätigt und bepreist, oder Knopf „Angebot jetzt senden"',
			'content' => [ 'Positionen und Summen', 'Partnercode-Rabatt', 'Frist', 'Annehmen-Link', 'PDF im Anhang', 'BCC an dich' ],
		],
		'offer_reminder' => [
			'label'   => 'Angebotserinnerung',
			'party'   => 'kunde',
			'subject' => 'Erinnerung: euer Angebot',
			'auto'    => true,
			'trigger' => 'Cron, drei Tage ohne Reaktion',
			'content' => [ 'Frist läuft', 'Termin noch reserviert' ],
		],
		'offer_query_internal' => [
			'label'   => 'Rückfrage des Kunden (intern)',
			'party'   => 'intern',
			'subject' => 'Rückfrage zum Angebot',
			'auto'    => true,
			'trigger' => 'Kunde stellt eine Rückfrage',
			'content' => [ 'Rückfragetext' ],
		],

		// ── Phase 4: Annahme ──────────────────────────────────────────────
		'order_internal' => [
			'label'   => 'Auftrag steht (intern)',
			'party'   => 'intern',
			'subject' => 'Auftrag steht',
			'auto'    => true,
			'trigger' => 'Kunde nimmt an',
			'content' => [ 'Termin, Firma, Platz', 'Abrechnung der Zusatzleistungen', 'Einkauf und Marge', 'Partnercode' ],
		],
		'booking_partner' => [
			'label'   => 'Auftragsbestätigung an den Platz',
			'party'   => 'platz',
			'subject' => 'Buchung bestätigt',
			'auto'    => true,
			'trigger' => 'Kunde nimmt an',
			'content' => [ 'Termin und Startzeit', 'Gruppe, Personenzahl, Niveau', 'Ansprechpartner der Gruppe', 'Paket und Leistungen', 'vereinbarter Betrag', 'unsere Rechnungsadresse', 'Verwendungszweck' ],
		],
		'booking_customer' => [
			'label'   => 'Buchungsbestätigung an den Kunden',
			'party'   => 'kunde',
			'subject' => 'Buchung bestätigt',
			'auto'    => true,
			'trigger' => 'Kunde nimmt an',
			'content' => [ 'Dank und Termin', 'PDF der Buchungsbestätigung', 'Link zur Buchung' ],
		],
		'provider_confirm' => [
			'label'   => 'Auftrag an den Dienstleister',
			'party'   => 'dienstleister',
			'subject' => 'Auftragsbestätigung Firmengolf',
			'auto'    => true,
			'trigger' => 'Kunde nimmt an, Position gebucht',
			'content' => [ 'Leistung, Termin, Ort', 'vereinbarter Einkaufspreis', 'Rechnungsadresse', 'nie Verkaufspreis' ],
		],
		'provider_cancel' => [
			'label'   => 'Absage an den Dienstleister',
			'party'   => 'dienstleister',
			'subject' => 'Absage Firmengolf',
			'auto'    => true,
			'trigger' => 'Position abgewählt oder Angebot abgelehnt',
			'content' => [ 'kein Auftrag', 'freundliche Absage' ],
		],
		'offer_declined_internal' => [
			'label'   => 'Angebot abgelehnt (intern)',
			'party'   => 'intern',
			'subject' => 'Angebot abgelehnt',
			'auto'    => true,
			'trigger' => 'Kunde lehnt ab',
			'content' => [ 'Absage mit Kontext' ],
		],

		// ── Zwischen Buchung und Vortag ───────────────────────────────────
		'day_plan_customer' => [
			'label'   => 'Ablauf für den Eventtag an den Kunden',
			'party'   => 'kunde',
			'subject' => 'Der Ablauf für euren Eventtag steht',
			'auto'    => false,
			'trigger' => 'Startzeit und Treffpunkt erstmals vollständig, oder Knopf',
			'content' => [ 'Wann und wo mit Adresse', 'Treffpunkt', 'Ansprechpartner vor Ort', 'was mitzubringen ist', 'Ablauf folgt am Vortag', 'Mobilnummer' ],
		],
		'venue_details_reminder' => [
			'label'   => 'Erinnerung an den Platz: Details für den Eventtag',
			'party'   => 'platz',
			'subject' => 'Kurze Bitte: Details für den Eventtag',
			'auto'    => true,
			'trigger' => 'Cron, drei Tage nach der Buchung ohne Eintrag, nur vor dem Termin',
			'content' => [ 'Termin und Gruppe', 'welche Angaben fehlen', 'Link zum Formular ohne Login', 'bis wann' ],
		],
		'venue_details_internal' => [
			'label'   => 'Platzdetails eingetragen (intern)',
			'party'   => 'intern',
			'subject' => 'Platzdetails eingetragen',
			'auto'    => true,
			'trigger' => 'Platz speichert das Formular aus der Auftragsbestätigung',
			'content' => [ 'alle sieben Eventtag-Felder, fehlende in Rot', 'ob die Ablauf-Info an den Kunden raus ist', 'Link zur Anfrage' ],
		],

		// ── Phase 5: Vortag ───────────────────────────────────────────────
		'day_info_customer' => [
			'label'   => 'Vortags-Info an den Kunden',
			'party'   => 'kunde',
			'subject' => 'Morgen ist es soweit',
			'auto'    => true,
			'trigger' => 'Cron 07:00 am Vortag, oder Knopf',
			'content' => [ 'Start, Treffpunkt, Adresse', 'Ansprechpartner vor Ort', 'Golflehrer und Ablauf', 'gebuchte Leistungen', 'Mobilnummer' ],
		],
		'day_info_partner' => [
			'label'   => 'Vortags-Info an den Platz',
			'party'   => 'platz',
			'subject' => 'Morgen: Firmengolf-Event',
			'auto'    => true,
			'trigger' => 'Cron 07:00 am Vortag, oder Knopf',
			'content' => [ 'Firma und Personenzahl', 'Start und Treffpunkt', 'Kontakt beim Kunden', 'gebuchte Leistungen', 'keine Preise' ],
		],
		'day_info_internal' => [
			'label'   => 'Vortags-Info verschickt (intern)',
			'party'   => 'intern',
			'subject' => 'Vortags-Info verschickt',
			'auto'    => true,
			'trigger' => 'Cron 07:00 am Vortag, oder Knopf',
			'content' => [ 'Zusammenfassung', 'fehlende Angaben in Rot' ],
		],

		'catalog_proposal' => [
			'label'   => 'Vorschlag: Event dauerhaft anbieten',
			'party'   => 'platz',
			'subject' => 'Wollt ihr das dauerhaft anbieten?',
			'auto'    => false,
			'trigger' => 'Knopf nach dem Event',
			'content' => [ 'Titel, Ort und Ablauf wie durchgeführt', 'Gruppengröße', 'euer Preis', 'Inklusivleistungen', 'Antwortlink ohne Login' ],
		],

		// ── Control Center ────────────────────────────────────────────────
		'daily_digest' => [
			'label'   => 'Tagesmail',
			'party'   => 'intern',
			'subject' => 'Heute bei Firmengolf',
			'auto'    => true,
			'trigger' => 'Cron 07:00, nur wenn es etwas zu melden gibt',
			'content' => [ 'fällige Aufgaben mit Vorgangsnummer', 'Termine heute und morgen', 'Link ins Control Center' ],
		],

		// ── Phase 7: Nachlauf ─────────────────────────────────────────────
		'review_customer' => [
			'label'   => 'Bewertungsbitte',
			'party'   => 'kunde',
			'subject' => 'Danke für euer Event mit Firmengolf',
			'auto'    => true,
			'trigger' => 'Status „Event durchgeführt"',
			'content' => [ 'Bitte um Google-Bewertung' ],
		],
	];
	return $reg;
}

/** Eine Mail aus der Registry, leeres Array wenn unbekannt. */
function fge_mail_meta( string $key ): array {
	return fge_mail_registry()[ $key ] ?? [];
}

/** Anzeigename einer Mail, sonst der Schlüssel selbst. */
function fge_mail_label( string $key ): string {
	return (string) ( fge_mail_meta( $key )['label'] ?? $key );
}

// ── Empfängerauflösung ───────────────────────────────────────────────────────

/**
 * Wer bekommt diese Mail bei DIESER Anfrage, mit echten Namen und Adressen.
 *
 * Rückgabe je Zeile: role, name, email, missing (bool), note.
 * missing = true heißt: die Mail kann nicht zugestellt werden. Das ist der
 * Fall, den die Vorschau in Rot zeigen muss.
 */
function fge_mail_recipients( string $key, int $req ): array {
	$meta = fge_mail_meta( $key );
	if ( ! $meta || ! empty( $meta['per_venue'] ) ) {
		// Mails an einen einzelnen Platz der Pipeline lösen ihre Empfänger dort
		// auf, nicht über den zugeordneten Platz der Anfrage.
		return [];
	}
	$data = function_exists( 'fge_get_request_email_data' ) ? fge_get_request_email_data( $req ) : [];
	$rows = [];

	switch ( $meta['party'] ) {
		case 'kunde':
			$name = trim( (string) ( $data['first_name'] ?? '' ) . ' ' . (string) ( $data['last_name'] ?? '' ) );
			$mail = (string) ( $data['contact_email'] ?? '' );
			$rows[] = [
				'role'    => 'Kunde',
				'name'    => $name ?: (string) ( $data['company_name'] ?? 'Ansprechpartner' ),
				'email'   => $mail,
				'missing' => ! is_email( $mail ),
				'note'    => 'offer_customer' === $key ? 'BCC an dich' : '',
			];
			break;

		case 'platz':
			$rows = fge_mail_partner_recipients( $key, $req, $data );
			break;

		case 'dienstleister':
			$rows = fge_mail_provider_recipients( $key, $req );
			break;

		case 'intern':
			$to = function_exists( 'fge_company_internal_email' ) ? fge_company_internal_email() : '';
			$rows[] = [
				'role'    => 'Intern',
				'name'    => 'Firmengolf',
				'email'   => $to,
				'missing' => ! is_email( $to ),
				'note'    => '',
			];
			break;
	}

	return $rows;
}

/** Empfänger beim Platz: Kontaktadresse plus die Leute aus der Terminabstimmung. */
function fge_mail_partner_recipients( string $key, int $req, array $data ): array {
	$partner_id = (int) ( $data['partner_id'] ?? 0 );
	$rows       = [];
	$seen       = [];

	$main = (string) ( $data['partner_email'] ?? '' );
	if ( is_email( $main ) ) {
		$rows[] = [
			'role'    => 'Platz',
			'name'    => (string) ( $data['partner_title'] ?? 'Golfplatz' ),
			'email'   => $main,
			'missing' => false,
			'note'    => '',
		];
		$seen[ strtolower( $main ) ] = true;
	}

	// Die Auftragsbestätigung geht zusätzlich an alle, die den Termin abgestimmt
	// haben: das sind die Leute, die die Gruppe tatsächlich in Empfang nehmen.
	if ( 'booking_partner' === $key && function_exists( 'fge_rr_responders' ) ) {
		foreach ( fge_rr_responders( $req ) as $c ) {
			$mail = (string) ( $c['email'] ?? '' );
			if ( ! is_email( $mail ) || isset( $seen[ strtolower( $mail ) ] ) ) {
				continue;
			}
			$seen[ strtolower( $mail ) ] = true;
			$rows[] = [
				'role'    => 'Platz',
				'name'    => trim( (string) ( $c['name'] ?? '' ) ) ?: 'Ansprechpartner',
				'email'   => $mail,
				'missing' => false,
				'note'    => (string) ( $c['role'] ?? '' ),
			];
		}
	}

	if ( ! $rows ) {
		$rows[] = [
			'role'    => 'Platz',
			'name'    => (string) ( $data['partner_title'] ?? '' ) ?: 'kein Platz zugeordnet',
			'email'   => '',
			'missing' => true,
			'note'    => $partner_id > 0
				? 'Keine Kontaktmail hinterlegt, diese Mail geht nicht raus'
				: 'Der Anfrage ist kein Platz zugeordnet',
		];
	}

	return $rows;
}

/** Empfänger bei den Zusatzleistungen, getrennt nach gebucht und abgewählt. */
function fge_mail_provider_recipients( string $key, int $req ): array {
	if ( ! function_exists( 'fge_xs_priced' ) ) {
		return [];
	}
	$selected = function_exists( 'fge_offer_selected_extras' ) ? fge_offer_selected_extras( $req ) : [];
	$want_on  = 'provider_confirm' === $key;
	$rows     = [];

	foreach ( fge_xs_priced( $req ) as $src => $item ) {
		if ( 'platz' === (string) ( $item['organizer'] ?? '' ) ) {
			continue; // Der Platz bekommt die Buchungsbestätigung, keinen Dienstleister-Auftrag.
		}
		$on = in_array( (int) $src, $selected, true );
		if ( $on !== $want_on ) {
			continue;
		}
		$mail = (string) ( $item['provider_email'] ?? '' );
		$rows[] = [
			'role'    => 'Dienstleister',
			'name'    => trim( (string) ( $item['provider_name'] ?? '' ) ) ?: (string) $item['label'],
			'email'   => $mail,
			'missing' => ! is_email( $mail ),
			'note'    => (string) $item['label'] . ( is_email( $mail ) ? '' : ', ohne Mail, bitte selbst anrufen' ),
		];
	}

	return $rows;
}

// ── Folgen-Vorschau ──────────────────────────────────────────────────────────

/** Welche Mails löst welche Aktion aus. */
function fge_mail_action_map(): array {
	return [
		'offer_send'    => [ 'offer_customer' ],
		'offer_accept'  => [ 'order_internal', 'booking_partner', 'booking_customer', 'provider_confirm', 'provider_cancel' ],
		'offer_decline' => [ 'offer_declined_internal', 'provider_cancel' ],
		'day_plan'      => [ 'day_plan_customer' ],
		'day_info'      => [ 'day_info_customer', 'day_info_partner', 'day_info_internal' ],
		'date_confirm'  => [ 'date_confirmed_internal', 'offer_customer' ],
		'review'        => [ 'review_customer' ],
	];
}

/**
 * Vollständige Vorschau einer Aktion: je Mail die aufgelösten Empfänger.
 * Mails ohne Empfänger (z. B. keine abgewählten Positionen) fallen raus.
 */
function fge_mail_action_preview( string $action, int $req ): array {
	$out = [];
	foreach ( fge_mail_action_map()[ $action ] ?? [] as $key ) {
		$rows = fge_mail_recipients( $key, $req );
		if ( ! $rows ) {
			continue;
		}
		$meta  = fge_mail_meta( $key );
		$out[] = [
			'key'        => $key,
			'label'      => (string) $meta['label'],
			'party'      => (string) $meta['party'],
			'content'    => (array) $meta['content'],
			'recipients' => $rows,
			'has_gap'    => (bool) array_filter( $rows, static fn( $r ) => ! empty( $r['missing'] ) ),
		];
	}
	return $out;
}
