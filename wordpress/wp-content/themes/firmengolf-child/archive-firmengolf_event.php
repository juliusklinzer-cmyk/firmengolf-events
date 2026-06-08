<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

// ── URL / page setup ────────────────────────────────────────────────────────
$archive_url = (string) get_post_type_archive_link( 'firmengolf_event' );
$ind_page    = get_page_by_path( 'individuelle-events' );
$ind_url     = $ind_page ? (string) get_permalink( $ind_page->ID ) : home_url( '/individuelle-events/' );
$kontakt_url = ( $kp = get_page_by_path( 'kontakt' ) ) ? (string) get_permalink( $kp->ID ) : home_url( '/kontakt/' );

// ── Sanitize GET params ──────────────────────────────────────────────────────
$active_format = sanitize_key( $_GET['format'] ?? 'all' );        // phpcs:ignore WordPress.Security.NonceVerification
$active_region = sanitize_text_field( $_GET['region'] ?? '' );    // phpcs:ignore WordPress.Security.NonceVerification
$active_pax    = max( 0, (int) ( $_GET['pax'] ?? 10 ) );          // phpcs:ignore WordPress.Security.NonceVerification — default 10
$active_sort   = sanitize_key( $_GET['sort'] ?? 'curated' );      // phpcs:ignore WordPress.Security.NonceVerification

// ── Geo (Umkreissuche) params ────────────────────────────────────────────────
$active_lat    = isset( $_GET['lat'] ) ? (float) $_GET['lat'] : 0.0;              // phpcs:ignore WordPress.Security.NonceVerification
$active_lng    = isset( $_GET['lng'] ) ? (float) $_GET['lng'] : 0.0;              // phpcs:ignore WordPress.Security.NonceVerification
$active_radius = isset( $_GET['radius'] ) ? max( 0, (int) $_GET['radius'] ) : 0;  // phpcs:ignore WordPress.Security.NonceVerification
$active_loc    = sanitize_text_field( wp_unslash( $_GET['loc'] ?? '' ) );         // phpcs:ignore WordPress.Security.NonceVerification
$geo_active    = ( $active_lat && $active_lng && $active_radius > 0 && function_exists( 'fge_geo_distance' ) );

// ── Format list (canonical — single source: event-formats.php) ───────────────
$formats = array_merge( [ 'all' => 'Alle Typen' ], fge_get_event_formats_flat( false ) );

// ── Regions: only those with at least one published event ────────────────────
global $wpdb;
$available_regions = $wpdb->get_col( $wpdb->prepare(
	"SELECT DISTINCT pm.meta_value
	 FROM {$wpdb->postmeta} pm
	 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
	 WHERE pm.meta_key = %s
	   AND p.post_type = %s
	   AND p.post_status = 'publish'
	   AND pm.meta_value != ''
	 ORDER BY pm.meta_value",
	'_fge_region',
	'firmengolf_event'
) );
if ( empty( $available_regions ) ) {
	$available_regions = [ 'Nord', 'Ost', 'Süd', 'West' ]; // fallback
}

// ── Build chip URL (preserves other active params) ───────────────────────────
$chip_url = static function( array $params ) use ( $archive_url, $active_format, $active_pax, $active_sort, $active_lat, $active_lng, $active_radius, $active_loc ): string {
	$base = [
		'format' => $active_format,
		'pax'    => $active_pax,
		'sort'   => $active_sort,
		'lat'    => $active_lat ?: '',
		'lng'    => $active_lng ?: '',
		'radius' => $active_radius ?: '',
		'loc'    => $active_loc,
	];
	$merged = array_filter( array_merge( $base, $params ), static fn( $v ) => $v !== '' && $v !== null );
	return add_query_arg( $merged, $archive_url );
};

// ── Base meta filters (Status + format / pax) ────────────────────────────────
// Nur freigegebene Angebote öffentlich zeigen (Lifecycle). Pausierte Plätze → Nachfilter unten.
$meta_query = [
	[ 'key' => '_fge_event_status', 'value' => 'freigegeben', 'compare' => '=' ],
];
if ( $active_format !== 'all' ) {
	$meta_query[] = [ 'key' => '_fge_event_type', 'value' => $active_format, 'compare' => '=' ];
}
if ( $active_pax > 0 ) {
	$meta_query[] = [ 'key' => '_fge_participants_max', 'value' => $active_pax, 'compare' => '>=', 'type' => 'NUMERIC' ];
}

// ── Build the event list: geo-filtered (Umkreis) or paged WP_Query ───────────
$event_items = []; // each: [ 'id' => int, 'dist' => float|null ]

