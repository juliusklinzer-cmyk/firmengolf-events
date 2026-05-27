<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$args        = $args ?? [];
$active_item = (string) ( $args['active_item'] ?? '' );
$cta_label   = (string) ( $args['cta_label']   ?? 'Event anfragen' );
$cta_active  = (bool)   ( $args['cta_active']  ?? false );

// CTA URL: allow explicit override, otherwise resolve event-anfrage page.
if ( isset( $args['cta_url'] ) ) {
	$cta_url = (string) $args['cta_url'];
} else {
	$anfrage_page = get_page_by_path( 'event-anfrage' );
	$cta_url      = $anfrage_page ? (string) get_permalink( $anfrage_page->ID ) : home_url( '/event-anfrage/' );
}

// Resolve nav item URLs (graceful fallback if page not created yet).
$ind_page = get_page_by_path( 'individuelle-events' );
$ind_url  = $ind_page ? (string) get_permalink( $ind_page->ID ) : home_url( '/individuelle-events/' );

$nav_items = [
	[ 'key' => 'firmenevents',        'label' => 'Firmenevents',        'url' => (string) get_post_type_archive_link( 'firmengolf_event' ) ],
	[ 'key' => 'individuelle-events', 'label' => 'Individuelle Events', 'url' => $ind_url ],
	[ 'key' => 'blog',                'label' => 'Blog & Ratgeber',     'url' => home_url( '/blog/' ) ],
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
				   <?php if ( $active_item === $item['key'] ) : ?>class="fg-nav-item--active"<?php endif; ?>
				><?php echo esc_html( $item['label'] ); ?></a>
			<?php endforeach; ?>
		</div>
		<div class="fg-nav-end">
			<?php if ( $cta_active ) : ?>
				<span class="fg-nav-cta fg-nav-cta--active">
					<?php echo esc_html( $cta_label ); ?>
				</span>
			<?php else : ?>
				<a href="<?php echo esc_url( $cta_url ); ?>" class="fg-nav-cta">
					<?php echo esc_html( $cta_label ); ?> <?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</a>
			<?php endif; ?>
		</div>
	</div>
</nav>
