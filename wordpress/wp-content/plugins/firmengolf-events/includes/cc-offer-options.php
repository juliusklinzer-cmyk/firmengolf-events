<?php
/**
 * Angebot mit Optionen (Julius, 28.09.2026).
 *
 * Statt „Platz wählen, Termin blind bestätigen, Angebot raus" läuft es so: Plätze
 * aus der Pipeline werden zu Optionen A, B, C (je Platz Kalkulation, freie
 * Wunschtermine, kurzer Text), Julius markiert eine Empfehlung und schickt EIN
 * Angebot. Der Kunde wählt Option und Termin und nimmt an. Erst dann steht der
 * Termin, der Platz wird zugeordnet, die übrigen Plätze bekommen automatisch die
 * Absage mit Katalog-Einladung.
 *
 * Der Snapshot bleibt abwärtskompatibel: die flachen Schlüssel (date, location,
 * price_*, extras, includes) tragen bis zur Annahme die empfohlene Option, bei der
 * Annahme die gewählte. Alle nachgelagerten Leser (Buchungsbestätigung, Eventtag,
 * Katalog, Kalender) laufen unverändert.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FGE_OFFER_MAX_OPTIONS = 3;

// ── Daten ────────────────────────────────────────────────────────────────────

/** Pipeline-Plätze, die im Angebot stehen sollen, sortiert nach Option (in_offer 1..3). */
function fge_offer_options_venues( int $req ): array {
	$out = [];
	foreach ( fge_venues_get( $req ) as $v ) {
		if ( (int) ( $v['in_offer'] ?? 0 ) > 0 && in_array( (string) $v['status'], [ 'zugesagt', 'gewaehlt' ], true ) ) {
			$out[ (int) $v['in_offer'] ] = $v;
		}
	}
	ksort( $out );
	return array_values( $out );
}

/** Buchstabe der Option aus der Position. */
function fge_offer_option_key( int $pos ): string {
	return chr( 64 + max( 1, min( 26, $pos ) ) );
}

/** Verkaufspreis des Grundpreises eines Platzes: gespeichert, sonst aus dem Einkauf gerechnet. */
function fge_offer_option_base_sale( array $v ): float {
	$sale = (float) ( $v['sale_price'] ?? 0 );
	if ( $sale > 0 ) {
		return $sale;
	}
	return function_exists( 'fge_venue_sale_from_cost' )
		? fge_venue_sale_from_cost( (float) $v['price'], (int) $v['price_gross'] > 0, (float) ( $v['markup_percent'] ?? 20 ) )
		: 0.0;
}

/** Verkaufspreis einer Pipeline-Position. */
function fge_offer_option_item_sale( array $it, array $v ): float {
	$sale = (float) ( $it['sale_price'] ?? 0 );
	if ( $sale > 0 ) {
		return $sale;
	}
	return function_exists( 'fge_venue_sale_from_cost' )
		? fge_venue_sale_from_cost( (float) $it['price'], (int) $it['price_gross'] > 0, (float) ( $v['markup_percent'] ?? 20 ) )
		: 0.0;
}

/**
 * Eine Option aus einer Pipeline-Zeile: dieselben Schlüssel wie der flache Snapshot,
 * dazu key, venue_id, partner_id, dates, recommended, text.
 */
