<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Helpers ──────────────────────────────────────────────────────────────────

function fge_get_event_format_options(): array {
	return fge_get_event_formats_flat( true );
}

function fge_get_infrastructure_options(): array {
	return [
		'has_driving_range'           => 'Driving Range',
		'has_putting_green'           => 'Putting Green',
		'has_short_game_area'         => 'Kurzspielbereich',
		'has_short_course'            => 'Kurzplatz',
		'has_9hole_course'            => '9-Loch-Platz',
		'has_18hole_course'           => '18-Loch-Platz',
		'has_rental_clubs'            => 'Leihschläger',
		'has_range_balls'             => 'Rangebälle',
		'has_golf_teacher'            => 'Golflehrer',
		'has_meeting_room'            => 'Meetingraum',
		'has_gastronomy'              => 'Restaurant',
		'has_terrace'                 => 'Terrasse / Außenbereich',
		'has_parking'                 => 'Parkplätze',
		'has_shuttle_access'          => 'Shuttle möglich',
		'has_indoor_or_simulator'     => 'Indoor / Simulator',
		'has_bad_weather_alternative' => 'Schlechtwetter Alternative',
		'has_branding_options'        => 'Branding Möglichkeiten',
		'has_tournament_organization' => 'Turnierorganisation',
		'has_locker_room'             => 'Umkleiden & Duschen',
	];
}

// ── Registration ─────────────────────────────────────────────────────────────

function fge_register_partner_metaboxes() {
	$screen = 'firmengolf_partner';

	add_meta_box( 'fge_pmb_basisdaten',      'Golfplatz Basisdaten',     'fge_render_pmb_basisdaten',      $screen, 'normal', 'high' );
	add_meta_box( 'fge_pmb_standort',        'Standort und Region',      'fge_render_pmb_standort',        $screen, 'normal', 'default' );
	add_meta_box( 'fge_pmb_profil',          'Profil & Ausstattung',     'fge_render_pmb_profil',          $screen, 'normal', 'default' );
	add_meta_box( 'fge_pmb_ansprechpartner', 'Ansprechpartner',          'fge_render_pmb_ansprechpartner', $screen, 'normal', 'default' );
	add_meta_box( 'fge_pmb_kapazitaeten',    'Verfügbarkeit & Formate',  'fge_render_pmb_kapazitaeten',    $screen, 'normal', 'default' );
	add_meta_box( 'fge_pmb_preis',           'Abrechnung (intern)',      'fge_render_pmb_preis',           $screen, 'normal', 'default' );
	add_meta_box( 'fge_pmb_portal',          'Partnerportal',            'fge_render_pmb_portal',          $screen, 'normal', 'default' );
	add_meta_box( 'fge_pmb_medien',          'Medien und Bildrechte',    'fge_render_pmb_medien',          $screen, 'normal', 'default' );
	add_meta_box( 'fge_pmb_tracking',        'Tracking Basis',           'fge_render_pmb_tracking',        $screen, 'side',   'default' );
}
add_action( 'add_meta_boxes', 'fge_register_partner_metaboxes' );

// ── Render: Golfplatz Basisdaten ─────────────────────────────────────────────

