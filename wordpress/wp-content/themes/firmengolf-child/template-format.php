<?php
/**
 * SEO format landing — rendered for /firmenevent/<format>/ via format-landing.php.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$slug    = (string) get_query_var( 'fge_format' );
$formats = function_exists( 'fge_get_event_format_pages' ) ? fge_get_event_format_pages() : [];
$format  = $formats[ $slug ] ?? null;
if ( ! $format ) {
	wp_safe_redirect( home_url( '/' ) );
	exit;
}

$f_name    = $format['name'];
$canonical = home_url( '/firmenevent/' . $slug . '/' );
$seo_title = $format['h1'] . ' | Firmengolf';
$seo_desc  = $format['lead'];
$events_url = (string) get_post_type_archive_link( 'firmengolf_event' );
$ind_url    = ( $p = get_page_by_path( 'individuelle-events' ) ) ? (string) get_permalink( $p->ID ) : home_url( '/individuelle-events/' );
// CTAs springen direkt in den Anfrage-Wizard (Deep-Link, s. fge-individual.js):
// quick = 30-Sekunden-Schnellanfrage, mit dem Format der Seite als vorgewähltem Anlass.
$fmt_occ       = function_exists( 'fge_format_occasion' ) ? fge_format_occasion( $slug ) : '';
$anfrage_args  = [ 'anfrage' => 'quick' ];
if ( '' !== $fmt_occ ) {
	$anfrage_args['anlass'] = $fmt_occ;
}
$anfrage_quick = add_query_arg( $anfrage_args, $ind_url );
$faqs       = $format['faqs'] ?? [];

// Passende Events: 2 volle 4er-Reihen (Grid wie auf der Eventliste), Fallback verhindert leere Seite.
$is_xmas       = ! empty( $format['xmas'] );
$is_summer     = ! empty( $format['summer'] );
$format_events = ( ! $is_xmas && ! $is_summer && function_exists( 'fge_format_events' ) ) ? fge_format_events( $format, 8 ) : [];
if ( $is_xmas && function_exists( 'fge_simulatoren_map_enqueue' ) ) {
	fge_simulatoren_map_enqueue();
}
if ( $is_summer && function_exists( 'fge_golfplatz_map_all_enqueue' ) ) {
	fge_golfplatz_map_all_enqueue();
}
if ( ! $is_xmas && ! $is_summer && empty( $format_events ) && function_exists( 'fge_get_featured_events' ) ) {
	$format_events = fge_get_featured_events( 8 );
}

// Icon-Set für die „Gründe"-Kacheln.
if ( ! function_exists( 'fge_format_ico' ) ) {
	function fge_format_ico( string $name ): string {
		$p = [
			'clock'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
			'users'     => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
			'mountain'  => '<path d="M3 20l6.5-11 4 6 2-3L21 20z"/>',
			'flag'      => '<path d="M5 21V4l9 2.5L5 9"/><circle cx="17" cy="17" r="3"/>',
			'castle'    => '<path d="M4 21V8l2 1V5l2 1V4l2 1V4l2-1v2l2-1v2l2-1v4l2-1v13z"/><path d="M10 21v-4h4v4"/>',
			'gift'      => '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M5 12v9h14v-9"/><path d="M12 8S10.5 3 8 4.5 9.5 8 12 8zM12 8s1.5-5 4-3.5S14.5 8 12 8z"/>',
			'leaf'      => '<path d="M11 20A7 7 0 0 1 4 13C4 8 9 4 20 4c0 9-4 16-9 16z"/><path d="M4 20c4-4 7-6 11-7"/>',
			'trophy'    => '<path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0z"/><path d="M7 6H4v2a3 3 0 0 0 3 3M17 6h3v2a3 3 0 0 1-3 3"/>',
			'handshake' => '<path d="m11 17 2 2a1 1 0 0 0 3-3"/><path d="m14 14 2.5 2.5a1 1 0 0 0 3-3l-3.9-3.9a2 2 0 0 0-2.8 0l-1.6 1.6a1 1 0 0 1-2.8-2.8l2.9-2.9"/><path d="m21 3-3 3M3 13l4 4"/>',
			'sun'       => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
		];
		$d = $p[ $name ] ?? $p['flag'];
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
	}
}

// ── SEO head injection ────────────────────────────────────────────────────────
add_filter( 'pre_get_document_title', static fn() => $seo_title );
add_action( 'wp_head', static function () use ( $seo_title, $seo_desc, $canonical, $f_name, $faqs ) {
	$GLOBALS['fge_seo_meta_done'] = true;
	echo '<meta name="description" content="' . esc_attr( $seo_desc ) . '">' . "\n";
	echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
	echo '<meta property="og:type" content="website">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $seo_title ) . '">' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $seo_desc ) . '">' . "\n";
	echo '<meta property="og:url" content="' . esc_url( $canonical ) . '">' . "\n";
	$og_img = function_exists( 'fge_default_og_image_url' ) ? fge_default_og_image_url() : '';
	if ( $og_img ) { echo '<meta property="og:image" content="' . esc_url( $og_img ) . '">' . "\n"; }

	$graph = [
		[
			'@context'    => 'https://schema.org',
			'@type'       => 'Service',
			'serviceType' => $f_name,
			'name'        => 'Firmengolf, ' . $f_name,
			'areaServed'  => [ '@type' => 'Country', 'name' => 'Deutschland' ],
			'provider'    => [ '@type' => 'Organization', 'name' => 'Firmengolf', 'url' => home_url( '/' ) ],
			'url'         => $canonical,
		],
		[
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => array_map( static function ( $f ) {
				return [
					'@type'          => 'Question',
					'name'           => $f['q'],
					'acceptedAnswer' => [ '@type' => 'Answer', 'text' => $f['a'] ],
				];
			}, $faqs ),
		],
		[
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => [
				[ '@type' => 'ListItem', 'position' => 1, 'name' => 'Firmengolf', 'item' => home_url( '/' ) ],
				[ '@type' => 'ListItem', 'position' => 2, 'name' => 'Firmenevents', 'item' => get_post_type_archive_link( 'firmengolf_event' ) ],
				[ '@type' => 'ListItem', 'position' => 3, 'name' => $f_name ],
			],
		],
	];
	echo '<script type="application/ld+json">' . wp_json_encode( $graph ) . '</script>' . "\n";
} );

get_header();
?>
<div class="fge-page fmt-lp" id="fge-main" role="main" tabindex="-1">

<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => 'events' ] ); ?>

<?php if ( $is_summer ) :
	// ── Sommerfest-Seite (Julius, 07.09.): Vorplanung 2027 im Aufbau der Weihnachts-
	// Seite: großer Hero, Sommerfest-Formate, Kurz-Anfrage, Golfplatz-Karte; danach
	// die Level-Sektion (für jedes Erfahrungslevel), FAQ, CTA.
	$sf_courses = function_exists( 'fge_verzeichnis_count' ) ? fge_verzeichnis_count() : 0;
	$sf_img     = static function ( string $slug, string $fallback ): string {
		$base = defined( 'FGE_DIR' ) ? FGE_DIR . 'assets/imagery/' : '';
		if ( '' !== $base && file_exists( $base . 'sommerfest/' . $slug . '.jpg' ) ) {
			return fge_get_placeholder_image_url( 'sommerfest/' . $slug . '.jpg' );
		}
		return fge_get_placeholder_image_url( $fallback );
	};
	$sf_formats = [
		[ 'slug' => 'firmenturnier',     't' => 'Firmenturnier',            'b' => 'Für die Golfer im Team: 9 oder 18 Löcher im Turniermodus, mit Live-Scoring.', 'img' => 'pool/turnier-abschlag-eventszene.jpg' ],
		[ 'slug' => 'kurzplatz-turnier', 't' => 'Kurzplatz-Turnier',        'b' => 'Für alle ohne Golferfahrung: kurze Bahnen, große Löcher, echter Wettbewerb.', 'img' => 'pool/platzreife-erste-schlaege-range.jpg' ],
		[ 'slug' => 'schnupperrunde',    't' => 'Schnupperrunde mit Pro',   'b' => 'Einführung mit dem Golflehrer, erste Schläge auf der Range, Schläger gestellt.', 'img' => 'pool/platzreife-golflehrer-erklaert.jpg' ],
		[ 'slug' => 'putting-challenge', 't' => 'Putting-Challenge',        'b' => 'Alle wieder zusammen: Team gegen Team auf dem Übungsgrün.', 'img' => 'pool/teamevent-putting.jpg' ],
		[ 'slug' => 'barbecue',          't' => 'Barbecue auf der Terrasse', 'b' => 'Grill, lange Tafel, Blick über den Platz, bis der Sundowner kommt.', 'img' => 'pool/pool-generiertes-grillfest.jpg' ],
		[ 'slug' => 'siegerehrung',      't' => 'Siegerehrung & Sundowner', 'b' => 'Preise für beide Gruppen, Anstoßen auf der Clubterrasse.', 'img' => 'pool/afterwork-anstossen.jpg' ],
	];
?>
<section class="mk-hero xmas-hero" aria-label="<?php echo esc_attr( $format['h1'] ); ?>">
	<div class="mk-hero-photo" style="background-image:url('<?php echo esc_url( fge_get_placeholder_image_url( $format['hero_img'] ) ); ?>'); --xmas-hero-m:url('<?php echo esc_url( fge_get_placeholder_image_url( 'pool/pool-hochformat-afterwork-sundowner-golf.jpg' ) ); ?>')">
		<div class="mk-hero-scrim" aria-hidden="true"></div>
		<div class="mk-hero-content">
			<span class="mk-hero-tag">Für 2027 vorplanen</span>
			<h1 class="mk-hero-title">Euer Sommerfest auf dem <strong>Golfplatz</strong></h1>
			<p class="mk-hero-sub"><?php echo esc_html( $format['lead'] ); ?></p>
			<div class="mk-hero-ctas">
				<a class="fg-btn-cta fg-btn-lg" href="#anfrage">Sommerfest anfragen <span class="fg-arrow"><?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?></span></a>
				<a class="fg-btn-ghost-light" href="#formate">Formate ansehen →</a>
			</div>
			<div class="cty-hero-facts">
				<span class="cty-hero-fact"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg><b>500+</b>&nbsp;Locations in ganz Deutschland</span>
				<span class="cty-hero-fact"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/></svg>Auch ohne Golferfahrung</span>
			</div>
		</div>
	</div>
</section>

<section class="mk-section playx cty-reveal" id="formate" aria-label="Sommerfest-Formate">
	<div class="mk-section-head">
		<h2 class="mk-h2">Unsere <em class="mk-italic">Sommerfest</em>-Formate.</h2>
		<p class="mk-sub">Turnier für die Könner, Kurzplatz und Schnupperrunde für alle anderen, am selben Tag auf derselben Anlage. Zur Putting-Challenge und zum Barbecue kommen alle wieder zusammen.</p>
	</div>
	<div class="iv-tiles playx-tiles">
		<?php foreach ( $sf_formats as $sf ) : ?>
		<article class="iv-tile playx-tile">
			<span class="iv-tile-img" style="background-image:url('<?php echo esc_url( $sf_img( $sf['slug'], $sf['img'] ) ); ?>')" role="img" aria-label="<?php echo esc_attr( $sf['t'] ); ?>"></span>
			<span class="iv-tile-scrim" aria-hidden="true"></span>
			<span class="iv-tile-label">
				<span>
					<span class="iv-tile-t"><?php echo esc_html( $sf['t'] ); ?></span>
					<span class="iv-tile-sub" style="display:block;"><?php echo esc_html( $sf['b'] ); ?></span>
				</span>
			</span>
		</article>
		<?php endforeach; ?>
	</div>
</section>

<?php get_template_part( 'template-parts/fge-xmas-request', null, [ 'id' => 'anfrage', 'variant' => 'sommerfest' ] ); ?>

<?php if ( function_exists( 'fge_gmaps_api_key' ) && fge_gmaps_api_key() !== '' ) : ?>
<section class="mk-section simx cty-reveal" id="golfplaetze" aria-label="Golfplätze in Deutschland">
	<div class="mk-section-head">
		<h2 class="mk-h2">Golfplätze in <em class="mk-italic">ganz Deutschland</em>.</h2>
		<p class="mk-sub"><?php echo esc_html( (string) $sf_courses ); ?> Anlagen aus dem DGV-Verzeichnis, blau markiert sind unsere Partnerplätze. Auf jeder davon planen wir Turnier, Kurzplatz-Runde und Barbecue am selben Tag, für Könner und Einsteiger.</p>
	</div>
	<div class="gpd-map simx-map" id="fge-city-map">
		<div class="gpd-map-consent">
			<p>Die Karte lädt erst nach deiner Einwilligung für Google&nbsp;Maps.</p>
			<button type="button" class="fg-btn-brand" onclick="if(window.klaro){window.klaro.show()}">Karte aktivieren</button>
		</div>
	</div>
	<div class="simx-legend" aria-label="Legende">
		<span class="simx-key"><i class="simx-dot simx-dot--partner"></i>Firmengolf-Partnerplatz</span>
		<span class="simx-key"><i class="simx-dot simx-dot--course"></i>Golfanlage, auf Anfrage</span>
	</div>
	<p class="simx-note">Ihr betreibt eine Golfanlage und wollt Sommerfeste über uns anbieten? <a href="<?php echo esc_url( add_query_arg( [ 'ob_step' => 1, 'ob_type' => 'course' ], home_url( '/partner-onboarding/' ) ) ); ?>">Als Golfplatz-Partner eintragen</a>.</p>
</section>
<?php endif; ?>

<section class="mk-section" aria-label="Über <?php echo esc_attr( $f_name ); ?>">
	<div class="mk-section-head">
		<h2 class="mk-h2"><?php echo esc_html( $f_name ); ?>, gemeinsam erleben.</h2>
		<p class="mk-sub" style="max-width:var(--width-prose);"><?php echo esc_html( $format['intro'] ); ?></p>
	</div>
</section>

<?php elseif ( $is_xmas ) :
	// ── Weihnachtsfeier-Seite (Julius, 07.09., Umbau): großer Hero, Angebote 4×2 mit
	// Filterleiste + Standort-Abfrage wie auf der Eventliste, Kurz-Anfrage, Simulator-
	// Karte, Spielformate. Facts, Stadt-Chips und Indoor-Kacheln entfallen hier.
	$xl_lat    = isset( $_GET['lat'] ) ? (float) $_GET['lat'] : 0.0;              // phpcs:ignore WordPress.Security.NonceVerification
	$xl_lng    = isset( $_GET['lng'] ) ? (float) $_GET['lng'] : 0.0;              // phpcs:ignore WordPress.Security.NonceVerification
	$xl_radius = isset( $_GET['radius'] ) ? max( 0, (int) $_GET['radius'] ) : 0;  // phpcs:ignore WordPress.Security.NonceVerification
	$xl_loc    = sanitize_text_field( wp_unslash( $_GET['loc'] ?? '' ) );         // phpcs:ignore WordPress.Security.NonceVerification
	$xl_pax    = isset( $_GET['pax'] ) ? max( 0, (int) $_GET['pax'] ) : 0;        // phpcs:ignore WordPress.Security.NonceVerification
	$xl_q      = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) );            // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! ( $xl_lat && $xl_lng ) && '' !== $xl_q ) {
		// PLZ oder Ort aus der Filterleiste (kein JS nötig): Koordinaten aus den Geo-Daten.
		$xl_c = null;
		if ( ctype_digit( $xl_q ) && function_exists( 'fge_geo_lookup_plz' ) ) {
			$xl_c = fge_geo_lookup_plz( $xl_q );
		} elseif ( function_exists( 'fge_geo_city_coords' ) ) {
			$xl_c = fge_geo_city_coords( $xl_q );
		}
		if ( $xl_c ) {
			$xl_lat    = (float) ( $xl_c[0] ?? $xl_c['lat'] ?? 0 );
			$xl_lng    = (float) ( $xl_c[1] ?? $xl_c['lng'] ?? 0 );
			$xl_radius = $xl_radius ?: 50;
			$xl_loc    = $xl_loc ?: ( ctype_digit( $xl_q ) && ! empty( $xl_c[2] ) ? (string) $xl_c[2] : $xl_q );
		} else {
			$xl_unknown = $xl_q; // Ort bleibt im Feld stehen, Hinweis darunter
		}
	}
	$xl_unknown = $xl_unknown ?? '';
	$xl_geo    = $xl_lat && $xl_lng && $xl_radius > 0 && function_exists( 'fge_geo_distance' ) && function_exists( 'fge_geo_event_coords' );
	$xl_items  = [];
	foreach ( ( function_exists( 'fge_format_events' ) ? fge_format_events( $format, 200 ) : [] ) as $xev ) {
		if ( $xl_pax > 0 && (int) get_post_meta( $xev->ID, '_fge_participants_max', true ) < $xl_pax ) {
			continue;
		}
		$xd = null;
		if ( $xl_geo ) {
			$xc = fge_geo_event_coords( (int) $xev->ID );
			$xd = $xc ? fge_geo_distance( $xl_lat, $xl_lng, $xc[0], $xc[1] ) : 99999.0;
		}
		$xl_items[] = [ 'id' => (int) $xev->ID, 'dist' => $xd ];
	}
	$xl_fallback = false;
	if ( $xl_geo ) {
		usort( $xl_items, static fn( $a, $b ) => $a['dist'] <=> $b['dist'] );
		$xl_near = array_values( array_filter( $xl_items, static fn( $it ) => $it['dist'] <= $xl_radius ) );
		if ( $xl_near ) {
			$xl_items = $xl_near;
		} else {
			$xl_fallback = true; // nichts im Umkreis: die nächstgelegenen zeigen
		}
	}
	$xl_show     = array_slice( $xl_items, 0, 8 );
	$xl_more_url = add_query_arg( array_filter( [
		'format' => 'weihnachtsfeier',
		'lat'    => $xl_geo ? $xl_lat : '',
		'lng'    => $xl_geo ? $xl_lng : '',
		'radius' => $xl_geo ? $xl_radius : '',
		'loc'    => $xl_geo ? $xl_loc : '',
		'pax'    => $xl_pax ?: '',
	], static fn( $v ) => '' !== $v && null !== $v ), $events_url );
	// Hero-Bild = Cover des Ulm-Platzhalters (Julius, 07.09.: Golferin an der Box, Team an der Bar).
	$xl_hero_post = get_page_by_path( 'weihnachtsfeier-mit-golf-in-ulm', OBJECT, 'firmengolf_event' );
	$xl_hero_img  = ( $xl_hero_post && function_exists( 'fge_event_cover_url' ) ) ? fge_event_cover_url( $xl_hero_post->ID ) : fge_get_placeholder_image_url( $format['hero_img'] ?? 'onboarding-indoor-lounge.jpg' );
	$xl_sim_count = function_exists( 'fge_simulatoren' ) ? count( fge_simulatoren() ) : 0;
	$xl_title     = $xl_geo && '' !== $xl_loc ? 'Weihnachtsfeiern rund um ' . $xl_loc : 'Beliebte Weihnachtsfeier-Angebote';
?>
<section class="mk-hero xmas-hero" aria-label="<?php echo esc_attr( $format['h1'] ); ?>">
	<div class="mk-hero-photo" style="background-image:url('<?php echo esc_url( $xl_hero_img ); ?>'); --xmas-hero-m:url('<?php echo esc_url( fge_get_placeholder_image_url( 'tiles/indoor-kundenevent.jpg' ) ); ?>')">
		<div class="mk-hero-scrim" aria-hidden="true"></div>
		<div class="mk-hero-content">
			<span class="mk-hero-tag">Top aktuell</span>
			<h1 class="mk-hero-title">Deine Weihnachtsfeier mit <strong>Indoor Golf</strong></h1>
			<p class="mk-hero-sub"><?php echo esc_html( $format['lead'] ); ?></p>
			<div class="mk-hero-ctas">
				<?php /* Standortfreigabe erst hier, nicht beim Seitenaufruf (Julius, 07.09.); Wunschtermin öffnet den Dialog */ ?>
				<a class="fg-btn-cta fg-btn-lg" href="#angebote" id="xmas-geo-trigger">Angebote ansehen <span class="fg-arrow"><?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?></span></a>
				<a class="fg-btn-ghost-light" href="#anfrage" onclick="if(window.fgeSimRequest){event.preventDefault();fgeSimRequest('','');}">Wunschtermin anfragen →</a>
			</div>
			<div class="cty-hero-facts">
				<span class="cty-hero-fact"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg><b><?php echo esc_html( (string) max( 50, $xl_sim_count ) ); ?>+</b>&nbsp;Simulatoren und Golfanlagen in ganz Deutschland</span>
				<span class="cty-hero-fact"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/></svg>Auch ohne Golferfahrung</span>
			</div>
		</div>
	</div>
