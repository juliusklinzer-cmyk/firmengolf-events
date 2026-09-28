<?php
/**
 * Angebots-Positionen im Cockpit bepreisen.
 *
 * Die zweite und letzte Stelle, die bisher ins WordPress-Backend zwang. Die
 * Logik zum Bauen der Positionen liegt weiterhin in extra-services.php
 * (fge_xs_items_from_post), beide Masken benutzen dieselbe, damit sie nicht
 * auseinanderlaufen.
 *
 * Bewusst ohne JavaScript: statt „Zeile hinzufügen" gibt es immer zwei leere
 * Zeilen am Ende. Wer mehr braucht, speichert einmal und bekommt wieder zwei.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Wie viele leere Zeilen immer angeboten werden. */
const FGE_CC_BLANK_ROWS = 2;

add_action( 'admin_post_fge_cc_positions', static function (): void {
	$req = fge_cc_guard( 'fge_cc_positions' );

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- fge_cc_guard prüft.
	update_post_meta( $req, '_fge_extra_services', fge_xs_items_from_post( $_POST ) );
	fge_save_extra_services_rest( $req );

	fge_activity_add( $req, 'system', 'Angebots-Positionen aktualisiert' );
	fge_cc_redirect( $req, 'saved' );
} );

/**
 * Die Maske. Erscheint, solange kein Angebot draußen ist, und beim Neuauflegen.
 */
