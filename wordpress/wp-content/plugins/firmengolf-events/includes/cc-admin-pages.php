<?php
/**
 * Control Center: Rechnungen, Leads, Partnercodes, Nutzer, Einstellungen
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
		'lead_set'     => [ 'ok', 'Lead aktualisiert.' ],
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

// ── Rechnungen ───────────────────────────────────────────────────────────────

/** Phase eines gebuchten Vorgangs für die Rechnungsliste. */
function fge_cc_invoice_bucket( int $req ): string {
	$st = (string) get_post_meta( $req, '_fge_request_status', true );
	if ( 'abgeschlossen' === $st ) {
		return 'bezahlt';
	}
	if ( 'rechnung_in_lexoffice_erstellt' === $st ) {
		return 'offen';
	}
	$date = fge_cc_event_date( $req );
	return ( 'event_durchgefuehrt' === $st || ( $date > 0 && $date < fge_cc_today() ) ) ? 'stellen' : 'spaeter';
}

// Rechnung gestellt (mit Nummer) oder bezahlt, von der Rechnungsliste oder der Anfrage.
add_action( 'admin_post_fge_cc_invoice', static function (): void {
	$req  = fge_cc_guard( 'fge_cc_invoice' );
	$step = sanitize_key( wp_unslash( $_POST['step'] ?? '' ) );
	$back = sanitize_key( wp_unslash( $_POST['back'] ?? '' ) );
	$no   = trim( sanitize_text_field( wp_unslash( $_POST['invoice_number'] ?? '' ) ) );
	if ( 'accepted' !== (string) get_post_meta( $req, '_fge_offer_status', true ) ) {
		fge_cc_redirect( $req, 'nothing' );
	}
	if ( '' !== $no ) {
		update_post_meta( $req, '_fge_lexoffice_invoice_number', mb_substr( $no, 0, 40 ) );
	}
	update_post_meta( $req, '_fge_lexoffice_invoice_created', 1 );
	fge_request_set_status( $req, 'paid' === $step ? 'abgeschlossen' : 'rechnung_in_lexoffice_erstellt' );
	$msg = 'paid' === $step ? 'inv_paid' : 'inv_set';
	if ( 'rechnungen' === $back ) {
		wp_safe_redirect( fge_cc_url( 'rechnungen', [ 'tab' => sanitize_key( wp_unslash( $_POST['tab'] ?? 'stellen' ) ), 'msg' => $msg ] ) );
		exit;
	}
	fge_cc_redirect( $req, $msg );
} );

/** Knopf „Rechnung gestellt“ mit Feld für die Lexoffice-Nummer. */
function fge_cc_invoice_form( int $req, string $back = '', string $tab = '' ): void {
	echo '<form class="cc-actform cc-invform" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="fge_cc_invoice"><input type="hidden" name="request_id" value="' . (int) $req . '"><input type="hidden" name="step" value="set">';
	echo '<input type="hidden" name="back" value="' . esc_attr( $back ) . '"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">';
	wp_nonce_field( 'fge_cc_invoice_' . $req );
	echo '<input type="text" name="invoice_number" value="' . esc_attr( (string) get_post_meta( $req, '_fge_lexoffice_invoice_number', true ) ) . '" placeholder="Rechnungsnr." aria-label="Rechnungsnummer aus Lexoffice">';
	echo '<button type="submit" class="cc-btn cc-btn--primary">Rechnung gestellt</button></form>';
}

function fge_cc_paid_button( int $req, string $back = '', string $tab = '' ): void {
	fge_cc_button( 'fge_cc_invoice', $req, 'Bezahlt, abschließen', [
		'class'   => 'cc-btn cc-btn--primary',
		'fields'  => [ 'step' => 'paid', 'back' => $back, 'tab' => $tab ],
		'confirm' => 'Zahlung eingegangen und Vorgang abschließen?',
	] );
}

