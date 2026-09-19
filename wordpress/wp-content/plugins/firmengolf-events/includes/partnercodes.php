<?php
/**
 * Partnercodes für Multiplikatoren (Julius, 18.09.2026).
 *
 * Ein Multiplikator (Content Creator, Verband wie DGV, GMVD, Agentur) bekommt genau
 * einen Code. Firmen geben ihn bei der Anfrage ein oder kommen über einen Link
 * (?pc=CODE). Wirkung: Anfrage wird dem Multiplikator zugeordnet, der Kunde bekommt
 * einen Rabatt in Prozent auf die Netto-Zwischensumme des Angebots (Standard 5 %),
 * der Multiplikator eine feste Provision in Euro je angenommenem Angebot.
 * Golfplatz-Partner bekommen keine Codes; Rabatt und Provision gehen zulasten der
 * Firmengolf-Marge, der Platz erhält weiter sein volles Netto.
 *
 * Codes werden nur im WP-Backend angelegt (Menü „Partnercodes"). Post-Type ist nicht
 * öffentlich und nicht in der REST-API. Der Livecheck im Frontend ist rate-limitiert
 * und antwortet bei jedem Fehler gleich (kein Enumerieren von Codes).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Post-Type-Namen dürfen höchstens 20 Zeichen haben, deshalb nicht „firmengolf_partnercode".
const FGE_PC_POST_TYPE = 'fge_partnercode';

// ── Post-Type ────────────────────────────────────────────────────────────────

add_action( 'init', static function () {
	register_post_type( FGE_PC_POST_TYPE, [
		'labels'              => [
			'name'               => 'Partnercodes',
			'singular_name'      => 'Partnercode',
			'add_new'            => 'Neuer Partnercode',
			'add_new_item'       => 'Neuen Partnercode anlegen',
			'edit_item'          => 'Partnercode bearbeiten',
			'all_items'          => 'Partnercodes',
			'search_items'       => 'Partnercodes durchsuchen',
			'not_found'          => 'Keine Partnercodes vorhanden.',
			'not_found_in_trash' => 'Keine Partnercodes im Papierkorb.',
		],
		'public'              => false,
		'publicly_queryable'  => false,
		'exclude_from_search' => true,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_rest'        => false,
		'has_archive'         => false,
		'rewrite'             => false,
		'supports'            => [ 'title' ],
		'capability_type'     => 'post',
		'menu_icon'           => 'dashicons-tickets-alt',
		'menu_position'       => 23,
	] );
} );

add_filter( 'enter_title_here', static function ( string $title, WP_Post $post ): string {
	return FGE_PC_POST_TYPE === $post->post_type ? 'Inhaber, z. B. Deutscher Golf Verband' : $title;
}, 10, 2 );

// ── Helfer ───────────────────────────────────────────────────────────────────

/** Code normalisieren: Großbuchstaben, nur A-Z0-9, 4 bis 12 Zeichen, sonst ''. */
function fge_pc_normalize( string $raw ): string {
	$c = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $raw ) );
	$n = strlen( $c );
	return ( $n >= 4 && $n <= 12 ) ? $c : '';
}

/** Codevorschlag aus dem Inhabernamen (max. 8 Zeichen), Fallback FG plus 4 Zufallszeichen. */
function fge_pc_suggest_code( string $title ): string {
	$t = remove_accents( $title );
	$t = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $t ) );
	$t = substr( $t, 0, 8 );
	if ( strlen( $t ) < 4 ) {
		$t = 'FG' . strtoupper( wp_generate_password( 4, false, false ) );
	}
	return $t;
}