function fge_render_pmb_basisdaten( WP_Post $post ) {
	wp_nonce_field( 'fge_partner_fields', 'fge_partner_nonce' );

	$public_golfclub_name      = get_post_meta( $post->ID, '_fge_public_golfclub_name', true );
	$legal_operator_name       = get_post_meta( $post->ID, '_fge_legal_operator_name', true );
	$partner_status            = get_post_meta( $post->ID, '_fge_partner_status', true );
	$partner_since             = get_post_meta( $post->ID, '_fge_partner_since', true );
	$public_short_description  = get_post_meta( $post->ID, '_fge_public_short_description', true );
	$internal_note             = get_post_meta( $post->ID, '_fge_internal_note', true );
	$review_quote              = get_post_meta( $post->ID, '_fge_review_quote', true );
	$review_author             = get_post_meta( $post->ID, '_fge_review_author', true );
	$review_role               = get_post_meta( $post->ID, '_fge_review_role', true );
	$rating                    = get_post_meta( $post->ID, '_fge_rating', true );

	$statuses = fge_get_statuses( 'partner' );
	?>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="fge_public_golfclub_name">Öffentlicher Anzeigename</label></th>
			<td><input type="text" id="fge_public_golfclub_name" name="fge_public_golfclub_name"
			           value="<?php echo esc_attr( $public_golfclub_name ); ?>" class="regular-text"></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_partner_status">Anbieter Status</label></th>
			<td>
				<select id="fge_partner_status" name="fge_partner_status">
					<option value="">— bitte wählen —</option>
					<?php foreach ( $statuses as $val ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $partner_status, $val ); ?>><?php echo esc_html( $val ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_partner_since">Partner seit</label></th>
			<td>
				<input type="date" id="fge_partner_since" name="fge_partner_since"
				       value="<?php echo esc_attr( $partner_since ); ?>">
				<p class="description">Wird im Portal und auf der Golfplatz-Detailseite als „Mitglied seit" angezeigt.</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_rating">Sterne-Bewertung</label></th>
			<td>
				<input type="number" id="fge_rating" name="fge_rating" min="0" max="5" step="0.1"
				       value="<?php echo esc_attr( $rating ); ?>" style="width:90px;">
				<p class="description">Statische Anzeige auf den Event-Karten (z. B. 4.8). Leer = keine Sterne.</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_public_short_description">Öffentliche Kurzbeschreibung</label></th>
			<td><textarea id="fge_public_short_description" name="fge_public_short_description" rows="3" class="large-text"><?php echo esc_textarea( $public_short_description ); ?></textarea></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_review_quote">Kundenstimme (öffentlich)</label></th>
			<td>
				<textarea id="fge_review_quote" name="fge_review_quote" rows="2" class="large-text" placeholder="Zitat des Kunden …"><?php echo esc_textarea( $review_quote ); ?></textarea>
				<input type="text" name="fge_review_author" value="<?php echo esc_attr( $review_author ); ?>" class="regular-text" placeholder="Name (z.B. Sandra Klein)" style="margin-top:6px;display:block;">
				<input type="text" name="fge_review_role" value="<?php echo esc_attr( $review_role ); ?>" class="regular-text" placeholder="Rolle · Firma (z.B. HR-Direktorin · Werkstatt 4)" style="margin-top:6px;display:block;">
				<p class="description">Wird auf den Event-Detailseiten dieses Platzes als Kundenstimme angezeigt. Leer = ausgeblendet.</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_legal_operator_name">Rechtlicher Betreibername</label></th>
			<td>
				<input type="text" id="fge_legal_operator_name" name="fge_legal_operator_name"
				       value="<?php echo esc_attr( $legal_operator_name ); ?>" class="regular-text">
				<p class="description">Für Rechnungen / rechtliche Zwecke. Nicht öffentlich.</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_internal_note">Interne Notiz</label></th>
			<td>
				<textarea id="fge_internal_note" name="fge_internal_note" rows="3" class="large-text"><?php echo esc_textarea( $internal_note ); ?></textarea>
				<p class="description">Nur für Firmengolf Admin.</p>
			</td>
		</tr>
	</table>
	<?php
}

// ── Render: Standort und Region ──────────────────────────────────────────────

function fge_render_pmb_standort( WP_Post $post ) {
	$street         = get_post_meta( $post->ID, '_fge_street', true );
	$house_number   = get_post_meta( $post->ID, '_fge_house_number', true );
	$postal_code    = get_post_meta( $post->ID, '_fge_postal_code', true );
	$city           = get_post_meta( $post->ID, '_fge_city', true );
	$federal_state  = get_post_meta( $post->ID, '_fge_federal_state', true );
	$free_region    = get_post_meta( $post->ID, '_fge_free_region', true );
	$website_url    = get_post_meta( $post->ID, '_fge_website_url', true );
	$google_maps_url = get_post_meta( $post->ID, '_fge_google_maps_url', true );
	$directions_text = get_post_meta( $post->ID, '_fge_directions_text', true );
	$poi_car        = get_post_meta( $post->ID, '_fge_poi_car', true );
	$poi_train      = get_post_meta( $post->ID, '_fge_poi_train', true );
	$poi_parking    = get_post_meta( $post->ID, '_fge_poi_parking', true );
	$poi_hotel      = get_post_meta( $post->ID, '_fge_poi_hotel', true );

	$federal_states = [
		'baden_wuerttemberg'    => 'Baden-Württemberg',
		'bayern'                => 'Bayern',
		'berlin'                => 'Berlin',
		'brandenburg'           => 'Brandenburg',
		'bremen'                => 'Bremen',
		'hamburg'               => 'Hamburg',
		'hessen'                => 'Hessen',
		'mecklenburg_vorpommern' => 'Mecklenburg-Vorpommern',
		'niedersachsen'         => 'Niedersachsen',
		'nordrhein_westfalen'   => 'Nordrhein-Westfalen',
		'rheinland_pfalz'       => 'Rheinland-Pfalz',
		'saarland'              => 'Saarland',
		'sachsen'               => 'Sachsen',
		'sachsen_anhalt'        => 'Sachsen-Anhalt',
		'schleswig_holstein'    => 'Schleswig-Holstein',
		'thueringen'            => 'Thüringen',
	];
	?>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="fge_street">Straße</label></th>
			<td><input type="text" id="fge_street" name="fge_street"
			           value="<?php echo esc_attr( $street ); ?>" class="regular-text"></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_house_number">Hausnummer</label></th>
			<td><input type="text" id="fge_house_number" name="fge_house_number"
			           value="<?php echo esc_attr( $house_number ); ?>" style="width:100px;"></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_postal_code">PLZ</label></th>
			<td><input type="text" id="fge_postal_code" name="fge_postal_code"
			           value="<?php echo esc_attr( $postal_code ); ?>" style="width:100px;"></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_city">Ort</label></th>
			<td><input type="text" id="fge_city" name="fge_city"
			           value="<?php echo esc_attr( $city ); ?>" class="regular-text"></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_federal_state">Bundesland</label></th>
			<td>
				<select id="fge_federal_state" name="fge_federal_state">
					<option value="">— bitte wählen —</option>
					<?php foreach ( $federal_states as $val => $label ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $federal_state, $val ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_free_region">Region frei</label></th>
			<td>
				<input type="text" id="fge_free_region" name="fge_free_region"
				       value="<?php echo esc_attr( $free_region ); ?>" class="regular-text">
				<p class="description">z.B. München, Oberbayern, Hamburg, Rhein-Main</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_website_url">Website</label></th>
			<td><input type="url" id="fge_website_url" name="fge_website_url"
			           value="<?php echo esc_attr( $website_url ); ?>" class="regular-text"></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_google_maps_url">Google Maps Link</label></th>
			<td>
				<input type="url" id="fge_google_maps_url" name="fge_google_maps_url"
				       value="<?php echo esc_attr( $google_maps_url ); ?>" class="regular-text">
				<p class="description">Optional.</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_directions_text">Anfahrt &amp; Lage (Text)</label></th>
			<td>
				<textarea id="fge_directions_text" name="fge_directions_text" rows="3" class="large-text" placeholder="z.B. Großzügige Anlage, Parkplätze direkt am Clubhaus, barrierearmer Zugang."><?php echo esc_textarea( $directions_text ); ?></textarea>
				<p class="description">Erscheint im „Anfahrt &amp; Location"-Abschnitt der Event-Detailseiten. Leer = nur Adresse + Karte.</p>
			</td>
		</tr>
		<tr>
			<th scope="row">Anfahrt-Kacheln (POIs)</th>
			<td>
				<input type="text" name="fge_poi_car" value="<?php echo esc_attr( $poi_car ); ?>" class="regular-text" placeholder="Auto: z.B. 15 Min. ab Stadtzentrum" style="display:block;">
				<input type="text" name="fge_poi_train" value="<?php echo esc_attr( $poi_train ); ?>" class="regular-text" placeholder="Bahn: z.B. Shuttle ab Hauptbahnhof" style="display:block;margin-top:6px;">
				<input type="text" name="fge_poi_parking" value="<?php echo esc_attr( $poi_parking ); ?>" class="regular-text" placeholder="Parken: z.B. Kostenfrei vor Ort" style="display:block;margin-top:6px;">
				<input type="text" name="fge_poi_hotel" value="<?php echo esc_attr( $poi_hotel ); ?>" class="regular-text" placeholder="Hotel: z.B. 3 Partnerhotels in 10 Min." style="display:block;margin-top:6px;">
				<p class="description">Jede Kachel wird nur angezeigt, wenn ausgefüllt.</p>
			</td>
		</tr>
	</table>
	<?php
}

// ── Render: Ansprechpartner ───────────────────────────────────────────────────

function fge_render_pmb_profil( WP_Post $post ) {
	$golf_type            = (string) get_post_meta( $post->ID, '_fge_golf_type', true );
	$infra                = (array) get_post_meta( $post->ID, '_fge_infra', true );
	$cap                  = (array) get_post_meta( $post->ID, '_fge_cap', true );
	$poi_shuttle          = (string) get_post_meta( $post->ID, '_fge_poi_shuttle', true );
	$estation             = (string) get_post_meta( $post->ID, '_fge_arrival_estation', true );
	$additional_equipment = (string) get_post_meta( $post->ID, '_fge_additional_equipment', true );
	?>
	<p class="description">Katalog-basiertes Profil-Modell. Dies ist die führende Quelle für Ausstattung und Kapazitäten (Onboarding, Portal, Matching). Quelle: <code>includes/catalogs.php</code>.</p>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="fge_golf_type">Golf-Angebot (Platztyp)</label></th>
			<td>
				<select id="fge_golf_type" name="fge_golf_type">
					<option value="">— wählen —</option>
					<?php foreach ( fge_catalog_golf_types() as $id => $label ) : ?>
						<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $golf_type, $id ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
	</table>

	<h3 style="margin:18px 0 4px;">Infrastruktur &amp; Gastronomie</h3>
	<?php foreach ( fge_catalog_infra_groups() as $group => $items ) : ?>
		<p style="margin:14px 0 4px;"><strong><?php echo esc_html( $group ); ?></strong></p>
		<div style="column-count:3;column-gap:24px;">
			<?php foreach ( $items as $id => $label ) : ?>
				<label style="display:block;break-inside:avoid;margin:2px 0;">
					<input type="checkbox" name="fge_infra[]" value="<?php echo esc_attr( $id ); ?>" <?php checked( in_array( $id, $infra, true ) ); ?>>
					<?php echo esc_html( $label ); ?>
				</label>
			<?php endforeach; ?>
		</div>
	<?php endforeach; ?>

	<table class="form-table">
		<tr>
			<th scope="row"><label for="fge_additional_equipment">Weitere Ausstattung</label></th>
			<td><textarea id="fge_additional_equipment" name="fge_additional_equipment" rows="3" class="large-text" placeholder="Freitext, z.B. besondere Anlagen oder Extras"><?php echo esc_textarea( $additional_equipment ); ?></textarea></td>
		</tr>
	</table>

	<h3 style="margin:18px 0 4px;">Kapazitäten</h3>
	<table class="form-table">
		<tr>
			<th scope="row">Teilnehmer min / max</th>
			<td>
				<input type="number" min="0" name="fge_cap[min]" value="<?php echo esc_attr( $cap['min'] ?? '' ); ?>" style="width:90px;"> –
				<input type="number" min="0" name="fge_cap[max]" value="<?php echo esc_attr( $cap['max'] ?? '' ); ?>" style="width:90px;">
			</td>
		</tr>
		<?php foreach ( fge_catalog_cap_rows() as $row ) :
			$relevant = in_array( $row['infra'], $infra, true ); ?>
			<tr<?php echo $relevant ? '' : ' style="opacity:.6;"'; ?>>
				<th scope="row"><?php echo esc_html( $row['label'] ); ?></th>
				<td>
					<input type="number" min="0" name="fge_cap[<?php echo esc_attr( $row['key'] ); ?>]" value="<?php echo esc_attr( $cap[ $row['key'] ] ?? '' ); ?>" style="width:90px;">
					<span class="description">Nur relevant, wenn „<?php echo esc_html( fge_catalog_infra_label( $row['infra'] ) ); ?>" gewählt. <?php echo esc_html( $row['hint'] ); ?></span>
				</td>
			</tr>
		<?php endforeach; ?>
	</table>

	<h3 style="margin:18px 0 4px;">Anfahrt-Zusatz</h3>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="fge_poi_shuttle">Shuttle</label></th>
			<td><input type="text" id="fge_poi_shuttle" name="fge_poi_shuttle" value="<?php echo esc_attr( $poi_shuttle ); ?>" class="regular-text" placeholder="z.B. Abholung nach Absprache"></td>
		</tr>
		<tr>
			<th scope="row">E-Ladestation</th>
			<td><label><input type="checkbox" name="fge_arrival_estation" value="1" <?php checked( $estation, '1' ); ?>> Ladestation für E-Autos vorhanden</label></td>
		</tr>
	</table>
	<?php
}

function fge_render_pmb_ansprechpartner( WP_Post $post ) {
	$contact_groups = [
		'main'        => 'Hauptansprechpartner',
		'event'       => 'Event Ansprechpartner',
		'gastro'      => 'Gastro Ansprechpartner',
		'golf_school' => 'Golflehrer / Golfschule',
		'billing'     => 'Abrechnung',
	];
	$has_role = [ 'main' ]; // Only main contact has a "role" field.
	?>
	<table class="form-table">
		<?php foreach ( $contact_groups as $prefix => $group_label ) :
			$name  = get_post_meta( $post->ID, '_fge_' . $prefix . '_contact_name', true );
			$email = get_post_meta( $post->ID, '_fge_' . $prefix . '_contact_email', true );
			$phone = get_post_meta( $post->ID, '_fge_' . $prefix . '_contact_phone', true );
			?>
			<tr>
				<td colspan="2"><strong><?php echo esc_html( $group_label ); ?></strong></td>
			</tr>
			<tr>
				<th scope="row"><label for="fge_<?php echo esc_attr( $prefix ); ?>_contact_name">Name</label></th>
				<td><input type="text" id="fge_<?php echo esc_attr( $prefix ); ?>_contact_name"
				           name="fge_<?php echo esc_attr( $prefix ); ?>_contact_name"
				           value="<?php echo esc_attr( $name ); ?>" class="regular-text"></td>
			</tr>
			<?php if ( in_array( $prefix, $has_role, true ) ) :
				$role = get_post_meta( $post->ID, '_fge_main_contact_role', true ); ?>
				<tr>
					<th scope="row"><label for="fge_main_contact_role">Rolle</label></th>
					<td><input type="text" id="fge_main_contact_role" name="fge_main_contact_role"
					           value="<?php echo esc_attr( $role ); ?>" class="regular-text"></td>
				</tr>
			<?php endif; ?>
			<tr>
				<th scope="row"><label for="fge_<?php echo esc_attr( $prefix ); ?>_contact_email">E-Mail</label></th>
				<td><input type="email" id="fge_<?php echo esc_attr( $prefix ); ?>_contact_email"
				           name="fge_<?php echo esc_attr( $prefix ); ?>_contact_email"
				           value="<?php echo esc_attr( $email ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th scope="row"><label for="fge_<?php echo esc_attr( $prefix ); ?>_contact_phone">Telefon</label></th>
				<td><input type="text" id="fge_<?php echo esc_attr( $prefix ); ?>_contact_phone"
				           name="fge_<?php echo esc_attr( $prefix ); ?>_contact_phone"
				           value="<?php echo esc_attr( $phone ); ?>" class="regular-text"></td>
			</tr>
		<?php endforeach; ?>
	</table>
	<?php
}

// ── Render: Verfügbarkeit & Formate ───────────────────────────────────────────

function fge_render_pmb_kapazitaeten( WP_Post $post ) {
	$preferred_event_days       = (array) get_post_meta( $post->ID, '_fge_preferred_event_days', true );
	$season                     = get_post_meta( $post->ID, '_fge_season', true );
	$evening_events_possible    = get_post_meta( $post->ID, '_fge_evening_events_possible', true );
	$min_lead_time_days         = get_post_meta( $post->ID, '_fge_min_lead_time_days', true );
	$individual_availability    = get_post_meta( $post->ID, '_fge_individual_availability_check', true );
	$event_formats              = (array) get_post_meta( $post->ID, '_fge_event_formats', true );

	$weekdays = [
		'monday'    => 'Montag',
		'tuesday'   => 'Dienstag',
		'wednesday' => 'Mittwoch',
		'thursday'  => 'Donnerstag',
		'friday'    => 'Freitag',
		'saturday'  => 'Samstag',
		'sunday'    => 'Sonntag',
	];
	$seasons = [
		'year_round'       => 'Ganzjährig',
		'march_to_october' => 'März bis Oktober',
		'april_to_october' => 'April bis Oktober',
		'on_request'       => 'Auf Anfrage',
	];
	$format_tiers = fge_get_event_formats();
	?>
	<p class="description">Teilnehmerzahlen und Raum-Kapazitäten pflegst du oben unter „Profil &amp; Ausstattung". Hier nur Verfügbarkeit und Formate.</p>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="fge_min_lead_time_days">Mindestvorlauf (Tage)</label></th>
			<td><input type="number" id="fge_min_lead_time_days" name="fge_min_lead_time_days"
			           value="<?php echo esc_attr( $min_lead_time_days ); ?>" min="0" step="1" style="width:110px;"></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_season">Saison</label></th>
			<td>
				<select id="fge_season" name="fge_season">
					<option value="">— bitte wählen —</option>
					<?php foreach ( $seasons as $val => $label ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $season, $val ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row">Bevorzugte Eventtage</th>
			<td>
				<?php foreach ( $weekdays as $val => $label ) : ?>
					<label style="display:inline-block;margin-right:14px;">
						<input type="checkbox" name="fge_preferred_event_days[]"
						       value="<?php echo esc_attr( $val ); ?>"
						       <?php checked( in_array( $val, $preferred_event_days, true ) ); ?>>
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
			</td>
		</tr>
		<tr>
			<th scope="row">Abend-Events möglich</th>
			<td><label><input type="checkbox" name="fge_evening_events_possible" value="1"
			                  <?php checked( $evening_events_possible, '1' ); ?>> Ja</label></td>
		</tr>
		<tr>
			<th scope="row">Individuelle Verfügbarkeitsprüfung</th>
			<td><label><input type="checkbox" name="fge_individual_availability_check" value="1"
			                  <?php checked( $individual_availability !== '0' ? '1' : '0', '1' ); ?>> Ja (Standard: aktiv)</label></td>
		</tr>
		<tr>
			<th scope="row">Eventformate</th>
			<td>
				<p style="margin:4px 0 6px;"><strong>Standard</strong></p>
				<?php foreach ( $format_tiers['standard'] as $val => $label ) : ?>
					<label style="display:inline-block;margin-right:14px;margin-bottom:6px;">
						<input type="checkbox" name="fge_event_formats[]"
						       value="<?php echo esc_attr( $val ); ?>"
						       <?php checked( in_array( $val, $event_formats, true ) ); ?>>
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
				<p style="margin:12px 0 6px;"><strong>Auf Anfrage</strong></p>
				<?php foreach ( $format_tiers['on_request'] as $val => $label ) : ?>
					<label style="display:inline-block;margin-right:14px;margin-bottom:6px;">
						<input type="checkbox" name="fge_event_formats[]"
						       value="<?php echo esc_attr( $val ); ?>"
						       <?php checked( in_array( $val, $event_formats, true ) ); ?>>
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
			</td>
		</tr>
	</table>
	<?php
}

// ── Render: Preis und Abrechnung Basis ───────────────────────────────────────

function fge_render_pmb_preis( WP_Post $post ) {
	$tax_number_or_vat_id    = get_post_meta( $post->ID, '_fge_tax_number_or_vat_id', true );
	$internal_billing_note   = get_post_meta( $post->ID, '_fge_internal_billing_note', true );
	?>
	<p class="description">Die Plätze stellen Firmengolf eine Rechnung. Hier nur, was wir intern dafür brauchen.</p>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="fge_tax_number_or_vat_id">Steuernummer / USt-ID</label></th>
			<td>
				<input type="text" id="fge_tax_number_or_vat_id" name="fge_tax_number_or_vat_id"
				       value="<?php echo esc_attr( $tax_number_or_vat_id ); ?>" class="regular-text">
				<p class="description">Optional, für Rechnungszwecke.</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_internal_billing_note">Interne Abrechnungsnotiz</label></th>
			<td><textarea id="fge_internal_billing_note" name="fge_internal_billing_note" rows="3" class="large-text"><?php echo esc_textarea( $internal_billing_note ); ?></textarea></td>
		</tr>
	</table>
	<?php
}

// ── Render: Partnerportal Rechte ──────────────────────────────────────────────

function fge_render_pmb_portal( WP_Post $post ) {
	$portal_enabled               = get_post_meta( $post->ID, '_fge_partner_portal_enabled', true );
	$assigned_wp_user_id          = get_post_meta( $post->ID, '_fge_assigned_wp_user_id', true );
	?>
	<table class="form-table">
		<tr>
			<th scope="row">Partnerportal aktiviert</th>
			<td><label><input type="checkbox" name="fge_partner_portal_enabled" value="1"
			                  <?php checked( $portal_enabled, '1' ); ?>> Partnerportal aktiviert</label></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_assigned_wp_user_id">Zugeordneter WordPress Benutzer</label></th>
			<td>
				<input type="number" id="fge_assigned_wp_user_id" name="fge_assigned_wp_user_id"
				       value="<?php echo esc_attr( $assigned_wp_user_id ); ?>" min="0" step="1" style="width:110px;">
				<p class="description">User-ID des zugeordneten WP-Benutzers. Steuert den Portal-Zugang dieses Platzes.</p>
			</td>
		</tr>
	</table>
	<?php
}

// ── Render: Medien und Bildrechte ─────────────────────────────────────────────

function fge_render_pmb_medien( WP_Post $post ) {
	$logo_attachment_id   = get_post_meta( $post->ID, '_fge_logo_attachment_id', true );
	$hero_image_id        = get_post_meta( $post->ID, '_fge_hero_image_attachment_id', true );
	$gallery_ids          = get_post_meta( $post->ID, '_fge_gallery_attachment_ids', true );
	$rights_confirmed     = get_post_meta( $post->ID, '_fge_image_rights_confirmed', true );
	$rights_note          = get_post_meta( $post->ID, '_fge_image_rights_note', true );
	?>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="fge_logo_attachment_id">Golfplatz Logo Attachment ID</label></th>
			<td>
				<input type="number" id="fge_logo_attachment_id" name="fge_logo_attachment_id"
				       value="<?php echo esc_attr( $logo_attachment_id ); ?>" min="0" step="1" style="width:110px;">
				<p class="description">Im MVP als Attachment-ID. Später Media Upload.</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_hero_image_attachment_id">Titelbild Attachment ID</label></th>
			<td><input type="number" id="fge_hero_image_attachment_id" name="fge_hero_image_attachment_id"
			           value="<?php echo esc_attr( $hero_image_id ); ?>" min="0" step="1" style="width:110px;"></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_gallery_attachment_ids">Galerie Attachment IDs</label></th>
			<td>
				<input type="text" id="fge_gallery_attachment_ids" name="fge_gallery_attachment_ids"
				       value="<?php echo esc_attr( $gallery_ids ); ?>" class="regular-text">
				<p class="description">Kommagetrennte Attachment-IDs, z.B. 12,34,56</p>
			</td>
		</tr>
		<tr>
			<th scope="row">Bildrechte bestätigt</th>
			<td><label><input type="checkbox" name="fge_image_rights_confirmed" value="1"
			                  <?php checked( $rights_confirmed, '1' ); ?>> Bildrechte bestätigt</label></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_image_rights_note">Hinweis zu Bildrechten</label></th>
			<td><textarea id="fge_image_rights_note" name="fge_image_rights_note" rows="2" class="large-text"><?php echo esc_textarea( $rights_note ); ?></textarea></td>
		</tr>
	</table>
	<?php
}

// ── Render: Tracking Basis ────────────────────────────────────────────────────

function fge_render_pmb_tracking( WP_Post $post ) {
	$published_events = (int) get_post_meta( $post->ID, '_fge_published_events_count', true );
	$views_total      = (int) get_post_meta( $post->ID, '_fge_event_views_total', true );
	$requests_total   = (int) get_post_meta( $post->ID, '_fge_requests_total', true );
	$bookings_total   = (int) get_post_meta( $post->ID, '_fge_bookings_total', true );
	$last_req         = get_post_meta( $post->ID, '_fge_last_request_at', true );

	$conv_vr = ( $views_total > 0 )    ? round( $requests_total / $views_total * 100, 1 ) . ' %' : '–';
	$conv_rb = ( $requests_total > 0 ) ? round( $bookings_total / $requests_total * 100, 1 ) . ' %' : '–';
	?>
	<table class="form-table">
		<tr>
			<th scope="row">Veröffentlichte Events</th>
			<td><input type="text" value="<?php echo esc_attr( $published_events ); ?>" readonly style="width:80px;background:#f0f0f1;"></td>
		</tr>
		<tr>
			<th scope="row">Event Aufrufe gesamt</th>
			<td><input type="text" value="<?php echo esc_attr( $views_total ); ?>" readonly style="width:80px;background:#f0f0f1;"></td>
		</tr>
		<tr>
			<th scope="row">Anfragen gesamt</th>
			<td><input type="text" value="<?php echo esc_attr( $requests_total ); ?>" readonly style="width:80px;background:#f0f0f1;"></td>
		</tr>
		<tr>
			<th scope="row">Buchungen gesamt</th>
			<td><input type="text" value="<?php echo esc_attr( $bookings_total ); ?>" readonly style="width:80px;background:#f0f0f1;"></td>
		</tr>
		<tr>
			<th scope="row">Letzte Anfrage</th>
			<td><input type="text" value="<?php echo esc_attr( $last_req ); ?>" readonly class="regular-text" style="background:#f0f0f1;"></td>
		</tr>
		<tr>
			<th scope="row">Conversion Aufruf → Anfrage</th>
			<td><input type="text" value="<?php echo esc_attr( $conv_vr ); ?>" readonly style="width:80px;background:#f0f0f1;"></td>
		</tr>
		<tr>
			<th scope="row">Conversion Anfrage → Buchung</th>
			<td><input type="text" value="<?php echo esc_attr( $conv_rb ); ?>" readonly style="width:80px;background:#f0f0f1;"></td>
		</tr>
	</table>
	<?php
}

// ── Save ─────────────────────────────────────────────────────────────────────

function fge_save_partner_fields( int $post_id ) {
	if ( ! isset( $_POST['fge_partner_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fge_partner_nonce'] ) ), 'fge_partner_fields' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( get_post_type( $post_id ) !== 'firmengolf_partner' ) {
		return;
	}

	// Allowed values.
	$allowed_statuses         = fge_get_statuses( 'partner' );
	$allowed_federal_states   = [
		'baden_wuerttemberg', 'bayern', 'berlin', 'brandenburg', 'bremen', 'hamburg',
		'hessen', 'mecklenburg_vorpommern', 'niedersachsen', 'nordrhein_westfalen',
		'rheinland_pfalz', 'saarland', 'sachsen', 'sachsen_anhalt',
		'schleswig_holstein', 'thueringen',
	];
	$allowed_seasons          = [ 'year_round', 'march_to_october', 'april_to_october', 'on_request' ];
	$allowed_event_days       = [ 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' ];

	$san_select = static function ( string $key, array $allowed ): string {
		$val = sanitize_text_field( wp_unslash( $_POST[ $key ] ?? '' ) );
		return in_array( $val, $allowed, true ) ? $val : '';
	};

	$san_group = static function ( string $key, array $allowed ): array {
		$raw = is_array( $_POST[ $key ] ?? null ) ? $_POST[ $key ] : [];
		return array_values( array_intersect( array_map( 'sanitize_text_field', $raw ), $allowed ) );
	};

	// ── Metabox 1: Basisdaten ──
	update_post_meta( $post_id, '_fge_public_golfclub_name',     sanitize_text_field( wp_unslash( $_POST['fge_public_golfclub_name'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_legal_operator_name',      sanitize_text_field( wp_unslash( $_POST['fge_legal_operator_name'] ?? '' ) ) );
	$new_pstatus = $san_select( 'fge_partner_status', $allowed_statuses );
	$old_pstatus = (string) get_post_meta( $post_id, '_fge_partner_status', true );
	update_post_meta( $post_id, '_fge_partner_status', $new_pstatus );
	// Auch bei Status-Wechsel über die Metabox den Partner informieren.
	if ( function_exists( 'fge_notify_partner_status_change' ) && '' !== $new_pstatus ) {
		fge_notify_partner_status_change( $post_id, $old_pstatus, $new_pstatus );
	}
	update_post_meta( $post_id, '_fge_partner_since',            sanitize_text_field( wp_unslash( $_POST['fge_partner_since'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_public_short_description', sanitize_textarea_field( wp_unslash( $_POST['fge_public_short_description'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_rating', max( 0.0, min( 5.0, (float) str_replace( ',', '.', (string) wp_unslash( $_POST['fge_rating'] ?? '' ) ) ) ) );
	update_post_meta( $post_id, '_fge_internal_note',            sanitize_textarea_field( wp_unslash( $_POST['fge_internal_note'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_review_quote',             sanitize_textarea_field( wp_unslash( $_POST['fge_review_quote'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_review_author',            sanitize_text_field( wp_unslash( $_POST['fge_review_author'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_review_role',              sanitize_text_field( wp_unslash( $_POST['fge_review_role'] ?? '' ) ) );

	// ── Metabox 2: Standort ──
	update_post_meta( $post_id, '_fge_street',        sanitize_text_field( wp_unslash( $_POST['fge_street'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_house_number',  sanitize_text_field( wp_unslash( $_POST['fge_house_number'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_postal_code',   sanitize_text_field( wp_unslash( $_POST['fge_postal_code'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_city',          sanitize_text_field( wp_unslash( $_POST['fge_city'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_federal_state', $san_select( 'fge_federal_state', $allowed_federal_states ) );
	update_post_meta( $post_id, '_fge_free_region',   sanitize_text_field( wp_unslash( $_POST['fge_free_region'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_website_url',   esc_url_raw( wp_unslash( $_POST['fge_website_url'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_google_maps_url', esc_url_raw( wp_unslash( $_POST['fge_google_maps_url'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_directions_text', sanitize_textarea_field( wp_unslash( $_POST['fge_directions_text'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_poi_car',     sanitize_text_field( wp_unslash( $_POST['fge_poi_car'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_poi_train',   sanitize_text_field( wp_unslash( $_POST['fge_poi_train'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_poi_parking', sanitize_text_field( wp_unslash( $_POST['fge_poi_parking'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_poi_hotel',   sanitize_text_field( wp_unslash( $_POST['fge_poi_hotel'] ?? '' ) ) );

	// ── Profil & Ausstattung (Katalog-Modell, includes/catalogs.php) ──
	$gt = sanitize_text_field( wp_unslash( $_POST['fge_golf_type'] ?? '' ) );
	update_post_meta( $post_id, '_fge_golf_type', array_key_exists( $gt, fge_catalog_golf_types() ) ? $gt : '' );

	$infra_in = is_array( $_POST['fge_infra'] ?? null ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['fge_infra'] ) ) : [];
	update_post_meta( $post_id, '_fge_infra', array_values( array_intersect( $infra_in, fge_catalog_infra_ids() ) ) );

	$cap_in  = is_array( $_POST['fge_cap'] ?? null ) ? wp_unslash( $_POST['fge_cap'] ) : [];
	$cap_out = [];
	foreach ( fge_catalog_cap_keys() as $ck ) {
		$cv = isset( $cap_in[ $ck ] ) ? absint( $cap_in[ $ck ] ) : 0;
		if ( $cv > 0 ) {
			$cap_out[ $ck ] = $cv;
		}
	}
	update_post_meta( $post_id, '_fge_cap', $cap_out );

	update_post_meta( $post_id, '_fge_poi_shuttle',       sanitize_text_field( wp_unslash( $_POST['fge_poi_shuttle'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_arrival_estation',  isset( $_POST['fge_arrival_estation'] ) ? '1' : '0' );

	// ── Metabox 3: Ansprechpartner ──
	$contact_prefixes = [ 'main', 'event', 'gastro', 'golf_school', 'billing' ];
	foreach ( $contact_prefixes as $prefix ) {
		update_post_meta( $post_id, '_fge_' . $prefix . '_contact_name',  sanitize_text_field( wp_unslash( $_POST[ 'fge_' . $prefix . '_contact_name' ] ?? '' ) ) );
		update_post_meta( $post_id, '_fge_' . $prefix . '_contact_email', sanitize_email( wp_unslash( $_POST[ 'fge_' . $prefix . '_contact_email' ] ?? '' ) ) );
		update_post_meta( $post_id, '_fge_' . $prefix . '_contact_phone', sanitize_text_field( wp_unslash( $_POST[ 'fge_' . $prefix . '_contact_phone' ] ?? '' ) ) );
	}
	update_post_meta( $post_id, '_fge_main_contact_role', sanitize_text_field( wp_unslash( $_POST['fge_main_contact_role'] ?? '' ) ) );

	// ── Profil-Zusatz: Weitere Ausstattung (Freitext, im Profil-Modell) ──
	update_post_meta( $post_id, '_fge_additional_equipment', sanitize_textarea_field( wp_unslash( $_POST['fge_additional_equipment'] ?? '' ) ) );

	// ── Verfügbarkeit & Formate ──
	update_post_meta( $post_id, '_fge_preferred_event_days',  $san_group( 'fge_preferred_event_days', $allowed_event_days ) );
	update_post_meta( $post_id, '_fge_evening_events_possible',  isset( $_POST['fge_evening_events_possible'] ) ? 1 : 0 );
	update_post_meta( $post_id, '_fge_min_lead_time_days',       absint( $_POST['fge_min_lead_time_days'] ?? 0 ) );
	update_post_meta( $post_id, '_fge_season',                $san_select( 'fge_season', $allowed_seasons ) );
	update_post_meta( $post_id, '_fge_individual_availability_check', isset( $_POST['fge_individual_availability_check'] ) ? 1 : 0 );
	$allowed_formats = array_keys( fge_get_event_format_options() );
	update_post_meta( $post_id, '_fge_event_formats', $san_group( 'fge_event_formats', $allowed_formats ) );

	// ── Abrechnung (intern) ──
	update_post_meta( $post_id, '_fge_tax_number_or_vat_id',      sanitize_text_field( wp_unslash( $_POST['fge_tax_number_or_vat_id'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_internal_billing_note',     sanitize_textarea_field( wp_unslash( $_POST['fge_internal_billing_note'] ?? '' ) ) );

	// ── Partnerportal ──
	update_post_meta( $post_id, '_fge_partner_portal_enabled',  isset( $_POST['fge_partner_portal_enabled'] ) ? 1 : 0 );
	update_post_meta( $post_id, '_fge_assigned_wp_user_id',     absint( $_POST['fge_assigned_wp_user_id'] ?? 0 ) );

	// ── Medien ──
	update_post_meta( $post_id, '_fge_logo_attachment_id',      absint( $_POST['fge_logo_attachment_id'] ?? 0 ) );
	update_post_meta( $post_id, '_fge_hero_image_attachment_id', absint( $_POST['fge_hero_image_attachment_id'] ?? 0 ) );
	update_post_meta( $post_id, '_fge_gallery_attachment_ids',  sanitize_text_field( wp_unslash( $_POST['fge_gallery_attachment_ids'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_image_rights_confirmed',  isset( $_POST['fge_image_rights_confirmed'] ) ? 1 : 0 );
	update_post_meta( $post_id, '_fge_image_rights_note',       sanitize_textarea_field( wp_unslash( $_POST['fge_image_rights_note'] ?? '' ) ) );

	// ── Tracking Defaults (set once on first save) ──
	if ( get_post_meta( $post_id, '_fge_published_events_count', true ) === '' ) {
		add_post_meta( $post_id, '_fge_published_events_count', 0, true );
	}
	if ( get_post_meta( $post_id, '_fge_event_views_total', true ) === '' ) {
		add_post_meta( $post_id, '_fge_event_views_total', 0, true );
	}
	if ( get_post_meta( $post_id, '_fge_requests_total', true ) === '' ) {
		add_post_meta( $post_id, '_fge_requests_total', 0, true );
	}
	if ( get_post_meta( $post_id, '_fge_bookings_total', true ) === '' ) {
		add_post_meta( $post_id, '_fge_bookings_total', 0, true );
	}
}
add_action( 'save_post', 'fge_save_partner_fields' );
