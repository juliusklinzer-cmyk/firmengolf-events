<?php
/**
 * Control Center: Angebote, Aufgaben, Geld und Postausgang.
 *
 * Alles reine Sichten auf vorhandene Daten. Keine dieser Seiten hält eigene
 * Wahrheit, sie sortieren nur, was ohnehin da ist.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Nettobetrag eines Angebots, inklusive der vom Kunden gewählten Positionen. */
function fge_cc_offer_net( int $req ): float {
	$snap = (array) get_post_meta( $req, '_fge_offer_snapshot', true );
	if ( ! $snap ) {
		return 0.0;
	}
	$sel = function_exists( 'fge_offer_selected_extras' ) ? fge_offer_selected_extras( $req ) : [];
	$tot = function_exists( 'fge_offer_totals' ) ? fge_offer_totals( $snap, $sel ) : [ 'net' => 0 ];
	return (float) ( $tot['net'] ?? 0 );
}

/**
 * Einkauf beim Platz als Nettobetrag.
 *
 * Mit dem Platz wird brutto verhandelt, das Angebot läuft netto. Ohne diese
 * Umrechnung wäre jede Margenzahl falsch.
 */
function fge_cc_partner_cost_net( int $req ): float {
	$cost = (float) get_post_meta( $req, '_fge_partner_cost', true );
	if ( $cost <= 0 ) {
		return 0.0;
	}
	$snap  = (array) get_post_meta( $req, '_fge_offer_snapshot', true );
	$pax   = (int) ( $snap['participants'] ?? 0 );
	$total = 'pauschal' === (string) get_post_meta( $req, '_fge_partner_cost_basis', true )
		? $cost
		: $cost * max( 1, $pax );

	$gross = '0' !== (string) get_post_meta( $req, '_fge_partner_cost_gross', true );
	return $gross ? $total / 1.19 : $total;
}

/** Einkauf der gebuchten Zusatzleistungen, netto. */
function fge_cc_extras_cost_net( int $req ): float {
	if ( ! function_exists( 'fge_xs_priced' ) ) {
		return 0.0;
	}
	$sel  = function_exists( 'fge_offer_selected_extras' ) ? fge_offer_selected_extras( $req ) : [];
	$snap = (array) get_post_meta( $req, '_fge_offer_snapshot', true );
	$pax  = (int) ( $snap['participants'] ?? 0 );
	$sum  = 0.0;
	foreach ( fge_xs_priced( $req ) as $src => $item ) {
		if ( ! in_array( (int) $src, $sel, true ) ) {
			continue;
		}
		// Verbrauchs-Positionen haben noch keinen Betrag, brutto eingegebene Einkäufe zählen netto.
		$cnet = function_exists( 'fge_xs_cost_net' ) ? fge_xs_cost_net( $item ) : (float) $item['cost'];
		$sum += 'person' === $item['basis'] ? $cnet * max( 1, $pax ) : ( 'verbrauch' === $item['basis'] ? 0.0 : $cnet );
	}
	return $sum;
}

// ── Angebote ─────────────────────────────────────────────────────────────────

