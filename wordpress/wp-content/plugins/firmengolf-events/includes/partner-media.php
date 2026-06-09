<?php
/**
 * Partner photo library — single source of truth for partner images.
 *
 * The gallery is ONE ordered attachment-ID list (_fge_gallery_attachment_ids, CSV).
 * Element 0 is the Titelfoto (cover). The legacy single-cover meta
 * (_fge_hero_image_attachment_id) is kept mirrored to gallery[0] so existing consumers
 * (portal overview, listing cards, event detail) keep working untouched.
 *
 * ALWAYS write the gallery through fge_partner_set_gallery() (or the add/remove/reorder
 * wrappers) so the cover mirror stays consistent everywhere.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Parse the ordered gallery attachment IDs for a partner. */
function fge_partner_gallery_ids( int $partner_id ): array {
	$raw = (string) get_post_meta( $partner_id, '_fge_gallery_attachment_ids', true );
	return array_values( array_filter( array_map( 'absint', explode( ',', $raw ) ) ) );
}

/** Partner cover (Titelfoto) = first gallery image, falling back to the legacy hero meta. */
function fge_partner_cover_id( int $partner_id ): int {
	$ids = fge_partner_gallery_ids( $partner_id );
	if ( ! empty( $ids ) ) {
		return (int) $ids[0];
	}
	return (int) get_post_meta( $partner_id, '_fge_hero_image_attachment_id', true );
}

/**
 * Central setter: dedupe, store the ordered CSV, and mirror the legacy hero meta to gallery[0].
 * Returns the cleaned, ordered list that was written.
 */
function fge_partner_set_gallery( int $partner_id, array $ids ): array {
	$clean = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	update_post_meta( $partner_id, '_fge_gallery_attachment_ids', implode( ',', $clean ) );
	update_post_meta( $partner_id, '_fge_hero_image_attachment_id', $clean[0] ?? 0 );
	return $clean;
}

/** Append attachment IDs to the end of the gallery (no duplicates). Returns the new list. */
function fge_partner_gallery_add( int $partner_id, $att_ids ): array {
	$current = fge_partner_gallery_ids( $partner_id );
	foreach ( (array) $att_ids as $id ) {
		$id = absint( $id );
		if ( $id > 0 && ! in_array( $id, $current, true ) ) {
			$current[] = $id;
		}
	}
	return fge_partner_set_gallery( $partner_id, $current );
}

/** Remove one attachment from the gallery list (keeps the underlying file). Returns the new list. */
function fge_partner_gallery_remove( int $partner_id, int $att_id ): array {
	$att_id  = absint( $att_id );
	$current = array_values( array_filter( fge_partner_gallery_ids( $partner_id ), static fn( $id ) => $id !== $att_id ) );
	return fge_partner_set_gallery( $partner_id, $current );
}

/**
 * Reorder the gallery to exactly the given IDs. Only IDs already in the list are honoured;
 * any current ID omitted from $ordered_ids is appended so nothing silently disappears.
 * Returns the new list.
 */
function fge_partner_gallery_reorder( int $partner_id, array $ordered_ids ): array {
	$current = fge_partner_gallery_ids( $partner_id );
	$ordered = array_values( array_filter(
		array_map( 'absint', $ordered_ids ),
		static fn( $id ) => in_array( $id, $current, true )
	) );
	foreach ( $current as $id ) {
		if ( ! in_array( $id, $ordered, true ) ) {
			$ordered[] = $id;
		}
	}
	return fge_partner_set_gallery( $partner_id, $ordered );
}