function fge_offer_option_from_venue( int $req, array $v, string $key ): array {
	$pid   = (int) $v['partner_id'];
	$pax   = (int) get_post_meta( $req, '_fge_expected_participants', true );
	$name  = (string) get_post_meta( $pid, '_fge_public_golfclub_name', true ) ?: get_the_title( $pid );
	$city  = (string) get_post_meta( $pid, '_fge_city', true );
	$labels = function_exists( 'fge_request_wish_date_labels' ) ? fge_request_wish_date_labels( $req ) : [];
	$dates  = [];
	foreach ( function_exists( 'fge_venue_free_dates' ) ? fge_venue_free_dates( (int) $v['id'] ) : [] as $idx ) {
		if ( isset( $labels[ $idx ] ) ) {
			$dates[] = [ 'index' => (int) $idx, 'label' => (string) $labels[ $idx ] ];
		}
	}
	$gross  = fge_offer_option_base_sale( $v );
	$is_pp  = 'pauschal' !== (string) $v['price_basis'];
	$extras = [];
	foreach ( function_exists( 'fge_venue_items_get' ) ? fge_venue_items_get( (int) $v['id'] ) : [] as $it ) {
		if ( 'green_fee' === (string) $it['wish_key'] || ! (int) $it['available'] ) {
			continue;
		}
		$sale = fge_offer_option_item_sale( $it, $v );
		// Position ohne Preis, aber verfügbar (z. B. „Getränke nach Verbrauch"): steht
		// als Verbrauchs-Position ohne Betrag im Angebot, die Notiz erklärt sie.
		$cons = $sale <= 0;
		if ( $cons && '' === trim( (string) ( $it['note'] ?? '' ) ) ) {
			continue; // ohne Preis und ohne Erklärung nichts anbieten
		}
		$extras[] = [
			'label'     => (string) $it['label'],
			'price'     => $cons ? 0.0 : $sale,
			'basis'     => $cons ? 'verbrauch' : ( 'pauschal' === (string) $it['price_basis'] ? 'pauschal' : 'person' ),
			'src'       => (int) $it['id'],
			'note'      => (string) ( $it['note'] ?? '' ),
			'organizer' => 'extern' === (string) ( $it['organizer'] ?? '' ) ? 'extern' : 'platz',
			'guide'     => '',
		];
	}
	$not_possible = [];
	foreach ( function_exists( 'fge_venue_items_get' ) ? fge_venue_items_get( (int) $v['id'] ) : [] as $it ) {
		if ( 'green_fee' !== (string) $it['wish_key'] && ! (int) $it['available'] ) {
			$not_possible[] = (string) $it['label'];
		}
	}
	$includes = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) get_post_meta( $req, '_fge_offer_includes', true ) ) ) ) );
	// Offene Wünsche je Option: alles, was weder bepreist noch als nicht möglich gemeldet ist.
	$covered = array_map( static fn( $s ) => mb_strtolower( trim( (string) $s ) ), array_merge( array_column( $extras, 'label' ), $not_possible ) );
	$g       = function_exists( 'fge_request_wish_groups' ) ? fge_request_wish_groups( $req ) : [ 'platz' => [], 'firmengolf' => [] ];
	$open    = static fn( array $list ): array => array_values( array_filter( array_map( 'strval', $list ), static fn( $w ) => ! in_array( mb_strtolower( trim( $w ) ), $covered, true ) ) );
	return [
		'wishes_platz'      => $open( (array) ( $g['platz'] ?? [] ) ),
		'wishes_firmengolf' => $open( (array) ( $g['firmengolf'] ?? [] ) ),
		'key'          => $key,
		'venue_id'     => (int) $v['id'],
		'partner_id'   => $pid,
		'title'        => $name,
		'location'     => trim( $name . ( '' !== $city ? ', ' . $city : '' ) ),
		'schedule'     => trim( (string) get_post_meta( $req, '_fge_offer_schedule', true ) ),
		'includes'     => $includes,
		'price_gross'  => $gross,
		'price_unit'   => $is_pp ? 'pro Person' : 'pauschal',
		'price_total'  => $is_pp && $pax > 0 ? $gross * $pax : $gross,
		'participants' => $pax,
		'extras'       => $extras,
		'not_possible' => $not_possible,
		'dates'        => $dates,
		'date'         => implode( ' oder ', array_column( $dates, 'label' ) ),
		'recommended'  => (int) get_post_meta( $req, '_fge_offer_recommended_venue', true ) === (int) $v['id'],
		'text'         => trim( (string) ( $v['option_text'] ?? '' ) ),
	];
}

