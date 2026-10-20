<?php
/**
 * Control Center: Partner und Events (Entwurf „Partner“, Julius 15.10.2026).
 *
 * Partner: Liste aller echten Partner (ohne DGV-Stammdaten) plus Dienstleister,
 * oben die Anmeldungen zur Freigabe. Detail mit Events, Buchungen und
 * Stammdaten. Prüfansicht zeigt die echte Partnerseite mit automatischen
 * Checks, drei Haken von Hand und Freigeben, Rückfrage oder Ablehnen.
 *
 * Events: alle Events mit Freigabe-Liste. Rückfrage ist kein eigener Status,
 * das Event bleibt in Prüfung und trägt die Nachricht (_fge_review_message).
 *
 * Status und Mails laufen über fge_partner_apply_status() und
 * fge_event_apply_status() (admin-approval.php), denselben Weg wie im WordPress.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Kataloge ─────────────────────────────────────────────────────────────────

/** Partnerarten wie im Entwurf, dl = Dienstleister (eigener Beitragstyp). */
function fge_cc_partner_kinds(): array {
	return [
		'course' => 'Golfplatz',
		'coach'  => 'Golflehrer',
		'indoor' => 'Indoor-Anlage',
		'dl'     => 'Dienstleister',
	];
}

/** Partnerstatus: Label und Ton der Pille. */
function fge_cc_pstatus(): array {
	return [
		'aktiv'       => [ 'Aktiv', 'good' ],
		'in_pruefung' => [ 'In Prüfung', 'warn' ],
		'rueckfragen' => [ 'Rückfrage offen', 'brand' ],
		'pausiert'    => [ 'Pausiert', 'neutral' ],
		'abgelehnt'   => [ 'Abgelehnt', 'bad' ],
	];
}

/** Eventstatus mit Rückfrage als Anzeige: [Schlüssel, Label, Ton]. */
function fge_cc_estatus( int $eid ): array {
	$st  = (string) get_post_meta( $eid, '_fge_event_status', true ) ?: 'entwurf';
	$ask = '' !== (string) get_post_meta( $eid, '_fge_review_message', true );
	if ( $ask && in_array( $st, [ 'zur_pruefung', 'aenderung_in_pruefung' ], true ) ) {
		return [ 'rueckfrage', 'Rückfrage offen', 'brand' ];
	}
	$map = [
		'freigegeben'           => [ 'Live', 'good' ],
		'zur_pruefung'          => [ 'Zur Prüfung', 'warn' ],
		'aenderung_in_pruefung' => [ 'Änderung in Prüfung', 'warn' ],
		'entwurf'               => [ 'Entwurf', 'neutral' ],
		'pausiert'              => [ 'Pausiert', 'neutral' ],
		'abgelehnt'             => [ 'Abgelehnt', 'bad' ],
	];
	return array_merge( [ $st ], $map[ $st ] ?? [ $st, 'neutral' ] );
}

/** Schnelltexte für Rückfragen in der Prüfansicht. */
function fge_cc_review_quicktexts(): array {
	return [
		'Bessere Fotos'         => 'Bitte lade noch Fotos in höherer Qualität hoch, am besten vom Platz bei Tageslicht.',
		'Beschreibung ergänzen' => 'Bitte ergänze die Beschreibung um ein paar Sätze zu Platz und Clubhaus.',
		'Adresse prüfen'        => 'Bitte prüfe die Adresse, sie lässt sich so nicht finden.',
	];
}

// ── Daten ────────────────────────────────────────────────────────────────────

function fge_cc_partner_name( int $pid ): string {
	return (string) get_post_meta( $pid, '_fge_public_golfclub_name', true ) ?: get_the_title( $pid );
}

/** Ein Partner als Zeile, gleiche Form für Plätze, Lehrer, Indoor und Dienstleister. */
function fge_cc_partner_row_data( int $pid ): array {
	if ( 'firmengolf_provider' === get_post_type( $pid ) ) {
		$types = function_exists( 'fge_provider_types' ) ? fge_provider_types() : [];
		$t     = (string) get_post_meta( $pid, '_fge_provider_type', true );
		return [
			'id'        => $pid,
			'kind'      => 'dl',
			'name'      => get_the_title( $pid ),
			'city'      => (string) get_post_meta( $pid, '_fge_provider_region', true ),
			'cat'       => (string) ( $types[ $t ] ?? '' ),
			'contact'   => (string) get_post_meta( $pid, '_fge_provider_contact', true ),
			'mail'      => (string) get_post_meta( $pid, '_fge_provider_email', true ),
			'phone'     => (string) get_post_meta( $pid, '_fge_provider_phone', true ),
			'status'    => 'aktiv',
			'since'     => (int) get_post_time( 'U', true, $pid ),
			'submitted' => 0,
		];
	}
	$since = (string) get_post_meta( $pid, '_fge_partner_since', true );
	return [
		'id'        => $pid,
		'kind'      => function_exists( 'fge_partner_type' ) ? fge_partner_type( $pid ) : 'course',
		'name'      => fge_cc_partner_name( $pid ),
		'city'      => (string) get_post_meta( $pid, '_fge_city', true ),
		'cat'       => '',
		'contact'   => (string) get_post_meta( $pid, '_fge_main_contact_name', true ),
		'mail'      => (string) get_post_meta( $pid, '_fge_main_contact_email', true ),
		'phone'     => (string) get_post_meta( $pid, '_fge_main_contact_phone', true ),
		'status'    => (string) get_post_meta( $pid, '_fge_partner_status', true ) ?: 'in_pruefung',
		'since'     => '' !== $since ? (int) strtotime( $since ) : 0,
		'submitted' => (int) strtotime( (string) get_post_meta( $pid, '_fge_submitted_at', true ) ),
	];
}

