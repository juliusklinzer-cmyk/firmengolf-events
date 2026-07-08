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

	$markup = round( $net * ( FGE_MARKUP_PERCENT / 100 ), 2 );
	$gross  = round( $net + $markup, 2 );

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
	return fge_event_pricing_calc( $mode, $amount, $basis, $items, $pax );
}

/** Formatierter Brutto-Preis fürs Unternehmen, z. B. „ab €320 p.P." oder „€2.400 gesamt". */
function fge_event_price_label( int $event_id ): string {
	$p = fge_event_pricing( $event_id );
	if ( $p['gross'] <= 0 ) {
		return 'Auf Anfrage';
	}
	$amount = number_format_i18n( $p['gross'], 0 ) . ' €'; // Suffix-Format (Kern-Audit H6)
	return $p['unit'] === 'pro Person' ? $amount . ' p.P.' : $amount . ' gesamt';
}
