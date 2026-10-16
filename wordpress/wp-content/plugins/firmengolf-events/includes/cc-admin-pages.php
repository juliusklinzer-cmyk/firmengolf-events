<?php
/**
 * Control Center: Leads, Partnercodes, Nutzer, Einstellungen (Rechnungen seit
 * 16.10.2026 in cc-finance.php)
 * (Julius, 15.10.2026: „baue das ganze Control Center fertig“, Design folgt je
 * Menüpunkt). Alles auf vorhandenen Daten, Lexoffice bleibt manuell.
 *
 * Neu ist nur _fge_lead_status an Budget-Leads (neu, kontaktiert, umgewandelt,
 * verloren), nach der Liste in docs/control-center-inhalte.md.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function fge_cc_admin_messages(): array {
	return [
		'inv_set'      => [ 'ok', 'Rechnung als gestellt markiert.' ],
		'inv_paid'     => [ 'ok', 'Bezahlt, der Vorgang ist abgeschlossen.' ],
		'inv_reminded' => [ 'ok', 'Zahlungserinnerung ist raus.' ],
		'mail_resent'  => [ 'ok', 'Mail ist erneut raus.' ],
		'mail_resend_fail' => [ 'err', 'Die Mail ging wieder nicht raus. Empfänger im Postausgang prüfen.' ],
		'inv_remind_fail' => [ 'err', 'Die Zahlungserinnerung ging nicht raus. Hat der Kunde eine Mailadresse?' ],
		'lead_set'     => [ 'ok', 'Lead aktualisiert.' ],
		'note_saved'   => [ 'ok', 'Notiz gespeichert.' ],
		'lead_conv'    => [ 'ok', 'Lead ist jetzt eine Anfrage. Weiter mit dem Bedarf.' ],
		'pc_saved'     => [ 'ok', 'Partnercode gespeichert.' ],
		'pc_taken'     => [ 'err', 'Diesen Code gibt es schon, der bisherige bleibt.' ],
		'pc_invalid'   => [ 'err', 'Bitte einen Inhaber angeben.' ],
		'pc_settled'   => [ 'ok', 'Provision als abgerechnet markiert.' ],
		'pc_none'      => [ 'ok', 'Keine offene Provision zum Abrechnen.' ],
		'user_reset'   => [ 'ok', 'Der Link zum Setzen des Passworts ist per Mail raus.' ],
		'user_mail'    => [ 'ok', 'Mailadresse geändert. Die alte Adresse bekommt einen Hinweis.' ],
		'user_created' => [ 'ok', 'Zugang angelegt. Die Einladung mit Passwort-Link ist raus.' ],
		'user_invalid' => [ 'err', 'Die Mailadresse ist ungültig oder schon vergeben.' ],
		'user_failed'  => [ 'err', 'Das hat nicht geklappt, bitte im WordPress prüfen.' ],
		'user_admin_mail' => [ 'err', 'Die Mailadresse eines anderen Admins änderst du im WordPress, dort mit Bestätigung an die neue Adresse.' ],
	];
}

/** Eine Zahl als Euro, ohne Nachkommastellen wenn rund. */
function fge_cc_eur( float $v ): string {
	return function_exists( 'fge_cc_money' ) ? fge_cc_money( $v ) : number_format_i18n( $v, 2 ) . ' €';
}

// ── Leads ────────────────────────────────────────────────────────────────────

function fge_cc_lead_statuses(): array {
	return [
		'neu'         => [ 'Neu', 'warn' ],
		'kontaktiert' => [ 'Kontaktiert', 'brand' ],
		'umgewandelt' => [ 'In Anfrage umgewandelt', 'good' ],
		'verloren'    => [ 'Verloren', 'bad' ],
	];
}

/** IDs aller Budget-Leads, die noch keine Anfrage sind. Die Anfragenliste blendet sie aus. */
function fge_cc_open_lead_ids(): array {
	static $ids = null;
	if ( null !== $ids ) {
		return $ids;
	}
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$ids = array_map( 'intval', $wpdb->get_col(
		"SELECT p.ID FROM {$wpdb->posts} p
		 JOIN {$wpdb->postmeta} t ON t.post_id = p.ID AND t.meta_key = '_fge_request_type' AND t.meta_value = 'budget_lead'
		 WHERE p.post_type = 'firmengolf_request' AND p.post_status IN ('publish','draft')"
	) );
	return $ids;
}

