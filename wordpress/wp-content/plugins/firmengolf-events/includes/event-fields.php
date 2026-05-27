<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Registration ─────────────────────────────────────────────────────────────

function fge_register_event_metaboxes() {
	$screen = 'firmengolf_event';

	add_meta_box( 'fge_mb_basisdaten', 'Firmenevent Basisdaten',    'fge_render_mb_basisdaten', $screen, 'normal', 'high' );
	add_meta_box( 'fge_mb_rahmen',     'Event Rahmen',              'fge_render_mb_rahmen',     $screen, 'normal', 'default' );
	add_meta_box( 'fge_mb_leistungen', 'Leistungen',                'fge_render_mb_leistungen', $screen, 'normal', 'default' );
	add_meta_box( 'fge_mb_preislogik', 'Preislogik',                'fge_render_mb_preislogik', $screen, 'normal', 'default' );
	add_meta_box( 'fge_mb_anfrage',    'Anfrage und Verfügbarkeit', 'fge_render_mb_anfrage',    $screen, 'normal', 'default' );
	add_meta_box( 'fge_mb_seo',        'SEO Basis',                 'fge_render_mb_seo',        $screen, 'normal', 'default' );
	add_meta_box( 'fge_mb_tracking',   'Tracking Basis',            'fge_render_mb_tracking',   $screen, 'side',   'default' );
}
add_action( 'add_meta_boxes', 'fge_register_event_metaboxes' );

// ── Render: Firmenevent Basisdaten ───────────────────────────────────────────

