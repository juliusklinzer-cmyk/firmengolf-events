<?php
/**
 * Budget-Rechner — Preis-Konfiguration & Backend-Settings.
 *
 * Der Rechner auf der Individuelle-Events-Seite läuft client-seitig, bezieht
 * seine Preise aber aus einer WP-Option (fge_budget_calc), die hier im Backend
 * gepflegt wird. So bleibt der Rechner ohne Code-Änderung immer aktuell.
 *
 * Modell: Ein Event-Typ hat KEINEN Grundpreis, sondern nur eine Liste passender
 * Dienstleistungen. Jede Dienstleistung trägt den Preis (€/Person ODER Pauschale).
 * Platzkosten/Greenfee stecken in der jeweiligen Golf-Leistung (z. B. „18-Loch-
 * Turnier inkl. Greenfee"), es gibt keine separate Greenfee-Position.
 *
 * Editierbar im Admin: Typ-Labels, Service-Labels + Preise (€/Person ODER
 * Pauschale), Preisniveau-Faktoren (€/€€/€€€), Rundung.
 * Fix (aus Defaults): IDs, Typ→Service-Zuordnung (services/default_on/required),
 * Kategorie-Zuordnung, Icons, Kategorie-Farben, Wizard-Mapping (wiz).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FGE_BC_OPTION = 'fge_budget_calc';

/** Werkseinstellungen — Quelle der Wahrheit für Struktur + Start-Preise. */
function fge_bc_defaults(): array {
	// Service-Katalog (konsolidiert) — in grober Tagesablauf-Reihenfolge.
	// cat (Donut-Kategorie), icon, pp (€/Person) ODER flat (Pauschale), wiz (Anfrage-Mapping).
	$services = [
		// Anreise.
		[ 'id' => 'shuttle',       'label' => 'Shuttle-Service',                        'cat' => 'transport','icon' => 'bus',  'pp' => 38,  'flat' => 0,    'wiz' => 'Shuttle / Transport' ],
		[ 'id' => 'vip_shuttle',   'label' => 'VIP-Shuttle',                            'cat' => 'transport','icon' => 'star', 'pp' => 0,   'flat' => 1200, 'wiz' => 'Shuttle / Transport' ],
		// Golf-Leistung (Platznutzung/Greenfee inkludiert).
		[ 'id' => 'schnupperkurs', 'label' => 'Schnupperkurs (inkl. Platz, Golflehrer & Leihschläger)', 'cat' => 'programm', 'icon' => 'coaching', 'pp' => 99, 'flat' => 0, 'wiz' => 'Schnupperkurs' ],
		[ 'id' => 'coaching',      'label' => 'Trainerstunde / Golftraining',           'cat' => 'programm', 'icon' => 'club', 'pp' => 48,  'flat' => 0,    'wiz' => 'Golflehrer / Coaching' ],
		[ 'id' => 'turnier',       'label' => 'Firmenturnier (9 oder 18 Loch, inkl. Greenfee & Scoring)', 'cat' => 'venue', 'icon' => 'trophy', 'pp' => 145, 'flat' => 0, 'wiz' => 'Firmenturnier' ],
		[ 'id' => 'putting',       'label' => 'Putting-Turnier',                        'cat' => 'programm', 'icon' => 'target','pp' => 0,   'flat' => 600,  'wiz' => 'Putting-Challenge' ],
		[ 'id' => 'nachtrunde',    'label' => 'Nacht-Runde (Kurzplatz / Range)',        'cat' => 'venue',    'icon' => 'flag', 'pp' => 75,  'flat' => 0,    'wiz' => 'Firmenturnier' ],
		// Verpflegung (im Tagesverlauf).
		[ 'id' => 'startgeschenk', 'label' => 'Startgeschenk / Goodie-Bag',             'cat' => 'extras',   'icon' => 'gift', 'pp' => 35,  'flat' => 0,    'wiz' => 'Individuelle Artikel' ],
		[ 'id' => 'welcome_drink', 'label' => 'Welcome Drink',                          'cat' => 'catering', 'icon' => 'drink','pp' => 12,  'flat' => 0,    'wiz' => 'Bar & Drinks' ],
		[ 'id' => 'halfway',       'label' => 'Half-Way-Verpflegung (auf der Runde)',   'cat' => 'catering', 'icon' => 'catering','pp' => 18, 'flat' => 0, 'wiz' => 'Lunch' ],
		[ 'id' => 'cominghome',    'label' => 'Coming Home (Imbiss nach der Runde)',    'cat' => 'catering', 'icon' => 'catering','pp' => 16, 'flat' => 0, 'wiz' => 'Lunch' ],
		[ 'id' => 'catering',      'label' => 'Bewirtung / Catering',                   'cat' => 'catering', 'icon' => 'catering','pp' => 62, 'flat' => 0, 'wiz' => 'Lunch' ],
		[ 'id' => 'dinner',        'label' => 'Dinner / Abendveranstaltung',            'cat' => 'catering', 'icon' => 'catering','pp' => 78, 'flat' => 0, 'wiz' => 'Abendessen' ],
		[ 'id' => 'getraenke',     'label' => 'Getränkepauschale',                      'cat' => 'catering', 'icon' => 'drink','pp' => 28,  'flat' => 0,    'wiz' => 'Bar & Drinks' ],
		[ 'id' => 'bar',           'label' => 'Bar & Drinks',                           'cat' => 'catering', 'icon' => 'drink','pp' => 35,  'flat' => 0,    'wiz' => 'Bar & Drinks' ],
		// Unterhaltung & Technik.
		[ 'id' => 'musik',         'label' => 'DJ oder Live-Band',                      'cat' => 'technik',  'icon' => 'music','pp' => 0,   'flat' => 1600, 'wiz' => 'DJ' ],
		[ 'id' => 'technik',       'label' => 'Bühne & Eventtechnik',                   'cat' => 'technik',  'icon' => 'show', 'pp' => 0,   'flat' => 3200, 'wiz' => 'Eventtechnik: Bühne + Personal' ],
		// Turnier-Extras.
		[ 'id' => 'siegerehrung',  'label' => 'Siegerehrung & Preise',                  'cat' => 'extras',   'icon' => 'trophy','pp' => 0,  'flat' => 900,  'wiz' => 'Pokale & Preise' ],
		[ 'id' => 'sonderwertung', 'label' => 'Sonderwertungen (Longest Drive / Nearest to Pin)', 'cat' => 'extras', 'icon' => 'target', 'pp' => 0, 'flat' => 400, 'wiz' => 'Firmenturnier' ],
		[ 'id' => 'branding',      'label' => 'Branding (Abschläge, Banner, Merch)',    'cat' => 'extras',   'icon' => 'tag',  'pp' => 0,   'flat' => 700,  'wiz' => 'Branding & Banner' ],
		[ 'id' => 'turnierserie',  'label' => 'Turnier-Serie (mehrere Termine)',        'cat' => 'extras',   'icon' => 'calendar','pp' => 0,'flat' => 2500, 'wiz' => 'Firmenturnier' ],
		// Raum, Übernachtung, Content.
		[ 'id' => 'meetingraum',   'label' => 'Meetingraum / Tagung',                   'cat' => 'programm', 'icon' => 'room', 'pp' => 0,   'flat' => 800,  'wiz' => 'Meetingraum' ],
		[ 'id' => 'uebernachtung', 'label' => 'Übernachtung',                           'cat' => 'uebernachtung','icon' => 'bed','pp' => 155,'flat' => 0,  'wiz' => 'Übernachtung' ],
		[ 'id' => 'foto',          'label' => 'Fotograf / Content',                     'cat' => 'foto',     'icon' => 'cam',  'pp' => 0,   'flat' => 1400, 'wiz' => 'Fotograf' ],
		// Nacht.
		[ 'id' => 'flutlicht',     'label' => 'Flutlicht / Einleuchten des Platzes',    'cat' => 'venue',    'icon' => 'bulb', 'pp' => 0,   'flat' => 2800, 'wiz' => 'Flutlicht / Nacht-Event' ],
		[ 'id' => 'leuchtball',    'label' => 'Leucht-Equipment / Nacht-Bälle',         'cat' => 'programm', 'icon' => 'ball', 'pp' => 9,   'flat' => 0,    'wiz' => 'Flutlicht / Nacht-Event' ],
	];

	$all_ids = array_column( $services, 'id' );

	$types = [
		[
			'id' => 'teamevent', 'label' => 'Teamevent', 'wiz' => 'Teamevent',
			'services'   => [ 'shuttle', 'schnupperkurs', 'coaching', 'putting', 'catering', 'getraenke', 'meetingraum', 'foto' ],
			'default_on' => [ 'schnupperkurs' ],
			'required'   => [],
		],
		[
			'id' => 'turnier', 'label' => 'Firmenturnier', 'wiz' => 'Firmenturnier',
			'services'   => [ 'vip_shuttle', 'turnier', 'startgeschenk', 'welcome_drink', 'halfway', 'cominghome', 'dinner', 'siegerehrung', 'sonderwertung', 'branding', 'turnierserie', 'musik', 'technik', 'foto' ],
			'default_on' => [ 'turnier' ],
			'required'   => [],
		],
		[
			'id' => 'kundenevent', 'label' => 'Kundenevent', 'wiz' => 'Kundenevent',
			'services'   => [ 'vip_shuttle', 'schnupperkurs', 'coaching', 'turnier', 'welcome_drink', 'catering', 'dinner', 'branding', 'foto' ],
			'default_on' => [ 'schnupperkurs' ],
			'required'   => [],
		],
		[
			'id' => 'offsite', 'label' => 'Offsite & Incentive', 'wiz' => 'Incentive-Reise',
			'services'   => [ 'shuttle', 'uebernachtung', 'meetingraum', 'coaching', 'turnier', 'halfway', 'dinner', 'foto' ],
			'default_on' => [ 'uebernachtung' ],
			'required'   => [],
		],
		[
			'id' => 'sommerfest', 'label' => 'Sommerfest & Dinner', 'wiz' => 'Sommerfest',
			'services'   => [ 'shuttle', 'schnupperkurs', 'putting', 'catering', 'dinner', 'getraenke', 'bar', 'musik', 'technik', 'foto' ],
			'default_on' => [ 'catering' ],
			'required'   => [],
		],
		[
			'id' => 'gesundheit', 'label' => 'Gesundheitstag', 'wiz' => 'Gesundheitstag',
			'services'   => [ 'shuttle', 'coaching', 'schnupperkurs', 'meetingraum', 'catering', 'getraenke', 'foto' ],
			'default_on' => [ 'coaching' ],
			'required'   => [],
		],
		[
			'id' => 'nachtturnier', 'label' => 'Nachtturnier', 'wiz' => 'Firmenturnier',
			'services'   => [ 'flutlicht', 'shuttle', 'nachtrunde', 'leuchtball', 'welcome_drink', 'halfway', 'cominghome', 'dinner', 'siegerehrung', 'musik', 'bar', 'technik', 'foto' ],
			'default_on' => [ 'nachtrunde' ],
			'required'   => [ 'flutlicht' ],
		],
		[
			'id' => 'andere', 'label' => 'Andere', 'wiz' => 'Teamevent',
			'services'   => $all_ids,
			'default_on' => [],
			'required'   => [],
		],
	];

	return [
		'types'    => $types,
		'services' => $services,
		'ranges'   => [
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
			'extras'        => [ 'label' => 'Extras & Branding',    'color' => '#C77D4A' ],
		],
		'round_to' => 50,
		'start'    => [ 'participants' => 30, 'type' => 'teamevent', 'range' => '€€' ],
	];
}

