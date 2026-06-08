<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

the_post();
$post_id = get_the_ID();
$status  = get_post_meta( $post_id, '_fge_event_status', true );
if ( $status !== 'freigegeben' ) {
	wp_redirect( get_post_type_archive_link( 'firmengolf_event' ), 302 );
	exit;
}

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
// Rating: gleiche Quelle wie die Eventsuche — primär Partner-Rating, Fallback Event-Meta.
$rating         = $partner_id ? (float) get_post_meta( $partner_id, '_fge_rating', true ) : 0;
if ( ! $rating ) {
	$rating = (float) fge_get_event_meta( $post_id, 'rating' );
}
$reviews        = (int) fge_get_event_meta( $post_id, 'reviews_count' );
$gallery_ids    = array_filter( array_map( 'absint', explode( ',', (string) get_post_meta( $post_id, '_fge_event_gallery_ids', true ) ) ) );
$thumb_url      = has_post_thumbnail() ? get_the_post_thumbnail_url( $post_id, 'full' ) : fge_get_placeholder_image_url( 'golfplatz-drohnenaufnahme.jpg' );

$format_label = fge_format_event_type( $event_type_raw ) ?: 'Event';

// ── Location map: build a Google Maps embed from the assigned partner address ──
$map_parts = [];
foreach ( [ '_fge_street', '_fge_house_number', '_fge_postal_code', '_fge_city' ] as $mk ) {
	$mv = trim( (string) get_post_meta( $partner_id, $mk, true ) );
	if ( $mv !== '' ) {
		$map_parts[] = $mv;
	}
}
$map_query = $map_parts ? implode( ' ', $map_parts ) : trim( $location . ' ' . $region );
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

// Venue string
$venue = $location ?: $region ?: '';

// Partner-Inhalte (Kundenstimme + Anfahrt) und abgeleitete Anzeige-Werte.
$review_quote  = (string) get_post_meta( $partner_id, '_fge_review_quote', true );
$review_author = (string) get_post_meta( $partner_id, '_fge_review_author', true );
$review_role   = (string) get_post_meta( $partner_id, '_fge_review_role', true );
$directions    = (string) get_post_meta( $partner_id, '_fge_directions_text', true );
$pois = array_filter( [
	'Auto'   => (string) get_post_meta( $partner_id, '_fge_poi_car', true ),
	'Bahn'   => (string) get_post_meta( $partner_id, '_fge_poi_train', true ),
	'Parken' => (string) get_post_meta( $partner_id, '_fge_poi_parking', true ),
	'Hotel'  => (string) get_post_meta( $partner_id, '_fge_poi_hotel', true ),
] );
$pricing_mode  = (string) get_post_meta( $post_id, '_fge_pricing_mode', true );
$booking_label = 'package' === $pricing_mode ? 'Als Paket — alles inklusive' : ( 'individual' === $pricing_mode ? 'Einzelpreise' : 'Auf Anfrage' );
// Im Event inkludierte Anfrage-Wünsche (für „✓ inklusive"-Markierung im Anfrage-Modal).
$included_wants = function_exists( 'fge_event_included_wants' ) ? fge_event_included_wants( $post_id ) : [];

// Inhalts-Felder (rev. 2): inkludierte Leistungen + Tagesablauf.
$dayflow_new  = (string) get_post_meta( $post_id, '_fge_event_dayflow', true );
$includes_new = (array) get_post_meta( $post_id, '_fge_event_includes', true );

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

// "Gut zu wissen" tags — derived from meta
$good_tags = [];
if ( $format_label === 'Schnupperkurs' || isset( $leistungen['has_golf_teacher'] ) ) {
	$good_tags[] = 'Einsteigerfreundlich';
}
if ( isset( $leistungen['has_rental_clubs'] ) ) {
	$good_tags[] = 'Schläger gestellt';
}
if ( isset( $leistungen['has_golf_teacher'] ) ) {
	$good_tags[] = 'PGA-Coach inklusive';
}
if ( isset( $leistungen['has_range_balls'] ) ) {
	$good_tags[] = 'Range-Bälle inklusive';
}
if ( isset( $leistungen['has_lunch'] ) || isset( $leistungen['has_dinner'] ) ) {
	$good_tags[] = 'Catering inklusive';
}
if ( isset( $leistungen['has_branding'] ) ) {
	$good_tags[] = 'Branding möglich';
}
if ( $guests_str ) {
	$good_tags[] = $guests_str;
}
if ( $duration ) {
	$good_tags[] = $duration;
}