function fge_render_mb_basisdaten( WP_Post $post ) {
	wp_nonce_field( 'fge_event_fields', 'fge_event_nonce' );

	$event_type          = get_post_meta( $post->ID, '_fge_event_type', true );
	$provider_type       = get_post_meta( $post->ID, '_fge_provider_type', true );
	$assigned_partner_id = get_post_meta( $post->ID, '_fge_assigned_partner_id', true );
	$event_status        = get_post_meta( $post->ID, '_fge_event_status', true );
	$card_description    = get_post_meta( $post->ID, '_fge_card_description', true );

	$event_types = [
		'teamevent'      => 'Teamevent',
		'kundenevent'    => 'Kundenevent',
		'gesundheitstag' => 'Gesundheitstag',
		'offsite'        => 'Offsite',
		'firmenturnier'  => 'Firmenturnier',
		'anderes_event'  => 'Anderes Event',
	];
	$provider_types = [
		'firmengolf'        => 'Firmengolf',
		'golfplatz_partner' => 'Golfplatz Partner',
	];
	$statuses = fge_get_statuses( 'event' );
	?>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="fge_event_type">Eventart</label></th>
			<td>
				<select id="fge_event_type" name="fge_event_type">
					<option value="">— bitte wählen —</option>
					<?php foreach ( $event_types as $val => $label ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $event_type, $val ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_provider_type">Anbieter Typ</label></th>
			<td>
				<select id="fge_provider_type" name="fge_provider_type">
					<option value="">— bitte wählen —</option>
					<?php foreach ( $provider_types as $val => $label ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $provider_type, $val ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_assigned_partner_id">Zugeordneter Golfplatz</label></th>
			<td>
				<input type="number" id="fge_assigned_partner_id" name="fge_assigned_partner_id"
				       value="<?php echo esc_attr( $assigned_partner_id ); ?>" min="0" step="1" style="width:110px;">
				<p class="description">Post-ID des zugeordneten Golfplatz-Partners (optional).</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_event_status">Status</label></th>
			<td>
				<select id="fge_event_status" name="fge_event_status">
					<option value="">— bitte wählen —</option>
					<?php foreach ( $statuses as $val ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $event_status, $val ); ?>><?php echo esc_html( $val ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_card_description">Kurzbeschreibung</label></th>
			<td>
				<textarea id="fge_card_description" name="fge_card_description" rows="3" class="large-text"><?php echo esc_textarea( $card_description ); ?></textarea>
				<p class="description">Wird später auf Eventkarten angezeigt.</p>
			</td>
		</tr>
	</table>
	<?php
}

// ── Render: Event Rahmen ─────────────────────────────────────────────────────

function fge_render_mb_rahmen( WP_Post $post ) {
	$participants_min   = get_post_meta( $post->ID, '_fge_participants_min', true );
	$participants_max   = get_post_meta( $post->ID, '_fge_participants_max', true );
	$duration           = get_post_meta( $post->ID, '_fge_duration', true );
	$season             = get_post_meta( $post->ID, '_fge_season', true );
	$available_weekdays = (array) get_post_meta( $post->ID, '_fge_available_weekdays', true );
	$region             = get_post_meta( $post->ID, '_fge_region', true );
	$event_location     = get_post_meta( $post->ID, '_fge_event_location', true );

	$weekdays = [
		'monday'    => 'Montag',
		'tuesday'   => 'Dienstag',
		'wednesday' => 'Mittwoch',
		'thursday'  => 'Donnerstag',
		'friday'    => 'Freitag',
		'saturday'  => 'Samstag',
		'sunday'    => 'Sonntag',
	];
	?>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="fge_participants_min">Teilnehmer Minimum</label></th>
			<td><input type="number" id="fge_participants_min" name="fge_participants_min"
			           value="<?php echo esc_attr( $participants_min ); ?>" min="0" step="1" style="width:110px;"></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_participants_max">Teilnehmer Maximum</label></th>
			<td><input type="number" id="fge_participants_max" name="fge_participants_max"
			           value="<?php echo esc_attr( $participants_max ); ?>" min="0" step="1" style="width:110px;"></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_duration">Dauer</label></th>
			<td>
				<input type="text" id="fge_duration" name="fge_duration"
				       value="<?php echo esc_attr( $duration ); ?>" class="regular-text">
				<p class="description">z.B. 2 Stunden, halber Tag, ganzer Tag</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_season">Saison / Zeitraum</label></th>
			<td>
				<input type="text" id="fge_season" name="fge_season"
				       value="<?php echo esc_attr( $season ); ?>" class="regular-text">
				<p class="description">z.B. April bis Oktober, ganzjährig, auf Anfrage</p>
			</td>
		</tr>
		<tr>
			<th scope="row">Verfügbare Wochentage</th>
			<td>
				<?php foreach ( $weekdays as $val => $label ) : ?>
					<label style="display:inline-block;margin-right:14px;">
						<input type="checkbox" name="fge_available_weekdays[]"
						       value="<?php echo esc_attr( $val ); ?>"
						       <?php checked( in_array( $val, $available_weekdays, true ) ); ?>>
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_region">Region</label></th>
			<td><input type="text" id="fge_region" name="fge_region"
			           value="<?php echo esc_attr( $region ); ?>" class="regular-text"></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_event_location">Ort</label></th>
			<td><input type="text" id="fge_event_location" name="fge_event_location"
			           value="<?php echo esc_attr( $event_location ); ?>" class="regular-text"></td>
		</tr>
	</table>
	<?php
}

// ── Render: Leistungen ───────────────────────────────────────────────────────

function fge_render_mb_leistungen( WP_Post $post ) {
	$checkboxes = [
		'has_golf_teacher'      => 'Golflehrer',
		'has_range_usage'       => 'Range Nutzung inklusive',
		'has_rental_clubs'      => 'Leihschläger inklusive',
		'has_range_balls'       => 'Rangebälle inklusive',
		'has_putting_shortgame' => 'Putting / Kurzspiel inklusive',
		'has_meeting_room'      => 'Meetingraum',
		'has_breakfast'         => 'Frühstück',
		'has_lunch'             => 'Lunch',
		'has_dinner'            => 'Abendessen',
		'has_shuttle'           => 'Shuttle möglich',
		'has_branding'          => 'Branding / individuelle Anpassung möglich',
	];
	$additional_services = get_post_meta( $post->ID, '_fge_additional_services', true );
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
			<th scope="row"><label for="fge_additional_services">Weitere Leistungen</label></th>
			<td><textarea id="fge_additional_services" name="fge_additional_services" rows="3" class="large-text"><?php echo esc_textarea( $additional_services ); ?></textarea></td>
		</tr>
	</table>
	<?php
}

// ── Render: Preislogik ───────────────────────────────────────────────────────

function fge_render_mb_preislogik( WP_Post $post ) {
	$pricing_mode       = get_post_meta( $post->ID, '_fge_pricing_mode', true );
	$markup_raw         = get_post_meta( $post->ID, '_fge_firmengolf_markup_percent', true );
	$markup             = ( $markup_raw !== '' ) ? $markup_raw : '20';
	$sale_price_net     = get_post_meta( $post->ID, '_fge_sale_price_net', true );
	$public_price_label = get_post_meta( $post->ID, '_fge_public_price_label', true );
	$price_note         = get_post_meta( $post->ID, '_fge_price_note', true );
	$price_per_person   = get_post_meta( $post->ID, '_fge_price_per_person_possible', true );
	$package_possible   = get_post_meta( $post->ID, '_fge_package_price_possible', true );

	$price_fields = [
		'purchase_price_package_net'          => 'Einkaufspreis Gesamtpaket netto',
		'purchase_price_meeting_room_hour_net' => 'Einkaufspreis Meetingraum netto/Stunde',
		'purchase_price_range_net'            => 'Einkaufspreis Range netto',
		'purchase_price_trainer_hour_net'     => 'Einkaufspreis Trainerstunde netto',
		'purchase_price_breakfast_net'        => 'Einkaufspreis Frühstück netto',
		'purchase_price_lunch_net'            => 'Einkaufspreis Lunch netto',
		'purchase_price_dinner_net'           => 'Einkaufspreis Abendessen netto',
		'purchase_price_shuttle_net'          => 'Einkaufspreis Shuttle netto',
		'purchase_price_other_net'            => 'Einkaufspreis Sonstige Leistungen netto',
	];
	?>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="fge_pricing_mode">Preislogik</label></th>
			<td>
				<select id="fge_pricing_mode" name="fge_pricing_mode">
					<option value="package"    <?php selected( $pricing_mode, 'package' ); ?>>Gesamtpaket</option>
					<option value="individual" <?php selected( $pricing_mode, 'individual' ); ?>>Einzelpreise</option>
				</select>
			</td>
		</tr>
		<?php foreach ( $price_fields as $key => $label ) :
			$val = get_post_meta( $post->ID, '_fge_' . $key, true );
			?>
			<tr>
				<th scope="row"><label for="fge_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
				<td>
					<input type="number" id="fge_<?php echo esc_attr( $key ); ?>" name="fge_<?php echo esc_attr( $key ); ?>"
					       value="<?php echo esc_attr( $val ); ?>" step="0.01" min="0" style="width:140px;"> €
				</td>
			</tr>
		<?php endforeach; ?>
		<tr>
			<th scope="row"><label for="fge_firmengolf_markup_percent">Firmengolf Aufschlag %</label></th>
			<td>
				<input type="number" id="fge_firmengolf_markup_percent" name="fge_firmengolf_markup_percent"
				       value="<?php echo esc_attr( $markup ); ?>" step="0.01" min="0" style="width:110px;">
			</td>
		</tr>
		<tr>
			<th scope="row">Verkaufspreis netto (auto)</th>
			<td>
				<input type="text" value="<?php echo esc_attr( $sale_price_net ); ?>" readonly
				       class="regular-text" style="background:#f0f0f1;">
				<p class="description">Wird beim Speichern automatisch berechnet.</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_public_price_label">Preislabel öffentlich</label></th>
			<td>
				<input type="text" id="fge_public_price_label" name="fge_public_price_label"
				       value="<?php echo esc_attr( $public_price_label ); ?>" class="regular-text">
				<p class="description">z.B. ab 1.200 € netto</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_price_note">Preis Hinweis</label></th>
			<td><textarea id="fge_price_note" name="fge_price_note" rows="2" class="large-text"><?php echo esc_textarea( $price_note ); ?></textarea></td>
		</tr>
		<tr>
			<th scope="row">Preis pro Person möglich</th>
			<td><label><input type="checkbox" name="fge_price_per_person_possible" value="1"
			                  <?php checked( $price_per_person, '1' ); ?>> Im Frontend als p.P. anzeigen</label></td>
		</tr>
		<tr>
			<th scope="row">Pauschalpreis möglich</th>
			<td><label><input type="checkbox" name="fge_package_price_possible" value="1"
			                  <?php checked( $package_possible, '1' ); ?>> Pauschalpreis möglich</label></td>
		</tr>
	</table>
	<?php
}

// ── Render: Anfrage und Verfügbarkeit ────────────────────────────────────────

function fge_render_mb_anfrage( WP_Post $post ) {
	$contact_name    = get_post_meta( $post->ID, '_fge_availability_contact_name', true );
	$contact_email   = get_post_meta( $post->ID, '_fge_availability_contact_email', true );
	$contact_phone   = get_post_meta( $post->ID, '_fge_availability_contact_phone', true );
	$req_interfaces  = (array) get_post_meta( $post->ID, '_fge_required_interfaces', true );
	$lead_time       = get_post_meta( $post->ID, '_fge_lead_time', true );
	$allow_dates_raw = get_post_meta( $post->ID, '_fge_allow_three_date_suggestions', true );
	$allow_dates     = ( $allow_dates_raw === '' ) ? true : (bool) $allow_dates_raw;

	$interfaces = [
		'golfplatz'              => 'Golfplatz',
		'golfpro'                => 'Golfpro',
		'gastro'                 => 'Gastro',
		'meetingraum'            => 'Meetingraum',
		'shuttle'                => 'Shuttle',
		'sonstige_dienstleister' => 'Sonstige Dienstleister',
	];
	?>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="fge_availability_contact_name">Ansprechpartner Verfügbarkeit</label></th>
			<td><input type="text" id="fge_availability_contact_name" name="fge_availability_contact_name"
			           value="<?php echo esc_attr( $contact_name ); ?>" class="regular-text"></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_availability_contact_email">E-Mail Verfügbarkeit</label></th>
			<td><input type="email" id="fge_availability_contact_email" name="fge_availability_contact_email"
			           value="<?php echo esc_attr( $contact_email ); ?>" class="regular-text"></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_availability_contact_phone">Telefon Verfügbarkeit</label></th>
			<td><input type="text" id="fge_availability_contact_phone" name="fge_availability_contact_phone"
			           value="<?php echo esc_attr( $contact_phone ); ?>" class="regular-text"></td>
		</tr>
		<tr>
			<th scope="row">Benötigte Schnittstellen</th>
			<td>
				<?php foreach ( $interfaces as $val => $label ) : ?>
					<label style="display:block;margin-bottom:5px;">
						<input type="checkbox" name="fge_required_interfaces[]"
						       value="<?php echo esc_attr( $val ); ?>"
						       <?php checked( in_array( $val, $req_interfaces, true ) ); ?>>
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_lead_time">Vorlaufzeit</label></th>
			<td>
				<input type="text" id="fge_lead_time" name="fge_lead_time"
				       value="<?php echo esc_attr( $lead_time ); ?>" class="regular-text">
				<p class="description">z.B. mindestens 14 Tage vorher</p>
			</td>
		</tr>
		<tr>
			<th scope="row">Terminvorschläge</th>
			<td>
				<label>
					<input type="checkbox" name="fge_allow_three_date_suggestions" value="1"
					       <?php checked( $allow_dates ); ?>>
					Bis zu 3 Terminvorschläge erlaubt
				</label>
			</td>
		</tr>
	</table>
	<?php
}

// ── Render: SEO Basis ────────────────────────────────────────────────────────

function fge_render_mb_seo( WP_Post $post ) {
	$seo_title        = get_post_meta( $post->ID, '_fge_seo_title', true );
	$meta_description = get_post_meta( $post->ID, '_fge_meta_description', true );
	$focus_keyword    = get_post_meta( $post->ID, '_fge_focus_keyword', true );
	$faq_content      = get_post_meta( $post->ID, '_fge_faq_content', true );
	?>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="fge_seo_title">SEO Titel</label></th>
			<td><input type="text" id="fge_seo_title" name="fge_seo_title"
			           value="<?php echo esc_attr( $seo_title ); ?>" class="large-text"></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_meta_description">Meta Description</label></th>
			<td><textarea id="fge_meta_description" name="fge_meta_description" rows="2" class="large-text"><?php echo esc_textarea( $meta_description ); ?></textarea></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_focus_keyword">Fokus Keyword</label></th>
			<td><input type="text" id="fge_focus_keyword" name="fge_focus_keyword"
			           value="<?php echo esc_attr( $focus_keyword ); ?>" class="regular-text"></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_faq_content">FAQ</label></th>
			<td>
				<textarea id="fge_faq_content" name="fge_faq_content" rows="5" class="large-text"><?php echo esc_textarea( $faq_content ); ?></textarea>
				<p class="description">Fragen und Antworten als Freitext. Später strukturiert.</p>
			</td>
		</tr>
	</table>
	<?php
}

// ── Render: Tracking Basis ───────────────────────────────────────────────────

function fge_render_mb_tracking( WP_Post $post ) {
	$views    = (int) get_post_meta( $post->ID, '_fge_views_count', true );
	$requests = (int) get_post_meta( $post->ID, '_fge_requests_count', true );
	$bookings = (int) get_post_meta( $post->ID, '_fge_bookings_count', true );
	$last_req = get_post_meta( $post->ID, '_fge_last_request_at', true );

	$conv_vr = ( $views > 0 )    ? round( $requests / $views * 100, 1 ) . ' %' : '–';
	$conv_rb = ( $requests > 0 ) ? round( $bookings / $requests * 100, 1 ) . ' %' : '–';
	?>
	<table class="form-table">
		<tr>
			<th scope="row">Aufrufe</th>
			<td><input type="text" value="<?php echo esc_attr( $views ); ?>" readonly style="width:80px;background:#f0f0f1;"></td>
		</tr>
		<tr>
			<th scope="row">Anfragen</th>
			<td><input type="text" value="<?php echo esc_attr( $requests ); ?>" readonly style="width:80px;background:#f0f0f1;"></td>
		</tr>
		<tr>
			<th scope="row">Buchungen</th>
			<td><input type="text" value="<?php echo esc_attr( $bookings ); ?>" readonly style="width:80px;background:#f0f0f1;"></td>
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

function fge_save_event_fields( int $post_id ) {
	if ( ! isset( $_POST['fge_event_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fge_event_nonce'] ) ), 'fge_event_fields' ) ) {
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
	if ( get_post_type( $post_id ) !== 'firmengolf_event' ) {
		return;
	}

	// Allowed values for select / checkbox-group fields.
	$allowed_event_types    = [ 'teamevent', 'kundenevent', 'gesundheitstag', 'offsite', 'firmenturnier', 'anderes_event' ];
	$allowed_provider_types = [ 'firmengolf', 'golfplatz_partner' ];
	$allowed_event_statuses = fge_get_statuses( 'event' );
	$allowed_pricing_modes  = [ 'package', 'individual' ];
	$allowed_weekdays       = [ 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' ];
	$allowed_interfaces     = [ 'golfplatz', 'golfpro', 'gastro', 'meetingraum', 'shuttle', 'sonstige_dienstleister' ];

	$san_select = static function ( string $key, array $allowed ): string {
		$val = sanitize_text_field( wp_unslash( $_POST[ $key ] ?? '' ) );
		return in_array( $val, $allowed, true ) ? $val : '';
	};

	$san_decimal = static function ( string $key ): float {
		$raw = wp_unslash( $_POST[ $key ] ?? '' );
		return (float) str_replace( ',', '.', $raw );
	};

	// ── Metabox 1: Basisdaten ──
	update_post_meta( $post_id, '_fge_event_type',          $san_select( 'fge_event_type', $allowed_event_types ) );
	update_post_meta( $post_id, '_fge_provider_type',       $san_select( 'fge_provider_type', $allowed_provider_types ) );
	update_post_meta( $post_id, '_fge_assigned_partner_id', absint( $_POST['fge_assigned_partner_id'] ?? 0 ) );
	update_post_meta( $post_id, '_fge_event_status',        $san_select( 'fge_event_status', $allowed_event_statuses ) );
	update_post_meta( $post_id, '_fge_card_description',    sanitize_textarea_field( wp_unslash( $_POST['fge_card_description'] ?? '' ) ) );

	// ── Metabox 2: Rahmen ──
	update_post_meta( $post_id, '_fge_participants_min', absint( $_POST['fge_participants_min'] ?? 0 ) );
	update_post_meta( $post_id, '_fge_participants_max', absint( $_POST['fge_participants_max'] ?? 0 ) );
	update_post_meta( $post_id, '_fge_duration',         sanitize_text_field( wp_unslash( $_POST['fge_duration'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_season',           sanitize_text_field( wp_unslash( $_POST['fge_season'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_region',           sanitize_text_field( wp_unslash( $_POST['fge_region'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_event_location',   sanitize_text_field( wp_unslash( $_POST['fge_event_location'] ?? '' ) ) );

	$raw_weekdays = is_array( $_POST['fge_available_weekdays'] ?? null ) ? $_POST['fge_available_weekdays'] : [];
	$weekdays     = array_values( array_intersect( array_map( 'sanitize_text_field', $raw_weekdays ), $allowed_weekdays ) );
	update_post_meta( $post_id, '_fge_available_weekdays', $weekdays );

	// ── Metabox 3: Leistungen ──
	$leistungen_keys = [
		'has_golf_teacher', 'has_range_usage', 'has_rental_clubs', 'has_range_balls',
		'has_putting_shortgame', 'has_meeting_room', 'has_breakfast', 'has_lunch',
		'has_dinner', 'has_shuttle', 'has_branding',
	];
	foreach ( $leistungen_keys as $key ) {
		update_post_meta( $post_id, '_fge_' . $key, isset( $_POST[ 'fge_' . $key ] ) ? 1 : 0 );
	}
	update_post_meta( $post_id, '_fge_additional_services', sanitize_textarea_field( wp_unslash( $_POST['fge_additional_services'] ?? '' ) ) );

	// ── Metabox 4: Preislogik ──
	$pricing_mode = $san_select( 'fge_pricing_mode', $allowed_pricing_modes );
	update_post_meta( $post_id, '_fge_pricing_mode', $pricing_mode );

	$price_keys = [
		'purchase_price_package_net',
		'purchase_price_meeting_room_hour_net',
		'purchase_price_range_net',
		'purchase_price_trainer_hour_net',
		'purchase_price_breakfast_net',
		'purchase_price_lunch_net',
		'purchase_price_dinner_net',
		'purchase_price_shuttle_net',
		'purchase_price_other_net',
	];
	$price_meta = [];
	foreach ( $price_keys as $key ) {
		$val               = $san_decimal( 'fge_' . $key );
		$price_meta[ $key ] = $val;
		update_post_meta( $post_id, '_fge_' . $key, $val );
	}

	$markup_raw = trim( wp_unslash( $_POST['fge_firmengolf_markup_percent'] ?? '' ) );
	$markup     = ( $markup_raw !== '' ) ? (float) str_replace( ',', '.', $markup_raw ) : 20.0;
	update_post_meta( $post_id, '_fge_firmengolf_markup_percent', $markup );

	update_post_meta( $post_id, '_fge_public_price_label',       sanitize_text_field( wp_unslash( $_POST['fge_public_price_label'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_price_note',               sanitize_textarea_field( wp_unslash( $_POST['fge_price_note'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_price_per_person_possible', isset( $_POST['fge_price_per_person_possible'] ) ? 1 : 0 );
	update_post_meta( $post_id, '_fge_package_price_possible',   isset( $_POST['fge_package_price_possible'] ) ? 1 : 0 );

	// Auto-calculate sale price from current POST values (no extra DB read needed).
	$calc_meta                              = $price_meta;
	$calc_meta['pricing_mode']              = $pricing_mode;
	$calc_meta['firmengolf_markup_percent'] = $markup;
	update_post_meta( $post_id, '_fge_sale_price_net', fge_calculate_sale_price_net( $calc_meta ) );

	// ── Metabox 5: Anfrage ──
	update_post_meta( $post_id, '_fge_availability_contact_name',  sanitize_text_field( wp_unslash( $_POST['fge_availability_contact_name'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_availability_contact_email', sanitize_email( wp_unslash( $_POST['fge_availability_contact_email'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_availability_contact_phone', sanitize_text_field( wp_unslash( $_POST['fge_availability_contact_phone'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_lead_time',                  sanitize_text_field( wp_unslash( $_POST['fge_lead_time'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_allow_three_date_suggestions', isset( $_POST['fge_allow_three_date_suggestions'] ) ? 1 : 0 );

	$raw_interfaces = is_array( $_POST['fge_required_interfaces'] ?? null ) ? $_POST['fge_required_interfaces'] : [];
	$interfaces     = array_values( array_intersect( array_map( 'sanitize_text_field', $raw_interfaces ), $allowed_interfaces ) );
	update_post_meta( $post_id, '_fge_required_interfaces', $interfaces );

	// ── Metabox 6: SEO ──
	update_post_meta( $post_id, '_fge_seo_title',        sanitize_text_field( wp_unslash( $_POST['fge_seo_title'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_meta_description', sanitize_textarea_field( wp_unslash( $_POST['fge_meta_description'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_focus_keyword',    sanitize_text_field( wp_unslash( $_POST['fge_focus_keyword'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_faq_content',      sanitize_textarea_field( wp_unslash( $_POST['fge_faq_content'] ?? '' ) ) );

	// ── Tracking Defaults (set once on first save) ──
	if ( get_post_meta( $post_id, '_fge_views_count', true ) === '' ) {
		add_post_meta( $post_id, '_fge_views_count', 0, true );
	}
	if ( get_post_meta( $post_id, '_fge_requests_count', true ) === '' ) {
		add_post_meta( $post_id, '_fge_requests_count', 0, true );
	}
	if ( get_post_meta( $post_id, '_fge_bookings_count', true ) === '' ) {
		add_post_meta( $post_id, '_fge_bookings_count', 0, true );
	}
}
add_action( 'save_post', 'fge_save_event_fields' );
