<?php
/**
 * Suchergebnisse – im Design des restlichen Frontends.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$search_query = get_search_query();
$found        = (int) ( $GLOBALS['wp_query']->found_posts ?? 0 );

get_header();
?>
<div class="fge-page">

	<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => '' ] ); ?>

	<div class="fg-content-page">
		<h1>Suchergebnisse</h1>
		<?php if ( $search_query !== '' ) : ?>
			<p>
				<?php
				printf(
					esc_html( _n( '%1$s Treffer für „%2$s"', '%1$s Treffer für „%2$s"', $found, 'firmengolf-events' ) ),
					esc_html( number_format_i18n( $found ) ),
					esc_html( $search_query )
				);
				?>
			</p>
		<?php endif; ?>

		<form class="fge-search-form" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<input type="search" name="s" value="<?php echo esc_attr( $search_query ); ?>" placeholder="Suchbegriff eingeben …" aria-label="Suche">
			<button class="fg-btn fg-btn-cta" type="submit">Suchen</button>
		</form>

		<?php if ( have_posts() ) : ?>
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
			<p>Keine Ergebnisse. Versuch es mit einem anderen Begriff oder sieh dir alle Firmenevents an.</p>
			<p>
				<a class="fg-btn fg-btn-ink fg-btn-lg" href="<?php echo esc_url( get_post_type_archive_link( 'firmengolf_event' ) ); ?>">Alle Firmenevents</a>
			</p>
		<?php endif; ?>
	</div>

	<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>
<?php get_footer(); ?>
