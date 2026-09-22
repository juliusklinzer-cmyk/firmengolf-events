<?php
/**
 * Control Center: Kalender, Outlook-Abo und Tagesmail.
 *
 * Termine bekommen bewusst keine eigene Tabelle, sie werden aus dem
 * vorhandenen Zustand abgeleitet: bestätigter Termin aus dem Angebots-
 * Snapshot, laufende Angebote als Option, Angebotsfristen und der Vortag.
 *
 * Outlook: ein geheimer Abo-Link (ICS). Read-only, kein OAuth, keine
 * Tokenpflege. Einschränkung, die man kennen muss: Outlook aktualisiert
 * abonnierte Kalender nur alle paar Stunden. Für Termine, die Wochen vorher
 * feststehen, ist das egal, für eine kurzfristige Verschiebung nicht. Deshalb
 * gibt es zusätzlich die Tagesmail um 07:00 Uhr.
 *
 * Aufgaben erscheinen im selben Abo als ganztägige Einträge mit Erinnerung,
 * weil Outlook VTODO praktisch nicht unterstützt.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Arten von Kalendereinträgen mit Anzeigefarbe. */
function fge_cc_cal_types(): array {
	return [
		'event'    => [ 'Event', 'good' ],
		'option'   => [ 'Option', 'warn' ],
		'deadline' => [ 'Frist', 'bad' ],
		'prep'     => [ 'Vortags-Info', 'info' ],
		'task'     => [ 'Aufgabe', 'neutral' ],
	];
}

// ── Ableitung ────────────────────────────────────────────────────────────────

/**
 * Alle Einträge in einem Zeitraum.
 *
 * @param int  $from      Zeitstempel ab.
 * @param int  $to        Zeitstempel bis.
 * @param bool $with_tasks Aufgaben mitliefern (für Abo und Tagesmail).
 */
function fge_cc_calendar_entries( int $from, int $to, bool $with_tasks = false ): array {
	$ids = get_posts( [
		'post_type'      => 'firmengolf_request',
		'post_status'    => [ 'publish', 'draft' ],
		'posts_per_page' => 400,
		'fields'         => 'ids',
		'orderby'        => 'date',
		'order'          => 'DESC',
	] );
	_prime_post_caches( $ids, false, true );

	$out = [];
	foreach ( $ids as $req ) {
		if ( function_exists( 'fge_is_demo_request' ) && fge_is_demo_request( $req ) ) {
			continue;
		}
		$status = (string) get_post_meta( $req, '_fge_request_status', true );
		if ( in_array( $status, [ 'verloren', 'angebot_abgelehnt', 'nicht_verfuegbar' ], true ) ) {
			continue;
		}

		$offer   = (string) get_post_meta( $req, '_fge_offer_status', true );
		$date    = fge_cc_event_date( $req );
		$company = (string) get_post_meta( $req, '_fge_company_name', true ) ?: 'Ohne Firma';
		$ref     = fge_request_number( $req );
		$pid     = (int) get_post_meta( $req, '_fge_assigned_partner_id', true );
		$place   = $pid > 0 ? (string) get_the_title( $pid ) : '';

		if ( $date > 0 ) {
			$start = fge_cc_event_start( $req, $date );
			$type  = 'accepted' === $offer ? 'event' : 'option';
			if ( $start >= $from && $start <= $to ) {
				$out[] = [
					'ts'       => $start,
					'end'      => $start + fge_cc_event_length( $req ),
					'allday'   => false,
					'type'     => $type,
					'req'      => $req,
					'title'    => ( 'option' === $type ? 'Option: ' : '' ) . $company,
					'place'    => $place,
					'ref'      => $ref,
					'partner'  => $pid,
				];
			}
			// Vortag: der Tag, an dem die Info rausgeht.
			$prep = strtotime( '-1 day', $date ) + 7 * HOUR_IN_SECONDS;
			if ( 'accepted' === $offer && $prep >= $from && $prep <= $to ) {
				$out[] = [
					'ts'      => $prep,
					'end'     => $prep + HOUR_IN_SECONDS,
					'allday'  => false,
					'type'    => 'prep',
					'req'     => $req,
					'title'   => 'Vortags-Info ' . $company,
					'place'   => $place,
					'ref'     => $ref,
					'partner' => $pid,
				];
			}
		}

		// Angebotsfrist.
		$deadline = (int) get_post_meta( $req, '_fge_offer_deadline', true );
		if ( 'pending' === $offer && $deadline > 0 && $deadline >= $from && $deadline <= $to ) {
			$out[] = [
				'ts'      => $deadline,
				'end'     => $deadline,
				'allday'  => true,
				'type'    => 'deadline',
				'req'     => $req,
				'title'   => 'Angebotsfrist ' . $company,
				'place'   => $place,
				'ref'     => $ref,
				'partner' => $pid,
			];
		}
	}

	if ( $with_tasks ) {
		$today = ( fge_cc_today() + 8 * HOUR_IN_SECONDS );
		if ( $today >= $from && $today <= $to ) {
			foreach ( fge_cc_worklist() as $row ) {
				if ( ! $row['mine'] || $row['snoozed'] || $row['cold'] ) {
					continue;
				}
				$mine = array_values( array_filter( $row['tasks'], static fn( $t ) => 'me' === $t['who'] && 'now' === $t['urgency'] ) );
				if ( ! $mine ) {
					continue;
				}
				$out[] = [
					'ts'      => $today,
					'end'     => $today,
					'allday'  => true,
					'type'    => 'task',
					'req'     => (int) $row['req'],
					'title'   => $row['ref'] . ': ' . $mine[0]['text'],
					'place'   => '',
					'ref'     => $row['ref'],
					'partner' => 0,
				];
			}
		}
	}

	usort( $out, static fn( $a, $b ) => $a['ts'] <=> $b['ts'] );
	return $out;
}

