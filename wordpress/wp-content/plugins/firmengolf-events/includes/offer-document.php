<?php
/**
 * Angebotsdokument: EINE Vorlage für Angebotsseite, PDF und Angebotsmail.
 *
 * Aufbau wie ein klassisches Angebot (Julius, 17.09.2026): Kopf mit Empfänger und
 * Angebotsdaten, Positionstabelle (Position 1 = Event mit Termin, Ort, Ablauf und
 * Leistungen zusammen; jede Zusatzleistung eine eigene Position), Summenblock
 * netto / USt. / Gesamtbetrag mit exakten Beträgen (kein „ca."), Konditionen.
 * Steuer steht nur einmal, im Summenblock. Begriff durchgehend „USt.".
 *
 * PDF über Dompdf (lib/dompdf, LGPL), Route /angebot/<token>/pdf, zusätzlich als
 * Anhang der Angebotsmail. Fehlt die Bibliothek, fallen PDF-Button und Anhang
 * still weg, Seite und Mail funktionieren weiter.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Betrag mit zwei Nachkommastellen, z. B. „312,00 €". */
function fge_money( float $v ): string {
	return number_format_i18n( round( $v, 2 ), 2 ) . ' €';
}

/**
 * Positionen und Summen aus dem Angebots-Snapshot.
 *
 * @param array      $snap     Snapshot (_fge_offer_snapshot).
 * @param array|null $selected src-Indizes der gewählten Zusatzleistungen, null = alle.
 * @return array{rows:array,net:float,vat:float,vat_percent:int,total:float,pp:bool,pax:int}
 */
function fge_offer_positions( array $snap, ?array $selected = null ): array {
	$pax   = (int) ( $snap['participants'] ?? 0 );
	$is_pp = 'pro Person' === (string) ( $snap['price_unit'] ?? '' );
	$gross = (float) ( $snap['price_gross'] ?? 0 );
	$rows  = [];
	$net   = 0.0;
	$pp    = false;

	$qty    = ( $is_pp && $gross > 0 ) ? max( 1, $pax ) : 1;
	$total  = round( $gross * $qty, 2 );
	$rows[] = [
		'kind'       => 'event',
		'src'        => null,
		'title'      => (string) ( $snap['event_title'] ?? 'Firmen-Event' ),
		'qty'        => $qty,
		'unit_label' => ( $is_pp && $gross > 0 ) ? 'Pers.' : 'pauschal',
		'unit_price' => $gross,
		'total'      => $total,
		'selected'   => true,
	];
	if ( $gross > 0 ) {
		$net += $total;
		$pp   = $pp || $is_pp;
	}

	foreach ( (array) ( $snap['extras'] ?? [] ) as $x ) {
		$src   = (int) ( $x['src'] ?? -1 );
		$x_pp  = 'person' === (string) ( $x['basis'] ?? '' );
		$price = (float) ( $x['price'] ?? 0 );
		$q     = $x_pp ? max( 1, $pax ) : 1;
		$t     = round( $price * $q, 2 );
		$on    = null === $selected || in_array( $src, $selected, true );
		$rows[] = [
			'kind'       => 'extra',
			'src'        => $src,
			'title'      => (string) ( $x['label'] ?? '' ),
			'qty'        => $q,
			'unit_label' => $x_pp ? 'Pers.' : 'pauschal',
			'unit_price' => $price,
			'total'      => $t,
			'selected'   => $on,
		];
		if ( $on ) {
			$net += $t;
			$pp   = $pp || $x_pp;
		}
	}

	$vatp  = (int) ( $snap['vat_percent'] ?? ( defined( 'FGE_VAT_PERCENT' ) ? FGE_VAT_PERCENT : 19 ) );
	$net   = round( $net, 2 );
	$vat   = round( $net * $vatp / 100, 2 );
	return [
		'rows'        => $rows,
		'net'         => $net,
		'vat'         => $vat,
		'vat_percent' => $vatp,
		'total'       => round( $net + $vat, 2 ),
		'pp'          => $pp,
		'pax'         => $pax,
	];
}

