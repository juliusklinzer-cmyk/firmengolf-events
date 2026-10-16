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

	// Stammdaten-Plätze (alle DGV-Plätze und Simulatoren, seit 28.09.2026) sind
	// keine Partner: eigene Sektion, nur mit Filter oder Suche gerendert, und sie
	// zählen nicht in die Warnung „ohne Kontaktmail" (sonst ~800 Treffer).
	_prime_post_caches( $ids, false, true );
	$partner_ids = [];
	$stamm_ids   = [];
	foreach ( $ids as $pid ) {
		if ( function_exists( 'fge_partner_is_stammdaten' ) && fge_partner_is_stammdaten( (int) $pid ) ) {
			$stamm_ids[] = (int) $pid;
		} else {
			$partner_ids[] = (int) $pid;
		}
	}
	$show_stamm = 'stammdaten' === $only;
	$list_ids   = $show_stamm ? $stamm_ids : $partner_ids;
	if ( '' !== $search && ! $show_stamm ) {
		$list_ids = array_merge( $partner_ids, $stamm_ids ); // Suche findet auch Stammdaten.
	}

	// Vorfilter über Titel und Ort, damit Kontakte und Preise nur für die
	// tatsächlich gezeigten Zeilen geladen werden.
	$shown = [];
	foreach ( $list_ids as $pid ) {
		$title = get_the_title( $pid );
		$city  = (string) get_post_meta( $pid, '_fge_city', true );
		$plz   = (string) get_post_meta( $pid, '_fge_postal_code', true );
		if ( '' !== $search && false === mb_stripos( $title . ' ' . $city . ' ' . $plz, $search ) ) {
			continue;
		}
		$shown[ $pid ] = [ $title, $city ];
	}
	$shown_ids           = array_keys( $shown );
	$contacts_by_partner = $shown_ids ? fge_cc_contacts_many( $shown_ids ) : [];
	$price_hints         = ( $shown_ids && function_exists( 'fge_venue_price_hints_many' ) ) ? fge_venue_price_hints_many( $shown_ids ) : [];

	foreach ( $partner_ids as $pid ) {
		if ( '' === ( function_exists( 'fge_cc_partner_email' ) ? fge_cc_partner_email( $pid ) : '' ) ) {
			$no_mail++;
		}
	}

	foreach ( $shown as $pid => [ $title, $city ] ) {
		$mail = function_exists( 'fge_cc_partner_email' ) ? fge_cc_partner_email( (int) $pid ) : '';
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

	if ( function_exists( 'fge_stammdaten_import_status_line' ) ) {
		fge_stammdaten_import_status_line();
	}

	// Datenqualität zuerst: was den Betrieb still sabotiert.
	if ( $no_mail > 0 ) {
		echo '<p class="cc-msg cc-msg--err">' . (int) $no_mail . ' ' . ( 1 === $no_mail ? 'Partner hat' : 'Partner haben' )
			. ' keine Kontaktmail. Diese Plätze bekommen weder die Auftragsbestätigung noch die Vortags-Info. '
			. '<a href="' . esc_url( fge_cc_url( 'verzeichnis', [ 'filter' => 'ohne_mail' ] ) ) . '">Nur diese zeigen</a></p>';
	}

	echo '<div class="cc-filters">';
	echo '<a class="cc-chip' . ( '' === $only ? ' is-on' : '' ) . '" href="' . esc_url( fge_cc_url( 'verzeichnis' ) ) . '">Partner ' . (int) count( $partner_ids ) . '</a>';
	echo '<a class="cc-chip' . ( 'ohne_mail' === $only ? ' is-on' : '' ) . '" href="' . esc_url( fge_cc_url( 'verzeichnis', [ 'filter' => 'ohne_mail' ] ) ) . '">Ohne Kontaktmail</a>';
	echo '<a class="cc-chip' . ( $show_stamm ? ' is-on' : '' ) . '" href="' . esc_url( fge_cc_url( 'verzeichnis', [ 'filter' => 'stammdaten' ] ) ) . '">Stammdaten ' . (int) count( $stamm_ids ) . '</a>';
	echo '</div>';
	if ( $show_stamm && '' === $search ) {
		echo '<p class="cc-muted">Stammdaten sind alle Golfplätze und Simulatoren aus dem Verzeichnis, keine Partner. Sie lassen sich in jeder Anfrage anfragen. Mit der Suche oben eingrenzen, die Liste zeigt sonst alle.</p>';
	}

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
	$tone = [ 'aktiv' => 'good', 'in_pruefung' => 'warn', 'rueckfragen' => 'warn', 'pausiert' => 'neutral', 'abgelehnt' => 'bad', 'stammdaten' => 'neutral' ][ $r['status'] ] ?? 'neutral';

	echo '<div class="cc-dir-row">';
	echo '<div class="cc-dir-head">';
	echo '<span class="cc-dir-name">' . esc_html( $r['title'] ) . '</span>';
	if ( '' !== $r['city'] ) {
		echo '<span class="cc-muted">' . esc_html( $r['city'] ) . '</span>';
	}
	$labels = [ 'stammdaten' => 'Stammdaten', 'in_pruefung' => 'In Prüfung', 'rueckfragen' => 'Rückfragen', 'aktiv' => 'Aktiv', 'pausiert' => 'Pausiert', 'abgelehnt' => 'Abgelehnt' ];
	echo fge_cc_pill( $labels[ $r['status'] ] ?? ( $r['status'] ?: 'ohne Status' ), $tone ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<span class="cc-dir-meta">' . (int) $r['events'] . ' Events · ' . (int) $r['requests'] . ' Anfragen</span>';
	if ( '' !== $r['price'] ) {
		echo '<span class="cc-dir-price">' . esc_html( $r['price'] ) . '</span>';
	}
	echo '</div>';

	if ( '' === $r['mail'] ) {
		echo '<p class="cc-warn">Keine Kontaktmail hinterlegt.</p>';
	}
	if ( function_exists( 'fge_cc_partner_contact_form' ) && ( '' === $r['mail'] || 'stammdaten' === $r['status'] ) ) {
		fge_cc_partner_contact_form( (int) $r['id'] );
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

	$is_partner = 'stammdaten' !== (string) get_post_meta( $r['id'], '_fge_partner_status', true );
	echo '<p class="cc-dir-links">' . ( $is_partner ? '<a href="' . esc_url( fge_cc_url( 'partner', [ 'pid' => $r['id'] ] ) ) . '">Partner öffnen</a> · ' : '' ) . '<a href="' . esc_url( get_edit_post_link( $r['id'], 'raw' ) ) . '">Im WordPress öffnen</a></p>';
	echo '</div>';
}

// ── Kunden ───────────────────────────────────────────────────────────────────

/**
 * Firmen aus den Anfragen verdichten.
 *
 * Bewusst kein eigener Stammdatensatz: Kunden entstehen aus Anfragen. Der
 * Schlüssel ist der Firmenname klein geschrieben, ohne Namen die Mailadresse.
 * Die interne Notiz liegt unter diesem Schlüssel in fge_customer_notes
 * (Freigabe Julius, 16.10.2026).
 */
function fge_cc_customers(): array {
	static $out = null;
	if ( null !== $out ) {
		return $out;
	}
	$result    = fge_cc_query_requests( [], 600 );
	$companies = [];
	$by_mail   = [];
	foreach ( $result['ids'] as $req ) {
		$req      = (int) $req;
		$name     = trim( (string) get_post_meta( $req, '_fge_company_name', true ) );
		$mail     = strtolower( trim( (string) get_post_meta( $req, '_fge_contact_email', true ) ) );
		$key      = fge_cc_customer_key( $name, $mail, $req );
		$accepted = 'accepted' === (string) get_post_meta( $req, '_fge_offer_status', true ) && 'verloren' !== (string) get_post_meta( $req, '_fge_request_status', true );
		$ts       = (int) get_post_time( 'U', false, $req );
		if ( ! isset( $companies[ $key ] ) ) {
			$companies[ $key ] = [
				'key'      => $key,
				'name'     => $name ?: 'Ohne Firmennamen',
				'city'     => (string) get_post_meta( $req, '_fge_company_city', true ),
				'contact'  => trim( (string) get_post_meta( $req, '_fge_contact_first_name', true ) . ' ' . (string) get_post_meta( $req, '_fge_contact_last_name', true ) ),
				'mail'     => (string) get_post_meta( $req, '_fge_contact_email', true ),
				'phone'    => (string) get_post_meta( $req, '_fge_contact_phone', true ),
				'requests' => [],
				'bookings' => 0,
				'revenue'  => 0.0,
				'last'     => 0,
				'first'    => $ts,
			];
		}
		$c = &$companies[ $key ];
		$c['requests'][] = $req;
		if ( $accepted ) {
			$c['bookings']++;
			$c['revenue'] += fge_cc_offer_net( $req );
		}
		if ( $ts > $c['last'] ) {
			// Kontaktdaten von der neuesten Anfrage, die alten können veraltet sein.
			$c['last'] = $ts;
			foreach ( [ 'city' => '_fge_company_city', 'mail' => '_fge_contact_email', 'phone' => '_fge_contact_phone' ] as $k => $mk ) {
				$v = (string) get_post_meta( $req, $mk, true );
				if ( '' !== $v ) {
					$c[ $k ] = $v;
				}
			}
			$cn = trim( (string) get_post_meta( $req, '_fge_contact_first_name', true ) . ' ' . (string) get_post_meta( $req, '_fge_contact_last_name', true ) );
			if ( '' !== $cn ) {
				$c['contact'] = $cn;
			}
		}
		$c['first'] = min( $c['first'], $ts );
		unset( $c );
		if ( '' !== $mail ) {
			$by_mail[ $mail ][ $key ] = true;
		}
	}
	// Dubletten: dieselbe Mailadresse unter verschiedenen Firmennamen.
	foreach ( $by_mail as $mail => $keys ) {
		if ( count( $keys ) > 1 ) {
			foreach ( array_keys( $keys ) as $k ) {
				$companies[ $k ]['dupe'] = $mail;
			}
		}
	}
	return $out = [ 'rows' => $companies, 'result' => $result ];
}

/** Schlüssel eines Kunden (Notiz, Link). */
function fge_cc_customer_key( string $name, string $mail, int $req = 0 ): string {
	$name = trim( $name );
	return '' !== $name ? mb_strtolower( $name ) : ( '' !== $mail ? strtolower( $mail ) : 'ohne-' . $req );
}

/** Interne Notiz zu einem Kunden. */
function fge_cc_customer_note( string $key ): string {
	$all = (array) get_option( 'fge_customer_notes', [] );
	return (string) ( $all[ $key ] ?? '' );
}

add_action( 'admin_post_fge_cc_customer_note', static function (): void {
	if ( ! fge_cc_can() ) {
		wp_die( 'Keine Berechtigung.', '', [ 'response' => 403 ] );
	}
	check_admin_referer( 'fge_cc_customer_note' );
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce oben geprüft.
	$key  = mb_substr( sanitize_text_field( wp_unslash( $_POST['key'] ?? '' ) ), 0, 190 );
	$note = mb_substr( sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) ), 0, 4000 );
	// phpcs:enable
	if ( '' !== $key ) {
		$all = (array) get_option( 'fge_customer_notes', [] );
		if ( '' === trim( $note ) ) {
			unset( $all[ $key ] );
		} else {
			$all[ $key ] = $note;
		}
		update_option( 'fge_customer_notes', $all, false );
	}
	wp_safe_redirect( fge_cc_url( 'kunden', [ 'kunde' => $key, 'msg' => 'note_saved' ] ) );
	exit;
} );