/** Warum das Optionen-Angebot noch nicht raus kann. Leer = bereit. */
function fge_offer_options_blocker( int $req ): string {
	$venues = fge_offer_options_venues( $req );
	if ( ! $venues ) {
		return 'Noch kein Platz als Option markiert.';
	}
	if ( count( $venues ) > FGE_OFFER_MAX_OPTIONS ) {
		return 'Höchstens ' . FGE_OFFER_MAX_OPTIONS . ' Optionen.';
	}
	if ( (int) get_post_meta( $req, '_fge_expected_participants', true ) <= 0 ) {
		return 'Teilnehmerzahl fehlt (Pro-Kopf-Preise brauchen sie).';
	}
	foreach ( $venues as $i => $v ) {
		$o = fge_offer_option_from_venue( $req, $v, fge_offer_option_key( $i + 1 ) );
		if ( ! $o['dates'] ) {
			return 'Option ' . $o['key'] . ' (' . $o['title'] . ') hat keinen freien Wunschtermin. Bitte in der Antwort des Platzes je Termin „geht" setzen.';
		}
		if ( $o['price_gross'] <= 0 ) {
			return 'Option ' . $o['key'] . ' (' . $o['title'] . ') hat keinen Grundpreis. Bitte Kalkulation speichern.';
		}
	}
	if ( (int) get_post_meta( $req, '_fge_offer_recommended_venue', true ) <= 0 && count( $venues ) > 1 ) {
		return 'Bitte eine Option als Empfehlung markieren.';
	}
	return '';
}

/** Snapshot mit Optionen: flache Schlüssel = empfohlene (sonst erste) Option. */
function fge_build_offer_snapshot_options( int $req ): array {
	$venues  = fge_offer_options_venues( $req );
	$options = [];
	foreach ( $venues as $i => $v ) {
		$options[] = fge_offer_option_from_venue( $req, $v, fge_offer_option_key( $i + 1 ) );
	}
	$lead = $options[0] ?? [];
	foreach ( $options as $o ) {
		if ( ! empty( $o['recommended'] ) ) {
			$lead = $o;
		}
	}
	$first_idx = (int) ( $lead['dates'][0]['index'] ?? 0 );
	$snap      = fge_build_offer_snapshot( $req, $first_idx > 0 ? $first_idx : 1 );
	foreach ( [ 'location', 'schedule', 'includes', 'price_gross', 'price_unit', 'price_total', 'extras', 'not_possible', 'date', 'wishes_platz', 'wishes_firmengolf' ] as $k ) {
		if ( array_key_exists( $k, $lead ) ) {
			$snap[ $k ] = $lead[ $k ];
		}
	}
	$snap['options']        = $options;
	$snap['recommendation'] = trim( (string) get_post_meta( $req, '_fge_offer_recommendation', true ) );
	$snap['chosen_option']  = '';
	return $snap;
}

/** Läuft diese Anfrage über ein Optionen-Angebot (noch nicht aufgelöst)? */
function fge_offer_options_active( int $req ): bool {
	$snap = (array) get_post_meta( $req, '_fge_offer_snapshot', true );
	return ! empty( $snap['options'] ) && '' === (string) ( $snap['chosen_option'] ?? '' );
}

// ── Versand ──────────────────────────────────────────────────────────────────

/** Angebot mit Optionen senden. Leer bei Erfolg, sonst der Grund. */
function fge_offer_send_options( int $req ): string {
	$blocker = fge_offer_options_blocker( $req );
	if ( '' !== $blocker ) {
		return $blocker;
	}
	if ( ! add_post_meta( $req, '_fge_offer_sent', 1, true ) ) {
		return 'Es ist schon ein Angebot draußen. Zum Ändern erst zurückziehen und neu auflegen.';
	}
	delete_post_meta( $req, '_fge_offer_hold' );
	$snap = fge_build_offer_snapshot_options( $req );
	update_post_meta( $req, '_fge_offer_snapshot', $snap );
	update_post_meta( $req, '_fge_offer_options_mode', 1 );
	delete_post_meta( $req, '_fge_offer_date_index' );
	update_post_meta( $req, '_fge_offer_status', 'pending' );
	$days = (int) apply_filters( 'fge_offer_response_days', 7 );
	update_post_meta( $req, '_fge_offer_deadline', time() + $days * DAY_IN_SECONDS );
	update_post_meta( $req, '_fge_offer_sent_at', time() );
	update_post_meta( $req, '_fge_offer_review_done', 1 );
	fge_send_offer_email( $req );
	fge_request_set_status( $req, 'angebot_versendet' );
	if ( function_exists( 'fge_activity_add' ) ) {
		$keys = array_map( static fn( $o ) => $o['key'] . ' ' . $o['title'], (array) $snap['options'] );
		fge_activity_add( $req, 'offer', 'Angebot mit ' . count( $keys ) . ' Option(en) gesendet: ' . implode( ', ', $keys ) );
	}
	// Jeder Platz im Angebot bekommt die Reservierungsbitte mit der Frist des Kunden,
	// außer er hat schon ein Datum, das mindestens so weit reicht.
	if ( function_exists( 'fge_venue_send_summary' ) ) {
		$deadline = (int) get_post_meta( $req, '_fge_offer_deadline', true );
		foreach ( (array) $snap['options'] as $o ) {
			$v = fge_venue_get( (int) $o['venue_id'] );
			$have = '' !== (string) ( $v['reserved_until'] ?? '' ) ? (int) strtotime( (string) $v['reserved_until'] . ' 23:59:59' ) : 0;
			if ( $have <= 0 || $have < $deadline ) {
				fge_venue_send_summary( $req, (int) $o['venue_id'], 'reservierung' );
			}
		}
	}
	return '';
}

