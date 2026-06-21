<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Quelle-Optionen für Anfragen. Enthält alle von den Formularen gesetzten Werte
 * (gespeichert in `_fge_source`) plus manuelle Admin-Quellen.
 *
 * @return array<string,string>
 */
function fge_request_source_options(): array {
	return [
		'event_page'          => 'Event-Seite',
		'general_landingpage' => 'Allgemeine Landingpage',
		'general_anfrage_page' => 'Anfrage-Seite',
		'partner_page'        => 'Partner-Seite',
		'contact_page'        => 'Kontaktseite',
		'callback_widget'     => 'Rückruf-Widget',
		'linkedin'            => 'LinkedIn',
		'google'              => 'Google',
		'manual'              => 'Manuell',
		'other'               => 'Sonstiges',
	];
}

// ── Registration ─────────────────────────────────────────────────────────────

function fge_register_request_metaboxes() {
	$screen = 'firmengolf_request';

	add_meta_box( 'fge_rmb_basis',       'Anfrage Basis',               'fge_render_rmb_basis',       $screen, 'normal', 'high' );
	add_meta_box( 'fge_rmb_unternehmen', 'Unternehmensdaten',           'fge_render_rmb_unternehmen', $screen, 'normal', 'default' );
	add_meta_box( 'fge_rmb_kontakt',     'Ansprechpartner Unternehmen', 'fge_render_rmb_kontakt',     $screen, 'normal', 'default' );
	add_meta_box( 'fge_rmb_rahmen',      'Event Rahmen',                'fge_render_rmb_rahmen',      $screen, 'normal', 'default' );
	add_meta_box( 'fge_rmb_termine',     'Terminvorschläge',            'fge_render_rmb_termine',     $screen, 'normal', 'default' );
	add_meta_box( 'fge_rmb_leistungen',  'Gewünschte Zusatzleistungen', 'fge_render_rmb_leistungen',  $screen, 'normal', 'default' );
	add_meta_box( 'fge_rmb_kit_hubspot', 'Kit und HubSpot (intern)',    'fge_render_rmb_kit_hubspot', $screen, 'normal', 'default' );
	add_meta_box( 'fge_rmb_lexoffice',   'Lexoffice (intern, manuell)', 'fge_render_rmb_lexoffice',   $screen, 'normal', 'default' );
	add_meta_box( 'fge_rmb_tracking',    'Quelle und Tracking',         'fge_render_rmb_tracking',    $screen, 'side',   'default' );
}
add_action( 'add_meta_boxes', 'fge_register_request_metaboxes' );

// ── Render: Anfrage Basis ─────────────────────────────────────────────────────