if ( $geo_active ) {
	$ids = get_posts( [
		'post_type'      => 'firmengolf_event',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => $meta_query ?: null,
	] );
	foreach ( $ids as $id ) {
		$coords = fge_geo_event_coords( (int) $id );
		if ( ! $coords ) {
			continue;
		}
		$dist = fge_geo_distance( $active_lat, $active_lng, $coords[0], $coords[1] );
		if ( $dist <= $active_radius ) {
			$event_items[] = [ 'id' => (int) $id, 'dist' => $dist ];
		}
	}
	switch ( $active_sort ) {
		case 'price-asc':
			usort( $event_items, static fn( $a, $b ) => (float) get_post_meta( $a['id'], '_fge_sale_price_net', true ) <=> (float) get_post_meta( $b['id'], '_fge_sale_price_net', true ) );
			break;
		case 'rating':
			usort( $event_items, static fn( $a, $b ) => (float) get_post_meta( $b['id'], '_fge_rating', true ) <=> (float) get_post_meta( $a['id'], '_fge_rating', true ) );
			break;
		case 'group':
			usort( $event_items, static fn( $a, $b ) => (int) get_post_meta( $b['id'], '_fge_participants_max', true ) <=> (int) get_post_meta( $a['id'], '_fge_participants_max', true ) );
			break;
		default:
			usort( $event_items, static fn( $a, $b ) => $a['dist'] <=> $b['dist'] );
	}
	$total = count( $event_items );
} else {
	$query_args = [
		'post_type'      => 'firmengolf_event',
		'post_status'    => 'publish',
		'posts_per_page' => 24,
		'paged'          => max( 1, (int) ( $_GET['paged'] ?? get_query_var( 'paged' ) ) ),  // phpcs:ignore WordPress.Security.NonceVerification
		'meta_query'     => $meta_query ?: null,
	];
	switch ( $active_sort ) {
		case 'price-asc':
			$query_args['meta_key'] = '_fge_sale_price_net';
			$query_args['orderby']  = 'meta_value_num';
			$query_args['order']    = 'ASC';
			break;
		case 'rating':
			$query_args['meta_key'] = '_fge_rating';
			$query_args['orderby']  = 'meta_value_num';
			$query_args['order']    = 'DESC';
			break;
		case 'group':
			$query_args['meta_key'] = '_fge_participants_max';
			$query_args['orderby']  = 'meta_value_num';
			$query_args['order']    = 'DESC';
			break;
		default:
			$query_args['orderby'] = 'menu_order';
			$query_args['order']   = 'ASC';
	}
	$events_query = new WP_Query( $query_args );
	foreach ( $events_query->posts as $p ) {
		$event_items[] = [ 'id' => (int) $p->ID, 'dist' => null ];
	}
	$total = (int) $events_query->found_posts;
}

// ── Pausieren-Kaskade: Angebote pausierter Plätze ausblenden ──────────────────
if ( function_exists( 'fge_event_is_public' ) ) {
	$event_items = array_values( array_filter( $event_items, static fn( $it ) => fge_event_is_public( $it['id'] ) ) );
	if ( $geo_active ) {
		$total = count( $event_items );
	}
}

// ── Heading ──────────────────────────────────────────────────────────────────
$heading_parts = [];
if ( $active_format !== 'all' ) {
	$heading_parts[] = $formats[ $active_format ] ?? 'Events';
}
if ( $geo_active && $active_loc !== '' ) {
	$heading_parts[] = $active_loc . ' · ' . $active_radius . ' km';
}
if ( $active_pax > 0 ) {
	$heading_parts[] = $active_pax . '+ Pers.';
}
$heading = $heading_parts ? implode( ' · ', $heading_parts ) : 'Alle Firmenevents';

// ── Arrow SVG ────────────────────────────────────────────────────────────────
$arrow = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17L17 7M9 7h8v8"/></svg>';
?>
<div class="fge-page">

<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => 'events' ] ); ?>