</section>

<section class="fg-grid-section xmas-events" id="angebote" aria-label="Weihnachtsfeier-Angebote">
	<div class="fg-grid-head xmas-events-head">
		<div>
			<h2 class="fg-grid-title"><?php echo esc_html( $xl_title ); ?></h2>
			<?php if ( $xl_fallback ) : ?>
			<p class="xmas-events-note">Im Umkreis von <?php echo (int) $xl_radius; ?> km ist noch nichts angelegt, hier die nächstgelegenen Angebote. Oder ihr fragt unten direkt an, wir planen in eurer Region.</p>
			<?php elseif ( ! $xl_geo ) : ?>
			<p class="xmas-events-note">Ort eingeben oder Standort freigeben, dann sortieren wir nach Nähe.</p>
			<?php endif; ?>
		</div>
	</div>
	<?php /* Filterleiste im Stil der Eventliste (Julius, 07.09.): Standort-Chip + PLZ/Ort, keine Personenzahl */ ?>
	<form class="xmas-filter" method="get" action="<?php echo esc_url( $canonical . '#angebote' ); ?>" role="search" aria-label="Weihnachtsfeiern filtern">
		<div class="fg-chip-row xmas-filter-row">
			<?php if ( $xl_geo && 'Mein Standort' === $xl_loc ) : ?>
			<a class="fg-chip active xmas-filter-geo" href="<?php echo esc_url( $canonical . '#angebote' ); ?>" title="Standortfilter entfernen"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><circle cx="12" cy="12" r="8"/></svg>Mein Standort · <?php echo (int) $xl_radius; ?> km <span aria-hidden="true">✕</span></a>
			<?php else : ?>
			<button type="button" class="fg-chip xmas-filter-geo" id="xmas-filter-geo"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><circle cx="12" cy="12" r="8"/></svg>An meinem Standort suchen</button>
			<?php endif; ?>
			<div class="xmas-filter-plz">
				<label class="screen-reader-text" for="xmas-filter-q">PLZ oder Ort</label>
				<input class="fg-input" type="text" id="xmas-filter-q" name="q" inputmode="text" placeholder="PLZ oder Ort" autocomplete="postal-code" value="<?php echo esc_attr( '' !== $xl_unknown ? $xl_unknown : ( 'Mein Standort' === $xl_loc ? '' : $xl_loc ) ); ?>">
				<button type="submit" class="fg-chip xmas-filter-go">Suchen</button>
			</div>
			<?php if ( $xl_geo && 'Mein Standort' !== $xl_loc ) : ?>
			<a class="fg-chip" href="<?php echo esc_url( $canonical . '#angebote' ); ?>">Alle Angebote</a>
			<?php endif; ?>
		</div>
		<p class="xmas-filter-hint" id="xmas-filter-hint"<?php echo '' === $xl_unknown ? ' hidden' : ''; ?>><?php echo '' !== $xl_unknown ? esc_html( '„' . $xl_unknown . '" kennen wir nicht. Bitte eine deutsche PLZ oder eine größere Stadt eingeben.' ) : ''; ?></p>
	</form>
	<?php if ( $xl_show ) : ?>
	<div class="fg-grid ev-grid4">
		<?php foreach ( $xl_show as $xit ) : ?>
			<?php get_template_part( 'template-parts/fge-event-card-v2', null, [ 'id' => (int) $xit['id'] ] ); ?>
		<?php endforeach; ?>
	</div>
	<?php else : ?>
	<p class="xmas-events-note">Für diese Gruppengröße ist noch keine Weihnachtsfeier angelegt. Fragt unten direkt an, wir planen sie für euch.</p>
	<?php endif; ?>
	<div class="xmas-events-more">
		<a class="fg-btn-ghost" href="<?php echo esc_url( $xl_more_url ); ?>">Alle Weihnachtsfeiern ansehen <?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
	</div>