function fge_cc_positions_panel( int $req ): void {
	$items  = function_exists( 'fge_extra_services' ) ? fge_extra_services( $req ) : [];
	$sent   = '1' === (string) get_post_meta( $req, '_fge_offer_sent', true );
	$priced = ! function_exists( 'fge_offer_is_priced' ) || fge_offer_is_priced( $req );
	$pax    = (int) get_post_meta( $req, '_fge_expected_participants', true );

	echo '<details class="cc-details cc-positions"' . ( $priced ? '' : ' open' ) . '>';
	echo '<summary>Angebots-Positionen' . ( $priced ? '' : ' (noch kein Preis hinterlegt)' ) . '</summary>';

	if ( $sent ) {
		echo '<p class="cc-hint cc-hint--warn">Das Angebot ist bereits draußen. Änderungen hier wirken erst, wenn du das Angebot neu auflegst: erst speichern, dann unten „Zurückziehen und als neue Fassung senden".</p>';
	}

	echo '<form class="cc-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="fge_cc_positions">';
	echo '<input type="hidden" name="request_id" value="' . (int) $req . '">';
	wp_nonce_field( 'fge_cc_positions_' . $req );

	// ── Eventpreis und Einkauf ──
	$ov      = (string) get_post_meta( $req, '_fge_offer_base_override', true );
	$ov_unit = (string) get_post_meta( $req, '_fge_offer_base_override_unit', true );
	$pc      = (string) get_post_meta( $req, '_fge_partner_cost', true );
	$pc_b    = (string) get_post_meta( $req, '_fge_partner_cost_basis', true );
	$pc_g    = '0' !== (string) get_post_meta( $req, '_fge_partner_cost_gross', true );

	echo '<div class="cc-fields">';
	echo '<label class="cc-field"><span>Eventpreis, netto</span>';
	echo '<input type="text" name="fge_offer_base_override" value="' . esc_attr( '' !== $ov ? number_format( (float) $ov, 2, ',', '.' ) : '' ) . '" placeholder="leer = Preis des Events"></label>';
	echo '<label class="cc-field"><span>Basis</span><select name="fge_offer_base_override_unit">';
	echo '<option value="pauschal"' . selected( $ov_unit, 'pauschal', false ) . '>pauschal</option>';
	echo '<option value="person"' . selected( $ov_unit, 'person', false ) . '>pro Person</option>';
	echo '</select></label>';
	echo '<label class="cc-field"><span>Einkauf beim Platz</span>';
	echo '<input type="text" name="fge_partner_cost" value="' . esc_attr( '' !== $pc ? number_format( (float) $pc, 2, ',', '.' ) : '' ) . '" placeholder="z. B. 49,00"></label>';
	echo '<label class="cc-field"><span>brutto oder netto</span><select name="fge_partner_cost_gross">';
	echo '<option value="1"' . selected( $pc_g, true, false ) . '>brutto</option>';
	echo '<option value="0"' . selected( $pc_g, false, false ) . '>netto</option>';
	echo '</select></label>';
	echo '<label class="cc-field"><span>Einkauf-Basis</span><select name="fge_partner_cost_basis">';
	echo '<option value="person"' . selected( $pc_b, 'person', false ) . '>pro Person</option>';
	echo '<option value="pauschal"' . selected( $pc_b, 'pauschal', false ) . '>pauschal</option>';
	echo '</select></label>';
	echo '</div>';

	// ── Zusatzleistungen ──
	echo '<p class="cc-kicker">Zusatzleistungen</p>';
	echo '<p class="cc-muted">Der Kunde sieht Verkaufspreis, Beschreibung und ob der Platz oder ein externer Dienstleister organisiert. Einkauf, Marge und Dienstleister-Name bleiben intern. Externe Positionen mit Dienstleister-Mail lösen bei der Buchung automatisch Auftrag oder Absage aus. „Nach Verbrauch" heißt: kein fester Betrag, Abrechnung nach dem Event, optional mit Richtwert. „Am Platz nicht möglich" steht so im Angebot.</p>';

	$rows = $items;
	for ( $i = 0; $i < FGE_CC_BLANK_ROWS; $i++ ) {
		$rows[] = [];
	}
	echo '<div class="cc-posrows">';
	foreach ( $rows as $it ) {
		fge_cc_position_row( $it, $pax );
	}
	echo '</div>';

	// ── Angebotstext ──
	echo '<p class="cc-kicker">Text zur Hauptposition</p>';
	if ( function_exists( 'fge_offer_event_is_placeholder' ) && fge_offer_event_is_placeholder( $req ) ) {
		$miss = [];
		if ( '' === trim( (string) get_post_meta( $req, '_fge_offer_includes', true ) ) ) {
			$miss[] = 'Leistungen';
		}
		if ( '' === trim( (string) get_post_meta( $req, '_fge_offer_schedule', true ) ) ) {
			$miss[] = 'Ablauf';
		}
		if ( $miss ) {
			echo '<p class="cc-hint cc-hint--warn">Platzhalter-Event: ' . esc_html( implode( ' und ', $miss ) ) . ' bitte hier eintragen. Die generische Liste des Platzhalters kommt nicht ins Angebot, ohne Eintrag fehlt der Block beim Kunden und beim Platz.</p>';
		}
	}
	foreach ( [
		'fge_offer_location' => [ 'Veranstaltungsort', 'text', 'leer = Angabe des Events' ],
		'fge_offer_schedule' => [ 'Ablauf und Zeiten', 'textarea', 'z. B. 12:00 Uhr Treffen, 12:30 Uhr Kurs' ],
		'fge_offer_includes' => [ 'Leistungen, eine je Zeile', 'textarea', 'z. B. Leihschläger und Bälle' ],
	] as $key => [ $label, $type, $ph ] ) {
		$val = (string) get_post_meta( $req, '_' . $key, true );
		if ( 'fge_offer_schedule' === $key && function_exists( 'fge_offer_schedule_prefill' ) ) {
			$val = fge_offer_schedule_prefill( $req );
		}
		echo '<label class="cc-field cc-field--wide"><span>' . esc_html( $label ) . '</span>';
		if ( 'textarea' === $type ) {
			echo '<textarea name="' . esc_attr( $key ) . '" rows="3" placeholder="' . esc_attr( $ph ) . '">' . esc_textarea( $val ) . '</textarea>';
		} else {
			echo '<input type="text" name="' . esc_attr( $key ) . '" value="' . esc_attr( $val ) . '" placeholder="' . esc_attr( $ph ) . '">';
		}
		echo '</label>';
	}

	echo '<p><button type="submit" class="cc-btn cc-btn--primary">Positionen speichern</button>';
	echo '<span class="cc-muted"> Leere Leistungszeilen werden verworfen.</span></p>';
	echo '</form>';

	// Nachträge (Kunde will noch einen Meetingraum): Speichern allein ändert das
	// versendete Angebot nicht, erst die neue Fassung. Deshalb der Knopf direkt hier.
	if ( $sent && function_exists( 'fge_offer_relaunch_blocker' ) && '' === fge_offer_relaunch_blocker( $req ) && function_exists( 'fge_cc_button' ) ) {
		echo '<div class="cc-venue-actions">';
		fge_cc_button( 'fge_cc_offer_relaunch', $req, 'Zurückziehen und als neue Fassung senden', [
			'confirm' => 'Das laufende Angebot wird ungültig und der Kunde bekommt eine neue Fassung mit den gespeicherten Positionen. Fortfahren?',
		] );
		echo '</div>';
	}
	echo '</details>';
}

