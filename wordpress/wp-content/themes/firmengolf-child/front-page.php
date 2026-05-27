<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$featured = fge_get_featured_events( 3 );

$anfrage_page = get_page_by_path( 'event-anfrage' );
$anfrage_url  = $anfrage_page ? (string) get_permalink( $anfrage_page->ID ) : home_url( '/event-anfrage/' );
$archive_url  = (string) get_post_type_archive_link( 'firmengolf_event' );
$hero_img     = fge_get_placeholder_image_url( 'hero-fairway-wide.jpg' );
?>
<div class="fge-page">

	<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => '' ] ); ?>

	<?php /* ── Hero ── */ ?>
	<section class="fg-hero">
		<div class="fg-hero-photo" style="background-image: url('<?php echo esc_url( $hero_img ); ?>')">
			<div class="fg-hero-scrim" aria-hidden="true"></div>
			<div class="fg-hero-content">
				<p class="fg-hero-eyebrow">Firmengolf Events</p>
				<h1 class="fg-hero-title">Bring dein Team raus aus dem Büro.</h1>
				<p class="fg-hero-sub">Firmenevents auf dem Golfplatz — für Teams, Kunden und besondere Anlässe. Unkompliziert, unvergesslich, perfekt organisiert.</p>
				<div class="fg-hero-actions">
					<a href="<?php echo esc_url( $archive_url ); ?>" class="fg-btn fg-btn-brand fg-btn-lg">Events entdecken <?php echo fge_icon_arrow_right(); // phpcs:ignore ?></a>
					<a href="<?php echo esc_url( $anfrage_url ); ?>" class="fg-btn fg-btn-glass fg-btn-lg">Event anfragen</a>
				</div>
			</div>
		</div>
	</section>

	<?php /* ── Wertversprechen ── */ ?>
	<section class="fg-home-value" aria-label="Unsere Leistungen">
		<div class="fg-home-value-inner">
			<div class="fg-home-value-item">
				<span class="fg-home-value-icon" aria-hidden="true">⛳</span>
				<h2 class="fg-home-value-title">Geprüfte Locations</h2>
				<p class="fg-home-value-text">Nur Golfplätze, die für Firmenevents geeignet und kuratiert sind — kein langes Suchen.</p>
			</div>
			<div class="fg-home-value-item">
				<span class="fg-home-value-icon" aria-hidden="true">🎯</span>
				<h2 class="fg-home-value-title">Alles aus einer Hand</h2>
				<p class="fg-home-value-text">Von der Anfrage bis zur Buchung — Firmengolf koordiniert alles mit dem Golfplatz für euch.</p>
			</div>
			<div class="fg-home-value-item">
				<span class="fg-home-value-icon" aria-hidden="true">👥</span>
				<h2 class="fg-home-value-title">Für jede Teamgröße</h2>
				<p class="fg-home-value-text">Kleine Runden oder große Firmenturniere — Formate für 8 bis 150 Personen und mehr.</p>
			</div>
		</div>
	</section>

	<?php /* ── Featured Events ── */ ?>
	<?php if ( ! empty( $featured ) ) : ?>
	<section class="fg-home-featured" aria-label="Unsere Events">
		<div class="fg-home-featured-head">
			<h2 class="fg-home-featured-title">Unsere Eventangebote</h2>
			<a href="<?php echo esc_url( $archive_url ); ?>" class="fg-home-featured-link">Alle Events <?php echo fge_icon_arrow_right(); // phpcs:ignore ?></a>
		</div>
		<div class="fg-grid">
			<?php foreach ( $featured as $event ) :
				$eid         = $event->ID;
				$event_type  = fge_format_event_type( fge_get_event_meta( $eid, 'event_type' ) );
				$region      = fge_get_event_meta( $eid, 'region' );
				$location    = fge_get_event_meta( $eid, 'event_location' );
				$p_min       = fge_get_event_meta( $eid, 'participants_min' );
				$p_max       = fge_get_event_meta( $eid, 'participants_max' );
				$duration    = fge_get_event_meta( $eid, 'duration' );
				$description = fge_get_event_meta( $eid, 'card_description', $event->post_excerpt );
				$price       = fge_get_event_price_display( $eid );
				$leistungen  = array_values( fge_get_active_leistungen( $eid ) );
				$thumb_url   = has_post_thumbnail( $eid ) ? get_the_post_thumbnail_url( $eid, 'large' ) : fge_get_placeholder_image_url( 'event-team.jpg' );
			?>
			<article class="fg-event">
				<a href="<?php echo esc_url( get_permalink( $eid ) ); ?>">
					<div class="fg-event-photo" style="background-image: url('<?php echo esc_url( $thumb_url ); ?>')">
						<?php if ( $event_type ) : ?>
							<div class="fg-event-chips">
								<span class="fg-photo-chip"><?php echo esc_html( $event_type ); ?></span>
							</div>
						<?php endif; ?>
					</div>
					<div class="fg-event-body">
						<?php if ( $event_type ) : ?>
							<span class="fg-type-tag"><?php echo esc_html( $event_type ); ?></span>
						<?php endif; ?>
						<h3 class="fg-event-title"><?php echo esc_html( $event->post_title ); ?></h3>
						<?php if ( $description ) : ?>
							<p class="fg-event-desc"><?php echo esc_html( $description ); ?></p>
						<?php endif; ?>
						<div class="fg-event-meta">
							<?php if ( $region || $location ) : ?>
								<?php echo fge_icon_map_pin(); // phpcs:ignore ?>
								<span><?php echo esc_html( $region ?: $location ); ?></span>
							<?php endif; ?>
							<?php if ( $p_min || $p_max ) : ?>
								<span class="dot">·</span>
								<?php echo fge_icon_users(); // phpcs:ignore ?>
								<span><?php
									if ( $p_min && $p_max ) {
										echo esc_html( $p_min . '–' . $p_max . ' Pers.' );
									} elseif ( $p_max ) {
										echo esc_html( 'bis ' . $p_max . ' Pers.' );
									} else {
										echo esc_html( 'ab ' . $p_min . ' Pers.' );
									}
								?></span>
							<?php endif; ?>
							<?php if ( $duration ) : ?>
								<span class="dot">·</span>
								<?php echo fge_icon_clock(); // phpcs:ignore ?>
								<span><?php echo esc_html( $duration ); ?></span>
							<?php endif; ?>
						</div>
						<?php if ( ! empty( $leistungen ) ) : ?>
							<div class="fg-badges">
								<?php foreach ( array_slice( $leistungen, 0, 4 ) as $badge ) : ?>
									<span class="fg-badge"><?php echo esc_html( $badge ); ?></span>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
						<div class="fg-event-foot">
							<?php if ( $price ) : ?>
								<span class="fg-event-price"><?php echo esc_html( $price ); ?></span>
							<?php else : ?>
								<span class="fg-event-price" style="color:var(--ink-500);font-size:14px;">Preis auf Anfrage</span>
							<?php endif; ?>
							<span class="fg-event-cta">Details <?php echo fge_icon_arrow_right(); // phpcs:ignore ?></span>
						</div>
					</div>
				</a>
			</article>
			<?php endforeach; ?>
		</div>
		<div style="text-align:center;margin-top:32px;">
			<a href="<?php echo esc_url( $archive_url ); ?>" class="fg-btn fg-btn-outline">Alle Eventangebote ansehen <?php echo fge_icon_arrow_right(); // phpcs:ignore ?></a>
		</div>
	</section>
	<?php endif; ?>

	<?php /* ── Wie es funktioniert ── */ ?>
	<section class="fg-how" aria-label="So funktioniert es">
		<div class="fg-how-inner">
			<p class="fg-how-label">So funktioniert es</p>
			<h2 class="fg-how-title">In vier Schritten zum Firmenevent</h2>
			<div class="fg-steps">
				<?php
				$steps = [
					[ '01', 'Eventangebot ansehen',       'Format, Leistungen und Preisrahmen prüfen — alles auf einen Blick.' ],
					[ '02', 'Wunschtermin anfragen',      'Datum und Teilnehmerzahl mitgeben — ganz ohne Vorwissen.' ],
					[ '03', 'Verfügbarkeit wird geprüft', 'Wir koordinieren mit dem Golfplatz und melden uns schnell zurück.' ],
					[ '04', 'Angebot erhalten',           'Konkretes Angebot, klare Preise — und dann kann es losgehen.' ],
				];
				foreach ( $steps as $step ) : ?>
					<div>
						<p class="fg-step-num"><?php echo esc_html( $step[0] ); ?></p>
						<h3 class="fg-step-title"><?php echo esc_html( $step[1] ); ?></h3>
						<p class="fg-step-body"><?php echo esc_html( $step[2] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<?php /* ── Blog-Teaser ── */ ?>
	<section class="fg-home-blog-teaser" aria-label="Blog und Ratgeber">
		<div class="fg-home-blog-teaser-inner">
			<p class="fg-home-blog-teaser-label">Wissen & Inspiration</p>
			<h2 class="fg-home-blog-teaser-title">Wissenswertes rund um Golf als Firmenevent</h2>
			<p class="fg-home-blog-teaser-sub">Ratgeber, Tipps und Eventideen — demnächst hier auf Firmengolf.</p>
			<span class="fg-home-blog-teaser-badge">Beiträge erscheinen demnächst</span>
		</div>
	</section>

	<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>
<?php get_footer(); ?>
