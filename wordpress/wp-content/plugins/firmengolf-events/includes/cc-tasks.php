<?php
/**
 * Control Center: Phasen, Klarnamen für Status und abgeleitete Aufgaben.
 *
 * Aufgaben werden NICHT von Hand gepflegt, sondern aus dem Zustand der Anfrage
 * berechnet. Dieselbe Logik, die request-followups.php intern per Mail meldet,
 * nur sichtbar. Von Hand kommen nur eigene Aufgaben (_fge_cc_tasks) und das
 * Schlummern (_fge_cc_snooze) dazu.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Status in Klartext. Bis hierhin gab es nirgends eine zentrale Übersetzung. */
function fge_cc_status_label( string $status ): string {
	$map = [
		'neu'                           => 'Neu',
		'eingangsbestaetigung_gesendet' => 'Eingang bestätigt',
		'verfuegbarkeit_wird_geprueft'  => 'Verfügbarkeit wird geprüft',
		'partner_angefragt'             => 'Platz angefragt',
		'teilweise_verfuegbar'          => 'Teilweise verfügbar',
		'vollstaendig_verfuegbar'       => 'Vollständig verfügbar',
		'nicht_verfuegbar'              => 'Nicht verfügbar',
		'in_uebernahme'                 => 'In Übernahme',
		'bestaetigt'                    => 'Termin bestätigt',
		'telefonat_offen'               => 'Telefonat offen',
		'telefonat_erledigt'            => 'Telefonat erledigt',
		'angebot_in_lexoffice_erstellt' => 'Angebot in Lexoffice',
		'angebot_versendet'             => 'Angebot versendet',
		'angebot_rueckfrage'            => 'Rückfrage offen',
		'angebot_angenommen'            => 'Gebucht',
		'angebot_abgelehnt'             => 'Abgelehnt',
		'event_durchgefuehrt'           => 'Event durchgeführt',
		'rechnung_in_lexoffice_erstellt' => 'Rechnung geschrieben',
		'abgeschlossen'                 => 'Abgeschlossen',
		'verloren'                      => 'Verloren',
	];
	return $map[ $status ] ?? ( '' !== $status ? $status : 'Neu' );
}

/** Status, bei denen nichts mehr zu tun ist. */
function fge_cc_terminal_statuses(): array {
	return [ 'abgeschlossen', 'verloren', 'angebot_abgelehnt', 'nicht_verfuegbar' ];
}

/**
 * Phase einer Anfrage: [nummer, name].
 * Deckungsgleich mit docs/prozess-anfrage-bis-rechnung.md.
 */
function fge_cc_phase( int $req ): array {
	$status = (string) get_post_meta( $req, '_fge_request_status', true );
	$sent   = '1' === (string) get_post_meta( $req, '_fge_offer_sent', true );
	$offer  = (string) get_post_meta( $req, '_fge_offer_status', true );
	$final  = function_exists( 'fge_rr_final_index' ) ? fge_rr_final_index( $req ) : 0;

	if ( in_array( $status, [ 'abgeschlossen', 'rechnung_in_lexoffice_erstellt', 'event_durchgefuehrt' ], true ) ) {
		return [ 7, 'Nachlauf' ];
	}
	if ( 'accepted' === $offer ) {
		$date = fge_cc_event_date( $req );
		return $date > 0 && $date < time() ? [ 6, 'Eventtag' ] : [ 5, 'Vorbereitung' ];
	}
	if ( $sent ) {
		return [ 4, 'Angebot läuft' ];
	}
	if ( $final > 0 ) {
		return [ 3, 'Angebot' ];
	}
	if ( 'neu' === $status || '' === $status || 'eingangsbestaetigung_gesendet' === $status ) {
		return [ 1, 'Eingang' ];
	}
	return [ 2, 'Termin' ];
}

/** Eventdatum als Zeitstempel, 0 wenn keines feststeht. */
function fge_cc_event_date( int $req ): int {
	if ( function_exists( 'fge_day_event_date' ) ) {
		$d = fge_day_event_date( $req );
		if ( is_numeric( $d ) && (int) $d > 0 ) {
			return (int) $d;
		}
		if ( is_string( $d ) && '' !== $d ) {
			return (int) strtotime( $d );
		}
	}
	$snap = (array) get_post_meta( $req, '_fge_offer_snapshot', true );
	$date = (string) ( $snap['date'] ?? '' );
	return '' !== $date ? (int) strtotime( $date ) : 0;
}