</section>
<?php get_template_part( 'template-parts/fge-geo-prompt', null, [
	'target'  => $canonical,
	'trigger' => '#xmas-geo-trigger, #xmas-filter-geo',
	'anchor'  => '#angebote',
	'title'  => 'Weihnachtsfeiern in eurer Nähe finden?',
	'text'   => 'Gebt kurz euren Standort frei, dann zeigen wir zuerst die Angebote, die ihr gut erreicht.',
	'skip'   => 'Alle Angebote ansehen',
] ); ?>

<?php get_template_part( 'template-parts/fge-xmas-request', null, [
	'id' => 'anfrage',
	'h2' => 'Nichts Passendes dabei? Wir planen eure <em class="mk-italic">Weihnachtsfeier</em> mit euch.',
] ); ?>

<?php
$sim_all = function_exists( 'fge_simulatoren' ) ? fge_simulatoren() : [];
$sim_by_land = [];
foreach ( $sim_all as $s ) {
	if ( '' === $s['bundesland'] ) { continue; }
	$sim_by_land[ $s['bundesland'] ] = ( $sim_by_land[ $s['bundesland'] ] ?? 0 ) + 1;
}
arsort( $sim_by_land );
?>
<?php if ( ! empty( $sim_all ) && function_exists( 'fge_gmaps_api_key' ) && fge_gmaps_api_key() !== '' ) : ?>
<section class="mk-section simx cty-reveal" id="simulatoren" aria-label="Golfsimulatoren in Deutschland">
	<div class="mk-section-head">
		<h2 class="mk-h2">Golfsimulatoren in <em class="mk-italic">ganz Deutschland</em>.</h2>
		<p class="mk-sub"><?php echo esc_html( (string) count( $sim_all ) ); ?> Indoor-Anlagen von Flensburg bis Garmisch. Pin antippen, Boxen und Website sehen und direkt dort eure Feier anfragen. Die Anfrage geht an uns, wir organisieren sie in dieser oder einer passenden Location.</p>
	</div>
	<div class="gpd-map simx-map" id="fge-sim-map">
		<div class="gpd-map-consent">
			<p>Die Karte lädt erst nach deiner Einwilligung für Google&nbsp;Maps.</p>
			<button type="button" class="fg-btn-brand" onclick="if(window.klaro){window.klaro.show()}">Karte aktivieren</button>
		</div>
	</div>
	<div class="simx-legend" aria-label="Legende">
		<span class="simx-key"><i class="simx-dot simx-dot--sim"></i>Golfsimulator</span>
		<span class="simx-key"><i class="simx-dot simx-dot--sim simx-dot--ring"></i>Bei Firmengolf buchbar</span>
		<span class="simx-key"><i class="simx-dot simx-dot--partner"></i>Golfanlage mit Weihnachtsfeier</span>
		<?php if ( $xl_geo ) : ?><span class="simx-key"><i class="simx-dot simx-dot--you"></i>Euer Standort</span><?php endif; ?>
	</div>
	<p class="simx-note">Stand September 2026, eigene Marktanalyse. Golfanlagen erscheinen nur, wenn sie selbst eine Weihnachtsfeier anbieten. Eure Anlage fehlt? <a href="<?php echo esc_url( add_query_arg( [ 'ob_step' => 1, 'ob_type' => 'indoor' ], home_url( '/partner-onboarding/' ) ) ); ?>">Als Simulator-Partner eintragen</a>.</p>
