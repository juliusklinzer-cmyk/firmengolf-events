<?php
/**
 * Angebots-Automatik: Sobald ein Termin bestätigt ist, wird automatisch ein
 * Angebot erzeugt (Event-Preis + Kundenwünsche als Posten) und dem Kunden per
 * Mail mit Annehmen/Ablehnen-Link geschickt. Der Kunde hat einen Magic-Link
 * (/angebot/<token>/), der zugleich als read-only Status-Seite dient.
 *
 * Eine Statusquelle: fge_request_set_status() schreibt _fge_request_status an
 * allen Übergängen und feuert fge_request_status_changed.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Eine Statusquelle ─────────────────────────────────────────────────────────

/** Setzt den Lebenszyklus-Status einer Anfrage (validiert, mit Zeitstempel + Hook). */
function fge_request_set_status( int $req, string $status ): void {
	if ( ! in_array( $status, fge_get_statuses( 'request' ), true ) ) {
		return;
	}
	$old = (string) get_post_meta( $req, '_fge_request_status', true );
	if ( $old === $status ) {
		return;
	}
	update_post_meta( $req, '_fge_request_status', $status );
	update_post_meta( $req, '_fge_last_status_change', current_datetime()->format( 'Y-m-d H:i:s' ) );
	do_action( 'fge_request_status_changed', $req, $status, $old );
}

// ── Kunden-Magic-Link ─────────────────────────────────────────────────────────

/** Kunden-Token, lazy erzeugt. */
function fge_request_customer_token( int $req ): string {
	$t = (string) get_post_meta( $req, '_fge_customer_token', true );
	if ( '' === $t ) {
		$t = bin2hex( random_bytes( 20 ) );
		update_post_meta( $req, '_fge_customer_token', $t );
	}
	return $t;
}