/** Alter in Tagen seit dem letzten Statuswechsel. */
function fge_cc_age_days( int $req ): int {
	$last = (string) get_post_meta( $req, '_fge_last_status_change', true );
	if ( '' === $last ) {
		$post = get_post( $req );
		$last = $post ? $post->post_date : '';
	}
	if ( '' === $last ) {
		return 0;
	}
	return max( 0, (int) floor( ( time() - (int) strtotime( $last ) ) / DAY_IN_SECONDS ) );
}

/** Schlummert die Anfrage gerade? */
function fge_cc_is_snoozed( int $req ): bool {
	$until = (int) get_post_meta( $req, '_fge_cc_snooze', true );
	return $until > 0 && time() < $until;
}

/**
 * Offene Aufgaben einer Anfrage.
 *
 * Je Eintrag: key, text, who (me|them), urgency (now|soon|wait), action
 * (Schlüssel für den Knopf im Cockpit, leer wenn es nichts zu klicken gibt).
 */
function fge_cc_tasks( int $req ): array {
	$status = (string) get_post_meta( $req, '_fge_request_status', true );
	if ( in_array( $status, fge_cc_terminal_statuses(), true ) ) {
		return [];
	}
	if ( function_exists( 'fge_is_demo_request' ) && fge_is_demo_request( $req ) ) {
		return [];
	}

	$tasks      = [];
	$add        = static function ( $key, $text, $who, $urgency, $action = '' ) use ( &$tasks ) {
		$tasks[] = [ 'key' => $key, 'text' => $text, 'who' => $who, 'urgency' => $urgency, 'action' => $action ];
	};
	$partner_id = (int) get_post_meta( $req, '_fge_assigned_partner_id', true );
	$sent       = '1' === (string) get_post_meta( $req, '_fge_offer_sent', true );
	$hold       = '1' === (string) get_post_meta( $req, '_fge_offer_hold', true );
	$offer      = (string) get_post_meta( $req, '_fge_offer_status', true );
	$final      = function_exists( 'fge_rr_final_index' ) ? fge_rr_final_index( $req ) : 0;
	$age        = fge_cc_age_days( $req );

	// ── Vor dem Angebot ───────────────────────────────────────────────────
	if ( ! $sent ) {
		// Plätze, die in der Liste stehen, aber nie angefragt wurden, gehen
		// sonst im Kopf verloren.
		$idle = function_exists( 'fge_venues_idle_counts' ) ? ( fge_venues_idle_counts()[ $req ] ?? 0 ) : 0;
		if ( $idle > 0 ) {
			$add( 'ask_venues', sprintf(
				'%d %s in der Liste noch nicht angefragt',
				$idle,
				1 === $idle ? 'Platz' : 'Plätze'
			), 'me', 'now', 'venues' );
		}
		if ( $partner_id <= 0 ) {
			// Frische Anfrage ist dringend, eine zwei Monate alte ist es nicht mehr.
			// Sonst steht alles auf Rot und Rot bedeutet nichts mehr.
			$add( 'find_venue', 'Passende Plätze finden und anfragen', 'me', $age <= 10 ? 'now' : 'soon', 'venues' );
		} elseif ( 0 === $final ) {
			$add( 'await_venue', sprintf( 'Platz antwortet seit %d %s nicht', $age, 1 === $age ? 'Tag' : 'Tagen' ),
				'them', $age >= 3 ? 'now' : 'wait' );
		}
		if ( $hold ) {
			$add( 'price_extras', 'Zusatzleistungen bepreisen, dann Angebot senden', 'me', 'now', 'offer_send' );
		} elseif ( $final > 0 ) {
			$add( 'send_offer', 'Angebot senden', 'me', 'now', 'offer_send' );
		}
	}

	// ── Angebot läuft ─────────────────────────────────────────────────────
	if ( $sent && 'pending' === $offer ) {
		$deadline = (int) get_post_meta( $req, '_fge_offer_deadline', true );
		if ( 'angebot_rueckfrage' === $status ) {
			$add( 'answer_query', 'Rückfrage des Kunden beantworten', 'me', 'now' );
		} elseif ( $deadline > 0 && time() > $deadline ) {
			$add( 'offer_overdue', 'Angebotsfrist ist abgelaufen, nachfassen', 'me', 'now' );
		} else {
			$add( 'await_customer', $deadline > 0
				? 'Kunde entscheidet, Frist bis ' . date_i18n( 'j. F', $deadline )
				: 'Kunde entscheidet', 'them', 'wait' );
		}
	}

	// ── Gebucht ───────────────────────────────────────────────────────────
	if ( 'accepted' === $offer ) {
		$start   = (string) get_post_meta( $req, '_fge_day_start_time', true );
		$meeting = (string) get_post_meta( $req, '_fge_day_meeting_point', true );
		$date    = fge_cc_event_date( $req );

		if ( $partner_id > 0 && '' === fge_cc_partner_email( $partner_id ) ) {
			$add( 'partner_no_mail', 'Der Platz hat keine Kontaktmail, Buchungs- und Vortagsinfo gehen nicht raus', 'me', 'now' );
		}
		// Alles rund um den Eventtag nur, solange der Tag noch bevorsteht.
		$upcoming = ( $date <= 0 || $date >= strtotime( 'today' ) )
			&& ! in_array( $status, [ 'event_durchgefuehrt', 'rechnung_in_lexoffice_erstellt' ], true );

		// Eine Woche vor dem Event wird das Ausfüllen dringend, vorher ist es Vorarbeit.
		$near = $date > 0 && $date <= strtotime( '+7 days' );

		if ( $upcoming ) {
			if ( '' === $start || '' === $meeting ) {
				$add( 'fill_day', 'Eventtag ausfüllen: Startzeit und Treffpunkt', 'me', $near ? 'now' : 'soon', 'day' );
			} elseif ( '' === (string) get_post_meta( $req, '_fge_day_plan_sent', true ) ) {
				// Gate hält einen Zeitstempel, nicht "1".
				$add( 'send_day_plan', 'Ablauf an den Kunden schicken', 'me', 'soon', 'day_plan' );
			}
		}
		if ( $date > 0 && $date < strtotime( 'today' ) && 'event_durchgefuehrt' !== $status
			&& 'rechnung_in_lexoffice_erstellt' !== $status ) {
			$add( 'mark_done', 'Event ist vorbei, Status auf „Event durchgeführt" setzen', 'me', 'now', 'done' );
		}
	}

	// ── Nachlauf ──────────────────────────────────────────────────────────
	if ( 'event_durchgefuehrt' === $status
		&& '1' !== (string) get_post_meta( $req, '_fge_lexoffice_invoice_created', true ) ) {
		// Erst nach ein paar Tagen dringend, direkt nach dem Event darf es liegen.
		$done_since = fge_cc_event_date( $req );
		$overdue    = $done_since > 0 && $done_since < strtotime( '-3 days' );
		$add( 'write_invoice', 'Rechnung in Lexoffice schreiben', 'me', $overdue ? 'now' : 'soon' );
	}
	if ( 'rechnung_in_lexoffice_erstellt' === $status ) {
		$add( 'check_payment', 'Zahlungseingang prüfen, dann abschließen', 'me', 'soon', 'close' );
	}

	// ── Eigene Aufgaben ───────────────────────────────────────────────────
	foreach ( (array) get_post_meta( $req, '_fge_cc_tasks', true ) as $i => $own ) {
		$text = is_array( $own ) ? (string) ( $own['text'] ?? '' ) : (string) $own;
		if ( '' !== trim( $text ) ) {
			$add( 'own_' . $i, $text, 'me', 'soon' );
		}
	}

	// Dringendstes zuerst, damit Listen, die nur eine Zeile zeigen, die
	// richtige zeigen. Eigene Reihenfolge innerhalb einer Stufe bleibt erhalten.
	$rank = [ 'now' => 0, 'soon' => 1, 'wait' => 2 ];
	usort( $tasks, static fn( $a, $b ) => $rank[ $a['urgency'] ] <=> $rank[ $b['urgency'] ] );

	return $tasks;
}

