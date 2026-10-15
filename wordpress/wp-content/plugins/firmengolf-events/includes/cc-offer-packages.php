<?php
/**
 * Angebote A, B, C als eigene Pakete (Julius, 15.10.2026, Entwurf „Anfragen").
 *
 * Bis 1.9.304 war eine Option gleich ein Platz mit dessen Kalkulation. Jetzt hat
 * jedes Angebot einen Golfplatz, einen Titel, eine Einleitung, einen Termin und
 * eine eigene Positionsliste: Grundpreis des Platzes, Leistungen des Platzes,
 * Leistungen von Dienstleistern und freie Positionen, je mit Einkauf, Aufschlag
 * und Verkauf.
 *
 * Gespeichert in `_fge_offer_packages`. Beim Senden wird daraus dieselbe
 * Options-Struktur wie bisher (fge_offer_option_from_package), sodass
 * Kundenseite, PDF, Mails und Annahme (fge_offer_resolve_option) unverändert
 * weiterlaufen. Anfragen mit alten Optionen werden beim ersten Öffnen einmal
 * übernommen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FGE_PKG_DEFAULT_MARKUP = 20.0;

/** Nächste laufende Nummer je Anfrage für Positionen (stabile IDs, auch für die Kundenauswahl). */
function fge_pkg_next_id( int $req ): int {
	$n = max( 700000, (int) get_post_meta( $req, '_fge_offer_pkg_seq', true ) ) + 1;
	update_post_meta( $req, '_fge_offer_pkg_seq', $n );
	return $n;
}

/** Verkauf netto je Einheit aus Einkauf und Aufschlag, auf volle Euro. */
function fge_pkg_sale( float $cost, bool $gross, float $markup ): float {
	return function_exists( 'fge_venue_sale_from_cost' ) ? fge_venue_sale_from_cost( $cost, $gross, $markup ) : 0.0;
}

/** Einkauf netto je Einheit. */
function fge_pkg_cost_net( array $it ): float {
	$vat = defined( 'FGE_VAT_PERCENT' ) ? (float) FGE_VAT_PERCENT : 19.0;
	$c   = (float) $it['cost'];
	return ! empty( $it['gross'] ) ? $c / ( 1 + $vat / 100 ) : $c;
}

/** Eine Position aus einer Pipeline-Zeile (Platz oder Dienstleister). */
function fge_pkg_item_from_venue_item( int $req, array $row, array $it ): array {
	$dl     = 'dl' === (string) ( $row['kind'] ?? 'platz' );
	$cost   = (float) $it['price'];
	$markup = $dl ? FGE_PKG_DEFAULT_MARKUP : (float) ( $row['markup_percent'] ?? FGE_PKG_DEFAULT_MARKUP );
	$sale   = ! $dl && (float) ( $it['sale_price'] ?? 0 ) > 0 ? (float) $it['sale_price'] : fge_pkg_sale( $cost, (bool) (int) $it['price_gross'], $markup );
	return [
		'id'           => fge_pkg_next_id( $req ),
		'kind'         => $dl ? 'provider' : 'venue',
		'ref'          => (int) $it['id'],
		'provider_row' => $dl ? (int) $row['id'] : 0,
		'title'        => (string) $it['label'],
		'text'         => trim( (string) ( $it['note'] ?? '' ) ),
		'cost'         => $cost,
		'gross'        => (int) $it['price_gross'] ? 1 : 0,
		'unit'         => $cost <= 0 && '' !== trim( (string) ( $it['note'] ?? '' ) ) ? 'verbrauch' : ( 'pauschal' === (string) $it['price_basis'] ? 'pauschal' : 'person' ),
		'markup'       => $markup,
		'sale'         => $sale,
		'organizer'    => $dl ? 'extern' : ( 'extern' === (string) ( $it['organizer'] ?? '' ) ? 'extern' : 'platz' ),
	];
}

/** Neues Paket für einen Platz: Grundpreis und alles, was der Platz anbietet. */
function fge_pkg_new( int $req, array $v ): array {
	$markup = (float) ( $v['markup_percent'] ?? FGE_PKG_DEFAULT_MARKUP );
	$cost   = (float) $v['price'];
	$items  = [ [
		'id'           => fge_pkg_next_id( $req ),
		'kind'         => 'base',
		'ref'          => (int) $v['id'],
		'provider_row' => 0,
		'title'        => 'Event und Platznutzung',
		'text'         => '',
		'cost'         => $cost,
		'gross'        => (int) $v['price_gross'] ? 1 : 0,
		'unit'         => 'pauschal' === (string) $v['price_basis'] ? 'pauschal' : 'person',
		'markup'       => $markup,
		'sale'         => (float) ( $v['sale_price'] ?? 0 ) > 0 ? (float) $v['sale_price'] : fge_pkg_sale( $cost, (bool) (int) $v['price_gross'], $markup ),
		'organizer'    => 'platz',
	] ];
	foreach ( fge_venue_items_get( (int) $v['id'] ) as $it ) {
		if ( 'green_fee' === (string) $it['wish_key'] || ! (int) $it['available'] ) {
			continue;
		}
		if ( (float) $it['price'] <= 0 && '' === trim( (string) ( $it['note'] ?? '' ) ) ) {
			continue;
		}
		$items[] = fge_pkg_item_from_venue_item( $req, $v, $it );
	}
	$pid  = (int) $v['partner_id'];
	$name = (string) get_post_meta( $pid, '_fge_public_golfclub_name', true ) ?: get_the_title( $pid );
	$f    = function_exists( 'fge_cc_request_fact_data' ) ? fge_cc_request_fact_data( $req ) : [ 'event' => '' ];
	return [
		'venue_id'    => (int) $v['id'],
		'title'       => trim( ( '' !== $f['event'] ? $f['event'] . ' im ' : '' ) . $name ),
		'intro'       => trim( (string) ( $v['option_text'] ?? '' ) ),
		'date_index'  => 0,
		'recommended' => false,
		'items'       => $items,
	];
}