</section>
<?php endif; ?>

<?php
$play_img = static function ( string $slug, string $fallback ): string {
	$base = defined( 'FGE_DIR' ) ? FGE_DIR . 'assets/imagery/' : '';
	if ( '' !== $base && file_exists( $base . 'spielformate/' . $slug . '.jpg' ) ) {
		return fge_get_placeholder_image_url( 'spielformate/' . $slug . '.jpg' );
	}
	return fge_get_placeholder_image_url( $fallback );
};
$play_formats = [
	[ 'slug' => 'nearest-to-the-pin', 't' => 'Nearest to the Pin', 'b' => 'Ein Schlag, eine Fahne. Wer liegt am nächsten?', 'img' => 'pool/indoor-topgolf-abschlag.jpg' ],
	[ 'slug' => 'longest-drive',      't' => 'Longest Drive',      'b' => 'Der weiteste Ball gewinnt, auf den Meter gemessen.', 'img' => 'pool/afterwork-closeup-drive.jpg' ],
	[ 'slug' => 'angry-birds',        't' => 'Angry Birds',        'b' => 'Bälle auf Zielscheiben, Punkte wie im Spiel.', 'img' => 'pool/indoor-topgolf-oberhausen.jpg' ],
	[ 'slug' => 'putt-bierpong',      't' => 'Putt-Bierpong',      'b' => 'Putten statt werfen, Becher statt Loch.', 'img' => 'pool/indoor-bier-und-simulator.jpg' ],
	[ 'slug' => 'team-scramble',      't' => 'Team-Scramble',      'b' => 'Vier spielen einen Ball, jeder Schlag zählt fürs Team.', 'img' => 'pool/indoor-golf-indoor-simulator-bar-event-im-team.jpg' ],
	[ 'slug' => 'virtuelle-runde',    't' => 'Runde in St Andrews',  'b' => 'Neun Löcher auf den berühmtesten Plätzen der Welt.', 'img' => 'pool/indoor-18-in-st-andrews-the-home-of-golf.jpg' ],
];
?>
<section class="mk-section playx cty-reveal" id="spielformate" aria-label="Spielformate für euer Team">
	<div class="mk-section-head">
		<h2 class="mk-h2">Mögliche <em class="mk-italic">Spielformate</em> für euer Team.</h2>
		<p class="mk-sub">Bewegung, Location und Wettbewerb an einem Abend. Diese Formate spielen wir im Wechsel, mit Live-Leaderboard und Betreuung an den Boxen.</p>
	</div>
	<div class="iv-tiles playx-tiles">
		<?php foreach ( $play_formats as $pf ) : ?>
		<article class="iv-tile playx-tile">
			<span class="iv-tile-img" style="background-image:url('<?php echo esc_url( $play_img( $pf['slug'], $pf['img'] ) ); ?>')" role="img" aria-label="<?php echo esc_attr( $pf['t'] ); ?>"></span>
			<span class="iv-tile-scrim" aria-hidden="true"></span>
			<span class="iv-tile-label">
				<span>
					<span class="iv-tile-t"><?php echo esc_html( $pf['t'] ); ?></span>
					<span class="iv-tile-sub" style="display:block;"><?php echo esc_html( $pf['b'] ); ?></span>
				</span>
			</span>
		</article>
		<?php endforeach; ?>
	</div>
