<?php
/**
 * Control Center: Finanzen nach „Control Center Redesign“ (Julius, 16.10.2026).
 *
 * Die Rechnung schreibt Julius in Lexoffice. Hier stehen Nummer, Datum,
 * Fälligkeit, Erinnerung und Zahlung. Neue Felder (Freigabe 16.10.2026):
 * _fge_invoice_date, _fge_invoice_due, _fge_invoice_reminded und
 * _fge_invoice_paid_at, alle als Y-m-d.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Zahlungsziel in Tagen, wenn keine Fälligkeit eingetragen ist. */
function fge_cc_invoice_terms(): int {
	return 14;
}

/** Betrag auf volle Euro für Kennzahlen. */
function fge_cc_eur0( float $v ): string {
	return number_format_i18n( round( $v ), 0 ) . ' €';
}

/** Faktor netto zu brutto. */
function fge_cc_vat_factor(): float {
	return 1 + ( function_exists( 'fge_xs_vat_percent' ) ? fge_xs_vat_percent() : 19.0 ) / 100;
}

/** Y-m-d als Zeitstempel, 0 wenn leer oder ungültig. */
function fge_cc_ymd_ts( string $ymd ): int {
	return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ymd ) ? fge_cc_local_ts( $ymd ) : 0;
}

/** Rechnungsdatum, ohne Eintrag der Tag nach dem Event. */
function fge_cc_invoice_date( int $req ): int {
	$ts = fge_cc_ymd_ts( (string) get_post_meta( $req, '_fge_invoice_date', true ) );
	if ( $ts > 0 ) {
		return $ts;
	}
	$ev = fge_cc_event_date( $req );
	return $ev > 0 ? $ev + DAY_IN_SECONDS : 0;
}

/** Fälligkeit, ohne Eintrag Rechnungsdatum plus Zahlungsziel. */
function fge_cc_invoice_due( int $req ): int {
	$ts = fge_cc_ymd_ts( (string) get_post_meta( $req, '_fge_invoice_due', true ) );
	if ( $ts > 0 ) {
		return $ts;
	}
	$d = fge_cc_invoice_date( $req );
	return $d > 0 ? $d + fge_cc_invoice_terms() * DAY_IN_SECONDS : 0;
}

/** Bezahlt am, ohne Eintrag der letzte Statuswechsel. */
function fge_cc_invoice_paid_at( int $req ): int {
	$ts = fge_cc_ymd_ts( (string) get_post_meta( $req, '_fge_invoice_paid_at', true ) );
	if ( $ts > 0 ) {
		return $ts;
	}
	return 'abgeschlossen' === (string) get_post_meta( $req, '_fge_request_status', true )
		? (int) strtotime( (string) get_post_meta( $req, '_fge_last_status_change', true ) )
		: 0;
}

/** Eine Rechnung als Zeile für Finanzen und Heute. */
function fge_cc_invoice_row( int $req ): array {
	$net  = fge_cc_offer_net( $req );
	$paid = 'abgeschlossen' === (string) get_post_meta( $req, '_fge_request_status', true );
	$due  = fge_cc_invoice_due( $req );
	$over = ! $paid && $due > 0 && $due < fge_cc_today();
	return [
		'req'      => $req,
		'no'       => (string) get_post_meta( $req, '_fge_lexoffice_invoice_number', true ),
		'ref'      => fge_request_number( $req ),
		'company'  => (string) get_post_meta( $req, '_fge_company_name', true ),
		'net'      => $net,
		'gross'    => round( $net * fge_cc_vat_factor(), 2 ),
		'date'     => fge_cc_invoice_date( $req ),
		'due'      => $due,
		'paid'     => $paid,
		'paid_at'  => $paid ? fge_cc_invoice_paid_at( $req ) : 0,
		'reminded' => fge_cc_ymd_ts( (string) get_post_meta( $req, '_fge_invoice_reminded', true ) ),
		'overdue'  => $over,
		'days'     => $over ? (int) round( ( fge_cc_today() - $due ) / DAY_IN_SECONDS ) : 0,
	];
}

/**
 * Alle gestellten Rechnungen, neueste zuerst. Gestellt heißt: Status
 * „Rechnung gestellt“ oder abgeschlossen mit Rechnungshaken.
 */