/** Code-Post zu einem normalisierten Code, 0 wenn unbekannt. */
function fge_pc_find( string $code ): int {
	if ( '' === $code ) {
		return 0;
	}
	$q = get_posts( [
		'post_type'   => FGE_PC_POST_TYPE,
		'post_status' => 'publish',
		'numberposts' => 1,
		'fields'      => 'ids',
		'meta_key'    => '_fge_pc_code', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value'  => $code, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	] );
	return $q ? (int) $q[0] : 0;
}

/** Aktiv und nicht abgelaufen? */
function fge_pc_is_usable( int $id ): bool {
	if ( $id <= 0 || 'publish' !== get_post_status( $id ) ) {
		return false;
	}
	if ( 'aktiv' !== ( (string) get_post_meta( $id, '_fge_pc_status', true ) ?: 'aktiv' ) ) {
		return false;
	}
	$until = (string) get_post_meta( $id, '_fge_pc_valid_until', true );
	return '' === $until || $until >= wp_date( 'Y-m-d' );
}

/** Rabattprozent eines Codes (0 bis 100). */
function fge_pc_percent( int $id ): int {
	$p = get_post_meta( $id, '_fge_pc_discount_percent', true );
	return max( 0, min( 100, (int) ( '' === $p ? 5 : $p ) ) );
}

/**
 * Rohe Eingabe auflösen. Gibt nie die Mailadresse des Inhabers zurück.
 *
 * @return array{ok:bool,id:int,code:string,holder:string,percent:int}
 */
function fge_pc_resolve( string $raw ): array {
	$none = [ 'ok' => false, 'id' => 0, 'code' => '', 'holder' => '', 'percent' => 0 ];
	$code = fge_pc_normalize( $raw );
	if ( '' === $code ) {
		return $none;
	}
	$id = fge_pc_find( $code );
	if ( $id <= 0 || ! fge_pc_is_usable( $id ) ) {
		return $none;
	}
	return [ 'ok' => true, 'id' => $id, 'code' => $code, 'holder' => get_the_title( $id ), 'percent' => fge_pc_percent( $id ) ];
}

/**
 * Code an eine Anfrage hängen (beim Eingang). Ungültige Eingabe wird gemerkt, blockiert aber nichts.
 *
 * @return array{ok:bool,code:string,holder:string,percent:int,invalid:bool}
 */
function fge_pc_attach_to_request( int $req, string $raw ): array {
	$raw = trim( $raw );
	if ( '' === $raw ) {
		delete_post_meta( $req, '_fge_partnercode' );
		delete_post_meta( $req, '_fge_partnercode_id' );
		delete_post_meta( $req, '_fge_partnercode_invalid' );
		return [ 'ok' => false, 'code' => '', 'holder' => '', 'percent' => 0, 'invalid' => false ];
	}
	$r = fge_pc_resolve( $raw );
	if ( $r['ok'] ) {
		update_post_meta( $req, '_fge_partnercode', $r['code'] );
		update_post_meta( $req, '_fge_partnercode_id', $r['id'] );
		delete_post_meta( $req, '_fge_partnercode_invalid' );
		return [ 'ok' => true, 'code' => $r['code'], 'holder' => $r['holder'], 'percent' => $r['percent'], 'invalid' => false ];
	}
	update_post_meta( $req, '_fge_partnercode_invalid', mb_substr( sanitize_text_field( $raw ), 0, 12 ) );
	delete_post_meta( $req, '_fge_partnercode' );
	delete_post_meta( $req, '_fge_partnercode_id' );
	return [ 'ok' => false, 'code' => '', 'holder' => '', 'percent' => 0, 'invalid' => true ];
}

/** Alles zum Code einer Anfrage an einer Stelle (Mails, Admin, Spalten). */
function fge_pc_request_summary( int $req ): array {
	$id = (int) get_post_meta( $req, '_fge_partnercode_id', true );
	return [
		'code'              => (string) get_post_meta( $req, '_fge_partnercode', true ),
		'code_id'           => $id,
		'holder'            => $id > 0 ? (string) get_the_title( $id ) : '',
		'percent'           => $id > 0 ? fge_pc_percent( $id ) : 0,
		'invalid'           => (string) get_post_meta( $req, '_fge_partnercode_invalid', true ),
		'commission_amount' => (float) get_post_meta( $req, '_fge_pc_commission_amount', true ),
		'commission_status' => (string) get_post_meta( $req, '_fge_pc_commission_status', true ),
	];
}

/** Rabattblock für den Angebots-Snapshot (Betrag füllt der Snapshot-Builder), oder null. */
function fge_pc_snapshot_block( int $req ): ?array {
	$id = (int) get_post_meta( $req, '_fge_partnercode_id', true );
	if ( $id <= 0 || ! fge_pc_is_usable( $id ) ) {
		return null;
	}
	$percent = fge_pc_percent( $id );
	if ( $percent <= 0 ) {
		return null;
	}
	return [
		'code'    => (string) get_post_meta( $req, '_fge_partnercode', true ),
		'holder'  => (string) get_the_title( $id ),
		'percent' => $percent,
		'amount'  => 0.0,
	];
}

/** Anfragen mit diesem Code (IDs, neueste zuerst). */
function fge_pc_requests( int $code_id ): array {
	return array_map( 'intval', get_posts( [
		'post_type'   => 'firmengolf_request',
		'post_status' => [ 'publish', 'draft' ],
		'numberposts' => -1,
		'fields'      => 'ids',
		'orderby'     => 'date',
		'order'       => 'DESC',
		'meta_key'    => '_fge_partnercode_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value'  => $code_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	] ) );
}

/** @return array{requests:int,bookings:int,open:float,settled:float,cancelled:float} */
function fge_pc_stats( int $code_id ): array {
	$s = [ 'requests' => 0, 'bookings' => 0, 'open' => 0.0, 'settled' => 0.0, 'cancelled' => 0.0 ];
	foreach ( fge_pc_requests( $code_id ) as $req ) {
		$s['requests']++;
		if ( 'accepted' === (string) get_post_meta( $req, '_fge_offer_status', true ) ) {
			$s['bookings']++;
		}
		$amt = (float) get_post_meta( $req, '_fge_pc_commission_amount', true );
		switch ( (string) get_post_meta( $req, '_fge_pc_commission_status', true ) ) {
			case 'offen':
				$s['open'] += $amt;
				break;
			case 'abgerechnet':
				$s['settled'] += $amt;
				break;
			case 'storniert':
				$s['cancelled'] += $amt;
				break;
		}
	}
	return $s;
}

// ── Livecheck (AJAX, auch ohne Login) ────────────────────────────────────────

add_action( 'wp_ajax_fge_pc_check', 'fge_ajax_pc_check' );
add_action( 'wp_ajax_nopriv_fge_pc_check', 'fge_ajax_pc_check' );
function fge_ajax_pc_check(): void {
	check_ajax_referer( 'fge_pc_check', 'nonce' );
	$generic = [ 'message' => 'Dieser Partnercode ist nicht gültig.' ];
	if ( function_exists( 'fge_form_rate_limited' ) && fge_form_rate_limited( 20, 600, 'pc_check' ) ) {
		wp_send_json_error( $generic, 429 );
	}
	$r = fge_pc_resolve( (string) wp_unslash( $_POST['code'] ?? '' ) );
	if ( ! $r['ok'] ) {
		wp_send_json_error( $generic, 422 );
	}
	wp_send_json_success( [ 'code' => $r['code'], 'holder' => $r['holder'], 'percent' => $r['percent'] ] );
}

// ── Provision ────────────────────────────────────────────────────────────────

// Bei Annahme: einmalig „offen" mit dem festen Betrag des Codes zum Zeitpunkt der Annahme.
add_action( 'fge_offer_accepted', static function ( int $req ): void {
	$id = (int) get_post_meta( $req, '_fge_partnercode_id', true );
	if ( $id <= 0 || FGE_PC_POST_TYPE !== get_post_type( $id ) ) {
		return;
	}
	if ( ! add_post_meta( $req, '_fge_pc_commission_status', 'offen', true ) ) {
		return; // schon verbucht
	}
	update_post_meta( $req, '_fge_pc_commission_amount', round( (float) get_post_meta( $id, '_fge_pc_commission_eur', true ), 2 ) );
}, 8 );

// Absage, verloren, kein Termin: offene Provision stornieren.
add_action( 'fge_request_status_changed', static function ( int $req, string $status ): void {
	if ( in_array( $status, [ 'angebot_abgelehnt', 'verloren', 'nicht_verfuegbar' ], true )
		&& 'offen' === (string) get_post_meta( $req, '_fge_pc_commission_status', true ) ) {
		update_post_meta( $req, '_fge_pc_commission_status', 'storniert' );
	}
}, 10, 2 );

// ── Admin-Aktionen: abrechnen ────────────────────────────────────────────────

function fge_pc_settle_request( int $req ): bool {
	if ( 'offen' !== (string) get_post_meta( $req, '_fge_pc_commission_status', true ) ) {
		return false;
	}
	update_post_meta( $req, '_fge_pc_commission_status', 'abgerechnet' );
	update_post_meta( $req, '_fge_pc_commission_settled_at', time() );
	return true;
}

add_action( 'admin_post_fge_pc_settle', static function () {
	$req     = absint( $_POST['request_id'] ?? 0 );
	$code_id = absint( $_POST['code_id'] ?? 0 );
	if ( $req <= 0 || ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Keine Berechtigung.', '', [ 'response' => 403 ] );
	}
	check_admin_referer( 'fge_pc_settle_' . $req );
	$n = fge_pc_settle_request( $req ) ? 1 : 0;
	wp_safe_redirect( add_query_arg( [ 'fge_pc_ok' => $n ], get_edit_post_link( $code_id ?: $req, 'raw' ) ) );
	exit;
} );

add_action( 'admin_post_fge_pc_settle_all', static function () {
	$code_id = absint( $_POST['code_id'] ?? 0 );
	if ( $code_id <= 0 || ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Keine Berechtigung.', '', [ 'response' => 403 ] );
	}
	check_admin_referer( 'fge_pc_settle_all_' . $code_id );
	$n = 0;
	foreach ( fge_pc_requests( $code_id ) as $req ) {
		$n += fge_pc_settle_request( $req ) ? 1 : 0;
	}
	wp_safe_redirect( add_query_arg( [ 'fge_pc_ok' => $n ], get_edit_post_link( $code_id, 'raw' ) ) );
	exit;
} );

add_action( 'admin_notices', static function () {
	if ( isset( $_GET['fge_pc_ok'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$n = (int) $_GET['fge_pc_ok']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $n > 0 ? $n . ' Provision(en) als abgerechnet markiert.' : 'Keine offene Provision zum Abrechnen.' ) . '</p></div>';
	}
	$key = 'fge_pc_notice_' . get_current_user_id();
	$msg = get_transient( $key );
	if ( $msg ) {
		delete_transient( $key );
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( (string) $msg ) . '</p></div>';
	}
} );

// ── Metaboxen am Code ────────────────────────────────────────────────────────

add_action( 'add_meta_boxes', static function () {
	add_meta_box( 'fge_pcmb_daten', 'Partnercode', 'fge_pc_render_mb_daten', FGE_PC_POST_TYPE, 'normal', 'high' );
	add_meta_box( 'fge_pcmb_stats', 'Statistik', 'fge_pc_render_mb_stats', FGE_PC_POST_TYPE, 'side', 'high' );
	add_meta_box( 'fge_pcmb_uebersicht', 'Anfragen und Provisionen', 'fge_pc_render_mb_uebersicht', FGE_PC_POST_TYPE, 'normal', 'default' );
} );

function fge_pc_render_mb_daten( WP_Post $post ): void {
	$m = static fn( string $k ): string => (string) get_post_meta( $post->ID, '_fge_pc_' . $k, true );
	$code    = $m( 'code' );
	$percent = $m( 'discount_percent' );
	$eur     = $m( 'commission_eur' );
	$status  = $m( 'status' ) ?: 'aktiv';
	wp_nonce_field( 'fge_pc_fields', 'fge_pc_nonce' );
	?>
	<p class="description" style="margin:0 0 10px;">Ein Code je Multiplikator (Content Creator, Verband, Agentur). Firmen geben ihn bei der Anfrage ein oder kommen über den Link aus der Statistik-Box. Der Kunde bekommt den Rabatt im Angebot, der Multiplikator die Provision je angenommenem Angebot. Beides geht zulasten der Firmengolf-Marge, der Golfplatz erhält sein volles Netto.</p>
	<table class="form-table" style="margin:0;">
		<tr><th scope="row" style="width:190px;"><label for="fge-pc-code">Code</label></th>
			<td><input type="text" id="fge-pc-code" name="fge_pc_code" value="<?php echo esc_attr( $code ); ?>" class="regular-text" maxlength="12" style="text-transform:uppercase;letter-spacing:.06em;font-weight:600;" placeholder="wird aus dem Inhaber vorgeschlagen">
			<p class="description">4 bis 12 Zeichen, nur Buchstaben und Ziffern, eindeutig. Leer lassen = Vorschlag aus dem Titel.</p></td></tr>
		<tr><th scope="row"><label for="fge-pc-contact-name">Ansprechpartner</label></th>
			<td><input type="text" id="fge-pc-contact-name" name="fge_pc_contact_name" value="<?php echo esc_attr( $m( 'contact_name' ) ); ?>" class="regular-text"></td></tr>
		<tr><th scope="row"><label for="fge-pc-contact-email">E-Mail</label></th>
			<td><input type="email" id="fge-pc-contact-email" name="fge_pc_contact_email" value="<?php echo esc_attr( $m( 'contact_email' ) ); ?>" class="regular-text">
			<p class="description">Nur intern, erscheint nie beim Kunden.</p></td></tr>
		<tr><th scope="row"><label for="fge-pc-percent">Rabatt für den Kunden</label></th>
			<td><input type="number" id="fge-pc-percent" name="fge_pc_discount_percent" value="<?php echo esc_attr( '' === $percent ? '5' : $percent ); ?>" min="0" max="100" step="1" style="width:90px;"> % auf die Netto-Zwischensumme des Angebots</td></tr>
		<tr><th scope="row"><label for="fge-pc-eur">Provision je Buchung</label></th>
			<td><input type="text" id="fge-pc-eur" name="fge_pc_commission_eur" value="<?php echo esc_attr( '' !== $eur ? number_format( (float) $eur, 2, ',', '.' ) : '' ); ?>" style="width:110px;" placeholder="z. B. 50,00"> € netto, fester Betrag je angenommenem Angebot</td></tr>
		<tr><th scope="row"><label for="fge-pc-status">Status</label></th>
			<td><select id="fge-pc-status" name="fge_pc_status">
				<option value="aktiv" <?php selected( $status, 'aktiv' ); ?>>aktiv</option>
				<option value="pausiert" <?php selected( $status, 'pausiert' ); ?>>pausiert</option>
			</select></td></tr>
		<tr><th scope="row"><label for="fge-pc-valid">Gültig bis</label></th>
			<td><input type="date" id="fge-pc-valid" name="fge_pc_valid_until" value="<?php echo esc_attr( $m( 'valid_until' ) ); ?>"> <span class="description">leer = unbegrenzt</span></td></tr>
		<tr><th scope="row"><label for="fge-pc-note">Interne Notiz</label></th>
			<td><textarea id="fge-pc-note" name="fge_pc_note" rows="3" class="large-text"><?php echo esc_textarea( $m( 'note' ) ); ?></textarea></td></tr>
	</table>
	<script>
	(function(){
		var t = document.getElementById('title'), c = document.getElementById('fge-pc-code');
		if (!t || !c) return;
		var touched = c.value !== '';
		c.addEventListener('input', function(){ touched = c.value !== ''; c.value = c.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 12); });
		t.addEventListener('input', function(){
			if (touched) return;
			var s = t.value.normalize('NFD').replace(/[̀-ͯ]/g, '').toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 8);
			c.placeholder = s.length >= 4 ? 'Vorschlag: ' + s : 'wird aus dem Inhaber vorgeschlagen';
		});
	})();
	</script>
	<?php
}

function fge_pc_render_mb_stats( WP_Post $post ): void {
	$code = (string) get_post_meta( $post->ID, '_fge_pc_code', true );
	$s    = fge_pc_stats( $post->ID );
	$link = '' !== $code ? home_url( '/?pc=' . rawurlencode( $code ) ) : '';
	echo '<p style="margin:0 0 8px;"><strong>Anfragen mit Code:</strong> ' . (int) $s['requests'] . '<br><strong>Buchungen:</strong> ' . (int) $s['bookings'] . '</p>';
	echo '<p style="margin:0 0 8px;"><strong>Provision offen:</strong> ' . esc_html( number_format_i18n( $s['open'], 2 ) ) . ' €<br><strong>abgerechnet:</strong> ' . esc_html( number_format_i18n( $s['settled'], 2 ) ) . ' €' . ( $s['cancelled'] > 0 ? '<br><strong>storniert:</strong> ' . esc_html( number_format_i18n( $s['cancelled'], 2 ) ) . ' €' : '' ) . '</p>';
	if ( '' !== $link ) {
		echo '<p style="margin:10px 0 4px;"><strong>Link zum Weitergeben</strong></p><input type="text" readonly value="' . esc_attr( $link ) . '" style="width:100%;" onclick="this.select();">';
		echo '<p class="description" style="margin:4px 0 0;">Der Code wird im Browser gemerkt und im Anfrage-Dialog vorausgefüllt.</p>';
	} else {
		echo '<p class="description">Nach dem ersten Speichern erscheint hier der Link zum Weitergeben.</p>';
	}
	echo '<p class="description" style="margin-top:10px;">Rabatt wird beim Angebotsversand eingefroren. Provision entsteht bei Annahme, solange der Code an der Anfrage hängt, und wird bei Absage storniert.</p>';
}

function fge_pc_render_mb_uebersicht( WP_Post $post ): void {
	$reqs = fge_pc_requests( $post->ID );
	if ( empty( $reqs ) ) {
		echo '<p class="description">Noch keine Anfrage mit diesem Code.</p>';
		return;
	}
	$labels = [ 'offen' => [ 'offen', '#9A6B12' ], 'abgerechnet' => [ 'abgerechnet', '#2C7A3D' ], 'storniert' => [ 'storniert', '#B4332B' ] ];
	$open   = 0;
	echo '<table class="widefat striped"><thead><tr><th>Nummer</th><th>Datum</th><th>Unternehmen</th><th>Anfrage</th><th>Angebot</th><th>Provision</th><th></th></tr></thead><tbody>';
	foreach ( $reqs as $req ) {
		$ref    = function_exists( 'fge_request_number' ) ? fge_request_number( $req ) : 'FG-' . $req;
		$cst    = (string) get_post_meta( $req, '_fge_pc_commission_status', true );
		$amt    = (float) get_post_meta( $req, '_fge_pc_commission_amount', true );
		$ost    = (string) get_post_meta( $req, '_fge_offer_status', true );
		$omap   = [ 'pending' => 'offen', 'accepted' => 'angenommen', 'declined' => 'abgelehnt' ];
		$badge  = $labels[ $cst ] ?? null;
		$open  += 'offen' === $cst ? 1 : 0;
		echo '<tr>';
		echo '<td><a href="' . esc_url( get_edit_post_link( $req ) ) . '"><strong>' . esc_html( $ref ) . '</strong></a></td>';
		echo '<td>' . esc_html( get_the_date( 'd.m.Y', $req ) ) . '</td>';
		echo '<td>' . esc_html( (string) get_post_meta( $req, '_fge_company_name', true ) ?: 'k. A.' ) . '</td>';
		echo '<td>' . esc_html( (string) get_post_meta( $req, '_fge_request_status', true ) ?: 'k. A.' ) . '</td>';
		echo '<td>' . esc_html( $omap[ $ost ] ?? 'noch keins' ) . '</td>';
		echo '<td>' . ( $badge ? esc_html( number_format_i18n( $amt, 2 ) ) . ' € <span style="display:inline-block;padding:1px 8px;border-radius:9px;color:#fff;font-size:11px;background:' . esc_attr( $badge[1] ) . ';">' . esc_html( $badge[0] ) . '</span>' : '<span style="color:#6C736E;">keine</span>' ) . '</td>';
		echo '<td>';
		if ( 'offen' === $cst ) {
			fge_admin_post_button( 'fge_pc_settle', [ 'request_id' => $req, 'code_id' => $post->ID ], 'Als abgerechnet markieren', [ 'class' => 'button button-small', 'nonce_action' => 'fge_pc_settle_' . $req ] );
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';
	if ( $open > 1 ) {
		echo '<p style="margin:10px 0 0;">';
		fge_admin_post_button( 'fge_pc_settle_all', [ 'code_id' => $post->ID ], 'Alle offenen (' . $open . ') als abgerechnet markieren', [ 'class' => 'button', 'confirm' => 'Alle offenen Provisionen dieses Codes als abgerechnet markieren?', 'nonce_action' => 'fge_pc_settle_all_' . $post->ID ] );
		echo '</p>';
	}
}

// ── Speichern ────────────────────────────────────────────────────────────────

add_action( 'save_post_' . FGE_PC_POST_TYPE, static function ( int $post_id ): void {
	if ( ! isset( $_POST['fge_pc_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fge_pc_nonce'] ) ), 'fge_pc_fields' )
		|| ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
		|| wp_is_post_revision( $post_id )
		|| ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$title = (string) get_post_field( 'post_title', $post_id );
	$old   = (string) get_post_meta( $post_id, '_fge_pc_code', true );
	$code  = fge_pc_normalize( (string) wp_unslash( $_POST['fge_pc_code'] ?? '' ) );
	if ( '' === $code ) {
		$code = $old ?: fge_pc_suggest_code( $title );
	}
	$other = fge_pc_find( $code );
	if ( $other > 0 && $other !== $post_id ) {
		set_transient( 'fge_pc_notice_' . get_current_user_id(), 'Der Code ' . $code . ' ist schon vergeben (' . get_the_title( $other ) . '). Der bisherige Code bleibt.', 60 );
		$code = $old ?: fge_pc_suggest_code( $title . wp_rand( 10, 99 ) );
	}
	update_post_meta( $post_id, '_fge_pc_code', $code );
	update_post_meta( $post_id, '_fge_pc_contact_name', sanitize_text_field( wp_unslash( $_POST['fge_pc_contact_name'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_pc_contact_email', sanitize_email( wp_unslash( $_POST['fge_pc_contact_email'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_pc_discount_percent', max( 0, min( 100, (int) ( $_POST['fge_pc_discount_percent'] ?? 5 ) ) ) );
	update_post_meta( $post_id, '_fge_pc_commission_eur', function_exists( 'fge_parse_de_amount' ) ? max( 0.0, fge_parse_de_amount( wp_unslash( $_POST['fge_pc_commission_eur'] ?? '' ) ) ) : (float) ( $_POST['fge_pc_commission_eur'] ?? 0 ) );
	update_post_meta( $post_id, '_fge_pc_status', 'pausiert' === ( $_POST['fge_pc_status'] ?? '' ) ? 'pausiert' : 'aktiv' );
	$until = sanitize_text_field( wp_unslash( $_POST['fge_pc_valid_until'] ?? '' ) );
	update_post_meta( $post_id, '_fge_pc_valid_until', preg_match( '/^\d{4}-\d{2}-\d{2}$/', $until ) ? $until : '' );
	update_post_meta( $post_id, '_fge_pc_note', sanitize_textarea_field( wp_unslash( $_POST['fge_pc_note'] ?? '' ) ) );
} );

// ── Listenspalten der Codes ──────────────────────────────────────────────────

add_filter( 'manage_' . FGE_PC_POST_TYPE . '_posts_columns', static function ( array $c ): array {
	unset( $c['date'] );
	return array_merge( $c, [
		'fge_pc_code'     => 'Code',
		'fge_pc_status'   => 'Status',
		'fge_pc_percent'  => 'Rabatt',
		'fge_pc_eur'      => 'Provision',
		'fge_pc_requests' => 'Anfragen',
		'fge_pc_bookings' => 'Buchungen',
		'fge_pc_open'     => 'Provision offen',
	] );
} );

add_action( 'manage_' . FGE_PC_POST_TYPE . '_posts_custom_column', static function ( string $col, int $id ): void {
	static $cache = [];
	if ( ! isset( $cache[ $id ] ) ) {
		$cache[ $id ] = fge_pc_stats( $id );
	}
	$s = $cache[ $id ];
	switch ( $col ) {
		case 'fge_pc_code':
			echo '<strong style="letter-spacing:.05em;">' . esc_html( (string) get_post_meta( $id, '_fge_pc_code', true ) ) . '</strong>';
			break;
		case 'fge_pc_status':
			$st = (string) get_post_meta( $id, '_fge_pc_status', true ) ?: 'aktiv';
			echo esc_html( $st ) . ( fge_pc_is_usable( $id ) ? '' : ' <span style="color:#B4332B;">(nicht nutzbar)</span>' );
			break;
		case 'fge_pc_percent':
			echo esc_html( fge_pc_percent( $id ) ) . ' %';
			break;
		case 'fge_pc_eur':
			echo esc_html( number_format_i18n( (float) get_post_meta( $id, '_fge_pc_commission_eur', true ), 2 ) ) . ' €';
			break;
		case 'fge_pc_requests':
			echo (int) $s['requests'];
			break;
		case 'fge_pc_bookings':
			echo (int) $s['bookings'];
			break;
		case 'fge_pc_open':
			echo esc_html( number_format_i18n( $s['open'], 2 ) ) . ' €';
			break;
	}
}, 10, 2 );
