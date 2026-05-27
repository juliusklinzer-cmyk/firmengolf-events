<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Resolve page URLs (# fallback if page not yet created).
$get_page_url = static function( string $slug, string $fallback = '#' ): string {
	$page = get_page_by_path( $slug );
	return $page ? (string) get_permalink( $page->ID ) : $fallback;
};

$url_firmenevents  = (string) get_post_type_archive_link( 'firmengolf_event' );
$url_anfrage       = $get_page_url( 'event-anfrage',        home_url( '/event-anfrage/' ) );
$url_ind_events    = $get_page_url( 'individuelle-events',  home_url( '/individuelle-events/' ) );
$url_portal        = $get_page_url( 'partnerportal',        home_url( '/partnerportal/' ) );
$url_ueber_uns     = $get_page_url( 'ueber-uns' );
$url_kontakt       = $get_page_url( 'kontakt' );
$url_blog          = home_url( '/blog/' );
$url_datenschutz   = $get_page_url( 'datenschutz' );
$url_agb           = $get_page_url( 'agb' );
$url_impressum     = $get_page_url( 'impressum' );
?>
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
					<a href="<?php echo esc_url( $url_firmenevents ); ?>">Alle Angebote</a>
					<a href="<?php echo esc_url( $url_anfrage ); ?>">Event anfragen</a>
				</div>
				<div>
					<p class="fg-footer-col-head">Für Unternehmen</p>
					<a href="https://firmen.golf" target="_blank" rel="noopener noreferrer">Corporate Benefits</a>
					<a href="<?php echo esc_url( $url_ind_events ); ?>">Individuelle Events</a>
					<a href="<?php echo esc_url( $url_portal ); ?>">Partner werden</a>
				</div>
				<div>
					<p class="fg-footer-col-head">Firmengolf</p>
					<a href="<?php echo esc_url( $url_ueber_uns ); ?>">Über uns</a>
					<a href="<?php echo esc_url( $url_kontakt ); ?>">Kontakt</a>
					<a href="<?php echo esc_url( $url_blog ); ?>">Blog & Ratgeber</a>
				</div>
			</div>
		</div>
		<div class="fg-footer-base">
			<span>© <?php echo esc_html( date( 'Y' ) ); ?> Firmengolf</span>
			<span>
				<a href="<?php echo esc_url( $url_datenschutz ); ?>" style="color:inherit;">Datenschutz</a>
				&ensp;·&ensp;
				<a href="<?php echo esc_url( $url_agb ); ?>" style="color:inherit;">AGB</a>
				&ensp;·&ensp;
				<a href="<?php echo esc_url( $url_impressum ); ?>" style="color:inherit;">Impressum</a>
			</span>
		</div>
	</div>
</footer>