// ── Annahme: gewählte Option in die heutigen Felder auflösen ─────────────────

/**
 * Setzt Termin, Platz, Einkauf und Positionen aus der gewählten Option und zieht
 * den Snapshot flach. Muss VOR `_fge_offer_status = accepted` laufen.
 */
function fge_offer_resolve_option( int $req, string $key, int $date_index, array $picked_srcs ): bool {
	$snap = (array) get_post_meta( $req, '_fge_offer_snapshot', true );
	$opt  = null;
	foreach ( (array) ( $snap['options'] ?? [] ) as $o ) {
		if ( (string) $o['key'] === $key ) {
			$opt = $o;
		}
	}
	if ( ! $opt ) {
		return false;
	}
	$ok_date = false;
	foreach ( (array) $opt['dates'] as $dt ) {
		if ( (int) $dt['index'] === $date_index ) {
			$ok_date = true;
		}
	}
	if ( ! $ok_date ) {
		return false;
	}

	// 1) Termin.
	if ( function_exists( 'fge_rr_set_final' ) ) {
		fge_rr_set_final( $req, $date_index );
	}
	update_post_meta( $req, '_fge_offer_date_index', $date_index );

	// 2) Platz, Einkauf des Grundpreises.
	$venue = function_exists( 'fge_venue_get' ) ? fge_venue_get( (int) $opt['venue_id'] ) : null;
	if ( $venue && function_exists( 'fge_venue_choose' ) ) {
		fge_venue_choose( $req, (int) $venue['id'] );
	} else {
		update_post_meta( $req, '_fge_assigned_partner_id', (int) $opt['partner_id'] );
	}
	update_post_meta( $req, '_fge_offer_base_override', (float) $opt['price_gross'] );
	update_post_meta( $req, '_fge_offer_base_override_unit', 'pro Person' === (string) $opt['price_unit'] ? 'person' : 'pauschal' );
	update_post_meta( $req, '_fge_offer_location', (string) $opt['location'] );

	// 3) Positionen deterministisch aus den Pipeline-Items: id = Item-id, damit die
	//    Kundenauswahl (src) und die Dienstleister-Mails zusammenpassen.
	$margin = (float) ( $venue['markup_percent'] ?? 20 );
	$pname  = (string) get_post_meta( (int) $opt['partner_id'], '_fge_public_golfclub_name', true ) ?: get_the_title( (int) $opt['partner_id'] );
	$pmail  = function_exists( 'fge_cc_partner_email' ) ? fge_cc_partner_email( (int) $opt['partner_id'] ) : '';
	$items  = $venue && function_exists( 'fge_venue_items_get' ) ? fge_venue_items_get( (int) $venue['id'] ) : [];
	$rows   = [];
	foreach ( (array) $opt['extras'] as $x ) {
		$it = null;
		foreach ( $items as $cand ) {
			if ( (int) $cand['id'] === (int) $x['src'] ) {
				$it = $cand;
			}
		}
		$extern = 'extern' === (string) ( $x['organizer'] ?? '' );
		$rows[] = [
			'id'             => (int) $x['src'],
			'label'          => (string) $x['label'],
			'cost'           => 'verbrauch' === (string) $x['basis'] ? 0.0 : (float) ( $it['price'] ?? 0 ),
			'cost_gross'     => (int) ( $it['price_gross'] ?? 1 ),
			'basis'          => (string) $x['basis'],
			'margin'         => $margin,
			'organizer'      => $extern ? 'extern' : 'platz',
			'provider_name'  => $extern ? '' : $pname,
			'provider_email' => $extern ? '' : $pmail,
			'partner_id'     => $extern ? 0 : (int) $opt['partner_id'],
			'wish'           => (string) $x['label'],
			'note'           => (string) ( $x['note'] ?? '' ),
			'guide'          => '',
			'status'         => 'angeboten',
			'sale_override'  => (float) $x['price'],
		];
	}
	foreach ( (array) ( $opt['not_possible'] ?? [] ) as $np ) {
		$rows[] = [
			'id' => 9000 + count( $rows ), 'label' => (string) $np, 'cost' => 0.0, 'cost_gross' => 1, 'basis' => 'pauschal', 'margin' => $margin,
			'organizer' => 'platz', 'provider_name' => $pname, 'provider_email' => $pmail, 'partner_id' => (int) $opt['partner_id'],
			'wish' => (string) $np, 'note' => '', 'guide' => '', 'status' => 'nicht_moeglich',
		];
	}
	update_post_meta( $req, '_fge_extra_services', $rows );
	$valid = array_map( static fn( $x ) => (int) $x['src'], (array) $opt['extras'] );
	update_post_meta( $req, '_fge_offer_extras_selected', array_values( array_intersect( $valid, array_map( 'intval', $picked_srcs ) ) ) );

	// 4) Snapshot flachziehen.
	$label = '';
	foreach ( (array) $opt['dates'] as $dt ) {
		if ( (int) $dt['index'] === $date_index ) {
			$label = (string) $dt['label'];
		}
	}
	foreach ( [ 'location', 'schedule', 'includes', 'price_gross', 'price_unit', 'price_total', 'extras', 'not_possible', 'wishes_platz', 'wishes_firmengolf' ] as $k ) {
		$snap[ $k ] = $opt[ $k ];
	}
	$snap['date']          = $label;
	$snap['options_all']   = $snap['options'];
	$snap['options']       = [];
	$snap['chosen_option'] = $key;
	$snap['chosen_title']  = (string) $opt['title'];
	update_post_meta( $req, '_fge_offer_snapshot', $snap );
	if ( function_exists( 'fge_activity_add' ) ) {
		fge_activity_add( $req, 'offer', 'Kunde hat Option ' . $key . ' (' . $opt['title'] . ') mit Termin ' . $label . ' gewählt' );
	}
	return true;
}

