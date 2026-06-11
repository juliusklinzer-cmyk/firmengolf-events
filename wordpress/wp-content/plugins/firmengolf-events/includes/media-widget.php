<?php
/**
 * Airbnb-style media gallery widget (frontend).
 *
 * Renders a container that the fge-media-gallery.js module fills via the firmengolf/v1
 * REST routes (upload/delete/reorder/logo). Reusable across the onboarding media slide,
 * the portal "Platz" tab and the event image picker.
 *
 * Enqueue with fge_media_enqueue($partner_id), then output with fge_media_gallery_render().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** JS config (localized as FGE_MEDIA) for the gallery widget of one partner. */
function fge_media_widget_config( int $partner_id ): array {
	$lim = fge_onboarding_media_limits();
	$lid = (int) get_post_meta( $partner_id, '_fge_logo_attachment_id', true );

	return [
		'restRoot'  => esc_url_raw( rest_url( 'firmengolf/v1' ) ),
		'nonce'     => wp_create_nonce( 'wp_rest' ),
		'partnerId' => $partner_id,
		'limits'    => [
			'mimes'          => array_values( $lim['mimes'] ),
			'exts'           => $lim['exts'],
			'logo'           => $lim['logo'],
			'gallery'        => $lim['gallery'],
			'coverMinW'      => $lim['cover_min_width'],
			'recommendedMin' => 5,
		],
		'gallery'   => fge_rest_gallery_payload( $partner_id ),
		'logo'      => $lid > 0 ? fge_rest_photo_payload( $lid ) : null,
		'i18n'      => [
			'emptyTitle'   => 'Füge ein paar Fotos deines Platzes hinzu',
			'emptyHint'    => 'Du brauchst mindestens 5 Fotos, um loszulegen. Du kannst später jederzeit weitere hinzufügen oder ändern.',
			'addPhotos'    => 'Fotos hinzufügen',
			'addMore'      => 'Weitere hinzufügen',
			'reorderHint'  => 'Ziehe Fotos per Drag & Drop, um die Reihenfolge zu ändern.',
			'slotLogoLabel'  => 'Logo',
			'slotCoverLabel' => 'Titelbild',
			'slotLogoEmpty'  => 'Logo ablegen oder klicken',
			'slotCoverEmpty' => 'Titelbild ablegen oder klicken',
			'slotCoverHint'  => 'Euer wichtigstes Bild. Ideal mindestens 1600 px breit, Querformat.',
			'slotSwap'       => 'Tauschen',
			'galleryTitle'   => 'Weitere Fotos',
			'dropHint'       => 'Fotos hierher ziehen oder klicken',
			'checkLogo'      => 'Logo',
			'checkCover'     => 'Titelbild',
			'checkPhotos'    => 'Fotos',
			'coverBadge'   => 'Titelfoto',
			'menuCover'    => 'Als Titelfoto festlegen',
			'menuRemove'   => 'Entfernen',
			'modalTitle'   => 'Lade Fotos hoch',
			'selected'     => '%d ausgewählt',
			'uploaded'     => '%1$d von %2$d hochgeladen',
			'cancel'       => 'Abbrechen',
			'upload'       => 'Hochladen',
			'recommend'    => 'Noch %d Foto(s) bis zur Empfehlung (5).',
			'recommendOk'  => 'Genug Fotos — sieht super aus!',
			'logoLabel'    => 'Logo',
			'logoHint'     => 'PNG mit transparentem Hintergrund, ideal ca. 400×400 px. Erscheint in der Trefferliste.',
			'logoChoose'   => 'Logo wählen',
			'logoReplace'  => 'Logo tauschen',
			'confirmDelete'=> 'Dieses Foto entfernen?',
			'errTooLarge'  => 'Datei zu groß',
			'errBadType'   => 'Nur JPG, PNG oder WebP erlaubt.',
			'coverMinWarn' => 'Dein Titelbild ist nur %1$d px breit. Empfohlen sind mindestens %2$d px, sonst kann es unscharf wirken.',
			'pickEmpty'    => 'Du hast noch keine Fotos in deiner Platz-Galerie. Lade hier welche hoch oder im Tab „Platz".',
			'pickUpload'   => 'Foto hochladen',
			'pickCover'    => 'Als Titelbild',
			'pickCoverBadge' => 'Titelbild',
			'pickHint'     => 'Tippe Fotos an, um sie diesem Event zuzuordnen. Das Titelbild erscheint auf der Angebotskarte.',
		],
	];
}