add_action( 'admin_post_fge_cc_lead', static function (): void {
	$req = fge_cc_guard( 'fge_cc_lead' );
	$to  = sanitize_key( wp_unslash( $_POST['to'] ?? '' ) );
	if ( 'budget_lead' !== (string) get_post_meta( $req, '_fge_request_type', true ) || ! isset( fge_cc_lead_statuses()[ $to ] ) ) {
		wp_safe_redirect( fge_cc_url( 'kunden', [ 'ansicht' => 'leads', 'msg' => 'nothing' ] ) );
		exit;
	}
	if ( 'umgewandelt' === $to ) {
		// Aus dem Lead wird eine normale Anfrage, Bedarf und Ablauf wie bei jeder anderen.
		update_post_meta( $req, '_fge_request_type', 'general_event_request' );
		update_post_meta( $req, '_fge_lead_status', 'umgewandelt' );
		fge_activity_add( $req, 'status', 'Budget-Lead in eine Anfrage umgewandelt' );
		fge_cc_redirect( $req, 'lead_conv' );
	}
	update_post_meta( $req, '_fge_lead_status', $to );
	fge_activity_add( $req, 'note', 'Lead: ' . fge_cc_lead_statuses()[ $to ][0] );
	$back = sanitize_key( wp_unslash( $_POST['back'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	wp_safe_redirect( 'heute' === $back ? fge_cc_url( 'dashboard', [ 'msg' => 'lead_set' ] ) : fge_cc_url( 'kunden', [ 'ansicht' => 'leads', 'msg' => 'lead_set' ] ) );
	exit;
} );

/**
 * Alle Leads als Zeilen für Kunden und Heute, neueste zuerst. Leads sind
 * Budget-Rechner-Anfragen (_fge_request_type budget_lead) und umgewandelte.
 */
function fge_cc_lead_rows(): array {
	static $rows = null;
	if ( null !== $rows ) {
		return $rows;
	}
	// Direkt per SQL: eine OR-Metaabfrage würde vom Demo-Filter (demo-portal.php)
	// um eine weitere ODER-Bedingung ergänzt und zeigte dann alle Anfragen (Audit).
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$ids = array_map( 'intval', $wpdb->get_col(
		"SELECT DISTINCT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
		 WHERE p.post_type = 'firmengolf_request' AND p.post_status IN ('publish','draft')
		 AND ( ( m.meta_key = '_fge_request_type' AND m.meta_value = 'budget_lead' ) OR m.meta_key = '_fge_lead_status' )
		 ORDER BY p.post_date DESC LIMIT 500"
	) );
	$rows = [];
	foreach ( $ids as $id ) {
		if ( function_exists( 'fge_is_demo_request' ) && fge_is_demo_request( $id ) ) {
			continue;
		}
		$bc    = (array) get_post_meta( $id, '_fge_budget_calc', true );
		$mail  = (string) get_post_meta( $id, '_fge_contact_email', true );
		$name  = trim( (string) get_post_meta( $id, '_fge_contact_first_name', true ) . ' ' . (string) get_post_meta( $id, '_fge_contact_last_name', true ) );
		$pax   = ! empty( $bc['participants'] ) ? (int) $bc['participants'] : (int) get_post_meta( $id, '_fge_expected_participants', true );
		$rows[] = [
			'id'      => $id,
			'status'  => (string) get_post_meta( $id, '_fge_lead_status', true ) ?: 'neu',
			'source'  => 'Budgetrechner',
			'company' => (string) get_post_meta( $id, '_fge_company_name', true ) ?: $mail,
			'type'    => (string) ( $bc['type'] ?? get_post_meta( $id, '_fge_occasion', true ) ),
			'name'    => $name,
			'mail'    => $mail,
			'pax'     => $pax,
			'budget'  => ! empty( $bc['total'] ) ? fge_cc_eur( (float) $bc['total'] ) : (string) ( $bc['range'] ?? '' ),
			'what'    => (string) ( $bc['services'] ?? '' ),
			'date'    => get_the_date( 'd.m.Y', $id ),
		];
	}
	return $rows;
}

// ── Partnercodes ─────────────────────────────────────────────────────────────

/** Rechte und Nonce für Partnercode-Aktionen, liefert die Code-ID (0 = neu). */
function fge_cc_pc_guard( string $action ): int {
	$id = absint( $_POST['code_id'] ?? 0 );
	if ( ! fge_cc_can() ) {
		wp_die( 'Keine Berechtigung.', '', [ 'response' => 403 ] );
	}
	check_admin_referer( $action . '_' . $id );
	return $id;
}

add_action( 'admin_post_fge_cc_pc_save', static function (): void {
	$id = fge_cc_pc_guard( 'fge_cc_pc_save' );
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- fge_cc_pc_guard prüft die Nonce.
	$title = trim( sanitize_text_field( wp_unslash( $_POST['holder'] ?? '' ) ) );
	if ( '' === $title ) {
		wp_safe_redirect( fge_cc_url( 'partnercodes', [ 'msg' => 'pc_invalid' ] ) );
		exit;
	}
	if ( $id > 0 && FGE_PC_POST_TYPE === get_post_type( $id ) ) {
		wp_update_post( [ 'ID' => $id, 'post_title' => $title ] );
	} else {
		$id = (int) wp_insert_post( [ 'post_type' => FGE_PC_POST_TYPE, 'post_status' => 'publish', 'post_title' => $title ] );
	}
	// Gleiche Regeln wie die Metabox im WordPress: Code normalisieren, eindeutig halten.
	$old   = (string) get_post_meta( $id, '_fge_pc_code', true );
	$code  = fge_pc_normalize( (string) wp_unslash( $_POST['code'] ?? '' ) ) ?: ( $old ?: fge_pc_suggest_code( $title ) );
	$other = fge_pc_find( $code );
	$taken = $other > 0 && $other !== $id;
	if ( $taken ) {
		$code = $old ?: fge_pc_suggest_code( $title . wp_rand( 10, 99 ) );
	}
	update_post_meta( $id, '_fge_pc_code', $code );
	update_post_meta( $id, '_fge_pc_contact_name', sanitize_text_field( wp_unslash( $_POST['contact_name'] ?? '' ) ) );
	update_post_meta( $id, '_fge_pc_contact_email', sanitize_email( wp_unslash( $_POST['contact_email'] ?? '' ) ) );
	update_post_meta( $id, '_fge_pc_discount_percent', max( 0, min( 100, (int) ( $_POST['percent'] ?? 5 ) ) ) );
	update_post_meta( $id, '_fge_pc_commission_eur', function_exists( 'fge_parse_de_amount' ) ? max( 0.0, fge_parse_de_amount( wp_unslash( $_POST['eur'] ?? '' ) ) ) : (float) ( $_POST['eur'] ?? 0 ) );
	update_post_meta( $id, '_fge_pc_status', 'pausiert' === ( $_POST['status'] ?? '' ) ? 'pausiert' : 'aktiv' );
	$until = sanitize_text_field( wp_unslash( $_POST['until'] ?? '' ) );
	update_post_meta( $id, '_fge_pc_valid_until', preg_match( '/^\d{4}-\d{2}-\d{2}$/', $until ) ? $until : '' );
	update_post_meta( $id, '_fge_pc_note', sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) ) );
	// phpcs:enable
	wp_safe_redirect( fge_cc_url( 'partnercodes', [ 'code' => $id, 'msg' => $taken ? 'pc_taken' : 'pc_saved' ] ) );
	exit;
} );

add_action( 'admin_post_fge_cc_pc_settle', static function (): void {
	$id  = fge_cc_pc_guard( 'fge_cc_pc_settle' );
	$req = absint( $_POST['req'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$n   = 0;
	foreach ( $req > 0 ? [ $req ] : fge_pc_requests( $id ) as $r ) {
		if ( (int) get_post_meta( $r, '_fge_partnercode_id', true ) === $id ) {
			$n += fge_pc_settle_request( (int) $r ) ? 1 : 0;
		}
	}
	wp_safe_redirect( fge_cc_url( 'partnercodes', [ 'code' => $id, 'msg' => $n ? 'pc_settled' : 'pc_none' ] ) );
	exit;
} );

function fge_cc_pc_form_open( string $action, int $id ): void {
	echo '<form class="cc-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( $action ) . '"><input type="hidden" name="code_id" value="' . (int) $id . '">';
	wp_nonce_field( $action . '_' . $id );
}

function fge_cc_pc_drawer( int $id, bool $auto ): void {
	$m = static fn( string $k ): string => $id > 0 ? (string) get_post_meta( $id, '_fge_pc_' . $k, true ) : '';
	fge_cc_drawer_open( 0 === $id ? 'cc-pc-new' : 'cc-pc-' . $id, 0 === $id ? 'Partnercode anlegen' : 'Partnercode bearbeiten', 'Rabatt für den Kunden, Provision für den Multiplikator', $auto );
	fge_cc_pc_form_open( 'fge_cc_pc_save', $id );
	echo '<label class="cc-field cc-field--wide"><span>Inhaber</span><input type="text" name="holder" value="' . esc_attr( $id > 0 ? get_the_title( $id ) : '' ) . '" placeholder="z. B. Deutscher Golf Verband" required></label>';
	echo '<div class="cc-fields cc-fields--2">';
	echo '<label class="cc-field"><span>Code</span><input type="text" name="code" value="' . esc_attr( $m( 'code' ) ) . '" maxlength="12" placeholder="wird vorgeschlagen"></label>';
	echo '<label class="cc-field"><span>Status</span><select name="status"><option value="aktiv">Aktiv</option><option value="pausiert"' . selected( $m( 'status' ), 'pausiert', false ) . '>Pausiert</option></select></label>';
	echo '<label class="cc-field"><span>Rabatt in %</span><input type="number" name="percent" min="0" max="100" value="' . esc_attr( '' === $m( 'discount_percent' ) ? '5' : $m( 'discount_percent' ) ) . '"></label>';
	echo '<label class="cc-field"><span>Provision je Buchung, € netto</span><input type="text" name="eur" value="' . esc_attr( '' !== $m( 'commission_eur' ) ? number_format( (float) $m( 'commission_eur' ), 2, ',', '.' ) : '' ) . '" placeholder="z. B. 50,00"></label>';
	echo '<label class="cc-field"><span>Ansprechpartner</span><input type="text" name="contact_name" value="' . esc_attr( $m( 'contact_name' ) ) . '"></label>';
	echo '<label class="cc-field"><span>E-Mail</span><input type="email" name="contact_email" value="' . esc_attr( $m( 'contact_email' ) ) . '"></label>';
	echo '<label class="cc-field"><span>Gültig bis</span><input type="date" name="until" value="' . esc_attr( $m( 'valid_until' ) ) . '"></label>';
	echo '</div>';
	echo '<label class="cc-field cc-field--wide"><span>Notiz</span><textarea name="note" rows="3">' . esc_textarea( $m( 'note' ) ) . '</textarea></label>';
	echo '<div class="cc-drawer-foot"><button type="button" class="cc-btn" data-cc-drawer-close>Abbrechen</button><button type="submit" class="cc-btn cc-btn--primary">Speichern</button></div></form>';
	fge_cc_drawer_close();
}

/**
 * Kennzahlen eines Codes für Liste und Detail: Anfragen, Buchungen, Umsatz,
 * gewährter Rabatt, Buchungen je Monat und die letzten Buchungen.
 */
function fge_cc_pc_data( int $id ): array {
	$reqs  = fge_pc_requests( $id );
	$book  = [];
	$rev   = 0.0;
	$given = 0.0;
	foreach ( $reqs as $r ) {
		if ( 'accepted' !== (string) get_post_meta( $r, '_fge_offer_status', true ) ) {
			continue;
		}
		$snap = (array) get_post_meta( $r, '_fge_offer_snapshot', true );
		$tot  = function_exists( 'fge_offer_totals' ) ? fge_offer_totals( $snap, function_exists( 'fge_offer_selected_extras' ) ? fge_offer_selected_extras( $r ) : [] ) : [ 'net' => 0, 'discount' => 0 ];
		$at   = (int) strtotime( (string) get_post_meta( $r, '_fge_offer_accepted_at', true ) ) ?: (int) get_post_time( 'U', false, $r );
		$rev   += (float) ( $tot['net'] ?? 0 );
		$given += (float) ( $tot['discount'] ?? 0 );
		$book[] = [ 'req' => $r, 'at' => $at, 'company' => (string) get_post_meta( $r, '_fge_company_name', true ), 'net' => (float) ( $tot['net'] ?? 0 ) ];
	}
	usort( $book, static fn( $a, $b ) => $b['at'] <=> $a['at'] );
	$st = (string) get_post_meta( $id, '_fge_pc_status', true );
	return [
		'id'       => $id,
		'code'     => (string) get_post_meta( $id, '_fge_pc_code', true ),
		'owner'    => get_the_title( $id ),
		'contact'  => (string) get_post_meta( $id, '_fge_pc_contact_name', true ),
		'percent'  => fge_pc_percent( $id ),
		'usable'   => fge_pc_is_usable( $id ),
		'status'   => fge_pc_is_usable( $id ) ? 'aktiv' : ( 'pausiert' === $st ? 'pausiert' : 'abgelaufen' ),
		'requests' => count( $reqs ),
		'uses'     => count( $book ),
		'revenue'  => $rev,
		'given'    => $given,
		'book'     => $book,
		'created'  => (int) get_post_time( 'U', false, $id ),
		'stats'    => fge_pc_stats( $id ),
	];
}

// Code pausieren oder wieder aktivieren, aus dem Detail.
add_action( 'admin_post_fge_cc_pc_status', static function (): void {
	$id = fge_cc_pc_guard( 'fge_cc_pc_status' );
	if ( FGE_PC_POST_TYPE === get_post_type( $id ) ) {
		update_post_meta( $id, '_fge_pc_status', 'pausiert' === ( $_POST['status'] ?? '' ) ? 'pausiert' : 'aktiv' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}
	wp_safe_redirect( fge_cc_url( 'partnercodes', [ 'code' => $id, 'msg' => 'pc_saved' ] ) );
	exit;
} );

function fge_cc_page_partnercodes(): void {
	fge_cc_message();
	if ( ! defined( 'FGE_PC_POST_TYPE' ) ) {
		fge_cc_empty( 'Partnercodes sind nicht aktiv.' );
		return;
	}
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$open = absint( $_GET['code'] ?? 0 );
	$q    = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
	$sort = sanitize_key( wp_unslash( $_GET['sort'] ?? 'uses' ) );
	$dir  = 'asc' === ( $_GET['dir'] ?? '' ) ? 'asc' : 'desc';
	$edit = isset( $_GET['bearbeiten'] );
	// phpcs:enable
	$ids  = get_posts( [ 'post_type' => FGE_PC_POST_TYPE, 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ] );
	$all  = array_map( static fn( $id ) => fge_cc_pc_data( (int) $id ), $ids );
	$act  = array_filter( $all, static fn( $c ) => $c['usable'] );

	echo '<p class="cc-top-sub cc-sub-under">' . esc_html( count( $act ) . ' aktive Codes · ' . array_sum( array_column( $all, 'uses' ) ) . ' Buchungen · ' . fge_cc_eur0( array_sum( array_column( $all, 'revenue' ) ) ) . ' Umsatz netto. Klick auf einen Code zeigt die Details.' ) . '</p>';
	if ( '' !== $q ) {
		$all = array_filter( $all, static fn( $c ) => false !== mb_stripos( $c['code'] . ' ' . $c['owner'] . ' ' . $c['contact'], $q ) );
	}
	$val = static fn( array $c ) => match ( $sort ) {
		'code'     => mb_strtolower( $c['code'] ),
		'owner'    => mb_strtolower( $c['owner'] ),
		'discount' => $c['percent'],
		'reqs'     => $c['requests'],
		'revenue'  => $c['revenue'],
		'status'   => $c['status'],
		default    => $c['uses'],
	};
	usort( $all, static function ( $a, $b ) use ( $val, $dir ) {
		$x = $val( $a );
		$y = $val( $b );
		$r = is_string( $x ) ? strcoll( $x, $y ) : $x <=> $y;
		return 'asc' === $dir ? $r : -$r;
	} );

	$sel  = null;
	foreach ( $all as $c ) {
		if ( $c['id'] === $open ) {
			$sel = $c;
		}
	}
	$keep = array_filter( [ 's' => $q ] );
	echo '<div class="cc-pcgrid' . ( $sel ? ' has-sel' : '' ) . '">';
	echo '<section class="cc-tablewrap"><table class="cc-table cc-pctable"><thead><tr>';
	fge_cc_sort_th( 'partnercodes', 'code', 'Code', $sort, $dir, $keep );
	fge_cc_sort_th( 'partnercodes', 'owner', 'Inhaber', $sort, $dir, $keep );
	fge_cc_sort_th( 'partnercodes', 'discount', 'Rabatt', $sort, $dir, $keep, true );
	fge_cc_sort_th( 'partnercodes', 'reqs', 'Anfragen', $sort, $dir, $keep, true );
	fge_cc_sort_th( 'partnercodes', 'uses', 'Genutzt', $sort, $dir, $keep, true );
	fge_cc_sort_th( 'partnercodes', 'revenue', 'Umsatz netto', $sort, $dir, $keep, true );
	fge_cc_sort_th( 'partnercodes', 'status', 'Status', $sort, $dir, $keep );
	echo '</tr></thead><tbody>';
	foreach ( $all as $c ) {
		$on  = $c['id'] === $open;
		$url = fge_cc_url( 'partnercodes', array_filter( [ 'code' => $on ? 0 : $c['id'], 's' => $q, 'sort' => $sort, 'dir' => $dir ] ) );
		echo '<tr class="' . ( $on ? 'is-on' : '' ) . '" onclick="location.href=\'' . esc_js( $url ) . '\'"><td class="cc-mono"><a href="' . esc_url( $url ) . '">' . esc_html( $c['code'] ) . '</a></td>';
		echo '<td><span class="cc-pcowner"><strong>' . esc_html( $c['owner'] ) . '</strong>' . ( '' !== $c['contact'] ? '<span>' . esc_html( $c['contact'] ) . '</span>' : '' ) . '</span></td>';
		echo '<td class="cc-num">' . (int) $c['percent'] . ' %</td><td class="cc-num">' . (int) $c['requests'] . '</td><td class="cc-num">' . (int) $c['uses'] . '</td>';
		echo '<td class="cc-num">' . esc_html( fge_cc_eur0( $c['revenue'] ) ) . '</td>';
		echo '<td>' . fge_cc_pill( [ 'aktiv' => 'Aktiv', 'pausiert' => 'Pausiert', 'abgelaufen' => 'Abgelaufen' ][ $c['status'] ], 'aktiv' === $c['status'] ? 'good' : 'neutral' ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	if ( ! $all ) {
		echo '<tr class="is-empty"><td colspan="7">' . ( '' !== $q ? 'Kein Code gefunden.' : 'Noch keine Partnercodes.' ) . '</td></tr>';
	}
	echo '</tbody></table></section>';

	if ( $sel ) {
		fge_cc_pc_detail( $sel, $q, $sort, $dir );
	}
	echo '</div>';
	if ( $sel ) {
		fge_cc_pc_drawer( $sel['id'], $edit );
	}
	fge_cc_pc_drawer( 0, false );
}

/** Detailfeld eines Codes rechts neben der Tabelle. */
function fge_cc_pc_detail( array $c, string $q, string $sort, string $dir ): void {
	$on    = $c['usable'];
	$book  = $c['book'];
	$last  = $book ? $book[0]['at'] : time();
	$mons  = [];
	for ( $i = 5; $i >= 0; $i-- ) {
		$m      = strtotime( wp_date( 'Y-m-01', $last ) . " -$i months" );
		$key    = wp_date( 'Y-m', $m );
		$mons[] = [ fge_cc_mon( $m ), count( array_filter( $book, static fn( $b ) => wp_date( 'Y-m', $b['at'] ) === $key ) ) ];
	}
	$max   = max( 1, ...array_column( $mons, 1 ) );
	$close = fge_cc_url( 'partnercodes', array_filter( [ 's' => $q, 'sort' => $sort, 'dir' => $dir ] ) );
	echo '<aside class="cc-card cc-pcdetail">';
	echo '<div class="cc-pcdetail-head"><div><div class="cc-pcdetail-code"><span class="cc-mono">' . esc_html( $c['code'] ) . '</span>' . fge_cc_pill( $on ? 'Aktiv' : ( 'pausiert' === $c['status'] ? 'Pausiert' : 'Abgelaufen' ), $on ? 'good' : 'neutral' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<span class="cc-muted">' . esc_html( implode( ' · ', array_filter( [ $c['owner'], $c['contact'] ] ) ) ) . '</span></div>';
	echo '<a class="cc-tdetail-x" href="' . esc_url( $close ) . '">Schließen</a></div>';

	echo '<div class="cc-pckpis">';
	foreach ( [
		'Anfragen mit Code' => (string) $c['requests'],
		'Gebucht'           => (string) $c['uses'],
		'Quote'             => $c['requests'] ? round( $c['uses'] / $c['requests'] * 100 ) . ' %' : 'keine',
		'Umsatz netto'      => fge_cc_eur0( $c['revenue'] ),
		'Ø pro Buchung'     => $c['uses'] ? fge_cc_eur0( $c['revenue'] / $c['uses'] ) : 'keine',
		'Rabatt gewährt'    => fge_cc_eur0( $c['given'] ),
	] as $k => $v ) {
		echo '<div><span>' . esc_html( $k ) . '</span><strong>' . esc_html( $v ) . '</strong></div>';
	}
	echo '</div>';

	echo '<div class="cc-pcbars-wrap"><span class="cc-pcsub">Buchungen pro Monat</span><div class="cc-pcbars">';
	foreach ( $mons as [ $m, $n ] ) {
		echo '<div class="cc-pcbar"><span>' . (int) $n . '</span><div style="height:' . esc_attr( $n ? max( $n / $max * 100, 8 ) : 2 ) . '%"></div></div>';
	}
	echo '</div><div class="cc-pcbars-x">';
	foreach ( $mons as [ $m ] ) {
		echo '<span>' . esc_html( $m ) . '</span>';
	}
	echo '</div></div>';

	echo '<div class="cc-pcbook"><span class="cc-pcsub">Letzte Buchungen</span>';
	foreach ( array_slice( $book, 0, 6 ) as $b ) {
		echo '<a href="' . esc_url( fge_cc_request_url( $b['req'] ) ) . '"><span class="cc-tnum">' . esc_html( wp_date( 'd.m.Y', $b['at'] ) ) . '</span><span>' . esc_html( $b['company'] ) . '</span><span class="cc-tnum">' . esc_html( fge_cc_eur0( $b['net'] ) ) . '</span></a>';
	}
	if ( ! $book ) {
		echo '<p class="cc-muted">Noch nicht genutzt.</p>';
	}
	echo '</div>';

	$s = $c['stats'];
	echo '<dl class="cc-tfacts">';
	foreach ( [
		'Rabatt'          => $c['percent'] . ' %',
		'Provision'       => fge_cc_eur( (float) get_post_meta( $c['id'], '_fge_pc_commission_eur', true ) ) . ' je Buchung',
		'Provision offen' => fge_cc_eur( $s['open'] ) . ( $s['settled'] > 0 ? ', abgerechnet ' . fge_cc_eur( $s['settled'] ) : '' ),
		'Gültig bis'      => '' !== ( $until = (string) get_post_meta( $c['id'], '_fge_pc_valid_until', true ) ) ? wp_date( 'd.m.Y', fge_cc_local_ts( $until ) ) . ( 'abgelaufen' === $c['status'] ? ', abgelaufen' : '' ) : 'unbegrenzt',
		'Angelegt am'     => $c['created'] > 0 ? wp_date( 'd.m.Y', $c['created'] ) : '',
		'Zuletzt genutzt' => $book ? wp_date( 'd.m.Y', $book[0]['at'] ) : 'noch nie',
	] as $k => $v ) {
		echo '<div><dt>' . esc_html( $k ) . '</dt><dd>' . esc_html( $v ) . '</dd></div>';
	}
	echo '</dl>';

	echo '<div class="cc-tdetail-act">';
	echo '<button type="button" class="cc-btn cc-btn--primary" data-cc-copy="' . esc_attr( $c['code'] ) . '">Code kopieren</button>';
	if ( 'abgelaufen' !== $c['status'] ) {
		fge_cc_pc_form_open( 'fge_cc_pc_status', $c['id'] );
		echo '<input type="hidden" name="status" value="' . ( $on ? 'pausiert' : 'aktiv' ) . '"><button type="submit" class="cc-btn">' . ( $on ? 'Code pausieren' : 'Code aktivieren' ) . '</button></form>';
	}
	echo '<button type="button" class="cc-btn cc-btn--text" data-cc-drawer-open="cc-pc-' . (int) $c['id'] . '">Bearbeiten</button>';
	if ( $s['open'] > 0 ) {
		fge_cc_pc_form_open( 'fge_cc_pc_settle', $c['id'] );
		echo '<button type="submit" class="cc-btn cc-btn--text" onclick="return confirm(\'Alle offenen Provisionen als abgerechnet markieren?\')">Provision abrechnen</button></form>';
	}
	echo '</div></aside>';
}

// ── Nutzer ───────────────────────────────────────────────────────────────────

add_action( 'admin_post_fge_cc_user', static function (): void {
	$uid = absint( $_POST['user_id'] ?? 0 );
	if ( ! fge_cc_can() ) {
		wp_die( 'Keine Berechtigung.', '', [ 'response' => 403 ] );
	}
	check_admin_referer( 'fge_cc_user_' . $uid );
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce oben geprüft.
	$do   = sanitize_key( wp_unslash( $_POST['do'] ?? '' ) );
	$back = static function ( string $msg ): void {
		wp_safe_redirect( fge_cc_url( 'einstellungen', [ 'bereich' => 'nutzer', 'msg' => $msg ] ) );
		exit;
	};
	// Nutzerverwaltung braucht dieselben Rechte wie im WordPress (Audit 15.10.2026).
	if ( ( 'create' === $do && ! ( current_user_can( 'create_users' ) && current_user_can( 'promote_users' ) ) )
		|| ( 'create' !== $do && ! current_user_can( 'edit_user', $uid ) ) ) {
		wp_die( 'Keine Berechtigung.', '', [ 'response' => 403 ] );
	}
	// Mailadresse anderer Admins nur über das WordPress mit Bestätigung, sonst
	// reicht eine gekaperte Sitzung, um einen Admin-Zugang zu übernehmen.
	if ( 'mail' === $do && $uid !== get_current_user_id() && user_can( $uid, 'manage_options' ) ) {
		$back( 'user_admin_mail' );
	}
	if ( 'create' === $do ) {
		$mail = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$name = trim( sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ) );
		if ( ! is_email( $mail ) || email_exists( $mail ) ) {
			$back( 'user_invalid' );
		}
		$parts = explode( ' ', $name, 2 );
		$new   = wp_insert_user( [
			'user_login'   => sanitize_user( strtolower( strtok( $mail, '@' ) ) . '-' . wp_rand( 100, 999 ), true ),
			'user_email'   => $mail,
			'user_pass'    => wp_generate_password( 24 ),
			'first_name'   => $parts[0] ?? '',
			'last_name'    => $parts[1] ?? '',
			'display_name' => '' !== $name ? $name : $mail,
			'role'         => 'administrator',
		] );
		if ( is_wp_error( $new ) ) {
			$back( 'user_failed' );
		}
		wp_new_user_notification( (int) $new, null, 'user' );
		$back( 'user_created' );
	}
	$user = get_userdata( $uid );
	if ( ! $user ) {
		$back( 'user_failed' );
	}
	if ( 'reset' === $do ) {
		$back( true === retrieve_password( $user->user_login ) ? 'user_reset' : 'user_failed' );
	}
	if ( 'mail' === $do ) {
		$mail = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		if ( ! is_email( $mail ) || ( email_exists( $mail ) && email_exists( $mail ) !== $uid ) ) {
			$back( 'user_invalid' );
		}
		$res = wp_update_user( [ 'ID' => $uid, 'user_email' => $mail ] );
		$back( is_wp_error( $res ) ? 'user_failed' : 'user_mail' );
	}
	// phpcs:enable
	$back( 'nothing' );
} );

function fge_cc_user_form_open( int $uid, string $do ): void {
	echo '<form class="cc-actform cc-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="fge_cc_user"><input type="hidden" name="user_id" value="' . (int) $uid . '"><input type="hidden" name="do" value="' . esc_attr( $do ) . '">';
	wp_nonce_field( 'fge_cc_user_' . $uid );
}

function fge_cc_page_users(): void {
	fge_cc_message();
	$tab   = sanitize_key( wp_unslash( $_GET['tab'] ?? 'team' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$tabs  = [ 'team' => 'Firmengolf', 'partner' => 'Partner-Zugänge', 'alle' => 'Alle' ];
	$tab   = isset( $tabs[ $tab ] ) ? $tab : 'team';
	$all   = get_users( [ 'orderby' => 'registered', 'order' => 'DESC', 'number' => 500 ] );
	$is_t  = static fn( WP_User $u ) => in_array( 'administrator', (array) $u->roles, true );
	$is_p  = static fn( WP_User $u ) => in_array( 'firmengolf_partner', (array) $u->roles, true );
	$counts = [ 'team' => count( array_filter( $all, $is_t ) ), 'partner' => count( array_filter( $all, $is_p ) ), 'alle' => count( $all ) ];
	$rows   = array_filter( $all, 'team' === $tab ? $is_t : ( 'partner' === $tab ? $is_p : '__return_true' ) );

	echo '<p class="cc-top-sub cc-sub-under">Wer ins Control Center und ins Partnerportal kommt. Passwörter setzt jeder selbst über den Link per Mail.</p>';
	echo '<div class="cc-toolbar"><button type="button" class="cc-btn cc-btn--primary" data-cc-drawer-open="cc-user-new">Zugang für das Team anlegen</button></div>';
	fge_cc_seglinks( 'nutzer', 'tab', $tabs, $tab, $counts, [] );
	echo '<section class="cc-tablewrap"><table class="cc-table"><thead><tr><th>Name</th><th>E-Mail</th><th>Rolle</th><th>Verknüpft mit</th><th>Seit</th><th></th></tr></thead><tbody>';
	foreach ( $rows as $u ) {
		$pid = 0;
		if ( $is_p( $u ) ) {
			$q   = get_posts( [ 'post_type' => 'firmengolf_partner', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_fge_assigned_wp_user_id', 'meta_value' => $u->ID ] ); // phpcs:ignore WordPress.DB.SlowDBQuery
			$pid = $q ? (int) $q[0] : 0;
		}
		echo '<tr><td><strong>' . esc_html( $u->display_name ) . '</strong><span class="cc-td-sub">' . esc_html( $u->user_login ) . '</span></td>';
		echo '<td><a href="mailto:' . esc_attr( $u->user_email ) . '">' . esc_html( $u->user_email ) . '</a></td>';
		echo '<td>' . esc_html( $is_t( $u ) ? 'Control Center' : ( $is_p( $u ) ? 'Partnerportal' : implode( ', ', (array) $u->roles ) ) ) . '</td>';
		echo '<td>' . ( $pid > 0 ? '<a href="' . esc_url( fge_cc_url( 'partner', [ 'pid' => $pid ] ) ) . '">' . esc_html( function_exists( 'fge_cc_partner_name' ) ? fge_cc_partner_name( $pid ) : get_the_title( $pid ) ) . '</a>' : '' ) . '</td>';
		echo '<td class="cc-tnum">' . esc_html( wp_date( 'd.m.Y', strtotime( $u->user_registered ) ) ) . '</td><td class="cc-td-act">';
		fge_cc_user_form_open( $u->ID, 'reset' );
		echo '<button type="submit" class="cc-btn cc-btn--tiny" onclick="return confirm(' . esc_attr( wp_json_encode( 'Link zum Setzen des Passworts an ' . $u->user_email . ' senden?' ) ) . ')">Passwort-Link senden</button></form>';
		echo '<button type="button" class="cc-btn cc-btn--text" data-cc-drawer-open="cc-user-' . (int) $u->ID . '">Mail ändern</button>';
		echo '</td></tr>';
	}
	if ( ! $rows ) {
		echo '<tr class="is-empty"><td colspan="6">Keine Nutzer in dieser Ansicht.</td></tr>';
	}
	echo '</tbody></table></section>';

	foreach ( $rows as $u ) {
		fge_cc_drawer_open( 'cc-user-' . $u->ID, 'Mailadresse ändern', $u->display_name );
		fge_cc_user_form_open( $u->ID, 'mail' );
		echo '<label class="cc-field cc-field--wide"><span>Neue Mailadresse</span><input type="email" name="email" value="' . esc_attr( $u->user_email ) . '" required></label>';
		echo '<p class="cc-muted">Die alte Adresse bekommt von WordPress einen Hinweis über die Änderung.</p>';
		echo '<div class="cc-drawer-foot"><button type="button" class="cc-btn" data-cc-drawer-close>Abbrechen</button><button type="submit" class="cc-btn cc-btn--primary">Ändern</button></div></form>';
		fge_cc_drawer_close();
	}
	fge_cc_drawer_open( 'cc-user-new', 'Zugang für das Team anlegen', 'Volle Rechte im Control Center und im WordPress' );
	fge_cc_user_form_open( 0, 'create' );
	echo '<label class="cc-field cc-field--wide"><span>Name</span><input type="text" name="name"></label>';
	echo '<label class="cc-field cc-field--wide"><span>E-Mail</span><input type="email" name="email" required></label>';
	echo '<p class="cc-muted">Die Person bekommt eine Mail mit einem Link, über den sie ihr Passwort selbst setzt. Partner-Zugänge entstehen weiter über die Anmeldung als Partner.</p>';
	echo '<div class="cc-drawer-foot"><button type="button" class="cc-btn" data-cc-drawer-close>Abbrechen</button><button type="submit" class="cc-btn cc-btn--primary">Anlegen und einladen</button></div></form>';
	fge_cc_drawer_close();
}

// ── Einstellungen ────────────────────────────────────────────────────────────

function fge_cc_page_settings(): void {
	$co = function_exists( 'fge_company' ) ? fge_company() : [];
	echo '<p class="cc-top-sub cc-sub-under">Diese Werte stehen fest im Plugin. Für eine Änderung kurz Bescheid geben, dann gehen sie mit dem nächsten Update raus.</p>';
	echo '<div class="cc-cols cc-cols--even">';

	echo '<section class="cc-card"><h2 class="cc-h-small">Firma</h2><dl class="cc-facts">';
	foreach ( [
		'Firma'             => $co['legal_name'] ?? '',
		'Marke'             => $co['brand'] ?? '',
		'Geschäftsführer'   => $co['managing_director'] ?? '',
		'Sitz'              => trim( ( $co['hq_street'] ?? '' ) . ', ' . ( $co['hq_zip'] ?? '' ) . ' ' . ( $co['hq_city'] ?? '' ), ', ' ),
		'Büro'              => trim( ( $co['office_name'] ?? '' ) . ', ' . ( $co['office_street'] ?? '' ) . ', ' . ( $co['office_zip'] ?? '' ) . ' ' . ( $co['office_city'] ?? '' ), ', ' ),
		'Register'          => trim( ( $co['register_court'] ?? '' ) . ' ' . ( $co['register_no'] ?? '' ) ),
		'USt-IdNr.'         => $co['ust_id'] ?? '',
		'Steuernummer'      => $co['tax_no'] ?? '',
		'Telefon'           => $co['phone_display'] ?? '',
		'WhatsApp'          => $co['whatsapp_display'] ?? '',
	] as $k => $v ) {
		fge_cc_fact( $k, (string) $v );
	}
	echo '</dl></section>';

	echo '<section class="cc-card"><h2 class="cc-h-small">Mails</h2><dl class="cc-facts">';
	fge_cc_fact( 'Absender', ( $co['brand'] ?? '' ) . ' <' . ( $co['email_events'] ?? '' ) . '>' );
	fge_cc_fact( 'Interne Mails an', function_exists( 'fge_company_internal_email' ) ? fge_company_internal_email() : '' );
	fge_cc_fact( 'Kopie Angebote an', $co['email_owner'] ?? '' );
	fge_cc_fact( 'Partner-Postfach', $co['email_partner'] ?? '' );
	fge_cc_fact( 'Allgemein', $co['email_general'] ?? '' );
	fge_cc_fact( 'Tagesmail', 'täglich 7:00 Uhr an ' . ( function_exists( 'fge_company_internal_email' ) ? fge_company_internal_email() : '' ) );
	fge_cc_fact( 'Vortags-Info', 'am Tag vor dem Event um 7:00 Uhr' );
	echo '</dl><p class="cc-muted">Welche Mail wann an wen geht, steht im <a href="' . esc_url( fge_cc_url( 'einstellungen', [ 'bereich' => 'postausgang' ] ) ) . '">Postausgang</a>.</p></section>';

	echo '<section class="cc-card"><h2 class="cc-h-small">Kalkulation</h2><dl class="cc-facts">';
	fge_cc_fact( 'Aufschlag', number_format_i18n( function_exists( 'fge_xs_default_margin' ) ? fge_xs_default_margin() : 20, 0 ) . ' % auf den Einkauf netto' );
	fge_cc_fact( 'Umsatzsteuer', number_format_i18n( function_exists( 'fge_xs_vat_percent' ) ? fge_xs_vat_percent() : 19, 0 ) . ' %' );
	fge_cc_fact( 'Preise auf der Website', 'enden auf 4 oder 9, Stufen 5 € p. P. und 50 € pauschal' );
	fge_cc_fact( 'Positionen im Angebot', 'auf volle Euro aufgerundet' );
	fge_cc_fact( 'Antwortfrist Angebot', 'im Senden-Dialog wählbar, höchstens 60 Tage' );
	echo '</dl><p class="cc-muted">Preise des Budget-Rechners: <a href="' . esc_url( admin_url( 'edit.php?post_type=firmengolf_event&page=fge-budget-calc' ) ) . '">im WordPress</a>.</p></section>';

	if ( function_exists( 'fge_cc_ics_panel' ) ) {
		fge_cc_ics_panel(); // eigene Karte, seit 15.10.2026 hier statt unter Kalender
	}

	echo '</div>';
	echo '<p class="cc-muted"><a href="' . esc_url( admin_url( 'options-general.php' ) ) . '">Allgemeine Einstellungen im WordPress</a></p>';
}
