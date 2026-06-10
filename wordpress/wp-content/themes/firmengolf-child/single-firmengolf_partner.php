<?php
/**
 * Public golf-course (partner) detail page. Mirrors the portal "Platz" design (.fgpp) so it
 * looks 1:1 like the in-portal view — but with neutral headings, no edit controls, plus the
 * events list + CTA. Visibility gated in fge_block_non_public_partners().
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
get_header();

$pid = (int) get_the_ID();
$m   = static fn( string $k ): string => (string) get_post_meta( $pid, '_fge_' . $k, true );

$name      = $m( 'public_golfclub_name' ) ?: get_the_title();
$city      = $m( 'city' );
$state     = $m( 'federal_state' );
$loc       = trim( $city . ( $state ? ', ' . $state : '' ) );
$golf      = $m( 'golf_type' );
$golf_lbl  = $golf && function_exists( 'fge_catalog_golf_types' ) ? ( fge_catalog_golf_types()[ $golf ] ?? $golf ) : '';
$rating    = (float) $m( 'rating' );
$desc      = $m( 'public_short_description' );
$cap       = (array) get_post_meta( $pid, '_fge_cap', true );
$formats   = (array) get_post_meta( $pid, '_fge_event_formats', true );
$season    = $m( 'season' );
$website   = $m( 'website_url' );
$infra     = array_map( 'strval', (array) get_post_meta( $pid, '_fge_infra', true ) );
$gallery   = function_exists( 'fge_partner_gallery_ids' ) ? fge_partner_gallery_ids( $pid ) : [];
$cover_att = function_exists( 'fge_partner_cover_id' ) ? fge_partner_cover_id( $pid ) : 0;
$cover     = $cover_att > 0 ? (string) wp_get_attachment_image_url( $cover_att, 'large' ) : fge_get_placeholder_image_url( 'hero-fairway-wide.jpg', $pid );
$mono      = function_exists( 'fge_portal_make_monogram' ) ? fge_portal_make_monogram( $name ) : strtoupper( mb_substr( $name, 0, 2 ) );
$events    = function_exists( 'fge_partner_public_event_ids' ) ? fge_partner_public_event_ids( $pid ) : [];
$fmt_lbl   = function_exists( 'fge_get_event_formats_flat' ) ? fge_get_event_formats_flat( false ) : [];
$ind_url   = ( $ip = get_page_by_path( 'individuelle-events' ) ) ? get_permalink( $ip->ID ) : home_url( '/individuelle-events/' );

$infra_index = [];
foreach ( fge_catalog_infra_groups() as $group => $items ) {
	foreach ( $items as $id => $label ) {
		$infra_index[ (string) $id ] = [ 'label' => $label, 'group' => $group ];
	}
}

$facts = [];
if ( $golf_lbl ) { $facts[] = [ 'Platztyp', $golf_lbl ]; }
if ( $loc )      { $facts[] = [ 'Standort', $loc ]; }
if ( ! empty( $cap['min'] ) || ! empty( $cap['max'] ) ) {
	$facts[] = [ 'Gruppengröße', trim( ( $cap['min'] ?? '?' ) . '–' . ( $cap['max'] ?? '?' ) . ' Personen' ) ];
}
if ( $formats ) { $facts[] = [ 'Veranstaltungstypen', (string) count( $formats ) ]; }
if ( $season )  { $facts[] = [ 'Saison', $season ]; }
?>
<div class="fge-page">

	<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => 'events' ] ); ?>

	<div class="fgpp">
		<div class="page-wide">

			<section class="hero">
				<div class="hero-photo" style="background-image:url('<?php echo esc_url( $cover ); ?>')">
					<div class="hero-scrim"></div>
					<?php if ( $gallery ) : ?>
						<button type="button" class="fg-btn fg-btn-glass gp-gallery-btn" data-gp-open>
							<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
							Galerie · <?php echo (int) count( $gallery ); ?>
						</button>
					<?php endif; ?>
					<div class="hero-body">
						<div class="hero-id">
							<?php
							$logo_id  = (int) $m( 'logo_attachment_id' );
							$logo_url = $logo_id > 0 ? (string) wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
							?>
							<?php if ( $logo_url !== '' ) : ?>
								<div class="hero-monogram hero-logo" style="background-image:url('<?php echo esc_url( $logo_url ); ?>')" role="img" aria-label="<?php echo esc_attr( $name ); ?> Logo"></div>
							<?php else : ?>
								<div class="hero-monogram"><?php echo esc_html( $mono ); ?></div>
							<?php endif; ?>
							<div class="hero-text">
								<div class="hero-eyebrow">Golfplatz auf Firmengolf</div>
								<h1 class="hero-name"><?php echo esc_html( $name ); ?></h1>
								<div class="hero-meta">
									<?php if ( $loc ) : ?><span><?php echo esc_html( $loc ); ?></span><?php endif; ?>
									<?php if ( $golf_lbl ) : ?><span class="dot">·</span><span><?php echo esc_html( $golf_lbl ); ?></span><?php endif; ?>
									<?php if ( $events ) : ?><span class="dot">·</span><span><?php echo (int) count( $events ); ?> Event<?php echo count( $events ) === 1 ? '' : 's'; ?></span><?php endif; ?>
								</div>
							</div>
						</div>
						<?php if ( $rating > 0 ) : ?>
							<div class="hero-cta-card"><div class="lbl">Bewertung</div><div class="val"><?php echo esc_html( number_format_i18n( $rating, 1 ) ); ?> ★</div></div>
						<?php endif; ?>
					</div>
				</div>
			</section>

			<section class="section">
				<div class="section-head"><div><div class="eyebrow">Über den Platz</div><h2>Über den <em>Platz</em></h2></div></div>
				<div class="about">
					<div class="about-main">
						<?php if ( $desc !== '' ) : ?>
							<?php foreach ( preg_split( '/\n\s*\n/', trim( $desc ) ) as $para ) : ?><p><?php echo esc_html( trim( $para ) ); ?></p><?php endforeach; ?>
						<?php else : ?>
							<p>Ein Golfplatz, der sich hervorragend für Firmenevents eignet.</p>
						<?php endif; ?>
					</div>
					<?php if ( $facts ) : ?>
						<div class="facts">
							<h4>Eckdaten</h4>
							<?php foreach ( $facts as $f ) : ?><div class="fact-row"><span class="lbl"><?php echo esc_html( $f[0] ); ?></span><span class="val"><?php echo esc_html( $f[1] ); ?></span></div><?php endforeach; ?>
							<?php if ( $website ) : ?><a class="btn btn-ghost btn-sm" style="margin-top:14px;" href="<?php echo esc_url( $website ); ?>" target="_blank" rel="noopener">Website&nbsp;↗</a><?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
			</section>

			<?php if ( $infra ) : ?>
			<section class="section">
				<div class="section-head"><div><div class="eyebrow">Ausstattung</div><h2>Was euch <em>erwartet</em></h2></div></div>
				<?php fge_render_amenities_grid( $pid ); ?>
			</section>
			<?php endif; ?>


			<?php if ( $events ) : ?>
			<section class="section">
				<div class="section-head"><div><div class="eyebrow">Veranstaltungen</div><h2>Alle Formate auf <em><?php echo esc_html( $name ); ?></em></h2></div></div>
				<div class="gp-events">
					<?php foreach ( $events as $eid ) {
						get_template_part( 'template-parts/fge-event-card', null, [ 'id' => (int) $eid ] );
					} ?>
				</div>
			</section>
			<?php endif; ?>

			<?php
			$plat  = (float) $m( 'latitude' );
			$plng  = (float) $m( 'longitude' );
			$paddr = trim( $m( 'street' ) . ' ' . $m( 'house_number' ) . ', ' . $m( 'postal_code' ) . ' ' . $city, ' ,' );
			$pmq   = ( $plat && $plng ) ? $plat . ',' . $plng : $paddr;
			if ( $pmq !== '' ) : ?>
			<section class="section">
				<div class="section-head"><div><div class="eyebrow">Standort</div><h2>Wo ihr uns <em>findet</em></h2></div></div>
				<div class="fgpp-map">
					<iframe src="https://www.google.com/maps?q=<?php echo rawurlencode( $pmq ); ?>&output=embed" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen title="Karte: <?php echo esc_attr( $name ); ?>"></iframe>
				</div>
				<?php if ( $paddr !== '' ) : ?><p class="fgpp-map-addr"><?php echo fge_icon_map_pin(); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php echo esc_html( $paddr ); ?></p><?php endif; ?>
			</section>
			<?php endif; ?>

			<section class="section">
				<div class="two-col">
					<div class="panel">
						<div class="panel-head"><h3 style="font-size:18px;">Was Firmen sagen</h3></div>
						<?php if ( $m( 'review_quote' ) !== '' ) : ?>
							<div class="review">
								<div class="review-head"><span class="review-company"><?php echo esc_html( $m( 'review_author' ) ?: 'Kunde' ); ?></span></div>
								<p class="review-quote">„<?php echo esc_html( $m( 'review_quote' ) ); ?>"</p>
								<?php if ( $m( 'review_role' ) !== '' ) : ?><div class="review-foot"><span><?php echo esc_html( $m( 'review_role' ) ); ?></span></div><?php endif; ?>
							</div>
						<?php else : ?>
							<p style="font-size:14px;color:var(--ink-500);">Noch keine Bewertung vorhanden.</p>
						<?php endif; ?>
					</div>
					<div class="panel">
						<div class="panel-head"><h3 style="font-size:18px;">Anfrage an diesen Platz</h3></div>
						<p style="font-size:14px;color:var(--ink-600,var(--ink-500));margin:0 0 16px;">Plant euer Firmenevent bei <?php echo esc_html( $name ); ?> — wir holen Verfügbarkeit &amp; Angebot direkt beim Platz ein.</p>
						<a class="btn btn-brand" href="<?php echo esc_url( $ind_url ); ?>">Anfrage an diesen Platz</a>
					</div>
				</div>
			</section>

			<section class="section">
				<div class="gp-cta">
					<div>
						<div class="eyebrow">Maßgeschneidert</div>
						<h2>Etwas Eigenes<?php echo $city ? ' in ' . esc_html( $city ) : ''; ?>?</h2>
						<p>Sag uns, was ihr vorhabt — Sommerfest, Incentive, Kundentag oder Turnier. Wir stellen euch ein Event genau nach euren Wünschen zusammen.</p>
					</div>
					<a class="btn btn-brand" href="<?php echo esc_url( $ind_url ); ?>">Individuelles Event anfragen</a>
				</div>
			</section>

		</div>
	</div>

	<?php
	if ( $gallery ) :
		$gallery_urls = [];
		foreach ( $gallery as $gid ) {
			$u = wp_get_attachment_image_url( $gid, 'large' );
			if ( $u ) { $gallery_urls[] = $u; }
		}
		if ( $gallery_urls ) : ?>
		<div class="gp-lightbox" data-gp-lightbox data-images="<?php echo esc_attr( wp_json_encode( $gallery_urls ) ); ?>" hidden>
			<button type="button" class="gp-lb-btn gp-lb-close" data-gp-close aria-label="Schließen">✕</button>
			<button type="button" class="gp-lb-btn gp-lb-prev" data-gp-prev aria-label="Vorheriges Bild">‹</button>
			<img class="gp-lb-img" alt="Galeriefoto" data-gp-img>
			<button type="button" class="gp-lb-btn gp-lb-next" data-gp-next aria-label="Nächstes Bild">›</button>
			<div class="gp-lb-count"><span data-gp-cur>1</span> / <?php echo (int) count( $gallery_urls ); ?></div>
		</div>
	<?php endif; endif; ?>

	<?php get_template_part( 'template-parts/fge-footer' ); ?>
</div>
<?php get_footer();
