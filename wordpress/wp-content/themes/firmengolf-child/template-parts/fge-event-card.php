<?php
/**
 * Reusable event card — ONE style everywhere (events archive, golf-course page,
 * city + format landing pages, related events). Design: Option E „Kompakt & komplett".
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
$g_min    = (int) fge_get_event_meta( $pid, 'participants_min' );
$g_max    = (int) fge_get_event_meta( $pid, 'participants_max' );
$duration = fge_get_event_meta( $pid, 'duration' );
$card_desc = trim( (string) fge_get_event_meta( $pid, 'card_description' ) );
// Preis strukturiert: große Zahl „89 €" + klein/grau „p.P. netto" bzw. „Gesamt netto" (Julius, 2026-07-07).
$pricing = function_exists( 'fge_event_pricing' ) ? fge_event_pricing( $pid ) : [];
if ( ( $pricing['gross'] ?? 0 ) > 0 ) {
	$price_amount = number_format_i18n( (float) $pricing['gross'], 0 ) . ' €';
	$price_unit   = ( ( $pricing['unit'] ?? '' ) === 'pro Person' ) ? 'p.P. netto' : 'Gesamt netto';
} else {
	// Legacy-/Custom-Label oder „Auf Anfrage" — unverändert als große Zeile.
	$fallback     = (string) fge_get_event_price_display( $pid );
	$price_amount = $fallback !== '' ? $fallback : 'Auf Anfrage';
	$price_unit   = ( false === stripos( $price_amount, 'netto' ) && false === stripos( $price_amount, 'Anfrage' ) ) ? 'netto' : '';
}
$cpartner = (int) fge_get_event_meta( $pid, 'assigned_partner_id', 0 );
$rating   = $cpartner ? (float) get_post_meta( $cpartner, '_fge_rating', true ) : 0;
if ( ! $rating ) { $rating = (float) fge_get_event_meta( $pid, 'rating' ); }
$reviews  = $cpartner ? (int) get_post_meta( $cpartner, '_fge_review_count', true ) : 0;
$thumb    = function_exists( 'fge_event_cover_url' ) ? fge_event_cover_url( $pid, 'large' ) : ( has_post_thumbnail( $pid ) ? get_the_post_thumbnail_url( $pid, 'large' ) : fge_get_placeholder_image_url( 'golf-coaching-gruppe.jpg' ) );
$permalink = get_permalink( $pid );
$title     = get_the_title( $pid );

// Gruppengröße als „min–max" bzw. „bis max".
$group_txt = '';
if ( $g_min > 0 && $g_max > 0 ) { $group_txt = $g_min . '–' . $g_max; }
elseif ( $g_max > 0 )           { $group_txt = 'bis ' . $g_max; }

$ic_pin   = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>';
$ic_users = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>';
$ic_clock = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';
$ic_star  = '<svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>';
$arrow    = '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>';
?>
<article class="fg-event evE-card">
	<a href="<?php echo esc_url( $permalink ); ?>" target="_blank" rel="noopener noreferrer">
		<div class="evE-photo">
			<div class="evE-img" style="background-image:url('<?php echo esc_url( $thumb ); ?>')"></div>
			<?php if ( $elabel ) : ?><span class="evE-chip"><?php echo esc_html( $elabel ); ?></span><?php endif; ?>
			<?php if ( $cpartner && function_exists( 'fge_partner_is_offseason' ) && fge_partner_is_offseason( $cpartner ) ) : ?>
				<span class="evE-chip fg-chip-offseason" style="left:auto;right:56px;">Offseason</span>
			<?php endif; ?>
			<button class="evE-share" type="button" aria-label="Event teilen"
			        data-share-url="<?php echo esc_url( $permalink ); ?>" data-share-title="<?php echo esc_attr( $title ); ?>">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 15V3"/><path d="M8 7l4-4 4 4"/><path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7"/></svg>
			</button>
			<?php if ( $edist !== null ) : ?>
				<span class="ev-distbadge"><?php echo $ic_pin; // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php echo esc_html( (string) round( (float) $edist ) ); ?> km</span>
			<?php endif; ?>
		</div>
		<div class="evE-body">
			<span class="evE-venue"><?php echo $ic_pin; // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo esc_html( $venue ?: $region_m ?: '—' ); ?></span>
			<h3 class="evE-title"><?php echo esc_html( $title ); ?></h3>
			<?php if ( $card_desc !== '' ) : ?><p class="evE-desc"><?php echo esc_html( $card_desc ); ?></p><?php endif; ?>
			<div class="evE-meta">
				<?php if ( $group_txt ) : ?><span class="m"><?php echo $ic_users; // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo esc_html( $group_txt ); ?></span><?php endif; ?>
				<?php if ( $duration ) : ?><span class="m"><?php echo $ic_clock; // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo esc_html( $duration ); ?></span><?php endif; ?>
				<?php if ( $rating ) : ?><span class="m evE-rate"><?php echo $ic_star; // phpcs:ignore WordPress.Security.EscapeOutput ?><b><?php echo esc_html( (string) $rating ); ?></b><?php if ( $reviews ) : ?> <span class="muted">(<?php echo esc_html( (string) $reviews ); ?>)</span><?php endif; ?></span><?php endif; ?>
			</div>
			<div class="evE-foot">
				<div class="evE-price<?php echo 'Auf Anfrage' === $price_amount ? ' evE-price--soft' : ''; ?>"><?php echo esc_html( $price_amount ); ?><?php if ( $price_unit !== '' ) : ?><span class="evE-net"><?php echo esc_html( $price_unit ); ?></span><?php endif; ?></div>
				<span class="evE-cta">Ansehen <?php echo $arrow; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			</div>
		</div>
	</a>
</article>
