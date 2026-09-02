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

/**
 * Normalisierte Positionsliste einer Anfrage.
 *
 * @return array<int,array{label:string,cost:float,basis:string,margin:float,provider_name:string,provider_email:string,wish:string}>
 */
function fge_extra_services( int $req ): array {
	$raw = get_post_meta( $req, '_fge_extra_services', true );
	if ( ! is_array( $raw ) ) {
		return [];
	}
	$out = [];
	foreach ( $raw as $r ) {
		if ( ! is_array( $r ) || '' === trim( (string) ( $r['label'] ?? '' ) ) ) {
			continue;
		}
		$out[] = [
			'label'          => (string) $r['label'],
			'cost'           => max( 0.0, (float) ( $r['cost'] ?? 0 ) ),
			'basis'          => 'person' === ( $r['basis'] ?? '' ) ? 'person' : 'pauschal',
			'margin'         => max( 0.0, (float) ( $r['margin'] ?? fge_xs_default_margin() ) ),
			'provider_name'  => (string) ( $r['provider_name'] ?? '' ),
			'provider_email' => (string) ( $r['provider_email'] ?? '' ),
			// Mehr-Partner-Angebot (Plan Abschnitt 7.1): Position kann einem
			// Firmengolf-Partner (z. B. Golflehrer) zugeordnet sein, damit dessen
			// Eingangsrechnung der Buchung zuordenbar ist.
			'partner_id'     => max( 0, (int) ( $r['partner_id'] ?? 0 ) ),
			'wish'           => (string) ( $r['wish'] ?? '' ),
		];
	}
	return $out;
}

/** Verkaufspreis (netto) einer Position: Einkauf + Marge. */
function fge_xs_sale_price( array $item ): float {
	$cost = (float) ( $item['cost'] ?? 0 );
	if ( $cost <= 0 ) {
		return 0.0;
	}
	return round( $cost * ( 1 + (float) ( $item['margin'] ?? 0 ) / 100 ), 2 );
}

/**
 * Bepreiste Positionen (Verkauf > 0), Schlüssel = Original-Zeilenindex ('src').
 * Der src-Index verbindet Snapshot-Extras, Kundenauswahl und Dienstleister-Mails.
 */
