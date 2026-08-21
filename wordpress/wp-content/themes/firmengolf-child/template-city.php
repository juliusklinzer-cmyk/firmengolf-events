<?php
/**
 * SEO city landing — rendered for /golf-events/<stadt>/ via city-landing.php.
 * Conversion-Umbau 2026-08-20 (Testseite für Google-Ads-Traffic, Julius):
 * Anfrage als Primäraktion überall, Hero mit integrierten Fakten statt Zahlenband,
 * Events mit Preisen direkt nach dem Hero, Story-Sektion (Intro + Vorteile + Bild)
 * statt Kärtchen-Reihe, Formate als Foto-Bento mit Mobile-Karussell, FAQ als Karten.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$slug   = (string) get_query_var( 'fge_city' );
$cities = function_exists( 'fge_get_cities' ) ? fge_get_cities() : [];
$city   = $cities[ $slug ] ?? null;
if ( ! $city ) {
	wp_safe_redirect( home_url( '/' ) );
	exit;
}

$city_name   = $city['name'];
$city_region = $city['region'];
$canonical   = home_url( '/golf-events/' . $slug . '/' );
$seo_title   = 'Firmen-Golfevents in ' . $city_name . ': Teamevents & Turniere | Firmengolf';
$seo_desc    = 'Firmenevents auf Golfplätzen in ' . $city_name . ': Teamevents, Firmenturniere, Platzreife und individuelle Events. Eine Anfrage, ein Ansprechpartner, eine Rechnung.';
$events_url  = (string) get_post_type_archive_link( 'firmengolf_event' );
$ind_url     = ( $p = get_page_by_path( 'individuelle-events' ) ) ? (string) get_permalink( $p->ID ) : home_url( '/individuelle-events/' );
$anfrage_url = add_query_arg( 'anfrage', 'quick', $ind_url );

// „Alle Events ansehen" springt in die Liste MIT vorgefilterter Stadt (Julius, 2026-08-20):
// Umkreissuche der Events-Seite (loc/lat/lng/radius), 100 km, damit der Großraum
// (z. B. Tegernsee ab München) sicher in den Treffern liegt.
$city_coords     = function_exists( 'fge_city_coords' ) ? ( fge_city_coords()[ $slug ] ?? null ) : null;
$events_url_city = $events_url;
if ( $city_coords ) {
	$events_url_city = add_query_arg( [
		'loc'    => $city_name,
		'lat'    => round( $city_coords[0], 4 ),
		'lng'    => round( $city_coords[1], 4 ),
		'radius' => 100,
	], $events_url );
}

// City FAQ: aus der Stadt-Config (eigener Inhalt), sonst generischer Fallback.
$faqs = ! empty( $city['faqs'] ) ? $city['faqs'] : [
	[ 'q' => 'Welche Golfplätze gibt es für Firmenevents in ' . $city_name . '?', 'a' => 'Wir arbeiten mit ausgewählten Partnerplätzen in der Region ' . $city_region . ' zusammen, von der Übungsanlage für Einsteigende bis zur 18-Loch-Anlage für Firmenturniere.' ],
	[ 'q' => 'Müssen unsere Mitarbeitenden Golf spielen können?', 'a' => 'Nein. Unsere Teamevents enthalten immer einen Schnupper- und Grundlagenteil, Golflehrer vor Ort, Schläger werden gestellt.' ],
	[ 'q' => 'Wie schnell bekommen wir eine Antwort?', 'a' => 'Innerhalb eines Werktags meldet sich ein persönlicher Ansprechpartner mit passenden Optionen für ' . $city_name . '.' ],
	[ 'q' => 'Wie wird abgerechnet?', 'a' => 'Eine Sammelrechnung von Firmengolf mit allen Posten, einfach für HR und Buchhaltung.' ],
];

// Events der lokalen Partnerplätze (Stadt → Platz-Standort), Fallback verhindert leere Seite.
$city_events_all   = function_exists( 'fge_city_events' ) ? fge_city_events( $city, 99 ) : [];
$city_events_count = count( $city_events_all );
$city_events       = array_slice( $city_events_all, 0, 4 ); // eine saubere 4er-Reihe im Grid
if ( empty( $city_events ) ) {
	$city_events = fge_get_featured_events( 4 );
}

// ── Fakten für Hero und Events-Kopf (Julius, 2026-08-20) ─────────────────────
// Golfplätze im Großraum (60 km, DGV-Verzeichnis) als Hero-Zeile; Events-Zahl
// im Großraum wandert in die Events-Sektion; Einstiegspreis aus echten Angeboten.
$gp_coords = $city_coords;

$stat_courses = 0;
if ( $gp_coords && function_exists( 'fge_verzeichnis_nearby' ) ) {
	$stat_courses = count( fge_verzeichnis_nearby( $gp_coords[0], $gp_coords[1], 60, 500 ) );
}

$stat_events = function_exists( 'fge_city_stat_events_count' ) ? fge_city_stat_events_count( $city ) : $city_events_count;

// Einstiegspreis: dieselbe Anzeige-Logik wie die Event-Karten (fge_event_pricing,
// netto inkl. Marge, geglättet), nur Pro-Person-Preise, damit „ab X € pro Person" stimmt.
$min_price = 0.0;
if ( function_exists( 'fge_event_pricing' ) ) {
	foreach ( $city_events_all as $mp_ev ) {
		$mp_pricing = fge_event_pricing( $mp_ev->ID );
		$mp         = (float) ( $mp_pricing['gross'] ?? 0 );
		if ( $mp > 0 && 'pro Person' === ( $mp_pricing['unit'] ?? '' ) && ( $min_price <= 0 || $mp < $min_price ) ) {
			$min_price = $mp;
		}
	}
}

// Story-Bild („Raus aus dem Büro"): stadtspezifisch aus der Config, sonst generisch.
$story_img     = ! empty( $city['story_img'] ) ? $city['story_img'] : 'work-life-balance-golf.jpg';
$story_img_alt = ! empty( $city['story_img_alt'] ) ? $city['story_img_alt'] : 'Golf nach Feierabend statt Büro';

// Icon-Set für Story-Punkte und Fakten.
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
			'board'    => '<rect x="3" y="4" width="18" height="12" rx="1"/><path d="M12 16v4M8 20h8"/><path d="M7 8h10M7 11h6"/>',
			'pin'      => '<path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
			'tag'      => '<path d="M20.6 13.4 12 22 2 12V2h10l8.6 8.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
		];
		$d = $p[ $name ] ?? $p['flag'];
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
	}
}

// ── SEO head injection ────────────────────────────────────────────────────────
add_filter( 'pre_get_document_title', static fn() => $seo_title );
add_action( 'wp_head', static function () use ( $seo_title, $seo_desc, $canonical, $city_name, $faqs ) {
	$GLOBALS['fge_seo_meta_done'] = true;
	echo '<meta name="description" content="' . esc_attr( $seo_desc ) . '">' . "\n";
	echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
	echo '<meta property="og:type" content="website">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $seo_title ) . '">' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $seo_desc ) . '">' . "\n";
	echo '<meta property="og:url" content="' . esc_url( $canonical ) . '">' . "\n";
	$city_og_img = function_exists( 'fge_default_og_image_url' ) ? fge_default_og_image_url() : '';
	if ( $city_og_img ) { echo '<meta property="og:image" content="' . esc_url( $city_og_img ) . '">' . "\n"; }

	$graph = [
		[
			'@context'    => 'https://schema.org',
			'@type'       => 'Service',
			'serviceType' => 'Firmen-Golfevents',
			'name'        => 'Firmengolf ' . $city_name,
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
				[ '@type' => 'ListItem', 'position' => 2, 'name' => 'Firmenevents ' . $city_name ],
			],
		],
	];
	echo '<script type="application/ld+json">' . wp_json_encode( $graph ) . '</script>' . "\n";
} );

get_header();
?>
<div class="fge-page fmt-lp cty-page" id="fge-main" role="main" tabindex="-1">

<?php
// Mobile-Bar bewusst OHNE fixierte Anfrage-Pill (Julius, 2026-08-20): die Seite
// trägt durchgehend eigene CTAs, die Bar zeigt nur die Icon-Tabs.
get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => '' ] );
?>

<?php /* Hero: Bild, Botschaft, Anfrage. Die Fakten leben als leichte Zeile IM Hero,
	     das frühere Karten-Band ist Geschichte (Julius, 2026-08-20). */ ?>