function fge_cc_invoices(): array {
	static $rows = null;
	if ( null !== $rows ) {
		return $rows;
	}
	$res  = fge_cc_query_requests( [
		'meta_query' => [ [ 'key' => '_fge_request_status', 'value' => [ 'rechnung_in_lexoffice_erstellt', 'abgeschlossen' ], 'compare' => 'IN' ] ], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	], 1500 );
	$rows = [];
	foreach ( $res['ids'] as $req ) {
		$req = (int) $req;
		if ( 'accepted' !== (string) get_post_meta( $req, '_fge_offer_status', true ) ) {
			continue;
		}
		$open = 'rechnung_in_lexoffice_erstellt' === (string) get_post_meta( $req, '_fge_request_status', true );
		if ( ! $open && '1' !== (string) get_post_meta( $req, '_fge_lexoffice_invoice_created', true ) && '' === (string) get_post_meta( $req, '_fge_lexoffice_invoice_number', true ) ) {
			continue; // abgeschlossen ohne Rechnung (vor 1.9.306 automatisch)
		}
		$rows[] = fge_cc_invoice_row( $req );
	}
	usort( $rows, static fn( $a, $b ) => $b['date'] <=> $a['date'] );
	return $rows;
}

/** Offene Rechnungen mit überschrittener Fälligkeit, älteste zuerst. */
function fge_cc_invoices_overdue(): array {
	$rows = array_values( array_filter( fge_cc_invoices(), static fn( $r ) => $r['overdue'] ) );
	usort( $rows, static fn( $a, $b ) => $a['due'] <=> $b['due'] );
	return $rows;
}

/**
 * Umsatz und Marge netto je Eventmonat eines Jahres, aus allen Buchungen.
 * Marge nur dort, wo ein Einkauf hinterlegt ist, sonst wäre sie geraten.
 *
 * @return array{months: array<int,array{net:float,margin:float}>, net:float, margin:float, margin_base:float, blind:int, count:int}
 */
