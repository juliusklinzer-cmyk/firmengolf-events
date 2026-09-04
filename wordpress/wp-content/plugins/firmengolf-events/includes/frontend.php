<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Hinweis: Das Stylesheet `fge-frontend` wird zentral im Child-Theme (functions.php)
// sitewide enqueued — viele Seitentypen brauchen es. Eine frühere, redundante
// Plugin-Enqueue desselben Handles (nur Event-Seiten) wurde entfernt.

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
	$meta_query = [
		[ 'key' => '_fge_event_status', 'value' => fge_public_event_statuses(), 'compare' => 'IN' ],
	];
	// Musterumgebung raus, damit auch die Trefferzahl/Paginierung stimmt (der
	// Nachfilter im Theme über fge_event_is_public() käme dafür zu spät).
	if ( function_exists( 'fge_demo_exclude_meta_clause' ) ) {
		$demo_clause = fge_demo_exclude_meta_clause();
		if ( $demo_clause ) {
			$meta_query[] = $demo_clause;
		}
	}
	$q->set( 'meta_query', $meta_query );
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

/**
 * Räumt einen Teasertext auf, der von einem maxlength-Feld mitten im Wort
 * abgeschnitten wurde (Audit 2026-08-12: „… eine kleine Challenge zum Abs").
 * Endet der Text nicht auf ein Satzzeichen, fällt das angebrochene Wort weg und
 * es kommt eine Ellipse dahinter.
 */
