<?php
/**
 * Public golf-course (partner) detail page. Visibility is gated in
 * fge_block_non_public_partners() — only active partners with ≥1 public event reach here.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
get_header();

$pid      = (int) get_the_ID();
$name     = (string) ( get_post_meta( $pid, '_fge_public_golfclub_name', true ) ?: get_the_title() );
$city     = (string) get_post_meta( $pid, '_fge_city', true );
$state    = (string) get_post_meta( $pid, '_fge_federal_state', true );
$region   = (string) get_post_meta( $pid, '_fge_free_region', true );
$loc      = trim( $city . ( $state ? ', ' . $state : '' ) );
$desc     = (string) get_post_meta( $pid, '_fge_public_short_description', true );
$gt       = (string) get_post_meta( $pid, '_fge_golf_type', true );
$gt_label = $gt && function_exists( 'fge_catalog_golf_types' ) ? ( fge_catalog_golf_types()[ $gt ] ?? $gt ) : '';
$rating   = (float) get_post_meta( $pid, '_fge_rating', true );
$cap      = (array) get_post_meta( $pid, '_fge_cap', true );
$formats  = (array) get_post_meta( $pid, '_fge_event_formats', true );
$season   = (string) get_post_meta( $pid, '_fge_season', true );
$website  = (string) get_post_meta( $pid, '_fge_website_url', true );
$infra    = array_map( 'strval', (array) get_post_meta( $pid, '_fge_infra', true ) );
$lat      = (float) get_post_meta( $pid, '_fge_latitude', true );
$lng      = (float) get_post_meta( $pid, '_fge_longitude', true );

$gallery   = function_exists( 'fge_partner_gallery_ids' ) ? fge_partner_gallery_ids( $pid ) : [];
$cover_att = function_exists( 'fge_partner_cover_id' ) ? fge_partner_cover_id( $pid ) : 0;
$cover_url = $cover_att > 0 ? (string) wp_get_attachment_image_url( $cover_att, 'full' ) : fge_get_placeholder_image_url( 'hero-fairway-wide.jpg', $pid );
$events    = function_exists( 'fge_partner_public_event_ids' ) ? fge_partner_public_event_ids( $pid ) : [];
$fmt_lbl   = function_exists( 'fge_get_event_formats_flat' ) ? fge_get_event_formats_flat( false ) : [];
?>
<div class="fge-page">

	<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => 'events' ] ); ?>

	<section class="gp-hero" style="background-image:url('<?php echo esc_url( $cover_url ); ?>')">
		<div class="gp-hero-scrim" aria-hidden="true"></div>
		<div class="gp-hero-inner">
			<a class="gp-hero-back" href="<?php echo esc_url( get_post_type_archive_link( 'firmengolf_event' ) ); ?>">← Alle Events</a>
			<?php if ( $gt_label ) : ?><div class="gp-hero-eyebrow"><?php echo esc_html( $gt_label ); ?></div><?php endif; ?>
			<h1 class="gp-hero-title"><?php echo esc_html( $name ); ?></h1>
			<div class="gp-hero-meta">
				<?php if ( $loc ) : ?><span><?php echo esc_html( $loc ); ?></span><?php endif; ?>
				<?php if ( $rating ) : ?><span>★ <?php echo esc_html( number_format( $rating, 1 ) ); ?></span><?php endif; ?>
				<?php if ( $events ) : ?><span><?php echo (int) count( $events ); ?> Event<?php echo count( $events ) === 1 ? '' : 's'; ?> hier</span><?php endif; ?>
			</div>
		</div>
	</section>

	<div class="gp-wrap">

		<section class="gp-about">
			<div class="gp-about-main">
				<h2>Über den Platz</h2>
				<?php if ( $desc !== '' ) : ?>
					<?php foreach ( preg_split( '/\n\s*\n/', trim( $desc ) ) as $para ) : ?>
						<p><?php echo esc_html( trim( $para ) ); ?></p>
					<?php endforeach; ?>
				<?php else : ?>
					<p>Ein Golfplatz, der sich für Firmenevents bestens eignet.</p>
				<?php endif; ?>
			</div>
			<aside class="gp-facts">
				<h3>Eckdaten</h3>
				<?php
				$facts = [];
				if ( $gt_label ) { $facts['Platztyp'] = $gt_label; }
				if ( ! empty( $cap['min'] ) || ! empty( $cap['max'] ) ) { $facts['Gruppengröße'] = trim( ( $cap['min'] ?? '?' ) . '–' . ( $cap['max'] ?? '?' ) . ' Personen' ); }
				if ( $formats ) { $facts['Veranstaltungstypen'] = (string) count( $formats ); }
				if ( $season ) { $facts['Saison'] = $season; }
				if ( $loc ) { $facts['Standort'] = $loc; }
				foreach ( $facts as $k => $val ) : ?>
					<div class="gp-fact"><span><?php echo esc_html( $k ); ?></span><strong><?php echo esc_html( $val ); ?></strong></div>
				<?php endforeach; ?>
				<?php if ( $website ) : ?><a class="gp-web" href="<?php echo esc_url( $website ); ?>" target="_blank" rel="noopener">Website&nbsp;↗</a><?php endif; ?>
			</aside>
		</section>

		<?php if ( $formats ) : ?>
		<section class="gp-sec">
			<h2>Veranstaltungstypen</h2>
			<div class="gp-tags">
				<?php foreach ( $formats as $f ) : ?><span class="gp-tag"><?php echo esc_html( $fmt_lbl[ $f ] ?? $f ); ?></span><?php endforeach; ?>
			</div>
		</section>
		<?php endif; ?>

		<?php if ( $gallery ) : ?>
		<section class="gp-sec">
			<h2>Bilder</h2>
			<div class="gp-gallery">
				<?php foreach ( $gallery as $gid ) :
					$gurl = (string) wp_get_attachment_image_url( $gid, 'large' );
					if ( $gurl === '' ) { continue; } ?>
					<div class="gp-gallery-item" style="background-image:url('<?php echo esc_url( $gurl ); ?>')"></div>
				<?php endforeach; ?>
			</div>
		</section>
		<?php endif; ?>

		<?php
		$infra_groups = function_exists( 'fge_catalog_infra_groups' ) ? fge_catalog_infra_groups() : [];
		$infra_set    = array_flip( $infra );
		$infra_html   = '';
		foreach ( $infra_groups as $group => $items ) {
			$hits = array_filter( $items, static fn( $l, $id ) => isset( $infra_set[ (string) $id ] ), ARRAY_FILTER_USE_BOTH );
			if ( ! $hits ) { continue; }
			$infra_html .= '<div class="gp-infra-group"><h4>' . esc_html( $group ) . '</h4><ul>';
			foreach ( $hits as $l ) { $infra_html .= '<li>' . esc_html( $l ) . '</li>'; }
			$infra_html .= '</ul></div>';
		}
		if ( $infra_html !== '' ) : ?>
		<section class="gp-sec">
			<h2>Ausstattung</h2>
			<div class="gp-infra"><?php echo $infra_html; // phpcs:ignore WordPress.Security.EscapeOutput — built with esc_html above ?></div>
		</section>
		<?php endif; ?>

		<?php
		$mq = ( $lat && $lng ) ? $lat . ',' . $lng : trim( implode( ' ', array_filter( [
			get_post_meta( $pid, '_fge_street', true ),
			get_post_meta( $pid, '_fge_house_number', true ),
			get_post_meta( $pid, '_fge_postal_code', true ),
			$city,
		] ) ) );
		if ( $mq !== '' ) : ?>
		<section class="gp-sec">
			<h2>Standort</h2>
			<iframe class="gp-map" src="https://www.google.com/maps?q=<?php echo rawurlencode( $mq ); ?>&output=embed" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen title="Karte: <?php echo esc_attr( $name ); ?>"></iframe>
		</section>
		<?php endif; ?>

		<?php if ( $events ) : ?>
		<section class="gp-sec">
			<h2>Events an diesem Platz</h2>
			<div class="gp-event-grid">
				<?php foreach ( $events as $eid ) :
					$ethumb = function_exists( 'fge_event_cover_url' ) ? fge_event_cover_url( $eid, 'large' ) : fge_get_placeholder_image_url( 'golf-coaching-gruppe.jpg', $eid );
					$etype  = $fmt_lbl[ (string) get_post_meta( $eid, '_fge_event_type', true ) ] ?? '';
					$eprice = function_exists( 'fge_get_event_price_display' ) ? fge_get_event_price_display( $eid ) : '';
					?>
					<a class="gp-event-card" href="<?php echo esc_url( get_permalink( $eid ) ); ?>">
						<span class="gp-event-thumb" style="background-image:url('<?php echo esc_url( $ethumb ); ?>')"></span>
						<span class="gp-event-body">
							<?php if ( $etype ) : ?><span class="gp-event-type"><?php echo esc_html( $etype ); ?></span><?php endif; ?>
							<span class="gp-event-title"><?php echo esc_html( get_the_title( $eid ) ); ?></span>
							<?php if ( $eprice ) : ?><span class="gp-event-price"><?php echo esc_html( $eprice ); ?></span><?php endif; ?>
						</span>
					</a>
				<?php endforeach; ?>
			</div>
		</section>
		<?php endif; ?>

	</div>

	<?php get_template_part( 'template-parts/fge-footer' ); ?>
</div>
<?php get_footer();
