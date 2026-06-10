<?php
/**
 * Reusable event card — ONE style everywhere (events archive, golf-course page, related events).
 * Args: [ 'id' => int (required), 'dist' => float|null (optional distance badge) ].
 * Uses explicit $pid getters (no setup_postdata) so it is safe inside any loop/context.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pid = (int) ( $args['id'] ?? 0 );
if ( $pid <= 0 || get_post_type( $pid ) !== 'firmengolf_event' ) {
	return;
}
$edist = $args['dist'] ?? null;

$etype    = fge_get_event_meta( $pid, 'event_type' );
$elabel   = fge_format_event_type( $etype ) ?: ucfirst( (string) $etype );
$venue    = fge_get_event_meta( $pid, 'event_location' );
$region_m = fge_get_event_meta( $pid, 'region' );
$eyebrow  = trim( $elabel . ' · ' . fge_get_event_meta( $pid, 'duration' ), ' ·' );
$p_max    = fge_get_event_meta( $pid, 'participants_max' );
$duration = fge_get_event_meta( $pid, 'duration' );
$price    = fge_get_event_price_display( $pid );
$cpartner = (int) fge_get_event_meta( $pid, 'assigned_partner_id', 0 );
$rating   = $cpartner ? (float) get_post_meta( $cpartner, '_fge_rating', true ) : 0;
if ( ! $rating ) { $rating = (float) fge_get_event_meta( $pid, 'rating' ); }
$thumb    = function_exists( 'fge_event_cover_url' ) ? fge_event_cover_url( $pid, 'large' ) : ( has_post_thumbnail( $pid ) ? get_the_post_thumbnail_url( $pid, 'large' ) : fge_get_placeholder_image_url( 'golf-coaching-gruppe.jpg' ) );
$indoor   = function_exists( 'fge_get_active_leistungen' ) && in_array( 'Indoor-Backup', fge_get_active_leistungen( $pid ), true );
$tags_arr = array_filter( array_map( 'trim', explode( ',', (string) fge_get_event_meta( $pid, 'event_tags' ) ) ) );
$permalink = get_permalink( $pid );
$title     = get_the_title( $pid );
$arrow     = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>';
?>
<article class="fg-event ev-card2">
	<a href="<?php echo esc_url( $permalink ); ?>" style="display:contents">
		<div class="fg-event-photo" style="background-image:url('<?php echo esc_url( $thumb ); ?>')">
			<div class="fg-event-chips">
				<?php if ( $elabel ) : ?><span class="fg-photo-chip"><?php echo esc_html( $elabel ); ?></span><?php endif; ?>
			</div>
			<button class="fg-event-heart" type="button" aria-label="Event teilen"
			        data-share-url="<?php echo esc_url( $permalink ); ?>" data-share-title="<?php echo esc_attr( $title ); ?>">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 15V3"/><path d="M8 7l4-4 4 4"/><path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7"/></svg>
			</button>
			<?php if ( $edist !== null ) : ?>
				<span class="ev-distbadge">
					<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
					<?php echo esc_html( (string) round( (float) $edist ) ); ?> km
				</span>
			<?php endif; ?>
		</div>
		<div class="fg-event-body">
			<div class="ev-card2-top">
				<?php if ( $eyebrow ) : ?><div class="fg-event-eyebrow"><?php echo esc_html( $eyebrow ); ?></div><?php endif; ?>
				<?php if ( $rating ) : ?>
					<div class="fg-event-rating">
						<svg viewBox="0 0 24 24" width="13" height="13" fill="#C9B488" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>
						<span><?php echo esc_html( (string) $rating ); ?></span>
					</div>
				<?php endif; ?>
			</div>
			<h3 class="fg-event-title"><?php echo esc_html( $title ); ?></h3>
			<div class="ev-card2-loc">
				<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
				<?php if ( $venue || $region_m ) : ?><span><?php echo esc_html( $venue ?: $region_m ); ?></span><?php endif; ?>
				<?php if ( $p_max ) : ?><span class="dot">·</span><span>bis <?php echo esc_html( (string) $p_max ); ?> Gäste</span><?php endif; ?>
				<?php if ( $duration ) : ?><span class="dot">·</span><span><?php echo esc_html( $duration ); ?></span><?php endif; ?>
			</div>
			<?php
			$mini_tags = [];
			if ( $indoor ) { $mini_tags[] = 'Indoor-Backup'; }
			foreach ( array_slice( $tags_arr, 0, 2 ) as $tg ) { $mini_tags[] = $tg; }
			?>
			<?php if ( $mini_tags ) : ?>
				<div class="ev-card2-badges">
					<?php foreach ( $mini_tags as $mt ) : ?><span class="ev-mini-tag"><?php echo esc_html( $mt ); ?></span><?php endforeach; ?>
				</div>
			<?php endif; ?>
			<div class="fg-event-foot ev-card2-foot">
				<div class="fg-event-price">
					<?php if ( $price ) : ?><?php echo esc_html( $price ); ?><?php else : ?><span style="font-size:13px;color:var(--ink-500)">Auf Anfrage</span><?php endif; ?>
				</div>
				<span class="ev-card2-cta">Ansehen <?php echo $arrow; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			</div>
		</div>
	</a>
</article>
