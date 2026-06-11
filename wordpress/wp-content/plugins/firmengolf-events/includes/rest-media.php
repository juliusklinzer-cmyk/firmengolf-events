<?php
/**
 * REST layer for the partner photo library (firmengolf/v1).
 *
 * Powers the async Airbnb-style gallery widget: upload / delete / reorder photos and
 * upload the logo, without full page reloads. All writes go through the central
 * fge_partner_*() setters (partner-media.php) so the Titelfoto mirror stays consistent.
 *
 * Auth: cookie + X-WP-Nonce (wp_rest). permission_callback checks partner ownership.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Ownership gate: admins always, otherwise the partner must be assigned to the current user. */
function fge_rest_media_can_edit( int $partner_id ): bool {
	if ( $partner_id <= 0 || get_post_type( $partner_id ) !== 'firmengolf_partner' ) {
		return false;
	}
	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}
	$owner = (int) get_post_meta( $partner_id, '_fge_assigned_wp_user_id', true );
	return $owner > 0 && $owner === get_current_user_id();
}

/** Compact JSON shape for one attachment. */
function fge_rest_photo_payload( int $att_id ): array {
	$meta = wp_get_attachment_metadata( $att_id );
	return [
		'id'    => $att_id,
		'thumb' => (string) wp_get_attachment_image_url( $att_id, 'thumbnail' ),
		'large' => (string) wp_get_attachment_image_url( $att_id, 'large' ),
		'full'  => (string) wp_get_attachment_image_url( $att_id, 'full' ),
		'name'  => (string) get_the_title( $att_id ),
		'width' => (int) ( is_array( $meta ) ? ( $meta['width'] ?? 0 ) : 0 ),
	];
}

/** Map a partner's ordered gallery to photo payloads. */
function fge_rest_gallery_payload( int $partner_id ): array {
	return array_map( 'fge_rest_photo_payload', fge_partner_gallery_ids( $partner_id ) );
}

/** Validate an uploaded file against a size cap + allowed MIME list. Returns WP_Error or null. */
function fge_rest_validate_upload( string $field, int $max, array $mimes ) {
	if ( empty( $_FILES[ $field ]['name'] ) ) {
		return new WP_Error( 'fge_no_file', 'Keine Datei übermittelt.', [ 'status' => 400 ] );
	}
	if ( ( $_FILES[ $field ]['error'] ?? 1 ) !== UPLOAD_ERR_OK ) {
		return new WP_Error( 'fge_upload_error', 'Upload fehlgeschlagen. Bitte erneut versuchen.', [ 'status' => 400 ] );
	}
	if ( (int) $_FILES[ $field ]['size'] > $max ) {
		return new WP_Error( 'fge_too_large', 'Datei zu groß (max. ' . size_format( $max ) . ').', [ 'status' => 413 ] );
	}
	$ft = wp_check_filetype_and_ext( $_FILES[ $field ]['tmp_name'], (string) $_FILES[ $field ]['name'] );
	if ( ! in_array( (string) ( $ft['type'] ?? '' ), $mimes, true ) ) {
		return new WP_Error( 'fge_bad_type', 'Nur JPG, PNG oder WebP erlaubt.', [ 'status' => 415 ] );
	}
	return null;
}

/** Run WordPress' attachment upload for $_FILES[$field], attached to the partner post. */
function fge_rest_handle_upload( string $field, int $partner_id, string $alt_suffix = 'Platzfoto' ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	$att = media_handle_upload( $field, $partner_id );
	if ( is_wp_error( $att ) ) {
		return new WP_Error( 'fge_upload_failed', $att->get_error_message(), [ 'status' => 400 ] );
	}
	// Alt-Text automatisch setzen (SEO/Barrierefreiheit), z. B. „Golfclub X, Platzfoto".
	$name = (string) get_post_meta( $partner_id, '_fge_public_golfclub_name', true ) ?: get_the_title( $partner_id );
	$alt  = $name !== '' ? $name . ', ' . $alt_suffix : $alt_suffix;
	update_post_meta( (int) $att, '_wp_attachment_image_alt', $alt );
	return (int) $att;
}

