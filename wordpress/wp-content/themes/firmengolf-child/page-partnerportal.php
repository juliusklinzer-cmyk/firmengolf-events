<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', function() {
	wp_enqueue_style(
		'fge-frontend',
		plugins_url( 'assets/css/fge-frontend.css', WP_PLUGIN_DIR . '/firmengolf-events/firmengolf-events.php' ),
		[],
		FGE_VERSION
	);
}, 5 );

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
				<span class="fg-nav-cta fg-nav-cta--active">
					Partnerportal
				</span>
			</div>
		</div>
	</nav>

	<div class="fg-portal-page">
		<?php fge_portal_render(); ?>
	</div>

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
