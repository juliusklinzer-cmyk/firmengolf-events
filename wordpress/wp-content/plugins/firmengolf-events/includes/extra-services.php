<?php
/**
 * Angebots-Positionen (Feinplanung): frei bepreisbare Zusatzleistungen pro Anfrage.
 *
 * Hintergrund (Julius, 2026-07-09): Wünscht ein Kunde z. B. Shuttle oder Fotograf,
 * verhandelt Firmengolf mit einem Drittdienstleister einen Einkaufspreis. Der wird
 * hier samt Dienstleister-Kontakt und Marge erfasst; ins Angebot wandert NUR der
 * Verkaufspreis (Einkauf + Marge) — Einkauf und Marge sieht ausschließlich der Admin
 * und sie kommen nie in den Angebots-Snapshot. Positionen ohne Dienstleister sind
 * freie Angebotszeilen (Platzhalter-Events, telefonisch vereinbarte Individual-Deals).
 *
 * Der Kunde kann jede Position auf der Angebotsseite einzeln abwählen. Bei Annahme
 * bekommt der Dienstleister automatisch Auftrag oder Absage (includes/emails.php).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Standard-Marge in Prozent (pro Position übersteuerbar). */
function fge_xs_default_margin(): float {
	return defined( 'FGE_MARKUP_PERCENT' ) ? (float) FGE_MARKUP_PERCENT : 20.0;
}

/** Mehrwertsteuersatz für die Brutto-nach-Netto-Rechnung beim Einkauf. */
function fge_xs_vat_percent(): float {
	return defined( 'FGE_VAT_PERCENT' ) ? (float) FGE_VAT_PERCENT : 19.0;
}

/** Wer eine Position erbringt: der gewählte Golfplatz selbst oder ein externer Dienstleister. */
function fge_xs_organizers(): array {
	return [
		'platz'  => 'Golfplatz selbst',
		'extern' => 'Externer Dienstleister',
	];
}

/** Abrechnungsbasis einer Position. „verbrauch" = kein fester Betrag, Abrechnung nach dem Event. */
function fge_xs_bases(): array {
	return [
		'pauschal'  => 'pauschal',
		'person'    => 'p.P.',
		'verbrauch' => 'nach Verbrauch',
	];
}

/**
 * Normalisierte Positionsliste einer Anfrage, Schlüssel = Zeilenindex, jede Zeile mit
 * stabiler `id` (Julius, 28.09.2026): Die id verbindet Snapshot, Kundenauswahl und
 * Dienstleister-Mails und überlebt Löschen und Umsortieren nach dem Versand. Altdaten
 * ohne id bekommen beim Lesen index+1, das entspricht ihrer bisherigen Position.
 *
 * @return array<int,array{id:int,label:string,cost:float,cost_gross:int,basis:string,margin:float,organizer:string,provider_name:string,provider_email:string,partner_id:int,wish:string,note:string,guide:string,status:string}>
 */
function fge_extra_services( int $req ): array {
	$raw = get_post_meta( $req, '_fge_extra_services', true );
	if ( ! is_array( $raw ) ) {
		return [];
	}
	$out = [];
	$idx = 0;
	foreach ( $raw as $r ) {
		if ( ! is_array( $r ) || '' === trim( (string) ( $r['label'] ?? '' ) ) ) {
			continue;
		}
		$idx++;
		$partner   = max( 0, (int) ( $r['partner_id'] ?? 0 ) );
		$organizer = (string) ( $r['organizer'] ?? '' );
		if ( ! isset( fge_xs_organizers()[ $organizer ] ) ) {
			// Altdaten: Partner-Zuordnung hieß „der Platz macht es", sonst extern.
			$organizer = $partner > 0 ? 'platz' : 'extern';
		}
		$basis = (string) ( $r['basis'] ?? '' );
		if ( ! isset( fge_xs_bases()[ $basis ] ) ) {
			$basis = 'pauschal';
		}
		$out[] = [
			'id'             => max( 0, (int) ( $r['id'] ?? 0 ) ) ?: $idx,
			'label'          => (string) $r['label'],
			'cost'           => max( 0.0, (float) ( $r['cost'] ?? 0 ) ),
			'cost_gross'     => ! empty( $r['cost_gross'] ) ? 1 : 0,
			'basis'          => $basis,
			'margin'         => max( 0.0, (float) ( $r['margin'] ?? fge_xs_default_margin() ) ),
			'organizer'      => $organizer,
			'provider_name'  => (string) ( $r['provider_name'] ?? '' ),
			'provider_email' => (string) ( $r['provider_email'] ?? '' ),
			// Mehr-Partner-Angebot (Plan Abschnitt 7.1): Position kann einem
			// Firmengolf-Partner (z. B. Golflehrer) zugeordnet sein, damit dessen
			// Eingangsrechnung der Buchung zuordenbar ist.
			'partner_id'     => $partner,
			'wish'           => (string) ( $r['wish'] ?? '' ),
			'note'           => trim( (string) ( $r['note'] ?? '' ) ),
			'guide'          => trim( (string) ( $r['guide'] ?? '' ) ),
			'status'         => 'nicht_moeglich' === ( $r['status'] ?? '' ) ? 'nicht_moeglich' : 'angeboten',
			// Verkaufspreis aus der Kalkulation je Platz (Optionen-Angebot), schlägt Einkauf plus Marge.
			'sale_override'  => max( 0.0, (float) ( $r['sale_override'] ?? 0 ) ),
		];
	}
	return $out;
}

