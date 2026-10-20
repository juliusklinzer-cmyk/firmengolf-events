<?php
/**
 * Control Center: „Heute“ nach „Control Center Redesign“ (Julius, 16.10.2026).
 *
 * Fünf Listen mit allem, was auf Julius wartet: Anfragen, Freigaben,
 * überfällige Zahlungen, neue Leads, Mails mit Fehler. Ein Klick auf eine
 * Zeile zeigt rechts Eckdaten und die passenden Knöpfe, ohne Auswahl stehen
 * dort die nächsten Events und vier Kennzahlen. Nur vorhandene Daten.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Ein Knopf im Detailfeld: Link oder fertiges Formular-HTML. */
function fge_cc_today_link( string $label, string $url, bool $primary = false ): string {
	return '<a class="cc-btn' . ( $primary ? ' cc-btn--primary' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
}

/** Ausgabe einer Funktion als HTML, für Formular-Knöpfe im Detailfeld. */
function fge_cc_today_capture( callable $fn ): string {
	ob_start();
	$fn();
	return (string) ob_get_clean();
}

/**
 * Die fünf Gruppen mit ihren Zeilen. Jede Zeile: key, tag [Text, Ton], title,
 * sub, url, facts [[Label, Wert]], actions (Callbacks, die HTML ausgeben).
 */
function fge_cc_today_groups(): array {
	static $groups = null;
	if ( null !== $groups ) {
		return $groups;
	}

	// Anfragen: alles, was auf mich wartet und nicht zurückgestellt ist.
	$req_rows = [];
	foreach ( fge_cc_worklist() as $r ) {
		if ( ! $r['mine'] || $r['snoozed'] ) {
			continue;
		}
		$req   = (int) $r['req'];
		if ( 'rechnung_in_lexoffice_erstellt' === (string) get_post_meta( $req, '_fge_request_status', true ) ) {
			continue; // gestellte Rechnungen stehen unter Zahlungen, sobald sie überfällig sind
		}
		$flow  = fge_cc_flow( $req );
		$f     = fge_cc_request_fact_data( $req );
		$tasks = array_values( array_filter( $r['tasks'], static fn( $t ) => 'me' === $t['who'] ) );
		$next  = (string) ( $tasks[0]['text'] ?? '' );
		$event = (string) ( $f['event'] ?? '' );
		$req_rows[] = [
			'key'     => 'r' . $req,
			'tag'     => [ $flow['label'], $flow['tone'] ],
			'title'   => implode( ' · ', array_filter( [ $r['company'] ?: 'Ohne Firma', $event ] ) ),
			'sub'     => implode( ' · ', array_filter( [ $r['ref'], $next ] ) ),
			'url'     => fge_cc_request_url( $req ),
			'facts'   => [
				[ 'Anfrage', $r['ref'] ],
				[ 'Firma', $r['company'] ],
				[ 'Event', $event ],
				[ 'Termin', $r['date'] > 0 ? wp_date( 'D, d.m.Y', (int) $r['date'] ) : 'offen' ],
				[ 'Nächster Schritt', $next ],
			],
			'actions' => [ static fn() => print( fge_cc_today_link( 'Anfrage öffnen', fge_cc_request_url( $req ), true ) ) ], // phpcs:ignore WordPress.Security.EscapeOutput
			'rank'    => [ 'now' => 0, 'soon' => 1, 'wait' => 2 ][ $r['urgency'] ] ?? 2,
		];
	}
	usort( $req_rows, static fn( $a, $b ) => $a['rank'] <=> $b['rank'] );

	// Freigaben: neue Partner und Events, ohne die, bei denen der Partner dran ist.
	$rev_rows = [];
	$kinds    = function_exists( 'fge_cc_partner_kinds' ) ? fge_cc_partner_kinds() : [];
	$partners = get_posts( [ 'post_type' => 'firmengolf_partner', 'post_status' => [ 'publish', 'draft', 'pending', 'private' ], 'numberposts' => 100, 'fields' => 'ids', 'meta_key' => '_fge_partner_status', 'meta_value' => 'in_pruefung', 'orderby' => 'modified', 'order' => 'ASC' ] ); // phpcs:ignore WordPress.DB.SlowDBQuery
	foreach ( $partners as $pid ) {
		$pid = (int) $pid;
		if ( '' !== (string) get_post_meta( $pid, '_fge_review_message', true ) ) {
			continue;
		}
		$kind = $kinds[ function_exists( 'fge_partner_type' ) ? fge_partner_type( $pid ) : 'course' ] ?? 'Partner';
		$city = (string) get_post_meta( $pid, '_fge_city', true );
		$subm = (int) strtotime( (string) get_post_meta( $pid, '_fge_submitted_at', true ) );
		$sub  = implode( ' · ', array_filter( [ $kind, $city, $subm > 0 ? 'eingereicht ' . wp_date( 'd.m.', $subm ) : '' ] ) );
		$url  = fge_cc_url( 'partner', [ 'pid' => $pid, 'pruefen' => 1 ] );
		$rev_rows[] = [
			'key'     => 'p' . $pid,
			'tag'     => [ 'Neuer Partner', 'brand' ],
			'title'   => fge_cc_partner_name( $pid ),
			'sub'     => $sub,
			'url'     => $url,
			'facts'   => [ [ 'Art', 'Neuer Partner' ], [ 'Details', $sub ] ],
			'actions' => [ static fn() => print( fge_cc_today_link( 'In Webansicht prüfen', $url, true ) ) ], // phpcs:ignore WordPress.Security.EscapeOutput
		];
	}
	$events = get_posts( [ 'post_type' => 'firmengolf_event', 'post_status' => [ 'publish', 'draft', 'pending', 'private' ], 'numberposts' => 100, 'fields' => 'ids', 'orderby' => 'modified', 'order' => 'ASC', 'meta_query' => [ [ 'key' => '_fge_event_status', 'value' => [ 'zur_pruefung', 'aenderung_in_pruefung' ], 'compare' => 'IN' ] ] ] ); // phpcs:ignore WordPress.DB.SlowDBQuery
	foreach ( $events as $eid ) {
		$eid = (int) $eid;
		if ( '' !== (string) get_post_meta( $eid, '_fge_review_message', true ) || ( function_exists( 'fge_pa_pending' ) && fge_pa_pending( $eid ) ) ) {
			continue; // der Partner ist dran
		}
		$change = 'aenderung_in_pruefung' === (string) get_post_meta( $eid, '_fge_event_status', true );
		$pid    = (int) get_post_meta( $eid, '_fge_assigned_partner_id', true );
		$sub    = implode( ' · ', array_filter( [ $pid > 0 ? fge_cc_partner_name( $pid ) : 'Firmengolf', function_exists( 'fge_cc_event_sub' ) ? fge_cc_event_sub( $eid ) : '' ] ) );
		$url    = function_exists( 'fge_cc_event_review_url' ) ? fge_cc_event_review_url( $eid ) : fge_cc_url( 'events', [ 'ev' => $eid ] );
		$rev_rows[] = [
			'key'     => 'e' . $eid,
			'tag'     => $change ? [ 'Änderung', 'neutral' ] : [ 'Neues Event', 'warn' ],
			'title'   => get_the_title( $eid ),
			'sub'     => $sub,
			'url'     => $url,
			'facts'   => [ [ 'Art', $change ? 'Änderung an einem Event' : 'Neues Event' ], [ 'Details', $sub ] ],
			'actions' => [ static fn() => print( fge_cc_today_link( 'Prüfen', $url, true ) ) ], // phpcs:ignore WordPress.Security.EscapeOutput
		];
	}

	// Partner-Freigabe nach drei Werktagen ohne Antwort: nachhaken (Julius, 20.10.2026).
	foreach ( function_exists( 'fge_pa_pending_events' ) ? fge_pa_pending_events() : [] as $eid ) {
		if ( ! fge_pa_overdue( $eid ) ) {
			continue;
		}
		$pid   = (int) get_post_meta( $eid, '_fge_assigned_partner_id', true );
		$phone = (string) get_post_meta( $pid, '_fge_main_contact_phone', true );
		$sent  = (int) get_post_meta( $eid, '_fge_pa_sent_at', true );
		$url   = fge_cc_event_edit_url( $eid );
		$rev_rows[] = [
			'key'     => 'pa' . $eid,
			'tag'     => [ 'Partner antwortet nicht', 'bad' ],
			'title'   => get_the_title( $eid ),
			'sub'     => implode( ' · ', array_filter( [ fge_cc_partner_name( $pid ), 'um Freigabe gebeten am ' . wp_date( 'd.m.', $sent ), '' !== (string) get_post_meta( $eid, '_fge_pa_reminded_at', true ) ? 'Erinnerung raus' : '' ] ) ),
			'url'     => $url,
			'facts'   => [ [ 'Art', 'Freigabe durch den Partner offen' ], [ 'Gefragt am', wp_date( 'd.m.Y', $sent ) ], [ 'Telefon', '' !== $phone ? $phone : 'nicht hinterlegt' ] ],
			'actions' => [ static fn() => print( fge_cc_today_link( 'Event öffnen', $url, true ) ) ], // phpcs:ignore WordPress.Security.EscapeOutput
		];
	}

	// Zahlungen: überfällige Rechnungen.
	$pay_rows = [];
	foreach ( function_exists( 'fge_cc_invoices_overdue' ) ? fge_cc_invoices_overdue() : [] as $i ) {
		$req = (int) $i['req'];
		$pay_rows[] = [
			'key'     => 'i' . $req,
			'tag'     => [ $i['days'] . ( 1 === $i['days'] ? ' Tag überfällig' : ' Tage überfällig' ), 'bad' ],
			'title'   => $i['company'] . ' · ' . fge_cc_eur( $i['gross'] ),
			'sub'     => implode( ' · ', array_filter( [ $i['no'], 'fällig am ' . wp_date( 'd.m.Y', $i['due'] ) ] ) ),
			'url'     => fge_cc_url( 'finanzen', [ 'tab' => 'ueber' ] ),
			'facts'   => [
				[ 'Rechnung', $i['no'] ?: 'ohne Nr.' ],
				[ 'Kunde', $i['company'] ],
				[ 'Anfrage', $i['ref'] ],
				[ 'Betrag brutto', fge_cc_eur( $i['gross'] ) ],
				[ 'Rechnungsdatum', $i['date'] > 0 ? wp_date( 'd.m.Y', $i['date'] ) : '' ],
				[ 'Fällig am', wp_date( 'd.m.Y', $i['due'] ) ],
				[ 'Erinnert', $i['reminded'] > 0 ? wp_date( 'd.m.Y', $i['reminded'] ) : 'noch nicht' ],
			],
			'actions' => [
				static fn() => fge_cc_remind_button( $req, 'heute', '', 'Zahlungserinnerung senden', 'cc-btn cc-btn--primary' ),
				static fn() => fge_cc_paid_button( $req, 'heute', '', 'Als bezahlt markieren', false ),
			],
		];
	}

	// Neue Leads aus dem Budget-Rechner.
	$lead_rows = [];
	foreach ( function_exists( 'fge_cc_lead_rows' ) ? fge_cc_lead_rows() : [] as $l ) {
		if ( 'neu' !== $l['status'] ) {
			continue;
		}
		$id = (int) $l['id'];
		$lead_rows[] = [
			'key'     => 'l' . $id,
			'tag'     => [ $l['source'], 'neutral' ],
			'title'   => implode( ' · ', array_filter( [ $l['company'], $l['type'] ] ) ),
			'sub'     => implode( ' · ', array_filter( [ $l['name'], $l['pax'] > 0 ? $l['pax'] . ' Personen' : '', '' !== $l['budget'] ? 'Budget ' . $l['budget'] : '' ] ) ),
			'url'     => fge_cc_url( 'kunden', [ 'ansicht' => 'leads' ] ),
			'facts'   => [
				[ 'Ansprechpartner', $l['name'] ],
				[ 'E-Mail', $l['mail'] ],
				[ 'Quelle', $l['source'] ],
				[ 'Event', $l['type'] ],
				[ 'Personen', $l['pax'] > 0 ? (string) $l['pax'] : '' ],
				[ 'Budget', $l['budget'] ],
				[ 'Eingang', $l['date'] ],
			],
			'actions' => [
				static fn() => fge_cc_button( 'fge_cc_lead', $id, 'In Anfrage umwandeln', [ 'class' => 'cc-btn cc-btn--primary', 'fields' => [ 'to' => 'umgewandelt', 'back' => 'heute' ] ] ),
				static fn() => fge_cc_button( 'fge_cc_lead', $id, 'Als kontaktiert markieren', [ 'fields' => [ 'to' => 'kontaktiert', 'back' => 'heute' ] ] ),
			],
		];
	}

	// Mails, die nicht rausgingen, letzte 14 Tage.
	$mail_rows = [];
	$since     = time() - 14 * DAY_IN_SECONDS;
	foreach ( function_exists( 'fge_mail_log_recent' ) ? fge_mail_log_recent( 30, 'failed' ) : [] as $m ) {
		if ( (int) strtotime( (string) $m['sent_at'] ) < $since ) {
			continue;
		}
		$req = (int) $m['request_id'];
		$ref = $req > 0 ? fge_request_number( $req ) : '';
		$mail_rows[] = [
			'key'     => 'm' . (int) $m['id'],
			'tag'     => [ 'Fehlgeschlagen', 'bad' ],
			'title'   => (string) $m['subject'],
			'sub'     => 'An ' . $m['recipient'] . ( '' !== (string) $m['error'] ? ' · ' . mb_substr( (string) $m['error'], 0, 80 ) : '' ),
			'url'     => $req > 0 ? fge_cc_request_url( $req ) : fge_cc_url( 'einstellungen', [ 'bereich' => 'postausgang' ] ),
			'facts'   => [
				[ 'Betreff', (string) $m['subject'] ],
				[ 'An', (string) $m['recipient'] ],
				[ 'Bezug', $ref ],
				[ 'Versucht', wp_date( 'd.m. H:i', (int) strtotime( (string) $m['sent_at'] ) ) ],
				[ 'Fehler', mb_substr( (string) $m['error'], 0, 200 ) ],
			],
			'actions' => array_filter( [
				static fn() => fge_mail_log_retry_button( $m, 'heute' ),
				$req > 0 ? static fn() => print( fge_cc_today_link( 'Anfrage öffnen', fge_cc_request_url( $req ) ) ) : null, // phpcs:ignore WordPress.Security.EscapeOutput
			] ),
		];
	}

	$groups = [
		[ 'Anfragen', $req_rows, fge_cc_url( 'anfragen' ), 'Alle Anfragen', 'Bei den Anfragen wartet nichts auf dich.' ],
		[ 'Freigaben', $rev_rows, fge_cc_url( 'partner' ), 'Partner und Events', 'Keine Freigaben offen.' ],
		[ 'Zahlungen', $pay_rows, fge_cc_url( 'finanzen' ), 'Finanzen', 'Keine überfälligen Rechnungen.' ],
		[ 'Neue Leads', $lead_rows, fge_cc_url( 'kunden', [ 'ansicht' => 'leads' ] ), 'Alle Leads', 'Keine neuen Leads.' ],
		[ 'Mails mit Fehler', $mail_rows, fge_cc_url( 'einstellungen', [ 'bereich' => 'postausgang' ] ), 'Postausgang', 'Alle Mails sind raus.' ],
	];
	// Was wartet, steht oben; die Reihenfolge darunter bleibt.
	$full  = array_values( array_filter( $groups, static fn( $g ) => (bool) $g[1] ) );
	$empty = array_values( array_filter( $groups, static fn( $g ) => ! $g[1] ) );
	return $groups = array_merge( $full, $empty );
}

/** Alles, was unter „Heute“ wartet. */
function fge_cc_today_total(): int {
	return array_sum( array_map( static fn( $g ) => count( $g[1] ), fge_cc_today_groups() ) );
}

/** Kennzahlen rechts unten: Anfragen nach Phase, Umsatz im Monat, offen brutto. */
/** Offene Vorgänge je Phase (offen, beim Kunden, gebucht), für Menü und Kennzahlen. */
function fge_cc_bucket_counts(): array {
	static $counts = null;
	if ( null !== $counts ) {
		return $counts;
	}
	$counts = [ 'offen' => 0, 'kunde' => 0, 'gebucht' => 0 ];
	$res    = fge_cc_query_requests( [ 'meta_query' => fge_cc_open_meta_query() ], 500 );
	foreach ( $res['ids'] as $req ) {
		$b = fge_cc_flow( (int) $req )['bucket'] ?? '';
		if ( isset( $counts[ $b ] ) ) {
			$counts[ $b ]++;
		}
	}
	return $counts;
}

function fge_cc_today_stats(): array {
	$counts = fge_cc_bucket_counts();
	$rev  = function_exists( 'fge_cc_revenue_year' ) ? fge_cc_revenue_year( (int) wp_date( 'Y' ) ) : [ 'months' => [] ];
	$open = array_filter( function_exists( 'fge_cc_invoices' ) ? fge_cc_invoices() : [], static fn( $r ) => ! $r['paid'] );
	return [
		[ 'Offene Anfragen', (string) $counts['offen'], fge_cc_url( 'anfragen', [ 'tab' => 'offen' ] ) ],
		[ 'Beim Kunden', (string) $counts['kunde'], fge_cc_url( 'anfragen', [ 'tab' => 'kunde' ] ) ],
		[ 'Umsatz ' . wp_date( 'F' ), fge_cc_eur0( (float) ( $rev['months'][ (int) wp_date( 'n' ) ]['net'] ?? 0 ) ), fge_cc_url( 'finanzen' ) ],
		[ 'Offen brutto', fge_cc_eur0( array_sum( array_column( $open, 'gross' ) ) ), fge_cc_url( 'finanzen' ) ],
	];
}

/** Die nächsten fünf gebuchten Events ab heute. */
function fge_cc_today_events(): array {
	$out = [];
	$acc = fge_cc_query_requests( [ 'meta_query' => [ [ 'key' => '_fge_offer_status', 'value' => 'accepted' ] ] ], 500 ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	foreach ( $acc['ids'] as $req ) {
		$req = (int) $req;
		$d   = fge_cc_event_date( $req );
		if ( $d < fge_cc_today() || 'verloren' === (string) get_post_meta( $req, '_fge_request_status', true ) ) {
			continue;
		}
		$f     = fge_cc_request_fact_data( $req );
		$pax   = function_exists( 'fge_request_pax_current' ) ? fge_request_pax_current( $req ) : (int) get_post_meta( $req, '_fge_expected_participants', true );
		$out[] = [ $req, $d, (string) get_post_meta( $req, '_fge_company_name', true ), implode( ' · ', array_filter( [ (string) ( $f['event'] ?? '' ), $pax > 0 ? $pax . ' Personen' : '' ] ) ) ];
	}
	usort( $out, static fn( $a, $b ) => $a[1] <=> $b[1] );
	return array_slice( $out, 0, 5 );
}

function fge_cc_page_dashboard(): void {
	fge_cc_message();
	$groups = fge_cc_today_groups();
	$tone   = static fn( string $t ) => in_array( $t, [ 'good', 'warn', 'bad', 'neutral', 'brand', 'info' ], true ) ? $t : 'neutral';

	echo '<div class="cc-today" data-cc-today>';
	echo '<div class="cc-today-main">';
	foreach ( $groups as [ $title, $rows, $all_url, $all_label, $empty ] ) {
		echo '<section class="cc-tgroup"><div class="cc-tgroup-head"><div class="cc-tgroup-title"><h2>' . esc_html( $title ) . '</h2><span class="cc-tcount' . ( $rows ? ' is-on' : '' ) . '">' . (int) count( $rows ) . '</span></div>';
		echo '<a class="cc-tgroup-all" href="' . esc_url( $all_url ) . '">' . esc_html( $all_label ) . '</a></div>';
		foreach ( $rows as $r ) {
			echo '<a class="cc-trow" href="' . esc_url( $r['url'] ) . '" data-cc-pick="' . esc_attr( $r['key'] ) . '">';
			echo '<span class="cc-trow-tag">' . fge_cc_pill( $r['tag'][0], $tone( $r['tag'][1] ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<span class="cc-trow-main"><strong>' . esc_html( $r['title'] ) . '</strong><span>' . esc_html( $r['sub'] ) . '</span></span></a>';
		}
		if ( ! $rows ) {
			echo '<p class="cc-tgroup-empty">' . esc_html( $empty ) . '</p>';
		}
		echo '</section>';
	}
	echo '</div>';

	echo '<aside class="cc-today-side">';
	// Detailfelder, eins je Zeile, per Klick sichtbar.
	foreach ( $groups as [ $title, $rows ] ) {
		foreach ( $rows as $r ) {
			echo '<section class="cc-tdetail" data-cc-detail="' . esc_attr( $r['key'] ) . '" hidden>';
			echo '<div class="cc-tdetail-top"><span class="cc-muted">' . esc_html( $title ) . '</span><button type="button" class="cc-tdetail-x" data-cc-unpick>Schließen</button></div>';
			echo '<div class="cc-tdetail-head">' . fge_cc_pill( $r['tag'][0], $tone( $r['tag'][1] ) ) . '<h2>' . esc_html( $r['title'] ) . '</h2></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<dl class="cc-tfacts">';
			foreach ( $r['facts'] as [ $k, $v ] ) {
				if ( '' !== trim( (string) $v ) ) {
					echo '<div><dt>' . esc_html( $k ) . '</dt><dd>' . esc_html( (string) $v ) . '</dd></div>';
				}
			}
			echo '</dl><div class="cc-tdetail-act">';
			foreach ( $r['actions'] as $act ) {
				$act();
			}
			echo '</div></section>';
		}
	}

	echo '<div class="cc-today-default" data-cc-default>';
	echo '<section class="cc-card cc-tnext"><h2>Nächste Events</h2>';
	$evs = fge_cc_today_events();
	foreach ( $evs as [ $req, $d, $company, $sub ] ) {
		echo '<a class="cc-tnext-row" href="' . esc_url( fge_cc_request_url( $req ) ) . '"><span class="cc-tnext-date"><strong>' . esc_html( wp_date( 'j', $d ) ) . '</strong><span>' . esc_html( fge_cc_mon( $d ) ) . '</span></span>';
		echo '<span class="cc-tnext-main"><strong>' . esc_html( $company ?: 'Ohne Firma' ) . '</strong><span>' . esc_html( $sub ) . '</span></span></a>';
	}
	if ( ! $evs ) {
		echo '<p class="cc-muted">Keine gebuchten Events.</p>';
	}
	echo '</section>';
	echo '<section class="cc-card cc-tstats">';
	foreach ( fge_cc_today_stats() as [ $label, $value, $url ] ) {
		echo '<a class="cc-tstat" href="' . esc_url( $url ) . '"><span>' . esc_html( $label ) . '</span><strong>' . esc_html( $value ) . '</strong></a>';
	}
	echo '</section></div>';
	echo '</aside></div>';
}