/** Kopfzeile einer sortierbaren Spalte. */
function fge_cc_sort_th( string $page, string $key, string $label, string $cur, string $dir, array $keep, bool $num = false ): void {
	$on   = $key === $cur;
	$next = $on ? ( 'asc' === $dir ? 'desc' : 'asc' ) : ( $num ? 'desc' : 'asc' );
	echo '<th' . ( $num ? ' class="cc-num"' : '' ) . ( $on ? ' aria-sort="' . ( 'asc' === $dir ? 'ascending' : 'descending' ) . '"' : '' ) . '>';
	echo '<a class="cc-sortth' . ( $on ? ' is-on' : '' ) . '" href="' . esc_url( fge_cc_url( $page, array_filter( [ 'sort' => $key, 'dir' => $next ] + $keep ) ) ) . '">' . esc_html( $label ) . '<span aria-hidden="true">' . ( $on ? ( 'asc' === $dir ? '↑' : '↓' ) : '' ) . '</span></a></th>';
}

function fge_cc_page_customers(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
	$view   = 'leads' === sanitize_key( wp_unslash( $_GET['ansicht'] ?? '' ) ) ? 'leads' : 'kunden';
	$open   = sanitize_text_field( wp_unslash( $_GET['kunde'] ?? '' ) );
	$sort   = sanitize_key( wp_unslash( $_GET['sort'] ?? 'last' ) );
	$dir    = 'asc' === ( $_GET['dir'] ?? '' ) ? 'asc' : 'desc';
	// phpcs:enable
	fge_cc_message();
	$data  = fge_cc_customers();
	$rows  = $data['rows'];
	$leads = function_exists( 'fge_cc_lead_rows' ) ? fge_cc_lead_rows() : [];
	$new   = count( array_filter( $leads, static fn( $l ) => 'neu' === $l['status'] ) );

	echo '<p class="cc-top-sub cc-sub-under">' . esc_html( count( $rows ) . ' Firmenkunden · ' . $new . ( 1 === $new ? ' neuer Lead' : ' neue Leads' ) ) . '</p>';
	fge_cc_seglinks( 'kunden', 'ansicht', [ 'kunden' => 'Kunden', 'leads' => 'Leads' ], $view, [ 'kunden' => count( $rows ), 'leads' => count( $leads ) ], [ 's' => $search ] );

	if ( 'leads' === $view ) {
		fge_cc_customers_leads( $leads, $search );
		return;
	}

	fge_cc_truncation_note( $data['result'], 'Anfragen' );
	if ( '' !== $search ) {
		$rows = array_filter( $rows, static fn( $c ) => false !== mb_stripos( $c['name'] . ' ' . $c['contact'] . ' ' . $c['city'] . ' ' . $c['mail'], $search ) );
	}
	$dupes = count( array_filter( $rows, static fn( $c ) => ! empty( $c['dupe'] ) ) );
	if ( $dupes ) {
		echo '<p class="cc-msg cc-msg--err">' . (int) $dupes . ' Einträge teilen sich eine Mailadresse unter verschiedenen Firmennamen. Vermutlich Dubletten.</p>';
	}
	$val = static function ( array $c ) use ( $sort ) {
		switch ( $sort ) {
			case 'company':
				return mb_strtolower( $c['name'] );
			case 'contact':
				return mb_strtolower( $c['contact'] );
			case 'city':
				return mb_strtolower( $c['city'] );
			case 'n':
				return count( $c['requests'] );
			case 'revenue':
				return $c['revenue'];
			default:
				return $c['last'];
		}
	};
	uasort( $rows, static function ( $a, $b ) use ( $val, $dir ) {
		$x = $val( $a );
		$y = $val( $b );
		$r = is_string( $x ) ? strcoll( $x, $y ) : $x <=> $y;
		return 'asc' === $dir ? $r : -$r;
	} );

	$keep = [ 's' => $search ];
	echo '<section class="cc-tablewrap"><table class="cc-table cc-custtable"><thead><tr>';
	fge_cc_sort_th( 'kunden', 'company', 'Firma', $sort, $dir, $keep );
	fge_cc_sort_th( 'kunden', 'contact', 'Ansprechpartner', $sort, $dir, $keep );
	fge_cc_sort_th( 'kunden', 'city', 'Ort', $sort, $dir, $keep );
	fge_cc_sort_th( 'kunden', 'n', 'Anfragen', $sort, $dir, $keep, true );
	fge_cc_sort_th( 'kunden', 'revenue', 'Umsatz netto', $sort, $dir, $keep, true );
	fge_cc_sort_th( 'kunden', 'last', 'Letzte Anfrage', $sort, $dir, $keep );
	echo '</tr></thead><tbody>';
	foreach ( $rows as $c ) {
		$url = fge_cc_url( 'kunden', array_filter( [ 'kunde' => $c['key'], 's' => $search, 'sort' => $sort, 'dir' => $dir ] ) );
		echo '<tr onclick="location.href=\'' . esc_js( $url ) . '\'"><td><a href="' . esc_url( $url ) . '"><strong>' . esc_html( $c['name'] ) . '</strong></a>' . ( ! empty( $c['dupe'] ) ? ' <span class="cc-dupe">Dublette?</span>' : '' ) . '</td>';
		echo '<td>' . esc_html( $c['contact'] ) . '</td><td>' . esc_html( $c['city'] ) . '</td>';
		echo '<td class="cc-num">' . (int) count( $c['requests'] ) . '</td>';
		echo '<td class="cc-num">' . esc_html( fge_cc_eur0( $c['revenue'] ) ) . '</td>';
		echo '<td class="cc-tnum cc-muted">' . esc_html( $c['last'] > 0 ? wp_date( 'd.m.Y', $c['last'] ) : '' ) . '</td></tr>';
	}
	if ( ! $rows ) {
		echo '<tr class="is-empty"><td colspan="6">Kein Kunde gefunden.</td></tr>';
	}
	echo '</tbody></table></section>';

	if ( '' !== $open && isset( $data['rows'][ $open ] ) ) {
		fge_cc_customer_drawer( $data['rows'][ $open ] );
	}
}

