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
 * Anfragen holen, mit ehrlicher Obergrenze.
 *
 * Bei zwanzig Anfragen am Tag kommen im Jahr rund fünftausend zusammen. Eine
 * pauschale Grenze würde dann stillschweigend Vorgänge verschlucken, ohne dass
 * es jemandem auffällt. Deshalb zwei Dinge: der Arbeitsvorrat wird über den
 * Status eingegrenzt statt über eine Zahl, und wenn doch abgeschnitten wird,
 * sagt die Oberfläche es.
 *
 * @return array{ids: int[], truncated: bool, limit: int}
 */
function fge_cc_query_requests( array $args = [], int $limit = 500 ): array {
	$defaults = [
		'post_type'      => 'firmengolf_request',
		'post_status'    => [ 'publish', 'draft' ],
		'posts_per_page' => $limit + 1, // eine mehr, um das Abschneiden zu erkennen
		'fields'         => 'ids',
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	];
	$ids = get_posts( array_merge( $defaults, $args ) );

	$truncated = count( $ids ) > $limit;
	if ( $truncated ) {
		$ids = array_slice( $ids, 0, $limit );
	}
	if ( $ids ) {
		_prime_post_caches( $ids, false, true );
	}
	return [ 'ids' => $ids, 'truncated' => $truncated, 'limit' => $limit ];
}

/** Meta-Bedingung: nur Vorgänge, an denen noch etwas zu tun ist. */
function fge_cc_open_meta_query(): array {
	return [
		'relation' => 'OR',
		[
			'key'     => '_fge_request_status',
			'value'   => fge_cc_terminal_statuses(),
			'compare' => 'NOT IN',
		],
		[
			'key'     => '_fge_request_status',
			'compare' => 'NOT EXISTS', // frisch angelegt, noch ohne Status
		],
	];
}

