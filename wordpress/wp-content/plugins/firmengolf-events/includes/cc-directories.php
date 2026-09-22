<?php
/**
 * Control Center: Verzeichnisse für Plätze und Kunden.
 *
 * „Alle Plätze im Blick, alle Ansprechpartner" war eine der Kernforderungen.
 * Dazu die Datenqualität: ein Platz ohne Kontaktmail bekommt weder die
 * Buchungs- noch die Vortagsmail, und das fällt heute erst auf, wenn ein
 * Event danebengeht (Befund Golfpark Weidenhof, 22.09.2026).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Plätze ───────────────────────────────────────────────────────────────────

/** Kennzahlen je Platz in wenigen Abfragen statt je Zeile. */
function fge_cc_partner_stats(): array {
	global $wpdb;
	$out = [];

	// Events und Anfragen hängen beide über _fge_assigned_partner_id am Platz.
	$rows = $wpdb->get_results(
		"SELECT pm.meta_value AS pid, p.post_type AS type, COUNT(*) AS n
		 FROM {$wpdb->postmeta} pm
		 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		 WHERE pm.meta_key = '_fge_assigned_partner_id'
		   AND pm.meta_value > 0
		   AND p.post_type IN ('firmengolf_event','firmengolf_request')
		 GROUP BY pm.meta_value, p.post_type",
		ARRAY_A
	) ?: [];

	foreach ( $rows as $r ) {
		$pid = (int) $r['pid'];
		$out[ $pid ] = $out[ $pid ] ?? [ 'events' => 0, 'requests' => 0 ];
		$key = 'firmengolf_event' === $r['type'] ? 'events' : 'requests';
		$out[ $pid ][ $key ] = (int) $r['n'];
	}
	return $out;
}

/** Alle Ansprechpartner vieler Plätze in einer Abfrage, nach Platz gruppiert. */
function fge_cc_contacts_many( array $partner_ids ): array {
	$partner_ids = array_values( array_unique( array_map( 'intval', $partner_ids ) ) );
	if ( ! $partner_ids || ! function_exists( 'fge_contacts_table' ) ) {
		return [];
	}
	global $wpdb;
	$t  = fge_contacts_table();
	$in = implode( ',', array_fill( 0, count( $partner_ids ), '%d' ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$t} WHERE status = 'active' AND partner_id IN ({$in}) ORDER BY id ASC",
		...$partner_ids
	), ARRAY_A ) ?: [];

	$out = [];
	foreach ( $rows as $r ) {
		$out[ (int) $r['partner_id'] ][] = $r;
	}
	return $out;
}