function fge_xs_priced( int $req ): array {
	$out = [];
	foreach ( fge_extra_services( $req ) as $i => $item ) {
		if ( fge_xs_sale_price( $item ) > 0 ) {
			$out[ $i ] = $item;
		}
	}
	return $out;
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
	foreach ( fge_xs_priced( $req ) as $item ) {
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
	$wants_labels = [
		'wants_golf_teacher' => 'Golflehrer', 'wants_meeting_room' => 'Meetingraum',
		'wants_breakfast' => 'Frühstück', 'wants_lunch' => 'Lunch', 'wants_dinner' => 'Abendessen',
		'wants_shuttle' => 'Shuttle', 'wants_branding' => 'Branding', 'wants_tournament_mode' => 'Turniermodus',
		'wants_bad_weather_alternative' => 'Schlechtwetter Alternative',
	];
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
	<p class="description" style="margin:0 0 10px;">Verhandelte Zusatzleistungen (Shuttle, Fotograf, Kurs …) und freie Angebotszeilen. Der Kunde sieht nur den Verkaufspreis, Einkauf und Marge bleiben intern. Positionen mit Dienstleister-Mail lösen bei Annahme automatisch Auftrag bzw. Absage aus. Zeile löschen = Leistung leeren.</p>
	<table class="widefat striped" id="fge-xs-table" style="margin:0 0 8px;">
		<thead><tr>
			<th style="width:22%;">Leistung</th>
			<th style="width:9%;">Einkauf € netto</th>
			<th style="width:8%;">Basis</th>
			<th style="width:7%;">Marge %</th>
			<th style="width:10%;">Verkauf € netto</th>
			<th style="width:14%;">Firmengolf-Partner</th>
			<th style="width:14%;">Dienstleister</th>
			<th>Dienstleister E-Mail</th>
		</tr></thead>
		<tbody>
		<?php
		// Aktive Partner für die Positions-Zuordnung (Golflehrer zuerst, dann Rest).
		$xs_partners = get_posts( [
			'post_type'      => 'firmengolf_partner',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'meta_query'     => [ [ 'key' => '_fge_partner_status', 'value' => 'aktiv' ] ],
		] );
		$xs_partner_opts = [];
		foreach ( $xs_partners as $xp ) {
			$xs_partner_opts[ (int) $xp->ID ] = get_the_title( $xp ) . ' (' . ( fge_catalog_partner_types()[ fge_partner_type( (int) $xp->ID ) ] ?? 'Partner' ) . ')';
		}
		$row = static function ( array $it = [] ) use ( $xs_partner_opts ) {
			$cost   = (float) ( $it['cost'] ?? 0 );
			$margin = isset( $it['margin'] ) ? (float) $it['margin'] : fge_xs_default_margin();
			$sale   = $it ? fge_xs_sale_price( $it + [ 'margin' => $margin ] ) : 0.0;
			?>
			<tr class="fge-xs-row">
				<td><input type="text" name="fge_xs_label[]" value="<?php echo esc_attr( (string) ( $it['label'] ?? '' ) ); ?>" class="widefat" placeholder="z. B. Shuttle Hotel und Platz">
					<input type="hidden" name="fge_xs_wish[]" value="<?php echo esc_attr( (string) ( $it['wish'] ?? '' ) ); ?>"></td>
				<td><input type="text" name="fge_xs_cost[]" value="<?php echo esc_attr( $cost > 0 ? number_format( $cost, 2, ',', '.' ) : '' ); ?>" class="widefat fge-xs-cost" placeholder="450,00"></td>
				<td><select name="fge_xs_basis[]" class="widefat">
					<option value="pauschal" <?php selected( ( $it['basis'] ?? 'pauschal' ), 'pauschal' ); ?>>pauschal</option>
					<option value="person" <?php selected( ( $it['basis'] ?? '' ), 'person' ); ?>>p.P.</option>
				</select></td>
				<td><input type="text" name="fge_xs_margin[]" value="<?php echo esc_attr( number_format( $margin, $margin === floor( $margin ) ? 0 : 1, ',', '.' ) ); ?>" class="widefat fge-xs-margin"></td>
				<td><span class="fge-xs-sale" style="font-weight:600;"><?php echo $sale > 0 ? esc_html( number_format( $sale, 2, ',', '.' ) . ' €' ) : ''; ?></span></td>
				<td><select name="fge_xs_partner[]" class="widefat">
					<option value="0">Extern / keiner</option>
					<?php $cur_p = (int) ( $it['partner_id'] ?? 0 ); foreach ( $xs_partner_opts as $pid_opt => $plabel ) : ?>
						<option value="<?php echo esc_attr( (string) $pid_opt ); ?>" <?php selected( $cur_p, $pid_opt ); ?>><?php echo esc_html( $plabel ); ?></option>
					<?php endforeach; ?>
				</select></td>
				<td><input type="text" name="fge_xs_pname[]" value="<?php echo esc_attr( (string) ( $it['provider_name'] ?? '' ) ); ?>" class="widefat" placeholder="optional"></td>
				<td><input type="email" name="fge_xs_pmail[]" value="<?php echo esc_attr( (string) ( $it['provider_email'] ?? '' ) ); ?>" class="widefat" placeholder="optional"></td>
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

	<p style="margin:0 0 4px;"><strong>Eventpreis überschreiben (optional)</strong></p>
	<p class="description" style="margin:0 0 6px;">Nur wenn der Standardpreis des zugeordneten Events nicht gilt (Platzhalter-Event, telefonisch vereinbarter Preis). Leer lassen = Eventpreis wie hinterlegt.</p>
	<p style="margin:0;">
		<input type="text" name="fge_offer_base_override" id="fge-xs-override" value="<?php echo esc_attr( '' !== $override ? number_format( (float) $override, 2, ',', '.' ) : '' ); ?>" placeholder="z. B. 4.500,00" style="width:130px;"> € netto
		<select name="fge_offer_base_override_unit" id="fge-xs-override-unit" style="margin-left:8px;">
			<option value="pauschal" <?php selected( $override_unit, 'pauschal' ); ?>>pauschal</option>
			<option value="person" <?php selected( $override_unit, 'person' ); ?>>p.P.</option>
		</select>
	</p>

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
		function rowSale(tr){
			var cost = num(tr.querySelector('.fge-xs-cost').value), m = num(tr.querySelector('.fge-xs-margin').value);
			return cost > 0 ? cost * (1 + m/100) : 0;
		}
		function recalc(){
			var pos = 0, ppMissing = false;
			t.querySelectorAll('.fge-xs-row').forEach(function(tr){
				var sale = rowSale(tr);
				tr.querySelector('.fge-xs-sale').textContent = sale > 0 ? sale.toLocaleString('de-DE', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' €' : '';
				if (sale <= 0) return;
				var pp = tr.querySelector('select[name="fge_xs_basis[]"]').value === 'person';
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
			tpl.querySelectorAll('input').forEach(function(i){ i.value = i.name === 'fge_xs_margin[]' ? '<?php echo esc_js( number_format( fge_xs_default_margin(), 0, ',', '.' ) ); ?>' : ''; });
			tpl.querySelector('select').value = 'pauschal';
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

	$labels  = array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['fge_xs_label'] ) );
	$costs   = array_map( 'sanitize_text_field', wp_unslash( (array) ( $_POST['fge_xs_cost'] ?? [] ) ) );
	$basis   = array_map( 'sanitize_text_field', wp_unslash( (array) ( $_POST['fge_xs_basis'] ?? [] ) ) );
	$margins = array_map( 'sanitize_text_field', wp_unslash( (array) ( $_POST['fge_xs_margin'] ?? [] ) ) );
	$pnames  = array_map( 'sanitize_text_field', wp_unslash( (array) ( $_POST['fge_xs_pname'] ?? [] ) ) );
	$pmails  = array_map( 'sanitize_email', wp_unslash( (array) ( $_POST['fge_xs_pmail'] ?? [] ) ) );
	$partners = array_map( 'absint', wp_unslash( (array) ( $_POST['fge_xs_partner'] ?? [] ) ) );
	$wishes  = array_map( 'sanitize_text_field', wp_unslash( (array) ( $_POST['fge_xs_wish'] ?? [] ) ) );

	$items = [];
	foreach ( $labels as $i => $label ) {
		if ( '' === trim( $label ) ) {
			continue;
		}
		$xs_pid = (int) ( $partners[ $i ] ?? 0 );
		if ( $xs_pid > 0 && 'firmengolf_partner' !== get_post_type( $xs_pid ) ) {
			$xs_pid = 0;
		}
		$pname = trim( $pnames[ $i ] ?? '' );
		$pmail = (string) ( $pmails[ $i ] ?? '' );
		// Partner-Zuordnung füllt Dienstleister-Name/Mail automatisch nach, damit
		// die bestehenden Auftrags-/Absage-Mails ohne Sonderfall funktionieren.
		if ( $xs_pid > 0 ) {
			if ( '' === $pname ) {
				$pname = (string) get_post_meta( $xs_pid, '_fge_public_golfclub_name', true ) ?: get_the_title( $xs_pid );
			}
			if ( '' === $pmail ) {
				$pmail = sanitize_email( (string) get_post_meta( $xs_pid, '_fge_main_contact_email', true ) );
			}
		}
		$items[] = [
			'label'          => trim( $label ),
			'cost'           => fge_xs_parse_num( $costs[ $i ] ?? '' ),
			'basis'          => 'person' === ( $basis[ $i ] ?? '' ) ? 'person' : 'pauschal',
			'margin'         => '' === trim( $margins[ $i ] ?? '' ) ? fge_xs_default_margin() : fge_xs_parse_num( $margins[ $i ] ),
			'provider_name'  => $pname,
			'provider_email' => $pmail,
			'partner_id'     => $xs_pid,
			'wish'           => trim( $wishes[ $i ] ?? '' ),
		];
	}
	update_post_meta( $post_id, '_fge_extra_services', $items );

	$ov = fge_xs_parse_num( sanitize_text_field( wp_unslash( $_POST['fge_offer_base_override'] ?? '' ) ) );
	if ( $ov > 0 ) {
		update_post_meta( $post_id, '_fge_offer_base_override', $ov );
		update_post_meta( $post_id, '_fge_offer_base_override_unit', 'person' === sanitize_text_field( wp_unslash( $_POST['fge_offer_base_override_unit'] ?? '' ) ) ? 'person' : 'pauschal' );
	} else {
		delete_post_meta( $post_id, '_fge_offer_base_override' );
		delete_post_meta( $post_id, '_fge_offer_base_override_unit' );
	}
}