/**
 * Aktive Konfiguration: gespeicherte Preise auf die feste Default-Struktur
 * gelegt. Struktur (IDs, services/default_on/required, cat, icon, color, wiz)
 * stammt immer aus den Defaults, nur die editierbaren Felder (Labels, pp, flat,
 * mult, round) werden aus der Option übernommen.
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
	$out['round_to'] = max( 1, (int) ( $_POST['round_to'] ?? $def['round_to'] ) );

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

	// Service-Label-Lookup für die lesbare Zuordnungs-Übersicht.
	$svc_label = [];
	foreach ( $cfg['services'] as $s ) {
		$svc_label[ $s['id'] ] = $s['label'];
	}

	settings_errors( 'fge_bc' );
	?>
	<div class="wrap">
		<h1>Budget-Rechner — Preise</h1>
		<p class="description" style="max-width:680px;">
			Diese Preise speisen den Budget-Rechner auf der Seite <em>Individuelle Events</em>.
			Ein Event-Typ hat <strong>keinen Grundpreis</strong> mehr — er bündelt nur die passenden
			Dienstleistungen. Jede Dienstleistung kostet entweder <strong>pro Person</strong>
			<em>oder</em> eine <strong>Pauschale</strong> (Pauschale &gt; 0 hat Vorrang). Die
			Platzkosten/Greenfee stecken in der jeweiligen Golf-Leistung (z.&nbsp;B. „18-Loch-Turnier
			inkl. Greenfee"). Das Preisniveau (€/€€/€€€) multipliziert die Gesamtsumme.
			Welche Services bei welchem Typ erscheinen, ist im Code festgelegt (siehe Übersicht unten).
		</p>
		<form method="post">
			<?php wp_nonce_field( 'fge_bc_save', 'fge_bc_nonce' ); ?>

			<h2>Event-Typen — Anzeigename</h2>
			<table class="widefat striped" style="max-width:680px;">
				<thead><tr><th>Typ (Anzeige)</th></tr></thead>
				<tbody>
				<?php foreach ( $cfg['types'] as $t ) : ?>
					<tr>
						<td><input type="text" class="regular-text" name="type_label[<?php echo esc_attr( $t['id'] ); ?>]" value="<?php echo esc_attr( $t['label'] ); ?>"></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2 style="margin-top:28px;">Services — Preise</h2>
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

			<h2 style="margin-top:28px;">Rundung</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="round_to">Rundung auf</label></th>
					<td><input type="number" step="1" min="1" id="round_to" name="round_to" value="<?php echo esc_attr( (string) $cfg['round_to'] ); ?>"> <span class="description">€ — Anzeigewerte werden hierauf gerundet</span></td>
				</tr>
			</table>

			<?php submit_button( 'Preise speichern' ); ?>
		</form>

		<h2 style="margin-top:28px;">Zuordnung Typ → Services <span class="description">(fix im Code)</span></h2>
		<table class="widefat striped" style="max-width:920px;">
			<thead><tr><th style="width:170px;">Event-Typ</th><th>Angezeigte Services (●&nbsp;=&nbsp;vorausgewählt, 🔒&nbsp;=&nbsp;fix enthalten)</th></tr></thead>
			<tbody>
			<?php foreach ( $cfg['types'] as $t ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $t['label'] ); ?></strong></td>
					<td><?php
						$labels = array_map(
							static function ( $sid ) use ( $svc_label, $t ) {
								$mark = in_array( $sid, $t['required'], true ) ? ' 🔒' : ( in_array( $sid, $t['default_on'], true ) ? ' ●' : '' );
								return ( $svc_label[ $sid ] ?? $sid ) . $mark;
							},
							$t['services']
						);
						echo esc_html( implode( ' · ', $labels ) );
					?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}