</section>

<?php /* Anfrage-Dialog von der Karte (Julius, 07.09.): gewählte Location oben, dann dieselbe
	Kurz-Anfrage; die Anfrage geht an Firmengolf, nicht an die Location. Vollflächig auf Mobil (DESIGN.md 8). */ ?>
<div class="fg-modal-scrim is-hidden" id="fge-sim-modal" role="dialog" aria-modal="true" aria-label="Weihnachtsfeier anfragen">
	<div class="fg-modal simx-modal">
		<button class="fg-modal-close" type="button" data-sim-modal-close aria-label="Schließen">
			<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
		</button>
		<div class="fg-modal-head simx-modal-head">
			<div class="fg-detail-eyebrow">Weihnachtsfeier anfragen</div>
			<h2 class="fg-modal-title" data-sim-modal-title>Weihnachtsfeier in eurer Wunsch-Location</h2>
			<p class="fg-modal-sub">Die Anfrage geht an Firmengolf. Wir prüfen die Location und schicken euch ein konkretes Angebot, dort oder in einer passenden Anlage in der Nähe.</p>
			<div class="simx-modal-loc" data-sim-modal-loc hidden>
				<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
				<span><b data-sim-modal-name></b><span data-sim-modal-ort></span></span>
			</div>
		</div>
		<?php get_template_part( 'template-parts/fge-xmas-request', null, [ 'id' => 'anfrage-location', 'prefix' => 'xm', 'compact' => true ] ); ?>
	</div>