/**
 * Hinweis zur Teilnehmerzahl bei Pro-Kopf-Preisen. Die gebuchte Zahl ist verbindlich,
 * der Platz rechnet mit ihr ab (Julius, 17.09.2026). Änderungsfrist passt zu § 4 und
 * § 7 der AGB (unter 7 Tagen bzw. Nichterscheinen = 90 % Storno).
 */
function fge_offer_pax_note( int $pax ): string {
	return 'Berechnungsbasis ' . $pax . ' Teilnehmer. Die gebuchte Teilnehmerzahl ist verbindlich. Änderungen sind bis 7 Tage vor dem Termin möglich, danach wird die gebuchte Zahl berechnet.';
}

/** Gemeinsames CSS für Seite und PDF (Klassen .od-*). Mail bekommt Inline-Styles. */
function fge_offer_document_css(): string {
	return '
.od { font-family: "DejaVu Sans", Arial, Helvetica, sans-serif; color: #1a1a1a; font-size: 13px; line-height: 1.5; }
.od table { border-collapse: collapse; }
.od strong { font-weight: 700; }
.od-head { width: 100%; background: #20294D; color: #ffffff; }
.od-head td { padding: 16px 26px; vertical-align: middle; }
.od-brand { font-size: 18px; font-weight: bold; letter-spacing: 0.03em; }
.od-doctype { text-align: right; font-size: 12px; letter-spacing: 0.16em; text-transform: uppercase; color: #C9D2EA; }
.od-body { padding: 26px 26px 22px; }
.od-status { background: #EAF7F0; color: #2F6E45; padding: 9px 12px; margin: 0 0 18px; font-weight: bold; font-size: 13px; }
.od-status.no { background: #FBEFEC; color: #B4332B; }
.od-meta { width: 100%; margin: 0 0 22px; }
.od-meta td { vertical-align: top; padding: 0; }
.od-addr { width: 52%; padding-right: 20px !important; }
.od-lbl { font-size: 10px; letter-spacing: 0.08em; text-transform: uppercase; color: #6C736E; margin: 0 0 4px; }
.od-facts table { width: 100%; }
.od-facts th { text-align: left; font-weight: normal; color: #6C736E; padding: 2px 12px 2px 0; white-space: nowrap; vertical-align: top; }
.od-facts td { padding: 2px 0; vertical-align: top; }
.od-title { font-size: 18px; font-weight: bold; margin: 0 0 6px; line-height: 1.3; }
.od p.od-intro { margin: 0 0 18px; }
.od-pos { width: 100%; }
.od-pos th { text-align: left; font-size: 10.5px; letter-spacing: 0.06em; text-transform: uppercase; color: #6C736E; border-bottom: 2px solid #20294D; padding: 6px 8px; }
.od-pos td { padding: 10px 8px; border-bottom: 1px solid #e4e4e0; vertical-align: top; }
.od-pos .r { text-align: right; white-space: nowrap; }
.od-pos .c-pos { width: 5%; color: #6C736E; }
.od-pos .c-qty { width: 10%; }
.od-pos .c-unit { width: 15%; }
.od-pos .c-sum { width: 15%; }
.od-pos tfoot td { border-bottom: 0; padding: 5px 8px; }
.od-pos tfoot tr.od-total td { font-weight: bold; font-size: 14px; border-top: 2px solid #20294D; padding-top: 9px; }
.od-pos tr.od-off td { color: #9a9a94; }
.od-desc { margin: 6px 0 0; color: #444; font-size: 12px; line-height: 1.55; }
.od-desc .k { color: #6C736E; }
.od-desc ul { margin: 2px 0 0; padding-left: 16px; }
.od-desc li { margin: 0 0 1px; }
.od-pick { margin: 0 6px 0 0; vertical-align: -1px; }
.od p.od-note { font-size: 12px; color: #555; margin: 10px 0 0; line-height: 1.5; }
.od-note a { color: #4279D1; }
.od-foot { margin-top: 26px; padding-top: 10px; border-top: 1px solid #e4e4e0; font-size: 10px; color: #8a8a84; line-height: 1.55; }
';
}

/**
 * Daten fürs Dokument an einer Stelle (Snapshot + Anfrage + Firmendaten).
 */
function fge_offer_document_data( int $req ): array {
	$snap   = (array) get_post_meta( $req, '_fge_offer_snapshot', true );
	$co     = function_exists( 'fge_company' ) ? fge_company() : [];
	$m      = static fn( string $k ): string => (string) get_post_meta( $req, '_fge_' . $k, true );
	$status = (string) get_post_meta( $req, '_fge_offer_status', true );
	$sel    = 'accepted' === $status
		? array_map( 'intval', (array) get_post_meta( $req, '_fge_offer_extras_selected', true ) )
		: null;
	$sent   = (int) get_post_meta( $req, '_fge_offer_sent_at', true );
	return [
		'snap'      => $snap,
		'ref'       => function_exists( 'fge_request_number' ) ? fge_request_number( $req ) : 'FG-' . $req,
		'status'    => $status,
		'selected'  => $sel,
		'deadline'  => (int) get_post_meta( $req, '_fge_offer_deadline', true ),
		'created'   => $sent > 0 ? $sent : ( strtotime( (string) get_post_field( 'post_date', $req ) ) ?: time() ),
		'accepted'  => (string) get_post_meta( $req, '_fge_offer_accepted_at', true ),
		'customer'  => [
			'company' => $m( 'company_name' ),
			'first'   => $m( 'contact_first_name' ),
			'last'    => $m( 'contact_last_name' ),
			'street'  => $m( 'company_street' ),
			'zip'     => $m( 'company_zip' ),
			'city'    => $m( 'company_city' ),
		],
		'company'   => $co,
		'agb_url'   => home_url( '/agb/' ),
	];
}

/**
 * Rendert das Angebotsdokument.
 *
 * @param int    $req  Anfrage.
 * @param string $mode 'web' (mit Abwahl-Checkboxen bei offenem Angebot), 'pdf' (statisch).
 */
function fge_offer_document_html( int $req, string $mode = 'web' ): string {
	$d    = fge_offer_document_data( $req );
	$snap = $d['snap'];
	if ( empty( $snap ) ) {
		return '';
	}
	$co   = $d['company'];
	$cu   = $d['customer'];
	$pos  = fge_offer_positions( $snap, $d['selected'] );
	$pick = 'web' === $mode && 'pending' === $d['status'];
	$e    = 'esc_html';

	$contact_name  = (string) ( $snap['contact_name'] ?? ( $co['managing_director'] ?? 'Firmengolf' ) );
	$contact_phone = (string) ( $snap['contact_phone'] ?? ( $co['phone_display'] ?? '' ) );
	$contact_mail  = (string) ( $snap['contact_email'] ?? ( $co['email_events'] ?? '' ) );
	$first         = trim( (string) $cu['first'] );
	$full_name     = trim( $cu['first'] . ' ' . $cu['last'] );
	$addr_line     = trim( $cu['zip'] . ' ' . $cu['city'] );

	// ── Kopf ──
	$h  = '<div class="od">';
	$h .= '<table class="od-head"><tr><td class="od-brand">' . $e( (string) ( $co['brand'] ?? 'Firmengolf' ) ) . '</td><td class="od-doctype">Angebot</td></tr></table>';
	$h .= '<div class="od-body">';

	if ( 'accepted' === $d['status'] ) {
		$acc = '' !== $d['accepted'] ? wp_date( 'd.m.Y', strtotime( $d['accepted'] ) ) : '';
		$h  .= '<div class="od-status">Angenommen' . ( $acc ? ' am ' . $e( $acc ) : '' ) . ', euer Event ist verbindlich gebucht.</div>';
	} elseif ( 'declined' === $d['status'] ) {
		$h .= '<div class="od-status no">Dieses Angebot wurde abgelehnt.</div>';
	}

	// ── Empfänger + Angebotsdaten ──
	$h .= '<table class="od-meta"><tr><td class="od-addr"><div class="od-lbl">Angebot an</div>';
	$h .= '<div>' . ( '' !== $cu['company'] ? '<strong>' . $e( $cu['company'] ) . '</strong><br>' : '' )
		. ( '' !== $full_name ? $e( $full_name ) . '<br>' : '' )
		. ( '' !== $cu['street'] ? $e( $cu['street'] ) . '<br>' : '' )
		. ( '' !== $addr_line ? $e( $addr_line ) : '' ) . '</div></td>';
	$h .= '<td class="od-facts"><table>'
		. '<tr><th>Angebotsnummer</th><td><strong>' . $e( $d['ref'] ) . '</strong></td></tr>'
		. '<tr><th>Datum</th><td>' . $e( wp_date( 'd.m.Y', $d['created'] ) ) . '</td></tr>'
		. ( $d['deadline'] > 0 && 'pending' === $d['status'] ? '<tr><th>Gültig bis</th><td>' . $e( wp_date( 'd.m.Y', $d['deadline'] ) ) . '</td></tr>' : '' )
		. '<tr><th>Ansprechpartner</th><td>' . $e( $contact_name )
			. ( '' !== $contact_phone ? '<br>' . $e( $contact_phone ) : '' )
			. ( '' !== $contact_mail ? '<br>' . $e( $contact_mail ) : '' ) . '</td></tr>'
		. '</table></td></tr></table>';

	// ── Titel + Anrede ──
	$h .= '<div class="od-title">Angebot ' . $e( $d['ref'] ) . ': ' . $e( (string) ( $snap['event_title'] ?? 'Firmen-Event' ) ) . '</div>';
	$h .= '<p class="od-intro">' . ( '' !== $first ? 'Hallo ' . $e( $first ) . ', ' : '' ) . 'vielen Dank für eure Anfrage. Wie besprochen bieten wir euch folgendes Event an:</p>';

	// ── Positionen ──
	$h .= '<table class="od-pos"><thead><tr><th class="c-pos">Pos.</th><th>Leistung</th><th class="r c-qty">Menge</th><th class="r c-unit">Einzelpreis netto</th><th class="r c-sum">Gesamt netto</th></tr></thead><tbody>';
	$n  = 0;
	foreach ( $pos['rows'] as $row ) {
		$n++;
		$is_event = 'event' === $row['kind'];
		$cls      = $row['selected'] ? '' : ' class="od-off"';
		$attrs    = ! $is_event ? ' data-src="' . (int) $row['src'] . '" data-total="' . esc_attr( (string) $row['total'] ) . '"' : '';
		$h       .= '<tr' . $cls . $attrs . '><td class="c-pos">' . $n . '</td><td>';
		if ( $pick && ! $is_event ) {
			$h .= '<label><input type="checkbox" class="od-pick tl-x-pick" name="fge_offer_extras[]" value="' . (int) $row['src'] . '"' . ( $row['selected'] ? ' checked' : '' ) . '><strong>' . $e( $row['title'] ) . '</strong></label>';
		} else {
			$h .= '<strong>' . $e( $row['title'] ) . '</strong>';
		}
		if ( $is_event ) {
			$desc  = '';
			$desc .= '' !== (string) ( $snap['date'] ?? '' ) ? '<span class="k">Termin:</span> ' . $e( (string) $snap['date'] ) . '<br>' : '';
			$desc .= '' !== (string) ( $snap['location'] ?? '' ) ? '<span class="k">Ort:</span> ' . $e( (string) $snap['location'] ) . '<br>' : '';
			$sched = trim( (string) ( $snap['schedule'] ?? '' ) );
			if ( '' !== $sched ) {
				$desc .= '<span class="k">Ablauf:</span> ' . nl2br( $e( $sched ) ) . '<br>';
			}
			$inc = array_values( array_filter( array_map( 'strval', (array) ( $snap['includes'] ?? [] ) ) ) );
			if ( ! empty( $inc ) ) {
				$desc .= '<span class="k">Leistungen:</span><ul>';
				foreach ( $inc as $i ) {
					$desc .= '<li>' . $e( $i ) . '</li>';
				}
				$desc .= '</ul>';
			}
			if ( '' !== $desc ) {
				$h .= '<div class="od-desc">' . $desc . '</div>';
			}
		}
		$h .= '</td>';
		if ( $row['unit_price'] > 0 ) {
			$h .= '<td class="r c-qty" data-label="Menge">' . (int) $row['qty'] . ' ' . $e( $row['unit_label'] ) . '</td>'
				. '<td class="r c-unit" data-label="Einzelpreis netto">' . $e( fge_money( (float) $row['unit_price'] ) ) . '</td>'
				. '<td class="r c-sum" data-label="Gesamt netto">' . $e( fge_money( (float) $row['total'] ) ) . '</td>';
		} else {
			$h .= '<td class="r c-qty"></td><td class="r c-unit"></td><td class="r c-sum" data-label="Gesamt netto">auf Anfrage</td>';
		}
		$h .= '</tr>';
	}
	$h .= '</tbody>';
	if ( $pos['net'] > 0 ) {
		$h .= '<tfoot>'
			. '<tr><td colspan="4" class="r">Zwischensumme netto</td><td class="r"><span id="od-net">' . $e( fge_money( $pos['net'] ) ) . '</span></td></tr>'
			. '<tr><td colspan="4" class="r">zzgl. ' . (int) $pos['vat_percent'] . ' % USt.</td><td class="r"><span id="od-vat">' . $e( fge_money( $pos['vat'] ) ) . '</span></td></tr>'
			. '<tr class="od-total"><td colspan="4" class="r">Gesamtbetrag</td><td class="r"><span id="od-total">' . $e( fge_money( $pos['total'] ) ) . '</span></td></tr>'
			. '</tfoot>';
	}
	$h .= '</table>';

	// ── Hinweise ──
	if ( $pos['pp'] && $pos['pax'] > 0 ) {
		$h .= '<p class="od-note">' . fge_offer_pax_note( (int) $pos['pax'] ) . '</p>';
	}
	if ( $pick && count( $pos['rows'] ) > 1 ) {
		$h .= '<p class="od-note">Zusatzleistungen könnt ihr abwählen, die Summe passt sich sofort an.</p>';
	}
	$wishes = array_merge( (array) ( $snap['wishes_platz'] ?? [] ), (array) ( $snap['wishes_firmengolf'] ?? [] ) );
	if ( ! empty( $wishes ) ) {
		$h .= '<p class="od-note">Auf Wunsch zusätzlich organisierbar, wird separat angeboten: ' . $e( implode( ', ', array_map( 'strval', $wishes ) ) ) . '.</p>';
	}
	$valid = ( $d['deadline'] > 0 && 'pending' === $d['status'] )
		? 'Dieses Angebot ist gültig bis ' . $e( wp_date( 'd.m.Y', $d['deadline'] ) ) . ', bis dahin halten wir den Termin für euch. '
		: '';
	$h .= '<p class="od-note">' . $valid . 'Es gelten unsere <a href="' . esc_url( $d['agb_url'] ) . '">AGB</a> inkl. der dort genannten Storno- und Zahlungsbedingungen.</p>';

	// ── Fußzeile (Pflichtangaben) ──
	$imp_addr = trim( ( $co['office_street'] ?? '' ) . ', ' . ( $co['office_zip'] ?? '' ) . ' ' . ( $co['office_city'] ?? '' ), ', ' );
	$h .= '<div class="od-foot">' . $e( (string) ( $co['legal_name'] ?? 'Visionpunch UG (haftungsbeschränkt)' ) )
		. ( '' !== $imp_addr ? ' · ' . $e( $imp_addr ) : '' )
		. ( ! empty( $co['managing_director'] ) ? ' · Geschäftsführer: ' . $e( (string) $co['managing_director'] ) : '' )
		. ( ! empty( $co['register_court'] ) ? ' · ' . $e( (string) $co['register_court'] . ' ' . ( $co['register_no'] ?? '' ) ) : '' )
		. ( ! empty( $co['ust_id'] ) ? ' · USt-IdNr. ' . $e( (string) $co['ust_id'] ) : '' )
		. ( ! empty( $co['email_events'] ) ? ' · ' . $e( (string) $co['email_events'] ) : '' )
		. '</div>';

	$h .= '</div></div>';
	return $h;
}

/**
 * Positionstabelle für die Angebotsmail (Inline-Styles, Mailclient-tauglich).
 */
function fge_offer_mail_table_html( int $req ): string {
	$d    = fge_offer_document_data( $req );
	$snap = $d['snap'];
	if ( empty( $snap ) ) {
		return '';
	}
	$pos = fge_offer_positions( $snap, $d['selected'] );
	$e   = 'esc_html';
	$th  = 'text-align:left;font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#6C736E;border-bottom:2px solid #20294D;padding:6px 8px;';
	$td  = 'padding:10px 8px;border-bottom:1px solid #e4e4e0;vertical-align:top;font-size:14px;';
	$r   = 'text-align:right;white-space:nowrap;';
	$h   = '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 6px;">'
		. '<tr><th style="' . $th . 'width:5%;">Pos.</th><th style="' . $th . '">Leistung</th><th style="' . $th . $r . 'width:10%;">Menge</th><th style="' . $th . $r . 'width:15%;">Einzelpreis netto</th><th style="' . $th . $r . 'width:15%;">Gesamt netto</th></tr>';
	$n = 0;
	foreach ( $pos['rows'] as $row ) {
		$n++;
		$grey = $row['selected'] ? '' : 'color:#9a9a94;';
		$h   .= '<tr><td style="' . $td . $grey . 'color:#6C736E;">' . $n . '</td><td style="' . $td . $grey . '"><strong>' . $e( $row['title'] ) . '</strong>';
		if ( 'event' === $row['kind'] ) {
			$desc  = '';
			$desc .= '' !== (string) ( $snap['date'] ?? '' ) ? '<span style="color:#6C736E;">Termin:</span> ' . $e( (string) $snap['date'] ) . '<br>' : '';
			$desc .= '' !== (string) ( $snap['location'] ?? '' ) ? '<span style="color:#6C736E;">Ort:</span> ' . $e( (string) $snap['location'] ) . '<br>' : '';
			$sched = trim( (string) ( $snap['schedule'] ?? '' ) );
			$desc .= '' !== $sched ? '<span style="color:#6C736E;">Ablauf:</span> ' . nl2br( $e( $sched ) ) . '<br>' : '';
			$inc   = array_values( array_filter( array_map( 'strval', (array) ( $snap['includes'] ?? [] ) ) ) );
			if ( ! empty( $inc ) ) {
				$desc .= '<span style="color:#6C736E;">Leistungen:</span><ul style="margin:2px 0 0;padding-left:16px;">';
				foreach ( $inc as $i ) {
					$desc .= '<li>' . $e( $i ) . '</li>';
				}
				$desc .= '</ul>';
			}
			if ( '' !== $desc ) {
				$h .= '<div style="margin-top:6px;color:#444;font-size:12px;line-height:1.55;">' . $desc . '</div>';
			}
		}
		$h .= '</td>';
		if ( $row['unit_price'] > 0 ) {
			$h .= '<td style="' . $td . $r . $grey . '">' . (int) $row['qty'] . ' ' . $e( $row['unit_label'] ) . '</td>'
				. '<td style="' . $td . $r . $grey . '">' . $e( fge_money( (float) $row['unit_price'] ) ) . '</td>'
				. '<td style="' . $td . $r . $grey . '">' . $e( fge_money( (float) $row['total'] ) ) . '</td>';
		} else {
			$h .= '<td style="' . $td . '"></td><td style="' . $td . '"></td><td style="' . $td . $r . '">auf Anfrage</td>';
		}
		$h .= '</tr>';
	}
	if ( $pos['net'] > 0 ) {
		$tf = 'padding:5px 8px;font-size:14px;' . $r;
		$h .= '<tr><td colspan="4" style="' . $tf . '">Zwischensumme netto</td><td style="' . $tf . '">' . $e( fge_money( $pos['net'] ) ) . '</td></tr>'
			. '<tr><td colspan="4" style="' . $tf . '">zzgl. ' . (int) $pos['vat_percent'] . ' % USt.</td><td style="' . $tf . '">' . $e( fge_money( $pos['vat'] ) ) . '</td></tr>'
			. '<tr><td colspan="4" style="' . $tf . 'font-weight:bold;font-size:15px;border-top:2px solid #20294D;padding-top:9px;">Gesamtbetrag</td><td style="' . $tf . 'font-weight:bold;font-size:15px;border-top:2px solid #20294D;padding-top:9px;">' . $e( fge_money( $pos['total'] ) ) . '</td></tr>';
	}
	$h .= '</table>';
	if ( $pos['pp'] && $pos['pax'] > 0 ) {
		$h .= '<p style="margin:0 0 6px;color:#555;font-size:12px;">' . fge_offer_pax_note( (int) $pos['pax'] ) . '</p>';
	}
	return $h;
}

// ── PDF ──────────────────────────────────────────────────────────────────────

/** Dompdf vorhanden? */
function fge_pdf_available(): bool {
	return is_readable( FGE_DIR . 'lib/dompdf/autoload.inc.php' );
}

/** Schreibbares Verzeichnis für Font-Cache und temporäre PDFs (Uploads, nicht listbar). */
function fge_pdf_workdir(): string {
	$up  = wp_upload_dir();
	$dir = trailingslashit( (string) $up['basedir'] ) . 'fge-pdf-cache';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
		@file_put_contents( $dir . '/index.html', '' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		@file_put_contents( $dir . '/.htaccess', "Order deny,allow\nDeny from all\n" ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	return $dir;
}

/** Vollständiges HTML-Dokument fürs PDF (A4). */
function fge_offer_pdf_html( int $req ): string {
	$doc = fge_offer_document_html( $req, 'pdf' );
	if ( '' === $doc ) {
		return '';
	}
	return '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><title>Angebot</title><style>'
		. '@page { margin: 0 0 10mm 0; } body { margin: 0; padding: 0; } '
		. fge_offer_document_css()
		. '.od { font-size: 11.5px; line-height: 1.42; } .od-head td { padding: 7mm 14mm; } .od-body { padding: 8mm 14mm 5mm; } '
		. '.od-meta { margin-bottom: 16px; } .od-title { font-size: 16px; } .od p.od-intro { margin-bottom: 12px; } '
		. '.od-pos td { padding: 7px 6px; } .od-pos th { padding: 5px 6px; } .od-desc { font-size: 10.5px; line-height: 1.45; } '
		. '.od-pos tfoot td { padding: 4px 6px; } .od p.od-note { font-size: 10.5px; margin-top: 7px; } .od-foot { margin-top: 16px; font-size: 9px; } '
		. '</style></head><body>' . $doc . '</body></html>';
}

/** PDF-Bytes des Angebots, oder null (keine Bibliothek / kein Snapshot / Fehler). */
function fge_offer_pdf_bytes( int $req ): ?string {
	if ( ! fge_pdf_available() ) {
		return null;
	}
	$html = fge_offer_pdf_html( $req );
	if ( '' === $html ) {
		return null;
	}
	try {
		require_once FGE_DIR . 'lib/dompdf/autoload.inc.php';
		$work    = fge_pdf_workdir();
		$options = new \Dompdf\Options();
		$options->set( 'isRemoteEnabled', false );
		$options->set( 'isPhpEnabled', false );
		$options->set( 'defaultFont', 'DejaVu Sans' );
		$options->set( 'tempDir', $work );
		$options->set( 'fontCache', $work );
		$options->set( 'chroot', FGE_DIR );
		$dompdf = new \Dompdf\Dompdf( $options );
		$dompdf->loadHtml( $html, 'UTF-8' );
		$dompdf->setPaper( 'A4', 'portrait' );
		$dompdf->render();
		$out = $dompdf->output();
		return is_string( $out ) && '' !== $out ? $out : null;
	} catch ( \Throwable $t ) {
		error_log( 'fge_offer_pdf_bytes: ' . $t->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		return null;
	}
}

/** Dateiname fürs PDF, z. B. „Angebot-FG-26-165.pdf". */
function fge_offer_pdf_filename( int $req ): string {
	$ref = function_exists( 'fge_request_number' ) ? fge_request_number( $req ) : 'FG-' . $req;
	return 'Angebot-' . preg_replace( '/[^A-Za-z0-9\-]/', '', $ref ) . '.pdf';
}

/** Öffentliche PDF-URL (Magic-Link + /pdf). */
function fge_offer_pdf_link( int $req ): string {
	return trailingslashit( fge_offer_link( $req ) ) . 'pdf/';
}

/**
 * PDF als temporäre Datei fürs Mail-Attachment. Rückgabe Pfad oder ''.
 * Aufrufer löscht die Datei nach dem Versand (fge_offer_pdf_cleanup).
 */
function fge_offer_pdf_tempfile( int $req ): string {
	$bytes = fge_offer_pdf_bytes( $req );
	if ( null === $bytes ) {
		return '';
	}
	$dir = fge_pdf_workdir() . '/' . wp_generate_password( 12, false, false );
	if ( ! wp_mkdir_p( $dir ) ) {
		return '';
	}
	$path = $dir . '/' . fge_offer_pdf_filename( $req );
	return false !== file_put_contents( $path, $bytes ) ? $path : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
}

function fge_offer_pdf_cleanup( string $path ): void {
	if ( '' === $path || ! file_exists( $path ) ) {
		return;
	}
	@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	@rmdir( dirname( $path ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
}

// ── Route: /angebot/<token>/pdf/ ─────────────────────────────────────────────

add_action( 'init', static function () {
	add_rewrite_rule( '^angebot/([^/]+)/pdf/?$', 'index.php?fge_angebot=$matches[1]&fge_angebot_pdf=1', 'top' );
} );
add_filter( 'query_vars', static function ( array $vars ): array {
	$vars[] = 'fge_angebot_pdf';
	return $vars;
} );
add_action( 'init', static function () {
	$rules = get_option( 'rewrite_rules' );
	if ( is_array( $rules ) && ! isset( $rules['^angebot/([^/]+)/pdf/?$'] ) ) {
		flush_rewrite_rules( false );
	}
}, 99 );

add_action( 'template_redirect', static function () {
	if ( ! get_query_var( 'fge_angebot_pdf' ) ) {
		return;
	}
	$token = (string) get_query_var( 'fge_angebot' );
	$req   = function_exists( 'fge_request_by_customer_token' ) ? fge_request_by_customer_token( $token ) : 0;
	if ( $req <= 0 || empty( get_post_meta( $req, '_fge_offer_snapshot', true ) ) ) {
		status_header( 404 );
		nocache_headers();
		wp_die( 'Kein Angebot gefunden.', 'Angebot', [ 'response' => 404 ] );
	}
	$bytes = fge_offer_pdf_bytes( $req );
	if ( null === $bytes ) {
		wp_safe_redirect( fge_offer_link( $req ) );
		exit;
	}
	nocache_headers();
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: attachment; filename="' . fge_offer_pdf_filename( $req ) . '"' );
	header( 'Content-Length: ' . strlen( $bytes ) );
	echo $bytes; // phpcs:ignore WordPress.Security.EscapeOutput
	exit;
} );