/** Startzeitpunkt eines Events: Datum plus Startzeit aus Schritt 4, sonst 10 Uhr. */
function fge_cc_event_start( int $req, int $date ): int {
	$raw = (string) get_post_meta( $req, '_fge_day_start_time', true );
	if ( '' === $raw ) {
		$snap = (array) get_post_meta( $req, '_fge_offer_snapshot', true );
		$raw  = (string) ( $snap['schedule'] ?? '' );
	}
	if ( preg_match( '/\b(\d{1,2})[:.](\d{2})/', $raw, $m ) ) {
		return $date + (int) $m[1] * HOUR_IN_SECONDS + (int) $m[2] * MINUTE_IN_SECONDS;
	}
	return $date + 10 * HOUR_IN_SECONDS;
}

/** Dauer eines Events, aus dem Ablauf gelesen, sonst vier Stunden. */
function fge_cc_event_length( int $req ): int {
	$snap = (array) get_post_meta( $req, '_fge_offer_snapshot', true );
	$text = (string) ( $snap['schedule'] ?? '' ) . ' ' . (string) ( $snap['event_title'] ?? '' );
	if ( preg_match( '/\b(\d{1,2})\s*(?:bis|-|–)\s*(\d{1,2})\s*Stunden?\b/u', $text, $m ) ) {
		return max( 1, (int) $m[2] ) * HOUR_IN_SECONDS;
	}
	if ( preg_match( '/\b(\d{1,2})\s*Stunden?\b/u', $text, $m ) ) {
		return max( 1, (int) $m[1] ) * HOUR_IN_SECONDS;
	}
	return 4 * HOUR_IN_SECONDS;
}

/**
 * Zwei Events am selben Tag am selben Platz, oder zwei Events am selben Tag
 * überhaupt. Beides will man sehen, bevor man einen Termin zusagt.
 */
function fge_cc_calendar_clashes( array $entries ): array {
	$byday = [];
	foreach ( $entries as $e ) {
		if ( 'event' !== $e['type'] ) {
			continue;
		}
		$byday[ wp_date( 'Y-m-d', $e['ts'] ) ][] = $e;
	}
	$out = [];
	foreach ( $byday as $day => $list ) {
		if ( count( $list ) < 2 ) {
			continue;
		}
		$places = array_filter( array_map( static fn( $e ) => (int) $e['partner'], $list ) );
		$out[ $day ] = [
			'count'     => count( $list ),
			'same_place' => count( $places ) !== count( array_unique( $places ) ),
		];
	}
	return $out;
}

// ── Seite ────────────────────────────────────────────────────────────────────

