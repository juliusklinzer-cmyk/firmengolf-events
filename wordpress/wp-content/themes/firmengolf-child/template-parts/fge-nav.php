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
	</div>
</nav>