/** Hinweis, wenn eine Liste abgeschnitten wurde. */
function fge_cc_truncation_note( array $result, string $what ): void {
	if ( empty( $result['truncated'] ) ) {
		return;
	}
	echo '<p class="cc-msg cc-msg--err">Es werden die neuesten ' . (int) $result['limit'] . ' ' . esc_html( $what )
		. ' gezeigt, es gibt ältere. Grenze die Liste über Suche oder Filter ein.</p>';
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
	// Verlorene Vorgänge hießen bis 28.09.2026 weiter „Angebot läuft" (Audit).
	if ( 'verloren' === $status ) {
		return [ 4, 'Verloren' ];
	}
	if ( 'nicht_verfuegbar' === $status ) {
		return [ 2, 'Nicht verfügbar' ];
	}
	if ( 'angebot_abgelehnt' === $status || 'declined' === $offer ) {
		return [ 4, 'Abgelehnt' ];
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

/**
 * Mitternacht heute in der Zeitzone der Site.
 *
 * strtotime('today') rechnet in der Serverzeit, und die ist unter WordPress
 * UTC. Alle Vergleiche und Zeitstempel im Control Center laufen deshalb über
 * diesen Helfer, sonst liegen Kalendereinträge zwei Stunden daneben.
 */
function fge_cc_today(): int {
	return current_datetime()->setTime( 0, 0 )->getTimestamp();
}

/** Ein Datum (Y-m-d) als Zeitstempel in der Zeitzone der Site. */
function fge_cc_local_ts( string $ymd ): int {
	$dt = date_create_immutable( $ymd . ' 00:00:00', wp_timezone() );
	return $dt ? $dt->getTimestamp() : 0;
}

/**
 * Eventdatum als Zeitstempel (lokale Mitternacht), 0 wenn keines feststeht.
 *
 * Das Datum steht im Angebots-Snapshot als Text („Mi, 30.09.2026") und ist
 * damit nicht abfragbar. Der berechnete Zeitstempel wird deshalb in
 * `_fge_event_ts` mitgeschrieben, sodass der Kalender nach Zeitraum suchen
 * kann, statt alle Vorgänge durchzugehen.
 */
function fge_cc_event_date( int $req ): int {
	$ts = 0;
	if ( function_exists( 'fge_day_event_date' ) ) {
		$d = fge_day_event_date( $req );
		if ( is_numeric( $d ) && (int) $d > 0 ) {
			$ts = (int) $d;
		} elseif ( is_string( $d ) && '' !== $d ) {
			$ts = fge_cc_local_ts( $d );
		}
	}
	if ( $ts <= 0 ) {
		$snap = (array) get_post_meta( $req, '_fge_offer_snapshot', true );
		$date = (string) ( $snap['date'] ?? '' );
		if ( '' !== $date ) {
			// Erst normalisieren, dann in der Zeitzone der Site verankern.
			$parsed = strtotime( $date );
			$ts     = $parsed > 0 ? fge_cc_local_ts( gmdate( 'Y-m-d', $parsed ) ) : 0;
		}
	}

	// Nur schreiben, wenn sich etwas geändert hat: sonst schreibt jede Anzeige.
	$stored = (int) get_post_meta( $req, '_fge_event_ts', true );
	if ( $ts !== $stored ) {
		if ( $ts > 0 ) {
			update_post_meta( $req, '_fge_event_ts', $ts );
		} else {
			delete_post_meta( $req, '_fge_event_ts' );
		}
	}
	return $ts;
}

/**
 * Einmalige Nachpflege des Datumsfeldes für Altbestand.
 *
 * Läuft als Cron in Blöcken, damit keine Seite darauf wartet, und meldet sich
 * ab, sobald alles durch ist.
 */
add_action( 'init', static function (): void {
	if ( '1' === (string) get_option( 'fge_cc_event_ts_done' ) || wp_next_scheduled( 'fge_cc_backfill_event_ts' ) ) {
		return;
	}
	wp_schedule_single_event( time() + 60, 'fge_cc_backfill_event_ts' );
}, 12 );

add_action( 'fge_cc_backfill_event_ts', 'fge_cc_backfill_event_ts' );
function fge_cc_backfill_event_ts(): void {
	$ids = get_posts( [
		'post_type'      => 'firmengolf_request',
		'post_status'    => [ 'publish', 'draft' ],
		'posts_per_page' => 300,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_query'     => [ [ 'key' => '_fge_event_ts_checked', 'compare' => 'NOT EXISTS' ] ],
	] );

	if ( ! $ids ) {
		update_option( 'fge_cc_event_ts_done', '1', false );
		return;
	}
	_prime_post_caches( $ids, false, true );
	foreach ( $ids as $id ) {
		fge_cc_event_date( (int) $id ); // schreibt _fge_event_ts mit
		update_post_meta( (int) $id, '_fge_event_ts_checked', 1 );
	}
	wp_schedule_single_event( time() + 60, 'fge_cc_backfill_event_ts' );
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
		$upcoming = ( $date <= 0 || $date >= fge_cc_today() )
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
			// Der Platz trägt seine Angaben selbst ein (Link aus der Auftragsbestätigung).
			// Kurz vor dem Termin wird das Warten zum Anruf.
			if ( $partner_id > 0 && function_exists( 'fge_venue_detail_row_for' ) ) {
				$vrow = fge_venue_detail_row_for( $req, $partner_id );
				if ( $vrow && '' === (string) ( $vrow['details_at'] ?? '' ) ) {
					if ( $near ) {
						$add( 'venue_details_call', 'Platzdetails fehlen: ' . get_the_title( $partner_id ) . ' anrufen', 'me', 'now' );
					} else {
						$add( 'venue_details_wait', 'Platz trägt Eventtag-Details ein', 'them', 'wait' );
					}
				}
			}
		}
		if ( $date > 0 && $date < fge_cc_today() && 'event_durchgefuehrt' !== $status
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
	foreach ( fge_cc_own_tasks( $req ) as $own ) {
		$add( 'own_' . $own['id'], (string) $own['text'], 'me', (string) ( $own['urgency'] ?? 'soon' ) );
	}

	// Dringendstes zuerst, damit Listen, die nur eine Zeile zeigen, die
	// richtige zeigen. Eigene Reihenfolge innerhalb einer Stufe bleibt erhalten.
	$rank = [ 'now' => 0, 'soon' => 1, 'wait' => 2 ];
	usort( $tasks, static fn( $a, $b ) => $rank[ $a['urgency'] ] <=> $rank[ $b['urgency'] ] );

	return $tasks;
}

/**
 * Eigene Aufgaben einer Anfrage.
 *
 * Alles andere wird abgeleitet, das hier ist der Platz für das, was nur Julius
 * weiß: „Pro anrufen", „Rechnungsadresse klären". Jede Zeile hat eine eigene
 * Kennung, damit das Abhaken nicht die falsche trifft, wenn dazwischen etwas
 * gelöscht wurde.
 */
function fge_cc_own_tasks( int $req ): array {
	$raw = get_post_meta( $req, '_fge_cc_tasks', true );
	if ( ! is_array( $raw ) ) {
		return [];
	}
	$out = [];
	foreach ( $raw as $i => $row ) {
		$text = is_array( $row ) ? (string) ( $row['text'] ?? '' ) : (string) $row;
		if ( '' === trim( $text ) ) {
			continue;
		}
		$out[] = [
			'id'      => (string) ( is_array( $row ) ? ( $row['id'] ?? $i ) : $i ),
			'text'    => trim( $text ),
			'urgency' => is_array( $row ) && 'now' === ( $row['urgency'] ?? '' ) ? 'now' : 'soon',
		];
	}
	return $out;
}

/** Eigene Aufgabe anlegen. */
function fge_cc_own_task_add( int $req, string $text, bool $urgent = false ): void {
	$text = trim( $text );
	if ( '' === $text ) {
		return;
	}
	$tasks   = fge_cc_own_tasks( $req );
	$tasks[] = [
		'id'      => uniqid( '', false ),
		'text'    => mb_substr( $text, 0, 200 ),
		'urgency' => $urgent ? 'now' : 'soon',
	];
	update_post_meta( $req, '_fge_cc_tasks', $tasks );
}

/** Eigene Aufgabe abhaken. */
function fge_cc_own_task_remove( int $req, string $id ): void {
	$tasks = array_values( array_filter(
		fge_cc_own_tasks( $req ),
		static fn( $t ) => (string) $t['id'] !== $id
	) );
	if ( $tasks ) {
		update_post_meta( $req, '_fge_cc_tasks', $tasks );
	} else {
		delete_post_meta( $req, '_fge_cc_tasks' );
	}
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
	// Nur offene Vorgänge: abgeschlossene und verlorene können nichts mehr
	// fordern, und damit bleibt die Arbeitsliste auch nach Jahren klein.
	$result   = fge_cc_query_requests( [ 'meta_query' => fge_cc_open_meta_query() ] );
	$requests = $result['ids'];
	$GLOBALS['fge_cc_worklist_truncated'] = (bool) $result['truncated'];

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
		$cold   = $age >= fge_cc_cold_days() && ( $date <= 0 || $date < fge_cc_today() );
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
