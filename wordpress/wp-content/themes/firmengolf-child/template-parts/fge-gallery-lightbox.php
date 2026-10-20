<?php
/**
 * Vollbild-Galerie der Partnerseiten (Golfplatz, Golflehrer, Indoor), eine Quelle.
 * Öffnet per [data-gp-open], Start-Bild per data-gp-index (fge-golfplatz.js).
 *
 * @param array $args { ids: int[] Anhang-IDs, Reihenfolge = Reihenfolge der Kacheln }
 */
$gallery_items = [];
foreach ( (array) ( $args['ids'] ?? [] ) as $gid ) {
	$u = wp_get_attachment_image_url( (int) $gid, 'large' );
	if ( $u ) {
		$gallery_items[] = [ 'url' => $u, 'name' => get_the_title( (int) $gid ), 'credit' => function_exists( 'fge_image_credit' ) ? fge_image_credit( (int) $gid ) : '' ];
	}
}
if ( ! $gallery_items ) {
	return;
}
?>
<div class="gp-lightbox" data-gp-lightbox data-images="<?php echo esc_attr( wp_json_encode( $gallery_items ) ); ?>" hidden>
	<button type="button" class="gp-lb-icon gp-lb-close" data-gp-close aria-label="Galerie schließen">
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
	</button>
	<button type="button" class="gp-lb-icon gp-lb-nav gp-lb-prev" data-gp-prev aria-label="Vorheriges Bild">
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
	</button>
	<img class="gp-lb-img" alt="" data-gp-img>
	<button type="button" class="gp-lb-icon gp-lb-nav gp-lb-next" data-gp-next aria-label="Nächstes Bild">
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
	</button>
	<div class="gp-lb-caption">
		<span class="gp-lb-count"><span data-gp-cur>1</span> / <?php echo (int) count( $gallery_items ); ?></span>
		<span class="gp-lb-credit" data-gp-credit></span>
	</div>
</div>
