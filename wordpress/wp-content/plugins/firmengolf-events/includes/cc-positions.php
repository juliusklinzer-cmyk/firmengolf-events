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
		echo '<p class="cc-hint cc-hint--warn">Das Angebot ist bereits draußen. Änderungen hier wirken erst, wenn du das Angebot neu auflegst.</p>';
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
	echo '<p class="cc-muted">Der Kunde sieht nur den Verkaufspreis. Positionen mit Dienstleister-Mail lösen bei der Buchung automatisch Auftrag oder Absage aus.</p>';

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
	foreach ( [
		'fge_offer_location' => [ 'Veranstaltungsort', 'text', 'leer = Angabe des Events' ],
		'fge_offer_schedule' => [ 'Ablauf und Zeiten', 'textarea', 'z. B. 12:00 Uhr Treffen, 12:30 Uhr Kurs' ],
		'fge_offer_includes' => [ 'Leistungen, eine je Zeile', 'textarea', 'z. B. Leihschläger und Bälle' ],
	] as $key => [ $label, $type, $ph ] ) {
		$val = (string) get_post_meta( $req, '_' . $key, true );
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
	echo '</form></details>';
}

/** Eine Positionszeile. Leeres Array heißt: neue, leere Zeile. */
function fge_cc_position_row( array $it, int $pax ): void {
	$margin = isset( $it['margin'] ) ? (float) $it['margin'] : ( function_exists( 'fge_xs_default_margin' ) ? fge_xs_default_margin() : 20 );
	$sale   = $it && function_exists( 'fge_xs_sale_price' ) ? fge_xs_sale_price( $it + [ 'margin' => $margin ] ) : 0.0;

	echo '<div class="cc-posrow">';
	echo '<input type="text" name="fge_xs_label[]" value="' . esc_attr( (string) ( $it['label'] ?? '' ) ) . '" placeholder="Leistung, z. B. Abendessen">';
	echo '<input type="text" name="fge_xs_cost[]" value="' . esc_attr( isset( $it['cost'] ) && (float) $it['cost'] > 0 ? number_format( (float) $it['cost'], 2, ',', '.' ) : '' ) . '" placeholder="Einkauf netto">';
	echo '<select name="fge_xs_basis[]">';
	echo '<option value="pauschal"' . selected( (string) ( $it['basis'] ?? '' ), 'pauschal', false ) . '>pauschal</option>';
	echo '<option value="person"' . selected( (string) ( $it['basis'] ?? '' ), 'person', false ) . '>p.P.</option>';
	echo '</select>';
	echo '<input type="text" name="fge_xs_margin[]" value="' . esc_attr( (string) $margin ) . '" placeholder="Marge %">';
	echo '<input type="text" name="fge_xs_pname[]" value="' . esc_attr( (string) ( $it['provider_name'] ?? '' ) ) . '" placeholder="Dienstleister">';
	echo '<input type="email" name="fge_xs_pmail[]" value="' . esc_attr( (string) ( $it['provider_email'] ?? '' ) ) . '" placeholder="Dienstleister-Mail" list="fge-cc-provider-mails">';
	echo '<input type="hidden" name="fge_xs_wish[]" value="' . esc_attr( (string) ( $it['wish'] ?? '' ) ) . '">';
	echo '<input type="hidden" name="fge_xs_partner[]" value="' . (int) ( $it['partner_id'] ?? 0 ) . '">';
	echo '<span class="cc-possale">' . ( $sale > 0
		? esc_html( number_format_i18n( $sale, 2 ) . ' € Verkauf' . ( 'person' === ( $it['basis'] ?? '' ) && $pax > 0 ? ' p.P.' : '' ) )
		: '' ) . '</span>';
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