// Side gallery images — from event gallery (attachment IDs) with placeholder fallback
$gallery_urls = [];
foreach ( $gallery_ids as $gid ) {
	$gurl = wp_get_attachment_image_url( $gid, 'large' );
	if ( $gurl ) {
		$gallery_urls[] = $gurl;
	}
}
$gallery_img_1 = $gallery_urls[0] ?? fge_get_placeholder_image_url( 'golf-coaching-gruppe.jpg' );
$gallery_img_2 = $gallery_urls[1] ?? fge_get_placeholder_image_url( 'clubhaus-aussenansicht.jpg' );

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

// SEO
$seo_title = fge_get_event_meta( $post_id, 'seo_title' );
$seo_desc  = fge_get_event_meta( $post_id, 'meta_description' );
if ( $seo_title ) {
	add_filter( 'pre_get_document_title', function() use ( $seo_title ) { return $seo_title; } );
}
if ( $seo_desc ) {
	add_action( 'wp_head', function() use ( $seo_desc ) {
		echo '<meta name="description" content="' . esc_attr( $seo_desc ) . '">' . "\n";
	} );
}

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

				<?php /* So läuft der Tag — Tagesablauf (rev. 2) bevorzugt, sonst Kurzbeschreibung */ ?>
				<?php if ( $dayflow_new !== '' || $description ) : ?>
				<section>
					<div class="fg-section-eyebrow">So läuft der Tag</div>
					<?php if ( $dayflow_new !== '' ) : ?>
						<div class="fg-detail-summary"><?php echo nl2br( esc_html( $dayflow_new ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
					<?php else : ?>
						<p class="fg-detail-summary"><?php echo esc_html( $description ); ?></p>
					<?php endif; ?>
				</section>
				<?php endif; ?>

				<?php /* Im Preis enthalten — inkludierte Leistungen (rev. 2) bevorzugt, sonst Alt-Leistungen */ ?>
				<?php $inc_list = $includes_new ?: array_values( $leistungen ); ?>
				<?php if ( $inc_list ) : ?>
				<section>
					<div class="fg-section-eyebrow">Im Preis enthalten</div>
					<ul class="fg-includes">
						<?php foreach ( $inc_list as $label ) : ?>
							<li>
								<span class="fg-includes-check"><?php echo fge_icon_check(); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
								<span><?php echo esc_html( $label ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
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

					<div class="fg-rail-note">Anfrage ist kostenlos. Sie geht direkt an den Golfplatz zur Terminfreigabe und an uns — du bekommst eine Antwort innerhalb von 48 Stunden.</div>
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

	<?php /* ── Location ── */ ?>
	<section class="evd-location">
		<div class="evd-location-inner">
			<div class="evd-location-info">
				<div class="mk-eyebrow">Anfahrt & Location</div>
				<h2 class="mk-h2" style="font-size:36px;margin-top:8px;"><?php echo esc_html( $venue ?: get_the_title() ); ?></h2>
				<p class="evd-location-p">
					<?php echo $directions !== '' ? esc_html( $directions ) : 'Genaue Adresse und Anfahrtsbeschreibung schicken wir mit der Bestätigung.'; ?>
				</p>
				<?php if ( $pois ) : ?>
				<div class="evd-poi-grid">
					<?php foreach ( $pois as $poi_label => $poi_val ) : ?>
						<div class="evd-poi"><div class="evd-poi-l"><?php echo esc_html( $poi_label ); ?></div><div class="evd-poi-v"><?php echo esc_html( $poi_val ); ?></div></div>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
			</div>
			<?php if ( $map_embed ) : ?>
				<iframe class="evd-map-frame" src="<?php echo esc_url( $map_embed ); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen title="Karte: <?php echo esc_attr( $venue ?: get_the_title() ); ?>"></iframe>
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
		<div class="fg-grid">
			<?php while ( $related_query->have_posts() ) : $related_query->the_post();
				$rid    = get_the_ID();
				$rtype  = $format_map[ fge_get_event_meta( $rid, 'event_type' ) ] ?? fge_format_event_type( fge_get_event_meta( $rid, 'event_type' ) );
				$rreg   = fge_get_event_meta( $rid, 'region' );
				$rloc   = fge_get_event_meta( $rid, 'event_location' );
				$rprice = fge_get_event_price_display( $rid );
				$rthumb = has_post_thumbnail() ? get_the_post_thumbnail_url( $rid, 'large' ) : fge_get_placeholder_image_url( 'golf-coaching-gruppe.jpg' );
				$rdur   = fge_get_event_meta( $rid, 'duration' );
			?>
			<article class="fg-event">
				<a href="<?php the_permalink(); ?>">
					<div class="fg-event-photo" style="background-image:url('<?php echo esc_url( $rthumb ); ?>')">
						<?php if ( $rtype ) : ?>
							<div class="fg-event-chips"><span class="fg-photo-chip"><?php echo esc_html( $rtype ); ?></span></div>
						<?php endif; ?>
					</div>
					<div class="fg-event-body">
						<?php if ( $rtype ) : ?><span class="fg-type-tag"><?php echo esc_html( $rtype ); ?></span><?php endif; ?>
						<h3 class="fg-event-title"><?php the_title(); ?></h3>
						<div class="fg-event-meta">
							<?php if ( $rloc || $rreg ) : ?>
								<?php echo fge_icon_map_pin(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
								<span><?php echo esc_html( $rloc ?: $rreg ); ?></span>
							<?php endif; ?>
							<?php if ( $rdur ) : ?>
								<?php echo fge_icon_clock(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
								<span><?php echo esc_html( $rdur ); ?></span>
							<?php endif; ?>
						</div>
						<div class="fg-event-foot">
							<?php if ( $rprice ) : ?><span class="fg-event-price"><?php echo esc_html( $rprice ); ?></span><?php endif; ?>
							<span class="fg-event-cta">Details <?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						</div>
					</div>
				</a>
			</article>
			<?php endwhile; wp_reset_postdata(); ?>
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

		<?php /* Step 0 */ ?>
		<div id="fg-modal-step-0">
			<div class="fg-form-grid">
				<div class="fg-field">
					<label class="fg-field-label" for="fg-date-pref">Wann ungefähr?</label>
					<input class="fg-input" id="fg-date-pref" placeholder="z.B. Juli 2026 · KW 28">
					<span class="fg-field-help">Wir prüfen Verfügbarkeit auf benachbarten Plätzen.</span>
				</div>
				<div class="fg-field">
					<label class="fg-field-label" for="fg-group-size">Gruppengröße</label>
					<input class="fg-input" id="fg-group-size" placeholder="z.B. 16 Personen">
				</div>
				<div class="fg-field fg-field-full">
					<label class="fg-field-label" for="fg-notes">Was wäre wichtig?</label>
					<textarea class="fg-input" id="fg-notes" rows="3" placeholder="Anlass, Erfahrungslevel, Wünsche zu Catering oder Programm …"></textarea>
				</div>
			</div>
			<div class="fg-modal-foot">
				<button class="fg-btn-ghost" id="fg-modal-cancel" type="button">Abbrechen</button>
				<button class="fg-btn-brand" data-modal-next="1" type="button">
					Weiter <?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</button>
			</div>
		</div>

		<?php /* Step 1 — Feinschliff & Sonderwünsche */ ?>
		<div id="fg-modal-step-1" style="display:none;">
			<?php if ( $included_wants ) : ?>
				<p class="fg-field-help" style="margin:0 0 12px;">Im Event-Paket bereits enthaltene Leistungen sind als <strong>✓ inklusive</strong> markiert. Wähle zusätzlich, was ihr noch möchtet.</p>
			<?php endif; ?>
			<div class="fg-wants-grid">
				<?php
				$want_opts = [
					'golf_teacher'             => 'Golflehrer / Coaching',
					'meeting_room'             => 'Meetingraum',
					'breakfast'                => 'Frühstück',
					'lunch'                    => 'Mittagessen',
					'dinner'                   => 'Abendessen',
					'shuttle'                  => 'Shuttle-Service',
					'branding'                 => 'Branding / Logo',
					'tournament_mode'          => 'Turniermodus',
					'bad_weather_alternative'  => 'Schlechtwetter-Alternative',
					'individual_customization' => 'Individuelle Anpassung',
				];
				foreach ( $want_opts as $wkey => $wlabel ) :
					$is_inc = in_array( $wkey, $included_wants, true ); ?>
					<label class="fg-want<?php echo $is_inc ? ' is-included' : ''; ?>">
						<input type="checkbox" class="fg-want-cb" value="<?php echo esc_attr( $wkey ); ?>"<?php echo $is_inc ? ' checked disabled data-included="1"' : ''; ?>>
						<span><?php echo esc_html( $wlabel ); ?><?php echo $is_inc ? ' <em class="fg-want-badge">✓ inklusive</em>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					</label>
				<?php endforeach; ?>
			</div>
			<div class="fg-field fg-field-full" style="margin-top:16px;">
				<label class="fg-field-label" for="fg-add-wishes">Weitere Wünsche (optional)</label>
				<textarea class="fg-input" id="fg-add-wishes" rows="2" placeholder="z.B. Fotograf, Welcome-Drink, Kaffee-Bar …"></textarea>
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
					<input class="fg-input" id="fg-company" placeholder="Quartz Labs">
				</div>
				<div class="fg-field">
					<label class="fg-field-label" for="fg-phone">Telefon (optional)</label>
					<input class="fg-input" id="fg-phone" placeholder="+49 …">
				</div>
				<div class="fg-field">
					<label class="fg-field-label" for="fg-city">Standort (Stadt)</label>
					<input class="fg-input" id="fg-city" placeholder="z.B. München">
				</div>
			</div>
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
			<p class="fg-modal-sub" style="margin-top:8px;">Eure Anfrage ist bei uns eingegangen — eine Rückmeldung folgt innerhalb von 24 Stunden.</p>
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
			{ t: 'Feinschliff & Sonderwünsche', s: 'Alles optional — hilft uns, das passende Angebot zu schnüren.' },
			{ t: 'Wer ist Ansprechpartner?',    s: 'Nur damit wir euch erreichen können — kein Newsletter, kein Spam.' }
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

		document.getElementById('fg-modal-submit').addEventListener('click', function () {
			var first = val('fg-first-name'), last = val('fg-last-name'),
			    email = val('fg-email'), company = val('fg-company');
			if (!first || !last || !email || !company) {
				alert('Bitte fülle Vorname, Nachname, E-Mail und Firma aus.');
				return;
			}
			var btn = this;
			btn.disabled = true;
			btn.textContent = 'Wird gesendet …';

			var wants = [];
			modal.querySelectorAll('.fg-want-cb:checked').forEach(function (cb) { wants.push(cb.value); });

			var body = new URLSearchParams({
				action:     'fge_modal_anfrage',
				nonce:      '<?php echo esc_js( $modal_nonce ); ?>',
				event_id:   '<?php echo esc_js( (string) $post_id ); ?>',
				date_pref:  val('fg-date-pref'),
				group_size: val('fg-group-size'),
				notes:      val('fg-notes'),
				wants:      wants.join(','),
				add_wishes: val('fg-add-wishes'),
				first_name: first,
				last_name:  last,
				email:      email,
				company:    company,
				phone:      val('fg-phone'),
				city:       val('fg-city')
			});

			fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString()
			})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (data.success) {
					setText('fg-receipt-date',  val('fg-date-pref')  || '–');
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
