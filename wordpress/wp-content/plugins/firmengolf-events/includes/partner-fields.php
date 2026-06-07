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

	add_meta_box( 'fge_pmb_basisdaten',      'Golfplatz Basisdaten',          'fge_render_pmb_basisdaten',      $screen, 'normal', 'high' );
	add_meta_box( 'fge_pmb_standort',         'Standort und Region',           'fge_render_pmb_standort',         $screen, 'normal', 'default' );
	add_meta_box( 'fge_pmb_ansprechpartner',  'Ansprechpartner',               'fge_render_pmb_ansprechpartner',  $screen, 'normal', 'default' );
	add_meta_box( 'fge_pmb_ausstattung',      'Event Ausstattung',             'fge_render_pmb_ausstattung',      $screen, 'normal', 'default' );
	add_meta_box( 'fge_pmb_kapazitaeten',     'Event Kapazitäten',             'fge_render_pmb_kapazitaeten',     $screen, 'normal', 'default' );
	add_meta_box( 'fge_pmb_preis',            'Preis und Abrechnung Basis',    'fge_render_pmb_preis',            $screen, 'normal', 'default' );
	add_meta_box( 'fge_pmb_portal',           'Partnerportal Rechte',          'fge_render_pmb_portal',           $screen, 'normal', 'default' );
	add_meta_box( 'fge_pmb_medien',           'Medien und Bildrechte',         'fge_render_pmb_medien',           $screen, 'normal', 'default' );
	add_meta_box( 'fge_pmb_tracking',         'Tracking Basis',                'fge_render_pmb_tracking',         $screen, 'side',   'default' );
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
			<th scope="row"><label for="fge_public_golfclub_name">Golfplatz Name öffentlich</label></th>
			<td><input type="text" id="fge_public_golfclub_name" name="fge_public_golfclub_name"
			           value="<?php echo esc_attr( $public_golfclub_name ); ?>" class="regular-text"></td>
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
			<th scope="row"><label for="fge_legal_operator_name">Rechtlicher Betreibername</label></th>
			<td><input type="text" id="fge_legal_operator_name" name="fge_legal_operator_name"
			           value="<?php echo esc_attr( $legal_operator_name ); ?>" class="regular-text"></td>
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
			<td><input type="date" id="fge_partner_since" name="fge_partner_since"
			           value="<?php echo esc_attr( $partner_since ); ?>"></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_public_short_description">Kurzbeschreibung öffentlich</label></th>
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

// ── Render: Event Ausstattung ─────────────────────────────────────────────────

