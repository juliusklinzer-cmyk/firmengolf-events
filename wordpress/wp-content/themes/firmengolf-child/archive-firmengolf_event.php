<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// SEO: Title + Meta + OpenGraph für die Event-Übersicht (vor get_header, damit es greift).
$arch_title = 'Firmenevents auf dem Golfplatz: alle Formate | Firmengolf';
$arch_desc  = 'Alle Firmenevent-Formate auf einen Blick: Teamevents, Firmenturniere, Schnupperkurse und Incentives auf Golfplätzen in ganz Deutschland. Nach Region und Gruppengröße filtern und anfragen.';
add_filter( 'pre_get_document_title', function () use ( $arch_title ) { return $arch_title; } );
add_action( 'wp_head', function () use ( $arch_title, $arch_desc ) {
	$GLOBALS['fge_seo_meta_done'] = true;
	echo '<meta name="description" content="' . esc_attr( $arch_desc ) . '">' . "\n";
	echo '<link rel="canonical" href="' . esc_url( get_post_type_archive_link( 'firmengolf_event' ) ) . '">' . "\n";
	echo '<meta property="og:type" content="website">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $arch_title ) . '">' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $arch_desc ) . '">' . "\n";
	echo '<meta property="og:url" content="' . esc_url( get_post_type_archive_link( 'firmengolf_event' ) ) . '">' . "\n";
	$arch_og_img = function_exists( 'fge_default_og_image_url' ) ? fge_default_og_image_url() : '';
	if ( $arch_og_img ) { echo '<meta property="og:image" content="' . esc_url( $arch_og_img ) . '">' . "\n"; }
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
} );

get_header();

// ── URL / page setup ────────────────────────────────────────────────────────
$archive_url = (string) get_post_type_archive_link( 'firmengolf_event' );
$ind_page    = get_page_by_path( 'individuelle-events' );
$ind_url     = $ind_page ? (string) get_permalink( $ind_page->ID ) : home_url( '/individuelle-events/' );
$kontakt_url = ( $kp = get_page_by_path( 'kontakt' ) ) ? (string) get_permalink( $kp->ID ) : home_url( '/kontakt/' );

// ── Sanitize GET params ──────────────────────────────────────────────────────
$active_format = sanitize_key( $_GET['format'] ?? 'all' );        // phpcs:ignore WordPress.Security.NonceVerification
$active_region = sanitize_text_field( $_GET['region'] ?? '' );    // phpcs:ignore WordPress.Security.NonceVerification
$active_pax    = max( 0, (int) ( $_GET['pax'] ?? 0 ) );           // phpcs:ignore WordPress.Security.NonceVerification — default 0 = kein Filter (alle anzeigen)
$active_sort   = sanitize_key( $_GET['sort'] ?? 'curated' );      // phpcs:ignore WordPress.Security.NonceVerification

// ── Geo (Umkreissuche) params ────────────────────────────────────────────────
$active_lat    = isset( $_GET['lat'] ) ? (float) $_GET['lat'] : 0.0;              // phpcs:ignore WordPress.Security.NonceVerification
$active_lng    = isset( $_GET['lng'] ) ? (float) $_GET['lng'] : 0.0;              // phpcs:ignore WordPress.Security.NonceVerification
$active_radius = isset( $_GET['radius'] ) ? max( 0, (int) $_GET['radius'] ) : 0;  // phpcs:ignore WordPress.Security.NonceVerification
$active_loc    = sanitize_text_field( wp_unslash( $_GET['loc'] ?? '' ) );         // phpcs:ignore WordPress.Security.NonceVerification
$geo_active    = ( $active_lat && $active_lng && $active_radius > 0 && function_exists( 'fge_geo_distance' ) );

