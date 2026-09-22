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

	echo '<section class="cc-card"><h2>Angefragte Plätze</h2>';

	if ( ! $venues ) {
		echo '<p class="cc-muted">Noch kein Platz in der Liste. Nimm die Plätze auf, die für diese Anfrage in Frage kommen, dann stehen ihre Preise nebeneinander.</p>';
	}
	foreach ( $venues as $v ) {
		fge_cc_venue_row( $req, $v );
	}

	fge_cc_venue_add_form( $req, $venues );

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

	echo '<article class="cc-venue is-' . esc_attr( $status ) . '">';
	echo '<div class="cc-venue-head">';
	echo '<span class="cc-venue-name">' . esc_html( (string) $v['title'] ) . '</span>';
	echo fge_cc_pill( fge_venue_statuses()[ $status ] ?? $status, $tones[ $status ] ?? 'neutral' ); // phpcs:ignore WordPress.Security.EscapeOutput
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
	if ( '' !== trim( (string) $v['reason'] ) ) {
		echo '<p class="cc-venue-reason">' . esc_html( (string) $v['reason'] ) . '</p>';
	}
	if ( '' !== trim( (string) $v['note'] ) ) {
		echo '<p class="cc-muted">' . esc_html( (string) $v['note'] ) . '</p>';
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
				echo '<span class="cc-muted">bietet der Platz nicht an</span>';
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
	if ( 'zugesagt' === $status ) {
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

	if ( 'angefragt' === $status ) {
		fge_cc_venue_reply_form( $req, $v );
	}
	echo '</article>';
}

/** Antwort des Platzes festhalten, mit Preis je Position. */
function fge_cc_venue_reply_form( int $req, array $v ): void {
	$id  = (int) $v['id'];
	$pid = (int) $v['partner_id'];

	echo '<details class="cc-details cc-venue-reply"><summary>Antwort festhalten</summary>';
	echo '<form class="cc-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="fge_cc_venue_reply">';
	echo '<input type="hidden" name="request_id" value="' . (int) $req . '">';
	echo '<input type="hidden" name="venue_id" value="' . $id . '">';
	wp_nonce_field( 'fge_cc_venue_reply_' . $req );

	echo '<p class="cc-radios">';
	echo '<label class="cc-inline"><input type="radio" name="answer" value="zusagt" checked> Zusage</label>';
	echo '<label class="cc-inline"><input type="radio" name="answer" value="absagt"> Absage</label>';
	echo '</p>';

	$hint = function_exists( 'fge_venue_price_hint' ) ? fge_venue_price_hint( $pid, 'green_fee' ) : '';
	echo '<div class="cc-fields">';
	echo '<label class="cc-field"><span>Preis Event und Platznutzung</span>';
	echo '<input type="text" name="price" value="" placeholder="' . esc_attr( '' !== $hint ? $hint : 'z. B. 49,00' ) . '"></label>';
	echo '<label class="cc-field"><span>brutto oder netto</span><select name="price_gross"><option value="1">brutto</option><option value="0">netto</option></select></label>';
	echo '<label class="cc-field"><span>Basis</span><select name="price_basis"><option value="person">pro Person</option><option value="pauschal">pauschal</option></select></label>';
	echo '</div>';

	$items = (array) $v['items'];
	$extra = array_values( array_filter( $items, static fn( $it ) => 'green_fee' !== (string) $it['wish_key'] ) );
	if ( $extra ) {
		echo '<p class="cc-kicker">Weitere Positionen</p><div class="cc-itemgrid">';
		foreach ( $extra as $it ) {
			$key   = (string) $it['wish_key'];
			$ihint = function_exists( 'fge_venue_price_hint' ) ? fge_venue_price_hint( $pid, $key ) : '';
			echo '<div class="cc-item">';
			echo '<label class="cc-inline"><input type="checkbox" name="item_available[' . esc_attr( $key ) . ']" value="1" checked> ' . esc_html( (string) $it['label'] ) . '</label>';
			echo '<input type="text" name="item_price[' . esc_attr( $key ) . ']" value="" placeholder="' . esc_attr( '' !== $ihint ? $ihint : 'Preis' ) . '">';
			echo '<select name="item_gross[' . esc_attr( $key ) . ']"><option value="1">brutto</option><option value="0">netto</option></select>';
			echo '<select name="item_basis[' . esc_attr( $key ) . ']"><option value="person">p.P.</option><option value="pauschal">pauschal</option></select>';
			echo '</div>';
		}
		echo '</div>';
		echo '<p class="cc-muted">Häkchen weg heißt: bietet dieser Platz nicht an.</p>';
	}

	echo '<label class="cc-field cc-field--wide"><span>Grund bei Absage</span>';
	echo '<input type="text" name="reason" list="cc-reasons" placeholder="z. B. Termin belegt"></label>';
	echo '<datalist id="cc-reasons">';
	foreach ( fge_venue_reasons() as $r ) {
		echo '<option value="' . esc_attr( $r ) . '"></option>';
	}
	echo '</datalist>';

	echo '<label class="cc-field cc-field--wide"><span>Notiz aus dem Gespräch</span>';
	echo '<textarea name="note" rows="2" placeholder="Was sonst noch gesagt wurde"></textarea></label>';

	echo '<p><button type="submit" class="cc-btn cc-btn--primary">Antwort speichern</button></p>';
	echo '</form></details>';
}

/** Platz aus dem Verzeichnis aufnehmen, mit dem zuletzt genannten Preis als Hinweis. */
function fge_cc_venue_add_form( int $req, array $venues ): void {
	$have = array_map( static fn( $v ) => (int) $v['partner_id'], $venues );

	$partners = get_posts( [
		'post_type'      => 'firmengolf_partner',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'fields'         => 'ids',
	] );

	$options = '';
	foreach ( $partners as $pid ) {
		if ( in_array( (int) $pid, $have, true ) ) {
			continue;
		}
		$city    = (string) get_post_meta( $pid, '_fge_city', true );
		$hint    = function_exists( 'fge_venue_price_hint' ) ? fge_venue_price_hint( (int) $pid, 'green_fee' ) : '';
		$label   = get_the_title( $pid ) . ( '' !== $city ? ', ' . $city : '' ) . ( '' !== $hint ? ' · ' . $hint : '' );
		$options .= '<option value="' . (int) $pid . '">' . esc_html( $label ) . '</option>';
	}

	if ( '' === $options ) {
		echo '<p class="cc-muted">Alle Plätze stehen schon in der Liste.</p>';
		return;
	}

	echo '<form class="cc-form cc-venue-add" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="fge_cc_venue_add">';
	echo '<input type="hidden" name="request_id" value="' . (int) $req . '">';
	wp_nonce_field( 'fge_cc_venue_add_' . $req );
	echo '<select name="partner_id" aria-label="Platz auswählen">' . $options . '</select>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<button type="submit" class="cc-btn">Platz aufnehmen</button>';
	echo '</form>';
}
