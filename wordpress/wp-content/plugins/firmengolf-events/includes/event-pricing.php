<?php
/**
 * Angebots-Preismodell (rev. 2) — eine Quelle für die Preisberechnung.
 *
 * Partner hinterlegt NETTO (Gesamtbetrag oder Einzelposten). Der Firmengolf-Aufschlag
 * (fix 20 %) kommt OBEN DRAUF — er wird nie vom Partner-Anteil abgezogen.
 * Quelle: _design_neu/partner-portal/EditApp.jsx (netSum, *0.2, *1.2).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Fixer Firmengolf-Vermittlungsaufschlag in Prozent. */
const FGE_MARKUP_PERCENT = 20;

/**
 * Glättet den Kundenpreis auf Endziffer 4 oder 9 (Julius, 2026-08-27, Entscheidung 2
 * in docs/onboarding-golflehrer-indoor.md Abschnitt 4): 5er- und 50er-Endungen wie
 * 65/70/650 wirkten gesetzt statt kalkuliert. Neu: dasselbe Raster (5 pro Person,
 * 50 pauschal), nur um eins nach unten versetzt, also 64, 69, 649, 1.049.
 * Immer AUFgerundet, Ergebnis nie unter dem Bruttopreis: Der Partner erhält weiterhin
 * exakt sein Netto, die Rundungsdifferenz ist zusätzliche Firmengolf-Marge (Aufschlag
 * mindestens 20 %). Preise, die schon auf 4 oder 9 enden, bleiben unverändert.
 * JS-Zwilling in der Portal-Summenbox (partner-portal.php) identisch halten!
 */
function fge_price_smooth( float $gross, string $unit ): float {
	if ( $gross <= 0 ) {
		return 0.0;
	}
	$step = ( 'pro Person' === $unit ) ? 5 : 50;
	return (float) ( ceil( ( $gross + 1 ) / $step ) * $step - 1 );
}

/** Gesetzliche Umsatzsteuer in Prozent. Preise (gross) werden NETTO ausgewiesen, USt kommt oben drauf. */
const FGE_VAT_PERCENT = 19;

/**
 * Reine Preisberechnung (ohne DB).
 *
 * @param string                              $mode       'gesamt' | 'einzel'
 * @param float                               $amount     Gesamtbetrag (bei 'gesamt')
 * @param string                              $basis      'person' | 'pauschal' (nur bei 'gesamt')
 * @param array<int,array{label:string,cost:mixed}> $line_items Einzelposten (bei 'einzel')
 * @return array{net:float,markup:float,gross:float,unit:string,basis:string,mode:string}
 */
function fge_event_pricing_calc( string $mode, float $amount, string $basis, array $line_items, int $pax_max = 0 ): array {
	$mode  = ( $mode === 'einzel' ) ? 'einzel' : 'gesamt';
	$basis = ( $basis === 'pauschal' ) ? 'pauschal' : 'person';

	$pp_net   = 0.0; // Summe der Pro-Person-Posten
	$flat_net = 0.0; // Summe der Pauschal-Posten

	if ( $mode === 'einzel' ) {
		foreach ( $line_items as $i ) {
			$cost = (float) ( $i['cost'] ?? 0 );
			if ( ( $i['basis'] ?? 'pauschal' ) === 'person' ) {
				$pp_net += $cost;
			} else {
				$flat_net += $cost; // Bestand ohne basis = pauschal (bisheriges Verhalten)
			}
		}
		if ( $pp_net > 0 ) {
			// „ab"-Preis pro Person: Pauschalen auf die maximale Gruppengröße umgelegt
			// (volle Gruppe = günstigster p.P.-Preis). Ohne max keine Umlage möglich →
			// die Formular-Validierung erzwingt max bei gemischten Posten.
			$net  = $pp_net + ( $pax_max > 0 ? $flat_net / $pax_max : 0 );
			$unit = 'pro Person';
		} else {
			$net  = $flat_net;
			$unit = 'gesamt';
		}
	} else {
		$net      = max( 0.0, $amount );
		$unit     = ( $basis === 'person' ) ? 'pro Person' : 'gesamt';
		$pp_net   = 'pro Person' === $unit ? $net : 0.0;
		$flat_net = 'pro Person' === $unit ? 0.0 : $net;
	}

	// Kundenpreis geglättet (5er- bzw. 50er-Stufen, aufgerundet); der ausgewiesene
	// Aufschlag enthält die Rundungsdifferenz und ist damit "mindestens 20 %".
	$gross  = fge_price_smooth( $net * ( 1 + FGE_MARKUP_PERCENT / 100 ), $unit );
	$markup = round( $gross - $net, 2 );

	return [
		'net'      => round( $net, 2 ),
		'markup'   => $markup,
		'gross'    => $gross,
		'unit'     => $unit,
		'basis'    => $basis,
		'mode'     => $mode,
		'pp_net'   => round( $pp_net, 2 ),
		'flat_net' => round( $flat_net, 2 ),
		'pax_max'  => $pax_max,
	];
}