</div>
<script>
(function () {
	var scrim = document.getElementById('fge-sim-modal');
	if (!scrim) return;
	var lockY = 0;
	function setOpen(open) {
		scrim.classList.toggle('is-hidden', !open);
		var b = document.body.style;
		if (open) {
			lockY = window.scrollY || 0;
			document.documentElement.classList.add('fg-drawer-lock');
			b.position = 'fixed'; b.top = (-lockY) + 'px'; b.left = '0'; b.right = '0'; b.width = '100%';
			var first = scrim.querySelector('select, input:not([type=hidden])'); if (first) setTimeout(function () { first.focus({ preventScroll: true }); }, 60);
		} else {
			document.documentElement.classList.remove('fg-drawer-lock');
			b.position = ''; b.top = ''; b.left = ''; b.right = ''; b.width = '';
			window.scrollTo(0, lockY);
		}
	}
	window.fgeSimRequest = function (name, ort) {
		var form = document.getElementById('xm-form');
		if (form) {
			if (form.elements.place) form.elements.place.value = name || '';
			if (form.elements.venue) form.elements.venue.value = 'Indoor-Simulator';
			if (form.elements.region && !form.elements.region.value) form.elements.region.value = ort || '';
		}
		var loc = scrim.querySelector('[data-sim-modal-loc]');
		scrim.querySelector('[data-sim-modal-name]').textContent = name || '';
		scrim.querySelector('[data-sim-modal-ort]').textContent = ort ? ', ' + ort : '';
		loc.hidden = !name;
		scrim.querySelector('[data-sim-modal-title]').textContent = name ? 'Weihnachtsfeier bei ' + name : 'Weihnachtsfeier in eurer Wunsch-Location';
		setOpen(true);
	};
	scrim.addEventListener('click', function (e) { if (e.target === scrim || e.target.closest('[data-sim-modal-close]')) setOpen(false); });
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !scrim.classList.contains('is-hidden')) setOpen(false); });
})();
</script>

<section class="mk-section" aria-label="Über <?php echo esc_attr( $f_name ); ?>">
	<div class="mk-section-head">
		<h2 class="mk-h2"><?php echo esc_html( $f_name ); ?>, gemeinsam erleben.</h2>
		<p class="mk-sub" style="max-width:var(--width-prose);"><?php echo esc_html( $format['intro'] ); ?></p>
	</div>
</section>

<?php else : ?>
<?php /* Hero mit direkten CTAs: Ads-Traffic soll ohne Umweg zu den Events bzw. zur Anfrage */ ?>
<section class="ev-hero" aria-label="<?php echo esc_attr( $format['h1'] ); ?>">
	<div class="ev-hero-photo" style="background-image:url('<?php echo esc_url( fge_get_placeholder_image_url( $format['hero_img'] ?? 'golfplatz-panorama.jpg' ) ); ?>')">
		<div class="ev-hero-scrim" aria-hidden="true"></div>
		<div class="ev-hero-content">
			<div class="ev-hero-eyebrow"><?php echo esc_html( $format['eyebrow'] ); ?></div>
			<h1 class="ev-hero-title"><?php echo esc_html( $format['h1'] ); ?></h1>
			<p class="ev-hero-sub"><?php echo esc_html( $format['lead'] ); ?></p>
			<div class="ev-hero-ctas">
				<a class="fg-btn-brand" href="#angebote">Passende Events ansehen</a>
				<a class="fg-btn-ghost-light" href="<?php echo esc_url( $anfrage_quick ); ?>">Unverbindlich anfragen</a>
			</div>
		</div>
	</div>
</section>

<?php /* Icon-Facts direkt unter dem Hero: die Antworten, die Ads-Besucher zuerst suchen
	(Kachel-Design, volle Container-Breite, vereint Facts + frühere Gründe-Sektion) */ ?>