function fge_cc_page_offers(): void {
	$filter = sanitize_key( wp_unslash( $_GET['filter'] ?? 'offen' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	// Nur Vorgänge mit versendetem Angebot, das hält die Liste auch bei vielen
	// Anfragen klein, weil unbepreiste und abgebrochene gar nicht auftauchen.
	$result = fge_cc_query_requests( [
		'meta_query' => [ [ 'key' => '_fge_offer_sent', 'value' => '1' ] ],
	], 400 );
	$ids = $result['ids'];

	$rows  = [];
	$counts = [ 'offen' => 0, 'angenommen' => 0, 'abgelehnt' => 0, 'ueberfaellig' => 0 ];

	foreach ( $ids as $req ) {
		$status   = (string) get_post_meta( $req, '_fge_offer_status', true );
		$deadline = (int) get_post_meta( $req, '_fge_offer_deadline', true );
		$overdue  = 'pending' === $status && $deadline > 0 && time() > $deadline;

		$bucket = 'pending' === $status ? ( $overdue ? 'ueberfaellig' : 'offen' )
			: ( 'accepted' === $status ? 'angenommen' : 'abgelehnt' );
		$counts[ $bucket ]++;

		if ( $filter !== $bucket ) {
			continue;
		}
		$rows[] = [
			'req'      => $req,
			'ref'      => fge_request_number( $req ),
			'company'  => (string) get_post_meta( $req, '_fge_company_name', true ),
			'net'      => fge_cc_offer_net( $req ),
			'deadline' => $deadline,
			'date'     => fge_cc_event_date( $req ),
			'overdue'  => $overdue,
			'query'    => (string) get_post_meta( $req, '_fge_offer_query', true ),
		];
	}

	fge_cc_truncation_note( $result, 'Angebote' );

	$labels = [ 'offen' => 'Läuft', 'ueberfaellig' => 'Überfällig', 'angenommen' => 'Angenommen', 'abgelehnt' => 'Abgelehnt' ];
	echo '<div class="cc-filters">';
	foreach ( $labels as $k => $label ) {
		echo '<a class="cc-chip' . ( $filter === $k ? ' is-on' : '' ) . '" href="' . esc_url( fge_cc_url( 'angebote', [ 'filter' => $k ] ) ) . '">'
			. esc_html( $label ) . ' ' . (int) $counts[ $k ] . '</a>';
	}
	echo '</div>';

	if ( ! $rows ) {
		fge_cc_empty( 'Hier ist gerade nichts.' );
		return;
	}

	$sum = array_sum( array_column( $rows, 'net' ) );
	echo '<p class="cc-muted">' . (int) count( $rows ) . ' Angebote, zusammen '
		. esc_html( number_format_i18n( $sum, 2 ) ) . ' € netto</p>';

	echo '<div class="cc-tablewrap"><table class="cc-table">';
	echo '<thead><tr><th>Vorgang</th><th>Firma</th><th class="cc-num">Netto</th><th>Frist</th><th>Termin</th><th>Hinweis</th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		echo '<tr onclick="location.href=\'' . esc_js( fge_cc_request_url( (int) $r['req'] ) ) . '\'">';
		echo '<td><a href="' . esc_url( fge_cc_request_url( (int) $r['req'] ) ) . '">' . esc_html( $r['ref'] ) . '</a></td>';
		echo '<td>' . esc_html( $r['company'] ?: '—' ) . '</td>';
		echo '<td class="cc-num">' . esc_html( $r['net'] > 0 ? number_format_i18n( $r['net'], 2 ) . ' €' : '—' ) . '</td>';
		echo '<td' . ( $r['overdue'] ? ' class="cc-td-bad"' : '' ) . '>' . esc_html( $r['deadline'] > 0 ? wp_date( 'd.m.Y', $r['deadline'] ) : '—' ) . '</td>';
		echo '<td>' . esc_html( $r['date'] > 0 ? wp_date( 'd.m.Y', $r['date'] ) : '—' ) . '</td>';
		echo '<td class="cc-muted">' . esc_html( '' !== $r['query'] ? 'Rückfrage offen' : '' ) . '</td>';
		echo '</tr>';
	}
	echo '</tbody></table></div>';
}

// ── Aufgaben ─────────────────────────────────────────────────────────────────

function fge_cc_page_tasks(): void {
	$groups = [ 'now' => 'Jetzt', 'soon' => 'Bald', 'wait' => 'Wartet auf andere' ];
	$buckets = [ 'now' => [], 'soon' => [], 'wait' => [] ];

	foreach ( fge_cc_worklist() as $row ) {
		if ( $row['snoozed'] ) {
			continue;
		}
		foreach ( $row['tasks'] as $t ) {
			$key = 'them' === $t['who'] ? 'wait' : $t['urgency'];
			if ( 'wait' !== $key && $row['cold'] ) {
				$key = 'soon'; // Verstaubtes drängt nicht.
			}
			$buckets[ $key ][] = $t + [ 'row' => $row ];
		}
	}

	foreach ( $groups as $key => $label ) {
		echo '<section class="cc-card"><h2>' . esc_html( $label ) . ' <span class="cc-muted">' . (int) count( $buckets[ $key ] ) . '</span></h2>';
		if ( ! $buckets[ $key ] ) {
			fge_cc_empty( 'Nichts hier.' );
			echo '</section>';
			continue;
		}
		echo '<ul class="cc-tasklist">';
		foreach ( $buckets[ $key ] as $t ) {
			$row = $t['row'];
			echo '<li><a href="' . esc_url( fge_cc_request_url( (int) $row['req'] ) ) . '">';
			echo '<span class="cc-ref">' . esc_html( $row['ref'] ) . '</span>';
			echo '<span class="cc-task-text">' . esc_html( $t['text'] ) . '</span>';
			echo '<span class="cc-muted">' . esc_html( $row['company'] ?: '' ) . '</span>';
			if ( $row['cold'] ) {
				echo '<span class="cc-muted">verstaubt</span>';
			}
			echo '</a></li>';
		}
		echo '</ul></section>';
	}
}

// ── Geld ─────────────────────────────────────────────────────────────────────

function fge_cc_page_money(): void {
	// Für die Geldsicht zählen nur Vorgänge mit versendetem Angebot. Alles
	// davor hat weder Umsatz noch Einkauf.
	$result = fge_cc_query_requests( [
		'meta_query' => [ [ 'key' => '_fge_offer_sent', 'value' => '1' ] ],
	], 400 );
	$ids = $result['ids'];

	$month_start = fge_cc_local_ts( wp_date( 'Y-m-01' ) );
	$open_offers  = 0.0;
	$month_net    = 0.0;
	$margin_sum   = 0.0;
	$margin_base  = 0.0;
	$margin_blind = 0;
	$bookings     = 0;
	$incoming    = [];
	$commissions = [];

	foreach ( $ids as $req ) {
		$offer = (string) get_post_meta( $req, '_fge_offer_status', true );
		$net   = fge_cc_offer_net( $req );

		if ( 'pending' === $offer ) {
			$open_offers += $net;
		}
		if ( 'accepted' !== $offer ) {
			continue;
		}

		$status = (string) get_post_meta( $req, '_fge_request_status', true );
		$cost   = fge_cc_partner_cost_net( $req ) + fge_cc_extras_cost_net( $req );

		$accepted_at = (int) strtotime( (string) get_post_meta( $req, '_fge_offer_accepted_at', true ) );
		if ( $accepted_at >= $month_start ) {
			$month_net += $net;
			$bookings++;
			// Nur Buchungen mit hinterlegtem Einkauf zählen in die Marge. Ohne
			// diese Trennung sähe jede Buchung ohne Einkaufspreis wie reiner
			// Gewinn aus, und die Quote wäre frei erfunden.
			if ( $cost > 0 ) {
				$margin_sum  += $net - $cost;
				$margin_base += $net;
			} else {
				$margin_blind++;
			}
		}

		// Offene Eingangsrechnung: gebucht, aber noch nicht abgeschlossen.
		if ( 'abgeschlossen' !== $status && $cost > 0 ) {
			$incoming[] = [
				'req'     => $req,
				'ref'     => fge_request_number( $req ),
				'company' => (string) get_post_meta( $req, '_fge_company_name', true ),
				'cost'    => $cost,
				'date'    => fge_cc_event_date( $req ),
			];
		}

		if ( function_exists( 'fge_pc_request_summary' ) ) {
			$pc = fge_pc_request_summary( $req );
			if ( $pc['code_id'] > 0 && (float) $pc['commission_amount'] > 0 && 'offen' === ( $pc['commission_status'] ?: 'offen' ) ) {
				$commissions[] = [
					'req'    => $req,
					'ref'    => fge_request_number( $req ),
					'holder' => (string) $pc['holder'],
					'amount' => (float) $pc['commission_amount'],
				];
			}
		}
	}

	$tiles = [
		[ 'Offene Angebote', number_format_i18n( $open_offers, 0 ) . ' €', 'netto, noch nicht entschieden' ],
		[ 'Gebucht im ' . wp_date( 'F' ), number_format_i18n( $month_net, 0 ) . ' €', $bookings . ' ' . ( 1 === $bookings ? 'Buchung' : 'Buchungen' ) ],
		[
			'Marge im Monat',
			$margin_base > 0 ? number_format_i18n( $margin_sum, 0 ) . ' €' : 'unbekannt',
			$margin_base > 0
				? round( $margin_sum / $margin_base * 100 ) . ' Prozent'
					. ( $margin_blind > 0 ? ', ' . $margin_blind . ' ohne Einkauf' : '' )
				: ( $bookings > 0 ? 'kein Einkaufspreis hinterlegt' : 'noch nichts gebucht' ),
		],
		[ 'Offene Eingangsrechnungen', number_format_i18n( array_sum( array_column( $incoming, 'cost' ) ), 0 ) . ' €', count( $incoming ) . ' Vorgänge' ],
		[ 'Offene Provisionen', number_format_i18n( array_sum( array_column( $commissions, 'amount' ) ), 0 ) . ' €', count( $commissions ) . ' Codes' ],
	];

	fge_cc_truncation_note( $result, 'Vorgänge' );

	echo '<div class="cc-tiles">';
	foreach ( $tiles as [ $label, $value, $hint ] ) {
		echo '<div class="cc-tile"><span class="cc-tile-label">' . esc_html( $label ) . '</span>';
		echo '<span class="cc-tile-value">' . esc_html( $value ) . '</span>';
		echo '<span class="cc-muted">' . esc_html( $hint ) . '</span></div>';
	}
	echo '</div>';

	echo '<p class="cc-muted">Marge heißt hier: Nettoumsatz minus Einkauf beim Platz und bei den Dienstleistern. Mit dem Platz wird brutto verhandelt, deshalb wird der Einkauf für diese Rechnung auf netto umgestellt.</p>';

	fge_cc_money_table( 'Erwartete Eingangsrechnungen', $incoming, 'cost', 'company' );
	fge_cc_money_table( 'Offene Provisionen', $commissions, 'amount', 'holder' );
}

function fge_cc_money_table( string $title, array $rows, string $amount_key, string $label_key ): void {
	echo '<section class="cc-card"><h2>' . esc_html( $title ) . '</h2>';
	if ( ! $rows ) {
		fge_cc_empty( 'Nichts offen.' );
		echo '</section>';
		return;
	}
	usort( $rows, static fn( $a, $b ) => $b[ $amount_key ] <=> $a[ $amount_key ] );
	echo '<table class="cc-table cc-table--plain"><tbody>';
	foreach ( $rows as $r ) {
		echo '<tr><td><a href="' . esc_url( fge_cc_request_url( (int) $r['req'] ) ) . '">' . esc_html( $r['ref'] ) . '</a></td>';
		echo '<td>' . esc_html( (string) ( $r[ $label_key ] ?? '' ) ) . '</td>';
		if ( isset( $r['date'] ) ) {
			echo '<td class="cc-muted">' . esc_html( $r['date'] > 0 ? wp_date( 'd.m.Y', (int) $r['date'] ) : '' ) . '</td>';
		}
		echo '<td class="cc-num">' . esc_html( number_format_i18n( (float) $r[ $amount_key ], 2 ) ) . ' €</td></tr>';
	}
	echo '</tbody></table></section>';
}

// ── Postausgang ──────────────────────────────────────────────────────────────

function fge_cc_page_outbox(): void {
	$filter = sanitize_key( wp_unslash( $_GET['filter'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$rows   = function_exists( 'fge_mail_log_recent' )
		? fge_mail_log_recent( 200, 'failed' === $filter ? 'failed' : '' )
		: [];

	$failed = function_exists( 'fge_mail_log_recent' ) ? count( fge_mail_log_recent( 200, 'failed' ) ) : 0;

	echo '<div class="cc-filters">';
	echo '<a class="cc-chip' . ( '' === $filter ? ' is-on' : '' ) . '" href="' . esc_url( fge_cc_url( 'postausgang' ) ) . '">Alle</a>';
	echo '<a class="cc-chip' . ( 'failed' === $filter ? ' is-on' : '' ) . '" href="' . esc_url( fge_cc_url( 'postausgang', [ 'filter' => 'failed' ] ) ) . '">Nicht zugestellt ' . (int) $failed . '</a>';
	echo '</div>';

	if ( ! $rows ) {
		fge_cc_empty( 'Noch nichts protokolliert. Das Protokoll beginnt mit der ersten Mail nach dem Einspielen.' );
		return;
	}

	echo '<div class="cc-tablewrap"><table class="cc-table">';
	echo '<thead><tr><th>Wann</th><th>Mail</th><th>An</th><th>Vorgang</th><th>Status</th></tr></thead><tbody>';
	foreach ( $rows as $m ) {
		$req  = (int) $m['request_id'];
		$bad  = 'sent' !== $m['status'];
		$name = function_exists( 'fge_mail_label' ) && '' !== (string) $m['mail_key']
			? fge_mail_label( (string) $m['mail_key'] )
			: (string) $m['subject'];

		echo '<tr>';
		echo '<td class="cc-muted">' . esc_html( wp_date( 'd.m. H:i', (int) strtotime( (string) $m['sent_at'] ) ) ) . '</td>';
		echo '<td>' . esc_html( $name ) . '<br><span class="cc-muted">' . esc_html( mb_substr( (string) $m['subject'], 0, 60 ) ) . '</span></td>';
		echo '<td>' . esc_html( (string) $m['recipient'] ) . '</td>';
		echo '<td>' . ( $req > 0 ? '<a href="' . esc_url( fge_cc_request_url( $req ) ) . '">' . esc_html( fge_request_number( $req ) ) . '</a>' : '<span class="cc-muted">—</span>' ) . '</td>';
		echo '<td>' . ( $bad
			? fge_cc_pill( 'nicht zugestellt', 'bad' ) . '<br><span class="cc-muted">' . esc_html( mb_substr( (string) $m['error'], 0, 70 ) ) . '</span>'
			: fge_cc_pill( 'zugestellt', 'good' ) ) . '</td>';
		echo '</tr>';
	}
	echo '</tbody></table></div>';
}
