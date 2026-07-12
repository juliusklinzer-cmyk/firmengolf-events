<?php
/**
 * Bildnachweis (© Fotograf) pro Bild.
 *
 * Hintergrund (Julius, 2026-07-12): Fotografen haben nach § 13 UrhG ein Recht auf
 * Namensnennung (z. B. Stefan von Stengel bei Hamburg-Ahrensburg). Der Nachweis
 * liegt als Attachment-Meta `_fge_image_credit` am Bild und wird öffentlich als
 * kleines Overlay unten rechts am Bild angezeigt: weiße Schrift auf halbtransparent
 * dunklem Pill, damit es auf hellen wie dunklen Fotos lesbar ist.
 *
 * Pflege: Mediathek (Admin) oder ©-Button im Portal-Medienbereich (rest-media.php).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Hinterlegter Bildnachweis eines Attachments ('' wenn keiner). */
function fge_image_credit( int $att_id ): string {
	return $att_id > 0 ? trim( (string) get_post_meta( $att_id, '_fge_image_credit', true ) ) : '';
}

/** Bildnachweis speichern (leer = löschen). */
function fge_image_credit_set( int $att_id, string $credit ): void {
	$credit = trim( sanitize_text_field( $credit ) );
	if ( '' === $credit ) {
		delete_post_meta( $att_id, '_fge_image_credit' );
	} else {
		update_post_meta( $att_id, '_fge_image_credit', mb_substr( $credit, 0, 120 ) );
	}
}

/**
 * Overlay-Markup fürs Bild ('' wenn kein Nachweis). Der umgebende Container
 * braucht position:relative (übernimmt das CSS für die bekannten Container).
 */
function fge_image_credit_overlay( int $att_id ): string {
	$c = fge_image_credit( $att_id );
	if ( '' === $c ) {
		return '';
	}
	// Vorangestelltes © nicht doppeln, falls schon eingetippt.
	$c = preg_replace( '/^\s*(©|\(c\)|copyright:?)\s*/iu', '', $c );
	return '<span class="fge-img-credit">© ' . esc_html( $c ) . '</span>';
}

// ── Admin: Feld in der Mediathek (Bild bearbeiten / Anhang-Details) ───────────

add_filter( 'attachment_fields_to_edit', static function ( array $fields, WP_Post $post ): array {
	if ( str_starts_with( (string) $post->post_mime_type, 'image/' ) ) {
		$fields['fge_image_credit'] = [
			'label' => 'Bildnachweis (Fotograf)',
			'input' => 'text',
			'value' => fge_image_credit( (int) $post->ID ),
			'helps' => 'Wird öffentlich als „© Name" klein unten rechts am Bild angezeigt. Leer lassen, wenn keine Nennung gefordert ist.',
		];
	}
	return $fields;
}, 10, 2 );

add_filter( 'attachment_fields_to_save', static function ( array $post, array $attachment ): array {
	if ( isset( $attachment['fge_image_credit'] ) ) {
		fge_image_credit_set( (int) $post['ID'], (string) $attachment['fge_image_credit'] );
	}
	return $post;
}, 10, 2 );