function fge_request_by_customer_token( string $token ): int {
	if ( '' === $token ) {
		return 0;
	}
	$q = get_posts( [
		'post_type'   => 'firmengolf_request',
		'post_status' => 'any',
		'numberposts' => 1,
		'fields'      => 'ids',
		'meta_key'    => '_fge_customer_token', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value'  => $token, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	] );
	return $q ? (int) $q[0] : 0;
}

/** Öffentliche Angebots-/Status-URL für den Kunden. */
function fge_offer_link( int $req ): string {
	return home_url( '/angebot/' . fge_request_customer_token( $req ) . '/' );
}

// ── Angebot bauen ─────────────────────────────────────────────────────────────

/** Snapshot des Angebots aus Event-Preis + Wünschen. */
function fge_build_offer_snapshot( int $req, int $date_index ): array {
	$event_id = (int) get_post_meta( $req, '_fge_assigned_event_id', true );
	$pricing  = ( $event_id && function_exists( 'fge_event_pricing' ) ) ? fge_event_pricing( $event_id ) : [ 'gross' => 0, 'unit' => '' ];
	$pax      = (int) get_post_meta( $req, '_fge_expected_participants', true );

	$includes = $event_id ? get_post_meta( $event_id, '_fge_event_includes', true ) : [];
	$includes = is_array( $includes ) ? $includes : array_filter( preg_split( '/\r\n|\r|\n/', (string) $includes ) );

	$g = function_exists( 'fge_request_wish_groups' ) ? fge_request_wish_groups( $req ) : [ 'platz' => [], 'firmengolf' => [] ];

	$gross = (float) ( $pricing['gross'] ?? 0 );
	$unit  = (string) ( $pricing['unit'] ?? '' );
	$total = ( 'pro Person' === $unit && $pax > 0 ) ? $gross * $pax : $gross;

	return [
		'event_title'       => $event_id ? get_the_title( $event_id ) : ( (string) get_post_meta( $req, '_fge_event_type', true ) ?: 'Firmen-Event' ),
		'date'              => (string) get_post_meta( $req, '_fge_preferred_date_' . $date_index, true ),
		'participants'      => $pax,
		'price_gross'       => $gross,
		'price_unit'        => $unit,
		'price_total'       => $total,
		'includes'          => array_values( array_filter( array_map( 'strval', (array) $includes ) ) ),
		'wishes_platz'      => array_values( (array) ( $g['platz'] ?? [] ) ),
		'wishes_firmengolf' => array_values( (array) ( $g['firmengolf'] ?? [] ) ),
	];
}

/** Preis als Text fürs Angebot. */
function fge_offer_price_text( array $snap ): string {
	$gross = (float) ( $snap['price_gross'] ?? 0 );
	if ( $gross <= 0 ) {
		return 'Auf Anfrage';
	}
	$unit = (string) ( $snap['price_unit'] ?? '' );
	$s    = '€' . number_format_i18n( $gross, 0 ) . ( 'pro Person' === $unit ? ' p.P.' : ' gesamt' );
	if ( 'pro Person' === $unit && (int) ( $snap['participants'] ?? 0 ) > 0 ) {
		$s .= ' · ca. €' . number_format_i18n( (float) $snap['price_total'], 0 ) . ' bei ' . (int) $snap['participants'] . ' Personen';
	}
	return $s;
}

// ── Auslöser: Termin bestätigt → Angebot erzeugen + senden ────────────────────

add_action( 'fge_request_date_confirmed', 'fge_offer_on_date_confirmed', 20, 2 );
function fge_offer_on_date_confirmed( int $req, int $date_index ): void {
	if ( '1' === (string) get_post_meta( $req, '_fge_offer_sent', true ) ) {
		return; // einmalig
	}
	$snap = fge_build_offer_snapshot( $req, $date_index );
	update_post_meta( $req, '_fge_offer_snapshot', $snap );
	update_post_meta( $req, '_fge_offer_date_index', $date_index );
	update_post_meta( $req, '_fge_offer_status', 'pending' );
	$days = (int) apply_filters( 'fge_offer_response_days', 7 );
	update_post_meta( $req, '_fge_offer_deadline', time() + $days * DAY_IN_SECONDS );
	update_post_meta( $req, '_fge_offer_sent', 1 );

	fge_send_offer_email( $req );
	fge_request_set_status( $req, 'angebot_versendet' );
}

// ── Routing: /angebot/<token>/ ────────────────────────────────────────────────

add_action( 'init', static function () {
	add_rewrite_rule( '^angebot/([^/]+)/?$', 'index.php?fge_angebot=$matches[1]', 'top' );
} );
add_filter( 'query_vars', static function ( array $vars ): array {
	$vars[] = 'fge_angebot';
	return $vars;
} );
add_filter( 'template_include', static function ( $template ) {
	if ( get_query_var( 'fge_angebot' ) ) {
		$t = locate_template( 'template-angebot.php' );
		return $t ?: $template;
	}
	return $template;
} );
add_action( 'init', static function () {
	$rules = get_option( 'rewrite_rules' );
	if ( is_array( $rules ) && ! isset( $rules['^angebot/([^/]+)/?$'] ) ) {
		flush_rewrite_rules( false );
	}
}, 99 );

/** Resolve token → Daten fürs Template, oder null. */
function fge_offer_resolve( string $token ): ?array {
	$req = fge_request_by_customer_token( $token );
	if ( $req <= 0 ) {
		return null;
	}
	return [ 'req' => $req ];
}

// ── Annahme / Ablehnung (POST vom /angebot/-Template) ─────────────────────────

add_action( 'init', 'fge_offer_handle_post' );
function fge_offer_handle_post(): void {
	$action = sanitize_key( $_POST['fge_offer_action'] ?? '' );
	if ( '' === $action ) {
		return;
	}
	$token = sanitize_text_field( wp_unslash( $_POST['fge_offer_token'] ?? '' ) );
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fge_offer_nonce'] ?? '' ) ), 'fge_offer_' . $token ) ) {
		return;
	}
	$req = fge_request_by_customer_token( $token );
	if ( $req <= 0 ) {
		return;
	}
	if ( 'pending' === (string) get_post_meta( $req, '_fge_offer_status', true ) ) {
		if ( 'accept' === $action ) {
			update_post_meta( $req, '_fge_offer_status', 'accepted' );
			fge_request_set_status( $req, 'angebot_angenommen' );
			do_action( 'fge_offer_accepted', $req );
		} elseif ( 'decline' === $action ) {
			update_post_meta( $req, '_fge_offer_status', 'declined' );
			fge_request_set_status( $req, 'angebot_abgelehnt' );
			do_action( 'fge_offer_declined', $req );
		}
	}
	wp_safe_redirect( fge_offer_link( $req ) . '?done=1' );
	exit;
}
