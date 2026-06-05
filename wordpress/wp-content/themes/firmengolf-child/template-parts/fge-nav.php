<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$args        = $args ?? [];
$active_item = (string) ( $args['active_item'] ?? '' );

// Resolve page URLs (graceful fallback if page not created yet).
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
$url_anfrage   = $get_page_url( 'individuelle-events', home_url( '/individuelle-events/' ) );

$nav_items = [
	[ 'key' => 'events',              'label' => 'Events',              'url' => $url_events ],
	[ 'key' => 'individuelle-events', 'label' => 'Individuelle Events', 'url' => $url_ind ],
	[ 'key' => 'blog',                'label' => 'Blog',                'url' => $url_blog ],
	[ 'key' => 'ueber-uns',           'label' => 'Über uns',            'url' => $url_ueber_uns ],
	[ 'key' => 'kontakt',             'label' => 'Kontakt',             'url' => $url_kontakt ],
];
?>
<nav class="fg-topnav" aria-label="Hauptnavigation">
	<div class="fg-topnav-inner">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="fg-brand">
			<img src="<?php echo esc_url( fge_get_logo_url() ); ?>" alt="Firmengolf" width="120" height="24">
		</a>
		<div class="fg-nav-items">
			<?php foreach ( $nav_items as $item ) : ?>
				<a href="<?php echo esc_url( $item['url'] ); ?>"
				   <?php if ( $active_item === $item['key'] ) : ?>class="active"<?php endif; ?>
				><?php echo esc_html( $item['label'] ); ?></a>
			<?php endforeach; ?>
		</div>
		<div class="fg-nav-end">
			<a class="fg-nav-link" href="<?php echo esc_url( $url_portal ); ?>">Partnerportal</a>
			<a class="fg-nav-cta" href="<?php echo esc_url( $url_anfrage ); ?>">
				Jetzt anfragen
				<span class="fg-arrow">
					<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
				</span>
			</a>
		</div>
		<button class="fg-nav-burger" type="button" aria-label="Menü öffnen" aria-expanded="false" aria-controls="fge-mobile-drawer">
			<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
		</button>
	</div>
	<div class="fg-drawer-scrim" id="fge-mobile-drawer">
		<div class="fg-drawer" role="dialog" aria-modal="true" aria-label="Navigation">
			<div class="fg-drawer-top">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="fg-brand">
					<img src="<?php echo esc_url( fge_get_logo_url() ); ?>" alt="Firmengolf" width="120" height="24">
				</a>
				<button class="fg-drawer-close" type="button" aria-label="Menü schließen">
					<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
				</button>
			</div>
			<div class="fg-drawer-items">
				<?php foreach ( $nav_items as $item ) : ?>
					<a class="fg-drawer-link <?php echo $active_item === $item['key'] ? 'active' : ''; ?>" href="<?php echo esc_url( $item['url'] ); ?>">
						<?php echo esc_html( $item['label'] ); ?>
						<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
					</a>
				<?php endforeach; ?>
			</div>
			<div class="fg-drawer-foot">
				<a class="fg-nav-cta fg-drawer-cta" href="<?php echo esc_url( $url_anfrage ); ?>">
					Jetzt anfragen
					<span class="fg-arrow">
						<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
					</span>
				</a>
				<a class="fg-drawer-partner" href="<?php echo esc_url( $url_portal ); ?>">Partnerportal für Golfplätze →</a>
			</div>
		</div>
	</div>
</nav>
<script>
(function () {
	var nav = document.currentScript.previousElementSibling;
	if ( ! nav || ! nav.classList.contains( 'fg-topnav' ) ) { return; }
	var burger = nav.querySelector( '.fg-nav-burger' );
	var scrim  = nav.querySelector( '.fg-drawer-scrim' );
	if ( ! burger || ! scrim ) { return; }
	function open() { scrim.classList.add( 'is-open' ); document.body.style.overflow = 'hidden'; burger.setAttribute( 'aria-expanded', 'true' ); }
	function close() { scrim.classList.remove( 'is-open' ); document.body.style.overflow = ''; burger.setAttribute( 'aria-expanded', 'false' ); }
	burger.addEventListener( 'click', open );
	scrim.querySelector( '.fg-drawer-close' ).addEventListener( 'click', close );
	scrim.addEventListener( 'click', function ( e ) { if ( e.target === scrim ) { close(); } } );
	scrim.querySelectorAll( '.fg-drawer-link, .fg-drawer-cta, .fg-drawer-partner' ).forEach( function ( a ) { a.addEventListener( 'click', close ); } );
	document.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Escape' && scrim.classList.contains( 'is-open' ) ) { close(); } } );
})();
</script>