function fge_cc_page_partners(): void {
	$search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$only   = sanitize_key( wp_unslash( $_GET['filter'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$ids = get_posts( [
		'post_type'      => 'firmengolf_partner',
		'post_status'    => [ 'publish', 'draft', 'pending' ],
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'fields'         => 'ids',
	] );

	$stats   = fge_cc_partner_stats();
	$rows    = [];
	$no_mail = 0;

	// Alles, was sonst je Zeile eine eigene Abfrage wäre, einmal vorladen:
	// Titel über den Post-Cache, Metafelder, Kontakte, Preise.
	_prime_post_caches( $ids, false, true );
	$contacts_by_partner = fge_cc_contacts_many( $ids );
	$price_hints         = function_exists( 'fge_venue_price_hints_many' ) ? fge_venue_price_hints_many( $ids ) : [];

	foreach ( $ids as $pid ) {
		$title = get_the_title( $pid );
		$city  = (string) get_post_meta( $pid, '_fge_city', true );
		if ( '' !== $search && false === mb_stripos( $title . ' ' . $city, $search ) ) {
			continue;
		}
		$mail = function_exists( 'fge_cc_partner_email' ) ? fge_cc_partner_email( (int) $pid ) : '';
		if ( '' === $mail ) {
			$no_mail++;
		}
		if ( 'ohne_mail' === $only && '' !== $mail ) {
			continue;
		}
		$rows[] = [
			'id'       => (int) $pid,
			'title'    => $title,
			'city'     => $city,
			'status'   => (string) get_post_meta( $pid, '_fge_partner_status', true ),
			'mail'     => $mail,
			'contacts' => $contacts_by_partner[ (int) $pid ] ?? [],
			'events'   => (int) ( $stats[ (int) $pid ]['events'] ?? 0 ),
			'requests' => (int) ( $stats[ (int) $pid ]['requests'] ?? 0 ),
			'price'    => (string) ( $price_hints[ (int) $pid ] ?? '' ),
		];
	}

	// Datenqualität zuerst: was den Betrieb still sabotiert.
	if ( $no_mail > 0 ) {
		echo '<p class="cc-msg cc-msg--err">' . (int) $no_mail . ' ' . ( 1 === $no_mail ? 'Platz hat' : 'Plätze haben' )
			. ' keine Kontaktmail. Diese Plätze bekommen weder die Auftragsbestätigung noch die Vortags-Info. '
			. '<a href="' . esc_url( fge_cc_url( 'plaetze', [ 'filter' => 'ohne_mail' ] ) ) . '">Nur diese zeigen</a></p>';
	}

	echo '<div class="cc-filters">';
	echo '<a class="cc-chip' . ( '' === $only ? ' is-on' : '' ) . '" href="' . esc_url( fge_cc_url( 'plaetze' ) ) . '">Alle ' . (int) count( $ids ) . '</a>';
	echo '<a class="cc-chip' . ( 'ohne_mail' === $only ? ' is-on' : '' ) . '" href="' . esc_url( fge_cc_url( 'plaetze', [ 'filter' => 'ohne_mail' ] ) ) . '">Ohne Kontaktmail</a>';
	echo '</div>';

	if ( ! $rows ) {
		fge_cc_empty( 'Kein Platz gefunden.' );
		return;
	}

	echo '<div class="cc-list">';
	foreach ( $rows as $r ) {
		fge_cc_partner_row( $r );
	}
	echo '</div>';
}

function fge_cc_partner_row( array $r ): void {
	$tone = [ 'aktiv' => 'good', 'in_pruefung' => 'warn', 'rueckfragen' => 'warn', 'pausiert' => 'neutral', 'abgelehnt' => 'bad' ][ $r['status'] ] ?? 'neutral';

	echo '<div class="cc-dir-row">';
	echo '<div class="cc-dir-head">';
	echo '<span class="cc-dir-name">' . esc_html( $r['title'] ) . '</span>';
	if ( '' !== $r['city'] ) {
		echo '<span class="cc-muted">' . esc_html( $r['city'] ) . '</span>';
	}
	echo fge_cc_pill( $r['status'] ?: 'ohne Status', $tone ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<span class="cc-dir-meta">' . (int) $r['events'] . ' Events · ' . (int) $r['requests'] . ' Anfragen</span>';
	if ( '' !== $r['price'] ) {
		echo '<span class="cc-dir-price">' . esc_html( $r['price'] ) . '</span>';
	}
	echo '</div>';

	if ( '' === $r['mail'] ) {
		echo '<p class="cc-warn">Keine Kontaktmail hinterlegt.</p>';
	}

	$contacts = $r['contacts'];
	if ( $contacts ) {
		echo '<details class="cc-details"><summary>' . (int) count( $contacts ) . ' '
			. ( 1 === count( $contacts ) ? 'Ansprechpartner' : 'Ansprechpartner' ) . '</summary>';
		echo '<table class="cc-table cc-table--plain"><tbody>';
		foreach ( $contacts as $c ) {
			echo '<tr><td>' . esc_html( (string) $c['name'] ) . '</td>';
			echo '<td class="cc-muted">' . esc_html( (string) $c['role'] ) . '</td>';
			echo '<td>' . ( is_email( (string) $c['email'] ) ? '<a href="mailto:' . esc_attr( (string) $c['email'] ) . '">' . esc_html( (string) $c['email'] ) . '</a>' : '<span class="cc-muted">ohne Mail</span>' ) . '</td>';
			echo '<td class="cc-muted">' . esc_html( 'vote' === ( $c['permission'] ?? '' ) ? 'stimmt ab' : 'wird informiert' ) . '</td></tr>';
		}
		echo '</tbody></table></details>';
	} else {
		echo '<p class="cc-muted">Keine weiteren Ansprechpartner hinterlegt.</p>';
	}

	echo '<p class="cc-dir-links"><a href="' . esc_url( get_edit_post_link( $r['id'], 'raw' ) ) . '">Im WordPress öffnen</a></p>';
	echo '</div>';
}

// ── Kunden ───────────────────────────────────────────────────────────────────

/**
 * Firmen aus den Anfragen verdichten.
 *
 * Bewusst kein eigener Stammdatensatz: Kunden entstehen aus Anfragen, und
 * solange es kaum Wiederkäufer gibt, wäre eine zweite Datenhaltung nur Ballast.
 */
function fge_cc_page_customers(): void {
	$search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$ids = get_posts( [
		'post_type'      => 'firmengolf_request',
		'post_status'    => [ 'publish', 'draft' ],
		'posts_per_page' => 400,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'fields'         => 'ids',
	] );

	_prime_post_caches( $ids, false, true );
	$companies = [];
	$by_mail   = [];

	foreach ( $ids as $req ) {
		$name  = trim( (string) get_post_meta( $req, '_fge_company_name', true ) );
		$mail  = strtolower( trim( (string) get_post_meta( $req, '_fge_contact_email', true ) ) );
		$key   = '' !== $name ? mb_strtolower( $name ) : ( '' !== $mail ? $mail : 'ohne-' . $req );
		$accepted = 'accepted' === (string) get_post_meta( $req, '_fge_offer_status', true );

		if ( ! isset( $companies[ $key ] ) ) {
			$companies[ $key ] = [
				'name'     => $name ?: 'Ohne Firmennamen',
				'city'     => (string) get_post_meta( $req, '_fge_company_city', true ),
				'contact'  => trim( (string) get_post_meta( $req, '_fge_contact_first_name', true ) . ' ' . (string) get_post_meta( $req, '_fge_contact_last_name', true ) ),
				'mail'     => (string) get_post_meta( $req, '_fge_contact_email', true ),
				'phone'    => (string) get_post_meta( $req, '_fge_contact_phone', true ),
				'requests' => [],
				'bookings' => 0,
				'revenue'  => 0.0,
				'last'     => 0,
			];
		}
		$companies[ $key ]['requests'][] = $req;
		if ( $accepted ) {
			$companies[ $key ]['bookings']++;
			$snap = (array) get_post_meta( $req, '_fge_offer_snapshot', true );
			$sel  = function_exists( 'fge_offer_selected_extras' ) ? fge_offer_selected_extras( $req ) : [];
			$tot  = function_exists( 'fge_offer_totals' ) ? fge_offer_totals( $snap, $sel ) : [ 'net' => 0 ];
			$companies[ $key ]['revenue'] += (float) ( $tot['net'] ?? 0 );
		}
		$ts = (int) get_post_time( 'U', false, $req );
		if ( $ts > $companies[ $key ]['last'] ) {
			$companies[ $key ]['last'] = $ts;
		}
		if ( '' !== $mail ) {
			$by_mail[ $mail ][ $key ] = true;
		}
	}

	// Dubletten: dieselbe Mailadresse unter verschiedenen Firmennamen.
	$dupes = [];
	foreach ( $by_mail as $mail => $keys ) {
		if ( count( $keys ) > 1 ) {
			foreach ( array_keys( $keys ) as $k ) {
				$dupes[ $k ] = $mail;
			}
		}
	}

	uasort( $companies, static fn( $a, $b ) => $b['last'] <=> $a['last'] );

	if ( '' !== $search ) {
		$companies = array_filter( $companies, static fn( $c ) => false !== mb_stripos( $c['name'] . ' ' . $c['mail'], $search ) );
	}

	if ( $dupes ) {
		echo '<p class="cc-msg cc-msg--err">' . (int) count( $dupes ) . ' Einträge teilen sich eine Mailadresse unter verschiedenen Firmennamen. Vermutlich Dubletten.</p>';
	}

	if ( ! $companies ) {
		fge_cc_empty( 'Kein Kunde gefunden.' );
		return;
	}

	echo '<div class="cc-tablewrap"><table class="cc-table">';
	echo '<thead><tr><th>Firma</th><th>Ansprechpartner</th><th>Kontakt</th><th class="cc-num">Anfragen</th><th class="cc-num">Buchungen</th><th class="cc-num">Umsatz netto</th><th>Zuletzt</th></tr></thead><tbody>';
	foreach ( $companies as $key => $c ) {
		echo '<tr>';
		echo '<td><strong>' . esc_html( $c['name'] ) . '</strong>';
		if ( '' !== $c['city'] ) {
			echo '<br><span class="cc-muted">' . esc_html( $c['city'] ) . '</span>';
		}
		if ( isset( $dupes[ $key ] ) ) {
			echo '<br><span class="cc-dupe">Dublette?</span>';
		}
		echo '</td>';
		echo '<td>' . esc_html( $c['contact'] ?: '—' ) . '</td>';
		echo '<td>';
		if ( '' !== $c['phone'] ) {
			echo '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $c['phone'] ) ) . '">' . esc_html( $c['phone'] ) . '</a><br>';
		}
		if ( '' !== $c['mail'] ) {
			echo '<a href="mailto:' . esc_attr( $c['mail'] ) . '">' . esc_html( $c['mail'] ) . '</a>';
		}
		echo '</td>';
		echo '<td class="cc-num">' . (int) count( $c['requests'] ) . '</td>';
		echo '<td class="cc-num">' . (int) $c['bookings'] . '</td>';
		echo '<td class="cc-num">' . esc_html( $c['revenue'] > 0 ? number_format_i18n( $c['revenue'], 0 ) . ' €' : '—' ) . '</td>';
		echo '<td class="cc-muted">' . esc_html( $c['last'] > 0 ? wp_date( 'd.m.Y', $c['last'] ) : '—' ) . '</td>';
		echo '</tr>';
	}
	echo '</tbody></table></div>';
}
