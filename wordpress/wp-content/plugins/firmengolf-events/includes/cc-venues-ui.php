<?php
/**
 * Oberfläche der Platz-Pipeline im Anfrage-Cockpit.
 *
 * Mehrere Plätze je Anfrage: aufnehmen, anfragen, Antwort festhalten,
 * Preise nebeneinander vergleichen, einen nehmen, den übrigen absagen.
 * Datenschicht liegt in cc-venues.php, die Mails in cc-venue-mails.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Die ganze Pipeline als Karte. */
function fge_cc_venues_panel( int $req ): void {
	$venues = function_exists( 'fge_venues_get' ) ? fge_venues_get( $req ) : [];
	$chosen = (int) get_post_meta( $req, '_fge_assigned_partner_id', true );

	echo '<section class="cc-card"><h2>Plätze</h2>';

	if ( ! $venues ) {
		echo '<p class="cc-muted">Noch kein Platz in der Liste. Nimm die Plätze auf, die für diese Anfrage in Frage kommen, dann stehen ihre Preise nebeneinander.</p>';
	}
	// Als Tafel nach Stand, wie im Design vom 07.10.2026. Abgesagte liegen
	// darunter, damit die laufenden Plätze vorne stehen.
	if ( $venues ) {
		$cols = [
			'idee'      => [ 'Vorschläge', 'neutral', [] ],
			'angefragt' => [ 'Angefragt', 'warn', [] ],
			'zugesagt'  => [ 'Zugesagt', 'good', [] ],
		];
		$off = [];
		foreach ( $venues as $v ) {
			$st = (string) $v['status'];
			if ( 'abgesagt' === $st ) {
				$off[] = $v;
			} else {
				$cols[ 'gewaehlt' === $st ? 'zugesagt' : ( isset( $cols[ $st ] ) ? $st : 'idee' ) ][2][] = $v;
			}
		}
		echo '<div class="cc-board">';
		foreach ( $cols as $key => [ $label, $tone, $list ] ) {
			echo '<div class="cc-board-col cc-board-col--' . esc_attr( $tone ) . '"><p class="cc-board-head"><span class="cc-dot"></span>' . esc_html( $label ) . '<span class="cc-count">' . (int) count( $list ) . '</span></p>';
			if ( ! $list ) {
				echo '<p class="cc-muted cc-board-empty">Keine.</p>';
			}
			foreach ( $list as $v ) {
				fge_cc_venue_row( $req, $v );
			}
			echo '</div>';
		}
		echo '</div>';
		if ( $off ) {
			echo '<details class="cc-details"><summary>Abgesagt (' . (int) count( $off ) . ')</summary>';
			foreach ( $off as $v ) {
				fge_cc_venue_row( $req, $v );
			}
			echo '</details>';
		}
	}

	// Nähe-Liste und Suche stören, sobald die Plätze stehen: ab drei Plätzen eingeklappt.
	echo '<details class="cc-details cc-more-venues"' . ( count( $venues ) < 3 ? ' open' : '' ) . '><summary>Weitere Plätze aufnehmen (Nähe und Suche)</summary>';
	fge_cc_venue_nearby_block( $req );
	fge_cc_venue_add_form( $req, $venues );
	echo '</details>';

	$rest = function_exists( 'fge_venues_to_decline' ) ? fge_venues_to_decline( $req ) : [];
	if ( $chosen > 0 && $rest ) {
		echo '<div class="cc-decline-all">';
		echo '<p>' . (int) count( $rest ) . ' ' . ( 1 === count( $rest ) ? 'Platz wartet' : 'Plätze warten' ) . ' noch auf eine Rückmeldung von dir.</p>';
		fge_cc_button( 'fge_cc_venue_decline_all', $req, 'Allen übrigen absagen', [
			'confirm' => 'Allen übrigen Plätzen eine freundliche Absage schicken?',
		] );
		echo '<p class="cc-muted">Persönlich formuliert, mit dem Hinweis, dass wir wieder anfragen. Einzeln absagen geht oben je Platz.</p>';
		echo '</div>';
	}
	echo '</section>';
}