/** Einkaufspreis netto: brutto eingegebene Preise (wie mit Plätzen verhandelt) werden umgerechnet. */
function fge_xs_cost_net( array $item ): float {
	$cost = max( 0.0, (float) ( $item['cost'] ?? 0 ) );
	if ( $cost <= 0 ) {
		return 0.0;
	}
	return ! empty( $item['cost_gross'] ) ? round( $cost / ( 1 + fge_xs_vat_percent() / 100 ), 2 ) : $cost;
}

/** Verkaufspreis (netto) einer Position: Einkauf netto + Marge. */
function fge_xs_sale_price( array $item ): float {
	if ( (float) ( $item['sale_override'] ?? 0 ) > 0 ) {
		return round( (float) $item['sale_override'], 2 );
	}
	$cost = fge_xs_cost_net( $item );
	if ( $cost <= 0 ) {
		return 0.0;
	}
	return round( $cost * ( 1 + (float) ( $item['margin'] ?? 0 ) / 100 ), 2 );
}

/** Position ohne festen Betrag, Abrechnung nach tatsächlichem Verbrauch im Nachgang. */
function fge_xs_is_consumption( array $item ): bool {
	return 'verbrauch' === (string) ( $item['basis'] ?? '' );
}

/**
 * Angebotsfähige Positionen, Schlüssel = stabile Zeilen-id ('src'): bepreiste Zeilen
 * (Verkauf > 0) und Verbrauchs-Positionen (ohne Betrag, aber im Angebot sichtbar).
 * Als „nicht möglich" markierte Zeilen bleiben draußen, sie landen als Hinweis im Angebot.
 */
function fge_xs_priced( int $req ): array {
	$out = [];
	foreach ( fge_extra_services( $req ) as $item ) {
		if ( 'nicht_moeglich' === $item['status'] ) {
			continue;
		}
		if ( fge_xs_sale_price( $item ) > 0 || fge_xs_is_consumption( $item ) ) {
			$out[ (int) $item['id'] ] = $item;
		}
	}
	return $out;
}

/** Wünsche, die der gewählte Platz nicht anbieten kann und die niemand extern gelöst hat. */
function fge_xs_not_possible( int $req ): array {
	$out = [];
	foreach ( fge_extra_services( $req ) as $item ) {
		if ( 'nicht_moeglich' === $item['status'] ) {
			$out[] = (string) $item['label'];
		}
	}
	return array_values( array_unique( $out ) );
}

/** Kundentaugliche Angabe, wer die Position erbringt. Nie der Name eines externen Dienstleisters. */
function fge_xs_organizer_label( array $item ): string {
	if ( 'platz' === (string) ( $item['organizer'] ?? '' ) ) {
		$name = trim( (string) ( $item['provider_name'] ?? '' ) );
		return '' !== $name ? 'über den Golfplatz (' . $name . ')' : 'über den Golfplatz';
	}
	return 'organisiert von Firmengolf mit einem externen Dienstleister';
}

/**
 * Kundenwünsche, die noch KEINE bepreiste Position haben (Match über das beim
 * Vorbefüllen mitgeführte wish-Feld oder identisches Label).
 *
 * @return array{platz:string[],firmengolf:string[]}
 */
function fge_xs_uncovered_wishes( int $req ): array {
	$g = function_exists( 'fge_request_wish_groups' ) ? fge_request_wish_groups( $req ) : [ 'platz' => [], 'firmengolf' => [] ];
	$covered = [];
	// Angebotene UND als nicht möglich markierte Zeilen decken einen Wunsch ab; nur
	// Wünsche ohne jede Zeile bleiben „offen" und stehen als Nachtrag im Angebot.
	foreach ( fge_extra_services( $req ) as $item ) {
		if ( 'nicht_moeglich' !== $item['status'] && fge_xs_sale_price( $item ) <= 0 && ! fge_xs_is_consumption( $item ) ) {
			continue;
		}
		foreach ( [ $item['wish'], $item['label'] ] as $k ) {
			$k = mb_strtolower( trim( (string) $k ) );
			if ( '' !== $k ) {
				$covered[ $k ] = true;
			}
		}
	}
	$filter = static function ( array $wishes ) use ( $covered ): array {
		return array_values( array_filter( $wishes, static function ( $w ) use ( $covered ) {
			return empty( $covered[ mb_strtolower( trim( (string) $w ) ) ] );
		} ) );
	};
	return [ 'platz' => $filter( $g['platz'] ), 'firmengolf' => $filter( $g['firmengolf'] ) ];
}

/** Deutsche Zahleneingabe → float (zentraler Parser aus helpers.php). */
function fge_xs_parse_num( string $s ): float {
	return max( 0.0, fge_parse_de_amount( $s ) );
}