/** Enqueue the gallery widget CSS/JS and localize the per-partner config. Call once per page. */
function fge_media_enqueue( int $partner_id ): void {
	$base = FGE_DIR . 'firmengolf-events.php';
	wp_enqueue_style( 'fge-media-gallery', plugins_url( 'assets/css/fge-media-gallery.css', $base ), [ 'fge-frontend' ], FGE_VERSION );
	wp_enqueue_script( 'fge-media-gallery', plugins_url( 'assets/js/fge-media-gallery.js', $base ), [], FGE_VERSION, true );
	wp_localize_script( 'fge-media-gallery', 'FGE_MEDIA', fge_media_widget_config( $partner_id ) );
}

/** Enqueue the event image picker (selects from the partner library). Reuses the gallery assets. */
function fge_event_picker_enqueue( int $partner_id ): void {
	fge_media_enqueue( $partner_id ); // provides FGE_MEDIA + CSS (gallery JS stays inert without a container)
	wp_enqueue_script(
		'fge-event-picker',
		plugins_url( 'assets/js/fge-event-picker.js', FGE_DIR . 'firmengolf-events.php' ),
		[ 'fge-media-gallery' ],
		FGE_VERSION,
		true
	);
}

/**
 * Output the event image picker. JS (fge-event-picker.js) renders the partner's library as a
 * selectable grid. Hidden inputs carry the selection (fge_event_gallery_ids, cover first) and the
 * cover id (fge_event_cover_id), submitted with the event form.
 *
 * @param int    $cover_id     Currently selected cover attachment id (0 for new).
 * @param string $selected_csv Ordered selected attachment ids (cover first), comma-separated.
 */
function fge_event_picker_render( int $cover_id, string $selected_csv ): void {
	$selected = implode( ',', array_filter( array_map( 'absint', explode( ',', $selected_csv ) ) ) );
	?>
	<div class="fge-picker" data-fge-picker data-cover="<?php echo (int) $cover_id; ?>" data-selected="<?php echo esc_attr( $selected ); ?>">
		<input type="hidden" name="fge_event_cover_id"   value="<?php echo (int) $cover_id; ?>" data-fge-picker-cover>
		<input type="hidden" name="fge_event_gallery_ids" value="<?php echo esc_attr( $selected ); ?>" data-fge-picker-ids>
		<div class="fge-picker-grid" data-fge-picker-grid></div>
	</div>
	<?php
}

/**
 * Persist an event's image selection from the picker. Only attachments that belong to the partner's
 * library are accepted. Stores the cover (_fge_cover_attachment_id + featured image, used by the
 * detail/archive templates) and the remaining photos (_fge_event_gallery_ids, cover excluded).
 */
function fge_event_save_images( int $event_id, int $partner_id, array $post ): void {
	$library = fge_partner_gallery_ids( $partner_id );
	if ( empty( $library ) ) {
		return; // Nothing to pick from — leave any existing values untouched.
	}
	$selected = array_values( array_filter(
		array_filter( array_map( 'absint', explode( ',', (string) ( $post['fge_event_gallery_ids'] ?? '' ) ) ) ),
		static fn( $id ) => in_array( $id, $library, true )
	) );

	$cover = absint( $post['fge_event_cover_id'] ?? 0 );
	if ( $cover <= 0 || ! in_array( $cover, $selected, true ) ) {
		$cover = $selected[0] ?? 0;
	}
	$rest = array_values( array_filter( $selected, static fn( $id ) => $id !== $cover ) );

	update_post_meta( $event_id, '_fge_cover_attachment_id', $cover );
	update_post_meta( $event_id, '_fge_event_gallery_ids', implode( ',', $rest ) );
	if ( $cover > 0 ) {
		set_post_thumbnail( $event_id, $cover );
	} else {
		delete_post_thumbnail( $event_id );
	}
}

/**
 * Renders a partner's infrastructure as grouped 2×2 cards (green label → white card with icon rows).
 * Same markup on the public golf-course page AND the portal "Platz" view. "Tagungstechnik" is
 * merged into "Im Clubhaus"; fixed group order. Outputs nothing if no infrastructure is set.
 */