/**
 * Pakete einer Anfrage. Hat eine laufende Anfrage noch Optionen alter Art
 * (Plätze mit in_offer), werden sie einmal als Pakete übernommen.
 */
function fge_offer_packages_get( int $req ): array {
	$pkgs = get_post_meta( $req, '_fge_offer_packages', true );
	if ( is_array( $pkgs ) ) {
		return array_values( $pkgs );
	}
	$pkgs = [];
	if ( '1' !== (string) get_post_meta( $req, '_fge_offer_sent', true ) && function_exists( 'fge_offer_options_venues' ) ) {
		$rec = (int) get_post_meta( $req, '_fge_offer_recommended_venue', true );
		foreach ( fge_offer_options_venues( $req ) as $v ) {
			$p                = fge_pkg_new( $req, $v );
			$p['recommended'] = $rec === (int) $v['id'];
			$pkgs[]           = $p;
		}
		if ( $pkgs ) {
			update_post_meta( $req, '_fge_offer_packages', $pkgs );
		}
	}
	return $pkgs;
}

function fge_offer_packages_save( int $req, array $pkgs ): void {
	update_post_meta( $req, '_fge_offer_packages', array_values( $pkgs ) );
}

/** Summen eines Pakets: Einkauf, Verkauf netto, Marge, brutto. */
function fge_pkg_totals( int $req, array $pkg ): array {
	$pax = max( 1, (int) get_post_meta( $req, '_fge_expected_participants', true ) );
	$ek  = 0.0;
	$vk  = 0.0;
	foreach ( (array) $pkg['items'] as $it ) {
		if ( 'verbrauch' === $it['unit'] ) {
			continue;
		}
		$q   = 'person' === $it['unit'] ? $pax : 1;
		$ek += fge_pkg_cost_net( $it ) * $q;
		$vk += (float) $it['sale'] * $q;
	}
	$vat = defined( 'FGE_VAT_PERCENT' ) ? (float) FGE_VAT_PERCENT : 19.0;
	return [ 'ek' => $ek, 'vk' => $vk, 'm' => $vk - $ek, 'pct' => $vk > 0 ? (int) round( ( $vk - $ek ) / $vk * 100 ) : 0, 'brutto' => $vk * ( 1 + $vat / 100 ), 'pax' => $pax ];
}

/** Freie Termine des Paket-Platzes, eingeschränkt auf den gewählten Termin. */
function fge_pkg_dates( int $req, array $pkg ): array {
	$labels = function_exists( 'fge_request_wish_date_labels' ) ? fge_request_wish_date_labels( $req ) : [];
	$out    = [];
	foreach ( function_exists( 'fge_venue_free_dates' ) ? fge_venue_free_dates( (int) $pkg['venue_id'] ) : [] as $idx ) {
		if ( isset( $labels[ $idx ] ) && ( (int) $pkg['date_index'] <= 0 || (int) $pkg['date_index'] === (int) $idx ) ) {
			$out[] = [ 'index' => (int) $idx, 'label' => (string) $labels[ $idx ] ];
		}
	}
	return $out;
}

/**
 * Option für Snapshot, Kundenseite und PDF aus einem Paket: gleiche Schlüssel wie
 * fge_offer_option_from_venue(), die Zusatzpositionen tragen Einkauf und
 * Dienstleister mit, damit die Annahme sie beauftragen kann.
 */