<?php /* ══════════════ HERO ══════════════ */ ?>
<section class="ev-hero" aria-label="Events">
	<div class="ev-hero-photo" style="background-image:url('<?php echo esc_url( fge_get_placeholder_image_url( 'hero-range.jpg' ) ); ?>')">
		<div class="ev-hero-scrim" aria-hidden="true"></div>
		<div class="ev-hero-content">
			<div class="ev-hero-eyebrow">Marketplace · Firmenevents</div>
			<h1 class="ev-hero-title">
				Finde dein nächstes <em class="mk-italic">Firmen-Event</em>.
			</h1>
			<p class="ev-hero-sub">
				Der Golfplatz ist die perfekte Location für euer nächstes Firmenevent — wir bringen euer Team in Bewegung. Such nach Ort, Anlass und Gruppengröße.
			</p>
		</div>
	</div>

	<?php /* ── Search bar ── */ ?>
	<form method="get" action="<?php echo esc_url( $archive_url ); ?>" class="fg-search-bar" role="search">

		<?php /* Cell 1: Format dropdown */ ?>
		<div class="fg-search-cell fg-format-cell" id="fg-format-cell"
		     tabindex="0" role="button" aria-haspopup="listbox" aria-expanded="false" aria-label="Format wählen">
			<div class="fg-cell-label">Veranstaltungstyp</div>
			<div class="fg-cell-value" id="fg-format-display">
				<span id="fg-format-text"><?php echo esc_html( $formats[ $active_format ] ?? 'Alle Formate' ); ?></span>
			</div>
			<input type="hidden" name="format" id="fg-format-val" value="<?php echo esc_attr( $active_format ); ?>">
			<div class="fg-search-panel" id="fg-format-panel" role="listbox">
				<?php foreach ( $formats as $slug => $label ) : ?>
					<button type="button"
					        class="fg-search-panel-opt<?php echo $active_format === $slug ? ' is-selected' : ''; ?>"
					        data-value="<?php echo esc_attr( $slug ); ?>"
					        data-label="<?php echo esc_attr( $label ); ?>"
					        role="option"
					        aria-selected="<?php echo $active_format === $slug ? 'true' : 'false'; ?>">
						<?php echo esc_html( $label ); ?>
					</button>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="fg-cell-divider" aria-hidden="true"></div>

		<?php /* Cell 2: Wo? — Ort/PLZ-Autocomplete + Standort + Umkreis */ ?>
		<div class="fg-search-cell fg-loc-cell" id="fg-loc-cell"
		     tabindex="0" role="button" aria-haspopup="dialog" aria-expanded="false" aria-label="Ort oder PLZ wählen">
			<div class="fg-cell-label">Wo?</div>
			<div class="fg-cell-value<?php echo $active_loc === '' ? ' fg-muted' : ''; ?>" id="fg-loc-display">
				<span id="fg-loc-text"><?php echo $active_loc !== '' ? esc_html( $active_loc ) : 'Ort oder PLZ'; ?></span>
			</div>
			<input type="hidden" name="lat"    id="fg-lat"     value="<?php echo esc_attr( $active_lat ?: '' ); ?>">
			<input type="hidden" name="lng"    id="fg-lng"     value="<?php echo esc_attr( $active_lng ?: '' ); ?>">
			<input type="hidden" name="radius" id="fg-radius"  value="<?php echo esc_attr( (string) ( $active_radius ?: 50 ) ); ?>">
			<input type="hidden" name="loc"    id="fg-loc-val" value="<?php echo esc_attr( $active_loc ); ?>">
			<div class="fg-search-panel fg-loc-panel" id="fg-loc-panel" role="dialog" aria-label="Ort und Umkreis">
				<div class="fg-loc-search">
					<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
					<input type="text" id="fg-loc-input" placeholder="Ort oder PLZ" autocomplete="off" value="<?php echo esc_attr( $active_loc ); ?>">
				</div>
				<button type="button" class="fg-loc-gps" id="fg-loc-gps">
					<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/></svg>
					Meinen Standort
				</button>
				<div class="fg-loc-suggest" id="fg-loc-suggest" role="listbox"></div>
				<div class="fg-loc-radius">
					<div class="fg-loc-radius-label">Umkreis</div>
					<div class="fg-loc-radius-btns">
						<?php foreach ( [ 25, 50, 100, 200 ] as $r ) : ?>
							<button type="button" class="fg-loc-rb<?php echo ( (int) ( $active_radius ?: 50 ) === $r ) ? ' active' : ''; ?>" data-r="<?php echo (int) $r; ?>"><?php echo (int) $r; ?> km</button>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		</div>

		<div class="fg-cell-divider" aria-hidden="true"></div>

		<?php /* Cell 3: Personenzahl with +/− (default 10) */ ?>
		<div class="fg-search-cell fg-pax-cell">
			<div class="fg-cell-label">Personen</div>
			<div class="fg-pax-ctrl">
				<button type="button" class="fg-pax-btn" data-fn="dec" aria-label="Weniger Personen"
				        <?php echo $active_pax <= 1 ? 'disabled' : ''; ?>>−</button>
				<span class="fg-pax-num" id="fg-pax-display">
					<?php echo esc_html( (string) $active_pax ); ?> Pers.
				</span>
				<button type="button" class="fg-pax-btn" data-fn="inc" aria-label="Mehr Personen">+</button>
			</div>
			<input type="hidden" name="pax" id="fg-pax-val" value="<?php echo esc_attr( (string) $active_pax ); ?>">
		</div>

		<input type="hidden" name="sort" value="<?php echo esc_attr( $active_sort ); ?>">

		<button type="submit" class="fg-search-btn" aria-label="Events suchen">
			<span>Suchen</span>
			<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
		</button>
	</form>
