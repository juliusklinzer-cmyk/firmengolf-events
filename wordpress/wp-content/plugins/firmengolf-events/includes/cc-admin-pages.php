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
		'bc_saved'     => [ 'ok', 'Budgetrechner gespeichert. Neue Berechnungen auf der Website nutzen die Werte sofort.' ],
		'co_saved'     => [ 'ok', 'Firmendaten gespeichert.' ],
		'co_badmail'   => [ 'err', 'Gespeichert, eine Mailadresse war ungültig und wurde nicht übernommen.' ],
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

// ── Einstellungen ────────────────────────────────────────────────────────────
// Fünf Bereiche nach „Control Center Redesign“ (16.10.2026): Budgetrechner,
// Postausgang, Nutzer, Verbindungen, Firmendaten.

function fge_cc_settings_areas(): array {
	return [ 'budgetrechner' => 'Budgetrechner', 'postausgang' => 'Postausgang', 'nutzer' => 'Nutzer', 'verbindungen' => 'Verbindungen', 'firmendaten' => 'Firmendaten' ];
}

function fge_cc_page_settings(): void {
	$area  = sanitize_key( wp_unslash( $_GET['bereich'] ?? 'budgetrechner' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$areas = fge_cc_settings_areas();
	$area  = isset( $areas[ $area ] ) ? $area : 'budgetrechner';
	fge_cc_message();
	echo '<p class="cc-top-sub cc-sub-under">Budgetrechner, Mails, Nutzer und Verbindungen</p>';
	$failed = count( array_filter( function_exists( 'fge_mail_log_recent' ) ? fge_mail_log_recent( 50, 'failed' ) : [], static fn( $m ) => (int) strtotime( (string) $m['sent_at'] ) >= time() - 14 * DAY_IN_SECONDS ) );
	echo '<nav class="cc-areatabs" aria-label="Bereich">';
	foreach ( $areas as $k => $label ) {
		echo '<a class="cc-areatab' . ( $k === $area ? ' is-on' : '' ) . '" href="' . esc_url( fge_cc_url( 'einstellungen', [ 'bereich' => $k ] ) ) . '"' . ( $k === $area ? ' aria-current="page"' : '' ) . '>' . esc_html( $label );
		if ( 'postausgang' === $k && $failed > 0 ) {
			echo '<span class="cc-areatab-badge">' . (int) $failed . '</span>';
		}
		echo '</a>';
	}
	echo '</nav>';
	call_user_func( 'fge_cc_settings_' . $area );
}

// ── Budgetrechner ────────────────────────────────────────────────────────────

/** Einheit einer Leistung: Person oder pauschal, dazu je Nacht oder Tag. */
function fge_cc_bc_unit( array $sv ): array {
	$pp   = array_sum( $sv['pp'] ) > 0 || array_sum( $sv['flat'] ) <= 0;
	$per  = [ 'night' => ' und Nacht', 'day' => ' und Tag' ][ $sv['per'] ?? '' ] ?? '';
	return [ $pp ? 'pp' : 'flat', $pp ? 'pro Person' . $per : 'pauschal' . ( '' !== $per ? $per : '' ) ];
}

add_action( 'admin_post_fge_cc_bc_save', static function (): void {
	if ( ! fge_cc_can() ) {
		wp_die( 'Keine Berechtigung.', '', [ 'response' => 403 ] );
	}
	check_admin_referer( 'fge_cc_bc_save' );
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce oben geprüft.
	$type  = sanitize_key( wp_unslash( $_POST['typ'] ?? '' ) );
	$on    = array_map( 'sanitize_key', (array) wp_unslash( $_POST['on'] ?? [] ) );
	$price = (array) wp_unslash( $_POST['price'] ?? [] );
	// phpcs:enable
	$cfg   = fge_bc_config();
	$def   = fge_bc_defaults();
	$known = array_column( $def['services'], 'id' );

	// Preise je Leistung, nur die Einheit, die die Leistung hat.
	$services = [];
	foreach ( $cfg['services'] as $sv ) {
		[ $unit ] = fge_cc_bc_unit( $sv );
		if ( isset( $price[ $sv['id'] ] ) && is_array( $price[ $sv['id'] ] ) ) {
			for ( $i = 0; $i < 3; $i++ ) {
				$raw = (string) ( $price[ $sv['id'] ][ $i ] ?? '' );
				if ( '' !== trim( $raw ) ) {
					$sv[ $unit ][ $i ] = max( 0, function_exists( 'fge_parse_de_amount' ) ? fge_parse_de_amount( $raw ) : (float) str_replace( ',', '.', $raw ) );
				}
			}
		}
		$services[] = [ 'id' => $sv['id'], 'label' => $sv['label'], 'pp' => $sv['pp'], 'flat' => $sv['flat'] ];
	}
	// Leistungen des gewählten Typs in Katalog-Reihenfolge.
	$types = [];
	foreach ( $cfg['types'] as $t ) {
		$list = $t['services'];
		if ( $t['id'] === $type ) {
			$list = array_values( array_intersect( $known, array_unique( array_merge( $on, $t['required'] ) ) ) );
		}
		$types[] = [ 'id' => $t['id'], 'label' => $t['label'], 'services' => $list ];
	}
	update_option( FGE_BC_OPTION, [ 'v' => $def['v'], 'types' => $types, 'services' => $services, 'round_to' => (int) $cfg['round_to'] ] );
	wp_safe_redirect( fge_cc_url( 'einstellungen', [ 'bereich' => 'budgetrechner', 'typ' => $type, 'msg' => 'bc_saved' ] ) );
	exit;
} );

function fge_cc_settings_budgetrechner(): void {
	if ( ! function_exists( 'fge_bc_config' ) ) {
		fge_cc_empty( 'Der Budgetrechner ist nicht aktiv.' );
		return;
	}
	$cfg   = fge_bc_config();
	$types = array_column( $cfg['types'], null, 'id' );
	$typ   = sanitize_key( wp_unslash( $_GET['typ'] ?? (string) ( $cfg['start']['type'] ?? 'teamevent' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$typ   = isset( $types[ $typ ] ) ? $typ : array_key_first( $types );
	$t     = $types[ $typ ];
	$cats  = $cfg['cats'];
	$on    = $t['services'];

	echo '<form class="cc-card cc-bc" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="fge_cc_bc_save"><input type="hidden" name="typ" value="' . esc_attr( $typ ) . '">';
	wp_nonce_field( 'fge_cc_bc_save' );
	echo '<div class="cc-bc-head"><div class="cc-chart-head"><h2>Budgetrechner auf der Website</h2><span class="cc-muted">Preise netto, Änderungen gelten sofort für neue Berechnungen</span></div>';
	echo '<div class="cc-bc-types">';
	foreach ( $types as $id => $ty ) {
		echo '<a class="cc-bc-type' . ( $id === $typ ? ' is-on' : '' ) . '" href="' . esc_url( fge_cc_url( 'einstellungen', [ 'bereich' => 'budgetrechner', 'typ' => $id ] ) ) . '">' . esc_html( $ty['label'] ) . '</a>';
	}
	echo '</div><span class="cc-bc-sub">' . esc_html( count( $on ) . ' Positionen im Rechner für ' . $t['label'] . '. Haken setzen oder entfernen, Preise direkt eintragen.' ) . '</span></div>';

	$rows = $cfg['services'];
	usort( $rows, static fn( $a, $b ) => (int) in_array( $b['id'], $on, true ) <=> (int) in_array( $a['id'], $on, true ) );
	echo '<div class="cc-bc-scroll"><div class="cc-bc-table"><div class="cc-bc-row cc-bc-row--head"><span>Aktiv</span><span>Position</span><span>Einheit</span><span class="cc-num">Günstig</span><span class="cc-num">Standard</span><span class="cc-num">Premium</span></div>';
	$sum = 0.0;
	$pax = 20;
	foreach ( $rows as $sv ) {
		$active = in_array( $sv['id'], $on, true );
		$req    = in_array( $sv['id'], $t['required'], true );
		[ $unit, $ulabel ] = fge_cc_bc_unit( $sv );
		if ( $active ) {
			$sum += 'pp' === $unit ? $sv['pp'][1] * $pax : $sv['flat'][1];
		}
		echo '<div class="cc-bc-row' . ( $active ? '' : ' is-off' ) . '">';
		echo '<span><input type="checkbox" name="on[]" value="' . esc_attr( $sv['id'] ) . '"' . checked( $active, true, false ) . ( $req ? ' disabled title="Pflicht für diesen Typ"' : '' ) . ' aria-label="' . esc_attr( $sv['label'] . ' im Rechner' ) . '">' . ( $req ? '<input type="hidden" name="on[]" value="' . esc_attr( $sv['id'] ) . '">' : '' ) . '</span>';
		echo '<span class="cc-bc-label"><strong>' . esc_html( $sv['label'] ) . '</strong><span>' . esc_html( (string) ( $cats[ $sv['cat'] ]['label'] ?? '' ) . ( $req ? ' · Pflicht' : '' ) . ( isset( $sv['zero_label'] ) ? ' · ohne Preis: ' . $sv['zero_label'] : '' ) ) . '</span></span>';
		echo '<span class="cc-bc-unit">' . esc_html( $ulabel ) . '</span>';
		for ( $i = 0; $i < 3; $i++ ) {
			$v = (float) $sv[ $unit ][ $i ];
			echo '<input type="text" inputmode="decimal" name="price[' . esc_attr( $sv['id'] ) . '][' . $i . ']" value="' . esc_attr( number_format( $v, abs( $v - round( $v ) ) > 0.004 ? 2 : 0, ',', '.' ) ) . '" aria-label="' . esc_attr( $sv['label'] . ', ' . [ 'Günstig', 'Standard', 'Premium' ][ $i ] ) . '">';
		}
		echo '</div>';
	}
	echo '</div></div>';
	echo '<div class="cc-bc-foot"><span class="cc-muted">' . esc_html( 'Beispiel ' . $t['label'] . ' mit ' . $pax . ' Personen, Stufe Standard' ) . '</span><strong>' . esc_html( fge_cc_eur0( $sum ) ) . '</strong><button type="submit" class="cc-btn cc-btn--primary">Speichern</button></div>';
	echo '</form>';
}

// ── Postausgang ──────────────────────────────────────────────────────────────

function fge_cc_settings_postausgang(): void {
	$only = 'fehler' === sanitize_key( wp_unslash( $_GET['filter'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$rows = function_exists( 'fge_mail_log_recent' ) ? fge_mail_log_recent( 200, $only ? 'failed' : '' ) : [];
	$nf   = function_exists( 'fge_mail_log_recent' ) ? count( fge_mail_log_recent( 200, 'failed' ) ) : 0;
	fge_cc_seglinks( 'einstellungen', 'filter', [ '' => 'Alle', 'fehler' => 'Nicht zugestellt' ], $only ? 'fehler' : '', [ '' => count( function_exists( 'fge_mail_log_recent' ) ? fge_mail_log_recent( 200 ) : [] ), 'fehler' => $nf ], [ 'bereich' => 'postausgang' ] );
	echo '<section class="cc-card cc-outbox">';
	foreach ( $rows as $m ) {
		$req  = (int) $m['request_id'];
		$st   = (string) $m['status'];
		$tag  = 'failed' === $st ? [ 'Fehlgeschlagen', 'bad' ] : ( 'resent' === $st ? [ 'Erneut gesendet', 'neutral' ] : [ 'Gesendet', 'good' ] );
		$name = function_exists( 'fge_mail_label' ) && '' !== (string) $m['mail_key'] ? fge_mail_label( (string) $m['mail_key'] ) : '';
		echo '<div class="cc-outrow"><span class="cc-outrow-tag">' . fge_cc_pill( $tag[0], $tag[1] ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="cc-outrow-main"><strong>' . esc_html( (string) $m['subject'] ) . '</strong><span>An ' . esc_html( (string) $m['recipient'] ) . ( $req > 0 ? ' · <a href="' . esc_url( fge_cc_request_url( $req ) ) . '">' . esc_html( fge_request_number( $req ) ) . '</a>' : '' ) . ( '' !== $name ? ' · ' . esc_html( $name ) : '' ) . '</span>';
		if ( 'failed' === $st && '' !== (string) $m['error'] ) {
			echo '<span class="cc-outrow-err">' . esc_html( mb_substr( (string) $m['error'], 0, 160 ) ) . '</span>';
		}
		echo '</div><span class="cc-outrow-at">' . esc_html( wp_date( 'd.m. H:i', (int) strtotime( (string) $m['sent_at'] ) ) ) . '</span>';
		if ( 'failed' === $st ) {
			fge_mail_log_retry_button( $m );
		}
		echo '</div>';
	}
	if ( ! $rows ) {
		echo '<p class="cc-tgroup-empty">' . esc_html( $only ? 'Alle Mails sind raus.' : 'Noch nichts protokolliert.' ) . '</p>';
	}
	echo '</section>';
}

// ── Nutzer ───────────────────────────────────────────────────────────────────

// Letzte Anmeldung merken, für „zuletzt“ in der Nutzerliste.
add_action( 'wp_login', static function ( string $login, WP_User $user ): void {
	update_user_meta( $user->ID, 'fge_last_login', time() );
}, 10, 2 );

function fge_cc_settings_nutzer(): void {
	$tab   = sanitize_key( wp_unslash( $_GET['tab'] ?? 'team' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$all   = get_users( [ 'orderby' => 'registered', 'order' => 'ASC', 'number' => 500 ] );
	$is_t  = static fn( WP_User $u ) => in_array( 'administrator', (array) $u->roles, true );
	$is_p  = static fn( WP_User $u ) => in_array( 'firmengolf_partner', (array) $u->roles, true );
	$team  = 'partner' !== $tab;
	$rows  = array_values( array_filter( $all, $team ? $is_t : $is_p ) );
	$me    = get_current_user_id();
	$ago   = static function ( int $ts ): string {
		if ( $ts <= 0 ) {
			return 'noch nie';
		}
		$d = (int) floor( ( fge_cc_today() - current_datetime()->setTimestamp( $ts )->setTime( 0, 0 )->getTimestamp() ) / DAY_IN_SECONDS );
		return 0 >= $d ? 'heute' : ( 1 === $d ? 'gestern' : 'am ' . wp_date( 'd.m.Y', $ts ) );
	};

	fge_cc_seglinks( 'einstellungen', 'tab', [ 'team' => 'Control Center', 'partner' => 'Partner-Zugänge' ], $team ? 'team' : 'partner', [ 'team' => count( array_filter( $all, $is_t ) ), 'partner' => count( array_filter( $all, $is_p ) ) ], [ 'bereich' => 'nutzer' ] );
	echo '<section class="cc-card cc-users"><div class="cc-users-head"><h2>' . ( $team ? 'Zugang zum Control Center' : 'Zugänge zum Partnerportal' ) . '</h2>';
	if ( $team ) {
		echo '<button type="button" class="cc-btn" data-cc-drawer-open="cc-user-new">Person einladen</button>';
	}
	echo '</div>';
	foreach ( $rows as $u ) {
		$name = trim( $u->first_name . ' ' . $u->last_name ) ?: $u->display_name;
		$ini  = mb_strtoupper( implode( '', array_map( static fn( $w ) => mb_substr( $w, 0, 1 ), array_slice( preg_split( '/\s+/', $name ) ?: [], 0, 2 ) ) ) );
		$pid  = 0;
		if ( ! $team ) {
			$q   = get_posts( [ 'post_type' => 'firmengolf_partner', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_fge_assigned_wp_user_id', 'meta_value' => $u->ID ] ); // phpcs:ignore WordPress.DB.SlowDBQuery
			$pid = $q ? (int) $q[0] : 0;
		}
		echo '<div class="cc-userrow"><span class="cc-avatar" aria-hidden="true">' . esc_html( $ini ) . '</span>';
		echo '<div class="cc-userrow-main"><strong>' . esc_html( $name ) . ( $u->ID === $me ? ' <span class="cc-muted">(du)</span>' : '' ) . '</strong><span>' . esc_html( $u->user_email . ' · zuletzt ' . $ago( (int) get_user_meta( $u->ID, 'fge_last_login', true ) ) ) . ( $pid > 0 ? ' · <a href="' . esc_url( fge_cc_url( 'partner', [ 'pid' => $pid ] ) ) . '">' . esc_html( fge_cc_partner_name( $pid ) ) . '</a>' : '' ) . '</span></div>';
		echo '<span class="cc-userrow-role">' . esc_html( $team ? 'Admin' : 'Partner' ) . '</span><div class="cc-userrow-act">';
		fge_cc_user_form_open( $u->ID, 'reset' );
		echo '<button type="submit" class="cc-btn cc-btn--tiny" onclick="return confirm(' . esc_attr( wp_json_encode( 'Link zum Setzen des Passworts an ' . $u->user_email . ' senden?' ) ) . ')">Passwort-Link senden</button></form>';
		echo '<button type="button" class="cc-btn cc-btn--text" data-cc-drawer-open="cc-user-' . (int) $u->ID . '">Mail ändern</button></div></div>';
	}
	if ( ! $rows ) {
		echo '<p class="cc-tgroup-empty">Keine Zugänge.</p>';
	}
	echo '</section>';

	foreach ( $rows as $u ) {
		fge_cc_drawer_open( 'cc-user-' . $u->ID, 'Mailadresse ändern', $u->display_name );
		fge_cc_user_form_open( $u->ID, 'mail' );
		echo '<label class="cc-field cc-field--wide"><span>Neue Mailadresse</span><input type="email" name="email" value="' . esc_attr( $u->user_email ) . '" required></label>';
		echo '<p class="cc-muted">Die alte Adresse bekommt von WordPress einen Hinweis über die Änderung.</p>';
		echo '<div class="cc-drawer-foot"><button type="button" class="cc-btn" data-cc-drawer-close>Abbrechen</button><button type="submit" class="cc-btn cc-btn--primary">Ändern</button></div></form>';
		fge_cc_drawer_close();
	}
	fge_cc_drawer_open( 'cc-user-new', 'Person einladen', 'Bekommt eine Mail mit Link zum Anmelden' );
	fge_cc_user_form_open( 0, 'create' );
	echo '<label class="cc-field cc-field--wide"><span>Name</span><input type="text" name="name"></label>';
	echo '<label class="cc-field cc-field--wide"><span>E-Mail</span><input type="email" name="email" required></label>';
	echo '<p class="cc-muted">Volle Rechte im Control Center und im WordPress. Das Passwort setzt die Person selbst über den Link. Partner-Zugänge entstehen über die Anmeldung als Partner.</p>';
	echo '<div class="cc-drawer-foot"><button type="button" class="cc-btn" data-cc-drawer-close>Abbrechen</button><button type="submit" class="cc-btn cc-btn--primary">Einladung senden</button></div></form>';
	fge_cc_drawer_close();
}

// ── Verbindungen ─────────────────────────────────────────────────────────────

function fge_cc_settings_verbindungen(): void {
	$co   = fge_company();
	$sent = function_exists( 'fge_mail_log_recent' ) ? fge_mail_log_recent( 1, 'sent' ) : [];
	$fail = function_exists( 'fge_mail_log_recent' ) ? count( array_filter( fge_mail_log_recent( 50, 'failed' ), static fn( $m ) => (int) strtotime( (string) $m['sent_at'] ) >= time() - 7 * DAY_IN_SECONDS ) ) : 0;
	echo '<section class="cc-conns">';

	echo '<div class="cc-card cc-conn"><div class="cc-conn-head"><strong>Outlook-Kalender</strong>' . fge_cc_pill( 'Verbunden', 'good' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<p>Gebuchte Events, Optionen, Fristen und fällige Erinnerungen erscheinen als Kalender-Abo in Outlook. Die Adresse einmal in Outlook unter Kalender hinzufügen, aus dem Internet eintragen.</p>';
	if ( function_exists( 'fge_cc_ics_url' ) ) {
		echo '<div class="cc-conn-url"><input type="text" readonly value="' . esc_attr( fge_cc_ics_url() ) . '" aria-label="Kalender-Adresse für Outlook" onclick="this.select()"><button type="button" class="cc-btn" data-cc-copy="' . esc_attr( fge_cc_ics_url() ) . '">Kopieren</button></div>';
	}
	echo '<p class="cc-muted">Outlook holt abonnierte Kalender alle paar Stunden neu. Der Link enthält einen geheimen Schlüssel, bitte nicht weitergeben.</p></div>';

	echo '<div class="cc-card cc-conn"><div class="cc-conn-head"><strong>Mailversand</strong>' . fge_cc_pill( $fail ? $fail . ' nicht zugestellt' : 'Läuft', $fail ? 'bad' : 'good' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<p>Mails gehen über ' . esc_html( (string) ( $co['email_events'] ?? '' ) ) . ' raus. Antworten landen in deinem Postfach. Tagesmail und Vortags-Info jeweils um 7:00 Uhr.</p>';
	echo '<p class="cc-muted">' . esc_html( $sent ? 'Zuletzt zugestellt: ' . wp_date( 'd.m.Y H:i', (int) strtotime( (string) $sent[0]['sent_at'] ) ) : 'Noch keine Mail protokolliert.' ) . ' · <a href="' . esc_url( fge_cc_url( 'einstellungen', [ 'bereich' => 'postausgang' ] ) ) . '">Postausgang</a></p></div>';

	echo '<div class="cc-card cc-conn"><div class="cc-conn-head"><strong>Lexoffice</strong>' . fge_cc_pill( 'Von Hand', 'neutral' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<p>Keine Anbindung. Die Rechnung schreibst du in Lexoffice und trägst Nummer, Datum und Fälligkeit in der Anfrage ein. Zahlungen hakst du unter Finanzen ab.</p></div>';

	echo '</section>';
}

// ── Firmendaten ──────────────────────────────────────────────────────────────

add_action( 'admin_post_fge_cc_company_save', static function (): void {
	if ( ! fge_cc_can() ) {
		wp_die( 'Keine Berechtigung.', '', [ 'response' => 403 ] );
	}
	check_admin_referer( 'fge_cc_company_save' );
	$over = [];
	$bad  = false;
	foreach ( fge_company_editable_keys() as $k ) {
		$v = trim( sanitize_text_field( wp_unslash( $_POST['co'][ $k ] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( str_starts_with( $k, 'email_' ) && '' !== $v && ! is_email( $v ) ) {
			$bad = true;
			continue; // ungültige Adresse nicht übernehmen
		}
		$over[ $k ] = $v;
	}
	// Nur speichern, was vom Wert im Plugin abweicht. Leere Felder heißen: Wert aus dem Plugin.
	$base = fge_company_base();
	update_option( 'fge_company_overrides', array_filter( $over, static fn( $v, $k ) => '' !== $v && $v !== (string) ( $base[ $k ] ?? '' ), ARRAY_FILTER_USE_BOTH ), false );
	wp_safe_redirect( fge_cc_url( 'einstellungen', [ 'bereich' => 'firmendaten', 'msg' => $bad ? 'co_badmail' : 'co_saved' ] ) );
	exit;
} );

function fge_cc_settings_firmendaten(): void {
	$co = fge_company();
	echo '<form class="cc-card cc-firm" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="fge_cc_company_save">';
	wp_nonce_field( 'fge_cc_company_save' );
	echo '<div class="cc-chart-head"><h2>Firmendaten für Angebote, Mails und Rechnungen</h2><span class="cc-muted">Gilt nach dem Speichern sofort, auch im Impressum</span></div>';
	echo '<div class="cc-firm-grid">';
	foreach ( fge_company_editable_fields() as $k => $label ) {
		$type = str_starts_with( $k, 'email_' ) ? 'email' : 'text';
		echo '<label class="cc-firm-f"><span>' . esc_html( $label ) . '</span><input type="' . $type . '" name="co[' . esc_attr( $k ) . ']" value="' . esc_attr( (string) ( $co[ $k ] ?? '' ) ) . '"></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</div><div class="cc-bc-foot"><span class="cc-muted">Kalkulation fest im Plugin: Aufschlag ' . esc_html( number_format_i18n( function_exists( 'fge_xs_default_margin' ) ? fge_xs_default_margin() : 20, 0 ) ) . ' % auf den Einkauf netto, Umsatzsteuer ' . esc_html( number_format_i18n( function_exists( 'fge_xs_vat_percent' ) ? fge_xs_vat_percent() : 19, 0 ) ) . ' %, Zahlungsziel ' . (int) fge_cc_invoice_terms() . ' Tage.</span><button type="submit" class="cc-btn cc-btn--primary">Speichern</button></div></form>';
}