function fge_render_amenities_grid( int $partner_id ): void {
	$infra = array_map( 'strval', (array) get_post_meta( $partner_id, '_fge_infra', true ) );
	if ( empty( $infra ) ) {
		return;
	}
	$merged = [];
	foreach ( fge_catalog_infra_groups() as $gname => $items ) {
		$target            = ( 'Tagungstechnik' === $gname ) ? 'Im Clubhaus' : $gname;
		$merged[ $target ] = ( $merged[ $target ] ?? [] ) + $items;
	}
	echo '<div class="gp-amenity-grid">';
	foreach ( [ 'Auf dem Platz', 'Im Clubhaus', 'Gastronomie', 'Golfschule' ] as $gname ) {
		$hits = array_filter( $merged[ $gname ] ?? [], static fn( $l, $id ) => in_array( (string) $id, $infra, true ), ARRAY_FILTER_USE_BOTH );
		if ( ! $hits ) {
			continue;
		}
		echo '<div class="gp-amenity-block"><div class="gp-amenity-label">' . esc_html( $gname ) . '</div><div class="gp-amenity-card">';
		foreach ( $hits as $id => $label ) {
			$icon = function_exists( 'fge_infra_icon' ) ? fge_infra_icon( (string) $id ) : '';
			echo '<div class="gp-amenity-row"><span class="gp-amenity-ico">' . $icon . '</span><span>' . esc_html( $label ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput — $icon is trusted SVG
		}
		echo '</div></div>';
	}
	echo '</div>';
}

/** Assigned partner id for an event (0 if none). */
function fge_event_partner_id( int $event_id ): int {
	return (int) get_post_meta( $event_id, '_fge_assigned_partner_id', true );
}

/**
 * Effective cover attachment id for an event: the event's own cover, else its featured image,
 * else — as a fallback — the assigned partner's Titelfoto. 0 if nothing is available.
 */
function fge_event_cover_id( int $event_id ): int {
	$cover = (int) get_post_meta( $event_id, '_fge_cover_attachment_id', true );
	if ( $cover > 0 ) {
		return $cover;
	}
	$thumb = (int) get_post_thumbnail_id( $event_id );
	if ( $thumb > 0 ) {
		return $thumb;
	}
	$pid = fge_event_partner_id( $event_id );
	return $pid > 0 ? fge_partner_cover_id( $pid ) : 0;
}

/** Cover image URL for an event (with partner-library fallback), or a placeholder file when none. */
function fge_event_cover_url( int $event_id, string $size = 'large', string $placeholder = 'golf-coaching-gruppe.jpg' ): string {
	$id = fge_event_cover_id( $event_id );
	if ( $id > 0 ) {
		$url = wp_get_attachment_image_url( $id, $size );
		if ( $url ) {
			return (string) $url;
		}
	}
	return fge_get_placeholder_image_url( $placeholder, $event_id );
}

/**
 * Effective gallery attachment ids for an event (the photo strip, cover excluded). Falls back to
 * the assigned partner's library when the event has no own selection — so an event always shows
 * the golf course's photos unless the partner picked specific ones.
 */
function fge_event_gallery_ids( int $event_id ): array {
	$own = array_values( array_filter( array_map( 'absint', explode( ',', (string) get_post_meta( $event_id, '_fge_event_gallery_ids', true ) ) ) ) );
	if ( ! empty( $own ) ) {
		return $own;
	}
	$pid = fge_event_partner_id( $event_id );
	if ( $pid <= 0 ) {
		return [];
	}
	$cover = fge_event_cover_id( $event_id );
	return array_values( array_filter( fge_partner_gallery_ids( $pid ), static fn( $id ) => $id !== $cover ) );
}

/**
 * Output the widget container. JS (fge-media-gallery.js) fills it from FGE_MEDIA.
 *
 * @param array $args { show_logo?: bool }
 */
function fge_media_gallery_render( array $args = [] ): void {
	$show_logo = $args['show_logo'] ?? true;
	?>
	<div class="fge-media" data-fge-media>
		<?php if ( $show_logo ) : ?>
		<div class="fge-logo" data-fge-logo></div>
		<?php endif; ?>
		<div class="fge-gallery" data-fge-gallery>
			<div class="fge-gallery-loading">Galerie wird geladen…</div>
		</div>
	</div>
	<?php
}