</section>

<?php /* ══════════════ FORMAT CHIPS ══════════════ */ ?>
<div class="fg-filter-rail">
	<div class="fg-chip-row">
		<?php foreach ( $formats as $slug => $label ) : ?>
			<a href="<?php echo esc_url( $chip_url( [ 'format' => $slug ] ) ); ?>"
			   class="fg-chip<?php echo $active_format === $slug ? ' active' : ''; ?>">
				<?php echo esc_html( $label ); ?>
			</a>
		<?php endforeach; ?>
	</div>
</div>

<?php /* ══════════════ GRID ══════════════ */ ?>
<section class="fg-grid-section" aria-label="Eventangebote">

	<?php /* Active filter pills */ ?>
	<?php $has_filters = ( $active_format !== 'all' ) || $geo_active || ( $active_pax !== 10 ); ?>
	<?php if ( $has_filters ) : ?>
		<div class="fg-activefilters">
			<?php if ( $active_format !== 'all' ) : ?>
				<a class="fg-fpill" href="<?php echo esc_url( $chip_url( [ 'format' => 'all' ] ) ); ?>">
					<?php echo esc_html( $formats[ $active_format ] ?? $active_format ); ?>
					<span class="fg-fpill-x" aria-hidden="true">×</span>
				</a>
			<?php endif; ?>
			<?php if ( $geo_active && $active_loc !== '' ) : ?>
				<a class="fg-fpill" href="<?php echo esc_url( $chip_url( [ 'lat' => '', 'lng' => '', 'radius' => '', 'loc' => '' ] ) ); ?>">
					<?php echo esc_html( $active_loc . ' · ' . $active_radius . ' km' ); ?>
					<span class="fg-fpill-x" aria-hidden="true">×</span>
				</a>
			<?php endif; ?>
			<?php if ( $active_pax !== 10 ) : ?>
				<a class="fg-fpill" href="<?php echo esc_url( $chip_url( [ 'pax' => 10 ] ) ); ?>">
					<?php echo esc_html( $active_pax . '+ Pers.' ); ?>
					<span class="fg-fpill-x" aria-hidden="true">×</span>
				</a>
			<?php endif; ?>
			<a class="fg-fclear" href="<?php echo esc_url( $archive_url ); ?>">Alle zurücksetzen</a>
		</div>
	<?php endif; ?>

	<div class="fg-grid-head">
		<span class="fg-grid-count">
			<?php echo esc_html( $total . ' ' . ( $total === 1 ? 'Event gefunden' : 'Events gefunden' ) ); ?>
		</span>
		<div class="ev-grid-controls">
			<?php
			$sort_options = [
				'curated'   => $geo_active ? 'Nächste zuerst' : 'Empfohlen',
				'price-asc' => 'Preis aufsteigend',
				'rating'    => 'Beste Bewertung',
				'group'     => 'Größte Gruppen',
			];
			$sort_label = $sort_options[ $active_sort ] ?? reset( $sort_options );
			?>
			<div class="ev-sort">
				<span class="fg-cell-label">Sortieren</span>
				<div class="ev-dd" id="fg-sort-dd">
					<button type="button" class="ev-dd-trigger" id="fg-sort-trigger" aria-haspopup="listbox" aria-expanded="false">
						<span><?php echo esc_html( $sort_label ); ?></span>
						<svg viewBox="0 0 12 8" width="12" height="8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 1l5 5 5-5"/></svg>
					</button>
					<ul class="ev-dd-menu" id="fg-sort-menu" role="listbox">
						<?php foreach ( $sort_options as $sid => $slabel ) : ?>
							<li>
								<a class="ev-dd-item<?php echo $active_sort === $sid ? ' on' : ''; ?>" href="<?php echo esc_url( $chip_url( [ 'sort' => $sid ] ) ); ?>" role="option" aria-selected="<?php echo $active_sort === $sid ? 'true' : 'false'; ?>">
									<?php echo esc_html( $slabel ); ?>
									<?php if ( $active_sort === $sid ) : ?>
										<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>
									<?php endif; ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		</div>
	</div>

	<?php if ( ! empty( $event_items ) ) : ?>

		<div class="fg-grid ev-grid4">
			<?php foreach ( $event_items as $item ) :
				$pid   = (int) $item['id'];
				$gpost = get_post( $pid );
				if ( ! $gpost ) { continue; }
				$GLOBALS['post'] = $gpost;
				setup_postdata( $gpost );
				$edist    = $item['dist'];
				$etype    = fge_get_event_meta( $pid, 'event_type' );
				$elabel   = fge_format_event_type( $etype ) ?: ucfirst( $etype );
				$venue    = fge_get_event_meta( $pid, 'event_location' );
				$region_m = fge_get_event_meta( $pid, 'region' );
				$eyebrow  = trim( $elabel . ' · ' . fge_get_event_meta( $pid, 'duration' ), ' ·' );
				$p_max    = fge_get_event_meta( $pid, 'participants_max' );
				$duration = fge_get_event_meta( $pid, 'duration' );
				$price    = fge_get_event_price_display( $pid );
				$cpartner = (int) fge_get_event_meta( $pid, 'assigned_partner_id', 0 );
				$rating   = $cpartner ? (float) get_post_meta( $cpartner, '_fge_rating', true ) : 0;
				if ( ! $rating ) { $rating = (float) fge_get_event_meta( $pid, 'rating' ); }
				$reviews  = fge_get_event_meta( $pid, 'reviews_count' );
				$featured = fge_get_event_meta( $pid, 'featured' );
				$thumb    = has_post_thumbnail() ? get_the_post_thumbnail_url( $pid, 'large' ) : fge_get_placeholder_image_url( 'golf-coaching-gruppe.jpg' );
				$indoor   = in_array( 'Indoor-Backup', fge_get_active_leistungen( $pid ), true );
				$tags_arr = array_filter( array_map( 'trim', explode( ',', (string) fge_get_event_meta( $pid, 'event_tags' ) ) ) );
			?>
			<article class="fg-event ev-card2">
				<a href="<?php the_permalink(); ?>" style="display:contents">
					<div class="fg-event-photo" style="background-image:url('<?php echo esc_url( $thumb ); ?>')">
						<div class="fg-event-chips">
							<?php if ( $elabel ) : ?>
								<span class="fg-photo-chip"><?php echo esc_html( $elabel ); ?></span>
							<?php endif; ?>
						</div>
						<button class="fg-event-heart" type="button" aria-label="Event teilen"
						        data-share-url="<?php echo esc_url( get_permalink() ); ?>" data-share-title="<?php echo esc_attr( get_the_title() ); ?>">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 15V3"/><path d="M8 7l4-4 4 4"/><path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7"/></svg>
						</button>
						<?php if ( $edist !== null ) : ?>
							<span class="ev-distbadge">
								<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
								<?php echo esc_html( (string) round( $edist ) ); ?> km
							</span>
						<?php endif; ?>
					</div>
					<div class="fg-event-body">
						<div class="ev-card2-top">
							<?php if ( $eyebrow ) : ?>
								<div class="fg-event-eyebrow"><?php echo esc_html( $eyebrow ); ?></div>
							<?php endif; ?>
							<?php if ( $rating ) : ?>
								<div class="fg-event-rating">
									<svg viewBox="0 0 24 24" width="13" height="13" fill="#C9B488" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>
									<span><?php echo esc_html( (string) $rating ); ?></span>
								</div>
							<?php endif; ?>
						</div>
						<h3 class="fg-event-title"><?php the_title(); ?></h3>
						<div class="ev-card2-loc">
							<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
							<?php if ( $venue || $region_m ) : ?><span><?php echo esc_html( $venue ?: $region_m ); ?></span><?php endif; ?>
							<?php if ( $p_max ) : ?><span class="dot">·</span><span>bis <?php echo esc_html( (string) $p_max ); ?> Gäste</span><?php endif; ?>
							<?php if ( $duration ) : ?><span class="dot">·</span><span><?php echo esc_html( $duration ); ?></span><?php endif; ?>
						</div>
						<?php
						$mini_tags = array();
						if ( $indoor ) { $mini_tags[] = 'Indoor-Backup'; }
						foreach ( array_slice( $tags_arr, 0, 2 ) as $tg ) { $mini_tags[] = $tg; }
						?>
						<?php if ( $mini_tags ) : ?>
							<div class="ev-card2-badges">
								<?php foreach ( $mini_tags as $mt ) : ?><span class="ev-mini-tag"><?php echo esc_html( $mt ); ?></span><?php endforeach; ?>
							</div>
						<?php endif; ?>
						<div class="fg-event-foot ev-card2-foot">
							<div class="fg-event-price">
								<?php if ( $price ) : ?><?php echo esc_html( $price ); ?><?php else : ?><span style="font-size:13px;color:var(--ink-500)">Auf Anfrage</span><?php endif; ?>
							</div>
							<span class="ev-card2-cta">Ansehen <?php echo $arrow; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						</div>
					</div>
				</a>
			</article>
			<?php endforeach; wp_reset_postdata(); ?>
		</div>

	<?php else : ?>

		<div class="fg-empty">
			Noch nichts Passendes dabei. Plane dein Format individuell — wir kümmern uns drum.
			<div style="margin-top:16px">
				<a class="fg-btn-cta" href="<?php echo esc_url( $ind_url ); ?>">
					Individuelles Event anfragen
					<span class="fg-arrow"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17L17 7M9 7h8v8"/></svg></span>
				</a>
			</div>
		</div>

	<?php endif; ?>

	<?php /* Inline CTA */ ?>
	<div class="ev-inline-cta">
		<div>
			<div class="mk-eyebrow">Kein passendes Format dabei?</div>
			<h3 class="ev-inline-h">Wir planen dein Event nach deinen Ansprüchen.</h3>
		</div>
		<a class="fg-btn-cta" href="<?php echo esc_url( $ind_url ); ?>">
			Individuelles Event anfragen
			<span class="fg-arrow"><?php echo $arrow; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
		</a>
	</div>
