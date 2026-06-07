<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Asset Enqueue ─────────────────────────────────────────────────────────────

function fge_enqueue_frontend_assets() {
	if ( ! is_singular( 'firmengolf_event' ) && ! is_post_type_archive( 'firmengolf_event' ) ) {
		return;
	}
	wp_enqueue_style(
		'fge-frontend',
		plugins_url( 'assets/css/fge-frontend.css', FGE_DIR . 'firmengolf-events.php' ),
		[],
		FGE_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'fge_enqueue_frontend_assets' );

// ── Archive Query Filter ──────────────────────────────────────────────────────

function fge_filter_archive_query( WP_Query $q ) {
	if ( is_admin() || ! $q->is_main_query() ) {
		return;
	}
	if ( ! $q->is_post_type_archive( 'firmengolf_event' ) ) {
		return;
	}
	$q->set( 'meta_key',   '_fge_event_status' );
	$q->set( 'meta_value', 'freigegeben' );
	$q->set( 'posts_per_page', 24 );
}
add_action( 'pre_get_posts', 'fge_filter_archive_query' );

// ── 404 Guard for non-approved event detail pages ────────────────────────────

add_action( 'template_redirect', 'fge_block_non_approved_events', 1 );

function fge_block_non_approved_events(): void {
	if ( ! is_singular( 'firmengolf_event' ) ) {
		return;
	}
	if ( is_preview() || current_user_can( 'manage_options' ) ) {
		return;
	}
	$status = (string) get_post_meta( get_the_ID(), '_fge_event_status', true );
	if ( $status === 'freigegeben' ) {
		return;
	}
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
	get_template_part( '404' );
	exit;
}

// ── View Counter ──────────────────────────────────────────────────────────────

function fge_maybe_increment_views() {
	if ( ! is_singular( 'firmengolf_event' ) ) {
		return;
	}
	if ( is_preview() ) {
		return;
	}
	if ( current_user_can( 'manage_options' ) ) {
		return;
	}
	$post_id = get_the_ID();
	$status  = get_post_meta( $post_id, '_fge_event_status', true );
	if ( $status !== 'freigegeben' ) {
		return;
	}
	$current = (int) get_post_meta( $post_id, '_fge_views_count', true );
	update_post_meta( $post_id, '_fge_views_count', $current + 1 );
}
add_action( 'template_redirect', 'fge_maybe_increment_views' );

// ── Helpers ───────────────────────────────────────────────────────────────────

function fge_get_event_meta( int $post_id, string $key, $default = '' ) {
	$val = get_post_meta( $post_id, '_fge_' . $key, true );
	return ( $val !== '' && $val !== false ) ? $val : $default;
}

function fge_format_weekdays( array $days ): string {
	$map = [
		'monday'    => 'Mo',
		'tuesday'   => 'Di',
		'wednesday' => 'Mi',
		'thursday'  => 'Do',
		'friday'    => 'Fr',
		'saturday'  => 'Sa',
		'sunday'    => 'So',
	];
	$labels = [];
	foreach ( $days as $day ) {
		if ( isset( $map[ $day ] ) ) {
			$labels[] = $map[ $day ];
		}
	}
	return implode( ', ', $labels );
}

function fge_get_partner_info( int $partner_id ): array {
	if ( $partner_id <= 0 ) {
		return [ 'title' => '', 'city' => '' ];
	}
	return [
		'title' => get_the_title( $partner_id ),
		'city'  => (string) get_post_meta( $partner_id, '_fge_city', true ),
	];
}

function fge_get_active_leistungen( int $post_id ): array {
	$all = [
		'has_golf_teacher'      => 'Golflehrer',
		'has_range_usage'       => 'Range',
		'has_rental_clubs'      => 'Leihschläger',
		'has_range_balls'       => 'Rangebälle',
		'has_putting_shortgame' => 'Putting',
		'has_meeting_room'      => 'Meetingraum',
		'has_breakfast'         => 'Frühstück',
		'has_lunch'             => 'Lunch',
		'has_dinner'            => 'Abendessen',
		'has_shuttle'           => 'Shuttle',
		'has_branding'          => 'Branding',
	];
	$active = [];
	foreach ( $all as $key => $label ) {
		if ( get_post_meta( $post_id, '_fge_' . $key, true ) == '1' ) {
			$active[ $key ] = $label;
		}
	}
	return $active;
}

/**
 * Kanonisches Mapping: Event-Leistung (has_*) → Anfrage-Wunsch (wants_*).
 *
 * Nur Leistungen mit einem echten Wunsch-Pendant. Event-only-Leistungen
 * (range_usage, rental_clubs, range_balls, putting_shortgame) haben kein Ziel
 * und werden im Anfrage-Formular nur als „inklusive" angezeigt, nicht als Wunsch gespeichert.
 *
 * @return array<string,string> has_-Schlüssel (ohne Präfix) => wants_-Schlüssel (ohne Präfix)
 */
function fge_leistung_to_want_map(): array {
	return [
		'golf_teacher' => 'golf_teacher',
		'meeting_room' => 'meeting_room',
		'breakfast'    => 'breakfast',
		'lunch'        => 'lunch',
		'dinner'       => 'dinner',
		'shuttle'      => 'shuttle',
		'branding'     => 'branding',
	];
}

/**
 * Öffentliche Golfplatz-Namen (für den „Konkreter Platz"-Dropdown im Anfrage-Wizard).
 *
 * @return string[] alphabetisch, dedupliziert
 */
function fge_get_public_place_names(): array {
	$posts = get_posts( [
		'post_type'   => 'firmengolf_partner',
		'post_status' => 'publish',
		'numberposts' => -1,
		'orderby'     => 'title',
		'order'       => 'ASC',
	] );
	$names = [];
	foreach ( $posts as $p ) {
		$n = (string) get_post_meta( $p->ID, '_fge_public_golfclub_name', true );
		if ( $n === '' ) {
			$n = (string) get_the_title( $p );
		}
		if ( $n !== '' ) {
			$names[] = $n;
		}
	}
	sort( $names );
	return array_values( array_unique( $names ) );
}

/**
 * Liefert die im Event inkludierten Anfrage-Wünsche (wants_-Schlüssel ohne Präfix).
 *
 * @param int $event_id
 * @return string[] z.B. [ 'golf_teacher', 'lunch', 'branding' ]
 */
function fge_event_included_wants( int $event_id ): array {
	$active = fge_get_active_leistungen( $event_id ); // has_-Schlüssel => Label
	$map    = fge_leistung_to_want_map();
	$wants  = [];
	foreach ( $active as $has_key => $label ) {
		$short = preg_replace( '/^has_/', '', $has_key );
		if ( isset( $map[ $short ] ) ) {
			$wants[] = $map[ $short ];
		}
	}
	return array_values( array_unique( $wants ) );
}

function fge_get_featured_events( int $count = 3 ): array {
	return get_posts( [
		'post_type'   => 'firmengolf_event',
		'post_status' => 'publish',
		'numberposts' => $count,
		'meta_query'  => [
			[ 'key' => '_fge_event_status', 'value' => 'freigegeben', 'compare' => '=' ],
		],
		'orderby' => 'rand',
	] );
}

function fge_get_event_price_display( int $post_id ): string {
	$label = get_post_meta( $post_id, '_fge_public_price_label', true );
	if ( $label !== '' ) {
		return $label;
	}
	$price = (float) get_post_meta( $post_id, '_fge_sale_price_net', true );
	if ( $price > 0 ) {
		return 'ab ' . number_format( $price, 0, ',', '.' ) . ' € netto';
	}
	return '';
}

function fge_get_logo_url( bool $light = false ): string {
	$file = $light ? 'firmengolf-wordmark-light.png' : 'firmengolf-wordmark.png';
	return plugins_url( 'assets/logo/' . $file, FGE_DIR . 'firmengolf-events.php' );
}

function fge_get_placeholder_image_url( string $name = 'golfplatz-drohnenaufnahme.jpg' ): string {
	return plugins_url( 'assets/imagery/' . $name, FGE_DIR . 'firmengolf-events.php' );
}

// ── SVG Icons ─────────────────────────────────────────────────────────────────

function fge_icon_check(): string {
	return '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
}

function fge_icon_arrow_right(): string {
	return '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>';
}

function fge_icon_arrow_left(): string {
	return '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>';
}

function fge_icon_arrow_up_right(): string {
	return '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7"/><path d="M7 7h10v10"/></svg>';
}

function fge_icon_map_pin(): string {
	return '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>';
}

function fge_icon_users(): string {
	return '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>';
}

function fge_icon_clock(): string {
	return '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
}

function fge_icon_eye(): string {
	return '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>';
}

function fge_icon_external(): string {
	return '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>';
}

function fge_icon_edit_pencil(): string {
	return '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>';
}

function fge_icon_upload(): string {
	return '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>';
}