// ── Format list (canonical — single source: event-formats.php) ───────────────
$formats = array_merge( [ 'all' => 'Alle Typen' ], fge_event_formats_in_use() );

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
// Öffentlich sichtbare Angebote (freigegeben + zuvor freigegeben mit anstehender Änderung).
// Pausierte Plätze → Nachfilter unten via fge_event_is_public().
$public_statuses = function_exists( 'fge_public_event_statuses' ) ? fge_public_event_statuses() : [ 'freigegeben' ];
$meta_query = [
	[ 'key' => '_fge_event_status', 'value' => $public_statuses, 'compare' => 'IN' ],
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
			usort( $event_items, static fn( $a, $b ) => (float) get_post_meta( $a['id'], '_fge_price_amount', true ) <=> (float) get_post_meta( $b['id'], '_fge_price_amount', true ) );
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
			$query_args['meta_key'] = '_fge_price_amount';
			$query_args['orderby']  = 'meta_value_num';
			$query_args['order']    = 'ASC';
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

<?php
// Mobile-Such-Pille für die Nav-Bar (öffnet das Sheet weiter unten).
$pill_bits = [];
if ( $active_format !== 'all' && isset( $formats[ $active_format ] ) ) { $pill_bits[] = $formats[ $active_format ]; }
if ( $active_loc !== '' ) { $pill_bits[] = $active_loc; }
if ( $active_pax > 0 ) { $pill_bits[] = $active_pax . ' Pers.'; }
$pill_summary = $pill_bits ? implode( ' · ', $pill_bits ) : 'Jetzt suchen';
$mbar_action  = '<button class="ev-msearch" type="button" id="fge-ev-pill" aria-label="Suche öffnen">'
	. '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>'
	. '<span class="ev-msearch-t ' . ( $pill_bits ? '' : 'muted' ) . '">' . esc_html( $pill_summary ) . '</span></button>';
get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => 'events', 'mbar_action' => $mbar_action ] );

// ── Mobiles Such-Sheet (≤768px) – gleiche GET-Parameter wie der Desktop-Filter ──
$group_bands = [
	[ 'pax' => 0,   'label' => 'Jede Größe' ],
	[ 'pax' => 12,  'label' => 'Bis 12' ],
	[ 'pax' => 30,  'label' => '12–30' ],
	[ 'pax' => 60,  'label' => '30–60' ],
	[ 'pax' => 100, 'label' => '60+' ],
];
?>
<form class="ev-sheet-scrim" id="fge-ev-sheet" method="get" action="<?php echo esc_url( $archive_url ); ?>">
	<div class="ev-sheet" role="dialog" aria-modal="true" aria-label="Event finden">
		<div class="ev-sheet-top">
			<button class="ev-sheet-close" type="button" aria-label="Schließen">
				<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
			</button>
			<span class="ev-sheet-title">Event finden</span>
		</div>
		<div class="ev-sheet-body">
			<section class="ev-sheet-card">
				<div class="ev-sheet-q">Was möchtet ihr machen?</div>
				<div class="ev-sheet-chips" id="fge-ev-fmt">
					<?php foreach ( $formats as $slug => $label ) : ?>
						<button type="button" class="ev-sheet-chip <?php echo $active_format === $slug ? 'on' : ''; ?>" data-fmt="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></button>
					<?php endforeach; ?>
				</div>
			</section>
			<section class="ev-sheet-card">
				<div class="ev-sheet-q">Wo seid ihr?</div>
				<div class="ev-loc-input ev-loc-input-sheet">
					<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
					<input type="text" id="fge-ev-loc" placeholder="Ort oder PLZ" autocomplete="off">
				</div>
				<button type="button" class="ev-loc-gps ev-loc-gps-sheet" id="fge-ev-gps">
					<span class="ev-loc-gps-ic"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/></svg></span>
					Meinen Standort
				</button>
				<div class="ev-sheet-chips" id="fge-ev-loc-sugg" style="margin-top:10px;"></div>
				<div class="ev-sheet-picked" id="fge-ev-loc-picked" style="<?php echo $active_loc !== '' ? '' : 'display:none;'; ?>">Gewählt: <strong id="fge-ev-loc-pickedlabel"><?php echo esc_html( $active_loc ); ?></strong> <button type="button" id="fge-ev-loc-clear">ändern</button></div>
			</section>
			<section class="ev-sheet-card">
				<div class="ev-sheet-q">Wie groß ist die Gruppe?</div>
				<div class="ev-sheet-chips" id="fge-ev-grp">
					<?php foreach ( $group_bands as $b ) :
						$on = ( 0 === $b['pax'] ) ? ( $active_pax <= 0 ) : ( $active_pax === $b['pax'] ); ?>
						<button type="button" class="ev-sheet-chip <?php echo $on ? 'on' : ''; ?>" data-pax="<?php echo (int) $b['pax']; ?>"><?php echo esc_html( $b['label'] ); ?></button>
					<?php endforeach; ?>
				</div>
			</section>
		</div>
		<div class="ev-sheet-foot">
			<button class="ev-sheet-clear" type="button" id="fge-ev-clear">Alle löschen</button>
			<button class="ev-sheet-go" type="submit">
				<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
				Suche
			</button>
		</div>
	</div>
	<input type="hidden" name="format" id="fge-ev-h-format" value="<?php echo esc_attr( $active_format ); ?>">
	<input type="hidden" name="lat"    id="fge-ev-h-lat"    value="<?php echo esc_attr( $active_lat ?: '' ); ?>">
	<input type="hidden" name="lng"    id="fge-ev-h-lng"    value="<?php echo esc_attr( $active_lng ?: '' ); ?>">
	<input type="hidden" name="radius" id="fge-ev-h-radius" value="<?php echo esc_attr( (string) ( $active_radius ?: 50 ) ); ?>">
	<input type="hidden" name="loc"    id="fge-ev-h-loc"    value="<?php echo esc_attr( $active_loc ); ?>">
	<input type="hidden" name="pax"    id="fge-ev-h-pax"    value="<?php echo esc_attr( (string) $active_pax ); ?>">
