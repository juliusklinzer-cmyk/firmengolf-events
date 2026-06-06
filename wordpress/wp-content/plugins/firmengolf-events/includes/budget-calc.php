<?php
/**
 * Budget-Rechner — Preis-Konfiguration & Backend-Settings.
 *
 * Der Rechner auf der Individuelle-Events-Seite läuft client-seitig, bezieht
 * seine Preise aber aus einer WP-Option (fge_budget_calc), die hier im Backend
 * gepflegt wird. So bleibt der Rechner ohne Code-Änderung immer aktuell.
 *
 * Editierbar: Event-Typen (€/Person), Services (€/Person oder Pauschale),
 * Preisniveau-Faktoren (€/€€/€€€), Platz/Programm-Aufteilung, Rundung.
 * Fix (aus Defaults): IDs, Kategorie-Zuordnung, Icons, Kategorie-Farben,
 * sowie das Mapping in den Anfrage-Wizard.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FGE_BC_OPTION = 'fge_budget_calc';

/** Werkseinstellungen — Quelle der Wahrheit für Struktur + Start-Preise. */
function fge_bc_defaults(): array {
	return [
		'types' => [
			[ 'id' => 'teamevent',   'label' => 'Teamevent',           'pp' => 150, 'wiz' => 'Teamevent' ],
			[ 'id' => 'turnier',     'label' => 'Firmenturnier',       'pp' => 290, 'wiz' => 'Firmenturnier' ],
			[ 'id' => 'kundenevent', 'label' => 'Kundenevent',         'pp' => 215, 'wiz' => 'Kundenevent' ],
			[ 'id' => 'offsite',     'label' => 'Offsite & Incentive', 'pp' => 430, 'wiz' => 'Incentive-Reise' ],
			[ 'id' => 'sommerfest',  'label' => 'Sommerfest & Dinner', 'pp' => 185, 'wiz' => 'Sommerfest' ],
			[ 'id' => 'gesundheit',  'label' => 'Gesundheitstag',      'pp' => 140, 'wiz' => 'Gesundheitstag' ],
		],
		'services' => [
			[ 'id' => 'catering',      'label' => 'Catering',            'cat' => 'catering',      'icon' => 'catering', 'pp' => 62,  'flat' => 0,    'wiz' => 'Lunch' ],
			[ 'id' => 'coaching',      'label' => 'Coaching & Programm', 'cat' => 'programm',      'icon' => 'coaching', 'pp' => 48,  'flat' => 0,    'wiz' => 'Golflehrer / Coaching' ],
			[ 'id' => 'uebernachtung', 'label' => 'Übernachtung',        'cat' => 'uebernachtung', 'icon' => 'bed',      'pp' => 155, 'flat' => 0,    'wiz' => 'Übernachtung' ],
			[ 'id' => 'transport',     'label' => 'Transport & Shuttle', 'cat' => 'transport',     'icon' => 'bus',      'pp' => 38,  'flat' => 0,    'wiz' => 'Shuttle / Transport' ],
			[ 'id' => 'technik',       'label' => 'Technik & Show',      'cat' => 'technik',       'icon' => 'show',     'pp' => 0,   'flat' => 3200, 'wiz' => 'Eventtechnik: Bühne + Personal' ],
			[ 'id' => 'foto',          'label' => 'Foto & Content',      'cat' => 'foto',          'icon' => 'cam',      'pp' => 0,   'flat' => 1400, 'wiz' => 'Fotograf' ],
		],
		'ranges' => [
			[ 'id' => '€',   'mult' => 0.82 ],
			[ 'id' => '€€',  'mult' => 1.0 ],
			[ 'id' => '€€€', 'mult' => 1.45 ],
		],
		'cats' => [
			'venue'         => [ 'label' => 'Golfplatz & Greenfee', 'color' => '#2C5036' ],
			'programm'      => [ 'label' => 'Programm & Coaching',  'color' => '#6E9A5E' ],
			'catering'      => [ 'label' => 'Catering',             'color' => '#C9B488' ],
			'uebernachtung' => [ 'label' => 'Übernachtung',         'color' => '#B45A37' ],
			'transport'     => [ 'label' => 'Transport',            'color' => '#6C736E' ],
			'technik'       => [ 'label' => 'Technik & Show',       'color' => '#3F6B49' ],
			'foto'          => [ 'label' => 'Foto & Content',       'color' => '#D8C9A6' ],
		],
		'venue_split'    => 0.6,
		'programm_split' => 0.4,
		'round_to'       => 50,
		'start'          => [ 'participants' => 30, 'type' => 'teamevent', 'range' => '€€', 'services' => [ 'catering', 'coaching' ] ],
	];
}

/**
 * Aktive Konfiguration: gespeicherte Preise auf die feste Default-Struktur
 * gelegt. Struktur (IDs, cat, icon, color, wiz) stammt immer aus den Defaults,
 * nur die editierbaren Felder (label, pp, flat, mult, splits, round) werden
 * aus der Option übernommen.
 */