/** Eine Zeile der Pipeline. */
function fge_cc_venue_row( int $req, array $v ): void {
	$id     = (int) $v['id'];
	$pid    = (int) $v['partner_id'];
	$status = (string) $v['status'];
	$mail   = function_exists( 'fge_cc_partner_email' ) ? fge_cc_partner_email( $pid ) : '';
	$tones  = [ 'gewaehlt' => 'good', 'zugesagt' => 'good', 'angefragt' => 'warn', 'abgesagt' => 'bad', 'idee' => 'neutral' ];
	$stamm  = function_exists( 'fge_partner_is_stammdaten' ) && fge_partner_is_stammdaten( $pid );

	echo '<article class="cc-venue is-' . esc_attr( $status ) . '">';
	echo '<div class="cc-venue-head">';
	echo '<span class="cc-venue-name">' . esc_html( (string) $v['title'] ) . '</span>';
	echo fge_cc_pill( fge_venue_statuses()[ $status ] ?? $status, $tones[ $status ] ?? 'neutral' ); // phpcs:ignore WordPress.Security.EscapeOutput
	if ( $stamm ) {
		echo fge_cc_pill( 'Stammdaten', 'neutral' ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	if ( (float) $v['price'] > 0 ) {
		echo '<span class="cc-venue-price">' . esc_html(
			number_format_i18n( (float) $v['price'], 2 ) . ' € '
			. ( (int) $v['price_gross'] ? 'brutto' : 'netto' ) . ' '
			. ( 'person' === $v['price_basis'] ? 'p.P.' : 'pauschal' )
		) . '</span>';
	}
	if ( '' !== (string) $v['asked_at'] ) {
		echo '<span class="cc-muted">angefragt ' . esc_html( wp_date( 'd.m.', (int) strtotime( (string) $v['asked_at'] ) ) )
			. ( '' !== (string) $v['channel'] ? ', ' . esc_html( (string) ( fge_venue_channels()[ $v['channel'] ] ?? '' ) ) : '' )
			. '</span>';
	}
	echo '</div>';

	if ( '' === $mail ) {
		echo '<p class="cc-warn">Keine Kontaktmail hinterlegt, dieser Platz geht nur telefonisch.</p>';
	}
	if ( $stamm || '' === $mail ) {
		fge_cc_partner_contact_form( $pid, $req );
	}
	if ( '' !== trim( (string) $v['reason'] ) ) {
		echo '<p class="cc-venue-reason">' . esc_html( (string) $v['reason'] ) . '</p>';
	}
	if ( '' !== trim( (string) $v['note'] ) ) {
		echo '<p class="cc-muted">' . esc_html( (string) $v['note'] ) . '</p>';
	}
	fge_cc_venue_dates_line( $req, $v );
	if ( function_exists( 'fge_venue_catalog_status_html' ) ) {
		echo fge_venue_catalog_status_html( $v ); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	// Das Green Fee steht schon in der Kopfzeile, hier stünde es sonst doppelt
	// und ohne Preis, weil es auf der Zeile selbst gespeichert ist.
	$items = array_values( array_filter( (array) $v['items'], static fn( $it ) => 'green_fee' !== (string) $it['wish_key'] ) );
	if ( $items && in_array( $status, [ 'zugesagt', 'gewaehlt' ], true ) ) {
		echo '<ul class="cc-venue-items">';
		foreach ( $items as $it ) {
			$price = (float) $it['price'];
			echo '<li>' . esc_html( (string) $it['label'] ) . ' ';
			if ( ! (int) $it['available'] ) {
				echo '<span class="cc-muted">bietet der Platz nicht an (steht nach der Platzwahl im Angebot als nicht möglich)</span>';
			} elseif ( $price > 0 ) {
				echo '<strong>' . esc_html( number_format_i18n( $price, 2 ) . ' €' ) . '</strong> '
					. '<span class="cc-muted">' . esc_html(
						( (int) $it['price_gross'] ? 'brutto' : 'netto' ) . ' '
						. ( 'person' === $it['price_basis'] ? 'p.P.' : 'pauschal' )
					) . '</span>';
			} else {
				echo '<span class="cc-muted">kein Preis genannt</span>';
			}
			echo '</li>';
		}
		echo '</ul>';
	}

	echo '<div class="cc-venue-actions">';
	if ( 'idee' === $status ) {
		if ( '' !== $mail ) {
			fge_cc_button( 'fge_cc_venue_ask', $req, 'Per Mail anfragen', [
				'class'   => 'cc-btn cc-btn--primary',
				'fields'  => [ 'venue_id' => $id, 'how' => 'mail' ],
				'confirm' => 'Anfrage an ' . $v['title'] . ' senden?',
			] );
		}
		fge_cc_button( 'fge_cc_venue_ask', $req, 'Telefonisch angefragt', [
			'fields' => [ 'venue_id' => $id, 'how' => 'telefon' ],
		] );
		fge_cc_button( 'fge_cc_venue_remove', $req, 'Entfernen', [
			'fields'  => [ 'venue_id' => $id ],
			'confirm' => 'Diesen Platz aus der Liste nehmen?',
		] );
	}
	if ( in_array( $status, [ 'zugesagt', 'gewaehlt' ], true ) && '' !== $mail && ! ( function_exists( 'fge_request_is_booked' ) && fge_request_is_booked( $req ) ) ) {
		// Nach Telefonat oder Mail: dem Platz schriftlich bestätigen, was wir notiert haben.
		$sum_at = (string) ( $v['summary_at'] ?? '' ) ?: (string) ( $v['reservation_at'] ?? '' );
		fge_cc_button( 'fge_cc_venue_summary', $req, '' !== $sum_at ? 'Bestätigung erneut senden' : 'Bestätigung senden', [
			'fields'  => [ 'venue_id' => $id ],
			'confirm' => 'Dem Platz per Mail bestätigen, was wir notiert haben (Termine, Preise, offene Punkte), mit der Bitte zu reservieren?',
		] );
		if ( '' !== (string) ( $v['reserved_until'] ?? '' ) ) {
			echo '<span class="cc-muted">Reserviert bis ' . esc_html( wp_date( 'd.m.Y', (int) strtotime( (string) $v['reserved_until'] ) ) ) . ( '' !== $sum_at ? ', bestätigt ' . esc_html( wp_date( 'd.m. H:i', (int) strtotime( $sum_at ) ) ) : '' ) . '</span>';
		}
	}
	if ( 'zugesagt' === $status && ! ( function_exists( 'fge_request_is_booked' ) && fge_request_is_booked( $req ) ) ) {
		fge_cc_button( 'fge_cc_venue_choose', $req, 'Diesen Platz nehmen', [
			'class'   => 'cc-btn cc-btn--primary',
			'fields'  => [ 'venue_id' => $id ],
			'confirm' => $v['title'] . ' als Platz für diese Anfrage festlegen?',
		] );
	}
	if ( in_array( $status, [ 'angefragt', 'zugesagt' ], true ) && '' !== $mail ) {
		fge_cc_button( 'fge_cc_venue_decline', $req, 'Absagen', [
			'fields'  => [ 'venue_id' => $id ],
			'confirm' => 'Diesem Platz absagen?',
		] );
	}
	echo '</div>';

	// Antwort ist jederzeit änderbar (Julius, 28.09.: „das müsste ich immer anpassen
	// können"), auch beim gewählten oder schon zugesagten Platz.
	if ( 'idee' !== $status ) {
		fge_cc_venue_reply_form( $req, $v );
	}
	if ( in_array( $status, [ 'zugesagt', 'gewaehlt' ], true ) ) {
		fge_cc_venue_calc_form( $req, $v );
	}
	echo '</article>';
}

/**
 * Antworten des Platzes je Wunschtermin als Pill-Zeile, plus Alternativvorschlag.
 * Erscheint, sobald mindestens ein Termin beantwortet ist oder der Platz zugesagt hat.
 */
function fge_cc_venue_dates_line( int $req, array $v ): void {
	if ( ! function_exists( 'fge_request_wish_date_labels' ) || ! function_exists( 'fge_venue_dates_get' ) ) {
		return;
	}
	$id     = (int) $v['id'];
	$status = (string) $v['status'];
	$labels = fge_request_wish_date_labels( $req );
	$dates  = fge_venue_dates_get( $id );
	if ( ! $labels ) {
		return;
	}
	$answered = array_filter( $dates, static fn( $d ) => null !== $d['available'] );
	if ( ! $answered && ! in_array( $status, [ 'zugesagt', 'gewaehlt' ], true ) ) {
		return;
	}

	echo '<p class="cc-dates"><span class="cc-muted">Termine:</span>';
	foreach ( $labels as $idx => $label ) {
		$avail = $dates[ $idx ]['available'] ?? null;
		if ( 1 === $avail ) {
			$pill = fge_cc_pill( $idx . ' geht', 'good' );
		} elseif ( 0 === $avail ) {
			$pill = fge_cc_pill( $idx . ' geht nicht', 'bad' );
		} else {
			$pill = fge_cc_pill( $idx . ' offen', 'neutral' );
		}
		echo '<span title="' . esc_attr( (string) $label ) . '">' . $pill . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	$first = (int) min( array_keys( $labels ) );
	$alt   = trim( (string) ( $dates[ $first ]['note'] ?? '' ) );
	if ( '' !== $alt ) {
		echo '<span class="cc-dates-alt">Alternativvorschlag: ' . esc_html( $alt ) . '</span>';
	}
	echo '</p>';

	if ( $answered && in_array( $status, [ 'zugesagt', 'gewaehlt' ], true ) && ! fge_venue_free_dates( $id ) ) {
		echo '<p class="cc-warn">Zugesagt, aber kein Wunschtermin frei.</p>';
	}
}

/**
 * Kalkulation je Platz: Einkauf, Netto, Vorschlag mit Aufschlag, Verkaufspreis
 * und wer die Position organisiert. Nur für Plätze, die zugesagt haben.
 */
function fge_cc_venue_calc_form( int $req, array $v ): void {
	if ( ! function_exists( 'fge_venue_sale_from_cost' ) ) {
		return;
	}
	$id     = (int) $v['id'];
	$markup = (float) ( $v['markup_percent'] ?? 20 );
	$vat    = defined( 'FGE_VAT_PERCENT' ) ? (float) FGE_VAT_PERCENT : 19.0;
	$net_of = static fn( float $cost, bool $gross ): float => $gross ? $cost / ( 1 + $vat / 100 ) : $cost;
	$money  = static fn( float $x ): string => number_format_i18n( $x, 2 ) . ' €';
	$whole  = static fn( float $x ): string => number_format_i18n( $x, 0 ) . ' €';
	$field  = static fn( float $x ): string => $x > 0 ? number_format_i18n( $x, 0 ) : '';
	$pct    = rtrim( rtrim( number_format_i18n( $markup, 2 ), '0' ), ',' );

	$pax = 0;
	if ( function_exists( 'fge_venue_mail_facts' ) ) {
		$pax = (int) fge_venue_mail_facts( $req )['pax'];
	}
	$mult = $pax > 0 ? $pax : 1;
	$sum  = 0.0;

	echo '<details class="cc-details cc-calc"><summary>Kalkulation</summary>';
	echo '<form class="cc-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="fge_cc_venue_calc">';
	echo '<input type="hidden" name="request_id" value="' . (int) $req . '">';
	echo '<input type="hidden" name="venue_id" value="' . $id . '">';
	wp_nonce_field( 'fge_cc_venue_calc_' . $req );

	echo '<label class="cc-field cc-field--markup"><span>Aufschlag % (für alle Zeilen)</span><input type="text" name="markup" class="cc-calc-markup" value="' . esc_attr( $pct ) . '" inputmode="decimal"></label>';

	// Die Felder hängen zusammen (Julius, 28.09.): Aufschlag ändert alle Verkaufspreise,
	// Marge je Zeile ändert ihren Verkaufspreis, ein Verkaufspreis ändert seine Marge.
	// Gespeichert werden Aufschlag und Verkaufspreise, die Marge je Zeile ist abgeleitet.
	$margin_of = static fn( float $net, float $sale ): string => $net > 0 && $sale > 0 ? rtrim( rtrim( number_format_i18n( ( $sale / $net - 1 ) * 100, 1 ), '0' ), ',' ) : '';
	echo '<table class="cc-calc-table"><thead><tr>';
	echo '<th>Position</th><th>Einkauf</th><th>Netto</th><th>Marge %</th><th>Verkauf netto</th><th>Organisation</th>';
	echo '</tr></thead><tbody>';

	// Grundpreis: Event und Platznutzung, immer vom Platz organisiert.
	$cost  = (float) $v['price'];
	$gross = (bool) (int) $v['price_gross'];
	$pp    = 'person' === (string) $v['price_basis'];
	$basis = $pp ? 'p.P.' : 'pauschal';
	if ( $cost > 0 ) {
		$sugg = fge_venue_sale_from_cost( $cost, $gross, $markup );
		$sale = (float) $v['sale_price'] > 0 ? (float) $v['sale_price'] : $sugg;
		$sum += $pp ? $sale * $mult : $sale;
		$net = $net_of( $cost, $gross );
		echo '<tr class="cc-calc-row" data-net="' . esc_attr( (string) round( $net, 4 ) ) . '" data-pp="' . ( $pp ? 1 : 0 ) . '">';
		echo '<td>Event und Platznutzung <span class="cc-muted">' . esc_html( $basis ) . '</span></td>';
		echo '<td>' . esc_html( $money( $cost ) . ' ' . ( $gross ? 'brutto' : 'netto' ) ) . '</td>';
		echo '<td>' . esc_html( $money( $net ) ) . '</td>';
		echo '<td><input type="text" class="cc-calc-margin" value="' . esc_attr( $margin_of( $net, $sale ) ) . '" inputmode="decimal" aria-label="Marge Grundpreis"></td>';
		echo '<td><input type="text" name="sale_base" class="cc-calc-sale" value="' . esc_attr( $field( $sale ) ) . '" inputmode="decimal" aria-label="Verkauf Grundpreis netto"></td>';
		echo '<td class="cc-muted">Platz</td>';
		echo '</tr>';
	} else {
		echo '<tr class="is-muted"><td>Event und Platznutzung</td><td colspan="5">kein Preis</td></tr>';
	}

	// Positionen: nur was der Platz anbietet, Green Fee steckt im Grundpreis.
	foreach ( (array) $v['items'] as $it ) {
		$key = (string) $it['wish_key'];
		if ( 'green_fee' === $key || ! (int) $it['available'] ) {
			continue;
		}
		$icost  = (float) $it['price'];
		$igross = (bool) (int) $it['price_gross'];
		$ipp    = 'person' === (string) $it['price_basis'];
		$ibasis = $ipp ? 'p.P.' : 'pauschal';
		if ( $icost <= 0 ) {
			echo '<tr class="is-muted"><td>' . esc_html( (string) $it['label'] ) . '</td><td colspan="5">kein Preis</td></tr>';
			continue;
		}
		$isugg = fge_venue_sale_from_cost( $icost, $igross, $markup );
		$isale = (float) ( $it['sale_price'] ?? 0 ) > 0 ? (float) $it['sale_price'] : $isugg;
		$org   = 'extern' === (string) ( $it['organizer'] ?? 'platz' ) ? 'extern' : 'platz';
		$sum  += $ipp ? $isale * $mult : $isale;
		$inet = $net_of( $icost, $igross );
		echo '<tr class="cc-calc-row" data-net="' . esc_attr( (string) round( $inet, 4 ) ) . '" data-pp="' . ( $ipp ? 1 : 0 ) . '">';
		echo '<td>' . esc_html( (string) $it['label'] ) . ' <span class="cc-muted">' . esc_html( $ibasis ) . '</span></td>';
		echo '<td>' . esc_html( $money( $icost ) . ' ' . ( $igross ? 'brutto' : 'netto' ) ) . '</td>';
		echo '<td>' . esc_html( $money( $inet ) ) . '</td>';
		echo '<td><input type="text" class="cc-calc-margin" value="' . esc_attr( $margin_of( $inet, $isale ) ) . '" inputmode="decimal" aria-label="Marge ' . esc_attr( (string) $it['label'] ) . '"></td>';
		echo '<td><input type="text" name="sale_item[' . esc_attr( $key ) . ']" class="cc-calc-sale" value="' . esc_attr( $field( $isale ) ) . '" inputmode="decimal" aria-label="Verkauf netto ' . esc_attr( (string) $it['label'] ) . '"></td>';
		echo '<td><select name="organizer[' . esc_attr( $key ) . ']" aria-label="Organisation ' . esc_attr( (string) $it['label'] ) . '">';
		echo '<option value="platz"' . selected( 'platz', $org, false ) . '>Platz</option>';
		echo '<option value="extern"' . selected( 'extern', $org, false ) . '>extern</option>';
		echo '</select></td>';
		echo '</tr>';
	}
	echo '</tbody></table>';

	echo '<p class="cc-calc-sum" data-pax="' . (int) $pax . '">Angebotssumme netto ca. <strong class="cc-calc-total">' . esc_html( $whole( $sum ) ) . '</strong>'
		. ( $pax > 0 ? ' bei ' . (int) $pax . ' Teilnehmern' : ' <span class="cc-muted">(ohne Teilnehmerzahl, p.P.-Zeilen einfach gezählt)</span>' )
		. '</p>';
	echo '<p class="cc-muted">Aufschlag, Marge und Verkaufspreis hängen zusammen: ändere eines, die anderen ziehen nach. Verkaufspreise auf volle Euro.</p>';
	echo '<p><button type="submit" class="cc-btn cc-btn--primary">Kalkulation speichern</button></p>';
	echo '</form></details>';

	static $script_done = false;
	if ( ! $script_done ) {
		$script_done = true;
		?>
		<script>
		(function(){
			function num(s){ s = String(s || '').replace(/[\s€%]/g, ''); if (s.indexOf(',') !== -1) { s = s.replace(/\./g, '').replace(',', '.'); } var f = parseFloat(s); return isNaN(f) ? 0 : f; }
			function pct(n){ return (Math.round(n * 10) / 10).toLocaleString('de-DE', { maximumFractionDigits: 1 }); }
			function euro(n){ return Math.round(n).toLocaleString('de-DE') + ' €'; }
			function saleFrom(net, m){ return Math.ceil(net * (1 + m / 100) - 0.00001); }
			document.querySelectorAll('.cc-calc form').forEach(function(form){
				var rows = form.querySelectorAll('.cc-calc-row'), markup = form.querySelector('.cc-calc-markup'),
				    total = form.querySelector('.cc-calc-total'), pax = parseInt((form.querySelector('.cc-calc-sum') || {}).dataset ? form.querySelector('.cc-calc-sum').dataset.pax : '0', 10) || 0;
				function sum(){
					var s = 0;
					rows.forEach(function(r){ var sale = num(r.querySelector('.cc-calc-sale').value); s += (r.dataset.pp === '1' && pax > 0) ? sale * pax : sale; });
					if (total) { total.textContent = euro(s); }
				}
				rows.forEach(function(r){
					var net = parseFloat(r.dataset.net) || 0, mi = r.querySelector('.cc-calc-margin'), si = r.querySelector('.cc-calc-sale');
					mi.addEventListener('input', function(){ if (net > 0) { si.value = saleFrom(net, num(mi.value)); sum(); } });
					si.addEventListener('input', function(){ if (net > 0 && num(si.value) > 0) { mi.value = pct((num(si.value) / net - 1) * 100); sum(); } });
				});
				if (markup) {
					markup.addEventListener('input', function(){
						var m = num(markup.value);
						rows.forEach(function(r){ var net = parseFloat(r.dataset.net) || 0; if (net > 0) { r.querySelector('.cc-calc-margin').value = pct(m); r.querySelector('.cc-calc-sale').value = saleFrom(net, m); } });
						sum();
					});
				}
				sum();
			});
		})();
		</script>
		<?php
	}
}

/** Antwort des Platzes festhalten, mit Preis je Position. */
function fge_cc_venue_reply_form( int $req, array $v ): void {
	$id  = (int) $v['id'];
	$pid = (int) $v['partner_id'];

	$answered = in_array( (string) $v['status'], [ 'zugesagt', 'gewaehlt', 'abgesagt' ], true );
	echo '<details class="cc-details cc-venue-reply"><summary>' . ( $answered ? 'Antwort bearbeiten (Termine, Preise, Positionen)' : 'Antwort festhalten' ) . '</summary>';
	echo '<form class="cc-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="fge_cc_venue_reply">';
	echo '<input type="hidden" name="request_id" value="' . (int) $req . '">';
	echo '<input type="hidden" name="venue_id" value="' . $id . '">';
	wp_nonce_field( 'fge_cc_venue_reply_' . $req );

	echo '<p class="cc-radios">';
	$is_no = 'abgesagt' === (string) $v['status'];
	echo '<label class="cc-inline"><input type="radio" name="answer" value="zusagt"' . ( $is_no ? '' : ' checked' ) . '> Zusage</label>';
	echo '<label class="cc-inline"><input type="radio" name="answer" value="absagt"' . ( $is_no ? ' checked' : '' ) . '> Absage</label>';
	echo '</p>';

	// Je Wunschtermin: geht, geht nicht, offen. Der Alternativvorschlag des
	// Platzes landet als Notiz beim ersten Termin-Index.
	$labels = function_exists( 'fge_request_wish_date_labels' ) ? fge_request_wish_date_labels( $req ) : [];
	$dates  = function_exists( 'fge_venue_dates_get' ) ? fge_venue_dates_get( $id ) : [];
	if ( $labels ) {
		$first = (int) min( array_keys( $labels ) );
		echo '<p class="cc-kicker">Wunschtermine</p><div class="cc-dategrid">';
		foreach ( $labels as $idx => $label ) {
			$avail = $dates[ $idx ]['available'] ?? null;
			$name  = 'date_avail[' . (int) $idx . ']';
			echo '<div class="cc-daterow">';
			echo '<span class="cc-daterow-label">' . (int) $idx . '. ' . esc_html( (string) $label ) . '</span>';
			echo '<span class="cc-daterow-opts">';
			echo '<label class="cc-inline"><input type="radio" name="' . esc_attr( $name ) . '" value="1"' . checked( 1, $avail, false ) . '> geht</label>';
			echo '<label class="cc-inline"><input type="radio" name="' . esc_attr( $name ) . '" value="0"' . checked( 0, $avail, false ) . '> geht nicht</label>';
			echo '<label class="cc-inline"><input type="radio" name="' . esc_attr( $name ) . '" value=""' . ( null === $avail ? ' checked' : '' ) . '> offen</label>';
			echo '</span></div>';
		}
		echo '</div>';
		echo '<label class="cc-field cc-field--wide"><span>Alternativvorschlag</span>';
		echo '<input type="text" name="date_alt" value="' . esc_attr( (string) ( $dates[ $first ]['note'] ?? '' ) ) . '" placeholder="Falls kein Wunschtermin passt: was der Platz stattdessen anbietet"></label>';
	}

	$hint = function_exists( 'fge_venue_price_hint' ) ? fge_venue_price_hint( $pid, 'green_fee' ) : '';
	echo '<div class="cc-fields">';
	echo '<label class="cc-field"><span>Preis Event und Platznutzung</span>';
	// Gespeicherte Werte vorbelegen, damit Nachtragen nichts leert.
	$cur_price = (float) $v['price'] > 0 ? number_format( (float) $v['price'], 2, ',', '.' ) : '';
	$cur_gross = (int) $v['price_gross'] ? '1' : '0';
	$cur_basis = 'pauschal' === (string) $v['price_basis'] ? 'pauschal' : 'person';
	echo '<input type="text" name="price" value="' . esc_attr( $cur_price ) . '" placeholder="' . esc_attr( '' !== $hint ? $hint : 'z. B. 49,00' ) . '"></label>';
	echo '<label class="cc-field"><span>brutto oder netto</span><select name="price_gross"><option value="1"' . selected( $cur_gross, '1', false ) . '>brutto</option><option value="0"' . selected( $cur_gross, '0', false ) . '>netto</option></select></label>';
	echo '<label class="cc-field"><span>Basis</span><select name="price_basis"><option value="person"' . selected( $cur_basis, 'person', false ) . '>pro Person</option><option value="pauschal"' . selected( $cur_basis, 'pauschal', false ) . '>pauschal</option></select></label>';
	echo '</div>';

	$items = (array) $v['items'];
	$extra = array_values( array_filter( $items, static fn( $it ) => 'green_fee' !== (string) $it['wish_key'] ) );
	if ( $extra ) {
		echo '<p class="cc-kicker">Weitere Positionen</p><div class="cc-itemgrid">';
		foreach ( $extra as $it ) {
			$key   = (string) $it['wish_key'];
			$ihint = function_exists( 'fge_venue_price_hint' ) ? fge_venue_price_hint( $pid, $key ) : '';
			echo '<div class="cc-item">';
			$i_on    = (int) $it['available'] > 0;
			$i_price = (float) $it['price'] > 0 ? number_format( (float) $it['price'], 2, ',', '.' ) : '';
			$i_gross = (int) $it['price_gross'] ? '1' : '0';
			$i_basis = 'pauschal' === (string) $it['price_basis'] ? 'pauschal' : 'person';
			echo '<label class="cc-inline"><input type="checkbox" name="item_available[' . esc_attr( $key ) . ']" value="1"' . ( $i_on ? ' checked' : '' ) . '> ' . esc_html( (string) $it['label'] ) . '</label>';
			echo '<input type="text" name="item_price[' . esc_attr( $key ) . ']" value="' . esc_attr( $i_price ) . '" placeholder="' . esc_attr( '' !== $ihint ? $ihint : 'Preis' ) . '">';
			echo '<select name="item_gross[' . esc_attr( $key ) . ']"><option value="1"' . selected( $i_gross, '1', false ) . '>brutto</option><option value="0"' . selected( $i_gross, '0', false ) . '>netto</option></select>';
			echo '<select name="item_basis[' . esc_attr( $key ) . ']"><option value="person"' . selected( $i_basis, 'person', false ) . '>p.P.</option><option value="pauschal"' . selected( $i_basis, 'pauschal', false ) . '>pauschal</option></select>';
			echo '</div>';
		}
		echo '</div>';
		echo '<p class="cc-muted">Häkchen weg heißt: bietet dieser Platz nicht an.</p>';
	}

	echo '<label class="cc-field cc-field--wide"><span>Grund bei Absage</span>';
	echo '<input type="text" name="reason" list="cc-reasons" value="' . esc_attr( (string) $v['reason'] ) . '" placeholder="z. B. Termin belegt"></label>';
	echo '<datalist id="cc-reasons">';
	foreach ( fge_venue_reasons() as $r ) {
		echo '<option value="' . esc_attr( $r ) . '"></option>';
	}
	echo '</datalist>';

	echo '<label class="cc-field cc-field--wide"><span>Notiz aus dem Gespräch</span>';
	echo '<textarea name="note" rows="2" placeholder="Was sonst noch gesagt wurde">' . esc_textarea( (string) $v['note'] ) . '</textarea></label>';

	echo '<p><button type="submit" class="cc-btn cc-btn--primary">Antwort speichern</button></p>';
	echo '</form></details>';
}

/**
 * Hidden-Felder, damit ein GET-Formular auf der Anfrage-Seite bleibt.
 * Browser werfen den Query-Teil der action-URL weg, deshalb wandern req
 * (und ohne Permalinks fge_cc) als eigene Felder mit.
 */
function fge_cc_request_hidden_fields( int $req ): string {
	$url   = fge_cc_request_url( $req );
	$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
	parse_str( $query, $args );
	$out = '';
	foreach ( (array) $args as $k => $val ) {
		$out .= '<input type="hidden" name="' . esc_attr( (string) $k ) . '" value="' . esc_attr( (string) $val ) . '">';
	}
	return $out;
}

/** Basis-URL der Anfrage ohne Query, als action eines GET-Formulars. */
function fge_cc_request_base_url( int $req ): string {
	$url = fge_cc_request_url( $req );
	return (string) strtok( $url, '?' );
}

/** Platz suchen und aufnehmen: Name, Ort oder PLZ, kein Riesen-Select mehr. */
function fge_cc_venue_add_form( int $req, array $venues ): void {
	$have = array_map( static fn( $v ) => (int) $v['partner_id'], $venues );
	$term = isset( $_GET['vs'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		? sanitize_text_field( wp_unslash( (string) $_GET['vs'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		: (string) get_post_meta( $req, '_fge_company_city', true );
	$term = trim( $term );

	echo '<div class="cc-vsearch-wrap">';
	echo '<p class="cc-kicker">Platz suchen</p>';
	echo '<form class="cc-vsearch" method="get" action="' . esc_url( fge_cc_request_base_url( $req ) ) . '">';
	echo fge_cc_request_hidden_fields( $req ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<input type="text" name="vs" value="' . esc_attr( $term ) . '" placeholder="Platz suchen (Name, Ort, PLZ)" aria-label="Platz suchen (Name, Ort, PLZ)">';
	echo '<button type="submit" class="cc-btn">Suchen</button>';
	echo '</form>';

	if ( '' === $term ) {
		echo '<p class="cc-muted">Name, Ort oder PLZ eintippen, dann erscheinen hier die passenden Plätze.</p></div>';
		return;
	}

	$hits = function_exists( 'fge_venue_search_partners' ) ? fge_venue_search_partners( $term, $have, 25 ) : [];
	if ( ! $hits ) {
		echo '<p class="cc-muted">Kein Platz zu „' . esc_html( $term ) . '“ gefunden. Anders schreiben oder oben über die Nähe-Liste aufnehmen.</p></div>';
		return;
	}

	echo '<ul class="cc-nearby cc-nearby--search">';
	foreach ( $hits as $h ) {
		$pid  = (int) $h['id'];
		$mail = function_exists( 'fge_cc_partner_email' ) ? fge_cc_partner_email( $pid ) : '';
		$hint = function_exists( 'fge_venue_price_hint' ) ? fge_venue_price_hint( $pid, 'green_fee' ) : '';
		$is_s = 'stammdaten' === $h['status'];

		echo '<li class="cc-nearby-row">';
		echo '<div class="cc-nearby-main">';
		echo '<span class="cc-nearby-name">' . esc_html( $h['title'] ) . '</span>';
		$place = trim( $h['plz'] . ' ' . $h['city'] );
		if ( '' !== $place ) {
			echo '<span class="cc-muted">' . esc_html( $place ) . '</span>';
		}
		if ( '' !== $hint ) {
			echo '<span class="cc-muted">' . esc_html( $hint ) . '</span>';
		}
		echo '</div>';
		echo '<span class="cc-nearby-dist"></span>';
		echo '<span class="cc-nearby-pills">';
		echo fge_cc_pill( $is_s ? 'Stammdaten' : 'Partner', $is_s ? 'neutral' : 'good' ); // phpcs:ignore WordPress.Security.EscapeOutput
		if ( '' === $mail ) {
			echo fge_cc_pill( 'ohne Mail', 'warn' ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</span>';
		echo '<span class="cc-nearby-act">';
		fge_cc_button( 'fge_cc_venue_add', $req, 'Aufnehmen', [ 'fields' => [ 'partner_id' => $pid ] ] );
		echo '</span>';
		echo '</li>';
	}
	echo '</ul></div>';
}

/** Plätze und Simulatoren rund um den Kundenstandort, mit Standort-Feld. */
function fge_cc_venue_nearby_block( int $req ): void {
	$anchor = function_exists( 'fge_request_geo_anchor' ) ? fge_request_geo_anchor( $req ) : [ 'lat' => 0.0, 'lng' => 0.0, 'label' => '', 'source' => 'none', 'candidates' => [] ];
	$saved  = (string) get_post_meta( $req, '_fge_geo_anchor', true );
	$has    = 'none' !== $anchor['source'];

	echo '<div class="cc-nearby-wrap">';
	echo '<p class="cc-kicker">Plätze in der Nähe</p>';

	echo '<form class="cc-vsearch cc-vsearch--geo" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="fge_cc_geo_anchor">';
	echo '<input type="hidden" name="request_id" value="' . (int) $req . '">';
	wp_nonce_field( 'fge_cc_geo_anchor_' . $req );
	echo '<input type="text" name="geo_anchor" value="' . esc_attr( '' !== $saved ? $saved : (string) $anchor['label'] ) . '" placeholder="Kundenstandort (PLZ oder Ort)" aria-label="Kundenstandort (PLZ oder Ort)">';
	echo '<button type="submit" class="cc-btn">Standort setzen</button>';
	echo '</form>';

	if ( ! $has ) {
		echo '<p class="cc-muted">Kein Standort erkannt, bitte PLZ oder Ort eintragen.</p></div>';
		return;
	}

	$src = [ 'anchor' => 'gesetzter Kundenstandort', 'plz' => 'aus der Firmen-PLZ', 'city' => 'aus dem Firmenort' ];
	echo '<p class="cc-muted">Umkreis um ' . esc_html( (string) $anchor['label'] ) . ' (' . esc_html( $src[ $anchor['source'] ] ?? $anchor['source'] ) . ')';
	if ( $anchor['candidates'] ) {
		$labels = array_map( static fn( $c ) => (string) $c['label'], (array) $anchor['candidates'] );
		echo ' <span class="cc-warn-inline">mehrdeutig</span>: ' . esc_html( implode( ', ', array_slice( $labels, 0, 6 ) ) ) . '. Genauer per PLZ setzen.';
	}
	echo '</p>';

	$venues = function_exists( 'fge_venues_get' ) ? fge_venues_get( $req ) : [];
	$have   = array_map( static fn( $v ) => (int) $v['partner_id'], $venues );
	$lat    = (float) $anchor['lat'];
	$lng    = (float) $anchor['lng'];

	$courses = function_exists( 'fge_verzeichnis_nearby' ) ? fge_verzeichnis_nearby( $lat, $lng, 120, 5 ) : [];
	$sims    = function_exists( 'fge_simulatoren_nearby' ) ? fge_simulatoren_nearby( $lat, $lng, 3 ) : [];

	if ( ! $courses && ! $sims ) {
		echo '<p class="cc-muted">Im Umkreis von 120 km ist nichts hinterlegt.</p></div>';
		return;
	}

	echo '<ul class="cc-nearby">';
	foreach ( $courses as $c ) {
		$pid  = (int) $c->partner_id;
		$meta = (int) $c->loecher > 0 ? (int) $c->loecher . ' Löcher' : '';
		fge_cc_nearby_row( $req, (string) $c->name, (string) $c->ort, (float) $c->dist, $meta, $pid, $have, [ 'vid' => (int) $c->id ] );
	}
	foreach ( $sims as $s ) {
		$bits = array_filter( [
			'' !== (string) ( $s['bays'] ?? '' ) ? $s['bays'] . ' Bays' : '',
			(string) ( $s['system'] ?? '' ),
		] );
		$meta = 'Simulator' . ( $bits ? ', ' . implode( ', ', $bits ) : '' );
		$pid  = 0;
		if ( '' !== (string) $s['key'] && function_exists( 'fge_stammdaten_sim_partner_id' ) ) {
			$pid = (int) fge_stammdaten_sim_partner_id( (string) $s['key'] );
		}
		fge_cc_nearby_row( $req, (string) $s['name'], (string) ( $s['ort'] ?? '' ), (float) $s['dist'], $meta, $pid, $have, [ 'sim' => (string) $s['key'] ] );
	}
	echo '</ul></div>';
}

/** Eine Zeile der Nähe-Liste. $fields ist vid oder sim für den Stammdaten-Weg. */
function fge_cc_nearby_row( int $req, string $name, string $ort, float $dist, string $meta, int $pid, array $have, array $fields ): void {
	$in_pipe = $pid > 0 && in_array( $pid, $have, true );
	$stamm   = $pid > 0 && function_exists( 'fge_partner_is_stammdaten' ) && fge_partner_is_stammdaten( $pid );

	echo '<li class="cc-nearby-row' . ( $in_pipe ? ' is-inpipe' : '' ) . '">';
	echo '<div class="cc-nearby-main">';
	echo '<span class="cc-nearby-name">' . esc_html( $name ) . '</span>';
	$sub = trim( $ort . ( '' !== $meta ? ( '' !== $ort ? ', ' : '' ) . $meta : '' ) );
	if ( '' !== $sub ) {
		echo '<span class="cc-muted">' . esc_html( $sub ) . '</span>';
	}
	echo '</div>';
	echo '<span class="cc-nearby-dist">' . esc_html( number_format_i18n( round( $dist ) ) . ' km' ) . '</span>';
	echo '<span class="cc-nearby-pills">';
	if ( $pid > 0 ) {
		echo fge_cc_pill( $stamm ? 'Stammdaten' : 'Partner', $stamm ? 'neutral' : 'good' ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</span>';
	echo '<span class="cc-nearby-act">';
	if ( $in_pipe ) {
		echo '<span class="cc-muted">schon in der Pipeline</span>';
	} elseif ( $pid > 0 ) {
		fge_cc_button( 'fge_cc_venue_add', $req, 'Aufnehmen', [ 'fields' => [ 'partner_id' => $pid ] ] );
	} else {
		$can = ( isset( $fields['vid'] ) && function_exists( 'fge_stammdaten_ensure_from_verzeichnis_row' ) )
			|| ( isset( $fields['sim'] ) && '' !== (string) $fields['sim'] && function_exists( 'fge_stammdaten_ensure_from_simulator' ) );
		if ( $can ) {
			fge_cc_button( 'fge_cc_venue_add_place', $req, 'Aufnehmen', [ 'fields' => $fields ] );
		} else {
			echo '<span class="cc-muted">noch nicht anlegbar</span>';
		}
	}
	echo '</span>';
	echo '</li>';
}

/**
 * Kontakt eines Platzes ergänzen: Name, Mail, Telefon.
 * Wiederverwendbar im Cockpit ($req > 0) und im Plätze-Verzeichnis ($req = 0).
 */
function fge_cc_partner_contact_form( int $pid, int $req = 0 ): void {
	if ( $pid <= 0 ) {
		return;
	}
	$name  = (string) get_post_meta( $pid, '_fge_main_contact_name', true );
	$email = (string) get_post_meta( $pid, '_fge_main_contact_email', true );
	$phone = (string) get_post_meta( $pid, '_fge_main_contact_phone', true );

	echo '<details class="cc-details cc-contact-edit"><summary>Kontakt ergänzen</summary>';
	echo '<form class="cc-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="fge_cc_partner_contact">';
	echo '<input type="hidden" name="partner_id" value="' . (int) $pid . '">';
	echo '<input type="hidden" name="request_id" value="' . (int) $req . '">';
	wp_nonce_field( 'fge_cc_partner_contact_' . $pid );
	echo '<div class="cc-fields">';
	echo '<label class="cc-field"><span>Ansprechpartner</span><input type="text" name="contact_name" value="' . esc_attr( $name ) . '" placeholder="Name"></label>';
	echo '<label class="cc-field"><span>Mail</span><input type="email" name="contact_email" value="' . esc_attr( $email ) . '" placeholder="name@platz.de"></label>';
	echo '<label class="cc-field"><span>Telefon</span><input type="text" name="contact_phone" value="' . esc_attr( $phone ) . '" placeholder="Telefon"></label>';
	echo '</div>';
	echo '<p><button type="submit" class="cc-btn">Kontakt speichern</button></p>';
	echo '</form></details>';
}