function fge_offer_option_from_package( int $req, array $pkg, string $key ): array {
	$v    = fge_venue_get( (int) $pkg['venue_id'] ) ?: [ 'id' => 0, 'partner_id' => 0 ];
	$base = fge_offer_option_from_venue( $req, $v + [ 'price' => 0, 'price_gross' => 1, 'price_basis' => 'person', 'markup_percent' => 20, 'sale_price' => 0, 'option_text' => '', 'status' => 'zugesagt' ], $key );
	$pax  = (int) get_post_meta( $req, '_fge_expected_participants', true );

	$extras = [];
	$gross  = 0.0;
	$unit   = 'pro Person';
	foreach ( (array) $pkg['items'] as $it ) {
		if ( 'base' === $it['kind'] ) {
			$gross = (float) $it['sale'];
			$unit  = 'pauschal' === $it['unit'] ? 'pauschal' : 'pro Person';
			continue;
		}
		$prov  = (int) ( $it['provider_row'] ?? 0 );
		$prow  = $prov > 0 ? fge_venue_get( $prov ) : null;
		$ppid  = $prow ? (int) $prow['partner_id'] : 0;
		$extras[] = [
			'label'          => (string) $it['title'],
			'price'          => 'verbrauch' === $it['unit'] ? 0.0 : (float) $it['sale'],
			'basis'          => (string) $it['unit'],
			'src'            => (int) $it['id'],
			'note'           => (string) $it['text'],
			'organizer'      => (string) $it['organizer'],
			'guide'          => '',
			'cost'           => (float) $it['cost'],
			'cost_gross'     => (int) $it['gross'],
			'markup'         => (float) $it['markup'],
			'provider_row'   => $prov,
			'provider_name'  => $ppid > 0 ? explode( ' ', trim( (string) get_post_meta( $ppid, '_fge_provider_contact', true ) ) )[0] : '',
			'provider_email' => $ppid > 0 && function_exists( 'fge_provider_email' ) ? fge_provider_email( $ppid ) : '',
		];
	}
	$dates = fge_pkg_dates( $req, $pkg );

	// Offene Wünsche neu gegen die Positionen dieses Pakets prüfen.
	$covered = array_map( static fn( $s ) => mb_strtolower( trim( (string) $s ) ), array_merge( array_column( $extras, 'label' ), (array) $base['not_possible'] ) );
	$g       = function_exists( 'fge_request_wish_groups' ) ? fge_request_wish_groups( $req ) : [ 'platz' => [], 'firmengolf' => [] ];
	$open    = static fn( array $list ): array => array_values( array_filter( array_map( 'strval', $list ), static fn( $w ) => ! in_array( mb_strtolower( trim( $w ) ), $covered, true ) ) );

	return array_merge( $base, [
		'title'             => '' !== trim( (string) $pkg['title'] ) ? (string) $pkg['title'] : $base['title'],
		'text'              => trim( (string) $pkg['intro'] ),
		'price_gross'       => $gross,
		'price_unit'        => $unit,
		'price_total'       => 'pro Person' === $unit && $pax > 0 ? $gross * $pax : $gross,
		'extras'            => $extras,
		'dates'             => $dates,
		'date'              => implode( ' oder ', array_column( $dates, 'label' ) ),
		'recommended'       => ! empty( $pkg['recommended'] ),
		'wishes_platz'      => $open( (array) ( $g['platz'] ?? [] ) ),
		'wishes_firmengolf' => $open( (array) ( $g['firmengolf'] ?? [] ) ),
	] );
}

/** Alle Optionen fürs Senden: aus den Paketen, sonst wie bisher aus den Plätzen. */
function fge_offer_options_list( int $req ): array {
	$out  = [];
	$pkgs = fge_offer_packages_get( $req );
	if ( $pkgs ) {
		foreach ( $pkgs as $i => $p ) {
			$out[] = fge_offer_option_from_package( $req, $p, fge_offer_option_key( $i + 1 ) );
		}
		return $out;
	}
	foreach ( fge_offer_options_venues( $req ) as $i => $v ) {
		$out[] = fge_offer_option_from_venue( $req, $v, fge_offer_option_key( $i + 1 ) );
	}
	return $out;
}

/** Warum die Pakete noch nicht raus können. Leer = bereit. */
function fge_offer_packages_blocker( int $req ): string {
	$pkgs = fge_offer_packages_get( $req );
	if ( ! $pkgs ) {
		return 'Noch kein Angebot angelegt.';
	}
	if ( (int) get_post_meta( $req, '_fge_expected_participants', true ) <= 0 ) {
		return 'Teilnehmerzahl fehlt (Pro-Kopf-Preise brauchen sie).';
	}
	$rec = 0;
	foreach ( $pkgs as $i => $p ) {
		$key = fge_offer_option_key( $i + 1 );
		$v   = fge_venue_get( (int) $p['venue_id'] );
		if ( ! $v || ! in_array( (string) $v['status'], [ 'zugesagt', 'gewaehlt' ], true ) ) {
			return 'Angebot ' . $key . ': Der Golfplatz hat nicht zugesagt.';
		}
		if ( ! fge_pkg_dates( $req, $p ) ) {
			return 'Angebot ' . $key . ': Kein freier Termin. Bitte in der Rückmeldung des Platzes je Termin „geht" setzen.';
		}
		$base = array_values( array_filter( (array) $p['items'], static fn( $it ) => 'base' === $it['kind'] ) );
		if ( ! $base || (float) $base[0]['sale'] <= 0 ) {
			return 'Angebot ' . $key . ': Event und Platznutzung hat keinen Verkaufspreis.';
		}
		$rec += ! empty( $p['recommended'] ) ? 1 : 0;
	}
	if ( count( $pkgs ) > 1 && 1 !== $rec ) {
		return 'Bitte genau ein Angebot als Empfehlung markieren.';
	}
	return '';
}

// ── Aktionen ─────────────────────────────────────────────────────────────────

