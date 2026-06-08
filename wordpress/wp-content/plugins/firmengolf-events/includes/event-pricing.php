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
 * Reine Preisberechnung (ohne DB).
 *
 * @param string                              $mode       'gesamt' | 'einzel'
 * @param float                               $amount     Gesamtbetrag (bei 'gesamt')
 * @param string                              $basis      'person' | 'pauschal' (nur bei 'gesamt')
 * @param array<int,array{label:string,cost:mixed}> $line_items Einzelposten (bei 'einzel')
 * @return array{net:float,markup:float,gross:float,unit:string,basis:string,mode:string}
 */
function fge_event_pricing_calc( string $mode, float $amount, string $basis, array $line_items ): array {
	$mode  = ( $mode === 'einzel' ) ? 'einzel' : 'gesamt';
	$basis = ( $basis === 'pauschal' ) ? 'pauschal' : 'person';

	$net = ( $mode === 'einzel' )
		? array_sum( array_map( static fn( $i ): float => (float) ( $i['cost'] ?? 0 ), $line_items ) )
		: max( 0.0, $amount );

	$markup = round( $net * ( FGE_MARKUP_PERCENT / 100 ), 2 );
	$gross  = round( $net + $markup, 2 );
	// Einzelposten gelten als Gesamt-/Pauschalpreis; "pro Person" nur bei Gesamtpreis + Basis person.
	$unit   = ( $mode === 'gesamt' && $basis === 'person' ) ? 'pro Person' : 'gesamt';

	return [
		'net'    => round( $net, 2 ),
		'markup' => $markup,
		'gross'  => $gross,
		'unit'   => $unit,
		'basis'  => $basis,
		'mode'   => $mode,
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
	return fge_event_pricing_calc( $mode, $amount, $basis, $items );
}

/** Formatierter Brutto-Preis fürs Unternehmen, z. B. „ab €320 p.P." oder „€2.400 gesamt". */
function fge_event_price_label( int $event_id ): string {
	$p = fge_event_pricing( $event_id );
	if ( $p['gross'] <= 0 ) {
		return 'Auf Anfrage';
	}
	$amount = '€' . number_format_i18n( $p['gross'], 0 );
	return $p['unit'] === 'pro Person' ? $amount . ' p.P.' : $amount . ' gesamt';
}
