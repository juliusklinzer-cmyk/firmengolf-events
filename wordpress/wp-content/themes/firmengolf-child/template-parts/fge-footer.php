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
$url_presse     = $get_page_url( 'presse' );
$url_karriere   = $get_page_url( 'karriere' );
?>
<footer class="fg-footer" aria-label="Seitenfooter">
	<div class="fg-footer-top">
		<div class="fg-footer-brand">
			<img src="<?php echo esc_url( fge_get_logo_url() ); ?>" alt="Firmengolf" height="28">
			<p class="fg-footer-line">
				Wir machen Golf zugänglich. Als Veranstaltungstyp, als Ausgleich und als
				Erlebnis, das Teams verbindet.
			</p>
			<p class="fg-footer-tag">
				Bringt euer Team raus aus dem Büro und rein ins <span class="mk-italic">Grüne</span>.
			</p>
			<div class="fg-footer-socials">
				<a aria-label="Instagram" href="https://www.instagram.com/firmengolf/" target="_blank" rel="noopener noreferrer">
					<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="5.5" fill="none" stroke="currentColor" stroke-width="1.7"/><circle cx="12" cy="12" r="4.2" fill="none" stroke="currentColor" stroke-width="1.7"/><circle cx="17.4" cy="6.6" r="1.2" fill="currentColor"/></svg>
				</a>
				<a aria-label="Facebook" href="https://www.facebook.com/Firmengolf" target="_blank" rel="noopener noreferrer">
					<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M22 12a10 10 0 1 0-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.78-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.89h-2.34v6.99A10 10 0 0 0 22 12z"/></svg>
				</a>
				<a aria-label="LinkedIn" href="https://www.linkedin.com/company/firmengolf/" target="_blank" rel="noopener noreferrer">
					<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.86 0-2.14 1.45-2.14 2.95v5.66H9.34V9h3.42v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46zM5.34 7.43a2.07 2.07 0 1 1 0-4.14 2.07 2.07 0 0 1 0 4.14zM7.12 20.45H3.55V9h3.57z"/></svg>
				</a>
			</div>
		</div>
		<div class="fg-footer-cols">
			<div>
				<div class="fg-footer-head">Events</div>
				<a href="<?php echo esc_url( $url_events ); ?>">Alle Veranstaltungstypen</a>
				<a href="<?php echo esc_url( add_query_arg( 'format', 'teamevent', $url_events ) ); ?>">Teamevent</a>
				<a href="<?php echo esc_url( add_query_arg( 'format', 'firmen_golfturnier', $url_events ) ); ?>">Firmen-Golfturnier</a>
				<a href="<?php echo esc_url( add_query_arg( 'format', 'after_work_golf', $url_events ) ); ?>">After-Work Golf</a>
				<a href="<?php echo esc_url( add_query_arg( 'format', 'offsite', $url_events ) ); ?>">Offsite</a>
				<a href="<?php echo esc_url( $url_ind ); ?>">Individuelles Event</a>
				<?php if ( function_exists( 'fge_get_cities' ) ) : ?>
					<div class="fg-footer-head" style="margin-top:18px;">Golf-Events nach Stadt</div>
					<?php foreach ( fge_get_cities() as $fc_slug => $fc_city ) : ?>
						<a href="<?php echo esc_url( home_url( '/golf-events/' . $fc_slug . '/' ) ); ?>">Firmenevents <?php echo esc_html( $fc_city['name'] ); ?></a>
					<?php endforeach; ?>
				<?php endif; ?>
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
				<a href="<?php echo esc_url( $get_page_url( 'partner-faq', home_url( '/partner-faq/' ) ) ); ?>">Partner-FAQ</a>
			</div>
			<div>
				<div class="fg-footer-head">Rechtliches</div>
				<a href="<?php echo esc_url( $url_impressum ); ?>">Impressum</a>
				<a href="<?php echo esc_url( $url_datenschutz ); ?>">Datenschutz</a>
				<a href="<?php echo esc_url( $url_agb ); ?>">AGB</a>
				<a href="#" onclick="if(window.klaro){window.klaro.show(undefined,true);}return false;">Cookie-Einstellungen</a>
			</div>
		</div>
	</div>
	<div class="fg-footer-base">
		<span>© <?php echo esc_html( (string) gmdate( 'Y' ) ); ?> Visionpunch UG (haftungsbeschränkt), München</span>
		<div class="fg-footer-links">
			<a href="<?php echo esc_url( $url_presse ); ?>">Presse</a>
			<span aria-hidden="true">·</span>
			<a href="<?php echo esc_url( $url_karriere ); ?>">Karriere</a>
		</div>
	</div>
</footer>
