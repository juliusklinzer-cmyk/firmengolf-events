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

// Mobile-Tabs (das Mobile-Menü, ≤768px). Aktiv-Status aus active_item ableiten.
$mtab_active = static function ( string $key ) use ( $active_item ): bool {
	if ( 'events' === $key ) {
		return 'events' === $active_item;
	}
	if ( 'anfrage' === $key ) {
		return in_array( $active_item, [ 'individuelle-events', 'anfrage' ], true );
	}
	if ( 'blog' === $key ) {
		return 'blog' === $active_item;
	}
	return false;
};
$mtab_ic = [
	'events'  => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
	'anfrage' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/>',
	'blog'    => '<path d="M4 5a2 2 0 0 1 2-2h8l6 6v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z"/><path d="M14 3v6h6"/><path d="M8 13h8M8 17h5"/>',
];
$mtabs = [
	[ 'key' => 'events',  'label' => 'Events',  'url' => $url_events ],
	[ 'key' => 'anfrage', 'label' => 'Anfrage', 'url' => $url_anfrage ],
	[ 'key' => 'blog',    'label' => 'Blog',    'url' => $url_blog ],
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
	</div>
</nav>

<?php /* Mobile-Menü: obere sticky Leiste mit 3 Icon-Tabs (≤768px), kein Burger. */ ?>
<div class="ev-msearch-bar <?php echo $mbar_action ? '' : 'tabs-only'; ?>" id="fge-mbar">
	<div class="ev-mtabs">
		<?php foreach ( $mtabs as $t ) : ?>
			<a href="<?php echo esc_url( $t['url'] ); ?>" class="ev-mtab <?php echo $mtab_active( $t['key'] ) ? 'active' : ''; ?>">
				<span class="ev-mtab-ic">
					<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><?php echo $mtab_ic[ $t['key'] ]; // phpcs:ignore WordPress.Security.EscapeOutput -- statische SVG-Pfade ?></svg>
				</span>
				<span class="ev-mtab-l"><?php echo esc_html( $t['label'] ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>
	<?php
	if ( $mbar_action ) {
		echo $mbar_action; // phpcs:ignore WordPress.Security.EscapeOutput -- vom Aufrufer escaptes HTML
	}
	?>
</div>
<script>
(function () {
	var bar = document.getElementById('fge-mbar');
	if (!bar) { return; }
	var onScroll = function () { bar.classList.toggle('is-stuck', window.scrollY > 56); };
	window.addEventListener('scroll', onScroll, { passive: true });
	onScroll();
})();
</script>