/** Kontaktmail eines Platzes, leer wenn keine hinterlegt ist. */
function fge_cc_partner_email( int $partner_id ): string {
	if ( $partner_id <= 0 ) {
		return '';
	}
	$mail = (string) get_post_meta( $partner_id, '_fge_event_contact_email', true );
	if ( '' === $mail ) {
		$mail = (string) get_post_meta( $partner_id, '_fge_main_contact_email', true );
	}
	return is_email( $mail ) ? $mail : '';
}

/** Nur die Aufgaben, die auf Julius warten. */
function fge_cc_tasks_mine( int $req ): array {
	return array_values( array_filter( fge_cc_tasks( $req ), static fn( $t ) => 'me' === $t['who'] ) );
}

/**
 * Ab wann gilt ein Vorgang als verstaubt.
 *
 * Ohne diese Grenze flutet jede alte Anfrage ohne Platz die Arbeitsliste mit
 * „Passende Plätze finden", obwohl niemand mehr daran arbeitet. Verstaubte
 * Vorgänge verschwinden nicht, sie wandern in einen eigenen Eimer.
 */
function fge_cc_cold_days(): int {
	return (int) apply_filters( 'fge_cc_cold_days', 45 );
}

/**
 * Alle offenen Anfragen mit ihren Aufgaben, sortiert nach Dringlichkeit.
 * Basis für Dashboard und Tagesmail. Innerhalb eines Aufrufs gecacht, weil
 * Seitenleiste und Inhalt dieselbe Liste brauchen.
 */