/** Eine Positionszeile. Leeres Array heißt: neue, leere Zeile. */
function fge_cc_position_row( array $it, int $pax ): void {
	$margin = isset( $it['margin'] ) ? (float) $it['margin'] : ( function_exists( 'fge_xs_default_margin' ) ? fge_xs_default_margin() : 20 );
	$sale   = $it && function_exists( 'fge_xs_sale_price' ) ? fge_xs_sale_price( $it + [ 'margin' => $margin ] ) : 0.0;
	$basis  = (string) ( $it['basis'] ?? 'pauschal' );
	$np     = 'nicht_moeglich' === (string) ( $it['status'] ?? '' );
	$bases  = function_exists( 'fge_xs_bases' ) ? fge_xs_bases() : [ 'pauschal' => 'pauschal', 'person' => 'p.P.' ];
	$orgs   = function_exists( 'fge_xs_organizers' ) ? fge_xs_organizers() : [ 'platz' => 'Golfplatz selbst', 'extern' => 'Externer Dienstleister' ];

	if ( $np ) {
		$sale_txt = 'nicht möglich';
	} elseif ( $sale > 0 ) {
		$sale_txt = number_format_i18n( $sale, 2 ) . ' € Verkauf' . ( 'person' === $basis && $pax > 0 ? ' p.P.' : '' );
	} else {
		$sale_txt = 'verbrauch' === $basis ? 'nach Verbrauch' : '';
	}

	echo '<div class="cc-posrow' . ( $np ? ' cc-posrow--np' : '' ) . '">';
	echo '<input type="text" name="fge_xs_label[]" value="' . esc_attr( (string) ( $it['label'] ?? '' ) ) . '" placeholder="Leistung, z. B. Abendessen">';
	echo '<input type="text" name="fge_xs_cost[]" value="' . esc_attr( isset( $it['cost'] ) && (float) $it['cost'] > 0 ? number_format( (float) $it['cost'], 2, ',', '.' ) : '' ) . '" placeholder="Einkauf">';
	echo '<select name="fge_xs_gross[]" title="Einkauf brutto oder netto">';
	echo '<option value="0"' . selected( empty( $it['cost_gross'] ), true, false ) . '>netto</option>';
	echo '<option value="1"' . selected( ! empty( $it['cost_gross'] ), true, false ) . '>brutto</option>';
	echo '</select>';
	echo '<select name="fge_xs_basis[]">';
	foreach ( $bases as $bk => $bl ) {
		echo '<option value="' . esc_attr( $bk ) . '"' . selected( $basis, $bk, false ) . '>' . esc_html( $bl ) . '</option>';
	}
	echo '</select>';
	echo '<input type="text" name="fge_xs_margin[]" value="' . esc_attr( (string) $margin ) . '" placeholder="Marge %">';
	echo '<select name="fge_xs_org[]" title="Wer organisiert">';
	foreach ( $orgs as $ok => $ol ) {
		echo '<option value="' . esc_attr( $ok ) . '"' . selected( (string) ( $it['organizer'] ?? 'extern' ), $ok, false ) . '>' . esc_html( $ol ) . '</option>';
	}
	echo '</select>';
	echo '<input type="text" name="fge_xs_pname[]" value="' . esc_attr( (string) ( $it['provider_name'] ?? '' ) ) . '" placeholder="Dienstleister (Platz: automatisch)">';
	echo '<input type="email" name="fge_xs_pmail[]" value="' . esc_attr( (string) ( $it['provider_email'] ?? '' ) ) . '" placeholder="Dienstleister-Mail" list="fge-cc-provider-mails">';
	echo '<span class="cc-possale">' . esc_html( $sale_txt ) . '</span>';
	echo '<input type="hidden" name="fge_xs_wish[]" value="' . esc_attr( (string) ( $it['wish'] ?? '' ) ) . '">';
	echo '<input type="hidden" name="fge_xs_id[]" value="' . (int) ( $it['id'] ?? 0 ) . '">';
	echo '<input type="hidden" name="fge_xs_partner[]" value="' . (int) ( $it['partner_id'] ?? 0 ) . '">';
	// Zweite Zeile: was der Kunde liest, Richtwert bei Verbrauch, Status.
	echo '<div class="cc-posrow__more">';
	echo '<input type="text" name="fge_xs_note[]" value="' . esc_attr( (string) ( $it['note'] ?? '' ) ) . '" placeholder="Beschreibung für den Kunden, z. B. Grillsemmeln und Getränke, Abrechnung nach Verbrauch">';
	echo '<input type="text" name="fge_xs_guide[]" value="' . esc_attr( (string) ( $it['guide'] ?? '' ) ) . '" placeholder="Richtwert bei Verbrauch, z. B. 15 bis 25 € p.P.">';
	echo '<select name="fge_xs_status[]">';
	echo '<option value="angeboten"' . selected( ! $np, true, false ) . '>steht im Angebot</option>';
	echo '<option value="nicht_moeglich"' . selected( $np, true, false ) . '>am Platz nicht möglich</option>';
	echo '</select>';
	echo '</div>';
	echo '</div>';
}

/** Vorschlagsliste der Dienstleister-Mails für die Positionszeilen. */
function fge_cc_provider_datalist(): void {
	if ( ! function_exists( 'fge_provider_emails' ) ) {
		return;
	}
	echo '<datalist id="fge-cc-provider-mails">';
	foreach ( fge_provider_emails() as $mail => $label ) {
		echo '<option value="' . esc_attr( $mail ) . '">' . esc_html( $label ) . '</option>';
	}
	echo '</datalist>';
}