/** Alle Partner ohne DGV-Stammdaten, dazu alle Dienstleister. */
function fge_cc_partner_list(): array {
	$ids = get_posts( [
		'post_type'   => 'firmengolf_partner',
		'post_status' => [ 'publish', 'draft', 'pending', 'private' ],
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_query'  => [ [ 'key' => '_fge_partner_status', 'value' => 'stammdaten', 'compare' => '!=' ] ], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	] );
	$dl  = get_posts( [ 'post_type' => 'firmengolf_provider', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ] );
	$out = [];
	foreach ( array_merge( $ids, $dl ) as $id ) {
		$out[ (int) $id ] = fge_cc_partner_row_data( (int) $id );
	}
	uasort( $out, static fn( $a, $b ) => strcasecmp( $a['name'], $b['name'] ) );
	return $out;
}

/** Alle Events, je Partner gruppiert: [partner_id => [event_id, …]], 0 = Firmengolf. */
function fge_cc_events_by_partner(): array {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$rows  = $wpdb->get_results(
		"SELECT p.ID, COALESCE(m.meta_value, '0') AS pid FROM {$wpdb->posts} p
		 LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_fge_assigned_partner_id'
		 WHERE p.post_type = 'firmengolf_event' AND p.post_status NOT IN ('trash','auto-draft','inherit')
		 ORDER BY p.post_title ASC",
		ARRAY_A
	);
	$cache = [];
	foreach ( $rows as $r ) {
		$cache[ (int) $r['pid'] ][] = (int) $r['ID'];
	}
	return $cache;
}

/** Live heißt öffentlich sichtbar: freigegeben oder Änderung in Prüfung. */
function fge_cc_event_is_live( int $eid ): bool {
	return in_array( (string) get_post_meta( $eid, '_fge_event_status', true ), [ 'freigegeben', 'aenderung_in_pruefung' ], true );
}

/**
 * Gebuchte Vorgänge je Partner und je Dienstleister, einmal je Seitenaufruf.
 * Umsatz und Marge wie unter Finanzen: Angebot netto minus Einkauf netto.
 * Beim Dienstleister zählen nur seine Positionen.
 */
function fge_cc_bookings_index(): array {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$cache = [ 'p' => [], 'dl' => [] ];
	$res   = fge_cc_query_requests( [ 'meta_query' => [ [ 'key' => '_fge_offer_status', 'value' => 'accepted' ] ] ], 1500 ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	foreach ( $res['ids'] as $req ) {
		$base = [
			'req'     => (int) $req,
			'ref'     => fge_request_number( $req ),
			'company' => (string) get_post_meta( $req, '_fge_company_name', true ),
			'date'    => fge_cc_event_date( $req ),
		];
		$pid = (int) get_post_meta( $req, '_fge_assigned_partner_id', true );
		if ( $pid > 0 ) {
			$net  = fge_cc_offer_net( $req );
			$cost = fge_cc_partner_cost_net( $req ) + fge_cc_extras_cost_net( $req );
			$cache['p'][ $pid ][] = $base + [ 'net' => $net, 'margin' => $cost > 0 ? $net - $cost : null ];
		}
		if ( ! function_exists( 'fge_venues_get' ) ) {
			continue;
		}
		foreach ( fge_venues_get( $req, 'dl' ) as $row ) {
			if ( 'gewaehlt' !== (string) $row['status'] ) {
				continue;
			}
			[ $net, $cost ] = fge_cc_provider_booking_sums( $req, (int) $row['id'] );
			$cache['dl'][ (int) $row['partner_id'] ][] = $base + [ 'net' => $net, 'margin' => $cost > 0 ? $net - $cost : null ];
		}
	}
	return $cache;
}

/** Verkauf und Einkauf netto der gewählten Positionen eines Dienstleisters. */
function fge_cc_provider_booking_sums( int $req, int $row_id ): array {
	$sel  = function_exists( 'fge_offer_selected_extras' ) ? fge_offer_selected_extras( $req ) : [];
	$snap = (array) get_post_meta( $req, '_fge_offer_snapshot', true );
	$pax  = max( 1, (int) ( $snap['participants'] ?? 0 ) );
	$raw  = (array) get_post_meta( $req, '_fge_extra_services', true );
	$mine = [];
	foreach ( $raw as $i => $x ) {
		if ( is_array( $x ) && (int) ( $x['provider_row'] ?? 0 ) === $row_id ) {
			$mine[ (int) ( $x['id'] ?? 0 ) ?: (int) $i ] = true;
		}
	}
	$net = $cost = 0.0;
	foreach ( function_exists( 'fge_extra_services' ) ? fge_extra_services( $req ) : [] as $it ) {
		$id = (int) $it['id'];
		if ( ! isset( $mine[ $id ] ) || ! in_array( $id, $sel, true ) ) {
			continue;
		}
		$q     = 'person' === (string) $it['basis'] ? $pax : ( 'verbrauch' === (string) $it['basis'] ? 0 : 1 );
		$net  += ( function_exists( 'fge_xs_sale_price' ) ? fge_xs_sale_price( $it ) : 0.0 ) * $q;
		$cost += ( function_exists( 'fge_xs_cost_net' ) ? fge_xs_cost_net( $it ) : 0.0 ) * $q;
	}
	return [ $net, $cost ];
}

/** Automatische Checks der Prüfansicht: [Text, erfüllt]. */
function fge_cc_partner_checks( int $pid ): array {
	$desc    = trim( wp_strip_all_tags( (string) get_post_field( 'post_content', $pid ) ) );
	$desc    = '' !== $desc ? $desc : trim( (string) get_post_meta( $pid, '_fge_public_short_description', true ) );
	$gallery = array_filter( array_map( 'absint', explode( ',', (string) get_post_meta( $pid, '_fge_gallery_attachment_ids', true ) ) ) );
	$photos  = count( $gallery ) + ( (int) get_post_meta( $pid, '_fge_hero_image_attachment_id', true ) > 0 ? 1 : 0 );
	$amen    = count( (array) get_post_meta( $pid, '_fge_infra', true ) ) + count( (array) get_post_meta( $pid, '_fge_cap', true ) );
	$events  = count( fge_cc_events_by_partner()[ $pid ] ?? [] );
	return [
		[ 'Beschreibung mit mindestens 200 Zeichen', mb_strlen( $desc ) >= 200 ],
		[ 'Mindestens 3 Fotos', $photos >= 3 ],
		[ 'Logo', (int) get_post_meta( $pid, '_fge_logo_attachment_id', true ) > 0 ],
		[ 'Adresse', '' !== trim( (string) get_post_meta( $pid, '_fge_street', true ) ) && '' !== trim( (string) get_post_meta( $pid, '_fge_city', true ) ) ],
		[ 'Telefon und E-Mail', '' !== trim( (string) get_post_meta( $pid, '_fge_main_contact_phone', true ) ) && is_email( (string) get_post_meta( $pid, '_fge_main_contact_email', true ) ) ],
		[ 'Mindestens 5 Ausstattungsmerkmale', $amen >= 5 ],
		[ 'Mindestens ein Event', $events > 0 ],
	];
}

function fge_cc_money( float $v ): string {
	return number_format_i18n( $v, abs( $v - round( $v ) ) > 0.004 ? 2 : 0 ) . ' €';
}

/** Öffentliche Adresse eines Partners oder Events, Vorschau-Link solange nicht veröffentlicht. */
function fge_cc_public_link( int $id ): string {
	return 'publish' === get_post_status( $id ) ? (string) get_permalink( $id ) : (string) get_preview_post_link( $id );
}

/** Ist das hier die eingebettete Seite der Prüfansicht? Dann ohne Admin-Leiste und Cookie-Banner. */
function fge_cc_is_review_frame(): bool {
	return isset( $_GET['fge_cc_preview'] ) && current_user_can( 'manage_options' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
}
add_filter( 'show_admin_bar', static fn( $show ) => fge_cc_is_review_frame() ? false : $show, 99 );
add_action( 'wp_head', static function (): void {
	if ( fge_cc_is_review_frame() ) {
		echo '<style>#klaro,.klaro{display:none!important}</style>';
	}
}, 99 );

// ── Aktionen ─────────────────────────────────────────────────────────────────

function fge_cc_partner_messages(): array {
	return [
		'p_approved'  => [ 'ok', 'Freigegeben. Die Willkommensmail ist raus.' ],
		'p_asked'     => [ 'ok', 'Rückfrage gesendet. Die Anmeldung bleibt in Prüfung.' ],
		'p_rejected'  => [ 'ok', 'Abgelehnt. Der Partner bekommt deine Nachricht per Mail.' ],
		'p_needmsg'   => [ 'err', 'Für Rückfrage oder Ablehnung bitte eine Nachricht an den Partner schreiben.' ],
		'p_needcheck' => [ 'err', 'Vor der Freigabe alle drei Punkte unter „Deine Prüfung“ abhaken.' ],
		'p_paused'    => [ 'ok', 'Pausiert. Die Events sind offline.' ],
		'p_active'    => [ 'ok', 'Wieder aktiv. Die pausierten Events sind zurück.' ],
		'p_saved'     => [ 'ok', 'Stammdaten gespeichert.' ],
		'p_created'   => [ 'ok', 'Partner angelegt.' ],
		'p_invalid'   => [ 'err', 'Bitte Name, Ort und eine Mailadresse oder Telefonnummer angeben.' ],
		'e_saved'     => [ 'ok', 'Event gespeichert.' ],
		'e_created'   => [ 'ok', 'Event angelegt.' ],
		'e_invalid'   => [ 'err', 'Bitte einen Titel angeben.' ],
		'e_approved'  => [ 'ok', 'Event freigegeben. Hatte der Partner es pausiert, bleibt es pausiert.' ],
		'e_asked'     => [ 'ok', 'Rückfrage gesendet. Das Event bleibt in Prüfung.' ],
		'e_rejected'  => [ 'ok', 'Event abgelehnt. Der Partner bekommt deine Nachricht per Mail.' ],
	];
}

/** Rechte und Nonce für Partner- und Event-Aktionen, liefert die Objekt-ID (0 = neu). */
function fge_cc_obj_guard( string $action ): int {
	$id = absint( $_POST['object_id'] ?? 0 );
	if ( ! fge_cc_can() ) {
		wp_die( 'Keine Berechtigung.', '', [ 'response' => 403 ] );
	}
	check_admin_referer( $action . '_' . $id );
	return $id;
}

function fge_cc_obj_back( string $page, array $args, string $msg ): void {
	wp_safe_redirect( fge_cc_url( $page, $args + [ 'msg' => $msg ] ) );
	exit;
}

/** Kleines POST-Formular für eine Partner- oder Event-Aktion. */
function fge_cc_obj_form_open( string $action, int $id, string $class = 'cc-form', string $form_id = '' ): void {
	echo '<form class="' . esc_attr( $class ) . '"' . ( '' !== $form_id ? ' id="' . esc_attr( $form_id ) . '"' : '' ) . ' method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '"><input type="hidden" name="object_id" value="' . (int) $id . '">';
	wp_nonce_field( $action . '_' . $id );
}

// Prüfansicht: freigeben, Rückfrage, ablehnen.
add_action( 'admin_post_fge_cc_partner_review', static function (): void {
	$pid = fge_cc_obj_guard( 'fge_cc_partner_review' );
	if ( 'firmengolf_partner' !== get_post_type( $pid ) ) {
		wp_die( 'Unbekannter Partner.', '', [ 'response' => 400 ] );
	}
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- fge_cc_obj_guard prüft die Nonce.
	$decision = sanitize_key( wp_unslash( $_POST['decision'] ?? '' ) );
	$msg      = trim( sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) ) );
	$checks   = array_filter( (array) ( $_POST['check'] ?? [] ) );
	// phpcs:enable
	$back = [ 'pid' => $pid, 'pruefen' => 1 ];
	if ( 'aktiv' === $decision ) {
		if ( count( $checks ) < 3 ) {
			fge_cc_obj_back( 'partner', $back, 'p_needcheck' );
		}
		fge_partner_apply_status( $pid, 'aktiv' );
		fge_cc_obj_back( 'partner', [ 'pid' => $pid ], 'p_approved' );
	}
	if ( in_array( $decision, [ 'rueckfragen', 'abgelehnt' ], true ) ) {
		if ( '' === $msg ) {
			fge_cc_obj_back( 'partner', $back, 'p_needmsg' );
		}
		fge_partner_apply_status( $pid, $decision, $msg );
		fge_cc_obj_back( 'partner', 'abgelehnt' === $decision ? [ 'pid' => $pid ] : $back, 'abgelehnt' === $decision ? 'p_rejected' : 'p_asked' );
	}
	fge_cc_obj_back( 'partner', $back, 'nothing' );
} );

// Aktiv oder pausiert, die Events ziehen über die Kaskade mit.
add_action( 'admin_post_fge_cc_partner_pstatus', static function (): void {
	$pid = fge_cc_obj_guard( 'fge_cc_partner_pstatus' );
	$new = sanitize_key( wp_unslash( $_POST['status'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$old = (string) get_post_meta( $pid, '_fge_partner_status', true );
	if ( 'firmengolf_partner' !== get_post_type( $pid ) || ! in_array( $new, [ 'aktiv', 'pausiert' ], true ) || ! in_array( $old, [ 'aktiv', 'pausiert' ], true ) || $old === $new ) {
		fge_cc_obj_back( 'partner', [ 'pid' => $pid ], 'nothing' );
	}
	fge_partner_apply_status( $pid, $new );
	fge_cc_obj_back( 'partner', [ 'pid' => $pid ], 'pausiert' === $new ? 'p_paused' : 'p_active' );
} );

/** Stammdaten-Felder im Control Center: Formularname => [Label, Meta-Key, Typ]. */
function fge_cc_partner_fields(): array {
	return [
		'name'     => [ 'Name', '_fge_public_golfclub_name', 'text' ],
		'contact'  => [ 'Ansprechpartner', '_fge_main_contact_name', 'text' ],
		'phone'    => [ 'Telefon', '_fge_main_contact_phone', 'text' ],
		'mail'     => [ 'E-Mail', '_fge_main_contact_email', 'email' ],
		'street'   => [ 'Straße', '_fge_street', 'text' ],
		'house'    => [ 'Hausnummer', '_fge_house_number', 'text' ],
		'zip'      => [ 'PLZ', '_fge_postal_code', 'text' ],
		'city'     => [ 'Ort', '_fge_city', 'text' ],
		'web'      => [ 'Website', '_fge_website_url', 'url' ],
		'since'    => [ 'Partner seit', '_fge_partner_since', 'date' ],
		'note'     => [ 'Interne Notiz', '_fge_internal_note', 'textarea' ],
	];
}

add_action( 'admin_post_fge_cc_partner_save', static function (): void {
	$pid = fge_cc_obj_guard( 'fge_cc_partner_save' );
	if ( 'firmengolf_partner' !== get_post_type( $pid ) ) {
		wp_die( 'Unbekannter Partner.', '', [ 'response' => 400 ] );
	}
	foreach ( fge_cc_partner_fields() as $k => [ , $meta, $type ] ) {
		if ( ! isset( $_POST[ $k ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			continue;
		}
		$raw = wp_unslash( $_POST[ $k ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$val = 'textarea' === $type ? sanitize_textarea_field( $raw ) : ( 'email' === $type ? sanitize_email( $raw ) : ( 'url' === $type ? esc_url_raw( trim( $raw ) ) : sanitize_text_field( $raw ) ) );
		update_post_meta( $pid, $meta, trim( $val ) );
		if ( 'name' === $k && '' !== trim( $val ) ) {
			wp_update_post( [ 'ID' => $pid, 'post_title' => trim( $val ) ] );
		}
	}
	fge_cc_obj_back( 'partner', [ 'pid' => $pid, 'tab' => 'st' ], 'p_saved' );
} );

// Partner von Hand anlegen. Golfplatz, Lehrer, Indoor werden Partner, Dienstleister Verzeichnis-Einträge.
add_action( 'admin_post_fge_cc_partner_create', static function (): void {
	fge_cc_obj_guard( 'fge_cc_partner_create' );
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- fge_cc_obj_guard prüft die Nonce.
	$kind    = sanitize_key( wp_unslash( $_POST['kind'] ?? 'course' ) );
	$name    = trim( sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ) );
	$city    = trim( sanitize_text_field( wp_unslash( $_POST['city'] ?? '' ) ) );
	$contact = trim( sanitize_text_field( wp_unslash( $_POST['contact'] ?? '' ) ) );
	$mail    = sanitize_email( wp_unslash( $_POST['mail'] ?? '' ) );
	$phone   = trim( sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ) );
	$cat     = sanitize_key( wp_unslash( $_POST['cat'] ?? 'sonstige' ) );
	// phpcs:enable
	if ( '' === $name || '' === $city || ( '' === $mail && '' === $phone ) || ! isset( fge_cc_partner_kinds()[ $kind ] ) ) {
		fge_cc_obj_back( 'partner', [ 'neu' => 1 ], 'p_invalid' );
	}
	if ( 'dl' === $kind ) {
		$id = (int) wp_insert_post( [ 'post_type' => 'firmengolf_provider', 'post_status' => 'publish', 'post_title' => $name ] );
		if ( $id > 0 ) {
			$types = function_exists( 'fge_provider_types' ) ? fge_provider_types() : [];
			foreach ( [ 'provider_type' => isset( $types[ $cat ] ) ? $cat : 'sonstige', 'provider_region' => $city, 'provider_contact' => $contact, 'provider_email' => $mail, 'provider_phone' => $phone ] as $k => $v ) {
				if ( '' !== $v ) {
					update_post_meta( $id, '_fge_' . $k, $v );
				}
			}
		}
		fge_cc_obj_back( 'partner', $id > 0 ? [ 'dl' => $id ] : [], $id > 0 ? 'p_created' : 'p_invalid' );
	}
	$id = (int) wp_insert_post( [ 'post_type' => 'firmengolf_partner', 'post_status' => 'publish', 'post_title' => $name ] );
	if ( $id <= 0 ) {
		fge_cc_obj_back( 'partner', [], 'p_invalid' );
	}
	foreach ( [
		'_fge_partner_type'         => $kind,
		'_fge_public_golfclub_name' => $name,
		'_fge_city'                 => $city,
		'_fge_main_contact_name'    => $contact,
		'_fge_main_contact_email'   => $mail,
		'_fge_main_contact_phone'   => $phone,
	] as $k => $v ) {
		update_post_meta( $id, $k, $v );
	}
	// Von dir angelegt heißt geprüft: direkt aktiv, ohne Willkommensmail (kein Statuswechsel).
	update_post_meta( $id, '_fge_partner_status', 'aktiv' );
	update_post_meta( $id, '_fge_partner_since', wp_date( 'Y-m-d' ) );
	if ( function_exists( 'fge_partner_number' ) ) {
		fge_partner_number( $id );
	}
	if ( function_exists( 'fge_verzeichnis_sync_partner' ) ) {
		fge_verzeichnis_sync_partner( $id, 'aktiv' );
	}
	fge_cc_obj_back( 'partner', [ 'pid' => $id ], 'p_created' );
} );

/** Hat das Event einen einfachen Preis (pro Person, ohne Einzelposten)? Nur dann im CC änderbar. */
function fge_cc_event_simple_price( int $eid ): bool {
	return 'gesamt' === ( (string) get_post_meta( $eid, '_fge_price_mode', true ) ?: 'gesamt' )
		&& 'person' === ( (string) get_post_meta( $eid, '_fge_price_basis', true ) ?: 'person' )
		&& ! array_filter( (array) get_post_meta( $eid, '_fge_line_items', true ) );
}

/** Partnerpreis p. P. brutto wie im Formular: gespeichertes Brutto, sonst aus dem Netto. */
function fge_cc_event_partner_gross( int $eid ): float {
	$gross = (float) get_post_meta( $eid, '_fge_price_gross', true );
	if ( $gross > 0 ) {
		return $gross;
	}
	$net = (float) get_post_meta( $eid, '_fge_price_amount', true );
	$pid = (int) get_post_meta( $eid, '_fge_assigned_partner_id', true );
	return $net > 0 && $pid > 0 && function_exists( 'fge_net_to_gross' ) ? fge_net_to_gross( $net, $pid ) : $net;
}

/** Felder aus dem Event-Formular übernehmen. Gibt false zurück, wenn der Titel fehlt. */
function fge_cc_event_apply_form( int $eid ): bool {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- Aufrufer prüft die Nonce.
	$title = trim( sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ) );
	if ( '' === $title ) {
		return false;
	}
	// Die Beschreibung steht im Formular als reiner Text. Unverändert bleibt die
	// Formatierung im Beitrag erhalten, nur bei echter Änderung wird ersetzt.
	$text = (string) wp_unslash( $_POST['text'] ?? '' );
	$old  = (string) get_post_field( 'post_content', $eid );
	$same = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $old ) ) ) === trim( preg_replace( '/\s+/', ' ', $text ) );
	wp_update_post( [ 'ID' => $eid, 'post_title' => $title ] + ( $same ? [] : [ 'post_content' => wp_kses_post( $text ) ] ) );
	$type = sanitize_key( wp_unslash( $_POST['type'] ?? '' ) );
	if ( isset( fge_get_event_formats()['standard'][ $type ] ) || $type === (string) get_post_meta( $eid, '_fge_event_type', true ) ) {
		update_post_meta( $eid, '_fge_event_type', $type );
	}
	foreach ( [ 'min' => '_fge_participants_min', 'max' => '_fge_participants_max' ] as $k => $meta ) {
		if ( isset( $_POST[ $k ] ) ) {
			$n = absint( wp_unslash( $_POST[ $k ] ) );
			update_post_meta( $eid, $meta, $n > 0 ? $n : '' );
		}
	}
	// Preis wie im WordPress: Partnerpreis brutto rein, netto und Verkaufspreis daraus.
	// Nur bei echter Eingabe, ein leeres oder unverändertes Feld lässt den Preis stehen
	// (Audit 15.10.2026: sonst fiel der Preis bei Events ohne Brutto-Wert auf 0).
	$raw   = trim( (string) wp_unslash( $_POST['price'] ?? '' ) );
	$num   = preg_replace( '/[^\d.,]/', '', $raw );
	$gross = (float) ( false !== strpos( $num, ',' ) ? str_replace( [ '.', ',' ], [ '', '.' ], $num ) : $num );
	if ( '' !== $raw && $gross > 0 && abs( $gross - fge_cc_event_partner_gross( $eid ) ) > 0.004 && fge_cc_event_simple_price( $eid ) ) {
		$pid   = (int) get_post_meta( $eid, '_fge_assigned_partner_id', true );
		update_post_meta( $eid, '_fge_price_mode', 'gesamt' );
		update_post_meta( $eid, '_fge_price_basis', 'person' );
		update_post_meta( $eid, '_fge_price_gross', $gross );
		update_post_meta( $eid, '_fge_price_amount', $pid > 0 && function_exists( 'fge_gross_to_net' ) ? fge_gross_to_net( $gross, $pid ) : $gross );
		$pr = fge_event_pricing( $eid );
		if ( $pr['gross'] > 0 ) {
			update_post_meta( $eid, '_fge_sale_price_net', $pr['gross'] );
			update_post_meta( $eid, '_fge_public_price_label', fge_event_price_label( $eid ) );
		}
	}
	// phpcs:enable
	return true;
}

// Event bearbeiten oder neu anlegen (object_id 0, partner_id im Formular).
add_action( 'admin_post_fge_cc_event_save', static function (): void {
	$eid  = fge_cc_obj_guard( 'fge_cc_event_save' );
	$from = sanitize_key( wp_unslash( $_POST['from'] ?? 'events' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$pid  = absint( $_POST['partner_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$pid  = 'firmengolf_partner' === get_post_type( $pid ) ? $pid : 0;
	$new  = 0 === $eid;
	if ( $new ) {
		$eid = (int) wp_insert_post( [ 'post_type' => 'firmengolf_event', 'post_status' => 'publish', 'post_title' => 'Neues Event' ] );
		if ( $eid <= 0 ) {
			fge_cc_obj_back( 'events', [], 'e_invalid' );
		}
		if ( $pid > 0 ) {
			update_post_meta( $eid, '_fge_assigned_partner_id', $pid );
			update_post_meta( $eid, '_fge_provider_type', 'golfplatz_partner' );
		}
		update_post_meta( $eid, '_fge_event_status', 'entwurf' );
	} elseif ( 'firmengolf_event' !== get_post_type( $eid ) ) {
		wp_die( 'Unbekanntes Event.', '', [ 'response' => 400 ] );
	} else {
		$pid = (int) get_post_meta( $eid, '_fge_assigned_partner_id', true );
	}
	if ( ! fge_cc_event_apply_form( $eid ) ) {
		if ( $new ) {
			wp_delete_post( $eid, true );
		}
		fge_cc_obj_back( 'partner' === $from ? 'partner' : 'events', 'partner' === $from ? [ 'pid' => $pid ] : [], 'e_invalid' );
	}
	// Status nur außerhalb der Prüfung: live, Entwurf oder pausiert.
	$st  = sanitize_key( wp_unslash( $_POST['status'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$cur = (string) get_post_meta( $eid, '_fge_event_status', true );
	if ( in_array( $st, [ 'freigegeben', 'entwurf', 'pausiert' ], true ) && ! in_array( $cur, [ 'zur_pruefung', 'aenderung_in_pruefung' ], true ) && $st !== $cur ) {
		// Live schalten oder pausieren ist keine Prüfung: keine Freigabe-Mail an den Partner.
		update_post_meta( $eid, '_fge_event_status', $st );
		delete_post_meta( $eid, '_fge_was_paused' );
	}
	fge_cc_obj_back( 'partner' === $from ? 'partner' : 'events', 'partner' === $from ? [ 'pid' => $pid ] : [], $new ? 'e_created' : 'e_saved' );
} );

// Event-Prüfung: mit Anpassungen freigeben, Rückfrage oder ablehnen.
add_action( 'admin_post_fge_cc_event_review', static function (): void {
	$eid  = fge_cc_obj_guard( 'fge_cc_event_review' );
	$from = sanitize_key( wp_unslash( $_POST['from'] ?? 'events' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( 'firmengolf_event' !== get_post_type( $eid ) ) {
		wp_die( 'Unbekanntes Event.', '', [ 'response' => 400 ] );
	}
	$pid      = (int) get_post_meta( $eid, '_fge_assigned_partner_id', true );
	$back     = 'partner' === $from && $pid > 0 ? [ 'partner', [ 'pid' => $pid ] ] : [ 'events', [] ];
	$decision = sanitize_key( wp_unslash( $_POST['decision'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$msg      = trim( sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( in_array( $decision, [ 'rueckfrage', 'abgelehnt' ], true ) && '' === $msg ) {
		fge_cc_obj_back( 'events', [ 'ev' => $eid, 'pruefen' => 1, 'from' => $from ], 'p_needmsg' );
	}
	if ( 'freigegeben' === $decision ) {
		fge_cc_event_apply_form( $eid );
		fge_event_apply_status( $eid, 'freigegeben' );
		fge_cc_obj_back( $back[0], $back[1], 'e_approved' );
	}
	if ( 'rueckfrage' === $decision ) {
		update_post_meta( $eid, '_fge_review_message', $msg );
		update_post_meta( $eid, '_fge_review_message_at', time() );
		fge_send_event_question_email( $eid, $msg );
		fge_cc_obj_back( $back[0], $back[1], 'e_asked' );
	}
	if ( 'abgelehnt' === $decision ) {
		fge_event_apply_status( $eid, 'abgelehnt', $msg );
		fge_cc_obj_back( $back[0], $back[1], 'e_rejected' );
	}
	fge_cc_obj_back( $back[0], $back[1], 'nothing' );
} );

// Neu eingereicht: die alte Rückfrage ist damit beantwortet.
add_action( 'fge_event_submitted', static function ( $event ): void {
	$eid = is_object( $event ) ? (int) $event->ID : (int) $event;
	if ( $eid > 0 ) {
		delete_post_meta( $eid, '_fge_review_message' );
		delete_post_meta( $eid, '_fge_review_message_at' );
	}
}, 5 );

// ── Oberfläche: Bausteine ────────────────────────────────────────────────────

function fge_cc_kindtag( string $label, string $tone ): string {
	return fge_cc_pill( $label, $tone );
}

/** Freigabe-Zeile (Partner oder Event) wie im Entwurf. */
function fge_cc_review_row( string $tag, string $tone, string $title, string $sub, int $date, bool $wait, string $btn_html ): void {
	echo '<div class="cc-revrow">';
	echo '<span class="cc-revrow-tag">' . fge_cc_pill( $tag, $tone ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<span class="cc-revrow-main"><strong>' . esc_html( $title ) . '</strong><span class="cc-muted">' . esc_html( $sub ) . '</span></span>';
	echo '<span class="cc-revrow-date cc-muted">' . ( $date > 0 ? esc_html( 'eingereicht ' . wp_date( 'd.m.', $date ) ) : '' ) . '</span>';
	echo $wait ? fge_cc_pill( 'Rückfrage offen', 'brand' ) : '<span></span>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo $btn_html; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</div>';
}

/** Filter-Links (Segmente) mit Zähler, GET-Parameter bleiben erhalten. */
function fge_cc_seglinks( string $page, string $param, array $tabs, string $cur, array $counts, array $keep ): void {
	echo '<nav class="cc-segs" aria-label="Ansicht">';
	foreach ( $tabs as $k => $label ) {
		$args = array_filter( [ $param => $k ] + $keep, static fn( $v ) => '' !== (string) $v );
		echo '<a class="cc-seg' . ( $k === $cur ? ' is-on' : '' ) . '" href="' . esc_url( fge_cc_url( $page, $args ) ) . '"' . ( $k === $cur ? ' aria-current="page"' : '' ) . '>'
			. '<span>' . esc_html( $label ) . '</span><span class="cc-seg-n">' . (int) ( $counts[ $k ] ?? 0 ) . '</span></a>';
	}
	echo '</nav>';
}

/** Typen für das Event-Formular. */
function fge_cc_event_types(): array {
	return function_exists( 'fge_get_event_formats' ) ? fge_get_event_formats()['standard'] : [];
}

/** Eine Zeile Eckdaten: Art · ab Preis p. P. · min bis max Personen. */
function fge_cc_event_sub( int $eid ): string {
	$types = function_exists( 'fge_get_event_formats_flat' ) ? fge_get_event_formats_flat() : fge_cc_event_types();
	$type  = (string) get_post_meta( $eid, '_fge_event_type', true );
	$label = function_exists( 'fge_event_price_label' ) ? fge_event_price_label( $eid ) : '';
	$min   = (int) get_post_meta( $eid, '_fge_participants_min', true );
	$max   = (int) get_post_meta( $eid, '_fge_participants_max', true );
	return implode( ' · ', array_filter( [
		(string) ( $types[ $type ] ?? '' ),
		'' !== $label ? ( 'Auf Anfrage' === $label ? 'Preis auf Anfrage' : 'ab ' . $label ) : '',
		$min || $max ? ( $min ?: 1 ) . ' bis ' . ( $max ?: '…' ) . ' Personen' : '',
	] ) );
}

/** Formularfelder eines Events (Bearbeiten, Anlegen, Prüfen). */
function fge_cc_event_fields( int $eid, bool $with_status, string $form = '' ): void {
	$fa     = '' !== $form ? ' form="' . esc_attr( $form ) . '"' : '';
	$title  = $eid > 0 ? get_the_title( $eid ) : '';
	$type   = $eid > 0 ? (string) get_post_meta( $eid, '_fge_event_type', true ) : 'teamevent';
	$min    = $eid > 0 ? (string) get_post_meta( $eid, '_fge_participants_min', true ) : '8';
	$max    = $eid > 0 ? (string) get_post_meta( $eid, '_fge_participants_max', true ) : '40';
	$text   = $eid > 0 ? (string) get_post_field( 'post_content', $eid ) : '';
	$simple = 0 === $eid || fge_cc_event_simple_price( $eid );
	$gross  = $eid > 0 ? fge_cc_event_partner_gross( $eid ) : 0.0;

	echo '<label class="cc-field cc-field--wide"><span>Titel</span><input type="text" name="title" value="' . esc_attr( $title ) . '" required' . $fa . '></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<div class="cc-fields cc-fields--2">';
	echo '<label class="cc-field"><span>Art</span><select name="type"' . $fa . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
	$types = fge_cc_event_types();
	if ( '' !== $type && ! isset( $types[ $type ] ) ) {
		// Alte Art behalten, sonst würde Speichern sie still zu „Teamevent“ machen.
		$flat  = function_exists( 'fge_get_event_formats_flat' ) ? fge_get_event_formats_flat() : [];
		$types = [ $type => (string) ( $flat[ $type ] ?? $type ) ] + $types;
	}
	foreach ( $types as $k => $l ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $type, $k, false ) . '>' . esc_html( $l ) . '</option>';
	}
	echo '</select></label>';
	if ( $simple ) {
		echo '<label class="cc-field"><span>Partnerpreis p. P. brutto</span><input type="text" inputmode="decimal" name="price" value="' . ( $gross > 0 ? esc_attr( number_format_i18n( $gross, 2 ) ) : '' ) . '"' . $fa . '></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
	} else {
		echo '<div class="cc-field"><span>Preis</span><p class="cc-plain">' . esc_html( function_exists( 'fge_event_price_label' ) ? fge_event_price_label( $eid ) : '' ) . ' <a href="' . esc_url( (string) get_edit_post_link( $eid, 'raw' ) ) . '">Preis im WordPress bearbeiten</a></p></div>';
	}
	echo '<label class="cc-field"><span>Mindestens Personen</span><input type="text" inputmode="numeric" name="min" value="' . esc_attr( $min ) . '"' . $fa . '></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<label class="cc-field"><span>Höchstens Personen</span><input type="text" inputmode="numeric" name="max" value="' . esc_attr( $max ) . '"' . $fa . '></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</div>';
	if ( $eid > 0 && $simple && function_exists( 'fge_event_price_label' ) ) {
		echo '<p class="cc-muted">Auf der Website: ' . esc_html( fge_event_price_label( $eid ) ) . '. Der Verkaufspreis rechnet sich aus dem Partnerpreis.</p>';
	}
	echo '<label class="cc-field cc-field--wide"><span>Beschreibung</span><textarea name="text" rows="5"' . $fa . '>' . esc_textarea( wp_strip_all_tags( $text ) ) . '</textarea></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
	if ( $with_status ) {
		$cur = $eid > 0 ? (string) get_post_meta( $eid, '_fge_event_status', true ) : 'entwurf';
		if ( in_array( $cur, [ 'zur_pruefung', 'aenderung_in_pruefung' ], true ) ) {
			echo '<p class="cc-muted">Das Event ist in Prüfung. Freigeben geht über „Prüfen“.</p>';
		} else {
			echo '<label class="cc-field"><span>Status</span><select name="status"' . $fa . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
			foreach ( [ 'freigegeben' => 'Live', 'entwurf' => 'Entwurf', 'pausiert' => 'Pausiert' ] as $k => $l ) {
				echo '<option value="' . esc_attr( $k ) . '"' . selected( $cur, $k, false ) . '>' . esc_html( $l ) . '</option>';
			}
			if ( 'abgelehnt' === $cur ) {
				echo '<option value="abgelehnt" selected>Abgelehnt</option>';
			}
			echo '</select></label>';
		}
	}
}

/** Seitenpanel: Event bearbeiten oder anlegen. */
function fge_cc_event_drawer( int $eid, int $pid, string $from, bool $auto = false ): void {
	$id = 0 === $eid ? 'cc-ev-new' : 'cc-ev-' . $eid;
	fge_cc_drawer_open( $id, 0 === $eid ? 'Event anlegen' : 'Event bearbeiten', $pid > 0 ? fge_cc_partner_name( $pid ) : 'Firmengolf', $auto );
	fge_cc_obj_form_open( 'fge_cc_event_save', $eid, 'cc-form cc-drawer-form' );
	echo '<input type="hidden" name="from" value="' . esc_attr( $from ) . '"><input type="hidden" name="partner_id" value="' . (int) $pid . '">';
	fge_cc_event_fields( $eid, true );
	echo '<div class="cc-drawer-foot"><button type="button" class="cc-btn" data-cc-drawer-close>Abbrechen</button><button type="submit" class="cc-btn cc-btn--primary">Speichern</button></div>';
	echo '</form>';
	fge_cc_drawer_close();
}

/** Link zur Prüfseite eines eingereichten Events. */
function fge_cc_event_review_url( int $eid, string $from = 'events' ): string {
	return fge_cc_url( 'events', [ 'ev' => $eid, 'pruefen' => 1 ] + ( 'partner' === $from ? [ 'from' => 'partner' ] : [] ) );
}

/**
 * Prüfansicht eines Events wie bei der Partner-Freigabe (Julius, 20.10.2026):
 * echte Eventseite links, Prüfung rechts, Anpassen vor der Freigabe aufklappbar.
 */
function fge_cc_event_review_page( int $eid, string $from ): void {
	$pid    = (int) get_post_meta( $eid, '_fge_assigned_partner_id', true );
	$change = 'aenderung_in_pruefung' === (string) get_post_meta( $eid, '_fge_event_status', true );
	$when   = (int) get_post_modified_time( 'U', true, $eid );
	$link   = fge_cc_public_link( $eid );
	$back   = 'partner' === $from && $pid > 0 ? fge_cc_url( 'partner', [ 'pid' => $pid ] ) : fge_cc_url( 'events' );
	$q      = (string) get_post_meta( $eid, '_fge_review_message', true );

	echo '<div class="cc-backrow"><p class="cc-back"><a href="' . esc_url( $back ) . '">Zurück zu ' . esc_html( 'partner' === $from && $pid > 0 ? fge_cc_partner_name( $pid ) : 'Events' ) . '</a></p>';
	echo '<span class="cc-review-note">' . fge_cc_pill( $change ? 'Änderung' : 'Neues Event', $change ? 'neutral' : 'warn' ) . ' So sieht das Event auf ' . esc_html( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) . ' aus. <a href="' . esc_url( $link ) . '" target="_blank" rel="noopener">In neuem Tab</a></span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput

	echo '<div class="cc-reviewgrid">';
	echo '<div class="cc-reviewframe"><iframe src="' . esc_url( add_query_arg( 'fge_cc_preview', 1, $link ) ) . '" title="Eventseite ' . esc_attr( get_the_title( $eid ) ) . '" loading="lazy"></iframe></div>';
	echo '<aside class="cc-card cc-reviewpanel">';
	echo '<div><h2 class="cc-h-small">' . ( $change ? 'Änderung prüfen' : 'Event prüfen' ) . '</h2><span class="cc-muted">' . esc_html( get_the_title( $eid ) . ' · ' . ( $pid > 0 ? fge_cc_partner_name( $pid ) : 'Firmengolf' ) . ' · eingereicht am ' . wp_date( 'd.m.Y', $when ) ) . '</span></div>';
	if ( '' !== $q ) {
		echo '<div class="cc-quote"><span class="cc-label">Deine Rückfrage</span><p>' . nl2br( esc_html( $q ) ) . '</p></div>';
	}
	if ( $change ) {
		echo '<p class="cc-hint cc-hint--box">Die Änderung ist schon live. Ein Vorher und Nachher gibt es nicht, du siehst die aktuelle Fassung.</p>';
	}
	fge_cc_obj_form_open( 'fge_cc_event_review', $eid, 'cc-form cc-reviewform' );
	echo '<input type="hidden" name="from" value="' . esc_attr( $from ) . '">';
	echo '<details class="cc-reviewblock cc-reviewedit"><summary class="cc-label">Vor der Freigabe anpassen</summary>';
	fge_cc_event_fields( $eid, false );
	echo '</details>';
	echo '<div class="cc-reviewblock"><span class="cc-label">Nachricht an den Partner</span><textarea name="message" rows="4" placeholder="Für Rückfrage oder Ablehnung, z. B. Bitte noch drei Fotos vom Platz hochladen." aria-label="Nachricht an den Partner" data-cc-msg></textarea></div>';
	echo '<button type="submit" name="decision" value="freigegeben" class="cc-btn cc-btn--primary cc-btn--block">' . ( $change ? 'Änderung freigeben' : 'Event freigeben' ) . '</button>';
	echo '<div class="cc-btnrow"><button type="submit" name="decision" value="rueckfrage" class="cc-btn cc-grow" data-cc-needmsg formnovalidate>Rückfrage senden</button>';
	echo '<button type="submit" name="decision" value="abgelehnt" class="cc-btn cc-btn--text cc-btn--danger" data-cc-needmsg formnovalidate>Ablehnen</button></div>';
	echo '</form></aside></div>';
}

// ── Seite: Partner ───────────────────────────────────────────────────────────

function fge_cc_page_partner_area(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$pid = absint( $_GET['pid'] ?? 0 );
	$dl  = absint( $_GET['dl'] ?? 0 );
	// phpcs:enable
	fge_cc_message();
	if ( $pid > 0 && 'firmengolf_partner' === get_post_type( $pid ) ) {
		if ( isset( $_GET['pruefen'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			fge_cc_partner_review_page( $pid );
		} else {
			fge_cc_partner_detail( $pid );
		}
		return;
	}
	if ( $dl > 0 && 'firmengolf_provider' === get_post_type( $dl ) ) {
		fge_cc_partner_detail( $dl );
		return;
	}
	fge_cc_partner_listing();
}

/** Umschalter Partner und Events, wie im Entwurf oben auf beiden Seiten. */
function fge_cc_partner_switch( string $on ): void {
	echo '<nav class="cc-areatabs" aria-label="Ansicht">';
	foreach ( [ 'partner' => 'Partner', 'events' => 'Events' ] as $k => $label ) {
		echo '<a class="cc-areatab' . ( $k === $on ? ' is-on' : '' ) . '" href="' . esc_url( fge_cc_url( $k ) ) . '"' . ( $k === $on ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>';
	}
	echo '</nav>';
}

function fge_cc_partner_listing(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$q    = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
	$kind = sanitize_key( wp_unslash( $_GET['art'] ?? 'alle' ) );
	$st   = sanitize_key( wp_unslash( $_GET['status'] ?? 'alle' ) );
	$neu  = isset( $_GET['neu'] );
	// phpcs:enable
	fge_cc_partner_switch( 'partner' );
	$all    = fge_cc_partner_list();
	$events = fge_cc_events_by_partner();
	$book   = fge_cc_bookings_index();
	$kinds  = fge_cc_partner_kinds();
	$pst    = fge_cc_pstatus();

	$n = static fn( string $k ) => count( array_filter( $all, static fn( $r ) => $r['kind'] === $k ) );
	echo '<p class="cc-top-sub cc-sub-under">' . esc_html( count( array_filter( $all, static fn( $r ) => 'aktiv' === $r['status'] ) ) . ' aktiv · ' . $n( 'course' ) . ' Golfplätze · ' . $n( 'coach' ) . ' Golflehrer · ' . $n( 'indoor' ) . ' Indoor · ' . $n( 'dl' ) . ' Dienstleister' ) . '</p>';

	echo '<div class="cc-toolbar"><button type="button" class="cc-btn" data-cc-drawer-open="cc-pnew">Partner anlegen</button></div>';

	// Neue Partner zur Freigabe: erst die, die auf dich warten, dann die mit Rückfrage.
	$rev = array_filter( $all, static fn( $r ) => in_array( $r['status'], [ 'in_pruefung', 'rueckfragen' ], true ) );
	uasort( $rev, static fn( $a, $b ) => ( 'rueckfragen' === $a['status'] ) <=> ( 'rueckfragen' === $b['status'] ) );
	if ( $rev ) {
		$wait = count( array_filter( $rev, static fn( $r ) => 'rueckfragen' === $r['status'] ) );
		echo '<section class="cc-card cc-revcard"><div class="cc-revcard-head"><h2>Neue Partner zur Freigabe</h2><span class="cc-muted">' . esc_html( ( count( $rev ) - $wait ) . ' warten auf dich' . ( $wait ? ', ' . $wait . ' auf den Partner' : '' ) ) . '</span></div>';
		foreach ( $rev as $r ) {
			$ne   = count( $events[ $r['id'] ] ?? [] );
			$w    = 'rueckfragen' === $r['status'];
			$btn  = '<a class="cc-btn' . ( $w ? '' : ' cc-btn--primary' ) . '" href="' . esc_url( fge_cc_url( 'partner', [ 'pid' => $r['id'], 'pruefen' => 1 ] ) ) . '">' . ( $w ? 'Webansicht öffnen' : 'In Webansicht prüfen' ) . '</a>';
			fge_cc_review_row( 'Neuer Partner', 'brand', $r['name'], $kinds[ $r['kind'] ] . ( '' !== $r['city'] ? ' in ' . $r['city'] : '' ) . ' · ' . $ne . ( 1 === $ne ? ' Event' : ' Events' ) . ' eingereicht', $r['submitted'], $w, $btn );
		}
		echo '</section>';
	}

	// Filter: Art als Segmente, Status als Auswahl, Suche oben.
	$rows = array_filter( $all, static function ( $r ) use ( $q, $st ) {
		if ( 'alle' !== $st && $r['status'] !== $st ) {
			return false;
		}
		return '' === $q || false !== mb_stripos( $r['name'] . ' ' . $r['city'] . ' ' . $r['contact'], $q );
	} );
	$counts = [ 'alle' => count( $rows ) ];
	foreach ( $kinds as $k => $l ) {
		$counts[ $k ] = count( array_filter( $rows, static fn( $r ) => $r['kind'] === $k ) );
	}
	$tabs = [ 'alle' => 'Alle', 'course' => 'Golfplätze', 'coach' => 'Golflehrer', 'indoor' => 'Indoor', 'dl' => 'Dienstleister' ];
	if ( ! isset( $tabs[ $kind ] ) ) {
		$kind = 'alle';
	}
	echo '<div class="cc-filterbar">';
	fge_cc_seglinks( 'partner', 'art', $tabs, $kind, $counts, [ 's' => $q, 'status' => 'alle' !== $st ? $st : '' ] );
	echo '<form method="get" action="' . esc_url( fge_cc_url( 'partner' ) ) . '" class="cc-filterform">';
	if ( ! get_option( 'permalink_structure' ) ) {
		echo '<input type="hidden" name="fge_cc" value="partner">';
	}
	echo '<input type="hidden" name="art" value="' . esc_attr( $kind ) . '"><input type="hidden" name="s" value="' . esc_attr( $q ) . '">';
	echo '<select name="status" aria-label="Status" data-cc-autosubmit><option value="alle">Alle Status</option>';
	foreach ( $pst as $k => [ $l ] ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $st, $k, false ) . '>' . esc_html( $l ) . '</option>';
	}
	echo '</select><noscript><button class="cc-btn">Filtern</button></noscript></form>';
	echo '</div>';

	if ( 'alle' !== $kind ) {
		$rows = array_filter( $rows, static fn( $r ) => $r['kind'] === $kind );
	}
	echo '<section class="cc-tablewrap"><table class="cc-table cc-ptable"><thead><tr><th>Partner</th><th>Art</th><th>Status</th><th>Events live</th><th class="cc-num">Buchungen</th><th class="cc-num">Umsatz netto</th><th>Seit</th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		$isdl = 'dl' === $r['kind'];
		$url  = fge_cc_url( 'partner', $isdl ? [ 'dl' => $r['id'] ] : [ 'pid' => $r['id'] ] );
		$bk   = $book[ $isdl ? 'dl' : 'p' ][ $r['id'] ] ?? [];
		$sum  = array_sum( array_column( $bk, 'net' ) );
		$live = $isdl ? 0 : count( array_filter( $events[ $r['id'] ] ?? [], 'fge_cc_event_is_live' ) );
		[ $sl, $stone ] = $pst[ $r['status'] ] ?? [ $r['status'], 'neutral' ];
		echo '<tr onclick="location.href=\'' . esc_js( $url ) . '\'">';
		echo '<td><a href="' . esc_url( $url ) . '"><strong class="cc-ellipsis">' . esc_html( $r['name'] ) . '</strong></a><span class="cc-td-sub">' . esc_html( implode( ' · ', array_filter( [ $r['city'], $r['cat'], $r['contact'] ] ) ) ) . '</span></td>';
		echo '<td>' . esc_html( $kinds[ $r['kind'] ] ) . '</td>';
		echo '<td>' . fge_cc_pill( $sl, $stone ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<td>' . ( $isdl ? '<span class="cc-muted">nur auf Anfrage</span>' : fge_cc_pill( 1 === $live ? '1 Event' : $live . ' Events', 0 === $live ? 'neutral' : ( $live <= 3 ? 'warn' : 'good' ) ) ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<td class="cc-num">' . (int) count( $bk ) . '</td>';
		echo '<td class="cc-num">' . ( $bk ? esc_html( fge_cc_money( $sum ) ) : '' ) . '</td>';
		echo '<td class="cc-tnum">' . ( $r['since'] > 0 ? esc_html( wp_date( 'm/Y', $r['since'] ) ) : ( in_array( $r['status'], [ 'in_pruefung', 'rueckfragen' ], true ) ? 'neu' : '' ) ) . '</td>';
		echo '</tr>';
	}
	if ( ! $rows ) {
		echo '<tr class="is-empty"><td colspan="7">Keine Partner in dieser Ansicht.</td></tr>';
	}
	echo '</tbody></table></section>';

	fge_cc_partner_new_drawer( $neu );
}

function fge_cc_partner_new_drawer( bool $auto ): void {
	fge_cc_drawer_open( 'cc-pnew', 'Partner anlegen', 'Golfplatz, Golflehrer, Indoor-Anlage oder Dienstleister', $auto );
	fge_cc_obj_form_open( 'fge_cc_partner_create', 0, 'cc-form cc-drawer-form' );
	echo '<label class="cc-field cc-field--wide"><span>Name</span><input type="text" name="name" required></label>';
	echo '<div class="cc-fields cc-fields--2">';
	echo '<label class="cc-field"><span>Art</span><select name="kind" data-cc-kindsel>';
	foreach ( fge_cc_partner_kinds() as $k => $l ) {
		echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
	}
	echo '</select></label>';
	echo '<label class="cc-field"><span>Ort</span><input type="text" name="city" required></label>';
	echo '<label class="cc-field" data-cc-only-dl hidden><span>Leistung</span><select name="cat">';
	foreach ( function_exists( 'fge_provider_types' ) ? fge_provider_types() : [] as $k => $l ) {
		echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
	}
	echo '</select></label>';
	echo '<label class="cc-field"><span>Ansprechpartner</span><input type="text" name="contact"></label>';
	echo '<label class="cc-field"><span>E-Mail</span><input type="email" name="mail"></label>';
	echo '<label class="cc-field"><span>Telefon</span><input type="tel" name="phone"></label>';
	echo '</div>';
	echo '<p class="cc-muted">Von dir angelegte Partner sind gleich aktiv. Öffentlich sichtbar wird ein Platz erst mit einem Event.</p>';
	echo '<div class="cc-drawer-foot"><button type="button" class="cc-btn" data-cc-drawer-close>Abbrechen</button><button type="submit" class="cc-btn cc-btn--primary">Anlegen</button></div>';
	echo '</form>';
	fge_cc_drawer_close();
}

/** Detail eines Partners oder Dienstleisters mit Reitern. */
function fge_cc_partner_detail( int $id ): void {
	$r     = fge_cc_partner_row_data( $id );
	$isdl  = 'dl' === $r['kind'];
	$kinds = fge_cc_partner_kinds();
	$evs   = $isdl ? [] : ( fge_cc_events_by_partner()[ $id ] ?? [] );
	$live  = count( array_filter( $evs, 'fge_cc_event_is_live' ) );
	$bk    = fge_cc_bookings_index()[ $isdl ? 'dl' : 'p' ][ $id ] ?? [];
	$tabs  = $isdl ? [ 'bu' => 'Buchungen und Umsatz', 'st' => 'Stammdaten' ] : [ 'ev' => 'Events', 'bu' => 'Buchungen und Umsatz', 'st' => 'Stammdaten' ];
	$tab   = sanitize_key( wp_unslash( $_GET['tab'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$tab   = isset( $tabs[ $tab ] ) ? $tab : (string) array_key_first( $tabs );
	$key   = $isdl ? 'dl' : 'pid';
	[ $sl, $stone ] = fge_cc_pstatus()[ $r['status'] ] ?? [ $r['status'], 'neutral' ];

	echo '<div class="cc-backrow"><p class="cc-back"><a href="' . esc_url( fge_cc_url( 'partner', $isdl ? [ 'art' => 'dl' ] : [] ) ) . '">Alle Partner</a></p>';
	echo '<a class="cc-btn cc-btn--text" href="' . esc_url( (string) get_edit_post_link( $id, 'raw' ) ) . '">Im WordPress öffnen</a></div>';

	echo '<div class="cc-headgrid">';
	echo '<section class="cc-card cc-headcard"><div class="cc-headcard-top"><div class="cc-headcard-title">';
	echo '<span class="cc-head-kicker">' . esc_html( $kinds[ $r['kind'] ] . ( '' !== $r['cat'] ? ', ' . $r['cat'] : '' ) . ( '' !== $r['city'] ? ' · ' . $r['city'] : '' ) ) . '</span>';
	echo '<h1>' . esc_html( $r['name'] ) . '</h1></div>';
	echo '<div class="cc-headcard-side">' . fge_cc_pill( $sl, $stone ) . ( $isdl ? '' : fge_cc_pill( $live . ' live', 0 === $live ? 'neutral' : ( $live <= 3 ? 'warn' : 'good' ) ) ) . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<div class="cc-btnrow">';
	if ( in_array( $r['status'], [ 'in_pruefung', 'rueckfragen' ], true ) ) {
		echo '<a class="cc-btn" href="' . esc_url( fge_cc_url( 'partner', [ 'pid' => $id, 'pruefen' => 1 ] ) ) . '">In der Webansicht prüfen</a>';
	}
	if ( ! $isdl && in_array( $r['status'], [ 'aktiv', 'pausiert' ], true ) ) {
		fge_cc_obj_form_open( 'fge_cc_partner_pstatus', $id, 'cc-inline' );
		echo '<label class="cc-inline-label">Status <select name="status" data-cc-autosubmit>';
		foreach ( [ 'aktiv' => 'Aktiv', 'pausiert' => 'Pausiert' ] as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $r['status'], $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></label><noscript><button class="cc-btn">Setzen</button></noscript></form>';
		echo '<a class="cc-btn cc-btn--text" href="' . esc_url( fge_cc_public_link( $id ) ) . '" target="_blank" rel="noopener">Öffentliche Seite</a>';
	}
	echo '</div>';
	if ( ! $isdl && 'rueckfragen' === $r['status'] && '' !== ( $q = (string) get_post_meta( $id, '_fge_review_message', true ) ) ) {
		echo '<div class="cc-quote"><span class="cc-label">Deine Rückfrage</span><p>' . nl2br( esc_html( $q ) ) . '</p></div>';
	}
	echo '</section>';

	echo '<section class="cc-card cc-headcard"><h2 class="cc-h-small">Kontakt</h2><dl class="cc-factgrid">';
	echo '<div><dt>Ansprechpartner</dt><dd>' . esc_html( $r['contact'] ?: 'fehlt' ) . '</dd></div>';
	echo '<div><dt>Telefon</dt><dd>' . ( '' !== $r['phone'] ? '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $r['phone'] ) ) . '">' . esc_html( $r['phone'] ) . '</a>' : 'fehlt' ) . '</dd></div>';
	echo '<div><dt>E-Mail</dt><dd>' . ( '' !== $r['mail'] ? '<a href="mailto:' . esc_attr( $r['mail'] ) . '">' . esc_html( $r['mail'] ) . '</a>' : 'fehlt' ) . '</dd></div>';
	echo '<div><dt>' . ( $isdl ? 'Im Verzeichnis seit' : 'Partner seit' ) . '</dt><dd>' . esc_html( $r['since'] > 0 ? wp_date( 'm/Y', $r['since'] ) : ( in_array( $r['status'], [ 'in_pruefung', 'rueckfragen' ], true ) ? 'noch nicht freigegeben' : 'nicht hinterlegt' ) ) . '</dd></div>';
	if ( ! $isdl && function_exists( 'fge_partner_number' ) ) {
		echo '<div><dt>Nummer</dt><dd class="cc-mono">' . esc_html( fge_partner_number( $id ) ) . '</dd></div>';
	}
	echo '</dl></section></div>';

	$counts = [ 'ev' => count( $evs ), 'bu' => count( $bk ) ];
	echo '<nav class="cc-segs" aria-label="Bereiche">';
	foreach ( $tabs as $k => $l ) {
		echo '<a class="cc-seg' . ( $k === $tab ? ' is-on' : '' ) . '" href="' . esc_url( fge_cc_url( 'partner', [ $key => $id, 'tab' => $k ] ) ) . '">' . '<span>' . esc_html( $l ) . '</span>' . ( isset( $counts[ $k ] ) ? '<span class="cc-seg-n">' . (int) $counts[ $k ] . '</span>' : '' ) . '</a>';
	}
	echo '</nav>';

	if ( 'ev' === $tab ) {
		fge_cc_partner_events_tab( $id, $evs, $live );
	} elseif ( 'bu' === $tab ) {
		fge_cc_partner_bookings_tab( $bk );
	} else {
		echo '<section class="cc-card"><div class="cc-block-head"><h2 class="cc-h-small">Stammdaten</h2></div>';
		if ( $isdl ) {
			fge_cc_provider_form( $id, true );
		} else {
			fge_cc_obj_form_open( 'fge_cc_partner_save', $id );
			echo '<div class="cc-fields cc-fields--2">';
			foreach ( fge_cc_partner_fields() as $k => [ $label, $meta, $type ] ) {
				$v = (string) get_post_meta( $id, $meta, true );
				if ( 'name' === $k && '' === $v ) {
					$v = get_the_title( $id );
				}
				if ( 'textarea' === $type ) {
					echo '<label class="cc-field cc-field--span2"><span>' . esc_html( $label ) . '</span><textarea name="' . esc_attr( $k ) . '" rows="3" placeholder="z. B. Absprachen, Besonderheiten, bevorzugte Ansprechzeiten">' . esc_textarea( $v ) . '</textarea></label>';
				} else {
					echo '<label class="cc-field"><span>' . esc_html( $label ) . '</span><input type="' . esc_attr( 'date' === $type ? 'date' : ( 'email' === $type ? 'email' : 'text' ) ) . '" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '"></label>';
				}
			}
			echo '</div>';
			echo '<p class="cc-muted">Beschreibung, Fotos, Ausstattung und Steuerdaten pflegt der Partner im Portal, oder du im WordPress.</p>';
			echo '<div class="cc-stepfoot"><button type="submit" class="cc-btn cc-btn--primary">Speichern</button></div></form>';
		}
		echo '</section>';
	}
}

function fge_cc_partner_events_tab( int $pid, array $evs, int $live ): void {
	echo '<section class="cc-card"><div class="cc-block-head"><div><h2 class="cc-h-small">Events</h2><span class="cc-muted">' . esc_html( $live . ' live von ' . count( $evs ) . ' angelegt' . ( $live >= 4 ? '' : '. Ab 4 Live-Events steht der Partner auf Grün.' ) ) . '</span></div>';
	echo '<button type="button" class="cc-btn cc-btn--primary" data-cc-drawer-open="cc-ev-new">Event anlegen</button></div>';
	if ( ! $evs ) {
		echo '<p class="cc-rowempty">Noch keine Events angelegt.</p>';
	}
	foreach ( $evs as $eid ) {
		fge_cc_event_line( $eid, 'partner', false );
	}
	echo '</section>';
	fge_cc_event_drawer( 0, $pid, 'partner' );
	foreach ( $evs as $eid ) {
		fge_cc_event_drawer( $eid, $pid, 'partner' );
	}
}

/** Event als Zeile mit Status und Knöpfen. */
function fge_cc_event_line( int $eid, string $from, bool $with_partner ): void {
	[ , $label, $tone ] = fge_cc_estatus( $eid );
	$review = in_array( (string) get_post_meta( $eid, '_fge_event_status', true ), [ 'zur_pruefung', 'aenderung_in_pruefung' ], true );
	$pid    = (int) get_post_meta( $eid, '_fge_assigned_partner_id', true );
	echo '<div class="cc-evline">';
	echo '<span class="cc-evline-main"><strong>' . esc_html( get_the_title( $eid ) ) . '</strong><span class="cc-muted">' . esc_html( ( $with_partner ? ( $pid > 0 ? fge_cc_partner_name( $pid ) : 'Firmengolf' ) . ' · ' : '' ) . fge_cc_event_sub( $eid ) ) . '</span></span>';
	echo fge_cc_pill( $label, $tone ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<span class="cc-evline-act">';
	if ( $review ) {
		echo '<a class="cc-btn" href="' . esc_url( fge_cc_event_review_url( $eid, $from ) ) . '">Prüfen</a>';
	}
	echo '<a class="cc-btn cc-btn--text" href="' . esc_url( fge_cc_public_link( $eid ) ) . '" target="_blank" rel="noopener">Ansehen</a>';
	echo '<button type="button" class="cc-btn" data-cc-drawer-open="cc-ev-' . (int) $eid . '">Bearbeiten</button>';
	echo '</span></div>';
}

function fge_cc_partner_bookings_tab( array $bk ): void {
	$sum    = array_sum( array_column( $bk, 'net' ) );
	$known  = array_filter( $bk, static fn( $b ) => null !== $b['margin'] );
	$margin = array_sum( array_column( $known, 'margin' ) );
	$base   = array_sum( array_column( $known, 'net' ) );
	echo '<div class="cc-kpis">';
	foreach ( [
		'Buchungen'    => (string) count( $bk ),
		'Umsatz netto' => fge_cc_money( $sum ),
		'Marge'        => $known ? fge_cc_money( $margin ) . ( $base > 0 ? ' · ' . round( $margin / $base * 100 ) . ' %' : '' ) : 'ohne Einkauf',
	] as $l => $v ) {
		echo '<div class="cc-kpi"><span class="cc-kpi-label">' . esc_html( $l ) . '</span><span class="cc-kpi-value">' . esc_html( $v ) . '</span></div>';
	}
	echo '</div>';
	echo '<section class="cc-tablewrap"><table class="cc-table"><thead><tr><th>Anfrage</th><th>Kunde</th><th>Termin</th><th class="cc-num">Umsatz netto</th><th class="cc-num">Marge</th></tr></thead><tbody>';
	usort( $bk, static fn( $a, $b ) => $b['date'] <=> $a['date'] );
	foreach ( $bk as $b ) {
		$url = fge_cc_request_url( $b['req'] );
		echo '<tr onclick="location.href=\'' . esc_js( $url ) . '\'"><td class="cc-mono"><a href="' . esc_url( $url ) . '">' . esc_html( $b['ref'] ) . '</a></td><td>' . esc_html( $b['company'] ) . '</td>'
			. '<td class="cc-tnum">' . ( $b['date'] > 0 ? esc_html( wp_date( 'd.m.Y', $b['date'] ) ) : 'offen' ) . '</td><td class="cc-num">' . esc_html( fge_cc_money( $b['net'] ) ) . '</td>'
			. '<td class="cc-num">' . ( null !== $b['margin'] ? esc_html( fge_cc_money( $b['margin'] ) ) : '<span class="cc-muted">ohne Einkauf</span>' ) . '</td></tr>';
	}
	if ( ! $bk ) {
		echo '<tr class="is-empty"><td colspan="5">Noch keine Buchungen über Firmengolf.</td></tr>';
	}
	echo '</tbody></table></section>';
}

/** Prüfansicht: echte Partnerseite links, Prüfung rechts. */
function fge_cc_partner_review_page( int $pid ): void {
	$r      = fge_cc_partner_row_data( $pid );
	$kinds  = fge_cc_partner_kinds();
	$open   = in_array( $r['status'], [ 'in_pruefung', 'rueckfragen' ], true );
	$link   = fge_cc_public_link( $pid );
	$checks = fge_cc_partner_checks( $pid );

	echo '<div class="cc-backrow"><p class="cc-back"><a href="' . esc_url( fge_cc_url( 'partner', [ 'pid' => $pid ] ) ) . '">Zurück zu ' . esc_html( $r['name'] ) . '</a></p>';
	echo '<span class="cc-review-note">' . fge_cc_pill( 'Nicht öffentlich', 'warn' ) . ' So sieht ' . esc_html( $r['name'] ) . ' auf ' . esc_html( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) . ' aus. <a href="' . esc_url( $link ) . '" target="_blank" rel="noopener">In neuem Tab</a></span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput

	echo '<div class="cc-reviewgrid">';
	echo '<div class="cc-reviewframe"><iframe src="' . esc_url( add_query_arg( 'fge_cc_preview', 1, $link ) ) . '" title="Partnerseite ' . esc_attr( $r['name'] ) . '" loading="lazy"></iframe></div>';
	echo '<aside class="cc-card cc-reviewpanel">';
	if ( ! $open ) {
		[ $sl, $stone ] = fge_cc_pstatus()[ $r['status'] ] ?? [ $r['status'], 'neutral' ];
		echo fge_cc_pill( $sl, $stone ) . '<p>Diese Anmeldung ist schon entschieden.</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</aside></div>';
		return;
	}
	echo '<div><h2 class="cc-h-small">Anmeldung prüfen</h2><span class="cc-muted">' . esc_html( $kinds[ $r['kind'] ] . ( '' !== $r['city'] ? ' in ' . $r['city'] : '' ) . ( '' !== $r['contact'] ? ' · ' . $r['contact'] : '' ) ) . '</span></div>';
	$q = (string) get_post_meta( $pid, '_fge_review_message', true );
	if ( 'rueckfragen' === $r['status'] && '' !== $q ) {
		echo '<div class="cc-quote"><span class="cc-label">Deine Rückfrage</span><p>' . nl2br( esc_html( $q ) ) . '</p></div>';
	}
	echo '<div class="cc-reviewblock"><span class="cc-label">Automatisch geprüft</span><ul class="cc-checks cc-checks--col">';
	foreach ( $checks as [ $label, $pass ] ) {
		echo '<li class="' . ( $pass ? 'is-ok' : '' ) . '"><span aria-hidden="true">' . ( $pass ? '✓' : '!' ) . '</span>' . esc_html( $label ) . '</li>';
	}
	echo '</ul></div>';

	fge_cc_obj_form_open( 'fge_cc_partner_review', $pid, 'cc-form cc-reviewform' );
	echo '<div class="cc-reviewblock"><span class="cc-label">Deine Prüfung</span>';
	foreach ( [ 'Bilder passen zur Anlage und sind in guter Qualität', 'Texte sind sauber und ohne Fehler', 'Daten stimmen: Adresse, Kontakt und Preise' ] as $i => $l ) {
		echo '<label class="cc-checkline"><input type="checkbox" name="check[' . (int) $i . ']" value="1" data-cc-gate-check> ' . esc_html( $l ) . '</label>';
	}
	echo '</div>';
	echo '<div class="cc-reviewblock"><span class="cc-label">Nachricht an den Partner</span><div class="cc-minichips">';
	foreach ( fge_cc_review_quicktexts() as $l => $t ) {
		echo '<button type="button" class="cc-minichip cc-minichip--btn" data-cc-quick="' . esc_attr( $t ) . '">' . esc_html( $l ) . '</button>';
	}
	echo '</div><textarea name="message" rows="4" placeholder="Für Rückfrage oder Ablehnung" aria-label="Nachricht an den Partner" data-cc-msg></textarea></div>';
	echo '<button type="submit" name="decision" value="aktiv" class="cc-btn cc-btn--primary cc-btn--block" data-cc-gate-btn>Partner freigeben</button>';
	echo '<span class="cc-muted cc-center" data-cc-gate-hint>Erst alle drei Punkte unter „Deine Prüfung“ abhaken</span>';
	echo '<div class="cc-btnrow"><button type="submit" name="decision" value="rueckfragen" class="cc-btn cc-grow" data-cc-needmsg>Rückfrage senden</button>';
	echo '<button type="submit" name="decision" value="abgelehnt" class="cc-btn cc-btn--text cc-btn--danger" data-cc-needmsg>Ablehnen</button></div>';
	echo '</form></aside></div>';
}

// ── Seite: Events ────────────────────────────────────────────────────────────

function fge_cc_page_events(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$q    = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
	$tab  = sanitize_key( wp_unslash( $_GET['tab'] ?? 'alle' ) );
	$open = absint( $_GET['ev'] ?? 0 );
	$edit = absint( $_GET['edit'] ?? 0 );
	// phpcs:enable
	fge_cc_message();
	if ( $open > 0 && isset( $_GET['pruefen'] ) && 'firmengolf_event' === get_post_type( $open ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		&& in_array( (string) get_post_meta( $open, '_fge_event_status', true ), [ 'zur_pruefung', 'aenderung_in_pruefung' ], true ) ) {
		fge_cc_event_review_page( $open, 'partner' === sanitize_key( wp_unslash( $_GET['from'] ?? '' ) ) ? 'partner' : 'events' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	fge_cc_partner_switch( 'events' );
	$by  = fge_cc_events_by_partner();
	$all = [];
	foreach ( $by as $pid => $ids ) {
		foreach ( $ids as $eid ) {
			$all[ $eid ] = $pid;
		}
	}
	_prime_post_caches( array_keys( $all ), false, true );
	_prime_post_caches( array_keys( $all ), false, true );
	$live_n  = count( array_filter( array_keys( $all ), 'fge_cc_event_is_live' ) );
	$partner = count( array_filter( array_keys( $by ), static fn( $p ) => $p > 0 ) );
	echo '<p class="cc-top-sub cc-sub-under">' . esc_html( $live_n . ' live · ' . count( $all ) . ' angelegt bei ' . $partner . ' Partnern und Firmengolf' ) . '</p>';

	$st  = static fn( int $e ) => (string) get_post_meta( $e, '_fge_event_status', true );
	$rev = array_filter( array_keys( $all ), static fn( $e ) => in_array( $st( $e ), [ 'zur_pruefung', 'aenderung_in_pruefung' ], true ) );
	if ( $rev ) {
		$ask = static fn( int $e ) => '' !== (string) get_post_meta( $e, '_fge_review_message', true );
		usort( $rev, static fn( $a, $b ) => $ask( $a ) <=> $ask( $b ) );
		$wait = count( array_filter( $rev, $ask ) );
		echo '<section class="cc-card cc-revcard"><div class="cc-revcard-head"><h2>Zur Freigabe</h2><span class="cc-muted">' . esc_html( ( count( $rev ) - $wait ) . ' warten auf dich' . ( $wait ? ', ' . $wait . ' auf den Partner' : '' ) ) . '</span></div>';
		foreach ( $rev as $eid ) {
			$pid    = $all[ $eid ];
			$change = 'aenderung_in_pruefung' === $st( $eid );
			$types  = function_exists( 'fge_get_event_formats_flat' ) ? fge_get_event_formats_flat() : [];
			$btn    = '<a class="cc-btn' . ( $ask( $eid ) ? '' : ' cc-btn--primary' ) . '" href="' . esc_url( fge_cc_event_review_url( $eid ) ) . '">' . ( $ask( $eid ) ? 'Ansehen' : 'Prüfen' ) . '</a>';
			fge_cc_review_row( $change ? 'Änderung' : 'Neues Event', $change ? 'neutral' : 'warn', get_the_title( $eid ), implode( ' · ', array_filter( [ $pid > 0 ? fge_cc_partner_name( $pid ) : 'Firmengolf', (string) ( $types[ (string) get_post_meta( $eid, '_fge_event_type', true ) ] ?? '' ) ] ) ), (int) get_post_modified_time( 'U', true, $eid ), $ask( $eid ), $btn );
		}
		echo '</section>';
	}

	$groups = [
		'alle'    => static fn( $e ) => true,
		'live'    => 'fge_cc_event_is_live',
		'pruefung' => static fn( $e ) => in_array( $st( $e ), [ 'zur_pruefung', 'aenderung_in_pruefung' ], true ),
		'entwurf' => static fn( $e ) => 'entwurf' === $st( $e ) || '' === $st( $e ),
		'aus'     => static fn( $e ) => in_array( $st( $e ), [ 'pausiert', 'abgelehnt' ], true ),
	];
	$tabs = [ 'alle' => 'Alle', 'live' => 'Live', 'pruefung' => 'In Prüfung', 'entwurf' => 'Entwurf', 'aus' => 'Pausiert oder abgelehnt' ];
	if ( ! isset( $tabs[ $tab ] ) ) {
		$tab = 'alle';
	}
	$match = array_filter( array_keys( $all ), static function ( $e ) use ( $q, $all ) {
		$p = $all[ $e ];
		return '' === $q || false !== mb_stripos( get_the_title( $e ) . ' ' . ( $p > 0 ? fge_cc_partner_name( $p ) : 'Firmengolf' ), $q );
	} );
	$counts = [];
	foreach ( $groups as $k => $fn ) {
		$counts[ $k ] = count( array_filter( $match, $fn ) );
	}
	fge_cc_seglinks( 'events', 'tab', $tabs, $tab, $counts, [ 's' => $q ] );
	$rows  = array_values( array_filter( $match, $groups[ $tab ] ) );
	// Seiten zu 100, sonst wird „Alle“ mit fast 400 Events sehr lang.
	$per   = 100;
	$pages = max( 1, (int) ceil( count( $rows ) / $per ) );
	$pg    = min( $pages, max( 1, absint( $_GET['seite'] ?? 1 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$total = count( $rows );
	$rows  = array_slice( $rows, ( $pg - 1 ) * $per, $per );

	$types = function_exists( 'fge_get_event_formats_flat' ) ? fge_get_event_formats_flat() : [];
	echo '<section class="cc-tablewrap"><table class="cc-table cc-evtable"><thead><tr><th>Event</th><th>Partner</th><th>Art</th><th class="cc-num">ab p. P.</th><th>Status</th><th></th></tr></thead><tbody>';
	foreach ( $rows as $eid ) {
		$pid = $all[ $eid ];
		$pr  = function_exists( 'fge_event_pricing' ) ? fge_event_pricing( $eid ) : [ 'gross' => 0 ];
		[ , $label, $tone ] = fge_cc_estatus( $eid );
		echo '<tr><td><strong class="cc-ellipsis">' . esc_html( get_the_title( $eid ) ) . '</strong></td>';
		echo '<td>' . ( $pid > 0 ? '<a href="' . esc_url( fge_cc_url( 'partner', [ 'pid' => $pid ] ) ) . '">' . esc_html( fge_cc_partner_name( $pid ) ) . '</a>' : '<span class="cc-muted">Firmengolf</span>' ) . '</td>';
		echo '<td>' . esc_html( (string) ( $types[ (string) get_post_meta( $eid, '_fge_event_type', true ) ] ?? '' ) ) . '</td>';
		echo '<td class="cc-num">' . ( (float) $pr['gross'] > 0 ? esc_html( fge_cc_money( (float) $pr['gross'] ) ) : '<span class="cc-muted">auf Anfrage</span>' ) . '</td>';
		echo '<td>' . fge_cc_pill( $label, $tone ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<td class="cc-td-act"><a class="cc-btn cc-btn--text" href="' . esc_url( fge_cc_public_link( $eid ) ) . '" target="_blank" rel="noopener">Ansehen</a>'
			. '<a class="cc-btn cc-btn--tiny" href="' . esc_url( fge_cc_url( 'events', array_filter( [ 'tab' => 'alle' !== $tab ? $tab : '', 's' => $q, 'edit' => $eid ] ) ) ) . '">Bearbeiten</a></td></tr>';
	}
	if ( ! $rows ) {
		echo '<tr class="is-empty"><td colspan="6">Keine Events in dieser Ansicht.</td></tr>';
	}
	echo '</tbody></table></section>';
	if ( $pages > 1 ) {
		echo '<nav class="cc-pager" aria-label="Seiten"><span class="cc-muted">' . esc_html( ( ( $pg - 1 ) * $per + 1 ) . ' bis ' . min( $total, $pg * $per ) . ' von ' . $total ) . '</span>';
		for ( $i = 1; $i <= $pages; $i++ ) {
			echo '<a class="cc-seg' . ( $i === $pg ? ' is-on' : '' ) . '" href="' . esc_url( fge_cc_url( 'events', array_filter( [ 'tab' => 'alle' !== $tab ? $tab : '', 's' => $q, 'seite' => $i > 1 ? $i : '' ] ) ) ) . '">' . (int) $i . '</a>';
		}
		echo '</nav>';
	}

	// Bearbeiten öffnet nur das eine Panel, bei fast 400 Events wäre die Seite sonst riesig.
	if ( $edit > 0 && isset( $all[ $edit ] ) ) {
		fge_cc_event_drawer( $edit, $all[ $edit ], 'events', true );
	}
}
