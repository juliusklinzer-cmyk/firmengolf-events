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

// ── Public visibility ─────────────────────────────────────────────────────────

/**
 * Event statuses that count as publicly visible. `aenderung_in_pruefung` is included so a
 * previously approved event stays online while a partner edit waits for re-review — instead of
 * vanishing from the marketplace until an admin re-approves it.
 */
function fge_public_event_statuses(): array {
	return [ 'freigegeben', 'aenderung_in_pruefung' ];
}

// ── Archive Query Filter ──────────────────────────────────────────────────────

function fge_filter_archive_query( WP_Query $q ) {
	if ( is_admin() || ! $q->is_main_query() ) {
		return;
	}
	if ( ! $q->is_post_type_archive( 'firmengolf_event' ) ) {
		return;
	}
	$q->set( 'meta_query', [
		[ 'key' => '_fge_event_status', 'value' => fge_public_event_statuses(), 'compare' => 'IN' ],
	] );
	$q->set( 'posts_per_page', 24 );
}
add_action( 'pre_get_posts', 'fge_filter_archive_query' );

// ── "Gone" (410) für dauerhaft entfernte Events/Plätze ───────────────────────

/**
 * Rendert eine gestylte „nicht mehr verfügbar"-Seite mit HTTP 410 (Gone) und
 * beendet die Anfrage. 410 signalisiert Google „dauerhaft entfernt" → schnellere
 * Deindexierung als bei 404. $kind: 'event' | 'partner'.
 */
function fge_render_gone_page( string $kind, int $post_id ): void {
	global $wp_query;
	$wp_query->set_404();
	status_header( 410 );
	nocache_headers();
	$rendered = get_template_part( 'template-parts/fge-gone', null, [ 'kind' => $kind, 'post_id' => $post_id ] );
	if ( false === $rendered ) {
		get_template_part( '404' ); // Fallback, falls das Theme die Vorlage nicht kennt.
	}
	exit;
}

// ── Guard für nicht-öffentliche Event-Detailseiten ───────────────────────────

add_action( 'template_redirect', 'fge_block_non_approved_events', 1 );

