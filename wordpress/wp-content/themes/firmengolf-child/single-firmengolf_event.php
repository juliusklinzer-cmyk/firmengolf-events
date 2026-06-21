<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

the_post();
$post_id = get_the_ID();
// Sichtbarkeit (Status + Pausieren-Kaskade) erzwingt fge_block_non_approved_events
// per template_redirect → hier ist das Event garantiert öffentlich.

// ── Meta ──────────────────────────────────────────────────────────────────────
$event_type_raw = fge_get_event_meta( $post_id, 'event_type' );
$location       = fge_get_event_meta( $post_id, 'event_location' );
$region         = fge_get_event_meta( $post_id, 'region' );
$p_min          = (int) fge_get_event_meta( $post_id, 'participants_min' );
$p_max          = (int) fge_get_event_meta( $post_id, 'participants_max' );
$duration       = fge_get_event_meta( $post_id, 'duration' );
$description    = fge_get_event_meta( $post_id, 'card_description', get_the_excerpt() );
$price_raw      = (float) get_post_meta( $post_id, '_fge_sale_price_net', true );
$price_label    = get_post_meta( $post_id, '_fge_public_price_label', true );
$leistungen     = fge_get_active_leistungen( $post_id );
$partner_id     = (int) fge_get_event_meta( $post_id, 'assigned_partner_id', 0 );
$partner        = fge_get_partner_info( $partner_id );
// Von Firmengolf selbst organisiertes Event (kein Golfplatz-Partner zugeordnet):
// → Orientierungspreis, Info-Box, Anfahrt als Optionen statt fixer Wegbeschreibung.
$is_self        = ( $partner_id <= 0 );
// Rating: gleiche Quelle wie die Eventsuche — primär Partner-Rating, Fallback Event-Meta.
$rating         = $partner_id ? (float) get_post_meta( $partner_id, '_fge_rating', true ) : 0;
if ( ! $rating ) {
	$rating = (float) fge_get_event_meta( $post_id, 'rating' );
}
$reviews        = (int) fge_get_event_meta( $post_id, 'reviews_count' );
$gallery_ids    = function_exists( 'fge_event_gallery_ids' ) ? fge_event_gallery_ids( $post_id ) : array_filter( array_map( 'absint', explode( ',', (string) get_post_meta( $post_id, '_fge_event_gallery_ids', true ) ) ) );
$thumb_url      = function_exists( 'fge_event_cover_url' ) ? fge_event_cover_url( $post_id, 'full' ) : ( has_post_thumbnail() ? get_the_post_thumbnail_url( $post_id, 'full' ) : fge_get_placeholder_image_url( 'golfplatz-drohnenaufnahme.jpg' ) );

$format_label = fge_format_event_type( $event_type_raw ) ?: 'Event';

// ── Location map: prefer the partner's exact pin (lat/lng from the picker), else its address ──
$p_lat = $partner_id ? (float) get_post_meta( $partner_id, '_fge_latitude', true ) : 0.0;
$p_lng = $partner_id ? (float) get_post_meta( $partner_id, '_fge_longitude', true ) : 0.0;
if ( $p_lat && $p_lng ) {
	$map_query = $p_lat . ',' . $p_lng;
} else {
	$map_parts = [];
	foreach ( [ '_fge_street', '_fge_house_number', '_fge_postal_code', '_fge_city' ] as $mk ) {
		$mv = trim( (string) get_post_meta( $partner_id, $mk, true ) );
		if ( $mv !== '' ) {
			$map_parts[] = $mv;
		}
	}
	$map_query = $map_parts ? implode( ' ', $map_parts ) : trim( $location . ' ' . $region );
}
$map_embed = $map_query !== '' ? 'https://www.google.com/maps?q=' . rawurlencode( $map_query ) . '&output=embed' : '';

// Price display — neues Preismodell (rev. 2) bevorzugt, sonst Altfelder.
$pricing_new = function_exists( 'fge_event_pricing' ) ? fge_event_pricing( $post_id ) : null;
if ( $pricing_new && $pricing_new['gross'] > 0 ) {
	$price_main   = '€' . number_format( $pricing_new['gross'], 0, ',', '.' );
	$price_suffix = $pricing_new['unit'] === 'pro Person' ? ' p.P.' : ' gesamt';
} elseif ( $price_label ) {
	$price_main   = $price_label;
	$price_suffix = '';
} elseif ( $price_raw > 0 ) {
	$price_main   = '€' . number_format( $price_raw, 0, ',', '.' );
	$price_suffix = ' p.P.';
} else {
	$price_main   = 'Auf Anfrage';
	$price_suffix = '';
}
// Orientierungspreis kennzeichnen, wenn von Firmengolf organisiert.
if ( $is_self && $price_main !== 'Auf Anfrage' ) {
	$price_suffix = trim( $price_suffix ) . ' · Orientierung';
}

// Venue string
$venue = $location ?: $region ?: '';

// Partner-Inhalte (Kundenstimme + Anfahrt) und abgeleitete Anzeige-Werte.
$review_quote  = (string) get_post_meta( $partner_id, '_fge_review_quote', true );
$review_author = (string) get_post_meta( $partner_id, '_fge_review_author', true );
$review_role   = (string) get_post_meta( $partner_id, '_fge_review_role', true );
$directions    = (string) get_post_meta( $partner_id, '_fge_directions_text', true );
$pois = function_exists( 'fge_partner_arrival_pois' ) ? fge_partner_arrival_pois( $partner_id ) : [];
$poi_hotel = (string) get_post_meta( $partner_id, '_fge_poi_hotel', true );
if ( $poi_hotel !== '' ) {
	$pois['Hotel'] = $poi_hotel;
}
$price_mode    = (string) get_post_meta( $post_id, '_fge_price_mode', true );
$booking_label = 'gesamt' === $price_mode ? 'Als Paket, alles inklusive' : ( 'einzel' === $price_mode ? 'Einzelpreise' : 'Auf Anfrage' );
// Im Event inkludierte Anfrage-Wünsche (für „✓ inklusive"-Markierung im Anfrage-Modal).
$included_wants = function_exists( 'fge_event_included_wants' ) ? fge_event_included_wants( $post_id ) : [];

// Inhalts-Felder (rev. 2): inkludierte Leistungen + Tagesablauf.
$dayflow_new  = (string) get_post_meta( $post_id, '_fge_event_dayflow', true );
// _fge_event_includes wird mal als Array, mal als Newline-Text gespeichert — robust normalisieren.
$includes_raw = get_post_meta( $post_id, '_fge_event_includes', true );
$includes_new = is_array( $includes_raw ) ? $includes_raw : preg_split( '/\r\n|\r|\n/', (string) $includes_raw );
$includes_new = array_values( array_filter( array_map( 'trim', array_map( 'strval', $includes_new ) ) ) );

// Participants string
if ( $p_min && $p_max ) {
	$guests_str = $p_min . '–' . $p_max . ' Gäste';
} elseif ( $p_max ) {
	$guests_str = 'bis ' . $p_max . ' Gäste';
} elseif ( $p_min ) {
	$guests_str = 'ab ' . $p_min . ' Gäste';
} else {
	$guests_str = '';
}

// "Gut zu wissen" — echte Rahmen-Infos von Event und Platz statt geratener Flags.
$good_tags = [];
$p_infra   = $partner_id ? array_map( 'strval', (array) get_post_meta( $partner_id, '_fge_infra', true ) ) : [];

