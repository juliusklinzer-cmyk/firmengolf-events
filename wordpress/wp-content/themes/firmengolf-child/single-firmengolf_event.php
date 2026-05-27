<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Visibility guard — redirect non-freigegeben events.
the_post();
$post_id = get_the_ID();
$status  = get_post_meta( $post_id, '_fge_event_status', true );
if ( $status !== 'freigegeben' ) {
	wp_redirect( get_post_type_archive_link( 'firmengolf_event' ), 302 );
	exit;
}

// Gather meta.
$event_type   = fge_format_event_type( fge_get_event_meta( $post_id, 'event_type' ) );
$region       = fge_get_event_meta( $post_id, 'region' );
$location     = fge_get_event_meta( $post_id, 'event_location' );
$p_min        = fge_get_event_meta( $post_id, 'participants_min' );
$p_max        = fge_get_event_meta( $post_id, 'participants_max' );
$duration     = fge_get_event_meta( $post_id, 'duration' );
$season       = fge_get_event_meta( $post_id, 'season' );
$weekdays_raw = (array) get_post_meta( $post_id, '_fge_available_weekdays', true );
$weekdays     = fge_format_weekdays( $weekdays_raw );
$description  = fge_get_event_meta( $post_id, 'card_description', get_the_excerpt() );
$price        = fge_get_event_price_display( $post_id );
$price_note   = fge_get_event_meta( $post_id, 'price_note' );
$per_person   = fge_get_event_meta( $post_id, 'price_per_person_possible' );
$package_ok   = fge_get_event_meta( $post_id, 'package_price_possible' );
$partner_id   = (int) fge_get_event_meta( $post_id, 'assigned_partner_id', 0 );
$partner      = fge_get_partner_info( $partner_id );
$leistungen   = fge_get_active_leistungen( $post_id );
$additional   = fge_get_event_meta( $post_id, 'additional_services' );
$thumb_url    = has_post_thumbnail() ? get_the_post_thumbnail_url( $post_id, 'full' ) : fge_get_placeholder_image_url( 'event-corporate.jpg' );

// Similar events.
$similar_query = new WP_Query( [
	'post_type'      => 'firmengolf_event',
	'post_status'    => 'publish',
	'post__not_in'   => [ $post_id ],
	'posts_per_page' => 3,
	'meta_query'     => [
		[ 'key' => '_fge_event_status', 'value' => 'freigegeben', 'compare' => '=' ],
	],
	'orderby' => 'rand',
] );

// SEO title/desc (used in wp_head via plugin).
$seo_title = fge_get_event_meta( $post_id, 'seo_title' );
$seo_desc  = fge_get_event_meta( $post_id, 'meta_description' );
if ( $seo_title || $seo_desc ) {
	add_filter( 'pre_get_document_title', function() use ( $seo_title ) {
		return $seo_title ?: get_the_title();
	} );
	if ( $seo_desc ) {
		add_action( 'wp_head', function() use ( $seo_desc ) {
			echo '<meta name="description" content="' . esc_attr( $seo_desc ) . '">' . "\n";
		} );
	}
}