/**
 * Preis eines Events aus den Meta-Feldern.
 *
 * @return array{net:float,markup:float,gross:float,unit:string,basis:string,mode:string}
 */
function fge_event_pricing( int $event_id ): array {
	$mode   = (string) get_post_meta( $event_id, '_fge_price_mode', true ) ?: 'gesamt';
	$amount = (float) get_post_meta( $event_id, '_fge_price_amount', true );
	$basis  = (string) get_post_meta( $event_id, '_fge_price_basis', true ) ?: 'person';
	$items  = (array) get_post_meta( $event_id, '_fge_line_items', true );
	$pax    = (int) get_post_meta( $event_id, '_fge_participants_max', true );
	if ( $pax <= 0 ) {
		// Sicherheitsnetz (Julius, 02.09., Simulator-Fund): Ohne max. Teilnehmer
		// fiele die Pauschale bei gemischten Posten stillschweigend aus dem
		// p.P.-Preis. Dann wenigstens auf die Mindestteilnehmer umlegen.
		$pax = (int) get_post_meta( $event_id, '_fge_participants_min', true );
	}
	return fge_event_pricing_calc( $mode, $amount, $basis, $items, $pax );
}

/**
 * Preis für eine KONKRETE Teilnehmerzahl (Angebots-/Anfragepfad, Julius 02.09.):
 * Pauschal-Posten werden auf die tatsächlich angefragte Personenzahl umgelegt,
 * nicht auf das Maximum. Weniger Personen → höherer p.P.-Preis; der Partner
 * bekommt so immer sein volles Netto inklusive kompletter Pauschalen. Die
 * öffentliche Karte zeigt weiter den günstigsten „ab"-Preis (volle Gruppe).
 */
function fge_event_pricing_for_pax( int $event_id, int $pax ): array {
	if ( $pax <= 0 ) {
		return fge_event_pricing( $event_id );
	}
	$mode   = (string) get_post_meta( $event_id, '_fge_price_mode', true ) ?: 'gesamt';
	$amount = (float) get_post_meta( $event_id, '_fge_price_amount', true );
	$basis  = (string) get_post_meta( $event_id, '_fge_price_basis', true ) ?: 'person';
	$items  = (array) get_post_meta( $event_id, '_fge_line_items', true );
	return fge_event_pricing_calc( $mode, $amount, $basis, $items, $pax );
}

/** Formatierter Kundenpreis (netto, zzgl. USt) fürs Unternehmen, z. B. „320 € p.P." oder „2.400 € gesamt". */
function fge_event_price_label( int $event_id ): string {
	$p = fge_event_pricing( $event_id );
	if ( $p['gross'] <= 0 ) {
		return 'Auf Anfrage';
	}
	$amount = number_format_i18n( $p['gross'], 0 ) . ' €'; // Suffix-Format (Kern-Audit H6)
	return $p['unit'] === 'pro Person' ? $amount . ' p.P.' : $amount . ' gesamt';
}

// ── Preisspannen für die Landingpages ────────────────────────────────────────
//
// Die Landingpages nannten Preise früher hartkodiert („Ab 190 € pro Person"),
// während die buchbaren Events längst andere Zahlen hatten (Audit 2026-08-12,
// fünf Widersprüche). Julius' Vorgabe: variabel rechnen und immer den
// günstigsten realen Preis zeigen, verlinkt auf das Angebot dahinter. Damit
// stimmt die Aussage automatisch, sobald ein Platz ein neues Angebot einstellt.

