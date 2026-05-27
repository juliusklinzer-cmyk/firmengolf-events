<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$anfrage_page = get_page_by_path( 'event-anfrage' );
$anfrage_url  = $anfrage_page ? (string) get_permalink( $anfrage_page->ID ) : home_url( '/event-anfrage/' );

get_header();
?>
<div class="fge-page">

	<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => 'firmenevents' ] ); ?>

	<?php /* ── Hero ── */ ?>
	<section class="fg-hero">
		<?php $hero_img = fge_get_placeholder_image_url( 'hero-fairway-wide.jpg' ); ?>
		<div class="fg-hero-photo" style="background-image: url('<?php echo esc_url( $hero_img ); ?>')">
			<div class="fg-hero-scrim" aria-hidden="true"></div>
			<div class="fg-hero-content">
				<p class="fg-hero-eyebrow">Firmenevents</p>
				<h1 class="fg-hero-title">Bring dein Team raus aus dem Büro.</h1>
				<p class="fg-hero-sub">Firmenevents auf dem Golfplatz — für Teams, die mehr wollen als den nächsten Meeting&shy;raum.</p>
				<div class="fg-hero-actions">
					<a href="<?php echo esc_url( $anfrage_url ); ?>" class="fg-btn fg-btn-brand fg-btn-lg">Event anfragen</a>
					<a href="#eventangebote" class="fg-btn fg-btn-glass fg-btn-lg">Eventangebote ansehen</a>
				</div>
			</div>
		</div>
	</section>

	<?php /* ── Intro ── */ ?>
	<section class="fg-intro" aria-label="Über Firmenevents">
		<div>
			<p class="fg-intro-label">Für Unternehmen</p>
			<h2 class="fg-intro-heading">Golf als Erlebnis für Teams und Kunden</h2>
		</div>
		<div>
			<p class="fg-intro-body">Firmengolf bündelt Firmenevents auf Golfplätzen in Deutschland. Unternehmen finden passende Eventformate, prüfen Details und fragen ihren Wunschtermin an — unkompliziert und ohne Vorkenntnisse.</p>
			<div class="fg-intro-targets">
				<?php foreach ( [ 'HR', 'Geschäftsführung', 'Marketing', 'Teams', 'Office Management' ] as $t ) : ?>
					<span class="fg-chip-sm"><?php echo esc_html( $t ); ?></span>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<?php /* ── Event Grid ── */ ?>
	<section class="fg-grid-section" id="eventangebote" aria-label="Eventangebote">
		<?php if ( have_posts() ) : ?>

			<div class="fg-grid-head">
				<h2 class="fg-grid-title">Eventangebote</h2>
				<span class="fg-grid-count"><?php echo esc_html( $wp_query->found_posts ); ?> <?php echo $wp_query->found_posts === 1 ? 'Angebot' : 'Angebote'; ?></span>
			</div>

			<div class="fg-grid">
				<?php while ( have_posts() ) : the_post();
					$post_id     = get_the_ID();
					$event_type  = fge_format_event_type( fge_get_event_meta( $post_id, 'event_type' ) );
					$region      = fge_get_event_meta( $post_id, 'region' );
					$location    = fge_get_event_meta( $post_id, 'event_location' );
					$p_min       = fge_get_event_meta( $post_id, 'participants_min' );
					$p_max       = fge_get_event_meta( $post_id, 'participants_max' );
					$duration    = fge_get_event_meta( $post_id, 'duration' );
					$description = fge_get_event_meta( $post_id, 'card_description', get_the_excerpt() );
					$price       = fge_get_event_price_display( $post_id );
					$leistungen  = array_values( fge_get_active_leistungen( $post_id ) );
					$badge_limit = 4;
					$thumb_url   = has_post_thumbnail() ? get_the_post_thumbnail_url( $post_id, 'large' ) : fge_get_placeholder_image_url( 'event-team.jpg' );
				?>
				<article class="fg-event">
					<a href="<?php the_permalink(); ?>">
						<div class="fg-event-photo" style="background-image: url('<?php echo esc_url( $thumb_url ); ?>')">
							<div class="fg-event-chips">
								<?php if ( $event_type ) : ?>
									<span class="fg-photo-chip"><?php echo esc_html( $event_type ); ?></span>
								<?php endif; ?>
							</div>
						</div>
						<div class="fg-event-body">
							<?php if ( $event_type ) : ?>
								<span class="fg-type-tag"><?php echo esc_html( $event_type ); ?></span>
							<?php endif; ?>
							<h3 class="fg-event-title"><?php the_title(); ?></h3>
							<?php if ( $description ) : ?>
								<p class="fg-event-desc"><?php echo esc_html( $description ); ?></p>
							<?php endif; ?>
							<div class="fg-event-meta">
								<?php if ( $region || $location ) : ?>
									<?php echo fge_icon_map_pin(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
									<span><?php echo esc_html( $region ?: $location ); ?></span>
								<?php endif; ?>
								<?php if ( $p_min || $p_max ) : ?>
									<span class="dot">·</span>
									<?php echo fge_icon_users(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
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
									<?php echo fge_icon_clock(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
									<span><?php echo esc_html( $duration ); ?></span>
								<?php endif; ?>
							</div>
							<?php if ( ! empty( $leistungen ) ) : ?>
								<div class="fg-badges">
									<?php foreach ( array_slice( $leistungen, 0, $badge_limit ) as $badge ) : ?>
										<span class="fg-badge"><?php echo esc_html( $badge ); ?></span>
									<?php endforeach; ?>
									<?php if ( count( $leistungen ) > $badge_limit ) : ?>
										<span class="fg-badge-more">+<?php echo esc_html( count( $leistungen ) - $badge_limit ); ?></span>
									<?php endif; ?>
								</div>
							<?php endif; ?>
							<div class="fg-event-foot">
								<?php if ( $price ) : ?>
									<span class="fg-event-price"><?php echo esc_html( $price ); ?></span>
								<?php else : ?>
									<span class="fg-event-price" style="color:var(--ink-500);font-size:14px;">Preis auf Anfrage</span>
								<?php endif; ?>
								<span class="fg-event-cta">Details <?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
							</div>
						</div>
					</a>
				</article>
				<?php endwhile; ?>
			</div>

			<?php the_posts_pagination( [
				'mid_size'  => 2,
				'prev_text' => fge_icon_arrow_left() . ' Zurück',
				'next_text' => 'Weiter ' . fge_icon_arrow_right(),
			] ); ?>

		<?php else : ?>

			<div class="fg-empty" id="eventangebote">
				<h2 class="fg-empty-title">Neue Firmenevents werden vorbereitet.</h2>
				<p>Aktuell werden neue Eventangebote zusammengestellt.<br>Schau bald wieder vorbei oder stelle eine allgemeine Anfrage.</p>
				<a href="<?php echo esc_url( $anfrage_url ); ?>" class="fg-btn fg-btn-brand">Event anfragen</a>
			</div>

		<?php endif; ?>
	</section>

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

	<?php /* ── Kein passendes Angebot? ── */ ?>
	<section class="fg-grid-section" id="event-anfrage" aria-label="Event anfragen">
		<div class="fg-anfrage">
			<h2 class="fg-anfrage-title">Kein passendes Angebot dabei?</h2>
			<p class="fg-anfrage-sub">Schreib uns — wir finden das richtige Format und den passenden Golfplatz für euer Team.</p>
			<a href="<?php echo esc_url( $anfrage_url ); ?>" class="fg-btn fg-btn-brand">Event anfragen <?php echo fge_icon_arrow_right(); // phpcs:ignore ?></a>
		</div>
	</section>

	<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>
<?php get_footer(); ?>