$ev_days = (array) get_post_meta( $post_id, '_fge_available_weekdays', true );
if ( $ev_days ) {
	$day_short  = [ 'monday' => 'Mo', 'tuesday' => 'Di', 'wednesday' => 'Mi', 'thursday' => 'Do', 'friday' => 'Fr', 'saturday' => 'Sa', 'sunday' => 'So' ];
	$day_labels = [];
	foreach ( $day_short as $dk => $dl ) {
		if ( in_array( $dk, $ev_days, true ) ) {
			$day_labels[] = $dl;
		}
	}
	if ( 7 === count( $day_labels ) ) {
		$good_tags[] = 'Täglich anfragbar';
	} elseif ( [ 'Mo', 'Di', 'Mi', 'Do', 'Fr' ] === $day_labels ) {
		$good_tags[] = 'Anfragbar Mo bis Fr';
	} elseif ( $day_labels ) {
		$good_tags[] = 'Anfragbar ' . implode( ', ', $day_labels );
	}
}
if ( $partner_id ) {
	$lead_days = (int) get_post_meta( $partner_id, '_fge_min_lead_time_days', true );
	if ( $lead_days > 0 ) {
		$good_tags[] = 'Mind. ' . $lead_days . ' Tage Vorlauf';
	}
	$season_lbl = function_exists( 'fge_season_label' ) ? fge_season_label( (string) get_post_meta( $partner_id, '_fge_season', true ) ) : '';
	if ( 'Ganzjährig' === $season_lbl ) {
		$good_tags[] = 'Ganzjährig buchbar';
	} elseif ( $season_lbl !== '' ) {
		$good_tags[] = 'Saison ' . $season_lbl;
	}
	if ( '1' === (string) get_post_meta( $partner_id, '_fge_evening_events_possible', true ) ) {
		$good_tags[] = 'Abend-Events möglich';
	}
	if ( in_array( 'indoor', $p_infra, true ) ) {
		$good_tags[] = 'Indoor-Backup vorhanden';
	}
	if ( in_array( 'barrierefrei', $p_infra, true ) ) {
		$good_tags[] = 'Barrierearme Anlage';
	}
}
if ( $guests_str ) {
	$good_tags[] = $guests_str;
}
if ( $duration ) {
	$good_tags[] = $duration;
}

// "Vor Ort am Platz" — Infrastruktur-Highlights des Golfplatzes mit Kapazitäten.
$onsite = [];
if ( $partner_id && $p_infra && function_exists( 'fge_catalog_infra_groups' ) ) {
	$p_cap        = (array) get_post_meta( $partner_id, '_fge_cap', true );
	$cap_by_infra = [];
	if ( function_exists( 'fge_catalog_cap_rows' ) ) {
		foreach ( fge_catalog_cap_rows() as $cr ) {
			$cn = (int) ( $p_cap[ $cr['key'] ] ?? 0 );
			if ( $cn > 0 ) {
				$cap_by_infra[ (string) $cr['infra'] ] = $cn;
			}
		}
	}
	$infra_names = [];
	foreach ( fge_catalog_infra_groups() as $infra_items ) {
		foreach ( $infra_items as $iid => $il ) {
			$infra_names[ (string) $iid ] = $il;
		}
	}
	// Nur echte Anlagen — Leistungen (Coaching, Catering …) stehen unter „Im Preis enthalten".
	$onsite_priority = [
		'meeting-room', 'conference', 'seminar', 'eventroom', 'restaurant', 'terrace',
		'driving-range', 'trackman', 'toptracer', 'indoor', 'course-18', 'course-9',
		'bar', 'lounge', 'shower', 'wifi',
	];
	foreach ( $onsite_priority as $iid ) {
		if ( count( $onsite ) >= 8 ) {
			break;
		}
		if ( in_array( $iid, $p_infra, true ) && isset( $infra_names[ $iid ] ) ) {
			$onsite[] = [ $infra_names[ $iid ], isset( $cap_by_infra[ $iid ] ) ? 'bis ' . $cap_by_infra[ $iid ] . ' Personen' : '' ];
		}
	}
}

// Side gallery images — from event gallery (attachment IDs) with placeholder fallback
$gallery_urls = [];
foreach ( $gallery_ids as $gid ) {
	$gurl = wp_get_attachment_image_url( $gid, 'large' );
	if ( $gurl ) {
		$gallery_urls[] = $gurl;
	}
}
$gallery_img_1 = $gallery_urls[0] ?? fge_get_placeholder_image_url( 'golf-coaching-gruppe.jpg', $post_id );
$gallery_img_2 = $gallery_urls[1] ?? fge_get_placeholder_image_url( 'clubhaus-aussenansicht.jpg', $post_id );

// Related events
$related_query = new WP_Query( [
	'post_type'      => 'firmengolf_event',
	'post_status'    => 'publish',
	'post__not_in'   => [ $post_id ],
	'posts_per_page' => 3,
	'meta_query'     => [
		[ 'key' => '_fge_event_status', 'value' => 'freigegeben', 'compare' => '=' ],
	],
	'orderby' => 'rand',
] );

// SEO: Title + Meta + OpenGraph. Format-first (z. B. „Team-Golftag mit Coaching in München"),
// damit Event-Singles NICHT mit der City-Landingpage um „Firmenevent Golf [Stadt]" konkurrieren –
// die City-Page besitzt das Head-Keyword, die Singles decken die längeren Format-Suchen ab.
$seo_city  = $location ?: $region;
$seo_title = fge_get_event_meta( $post_id, 'seo_title' );
if ( ! $seo_title ) {
	$seo_base = str_replace( ' · ', ' in ', get_the_title() );
	if ( $seo_city && stripos( $seo_base, (string) $seo_city ) === false ) {
		$seo_base .= ' in ' . $seo_city;
	}
	$seo_title = $seo_base . ' | Firmengolf';
}
$seo_desc = fge_get_event_meta( $post_id, 'meta_description' );
if ( ! $seo_desc ) {
	$seo_base = $description ?: ( $format_label . ' auf dem Golfplatz' . ( $seo_city ? ' in ' . $seo_city : '' ) );
	$seo_tail = ' Jetzt bei Firmengolf anfragen.';
	$seo_body = wp_strip_all_tags( (string) $seo_base );
	$seo_max  = 150 - mb_strlen( $seo_tail );
	if ( mb_strlen( $seo_body ) > $seo_max ) {
		$seo_body = mb_substr( $seo_body, 0, $seo_max );
		$seo_sp   = mb_strrpos( $seo_body, ' ' );
		if ( false !== $seo_sp && $seo_sp > 40 ) {
			$seo_body = mb_substr( $seo_body, 0, $seo_sp );
		}
		$seo_body = rtrim( $seo_body, " ,.;:–-" );
	}
	$seo_desc = $seo_body . $seo_tail;
}
$seo_price = ( $pricing_new && ( $pricing_new['gross'] ?? 0 ) > 0 ) ? (int) round( $pricing_new['gross'] ) : 0;
add_filter( 'pre_get_document_title', function () use ( $seo_title ) { return $seo_title; } );
add_action( 'wp_head', function () use ( $seo_title, $seo_desc, $post_id, $thumb_url, $seo_price ) {
	$GLOBALS['fge_seo_meta_done'] = true;
	echo '<meta name="description" content="' . esc_attr( $seo_desc ) . '">' . "\n";
	echo '<meta property="og:type" content="website">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $seo_title ) . '">' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $seo_desc ) . '">' . "\n";
	echo '<meta property="og:url" content="' . esc_url( get_permalink( $post_id ) ) . '">' . "\n";
	if ( $thumb_url ) { echo '<meta property="og:image" content="' . esc_url( $thumb_url ) . '">' . "\n"; }
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";

	// JSON-LD: Produkt/Angebot (Preis als Rich-Result) + Breadcrumb. Keine erfundenen Bewertungen.
	$product = [
		'@context'    => 'https://schema.org',
		'@type'       => 'Product',
		'name'        => get_the_title( $post_id ),
		'description' => $seo_desc,
		'brand'       => [ '@type' => 'Brand', 'name' => 'Firmengolf' ],
		'url'         => get_permalink( $post_id ),
	];
	if ( $thumb_url ) { $product['image'] = $thumb_url; }
	if ( $seo_price > 0 ) {
		$product['offers'] = [
			'@type'         => 'Offer',
			'price'         => $seo_price,
			'priceCurrency' => 'EUR',
			'availability'  => 'https://schema.org/InStock',
			'url'           => get_permalink( $post_id ),
		];
	}
	$crumbs = [
		'@context'        => 'https://schema.org',
		'@type'           => 'BreadcrumbList',
		'itemListElement' => [
			[ '@type' => 'ListItem', 'position' => 1, 'name' => 'Firmengolf', 'item' => home_url( '/' ) ],
			[ '@type' => 'ListItem', 'position' => 2, 'name' => 'Firmenevents', 'item' => get_post_type_archive_link( 'firmengolf_event' ) ],
			[ '@type' => 'ListItem', 'position' => 3, 'name' => get_the_title( $post_id ) ],
		],
	];
	echo '<script type="application/ld+json">' . wp_json_encode( $product ) . '</script>' . "\n";
	echo '<script type="application/ld+json">' . wp_json_encode( $crumbs ) . '</script>' . "\n";
} );