<section class="ev-hero" aria-label="Golf-Events in <?php echo esc_attr( $city_name ); ?>">
	<?php
	// Stadtspezifisches Hero-Bild (stadt-<slug>.jpg), sonst generisches Panorama.
	$city_hero = 'stadt-' . $slug . '.jpg';
	if ( ! defined( 'FGE_DIR' ) || ! file_exists( FGE_DIR . 'assets/imagery/' . $city_hero ) ) {
		$city_hero = 'golfplatz-panorama.jpg';
	}
	?>
	<div class="ev-hero-photo" style="background-image:url('<?php echo esc_url( fge_get_placeholder_image_url( $city_hero ) ); ?>')">
		<div class="ev-hero-scrim" aria-hidden="true"></div>
		<div class="ev-hero-content">
			<h1 class="ev-hero-title">Firmen-Golfevents in <em class="mk-italic"><?php echo esc_html( $city_name ); ?></em>.</h1>
			<p class="ev-hero-sub">
				Teamevents, Firmenturniere, Platzreife und individuelle Events auf Partnerplätzen rund um
				<?php echo esc_html( $city_name ); ?>. Eine Anfrage, ein Ansprechpartner, eine Rechnung.
			</p>
			<div class="ev-hero-ctas">
				<a class="fg-btn-brand" href="<?php echo esc_url( $anfrage_url ); ?>">Event anfragen</a>
				<a class="fg-btn-ghost-light" href="#angebote">Events ansehen</a>
			</div>
			<?php if ( $stat_courses > 0 || $min_price > 0 ) : ?>
			<div class="cty-hero-facts">
				<?php if ( $stat_courses > 0 ) : ?>
				<span class="cty-hero-fact"><?php echo fge_city_ico( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><strong><?php echo esc_html( (string) $stat_courses ); ?></strong>&nbsp;Golfplätze im Großraum <?php echo esc_html( $city_name ); ?></span>
				<?php endif; ?>
				<?php if ( $min_price > 0 ) : ?>
				<span class="cty-hero-fact"><?php echo fge_city_ico( 'tag' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>Events ab&nbsp;<strong><?php echo esc_html( number_format_i18n( $min_price, 0 ) ); ?>&nbsp;€</strong>&nbsp;pro Person</span>
				<?php endif; ?>
			</div>
			<?php endif; ?>
		</div>
	</div>
</section>

<?php /* Events in der Region: dieselben Karten wie auf der Events-Seite, direkt nach dem Hero */ ?>
<?php if ( ! empty( $city_events ) ) : ?>
<section class="mk-section cty-reveal" id="angebote" aria-label="Events rund um <?php echo esc_attr( $city_name ); ?>">
	<div class="mk-section-head between">
		<div>
			<h2 class="mk-h2">Beliebte Events rund um <?php echo esc_html( $city_name ); ?>.</h2>
			<p class="mk-sub"><?php echo $stat_events > 4 ? esc_html( $stat_events . ' Events im Großraum, feste' ) : 'Feste'; ?> Pakete mit transparentem Preis pro Person, direkt anfragbar.</p>
		</div>
		<a class="fg-btn-ghost" href="<?php echo esc_url( $events_url_city ); ?>">Alle <?php echo $stat_events > 4 ? esc_html( (string) $stat_events ) . ' ' : ''; ?>Events ansehen →</a>
	</div>
	<div class="fg-grid ev-grid4">
		<?php foreach ( $city_events as $ev ) {
			// Karte v2 (seit 2026-08-21 Standard auf allen Seiten). Revert: 'template-parts/fge-event-card'.
			get_template_part( 'template-parts/fge-event-card-v2', null, [ 'id' => (int) $ev->ID, 'dist' => null ] );
		} ?>
	</div>
</section>
<?php endif; ?>

<?php /* Story: Intro + Vorteile + Bild in EINER editorialen Sektion (statt Kärtchen-Reihe) */ ?>
<section class="mk-section cty-story cty-reveal" aria-label="Warum Golf-Events in <?php echo esc_attr( $city_name ); ?>">
	<div class="cty-story-grid">
		<div class="cty-story-copy">
			<h2 class="mk-h2">Raus aus dem Büro, rein ins Grüne.</h2>
			<p class="cty-story-intro"><?php echo esc_html( $city['intro'] ); ?></p>
			<?php if ( ! empty( $city['reasons'] ) ) : ?>
			<ul class="cty-story-points">
				<?php foreach ( $city['reasons'] as $r ) : ?>
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
			<img src="<?php echo esc_url( fge_get_placeholder_image_url( $story_img ) ); ?>"
			     alt="<?php echo esc_attr( $story_img_alt ); ?>" loading="lazy"
			     <?php if ( ! empty( $city['story_img_pos'] ) ) : ?>style="object-position:<?php echo esc_attr( $city['story_img_pos'] ); ?>"<?php endif; ?>>
			<?php if ( ! empty( $city['story_img_tag'] ) ) : ?>
			<figcaption class="cty-story-tag"><?php echo fge_city_ico( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo esc_html( $city['story_img_tag'] ); ?></figcaption>
			<?php endif; ?>
		</figure>
	</div>
</section>

<?php /* Format×Stadt-Spokes als Foto-Bento (nur scharf geschaltete Städte): erste Kachel
	     breit, mobil ein Wisch-Karussell statt sieben Karten übereinander. */ ?>
<?php if ( function_exists( 'fge_citformat_enabled_cities' ) && in_array( $slug, fge_citformat_enabled_cities(), true ) && function_exists( 'fge_citformat_format_meta' ) ) : ?>
<section class="mk-section cty-reveal" aria-label="Formate in <?php echo esc_attr( $city_name ); ?>">
	<div class="mk-section-head">
		<h2 class="mk-h2">Das passende Format für euer Team.</h2>
		<p class="mk-sub">Jedes Format mit eigener Seite: was drinsteckt, für wen es passt und was es kostet.</p>
	</div>
	<div class="cty-fmt-bento">
		<?php
		// Kachel-Bilder: dieselben kuratierten Motive wie die Format-Landingpages.
		// Incentive weicht ab: dessen Alpen-Motiv ist dieselbe Garmisch-Szene wie das
		// Story-Bild dieser Seite, daher hier das Abendlicht-Panorama (kein Dubletten-Motiv).
		$fmt_pages        = function_exists( 'fge_get_event_format_pages' ) ? fge_get_event_format_pages() : [];
		$fmt_img_override = [ 'incentive' => 'golfplatz-huegel-abendlicht.jpg' ];
		$fmt_i            = 0;
		foreach ( fge_citformat_format_meta() as $fslug => $fm ) :
			$fmt_title = trim( (string) strtok( sprintf( $fm['eyeb'], $city_name ), '·' ) );
			$fmt_img   = $fmt_img_override[ $fslug ] ?? ( $fmt_pages[ $fslug ]['hero_img'] ?? 'golfplatz-panorama.jpg' );
		?>
			<a class="cty-fmt-tile<?php echo 0 === $fmt_i ? ' is-wide' : ''; ?>" href="<?php echo esc_url( home_url( '/golf-events/' . $slug . '/' . $fslug . '/' ) ); ?>">
				<img src="<?php echo esc_url( fge_get_placeholder_image_url( $fmt_img ) ); ?>" alt="" loading="lazy">
				<span class="cty-fmt-tile-scrim" aria-hidden="true"></span>
				<span class="cty-fmt-tile-txt">
					<span class="cty-fmt-tile-h"><?php echo esc_html( $fmt_title ); ?></span>
					<span class="cty-fmt-tile-p"><?php echo esc_html( sprintf( $fm['desc'], $city_name ) ); ?></span>
					<span class="cty-fmt-tile-go"><?php echo esc_html( $fmt_title ); ?> in <?php echo esc_html( $city_name ); ?> ansehen
						<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
					</span>
				</span>
			</a>
		<?php $fmt_i++; endforeach; ?>
	</div>
</section>
<?php endif; ?>

<?php /* Founder-Band: echter Ansprechpartner statt anonymem Formular (Vertrauensanker) */ ?>
<section class="cty-founder" aria-label="Euer Ansprechpartner">
	<div class="cty-founder-inner cty-reveal">
		<div class="cty-founder-photo">
			<img src="<?php echo esc_url( fge_get_placeholder_image_url( 'gruender-julius-klinzer.jpg' ) ); ?>"
			     alt="Julius Klinzer, Gründer von Firmengolf" loading="lazy">
		</div>
		<div class="cty-founder-body">
			<h2 class="mk-h2">Ihr fragt an, ich kümmere mich um den Rest.</h2>
			<p class="cty-founder-p">
				Ich bin Julius, Gründer von Firmengolf. Ich kenne die Partnerplätze rund um
				<?php echo esc_html( $city_name ); ?> persönlich und stelle euch ein Event zusammen,
				das zu Team, Anlass und Budget passt. Innerhalb eines Werktags habt ihr konkrete
				Vorschläge für Platz, Format und Termin auf dem Tisch.
			</p>
			<div class="cty-founder-ctas">
				<a class="fg-btn-brand" href="<?php echo esc_url( $anfrage_url ); ?>">Event anfragen</a>
				<a class="cty-founder-mail" href="mailto:julius@firmengolf-events.de">julius@firmengolf-events.de</a>
			</div>
		</div>
	</div>
</section>

<?php
/* Golfplatz-Verzeichnis: alle Anlagen im Umkreis (Fakten-Liste, SEO + Orientierung).
   Partnerplätze bekommen ein Badge und stehen zuerst; bewusst KEINE Links auf Nicht-Partner
   (kein Link-Out-Spam) und noch keine Partner-Links (Golfplatz-Seiten erst öffentlich,
   wenn Partner-Events live sind). Liste eingeklappt, damit die Seite zum Ziel führt,
   der volle Inhalt bleibt für SEO im DOM. */
$gp_nearby = ( $gp_coords && function_exists( 'fge_verzeichnis_nearby' ) )
	? fge_verzeichnis_nearby( $gp_coords[0], $gp_coords[1], 60, 18 )
	: [];
// Partnerplätze zuerst, innerhalb der Gruppen nach Entfernung (stabile Sortierung).
if ( ! empty( $gp_nearby ) ) {
	usort( $gp_nearby, static function ( $a, $b ) {
		$pa = (int) $b->ist_partner <=> (int) $a->ist_partner;
		return 0 !== $pa ? $pa : ( $a->dist <=> $b->dist );
	} );
}
?>
<?php
// Vorauswahl fürs Panel: der nächstgelegene Partnerplatz (Liste ist partner-first
// sortiert), Fallback erster Eintrag. Foto = Hero-Bild des Partner-Profils.
$gp_sel       = $gp_nearby[0] ?? null;
$gp_sel_photo = '';
if ( $gp_sel && 1 === (int) $gp_sel->ist_partner && (int) ( $gp_sel->partner_id ?? 0 ) > 0 ) {
	$gp_sel_aid = (int) get_post_meta( (int) $gp_sel->partner_id, '_fge_hero_image_attachment_id', true );
	if ( $gp_sel_aid ) {
		$gp_sel_photo = (string) wp_get_attachment_image_url( $gp_sel_aid, 'medium_large' );
	}
}
$gp_holes_label = static function ( $raw ) {
	// „Löcher" nur an reine Zahlwerte hängen („18+9"), Freitexte („9-Loch Kurzplatz") unverändert.
	if ( '' === (string) $raw ) { return ''; }
	return preg_match( '/^[0-9+\/\s]+$/', (string) $raw ) ? $raw . ' Löcher' : (string) $raw;
};
?>
<?php if ( ! empty( $gp_nearby ) && $gp_sel ) : ?>
<section class="mk-section gpd-section cty-reveal" aria-label="Golfplätze rund um <?php echo esc_attr( $city_name ); ?>">
	<div class="mk-section-head">
		<h2 class="mk-h2">Golfplätze rund um <?php echo esc_html( $city_name ); ?>.</h2>
		<p class="mk-sub">
			<?php echo (int) count( $gp_nearby ); ?> Anlagen im Umkreis von 60 km. Tippt einen Pin oder
			einen Platz an, unten seht ihr die Details. <strong>Partnerplätze</strong> gehören zum
			Firmengolf-Netz, auf allen anderen organisieren wir Events auf Anfrage.
		</p>
	</div>
	<div class="gpx-layout">
		<?php if ( function_exists( 'fge_gmaps_api_key' ) && fge_gmaps_api_key() !== '' ) : ?>
		<div class="gpd-map" id="fge-city-map">
			<div class="gpd-map-consent">
				<p>Die Karte lädt erst nach deiner Einwilligung für Google&nbsp;Maps.</p>
				<button type="button" class="fg-btn-brand" onclick="if(window.klaro){window.klaro.show()}">Karte aktivieren</button>
			</div>
		</div>
		<?php endif; ?>
		<aside class="gpx-side">
			<?php $gp_sel_is_partner = 1 === (int) $gp_sel->ist_partner; ?>
			<div class="gpx-panel gpx-panel--boot<?php echo $gp_sel_is_partner ? ' gpx-panel--partner' : ''; ?>"
			     id="fge-gpx-panel" data-initial-id="<?php echo (int) $gp_sel->id; ?>" aria-live="polite">
				<div class="gpx-panel-media"<?php echo '' === $gp_sel_photo ? ' hidden' : ''; ?>>
					<img src="<?php echo esc_url( $gp_sel_photo ?: fge_get_placeholder_image_url( 'golfplatz-panorama.jpg' ) ); ?>"
					     alt="<?php echo esc_attr( $gp_sel->name ); ?>" loading="lazy">
				</div>
				<div class="gpx-panel-body">
					<div class="gpx-panel-top">
						<h3 class="gpx-panel-name"><?php echo esc_html( $gp_sel->name ); ?></h3>
						<span class="gpx-panel-badge"<?php echo $gp_sel_is_partner ? '' : ' hidden'; ?>>Partnerplatz</span>
					</div>
					<div class="gpx-panel-meta"><?php
						echo esc_html( $gp_sel->ort . ' · ' . round( $gp_sel->dist ) . ' km' );
						$gp_sel_holes = $gp_holes_label( $gp_sel->loecher );
						echo $gp_sel_holes !== '' ? esc_html( ' · ' . $gp_sel_holes ) : '';
					?></div>
					<p class="gpx-panel-note"><?php echo $gp_sel_is_partner
						? 'Partnerplatz im Firmengolf-Netz: Events hier organisieren wir direkt mit dem Club.'
						: 'Noch kein Partnerplatz: auf Anfrage organisieren wir euer Event auch hier.'; ?></p>
					<a class="fg-btn-brand gpx-panel-cta" href="<?php echo esc_url( $anfrage_url ); ?>">Event hier anfragen</a>
				</div>
			</div>
			<div class="gpx-list" aria-label="Golfplätze auswählen">
				<?php foreach ( $gp_nearby as $gp ) : $gp_is_partner = 1 === (int) $gp->ist_partner; ?>
				<button type="button" class="gpx-item<?php echo (int) $gp->id === (int) $gp_sel->id ? ' is-active' : ''; ?>"
				        data-gpx-id="<?php echo (int) $gp->id; ?>" aria-pressed="<?php echo (int) $gp->id === (int) $gp_sel->id ? 'true' : 'false'; ?>">
					<span class="gpx-item-name"><?php if ( $gp_is_partner ) : ?><span class="gpx-item-p" title="Partnerplatz"></span><?php endif; ?><?php echo esc_html( $gp->name ); ?></span>
					<span class="gpx-item-km"><?php echo esc_html( (string) round( $gp->dist ) ); ?> km</span>
				</button>
				<?php endforeach; ?>
			</div>
		</aside>
	</div>
	<?php /* Mobil: Karten-Karussell unter der Map (Muster der Karten-Apps): Wischen
	         wählt den Platz, die Karte schwenkt mit, Pin-Tipp scrollt zur Karte.
	         Partner-Karten tragen Foto und Badge, alle Namen bleiben im DOM (SEO). */ ?>
	<div class="gpx-carousel" id="fge-gpx-carousel" aria-label="Golfplätze durchblättern">
		<?php
		$gp_photo_of = static function ( $gp ) {
			if ( 1 !== (int) $gp->ist_partner || (int) ( $gp->partner_id ?? 0 ) <= 0 ) { return ''; }
			$aid = (int) get_post_meta( (int) $gp->partner_id, '_fge_hero_image_attachment_id', true );
			return $aid ? (string) wp_get_attachment_image_url( $aid, 'medium_large' ) : '';
		};
		foreach ( $gp_nearby as $gp ) :
			$gp_is_partner = 1 === (int) $gp->ist_partner;
			$gp_photo      = $gp_photo_of( $gp );
			$gp_holes_txt  = $gp_holes_label( $gp->loecher );
		?>
		<article class="gpx-slide<?php echo (int) $gp->id === (int) $gp_sel->id ? ' is-active' : ''; ?><?php echo $gp_is_partner ? ' gpx-slide--partner' : ''; ?>"
		         data-gpx-slide="<?php echo (int) $gp->id; ?>">
			<?php if ( $gp_photo ) : ?>
			<div class="gpx-slide-media"><img src="<?php echo esc_url( $gp_photo ); ?>" alt="<?php echo esc_attr( $gp->name ); ?>" loading="lazy"></div>
			<?php endif; ?>
			<div class="gpx-slide-body">
				<div class="gpx-slide-top">
					<span class="gpx-slide-name"><?php echo esc_html( $gp->name ); ?></span>
					<?php if ( $gp_is_partner ) : ?><span class="gpx-panel-badge">Partnerplatz</span><?php endif; ?>
				</div>
				<div class="gpx-slide-meta"><?php echo esc_html( $gp->ort . ' · ' . round( $gp->dist ) . ' km' . ( $gp_holes_txt !== '' ? ' · ' . $gp_holes_txt : '' ) ); ?></div>
				<?php if ( $gp_is_partner ) : ?>
				<a class="gpx-slide-cta" href="<?php echo esc_url( $anfrage_url ); ?>">Event hier anfragen
					<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg></a>
				<?php else : ?>
				<span class="gpx-slide-hint">Events auf Anfrage über Firmengolf</span>
				<?php endif; ?>
			</div>
		</article>
		<?php endforeach; ?>
	</div>
	<div class="gpd-note">
		<span>Du vertrittst einen dieser Plätze?</span>
		<a class="fg-btn-ghost gpd-note-link" href="<?php echo esc_url( home_url( '/partner-onboarding/' ) ); ?>">Firmengolf-Partner werden →</a>
	</div>
</section>
<?php endif; ?>

<?php /* FAQ: zentrierte Karten-Akkordeons statt Hairline-Liste */ ?>
<section class="mk-section faq-section cty-faq cty-reveal" aria-label="FAQ">
	<div class="cty-faq-head">
		<h2 class="mk-h2">Firmenevents in <?php echo esc_html( $city_name ); ?>, kurz erklärt.</h2>
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

<?php /* CTA mit Putt-Moment (gemeinsamer Baustein, template-parts/fge-putt-cta.php) */
$cty_cta_links = '<span>Auch in:</span>';
foreach ( $cities as $cslug => $c ) {
	if ( $cslug === $slug ) { continue; }
	$cty_cta_links .= '<a href="' . esc_url( home_url( '/golf-events/' . $cslug . '/' ) ) . '">' . esc_html( $c['name'] ) . '</a>';
}
get_template_part( 'template-parts/fge-putt-cta', null, [
	'headline_html' => 'Lasst uns euer Event in ' . esc_html( $city_name ) . ' <em class="mk-italic">planen</em>.',
	'sub'           => 'Schickt uns eure Eckdaten in 30 Sekunden. Innerhalb eines Werktags habt ihr konkrete Vorschläge, kostenlos und unverbindlich.',
	'anfrage_url'   => $anfrage_url,
	'links_html'    => $cty_cta_links,
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
/* Sanftes Einblenden der Sektionen beim Scrollen. Ohne JS oder mit reduzierter
   Bewegung bleibt alles sofort sichtbar (CSS greift nur unter html.cty-io). */
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
