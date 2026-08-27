<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$args        = $args ?? [];
$active_item = (string) ( $args['active_item'] ?? '' );
// Optionale kontextuelle Aktion in der Mobile-Bar (Etappe 2: Such-Pille / CTA pro Seite).
// Erwartet fertiges, escaptes HTML.
$mbar_action = (string) ( $args['mbar_action'] ?? '' );

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

/* Wie der Desktop-CTA direkt in die 30-Sekunden-Schnellanfrage (Julius, 2026-08-10). */
$url_quick = add_query_arg( 'anfrage', 'quick', $url_anfrage );
?>
<nav class="fg-topnav" aria-label="Hauptnavigation">
	<div class="fg-topnav-inner">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="fg-brand">
			<img src="<?php echo esc_url( fge_get_logo_url() ); ?>" alt="Firmengolf" width="104" height="24">
		</a>
		<div class="fg-nav-items">
			<?php foreach ( $nav_items as $item ) : ?>
				<a href="<?php echo esc_url( $item['url'] ); ?>"
				   <?php if ( $active_item === $item['key'] ) : ?>class="active" aria-current="page"<?php endif; ?>
				><?php echo esc_html( $item['label'] ); ?></a>
			<?php endforeach; ?>
		</div>
		<div class="fg-nav-end">
			<a class="fg-nav-link" href="<?php echo esc_url( $url_portal ); ?>">Partnerportal</a>
			<a class="fg-nav-cta" href="<?php echo esc_url( $url_quick ); ?>">
				Jetzt anfragen
				<span class="fg-arrow">
					<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
				</span>
			</a>
		</div>
		<?php /* Mobile (≤768px): nur Logo + Burger, alles statisch, nichts sticky.
			(Anfrage-Pill entfernt, Julius 28.08.: der Hero trägt denselben Button,
			und beim Scrollen ist der Header ohnehin aus dem Bild.) */ ?>
		<button class="fg-nav-burger" type="button" id="fge-burger" aria-label="Menü öffnen" aria-expanded="false" aria-controls="fge-drawer">
			<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
		</button>
	</div>
</nav>

<?php /* Mobiles Slide-in-Menü (Drawer), öffnet über den Burger. */ ?>
<div class="fg-drawer-scrim" id="fge-drawer" role="dialog" aria-modal="true" aria-label="Menü" hidden>
	<div class="fg-drawer">
		<div class="fg-drawer-top">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="fg-brand">
				<img src="<?php echo esc_url( fge_get_logo_url() ); ?>" alt="Firmengolf" width="104" height="24">
			</a>
			<button class="fg-drawer-close" type="button" id="fge-drawer-close" aria-label="Menü schließen">
				<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
			</button>
		</div>
		<div class="fg-drawer-items">
			<?php foreach ( $nav_items as $item ) : ?>
				<a href="<?php echo esc_url( $item['url'] ); ?>" class="fg-drawer-link <?php echo $active_item === $item['key'] ? 'active' : ''; ?>"<?php echo $active_item === $item['key'] ? ' aria-current="page"' : ''; ?>>
					<?php echo esc_html( $item['label'] ); ?>
					<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>
				</a>
			<?php endforeach; ?>
		</div>
		<div class="fg-drawer-foot">
			<a class="fg-nav-cta fg-drawer-cta" href="<?php echo esc_url( $url_quick ); ?>">
				Jetzt anfragen
				<span class="fg-arrow">
					<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
				</span>
			</a>
			<a class="fg-drawer-partner" href="<?php echo esc_url( $url_portal ); ?>">Partnerportal für Golfplätze</a>
		</div>
	</div>
</div>

<?php /* Mobile: kontextuelle Aktion (Such-Pille) statisch oben, scrollt mit weg. */ ?>
<?php if ( $mbar_action ) : ?>
<div class="ev-msearch-bar" id="fge-mbar">
	<?php echo $mbar_action; // phpcs:ignore WordPress.Security.EscapeOutput -- vom Aufrufer escaptes HTML ?>
</div>
<?php endif; ?>

<script>
(function () {
	var burger = document.getElementById('fge-burger');
	var drawer = document.getElementById('fge-drawer');
	var close  = document.getElementById('fge-drawer-close');
	if (!burger || !drawer || !close) { return; }
	var open = function () {
		drawer.hidden = false;
		// hidden-Attribut erst im naechsten Frame durch die Klasse ersetzen,
		// damit die Einblend-Animation des Drawers greift.
		requestAnimationFrame(function () { drawer.classList.add('is-open'); });
		burger.setAttribute('aria-expanded', 'true');
		document.documentElement.classList.add('fg-drawer-lock');
		close.focus();
	};
	var shut = function () {
		drawer.classList.remove('is-open');
		drawer.hidden = true;
		burger.setAttribute('aria-expanded', 'false');
		document.documentElement.classList.remove('fg-drawer-lock');
		burger.focus();
	};
	burger.addEventListener('click', open);
	close.addEventListener('click', shut);
	drawer.addEventListener('click', function (e) { if (e.target === drawer) { shut(); } });
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && !drawer.hidden) { shut(); }
	});
})();
</script>
