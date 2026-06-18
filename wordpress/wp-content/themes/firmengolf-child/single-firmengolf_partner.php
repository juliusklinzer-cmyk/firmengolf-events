<?php
/**
 * Public golf-course (partner) detail page. Mirrors the portal "Platz" design (.fgpp) so it
 * looks 1:1 like the in-portal view — but with neutral headings, no edit controls, plus the
 * events list + CTA. Visibility gated in fge_block_non_public_partners().
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pid = (int) get_the_ID();
$m   = static fn( string $k ): string => (string) get_post_meta( $pid, '_fge_' . $k, true );

$name      = $m( 'public_golfclub_name' ) ?: get_the_title();
$city      = $m( 'city' );
$state     = $m( 'federal_state' );
$loc       = trim( $city . ( $state ? ', ' . $state : '' ) );
$golf      = $m( 'golf_type' );
$golf_lbl  = $golf && function_exists( 'fge_catalog_golf_types' ) ? ( fge_catalog_golf_types()[ $golf ] ?? $golf ) : '';
$rating    = (float) $m( 'rating' );
$desc      = $m( 'public_short_description' );
$cap       = (array) get_post_meta( $pid, '_fge_cap', true );
$formats   = (array) get_post_meta( $pid, '_fge_event_formats', true );
$season    = $m( 'season' );
$website   = $m( 'website_url' );
$infra     = array_map( 'strval', (array) get_post_meta( $pid, '_fge_infra', true ) );
$gallery   = function_exists( 'fge_partner_gallery_ids' ) ? fge_partner_gallery_ids( $pid ) : [];
$cover_att = function_exists( 'fge_partner_cover_id' ) ? fge_partner_cover_id( $pid ) : 0;
$cover     = $cover_att > 0 ? (string) wp_get_attachment_image_url( $cover_att, '2048x2048' ) : fge_get_placeholder_image_url( 'hero-fairway-wide.jpg', $pid );
$mono      = function_exists( 'fge_portal_make_monogram' ) ? fge_portal_make_monogram( $name ) : strtoupper( mb_substr( $name, 0, 2 ) );
$events    = function_exists( 'fge_partner_public_event_ids' ) ? fge_partner_public_event_ids( $pid ) : [];
$fmt_lbl   = function_exists( 'fge_get_event_formats_flat' ) ? fge_get_event_formats_flat( false ) : [];
$ind_url   = ( $ip = get_page_by_path( 'individuelle-events' ) ) ? get_permalink( $ip->ID ) : home_url( '/individuelle-events/' );

$infra_index = [];
foreach ( fge_catalog_infra_groups() as $group => $items ) {
	foreach ( $items as $id => $label ) {
		$infra_index[ (string) $id ] = [ 'label' => $label, 'group' => $group ];
	}
}

$facts = [];
if ( $golf_lbl ) { $facts[] = [ 'Platztyp', $golf_lbl ]; }
if ( $loc )      { $facts[] = [ 'Standort', $loc ]; }
if ( ! empty( $cap['min'] ) || ! empty( $cap['max'] ) ) {
	$facts[] = [ 'Gruppengröße', trim( ( $cap['min'] ?? '?' ) . '–' . ( $cap['max'] ?? '?' ) . ' Personen' ) ];
}
if ( $formats ) { $facts[] = [ 'Veranstaltungstypen', (string) count( $formats ) ]; }
if ( $season )  { $facts[] = [ 'Saison', function_exists( 'fge_season_label' ) ? fge_season_label( $season ) : $season ]; }
$since = $m( 'partner_since' );
if ( $since )   { $facts[] = [ 'Mitglied seit', preg_match( '/^(\d{4})/', $since, $sm ) ? $sm[1] : $since ]; }
$os_note = function_exists( 'fge_partner_offseason_note' ) ? fge_partner_offseason_note( $pid ) : '';
if ( $os_note ) { $facts[] = [ 'Aktuell', 'Offseason. ' . $os_note ]; }

// SEO: Title + Meta + OpenGraph für die öffentliche Platzseite (lokales Keyword-Muster).
$p_seo_title = $name . ': Firmenevents & Golfturniere' . ( $city ? ' in ' . $city : '' ) . ' | Firmengolf';
$p_seo_desc  = $desc ?: ( 'Firmenevents, Teamevents und Golfturniere bei ' . $name . ( $city ? ' in ' . $city : '' ) . '.' );
$p_seo_tail  = ' Jetzt bei Firmengolf anfragen.';
$p_seo_desc  = rtrim( mb_substr( wp_strip_all_tags( (string) $p_seo_desc ), 0, 150 - mb_strlen( $p_seo_tail ) ) ) . $p_seo_tail;
add_filter( 'pre_get_document_title', function () use ( $p_seo_title ) { return $p_seo_title; } );
add_action( 'wp_head', function () use ( $p_seo_title, $p_seo_desc, $pid, $cover, $name, $city ) {
	$GLOBALS['fge_seo_meta_done'] = true;
	echo '<meta name="description" content="' . esc_attr( $p_seo_desc ) . '">' . "\n";
	echo '<meta property="og:type" content="website">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $p_seo_title ) . '">' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $p_seo_desc ) . '">' . "\n";
	echo '<meta property="og:url" content="' . esc_url( get_permalink( $pid ) ) . '">' . "\n";
	if ( $cover ) { echo '<meta property="og:image" content="' . esc_url( $cover ) . '">' . "\n"; }
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";

	// JSON-LD: GolfCourse mit Adresse + Geo (lokales SEO, Maps, KI). Keine erfundenen Bewertungen.
	$states = [
		'baden_wuerttemberg' => 'Baden-Württemberg', 'bayern' => 'Bayern', 'berlin' => 'Berlin',
		'brandenburg' => 'Brandenburg', 'bremen' => 'Bremen', 'hamburg' => 'Hamburg', 'hessen' => 'Hessen',
		'mecklenburg_vorpommern' => 'Mecklenburg-Vorpommern', 'niedersachsen' => 'Niedersachsen',
		'nordrhein_westfalen' => 'Nordrhein-Westfalen', 'rheinland_pfalz' => 'Rheinland-Pfalz',
		'saarland' => 'Saarland', 'sachsen' => 'Sachsen', 'sachsen_anhalt' => 'Sachsen-Anhalt',
		'schleswig_holstein' => 'Schleswig-Holstein', 'thueringen' => 'Thüringen',
	];
	$street  = trim( (string) get_post_meta( $pid, '_fge_street', true ) . ' ' . (string) get_post_meta( $pid, '_fge_house_number', true ) );
	$addr    = [ '@type' => 'PostalAddress', 'addressCountry' => 'DE' ];
	if ( '' !== $street ) { $addr['streetAddress'] = $street; }
	$postal = (string) get_post_meta( $pid, '_fge_postal_code', true );
	if ( '' !== $postal ) { $addr['postalCode'] = $postal; }
	if ( '' !== (string) $city ) { $addr['addressLocality'] = $city; }
	$st = (string) get_post_meta( $pid, '_fge_federal_state', true );
	if ( isset( $states[ $st ] ) ) { $addr['addressRegion'] = $states[ $st ]; }

	$gc = [
		'@context'    => 'https://schema.org',
		'@type'       => 'GolfCourse',
		'name'        => $name,
		'description' => $p_seo_desc,
		'url'         => get_permalink( $pid ),
		'address'     => $addr,
	];
	if ( $cover ) { $gc['image'] = $cover; }
	$lat = (float) get_post_meta( $pid, '_fge_latitude', true );
	$lng = (float) get_post_meta( $pid, '_fge_longitude', true );
	if ( $lat && $lng ) { $gc['geo'] = [ '@type' => 'GeoCoordinates', 'latitude' => $lat, 'longitude' => $lng ]; }
	$phone = (string) get_post_meta( $pid, '_fge_main_contact_phone', true );
	if ( '' !== $phone ) { $gc['telephone'] = $phone; }
	$web = (string) get_post_meta( $pid, '_fge_website_url', true );
	if ( '' !== $web ) { $gc['sameAs'] = [ $web ]; }

	$crumbs = [
		'@context'        => 'https://schema.org',
		'@type'           => 'BreadcrumbList',
		'itemListElement' => [
			[ '@type' => 'ListItem', 'position' => 1, 'name' => 'Firmengolf', 'item' => home_url( '/' ) ],
			[ '@type' => 'ListItem', 'position' => 2, 'name' => $name ],
		],
	];
	echo '<script type="application/ld+json">' . wp_json_encode( $gc ) . '</script>' . "\n";
	echo '<script type="application/ld+json">' . wp_json_encode( $crumbs ) . '</script>' . "\n";
} );
get_header();
?>
<div class="fge-page">

	<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => 'events' ] ); ?>

	<div class="fgpp">
		<div class="page-wide">

			<section class="hero">
				<div class="hero-photo" style="background-image:url('<?php echo esc_url( $cover ); ?>')">
					<div class="hero-scrim"></div>
					<?php if ( $gallery ) : ?>
						<button type="button" class="fg-btn fg-btn-glass gp-gallery-btn" data-gp-open>
							<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
							Galerie
						</button>
					<?php endif; ?>
					<div class="hero-body">
						<div class="hero-id">
							<?php
							$logo_id  = (int) $m( 'logo_attachment_id' );
							$logo_url = $logo_id > 0 ? (string) wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
							?>
							<?php if ( $logo_url !== '' ) : ?>
								<div class="hero-monogram hero-logo" style="background-image:url('<?php echo esc_url( $logo_url ); ?>')" role="img" aria-label="<?php echo esc_attr( $name ); ?> Logo"></div>
							<?php else : ?>
								<div class="hero-monogram"><?php echo esc_html( $mono ); ?></div>
							<?php endif; ?>
							<div class="hero-text">
								<div class="hero-eyebrow">Golfplatz auf Firmengolf</div>
								<h1 class="hero-name"><?php echo esc_html( $name ); ?></h1>
								<div class="hero-meta">
									<?php if ( $loc ) : ?><span><?php echo esc_html( $loc ); ?></span><?php endif; ?>
									<?php if ( $golf_lbl ) : ?><span class="dot">·</span><span><?php echo esc_html( $golf_lbl ); ?></span><?php endif; ?>
									<?php if ( $events ) : ?><span class="dot">·</span><span><?php echo (int) count( $events ); ?> Event<?php echo count( $events ) === 1 ? '' : 's'; ?></span><?php endif; ?>
								</div>
							</div>
						</div>
						<?php if ( $rating > 0 ) : ?>
							<div class="hero-cta-card"><div class="lbl">Bewertung</div><div class="val"><?php echo esc_html( number_format_i18n( $rating, 1 ) ); ?> ★</div></div>
						<?php endif; ?>
					</div>
				</div>
			</section>

			<section class="section">
				<div class="section-head"><div><div class="eyebrow">Über den Platz</div><h2>Über den <em>Platz</em></h2></div></div>
				<div class="about">
					<div class="about-main">
						<?php if ( $desc !== '' ) : ?>
							<?php foreach ( preg_split( '/\n\s*\n/', trim( $desc ) ) as $para ) : ?><p><?php echo esc_html( trim( $para ) ); ?></p><?php endforeach; ?>
						<?php else : ?>
							<p>Ein Golfplatz, der sich hervorragend für Firmenevents eignet.</p>
						<?php endif; ?>
					</div>
					<?php if ( $facts ) : ?>
						<div class="facts">
							<h4>Eckdaten</h4>
							<?php foreach ( $facts as $f ) : ?><div class="fact-row"><span class="lbl"><?php echo esc_html( $f[0] ); ?></span><span class="val"><?php echo esc_html( $f[1] ); ?></span></div><?php endforeach; ?>
							<?php if ( $website ) : ?><a class="btn btn-ghost btn-sm" style="margin-top:14px;" href="<?php echo esc_url( $website ); ?>" target="_blank" rel="noopener">Website&nbsp;↗</a><?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
			</section>

			<?php $extra_equipment = $m( 'additional_equipment' ); ?>
			<?php if ( $infra || $extra_equipment !== '' ) : ?>
			<section class="section">
				<div class="section-head"><div><div class="eyebrow">Ausstattung</div><h2>Was euch <em>erwartet</em></h2></div></div>
				<?php if ( $infra ) { fge_render_amenities_grid( $pid ); } ?>
				<?php
				// Bereichs-Kapazitäten (aus dem Onboarding) zu den gewählten Anlagen.
				$cap_lines = [];
				if ( function_exists( 'fge_catalog_cap_rows' ) ) {
					foreach ( fge_catalog_cap_rows() as $cr ) {
						$cn = (int) ( $cap[ $cr['key'] ] ?? 0 );
						if ( $cn > 0 && in_array( (string) $cr['infra'], $infra, true ) && isset( $infra_index[ (string) $cr['infra'] ] ) ) {
							$cap_lines[] = $infra_index[ (string) $cr['infra'] ]['label'] . ' bis ' . $cn . ' Personen';
						}
					}
				}
				?>
				<?php if ( $cap_lines ) : ?>
				<p class="fgpp-extra-equipment"><strong>Kapazitäten:</strong> <?php echo esc_html( implode( ' · ', $cap_lines ) ); ?></p>
				<?php endif; ?>
				<?php if ( $extra_equipment !== '' ) : ?>
				<p class="fgpp-extra-equipment"><strong>Außerdem vor Ort:</strong> <?php echo esc_html( $extra_equipment ); ?></p>
				<?php endif; ?>
			</section>
			<?php endif; ?>


			<?php if ( $events ) : ?>
			<section class="section">
				<div class="section-head"><div><div class="eyebrow">Veranstaltungen</div><h2>Alle Formate auf <em><?php echo esc_html( $name ); ?></em></h2></div></div>
				<div class="gp-events">
					<?php foreach ( $events as $eid ) {
						get_template_part( 'template-parts/fge-event-card', null, [ 'id' => (int) $eid ] );
					} ?>
				</div>
			</section>
			<?php endif; ?>

			<?php
			$plat  = (float) $m( 'latitude' );
			$plng  = (float) $m( 'longitude' );
			$paddr = trim( $m( 'street' ) . ' ' . $m( 'house_number' ) . ', ' . $m( 'postal_code' ) . ' ' . $city, ' ,' );
			$pmq   = ( $plat && $plng ) ? $plat . ',' . $plng : $paddr;
			if ( $pmq !== '' ) : ?>
			<section class="section">
				<div class="section-head"><div><div class="eyebrow">Standort</div><h2>Wo ihr uns <em>findet</em></h2></div></div>
				<div class="fgpp-map">
					<iframe src="https://www.google.com/maps?q=<?php echo rawurlencode( $pmq ); ?>&output=embed" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen title="Karte: <?php echo esc_attr( $name ); ?>"></iframe>
				</div>
				<?php if ( $paddr !== '' ) : ?><p class="fgpp-map-addr"><?php echo fge_icon_map_pin(); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php echo esc_html( $paddr ); ?></p><?php endif; ?>
				<?php $apois = function_exists( 'fge_partner_arrival_pois' ) ? fge_partner_arrival_pois( $pid ) : []; ?>
				<?php if ( $apois ) : ?>
				<div class="fgpp-poi-grid">
					<?php foreach ( $apois as $pl => $pv ) : ?>
						<div class="fgpp-poi"><div class="fgpp-poi-l"><?php echo esc_html( $pl ); ?></div><div class="fgpp-poi-v"><?php echo esc_html( $pv ); ?></div></div>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
			</section>
			<?php endif; ?>

			<section class="section">
				<div class="two-col">
					<div class="panel">
						<div class="panel-head"><h3 style="font-size:18px;">Was Firmen sagen</h3></div>
						<?php if ( $m( 'review_quote' ) !== '' ) : ?>
							<div class="review">
								<div class="review-head"><span class="review-company"><?php echo esc_html( $m( 'review_author' ) ?: 'Kunde' ); ?></span></div>
								<p class="review-quote">„<?php echo esc_html( $m( 'review_quote' ) ); ?>"</p>
								<?php if ( $m( 'review_role' ) !== '' ) : ?><div class="review-foot"><span><?php echo esc_html( $m( 'review_role' ) ); ?></span></div><?php endif; ?>
							</div>
						<?php else : ?>
							<p style="font-size:14px;color:var(--ink-500);">Noch keine Bewertung vorhanden.</p>
						<?php endif; ?>
					</div>
					<div class="panel">
						<div class="panel-head"><h3 style="font-size:18px;">Anfrage an diesen Platz</h3></div>
						<p style="font-size:14px;color:var(--ink-600,var(--ink-500));margin:0 0 16px;">Plant euer Firmenevent bei <?php echo esc_html( $name ); ?> — wir holen Verfügbarkeit &amp; Angebot direkt beim Platz ein.</p>
						<a class="btn btn-brand" href="<?php echo esc_url( $ind_url ); ?>">Anfrage an diesen Platz</a>
					</div>
				</div>
			</section>

			<section class="section">
				<div class="gp-cta">
					<div>
						<div class="eyebrow">Maßgeschneidert</div>
						<h2>Etwas Eigenes<?php echo $city ? ' in ' . esc_html( $city ) : ''; ?>?</h2>
						<p>Sag uns, was ihr vorhabt — Sommerfest, Incentive, Kundentag oder Turnier. Wir stellen euch ein Event genau nach euren Wünschen zusammen.</p>
					</div>
					<a class="btn btn-brand" href="<?php echo esc_url( $ind_url ); ?>">Individuelles Event anfragen</a>
				</div>
			</section>

		</div>
	</div>

	<?php
	if ( $gallery ) :
		$gallery_items = [];
		foreach ( $gallery as $gid ) {
			$u = wp_get_attachment_image_url( $gid, 'large' );
			if ( $u ) {
				$gallery_items[] = [ 'url' => $u, 'name' => get_the_title( $gid ) ];
			}
		}
		if ( $gallery_items ) : ?>
		<div class="gp-lightbox" data-gp-lightbox data-images="<?php echo esc_attr( wp_json_encode( $gallery_items ) ); ?>" hidden>
			<button type="button" class="gp-lb-icon gp-lb-close" data-gp-close aria-label="Galerie schließen">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
			</button>
			<button type="button" class="gp-lb-icon gp-lb-nav gp-lb-prev" data-gp-prev aria-label="Vorheriges Bild">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
			</button>
			<img class="gp-lb-img" alt="" data-gp-img>
			<button type="button" class="gp-lb-icon gp-lb-nav gp-lb-next" data-gp-next aria-label="Nächstes Bild">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
			</button>
			<div class="gp-lb-caption">
				<span class="gp-lb-count"><span data-gp-cur>1</span> / <?php echo (int) count( $gallery_items ); ?></span>
			</div>
		</div>
	<?php endif; endif; ?>

	<?php get_template_part( 'template-parts/fge-footer' ); ?>
</div>
<?php get_footer();