/** Seitenfenster eines Kunden: Kontakt, Umsatz, alle Anfragen, Notiz. */
function fge_cc_customer_drawer( array $c ): void {
	fge_cc_drawer_open( 'cc-cust', $c['name'], implode( ' · ', array_filter( [ $c['city'], $c['first'] > 0 ? 'erste Anfrage ' . wp_date( 'm.Y', $c['first'] ) : '' ] ) ), true );
	echo '<div class="cc-custfacts">';
	foreach ( [ 'Ansprechpartner' => $c['contact'], 'Telefon' => $c['phone'], 'E-Mail' => $c['mail'], 'Umsatz netto' => fge_cc_eur0( $c['revenue'] ) ] as $k => $v ) {
		$val = esc_html( '' !== $v ? $v : 'nicht hinterlegt' );
		if ( 'Telefon' === $k && '' !== $v ) {
			$val = '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $v ) ) . '">' . esc_html( $v ) . '</a>';
		} elseif ( 'E-Mail' === $k && is_email( $v ) ) {
			$val = '<a href="mailto:' . esc_attr( $v ) . '">' . esc_html( $v ) . '</a>';
		}
		echo '<div><span>' . esc_html( $k ) . '</span><strong>' . $val . '</strong></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</div>';
	echo '<div class="cc-custreqs"><h3>Anfragen</h3>';
	$reqs = $c['requests'];
	usort( $reqs, static fn( $a, $b ) => (int) get_post_time( 'U', false, $b ) <=> (int) get_post_time( 'U', false, $a ) );
	foreach ( $reqs as $req ) {
		$f    = fge_cc_request_fact_data( $req );
		$d    = fge_cc_event_date( $req );
		$acc  = 'accepted' === (string) get_post_meta( $req, '_fge_offer_status', true );
		$flow = fge_cc_flow( $req );
		echo '<a class="cc-custreq" href="' . esc_url( fge_cc_request_url( $req ) ) . '"><span class="cc-mono">' . esc_html( fge_request_number( $req ) ) . '</span>';
		echo '<span>' . esc_html( (string) ( $f['event'] ?? '' ) ) . '</span><span class="cc-tnum">' . esc_html( $d > 0 ? wp_date( 'd.m.Y', $d ) : 'offen' ) . '</span>';
		echo '<span class="cc-num">' . ( $acc ? esc_html( fge_cc_eur0( fge_cc_offer_net( $req ) ) ) : fge_cc_pill( $flow['label'], $flow['tone'] ) ) . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</div>';
	echo '<form class="cc-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="fge_cc_customer_note"><input type="hidden" name="key" value="' . esc_attr( $c['key'] ) . '">';
	wp_nonce_field( 'fge_cc_customer_note' );
	echo '<label class="cc-field cc-field--wide"><span>Interne Notiz</span><textarea name="note" rows="3" placeholder="Nur für dich, der Kunde sieht das nicht">' . esc_textarea( fge_cc_customer_note( $c['key'] ) ) . '</textarea></label>';
	echo '<div class="cc-drawer-foot"><button type="button" class="cc-btn" data-cc-drawer-close>Schließen</button><button type="submit" class="cc-btn">Notiz speichern</button>';
	echo '<a class="cc-btn cc-btn--primary" href="' . esc_url( fge_cc_url( 'anfragen', [ 'tab' => 'alle', 's' => 'Ohne Firmennamen' === $c['name'] ? $c['mail'] : $c['name'] ] ) ) . '">Anfragen ansehen</a></div></form>';
	fge_cc_drawer_close();
}

/** Tab Leads: wie im Entwurf eine Zeile je Lead mit Umwandeln und Status. */
function fge_cc_customers_leads( array $leads, string $search ): void {
	if ( '' !== $search ) {
		$leads = array_filter( $leads, static fn( $l ) => false !== mb_stripos( $l['company'] . ' ' . $l['name'] . ' ' . $l['mail'], $search ) );
	}
	echo '<p class="cc-muted cc-leads-note">Leute, die im Budget-Rechner den Richtwert freigeschaltet haben. Noch keine Anfrage, aber eine warme Adresse.</p>';
	echo '<section class="cc-card cc-leadlist">';
	// Töne wie im Entwurf: neu blau, kontaktiert gelb, verloren grau, umgewandelt grün.
	$tags = [ 'neu' => [ 'Neu', 'brand' ], 'kontaktiert' => [ 'Kontaktiert', 'warn' ], 'verloren' => [ 'Verloren', 'neutral' ], 'umgewandelt' => [ 'Anfrage angelegt', 'good' ] ];
	foreach ( $leads as $l ) {
		$id   = (int) $l['id'];
		$conv = 'umgewandelt' === $l['status'];
		[ $label, $tone ] = $tags[ $l['status'] ] ?? $tags['neu'];
		$wish = implode( ', ', array_filter( [ $l['type'], $l['pax'] > 0 ? $l['pax'] . ' Personen' : '', '' !== $l['budget'] ? 'Budget ' . $l['budget'] : '' ] ) );
		echo '<div class="cc-leadrow"><div class="cc-leadrow-main"><strong>' . esc_html( $l['company'] ) . '</strong><span>' . esc_html( implode( ' · ', array_filter( [ $l['name'], $l['source'], $l['date'] ] ) ) ) . '</span></div>';
		echo '<span class="cc-leadrow-wish">' . esc_html( $wish ) . '</span>';
		echo fge_cc_pill( $label, $tone ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="cc-leadrow-act">';
		if ( $conv ) {
			echo '<a class="cc-btn" href="' . esc_url( fge_cc_request_url( $id ) ) . '">Zur Anfrage</a>';
		} else {
			fge_cc_button( 'fge_cc_lead', $id, 'In Anfrage umwandeln', [ 'fields' => [ 'to' => 'umgewandelt' ] ] );
			echo '<form class="cc-actform" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="fge_cc_lead"><input type="hidden" name="request_id" value="' . $id . '">';
			wp_nonce_field( 'fge_cc_lead_' . $id );
			echo '<select name="to" class="cc-pillselect" data-cc-autosubmit aria-label="Status des Leads">';
			foreach ( [ 'neu' => 'Neu', 'kontaktiert' => 'Kontaktiert', 'verloren' => 'Verloren' ] as $k => $t ) {
				echo '<option value="' . esc_attr( $k ) . '"' . selected( $l['status'], $k, false ) . '>' . esc_html( $t ) . '</option>';
			}
			echo '</select><noscript><button type="submit" class="cc-btn">Setzen</button></noscript></form>';
		}
		echo '</div></div>';
	}
	if ( ! $leads ) {
		echo '<p class="cc-tgroup-empty">Keine Leads.</p>';
	}
	echo '</section>';
}