function fge_bc_config(): array {
	$def   = fge_bc_defaults();
	$saved = get_option( FGE_BC_OPTION, [] );
	if ( ! is_array( $saved ) ) {
		$saved = [];
	}

	$by_id = static function ( array $rows ) {
		$map = [];
		foreach ( $rows as $r ) {
			if ( isset( $r['id'] ) ) {
				$map[ $r['id'] ] = $r;
			}
		}
		return $map;
	};

	$saved_types = $by_id( $saved['types'] ?? [] );
	foreach ( $def['types'] as &$t ) {
		$s = $saved_types[ $t['id'] ] ?? [];
		if ( isset( $s['label'] ) && $s['label'] !== '' ) {
			$t['label'] = (string) $s['label'];
		}
		if ( isset( $s['pp'] ) ) {
			$t['pp'] = max( 0, (float) $s['pp'] );
		}
	}
	unset( $t );

	$saved_svc = $by_id( $saved['services'] ?? [] );
	foreach ( $def['services'] as &$sv ) {
		$s = $saved_svc[ $sv['id'] ] ?? [];
		if ( isset( $s['label'] ) && $s['label'] !== '' ) {
			$sv['label'] = (string) $s['label'];
		}
		if ( isset( $s['pp'] ) ) {
			$sv['pp'] = max( 0, (float) $s['pp'] );
		}
		if ( isset( $s['flat'] ) ) {
			$sv['flat'] = max( 0, (float) $s['flat'] );
		}
	}
	unset( $sv );

	$saved_rng = [];
	foreach ( $saved['ranges'] ?? [] as $r ) {
		if ( isset( $r['id'] ) ) {
			$saved_rng[ $r['id'] ] = $r;
		}
	}
	foreach ( $def['ranges'] as &$rg ) {
		if ( isset( $saved_rng[ $rg['id'] ]['mult'] ) ) {
			$rg['mult'] = max( 0, (float) $saved_rng[ $rg['id'] ]['mult'] );
		}
	}
	unset( $rg );

	if ( isset( $saved['venue_split'] ) ) {
		$def['venue_split'] = min( 1, max( 0, (float) $saved['venue_split'] ) );
	}
	if ( isset( $saved['programm_split'] ) ) {
		$def['programm_split'] = min( 1, max( 0, (float) $saved['programm_split'] ) );
	}
	if ( isset( $saved['round_to'] ) ) {
		$def['round_to'] = max( 1, (int) $saved['round_to'] );
	}

	return $def;
}

// ── Admin-Menü ─────────────────────────────────────────────────────────────
add_action( 'admin_menu', 'fge_bc_register_menu' );
function fge_bc_register_menu(): void {
	add_submenu_page(
		'edit.php?post_type=firmengolf_event',
		'Budget-Rechner',
		'Budget-Rechner',
		'manage_options',
		'fge-budget-calc',
		'fge_bc_render_settings_page'
	);
}