get_header();
?>
<div class="fge-page">

	<?php /* ── Top Nav ── */ ?>
	<nav class="fg-topnav" aria-label="Hauptnavigation">
		<div class="fg-topnav-inner">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="fg-brand">
				<img src="<?php echo esc_url( fge_get_logo_url() ); ?>" alt="Firmengolf" width="120" height="24">
			</a>
			<div class="fg-nav-items">
				<a href="<?php echo esc_url( get_post_type_archive_link( 'firmengolf_event' ) ); ?>">Firmenevents</a>
			</div>
			<div class="fg-nav-end">
				<a href="#event-anfrage" class="fg-nav-cta">
					Event anfragen <?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</a>
			</div>
		</div>
	</nav>

	<?php /* ── Detail Section ── */ ?>
	<div class="fg-detail">

		<?php /* Back link */ ?>
		<a href="<?php echo esc_url( get_post_type_archive_link( 'firmengolf_event' ) ); ?>" class="fg-detail-back">
			<?php echo fge_icon_arrow_left(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			Alle Firmenevents
		</a>

		<?php /* Header */ ?>
		<header class="fg-detail-header">
			<?php if ( $event_type ) : ?>
				<p class="fg-detail-eyebrow"><?php echo esc_html( $event_type ); ?></p>
			<?php endif; ?>
			<h1 class="fg-detail-title"><?php the_title(); ?></h1>
			<div class="fg-detail-meta">
				<?php if ( $region || $location ) : ?>
					<span><?php echo fge_icon_map_pin(); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php echo esc_html( $region ?: $location ); ?></span>
				<?php endif; ?>
				<?php if ( $p_min || $p_max ) : ?>
					<span><?php echo fge_icon_users(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php
						if ( $p_min && $p_max ) {
							echo esc_html( $p_min . '–' . $p_max . ' Personen' );
						} elseif ( $p_max ) {
							echo esc_html( 'bis ' . $p_max . ' Personen' );
						} else {
							echo esc_html( 'ab ' . $p_min . ' Personen' );
						}
					?></span>
				<?php endif; ?>
				<?php if ( $duration ) : ?>
					<span><?php echo fge_icon_clock(); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php echo esc_html( $duration ); ?></span>
				<?php endif; ?>
			</div>
		</header>

		<?php /* Hero Image */ ?>
		<div class="fg-detail-hero" style="background-image: url('<?php echo esc_url( $thumb_url ); ?>')">
			<div class="fg-detail-hero-scrim" aria-hidden="true"></div>
			<?php if ( $price ) : ?>
				<div class="fg-detail-hero-cta">
					<a href="#event-anfrage" class="fg-btn fg-btn-brand">Event anfragen</a>
				</div>
			<?php endif; ?>
		</div>

		<?php /* Body: main + rail */ ?>
		<div class="fg-detail-body">

			<main class="fg-detail-main">

				<?php /* Kurzbeschreibung */ ?>
				<?php if ( $description ) : ?>
					<div>
						<p class="fg-section-label">Über dieses Event</p>
						<p class="fg-detail-summary"><?php echo esc_html( $description ); ?></p>
					</div>
				<?php endif; ?>

				<?php /* Ausführliche Beschreibung */ ?>
				<?php
				$content = get_the_content();
				if ( $content ) : ?>
					<div class="fg-detail-content">
						<?php echo wp_kses_post( apply_filters( 'the_content', $content ) ); ?>
					</div>
				<?php endif; ?>

				<?php /* Event Rahmen */ ?>
				<?php if ( $p_min || $p_max || $duration || $season || $weekdays || $region || $location ) : ?>
					<div>
						<p class="fg-section-label">Event Rahmen</p>
						<div class="fg-rahmen-grid">
							<?php if ( $p_min || $p_max ) : ?>
								<div class="fg-rahmen-item">
									<p class="fg-rahmen-label">Teilnehmer</p>
									<p class="fg-rahmen-value"><?php
										if ( $p_min && $p_max ) {
											echo esc_html( $p_min . '–' . $p_max . ' Personen' );
										} elseif ( $p_max ) {
											echo esc_html( 'bis ' . $p_max . ' Personen' );
										} else {
											echo esc_html( 'ab ' . $p_min . ' Personen' );
										}
									?></p>
								</div>
							<?php endif; ?>
							<?php if ( $duration ) : ?>
								<div class="fg-rahmen-item">
									<p class="fg-rahmen-label">Dauer</p>
									<p class="fg-rahmen-value"><?php echo esc_html( $duration ); ?></p>
								</div>
							<?php endif; ?>
							<?php if ( $season ) : ?>
								<div class="fg-rahmen-item">
									<p class="fg-rahmen-label">Saison</p>
									<p class="fg-rahmen-value"><?php echo esc_html( $season ); ?></p>
								</div>
							<?php endif; ?>
							<?php if ( $weekdays ) : ?>
								<div class="fg-rahmen-item">
									<p class="fg-rahmen-label">Wochentage</p>
									<p class="fg-rahmen-value"><?php echo esc_html( $weekdays ); ?></p>
								</div>
							<?php endif; ?>
							<?php if ( $region ) : ?>
								<div class="fg-rahmen-item">
									<p class="fg-rahmen-label">Region</p>
									<p class="fg-rahmen-value"><?php echo esc_html( $region ); ?></p>
								</div>
							<?php endif; ?>
							<?php if ( $location ) : ?>
								<div class="fg-rahmen-item">
									<p class="fg-rahmen-label">Ort</p>
									<p class="fg-rahmen-value"><?php echo esc_html( $location ); ?></p>
								</div>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>

				<?php /* Leistungen */ ?>
				<?php if ( ! empty( $leistungen ) ) : ?>
					<div>
						<p class="fg-section-label">Leistungen inklusive</p>
						<ul class="fg-includes">
							<?php foreach ( $leistungen as $key => $label ) : ?>
								<li>
									<span class="fg-includes-check"><?php echo fge_icon_check(); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
									<?php echo esc_html( $label ); ?>
								</li>
							<?php endforeach; ?>
						</ul>
						<?php if ( $additional ) : ?>
							<div class="fg-additional"><?php echo esc_html( $additional ); ?></div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

			</main>

			<?php /* ── Price Rail ── */ ?>
			<aside class="fg-detail-rail" aria-label="Preisinfos und Anfrage">
				<div class="fg-rail-card">

					<?php if ( $price ) : ?>
						<div>
							<p class="fg-rail-label">Preis</p>
							<p class="fg-rail-price"><?php echo esc_html( $price ); ?></p>
							<?php if ( $per_person == '1' ) : ?>
								<p class="fg-rail-note" style="margin-top:4px;">Preis pro Person möglich</p>
							<?php endif; ?>
							<?php if ( $package_ok == '1' ) : ?>
								<p class="fg-rail-note">Pauschalpreis möglich</p>
							<?php endif; ?>
						</div>
					<?php else : ?>
						<div>
							<p class="fg-rail-label">Preis</p>
							<p class="fg-rail-price" style="font-size:20px;color:var(--ink-600);">Auf Anfrage</p>
						</div>
					<?php endif; ?>

					<?php if ( $price_note ) : ?>
						<p class="fg-rail-note"><?php echo esc_html( $price_note ); ?></p>
					<?php endif; ?>

					<hr class="fg-rail-divider">

					<a href="#event-anfrage" class="fg-btn fg-btn-brand fg-btn-block">Event anfragen</a>

					<?php if ( $partner['title'] ) : ?>
						<hr class="fg-rail-divider">
						<div class="fg-partner-block">
							<p class="fg-rail-label" style="margin-bottom:6px;">Golfplatz</p>
							<p class="fg-partner-name"><?php echo esc_html( $partner['title'] ); ?></p>
							<?php if ( $partner['city'] ) : ?>
								<p class="fg-partner-city"><?php echo fge_icon_map_pin(); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php echo esc_html( $partner['city'] ); ?></p>
							<?php endif; ?>
						</div>
					<?php endif; ?>

				</div>
			</aside>

		</div><?php /* .fg-detail-body */ ?>

		<?php /* ── Anfrage Placeholder ── */ ?>
		<section class="fg-anfrage" id="event-anfrage" aria-label="Event anfragen">
			<h2 class="fg-anfrage-title">Du interessierst dich für dieses Event?</h2>
			<p class="fg-anfrage-sub">Im nächsten Schritt kannst du hier deinen Wunschtermin anfragen — unkompliziert und ohne Vorkenntnisse.</p>
			<button class="fg-btn fg-btn-disabled" aria-disabled="true">Anfragefunktion folgt im nächsten Schritt</button>
		</section>

	</div><?php /* .fg-detail */ ?>

	<?php /* ── Similar Events ── */ ?>
	<?php if ( $similar_query->have_posts() ) : ?>
		<section class="fg-similar" aria-label="Weitere Firmenevents">
			<h2 class="fg-similar-title">Weitere Firmenevents</h2>
			<div class="fg-similar-grid">
				<?php while ( $similar_query->have_posts() ) : $similar_query->the_post();
					$sid   = get_the_ID();
					$stype = fge_format_event_type( fge_get_event_meta( $sid, 'event_type' ) );
					$sreg  = fge_get_event_meta( $sid, 'region' );
					$sprice = fge_get_event_price_display( $sid );
					$sthumb = has_post_thumbnail() ? get_the_post_thumbnail_url( $sid, 'large' ) : fge_get_placeholder_image_url( 'event-team.jpg' );
				?>
				<article class="fg-event">
					<a href="<?php the_permalink(); ?>">
						<div class="fg-event-photo" style="background-image: url('<?php echo esc_url( $sthumb ); ?>')">
							<?php if ( $stype ) : ?>
								<div class="fg-event-chips">
									<span class="fg-photo-chip"><?php echo esc_html( $stype ); ?></span>
								</div>
							<?php endif; ?>
						</div>
						<div class="fg-event-body">
							<?php if ( $stype ) : ?>
								<p class="fg-event-eyebrow"><?php echo esc_html( $stype ); ?></p>
							<?php endif; ?>
							<h3 class="fg-event-title"><?php the_title(); ?></h3>
							<div class="fg-event-meta">
								<?php if ( $sreg ) : ?>
									<?php echo fge_icon_map_pin(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
									<span><?php echo esc_html( $sreg ); ?></span>
								<?php endif; ?>
							</div>
							<div class="fg-event-foot">
								<?php if ( $sprice ) : ?>
									<span class="fg-event-price"><?php echo esc_html( $sprice ); ?></span>
								<?php endif; ?>
								<span class="fg-event-cta">Details <?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
							</div>
						</div>
					</a>
				</article>
				<?php endwhile; wp_reset_postdata(); ?>
			</div>
		</section>
	<?php endif; ?>

	<?php /* ── Footer ── */ ?>
	<footer class="fg-footer" aria-label="Seitenfooter">
		<div class="fg-footer-inner">
			<div class="fg-footer-top">
				<div class="fg-footer-brand">
					<img src="<?php echo esc_url( fge_get_logo_url( true ) ); ?>" alt="Firmengolf" width="110" height="26">
					<p class="fg-footer-line">Golf als Firmenbenefit und Eventformat — offen, frisch und unkompliziert.</p>
				</div>
				<div class="fg-footer-cols">
					<div>
						<p class="fg-footer-col-head">Firmenevents</p>
						<a href="<?php echo esc_url( get_post_type_archive_link( 'firmengolf_event' ) ); ?>">Alle Angebote</a>
						<a href="#event-anfrage">Event anfragen</a>
					</div>
					<div>
						<p class="fg-footer-col-head">Unternehmen</p>
						<a href="#">Corporate Benefit</a>
						<a href="#">Partner werden</a>
					</div>
					<div>
						<p class="fg-footer-col-head">Firmengolf</p>
						<a href="#">Über uns</a>
						<a href="#">Kontakt</a>
					</div>
				</div>
			</div>
			<div class="fg-footer-base">
				<span>© <?php echo esc_html( date( 'Y' ) ); ?> Firmengolf</span>
				<span>
					<a href="#" style="color:inherit;">Datenschutz</a>
					&ensp;·&ensp;
					<a href="#" style="color:inherit;">Impressum</a>
				</span>
			</div>
		</div>
	</footer>

</div><?php /* .fge-page */ ?>
<?php get_footer(); ?>