function fge_cc_page_invoices(): void {
	fge_cc_message();
	$tab  = sanitize_key( wp_unslash( $_GET['tab'] ?? 'stellen' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$tabs = [ 'stellen' => 'Zu stellen', 'offen' => 'Gestellt, offen', 'bezahlt' => 'Bezahlt', 'spaeter' => 'Event steht noch an' ];
	if ( ! isset( $tabs[ $tab ] ) ) {
		$tab = 'stellen';
	}
	$res  = fge_cc_query_requests( [ 'meta_query' => [ [ 'key' => '_fge_offer_status', 'value' => 'accepted' ] ] ], 1500 ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	$rows = array_fill_keys( array_keys( $tabs ), [] );
	$sum  = array_fill_keys( array_keys( $tabs ), 0.0 );
	foreach ( $res['ids'] as $req ) {
		$b             = fge_cc_invoice_bucket( (int) $req );
		$net           = fge_cc_offer_net( (int) $req );
		$rows[ $b ][]  = [ (int) $req, $net ];
		$sum[ $b ]    += $net;
	}
	if ( ! isset( $_GET['tab'] ) && ! $rows[ $tab ] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		foreach ( array_keys( $tabs ) as $k ) {
			if ( $rows[ $k ] ) {
				$tab = $k;
				break;
			}
		}
	}
	fge_cc_truncation_note( $res, 'Buchungen' );
	echo '<p class="cc-top-sub cc-sub-under">Rechnungen schreibst du in Lexoffice, hier hältst du nach, was gestellt und bezahlt ist.</p>';

	$vat = function_exists( 'fge_xs_vat_percent' ) ? fge_xs_vat_percent() : 19.0;
	echo '<div class="cc-kpis">';
	foreach ( [ 'stellen' => 'Noch zu stellen', 'offen' => 'Gestellt, nicht bezahlt', 'bezahlt' => 'Bezahlt' ] as $k => $l ) {
		echo '<a class="cc-kpi" href="' . esc_url( fge_cc_url( 'rechnungen', [ 'tab' => $k ] ) ) . '"><span class="cc-kpi-label">' . esc_html( $l ) . '</span><span class="cc-kpi-value">' . esc_html( fge_cc_eur( $sum[ $k ] ) ) . '</span><span class="cc-kpi-sub">' . esc_html( count( $rows[ $k ] ) . ' Vorgänge, netto' ) . '</span></a>';
	}
	echo '</div>';

	fge_cc_seglinks( 'rechnungen', 'tab', $tabs, $tab, array_map( 'count', $rows ), [] );
	echo '<section class="cc-tablewrap"><table class="cc-table"><thead><tr><th>Anfrage</th><th>Kunde</th><th>Termin</th><th>Rechnungsnr.</th><th class="cc-num">Netto</th><th class="cc-num">Brutto</th><th>Status</th><th></th></tr></thead><tbody>';
	usort( $rows[ $tab ], static fn( $a, $b ) => fge_cc_event_date( $a[0] ) <=> fge_cc_event_date( $b[0] ) );
	foreach ( $rows[ $tab ] as [ $req, $net ] ) {
		$url  = fge_cc_request_url( $req );
		$date = fge_cc_event_date( $req );
		$flow = fge_cc_flow( $req );
		echo '<tr><td class="cc-mono"><a href="' . esc_url( $url ) . '">' . esc_html( fge_request_number( $req ) ) . '</a></td>';
		echo '<td>' . esc_html( (string) get_post_meta( $req, '_fge_company_name', true ) ) . '</td>';
		echo '<td class="cc-tnum">' . ( $date > 0 ? esc_html( wp_date( 'd.m.Y', $date ) ) : 'offen' ) . '</td>';
		echo '<td class="cc-mono">' . esc_html( (string) get_post_meta( $req, '_fge_lexoffice_invoice_number', true ) ) . '</td>';
		echo '<td class="cc-num">' . esc_html( fge_cc_eur( $net ) ) . '</td><td class="cc-num">' . esc_html( fge_cc_eur( round( $net * ( 1 + $vat / 100 ), 2 ) ) ) . '</td>';
		echo '<td>' . fge_cc_pill( $flow['label'], $flow['tone'] ) . '</td><td class="cc-td-act">'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( in_array( $tab, [ 'stellen', 'spaeter' ], true ) ) {
			fge_cc_invoice_form( $req, 'rechnungen', $tab );
		} elseif ( 'offen' === $tab ) {
			fge_cc_paid_button( $req, 'rechnungen', $tab );
		}
		echo '</td></tr>';
	}
	if ( ! $rows[ $tab ] ) {
		echo '<tr class="is-empty"><td colspan="8">Nichts in dieser Ansicht.</td></tr>';
	}
	echo '</tbody></table></section>';
	echo '<p class="cc-muted">Eingangsrechnungen von Plätzen und Dienstleistern stehen unter <a href="' . esc_url( fge_cc_url( 'finanzen' ) ) . '">Finanzen</a>.</p>';
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
		wp_safe_redirect( fge_cc_url( 'leads', [ 'msg' => 'nothing' ] ) );
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
	wp_safe_redirect( fge_cc_url( 'leads', [ 'msg' => 'lead_set' ] ) );
	exit;
} );

function fge_cc_page_leads(): void {
	fge_cc_message();
	$st   = sanitize_key( wp_unslash( $_GET['tab'] ?? 'offen' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	// Direkt per SQL: eine OR-Metaabfrage würde vom Demo-Filter (demo-portal.php)
	// um eine weitere ODER-Bedingung ergänzt und zeigte dann alle Anfragen (Audit).
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$all = array_map( 'intval', $wpdb->get_col(
		"SELECT DISTINCT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
		 WHERE p.post_type = 'firmengolf_request' AND p.post_status IN ('publish','draft')
		 AND ( ( m.meta_key = '_fge_request_type' AND m.meta_value = 'budget_lead' ) OR m.meta_key = '_fge_lead_status' )
		 ORDER BY p.post_date DESC LIMIT 500"
	) );
	if ( function_exists( 'fge_is_demo_request' ) ) {
		$all = array_values( array_filter( $all, static fn( $id ) => ! fge_is_demo_request( $id ) ) );
	}
	$map  = fge_cc_lead_statuses();
	$of   = static fn( int $id ) => (string) get_post_meta( $id, '_fge_lead_status', true ) ?: 'neu';
	$tabs = [ 'offen' => 'Offen', 'umgewandelt' => 'Umgewandelt', 'verloren' => 'Verloren', 'alle' => 'Alle' ];
	$in   = static fn( int $id, string $t ) => 'alle' === $t || ( 'offen' === $t ? in_array( $of( $id ), [ 'neu', 'kontaktiert' ], true ) : $of( $id ) === $t );
	$counts = [];
	foreach ( $tabs as $k => $l ) {
		$counts[ $k ] = count( array_filter( $all, static fn( $id ) => $in( (int) $id, $k ) ) );
	}
	if ( ! isset( $tabs[ $st ] ) ) {
		$st = 'offen';
	}
	echo '<p class="cc-top-sub cc-sub-under">Leute, die im Budget-Rechner den Richtwert freigeschaltet haben. Noch keine Anfrage, aber eine warme Adresse.</p>';
	echo '<div class="cc-toolbar"><a class="cc-btn" href="' . esc_url( admin_url( 'edit.php?post_type=firmengolf_event&page=fge-budget-calc' ) ) . '">Preise des Rechners im WordPress</a></div>';
	fge_cc_seglinks( 'leads', 'tab', $tabs, $st, $counts, [] );
	echo '<section class="cc-tablewrap"><table class="cc-table"><thead><tr><th>Eingang</th><th>E-Mail</th><th>Was gerechnet</th><th class="cc-num">Richtwert</th><th>Status</th><th></th></tr></thead><tbody>';
	$n = 0;
	foreach ( $all as $id ) {
		$id = (int) $id;
		if ( ! $in( $id, $st ) ) {
			continue;
		}
		$n++;
		$bc    = (array) get_post_meta( $id, '_fge_budget_calc', true );
		$mail  = (string) get_post_meta( $id, '_fge_contact_email', true );
		[ $l, $tone ] = $map[ $of( $id ) ];
		$what  = implode( ' · ', array_filter( [ (string) ( $bc['type'] ?? get_post_meta( $id, '_fge_occasion', true ) ), ! empty( $bc['participants'] ) ? (int) $bc['participants'] . ' Personen' : ( (int) get_post_meta( $id, '_fge_expected_participants', true ) > 0 ? (int) get_post_meta( $id, '_fge_expected_participants', true ) . ' Personen' : '' ), (string) ( $bc['range'] ?? '' ), (string) ( $bc['services'] ?? '' ) ] ) );
		echo '<tr><td class="cc-tnum">' . esc_html( get_the_date( 'd.m.Y', $id ) ) . '</td>';
		echo '<td><a href="mailto:' . esc_attr( $mail ) . '">' . esc_html( $mail ) . '</a></td>';
		echo '<td><span class="cc-td-sub cc-td-sub--plain">' . esc_html( $what ) . '</span></td>';
		echo '<td class="cc-num">' . ( ! empty( $bc['total'] ) ? esc_html( fge_cc_eur( (float) $bc['total'] ) ) : '' ) . '</td>';
		echo '<td>' . fge_cc_pill( $l, $tone ) . '</td><td class="cc-td-act">'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( 'umgewandelt' === $of( $id ) ) {
			echo '<a class="cc-btn cc-btn--tiny" href="' . esc_url( fge_cc_request_url( $id ) ) . '">Zur Anfrage</a>';
		} else {
			if ( 'neu' === $of( $id ) ) {
				fge_cc_button( 'fge_cc_lead', $id, 'Kontaktiert', [ 'class' => 'cc-btn cc-btn--tiny', 'fields' => [ 'to' => 'kontaktiert' ] ] );
			}
			fge_cc_button( 'fge_cc_lead', $id, 'In Anfrage umwandeln', [ 'class' => 'cc-btn cc-btn--tiny cc-btn--primary', 'fields' => [ 'to' => 'umgewandelt' ] ] );
			if ( 'verloren' !== $of( $id ) ) {
				fge_cc_button( 'fge_cc_lead', $id, 'Verloren', [ 'class' => 'cc-btn cc-btn--text', 'fields' => [ 'to' => 'verloren' ] ] );
			}
		}
		echo '</td></tr>';
	}
	if ( ! $n ) {
		echo '<tr class="is-empty"><td colspan="6">Keine Leads in dieser Ansicht.</td></tr>';
	}
	echo '</tbody></table></section>';
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

function fge_cc_page_partnercodes(): void {
	fge_cc_message();
	if ( ! defined( 'FGE_PC_POST_TYPE' ) ) {
		fge_cc_empty( 'Partnercodes sind nicht aktiv.' );
		return;
	}
	$open  = absint( $_GET['code'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$codes = get_posts( [ 'post_type' => FGE_PC_POST_TYPE, 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'fields' => 'ids' ] );
	echo '<p class="cc-top-sub cc-sub-under">Multiplikatoren wie Verbände oder Creator. Der Kunde bekommt Rabatt, der Inhaber eine feste Provision je angenommenem Angebot.</p>';
	echo '<div class="cc-toolbar"><button type="button" class="cc-btn cc-btn--primary" data-cc-drawer-open="cc-pc-new">Partnercode anlegen</button></div>';
	echo '<section class="cc-tablewrap"><table class="cc-table"><thead><tr><th>Inhaber</th><th>Code</th><th>Status</th><th class="cc-num">Rabatt</th><th class="cc-num">Provision</th><th class="cc-num">Anfragen</th><th class="cc-num">Buchungen</th><th class="cc-num">Provision offen</th></tr></thead><tbody>';
	foreach ( $codes as $id ) {
		$s    = fge_pc_stats( (int) $id );
		$url  = fge_cc_url( 'partnercodes', [ 'code' => $id ] );
		$use  = fge_pc_is_usable( (int) $id );
		echo '<tr onclick="location.href=\'' . esc_js( $url ) . '\'"><td><a href="' . esc_url( $url ) . '"><strong>' . esc_html( get_the_title( $id ) ) . '</strong></a></td>';
		echo '<td class="cc-mono">' . esc_html( (string) get_post_meta( $id, '_fge_pc_code', true ) ) . '</td>';
		echo '<td>' . fge_cc_pill( $use ? 'Aktiv' : ( 'pausiert' === get_post_meta( $id, '_fge_pc_status', true ) ? 'Pausiert' : 'Abgelaufen' ), $use ? 'good' : 'neutral' ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<td class="cc-num">' . (int) fge_pc_percent( (int) $id ) . ' %</td>';
		echo '<td class="cc-num">' . esc_html( fge_cc_eur( (float) get_post_meta( $id, '_fge_pc_commission_eur', true ) ) ) . '</td>';
		echo '<td class="cc-num">' . (int) $s['requests'] . '</td><td class="cc-num">' . (int) $s['bookings'] . '</td><td class="cc-num">' . ( $s['open'] > 0 ? '<strong>' . esc_html( fge_cc_eur( $s['open'] ) ) . '</strong>' : '' ) . '</td></tr>';
	}
	if ( ! $codes ) {
		echo '<tr class="is-empty"><td colspan="8">Noch keine Partnercodes.</td></tr>';
	}
	echo '</tbody></table></section>';

	// Detail eines Codes: Anfragen mit Provision, abrechnen.
	if ( $open > 0 && FGE_PC_POST_TYPE === get_post_type( $open ) ) {
		$reqs = fge_pc_requests( $open );
		$s    = fge_pc_stats( $open );
		echo '<section class="cc-card cc-pcdetail"><div class="cc-block-head"><div><h2 class="cc-h-small">' . esc_html( get_the_title( $open ) . ' · ' . get_post_meta( $open, '_fge_pc_code', true ) ) . '</h2>';
		echo '<span class="cc-muted">' . esc_html( 'Offen ' . fge_cc_eur( $s['open'] ) . ' · abgerechnet ' . fge_cc_eur( $s['settled'] ) . ' · storniert ' . fge_cc_eur( $s['cancelled'] ) ) . '</span></div><div class="cc-btnrow">';
		echo '<button type="button" class="cc-btn" data-cc-drawer-open="cc-pc-' . (int) $open . '">Bearbeiten</button>';
		if ( $s['open'] > 0 ) {
			fge_cc_pc_form_open( 'fge_cc_pc_settle', $open );
			echo '<button type="submit" class="cc-btn cc-btn--primary" onclick="return confirm(\'Alle offenen Provisionen als abgerechnet markieren?\')">Alle offenen abrechnen</button></form>';
		}
		echo '</div></div>';
		echo '<table class="cc-table cc-table--plain"><thead><tr><th>Anfrage</th><th>Firma</th><th>Status</th><th class="cc-num">Provision</th><th></th></tr></thead><tbody>';
		foreach ( $reqs as $r ) {
			$cs = (string) get_post_meta( $r, '_fge_pc_commission_status', true );
			echo '<tr><td class="cc-mono"><a href="' . esc_url( fge_cc_request_url( $r ) ) . '">' . esc_html( fge_request_number( $r ) ) . '</a></td><td>' . esc_html( (string) get_post_meta( $r, '_fge_company_name', true ) ) . '</td>';
			echo '<td>' . fge_cc_pill( fge_cc_flow( $r )['label'], fge_cc_flow( $r )['tone'] ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<td class="cc-num">' . ( '' !== $cs ? esc_html( fge_cc_eur( (float) get_post_meta( $r, '_fge_pc_commission_amount', true ) ) . ' ' . $cs ) : '' ) . '</td><td class="cc-td-act">';
			if ( 'offen' === $cs ) {
				fge_cc_pc_form_open( 'fge_cc_pc_settle', $open );
				echo '<input type="hidden" name="req" value="' . (int) $r . '"><button type="submit" class="cc-btn cc-btn--tiny">Abrechnen</button></form>';
			}
			echo '</td></tr>';
		}
		if ( ! $reqs ) {
			echo '<tr class="is-empty"><td colspan="5">Noch keine Anfrage mit diesem Code.</td></tr>';
		}
		echo '</tbody></table></section>';
		fge_cc_pc_drawer( $open, false );
	}
	fge_cc_pc_drawer( 0, false );
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
		wp_safe_redirect( fge_cc_url( 'nutzer', [ 'msg' => $msg ] ) );
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
	echo '</dl><p class="cc-muted">Welche Mail wann an wen geht, steht im <a href="' . esc_url( fge_cc_url( 'postausgang' ) ) . '">Postausgang</a>.</p></section>';

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