function fge_render_pmb_ausstattung( WP_Post $post ) {
	$checkboxes = [
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
		'has_gastronomy'              => 'Restaurant / Gastronomie',
		'has_breakfast'               => 'Frühstück',
		'has_lunch'                   => 'Lunch',
		'has_dinner'                  => 'Abendessen',
		'has_terrace'                 => 'Terrasse / Außenbereich',
		'has_parking'                 => 'Parkplätze',
		'has_shuttle_access'          => 'Shuttle möglich',
		'has_indoor_or_simulator'     => 'Indoor / Simulator',
		'has_branding_options'        => 'Branding Möglichkeiten',
		'has_tournament_organization' => 'Turnierorganisation',
		'has_bad_weather_alternative' => 'Schlechtwetter Alternative',
		'has_locker_room'             => 'Umkleiden & Duschen',
	];
	$additional_equipment = get_post_meta( $post->ID, '_fge_additional_equipment', true );
	?>
	<table class="form-table">
		<?php foreach ( $checkboxes as $key => $label ) :
			$val = get_post_meta( $post->ID, '_fge_' . $key, true );
			?>
			<tr>
				<th scope="row"><?php echo esc_html( $label ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="fge_<?php echo esc_attr( $key ); ?>" value="1"
						       <?php checked( $val, '1' ); ?>>
						<?php echo esc_html( $label ); ?>
					</label>
				</td>
			</tr>
		<?php endforeach; ?>
		<tr>
			<th scope="row"><label for="fge_additional_equipment">Weitere Ausstattung</label></th>
			<td><textarea id="fge_additional_equipment" name="fge_additional_equipment" rows="3" class="large-text"><?php echo esc_textarea( $additional_equipment ); ?></textarea></td>
		</tr>
	</table>
	<?php
}

// ── Render: Event Kapazitäten ─────────────────────────────────────────────────

function fge_render_pmb_kapazitaeten( WP_Post $post ) {
	$participants_min_general   = get_post_meta( $post->ID, '_fge_participants_min_general', true );
	$participants_max_general   = get_post_meta( $post->ID, '_fge_participants_max_general', true );
	$meeting_room_capacity      = get_post_meta( $post->ID, '_fge_meeting_room_capacity', true );
	$gastro_capacity            = get_post_meta( $post->ID, '_fge_gastro_capacity', true );
	$gastro_outdoor_capacity    = get_post_meta( $post->ID, '_fge_gastro_outdoor_capacity', true );
	$range_group_capacity       = get_post_meta( $post->ID, '_fge_range_group_capacity', true );
	$putting_green_capacity     = get_post_meta( $post->ID, '_fge_putting_green_capacity', true );
	$short_game_capacity        = get_post_meta( $post->ID, '_fge_short_game_capacity', true );
	$golf_teacher_capacity      = get_post_meta( $post->ID, '_fge_golf_teacher_capacity', true );
	$parking_count              = get_post_meta( $post->ID, '_fge_parking_count', true );
	$preferred_event_days       = (array) get_post_meta( $post->ID, '_fge_preferred_event_days', true );
	$preferred_event_times      = (array) get_post_meta( $post->ID, '_fge_preferred_event_times', true );
	$season                     = get_post_meta( $post->ID, '_fge_season', true );
	$weekend_events_possible    = get_post_meta( $post->ID, '_fge_weekend_events_possible', true );
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
	$event_times = [
		'morning'    => 'Vormittag',
		'afternoon'  => 'Nachmittag',
		'after_work' => 'After Work',
		'full_day'   => 'Ganztägig',
	];
	$seasons = [
		'year_round'       => 'Ganzjährig',
		'march_to_october' => 'März bis Oktober',
		'april_to_october' => 'April bis Oktober',
		'on_request'       => 'Auf Anfrage',
	];
	$format_tiers = fge_get_event_formats();
	?>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="fge_participants_min_general">Teilnehmer Minimum</label></th>
			<td><input type="number" id="fge_participants_min_general" name="fge_participants_min_general"
			           value="<?php echo esc_attr( $participants_min_general ); ?>" min="0" step="1" style="width:110px;"></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_participants_max_general">Teilnehmer Maximum</label></th>
			<td><input type="number" id="fge_participants_max_general" name="fge_participants_max_general"
			           value="<?php echo esc_attr( $participants_max_general ); ?>" min="0" step="1" style="width:110px;"></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_range_group_capacity">Kapazität Driving Range</label></th>
			<td><input type="number" id="fge_range_group_capacity" name="fge_range_group_capacity"
			           value="<?php echo esc_attr( $range_group_capacity ); ?>" min="0" step="1" style="width:110px;"></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_putting_green_capacity">Kapazität Putting Green</label></th>
			<td><input type="number" id="fge_putting_green_capacity" name="fge_putting_green_capacity"
			           value="<?php echo esc_attr( $putting_green_capacity ); ?>" min="0" step="1" style="width:110px;"></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_short_game_capacity">Kapazität Kurzspielbereich</label></th>
			<td><input type="number" id="fge_short_game_capacity" name="fge_short_game_capacity"
			           value="<?php echo esc_attr( $short_game_capacity ); ?>" min="0" step="1" style="width:110px;"></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_meeting_room_capacity">Kapazität Meetingraum</label></th>
			<td><input type="number" id="fge_meeting_room_capacity" name="fge_meeting_room_capacity"
			           value="<?php echo esc_attr( $meeting_room_capacity ); ?>" min="0" step="1" style="width:110px;"></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_gastro_capacity">Kapazität Restaurant innen</label></th>
			<td><input type="number" id="fge_gastro_capacity" name="fge_gastro_capacity"
			           value="<?php echo esc_attr( $gastro_capacity ); ?>" min="0" step="1" style="width:110px;"></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_gastro_outdoor_capacity">Kapazität Restaurant außen</label></th>
			<td><input type="number" id="fge_gastro_outdoor_capacity" name="fge_gastro_outdoor_capacity"
			           value="<?php echo esc_attr( $gastro_outdoor_capacity ); ?>" min="0" step="1" style="width:110px;"></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_golf_teacher_capacity">Anzahl Golflehrer</label></th>
			<td><input type="number" id="fge_golf_teacher_capacity" name="fge_golf_teacher_capacity"
			           value="<?php echo esc_attr( $golf_teacher_capacity ); ?>" min="0" step="1" style="width:110px;"></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_parking_count">Anzahl Parkplätze</label></th>
			<td><input type="number" id="fge_parking_count" name="fge_parking_count"
			           value="<?php echo esc_attr( $parking_count ); ?>" min="0" step="1" style="width:110px;"></td>
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
			<th scope="row">Wochenend-Events möglich</th>
			<td><label><input type="checkbox" name="fge_weekend_events_possible" value="1"
			                  <?php checked( $weekend_events_possible, '1' ); ?>> Ja</label></td>
		</tr>
		<tr>
			<th scope="row">Abend-Events möglich</th>
			<td><label><input type="checkbox" name="fge_evening_events_possible" value="1"
			                  <?php checked( $evening_events_possible, '1' ); ?>> Ja</label></td>
		</tr>
		<tr>
			<th scope="row">Bevorzugte Eventzeiten</th>
			<td>
				<?php foreach ( $event_times as $val => $label ) : ?>
					<label style="display:inline-block;margin-right:14px;">
						<input type="checkbox" name="fge_preferred_event_times[]"
						       value="<?php echo esc_attr( $val ); ?>"
						       <?php checked( in_array( $val, $preferred_event_times, true ) ); ?>>
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
			</td>
		</tr>
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
	$markup_raw              = get_post_meta( $post->ID, '_fge_default_markup_percent', true );
	$markup                  = ( $markup_raw !== '' ) ? $markup_raw : '20';
	$billing_method_internal = get_post_meta( $post->ID, '_fge_billing_method_internal', true );
	$bank_details_available  = get_post_meta( $post->ID, '_fge_bank_details_available', true );
	$vat_required            = get_post_meta( $post->ID, '_fge_vat_required', true );
	$tax_number_or_vat_id    = get_post_meta( $post->ID, '_fge_tax_number_or_vat_id', true );
	$internal_billing_note   = get_post_meta( $post->ID, '_fge_internal_billing_note', true );

	$billing_methods = [
		'manual_clarification'                 => 'Manuelle Klärung',
		'invoice_from_partner_to_firmengolf'   => 'Rechnung Partner an Firmengolf',
		'credit_note'                          => 'Gutschrift',
		'other'                                => 'Sonstiges',
	];
	?>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="fge_default_markup_percent">Standard Firmengolf Aufschlag %</label></th>
			<td><input type="number" id="fge_default_markup_percent" name="fge_default_markup_percent"
			           value="<?php echo esc_attr( $markup ); ?>" step="0.01" min="0" style="width:110px;"></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_billing_method_internal">Abrechnungsmethode intern</label></th>
			<td>
				<select id="fge_billing_method_internal" name="fge_billing_method_internal">
					<option value="">— bitte wählen —</option>
					<?php foreach ( $billing_methods as $val => $label ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $billing_method_internal, $val ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row">Bankdaten hinterlegt</th>
			<td><label><input type="checkbox" name="fge_bank_details_available" value="1"
			                  <?php checked( $bank_details_available, '1' ); ?>> Bankdaten hinterlegt</label></td>
		</tr>
		<tr>
			<th scope="row">Umsatzsteuerpflichtig</th>
			<td><label><input type="checkbox" name="fge_vat_required" value="1"
			                  <?php checked( $vat_required, '1' ); ?>> Umsatzsteuerpflichtig</label></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_tax_number_or_vat_id">Steuernummer / USt-ID</label></th>
			<td>
				<input type="text" id="fge_tax_number_or_vat_id" name="fge_tax_number_or_vat_id"
				       value="<?php echo esc_attr( $tax_number_or_vat_id ); ?>" class="regular-text">
				<p class="description">Optional.</p>
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
	$can_create_events            = get_post_meta( $post->ID, '_fge_can_create_events', true );
	$can_edit_events              = get_post_meta( $post->ID, '_fge_can_edit_events', true );
	$can_view_requests            = get_post_meta( $post->ID, '_fge_can_view_requests', true );
	$can_view_statistics          = get_post_meta( $post->ID, '_fge_can_view_statistics', true );
	$auto_publication_allowed     = get_post_meta( $post->ID, '_fge_automatic_publication_allowed', true );
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
				<p class="description">User-ID des zugeordneten WP-Benutzers. Später als Suche.</p>
			</td>
		</tr>
		<tr>
			<th scope="row">Darf Events erstellen</th>
			<td><label><input type="checkbox" name="fge_can_create_events" value="1"
			                  <?php checked( $can_create_events, '1' ); ?>> Darf Events erstellen</label></td>
		</tr>
		<tr>
			<th scope="row">Darf Events bearbeiten</th>
			<td><label><input type="checkbox" name="fge_can_edit_events" value="1"
			                  <?php checked( $can_edit_events, '1' ); ?>> Darf Events bearbeiten</label></td>
		</tr>
		<tr>
			<th scope="row">Darf Anfragen sehen</th>
			<td><label><input type="checkbox" name="fge_can_view_requests" value="1"
			                  <?php checked( $can_view_requests, '1' ); ?>> Darf Anfragen sehen</label></td>
		</tr>
		<tr>
			<th scope="row">Darf Statistiken sehen</th>
			<td><label><input type="checkbox" name="fge_can_view_statistics" value="1"
			                  <?php checked( $can_view_statistics, '1' ); ?>> Darf Statistiken sehen</label></td>
		</tr>
		<tr>
			<th scope="row">Automatische Veröffentlichung erlaubt</th>
			<td>
				<label><input type="checkbox" name="fge_automatic_publication_allowed" value="1"
				              <?php checked( $auto_publication_allowed, '1' ); ?>> Automatische Veröffentlichung erlaubt</label>
				<p class="description">Im MVP standardmäßig nicht gesetzt.</p>
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
	$allowed_billing_methods  = [
		'manual_clarification', 'invoice_from_partner_to_firmengolf', 'credit_note', 'other',
	];
	$allowed_event_days       = [ 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' ];
	$allowed_event_times      = [ 'morning', 'afternoon', 'after_work', 'full_day' ];

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
	update_post_meta( $post_id, '_fge_partner_status',           $san_select( 'fge_partner_status', $allowed_statuses ) );
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

	// ── Metabox 3: Ansprechpartner ──
	$contact_prefixes = [ 'main', 'event', 'gastro', 'golf_school', 'billing' ];
	foreach ( $contact_prefixes as $prefix ) {
		update_post_meta( $post_id, '_fge_' . $prefix . '_contact_name',  sanitize_text_field( wp_unslash( $_POST[ 'fge_' . $prefix . '_contact_name' ] ?? '' ) ) );
		update_post_meta( $post_id, '_fge_' . $prefix . '_contact_email', sanitize_email( wp_unslash( $_POST[ 'fge_' . $prefix . '_contact_email' ] ?? '' ) ) );
		update_post_meta( $post_id, '_fge_' . $prefix . '_contact_phone', sanitize_text_field( wp_unslash( $_POST[ 'fge_' . $prefix . '_contact_phone' ] ?? '' ) ) );
	}
	update_post_meta( $post_id, '_fge_main_contact_role', sanitize_text_field( wp_unslash( $_POST['fge_main_contact_role'] ?? '' ) ) );

	// ── Metabox 4: Ausstattung ──
	$ausstattung_keys = [
		'has_driving_range', 'has_putting_green', 'has_short_game_area', 'has_short_course',
		'has_9hole_course', 'has_18hole_course', 'has_rental_clubs', 'has_range_balls',
		'has_golf_teacher', 'has_meeting_room', 'has_gastronomy', 'has_breakfast', 'has_lunch',
		'has_dinner', 'has_terrace', 'has_parking', 'has_shuttle_access',
		'has_indoor_or_simulator', 'has_branding_options', 'has_tournament_organization',
		'has_bad_weather_alternative', 'has_locker_room',
	];
	foreach ( $ausstattung_keys as $key ) {
		update_post_meta( $post_id, '_fge_' . $key, isset( $_POST[ 'fge_' . $key ] ) ? 1 : 0 );
	}
	update_post_meta( $post_id, '_fge_additional_equipment', sanitize_textarea_field( wp_unslash( $_POST['fge_additional_equipment'] ?? '' ) ) );

	// ── Metabox 5: Kapazitäten ──
	update_post_meta( $post_id, '_fge_participants_min_general', absint( $_POST['fge_participants_min_general'] ?? 0 ) );
	update_post_meta( $post_id, '_fge_participants_max_general', absint( $_POST['fge_participants_max_general'] ?? 0 ) );
	update_post_meta( $post_id, '_fge_range_group_capacity',     absint( $_POST['fge_range_group_capacity'] ?? 0 ) );
	update_post_meta( $post_id, '_fge_putting_green_capacity',   absint( $_POST['fge_putting_green_capacity'] ?? 0 ) );
	update_post_meta( $post_id, '_fge_short_game_capacity',      absint( $_POST['fge_short_game_capacity'] ?? 0 ) );
	update_post_meta( $post_id, '_fge_meeting_room_capacity',    absint( $_POST['fge_meeting_room_capacity'] ?? 0 ) );
	update_post_meta( $post_id, '_fge_gastro_capacity',          absint( $_POST['fge_gastro_capacity'] ?? 0 ) );
	update_post_meta( $post_id, '_fge_gastro_outdoor_capacity',  absint( $_POST['fge_gastro_outdoor_capacity'] ?? 0 ) );
	update_post_meta( $post_id, '_fge_golf_teacher_capacity',    absint( $_POST['fge_golf_teacher_capacity'] ?? 0 ) );
	update_post_meta( $post_id, '_fge_parking_count',            absint( $_POST['fge_parking_count'] ?? 0 ) );
	update_post_meta( $post_id, '_fge_preferred_event_days',  $san_group( 'fge_preferred_event_days', $allowed_event_days ) );
	update_post_meta( $post_id, '_fge_weekend_events_possible',  isset( $_POST['fge_weekend_events_possible'] ) ? 1 : 0 );
	update_post_meta( $post_id, '_fge_evening_events_possible',  isset( $_POST['fge_evening_events_possible'] ) ? 1 : 0 );
	update_post_meta( $post_id, '_fge_preferred_event_times', $san_group( 'fge_preferred_event_times', $allowed_event_times ) );
	update_post_meta( $post_id, '_fge_min_lead_time_days',       absint( $_POST['fge_min_lead_time_days'] ?? 0 ) );
	update_post_meta( $post_id, '_fge_season',                $san_select( 'fge_season', $allowed_seasons ) );
	update_post_meta( $post_id, '_fge_individual_availability_check', isset( $_POST['fge_individual_availability_check'] ) ? 1 : 0 );
	$allowed_formats = array_keys( fge_get_event_format_options() );
	update_post_meta( $post_id, '_fge_event_formats', $san_group( 'fge_event_formats', $allowed_formats ) );

	// ── Metabox 6: Preis ──
	$markup_raw = trim( wp_unslash( $_POST['fge_default_markup_percent'] ?? '' ) );
	$markup     = ( $markup_raw !== '' ) ? (float) str_replace( ',', '.', $markup_raw ) : 20.0;
	update_post_meta( $post_id, '_fge_default_markup_percent',    $markup );
	update_post_meta( $post_id, '_fge_billing_method_internal',   $san_select( 'fge_billing_method_internal', $allowed_billing_methods ) );
	update_post_meta( $post_id, '_fge_bank_details_available',    isset( $_POST['fge_bank_details_available'] ) ? 1 : 0 );
	update_post_meta( $post_id, '_fge_vat_required',              isset( $_POST['fge_vat_required'] ) ? 1 : 0 );
	update_post_meta( $post_id, '_fge_tax_number_or_vat_id',      sanitize_text_field( wp_unslash( $_POST['fge_tax_number_or_vat_id'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_internal_billing_note',     sanitize_textarea_field( wp_unslash( $_POST['fge_internal_billing_note'] ?? '' ) ) );

	// ── Metabox 7: Portal Rechte ──
	update_post_meta( $post_id, '_fge_partner_portal_enabled',          isset( $_POST['fge_partner_portal_enabled'] ) ? 1 : 0 );
	update_post_meta( $post_id, '_fge_assigned_wp_user_id',             absint( $_POST['fge_assigned_wp_user_id'] ?? 0 ) );
	update_post_meta( $post_id, '_fge_can_create_events',               isset( $_POST['fge_can_create_events'] ) ? 1 : 0 );
	update_post_meta( $post_id, '_fge_can_edit_events',                 isset( $_POST['fge_can_edit_events'] ) ? 1 : 0 );
	update_post_meta( $post_id, '_fge_can_view_requests',               isset( $_POST['fge_can_view_requests'] ) ? 1 : 0 );
	update_post_meta( $post_id, '_fge_can_view_statistics',             isset( $_POST['fge_can_view_statistics'] ) ? 1 : 0 );
	update_post_meta( $post_id, '_fge_automatic_publication_allowed',   isset( $_POST['fge_automatic_publication_allowed'] ) ? 1 : 0 );

	// ── Metabox 8: Medien ──
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