</section>

<?php /* ══════════════ TRUST STRIP ══════════════ */ ?>
<div class="trust-strip" aria-label="Vertrauen">
	<div class="trust-inner">
		<?php
		$trust = [
			[ '180+ Partnerplätze',  'Vom Stadtkurs bis zur Berg-Anlage.' ],
			[ 'Ein Ansprechpartner', 'Vom Erstkontakt bis nach dem Event.' ],
			[ 'Eine Rechnung',       'Sauber abgerechnet, BGM-konform wenn nötig.' ],
			[ 'Antwort < 24 h',      'Werktags innerhalb eines Arbeitstags.' ],
		];
		foreach ( $trust as $t ) : ?>
			<div class="trust-cell">
				<div class="trust-t"><?php echo esc_html( $t[0] ); ?></div>
				<div class="trust-b"><?php echo esc_html( $t[1] ); ?></div>
			</div>
		<?php endforeach; ?>
	</div>
</div>

<?php /* ══════════════ FAQ ══════════════ */ ?>
<section class="mk-section faq-section" aria-label="FAQ">
	<div class="faq-shell">
		<div class="faq-aside">
			<div class="mk-eyebrow">Häufige Fragen</div>
			<h2 class="mk-h2" style="margin-top:8px">Was Firmen vor der Buchung wissen wollen.</h2>
			<p class="mk-sub" style="margin-top:16px">Die häufigsten Fragen unserer Kunden — vor allem von HR und Office Management. Was hier nicht steht: einfach kurz schreiben.</p>
			<div class="faq-cta">
				<a class="fg-btn-ghost" href="<?php echo esc_url( $kontakt_url ); ?>">
					Etwas anderes fragen
					<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17L17 7M9 7h8v8"/></svg>
				</a>
			</div>
		</div>
		<ul class="faq-list">
			<?php
			$faqs = [
				[
					'q' => 'Müssen meine Mitarbeitenden Golf spielen können?',
					'a' => 'Nein. Unsere Schnupperkurse und Team-Building-Formate sind komplett für Einsteigende konzipiert. Es gibt einen PGA-Coach vor Ort, alle Schläger werden gestellt und niemand wird mit einem 18-Loch-Turnier konfrontiert, wenn er noch nie einen Schläger gehalten hat.',
				],
				[
					'q' => 'Wie kurzfristig können wir buchen?',
					'a' => 'Beliebte Termine im Mai–September gehen meist 4–6 Wochen im Voraus weg. Im Frühjahr und Herbst gibt es oft noch Slots innerhalb von 1–2 Wochen. Trag uns gern unverbindlich ein — wir prüfen, was kurzfristig möglich ist.',
				],
				[
					'q' => 'Was passiert bei schlechtem Wetter?',
					'a' => 'Wir buchen immer mit Indoor-Backup an Plätzen, die das anbieten — z. B. überdachte Range oder Indoor-Simulatoren. Für reine Outdoor-Formate gibt es großzügige Stornoregeln 24 Stunden vor Termin.',
				],
				[
					'q' => 'Gibt es eine Mindestgruppengröße?',
					'a' => 'Je nach Format ab 4 Personen (Coaching, Schnupperkurs) bis 24 Personen (Firmenturnier mit Shotgun-Start). Für kleinere oder größere Gruppen planen wir individuell.',
				],
				[
					'q' => 'Wie wird abgerechnet?',
					'a' => 'Eine Sammelrechnung von Firmengolf, mit allen Posten ausgewiesen — Greenfees, Coaching, Catering, Trophäen. Das macht es für HR und Buchhaltung einfach.',
				],
				[
					'q' => 'Können wir das Event als Gesundheitsmaßnahme abrechnen?',
					'a' => 'Ja — unsere Gesundheitstage und Coaching-Formate sind BGM-konform (§ 3 Nr. 34 EStG) abrechenbar. Wir stellen die nötigen Belege aus.',
				],
			];
			foreach ( $faqs as $i => $faq ) : ?>
				<li class="faq-item<?php echo $i === 0 ? ' open' : ''; ?>" id="faq-<?php echo (int) $i; ?>">
					<button class="faq-q" onclick="(function(b){var it=b.closest('.faq-item');var o=it.classList.toggle('open');b.setAttribute('aria-expanded',o);it.querySelector('.faq-toggle').textContent=o?'–':'+';})(this)" aria-expanded="<?php echo $i === 0 ? 'true' : 'false'; ?>">
						<span><?php echo esc_html( $faq['q'] ); ?></span>
						<span class="faq-toggle" aria-hidden="true"><?php echo $i === 0 ? '–' : '+'; ?></span>
					</button>
					<div class="faq-a"><?php echo esc_html( $faq['a'] ); ?></div>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>