</form>
<script>
(function () {
	var pill  = document.getElementById('fge-ev-pill');
	var sheet = document.getElementById('fge-ev-sheet');
	if (!sheet) { return; }
	var openS  = function () { sheet.classList.add('is-open'); document.body.style.overflow = 'hidden'; };
	var closeS = function () { sheet.classList.remove('is-open'); document.body.style.overflow = ''; };
	if (pill) { pill.addEventListener('click', openS); }
	sheet.addEventListener('click', function (e) { if (e.target === sheet) { closeS(); } });
	sheet.querySelector('.ev-sheet-close').addEventListener('click', closeS);
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && sheet.classList.contains('is-open')) { closeS(); } });

	function singleSelect(wrapId, attr, hidden) {
		var wrap = document.getElementById(wrapId);
		wrap.querySelectorAll('.ev-sheet-chip').forEach(function (c) {
			c.addEventListener('click', function () {
				wrap.querySelectorAll('.ev-sheet-chip').forEach(function (x) { x.classList.remove('on'); });
				c.classList.add('on');
				hidden.value = c.getAttribute(attr);
			});
		});
	}
	singleSelect('fge-ev-fmt', 'data-fmt', document.getElementById('fge-ev-h-format'));
	singleSelect('fge-ev-grp', 'data-pax', document.getElementById('fge-ev-h-pax'));

	var ajax    = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
	var locIn   = document.getElementById('fge-ev-loc');
	var sugg    = document.getElementById('fge-ev-loc-sugg');
	var picked  = document.getElementById('fge-ev-loc-picked');
	var pickedL = document.getElementById('fge-ev-loc-pickedlabel');
	var hLat = document.getElementById('fge-ev-h-lat'), hLng = document.getElementById('fge-ev-h-lng'), hLoc = document.getElementById('fge-ev-h-loc');
	function setLoc(label, lat, lng) {
		hLoc.value = label;
		if (lat !== null && lat !== undefined) { hLat.value = lat; hLng.value = lng; }
		pickedL.textContent = label; picked.style.display = ''; sugg.innerHTML = ''; locIn.value = '';
	}
	var t = null;
	if (locIn) locIn.addEventListener('input', function () {
		var q = locIn.value.trim(); clearTimeout(t);
		if (q.length < 2) { sugg.innerHTML = ''; return; }
		t = setTimeout(function () {
			fetch(ajax + '?action=fge_geo_suggest&q=' + encodeURIComponent(q))
				.then(function (r) { return r.json(); })
				.then(function (res) {
					sugg.innerHTML = '';
					if (!res || !res.success) { return; }
					res.data.forEach(function (s) {
						var b = document.createElement('button');
						b.type = 'button'; b.className = 'ev-sheet-chip'; b.textContent = s.label;
						b.addEventListener('click', function () { setLoc(s.label, s.lat, s.lng); });
						sugg.appendChild(b);
					});
				}).catch(function () { sugg.innerHTML = ''; });
		}, 220);
	});
	var gps = document.getElementById('fge-ev-gps');
	if (gps) gps.addEventListener('click', function () {
		if (!navigator.geolocation) { return; }
		var orig = gps.innerHTML; gps.disabled = true;
		navigator.geolocation.getCurrentPosition(function (pos) {
			gps.disabled = false; gps.innerHTML = orig;
			setLoc('Mein Standort', pos.coords.latitude.toFixed(5), pos.coords.longitude.toFixed(5));
		}, function () { gps.disabled = false; gps.innerHTML = orig; }, { timeout: 8000 });
	});
	var clearLoc = document.getElementById('fge-ev-loc-clear');
	if (clearLoc) clearLoc.addEventListener('click', function () { hLat.value = ''; hLng.value = ''; hLoc.value = ''; picked.style.display = 'none'; });
	var clearAll = document.getElementById('fge-ev-clear');
	if (clearAll) clearAll.addEventListener('click', function () { window.location.href = '<?php echo esc_js( $archive_url ); ?>'; });
})();
</script>

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
				        <?php echo $active_pax <= 0 ? 'disabled' : ''; ?>>−</button>
				<span class="fg-pax-num" id="fg-pax-display">
					<?php echo $active_pax > 0 ? esc_html( $active_pax . ' Pers.' ) : 'Alle'; ?>
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
<?php
$has_filters = ( $active_format !== 'all' ) || $geo_active || ( $active_pax > 0 );
if ( ! $has_filters ) :
	// Mobile (≤768px via CSS): nach Kategorie gruppierte, wischbare Reihen. Desktop unberührt.
	$legacy_map = function_exists( 'fge_get_event_format_legacy_map' ) ? fge_get_event_format_legacy_map() : [];
	$cat_key = static function ( string $type ) use ( $formats, $legacy_map ) {
		if ( isset( $formats[ $type ] ) ) { return $type; }
		if ( isset( $legacy_map[ $type ], $formats[ $legacy_map[ $type ] ] ) ) { return $legacy_map[ $type ]; }
		return null;
	};
	$browse_all = get_posts( [
		'post_type'   => 'firmengolf_event',
		'post_status' => 'publish',
		'numberposts' => -1,
		'meta_query'  => [ [ 'key' => '_fge_event_status', 'value' => fge_public_event_statuses(), 'compare' => 'IN' ] ],
	] );
	$by_cat = [];
	foreach ( $browse_all as $bp ) {
		if ( function_exists( 'fge_event_is_public' ) && ! fge_event_is_public( $bp->ID ) ) { continue; }
		$k = $cat_key( (string) get_post_meta( $bp->ID, '_fge_event_type', true ) );
		if ( $k ) { $by_cat[ $k ][] = (int) $bp->ID; }
	}
	$ind_link = ( $p = get_page_by_path( 'individuelle-events' ) ) ? get_permalink( $p->ID ) : home_url( '/individuelle-events/' );
	?>
	<div class="ev-catbrowse" aria-label="Events nach Kategorie">
		<?php foreach ( $formats as $fkey => $flabel ) :
			if ( 'all' === $fkey || empty( $by_cat[ $fkey ] ) ) { continue; }
			$ids = array_slice( $by_cat[ $fkey ], 0, 5 );
			$all_url = esc_url( add_query_arg( 'format', $fkey, $archive_url ) ); ?>
			<section class="ev-catsec">
				<div class="ev-catsec-head">
					<h3 class="ev-catsec-h"><?php echo esc_html( $flabel ); ?> <span class="ev-catsec-c"><?php echo (int) count( $by_cat[ $fkey ] ); ?></span></h3>
					<a class="ev-catsec-all" href="<?php echo $all_url; ?>">Alle ansehen →</a>
				</div>
				<div class="ev-catrow">
					<?php foreach ( $ids as $id ) { get_template_part( 'template-parts/fge-event-card', null, [ 'id' => $id, 'dist' => null ] ); } ?>
					<a class="ev-allcard" href="<?php echo $all_url; ?>">
						<span class="ev-allcard-ic"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg></span>
						<span class="ev-allcard-t">Alle <?php echo esc_html( $flabel ); ?></span>
						<span class="ev-allcard-go">anzeigen →</span>
					</a>
				</div>
			</section>
		<?php endforeach; ?>
		<div class="ev-inline-cta">
			<div>
				<div class="mk-eyebrow">Kein passender Veranstaltungstyp dabei?</div>
				<h3 class="ev-inline-h">Wir planen dein Event nach deinen Ansprüchen.</h3>
			</div>
			<a class="fg-btn fg-btn-brand" href="<?php echo esc_url( $ind_link ); ?>">Individuelles Event anfragen</a>
		</div>
	</div>