/** Speichern der geposteten Preise. */
function fge_bc_handle_save(): void {
	if ( ! isset( $_POST['fge_bc_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['fge_bc_nonce'] ), 'fge_bc_save' ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$num = static function ( $v ): float {
		return max( 0.0, (float) str_replace( ',', '.', (string) wp_unslash( $v ) ) );
	};

	$def = fge_bc_defaults();
	$out = [ 'types' => [], 'services' => [], 'ranges' => [] ];

	foreach ( $def['types'] as $t ) {
		$id = $t['id'];
		$out['types'][] = [
			'id'    => $id,
			'label' => sanitize_text_field( wp_unslash( $_POST['type_label'][ $id ] ?? $t['label'] ) ),
			'pp'    => $num( $_POST['type_pp'][ $id ] ?? $t['pp'] ),
		];
	}
	foreach ( $def['services'] as $s ) {
		$id = $s['id'];
		$out['services'][] = [
			'id'    => $id,
			'label' => sanitize_text_field( wp_unslash( $_POST['svc_label'][ $id ] ?? $s['label'] ) ),
			'pp'    => $num( $_POST['svc_pp'][ $id ] ?? $s['pp'] ),
			'flat'  => $num( $_POST['svc_flat'][ $id ] ?? $s['flat'] ),
		];
	}
	foreach ( $def['ranges'] as $r ) {
		$id = $r['id'];
		$out['ranges'][] = [
			'id'   => $id,
			'mult' => $num( $_POST['range_mult'][ $id ] ?? $r['mult'] ),
		];
	}
	$out['venue_split']    = min( 1.0, $num( $_POST['venue_split'] ?? $def['venue_split'] ) );
	$out['programm_split'] = min( 1.0, $num( $_POST['programm_split'] ?? $def['programm_split'] ) );
	$out['round_to']       = max( 1, (int) ( $_POST['round_to'] ?? $def['round_to'] ) );

	update_option( FGE_BC_OPTION, $out );
	add_settings_error( 'fge_bc', 'saved', 'Preise gespeichert.', 'updated' );
}

function fge_bc_render_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		fge_bc_handle_save();
	}
	$cfg = fge_bc_config();
	settings_errors( 'fge_bc' );
	?>
	<div class="wrap">
		<h1>Budget-Rechner — Preise</h1>
		<p class="description" style="max-width:680px;">
			Diese Preise speisen den Budget-Rechner auf der Seite <em>Individuelle Events</em>.
			Grundkosten = Teilnehmerzahl × €/Person, aufgeteilt in Platz und Programm. Services
			kommen je nach Einstellung pro Person oder als Pauschale dazu. Das Preisniveau
			(€/€€/€€€) multipliziert die Gesamtsumme.
		</p>
		<form method="post">
			<?php wp_nonce_field( 'fge_bc_save', 'fge_bc_nonce' ); ?>

			<h2>Event-Typen — Grundpreis pro Person</h2>
			<table class="widefat striped" style="max-width:680px;">
				<thead><tr><th>Typ (Anzeige)</th><th style="width:160px;">€ / Person</th></tr></thead>
				<tbody>
				<?php foreach ( $cfg['types'] as $t ) : ?>
					<tr>
						<td><input type="text" class="regular-text" name="type_label[<?php echo esc_attr( $t['id'] ); ?>]" value="<?php echo esc_attr( $t['label'] ); ?>"></td>
						<td><input type="number" step="0.01" min="0" name="type_pp[<?php echo esc_attr( $t['id'] ); ?>]" value="<?php echo esc_attr( (string) $t['pp'] ); ?>"></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2 style="margin-top:28px;">Services — Aufpreis</h2>
			<p class="description">Trage entweder einen Preis <strong>pro Person</strong> <em>oder</em> eine <strong>Pauschale</strong> ein. Ist eine Pauschale &gt; 0 gesetzt, gilt diese (pro Person wird ignoriert).</p>
			<table class="widefat striped" style="max-width:760px;">
				<thead><tr><th>Service (Anzeige)</th><th style="width:150px;">€ / Person</th><th style="width:150px;">Pauschale €</th></tr></thead>
				<tbody>
				<?php foreach ( $cfg['services'] as $s ) : ?>
					<tr>
						<td><input type="text" class="regular-text" name="svc_label[<?php echo esc_attr( $s['id'] ); ?>]" value="<?php echo esc_attr( $s['label'] ); ?>"></td>
						<td><input type="number" step="0.01" min="0" name="svc_pp[<?php echo esc_attr( $s['id'] ); ?>]" value="<?php echo esc_attr( (string) $s['pp'] ); ?>"></td>
						<td><input type="number" step="0.01" min="0" name="svc_flat[<?php echo esc_attr( $s['id'] ); ?>]" value="<?php echo esc_attr( (string) $s['flat'] ); ?>"></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2 style="margin-top:28px;">Preisniveau-Faktoren</h2>
			<table class="widefat striped" style="max-width:420px;">
				<thead><tr><th>Stufe</th><th style="width:160px;">Faktor</th></tr></thead>
				<tbody>
				<?php foreach ( $cfg['ranges'] as $r ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $r['id'] ); ?></strong></td>
						<td><input type="number" step="0.01" min="0" name="range_mult[<?php echo esc_attr( $r['id'] ); ?>]" value="<?php echo esc_attr( (string) $r['mult'] ); ?>"></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2 style="margin-top:28px;">Aufteilung & Rundung</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="venue_split">Anteil Golfplatz</label></th>
					<td><input type="number" step="0.01" min="0" max="1" id="venue_split" name="venue_split" value="<?php echo esc_attr( (string) $cfg['venue_split'] ); ?>"> <span class="description">z.&nbsp;B. 0.6 = 60&nbsp;% des Grundpreises</span></td>
				</tr>
				<tr>
					<th scope="row"><label for="programm_split">Anteil Programm</label></th>
					<td><input type="number" step="0.01" min="0" max="1" id="programm_split" name="programm_split" value="<?php echo esc_attr( (string) $cfg['programm_split'] ); ?>"> <span class="description">z.&nbsp;B. 0.4 = 40&nbsp;%</span></td>
				</tr>
				<tr>
					<th scope="row"><label for="round_to">Rundung auf</label></th>
					<td><input type="number" step="1" min="1" id="round_to" name="round_to" value="<?php echo esc_attr( (string) $cfg['round_to'] ); ?>"> <span class="description">€ — Anzeigewerte werden hierauf gerundet</span></td>
				</tr>
			</table>

			<?php submit_button( 'Preise speichern' ); ?>
		</form>
	</div>
	<?php
}