function fge_tidy_teaser( string $text ): string {
	$text = trim( wp_strip_all_tags( $text ) );
	if ( '' === $text ) {
		return '';
	}
	$last = mb_substr( $text, -1 );
	if ( in_array( $last, [ '.', '!', '?', '…', '"', '”', '»', ')' ], true ) ) {
		return $text;
	}
	$sp = mb_strrpos( $text, ' ' );
	if ( false !== $sp && $sp > 20 ) {
		$text = mb_substr( $text, 0, $sp );
	}
	return rtrim( $text, " ,;:-–" ) . ' …';
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
	// Konkreter Platzwunsch im Anfrage-Wizard: ALLE deutschen Plätze aus dem
	// DGV-Verzeichnis, nicht nur Partner (Julius, 2026-07-06). Ort dran für die Suche.
	if ( function_exists( 'fge_verzeichnis_table' ) ) {
		global $wpdb;
		$table = fge_verzeichnis_table();
		$rows  = $wpdb->get_results( "SELECT name, ort FROM {$table} ORDER BY name ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $rows ) {
			$names = [];
			foreach ( $rows as $r ) {
				$n = trim( (string) $r->name );
				if ( '' === $n ) {
					continue;
				}
				$ort     = trim( (string) $r->ort );
				$names[] = ( '' !== $ort && false === stripos( $n, $ort ) ) ? $n . ' · ' . $ort : $n;
			}
			if ( $names ) {
				return array_values( array_unique( $names ) );
			}
		}
	}
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
	$partner_id = (int) get_post_meta( $event_id, '_fge_assigned_partner_id', true );
	// Musterumgebung ist NIE öffentlich, unabhängig von Post- und Event-Status.
	if ( function_exists( 'fge_is_demo_partner' ) && fge_is_demo_partner( $partner_id ) ) {
		return false;
	}
	if ( ! in_array( (string) get_post_meta( $event_id, '_fge_event_status', true ), fge_public_event_statuses(), true ) ) {
		return false;
	}
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
	// Muster-Golfplatz ist NIE öffentlich, auch nicht mit Status `aktiv`.
	if ( function_exists( 'fge_is_demo_partner' ) && fge_is_demo_partner( $partner_id ) ) {
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
	// Der verknüpfte Partner-Nutzer darf seine EIGENE Seite immer als Vorschau sehen,
	// auch bevor sie öffentlich ist (Portal-Link „Vorschau"). Gilt für alle ASP des Platzes.
	if ( is_user_logged_in() && function_exists( 'fge_user_can_manage_partner' ) && fge_user_can_manage_partner( $id ) ) {
		return;
	}
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

	// „Beliebte Formate": an erster Stelle steht immer ein Teamevent (Julius, 2026-07-06).
	$is_team = static function ( $p ): bool {
		$type = (string) get_post_meta( $p->ID, '_fge_event_type', true );
		if ( function_exists( 'fge_get_event_format_legacy_map' ) ) {
			$type = fge_get_event_format_legacy_map()[ $type ] ?? $type;
		}
		return 'teamevent' === $type;
	};
	if ( ! empty( $out ) && ! $is_team( $out[0] ) ) {
		foreach ( $out as $i => $p ) {
			if ( $is_team( $p ) ) {
				array_unshift( $out, ...array_splice( $out, $i, 1 ) );
				break;
			}
		}
		if ( ! $is_team( $out[0] ) ) {
			// Kein Teamevent in der Zufallsauswahl → aus den restlichen Kandidaten nachziehen.
			foreach ( $candidates as $p ) {
				if ( $is_team( $p ) && fge_event_is_public( $p->ID ) ) {
					array_unshift( $out, $p );
					array_pop( $out );
					break;
				}
			}
		}
	}
	return $out;
}

function fge_get_event_price_display( int $post_id ): string {
	$label = get_post_meta( $post_id, '_fge_public_price_label', true );
	if ( $label !== '' ) {
		// Alte gespeicherte Labels („€89 p.P.") on-the-fly aufs Suffix-Format normalisieren (Kern-Audit H6).
		return preg_replace( '/€\s?([\d.,]+)/u', '$1 €', $label );
	}
	// Aktuelles Pricing-Modell (_fge_price_mode/-amount/-basis) – wie auf der Detailseite.
	if ( function_exists( 'fge_event_pricing' ) ) {
		$p = fge_event_pricing( $post_id );
		if ( ( $p['gross'] ?? 0 ) > 0 ) {
			$amount = number_format_i18n( $p['gross'], 0 ) . ' €';
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
	// Neues Marken-Logo (SVG, 2026-07); light = weiße Variante für dunkle Flächen.
	$file = $light ? 'firmengolf-logo-light.svg' : 'firmengolf-logo.svg';
	return plugins_url( 'assets/logo/' . $file, FGE_DIR . 'firmengolf-events.php' );
}

/**
 * Categorised placeholder pool: buckets assets/imagery/pool/*.jpg by filename keyword.
 * Cached per request. Lets fallbacks pick a *fitting, varied* stock image instead of one fixed file.
 *
 * @return array<string,string[]> category => list of pool filenames
 */
/**
 * Motive, die nie automatisch vergeben werden (Audit 2026-08-12). Entweder
 * widersprechen sie dem Versprechen „Golfplätze in Deutschland" oder sie sind
 * ohne Bildunterschrift nicht als Golf lesbar. Die Dateien bleiben liegen und
 * können weiter gezielt gesetzt werden.
 */
function fge_placeholder_blocklist(): array {
	return [
		// Mid-Century-Clubhaus mit Palmen und Wüstenbergen (Kalifornien-Look).
		'kundenevent-clubhaus-modern.jpg',
		'workshop-clubhaus-modern.jpg',
		// Café-Innenraum mit sitzenden Gästen, kein Golfbezug.
		'teamevent-buero.jpg',
		// Leeres Großraumbüro („alle sind golfen"), ohne Kontext nicht lesbar.
		'teamevent-buero-alle-sind-golfen.jpg',
	];
}

/**
 * Inhalts-Schlüssel eines Pool-Bilds, damit identische Dateien unter
 * verschiedenen Namen als ein Motiv zählen. Ergebnis wird tagesweise gecacht,
 * damit nicht bei jedem Request über den ganzen Ordner gehasht wird.
 */
function fge_placeholder_content_key( string $file ): string {
	static $map = null;
	if ( null === $map ) {
		// Versioniert: nach einem Deploy mit neuen Pool-Dateien darf kein alter
		// Hash-Stand einen Tag lang weiterleben (Import 04.09.).
		$cached = get_transient( 'fge_placeholder_content_keys' );
		$map    = ( is_array( $cached ) && ( $cached['v'] ?? '' ) === FGE_VERSION ) ? $cached['map'] : null;
		if ( ! is_array( $map ) ) {
			$map = [];
			foreach ( glob( FGE_DIR . 'assets/imagery/pool/*.jpg' ) ?: [] as $path ) {
				$map[ basename( $path ) ] = (string) md5_file( $path );
			}
			set_transient( 'fge_placeholder_content_keys', [ 'v' => FGE_VERSION, 'map' => $map ], DAY_IN_SECONDS );
		}
	}
	return $map[ $file ] ?? $file;
}

function fge_placeholder_pool(): array {
	static $buckets = null;
	if ( $buckets !== null ) {
		return $buckets;
	}
	$blocked = array_flip( fge_placeholder_blocklist() );
	$buckets = [
		'event' => [], 'course' => [], 'range' => [], 'clubhouse' => [], 'founder' => [], 'misc' => [], 'all' => [],
		// Format-Gruppen (2026-07): Dateiname-Präfix bestimmt die Gruppe, z. B. pool/platzreife-*.jpg.
		// Die Alt-Bestände tragen das teamevent-Präfix (Julius' Entscheidung: bisherige Bilder = Teamevent-Topf).
		'teamevent' => [], 'platzreife' => [], 'turnier' => [], 'kundenevent' => [], 'afterwork' => [], 'incentive' => [], 'nachtevent' => [], 'workshop' => [], 'indoor' => [], 'weihnachtsfeier' => [],
		// Beimisch-Topf (Julius, 03.09.): pool/golfplatz-*.jpg = echte Platz-Motive,
		// zugleich Platz-/Stadt-Motive (course); keine Beimischung in Event-Galerien.
		'golfplatz' => [],
		// Closeup-Topf (Julius, 04.09.): pool/closeup-*.jpg = Nahaufnahmen (Bälle,
		// Schläger, Schuhe …), wird nicht automatisch vergeben, nur gezielt gesetzt.
		'closeup' => [],
	];
	$dir     = FGE_DIR . 'assets/imagery/pool';
	foreach ( glob( $dir . '/*.jpg' ) ?: [] as $path ) {
		$file = basename( $path );
		if ( isset( $blocked[ $file ] ) ) {
			continue;
		}
		// Reserve (Julius, 04.09.): pool-*.jpg (inkl. pool-hochformat-*) liegt nur
		// zum Nachschlagen bereit und wird NIE automatisch vergeben.
		if ( 0 === strpos( $file, 'pool-' ) ) {
			continue;
		}
		if ( 0 === strpos( $file, 'closeup-' ) ) {
			$buckets['closeup'][] = $file;
			continue; // nicht in „all": kein Schuh-Closeup als Platz- oder Stadt-Cover
		}
		$buckets['all'][] = $file;
		if ( 0 === strpos( $file, 'golfplatz-' ) || 0 === strpos( $file, 'platz-' ) ) {
			$buckets['golfplatz'][] = $file;
			$cat                    = 'course'; // bleibt zugleich Platz-/Stadt-Motiv
		} elseif ( preg_match( '/^(teamevent|platzreife|turnier|kundenevent|afterwork|incentive|nachtevent|workshop|indoor|weihnachtsfeier)-/', $file, $m ) ) {
			$cat = $m[1];
		} elseif ( strpos( $file, 'gruender' ) !== false ) {
			$cat = 'founder';
		} elseif ( preg_match( '/hund|tennis|ubahn|pilot|cockpit|handgepaeck|burnout|buerodach/', $file ) ) {
			$cat = 'misc'; // off-topic marketing/blog imagery, not used for event/course covers
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

/**
 * Format-spezifische Pool-Gruppe eines Events (per _fge_event_type, inkl. Legacy-Mapping).
 * Leerstring, wenn es für den Typ (noch) keine gefüllte Gruppe gibt → Aufrufer fällt
 * auf die Namens-Kategorie zurück.
 */
/** Event-Typ (nach Legacy-Mapping) → Pool-Gruppe, ohne Rücksicht auf Füllstand. */
function fge_event_type_pool_map(): array {
	return [
		'teamevent'          => 'teamevent',
		'platzreife'         => 'platzreife',
		'firmen_golfturnier' => 'turnier',
		'kundenevent'        => 'kundenevent',
		'after_work_golf'    => 'afterwork',
		'incentive'          => 'incentive',
		'workshop'           => 'workshop',
		'nacht_event'        => 'nachtevent',
		'indoor-golf'        => 'indoor', // Persona-Audit 02.09.: Indoor-Events bekamen Fairway-Luftbilder
		'weihnachtsfeier'    => 'weihnachtsfeier', // eigener Pool (pool/weihnachtsfeier-*.jpg); leer → Indoor-Fallback unten
	];
}

/** Löst eine Pool-Gruppe auf den tatsächlich gefüllten Topf auf (Leerstring = keiner). */
function fge_resolve_pool_category( string $cat ): string {
	if ( $cat === '' ) {
		return '';
	}
	$pool = fge_placeholder_pool();
	if ( ! empty( $pool[ $cat ] ) ) {
		return $cat;
	}
	// Weihnachtsfeiern spielen im Winter drinnen (Julius, 03.09.): solange der
	// eigene Pool leer ist, kommt die Indoor-Bildwelt statt Outdoor-Fallback.
	if ( 'weihnachtsfeier' === $cat && ! empty( $pool['indoor'] ) ) {
		return 'indoor';
	}
	return '';
}

function fge_event_pool_category( int $event_id ): string {
	$type = (string) get_post_meta( $event_id, '_fge_event_type', true );
	if ( function_exists( 'fge_get_event_format_legacy_map' ) ) {
		$type = fge_get_event_format_legacy_map()[ $type ] ?? $type;
	}
	return fge_resolve_pool_category( fge_event_type_pool_map()[ $type ] ?? '' );
}

/**
 * Rang eines Events unter allen veröffentlichten Events (nach ID): [Rang innerhalb
 * seiner Pool-Gruppe, Rang gesamt]. Damit bekommt jedes Platzhalter-Event ein
 * EIGENES Cover, bis der Topf einmal durch ist, danach wiederholt es sich
 * gleichmäßig statt „jedes zweite Event dasselbe Bild" (Julius, 04.09.).
 * Eine Abfrage pro Request, danach statisch.
 */
function fge_event_placeholder_rank( int $event_id ): array {
	static $ranks = null;
	if ( null === $ranks ) {
		global $wpdb;
		$rows   = $wpdb->get_results( "SELECT p.ID, pm.meta_value AS t FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_fge_event_type' WHERE p.post_type = 'firmengolf_event' AND p.post_status = 'publish' ORDER BY p.ID ASC" );
		$legacy = function_exists( 'fge_get_event_format_legacy_map' ) ? fge_get_event_format_legacy_map() : [];
		$tmap   = fge_event_type_pool_map();
		$ranks  = [];
		$per    = [];
		$all    = 0;
		foreach ( (array) $rows as $r ) {
			$t   = (string) $r->t;
			$t   = $legacy[ $t ] ?? $t;
			$cat = fge_resolve_pool_category( $tmap[ $t ] ?? '' );
			$per[ $cat ]          = $per[ $cat ] ?? 0;
			$ranks[ (int) $r->ID ] = [ $per[ $cat ]++, $all++ ];
		}
	}
	return $ranks[ $event_id ] ?? [ abs( crc32( (string) $event_id ) ), abs( crc32( 'all|' . $event_id ) ) ];
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
function fge_get_placeholder_image_url( string $name = 'golfplatz-drohnenaufnahme.jpg', int $seed = 0, int $offset = 0 ): string {
	if ( $seed > 0 ) {
		$cat = fge_placeholder_category( $name );
		// Events ziehen bevorzugt aus der Format-Gruppe ihres Typs (teamevent/platzreife/…),
		// damit Platzhalter-Events pro Typ eine eigene, stimmige Bildwelt bekommen.
		$is_event = get_post_type( $seed ) === 'firmengolf_event';
		if ( $is_event ) {
			$type_cat = fge_event_pool_category( $seed );
			if ( $type_cat !== '' ) {
				$cat = $type_cat;
			}
		}
		$pool      = fge_placeholder_pool();
		$type_cats = array_values( fge_event_type_pool_map() );
		$is_type   = $is_event && ( in_array( $cat, $type_cats, true ) || 'event' === $cat );
		// Platzhalter-Events zeigen NUR Motive ihres eigenen Typ-Topfs, Cover wie
		// Galerie-Kacheln (Julius, 04.09.; die Beimisch-Slots Golfplatz/Closeup
		// wurden am selben Tag wieder verworfen). golfplatz-* bleibt Platz- und
		// Stadt-Motiv, closeup-* liegt nur für gezielte Einsätze bereit.
		// „all" ohne Off-Topic (misc: U-Bahn, Cockpit …) und Gründerfotos: der
		// Gesamtpool ist NUR Fallback für Event-/Platz-Cover, dort haben die
		// Marketing-Motive nichts verloren (Design-QA 2026-08-21).
		$pool_cover_all = array_values( array_diff(
			(array) ( $pool['all'] ?? [] ),
			(array) ( $pool['misc'] ?? [] ),
			(array) ( $pool['founder'] ?? [] )
		) );
		$list = ! empty( $pool[ $cat ] ) ? $pool[ $cat ] : $pool_cover_all;
		if ( ! empty( $list ) ) {
			// Seiten-weiter Dedup (Julius: „nie ein Bild zweimal"): pro Request wird jedes
			// Pool-Bild nur EINMAL vergeben — kollidiert der Seed-Index, rückt der nächste
			// freie nach. $memo hält identische Aufrufe stabil (og:image == Hero-Cover).
			// Erst wenn eine Gruppe komplett vergeben ist, wiederholt sich zwangsläufig etwas.
			static $used = [], $memo = [];
			$key = $cat . '|' . $seed . '|' . $offset;
			if ( isset( $memo[ $key ] ) ) {
				return plugins_url( 'assets/imagery/pool/' . $memo[ $key ], FGE_DIR . 'firmengolf-events.php' );
			}
			// Detail-Motive (Dateisuffix "-detail": Essen-/Deko-Closeups, Schriftzüge …)
			// sind fürs HAUPTBILD (Offset 0) nur letzte Reserve — als Galerie-Kacheln erwünscht.
			if ( 0 === $offset ) {
				$primary = array_values( array_filter( $list, static fn( $f ) => false === strpos( $f, '-detail.' ) ) );
				$detail  = array_values( array_diff( $list, $primary ) );
				$list    = array_merge( $primary, $detail );
				$span    = count( $primary ) > 0 ? count( $primary ) : count( $list );
			} else {
				$span = count( $list );
			}
			$count = count( $list );
			// Events: Rang statt Zufallshash, damit Cover erst nach einem vollen
			// Durchlauf des Topfs wiederkommen. Die Beimisch-Töpfe (Platz, Closeup)
			// zählen über ALLE Events, damit sich auch dort nichts häuft.
			if ( $is_event ) {
				[ $rank_type, $rank_all ] = fge_event_placeholder_rank( $seed );
				$base = in_array( $cat, [ 'golfplatz', 'closeup' ], true ) ? $rank_all : $rank_type;
			} else {
				$base = abs( crc32( $cat . '|' . $seed ) );
			}
			$idx = ( $base + $offset ) % $span;
			// Kandidaten-Reihenfolge: zirkulär durch die Primär-Bilder, Details erst danach.
			$order = [];
			for ( $i = 0; $i < $span; $i++ ) {
				$order[] = ( $idx + $i ) % $span;
			}
			for ( $i = $span; $i < $count; $i++ ) {
				$order[] = $i;
			}
			// Dedup über den Bildinhalt, nicht über den Dateinamen: mehrere Motive
			// liegen unter verschiedenen Namen doppelt im Pool (z. B. clubhaus.jpg,
			// workshop-clubhaus.jpg und workshop-clubhaus-aussen.jpg sind dieselbe
			// Datei). Namensbasiert erschien dasselbe Foto so mehrfach auf einer
			// Seite (Audit 2026-08-12).
			$pick = null;
			foreach ( $order as $try ) {
				if ( empty( $used[ fge_placeholder_content_key( $list[ $try ] ) ] ) ) {
					$pick = $list[ $try ];
					break;
				}
			}
			// Gruppe auf dieser Seite erschöpft: Event-Töpfe wiederholen sich dann
			// lieber gleichmäßig (Rang), als dass ein Turnier ein Incentive-Motiv
			// bekommt (Julius, 04.09.). Nur Platz-/Range-/Clubhaus-Kategorien
			// dürfen weiter in den Gesamtpool ausweichen.
			if ( null === $pick && ! $is_type && ! in_array( $cat, [ 'golfplatz', 'closeup' ], true ) ) {
				$fallback = array_values( array_diff( $pool_cover_all, $list ) );
				$fcount   = count( $fallback );
				for ( $i = 0; $i < $fcount; $i++ ) {
					$cand = $fallback[ ( abs( crc32( $cat . '|' . $seed ) ) + $offset + $i ) % $fcount ];
					if ( empty( $used[ fge_placeholder_content_key( $cand ) ] ) ) {
						$pick = $cand;
						break;
					}
				}
			}
			if ( null === $pick ) {
				$pick = $list[ $idx ]; // alles vergeben, Wiederholung unvermeidbar
			}
			$used[ fge_placeholder_content_key( $pick ) ] = true;
			$memo[ $key ]                                 = $pick;
			return plugins_url( 'assets/imagery/pool/' . $pick, FGE_DIR . 'firmengolf-events.php' );
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