function fge_cc_worklist(): array {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$requests = get_posts( [
		'post_type'   => 'firmengolf_request',
		'post_status' => [ 'publish', 'draft' ],
		'numberposts' => 300,
		'fields'      => 'ids',
		'orderby'     => 'date',
		'order'       => 'DESC',
	] );

	$rank = [ 'now' => 0, 'soon' => 1, 'wait' => 2 ];
	$rows = [];
	foreach ( $requests as $req ) {
		$tasks = fge_cc_tasks( $req );
		if ( ! $tasks ) {
			continue;
		}
		$mine    = array_filter( $tasks, static fn( $t ) => 'me' === $t['who'] );
		$urgency = 'wait';
		foreach ( $tasks as $t ) {
			if ( $rank[ $t['urgency'] ] < $rank[ $urgency ] ) {
				$urgency = $t['urgency'];
			}
		}
		$phase  = fge_cc_phase( $req );
		$age    = fge_cc_age_days( $req );
		$date   = fge_cc_event_date( $req );
		// Verstaubt: lange kein Fortschritt und kein Termin in der Zukunft.
		$cold   = $age >= fge_cc_cold_days() && ( $date <= 0 || $date < strtotime( 'today' ) );
		$rows[] = [
			'req'     => $req,
			'ref'     => function_exists( 'fge_request_number' ) ? fge_request_number( $req ) : (string) $req,
			'company' => (string) get_post_meta( $req, '_fge_company_name', true ),
			'phase'   => $phase[1],
			'status'  => fge_cc_status_label( (string) get_post_meta( $req, '_fge_request_status', true ) ),
			'tasks'   => $tasks,
			'mine'    => (bool) $mine,
			'urgency' => $urgency,
			'age'     => $age,
			'date'    => $date,
			'cold'    => $cold,
			'snoozed' => fge_cc_is_snoozed( $req ),
		];
	}

	usort( $rows, static function ( $a, $b ) use ( $rank ) {
		// Verstaubtes immer nach hinten, dann was auf mich wartet, dann Dringlichkeit.
		if ( $a['cold'] !== $b['cold'] ) {
			return $a['cold'] ? 1 : -1;
		}
		if ( $a['mine'] !== $b['mine'] ) {
			return $a['mine'] ? -1 : 1;
		}
		if ( $a['urgency'] !== $b['urgency'] ) {
			return $rank[ $a['urgency'] ] <=> $rank[ $b['urgency'] ];
		}
		// Bei gleicher Dringlichkeit zuerst, was einen nahen Termin hat.
		if ( ( $a['date'] > 0 ) !== ( $b['date'] > 0 ) ) {
			return $a['date'] > 0 ? -1 : 1;
		}
		if ( $a['date'] > 0 && $b['date'] > 0 && $a['date'] !== $b['date'] ) {
			return $a['date'] <=> $b['date'];
		}
		return $b['age'] <=> $a['age'];
	} );

	$cache = $rows;
	return $rows;
}
