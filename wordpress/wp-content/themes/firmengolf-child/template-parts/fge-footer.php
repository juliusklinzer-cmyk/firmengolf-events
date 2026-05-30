<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$get_page_url = static function( string $slug, string $fallback = '#' ): string {
	$page = get_page_by_path( $slug );
	return $page ? (string) get_permalink( $page->ID ) : $fallback;
};

$url_events    = (string) get_post_type_archive_link( 'firmengolf_event' );
$url_ind       = $get_page_url( 'individuelle-events', home_url( '/individuelle-events/' ) );
$url_blog      = home_url( '/blog/' );
$url_ueber_uns = $get_page_url( 'ueber-uns', home_url( '/ueber-uns/' ) );
$url_kontakt   = $get_page_url( 'kontakt', home_url( '/kontakt/' ) );
$url_portal    = $get_page_url( 'partnerportal', home_url( '/partnerportal/' ) );
$url_onboarding = home_url( '/partner-onboarding/' );
$url_datenschutz = $get_page_url( 'datenschutz' );
$url_agb        = $get_page_url( 'agb' );
$url_impressum  = $get_page_url( 'impressum' );
?>
<footer class="fg-footer" aria-label="Seitenfooter">
	<div class="fg-footer-top">
		<div class="fg-footer-brand">
			<img src="<?php echo esc_url( fge_get_logo_url() ); ?>" alt="Firmengolf" height="28">
			<p class="fg-footer-line">
				Firmengolf macht Golf zugänglich — als Event-Format, als Benefit, als Ausgleich.
				Offen, frei und gesund.
			</p>
			<div class="fg-footer-socials">
				<a aria-label="LinkedIn" href="#" target="_blank" rel="noopener noreferrer">in</a>
				<a aria-label="Instagram" href="#" target="_blank" rel="noopener noreferrer">ig</a>
				<a aria-label="YouTube" href="#" target="_blank" rel="noopener noreferrer">yt</a>
			</div>
		</div>
		<div class="fg-footer-cols">
			<div>
				<div class="fg-footer-head">Events</div>
				<a href="<?php echo esc_url( $url_events ); ?>">Alle Formate</a>
				<a href="<?php echo esc_url( add_query_arg( 'format', 'schnupperkurs', $url_events ) ); ?>">Schnupperkurs</a>
				<a href="<?php echo esc_url( add_query_arg( 'format', 'firmenturnier', $url_events ) ); ?>">Firmenturnier</a>
				<a href="<?php echo esc_url( add_query_arg( 'format', 'team-building', $url_events ) ); ?>">Team-Building</a>
				<a href="<?php echo esc_url( add_query_arg( 'format', 'offsite', $url_events ) ); ?>">Offsite</a>
				<a href="<?php echo esc_url( $url_ind ); ?>">Individuelles Event</a>
			</div>
			<div>
				<div class="fg-footer-head">Firmengolf</div>
				<a href="<?php echo esc_url( $url_ueber_uns ); ?>">Über uns</a>
				<a href="<?php echo esc_url( $url_blog ); ?>">Blog</a>
				<a href="<?php echo esc_url( $url_kontakt ); ?>">Kontakt</a>
				<a href="https://firmen.golf" target="_blank" rel="noopener noreferrer">Corporate Benefit ↗</a>
			</div>
			<div>
				<div class="fg-footer-head">Für Plätze</div>
				<a href="<?php echo esc_url( $url_portal ); ?>">Partnerportal</a>
				<a href="<?php echo esc_url( $url_onboarding ); ?>">Platz anbieten</a>
				<a href="#">Partner-FAQ</a>
			</div>
			<div>
				<div class="fg-footer-head">Rechtliches</div>
				<a href="<?php echo esc_url( $url_impressum ); ?>">Impressum</a>
				<a href="<?php echo esc_url( $url_datenschutz ); ?>">Datenschutz</a>
				<a href="<?php echo esc_url( $url_agb ); ?>">AGB</a>
			</div>
		</div>
	</div>
	<div class="fg-footer-bottom">
		<span>© <?php echo esc_html( (string) gmdate( 'Y' ) ); ?> Firmengolf GmbH · Hamburg</span>
		<div class="fg-footer-legal">
			<a href="#">DE · EN</a>
			<a href="#">Presse</a>
			<a href="#">Karriere</a>
		</div>
	</div>
</footer>