// ── Automatische Absagen und Freigaben ───────────────────────────────────────

/** Annahme: alle übrigen Plätze der Anfrage bekommen die Absage mit Katalog-Einladung. */
add_action( 'fge_offer_accepted', static function ( int $req ): void {
	if ( ! function_exists( 'fge_venues_get' ) || ! function_exists( 'fge_venue_send_decline' ) ) {
		return;
	}
	$chosen = (int) get_post_meta( $req, '_fge_assigned_partner_id', true );
	foreach ( fge_venues_get( $req ) as $v ) {
		if ( (int) $v['partner_id'] === $chosen || ! in_array( (string) $v['status'], [ 'angefragt', 'zugesagt' ], true ) ) {
			continue;
		}
		fge_venue_send_decline( $req, (int) $v['id'], '' );
	}
}, 40 );

/** Ablehnung durch den Kunden: alle angefragten Plätze bekommen den Termin frei. */
add_action( 'fge_offer_declined', static function ( int $req ): void {
	if ( ! function_exists( 'fge_venues_get' ) || ! function_exists( 'fge_venue_send_release' ) ) {
		return;
	}
	foreach ( fge_venues_get( $req ) as $v ) {
		if ( in_array( (string) $v['status'], [ 'zugesagt', 'gewaehlt', 'angefragt' ], true ) ) {
			fge_venue_send_release( $req, (int) $v['id'] );
		}
	}
	update_post_meta( $req, '_fge_venue_released', 1 );
}, 40 );