function fge_cc_revenue_year( int $year ): array {
	static $cache = [];
	if ( isset( $cache[ $year ] ) ) {
		return $cache[ $year ];
	}
	$out = [ 'months' => array_fill( 1, 12, [ 'net' => 0.0, 'margin' => 0.0 ] ), 'net' => 0.0, 'margin' => 0.0, 'margin_base' => 0.0, 'blind' => 0, 'count' => 0 ];
	$res = fge_cc_query_requests( [ 'meta_query' => [ [ 'key' => '_fge_offer_status', 'value' => 'accepted' ] ] ], 1500 ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	foreach ( $res['ids'] as $req ) {
		$req = (int) $req;
		if ( 'verloren' === (string) get_post_meta( $req, '_fge_request_status', true ) ) {
			continue;
		}
		$d = fge_cc_event_date( $req );
		if ( $d <= 0 || (int) wp_date( 'Y', $d ) !== $year ) {
			continue;
		}
		$m    = (int) wp_date( 'n', $d );
		$net  = fge_cc_offer_net( $req );
		$cost = fge_cc_partner_cost_net( $req ) + fge_cc_extras_cost_net( $req );
		$out['months'][ $m ]['net'] += $net;
		$out['net']                 += $net;
		$out['count']++;
		if ( $cost > 0 ) {
			$out['months'][ $m ]['margin'] += $net - $cost;
			$out['margin']                 += $net - $cost;
			$out['margin_base']            += $net;
		} elseif ( $net > 0 ) {
			$out['blind']++;
		}
	}
	return $cache[ $year ] = $out;
}

// ── Datum, Fälligkeit und Zahlung an den Statuswechsel hängen ───────────────
// So stimmen die Felder auch, wenn der Status im WordPress oder über
// „Zurücknehmen“ gesetzt wird.

add_action( 'fge_request_status_changed', static function ( int $req, string $status, string $old ): void {
	if ( 'rechnung_in_lexoffice_erstellt' === $status ) {
		if ( '' === (string) get_post_meta( $req, '_fge_invoice_date', true ) ) {
			update_post_meta( $req, '_fge_invoice_date', wp_date( 'Y-m-d' ) );
		}
		if ( '' === (string) get_post_meta( $req, '_fge_invoice_due', true ) ) {
			update_post_meta( $req, '_fge_invoice_due', wp_date( 'Y-m-d', fge_cc_invoice_date( $req ) + fge_cc_invoice_terms() * DAY_IN_SECONDS ) );
		}
		delete_post_meta( $req, '_fge_invoice_paid_at' );
		return;
	}
	if ( 'abgeschlossen' === $status ) {
		if ( '' === (string) get_post_meta( $req, '_fge_invoice_paid_at', true ) && 'rechnung_in_lexoffice_erstellt' === $old ) {
			update_post_meta( $req, '_fge_invoice_paid_at', wp_date( 'Y-m-d' ) );
		}
		return;
	}
	if ( in_array( $old, [ 'rechnung_in_lexoffice_erstellt', 'abgeschlossen' ], true ) ) {
		// Rechnung zurückgenommen: Termine weg, die Nummer bleibt als Vorschlag.
		foreach ( [ 'date', 'due', 'reminded', 'paid_at' ] as $k ) {
			delete_post_meta( $req, '_fge_invoice_' . $k );
		}
	}
}, 10, 3 );

// ── Aktion: Rechnung gestellt, erinnert, bezahlt ────────────────────────────

add_action( 'admin_post_fge_cc_invoice', static function (): void {
	$req  = fge_cc_guard( 'fge_cc_invoice' );
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- fge_cc_guard prüft die Nonce.
	$step = sanitize_key( wp_unslash( $_POST['step'] ?? '' ) );
	$back = sanitize_key( wp_unslash( $_POST['back'] ?? '' ) );
	$tab  = sanitize_key( wp_unslash( $_POST['tab'] ?? '' ) );
	$go   = static function ( string $msg ) use ( $req, $back, $tab ): void {
		if ( 'finanzen' === $back ) {
			wp_safe_redirect( fge_cc_url( 'finanzen', array_filter( [ 'tab' => $tab, 'msg' => $msg ] ) ) );
			exit;
		}
		if ( 'heute' === $back ) {
			wp_safe_redirect( fge_cc_url( 'dashboard', [ 'msg' => $msg ] ) );
			exit;
		}
		fge_cc_redirect( $req, $msg );
	};
	if ( 'accepted' !== (string) get_post_meta( $req, '_fge_offer_status', true ) ) {
		$go( 'nothing' );
	}
	$status = (string) get_post_meta( $req, '_fge_request_status', true );

	if ( 'remind' === $step ) {
		if ( 'rechnung_in_lexoffice_erstellt' !== $status ) {
			$go( 'nothing' );
		}
		if ( ! fge_send_payment_reminder( $req ) ) {
			$go( 'inv_remind_fail' );
		}
		update_post_meta( $req, '_fge_invoice_reminded', wp_date( 'Y-m-d' ) );
		fge_activity_add( $req, 'mail', 'Zahlungserinnerung an den Kunden gesendet' );
		$go( 'inv_reminded' );
	}

	if ( 'paid' === $step ) {
		update_post_meta( $req, '_fge_lexoffice_invoice_created', 1 );
		if ( 'rechnung_in_lexoffice_erstellt' !== $status ) {
			fge_request_set_status( $req, 'rechnung_in_lexoffice_erstellt' ); // ohne Zwischenschritt gibt es kein Rechnungsdatum
		}
		fge_request_set_status( $req, 'abgeschlossen' );
		$go( 'inv_paid' );
	}

	// Rechnung gestellt: Nummer, Datum und Fälligkeit aus dem Formular.
	$no   = trim( sanitize_text_field( wp_unslash( $_POST['invoice_number'] ?? '' ) ) );
	$date = sanitize_text_field( wp_unslash( $_POST['invoice_date'] ?? '' ) );
	$due  = sanitize_text_field( wp_unslash( $_POST['invoice_due'] ?? '' ) );
	// phpcs:enable
	if ( '' !== $no ) {
		update_post_meta( $req, '_fge_lexoffice_invoice_number', mb_substr( $no, 0, 40 ) );
	}
	$dts = fge_cc_ymd_ts( $date ) ?: fge_cc_today();
	update_post_meta( $req, '_fge_invoice_date', wp_date( 'Y-m-d', $dts ) );
	$uts = fge_cc_ymd_ts( $due );
	update_post_meta( $req, '_fge_invoice_due', wp_date( 'Y-m-d', $uts >= $dts ? $uts : $dts + fge_cc_invoice_terms() * DAY_IN_SECONDS ) );
	update_post_meta( $req, '_fge_lexoffice_invoice_created', 1 );
	fge_request_set_status( $req, 'rechnung_in_lexoffice_erstellt' );
	$go( 'inv_set' );
} );

/** Formular „Rechnung gestellt“: Nummer aus Lexoffice, Datum, Fälligkeit. */
function fge_cc_invoice_form( int $req, string $back = '', string $tab = '' ): void {
	$today = wp_date( 'Y-m-d' );
	echo '<form class="cc-actform cc-invform" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="fge_cc_invoice"><input type="hidden" name="request_id" value="' . (int) $req . '"><input type="hidden" name="step" value="set">';
	echo '<input type="hidden" name="back" value="' . esc_attr( $back ) . '"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">';
	wp_nonce_field( 'fge_cc_invoice_' . $req );
	echo '<label class="cc-invform-f"><span>Rechnungsnr.</span><input type="text" name="invoice_number" value="' . esc_attr( (string) get_post_meta( $req, '_fge_lexoffice_invoice_number', true ) ) . '" placeholder="aus Lexoffice"></label>';
	echo '<label class="cc-invform-f"><span>Datum</span><input type="date" name="invoice_date" value="' . esc_attr( $today ) . '" data-cc-invdate></label>';
	echo '<label class="cc-invform-f"><span>Fällig am</span><input type="date" name="invoice_due" value="' . esc_attr( wp_date( 'Y-m-d', fge_cc_today() + fge_cc_invoice_terms() * DAY_IN_SECONDS ) ) . '" data-cc-invdue data-terms="' . (int) fge_cc_invoice_terms() . '"></label>';
	echo '<button type="submit" class="cc-btn cc-btn--primary">Rechnung gestellt</button></form>';
}

function fge_cc_paid_button( int $req, string $back = '', string $tab = '', string $label = 'Bezahlt, abschließen', bool $primary = true ): void {
	fge_cc_button( 'fge_cc_invoice', $req, $label, [
		'class'   => $primary ? 'cc-btn cc-btn--primary' : 'cc-btn',
		'fields'  => [ 'step' => 'paid', 'back' => $back, 'tab' => $tab ],
		'confirm' => 'Zahlung eingegangen und Vorgang abschließen?',
	] );
}

/** Knopf „Erinnern“: Zahlungserinnerung an den Kunden, Empfänger steht in der Rückfrage. */
function fge_cc_remind_button( int $req, string $back = '', string $tab = '', string $label = 'Erinnern', string $class = 'cc-btn' ): void {
	$to = function_exists( 'fge_offer_recipient' ) ? fge_offer_recipient( $req ) : '';
	if ( ! is_email( $to ) ) {
		echo '<span class="cc-muted" title="Keine Mailadresse beim Kunden">ohne Mail</span>';
		return;
	}
	fge_cc_button( 'fge_cc_invoice', $req, $label, [
		'class'   => $class,
		'fields'  => [ 'step' => 'remind', 'back' => $back, 'tab' => $tab ],
		'confirm' => 'Zahlungserinnerung an ' . $to . ' senden?',
	] );
}

// ── Seite ────────────────────────────────────────────────────────────────────

function fge_cc_page_money(): void {
	fge_cc_message();
	$year  = (int) wp_date( 'Y' );
	$rev   = fge_cc_revenue_year( $year );
	$all   = fge_cc_invoices();
	$open  = array_filter( $all, static fn( $r ) => ! $r['paid'] );
	$over  = array_filter( $all, static fn( $r ) => $r['overdue'] );
	$sumg  = static fn( array $rows ): float => array_sum( array_column( $rows, 'gross' ) );

	echo '<p class="cc-top-sub cc-sub-under">Rechnungen schreibst du in Lexoffice, Zahlungen gleichst du hier ab.</p>';

	$kpis = [
		[ 'Umsatz netto ' . $year, fge_cc_eur0( $rev['net'] ), 'aus ' . $rev['count'] . ( 1 === $rev['count'] ? ' Buchung' : ' Buchungen' ), '' ],
		[
			'Marge ' . $year,
			$rev['margin_base'] > 0 ? fge_cc_eur0( $rev['margin'] ) : 'unbekannt',
			$rev['margin_base'] > 0 ? round( $rev['margin'] / $rev['margin_base'] * 100 ) . ' % vom Umsatz' . ( $rev['blind'] ? ', ' . $rev['blind'] . ' ohne Einkauf' : '' ) : 'kein Einkauf hinterlegt',
			'good',
		],
		[ 'Offene Forderungen', fge_cc_eur0( $sumg( $open ) ), count( $open ) . ( 1 === count( $open ) ? ' Rechnung brutto' : ' Rechnungen brutto' ), '' ],
		[ 'Davon überfällig', fge_cc_eur0( $sumg( $over ) ), count( $over ) . ( 1 === count( $over ) ? ' Rechnung' : ' Rechnungen' ), $over ? 'bad' : '' ],
	];
	echo '<div class="cc-fkpis">';
	foreach ( $kpis as [ $label, $value, $sub, $tone ] ) {
		echo '<div class="cc-fkpi"><span class="cc-fkpi-label">' . esc_html( $label ) . '</span><span class="cc-fkpi-value' . ( '' !== $tone ? ' is-' . esc_attr( $tone ) : '' ) . '">' . esc_html( $value ) . '</span><span class="cc-fkpi-sub">' . esc_html( $sub ) . '</span></div>';
	}
	echo '</div>';

	// Umsatz nach Eventmonat, Marge als dunkler Anteil.
	$mon = [ 'Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez' ];
	$max = max( array_map( static fn( $m ) => $m['net'], $rev['months'] ) );
	echo '<section class="cc-card cc-chart"><div class="cc-chart-head"><h2>Umsatz netto ' . (int) $year . ' nach Eventmonat</h2><span class="cc-muted">Marge dunkel</span></div>';
	echo '<div class="cc-bars" role="img" aria-label="' . esc_attr( 'Umsatz netto ' . $year . ' je Monat' ) . '">';
	foreach ( $rev['months'] as $i => $m ) {
		$h  = $max > 0 ? max( $m['net'] / $max * 100, $m['net'] > 0 ? 4 : 1 ) : 1;
		$mh = $m['net'] > 0 ? max( 0, min( 100, $m['margin'] / $m['net'] * 100 ) ) : 0;
		$t  = $mon[ $i - 1 ] . ': ' . fge_cc_eur( $m['net'] ) . ' Umsatz, ' . fge_cc_eur( $m['margin'] ) . ' Marge';
		echo '<div class="cc-bar" title="' . esc_attr( $t ) . '"><div class="cc-bar-net" style="height:' . esc_attr( round( $h, 1 ) ) . '%"><div class="cc-bar-margin" style="height:' . esc_attr( round( $mh, 1 ) ) . '%"></div></div></div>';
	}
	echo '</div><div class="cc-bars-x">';
	foreach ( $mon as $m ) {
		echo '<span>' . esc_html( $m ) . '</span>';
	}
	echo '</div></section>';

	$tabs = [ 'offen' => 'Offen', 'ueber' => 'Überfällig', 'bezahlt' => 'Bezahlt', 'alle' => 'Alle' ];
	$in   = [
		'offen'   => static fn( $r ) => ! $r['paid'] && ! $r['overdue'],
		'ueber'   => static fn( $r ) => $r['overdue'],
		'bezahlt' => static fn( $r ) => $r['paid'],
		'alle'    => static fn( $r ) => true,
	];
	$tab = sanitize_key( wp_unslash( $_GET['tab'] ?? 'offen' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$tab = isset( $tabs[ $tab ] ) ? $tab : 'offen';
	$counts = array_map( static fn( $f ) => count( array_filter( $all, $f ) ), $in );
	fge_cc_seglinks( 'finanzen', 'tab', $tabs, $tab, $counts, [] );

	$rows = array_values( array_filter( $all, $in[ $tab ] ) );
	echo '<section class="cc-tablewrap"><table class="cc-table cc-invtable"><thead><tr><th>Rechnung</th><th>Kunde</th><th>Anfrage</th><th class="cc-num">Brutto</th><th>Fällig</th><th>Status</th><th></th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		$pill = $r['paid'] ? [ 'Bezahlt', 'good' ] : ( $r['overdue'] ? [ 'Überfällig', 'bad' ] : ( $r['reminded'] ? [ 'Erinnert', 'warn' ] : [ 'Offen', 'neutral' ] ) );
		echo '<tr><td class="cc-mono">' . esc_html( '' !== $r['no'] ? $r['no'] : 'ohne Nr.' ) . '</td>';
		echo '<td><strong class="cc-td-strong">' . esc_html( $r['company'] ) . '</strong></td>';
		echo '<td class="cc-mono"><a href="' . esc_url( fge_cc_request_url( $r['req'] ) ) . '">' . esc_html( $r['ref'] ) . '</a></td>';
		echo '<td class="cc-num">' . esc_html( fge_cc_eur( $r['gross'] ) ) . '</td>';
		echo '<td class="cc-tnum' . ( $r['overdue'] ? ' cc-td-bad' : '' ) . '">' . esc_html( $r['due'] > 0 ? wp_date( 'd.m.', $r['due'] ) : '' ) . '</td>';
		echo '<td>' . fge_cc_pill( $pill[0], $pill[1] ) . '</td><td class="cc-td-act">'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( $r['paid'] ) {
			echo '<span class="cc-muted">' . esc_html( $r['paid_at'] > 0 ? 'bezahlt am ' . wp_date( 'd.m.', $r['paid_at'] ) : 'bezahlt' ) . '</span>';
		} else {
			fge_cc_remind_button( $r['req'], 'finanzen', $tab );
			fge_cc_paid_button( $r['req'], 'finanzen', $tab, 'Bezahlt', false );
		}
		echo '</td></tr>';
	}
	if ( ! $rows ) {
		echo '<tr class="is-empty"><td colspan="7">Keine Rechnungen in dieser Ansicht.</td></tr>';
	}
	echo '</tbody></table></section>';
}