add_action( 'rest_api_init', 'fge_rest_media_routes' );
function fge_rest_media_routes(): void {
	$can = static function ( WP_REST_Request $req ): bool {
		return fge_rest_media_can_edit( (int) $req['id'] );
	};
	$id_arg = [ 'id' => [ 'validate_callback' => static fn( $v ) => is_numeric( $v ) ] ];

	register_rest_route( 'firmengolf/v1', '/partner/(?P<id>\d+)/gallery', [
		[
			'methods'             => 'GET',
			'callback'            => 'fge_rest_gallery_get',
			'permission_callback' => $can,
			'args'                => $id_arg,
		],
		[
			'methods'             => 'POST',
			'callback'            => 'fge_rest_gallery_upload',
			'permission_callback' => $can,
			'args'                => $id_arg,
		],
	] );

	register_rest_route( 'firmengolf/v1', '/partner/(?P<id>\d+)/gallery/order', [
		'methods'             => 'POST',
		'callback'            => 'fge_rest_gallery_reorder',
		'permission_callback' => $can,
		'args'                => [
			'id'  => [ 'validate_callback' => static fn( $v ) => is_numeric( $v ) ],
			'ids' => [ 'required' => true, 'type' => 'array' ],
		],
	] );

	register_rest_route( 'firmengolf/v1', '/partner/(?P<id>\d+)/gallery/(?P<att>\d+)', [
		'methods'             => 'DELETE',
		'callback'            => 'fge_rest_gallery_delete',
		'permission_callback' => $can,
		'args'                => [
			'id'  => [ 'validate_callback' => static fn( $v ) => is_numeric( $v ) ],
			'att' => [ 'validate_callback' => static fn( $v ) => is_numeric( $v ) ],
		],
	] );

	register_rest_route( 'firmengolf/v1', '/partner/(?P<id>\d+)/logo', [
		'methods'             => 'POST',
		'callback'            => 'fge_rest_logo_upload',
		'permission_callback' => $can,
		'args'                => $id_arg,
	] );
}

/** GET — current ordered gallery + cover id. */
function fge_rest_gallery_get( WP_REST_Request $req ) {
	$pid = (int) $req['id'];
	return [
		'gallery' => fge_rest_gallery_payload( $pid ),
		'cover'   => fge_partner_cover_id( $pid ),
	];
}

/** POST — upload one photo, append to the gallery. */
function fge_rest_gallery_upload( WP_REST_Request $req ) {
	$pid = (int) $req['id'];
	$lim = fge_onboarding_media_limits();

	$err = fge_rest_validate_upload( 'file', $lim['gallery'], $lim['mimes'] );
	if ( is_wp_error( $err ) ) {
		return $err;
	}
	$att = fge_rest_handle_upload( 'file', $pid );
	if ( is_wp_error( $att ) ) {
		return $att;
	}
	$ids = fge_partner_gallery_add( $pid, $att );
	return new WP_REST_Response( [
		'photo'   => fge_rest_photo_payload( (int) $att ),
		'gallery' => array_map( 'fge_rest_photo_payload', $ids ),
		'cover'   => fge_partner_cover_id( $pid ),
	], 201 );
}

/** DELETE — remove one photo from the gallery (keeps the underlying file). */
function fge_rest_gallery_delete( WP_REST_Request $req ) {
	$pid = (int) $req['id'];
	$ids = fge_partner_gallery_remove( $pid, (int) $req['att'] );
	return [
		'gallery' => array_map( 'fge_rest_photo_payload', $ids ),
		'cover'   => fge_partner_cover_id( $pid ),
	];
}

/** POST — reorder the gallery to the given list of attachment IDs. */
function fge_rest_gallery_reorder( WP_REST_Request $req ) {
	$pid = (int) $req['id'];
	$ids = $req->get_param( 'ids' );
	if ( ! is_array( $ids ) ) {
		return new WP_Error( 'fge_bad_order', 'Ungültige Reihenfolge.', [ 'status' => 400 ] );
	}
	$new = fge_partner_gallery_reorder( $pid, $ids );
	return [
		'gallery' => array_map( 'fge_rest_photo_payload', $new ),
		'cover'   => fge_partner_cover_id( $pid ),
	];
}

/** POST — upload/replace the partner logo. */
function fge_rest_logo_upload( WP_REST_Request $req ) {
	$pid = (int) $req['id'];
	$lim = fge_onboarding_media_limits();

	$err = fge_rest_validate_upload( 'file', $lim['logo'], $lim['mimes'] );
	if ( is_wp_error( $err ) ) {
		return $err;
	}
	$att = fge_rest_handle_upload( 'file', $pid, 'Logo' );
	if ( is_wp_error( $att ) ) {
		return $att;
	}
	update_post_meta( $pid, '_fge_logo_attachment_id', (int) $att );
	return new WP_REST_Response( [ 'logo' => fge_rest_photo_payload( (int) $att ) ], 201 );
}