<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>

<script>
(function () {
  'use strict';

  /* ── Generic dropdown factory ── */
  function initDropdown(cellId, panelId, inputId, displayId) {
    var cell    = document.getElementById(cellId);
    var panel   = document.getElementById(panelId);
    var input   = document.getElementById(inputId);
    var display = document.getElementById(displayId);
    if (!cell || !panel) return;

    function open()  { panel.classList.add('is-open');    cell.setAttribute('aria-expanded', 'true'); }
    function close() { panel.classList.remove('is-open'); cell.setAttribute('aria-expanded', 'false'); }

    cell.addEventListener('click', function (e) {
      if (panel.contains(e.target)) return;
      panel.classList.contains('is-open') ? close() : open();
    });
    cell.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); panel.hidden ? open() : close(); }
      if (e.key === 'Escape') close();
    });

    panel.querySelectorAll('.fg-search-panel-opt').forEach(function (opt) {
      opt.addEventListener('click', function (e) {
        e.stopPropagation();
        var val   = opt.dataset.value;
        var label = opt.dataset.label;
        if (input)   input.value = val;
        if (display) display.textContent = label;
        panel.querySelectorAll('.fg-search-panel-opt').forEach(function (o) {
          o.classList.toggle('is-selected', o.dataset.value === val);
          o.setAttribute('aria-selected', String(o.dataset.value === val));
        });
        close();
      });
    });

    document.addEventListener('click', function (e) {
      if (!cell.contains(e.target)) close();
    });
  }

  initDropdown('fg-format-cell', 'fg-format-panel', 'fg-format-val', 'fg-format-text');

  /* ── Location picker: Wo? (Ort/PLZ-Autocomplete + Standort + Umkreis) ── */
  initLocationPicker();

  /* ── Pax +/− controls ── */
  var paxDisplay = document.getElementById('fg-pax-display');
  var paxVal     = document.getElementById('fg-pax-val');
  var decBtn     = document.querySelector('.fg-pax-btn[data-fn="dec"]');
  var incBtn     = document.querySelector('.fg-pax-btn[data-fn="inc"]');

  function updatePax(next) {
    next = Math.max(1, next);
    paxVal.value           = next;
    paxDisplay.textContent = next + ' Pers.';
    if (decBtn) decBtn.disabled = next <= 1;
  }

  if (incBtn) incBtn.addEventListener('click', function () { updatePax((parseInt(paxVal.value) || 10) + 1); });
  if (decBtn) decBtn.addEventListener('click', function () { updatePax((parseInt(paxVal.value) || 10) - 1); });

  /* ── Custom sort dropdown ── */
  var sortDd = document.getElementById('fg-sort-dd');
  if (sortDd) {
    var sortTrigger = document.getElementById('fg-sort-trigger');
    var sortMenu = document.getElementById('fg-sort-menu');
    sortTrigger.addEventListener('click', function (e) {
      e.stopPropagation();
      var open = sortMenu.classList.toggle('open');
      sortTrigger.classList.toggle('open', open);
      sortTrigger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('click', function (e) {
      if (!sortDd.contains(e.target)) { sortMenu.classList.remove('open'); sortTrigger.classList.remove('open'); sortTrigger.setAttribute('aria-expanded', 'false'); }
    });
  }

  /* ── Card share (Teilen) buttons ── */
  document.querySelectorAll('.fg-event-heart').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault(); e.stopPropagation();
      var url = btn.getAttribute('data-share-url') || window.location.href;
      var title = btn.getAttribute('data-share-title') || document.title;
      if (navigator.share) { navigator.share({ title: title, url: url }).catch(function () {}); return; }
      if (navigator.clipboard) { navigator.clipboard.writeText(url).then(function () { btn.classList.add('copied'); setTimeout(function () { btn.classList.remove('copied'); }, 1200); }); }
      else { window.prompt('Link kopieren:', url); }
    });
  });

  /* ── Location picker implementation ── */
  function initLocationPicker() {
    var cell = document.getElementById('fg-loc-cell');
    var panel = document.getElementById('fg-loc-panel');
    if (!cell || !panel) return;
    var input = document.getElementById('fg-loc-input');
    var suggest = document.getElementById('fg-loc-suggest');
    var gps = document.getElementById('fg-loc-gps');
    var latEl = document.getElementById('fg-lat');
    var lngEl = document.getElementById('fg-lng');
    var radEl = document.getElementById('fg-radius');
    var locEl = document.getElementById('fg-loc-val');
    var textEl = document.getElementById('fg-loc-text');
    var display = document.getElementById('fg-loc-display');
    var form = cell.closest('form');
    var ajax = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

    function open() { panel.classList.add('is-open'); cell.setAttribute('aria-expanded', 'true'); setTimeout(function () { input && input.focus(); }, 30); }
    function close() { panel.classList.remove('is-open'); cell.setAttribute('aria-expanded', 'false'); }

    cell.addEventListener('click', function (e) {
      if (panel.contains(e.target)) return;
      panel.classList.contains('is-open') ? close() : open();
    });
    document.addEventListener('click', function (e) { if (!cell.contains(e.target)) close(); });
    cell.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });

    function submitNow() { if (latEl.value && lngEl.value && radEl.value && form) form.submit(); }
    function setLocation(lat, lng, label) {
      latEl.value = lat; lngEl.value = lng; locEl.value = label;
      if (textEl) textEl.textContent = label;
      if (display) display.classList.remove('fg-muted');
      submitNow();
    }

    var t = null;
    if (input) input.addEventListener('input', function () {
      var q = input.value.trim();
      clearTimeout(t);
      if (q.length < 2) { suggest.innerHTML = ''; return; }
      t = setTimeout(function () {
        fetch(ajax + '?action=fge_geo_suggest&q=' + encodeURIComponent(q))
          .then(function (r) { return r.json(); })
          .then(function (res) {
            suggest.innerHTML = '';
            if (!res || !res.success) return;
            res.data.forEach(function (s) {
              var b = document.createElement('button');
              b.type = 'button';
              b.className = 'fg-loc-opt';
              b.textContent = s.label;
              b.addEventListener('click', function () { suggest.innerHTML = ''; input.value = s.label; setLocation(s.lat, s.lng, s.label); });
              suggest.appendChild(b);
            });
          })
          .catch(function () { suggest.innerHTML = ''; });
      }, 220);
    });

    if (gps) gps.addEventListener('click', function () {
      if (!navigator.geolocation) { alert('Standort wird vom Browser nicht unterstützt.'); return; }
      gps.disabled = true; gps.classList.add('is-loading');
      navigator.geolocation.getCurrentPosition(function (pos) {
        gps.disabled = false; gps.classList.remove('is-loading');
        setLocation(pos.coords.latitude.toFixed(5), pos.coords.longitude.toFixed(5), 'Mein Standort');
      }, function () {
        gps.disabled = false; gps.classList.remove('is-loading');
        alert('Standort konnte nicht ermittelt werden. Bitte Freigabe erlauben oder Ort eingeben.');
      }, { enableHighAccuracy: false, timeout: 8000 });
    });

    panel.querySelectorAll('.fg-loc-rb').forEach(function (btn) {
      btn.addEventListener('click', function () {
        panel.querySelectorAll('.fg-loc-rb').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        radEl.value = btn.getAttribute('data-r');
        if (latEl.value && lngEl.value) submitNow();
      });
    });
  }
}());
</script>

<?php get_footer(); ?>