/** Preis als „20 €" (ohne Nachkommastellen, deutsche Tausendertrennung). */
function fge_price_eur( float $amount ): string {
	return number_format_i18n( $amount, 0 ) . ' €';
}

/**
 * Alle öffentlich buchbaren Pro-Person-Preise, gruppiert nach `_fge_event_type`.
 * Pauschal-/Gesamtpreise bleiben außen vor: aus ihnen lässt sich kein
 * belastbares „ab X € pro Person" ableiten.
 *
 * @return array<string,array{min:float,max:float,min_id:int}>
 */
function fge_person_price_index(): array {
	$cached = get_transient( 'fge_person_price_index' );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$index = [];
	$ids   = get_posts( [
		'post_type'        => 'firmengolf_event',
		'post_status'      => 'publish',
		'posts_per_page'   => -1,
		'fields'           => 'ids',
		'suppress_filters' => true,
		'no_found_rows'    => true,
		'meta_query'       => [
			[ 'key' => '_fge_event_status', 'value' => fge_public_event_statuses(), 'compare' => 'IN' ],
		],
	] );

	foreach ( $ids as $id ) {
		$id = (int) $id;
		if ( ! fge_event_is_public( $id ) ) {
			continue;
		}
		$p = fge_event_pricing( $id );
		if ( 'pro Person' !== $p['unit'] || $p['gross'] <= 0 ) {
			continue;
		}
		$type = (string) get_post_meta( $id, '_fge_event_type', true );
		if ( '' === $type ) {
			continue;
		}
		if ( ! isset( $index[ $type ] ) ) {
			$index[ $type ] = [ 'min' => $p['gross'], 'max' => $p['gross'], 'min_id' => $id ];
			continue;
		}
		if ( $p['gross'] < $index[ $type ]['min'] ) {
			$index[ $type ]['min']    = $p['gross'];
			$index[ $type ]['min_id'] = $id;
		}
		$index[ $type ]['max'] = max( $index[ $type ]['max'], $p['gross'] );
	}

	set_transient( 'fge_person_price_index', $index, DAY_IN_SECONDS );
	return $index;
}

/** Cache verwerfen, sobald sich an Events, Preisen oder Freigaben etwas ändert. */
function fge_flush_person_price_index(): void {
	delete_transient( 'fge_person_price_index' );
}

add_action( 'save_post_firmengolf_event', 'fge_flush_person_price_index' );
add_action( 'deleted_post', 'fge_flush_person_price_index' );
add_action( 'trashed_post', 'fge_flush_person_price_index' );
add_action( 'untrashed_post', 'fge_flush_person_price_index' );
// Portal und Freigabe-Workflow schreiben Preis- und Statusmetas teils ohne save_post.
add_action( 'updated_post_meta', static function ( $mid, $post_id, $key ): void {
	if ( in_array( (string) $key, [ '_fge_sale_price_net', '_fge_event_status', '_fge_price_amount', '_fge_line_items', '_fge_event_type' ], true ) ) {
		fge_flush_person_price_index();
	}
}, 10, 3 );
add_action( 'added_post_meta', static function ( $mid, $post_id, $key ): void {
	if ( in_array( (string) $key, [ '_fge_sale_price_net', '_fge_event_status', '_fge_price_amount', '_fge_line_items', '_fge_event_type' ], true ) ) {
		fge_flush_person_price_index();
	}
}, 10, 3 );

/**
 * Preisspanne pro Person über mehrere Event-Typen (= ein Format-Hub).
 *
 * @param string[] $types Event-Typ-Keys des Formats.
 * @return array{min:float,max:float,min_id:int,min_url:string,min_title:string}|null
 */