// AJAX nonce for modal
$modal_nonce = wp_create_nonce( 'fge_modal_anfrage' );

get_header();
?>
<div class="fge-page">

	<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => 'events' ] ); ?>

	<article class="fg-detail">

		<?php /* ── Back link ── */ ?>
		<a href="<?php echo esc_url( get_post_type_archive_link( 'firmengolf_event' ) ); ?>" class="ev-back">← Alle Events</a>

		<?php /* ── Header ── */ ?>
		<header class="fg-detail-header">
			<div class="fg-detail-eyebrow">
				<?php echo esc_html( $format_label ); ?>
				<?php if ( $venue ) : ?> · <?php echo esc_html( $venue ); ?><?php endif; ?>
			</div>
			<h1 class="fg-detail-title"><?php the_title(); ?></h1>
			<div class="fg-detail-meta">
				<?php if ( $venue ) : ?>
					<span>
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
						<?php echo esc_html( $venue ); ?><?php if ( $region && $region !== $venue ) echo ' · ' . esc_html( $region ); ?>
					</span>
				<?php endif; ?>
				<?php if ( $duration ) : ?>
					<span>
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
						<?php echo esc_html( $duration ); ?>
					</span>
				<?php endif; ?>
				<?php if ( $guests_str ) : ?>
					<span>
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
						<?php echo esc_html( $guests_str ); ?>
					</span>
				<?php endif; ?>
				<?php if ( $rating > 0 ) : ?>
					<span class="fg-detail-rating">
						<svg width="13" height="13" viewBox="0 0 24 24" fill="#C9B488" style="color:#C9B488;"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>
						<?php echo esc_html( number_format( $rating, 1 ) ); ?>
						<?php if ( $reviews > 0 ) : ?>
							<span class="muted">(<?php echo esc_html( (string) $reviews ); ?> Bewertungen)</span>
						<?php endif; ?>
					</span>
				<?php endif; ?>
			</div>
		</header>

		<?php /* ── Gallery ── */ ?>
		<div class="fg-detail-gallery">
			<div class="fg-gallery-main" style="background-image:url('<?php echo esc_url( $thumb_url ); ?>')">
			</div>
			<div class="fg-gallery-side">
				<div class="fg-gallery-tile" style="background-image:url('<?php echo esc_url( $gallery_img_1 ); ?>')"></div>
				<div class="fg-gallery-tile" style="background-image:url('<?php echo esc_url( $gallery_img_2 ); ?>')">
					<button class="fg-gallery-more fg-btn-ghost-light">+ alle Fotos</button>
				</div>
			</div>
		</div>

		<?php /* ── Detail Body ── */ ?>
		<div class="fg-detail-body">

			<main class="fg-detail-main">

				<?php /* Info-Box: von Firmengolf organisiert + Orientierungspreis */ ?>
				<?php if ( $is_self ) : ?>
				<div class="fg-selfbox">
					<span class="fg-selfbox-ic" aria-hidden="true"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 11.5v4.5M12 8h.01"/></svg></span>
					<div class="fg-selfbox-t">
						<strong>Von Firmengolf für dich organisiert.</strong>
						Du fragst dieses Event unverbindlich an – wir holen Verfügbarkeit und ein konkretes Angebot beim passenden Golfplatz ein. Der angezeigte Preis ist ein <strong>Orientierungswert</strong> und kann sich im finalen Angebot je nach Platz, Gruppe und Wünschen ändern.
					</div>
				</div>
				<?php endif; ?>

				<?php /* So läuft der Tag — Phasen: Block = Überschrift (Zeile 1) + Text darunter, Blöcke per Leerzeile */ ?>
				<?php
				$dayflow_parts = [];
				if ( $dayflow_new !== '' ) {
					foreach ( preg_split( '/\n\s*\n/', $dayflow_new ) as $df_blk ) {
						$df_lines = preg_split( '/\r?\n/', trim( $df_blk ) );
						$df_h     = trim( (string) array_shift( $df_lines ) );
						$df_t     = trim( implode( ' ', array_map( 'trim', $df_lines ) ) );
						if ( $df_h !== '' ) { $dayflow_parts[] = [ 'h' => $df_h, 't' => $df_t ]; }
					}
				}
				?>
				<?php if ( $dayflow_parts || $description ) : ?>
				<section>
					<div class="fg-section-eyebrow">So läuft der Tag</div>
					<?php if ( $dayflow_parts ) : ?>
						<ol class="fg-dayflow">
							<?php foreach ( $dayflow_parts as $dp ) : ?>
							<li class="fg-dayflow-step">
								<span class="fg-dayflow-dot" aria-hidden="true"></span>
								<div class="fg-dayflow-c">
									<h3 class="fg-dayflow-h"><?php echo esc_html( $dp['h'] ); ?></h3>
									<?php if ( $dp['t'] !== '' ) : ?><p class="fg-dayflow-t"><?php echo esc_html( $dp['t'] ); ?></p><?php endif; ?>
								</div>
							</li>
							<?php endforeach; ?>
						</ol>
					<?php else : ?>
						<p class="fg-detail-summary"><?php echo esc_html( $description ); ?></p>
					<?php endif; ?>
				</section>
				<?php endif; ?>

				<?php /* Im Preis enthalten — ausschließlich die im Editor kuratierten Leistungen */ ?>
				<?php $inc_list = array_values( array_filter( array_map( 'strval', $includes_new ) ) ); ?>
				<?php if ( $inc_list ) : ?>
				<section>
					<div class="fg-section-eyebrow">Im Preis enthalten</div>
					<div class="evd-poi-grid evd-onsite evd-includes">
						<?php foreach ( $inc_list as $label ) : ?>
						<div class="evd-poi evd-include">
							<span class="evd-include-ic" aria-hidden="true"><?php echo function_exists( 'fge_include_icon' ) ? fge_include_icon( $label ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- statische SVGs ?></span>
							<div class="evd-onsite-n"><?php echo esc_html( $label ); ?></div>
						</div>
						<?php endforeach; ?>
					</div>
				</section>
				<?php endif; ?>

				<?php /* Optional zubuchbar — Add-ons (z. B. Meetingraum, Abholservice) */ ?>
				<?php
				$addons_raw = get_post_meta( $post_id, '_fge_event_addons', true );
				$addons     = is_array( $addons_raw ) ? $addons_raw : preg_split( '/\r\n|\r|\n/', (string) $addons_raw );
				$addons     = array_values( array_filter( array_map( 'trim', array_map( 'strval', $addons ) ) ) );
				?>
				<?php if ( $addons ) : ?>
				<section>
					<div class="fg-section-eyebrow">Optional zubuchbar</div>
					<div class="evd-poi-grid evd-onsite evd-includes evd-addons">
						<?php foreach ( $addons as $label ) : ?>
						<div class="evd-poi evd-include evd-addon">
							<span class="evd-include-ic" aria-hidden="true"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></span>
							<div class="evd-onsite-n"><?php echo esc_html( $label ); ?></div>
						</div>
						<?php endforeach; ?>
					</div>
				</section>
				<?php endif; ?>

				<?php /* Gut zu wissen */ ?>
				<?php if ( $good_tags ) : ?>
				<section>
					<div class="fg-section-eyebrow">Gut zu wissen</div>
					<div class="fg-good-grid">
						<?php foreach ( $good_tags as $tag ) : ?>
							<div class="fg-good"><?php echo esc_html( $tag ); ?></div>
						<?php endforeach; ?>
					</div>
				</section>
				<?php endif; ?>

				<?php /* Vor Ort am Platz — Infrastruktur-Highlights des Golfplatzes */ ?>
				<?php if ( $onsite ) : ?>
				<section>
					<div class="fg-section-eyebrow">Vor Ort am Platz</div>
					<div class="evd-poi-grid evd-onsite">
						<?php foreach ( $onsite as $o ) : ?>
						<div class="evd-poi">
							<div class="evd-onsite-n"><?php echo esc_html( $o[0] ); ?></div>
							<?php if ( $o[1] !== '' ) : ?><div class="evd-onsite-c"><?php echo esc_html( $o[1] ); ?></div><?php endif; ?>
						</div>
						<?php endforeach; ?>
					</div>
				</section>
				<?php endif; ?>

				<?php /* Customer quote — nur wenn beim Golfplatz hinterlegt */ ?>
				<?php if ( $review_quote !== '' ) : ?>
				<section class="fg-quote">
					<span class="fg-quote-mark">"</span>
					<p><?php echo esc_html( $review_quote ); ?></p>
					<?php if ( $review_author !== '' || $review_role !== '' ) : ?>
						<div class="fg-quote-attr">— <?php echo esc_html( trim( $review_author . ( $review_role !== '' ? ', ' . $review_role : '' ) ) ); ?></div>
					<?php endif; ?>
				</section>
				<?php endif; ?>

	<?php /* ── Location ── */ ?>
	<section class="evd-location">
		<div class="evd-location-inner">
			<div class="evd-location-info">
				<div class="mk-eyebrow">Anfahrt & Location</div>
				<h2 class="mk-h2" style="font-size:36px;margin-top:8px;"><?php echo esc_html( $is_self ? ( $location ?: $region ?: 'Nach Absprache' ) : ( $venue ?: get_the_title() ) ); ?></h2>
				<?php if ( $is_self ) : ?>
				<p class="evd-location-p">Den genauen Golfplatz wählen wir passend zu Gruppengröße, Termin und Anfahrt – flexibel im Raum <?php echo esc_html( $region ?: $location ?: 'eurer Region' ); ?>. Die Anfahrt hängt vom Platz ab, üblich sind:</p>
				<div class="evd-poi-grid">
					<div class="evd-poi"><div class="evd-poi-l">Lage</div><div class="evd-poi-v">Stadtnahe Golfplätze (ca. 30 Min.)</div></div>
					<div class="evd-poi"><div class="evd-poi-l">ÖPNV</div><div class="evd-poi-v">Mit Öffentlichen erreichbar</div></div>
					<div class="evd-poi"><div class="evd-poi-l">Transfer</div><div class="evd-poi-v">Abholservice möglich</div></div>
				</div>
				<?php else : ?>
				<p class="evd-location-p">
					<?php echo $directions !== '' ? esc_html( $directions ) : 'Genaue Adresse und Anfahrtsbeschreibung schicken wir mit der Bestätigung.'; ?>
				</p>
				<?php endif; ?>
				<?php
				$os_note = ( $partner_id && function_exists( 'fge_partner_offseason_note' ) ) ? fge_partner_offseason_note( $partner_id ) : '';
				if ( $os_note !== '' ) : ?>
				<p class="evd-offseason"><strong>Aktuell Offseason.</strong> <?php echo esc_html( $os_note ); ?>. Anfragen für Termine in der Saison sind jederzeit möglich.</p>
				<?php endif; ?>
				<?php if ( $pois ) : ?>
				<div class="evd-poi-grid">
					<?php foreach ( $pois as $poi_label => $poi_val ) : ?>
						<div class="evd-poi"><div class="evd-poi-l"><?php echo esc_html( $poi_label ); ?></div><div class="evd-poi-v"><?php echo esc_html( $poi_val ); ?></div></div>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
				<?php if ( $partner_id && function_exists( 'fge_partner_is_public' ) && fge_partner_is_public( $partner_id ) ) : ?>
				<a class="fg-btn fg-btn-outline evd-venue-link" href="<?php echo esc_url( get_permalink( $partner_id ) ); ?>" style="margin-top:20px;">
					Mehr zum Golfplatz <?php echo esc_html( $venue ?: get_the_title( $partner_id ) ); ?> →
				</a>
				<?php endif; ?>
			</div>
			<?php if ( $map_embed && ! $is_self ) : ?>
				<iframe class="evd-map-frame" data-name="googlemaps" data-src="<?php echo esc_url( $map_embed ); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen title="Karte: <?php echo esc_attr( $venue ?: get_the_title() ); ?>"></iframe>
			<?php else : ?>
				<div class="evd-map" role="img" aria-label="Ungefähre Lage des Platzes">
					<div class="evd-map-grid"></div>
					<div class="evd-map-pin"></div>
					<div class="evd-map-tag">
						<div class="evd-map-tag-l">Platz</div>
						<div class="evd-map-tag-v"><?php echo esc_html( ( $region ? $region . ' · ' : '' ) . ( $venue ?: get_the_title() ) ); ?></div>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<?php /* ── FAQ ── */ ?>
	<?php
	// FAQ: aus dem Event-Feld (Block = Frage in Zeile 1, Antwort darunter; Blöcke per Leerzeile).
	$faq_raw   = trim( (string) get_post_meta( $post_id, '_fge_faq_content', true ) );
	$faq_items = [];
	if ( $faq_raw !== '' ) {
		foreach ( preg_split( '/\n\s*\n/', $faq_raw ) as $faq_block ) {
			$flines = preg_split( '/\r?\n/', trim( $faq_block ) );
			$fq = trim( (string) array_shift( $flines ) );
			$fa = trim( implode( ' ', array_map( 'trim', $flines ) ) );
			if ( $fq !== '' ) { $faq_items[] = [ 'q' => $fq, 'a' => $fa ]; }
		}
	}
	if ( ! $faq_items ) { $faq_items = [
		[
			'q' => 'Was ist im Preis enthalten?',
			'a' => 'Alle Punkte aus der Liste oben — Coaching, Ausrüstung, Green-Fee, Catering wo angegeben. Was nicht enthalten ist: persönliche Getränke an der Bar, optionale Add-ons (Fotograf, Trophäen), eventuelle Übernachtungen.',
		],
		[
			'q' => 'Können Begleitpersonen mitkommen?',
			'a' => 'Ja — Partner und Familien sind auf den meisten Plätzen willkommen. Sag uns Bescheid, wir sprechen mit dem Platz und melden uns mit Optionen.',
		],
		[
			'q' => 'Wie viel Vorlauf brauchen wir?',
			'a' => 'Für dieses Format empfehlen wir 4–6 Wochen Vorlauf. Kurzfristiger ist oft möglich — frag einfach an, wir prüfen Verfügbarkeit.',
		],
		[
			'q' => 'Was passiert bei Regen?',
			'a' => 'Wir kommunizieren am Vortag, ob das Programm angepasst wird (Indoor-Backup, gekürzte Runde, Verschiebung). Bei kompletter Absage durch den Platz: voller Storno bis 24 h vor Termin.',
		],
		[
			'q' => 'Gibt es einen Dresscode?',
			'a' => 'Smart-Casual. Wir empfehlen Sportschuhe oder Golf-Spikes; Schläger und alles weitere werden gestellt. Keine Krawatten-Pflicht, kein Polo-Zwang.',
		],
	]; }
	$kontakt_url = home_url( '/kontakt/' );
	// JSON-LD FAQPage aus den sichtbaren FAQ (valide, da echter Seiteninhalt) — stark für KI + Google-FAQ.
	$faq_schema = [
		'@context'   => 'https://schema.org',
		'@type'      => 'FAQPage',
		'mainEntity' => array_map( static function ( $f ) {
			return [ '@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => [ '@type' => 'Answer', 'text' => $f['a'] ] ];
		}, $faq_items ),
	];
	echo '<script type="application/ld+json">' . wp_json_encode( $faq_schema ) . '</script>' . "\n";
	?>
	<section class="mk-section faq-section">
		<div class="faq-shell">
			<div class="faq-aside">
				<div class="mk-eyebrow">Häufige Fragen</div>
				<h2 class="mk-h2" style="margin-top:8px;font-size:36px;">Häufige Fragen zu diesem <?php echo esc_html( $format_label ); ?>.</h2>
				<div class="faq-cta">
					<a class="fg-btn-ghost" href="<?php echo esc_url( $kontakt_url ); ?>">
						Etwas anderes fragen <?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</a>
				</div>
			</div>
			<ul class="faq-list">
				<?php foreach ( $faq_items as $i => $faq ) : ?>
					<li class="faq-item" id="faq-ev-<?php echo esc_attr( (string) $i ); ?>">
						<button class="faq-q" type="button" aria-expanded="false">
							<span><?php echo esc_html( $faq['q'] ); ?></span>
							<span class="faq-toggle" aria-hidden="true">+</span>
						</button>
						<div class="faq-a"><?php echo esc_html( $faq['a'] ); ?></div>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</section>

			</main>

			<?php /* ── Price Rail ── */ ?>
			<aside class="fg-detail-rail">
				<div class="fg-rail-card">
					<div>
						<div class="fg-rail-live">
							<span class="fg-live-dot" aria-hidden="true"></span>
							<span>Angebot live seit <?php echo esc_html( get_the_date( 'F Y' ) ); ?></span>
						</div>
						<div class="fg-rail-price">
							<?php echo esc_html( $price_main ); ?>
							<?php if ( $price_suffix ) : ?><span><?php echo esc_html( $price_suffix ); ?></span><?php endif; ?>
						</div>
					</div>

					<div class="fg-rail-fields">
						<div class="fg-rail-field">
							<div class="fg-cell-label">Gruppe</div>
							<div class="fg-cell-value"><?php echo esc_html( $guests_str ?: 'Nach Vereinbarung' ); ?></div>
						</div>
						<div class="fg-rail-field">
							<div class="fg-cell-label">Buchung</div>
							<div class="fg-cell-value"><?php echo esc_html( $booking_label ); ?></div>
						</div>
					</div>

					<button class="fg-btn-brand block" id="open-modal-btn" type="button">
						Dieses Event anfragen
					</button>
					<button class="fg-btn-ghost block" id="fg-share-btn" type="button"
					        data-share-url="<?php echo esc_url( get_permalink() ); ?>"
					        data-share-title="<?php echo esc_attr( get_the_title() ); ?>">
						Event teilen
					</button>

					<div class="fg-rail-note"><?php echo esc_html( $is_self
						? 'Anfrage ist kostenlos und unverbindlich. Sie geht direkt an uns – wir holen Verfügbarkeit und ein konkretes Angebot beim passenden Golfplatz ein und melden uns innerhalb eines Werktags.'
						: 'Anfrage ist kostenlos. Sie geht direkt an den Golfplatz zur Terminfreigabe und an uns — du bekommst eine Antwort innerhalb eines Werktags.' ); ?></div>
				</div>

				<div class="fg-rail-host">
					<img src="<?php echo esc_url( fge_get_placeholder_image_url( 'gruender-julius-klinzer.jpg' ) ); ?>" alt="Julius Klinzer · Firmengolf" class="fg-rail-host-photo">
					<div>
						<div class="fg-rail-host-name">Gebucht über Firmengolf</div>
						<div class="fg-rail-host-meta">Ein Ansprechpartner · eine Rechnung</div>
					</div>
				</div>
			</aside>

		</div><?php /* .fg-detail-body */ ?>

	</article>

	<?php /* ── Related Events ── */ ?>
	<?php if ( $related_query->have_posts() ) : ?>
	<section class="mk-section evd-related">
		<div class="mk-section-head between">
			<div>
				<div class="mk-eyebrow">Auch interessant</div>
				<h2 class="mk-h2" style="font-size:32px;margin-top:4px;">Ähnliche Events</h2>
			</div>
			<a class="fg-btn-ghost" href="<?php echo esc_url( get_post_type_archive_link( 'firmengolf_event' ) ); ?>">
				Alle Events <?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</a>
		</div>
		<div class="fg-grid ev-grid4">
			<?php foreach ( $related_query->posts as $rp ) {
				get_template_part( 'template-parts/fge-event-card', null, [ 'id' => (int) $rp->ID ] );
			} wp_reset_postdata(); ?>
		</div>
	</section>
	<?php endif; ?>

	<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>

<?php /* ── Request Modal ── */ ?>
<div class="fg-modal-scrim is-hidden" id="fg-request-modal" role="dialog" aria-modal="true" aria-label="Event anfragen">
	<div class="fg-modal">
		<button class="fg-modal-close" id="fg-modal-close" aria-label="Schließen" type="button">
			<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
		</button>

		<?php /* Steps 0 + 1 share a header */ ?>
		<div class="fg-modal-head" id="fg-modal-head">
			<div class="fg-detail-eyebrow"><?php echo esc_html( $format_label . ( $venue ? ' · ' . $venue : '' ) ); ?></div>
			<div class="fg-modal-context">
				<span class="fg-modal-context-label">Deine Auswahl</span>
				<div class="fg-modal-context-row">
					<span class="fg-modal-context-chip"><?php echo esc_html( get_the_title() ); ?></span>
					<?php if ( $venue ) : ?><span class="fg-modal-context-chip"><?php echo esc_html( $venue ); ?></span><?php endif; ?>
					<span class="fg-modal-context-chip"><?php echo esc_html( trim( $price_main . $price_suffix ) ); ?></span>
					<?php if ( $guests_str ) : ?><span class="fg-modal-context-chip"><?php echo esc_html( $guests_str ); ?></span><?php endif; ?>
				</div>
			</div>
			<h2 class="fg-modal-title" id="fg-modal-title">Eure Anfrage zu diesem Event</h2>
			<p class="fg-modal-sub" id="fg-modal-sub">Erzähl uns kurz, was ihr vorhabt. Wir melden uns innerhalb eines Werktags zurück.</p>
			<div class="fg-step-rail">
				<div class="fg-step done" id="fg-srail-0"></div>
				<div class="fg-step" id="fg-srail-1"></div>
				<div class="fg-step" id="fg-srail-2"></div>
			</div>
		</div>

		<?php
		/* Step 0 — Mindest-Vorlauf des Platzes begrenzt die wählbaren Termine. */
		$fg_lead_days = $partner_id ? (int) get_post_meta( $partner_id, '_fge_min_lead_time_days', true ) : 0;
		$fg_min_date  = gmdate( 'Y-m-d', current_time( 'timestamp' ) + max( 0, $fg_lead_days ) * DAY_IN_SECONDS );
		// Gäste-Rahmen des Events für die Live-Hinweise zur Gruppengröße.
		if ( $p_min && $p_max ) {
			$fg_group_help = 'Dieses Event ist für ' . $p_min . ' bis ' . $p_max . ' Gäste ausgelegt.';
		} elseif ( $p_max ) {
			$fg_group_help = 'Dieses Event ist für bis zu ' . $p_max . ' Gäste ausgelegt.';
		} elseif ( $p_min ) {
			$fg_group_help = 'Dieses Event ist ab ' . $p_min . ' Gästen buchbar.';
		} else {
			$fg_group_help = '';
		}
		?>
		<div id="fg-modal-step-0">
			<div class="fg-dates">
				<div class="fg-dates-h">Eure Wunschtermine</div>
				<div class="fg-dates-row">
					<div class="fg-field">
						<label class="fg-field-label" for="fg-date-1">1. Wunsch</label>
						<input class="fg-input fg-date" id="fg-date-1" type="date" min="<?php echo esc_attr( $fg_min_date ); ?>">
					</div>
					<div class="fg-field">
						<label class="fg-field-label" for="fg-date-2">2. Wunsch <span class="fg-opt">optional</span></label>
						<input class="fg-input fg-date" id="fg-date-2" type="date" min="<?php echo esc_attr( $fg_min_date ); ?>">
					</div>
					<div class="fg-field">
						<label class="fg-field-label" for="fg-date-3">3. Wunsch <span class="fg-opt">optional</span></label>
						<input class="fg-input fg-date" id="fg-date-3" type="date" min="<?php echo esc_attr( $fg_min_date ); ?>">
					</div>
				</div>
				<span class="fg-field-help">Gib bis zu drei Termine in deiner Wunschreihenfolge an. Wir stimmen sie mit dem Platz ab.<?php if ( $fg_lead_days > 0 ) : ?> Mindestens <?php echo (int) $fg_lead_days; ?> Tage Vorlauf nötig, frühere Termine sind gesperrt.<?php endif; ?></span>
			</div>

			<div class="fg-form-grid">
				<div class="fg-field fg-field-full">
					<label class="fg-field-label" for="fg-group-size">Gruppengröße</label>
					<div class="fg-stepper-wrap">
						<div class="fg-stepper" id="fg-stepper" data-min="<?php echo (int) $p_min; ?>" data-max="<?php echo (int) $p_max; ?>">
							<button type="button" class="fg-stepper-btn" data-step="-1" aria-label="Weniger Gäste">&minus;</button>
							<input class="fg-stepper-input" id="fg-group-size" type="number" inputmode="numeric" readonly value="<?php echo (int) ( $p_min > 0 ? $p_min : 1 ); ?>">
							<button type="button" class="fg-stepper-btn" data-step="1" aria-label="Mehr Gäste">+</button>
						</div>
						<span class="fg-stepper-unit">Gäste</span>
					</div>
					<?php if ( $fg_group_help ) : ?><span class="fg-field-help"><?php echo esc_html( $fg_group_help ); ?></span><?php endif; ?>
				</div>
				<div class="fg-field fg-field-full">
					<label class="fg-field-label" for="fg-experience">Golf-Erfahrung im Team (optional)</label>
					<select class="fg-input" id="fg-experience">
						<option value="">Bitte wählen …</option>
						<option>Überwiegend Anfänger</option>
						<option>Gemischt</option>
						<option>Erfahrene Golfer</option>
						<option>Weiß noch nicht</option>
					</select>
				</div>
				<div class="fg-field fg-field-full">
					<label class="fg-field-label" for="fg-diet">Verpflegung &amp; Diät (optional)</label>
					<input class="fg-input" id="fg-diet" placeholder="z.B. 5× vegetarisch, 1× vegan, Nussallergie">
				</div>
				<div class="fg-field fg-field-full">
					<label class="fg-field-label" for="fg-notes">Weitere Terminwünsche oder Anmerkungen? (optional)</label>
					<textarea class="fg-input" id="fg-notes" rows="3" placeholder="z.B. „auch Juli flexibel", Anlass oder besondere Wünsche …"></textarea>
				</div>
			</div>
			<div class="fg-modal-foot">
				<button class="fg-btn-ghost" id="fg-modal-cancel" type="button">Abbrechen</button>
				<button class="fg-btn-brand" data-modal-next="1" type="button">
					Weiter <?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</button>
			</div>
		</div>

		<?php
		/* Step 1 — Wunsch-Leistungen: Platz-Leistungen (gefiltert) + Firmengolf-Leistungen. */
		$wish_cats   = function_exists( 'fge_request_wish_categories' ) ? fge_request_wish_categories( $partner_id ) : [];
		$cats_platz  = array_values( array_filter( $wish_cats, static function ( $c ) { return $c['source'] === 'platz'; } ) );
		$cats_fg     = array_values( array_filter( $wish_cats, static function ( $c ) { return $c['source'] === 'firmengolf'; } ) );
		// Inkludierte Leistungen: bevorzugt die im Editor kuratierten Chips, sonst
		// Fallback auf die aktiven has_-Leistungen (manche Events haben nur diese).
		$inc_chips = $includes_new;
		if ( empty( $inc_chips ) && function_exists( 'fge_get_active_leistungen' ) ) {
			$inc_chips = array_values( fge_get_active_leistungen( $post_id ) );
		}

		$render_cat = static function ( array $c ): void {
			?>
			<div class="fg-cat" data-cat="<?php echo esc_attr( $c['key'] ); ?>" data-source="<?php echo esc_attr( $c['source'] ); ?>" data-label="<?php echo esc_attr( $c['label'] ); ?>">
				<button type="button" class="fg-cat-head">
					<span class="fg-cat-ic" aria-hidden="true"><?php echo fge_include_icon( $c['label'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- statische SVGs ?></span>
					<span class="fg-cat-l"><?php echo esc_html( $c['label'] ); ?></span>
					<?php if ( ! empty( $c['subs'] ) ) : ?><span class="fg-cat-chev" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg></span><?php endif; ?>
				</button>
				<?php if ( ! empty( $c['subs'] ) ) : ?>
				<div class="fg-cat-subs" hidden>
					<?php foreach ( $c['subs'] as $sub ) : ?>
					<label class="fg-subpill"><input type="checkbox" class="fg-sub-cb" value="<?php echo esc_attr( $sub ); ?>"><span><?php echo esc_html( $sub ); ?></span></label>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
			</div>
			<?php
		};
		?>
		<div id="fg-modal-step-1" style="display:none;">
			<?php if ( $inc_chips ) : ?>
			<div class="fg-incl">
				<div class="fg-incl-h">Im Paket bereits enthalten</div>
				<div class="fg-incl-chips">
					<?php foreach ( $inc_chips as $chip ) : ?>
					<span class="fg-incl-chip"><span class="fg-incl-ic" aria-hidden="true"><?php echo fge_include_icon( $chip ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span><?php echo esc_html( $chip ); ?></span>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endif; ?>

			<p class="fg-wish-intro">Optionale Zusatzleistungen. Was ihr hier auswählt, fragen wir gleich mit an, und der Platz nimmt es ins Angebot auf. Für Details eine Kategorie antippen und aufklappen.</p>

			<div class="fg-wish-group-h">Zusatzleistungen am Platz <span class="fg-wish-group-note">vom Golfplatz</span></div>
			<div class="fg-cat-grid">
				<?php foreach ( $cats_platz as $c ) { $render_cat( $c ); } ?>
			</div>

			<div class="fg-wish-group-h" style="margin-top:22px;">Zusatzleistungen über Firmengolf <span class="fg-wish-group-note">organisieren wir</span></div>
			<div class="fg-cat-grid">
				<?php foreach ( $cats_fg as $c ) { $render_cat( $c ); } ?>
			</div>

			<div class="fg-modal-foot">
				<button class="fg-btn-ghost" data-modal-back="0" type="button">Zurück</button>
				<button class="fg-btn-brand" data-modal-next="2" type="button">
					Weiter <?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</button>
			</div>
		</div>

		<?php /* Step 2 — Kontakt */ ?>
		<div id="fg-modal-step-2" style="display:none;">
			<div class="fg-form-grid">
				<div class="fg-field">
					<label class="fg-field-label" for="fg-first-name">Vorname</label>
					<input class="fg-input" id="fg-first-name" placeholder="Lena">
				</div>
				<div class="fg-field">
					<label class="fg-field-label" for="fg-last-name">Nachname</label>
					<input class="fg-input" id="fg-last-name" placeholder="Hoffmann">
				</div>
				<div class="fg-field">
					<label class="fg-field-label" for="fg-email">E-Mail</label>
					<input class="fg-input" id="fg-email" type="email" placeholder="lena@firma.de">
				</div>
				<div class="fg-field">
					<label class="fg-field-label" for="fg-company">Firma</label>
					<input class="fg-input" id="fg-company" placeholder="Eure Firma GmbH">
				</div>
				<div aria-hidden="true" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;">
					<label for="fg-hp">Bitte dieses Feld leer lassen</label>
					<input id="fg-hp" name="fge_hp" type="text" tabindex="-1" autocomplete="off">
				</div>
				<div class="fg-field">
					<label class="fg-field-label" for="fg-phone">Telefon (optional)</label>
					<input class="fg-input" id="fg-phone" placeholder="+49 …">
				</div>
				<div class="fg-field">
					<label class="fg-field-label" for="fg-city">Standort (Stadt)</label>
					<input class="fg-input" id="fg-city" placeholder="z.B. München">
				</div>
				<div class="fg-field">
					<label class="fg-field-label" for="fg-contact-pref">Bevorzugte Kontaktart</label>
					<select class="fg-input" id="fg-contact-pref">
						<option>E-Mail</option>
						<option>Telefon</option>
						<option>Egal</option>
					</select>
				</div>
			</div>
			<label class="fg-consent" style="display:flex;gap:9px;align-items:flex-start;margin-top:14px;font-size:13px;line-height:1.45;color:var(--ink-700,#4a4a44);">
				<input type="checkbox" id="fg-consent" style="margin-top:3px;flex:0 0 auto;">
				<span>Ich stimme der Verarbeitung meiner Daten zur Bearbeitung der Anfrage gemäß <a href="<?php echo esc_url( home_url( '/datenschutz/' ) ); ?>" target="_blank" rel="noopener">Datenschutzerklärung</a> zu.</span>
			</label>
			<div class="fg-modal-foot">
				<button class="fg-btn-ghost" data-modal-back="1" type="button">Zurück</button>
				<button class="fg-btn-brand" id="fg-modal-submit" type="button">
					Anfrage senden <?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</button>
			</div>
		</div>

		<?php /* Step 3 — success */ ?>
		<div id="fg-modal-step-3" class="fg-modal-success" style="display:none;">
			<div class="fg-success-mark"><?php echo fge_icon_check(); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
			<div class="mk-eyebrow" style="margin-top:12px;">Anfrage eingegangen</div>
			<h2 class="fg-modal-title" style="max-width:360px;margin:6px auto 0;">Wir freuen uns auf euch.</h2>
			<p class="fg-modal-sub" style="margin-top:8px;">Eure Anfrage ist bei uns eingegangen — eine Rückmeldung folgt innerhalb eines Werktags.</p>
			<div class="fg-success-receipt" id="fg-success-receipt">
				<div><span>Format</span><span><?php echo esc_html( $format_label ); ?></span></div>
				<div><span>Platz</span><span><?php echo esc_html( $venue ?: get_the_title() ); ?></span></div>
				<div><span>Datum</span><span id="fg-receipt-date">–</span></div>
				<div><span>Gruppe</span><span id="fg-receipt-group">–</span></div>
				<div><span>Anfrage-Nr.</span><span class="mono" id="fg-receipt-ref">–</span></div>
			</div>
			<div class="fg-modal-foot single">
				<button class="fg-btn-brand" id="fg-modal-done" type="button">Schließen</button>
			</div>
		</div>

	</div>
</div>

<script>
(function () {
	var modal   = document.getElementById('fg-request-modal');
	var openBtn = document.getElementById('open-modal-btn');
	if (modal && openBtn) {
		var head  = document.getElementById('fg-modal-head');
		var title = document.getElementById('fg-modal-title');
		var sub   = document.getElementById('fg-modal-sub');
		var steps = [0, 1, 2, 3].map(function (i) { return document.getElementById('fg-modal-step-' + i); });
		var srail = [0, 1, 2].map(function (i) { return document.getElementById('fg-srail-' + i); });

		var COPY = [
			{ t: 'Wann & wie groß?',            s: 'Erzähl uns kurz, was ihr vorhabt. Wir melden uns innerhalb eines Werktags zurück.' },
			{ t: 'Was wünscht ihr euch?',       s: 'Optionale Zusatzleistungen, die wir gleich mit anfragen und ins Angebot aufnehmen.' },
			{ t: 'Wer ist Ansprechpartner?',    s: 'Nur damit wir euch erreichen können, kein Newsletter, kein Spam.' }
		];

		function show(n) {
			steps.forEach(function (el, i) { if (el) el.style.display = (i === n ? '' : 'none'); });
			if (n < 3) {
				head.style.display = '';
				title.textContent = COPY[n].t;
				sub.textContent   = COPY[n].s;
				srail.forEach(function (d, i) { if (d) d.classList.toggle('done', i <= n); });
			} else {
				head.style.display = 'none';
			}
		}

		function openModal()  { modal.classList.remove('is-hidden'); document.body.style.overflow = 'hidden'; show(0); }
		function closeModal() { modal.classList.add('is-hidden'); document.body.style.overflow = ''; }

		openBtn.addEventListener('click', openModal);
		document.getElementById('fg-modal-close').addEventListener('click', closeModal);
		document.getElementById('fg-modal-cancel').addEventListener('click', closeModal);
		document.getElementById('fg-modal-done').addEventListener('click', closeModal);
		modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });

		modal.querySelectorAll('[data-modal-next]').forEach(function (b) {
			b.addEventListener('click', function () { show(parseInt(b.getAttribute('data-modal-next'), 10)); });
		});
		modal.querySelectorAll('[data-modal-back]').forEach(function (b) {
			b.addEventListener('click', function () { show(parseInt(b.getAttribute('data-modal-back'), 10)); });
		});

		function val(id) { var el = document.getElementById(id); return el ? el.value.trim() : ''; }
		function setText(id, t) { var el = document.getElementById(id); if (el) el.textContent = t; }

		// ISO-Datum (vom Kalender) → „Do, 18.06.2026" für Beleg & Anzeige.
		function fmtDate(iso) {
			if (!iso) return '';
			var p = iso.split('-');
			if (p.length !== 3) return iso;
			var d = new Date(+p[0], +p[1] - 1, +p[2]);
			if (isNaN(d.getTime())) return iso;
			var days = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
			function pad(n) { return n < 10 ? '0' + n : '' + n; }
			return days[d.getDay()] + ', ' + pad(d.getDate()) + '.' + pad(d.getMonth() + 1) + '.' + d.getFullYear();
		}

		// Wunsch-Kategorien: Kopf tippen = auswählen + aufklappen. Detail-Pillen wählen aktiviert die Kategorie automatisch.
		modal.querySelectorAll('.fg-cat').forEach(function (cat) {
			var head = cat.querySelector('.fg-cat-head');
			var subs = cat.querySelector('.fg-cat-subs');
			if (head) {
				head.addEventListener('click', function () {
					var on = cat.classList.toggle('is-on');
					if (subs) { subs.hidden = !on; }
					if (!on && subs) {
						subs.querySelectorAll('input:checked').forEach(function (cb) { cb.checked = false; });
					}
				});
			}
			if (subs) {
				subs.querySelectorAll('.fg-sub-cb').forEach(function (cb) {
					cb.addEventListener('change', function () {
						if (cb.checked) { cat.classList.add('is-on'); }
					});
				});
			}
		});

		// Sammelt die gewählten Wünsche [{source, label}]: Detail-Pillen falls vorhanden, sonst die Kategorie selbst.
		function collectWishes() {
			var out = [];
			modal.querySelectorAll('.fg-cat.is-on').forEach(function (cat) {
				var source = cat.getAttribute('data-source');
				var picked = cat.querySelectorAll('.fg-sub-cb:checked');
				if (picked.length) {
					picked.forEach(function (cb) { out.push({ source: source, label: cb.value }); });
				} else {
					out.push({ source: source, label: cat.getAttribute('data-label') });
				}
			});
			return out;
		}

		// Ganzes Datumsfeld öffnet den Kalender (nicht nur das Icon).
		modal.querySelectorAll('.fg-date').forEach(function (d) {
			d.addEventListener('click', function () {
				if (typeof d.showPicker === 'function') { try { d.showPicker(); } catch (e) {} }
			});
		});

		// Gruppengröße als +/- Stepper im erlaubten Rahmen des Events (Start = Minimum).
		var stepper = document.getElementById('fg-stepper');
		if (stepper) {
			var sinp = document.getElementById('fg-group-size');
			var smin = parseInt(stepper.getAttribute('data-min'), 10) || 0;
			var smax = parseInt(stepper.getAttribute('data-max'), 10) || 0; // 0 = keine Obergrenze
			if (smin < 1) { smin = 1; }
			var bMinus = stepper.querySelector('[data-step="-1"]');
			var bPlus  = stepper.querySelector('[data-step="1"]');
			function sclamp(v) { if (v < smin) { v = smin; } if (smax && v > smax) { v = smax; } return v; }
			function scur() { var m = (sinp.value || '').match(/\d+/); return m ? parseInt(m[0], 10) : smin; }
			function srender(v) {
				sinp.value = v;
				if (bMinus) { bMinus.disabled = (v <= smin); }
				if (bPlus)  { bPlus.disabled  = (smax && v >= smax); }
			}
			srender(sclamp(scur()));
			stepper.querySelectorAll('.fg-stepper-btn').forEach(function (b) {
				b.addEventListener('click', function () {
					srender(sclamp(scur() + (parseInt(b.getAttribute('data-step'), 10) || 0)));
				});
			});
		}

		document.getElementById('fg-modal-submit').addEventListener('click', function () {
			var first = val('fg-first-name'), last = val('fg-last-name'),
			    email = val('fg-email'), company = val('fg-company');
			var consentEl = document.getElementById('fg-consent');
			if (!first || !last || !email || !company) {
				alert('Bitte fülle Vorname, Nachname, E-Mail und Firma aus.');
				return;
			}
			if (!consentEl || !consentEl.checked) {
				alert('Bitte stimme der Datenverarbeitung zu, um die Anfrage zu senden.');
				return;
			}
			var btn = this;
			btn.disabled = true;
			btn.textContent = 'Wird gesendet …';

			var body = new URLSearchParams({
				action:     'fge_modal_anfrage',
				nonce:      '<?php echo esc_js( $modal_nonce ); ?>',
				fge_hp:     val('fg-hp'),
				event_id:   '<?php echo esc_js( (string) $post_id ); ?>',
				date1:      val('fg-date-1'),
				date2:      val('fg-date-2'),
				date3:      val('fg-date-3'),
				group_size: val('fg-group-size'),
				notes:      val('fg-notes'),
				wishes:     JSON.stringify(collectWishes()),
				first_name: first,
				last_name:  last,
				email:      email,
				company:    company,
				phone:      val('fg-phone'),
				city:       val('fg-city'),
				experience: val('fg-experience'),
				diet:       val('fg-diet'),
				contact_pref: val('fg-contact-pref'),
				consent:    consentEl && consentEl.checked ? '1' : ''
			});

			fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString()
			})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (data.success) {
					setText('fg-receipt-date',  fmtDate(val('fg-date-1')) || '–');
					setText('fg-receipt-group', val('fg-group-size') || '–');
					setText('fg-receipt-ref',   (data.data && data.data.ref) || '–');
					show(3);
				} else {
					alert('Es ist ein Fehler aufgetreten. Bitte versuche es erneut.');
					btn.disabled = false;
					btn.textContent = 'Anfrage senden';
				}
			})
			.catch(function () {
				alert('Verbindungsfehler. Bitte versuche es erneut.');
				btn.disabled = false;
				btn.textContent = 'Anfrage senden';
			});
		});
	}

	// Share button (Web Share API → clipboard fallback)
	var shareBtn = document.getElementById('fg-share-btn');
	if (shareBtn) {
		shareBtn.addEventListener('click', function () {
			var url   = shareBtn.getAttribute('data-share-url') || window.location.href;
			var title = shareBtn.getAttribute('data-share-title') || document.title;
			if (navigator.share) {
				navigator.share({ title: title, url: url }).catch(function () {});
				return;
			}
			function feedback() {
				var orig = shareBtn.textContent;
				shareBtn.textContent = 'Link kopiert ✓';
				setTimeout(function () { shareBtn.textContent = orig; }, 1800);
			}
			if (navigator.clipboard) {
				navigator.clipboard.writeText(url).then(feedback, function () { window.prompt('Link kopieren:', url); });
			} else {
				window.prompt('Link kopieren:', url);
			}
		});
	}

	// FAQ accordion
	document.querySelectorAll('.faq-q').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var item = btn.closest('.faq-item');
			var isOpen = item.classList.contains('open');
			document.querySelectorAll('.faq-item').forEach(function (el) {
				el.classList.remove('open');
				el.querySelector('.faq-q').setAttribute('aria-expanded', 'false');
				el.querySelector('.faq-toggle').textContent = '+';
			});
			if (!isOpen) {
				item.classList.add('open');
				btn.setAttribute('aria-expanded', 'true');
				btn.querySelector('.faq-toggle').textContent = '–';
			}
		});
	});
})();
</script>

<?php get_footer(); ?>
