<?php
/**
 * „Nicht mehr verfügbar" (HTTP 410) – für dauerhaft entfernte Events/Golfplätze.
 * Wird von fge_render_gone_page() im Plugin aufgerufen.
 * Args: [ 'kind' => 'event'|'partner', 'post_id' => int ].
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kind    = ( ( $args['kind'] ?? '' ) === 'partner' ) ? 'partner' : 'event';
$post_id = (int) ( $args['post_id'] ?? 0 );

if ( 'partner' === $kind ) {
	$heading = 'Dieser Golfplatz ist nicht mehr gelistet';
	$intro   = 'Der angefragte Golfplatz steht bei Firmengolf aktuell nicht mehr zur Verfügung. Diese aktuellen Firmenevents könnten dich interessieren:';
} else {
	$heading = 'Dieses Event ist nicht mehr verfügbar';
	$intro   = 'Das angefragte Firmenevent ist nicht mehr buchbar. Diese aktuellen Events könnten passen:';
}

$suggestions = function_exists( 'fge_get_featured_events' ) ? fge_get_featured_events( 3 ) : [];

// noindex zusätzlich zum 410-Status (Gürtel + Hosenträger).
add_action( 'wp_head', static function () {
	echo '<meta name="robots" content="noindex">' . "\n";
}, 1 );

get_header();
?>
<div class="fge-page">

	<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => 'events' ] ); ?>

	<section class="fge-state">
		<p class="fge-state-code">410</p>
		<h1><?php echo esc_html( $heading ); ?></h1>
		<p><?php echo esc_html( $intro ); ?></p>
		<div class="fge-state-actions">
			<a class="fg-btn fg-btn-cta fg-btn-lg" href="<?php echo esc_url( get_post_type_archive_link( 'firmengolf_event' ) ); ?>">Alle Firmenevents</a>
			<a class="fg-btn fg-btn-ink fg-btn-lg" href="<?php echo esc_url( home_url( '/' ) ); ?>">Zur Startseite</a>
		</div>
	</section>

	<?php if ( ! empty( $suggestions ) ) : ?>
		<div class="fg-grid ev-grid4" style="max-width:1200px;margin:0 auto 64px;padding:0 20px;">
			<?php foreach ( $suggestions as $sp ) {
				get_template_part( 'template-parts/fge-event-card', null, [ 'id' => (int) $sp->ID, 'dist' => null ] );
			} ?>
		</div>
	<?php endif; ?>

	<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>
<?php
get_footer();