function fge_cc_page_calendar(): void {
	$month = sanitize_text_field( wp_unslash( $_GET['monat'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	// Monatsgrenzen in der Zeitzone der Site, sonst beginnt der Monat zwei
	// Stunden zu spät und der erste Termin fällt aus dem Raster.
	if ( ! preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
		$month = wp_date( 'Y-m' );
	}
	$first = fge_cc_local_ts( $month . '-01' );
	$last  = (int) strtotime( '+1 month', $first ) - 1;

	$entries = fge_cc_calendar_entries( $first, $last );
	$clashes = fge_cc_calendar_clashes( $entries );

	$byday = [];
	foreach ( $entries as $e ) {
		$byday[ wp_date( 'Y-m-d', $e['ts'] ) ][] = $e;
	}

	// Kopf mit Monatswechsel
	$prev = wp_date( 'Y-m', (int) strtotime( '-1 month', $first ) );
	$next = wp_date( 'Y-m', (int) strtotime( '+1 month', $first ) );
	echo '<div class="cc-cal-top">';
	echo '<a class="cc-btn" href="' . esc_url( fge_cc_url( 'kalender', [ 'monat' => $prev ] ) ) . '">Zurück</a>';
	echo '<h2>' . esc_html( wp_date( 'F Y', $first ) ) . '</h2>';
	echo '<a class="cc-btn" href="' . esc_url( fge_cc_url( 'kalender', [ 'monat' => $next ] ) ) . '">Weiter</a>';
	echo '</div>';

	if ( $clashes ) {
		foreach ( $clashes as $day => $c ) {
			echo '<p class="cc-msg cc-msg--err">' . esc_html( wp_date( 'd.m.Y', (int) strtotime( $day ) ) ) . ': '
				. (int) $c['count'] . ' Events am selben Tag'
				. ( $c['same_place'] ? ', davon mindestens zwei am selben Platz' : '' ) . '.</p>';
		}
	}

	// Monatsraster
	$start_weekday = (int) wp_date( 'N', $first );
	$days_in_month = (int) wp_date( 't', $first );
	$today         = wp_date( 'Y-m-d' );

	echo '<div class="cc-cal">';
	foreach ( [ 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So' ] as $wd ) {
		echo '<div class="cc-cal-wd">' . esc_html( $wd ) . '</div>';
	}
	for ( $i = 1; $i < $start_weekday; $i++ ) {
		echo '<div class="cc-cal-cell is-empty"></div>';
	}
	for ( $d = 1; $d <= $days_in_month; $d++ ) {
		$key  = sprintf( '%s-%02d', wp_date( 'Y-m', $first ), $d );
		$list = $byday[ $key ] ?? [];
		echo '<div class="cc-cal-cell' . ( $key === $today ? ' is-today' : '' ) . ( isset( $clashes[ $key ] ) ? ' is-clash' : '' ) . '">';
		echo '<span class="cc-cal-n">' . (int) $d . '</span>';
		foreach ( $list as $e ) {
			[ , $tone ] = fge_cc_cal_types()[ $e['type'] ];
			echo '<a class="cc-cal-item cc-cal-item--' . esc_attr( $tone ) . '" href="' . esc_url( fge_cc_request_url( (int) $e['req'] ) ) . '" title="' . esc_attr( $e['title'] ) . '">';
			if ( ! $e['allday'] ) {
				echo '<span class="cc-cal-time">' . esc_html( wp_date( 'H:i', $e['ts'] ) ) . '</span> ';
			}
			echo esc_html( mb_substr( $e['title'], 0, 28 ) );
			echo '</a>';
		}
		echo '</div>';
	}
	echo '</div>';

	// Liste unter dem Raster, damit man am Telefon nicht in Zellen tippen muss.
	echo '<section class="cc-card"><h2>Im ' . esc_html( wp_date( 'F', $first ) ) . '</h2>';
	if ( ! $entries ) {
		fge_cc_empty( 'In diesem Monat steht nichts an.' );
	} else {
		echo '<ul class="cc-cal-list">';
		foreach ( $entries as $e ) {
			[ $label, $tone ] = fge_cc_cal_types()[ $e['type'] ];
			echo '<li><a href="' . esc_url( fge_cc_request_url( (int) $e['req'] ) ) . '">';
			// Ganztägiges hat keine sinnvolle Uhrzeit, eine Frist schon gar nicht.
			echo '<span class="cc-cal-date">' . esc_html( wp_date( $e['allday'] ? 'D, d.m.' : 'D, d.m., H:i', $e['ts'] ) ) . '</span>';
			echo fge_cc_pill( $label, $tone ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<span>' . esc_html( $e['title'] ) . '</span>';
			if ( '' !== $e['place'] ) {
				echo '<span class="cc-muted">' . esc_html( $e['place'] ) . '</span>';
			}
			echo '</a></li>';
		}
		echo '</ul>';
	}
	echo '</section>';

	fge_cc_ics_panel();
}

/** Der Abo-Link für Outlook, mit der ehrlichen Einschränkung dazu. */
function fge_cc_ics_panel(): void {
	echo '<section class="cc-card"><h2>In Outlook abonnieren</h2>';
	echo '<p class="cc-muted">Diesen Link einmal in Outlook als Internetkalender hinzufügen. Danach erscheinen Events, Optionen, Fristen und fällige Aufgaben von selbst.</p>';
	echo '<p><input class="cc-copyfield" type="text" readonly value="' . esc_attr( fge_cc_ics_url() ) . '" onclick="this.select()"></p>';
	echo '<p class="cc-muted">Outlook aktualisiert abonnierte Kalender nur alle paar Stunden. Für Termine, die Wochen vorher feststehen, ist das egal. Bei kurzfristigen Änderungen gilt die Tagesmail um 07:00 Uhr.</p>';
	echo '<p class="cc-muted">Der Link enthält einen geheimen Schlüssel. Wer ihn hat, sieht die Termine. Nicht weitergeben.</p>';
	echo '</section>';
}

// ── ICS-Abo ──────────────────────────────────────────────────────────────────

/** Geheimer Schlüssel des Abos, beim ersten Aufruf erzeugt. */
function fge_cc_ics_key(): string {
	$key = (string) get_option( 'fge_cc_ics_key', '' );
	if ( '' === $key ) {
		$key = bin2hex( random_bytes( 16 ) );
		update_option( 'fge_cc_ics_key', $key, false );
	}
	return $key;
}

function fge_cc_ics_url(): string {
	return add_query_arg( 'key', fge_cc_ics_key(), home_url( '/control/kalender.ics' ) );
}

add_action( 'init', static function (): void {
	add_rewrite_rule( '^control/kalender\.ics$', 'index.php?fge_cc_ics=1', 'top' );
}, 11 );

add_filter( 'query_vars', static function ( array $vars ): array {
	$vars[] = 'fge_cc_ics';
	return $vars;
} );

add_action( 'template_redirect', static function (): void {
	if ( '1' !== (string) get_query_var( 'fge_cc_ics' ) ) {
		return;
	}
	// Der Schlüssel ist die einzige Hürde: Outlook kann sich nicht anmelden.
	$given = (string) ( $_GET['key'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! hash_equals( fge_cc_ics_key(), $given ) ) {
		status_header( 404 );
		nocache_headers();
		exit;
	}
	fge_cc_ics_output();
	exit;
}, 1 );

/** Text für ICS maskieren. */
function fge_cc_ics_escape( string $s ): string {
	$s = wp_strip_all_tags( $s );
	$s = str_replace( [ '\\', ';', ',', "\r\n", "\n" ], [ '\\\\', '\\;', '\\,', '\\n', '\\n' ], $s );
	return $s;
}

/** Eine Zeile nach RFC 5545 auf 75 Oktette falten. */
function fge_cc_ics_fold( string $line ): string {
	$out = '';
	while ( strlen( $line ) > 73 ) {
		$out  .= substr( $line, 0, 73 ) . "\r\n ";
		$line  = substr( $line, 73 );
	}
	return $out . $line . "\r\n";
}

function fge_cc_ics_output(): void {
	$from    = (int) strtotime( '-3 months' );
	$to      = (int) strtotime( '+12 months' );
	$entries = fge_cc_calendar_entries( $from, $to, true );

	$host  = wp_parse_url( home_url(), PHP_URL_HOST ) ?: 'firmengolf';
	$stamp = gmdate( 'Ymd\THis\Z' );

	$ics  = "BEGIN:VCALENDAR\r\n";
	$ics .= "VERSION:2.0\r\n";
	$ics .= "PRODID:-//Firmengolf//Control Center//DE\r\n";
	$ics .= "CALSCALE:GREGORIAN\r\n";
	$ics .= "METHOD:PUBLISH\r\n";
	$ics .= fge_cc_ics_fold( 'X-WR-CALNAME:Firmengolf' );
	$ics .= "X-PUBLISHED-TTL:PT2H\r\n";

	foreach ( $entries as $i => $e ) {
		[ $label ] = fge_cc_cal_types()[ $e['type'] ];
		$uid = sprintf( 'fge-%d-%s-%d@%s', (int) $e['req'], $e['type'], $i, $host );

		$ics .= "BEGIN:VEVENT\r\n";
		$ics .= fge_cc_ics_fold( 'UID:' . $uid );
		$ics .= "DTSTAMP:{$stamp}\r\n";

		if ( $e['allday'] ) {
			$ics .= 'DTSTART;VALUE=DATE:' . gmdate( 'Ymd', $e['ts'] ) . "\r\n";
			$ics .= 'DTEND;VALUE=DATE:' . gmdate( 'Ymd', $e['ts'] + DAY_IN_SECONDS ) . "\r\n";
		} else {
			$ics .= 'DTSTART:' . gmdate( 'Ymd\THis\Z', $e['ts'] ) . "\r\n";
			$ics .= 'DTEND:' . gmdate( 'Ymd\THis\Z', max( $e['end'], $e['ts'] + 1800 ) ) . "\r\n";
		}

		$ics .= fge_cc_ics_fold( 'SUMMARY:' . fge_cc_ics_escape( ( 'event' === $e['type'] ? '' : $label . ': ' ) . $e['title'] ) );
		if ( '' !== $e['place'] ) {
			$ics .= fge_cc_ics_fold( 'LOCATION:' . fge_cc_ics_escape( $e['place'] ) );
		}
		$ics .= fge_cc_ics_fold( 'DESCRIPTION:' . fge_cc_ics_escape( $e['ref'] . "\n" . fge_cc_request_url( (int) $e['req'] ) ) );
		$ics .= fge_cc_ics_fold( 'URL:' . fge_cc_request_url( (int) $e['req'] ) );
		$ics .= 'TRANSP:' . ( 'event' === $e['type'] ? 'OPAQUE' : 'TRANSPARENT' ) . "\r\n";

		// Aufgaben und Fristen bekommen eine Erinnerung, weil Outlook VTODO
		// praktisch nicht unterstützt.
		if ( in_array( $e['type'], [ 'task', 'deadline', 'prep' ], true ) ) {
			// Ganztägige Einträge beginnen um Mitternacht. Eine Erinnerung
			// „15 Minuten vorher" käme dann um 23:45 am Vorabend, deshalb
			// werden sie auf 8 Uhr des Tages selbst gelegt.
			$trigger = $e['allday'] ? 'PT8H' : '-PT15M';
			$ics    .= "BEGIN:VALARM\r\nACTION:DISPLAY\r\nTRIGGER:{$trigger}\r\n";
			$ics .= fge_cc_ics_fold( 'DESCRIPTION:' . fge_cc_ics_escape( $e['title'] ) );
			$ics .= "END:VALARM\r\n";
		}
		$ics .= "END:VEVENT\r\n";
	}
	$ics .= "END:VCALENDAR\r\n";

	nocache_headers();
	header( 'Content-Type: text/calendar; charset=utf-8' );
	header( 'Content-Disposition: inline; filename="firmengolf.ics"' );
	echo $ics; // phpcs:ignore WordPress.Security.EscapeOutput
}

// ── Tagesmail ────────────────────────────────────────────────────────────────

add_action( 'init', static function (): void {
	if ( ! wp_next_scheduled( 'fge_cc_daily_digest' ) ) {
		// Gleiches Zeitfenster wie die Vortags-Info, und wie dort über die
		// Zeitzone der Site: strtotime() rechnet in der Serverzeit und legte
		// die Mail sonst auf 09:00 statt 07:00.
		$first = new DateTime( 'tomorrow 07:00', wp_timezone() );
		wp_schedule_event( $first->getTimestamp(), 'daily', 'fge_cc_daily_digest' );
	}
}, 12 );

add_action( 'fge_cc_daily_digest', 'fge_cc_send_daily_digest' );

/**
 * „Das steht heute an": fällige Aufgaben und Termine des Tages.
 *
 * Ersetzt das tägliche Öffnen des Control Centers und fängt ab, dass Outlook
 * abonnierte Kalender nur träge aktualisiert.
 */
function fge_cc_send_daily_digest(): bool {
	$today = fge_cc_today();
	$end   = ( fge_cc_today() + 2 * DAY_IN_SECONDS - 1 );

	$entries = array_values( array_filter(
		fge_cc_calendar_entries( $today, $end ),
		static fn( $e ) => 'task' !== $e['type']
	) );

	$mine = [];
	foreach ( fge_cc_worklist() as $row ) {
		if ( ! $row['mine'] || $row['snoozed'] || $row['cold'] ) {
			continue;
		}
		foreach ( $row['tasks'] as $t ) {
			if ( 'me' === $t['who'] && 'now' === $t['urgency'] ) {
				$mine[] = [ 'ref' => $row['ref'], 'req' => (int) $row['req'], 'text' => $t['text'], 'company' => $row['company'] ];
			}
		}
	}

	if ( ! $entries && ! $mine ) {
		return false; // Nichts zu melden, dann auch keine Mail.
	}

	$rows = '';
	foreach ( $mine as $m ) {
		$rows .= '<tr><td style="padding:5px 16px 5px 0;color:#555;white-space:nowrap;vertical-align:top;">'
			. '<a href="' . esc_url( fge_cc_request_url( $m['req'] ) ) . '" style="color:#4279D1;">' . esc_html( $m['ref'] ) . '</a></td>'
			. '<td style="padding:5px 0;color:#1a1a1a;">' . esc_html( $m['text'] )
			. ( '' !== $m['company'] ? ' <span style="color:#6C736E;">' . esc_html( $m['company'] ) . '</span>' : '' )
			. '</td></tr>';
	}

	$cal = '';
	foreach ( $entries as $e ) {
		[ $label ] = fge_cc_cal_types()[ $e['type'] ];
		$cal .= '<li style="margin-bottom:4px;"><strong>' . esc_html( wp_date( $e['allday'] ? 'D, d.m.' : 'D, d.m., H:i', $e['ts'] ) ) . '</strong> '
			. esc_html( $label . ': ' . $e['title'] )
			. ( '' !== $e['place'] ? ' <span style="color:#6C736E;">' . esc_html( $e['place'] ) . '</span>' : '' )
			. '</li>';
	}

	$subject = 'Heute bei Firmengolf: ' . count( $mine ) . ' ' . ( 1 === count( $mine ) ? 'Aufgabe' : 'Aufgaben' )
		. ( $entries ? ', ' . count( $entries ) . ' ' . ( 1 === count( $entries ) ? 'Termin' : 'Termine' ) : '' );

	$content = '
		<p style="margin:0 0 16px;">Guten Morgen,</p>
		' . ( '' !== $rows
			? '<p style="margin:0 0 6px;font-weight:600;">Das wartet auf dich</p><table style="width:100%;border-collapse:collapse;font-size:14px;line-height:1.5;margin:0 0 16px;">' . $rows . '</table>'
			: '<p style="margin:0 0 16px;">Nichts Dringendes offen.</p>' ) . '
		' . ( '' !== $cal ? '<p style="margin:0 0 6px;font-weight:600;">Heute und morgen im Kalender</p><ul style="margin:0 0 16px;padding-left:20px;">' . $cal . '</ul>' : '' ) . '
		<p style="margin:0;">' . fge_email_button( fge_cc_url( 'dashboard' ), 'Control Center öffnen' ) . '</p>
	';

	$to = apply_filters( 'fge_internal_email', fge_company_internal_email() );
	fge_mail_log_context( 0, 'daily_digest' );
	$ok = (bool) wp_mail( $to, $subject, fge_email_wrap( $subject, $content ), [ 'Content-Type: text/html; charset=UTF-8' ] );
	fge_mail_log_context_clear();
	return $ok;
}
