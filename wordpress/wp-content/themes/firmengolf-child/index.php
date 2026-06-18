<?php
/**
 * Generischer Fallback. Greift für jede Abfrage, die kein spezifischeres Template
 * hat (z. B. Kategorie-, Schlagwort-, Datums-, Autor-Archive). Hält das Design
 * konsistent statt aufs Eltern-Block-Theme zurückzufallen.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div class="fge-page">

	<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => '' ] ); ?>

	<div class="fg-content-page">
		<?php if ( is_singular() ) : ?>
			<?php while ( have_posts() ) : the_post(); ?>
				<h1><?php the_title(); ?></h1>
				<?php the_content(); ?>
			<?php endwhile; ?>

		<?php elseif ( have_posts() ) : ?>
			<h1><?php echo esc_html( wp_strip_all_tags( get_the_archive_title() ) ); ?></h1>
			<?php $desc = get_the_archive_description(); ?>
			<?php if ( $desc ) : ?>
				<p><?php echo esc_html( wp_strip_all_tags( $desc ) ); ?></p>
			<?php endif; ?>
			<ul class="fge-result-list">
				<?php while ( have_posts() ) : the_post(); ?>
					<li>
						<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
						<?php $ex = get_the_excerpt(); ?>
						<?php if ( $ex ) : ?>
							<p><?php echo esc_html( wp_trim_words( $ex, 30 ) ); ?></p>
						<?php endif; ?>
					</li>
				<?php endwhile; ?>
			</ul>
			<?php the_posts_pagination( [ 'mid_size' => 1 ] ); ?>

		<?php else : ?>
			<h1>Nichts gefunden</h1>
			<p>Hier gibt es aktuell keine Inhalte.</p>
			<p>
				<a class="fg-btn fg-btn-ink fg-btn-lg" href="<?php echo esc_url( home_url( '/' ) ); ?>">Zur Startseite</a>
			</p>
		<?php endif; ?>
	</div>

	<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>
<?php get_footer(); ?>