/** Paket per Index aus dem POST, geprüft. */
function fge_cc_pkg_index_from_post( int $req ): int {
	$i    = absint( $_POST['pkg'] ?? -1 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$pkgs = fge_offer_packages_get( $req );
	// Nach dem Versand bleibt es änderbar: „Zurückziehen und neu auflegen" schickt
	// den aktuellen Stand. Nach der Annahme ist das Angebot fest.
	if ( ! isset( $pkgs[ $i ] ) || 'accepted' === (string) get_post_meta( $req, '_fge_offer_status', true ) ) {
		fge_cc_redirect( $req, 'nothing' );
	}
	return $i;
}

add_action( 'admin_post_fge_cc_pkg_add', static function (): void {
	$req  = fge_cc_guard( 'fge_cc_pkg_add' );
	$v    = fge_venue_get( absint( $_POST['venue_id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$pkgs = fge_offer_packages_get( $req );
	if ( ! $v || (int) $v['request_id'] !== $req || ! in_array( (string) $v['status'], [ 'zugesagt', 'gewaehlt' ], true ) ) {
		fge_cc_redirect( $req, 'nothing' );
	}
	if ( count( $pkgs ) >= FGE_OFFER_MAX_OPTIONS ) {
		fge_cc_redirect( $req, 'package_max' );
	}
	$p                = fge_pkg_new( $req, $v );
	$p['recommended'] = ! $pkgs;
	$pkgs[]           = $p;
	fge_offer_packages_save( $req, $pkgs );
	fge_activity_add( $req, 'offer', 'Angebot ' . fge_offer_option_key( count( $pkgs ) ) . ' angelegt (' . get_the_title( (int) $v['partner_id'] ) . ')' );
	fge_cc_redirect( $req, 'package_added' );
} );

add_action( 'admin_post_fge_cc_pkg_remove', static function (): void {
	$req  = fge_cc_guard( 'fge_cc_pkg_remove' );
	$i    = fge_cc_pkg_index_from_post( $req );
	$pkgs = fge_offer_packages_get( $req );
	$was  = ! empty( $pkgs[ $i ]['recommended'] );
	array_splice( $pkgs, $i, 1 );
	if ( $was && $pkgs ) {
		$pkgs[0]['recommended'] = true;
	}
	fge_offer_packages_save( $req, $pkgs );
	fge_activity_add( $req, 'offer', 'Angebot ' . fge_offer_option_key( $i + 1 ) . ' gelöscht' );
	fge_cc_redirect( $req, 'package_removed' );
} );

add_action( 'admin_post_fge_cc_pkg_save', static function (): void {
	$req  = fge_cc_guard( 'fge_cc_pkg_save' );
	$i    = fge_cc_pkg_index_from_post( $req );
	$pkgs = fge_offer_packages_get( $req );
	$p    = $pkgs[ $i ];
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- fge_cc_guard prüft die Nonce.
	$p['title']      = mb_substr( sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ), 0, 160 );
	$p['intro']      = mb_substr( sanitize_textarea_field( wp_unslash( $_POST['intro'] ?? '' ) ), 0, 2000 );
	$p['date_index'] = absint( $_POST['date_index'] ?? 0 );
	if ( ! empty( $_POST['recommended'] ) ) {
		foreach ( $pkgs as $j => $o ) {
			$pkgs[ $j ]['recommended'] = false;
		}
		$p['recommended'] = true;
	}

	// Positionen: Felder je ID, Entfernen per Häkchen. Der Grundpreis bleibt immer.
	$in   = (array) wp_unslash( $_POST['it'] ?? [] );
	$keep = [];
	foreach ( (array) $p['items'] as $it ) {
		$row = (array) ( $in[ $it['id'] ] ?? [] );
		if ( ! empty( $row['del'] ) && 'base' !== $it['kind'] ) {
			continue;
		}
		if ( $row ) {
			$it['title']  = mb_substr( sanitize_text_field( (string) ( $row['title'] ?? $it['title'] ) ), 0, 160 ) ?: $it['title'];
			$it['text']   = mb_substr( sanitize_textarea_field( (string) ( $row['text'] ?? '' ) ), 0, 600 );
			$it['cost']   = max( 0.0, fge_xs_parse_num( sanitize_text_field( (string) ( $row['cost'] ?? '0' ) ) ) );
			$it['gross']  = '1' === (string) ( $row['gross'] ?? '0' ) ? 1 : 0;
			$unit         = sanitize_key( (string) ( $row['unit'] ?? 'person' ) );
			$it['unit']   = in_array( $unit, [ 'person', 'pauschal', 'verbrauch' ], true ) ? $unit : 'person';
			if ( 'base' === $it['kind'] && 'verbrauch' === $it['unit'] ) {
				$it['unit'] = 'person';
			}
			$markup       = fge_xs_parse_num( sanitize_text_field( (string) ( $row['markup'] ?? '' ) ) );
			$sale         = fge_xs_parse_num( sanitize_text_field( (string) ( $row['sale'] ?? '' ) ) );
			$it['markup'] = min( 500.0, max( 0.0, $markup ) );
			// Verkauf schlägt Aufschlag (beide hängen im Formular zusammen); leer = aus dem Aufschlag.
			$it['sale']   = $sale > 0 ? $sale : fge_pkg_sale( (float) $it['cost'], (bool) $it['gross'], $it['markup'] );
			if ( 'verbrauch' === $it['unit'] ) {
				$it['sale'] = 0.0;
			}
		}
		$keep[] = $it;
	}
	$p['items'] = $keep;

	// Neue Position: alles von einem Partner, eine einzelne Leistung oder frei.
	$add = sanitize_text_field( wp_unslash( $_POST['add'] ?? '' ) );
	// phpcs:enable
	if ( '' !== $add ) {
		$have = array_map( static fn( $it ) => (int) $it['ref'] . ':' . $it['kind'], (array) $p['items'] );
		if ( 'free' === $add ) {
			$p['items'][] = [ 'id' => fge_pkg_next_id( $req ), 'kind' => 'free', 'ref' => 0, 'provider_row' => 0, 'title' => 'Neue Position', 'text' => '', 'cost' => 0.0, 'gross' => 0, 'unit' => 'pauschal', 'markup' => FGE_PKG_DEFAULT_MARKUP, 'sale' => 0.0, 'organizer' => 'extern' ];
		} elseif ( preg_match( '/^(all|one):(\d+)(?::(\d+))?$/', $add, $m ) ) {
			$row = fge_venue_get( (int) $m[2] );
			if ( $row && (int) $row['request_id'] === $req && in_array( (string) $row['status'], [ 'zugesagt', 'gewaehlt' ], true ) ) {
				foreach ( fge_venue_items_get( (int) $row['id'] ) as $vi ) {
					$kind = 'dl' === (string) $row['kind'] ? 'provider' : 'venue';
					if ( 'green_fee' === (string) $vi['wish_key'] || ! (int) $vi['available'] || in_array( (int) $vi['id'] . ':' . $kind, $have, true ) ) {
						continue;
					}
					if ( 'one' === $m[1] && (int) ( $m[3] ?? 0 ) !== (int) $vi['id'] ) {
						continue;
					}
					$p['items'][] = fge_pkg_item_from_venue_item( $req, $row, $vi );
				}
			}
		}
	}
	$pkgs[ $i ] = $p;
	fge_offer_packages_save( $req, $pkgs );
	fge_cc_redirect( $req, 'packages_saved' );
} );

add_action( 'admin_post_fge_cc_offer_reco_save', static function (): void {
	$req = fge_cc_guard( 'fge_cc_offer_reco_save' );
	update_post_meta( $req, '_fge_offer_recommendation', mb_substr( sanitize_textarea_field( wp_unslash( $_POST['opt_recommendation'] ?? '' ) ), 0, 1500 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	fge_cc_redirect( $req, 'saved' );
} );

// ── Oberfläche: Angebote im Schritt Kalkulation ──────────────────────────────

function fge_cc_packages_panel( int $req ): void {
	$pkgs  = fge_offer_packages_get( $req );
	$golf  = array_values( array_filter( fge_venues_get( $req ), static fn( $v ) => in_array( (string) $v['status'], [ 'zugesagt', 'gewaehlt' ], true ) ) );
	$provs = array_values( array_filter( fge_venues_get( $req, 'dl' ), static fn( $v ) => in_array( (string) $v['status'], [ 'zugesagt', 'gewaehlt' ], true ) ) );

	foreach ( $pkgs as $i => $p ) {
		fge_cc_package_card( $req, $i, $p, $golf, $provs, count( $pkgs ) );
	}
	if ( ! $pkgs ) {
		echo '<p class="cc-rowempty">Noch kein Angebot. Lege eins für einen Golfplatz an, der zugesagt hat. Die Positionen kommen aus seiner Rückmeldung, Dienstleister nimmst du danach dazu.</p>';
	}

	$sent = '1' === (string) get_post_meta( $req, '_fge_offer_sent', true );
	echo '<div class="cc-stepfoot cc-stepfoot--offer">';
	if ( $sent ) {
		echo '<span class="cc-muted">Das Angebot ist beim Kunden. Änderungen hier gehen erst mit „Zurückziehen und neu auflegen" im Schritt Angebot raus.</span></div>';
		return;
	}
	if ( count( $pkgs ) < FGE_OFFER_MAX_OPTIONS && $golf ) {
		echo '<form class="cc-inlineform" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="fge_cc_pkg_add"><input type="hidden" name="request_id" value="' . (int) $req . '">';
		wp_nonce_field( 'fge_cc_pkg_add_' . $req );
		echo '<select name="venue_id" aria-label="Golfplatz für das neue Angebot">';
		foreach ( $golf as $v ) {
			echo '<option value="' . (int) $v['id'] . '">' . esc_html( (string) $v['title'] ) . '</option>';
		}
		echo '</select><button type="submit" class="cc-btn">Angebot ' . esc_html( fge_offer_option_key( count( $pkgs ) + 1 ) ) . ' anlegen</button></form>';
	} elseif ( ! $golf ) {
		echo '<span class="cc-muted">Sobald ein Golfplatz zugesagt hat, kannst du ein Angebot anlegen.</span>';
	}
	$blocker = fge_offer_packages_blocker( $req );
	if ( $pkgs && '' !== $blocker ) {
		echo '<span class="cc-note-warn">' . esc_html( $blocker ) . '</span>';
	}
	if ( $pkgs ) {
		$n = count( $pkgs );
		echo '<button type="button" class="cc-btn cc-btn--primary"' . ( '' === $blocker ? '' : ' disabled' ) . ' data-cc-drawer-open="cc-sendoffer">' . esc_html( $n > 1 ? $n . ' Angebote online senden' : 'Angebot online senden' ) . '</button>';
	}
	echo '</div>';

	if ( count( $pkgs ) > 1 ) {
		echo '<form class="cc-form cc-recoform" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="fge_cc_offer_reco_save"><input type="hidden" name="request_id" value="' . (int) $req . '">';
		wp_nonce_field( 'fge_cc_offer_reco_save_' . $req );
		echo '<label class="cc-field cc-field--wide"><span>Empfehlung an den Kunden (steht über den Angeboten)</span><textarea name="opt_recommendation" rows="2" placeholder="z. B. Unsere Empfehlung ist Angebot A: kürzeste Anfahrt, Abendessen direkt am Platz.">' . esc_textarea( (string) get_post_meta( $req, '_fge_offer_recommendation', true ) ) . '</textarea></label>';
		echo '<p><button type="submit" class="cc-btn">Empfehlung speichern</button></p></form>';
	}
	if ( $pkgs && '' === $blocker && function_exists( 'fge_cc_offer_send_drawer' ) ) {
		fge_cc_offer_send_drawer( $req );
	}
}

/** Ein Angebot als Karte mit Positionstabelle, wie im Entwurf. */
function fge_cc_package_card( int $req, int $i, array $p, array $golf, array $provs, int $count ): void {
	$key    = fge_offer_option_key( $i + 1 );
	$v      = fge_venue_get( (int) $p['venue_id'] );
	$labels = function_exists( 'fge_request_wish_date_labels' ) ? fge_request_wish_date_labels( $req ) : [];
	$free   = $v && function_exists( 'fge_venue_free_dates' ) ? fge_venue_free_dates( (int) $v['id'] ) : [];
	$t      = fge_pkg_totals( $req, $p );
	$money  = static fn( float $x ): string => number_format_i18n( $x, ( abs( $x - round( $x ) ) > 0.004 ) ? 2 : 0 ) . ' €';
	$form   = 'cc-pkg-' . $i;

	echo '<section class="cc-pkg">';
	echo '<div class="cc-pkg-head"><strong>Angebot ' . esc_html( $key ) . '</strong>';
	echo fge_cc_pill( ! empty( $p['recommended'] ) && $count > 1 ? 'Empfehlung' : 'Entwurf', ! empty( $p['recommended'] ) && $count > 1 ? 'brand' : 'neutral' ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<span class="cc-muted">' . esc_html( $v ? (string) get_the_title( (int) $v['partner_id'] ) : 'Golfplatz fehlt' ) . '</span>';
	echo '<span class="cc-grow"></span>';
	echo '<button type="button" class="cc-btn" data-cc-drawer-open="cc-pv-' . $i . '">Vorschau</button>';
	echo '<button type="submit" form="' . esc_attr( $form ) . '-rm" class="cc-btn cc-btn--danger" data-cc-confirm="' . esc_attr( 'Angebot ' . $key . ' löschen?' ) . '">Löschen</button>';
	echo '</div>';

	echo '<form id="' . esc_attr( $form ) . '" class="cc-form cc-pkgform" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-cc-pkg data-pax="' . (int) $t['pax'] . '">';
	echo '<input type="hidden" name="action" value="fge_cc_pkg_save"><input type="hidden" name="request_id" value="' . (int) $req . '"><input type="hidden" name="pkg" value="' . (int) $i . '">';
	wp_nonce_field( 'fge_cc_pkg_save_' . $req );

	echo '<div class="cc-fields cc-fields--pkg">';
	echo '<label class="cc-field"><span>Titel im Angebot</span><input type="text" name="title" value="' . esc_attr( (string) $p['title'] ) . '"></label>';
	echo '<label class="cc-field"><span>Termin</span><select name="date_index">';
	echo '<option value="0"' . selected( 0, (int) $p['date_index'], false ) . '>' . esc_html( count( $free ) > 1 ? 'Kunde wählt aus allen freien' : ( $free ? 'Freier Termin' : 'kein Termin frei' ) ) . '</option>';
	foreach ( $free as $idx ) {
		if ( isset( $labels[ $idx ] ) && count( $free ) > 1 ) {
			echo '<option value="' . (int) $idx . '"' . selected( (int) $idx, (int) $p['date_index'], false ) . '>' . esc_html( (string) $labels[ $idx ] ) . '</option>';
		}
	}
	echo '</select></label>';
	echo '<label class="cc-field cc-field--wide"><span>Einleitung</span><textarea name="intro" rows="2" placeholder="Kurzer Text zu diesem Angebot, steht beim Kunden über den Positionen">' . esc_textarea( (string) $p['intro'] ) . '</textarea></label>';
	echo '</div>';
	if ( $count > 1 ) {
		echo '<label class="cc-checkline"><input type="checkbox" name="recommended" value="1"' . checked( ! empty( $p['recommended'] ), true, false ) . '><span><strong>Als Empfehlung markieren</strong></span></label>';
	}

	echo '<div class="cc-pkgtable-wrap"><table class="cc-pkgtable"><thead><tr><th>Position</th><th>Einkauf</th><th>Einheit</th><th>Aufschlag</th><th>Verkauf</th><th>Summe</th><th>Marge</th><th><span class="cc-sr">Entfernen</span></th></tr></thead><tbody>';
	foreach ( (array) $p['items'] as $it ) {
		$id   = (int) $it['id'];
		$n    = 'it[' . $id . ']';
		$q    = 'person' === $it['unit'] ? $t['pax'] : 1;
		$net  = fge_pkg_cost_net( $it );
		$sum  = 'verbrauch' === $it['unit'] ? 0.0 : (float) $it['sale'] * $q;
		$m    = 'verbrauch' === $it['unit'] ? 0.0 : ( (float) $it['sale'] - $net ) * $q;
		$pct  = $sum > 0 ? (int) round( $m / $sum * 100 ) : 0;
		$src  = 'base' === $it['kind'] || 'venue' === $it['kind'] ? ( $v ? (string) get_the_title( (int) $v['partner_id'] ) : '' )
			: ( 'provider' === $it['kind'] ? (string) get_the_title( (int) ( fge_venue_get( (int) $it['provider_row'] )['partner_id'] ?? 0 ) ) : 'Freie Position' );
		echo '<tr data-pkg-row data-net-of>';
		echo '<td class="cc-pkg-pos"><input type="text" name="' . esc_attr( $n ) . '[title]" value="' . esc_attr( (string) $it['title'] ) . '" aria-label="Position"' . ( 'base' === $it['kind'] ? '' : '' ) . '>';
		echo '<textarea name="' . esc_attr( $n ) . '[text]" rows="1" placeholder="Freitext für den Kunden" aria-label="Freitext">' . esc_textarea( (string) $it['text'] ) . '</textarea>';
		echo '<span class="cc-muted">' . esc_html( $src . ( 'person' === $it['unit'] ? ' · ' . $t['pax'] . ' Personen' : '' ) ) . '</span></td>';
		echo '<td><input type="text" name="' . esc_attr( $n ) . '[cost]" value="' . esc_attr( (float) $it['cost'] > 0 ? number_format( (float) $it['cost'], 2, ',', '' ) : '' ) . '" inputmode="decimal" class="cc-num-in" data-cost aria-label="Einkauf">';
		echo '<select name="' . esc_attr( $n ) . '[gross]" data-gross aria-label="brutto oder netto"><option value="0"' . selected( 0, (int) $it['gross'], false ) . '>netto</option><option value="1"' . selected( 1, (int) $it['gross'], false ) . '>brutto</option></select></td>';
		echo '<td><select name="' . esc_attr( $n ) . '[unit]" data-unit aria-label="Einheit"><option value="person"' . selected( 'person', $it['unit'], false ) . '>pro Person</option><option value="pauschal"' . selected( 'pauschal', $it['unit'], false ) . '>pauschal</option>'
			. ( 'base' === $it['kind'] ? '' : '<option value="verbrauch"' . selected( 'verbrauch', $it['unit'], false ) . '>nach Verbrauch</option>' ) . '</select></td>';
		echo '<td><span class="cc-pct"><input type="text" name="' . esc_attr( $n ) . '[markup]" value="' . esc_attr( rtrim( rtrim( number_format( (float) $it['markup'], 1, ',', '' ), '0' ), ',' ) ) . '" inputmode="decimal" class="cc-num-in" data-markup aria-label="Aufschlag in Prozent">%</span></td>';
		echo '<td><input type="text" name="' . esc_attr( $n ) . '[sale]" value="' . esc_attr( (float) $it['sale'] > 0 ? number_format( (float) $it['sale'], 0, ',', '' ) : '' ) . '" inputmode="decimal" class="cc-num-in" data-sale aria-label="Verkauf netto"></td>';
		echo '<td class="cc-num cc-strong" data-sum>' . esc_html( $money( $sum ) ) . '</td>';
		echo '<td class="cc-num" data-margin><span class="' . ( $pct < 10 ? 'is-bad' : 'is-good' ) . '">' . esc_html( $money( $m ) . ' · ' . $pct . ' %' ) . '</span></td>';
		echo '<td>' . ( 'base' === $it['kind'] ? '' : '<label class="cc-svcrow-x" title="Position entfernen"><input type="checkbox" name="' . esc_attr( $n ) . '[del]" value="1"><span aria-hidden="true">×</span><span class="cc-sr">Position entfernen</span></label>' ) . '</td>';
		echo '</tr>';
	}
	echo '</tbody></table></div>';

	echo '<div class="cc-pkg-foot"><div class="cc-pkg-add">';
	echo '<select name="add" aria-label="Position hinzufügen"><option value="">Position hinzufügen</option>';
	foreach ( array_merge( $v ? [ $v ] : [], $provs ) as $row ) {
		$row['title'] = (string) ( $row['title'] ?? get_the_title( (int) $row['partner_id'] ) );
		$items = array_values( array_filter( fge_venue_items_get( (int) $row['id'] ), static fn( $x ) => 'green_fee' !== (string) $x['wish_key'] && (int) $x['available'] ) );
		if ( ! $items ) {
			continue;
		}
		echo '<option value="all:' . (int) $row['id'] . '">Alle Leistungen von ' . esc_html( (string) $row['title'] ) . '</option>';
		foreach ( $items as $x ) {
			echo '<option value="one:' . (int) $row['id'] . ':' . (int) $x['id'] . '">' . esc_html( $row['title'] . ' · ' . $x['label'] . ( (float) $x['price'] > 0 ? ' · ' . number_format_i18n( (float) $x['price'], 2 ) . ' €' : '' ) ) . '</option>';
		}
	}
	echo '<option value="free">Freie Position</option></select>';
	echo '<button type="submit" class="cc-btn cc-btn--primary">Speichern</button></div>';
	echo '<dl class="cc-pkg-totals" data-totals>';
	echo '<dt>Einkauf</dt><dd data-t-ek>' . esc_html( $money( $t['ek'] ) ) . '</dd>';
	echo '<dt>Verkauf netto</dt><dd class="cc-strong" data-t-vk>' . esc_html( $money( $t['vk'] ) ) . '</dd>';
	echo '<dt>Marge</dt><dd class="cc-strong ' . ( $t['pct'] < 10 ? 'is-bad' : 'is-good' ) . '" data-t-m>' . esc_html( $money( $t['m'] ) . ' · ' . $t['pct'] . ' %' ) . '</dd>';
	echo '<dt>Brutto für den Kunden</dt><dd data-t-b>' . esc_html( $money( $t['brutto'] ) ) . '</dd>';
	echo '</dl></div>';
	echo '</form>';

	echo '<form id="' . esc_attr( $form ) . '-rm" class="cc-hiddenform" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="fge_cc_pkg_remove"><input type="hidden" name="request_id" value="' . (int) $req . '"><input type="hidden" name="pkg" value="' . (int) $i . '">';
	wp_nonce_field( 'fge_cc_pkg_remove_' . $req );
	echo '</form>';
	echo '</section>';

	fge_cc_package_preview( $req, $i, $p );
}

/** Vorschau, wie der Kunde dieses Angebot sieht (gespeicherter Stand). */
function fge_cc_package_preview( int $req, int $i, array $p ): void {
	$key  = fge_offer_option_key( $i + 1 );
	$co   = function_exists( 'fge_company' ) ? fge_company() : [];
	$data = fge_get_request_email_data( $req );
	$t    = fge_pkg_totals( $req, $p );
	$o    = fge_offer_option_from_package( $req, $p, $key );
	$vat  = defined( 'FGE_VAT_PERCENT' ) ? (float) FGE_VAT_PERCENT : 19.0;
	$days = (int) apply_filters( 'fge_offer_response_days', 7 );
	$eur  = static fn( float $x ): string => number_format_i18n( $x, 2 ) . ' €';
	$f    = fge_cc_request_fact_data( $req );
	$logo = plugins_url( 'assets/logo/firmengolf-logo.svg', FGE_DIR . 'firmengolf-events.php' );

	fge_cc_drawer_open( 'cc-pv-' . $i, 'Vorschau Angebot ' . $key, 'So sieht der Kunde das Angebot, gespeicherter Stand' );
	echo '<article class="cc-doc">';
	echo '<div class="cc-doc-top"><img src="' . esc_url( $logo ) . '" alt="Firmengolf" class="cc-doc-logo"><div class="cc-doc-from">' . esc_html( (string) ( $co['legal_name'] ?? '' ) ) . '<br>' . esc_html( (string) ( $co['managing_director'] ?? '' ) ) . '<br>' . esc_html( (string) ( $co['email_events'] ?? '' ) ) . '</div></div>';
	echo '<div class="cc-doc-meta"><div><strong>' . esc_html( (string) $data['company_name'] ) . '</strong><br>' . esc_html( trim( $data['first_name'] . ' ' . $data['last_name'] ) ) . '<br>' . esc_html( (string) get_post_meta( $req, '_fge_company_city', true ) ) . '</div>';
	echo '<dl><dt>Angebot</dt><dd class="cc-mono">' . esc_html( fge_request_number( $req ) . '-' . $key ) . '</dd><dt>Datum</dt><dd>' . esc_html( wp_date( 'd.m.Y' ) ) . '</dd><dt>Gültig bis</dt><dd>' . esc_html( wp_date( 'd.m.Y', time() + $days * DAY_IN_SECONDS ) ) . '</dd></dl></div>';
	echo '<h3>' . esc_html( (string) $o['title'] ) . '</h3>';
	echo '<p class="cc-muted">' . esc_html( implode( ' · ', array_filter( [ (string) $o['date'], $t['pax'] . ' Personen', '' !== $f['start'] ? 'Start ' . $f['start'] : '' ] ) ) ) . '</p>';
	if ( '' !== trim( (string) $p['intro'] ) ) {
		echo '<p>' . nl2br( esc_html( (string) $p['intro'] ) ) . '</p>';
	}
	echo '<table class="cc-doc-table"><thead><tr><th>Leistung</th><th>Menge</th><th>Einzelpreis</th><th>Gesamt</th></tr></thead><tbody>';
	foreach ( (array) $p['items'] as $it ) {
		$q   = 'person' === $it['unit'] ? $t['pax'] : 1;
		$con = 'verbrauch' === $it['unit'];
		echo '<tr><td><strong>' . esc_html( (string) $it['title'] ) . '</strong>' . ( '' !== trim( (string) $it['text'] ) ? '<br><span>' . esc_html( (string) $it['text'] ) . '</span>' : '' ) . '</td>';
		echo '<td>' . esc_html( $con ? 'nach Verbrauch' : ( 'person' === $it['unit'] ? $q . ' P.' : 'pauschal' ) ) . '</td>';
		echo '<td>' . esc_html( $con ? '' : $eur( (float) $it['sale'] ) ) . '</td><td>' . esc_html( $con ? '' : $eur( (float) $it['sale'] * $q ) ) . '</td></tr>';
	}
	echo '</tbody></table>';
	echo '<dl class="cc-doc-sum"><dt>Summe netto</dt><dd>' . esc_html( $eur( $t['vk'] ) ) . '</dd><dt>zzgl. ' . esc_html( rtrim( rtrim( number_format_i18n( $vat, 1 ), '0' ), ',' ) ) . ' % USt.</dt><dd>' . esc_html( $eur( $t['vk'] * $vat / 100 ) ) . '</dd><dt><strong>Gesamt brutto</strong></dt><dd><strong>' . esc_html( $eur( $t['brutto'] ) ) . '</strong></dd></dl>';
	echo '<p class="cc-muted">Die Kundenseite und das PDF zeigen dieselben Beträge in ihrer eigenen Gestaltung. Zusatzleistungen kann der Kunde dort einzeln abwählen.</p>';
	echo '</article>';
	fge_cc_drawer_close();
}