function fge_block_non_approved_events(): void {
	if ( ! is_singular( 'firmengolf_event' ) ) {
		return;
	}
	if ( is_preview() || current_user_can( 'manage_options' ) ) {
		return;
	}
	$id = (int) get_the_ID();
	// Prüft Status UND Pausieren-Kaskade: Events eines deaktivierten Platzes → offline.
	if ( fge_event_is_public( $id ) ) {
		return;
	}
	fge_render_gone_page( 'event', $id );
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
	$status  = (string) get_post_meta( $post_id, '_fge_event_status', true );
	if ( ! in_array( $status, fge_public_event_statuses(), true ) ) {
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

/**
 * Anlage (Onboarding-Infrastruktur) → buchbare Leistungs-Formulierung, gruppiert.
 * Einzige Quelle der Wahrheit, damit Editor und Anfrage-Formular dieselbe Liste teilen.
 *
 * @return array<string,array<string,string>> Gruppe => [ infra-id => Leistungs-Label ]
 */
function fge_infra_to_service_map(): array {
	return [
		'Golf & Training' => [
			'trial-course'    => 'Schnupperkurs',
			'platzreife'      => 'Platzreifekurs',
			'company-course'  => 'Firmenkurs',
			'advanced-course' => 'Fortgeschrittenenkurs',
			'coach'           => 'Golftraining',
			'driving-range'   => 'Range-Nutzung inkl. Bälle',
			'trackman'        => 'TrackMan-Session',
			'toptracer'       => 'Toptracer-Session',
			'indoor'          => 'Indoor-Simulator-Session',
			'course-18'       => '18-Loch-Runde (Greenfee)',
			'course-9'        => '9-Loch-Runde (Greenfee)',
			'short-course'    => 'Kurzplatz-Runde',
			'short-game'      => 'Putting- & Kurzspiel-Challenge',
			'rental-clubs'    => 'Leihschläger',
			'range-balls'     => 'Range-Bälle',
		],
		'Räume & Tagung' => [
			'meeting-room' => 'Meetingraum-Nutzung',
			'seminar'      => 'Seminarraum-Nutzung',
			'conference'   => 'Konferenzraum-Nutzung',
			'workshop'     => 'Workshopraum-Nutzung',
			'eventroom'    => 'Eventraum-Nutzung',
		],
		'Verpflegung' => [
			'breakfast'    => 'Frühstück',
			'lunch'        => 'Lunch',
			'dinner'       => 'Abendessen',
			'bbq'          => 'BBQ',
			'catering'     => 'Catering',
			'coffee-break' => 'Kaffeepause',
			'drinks-flat'  => 'Getränkepauschale',
			'halfway'      => 'Halfway-Verpflegung',
		],
	];
}

/**
 * Buchbare Leistungen eines Platzes, abgeleitet aus seiner Infrastruktur (`_fge_infra`).
 *
 * @return array<string,string[]> Gruppe => Liste der Leistungs-Labels (nur vorhandene)
 */
function fge_partner_bookable_services( int $partner_id ): array {
	if ( $partner_id <= 0 ) {
		return [];
	}
	$sel    = array_map( 'strval', (array) get_post_meta( $partner_id, '_fge_infra', true ) );
	$groups = [];
	foreach ( fge_infra_to_service_map() as $group => $map ) {
		foreach ( $map as $infra_id => $label ) {
			if ( in_array( $infra_id, $sel, true ) ) {
				$groups[ $group ][] = $label;
			}
		}
	}
	if ( array_intersect( [ 'beamer', 'screen', 'mic', 'flipchart', 'whiteboard', 'moderation' ], $sel ) ) {
		$groups['Räume & Tagung'][] = 'Tagungstechnik (Beamer, Leinwand & Co.)';
	}
	return $groups;
}

/**
 * Wunsch-Kategorien für das Event-Anfrage-Modal (Schritt 2).
 *
 * Zwei Quellen, klar getrennt: „Am Platz" (gefiltert nach dem, was der Platz laut
 * Infrastruktur wirklich liefern kann) und „Durch Firmengolf organisiert"
 * (plattformweite Leistungen, immer wählbar). Jede Kategorie hat optionale
 * Detail-Leistungen, die sich im Modal aufklappen lassen.
 *
 * @return array<int,array{key:string,source:string,label:string,subs:string[]}>
 */
function fge_request_wish_categories( int $partner_id ): array {
	$svc = fge_partner_bookable_services( $partner_id );
	$cats = [];

	// ── Am Platz ──────────────────────────────────────────────────────────────
	if ( ! empty( $svc['Golf & Training'] ) ) {
		$cats[] = [ 'key' => 'golf', 'source' => 'platz', 'label' => 'Golf & Training', 'subs' => $svc['Golf & Training'] ];
	}
	$cats[] = [ 'key' => 'tournament', 'source' => 'platz', 'label' => 'Turnier & Wettspiel', 'subs' => [ 'Turniermodus / Scoring', 'Spielleitung & Starter', 'Siegerehrung', 'Urkunde & Foto-Erinnerung' ] ];
	if ( ! empty( $svc['Verpflegung'] ) ) {
		$cats[] = [ 'key' => 'food', 'source' => 'platz', 'label' => 'Verpflegung', 'subs' => $svc['Verpflegung'] ];
	}
	if ( ! empty( $svc['Räume & Tagung'] ) ) {
		$cats[] = [ 'key' => 'rooms', 'source' => 'platz', 'label' => 'Tagung & Räume', 'subs' => $svc['Räume & Tagung'] ];
	}
	$cats[] = [ 'key' => 'shuttle', 'source' => 'platz', 'label' => 'Shuttle & Transfer', 'subs' => [ 'Shuttle ab Bahnhof', 'Hotel-Transfer', 'Bus für die Gruppe' ] ];
	$cats[] = [ 'key' => 'stay', 'source' => 'platz', 'label' => 'Übernachtung', 'subs' => [ 'Hotel-Partner', 'Einzelzimmer', 'Doppelzimmer' ] ];

	// ── Durch Firmengolf organisiert ─────────────────────────────────────────
	$cats[] = [ 'key' => 'tech', 'source' => 'firmengolf', 'label' => 'Event-Technik', 'subs' => [ 'Bühne', 'LED-Wand', 'Tontechnik', 'Lichttechnik', 'Zelt / Pavillon', 'Stromversorgung' ] ];
	$cats[] = [ 'key' => 'entertainment', 'source' => 'firmengolf', 'label' => 'Entertainment', 'subs' => [ 'DJ', 'Party-Band', 'Jazz-Band', 'Walking Act', 'Moderation', 'Fotograf' ] ];
	$cats[] = [ 'key' => 'branding', 'source' => 'firmengolf', 'label' => 'Branding & Teamwear', 'subs' => [ 'Logo-Branding', 'Banner / Flags', 'Polos / Caps', 'Goodie-Bags' ] ];
	$cats[] = [ 'key' => 'program', 'source' => 'firmengolf', 'label' => 'Rahmenprogramm', 'subs' => [ 'Team-Challenge', 'Welcome-Drink', 'Tagesabschluss', 'Sonstiges' ] ];

	return $cats;
}

/**
 * Formatiert einen Wunschtermin: ISO-Datum (vom Kalender) → „Do, 18.06.2026".
 * Freitext (z. B. „KW 30") bleibt unverändert.
 */
function fge_format_wish_date( string $raw ): string {
	$raw = trim( $raw );
	if ( '' === $raw ) {
		return '';
	}
	if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) {
		$ts = strtotime( $raw );
		if ( $ts ) {
			$days = [ 'So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa' ];
			return $days[ (int) gmdate( 'w', $ts ) ] . ', ' . gmdate( 'd.m.Y', $ts );
		}
	}
	return $raw;
}

/**
 * Gespeicherte Wunsch-Leistungen einer Anfrage, getrennt nach Quelle.
 *
 * @return array{platz:string[],firmengolf:string[]}
 */
function fge_request_wish_groups( int $request_id ): array {
	$platz = array_values( array_filter( array_map( 'strval', (array) get_post_meta( $request_id, '_fge_wishes_platz', true ) ) ) );
	$fg    = array_values( array_filter( array_map( 'strval', (array) get_post_meta( $request_id, '_fge_wishes_firmengolf', true ) ) ) );
	return [ 'platz' => $platz, 'firmengolf' => $fg ];
}

/**
 * Ist ein Event öffentlich sichtbar/buchbar?
 *
 * Bedingung: Event-Status `freigegeben` UND der zugehörige Golfplatz ist NICHT pausiert
 * (Pausieren-Kaskade, Handoff §1: Platz pausiert ⇒ alle seine Events offline).
 */
function fge_event_is_public( int $event_id ): bool {
	if ( ! in_array( (string) get_post_meta( $event_id, '_fge_event_status', true ), fge_public_event_statuses(), true ) ) {
		return false;
	}
	$partner_id = (int) get_post_meta( $event_id, '_fge_assigned_partner_id', true );
	if ( $partner_id > 0 && get_post_meta( $partner_id, '_fge_partner_status', true ) === 'pausiert' ) {
		return false;
	}
	return true;
}

/** Published, public-visible event IDs hosted by a partner (newest first). */
function fge_partner_public_event_ids( int $partner_id ): array {
	if ( $partner_id <= 0 ) {
		return [];
	}
	return get_posts( [
		'post_type'      => 'firmengolf_event',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => [
			'relation' => 'AND',
			[ 'key' => '_fge_assigned_partner_id', 'value' => $partner_id ],
			[ 'key' => '_fge_event_status', 'value' => fge_public_event_statuses(), 'compare' => 'IN' ],
		],
	] );
}

/**
 * Is a golf-course (partner) page publicly visible?
 * Condition: published + status `aktiv` + at least one publicly visible event.
 */
function fge_partner_is_public( int $partner_id ): bool {
	if ( $partner_id <= 0 || get_post_type( $partner_id ) !== 'firmengolf_partner' ) {
		return false;
	}
	if ( get_post_status( $partner_id ) !== 'publish' ) {
		return false;
	}
	if ( (string) get_post_meta( $partner_id, '_fge_partner_status', true ) !== 'aktiv' ) {
		return false;
	}
	return ! empty( fge_partner_public_event_ids( $partner_id ) );
}

// ── Guard für nicht-öffentliche Golfplatz-Seiten ─────────────────────────────

add_action( 'template_redirect', 'fge_block_non_public_partners', 1 );

function fge_block_non_public_partners(): void {
	if ( ! is_singular( 'firmengolf_partner' ) ) {
		return;
	}
	if ( is_preview() || current_user_can( 'manage_options' ) ) {
		return;
	}
	$id = (int) get_the_ID();
	if ( fge_partner_is_public( $id ) ) {
		return;
	}
	fge_render_gone_page( 'partner', $id );
}

function fge_get_featured_events( int $count = 3 ): array {
	$candidates = get_posts( [
		'post_type'   => 'firmengolf_event',
		'post_status' => 'publish',
		'numberposts' => max( $count * 4, 12 ),
		'meta_query'  => [
			[ 'key' => '_fge_event_status', 'value' => fge_public_event_statuses(), 'compare' => 'IN' ],
		],
		'orderby' => 'rand',
	] );
	$out = [];
	foreach ( $candidates as $p ) {
		if ( fge_event_is_public( $p->ID ) ) {
			$out[] = $p;
			if ( count( $out ) >= $count ) {
				break;
			}
		}
	}
	return $out;
}

function fge_get_event_price_display( int $post_id ): string {
	$label = get_post_meta( $post_id, '_fge_public_price_label', true );
	if ( $label !== '' ) {
		return $label;
	}
	// Aktuelles Pricing-Modell (_fge_price_mode/-amount/-basis) – wie auf der Detailseite.
	if ( function_exists( 'fge_event_pricing' ) ) {
		$p = fge_event_pricing( $post_id );
		if ( ( $p['gross'] ?? 0 ) > 0 ) {
			$amount = '€' . number_format_i18n( $p['gross'], 0 );
			return ( ( $p['unit'] ?? '' ) === 'pro Person' ) ? $amount . ' p.P.' : $amount . ' gesamt';
		}
	}
	// Legacy-Fallback (alte Felder).
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

/**
 * Categorised placeholder pool: buckets assets/imagery/pool/*.jpg by filename keyword.
 * Cached per request. Lets fallbacks pick a *fitting, varied* stock image instead of one fixed file.
 *
 * @return array<string,string[]> category => list of pool filenames
 */
function fge_placeholder_pool(): array {
	static $buckets = null;
	if ( $buckets !== null ) {
		return $buckets;
	}
	$buckets = [ 'event' => [], 'course' => [], 'range' => [], 'clubhouse' => [], 'founder' => [], 'misc' => [], 'all' => [] ];
	$dir     = FGE_DIR . 'assets/imagery/pool';
	foreach ( glob( $dir . '/*.jpg' ) ?: [] as $path ) {
		$file = basename( $path );
		$buckets['all'][] = $file;
		if ( strpos( $file, 'gruender' ) !== false ) {
			$cat = 'founder';
		} elseif ( preg_match( '/hund|tennis|ubahn|pilot|cockpit|handgepaeck|burnout|buerodach/', $file ) ) {
			$cat = 'misc'; // off-topic marketing/blog imagery — not used for event/course covers
		} elseif ( preg_match( '/range|korb|driving/', $file ) ) {
			$cat = 'range';
		} elseif ( strpos( $file, 'club' ) !== false ) {
			$cat = 'clubhouse';
		} elseif ( preg_match( '/golfplatz|golfloch|gruen|inselgruen|rasen|sand|meer|eagle|panorama|greenkeeper|uebungsgruen|puttinggruen|annaeherung/', $file ) ) {
			$cat = 'course';
		} else {
			$cat = 'event';
		}
		$buckets[ $cat ][] = $file;
	}
	return $buckets;
}

/** Map a legacy placeholder filename to a pool category. */
function fge_placeholder_category( string $name ): string {
	$map = [
		'golf-coaching-gruppe.jpg'       => 'event',
		'golfplatz-drohnenaufnahme.jpg'  => 'course',
		'hero-fairway-wide.jpg'          => 'course',
		'golfplatz-rasen-qualitaet.jpg'  => 'course',
		'golfplatz-panorama.jpg'         => 'course',
		'hero-range.jpg'                 => 'range',
		'clubhaus-aussenansicht.jpg'     => 'clubhouse',
		'gruender-julius-klinzer.jpg'    => 'founder',
	];
	return $map[ $name ] ?? 'event';
}

/**
 * Image URL for the given asset name.
 *
 * With NO $seed (default) this returns the EXACT named file — used for fixed brand assets:
 * page heroes (home, events, city), static pages and the founder photo. These never vary.
 *
 * With a $seed > 0 (a post ID) it instead picks a fitting, stable-but-varied image from the stock
 * pool (assets/imagery/pool/) by the name's category. This is only for golf-course / event items
 * that have no own image — so empty events/places show varied golf photos instead of one repeat.
 */
function fge_get_placeholder_image_url( string $name = 'golfplatz-drohnenaufnahme.jpg', int $seed = 0 ): string {
	if ( $seed > 0 ) {
		$cat  = fge_placeholder_category( $name );
		$pool = fge_placeholder_pool();
		$list = ! empty( $pool[ $cat ] ) ? $pool[ $cat ] : ( $pool['all'] ?? [] );
		if ( ! empty( $list ) ) {
			$idx = abs( crc32( $cat . '|' . $seed ) ) % count( $list );
			return plugins_url( 'assets/imagery/pool/' . $list[ $idx ], FGE_DIR . 'firmengolf-events.php' );
		}
	}
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