<?php endif; ?>
<section class="fg-grid-section<?php echo ! $has_filters ? ' fge-hide-mobile' : ''; ?>" aria-label="Eventangebote">

	<?php /* Active filter pills */ ?>
	<?php $has_filters = ( $active_format !== 'all' ) || $geo_active || ( $active_pax > 0 ); ?>
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
			<?php if ( $active_pax > 0 ) : ?>
				<a class="fg-fpill" href="<?php echo esc_url( $chip_url( [ 'pax' => '' ] ) ); ?>">
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
			<?php foreach ( $event_items as $item ) {
				get_template_part( 'template-parts/fge-event-card', null, [ 'id' => (int) $item['id'], 'dist' => $item['dist'] ] );
			} ?>
		</div>

		<?php
		// Pagination nur im Nicht-Geo-Pfad (Umkreissuche listet alle Treffer ungeteilt).
		if ( isset( $events_query ) && (int) $events_query->max_num_pages > 1 ) :
			$pg_base = remove_query_arg( 'paged', add_query_arg( null, null ) );
			$pg_links = paginate_links( [
				'base'      => add_query_arg( 'paged', '%#%', $pg_base ),
				'format'    => '',
				'current'   => max( 1, (int) ( $_GET['paged'] ?? 1 ) ),  // phpcs:ignore WordPress.Security.NonceVerification
				'total'     => (int) $events_query->max_num_pages,
				'mid_size'  => 1,
				'prev_text' => '‹ Zurück',
				'next_text' => 'Weiter ›',
			] );
			if ( $pg_links ) {
				echo '<nav class="fg-pagination" aria-label="Seiten" style="display:flex;flex-wrap:wrap;gap:8px;justify-content:center;margin-top:32px;">' . $pg_links . '</nav>';  // phpcs:ignore WordPress.Security.EscapeOutput
			}
		endif;
		?>

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
			[ '721 Golfplätze deutschlandweit', 'Events auf Golfplätzen in ganz Deutschland – passt einer nicht, nehmen wir den nächsten.' ],
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
					'a' => 'Nein. Unsere Schnupperkurse und Team-Building-Formate sind komplett für Einsteigende konzipiert. Es gibt einen Golflehrer vor Ort, alle Schläger werden gestellt und niemand wird mit einem 18-Loch-Turnier konfrontiert, wenn er noch nie einen Schläger gehalten hat.',
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
    next = Math.max(0, next); // 0 = Alle (kein Filter)
    paxVal.value           = next;
    paxDisplay.textContent = next > 0 ? next + ' Pers.' : 'Alle';
    if (decBtn) decBtn.disabled = next <= 0;
  }

  if (incBtn) incBtn.addEventListener('click', function () { updatePax((parseInt(paxVal.value) || 0) + 1); });
  if (decBtn) decBtn.addEventListener('click', function () { updatePax((parseInt(paxVal.value) || 0) - 1); });

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