<?php if ( ! empty( $format['facts'] ) ) : ?>
<section class="mk-section fmt-keyfacts" aria-label="<?php echo esc_attr( $f_name ); ?> auf einen Blick">
	<div class="city-reasons">
		<?php foreach ( $format['facts'] as $fact ) : ?>
		<div class="city-reason">
			<span class="city-reason-ic" aria-hidden="true"><?php echo fge_format_ico( $fact['ic'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- statische SVGs ?></span>
			<h2 class="city-reason-t"><?php echo esc_html( $fact['t'] ); ?></h2>
			<p class="city-reason-b"><?php echo esc_html( $fact['b'] ); ?></p>
		</div>
		<?php endforeach; ?>
	</div>
</section>
<?php endif; ?>

<?php /* Passende Events zuerst: das schnellste Ergebnis für die Suchanfrage */ ?>
<?php if ( ! empty( $format_events ) ) : ?>
<section class="fg-grid-section" id="angebote" aria-label="<?php echo esc_attr( $f_name ); ?>-Angebote">
	<div class="fg-grid-head">
		<h2 class="fg-grid-title">Beliebte <?php echo esc_html( $f_name ); ?>-Angebote</h2>
		<div class="fmt-city-chips">
			<?php if ( function_exists( 'fge_citformat_is_valid' ) && function_exists( 'fge_get_cities' ) ) :
				$fmt_cities = function_exists( 'fge_citformat_enabled_cities' ) ? fge_citformat_enabled_cities() : [];
				$all_cities = fge_get_cities();
				foreach ( $fmt_cities as $cslug ) : if ( ! fge_citformat_is_valid( $cslug, $slug ) ) continue; ?>
				<a class="fg-chip" href="<?php echo esc_url( home_url( '/golf-events/' . $cslug . '/' . $slug . '/' ) ); ?>"><?php echo esc_html( $all_cities[ $cslug ]['name'] ?? ucfirst( $cslug ) ); ?></a>
			<?php endforeach; endif; ?>
			<a class="fg-chip" href="<?php echo esc_url( $events_url ); ?>">Alle ansehen</a>
		</div>
	</div>
	<div class="fg-grid ev-grid4">
		<?php foreach ( $format_events as $ev ) : ?>
			<?php get_template_part( 'template-parts/fge-event-card-v2', null, [ 'id' => (int) $ev->ID ] ); ?>
			<?php endforeach; ?>
	</div>
</section>
<?php endif; ?>

<?php /* Intro */ ?>
<section class="mk-section" aria-label="Über <?php echo esc_attr( $f_name ); ?>">
	<div class="mk-section-head">
		<div class="mk-eyebrow"><?php echo esc_html( $f_name ); ?> für Unternehmen</div>
		<h2 class="mk-h2"><?php echo esc_html( $f_name ); ?>, gemeinsam erleben.</h2>
		<p class="mk-sub" style="max-width:var(--width-prose);"><?php echo esc_html( $format['intro'] ); ?></p>
	</div>
</section>

<?php if ( ! empty( $format['tiles'] ) ) :
	/* Format-Kacheln im Homepage-Look (Julius, 03.09.): lange iv-Kacheln in zwei
	   Reihen. Bild-Slots aus assets/imagery/tiles/ mit Lounge-Fallback, solange
	   die Stock-Bilder noch nicht eingepflegt sind. */
	$fmt_tile_img = static function ( string $file ): string {
		$base = defined( 'FGE_DIR' ) ? FGE_DIR . 'assets/imagery/' : '';
		if ( '' !== $base && file_exists( $base . $file ) ) {
			return fge_get_placeholder_image_url( $file );
		}
		return fge_get_placeholder_image_url( 'onboarding-indoor-lounge.jpg' );
	};
	?>
<section class="mk-section cty-reveal" aria-label="Formate für eure <?php echo esc_attr( $f_name ); ?>">
	<div class="mk-section-head">
		<h2 class="mk-h2"><?php echo wp_kses_post( $format['tiles_h2'] ?? 'Das passende Format für euer <em class="mk-italic">Team</em>.' ); ?></h2>
		<?php if ( ! empty( $format['tiles_sub'] ) ) : ?><p class="mk-sub"><?php echo esc_html( $format['tiles_sub'] ); ?></p><?php endif; ?>
	</div>
	<div class="iv-tiles">
		<?php foreach ( $format['tiles'] as $tile ) : ?>
			<a class="iv-tile" href="<?php echo esc_url( $tile['url'] ); ?>">
				<span class="iv-tile-img" style="background-image:url('<?php echo esc_url( $fmt_tile_img( $tile['img'] ) ); ?>')"></span>
				<span class="iv-tile-scrim"></span>
				<span class="iv-tile-label">
					<span>
						<span class="iv-tile-t"><?php echo esc_html( $tile['t'] ); ?></span>
						<span class="iv-tile-sub" style="display:block;"><?php echo esc_html( $tile['sub'] ); ?></span>
					</span>
					<span class="iv-tile-arrow"><?php echo function_exists( 'fge_icon_arrow_right' ) ? fge_icon_arrow_right() : '→'; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				</span>
			</a>
		<?php endforeach; ?>
	</div>
</section>
<?php endif; ?>

<?php /* So könnte dein Tag ablaufen: eine Reihe, Punkte blenden gestaffelt von oben ein */ ?>
<?php endif; // summer / xmas / Standard ?>

<?php if ( ! empty( $format['flow'] ) ) : ?>
<section class="mk-section mk-band fmt-flow5" aria-label="So läuft euer <?php echo esc_attr( $f_name ); ?>">
	<div class="mk-section-head">
		<div class="mk-eyebrow">So könnte euer Event ablaufen</div>
		<h2 class="mk-h2"><?php echo esc_html( $format['flow_h'] ?? 'Vom Welcome bis zum Ausklang.' ); ?></h2>
	</div>
	<div class="fmt-flow5-row">
		<?php foreach ( $format['flow'] as $i => $step ) : ?>
		<div class="fmt-fstep" style="--fstep-delay:<?php echo esc_attr( (string) ( $i * 0.18 ) ); ?>s">
			<div class="fmt-fstep-n"><?php echo esc_html( (string) ( $i + 1 ) ); ?></div>
			<h3 class="fmt-fstep-t"><?php echo esc_html( $step['t'] ); ?></h3>
			<p class="fmt-fstep-b"><?php echo esc_html( $step['b'] ); ?></p>
		</div>
		<?php endforeach; ?>
	</div>
</section>
<?php endif; ?>

<?php /* Golf-Erfahrung: für jedes Level das Passende (identisch zu Individuelle Events) */ ?>
<?php if ( ! empty( $format['levels'] ) ) :
	$fmt_levels = [
		[ 'level' => 1, 'badge' => 'Einsteiger', 't' => 'Erste Erfahrungen', 'img' => 'erfahrung-korb.jpg',
			'b' => 'Noch nie einen Schläger gehalten? Genau richtig. Golflehrer, Leih-Ausrüstung und die ersten Schwünge auf der Range, locker, ohne Druck.',
			'meta' => [ 'Golflehrer', 'Schläger gestellt', 'Range & Putting' ] ],
		[ 'level' => 2, 'badge' => 'Auffrischer', 't' => 'Schon mal gespielt', 'img' => 'erfahrung-sand.jpg',
			'b' => 'Ein paar Runden Erfahrung? Wir frischen den Schwung auf, gehen ins Kurzspiel und spielen danach gemeinsam entspannte 9 Loch.',
			'meta' => [ 'Kurzspiel-Training', '9 Loch', 'Gemischte Flights' ] ],
		[ 'level' => 3, 'badge' => 'Fortgeschritten', 't' => 'Fortgeschrittene Golfer', 'img' => 'erfahrung-inselgruen.jpg',
			'b' => 'Platzreife in der Tasche? Volle 18 Loch im Turnierformat mit Flights, Live-Scoring und Siegerehrung bei Sonnenuntergang.',
			'meta' => [ '18 Loch', 'Live-Scoring', 'Siegerehrung' ] ],
	];
?>
<?php /* mk-section statt iv-section: sonst rendert genau diese eine Überschrift 44px
	statt 48px und die Sektion bricht mobil aus dem vertikalen Rhythmus
	(Audit 2026-08-12, einziger Ausreißer auf allen Formatseiten). */ ?>
<section class="mk-section">
	<div class="mk-section-head">
		<div class="mk-eyebrow">Golf-Erfahrung</div>
		<h2 class="mk-h2">Für jedes Level das <span class="mk-italic">Passende</span></h2>
		<p class="mk-sub" style="max-width:var(--width-prose);">In jedem Team spielt jemand zum ersten Mal, und jemand seit Jahren. Wir stellen jedes Event so zusammen, dass alle Spaß haben, egal auf welchem Level.</p>
	</div>
	<div class="iv-exp-grid">
		<?php foreach ( $fmt_levels as $x ) : ?>
			<article class="iv-exp">
				<div class="iv-exp-photo" style="background-image:url('<?php echo esc_url( fge_get_placeholder_image_url( $x['img'] ) ); ?>')">
					<span class="iv-exp-badge"><?php echo esc_html( $x['badge'] ); ?></span>
				</div>
				<div class="iv-exp-body">
					<div class="iv-exp-dots" aria-hidden="true">
						<?php for ( $n = 1; $n <= 3; $n++ ) : ?>
							<span class="iv-exp-dot<?php echo $n <= $x['level'] ? ' on' : ''; ?>"></span>
						<?php endfor; ?>
					</div>
					<h3 class="iv-exp-t"><?php echo esc_html( $x['t'] ); ?></h3>
					<p class="iv-exp-b"><?php echo esc_html( $x['b'] ); ?></p>
					<div class="iv-exp-meta">
						<?php foreach ( $x['meta'] as $m ) : ?>
							<span class="iv-exp-tag"><?php echo esc_html( $m ); ?></span>
						<?php endforeach; ?>
					</div>
				</div>
			</article>
		<?php endforeach; ?>
	</div>
</section>
<?php endif; ?>

<?php /* Gründe */ ?>
<?php if ( ! empty( $format['reasons'] ) ) : ?>
<section class="mk-section city-reasons-section" aria-label="Gründe für <?php echo esc_attr( $f_name ); ?>">
	<div class="city-reasons">
		<?php foreach ( $format['reasons'] as $r ) : ?>
		<div class="city-reason">
			<span class="city-reason-ic" aria-hidden="true"><?php echo fge_format_ico( $r['ic'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- statische SVGs ?></span>
			<h3 class="city-reason-t"><?php echo esc_html( $r['t'] ); ?></h3>
			<p class="city-reason-b"><?php echo esc_html( $r['b'] ); ?></p>
		</div>
		<?php endforeach; ?>
	</div>
</section>
<?php endif; ?>

<?php /* Optionale Cross-Promo (z. B. Platzreife → Firmengolf-Benefit) */ ?>
<?php if ( ! empty( $format['promo'] ) ) : $fmt_promo = $format['promo']; ?>
<section class="mk-section" aria-label="<?php echo esc_attr( $fmt_promo['title'] ); ?>">
	<div class="fmt-promo">
		<div>
			<div class="mk-eyebrow"><?php echo esc_html( $fmt_promo['eyebrow'] ); ?></div>
			<h2 class="mk-h2" style="font-size:30px;"><?php echo esc_html( $fmt_promo['title'] ); ?></h2>
			<p class="mk-sub"><?php echo esc_html( $fmt_promo['text'] ); ?></p>
		</div>
		<a class="fg-btn-brand" href="<?php echo esc_url( $fmt_promo['url'] ); ?>"<?php echo ! empty( $fmt_promo['external'] ) ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
			<?php echo esc_html( $fmt_promo['cta'] ); ?>
		</a>
	</div>
</section>
<?php endif; ?>

<?php /* Wertschätzungs-Teaser (auf allen Formatseiten, Julius 2026-08-11) */ ?>
<?php get_template_part( 'template-parts/fge-wz-promo' ); ?>

<?php /* FAQ */ ?>
<?php if ( ! empty( $faqs ) ) : ?>
<section class="mk-section faq-section" aria-label="FAQ">
	<div class="faq-shell">
		<div class="faq-aside">
			<div class="mk-eyebrow">Häufige Fragen</div>
			<h2 class="mk-h2" style="margin-top:8px;"><?php echo esc_html( $f_name ); ?>, kurz erklärt.</h2>
		</div>
		<?php
		/* Globale FAQ-Komponente (Design-Linie, 28.08.2026); optionale Links wandern
		   als fertiges HTML in die Antwort. */
		get_template_part( 'template-parts/fge-faq', null, [
			'items' => array_map( static function ( $faq ) {
				$html = esc_html( $faq['a'] );
				if ( ! empty( $faq['link']['url'] ) ) {
					$html .= ' <a class="faq-a-link" href="' . esc_url( $faq['link']['url'] ) . '">' . esc_html( $faq['link']['label'] ) . ' &rarr;</a>';
				}
				return [ 'q' => $faq['q'], 'a_html' => $html ];
			}, $faqs ),
		] );
		?>
	</div>
</section>
<?php endif; ?>

<?php /* Cross-links zu anderen Formaten + CTA */ ?>
<section class="mk-cta" aria-label="Anfrage">
	<div class="mk-cta-inner">
		<div class="mk-eyebrow" style="color:rgba(251,250,246,0.65)">Bereit für euer <?php echo esc_html( $f_name ); ?>?</div>
		<h2 class="mk-cta-h">Lasst uns euer Event <em class="mk-italic">planen</em>.</h2>
		<div class="mk-cta-ctas">
			<a class="fg-btn-ink fg-btn-lg" href="<?php echo esc_url( $anfrage_quick ); ?>" style="background:var(--paper-100);color:var(--fairway-900)">Event anfragen</a>
			<?php foreach ( $formats as $fslug => $f ) : if ( $fslug === $slug ) continue; ?>
				<a class="mk-cta-mail" href="<?php echo esc_url( home_url( '/firmenevent/' . $fslug . '/' ) ); ?>"><?php echo esc_html( $f['name'] ); ?> →</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>

<script>
// Ablauf-Punkte blenden gestaffelt ein, sobald die Sektion sichtbar wird.
(function () {
	var row = document.querySelector('.fmt-flow5-row');
	if (!row) return;
	if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
		row.classList.add('is-in');
		return;
	}
	var io = new IntersectionObserver(function (entries) {
		entries.forEach(function (e) {
			if (e.isIntersecting) { row.classList.add('is-in'); io.disconnect(); }
		});
	}, { threshold: 0.25 });
	io.observe(row);
})();
/* FAQ-Toggle kommt aus der globalen Komponente (template-parts/fge-faq.php). */
</script>

<?php get_footer();