// ── Metabox: Editor in der Anfrage ────────────────────────────────────────────

// Hook-Priorität 20: erst nach der Terminabstimmung (Schritt 1) einsortieren.
add_action( 'add_meta_boxes', static function () {
	add_meta_box( 'fge_rmb_positionen', 'Schritt 2: Angebots-Positionen (Feinplanung)', 'fge_render_rmb_positionen', 'firmengolf_request', 'normal', 'default' );
}, 20 );

function fge_render_rmb_positionen( WP_Post $post ) {
	$req   = $post->ID;
	$items = fge_extra_services( $req );
	$sent  = '1' === (string) get_post_meta( $req, '_fge_offer_sent', true );

	// Unbepreiste Wünsche als vorgeschlagene Leerzeilen anhängen, damit nichts vergessen wird.
	// Individual-Anfragen (Wizard) speichern Leistungen nur als wants_*-Häkchen, nicht als
	// Wunschliste — die kommen deshalb zusätzlich als Vorschläge rein.
	$open = fge_xs_uncovered_wishes( $req );
	$have_wish = [];
	foreach ( $items as $it ) {
		foreach ( [ $it['wish'], $it['label'] ] as $k ) {
			$have_wish[ mb_strtolower( trim( (string) $k ) ) ] = true;
		}
	}
	$wants_labels = fge_catalog_wish_labels();
	$candidates = array_merge( $open['platz'], $open['firmengolf'] );
	foreach ( $wants_labels as $key => $label ) {
		if ( '1' === (string) get_post_meta( $req, '_fge_' . $key, true ) ) {
			$candidates[] = $label;
		}
	}
	$suggest = [];
	foreach ( $candidates as $w ) {
		$k = mb_strtolower( trim( $w ) );
		if ( empty( $have_wish[ $k ] ) ) {
			$have_wish[ $k ] = true;
			$suggest[]       = $w;
		}
	}

	$override      = (string) get_post_meta( $req, '_fge_offer_base_override', true );
	$override_unit = (string) get_post_meta( $req, '_fge_offer_base_override_unit', true );

	// Eckdaten für die Live-Summe: Eventpreis + Teilnehmerzahl. Pauschalen werden
	// auf die tatsächlich angefragte Personenzahl umgelegt (wie im Angebot).
	$pax      = (int) get_post_meta( $req, '_fge_expected_participants', true );
	$event_id = (int) get_post_meta( $req, '_fge_assigned_event_id', true );
	$pricing  = ( $event_id > 0 && 'firmengolf_event' === get_post_type( $event_id ) && function_exists( 'fge_event_pricing_for_pax' ) )
		? fge_event_pricing_for_pax( $event_id, $pax )
		: [ 'gross' => 0, 'unit' => '' ];

	if ( $sent ) {
		echo '<p style="margin:0 0 10px;color:#9A6B12;"><strong>Hinweis:</strong> Das Angebot ist bereits versendet. Änderungen hier wirken sich nicht mehr auf das laufende Angebot aus.</p>';
	}
	?>
	<p class="description" style="margin:0 0 10px;">Verhandelte Zusatzleistungen (Shuttle, Fotograf, Kurs …) und freie Angebotszeilen. Der Kunde sieht Verkaufspreis, Beschreibung und ob der Platz oder ein externer Dienstleister organisiert; Einkauf, Marge und Dienstleister-Name bleiben intern. Externe Positionen mit Dienstleister-Mail lösen bei Annahme automatisch Auftrag bzw. Absage aus. „Nach Verbrauch" = kein fester Betrag, Abrechnung nach dem Event. Zeile löschen = Leistung leeren.</p>
	<table class="widefat striped" id="fge-xs-table" style="margin:0 0 8px;">
		<thead><tr>
			<th style="width:26%;">Leistung und Beschreibung</th>
			<th style="width:13%;">Einkauf €</th>
			<th style="width:9%;">Basis</th>
			<th style="width:6%;">Marge %</th>
			<th style="width:9%;">Verkauf € netto</th>
			<th style="width:12%;">Organisation</th>
			<th style="width:12%;">Dienstleister</th>
			<th>Dienstleister E-Mail</th>
		</tr></thead>
		<tbody>
		<?php
		$row = static function ( array $it = [] ) {
			$cost   = (float) ( $it['cost'] ?? 0 );
			$margin = isset( $it['margin'] ) ? (float) $it['margin'] : fge_xs_default_margin();
			$sale   = $it ? fge_xs_sale_price( $it + [ 'margin' => $margin ] ) : 0.0;
			$basis  = (string) ( $it['basis'] ?? 'pauschal' );
			$np     = 'nicht_moeglich' === (string) ( $it['status'] ?? '' );
			?>
			<tr class="fge-xs-row<?php echo $np ? ' fge-xs-row--np' : ''; ?>">
				<td><input type="text" name="fge_xs_label[]" value="<?php echo esc_attr( (string) ( $it['label'] ?? '' ) ); ?>" class="widefat" placeholder="z. B. Shuttle Hotel und Platz">
					<input type="hidden" name="fge_xs_wish[]" value="<?php echo esc_attr( (string) ( $it['wish'] ?? '' ) ); ?>">
					<input type="hidden" name="fge_xs_id[]" value="<?php echo (int) ( $it['id'] ?? 0 ); ?>">
					<input type="hidden" name="fge_xs_partner[]" value="<?php echo (int) ( $it['partner_id'] ?? 0 ); ?>">
					<textarea name="fge_xs_note[]" rows="2" class="widefat" style="margin-top:4px;" placeholder="Beschreibung für den Kunden (optional), z. B. Grillsemmeln und Getränke, Abrechnung nach Verbrauch"><?php echo esc_textarea( (string) ( $it['note'] ?? '' ) ); ?></textarea>
					<select name="fge_xs_status[]" class="widefat fge-xs-status" style="margin-top:4px;">
						<option value="angeboten" <?php selected( ! $np ); ?>>steht im Angebot</option>
						<option value="nicht_moeglich" <?php selected( $np ); ?>>am Platz nicht möglich (Kunde erfährt es)</option>
					</select></td>
				<td><input type="text" name="fge_xs_cost[]" value="<?php echo esc_attr( $cost > 0 ? number_format( $cost, 2, ',', '.' ) : '' ); ?>" class="widefat fge-xs-cost" placeholder="450,00">
					<select name="fge_xs_gross[]" class="widefat fge-xs-gross" style="margin-top:4px;">
						<option value="0" <?php selected( empty( $it['cost_gross'] ) ); ?>>netto</option>
						<option value="1" <?php selected( ! empty( $it['cost_gross'] ) ); ?>>brutto</option>
					</select></td>
				<td><select name="fge_xs_basis[]" class="widefat fge-xs-basis">
					<?php foreach ( fge_xs_bases() as $bk => $bl ) : ?>
						<option value="<?php echo esc_attr( $bk ); ?>" <?php selected( $basis, $bk ); ?>><?php echo esc_html( $bl ); ?></option>
					<?php endforeach; ?>
				</select>
					<input type="text" name="fge_xs_guide[]" value="<?php echo esc_attr( (string) ( $it['guide'] ?? '' ) ); ?>" class="widefat fge-xs-guide" style="margin-top:4px;<?php echo 'verbrauch' === $basis ? '' : 'display:none;'; ?>" placeholder="Richtwert, z. B. 15 bis 25 € p.P."></td>
				<td><input type="text" name="fge_xs_margin[]" value="<?php echo esc_attr( number_format( $margin, $margin === floor( $margin ) ? 0 : 1, ',', '.' ) ); ?>" class="widefat fge-xs-margin"></td>
				<td><span class="fge-xs-sale" style="font-weight:600;"><?php echo $sale > 0 ? esc_html( number_format( $sale, 2, ',', '.' ) . ' €' ) : ( 'verbrauch' === $basis ? 'nach Verbrauch' : '' ); ?></span></td>
				<td><select name="fge_xs_org[]" class="widefat fge-xs-org">
					<?php foreach ( fge_xs_organizers() as $ok => $ol ) : ?>
						<option value="<?php echo esc_attr( $ok ); ?>" <?php selected( (string) ( $it['organizer'] ?? 'extern' ), $ok ); ?>><?php echo esc_html( $ol ); ?></option>
					<?php endforeach; ?>
				</select></td>
				<td><input type="text" name="fge_xs_pname[]" value="<?php echo esc_attr( (string) ( $it['provider_name'] ?? '' ) ); ?>" class="widefat" placeholder="bei „Golfplatz selbst" automatisch"></td>
				<td><input type="email" name="fge_xs_pmail[]" value="<?php echo esc_attr( (string) ( $it['provider_email'] ?? '' ) ); ?>" class="widefat" placeholder="optional" list="fge-provider-mails"></td>
			</tr>
			<?php
		};
		foreach ( $items as $it ) {
			$row( $it );
		}
		foreach ( $suggest as $w ) {
			$row( [ 'label' => $w, 'wish' => $w ] );
		}
		if ( empty( $items ) && empty( $suggest ) ) {
			$row();
		}
		?>
		</tbody>
	</table>
	<p style="margin:0 0 14px;"><button type="button" class="button" id="fge-xs-add">Position hinzufügen</button></p>
	<?php
	// Dienstleister aus dem Verzeichnis zur Vervollständigung anbieten, damit
	// Mailadressen nicht jedes Mal neu getippt werden.
	if ( function_exists( 'fge_provider_emails' ) ) {
		echo '<datalist id="fge-provider-mails">';
		foreach ( fge_provider_emails() as $mail => $label ) {
			echo '<option value="' . esc_attr( $mail ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</datalist>';
	}
	?>

	<p style="margin:0 0 4px;"><strong>Eventpreis überschreiben (optional)</strong></p>
	<p class="description" style="margin:0 0 6px;">Nur wenn der Standardpreis des zugeordneten Events nicht gilt (Platzhalter-Event, telefonisch vereinbarter Preis). Leer lassen = Eventpreis wie hinterlegt.</p>
	<p style="margin:0;">
		<input type="text" name="fge_offer_base_override" id="fge-xs-override" value="<?php echo esc_attr( '' !== $override ? number_format( (float) $override, 2, ',', '.' ) : '' ); ?>" placeholder="z. B. 4.500,00" style="width:130px;"> € netto
		<select name="fge_offer_base_override_unit" id="fge-xs-override-unit" style="margin-left:8px;">
			<option value="pauschal" <?php selected( $override_unit, 'pauschal' ); ?>>pauschal</option>
			<option value="person" <?php selected( $override_unit, 'person' ); ?>>p.P.</option>
		</select>
	</p>

	<?php
	// Einkaufspreis des Platzes (Julius, 22.09.2026): Für das Green Fee gab es bisher
	// kein Feld, obwohl er telefonisch verhandelt wird. Er steht in der Auftrags-
	// bestätigung an den Platz und in der internen Margenübersicht, nie beim Kunden.
	// Brutto/Netto ist Pflicht, weil mit dem Platz brutto verhandelt wird (49 € p.P.),
	// das Angebot an den Kunden aber netto läuft.
	$pc_cost  = (string) get_post_meta( $req, '_fge_partner_cost', true );
	$pc_basis = (string) get_post_meta( $req, '_fge_partner_cost_basis', true );
	$pc_gross = '1' === (string) get_post_meta( $req, '_fge_partner_cost_gross', true );
	?>
	<p style="margin:16px 0 4px;"><strong>Vereinbart mit dem Platz (Einkauf, intern)</strong></p>
	<p class="description" style="margin:0 0 6px;">Der Preis, den der Golfplatz uns in Rechnung stellt. Steht in der Auftragsbestätigung an den Platz, damit er weiß, was er fakturieren darf. Der Kunde sieht diesen Wert nie.</p>
	<p style="margin:0;">
		<input type="text" name="fge_partner_cost" value="<?php echo esc_attr( '' !== $pc_cost ? number_format( (float) $pc_cost, 2, ',', '.' ) : '' ); ?>" placeholder="z. B. 49,00" style="width:130px;"> €
		<select name="fge_partner_cost_gross" style="margin-left:8px;">
			<option value="1" <?php selected( $pc_gross, true ); ?>>brutto</option>
			<option value="0" <?php selected( $pc_gross, false ); ?>>netto</option>
		</select>
		<select name="fge_partner_cost_basis" style="margin-left:8px;">
			<option value="person" <?php selected( $pc_basis, 'person' ); ?>>p.P.</option>
			<option value="pauschal" <?php selected( $pc_basis, 'pauschal' ); ?>>pauschal</option>
		</select>
	</p>

	<?php
	// Angebotstext für Position 1 (Julius, 17.09.2026): Ort, Ablauf und Leistungen
	// stehen im Angebot zusammen unter dem Event. Leer = Angaben des zugeordneten Events.
	$ov_loc  = (string) get_post_meta( $req, '_fge_offer_location', true );
	$ov_sch  = function_exists( 'fge_offer_schedule_prefill' ) ? fge_offer_schedule_prefill( $req ) : (string) get_post_meta( $req, '_fge_offer_schedule', true );
	$ov_inc  = (string) get_post_meta( $req, '_fge_offer_includes', true );
	$ev_loc  = $event_id > 0 ? (string) get_post_meta( $event_id, '_fge_event_location', true ) : '';
	$ev_inc  = $event_id > 0 ? get_post_meta( $event_id, '_fge_event_includes', true ) : [];
	$ev_inc  = is_array( $ev_inc ) ? $ev_inc : array_filter( preg_split( '/\r\n|\r|\n/', (string) $ev_inc ) );
	$ev_inc  = implode( "\n", array_map( 'strval', (array) $ev_inc ) );
	?>
	<p style="margin:16px 0 4px;"><strong>Angebotstext zu Position 1 (optional)</strong></p>
	<p class="description" style="margin:0 0 6px;">Steht im Angebot, in der Angebotsmail und im PDF direkt unter dem Event: Ort, Ablauf und Leistungen zusammen. Leer gelassen gelten Ort und Leistungsliste des zugeordneten Events.</p>
	<table class="form-table" style="margin:0;">
		<tr>
			<th scope="row" style="padding:6px 10px 6px 0;width:160px;"><label for="fge-offer-location">Veranstaltungsort</label></th>
			<td style="padding:6px 0;"><input type="text" id="fge-offer-location" name="fge_offer_location" value="<?php echo esc_attr( $ov_loc ); ?>" class="regular-text" placeholder="<?php echo esc_attr( '' !== $ev_loc ? 'Standard: ' . $ev_loc : 'z. B. Golfpark Weidenhof, Pinneberg' ); ?>"></td>
		</tr>
		<tr>
			<th scope="row" style="padding:6px 10px 6px 0;"><label for="fge-offer-schedule">Ablauf und Zeiten</label></th>
			<td style="padding:6px 0;"><textarea id="fge-offer-schedule" name="fge_offer_schedule" rows="3" class="large-text" placeholder="z. B. Start 12:00 Uhr, 3 Stunden Schnupperkurs, danach 6-Loch-Spiel auf dem Kurzplatz in zwei 3er-Flights"><?php echo esc_textarea( $ov_sch ); ?></textarea></td>
		</tr>
		<tr>
			<th scope="row" style="padding:6px 10px 6px 0;"><label for="fge-offer-includes">Leistungen</label></th>
			<td style="padding:6px 0;"><textarea id="fge-offer-includes" name="fge_offer_includes" rows="5" class="large-text" placeholder="<?php echo esc_attr( '' !== $ev_inc ? "Standard vom Event:\n" . $ev_inc : 'Eine Leistung je Zeile' ); ?>"><?php echo esc_textarea( $ov_inc ); ?></textarea>
			<p class="description" style="margin:4px 0 0;">Eine Leistung je Zeile. Ersetzt die Liste des Events komplett.</p></td>
		</tr>
	</table>

	<div style="margin:14px 0 0;padding:10px 12px;background:#F0F4FA;border-radius:6px;font-size:13px;line-height:1.7;" id="fge-xs-sums">
		<strong>Angebotssumme (Vorschau, netto)</strong><br>
		Eventpreis: <span id="fge-xs-sum-base"></span> · Positionen: <span id="fge-xs-sum-pos"></span> · <strong>Gesamt: <span id="fge-xs-sum-total"></span></strong>
		<span id="fge-xs-sum-hint" style="color:#6C736E;"></span>
	</div>

	<script>
	(function(){
		var t = document.getElementById('fge-xs-table');
		if (!t) return;
		var PAX = <?php echo (int) $pax; ?>,
		    EVENT_GROSS = <?php echo wp_json_encode( round( (float) ( $pricing['gross'] ?? 0 ), 2 ) ); ?>,
		    EVENT_PP = <?php echo 'pro Person' === (string) ( $pricing['unit'] ?? '' ) ? 'true' : 'false'; ?>;
		function num(s){ s = (s||'').replace(/[\s€]/g,''); if (s.indexOf(',') !== -1) { s = s.replace(/\./g,'').replace(',', '.'); } var f = parseFloat(s); return isNaN(f) || f < 0 ? 0 : f; }
		function fmt(n){ return n.toLocaleString('de-DE', {minimumFractionDigits:0, maximumFractionDigits:2}) + ' €'; }
		var VAT = <?php echo wp_json_encode( fge_xs_vat_percent() ); ?>;
		function rowSale(tr){
			var cost = num(tr.querySelector('.fge-xs-cost').value), m = num(tr.querySelector('.fge-xs-margin').value);
			if (tr.querySelector('.fge-xs-gross').value === '1') { cost = cost / (1 + VAT/100); }
			return cost > 0 ? cost * (1 + m/100) : 0;
		}
		function recalc(){
			var pos = 0, ppMissing = false;
			t.querySelectorAll('.fge-xs-row').forEach(function(tr){
				var basis = tr.querySelector('.fge-xs-basis').value, np = tr.querySelector('.fge-xs-status').value === 'nicht_moeglich';
				tr.querySelector('.fge-xs-guide').style.display = basis === 'verbrauch' ? '' : 'none';
				tr.classList.toggle('fge-xs-row--np', np);
				var sale = np ? 0 : rowSale(tr);
				tr.querySelector('.fge-xs-sale').textContent = np ? 'nicht möglich' : (sale > 0 ? sale.toLocaleString('de-DE', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' €' : (basis === 'verbrauch' ? 'nach Verbrauch' : ''));
				if (sale <= 0 || basis === 'verbrauch') return;
				var pp = basis === 'person';
				if (pp && PAX <= 0) { ppMissing = true; return; }
				pos += pp ? sale * PAX : sale;
			});
			var ovEl = document.getElementById('fge-xs-override'),
			    ov = ovEl ? num(ovEl.value) : 0,
			    ovPP = (document.getElementById('fge-xs-override-unit')||{}).value === 'person',
			    base;
			if (ov > 0) { base = ovPP ? (PAX > 0 ? ov * PAX : 0) : ov; if (ovPP && PAX <= 0) ppMissing = true; }
			else { base = EVENT_PP ? (PAX > 0 ? EVENT_GROSS * PAX : 0) : EVENT_GROSS; if (EVENT_PP && EVENT_GROSS > 0 && PAX <= 0) ppMissing = true; }
			document.getElementById('fge-xs-sum-base').textContent = fmt(base);
			document.getElementById('fge-xs-sum-pos').textContent = fmt(pos);
			document.getElementById('fge-xs-sum-total').textContent = fmt(base + pos);
			document.getElementById('fge-xs-sum-hint').textContent =
				(PAX > 0 ? ' (p.P.-Anteile mit ' + PAX + ' Teilnehmern gerechnet)' : '') +
				(ppMissing ? ' Achtung: p.P.-Preise ohne Teilnehmerzahl fehlen in der Summe.' : '');
		}
		document.getElementById('fge_rmb_positionen').addEventListener('input', recalc);
		document.getElementById('fge_rmb_positionen').addEventListener('change', recalc);
		var add = document.getElementById('fge-xs-add');
		if (add) add.addEventListener('click', function(){
			var rows = t.querySelectorAll('.fge-xs-row'), tpl = rows[rows.length-1].cloneNode(true);
			tpl.querySelectorAll('input').forEach(function(i){ i.value = i.name === 'fge_xs_margin[]' ? '<?php echo esc_js( number_format( fge_xs_default_margin(), 0, ',', '.' ) ); ?>' : (i.name === 'fge_xs_id[]' || i.name === 'fge_xs_partner[]' ? '0' : ''); });
			tpl.querySelectorAll('textarea').forEach(function(i){ i.value = ''; });
			tpl.querySelector('.fge-xs-basis').value = 'pauschal';
			tpl.querySelector('.fge-xs-gross').value = '0';
			tpl.querySelector('.fge-xs-status').value = 'angeboten';
			tpl.querySelector('.fge-xs-org').value = 'extern';
			tpl.classList.remove('fge-xs-row--np');
			tpl.querySelector('.fge-xs-sale').textContent = '';
			t.querySelector('tbody').appendChild(tpl);
		});
		recalc();
	})();
	</script>
	<?php
}

// ── Speichern (gleiche Nonce wie die übrigen Anfrage-Metaboxen) ───────────────

add_action( 'save_post', 'fge_save_extra_services' );
function fge_save_extra_services( int $post_id ) {
	if ( ! isset( $_POST['fge_request_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fge_request_nonce'] ) ), 'fge_request_fields' )
		|| ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
		|| wp_is_post_revision( $post_id )
		|| ! current_user_can( 'edit_post', $post_id )
		|| get_post_type( $post_id ) !== 'firmengolf_request'
		|| ! isset( $_POST['fge_xs_label'] ) ) {
		return;
	}

	$items = fge_xs_items_from_post( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	update_post_meta( $post_id, '_fge_extra_services', $items );
	fge_save_extra_services_rest( $post_id );
}

/**
 * Positionen aus abgeschickten Formularfeldern bauen.
 *
 * Herausgelöst, damit die WordPress-Maske und das Control Center dieselbe
 * Logik benutzen und nicht auseinanderlaufen. Die Rechteprüfung bleibt beim
 * Aufrufer, diese Funktion formt nur.
 */
function fge_xs_items_from_post( array $src ): array {
	$labels  = array_map( 'sanitize_text_field', wp_unslash( (array) ( $src['fge_xs_label'] ?? [] ) ) );
	$costs   = array_map( 'sanitize_text_field', wp_unslash( (array) ( $src['fge_xs_cost'] ?? [] ) ) );
	$basis   = array_map( 'sanitize_text_field', wp_unslash( (array) ( $src['fge_xs_basis'] ?? [] ) ) );
	$margins = array_map( 'sanitize_text_field', wp_unslash( (array) ( $src['fge_xs_margin'] ?? [] ) ) );
	$pnames  = array_map( 'sanitize_text_field', wp_unslash( (array) ( $src['fge_xs_pname'] ?? [] ) ) );
	$pmails  = array_map( 'sanitize_email', wp_unslash( (array) ( $src['fge_xs_pmail'] ?? [] ) ) );
	$partners = array_map( 'absint', wp_unslash( (array) ( $src['fge_xs_partner'] ?? [] ) ) );
	$wishes  = array_map( 'sanitize_text_field', wp_unslash( (array) ( $src['fge_xs_wish'] ?? [] ) ) );
	$ids     = array_map( 'absint', wp_unslash( (array) ( $src['fge_xs_id'] ?? [] ) ) );
	$gross   = array_map( 'absint', wp_unslash( (array) ( $src['fge_xs_gross'] ?? [] ) ) );
	$orgs    = array_map( 'sanitize_key', wp_unslash( (array) ( $src['fge_xs_org'] ?? [] ) ) );
	$notes   = array_map( 'sanitize_textarea_field', wp_unslash( (array) ( $src['fge_xs_note'] ?? [] ) ) );
	$guides  = array_map( 'sanitize_text_field', wp_unslash( (array) ( $src['fge_xs_guide'] ?? [] ) ) );
	$status  = array_map( 'sanitize_key', wp_unslash( (array) ( $src['fge_xs_status'] ?? [] ) ) );

	// Stabile Zeilen-ids: vorhandene bleiben, neue Zeilen bekommen die nächste freie.
	$next_id = max( array_merge( [ 0 ], array_map( 'intval', $ids ) ) ) + 1;
	// Der gewählte Platz erbringt „Golfplatz selbst"-Positionen; seine Kontaktdaten
	// werden nachgefüllt, damit Buchungsbestätigung und Margenübersicht ihn nennen.
	$req_id      = (int) ( $src['request_id'] ?? $src['post_ID'] ?? 0 );
	$venue_id    = $req_id > 0 ? (int) get_post_meta( $req_id, '_fge_assigned_partner_id', true ) : 0;

	$items = [];
	foreach ( $labels as $i => $label ) {
		if ( '' === trim( $label ) ) {
			continue;
		}
		$organizer = isset( fge_xs_organizers()[ $orgs[ $i ] ?? '' ] ) ? (string) $orgs[ $i ] : 'extern';
		$xs_pid    = (int) ( $partners[ $i ] ?? 0 );
		if ( 'platz' === $organizer && $xs_pid <= 0 ) {
			$xs_pid = $venue_id;
		}
		if ( $xs_pid > 0 && 'firmengolf_partner' !== get_post_type( $xs_pid ) ) {
			$xs_pid = 0;
		}
		if ( 'extern' === $organizer ) {
			$xs_pid = 0;
		}
		$pname = trim( $pnames[ $i ] ?? '' );
		$pmail = (string) ( $pmails[ $i ] ?? '' );
		if ( 'platz' === $organizer && $xs_pid > 0 ) {
			if ( '' === $pname ) {
				$pname = (string) get_post_meta( $xs_pid, '_fge_public_golfclub_name', true ) ?: get_the_title( $xs_pid );
			}
			if ( '' === $pmail ) {
				$pmail = sanitize_email( (string) get_post_meta( $xs_pid, '_fge_main_contact_email', true ) );
			}
		}
		$id = (int) ( $ids[ $i ] ?? 0 );
		if ( $id <= 0 ) {
			$id = $next_id++;
		}
		$b    = (string) ( $basis[ $i ] ?? '' );
		$cost = fge_xs_parse_num( $costs[ $i ] ?? '' );
		$st   = 'nicht_moeglich' === ( $status[ $i ] ?? '' ) ? 'nicht_moeglich' : 'angeboten';
		$items[] = [
			'id'             => $id,
			'label'          => trim( $label ),
			'cost'           => $cost,
			'cost_gross'     => ! empty( $gross[ $i ] ) ? 1 : 0,
			'basis'          => isset( fge_xs_bases()[ $b ] ) ? $b : 'pauschal',
			'margin'         => '' === trim( $margins[ $i ] ?? '' ) ? fge_xs_default_margin() : fge_xs_parse_num( $margins[ $i ] ),
			'organizer'      => $organizer,
			'provider_name'  => $pname,
			'provider_email' => $pmail,
			'partner_id'     => $xs_pid,
			'wish'           => trim( $wishes[ $i ] ?? '' ),
			'note'           => trim( $notes[ $i ] ?? '' ),
			'guide'          => trim( $guides[ $i ] ?? '' ),
			'status'         => $st,
		];
	}
	return $items;
}

/** Eventpreis, Angebotstext und Einkauf beim Platz, aus denselben Feldnamen. */
function fge_save_extra_services_rest( int $post_id ): void {

	$ov = fge_xs_parse_num( sanitize_text_field( wp_unslash( $_POST['fge_offer_base_override'] ?? '' ) ) );
	if ( $ov > 0 ) {
		update_post_meta( $post_id, '_fge_offer_base_override', $ov );
		update_post_meta( $post_id, '_fge_offer_base_override_unit', 'person' === sanitize_text_field( wp_unslash( $_POST['fge_offer_base_override_unit'] ?? '' ) ) ? 'person' : 'pauschal' );
	} else {
		delete_post_meta( $post_id, '_fge_offer_base_override' );
		delete_post_meta( $post_id, '_fge_offer_base_override_unit' );
	}

	// Einkaufspreis des Platzes (intern, nie beim Kunden).
	$pc = fge_xs_parse_num( sanitize_text_field( wp_unslash( $_POST['fge_partner_cost'] ?? '' ) ) );
	if ( $pc > 0 ) {
		update_post_meta( $post_id, '_fge_partner_cost', $pc );
		update_post_meta( $post_id, '_fge_partner_cost_basis', 'pauschal' === sanitize_text_field( wp_unslash( $_POST['fge_partner_cost_basis'] ?? '' ) ) ? 'pauschal' : 'person' );
		update_post_meta( $post_id, '_fge_partner_cost_gross', '0' === (string) ( $_POST['fge_partner_cost_gross'] ?? '1' ) ? 0 : 1 );
	} else {
		delete_post_meta( $post_id, '_fge_partner_cost' );
		delete_post_meta( $post_id, '_fge_partner_cost_basis' );
		delete_post_meta( $post_id, '_fge_partner_cost_gross' );
	}

	// Angebotstext zu Position 1 (Ort, Ablauf, Leistungen), leer = Event-Angaben.
	$ov_loc = sanitize_text_field( wp_unslash( $_POST['fge_offer_location'] ?? '' ) );
	$ov_sch = sanitize_textarea_field( wp_unslash( $_POST['fge_offer_schedule'] ?? '' ) );
	$ov_inc = sanitize_textarea_field( wp_unslash( $_POST['fge_offer_includes'] ?? '' ) );
	foreach ( [ '_fge_offer_location' => $ov_loc, '_fge_offer_schedule' => $ov_sch, '_fge_offer_includes' => $ov_inc ] as $k => $v ) {
		if ( '' !== trim( $v ) ) {
			update_post_meta( $post_id, $k, trim( $v ) );
		} else {
			delete_post_meta( $post_id, $k );
		}
	}
}