function fge_render_rmb_basis( WP_Post $post ) {
	wp_nonce_field( 'fge_request_fields', 'fge_request_nonce' );

	$request_type       = get_post_meta( $post->ID, '_fge_request_type', true );
	$assigned_event_id  = get_post_meta( $post->ID, '_fge_assigned_event_id', true );
	$assigned_partner_id = get_post_meta( $post->ID, '_fge_assigned_partner_id', true );
	$request_status     = get_post_meta( $post->ID, '_fge_request_status', true );

	$request_types = [
		'specific_event'         => 'Konkretes Event',
		'general_event_request'  => 'Allgemeine Eventanfrage',
	];
	$statuses = fge_get_statuses( 'request' );
	$ref      = function_exists( 'fge_request_number' ) ? fge_request_number( $post->ID ) : (string) get_post_meta( $post->ID, '_fge_ref', true );
	?>
	<table class="form-table">
		<tr>
			<th scope="row">Anfragenummer</th>
			<td><input type="text" value="<?php echo esc_attr( $ref ); ?>" readonly class="regular-text" style="background:#f0f0f1;font-weight:600;width:160px;"></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_request_type">Anfrageart</label></th>
			<td>
				<select id="fge_request_type" name="fge_request_type">
					<option value="">— bitte wählen —</option>
					<?php foreach ( $request_types as $val => $label ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $request_type, $val ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_assigned_event_id">Zugeordnetes Event</label></th>
			<td>
				<select id="fge_assigned_event_id" name="fge_assigned_event_id">
					<option value="0">— kein Event zugeordnet —</option>
					<?php foreach ( fge_get_posts_select_options( 'firmengolf_event' ) as $pid => $label ) : ?>
						<option value="<?php echo esc_attr( $pid ); ?>" <?php selected( (int) $assigned_event_id, $pid ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_assigned_partner_id">Zugeordneter Golfplatz</label></th>
			<td>
				<select id="fge_assigned_partner_id" name="fge_assigned_partner_id">
					<option value="0">— kein Golfplatz zugeordnet —</option>
					<?php foreach ( fge_get_posts_select_options( 'firmengolf_partner' ) as $pid => $label ) : ?>
						<option value="<?php echo esc_attr( $pid ); ?>" <?php selected( (int) $assigned_partner_id, $pid ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_request_status">Anfrage Status</label></th>
			<td>
				<select id="fge_request_status" name="fge_request_status">
					<option value="">— bitte wählen —</option>
					<?php foreach ( $statuses as $val ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $request_status, $val ); ?>><?php echo esc_html( $val ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
	</table>
	<?php
}

// ── Render: Unternehmensdaten ─────────────────────────────────────────────────

function fge_render_rmb_unternehmen( WP_Post $post ) {
	$company_name        = get_post_meta( $post->ID, '_fge_company_name', true );
	$industry            = get_post_meta( $post->ID, '_fge_industry', true );
	$company_website     = get_post_meta( $post->ID, '_fge_company_website', true );
	$company_street      = get_post_meta( $post->ID, '_fge_company_street', true );
	$company_postal_code = get_post_meta( $post->ID, '_fge_company_postal_code', true );
	$company_city        = get_post_meta( $post->ID, '_fge_company_city', true );
	$company_region      = get_post_meta( $post->ID, '_fge_company_region', true );
	?>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="fge_company_name">Unternehmensname</label></th>
			<td><input type="text" id="fge_company_name" name="fge_company_name"
			           value="<?php echo esc_attr( $company_name ); ?>" class="regular-text"></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_industry">Branche</label></th>
			<td>
				<input type="text" id="fge_industry" name="fge_industry"
				       value="<?php echo esc_attr( $industry ); ?>" class="regular-text">
				<p class="description">Optional.</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_company_website">Website</label></th>
			<td>
				<input type="url" id="fge_company_website" name="fge_company_website"
				       value="<?php echo esc_attr( $company_website ); ?>" class="regular-text">
				<p class="description">Optional.</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_company_street">Straße und Hausnummer</label></th>
			<td>
				<input type="text" id="fge_company_street" name="fge_company_street"
				       value="<?php echo esc_attr( $company_street ); ?>" class="regular-text">
				<p class="description">Optional.</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_company_postal_code">PLZ</label></th>
			<td>
				<input type="text" id="fge_company_postal_code" name="fge_company_postal_code"
				       value="<?php echo esc_attr( $company_postal_code ); ?>" style="width:100px;">
				<p class="description">Optional.</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_company_city">Ort</label></th>
			<td><input type="text" id="fge_company_city" name="fge_company_city"
			           value="<?php echo esc_attr( $company_city ); ?>" class="regular-text"></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_company_region">Bundesland / Region</label></th>
			<td><input type="text" id="fge_company_region" name="fge_company_region"
			           value="<?php echo esc_attr( $company_region ); ?>" class="regular-text"></td>
		</tr>
	</table>
	<?php
}

// ── Render: Ansprechpartner Unternehmen ───────────────────────────────────────

function fge_render_rmb_kontakt( WP_Post $post ) {
	$first_name               = get_post_meta( $post->ID, '_fge_contact_first_name', true );
	$last_name                = get_post_meta( $post->ID, '_fge_contact_last_name', true );
	$email                    = get_post_meta( $post->ID, '_fge_contact_email', true );
	$phone                    = get_post_meta( $post->ID, '_fge_contact_phone', true );
	$role                     = get_post_meta( $post->ID, '_fge_contact_role', true );
	$preferred_contact_method = get_post_meta( $post->ID, '_fge_preferred_contact_method', true );

	$contact_methods = [
		'phone' => 'Telefon',
		'email' => 'E-Mail',
		'any'   => 'Egal',
	];
	?>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="fge_contact_first_name">Vorname</label></th>
			<td><input type="text" id="fge_contact_first_name" name="fge_contact_first_name"
			           value="<?php echo esc_attr( $first_name ); ?>" class="regular-text"></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_contact_last_name">Nachname</label></th>
			<td><input type="text" id="fge_contact_last_name" name="fge_contact_last_name"
			           value="<?php echo esc_attr( $last_name ); ?>" class="regular-text"></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_contact_email">E-Mail</label></th>
			<td><input type="email" id="fge_contact_email" name="fge_contact_email"
			           value="<?php echo esc_attr( $email ); ?>" class="regular-text"></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_contact_phone">Telefon</label></th>
			<td><input type="text" id="fge_contact_phone" name="fge_contact_phone"
			           value="<?php echo esc_attr( $phone ); ?>" class="regular-text"></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_contact_role">Rolle im Unternehmen</label></th>
			<td>
				<input type="text" id="fge_contact_role" name="fge_contact_role"
				       value="<?php echo esc_attr( $role ); ?>" class="regular-text">
				<p class="description">Optional.</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_preferred_contact_method">Bevorzugte Kontaktart</label></th>
			<td>
				<select id="fge_preferred_contact_method" name="fge_preferred_contact_method">
					<option value="">— bitte wählen —</option>
					<?php foreach ( $contact_methods as $val => $label ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $preferred_contact_method, $val ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
	</table>
	<?php
}

// ── Render: Event Rahmen ──────────────────────────────────────────────────────

function fge_render_rmb_rahmen( WP_Post $post ) {
	$expected_participants = get_post_meta( $post->ID, '_fge_expected_participants', true );
	$participants_range    = get_post_meta( $post->ID, '_fge_participants_range', true );
	$event_goal            = get_post_meta( $post->ID, '_fge_event_goal', true );
	$desired_region        = get_post_meta( $post->ID, '_fge_desired_region', true );
	$budget_range          = get_post_meta( $post->ID, '_fge_budget_range', true );
	$message               = get_post_meta( $post->ID, '_fge_message', true );
	$group_experience      = get_post_meta( $post->ID, '_fge_group_experience', true );
	$catering_notes        = get_post_meta( $post->ID, '_fge_catering_notes', true );

	$format_tiers = fge_get_event_formats();
	?>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="fge_expected_participants">Teilnehmerzahl erwartet</label></th>
			<td><input type="number" id="fge_expected_participants" name="fge_expected_participants"
			           value="<?php echo esc_attr( $expected_participants ); ?>" min="0" step="1" style="width:110px;"></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_participants_range">Teilnehmerzahl Spanne</label></th>
			<td>
				<input type="text" id="fge_participants_range" name="fge_participants_range"
				       value="<?php echo esc_attr( $participants_range ); ?>" class="regular-text">
				<p class="description">Optional. z.B. 20 bis 30 Personen</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_event_goal">Eventziel</label></th>
			<td>
				<select id="fge_event_goal" name="fge_event_goal">
					<option value="">— bitte wählen —</option>
					<optgroup label="Standard">
						<?php foreach ( $format_tiers['standard'] as $val => $label ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $event_goal, $val ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</optgroup>
					<optgroup label="Auf Anfrage">
						<?php foreach ( $format_tiers['on_request'] as $val => $label ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $event_goal, $val ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</optgroup>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_desired_region">Gewünschte Region</label></th>
			<td><input type="text" id="fge_desired_region" name="fge_desired_region"
			           value="<?php echo esc_attr( $desired_region ); ?>" class="regular-text"></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_budget_range">Budgetrahmen</label></th>
			<td>
				<input type="text" id="fge_budget_range" name="fge_budget_range"
				       value="<?php echo esc_attr( $budget_range ); ?>" class="regular-text">
				<p class="description">Optional.</p>
			</td>
		</tr>
		<tr>
			<th scope="row">Golf-Erfahrung (Team)</th>
			<td><?php echo '' !== $group_experience ? esc_html( $group_experience ) : '—'; ?></td>
		</tr>
		<tr>
			<th scope="row">Verpflegung / Diät</th>
			<td><?php echo '' !== $catering_notes ? esc_html( $catering_notes ) : '—'; ?></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_message">Nachricht / Sonderwünsche</label></th>
			<td><textarea id="fge_message" name="fge_message" rows="4" class="large-text"><?php echo esc_textarea( $message ); ?></textarea></td>
		</tr>
	</table>
	<?php
}

// ── Render: Terminvorschläge ──────────────────────────────────────────────────

function fge_render_rmb_termine( WP_Post $post ) {
	$preferred_date_1  = get_post_meta( $post->ID, '_fge_preferred_date_1', true );
	$preferred_date_2  = get_post_meta( $post->ID, '_fge_preferred_date_2', true );
	$preferred_date_3  = get_post_meta( $post->ID, '_fge_preferred_date_3', true );
	$alternative_period = get_post_meta( $post->ID, '_fge_alternative_period', true );
	$preferred_time    = get_post_meta( $post->ID, '_fge_preferred_time', true );

	$times = [
		'morning'   => 'Vormittag',
		'afternoon' => 'Nachmittag',
		'after_work' => 'After Work',
		'full_day'  => 'Ganztägig',
		'open'      => 'Offen',
	];
	?>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="fge_preferred_date_1">Wunschtermin 1</label></th>
			<td>
				<input type="text" id="fge_preferred_date_1" name="fge_preferred_date_1"
				       value="<?php echo esc_attr( $preferred_date_1 ); ?>" class="regular-text">
				<p class="description">Wird als Label gespeichert (z.B. „Do, 18.06.2026"), so wie es in der Terminabstimmung erscheint.</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_preferred_date_2">Wunschtermin 2</label></th>
			<td><input type="text" id="fge_preferred_date_2" name="fge_preferred_date_2"
			           value="<?php echo esc_attr( $preferred_date_2 ); ?>" class="regular-text"></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_preferred_date_3">Wunschtermin 3</label></th>
			<td><input type="text" id="fge_preferred_date_3" name="fge_preferred_date_3"
			           value="<?php echo esc_attr( $preferred_date_3 ); ?>" class="regular-text"></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_alternative_period">Alternativer Zeitraum</label></th>
			<td>
				<input type="text" id="fge_alternative_period" name="fge_alternative_period"
				       value="<?php echo esc_attr( $alternative_period ); ?>" class="regular-text">
				<p class="description">z.B. Juni 2026, September bis Oktober, Freitag Nachmittag</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_preferred_time">Uhrzeit Wunsch</label></th>
			<td>
				<select id="fge_preferred_time" name="fge_preferred_time">
					<option value="">— bitte wählen —</option>
					<?php foreach ( $times as $val => $label ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $preferred_time, $val ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
	</table>
	<?php
}

// ── Render: Gewünschte Zusatzleistungen ───────────────────────────────────────

function fge_render_rmb_leistungen( WP_Post $post ) {
	$checkboxes = [
		'wants_golf_teacher'             => 'Golflehrer',
		'wants_meeting_room'             => 'Meetingraum',
		'wants_breakfast'                => 'Frühstück',
		'wants_lunch'                    => 'Lunch',
		'wants_dinner'                   => 'Abendessen',
		'wants_shuttle'                  => 'Shuttle',
		'wants_branding'                 => 'Branding',
		'wants_tournament_mode'          => 'Turniermodus',
		'wants_bad_weather_alternative'  => 'Schlechtwetter Alternative',
		'wants_individual_customization' => 'Individuelle Anpassung',
	];
	$additional_wishes = get_post_meta( $post->ID, '_fge_additional_wishes', true );
	$wish_groups = function_exists( 'fge_request_wish_groups' ) ? fge_request_wish_groups( $post->ID ) : [ 'platz' => [], 'firmengolf' => [] ];
	if ( ! empty( $wish_groups['platz'] ) || ! empty( $wish_groups['firmengolf'] ) ) :
		?>
		<p style="margin:0 0 6px;"><strong>Vom Kunden gewählt (Event-Anfrage)</strong></p>
		<table class="form-table" style="margin-top:0;">
			<?php if ( ! empty( $wish_groups['platz'] ) ) : ?>
			<tr><th scope="row">Am Platz</th><td><?php echo esc_html( implode( ', ', $wish_groups['platz'] ) ); ?></td></tr>
			<?php endif; ?>
			<?php if ( ! empty( $wish_groups['firmengolf'] ) ) : ?>
			<tr><th scope="row">Über Firmengolf</th><td><?php echo esc_html( implode( ', ', $wish_groups['firmengolf'] ) ); ?></td></tr>
			<?php endif; ?>
		</table>
		<?php
	endif;
	?>
	<p style="margin:14px 0 4px;"><strong>Interne Einordnung</strong></p>
	<p class="description" style="margin:0 0 6px;">Bei Event-Anfragen steht die echte Kundenauswahl oben. Diese Häkchen befüllt die allgemeine Anfrage und dienen sonst der internen Zuordnung.</p>
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
			<th scope="row"><label for="fge_additional_wishes">Sonstige Wünsche</label></th>
			<td><textarea id="fge_additional_wishes" name="fge_additional_wishes" rows="3" class="large-text"><?php echo esc_textarea( $additional_wishes ); ?></textarea></td>
		</tr>
	</table>
	<?php
}

// ── Render: Kit und HubSpot ───────────────────────────────────────────────────

function fge_render_rmb_kit_hubspot( WP_Post $post ) {
	$kit_opt_in         = get_post_meta( $post->ID, '_fge_kit_opt_in', true );
	$kit_status         = get_post_meta( $post->ID, '_fge_kit_status', true );
	$kit_tags           = get_post_meta( $post->ID, '_fge_kit_tags', true );
	$hubspot_status     = get_post_meta( $post->ID, '_fge_hubspot_status', true );
	$hubspot_contact_id = get_post_meta( $post->ID, '_fge_hubspot_contact_id', true );

	$integration_statuses = [
		'not_sent' => 'Nicht gesendet',
		'sent'     => 'Gesendet',
		'error'    => 'Fehler',
	];
	?>
	<table class="form-table">
		<tr>
			<th scope="row">Kit Opt-in</th>
			<td><label><input type="checkbox" name="fge_kit_opt_in" value="1"
			                  <?php checked( $kit_opt_in, '1' ); ?>> Kit Opt-in</label></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_kit_status">Kit Status</label></th>
			<td>
				<select id="fge_kit_status" name="fge_kit_status">
					<option value="">— bitte wählen —</option>
					<?php foreach ( $integration_statuses as $val => $label ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $kit_status, $val ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_kit_tags">Kit Tags</label></th>
			<td>
				<input type="text" id="fge_kit_tags" name="fge_kit_tags"
				       value="<?php echo esc_attr( $kit_tags ); ?>" class="regular-text">
				<p class="description">Kommagetrennt, z.B. firmenevent lead, teamevent interesse</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_hubspot_status">HubSpot Lead Status</label></th>
			<td>
				<select id="fge_hubspot_status" name="fge_hubspot_status">
					<option value="">— bitte wählen —</option>
					<?php foreach ( $integration_statuses as $val => $label ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $hubspot_status, $val ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_hubspot_contact_id">HubSpot Kontakt ID</label></th>
			<td>
				<input type="text" id="fge_hubspot_contact_id" name="fge_hubspot_contact_id"
				       value="<?php echo esc_attr( $hubspot_contact_id ); ?>" class="regular-text">
				<p class="description">Optional. Keine API-Anbindung im MVP.</p>
			</td>
		</tr>
	</table>
	<?php
}

// ── Render: Lexoffice MVP manuell ─────────────────────────────────────────────

function fge_render_rmb_lexoffice( WP_Post $post ) {
	$offer_created   = get_post_meta( $post->ID, '_fge_lexoffice_offer_created', true );
	$offer_number    = get_post_meta( $post->ID, '_fge_lexoffice_offer_number', true );
	$invoice_created = get_post_meta( $post->ID, '_fge_lexoffice_invoice_created', true );
	$invoice_number  = get_post_meta( $post->ID, '_fge_lexoffice_invoice_number', true );
	$lexoffice_note  = get_post_meta( $post->ID, '_fge_lexoffice_note', true );
	?>
	<table class="form-table">
		<tr>
			<th scope="row">Lexoffice Angebot erstellt</th>
			<td><label><input type="checkbox" name="fge_lexoffice_offer_created" value="1"
			                  <?php checked( $offer_created, '1' ); ?>> Angebot erstellt</label></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_lexoffice_offer_number">Lexoffice Angebotsnummer</label></th>
			<td>
				<input type="text" id="fge_lexoffice_offer_number" name="fge_lexoffice_offer_number"
				       value="<?php echo esc_attr( $offer_number ); ?>" class="regular-text">
				<p class="description">Optional.</p>
			</td>
		</tr>
		<tr>
			<th scope="row">Lexoffice Rechnung erstellt</th>
			<td><label><input type="checkbox" name="fge_lexoffice_invoice_created" value="1"
			                  <?php checked( $invoice_created, '1' ); ?>> Rechnung erstellt</label></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_lexoffice_invoice_number">Lexoffice Rechnungsnummer</label></th>
			<td>
				<input type="text" id="fge_lexoffice_invoice_number" name="fge_lexoffice_invoice_number"
				       value="<?php echo esc_attr( $invoice_number ); ?>" class="regular-text">
				<p class="description">Optional.</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_lexoffice_note">Lexoffice Notiz</label></th>
			<td><textarea id="fge_lexoffice_note" name="fge_lexoffice_note" rows="3" class="large-text"><?php echo esc_textarea( $lexoffice_note ); ?></textarea></td>
		</tr>
	</table>
	<?php
}

// ── Render: Quelle und Tracking ───────────────────────────────────────────────

function fge_render_rmb_tracking( WP_Post $post ) {
	$request_source     = get_post_meta( $post->ID, '_fge_source', true );
	$utm_source         = get_post_meta( $post->ID, '_fge_utm_source', true );
	$utm_medium         = get_post_meta( $post->ID, '_fge_utm_medium', true );
	$utm_campaign       = get_post_meta( $post->ID, '_fge_utm_campaign', true );
	$request_date       = get_post_meta( $post->ID, '_fge_request_date', true );
	$last_status_change = get_post_meta( $post->ID, '_fge_last_status_change', true );

	$sources = fge_request_source_options();
	?>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="fge_request_source">Quelle</label></th>
			<td>
				<select id="fge_request_source" name="fge_request_source">
					<option value="">— bitte wählen —</option>
					<?php foreach ( $sources as $val => $label ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $request_source, $val ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_utm_source">UTM Source</label></th>
			<td><input type="text" id="fge_utm_source" name="fge_utm_source"
			           value="<?php echo esc_attr( $utm_source ); ?>" class="regular-text"></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_utm_medium">UTM Medium</label></th>
			<td><input type="text" id="fge_utm_medium" name="fge_utm_medium"
			           value="<?php echo esc_attr( $utm_medium ); ?>" class="regular-text"></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_utm_campaign">UTM Campaign</label></th>
			<td><input type="text" id="fge_utm_campaign" name="fge_utm_campaign"
			           value="<?php echo esc_attr( $utm_campaign ); ?>" class="regular-text"></td>
		</tr>
		<tr>
			<th scope="row">Anfrage Datum</th>
			<td><input type="text" value="<?php echo esc_attr( $request_date ); ?>" readonly
			           class="regular-text" style="background:#f0f0f1;"></td>
		</tr>
		<tr>
			<th scope="row">Letzte Statusänderung</th>
			<td><input type="text" value="<?php echo esc_attr( $last_status_change ); ?>" readonly
			           class="regular-text" style="background:#f0f0f1;"></td>
		</tr>
	</table>
	<?php
}

// ── Save ─────────────────────────────────────────────────────────────────────

function fge_save_request_fields( int $post_id ) {
	if ( ! isset( $_POST['fge_request_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fge_request_nonce'] ) ), 'fge_request_fields' ) ) {
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
	if ( get_post_type( $post_id ) !== 'firmengolf_request' ) {
		return;
	}

	// Allowed values.
	$allowed_request_types      = [ 'specific_event', 'general_event_request' ];
	$allowed_request_statuses   = fge_get_statuses( 'request' );
	$allowed_contact_methods    = [ 'phone', 'email', 'any' ];
	$allowed_event_goals        = array_keys( fge_get_event_formats_flat( true ) );
	$allowed_preferred_times    = [ 'morning', 'afternoon', 'after_work', 'full_day', 'open' ];
	$allowed_integration_status = [ 'not_sent', 'sent', 'error' ];
	$allowed_sources            = array_keys( fge_request_source_options() );

	$san_select = static function ( string $key, array $allowed ): string {
		$val = sanitize_text_field( wp_unslash( $_POST[ $key ] ?? '' ) );
		return in_array( $val, $allowed, true ) ? $val : '';
	};

	// ── Metabox 1: Anfrage Basis ──
	// Read old status before saving, to detect changes.
	$old_status = get_post_meta( $post_id, '_fge_request_status', true );
	$new_status = $san_select( 'fge_request_status', $allowed_request_statuses );

	update_post_meta( $post_id, '_fge_request_type',        $san_select( 'fge_request_type', $allowed_request_types ) );
	$raw_event_id = absint( $_POST['fge_assigned_event_id'] ?? 0 );
	update_post_meta( $post_id, '_fge_assigned_event_id', ( $raw_event_id > 0 && get_post_type( $raw_event_id ) === 'firmengolf_event' ) ? $raw_event_id : 0 );

	$raw_partner_id = absint( $_POST['fge_assigned_partner_id'] ?? 0 );
	update_post_meta( $post_id, '_fge_assigned_partner_id', ( $raw_partner_id > 0 && get_post_type( $raw_partner_id ) === 'firmengolf_partner' ) ? $raw_partner_id : 0 );
	update_post_meta( $post_id, '_fge_request_status',      $new_status );

	// ── Metabox 2: Unternehmen ──
	update_post_meta( $post_id, '_fge_company_name',        sanitize_text_field( wp_unslash( $_POST['fge_company_name'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_industry',            sanitize_text_field( wp_unslash( $_POST['fge_industry'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_company_website',     esc_url_raw( wp_unslash( $_POST['fge_company_website'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_company_street',      sanitize_text_field( wp_unslash( $_POST['fge_company_street'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_company_postal_code', sanitize_text_field( wp_unslash( $_POST['fge_company_postal_code'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_company_city',        sanitize_text_field( wp_unslash( $_POST['fge_company_city'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_company_region',      sanitize_text_field( wp_unslash( $_POST['fge_company_region'] ?? '' ) ) );

	// ── Metabox 3: Kontakt ──
	update_post_meta( $post_id, '_fge_contact_first_name',        sanitize_text_field( wp_unslash( $_POST['fge_contact_first_name'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_contact_last_name',         sanitize_text_field( wp_unslash( $_POST['fge_contact_last_name'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_contact_email',             sanitize_email( wp_unslash( $_POST['fge_contact_email'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_contact_phone',             sanitize_text_field( wp_unslash( $_POST['fge_contact_phone'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_contact_role',              sanitize_text_field( wp_unslash( $_POST['fge_contact_role'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_preferred_contact_method',  $san_select( 'fge_preferred_contact_method', $allowed_contact_methods ) );

	// ── Metabox 4: Rahmen ──
	update_post_meta( $post_id, '_fge_expected_participants', absint( $_POST['fge_expected_participants'] ?? 0 ) );
	update_post_meta( $post_id, '_fge_participants_range',    sanitize_text_field( wp_unslash( $_POST['fge_participants_range'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_event_goal',            $san_select( 'fge_event_goal', $allowed_event_goals ) );
	update_post_meta( $post_id, '_fge_desired_region',        sanitize_text_field( wp_unslash( $_POST['fge_desired_region'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_budget_range',          sanitize_text_field( wp_unslash( $_POST['fge_budget_range'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_message',               sanitize_textarea_field( wp_unslash( $_POST['fge_message'] ?? '' ) ) );

	// ── Metabox 5: Termine ──
	update_post_meta( $post_id, '_fge_preferred_date_1',   sanitize_text_field( wp_unslash( $_POST['fge_preferred_date_1'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_preferred_date_2',   sanitize_text_field( wp_unslash( $_POST['fge_preferred_date_2'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_preferred_date_3',   sanitize_text_field( wp_unslash( $_POST['fge_preferred_date_3'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_alternative_period', sanitize_text_field( wp_unslash( $_POST['fge_alternative_period'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_preferred_time',     $san_select( 'fge_preferred_time', $allowed_preferred_times ) );

	// ── Metabox 6: Leistungen ──
	$leistungen_keys = [
		'wants_golf_teacher', 'wants_meeting_room', 'wants_breakfast', 'wants_lunch',
		'wants_dinner', 'wants_shuttle', 'wants_branding', 'wants_tournament_mode',
		'wants_bad_weather_alternative', 'wants_individual_customization',
	];
	foreach ( $leistungen_keys as $key ) {
		update_post_meta( $post_id, '_fge_' . $key, isset( $_POST[ 'fge_' . $key ] ) ? 1 : 0 );
	}
	update_post_meta( $post_id, '_fge_additional_wishes', sanitize_textarea_field( wp_unslash( $_POST['fge_additional_wishes'] ?? '' ) ) );

	// ── Kit / HubSpot (intern) ──
	update_post_meta( $post_id, '_fge_kit_opt_in',          isset( $_POST['fge_kit_opt_in'] ) ? 1 : 0 );
	update_post_meta( $post_id, '_fge_kit_status',          $san_select( 'fge_kit_status', $allowed_integration_status ) );
	update_post_meta( $post_id, '_fge_kit_tags',            sanitize_text_field( wp_unslash( $_POST['fge_kit_tags'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_hubspot_status',      $san_select( 'fge_hubspot_status', $allowed_integration_status ) );
	update_post_meta( $post_id, '_fge_hubspot_contact_id',  sanitize_text_field( wp_unslash( $_POST['fge_hubspot_contact_id'] ?? '' ) ) );

	// ── Metabox 9: Lexoffice ──
	update_post_meta( $post_id, '_fge_lexoffice_offer_created',   isset( $_POST['fge_lexoffice_offer_created'] ) ? 1 : 0 );
	update_post_meta( $post_id, '_fge_lexoffice_offer_number',    sanitize_text_field( wp_unslash( $_POST['fge_lexoffice_offer_number'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_lexoffice_invoice_created', isset( $_POST['fge_lexoffice_invoice_created'] ) ? 1 : 0 );
	update_post_meta( $post_id, '_fge_lexoffice_invoice_number',  sanitize_text_field( wp_unslash( $_POST['fge_lexoffice_invoice_number'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_lexoffice_note',            sanitize_textarea_field( wp_unslash( $_POST['fge_lexoffice_note'] ?? '' ) ) );

	// ── Tracking ──
	update_post_meta( $post_id, '_fge_source', $san_select( 'fge_request_source', $allowed_sources ) );
	update_post_meta( $post_id, '_fge_utm_source',     sanitize_text_field( wp_unslash( $_POST['fge_utm_source'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_utm_medium',     sanitize_text_field( wp_unslash( $_POST['fge_utm_medium'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_utm_campaign',   sanitize_text_field( wp_unslash( $_POST['fge_utm_campaign'] ?? '' ) ) );

	// ── System: request_date (set once, never overwritten) ──
	if ( get_post_meta( $post_id, '_fge_request_date', true ) === '' ) {
		add_post_meta( $post_id, '_fge_request_date', current_datetime()->format( 'Y-m-d H:i:s' ), true );
	}

	// ── System: last_status_change (update on status delta) ──
	if ( $new_status !== $old_status ) {
		update_post_meta( $post_id, '_fge_last_status_change', current_datetime()->format( 'Y-m-d H:i:s' ) );
	}
}
add_action( 'save_post', 'fge_save_request_fields' );

// ── Auto-Titel für Event Anfragen ─────────────────────────────────────────────

function fge_auto_title_request( int $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( get_post_type( $post_id ) !== 'firmengolf_request' ) {
		return;
	}

	$post = get_post( $post_id );
	if ( ! $post ) {
		return;
	}

	// Nur beim echten Admin-Metabox-Speichern greifen. Modal-/Wizard-/Kontakt-Handler
	// setzen ihren eigenen „FG-… · Name"-Titel beim Insert; der darf hier nicht vom
	// generischen Fallback überschrieben werden (AJAX/PRG senden keinen Admin-Nonce).
	if ( ! isset( $_POST['fge_request_nonce'] ) ) {
		return;
	}

	$company_name = sanitize_text_field( wp_unslash( $_POST['fge_company_name'] ?? '' ) );
	$last_name    = sanitize_text_field( wp_unslash( $_POST['fge_contact_last_name'] ?? '' ) );
	$date         = current_datetime()->format( 'Y-m-d' );

	if ( $company_name !== '' ) {
		$title = 'Anfrage ' . $company_name . ' ' . $date;
	} elseif ( $last_name !== '' ) {
		$title = 'Anfrage ' . $last_name . ' ' . $date;
	} else {
		$title = 'Event Anfrage ' . $post_id . ' ' . $date;
	}

	remove_action( 'save_post', 'fge_auto_title_request', 20 );
	wp_update_post( [
		'ID'         => $post_id,
		'post_title' => $title,
		'post_name'  => sanitize_title( $title ),
	] );
	add_action( 'save_post', 'fge_auto_title_request', 20 );
}
add_action( 'save_post', 'fge_auto_title_request', 20 );
