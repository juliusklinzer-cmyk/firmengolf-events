<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Registration ─────────────────────────────────────────────────────────────

function fge_register_event_metaboxes() {
	$screen = 'firmengolf_event';

	add_meta_box( 'fge_mb_basisdaten',  'Firmenevent Basisdaten',  'fge_render_mb_basisdaten',  $screen, 'normal', 'high' );
	add_meta_box( 'fge_mb_rahmen',      'Event Rahmen',            'fge_render_mb_rahmen',      $screen, 'normal', 'default' );
	add_meta_box( 'fge_mb_leistungen',  'Leistungen',              'fge_render_mb_leistungen',  $screen, 'normal', 'default' );
	add_meta_box( 'fge_mb_angebot_neu', 'Angebot: Preis & Inhalt', 'fge_render_mb_angebot_neu', $screen, 'normal', 'default' );
	add_meta_box( 'fge_mb_marktplatz',  'Marktplatz-Darstellung',  'fge_render_mb_marktplatz',  $screen, 'normal', 'default' );
	add_meta_box( 'fge_mb_seo',         'SEO Basis',               'fge_render_mb_seo',         $screen, 'normal', 'default' );
	add_meta_box( 'fge_mb_tracking',    'Tracking Basis',          'fge_render_mb_tracking',    $screen, 'side',   'default' );
	add_meta_box( 'fge_mb_review',      'Interne Prüfnotiz',       'fge_render_mb_review',      $screen, 'side',   'high' );
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

	$event_types = fge_get_event_formats()['standard'];
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
				<select id="fge_assigned_partner_id" name="fge_assigned_partner_id">
					<option value="0">— kein Golfplatz zugeordnet —</option>
					<?php foreach ( fge_get_posts_select_options( 'firmengolf_partner' ) as $pid => $label ) : ?>
						<option value="<?php echo esc_attr( $pid ); ?>" <?php selected( (int) $assigned_partner_id, $pid ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
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
	$available_weekdays = (array) get_post_meta( $post->ID, '_fge_available_weekdays', true );
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
	</table>
	<p class="description">Diese Häkchen liefern die „Im Preis enthalten"-Fallbacks und das Anfrage-Matching. Die kuratierten Leistungstexte pflegst du unter „Angebot: Preis &amp; Inhalt".</p>
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
				<p class="description">Pro Frage ein Block: erste Zeile = Frage, folgende Zeile(n) = Antwort. Blöcke durch eine Leerzeile trennen. Leer = allgemeine Standard-FAQ.</p>
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

function fge_render_mb_marktplatz( WP_Post $post ) {
	$featured = get_post_meta( $post->ID, '_fge_featured', true );
	$rating   = get_post_meta( $post->ID, '_fge_rating', true );
	$reviews  = (int) get_post_meta( $post->ID, '_fge_reviews_count', true );
	$gallery  = (string) get_post_meta( $post->ID, '_fge_event_gallery_ids', true );
	?>
	<table class="form-table">
		<tr>
			<th scope="row">Featured</th>
			<td><label><input type="checkbox" name="fge_featured" value="1" <?php checked( $featured, '1' ); ?>> Auf Startseite / Marktplatz hervorheben</label></td>
		</tr>
		<tr>
			<th scope="row">Bewertung (0–5)</th>
			<td>
				<input type="number" name="fge_rating" min="0" max="5" step="0.1" value="<?php echo esc_attr( $rating ); ?>" style="width:90px;">
				<p class="description">z. B. 4.9 — leer lassen bzw. 0, wenn keine Bewertung angezeigt werden soll.</p>
			</td>
		</tr>
		<tr>
			<th scope="row">Anzahl Bewertungen</th>
			<td><input type="number" name="fge_reviews_count" min="0" step="1" value="<?php echo esc_attr( (string) $reviews ); ?>" style="width:90px;"></td>
		</tr>
		<tr>
			<th scope="row">Galerie (Bild-IDs)</th>
			<td>
				<input type="text" name="fge_event_gallery_ids" value="<?php echo esc_attr( $gallery ); ?>" class="large-text" placeholder="z. B. 123, 145, 167">
				<p class="description">Komma-getrennte Medien-/Anhang-IDs. Werden auf der Detailseite als Galerie genutzt.</p>
			</td>
		</tr>
	</table>
	<?php
}

// ── Save ─────────────────────────────────────────────────────────────────────

function fge_render_mb_review( WP_Post $post ) {
	$note = (string) get_post_meta( $post->ID, '_fge_review_note', true );
	?>
	<p style="margin:0 0 6px;font-size:12px;color:#666;">Nur für Firmengolf Admin. Wird nicht öffentlich ausgegeben.</p>
	<textarea name="fge_review_note" id="fge_review_note" rows="5" style="width:100%;box-sizing:border-box;"><?php echo esc_textarea( $note ); ?></textarea>
	<?php
}

function fge_render_mb_angebot_neu( WP_Post $post ) {
	$price_mode = get_post_meta( $post->ID, '_fge_price_mode', true ) ?: 'gesamt';
	$amount     = get_post_meta( $post->ID, '_fge_price_amount', true );
	$basis      = get_post_meta( $post->ID, '_fge_price_basis', true ) ?: 'person';
	$items      = (array) get_post_meta( $post->ID, '_fge_line_items', true );
	$includes   = (array) get_post_meta( $post->ID, '_fge_event_includes', true );
	$dayflow    = (string) get_post_meta( $post->ID, '_fge_event_dayflow', true );
	$release    = get_post_meta( $post->ID, '_fge_release_mode', true ) ?: 'us';
	$items_text = implode( "\n", array_map( static fn( $i ): string => ( $i['label'] ?? '' ) . ' | ' . ( $i['cost'] ?? '' ), $items ) );
	$p          = fge_event_pricing( $post->ID );
	?>
	<p class="description">Partner hinterlegt <strong>netto</strong>; Firmengolf-Aufschlag fix <?php echo (int) FGE_MARKUP_PERCENT; ?> % oben drauf.</p>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="fge_price_mode">Preislogik</label></th>
			<td>
				<select id="fge_price_mode" name="fge_price_mode">
					<option value="gesamt" <?php selected( $price_mode, 'gesamt' ); ?>>Gesamtpreis</option>
					<option value="einzel" <?php selected( $price_mode, 'einzel' ); ?>>Einzelauflistung</option>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_price_amount">Gesamtpreis netto (€)</label></th>
			<td>
				<input type="text" id="fge_price_amount" name="fge_price_amount" value="<?php echo esc_attr( $amount ); ?>" style="width:120px;" placeholder="2400">
				&nbsp;Basis:
				<label><input type="radio" name="fge_price_basis" value="person"   <?php checked( $basis, 'person' ); ?>> pro Person</label>
				<label style="margin-left:10px;"><input type="radio" name="fge_price_basis" value="pauschal" <?php checked( $basis, 'pauschal' ); ?>> Pauschal</label>
				<p class="description">Nur bei „Gesamtpreis". Bei „Einzelauflistung" werden die Posten unten summiert.</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_line_items">Einzelposten</label></th>
			<td>
				<textarea id="fge_line_items" name="fge_line_items" rows="4" class="large-text" placeholder="Golflehrer | 80&#10;Meetingraum | 50"><?php echo esc_textarea( $items_text ); ?></textarea>
				<p class="description">Pro Zeile: <code>Bezeichnung | Kosten netto</code>. Nur bei „Einzelauflistung" relevant.</p>
			</td>
		</tr>
		<tr>
			<th scope="row">Preis-Vorschau</th>
			<td>
				Netto <strong>€<?php echo esc_html( number_format_i18n( $p['net'], 2 ) ); ?></strong>
				&nbsp;·&nbsp; Aufschlag <?php echo (int) FGE_MARKUP_PERCENT; ?> % <strong>€<?php echo esc_html( number_format_i18n( $p['markup'], 2 ) ); ?></strong>
				&nbsp;·&nbsp; Brutto fürs Unternehmen <strong>€<?php echo esc_html( number_format_i18n( $p['gross'], 2 ) ); ?> <?php echo esc_html( $p['unit'] ); ?></strong>
				<p class="description">Aktualisiert sich nach dem Speichern.</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_event_includes">Inkludierte Leistungen</label></th>
			<td>
				<textarea id="fge_event_includes" name="fge_event_includes" rows="5" class="large-text" placeholder="90 Min. Coaching&#10;Leihschläger&#10;Lunch im Clubhaus"><?php echo esc_textarea( implode( "\n", $includes ) ); ?></textarea>
				<p class="description">Eine Leistung pro Zeile. Erscheint als „Das ist dabei"-Liste im Angebot.</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_event_dayflow">So läuft der Tag ab</label></th>
			<td><textarea id="fge_event_dayflow" name="fge_event_dayflow" rows="4" class="large-text" placeholder="Ankunft & Begrüßung → Coaching → Lunch → Turnier → Ausklang"><?php echo esc_textarea( $dayflow ); ?></textarea></td>
		</tr>
		<tr>
			<th scope="row"><label for="fge_release_mode">Terminabstimmung</label></th>
			<td>
				<select id="fge_release_mode" name="fge_release_mode">
					<option value="us"      <?php selected( $release, 'us' ); ?>>Nur der Platz selbst gibt Anfragen frei</option>
					<option value="approve" <?php selected( $release, 'approve' ); ?>>Zusätzliche Personen stimmen Termine ab</option>
				</select>
				<?php
				$resp_sel    = array_map( 'absint', (array) get_post_meta( $post->ID, '_fge_event_responder_ids', true ) );
				$ev_partner  = (int) get_post_meta( $post->ID, '_fge_assigned_partner_id', true );
				$ev_contacts = ( $ev_partner > 0 && function_exists( 'fge_contacts_get' ) ) ? fge_contacts_get( $ev_partner ) : [];
				if ( $ev_contacts ) :
				?>
				<fieldset style="margin-top:10px;">
					<?php foreach ( $ev_contacts as $fc ) :
						$is_checked = $resp_sel ? in_array( (int) $fc['id'], $resp_sel, true ) : ( ( $fc['permission'] ?? '' ) === 'vote' );
					?>
					<label style="display:block;margin-bottom:4px;">
						<input type="checkbox" name="fge_event_responders[]" value="<?php echo esc_attr( (string) $fc['id'] ); ?>" <?php checked( $is_checked ); ?>>
						<?php echo esc_html( $fc['name'] . ( '' !== (string) ( $fc['role'] ?? '' ) ? ' · ' . $fc['role'] : '' ) ); ?>
					</label>
					<?php endforeach; ?>
				</fieldset>
				<p class="description">Welche Ansprechpartner für dieses Event Termine abstimmen. Keine Auswahl = alle Kontakte mit Berechtigung „Terminabstimmung".</p>
				<?php elseif ( $ev_partner > 0 ) : ?>
				<p class="description">Der zugewiesene Partner hat noch keine Ansprechpartner angelegt.</p>
				<?php else : ?>
				<p class="description">Ansprechpartner-Auswahl erscheint, sobald ein Partner zugewiesen ist.</p>
				<?php endif; ?>
			</td>
		</tr>
	</table>
	<?php
}

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
	$allowed_event_types    = array_keys( fge_get_event_formats()['standard'] );
	$allowed_provider_types = [ 'firmengolf', 'golfplatz_partner' ];
	$allowed_event_statuses = fge_get_statuses( 'event' );
	$allowed_weekdays       = [ 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' ];

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
	$raw_partner_id = absint( $_POST['fge_assigned_partner_id'] ?? 0 );
	update_post_meta( $post_id, '_fge_assigned_partner_id', ( $raw_partner_id > 0 && get_post_type( $raw_partner_id ) === 'firmengolf_partner' ) ? $raw_partner_id : 0 );
	update_post_meta( $post_id, '_fge_event_status',        $san_select( 'fge_event_status', $allowed_event_statuses ) );
	update_post_meta( $post_id, '_fge_card_description',    sanitize_textarea_field( wp_unslash( $_POST['fge_card_description'] ?? '' ) ) );

	// ── Metabox 2: Rahmen ──
	update_post_meta( $post_id, '_fge_participants_min', absint( $_POST['fge_participants_min'] ?? 0 ) );
	update_post_meta( $post_id, '_fge_participants_max', absint( $_POST['fge_participants_max'] ?? 0 ) );
	update_post_meta( $post_id, '_fge_duration',         sanitize_text_field( wp_unslash( $_POST['fge_duration'] ?? '' ) ) );
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

	// ── Metabox: Marktplatz-Darstellung ──
	update_post_meta( $post_id, '_fge_featured',       isset( $_POST['fge_featured'] ) ? 1 : 0 );
	update_post_meta( $post_id, '_fge_rating',         max( 0.0, min( 5.0, $san_decimal( 'fge_rating' ) ) ) );
	update_post_meta( $post_id, '_fge_reviews_count',  absint( $_POST['fge_reviews_count'] ?? 0 ) );
	$gallery_raw = sanitize_text_field( wp_unslash( $_POST['fge_event_gallery_ids'] ?? '' ) );
	$gallery_ids = implode( ',', array_filter( array_map( 'absint', explode( ',', $gallery_raw ) ) ) );
	update_post_meta( $post_id, '_fge_event_gallery_ids', $gallery_ids );

	// ── Metabox 6: SEO ──
	update_post_meta( $post_id, '_fge_seo_title',        sanitize_text_field( wp_unslash( $_POST['fge_seo_title'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_meta_description', sanitize_textarea_field( wp_unslash( $_POST['fge_meta_description'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_focus_keyword',    sanitize_text_field( wp_unslash( $_POST['fge_focus_keyword'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_faq_content',      sanitize_textarea_field( wp_unslash( $_POST['fge_faq_content'] ?? '' ) ) );

	// ── Angebot — Preis & Inhalt (rev. 2) ──
	$price_mode = in_array( $_POST['fge_price_mode'] ?? '', [ 'gesamt', 'einzel' ], true ) ? $_POST['fge_price_mode'] : 'gesamt';
	update_post_meta( $post_id, '_fge_price_mode', $price_mode );
	$amount_raw = preg_replace( '/[^\d.,]/', '', (string) wp_unslash( $_POST['fge_price_amount'] ?? '' ) );
	update_post_meta( $post_id, '_fge_price_amount', (float) str_replace( ',', '.', $amount_raw ) );
	update_post_meta( $post_id, '_fge_price_basis', in_array( $_POST['fge_price_basis'] ?? '', [ 'person', 'pauschal' ], true ) ? $_POST['fge_price_basis'] : 'person' );

	$line_items = [];
	foreach ( preg_split( '/\r?\n/', (string) wp_unslash( $_POST['fge_line_items'] ?? '' ) ) as $line ) {
		$line = trim( $line );
		if ( $line === '' ) {
			continue;
		}
		$parts = explode( '|', $line, 2 );
		$label = sanitize_text_field( trim( $parts[0] ?? '' ) );
		$cost  = (float) str_replace( ',', '.', preg_replace( '/[^\d.,]/', '', $parts[1] ?? '' ) );
		if ( $label !== '' ) {
			$line_items[] = [ 'label' => $label, 'cost' => $cost ];
		}
	}
	update_post_meta( $post_id, '_fge_line_items', $line_items );

	$includes = array_values( array_filter( array_map(
		static fn( $l ): string => sanitize_text_field( trim( $l ) ),
		preg_split( '/\r?\n/', (string) wp_unslash( $_POST['fge_event_includes'] ?? '' ) )
	) ) );
	update_post_meta( $post_id, '_fge_event_includes', $includes );
	update_post_meta( $post_id, '_fge_event_dayflow', sanitize_textarea_field( wp_unslash( $_POST['fge_event_dayflow'] ?? '' ) ) );
	update_post_meta( $post_id, '_fge_release_mode', in_array( $_POST['fge_release_mode'] ?? '', [ 'us', 'approve' ], true ) ? $_POST['fge_release_mode'] : 'us' );
	update_post_meta( $post_id, '_fge_event_responder_ids', array_values( array_filter( array_map( 'absint', (array) ( $_POST['fge_event_responders'] ?? [] ) ) ) ) );

	// Öffentliche Preis-Felder aus dem neuen Modell spiegeln (eine Quelle: event-pricing.php).
	$pr = fge_event_pricing( $post_id );
	if ( $pr['gross'] > 0 ) {
		update_post_meta( $post_id, '_fge_sale_price_net', $pr['gross'] );
		update_post_meta( $post_id, '_fge_public_price_label', fge_event_price_label( $post_id ) );
	}

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

	// ── Metabox: Prüfnotiz (Admin only) ──
	update_post_meta( $post_id, '_fge_review_note', sanitize_textarea_field( wp_unslash( $_POST['fge_review_note'] ?? '' ) ) );
}
add_action( 'save_post', 'fge_save_event_fields' );