// ── Cockpit: Angebot zusammenstellen ─────────────────────────────────────────

add_action( 'admin_post_fge_cc_offer_options_save', static function (): void {
	$req = fge_cc_guard( 'fge_cc_offer_options_save' );
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- fge_cc_guard prüft.
	$pos   = array_map( 'absint', (array) ( $_POST['opt_pos'] ?? [] ) );
	$texts = array_map( 'sanitize_text_field', wp_unslash( (array) ( $_POST['opt_text'] ?? [] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$rec   = absint( $_POST['opt_recommended'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$used  = [];
	foreach ( fge_venues_get( $req ) as $v ) {
		$vid = (int) $v['id'];
		$p   = (int) ( $pos[ $vid ] ?? 0 );
		if ( $p > 0 && ( isset( $used[ $p ] ) || ! in_array( (string) $v['status'], [ 'zugesagt', 'gewaehlt' ], true ) ) ) {
			$p = 0;
		}
		if ( $p > 0 ) {
			$used[ $p ] = true;
		}
		fge_venue_update( $vid, [ 'in_offer' => min( FGE_OFFER_MAX_OPTIONS, $p ), 'option_text' => (string) ( $texts[ $vid ] ?? '' ) ] );
	}
	update_post_meta( $req, '_fge_offer_recommended_venue', $rec );
	update_post_meta( $req, '_fge_offer_recommendation', mb_substr( sanitize_textarea_field( wp_unslash( $_POST['opt_recommendation'] ?? '' ) ), 0, 1500 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	fge_activity_add( $req, 'system', 'Angebots-Optionen gespeichert' );
	fge_cc_redirect( $req, 'saved' );
} );

add_action( 'admin_post_fge_cc_offer_send_options', static function (): void {
	$req = fge_cc_guard( 'fge_cc_offer_send_options' );
	$err = fge_offer_send_options( $req );
	if ( '' !== $err ) {
		fge_activity_add( $req, 'system', 'Angebot mit Optionen nicht gesendet: ' . $err );
		fge_cc_redirect( $req, 'nothing' );
	}
	fge_cc_redirect( $req, 'offer_sent' );
} );

/** Panel in Phase 1/2: Optionen wählen, Empfehlung, Vorschau, senden. */
function fge_cc_offer_options_panel( int $req ): void {
	$venues = array_values( array_filter( fge_venues_get( $req ), static fn( $v ) => in_array( (string) $v['status'], [ 'zugesagt', 'gewaehlt' ], true ) ) );
	$labels = function_exists( 'fge_request_wish_date_labels' ) ? fge_request_wish_date_labels( $req ) : [];
	$rec    = (int) get_post_meta( $req, '_fge_offer_recommended_venue', true );
	$pax    = (int) get_post_meta( $req, '_fge_expected_participants', true );

	echo '<div class="cc-offer-options">';
	echo '<p class="cc-kicker">Angebot zusammenstellen</p>';
	if ( ! $venues ) {
		echo '<p class="cc-muted">Sobald ein Platz zugesagt hat, kannst du ihn hier als Option ins Angebot nehmen. Der Kunde wählt dann Option und Termin selbst.</p></div>';
		return;
	}
	echo '<p class="cc-muted">Bis zu drei Optionen. Je Option zählen die Wunschtermine, die der Platz frei gemeldet hat, und seine Kalkulation. Der Kunde sieht alle Optionen nebeneinander und wählt Option und Termin.</p>';
	echo '<form class="cc-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="fge_cc_offer_options_save"><input type="hidden" name="request_id" value="' . (int) $req . '">';
	wp_nonce_field( 'fge_cc_offer_options_save_' . $req );
	echo '<table class="cc-table cc-table--plain cc-opt-table"><thead><tr><th>Platz</th><th>Option</th><th>Freie Termine</th><th>Grundpreis netto</th><th>Positionen</th><th>Empfehlung</th><th>Kurztext für den Kunden</th></tr></thead><tbody>';
	foreach ( $venues as $v ) {
		$vid   = (int) $v['id'];
		$o     = fge_offer_option_from_venue( $req, $v, 'X' );
		$free  = array_map( static fn( $d ) => $d['index'] . '. ' . $d['label'], $o['dates'] );
		$sum   = $o['price_total'];
		foreach ( $o['extras'] as $x ) {
			$sum += 'person' === $x['basis'] ? $x['price'] * max( 1, $pax ) : $x['price'];
		}
		echo '<tr>';
		echo '<td><strong>' . esc_html( $o['title'] ) . '</strong></td>';
		echo '<td><select name="opt_pos[' . $vid . ']"><option value="0">nicht im Angebot</option>';
		for ( $p = 1; $p <= FGE_OFFER_MAX_OPTIONS; $p++ ) {
			echo '<option value="' . $p . '"' . selected( (int) ( $v['in_offer'] ?? 0 ), $p, false ) . '>Option ' . esc_html( fge_offer_option_key( $p ) ) . '</option>';
		}
		echo '</select></td>';
		echo '<td>' . ( $free ? esc_html( implode( ' · ', $free ) ) : '<span class="cc-warn-inline">keiner frei gemeldet</span>' ) . '</td>';
		echo '<td>' . ( $o['price_gross'] > 0 ? esc_html( number_format_i18n( $o['price_gross'], 2 ) . ' € ' . ( 'pro Person' === $o['price_unit'] ? 'p.P.' : 'pauschal' ) ) : '<span class="cc-warn-inline">Kalkulation fehlt</span>' ) . '</td>';
		echo '<td>' . esc_html( $o['extras'] ? implode( ', ', array_map( static fn( $x ) => $x['label'] . ( 'verbrauch' === $x['basis'] ? ' nach Verbrauch' : ' ' . number_format_i18n( $x['price'], 2 ) . ' €' ), $o['extras'] ) ) : 'keine' )
			. ( $o['not_possible'] ? '<br><span class="cc-muted">nicht möglich: ' . esc_html( implode( ', ', $o['not_possible'] ) ) . '</span>' : '' )
			. ( $sum > 0 ? '<br><span class="cc-muted">Summe ca. ' . esc_html( number_format_i18n( $sum, 2 ) ) . ' € netto</span>' : '' ) . '</td>';
		echo '<td><label><input type="radio" name="opt_recommended" value="' . $vid . '"' . checked( $rec, $vid, false ) . '> empfohlen</label></td>';
		echo '<td><input type="text" name="opt_text[' . $vid . ']" value="' . esc_attr( (string) ( $v['option_text'] ?? '' ) ) . '" placeholder="z. B. nur Kurs, Gastro als Selbstzahler vor Ort"></td>';
		echo '</tr>';
	}
	echo '</tbody></table>';
	echo '<label class="cc-field cc-field--wide"><span>Empfehlung an den Kunden (steht über den Optionen)</span>';
	echo '<textarea name="opt_recommendation" rows="2" placeholder="z. B. Unsere Empfehlung ist Option A: kürzeste Anfahrt, Abendessen direkt am Platz.">' . esc_textarea( (string) get_post_meta( $req, '_fge_offer_recommendation', true ) ) . '</textarea></label>';
	echo '<p><button type="submit" class="cc-btn">Optionen speichern</button></p>';
	echo '</form>';

	$blocker = fge_offer_options_blocker( $req );
	$sent    = '1' === (string) get_post_meta( $req, '_fge_offer_sent', true );
	if ( $sent ) {
		echo '<p class="cc-muted">Es ist schon ein Angebot draußen. Änderungen an den Optionen wirken erst nach „Zurückziehen und neu auflegen".</p>';
	} elseif ( '' !== $blocker ) {
		echo '<p class="cc-hint cc-hint--warn">' . esc_html( $blocker ) . '</p>';
	} else {
		echo '<div class="cc-venue-actions">';
		fge_cc_button( 'fge_cc_offer_send_options', $req, 'Angebot mit Optionen senden', [
			'class'   => 'cc-btn cc-btn--primary',
			'confirm' => 'Das Angebot geht jetzt mit allen markierten Optionen und PDF an den Kunden. Fortfahren?',
		] );
		if ( function_exists( 'fge_cc_action_preview' ) ) {
			fge_cc_action_preview( 'offer_send', $req );
		}
		echo '</div>';
	}
	echo '</div>';
}