function fge_format_price_range( array $types ): ?array {
	$index = fge_person_price_index();
	$out   = null;
	foreach ( $types as $type ) {
		if ( ! isset( $index[ $type ] ) ) {
			continue;
		}
		$row = $index[ $type ];
		if ( null === $out ) {
			$out = $row;
			continue;
		}
		if ( $row['min'] < $out['min'] ) {
			$out['min']    = $row['min'];
			$out['min_id'] = $row['min_id'];
		}
		$out['max'] = max( $out['max'], $row['max'] );
	}
	if ( null === $out ) {
		return null;
	}
	// Stadt-×-Format-Seiten (Review 07.09.): das günstigste Event DIESER Stadt nennen,
	// nicht das aus Köln, wenn direkt darüber das Hamburger Angebot steht.
	$city = (string) ( $GLOBALS['fge_price_range_city'] ?? '' );
	if ( '' !== $city ) {
		$city_posts = get_posts( [
			'post_type'      => 'firmengolf_event',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'fields'         => 'ids',
			'meta_query'     => [
				[ 'key' => '_fge_event_type', 'value' => $types, 'compare' => 'IN' ],
				[ 'key' => '_fge_city', 'value' => $city ],
				[ 'key' => '_fge_price_amount', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ],
			],
		] );
		$best = null;
		foreach ( $city_posts as $cid ) {
			if ( function_exists( 'fge_event_is_public' ) && ! fge_event_is_public( (int) $cid ) ) {
				continue;
			}
			$price = (float) get_post_meta( (int) $cid, '_fge_price_amount', true );
			if ( function_exists( 'fge_customer_price' ) ) {
				$price = (float) fge_customer_price( $price );
			}
			if ( $price > 0 && ( null === $best || $price < $best[0] ) ) {
				$best = [ $price, (int) $cid ];
			}
		}
		if ( $best ) {
			$out['min']    = $best[0];
			$out['min_id'] = $best[1];
		}
	}
	$out['min_url']   = (string) get_permalink( $out['min_id'] );
	$out['min_title'] = (string) get_the_title( $out['min_id'] );
	return $out;
}

/** „Ab 20 € pro Person" für Hero und Kacheln, leer wenn es gerade kein Angebot gibt. */
function fge_format_price_from_label( array $types ): string {
	$range = fge_format_price_range( $types );
	return $range ? 'Ab ' . fge_price_eur( $range['min'] ) . ' pro Person' : '';
}

/**
 * Preisübersicht je Format-Hub für Startseite und llms.txt (KI-Sichtbarkeit, 15.09.2026):
 * Zeilen [ label, from, to|null, url ] nur für Hubs mit aktuell buchbarem Pro-Person-Angebot.
 *
 * @return array<int,array{label:string,from:float,to:float,url:string,min_url:string}>
 */
function fge_price_overview_rows(): array {
	if ( ! function_exists( 'fge_get_event_format_pages' ) ) {
		return [];
	}
	$rows = [];
	foreach ( fge_get_event_format_pages() as $slug => $f ) {
		if ( empty( $f['types'] ) ) {
			continue;
		}
		$r = fge_format_price_range( (array) $f['types'] );
		if ( ! $r ) {
			continue;
		}
		$rows[] = [
			'label'   => (string) $f['name'],
			'from'    => (float) $r['min'],
			'to'      => (float) $r['max'],
			'url'     => home_url( '/firmenevent/' . $slug . '/' ),
			'min_url' => (string) $r['min_url'],
		];
	}
	return $rows;
}

/**
 * Aufzählung „Teamevents ab 20 €, Workshops ab 230 €" für Preis-FAQs.
 * Formate ohne aktuelles Angebot fallen still raus, statt eine Zahl zu erfinden.
 *
 * @param array<string,string[]> $formats Label => Event-Typ-Keys.
 */
function fge_price_from_summary( array $formats ): string {
	$parts = [];
	foreach ( $formats as $label => $types ) {
		$range = fge_format_price_range( $types );
		if ( $range ) {
			$parts[] = $label . ' ab ' . fge_price_eur( $range['min'] );
		}
	}
	if ( ! $parts ) {
		return '';
	}
	if ( count( $parts ) === 1 ) {
		return $parts[0];
	}
	$last = array_pop( $parts );
	return implode( ', ', $parts ) . ' und ' . $last;
}
