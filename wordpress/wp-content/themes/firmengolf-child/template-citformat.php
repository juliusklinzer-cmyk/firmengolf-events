<?php
/**
 * SEO Format×Stadt landing — rendered for /golf-events/<stadt>/<format>/
 * via citformat-landing.php. Mergt City- (city-landing.php) und Format-Inhalt
 * (format-landing.php) zu lokalem Long-Tail-Content.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$city_slug   = (string) get_query_var( 'fge_city' );
$format_slug = (string) get_query_var( 'fge_format' );

$cities  = function_exists( 'fge_get_cities' ) ? fge_get_cities() : [];
$formats = function_exists( 'fge_get_event_format_pages' ) ? fge_get_event_format_pages() : [];
$city    = $cities[ $city_slug ] ?? null;
$format  = $formats[ $format_slug ] ?? null;
if ( ! $city || ! $format || ! fge_citformat_is_valid( $city_slug, $format_slug ) ) {
	wp_safe_redirect( home_url( '/' ) );
	exit;
}

$city_name   = $city['name'];
$city_region = $city['region'];
$fmeta_all   = fge_citformat_format_meta();
$fmeta       = $fmeta_all[ $format_slug ] ?? [ 'h1' => '%s', 'eyeb' => '%s', 'title' => '%s | Firmengolf', 'desc' => '' ];
$intros      = fge_citformat_intros();
$intro       = $intros[ $city_slug ][ $format_slug ] ?? ( $format['intro'] ?? '' );

// Preis-Satz kommt weiter unten aus den Events DIESER Seite (statt deutschlandweit):
// sonst widersprach der Text der Karte, sobald eine Stadt eigene Preise fährt
// (z. B. After-Work München 49 € vs. 60 € anderswo, 2026-08-20).

$h1          = sprintf( $fmeta['h1'], $city_name );
$eyebrow     = sprintf( $fmeta['eyeb'], $city_name );
$canonical   = home_url( '/golf-events/' . $city_slug . '/' . $format_slug . '/' );
$city_url    = home_url( '/golf-events/' . $city_slug . '/' );
$format_url  = home_url( '/firmenevent/' . $format_slug . '/' );
$seo_title   = sprintf( $fmeta['title'], $city_name );
$seo_desc    = sprintf( $fmeta['desc'], $city_name );
$events_url  = (string) get_post_type_archive_link( 'firmengolf_event' );
$ind_url     = ( $p = get_page_by_path( 'individuelle-events' ) ) ? (string) get_permalink( $p->ID ) : home_url( '/individuelle-events/' );
// Deep-Link in die 30-Sekunden-Anfrage mit dem Format der Seite als Anlass.
$cf_anfrage_quick = add_query_arg( array_filter( [
	'anfrage' => 'quick',
	'anlass'  => function_exists( 'fge_format_occasion' ) ? fge_format_occasion( $format_slug ) : '',
] ), $ind_url );

// Gründe: 2 Format-Gründe + 2 Stadt-Gründe, dedupliziert nach Titel.
$reasons = [];
$seen_r  = [];
// Format-Seite pflegt ihre Argumente unter 'facts' ('reasons' gab es dort nie,
// deshalb fehlten die Format-Punkte bisher komplett, 2026-08-20).
foreach ( array_merge( array_slice( (array) ( $format['reasons'] ?? $format['facts'] ?? [] ), 0, 2 ), array_slice( (array) ( $city['reasons'] ?? [] ), 0, 2 ) ) as $r ) {
	$key = $r['t'] ?? '';
	if ( $key === '' || isset( $seen_r[ $key ] ) ) {
		continue;
	}
	$seen_r[ $key ] = true;
	$reasons[]      = $r;
}

// FAQ: stadtspezifische „Welche Golfplätze…"-Frage zuerst, dann Format-FAQ, dedupliziert.
$faqs       = [];
$seen_q     = [];
$city_faq0  = ( ! empty( $city['faqs'] ) && isset( $city['faqs'][0] ) ) ? [ $city['faqs'][0] ] : [];
foreach ( array_merge( $city_faq0, (array) ( $format['faqs'] ?? [] ) ) as $f ) {
	$key = $f['q'] ?? '';
	if ( $key === '' || isset( $seen_q[ $key ] ) ) {
		continue;
	}
	$seen_q[ $key ] = true;
	$faqs[]         = $f;
	if ( count( $faqs ) >= 5 ) {
		break;
	}
}

// Events am Schnittpunkt Stadt × Format; Fallback verhindert leere Seite.
$cf_events = fge_citformat_events( $city, $format, 6 );
if ( empty( $cf_events ) && function_exists( 'fge_city_events' ) ) {
	$cf_events = fge_city_events( $city, 6 );
}
if ( empty( $cf_events ) && function_exists( 'fge_get_featured_events' ) ) {
	$cf_events = fge_get_featured_events( 6 );
}

// Andere Formate in dieser Stadt (Cross-Links) + gleiches Format in der anderen Stadt.
$sibling_cities = array_values( array_diff( fge_citformat_enabled_cities(), [ $city_slug ] ) );

// ── Conversion-Umbau (2026-08-20, Stil der Stadt-Seite) ──────────────────────
// Hero traegt nur den ersten Intro-Satz plus Kernversprechen; der Rest des
// Intros erzaehlt in der Story-Sektion weiter (kein Content geht verloren).
$hero_sub   = $intro;
$story_text = '';
if ( preg_match( '/^(.+?[.!?])\s+(.+)$/u', $intro, $cf_m ) ) {
	$hero_sub   = $cf_m[1] . ' Eine Anfrage, ein Ansprechpartner, eine Rechnung.';
	$story_text = $cf_m[2];
}

// Fakten fuer die Hero-Zeile: Golfplaetze im Grossraum + Einstiegspreis
// (dieselbe Anzeige-Logik wie die Event-Karten, nur Pro-Person-Preise).
$cf_coords       = function_exists( 'fge_city_coords' ) ? ( fge_city_coords()[ $city_slug ] ?? null ) : null;
$cf_stat_courses = ( $cf_coords && function_exists( 'fge_verzeichnis_nearby' ) )
	? count( fge_verzeichnis_nearby( $cf_coords[0], $cf_coords[1], 60, 500 ) )
	: 0;
$cf_min_price = 0.0;
if ( function_exists( 'fge_event_pricing' ) ) {
	foreach ( $cf_events as $cf_mp_ev ) {
		$cf_mp_pricing = fge_event_pricing( $cf_mp_ev->ID );
		$cf_mp = (float) ( $cf_mp_pricing['gross'] ?? 0 );
		if ( $cf_mp <= 0 && function_exists( 'fge_get_event_price_display' ) ) {
			// Custom-Preis-Label (z. B. „49 € p.P.", wenn die 5er-Glaettung den
			// Wunschpreis nicht hergibt): Zahl fuer den „Ab …"-Anker mitzaehlen.
			$cf_lbl = fge_get_event_price_display( $cf_mp_ev->ID );
			if ( false !== stripos( $cf_lbl, 'p.P' ) && preg_match( '/([\d]+(?:[.,]\d+)?)\s*€/u', $cf_lbl, $cf_lm ) ) {
				$cf_mp = (float) str_replace( ',', '.', $cf_lm[1] );
			}
		}
		if ( $cf_mp > 0 && ( $cf_min_price <= 0 || $cf_mp < $cf_min_price ) && ( 'pro Person' === ( $cf_mp_pricing['unit'] ?? '' ) || ( $cf_mp_pricing['gross'] ?? 0 ) <= 0 ) ) {
			$cf_min_price = $cf_mp;
		}
	}
}
// Preis-Satz der Story aus den Events DIESER Seite.
if ( $cf_min_price > 0 ) {
	$cf_price_sentence = 'Ab ' . number_format_i18n( $cf_min_price, 0 ) . ' € pro Person.';
	$story_text        = '' !== $story_text ? $story_text . ' ' . $cf_price_sentence : $cf_price_sentence;
}

// „Alle Events ansehen" vorgefiltert auf die Stadt (Umkreissuche der Events-Seite).
$events_url_city = $events_url;
if ( $cf_coords ) {
	$events_url_city = add_query_arg( [
		'loc'    => $city_name,
		'lat'    => round( $cf_coords[0], 4 ),
		'lng'    => round( $cf_coords[1], 4 ),
		'radius' => 100,
	], $events_url );
}
$format_name = (string) ( $format['name'] ?? 'Golf-Event' );

// Icon-Set (teilt sich die Helferfunktion mit der City-Seite, falls geladen).
if ( ! function_exists( 'fge_city_ico' ) ) {
	function fge_city_ico( string $name ): string {
		$p = [
			'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
			'users'    => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
			'mountain' => '<path d="M3 20l6.5-11 4 6 2-3L21 20z"/>',
			'flag'     => '<path d="M5 21V4l9 2.5L5 9"/><circle cx="17" cy="17" r="3"/>',
			'castle'   => '<path d="M4 21V8l2 1V5l2 1V4l2 1V4l2-1v2l2-1v2l2-1v4l2-1v13z"/><path d="M10 21v-4h4v4"/>',
			'gift'     => '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M5 12v9h14v-9"/><path d="M12 8S10.5 3 8 4.5 9.5 8 12 8zM12 8s1.5-5 4-3.5S14.5 8 12 8z"/>',
			'leaf'     => '<path d="M11 20A7 7 0 0 1 4 13C4 8 9 4 20 4c0 9-4 16-9 16z"/><path d="M4 20c4-4 7-6 11-7"/>',
			'trophy'   => '<path d="M8 21h8M12 17v4M7 4h10v4a5 5 0 0 1-10 0z"/><path d="M5 4H3v2a3 3 0 0 0 3 3M19 4h2v2a3 3 0 0 1-3 3"/>',
			'handshake'=> '<path d="M8 13l3 3 4-4 3 3"/><path d="M2 12l4-4 4 3M22 12l-4-4-3 2"/>',
			'sun'      => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5L19 19M19 5l-1.5 1.5M6.5 17.5L5 19"/>',
		];
		$d = $p[ $name ] ?? $p['flag'];
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
	}
}

// ── SEO head injection ────────────────────────────────────────────────────────
add_filter( 'pre_get_document_title', static fn() => $seo_title );
add_action( 'wp_head', static function () use ( $seo_title, $seo_desc, $canonical, $city_name, $h1, $city_url, $faqs ) {
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
			'serviceType' => $h1,
			'name'        => $h1,
			'areaServed'  => [ '@type' => 'City', 'name' => $city_name ],
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
				[ '@type' => 'ListItem', 'position' => 2, 'name' => 'Firmenevents ' . $city_name, 'item' => $city_url ],
				[ '@type' => 'ListItem', 'position' => 3, 'name' => $h1 ],
			],
		],
	];
	echo '<script type="application/ld+json">' . wp_json_encode( $graph ) . '</script>' . "\n";
} );

get_header();
?>
<div class="fge-page fmt-lp cty-page" id="fge-main" role="main" tabindex="-1">

<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => '' ] ); ?>

<?php /* Hero: Botschaft, Anfrage, Fakten. Eyebrow und Textwand sind Geschichte. */ ?>
<section class="ev-hero" aria-label="<?php echo esc_attr( $h1 ); ?>">
	<?php
	// Stadtspezifisches Hero-Bild (stadt-<slug>.jpg), sonst generisches Panorama.
	$city_hero = 'stadt-' . $city_slug . '.jpg';
	if ( ! defined( 'FGE_DIR' ) || ! file_exists( FGE_DIR . 'assets/imagery/' . $city_hero ) ) {
		$city_hero = 'golfplatz-panorama.jpg';
	}
	?>
	<div class="ev-hero-photo" style="background-image:url('<?php echo esc_url( fge_get_placeholder_image_url( $city_hero ) ); ?>')">
		<div class="ev-hero-scrim" aria-hidden="true"></div>
		<div class="ev-hero-content">
			<h1 class="ev-hero-title"><?php echo esc_html( $h1 ); ?></h1>
			<p class="ev-hero-sub"><?php echo esc_html( $hero_sub ); ?></p>
			<div class="ev-hero-ctas">
				<a class="fg-btn-brand" href="<?php echo esc_url( $cf_anfrage_quick ); ?>">Event anfragen</a>
				<a class="fg-btn-ghost-light" href="#angebote">Events ansehen</a>
			</div>
			<?php if ( $cf_stat_courses > 0 || $cf_min_price > 0 ) : ?>
			<div class="cty-hero-facts">
				<?php if ( $cf_stat_courses > 0 ) : ?>
				<span class="cty-hero-fact"><?php echo fge_city_ico( 'flag' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><strong><?php echo esc_html( (string) $cf_stat_courses ); ?></strong>&nbsp;Golfplätze im Großraum <?php echo esc_html( $city_name ); ?></span>
				<?php endif; ?>
				<?php if ( $cf_min_price > 0 ) : ?>
				<span class="cty-hero-fact"><?php echo fge_city_ico( 'gift' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>Ab&nbsp;<strong><?php echo esc_html( number_format_i18n( $cf_min_price, 0 ) ); ?>&nbsp;€</strong>&nbsp;pro Person</span>
				<?php endif; ?>
			</div>
			<?php endif; ?>
		</div>
	</div>
</section>

<?php /* Events am Schnittpunkt Stadt × Format, direkt nach dem Hero. Bei wenigen
	     Treffern fuellt eine Anfrage-Kachel das Grid und faengt „nichts dabei" auf. */ ?>
<?php if ( ! empty( $cf_events ) ) : ?>
<section class="mk-section cty-reveal" id="angebote" aria-label="Events in <?php echo esc_attr( $city_name ); ?>">
	<div class="mk-section-head between">
		<div>
			<h2 class="mk-h2">Passende Events in <?php echo esc_html( $city_name ); ?>.</h2>
			<p class="mk-sub">Feste Pakete mit transparentem Preis pro Person, direkt anfragbar.</p>
		</div>
		<a class="fg-btn-ghost" href="<?php echo esc_url( $events_url_city ); ?>">Alle Events ansehen →</a>
	</div>
	<div class="fg-grid ev-grid4">
		<?php foreach ( array_slice( $cf_events, 0, 4 ) as $ev ) {
			get_template_part( 'template-parts/fge-event-card-v2', null, [ 'id' => (int) $ev->ID, 'dist' => null ] );
		} ?>
		<?php if ( count( $cf_events ) < 4 ) : ?>
		<a class="evF-fill" href="<?php echo esc_url( $cf_anfrage_quick ); ?>">
			<span class="evF-fill-ic" aria-hidden="true"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></svg></span>
			<span class="evF-fill-h">Nichts Passendes dabei?</span>
			<span class="evF-fill-p">Wir stellen euer <?php echo esc_html( $format_name ); ?> in <?php echo esc_html( $city_name ); ?> nach Maß zusammen.</span>
			<span class="evF-fill-go">Event anfragen
				<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg></span>
		</a>
		<?php endif; ?>
	</div>
</section>
<?php endif; ?>

<?php /* Turnier-Varianten (nur Firmen-Golfturnier, Julius 2026-08-20): Einsteiger auf
	     dem Kurzplatz ohne Platzreife, klassisch auf 18 Loch, oder beides gemischt.
	     Die Mix-Karte ist bewusst breit und hervorgehoben, sie ist die Pointe. */ ?>
<?php if ( 'golfturnier' === $format_slug ) : ?>
<section class="mk-section cty-reveal" aria-label="Turnier-Varianten in <?php echo esc_attr( $city_name ); ?>">
	<div class="mk-section-head">
		<h2 class="mk-h2">Ein Turnier für jedes Level.</h2>
		<p class="mk-sub">Ob euer Team noch nie einen Schläger in der Hand hatte oder längst die Platzreife hat: Wir bauen euer Turnier passend, auch gemischt.</p>
	</div>
	<div class="cfv-grid">
		<div class="cfv-card">
			<span class="cfv-ic" aria-hidden="true"><?php echo fge_city_ico( 'leaf' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			<h3>Für komplette Einsteiger</h3>
			<p>Turnier auf dem Kurzplatz, ganz ohne Platzreife-Prüfung. Vorab eine Einführung mit dem Golf-Pro, Schläger und Bälle werden gestellt.</p>
		</div>
		<div class="cfv-card">
			<span class="cfv-ic" aria-hidden="true"><?php echo fge_city_ico( 'trophy' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			<h3>Für Golfer mit Platzreife</h3>
			<p>Das klassische Firmenturnier auf der 18-Loch-Anlage: Flights, faire Zählformate für alle Handicaps und Siegerehrung.</p>
		</div>
		<div class="cfv-card cfv-card--mix">
			<div class="cfv-mix-copy">
				<h3>Oder gemischt, beides parallel</h3>
				<p>Einsteiger lernen die Grundlagen und spielen ihr Turnier auf dem Kurzplatz, Golfer mit Platzreife starten ihr eigenes auf der 18-Loch-Runde. Zur Siegerehrung kommen alle wieder zusammen.</p>
			</div>
			<a class="fg-btn-brand" href="<?php echo esc_url( $cf_anfrage_quick ); ?>">Jetzt anfragen</a>
		</div>
	</div>
</section>
<?php endif; ?>

<?php /* Story: restlicher Intro-Text + Gruende als Liste, daneben das Format-Motiv. */ ?>
<section class="mk-section cty-story cty-reveal" aria-label="Warum <?php echo esc_attr( $h1 ); ?>">
	<div class="cty-story-grid">
		<div class="cty-story-copy">
			<h2 class="mk-h2"><?php echo esc_html( $format_name ); ?> in <?php echo esc_html( $city_name ); ?>, gut aufgehoben.</h2>
			<?php if ( '' !== $story_text ) : ?>
			<p class="cty-story-intro"><?php echo esc_html( $story_text ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $reasons ) ) : ?>
			<ul class="cty-story-points">
				<?php foreach ( $reasons as $r ) : ?>
				<li class="cty-story-point">
					<span class="cty-story-ic" aria-hidden="true"><?php echo fge_city_ico( $r['ic'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<div>
						<h3><?php echo esc_html( $r['t'] ); ?></h3>
						<p><?php echo esc_html( $r['b'] ); ?></p>
					</div>
				</li>
				<?php endforeach; ?>
			</ul>
			<?php endif; ?>
		</div>
		<figure class="cty-story-media">
			<img src="<?php echo esc_url( fge_get_placeholder_image_url( $format['hero_img'] ?? 'golfplatz-panorama.jpg' ) ); ?>"
			     alt="<?php echo esc_attr( $format_name . ' auf dem Golfplatz' ); ?>" loading="lazy">
		</figure>
	</div>
</section>

<?php /* FAQ: zentrierte Karten-Akkordeons (gleicher Stil wie die Stadt-Seite) */ ?>
<section class="mk-section faq-section cty-faq cty-reveal" aria-label="FAQ">
	<div class="cty-faq-head">
		<h2 class="mk-h2"><?php echo esc_html( $eyebrow ); ?>, kurz erklärt.</h2>
		<p class="faq-aside-note">Eure Frage ist nicht dabei? Schreibt mir direkt:
			<a href="mailto:julius@firmengolf-events.de">julius@firmengolf-events.de</a></p>
	</div>
	<ul class="faq-list faq-anim cty-faq-cards">
		<?php foreach ( $faqs as $faq ) : ?>
			<li class="faq-item">
				<button class="faq-q" type="button" aria-expanded="false">
					<span><?php echo esc_html( $faq['q'] ); ?></span>
					<span class="faq-toggle cty-faq-chev" aria-hidden="true">
						<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
					</span>
				</button>
				<div class="faq-a">
					<div class="faq-a-in">
						<?php echo esc_html( $faq['a'] ); ?>
						<?php if ( ! empty( $faq['link']['url'] ) ) : ?>
							<a class="faq-a-link" href="<?php echo esc_url( $faq['link']['url'] ); ?>"><?php echo esc_html( $faq['link']['label'] ); ?> &rarr;</a>
						<?php endif; ?>
					</div>
				</div>
			</li>
		<?php endforeach; ?>
	</ul>
</section>

<?php /* Cross-Links: andere Formate in dieser Stadt + gleiches Format in anderer Stadt */ ?>
<section class="mk-section cty-reveal" aria-label="Weitere Formate in <?php echo esc_attr( $city_name ); ?>">
	<div class="mk-section-head">
		<h2 class="mk-h2">Noch mehr Golf-Events in <?php echo esc_html( $city_name ); ?>.</h2>
	</div>
	<div class="cty-fmt-bento">
		<?php
		// Foto-Kacheln wie auf der Stadt-Seite (Julius, 2026-08-20): 6 Formate ohne das
		// aktuelle; erste und letzte Kachel breit, damit beide Reihen exakt aufgehen.
		$cf_fmt_pages = function_exists( 'fge_get_event_format_pages' ) ? fge_get_event_format_pages() : [];
		$cf_fmt_rest  = array_diff_key( $fmeta_all, [ $format_slug => true ] );
		$cf_fmt_last  = count( $cf_fmt_rest ) - 1;
		$cf_fmt_i     = 0;
		foreach ( $cf_fmt_rest as $fslug => $fm ) :
			$cf_fmt_title = trim( (string) strtok( sprintf( $fm['eyeb'], $city_name ), '·' ) );
			$cf_fmt_img   = $cf_fmt_pages[ $fslug ]['hero_img'] ?? 'golfplatz-panorama.jpg';
		?>
			<a class="cty-fmt-tile<?php echo ( 0 === $cf_fmt_i || $cf_fmt_last === $cf_fmt_i ) ? ' is-wide' : ''; ?>" href="<?php echo esc_url( home_url( '/golf-events/' . $city_slug . '/' . $fslug . '/' ) ); ?>">
				<img src="<?php echo esc_url( fge_get_placeholder_image_url( $cf_fmt_img ) ); ?>" alt="" loading="lazy">
				<span class="cty-fmt-tile-scrim" aria-hidden="true"></span>
				<span class="cty-fmt-tile-txt">
					<span class="cty-fmt-tile-h"><?php echo esc_html( $cf_fmt_title ); ?></span>
					<span class="cty-fmt-tile-p"><?php echo esc_html( sprintf( $fm['desc'], $city_name ) ); ?></span>
					<span class="cty-fmt-tile-go"><?php echo esc_html( $cf_fmt_title ); ?> in <?php echo esc_html( $city_name ); ?> ansehen
						<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
					</span>
				</span>
			</a>
		<?php $cf_fmt_i++; endforeach; ?>
	</div>
	<?php /* Stadt-Links nur mit Stadtnamen statt „Format · Stadt" in Endlos-Wiederholung;
	         nowrap via .cfx-links, Kontext liefert das Label davor (Design-QA 2026-08-21). */ ?>
	<p class="mk-sub cfx-links" style="margin-top:18px;">
		<a href="<?php echo esc_url( $city_url ); ?>">Alle Golf-Events in <?php echo esc_html( $city_name ); ?></a>
		· <a href="<?php echo esc_url( $format_url ); ?>"><?php echo esc_html( $format_name ); ?> deutschlandweit</a>
		<span class="cfx-links-label">· <?php echo esc_html( $format_name ); ?> in anderen Städten:</span>
		<?php $cfx_first = true; foreach ( $sibling_cities as $sib ) : if ( ! isset( $cities[ $sib ] ) ) { continue; } ?>
			<?php echo $cfx_first ? '' : '· '; $cfx_first = false; ?><a href="<?php echo esc_url( home_url( '/golf-events/' . $sib . '/' . $format_slug . '/' ) ); ?>"><?php echo esc_html( $cities[ $sib ]['name'] ); ?></a>
		<?php endforeach; ?>
	</p>
</section>

<?php /* CTA mit Putt-Moment (gemeinsamer Baustein) */
$cf_cta_links = '<a href="' . esc_url( $city_url ) . '">Alle Formate in ' . esc_html( $city_name ) . '</a>';
foreach ( $sibling_cities as $sib ) {
	if ( ! isset( $cities[ $sib ] ) ) { continue; }
	$cf_cta_links .= '<a href="' . esc_url( home_url( '/golf-events/' . $sib . '/' . $format_slug . '/' ) ) . '">' . esc_html( $format_name . ' in ' . $cities[ $sib ]['name'] ) . '</a>';
}
get_template_part( 'template-parts/fge-putt-cta', null, [
	'headline_html' => 'Lasst uns euer ' . esc_html( $format_name ) . ' in ' . esc_html( $city_name ) . ' <em class="mk-italic">planen</em>.',
	'sub'           => 'Schickt uns eure Eckdaten in 30 Sekunden. Innerhalb eines Werktags habt ihr konkrete Vorschläge, kostenlos und unverbindlich.',
	'anfrage_url'   => $cf_anfrage_quick,
	'links_html'    => $cf_cta_links,
] );
?>

<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>

<script>
document.querySelectorAll('.fge-page .faq-q[aria-expanded]').forEach(function (btn) {
	btn.addEventListener('click', function () {
		var item = btn.closest('.faq-item');
		var open = item.classList.toggle('open');
		btn.setAttribute('aria-expanded', open ? 'true' : 'false');
	});
});
/* Sanftes Einblenden der Sektionen beim Scrollen (wie Stadt-Seite): greift nur
   unter html.cty-io, ohne JS oder mit reduzierter Bewegung bleibt alles sichtbar. */
(function () {
	if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) { return; }
	document.documentElement.classList.add('cty-io');
	var io = new IntersectionObserver(function (entries) {
		entries.forEach(function (e) {
			if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); }
		});
	}, { rootMargin: '0px 0px -8% 0px' });
	document.querySelectorAll('.cty-reveal').forEach(function (el) { io.observe(el); });
})();
</script>

<?php get_footer();
