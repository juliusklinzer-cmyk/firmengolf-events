<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div class="fge-page">

	<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => 'blog' ] ); ?>

	<?php /* ── Seiten-Intro ── */ ?>
	<section class="fg-grid-section">
		<div class="fg-page-intro">
			<p class="fg-section-label">Blog & Ratgeber</p>
			<h1 class="fg-page-intro-title">Wissen für dein Firmenevent</h1>
			<p class="fg-page-intro-sub">Ratgeber, Eventideen und Praxistipps rund um Golf als Firmen- und Teamevent.</p>
		</div>

		<?php if ( have_posts() ) : ?>
			<div class="fg-grid">
				<?php while ( have_posts() ) : the_post(); ?>
					<article class="fg-event">
						<a href="<?php the_permalink(); ?>">
							<div class="fg-event-photo" style="background-image: url('<?php echo has_post_thumbnail() ? esc_url( get_the_post_thumbnail_url( get_the_ID(), 'large' ) ) : esc_url( fge_get_placeholder_image_url( 'event-team.jpg' ) ); ?>')">
							</div>
							<div class="fg-event-body">
								<span class="fg-type-tag"><?php echo esc_html( get_the_date( 'd. F Y' ) ); ?></span>
								<h2 class="fg-event-title"><?php the_title(); ?></h2>
								<p class="fg-event-desc"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 20 ) ); ?></p>
								<div class="fg-event-foot">
									<span class="fg-event-cta">Weiterlesen <?php echo fge_icon_arrow_right(); // phpcs:ignore ?></span>
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
			<div class="fg-empty">
				<h2 class="fg-empty-title">Beiträge erscheinen demnächst.</h2>
				<p>Hier entstehen Ratgeber, Tipps und Eventideen rund um Golf als Firmenevent.</p>
			</div>
		<?php endif; ?>
	</section>

	<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>
<?php get_footer(); ?>
