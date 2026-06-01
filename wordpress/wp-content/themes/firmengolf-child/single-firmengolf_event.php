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
$rating         = (float) ( get_post_meta( $post_id, '_fge_rating', true ) ?: 4.9 );
$reviews        = (int) ( get_post_meta( $post_id, '_fge_reviews', true ) ?: 0 );
$spots_left     = (int) ( get_post_meta( $post_id, '_fge_spots_left', true ) ?: 6 );
$thumb_url      = has_post_thumbnail() ? get_the_post_thumbnail_url( $post_id, 'full' ) : fge_get_placeholder_image_url( 'golfplatz-drohnenaufnahme.jpg' );

$format_label = fge_format_event_type( $event_type_raw ) ?: 'Event';

// Price display
if ( $price_label ) {
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

// Side gallery images
$gallery_img_1 = fge_get_placeholder_image_url( 'golf-coaching-gruppe.jpg' );
$gallery_img_2 = fge_get_placeholder_image_url( 'clubhaus-aussenansicht.jpg' );

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
				<?php if ( $reviews > 0 ) : ?>
					<span class="fg-detail-rating">
						<svg width="13" height="13" viewBox="0 0 24 24" fill="#C9B488" style="color:#C9B488;"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>
						<?php echo esc_html( number_format( $rating, 1 ) ); ?>
						<span class="muted">(<?php echo esc_html( (string) $reviews ); ?> Bewertungen)</span>
					</span>
				<?php endif; ?>
			</div>
		</header>

		<?php /* ── Gallery ── */ ?>
		<div class="fg-detail-gallery">
			<div class="fg-gallery-main" style="background-image:url('<?php echo esc_url( $thumb_url ); ?>')">
				<div class="fg-gallery-floating">
					<div class="fg-floating-card">
						<div class="fg-floating-thumb" style="background-image:url('<?php echo esc_url( $gallery_img_1 ); ?>')"></div>
						<div class="fg-floating-body">
							<div class="fg-floating-chip">Nächster freier Termin</div>
							<div class="fg-floating-meta">Auf Anfrage · Plätze verfügbar</div>
						</div>
					</div>
				</div>
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

				<?php /* So läuft der Tag */ ?>
				<?php if ( $description ) : ?>
				<section>
					<div class="fg-section-eyebrow">So läuft der Tag</div>
					<p class="fg-detail-summary"><?php echo esc_html( $description ); ?></p>
				</section>
				<?php endif; ?>

				<?php /* Im Preis enthalten */ ?>
				<?php if ( $leistungen ) : ?>
				<section>
					<div class="fg-section-eyebrow">Im Preis enthalten</div>
					<ul class="fg-includes">
						<?php foreach ( $leistungen as $label ) : ?>
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

				<?php /* Customer quote */ ?>
				<section class="fg-quote">
					<span class="fg-quote-mark">"</span>
					<p>Wir haben mit Firmengolf zum dritten Mal in Folge unseren Team-Tag gebucht. Buchung im Self-Service, Ansprechpartner immer erreichbar, Rechnung sauber.</p>
					<div class="fg-quote-attr">— Sandra Klein, HR-Direktorin · Werkstatt 4</div>
				</section>

			</main>

			<?php /* ── Price Rail ── */ ?>
			<aside class="fg-detail-rail">
				<div class="fg-rail-card">
					<div>
						<div class="fg-rail-price">
							<?php echo esc_html( $price_main ); ?>
							<?php if ( $price_suffix ) : ?><span><?php echo esc_html( $price_suffix ); ?></span><?php endif; ?>
						</div>
						<?php if ( $spots_left > 0 ) : ?>
							<div class="fg-rail-spots" style="margin-top:6px;"><?php echo esc_html( (string) $spots_left ); ?> freie Plätze in den nächsten 30 Tagen</div>
						<?php endif; ?>
					</div>

					<div class="fg-rail-fields">
						<div class="fg-rail-field">
							<div class="fg-cell-label">Wann</div>
							<div class="fg-cell-value">Auf Anfrage</div>
						</div>
						<div class="fg-rail-field">
							<div class="fg-cell-label">Gruppe</div>
							<div class="fg-cell-value"><?php echo esc_html( $guests_str ?: 'Nach Vereinbarung' ); ?></div>
						</div>
					</div>

					<button class="fg-btn-brand block" id="open-modal-btn" type="button">
						Diesen Termin anfragen
					</button>
					<button class="fg-btn-ghost block" type="button">
						Für später merken
					</button>

					<div class="fg-rail-note">Anfrage ist kostenlos. Der Platz antwortet innerhalb von 24 h.</div>

					<div class="fg-rail-host">
						<img src="<?php echo esc_url( fge_get_logo_url() ); ?>" alt="Firmengolf" class="fg-rail-host-photo" style="border-radius:8px;object-fit:contain;padding:4px;background:var(--paper-200);">
						<div>
							<div class="fg-rail-host-name">Gebucht über Firmengolf</div>
							<div class="fg-rail-host-meta">Ein Ansprechpartner · eine Rechnung</div>
						</div>
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
					Großzügige Anlage, Parkplätze direkt am Clubhaus, barrierearmer Zugang.
					Genaue Adresse und Anfahrtsbeschreibung schicken wir mit der Bestätigung.
				</p>
				<div class="evd-poi-grid">
					<div class="evd-poi"><div class="evd-poi-l">Auto</div><div class="evd-poi-v">15 Min. ab Stadtzentrum</div></div>
					<div class="evd-poi"><div class="evd-poi-l">Bahn</div><div class="evd-poi-v">Shuttle ab Hauptbahnhof</div></div>
					<div class="evd-poi"><div class="evd-poi-l">Parken</div><div class="evd-poi-v">Kostenfrei vor Ort</div></div>
					<div class="evd-poi"><div class="evd-poi-l">Hotel</div><div class="evd-poi-v">3 Partnerhotels in 10 Min.</div></div>
				</div>
			</div>
			<div class="evd-map" role="img" aria-label="Ungefähre Lage des Platzes">
				<div class="evd-map-grid"></div>
				<div class="evd-map-pin"></div>
				<div class="evd-map-tag">
					<div class="evd-map-tag-l">Platz</div>
					<div class="evd-map-tag-v"><?php echo esc_html( ( $region ? $region . ' · ' : '' ) . ( $venue ?: get_the_title() ) ); ?></div>
				</div>
			</div>
		</div>
	</section>

	<?php /* ── FAQ ── */ ?>
	<?php
	$faq_items = [
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
	];
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
				<button class="fg-btn-brand" id="fg-step-0-next" type="button">
					Weiter <?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</button>
			</div>
		</div>

		<?php /* Step 1 */ ?>
		<div id="fg-modal-step-1" style="display:none;">
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
				<div class="fg-field fg-field-full">
					<label class="fg-field-label" for="fg-phone">Telefon (optional)</label>
					<input class="fg-input" id="fg-phone" placeholder="+49 …">
				</div>
			</div>
			<div class="fg-modal-foot">
				<button class="fg-btn-ghost" id="fg-step-1-back" type="button">Zurück</button>
				<button class="fg-btn-brand" id="fg-step-1-submit" type="button">
					Anfrage senden <?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</button>
			</div>
		</div>

		<?php /* Step 2 — success */ ?>
		<div id="fg-modal-step-2" class="fg-modal-success" style="display:none;">
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
	var modal    = document.getElementById('fg-request-modal');
	var openBtn  = document.getElementById('open-modal-btn');
	var closeBtn = document.getElementById('fg-modal-close');
	var cancelBtn = document.getElementById('fg-modal-cancel');
	var doneBtn  = document.getElementById('fg-modal-done');

	var step0    = document.getElementById('fg-modal-step-0');
	var step1    = document.getElementById('fg-modal-step-1');
	var step2    = document.getElementById('fg-modal-step-2');
	var head     = document.getElementById('fg-modal-head');
	var title    = document.getElementById('fg-modal-title');
	var sub      = document.getElementById('fg-modal-sub');
	var srail    = [
		document.getElementById('fg-srail-0'),
		document.getElementById('fg-srail-1'),
		document.getElementById('fg-srail-2'),
	];

	function openModal() {
		modal.classList.remove('is-hidden');
		document.body.style.overflow = 'hidden';
	}
	function closeModal() {
		modal.classList.add('is-hidden');
		document.body.style.overflow = '';
	}

	openBtn.addEventListener('click', openModal);
	closeBtn.addEventListener('click', closeModal);
	cancelBtn.addEventListener('click', closeModal);
	doneBtn.addEventListener('click', closeModal);
	modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });

	// Step 0 → 1
	document.getElementById('fg-step-0-next').addEventListener('click', function () {
		step0.style.display = 'none';
		step1.style.display = '';
		title.textContent = 'Fast geschafft — wer ist Ansprechpartner?';
		sub.textContent   = 'Nur damit wir dich erreichen können — keine Werbung, kein Newsletter.';
		srail[1].classList.add('done');
	});

	// Step 1 back
	document.getElementById('fg-step-1-back').addEventListener('click', function () {
		step1.style.display = 'none';
		step0.style.display = '';
		title.textContent = 'Eure Anfrage zu diesem Event';
		sub.textContent   = 'Erzähl uns kurz, was ihr vorhabt. Wir melden uns innerhalb eines Werktags zurück.';
		srail[1].classList.remove('done');
	});

	// Step 1 submit
	document.getElementById('fg-step-1-submit').addEventListener('click', function () {
		var first   = document.getElementById('fg-first-name').value.trim();
		var last    = document.getElementById('fg-last-name').value.trim();
		var email   = document.getElementById('fg-email').value.trim();
		var company = document.getElementById('fg-company').value.trim();
		if (!first || !last || !email || !company) {
			alert('Bitte fülle Vorname, Nachname, E-Mail und Firma aus.');
			return;
		}

		var btn = document.getElementById('fg-step-1-submit');
		btn.disabled = true;
		btn.textContent = 'Wird gesendet …';

		var body = new URLSearchParams({
			action:     'fge_modal_anfrage',
			nonce:      '<?php echo esc_js( $modal_nonce ); ?>',
			event_id:   '<?php echo esc_js( (string) $post_id ); ?>',
			date_pref:  document.getElementById('fg-date-pref').value.trim(),
			group_size: document.getElementById('fg-group-size').value.trim(),
			notes:      document.getElementById('fg-notes').value.trim(),
			first_name: first,
			last_name:  last,
			email:      email,
			company:    company,
			phone:      document.getElementById('fg-phone').value.trim(),
		});

		fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
		})
		.then(function (r) { return r.json(); })
		.then(function (data) {
			if (data.success) {
				document.getElementById('fg-receipt-date').textContent  = document.getElementById('fg-date-pref').value.trim() || '–';
				document.getElementById('fg-receipt-group').textContent = document.getElementById('fg-group-size').value.trim() || '–';
				document.getElementById('fg-receipt-ref').textContent   = data.data.ref || '–';
				step1.style.display = 'none';
				head.style.display  = 'none';
				step2.style.display = '';
				srail[2].classList.add('done');
			} else {
				alert('Es ist ein Fehler aufgetreten. Bitte versuche es erneut.');
				btn.disabled = false;
				btn.innerHTML = 'Anfrage senden';
			}
		})
		.catch(function () {
			alert('Verbindungsfehler. Bitte versuche es erneut.');
			btn.disabled = false;
			btn.innerHTML = 'Anfrage senden';
		});
	});

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
