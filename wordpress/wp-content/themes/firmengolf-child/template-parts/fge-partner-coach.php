<?php
/**
 * Öffentliche Golflehrer-Visitenkarte (Partner-Typ coach, Plan Abschnitt 8b/7).
 * Stellt eine PERSON vor, nicht eine Anlage: Portrait, Titel, Qualifikation,
 * Über-mich, Formate, Kursbilder, Standort, Events, Anfrage. Nutzt dieselben
 * .fgpp-Komponenten wie die Platz-Seite (Design-Linie: keine Namensraum-Kopien).
 * Sichtbarkeit gated wie die Platz-Seite in fge_block_non_public_partners().
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pid = (int) get_the_ID();
$m   = static fn( string $k ): string => (string) get_post_meta( $pid, '_fge_' . $k, true );

$first       = $m( 'coach_first' );
$coach_name  = trim( $first . ' ' . $m( 'coach_last' ) ) ?: get_the_title();
$coach_title = $m( 'public_golfclub_name' );
$desc        = $m( 'public_short_description' );
$about       = $m( 'coach_about' );
$website     = $m( 'website_url' );
$city        = $m( 'city' );

// Qualifikations-Zeile im Stil „PGA Golf Professional · über 10 Jahre · Deutsch, Englisch"
// (Plan A3: klein anzeigen, kein Gate, kein Badge, das andere abwertet).
$quali_all   = function_exists( 'fge_catalog_coach_quali' ) ? fge_catalog_coach_quali() : [];
$quali_names = [];
foreach ( (array) get_post_meta( $pid, '_fge_coach_quali', true ) as $qid ) {
	if ( isset( $quali_all[ $qid ] ) ) {
		$quali_names[] = 'other' === $qid && '' !== $m( 'coach_quali_other' ) ? $m( 'coach_quali_other' ) : $quali_all[ $qid ];
	}
}
$years_l = [ 'u3' => 'Trainererfahrung', '3-5' => '3 bis 5 Jahre Erfahrung', '6-10' => '6 bis 10 Jahre Erfahrung', '10plus' => 'Über 10 Jahre Erfahrung' ];
$years   = $years_l[ $m( 'coach_years' ) ] ?? '';
$lang_all   = [ 'de' => 'Deutsch', 'en' => 'Englisch', 'fr' => 'Französisch', 'it' => 'Italienisch', 'es' => 'Spanisch' ];
$lang_names = [];
foreach ( (array) get_post_meta( $pid, '_fge_coach_langs', true ) as $lid ) {
	if ( isset( $lang_all[ $lid ] ) ) {
		$lang_names[] = $lang_all[ $lid ];
	} elseif ( 'other' === $lid && '' !== $m( 'coach_langs_other' ) ) {
		$lang_names[] = $m( 'coach_langs_other' );
	}
}
$quali_line = implode( ' · ', array_filter( [ $quali_names[0] ?? '', $years, implode( ', ', $lang_names ) ] ) );

// Hauptstandort: Firmengolf-Partnerplatz (verlinkbar wenn öffentlich) oder Freitext-Anlage.
$venue_pid    = (int) $m( 'coach_venue_partner_id' );
$venue_public = $venue_pid > 0 && function_exists( 'fge_partner_is_public' ) && fge_partner_is_public( $venue_pid );
$venue_name   = $venue_pid > 0
	? ( (string) get_post_meta( $venue_pid, '_fge_public_golfclub_name', true ) ?: get_the_title( $venue_pid ) )
	: $m( 'coach_venue_name' );
$venue_city   = $venue_pid > 0 ? (string) get_post_meta( $venue_pid, '_fge_city', true ) : $m( 'coach_venue_city' );
$is_mobile    = '1' === $m( 'coach_mobile' );
$mobile_km    = (int) $m( 'coach_mobile_radius' );

$ccap     = (array) get_post_meta( $pid, '_fge_coach_cap', true );
$cap_line = ( (int) ( $ccap['min'] ?? 0 ) > 0 && (int) ( $ccap['solo_max'] ?? 0 ) > 0 )
	? ( (int) $ccap['min'] . ' bis ' . max( (int) $ccap['solo_max'], (int) ( $ccap['team_max'] ?? 0 ) ) . ' Personen' )
	: '';

$cf_all    = function_exists( 'fge_catalog_coach_formats' ) ? fge_catalog_coach_formats() : [];
$cf_names  = [];
foreach ( (array) get_post_meta( $pid, '_fge_coach_formats', true ) as $fid ) {
	if ( isset( $cf_all[ $fid ] ) ) {
		$cf_names[] = $cf_all[ $fid ];
	}
}
$pf_all = function_exists( 'fge_catalog_partner_formats' ) ? fge_catalog_partner_formats() : [];
foreach ( (array) get_post_meta( $pid, '_fge_event_formats', true ) as $fid ) {
	if ( isset( $pf_all[ $fid ] ) ) {
		$cf_names[] = $pf_all[ $fid ];
	}
}
$cf_names = array_values( array_unique( $cf_names ) );

$gallery   = function_exists( 'fge_partner_gallery_ids' ) ? fge_partner_gallery_ids( $pid ) : [];
$cover_att = function_exists( 'fge_partner_cover_id' ) ? fge_partner_cover_id( $pid ) : 0;
$cover     = $cover_att > 0 ? (string) wp_get_attachment_image_url( $cover_att, '2048x2048' ) : fge_get_placeholder_image_url( 'hero-fairway-wide.jpg', $pid );
$portrait  = $cover_att > 0 ? (string) wp_get_attachment_image_url( $cover_att, 'medium' ) : '';
$mono      = strtoupper( mb_substr( $first ?: $coach_name, 0, 1 ) . mb_substr( $m( 'coach_last' ) ?: '', 0, 1 ) );

$events           = function_exists( 'fge_partner_public_event_ids' ) ? fge_partner_public_event_ids( $pid ) : [];
$is_owner_preview = is_user_logged_in() && function_exists( 'fge_user_can_manage_partner' ) && fge_user_can_manage_partner( $pid );
$preview_ids      = [];
if ( $is_owner_preview ) {
	$own = get_posts( [
		'post_type'   => 'firmengolf_event',
		'post_status' => [ 'publish', 'draft' ],
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_query'  => [
			'relation' => 'AND',
			[ 'key' => '_fge_assigned_partner_id', 'value' => $pid, 'type' => 'NUMERIC' ],
			[ 'key' => '_fge_event_status', 'value' => [ 'zur_pruefung', 'aenderung_in_pruefung' ], 'compare' => 'IN' ],
		],
	] );
	$preview_ids = array_map( 'intval', $own );
	$events      = array_values( array_unique( array_merge( array_map( 'intval', $events ), $preview_ids ) ) );
}
$ind_url = ( $ip = get_page_by_path( 'individuelle-events' ) ) ? get_permalink( $ip->ID ) : home_url( '/individuelle-events/' );

// SEO: Person statt GolfCourse.
$c_seo_title = $coach_name . ': Golflehrer für Firmenevents' . ( $city ? ' in ' . $city : '' ) . ' | Firmengolf';
$c_seo_desc  = $desc ?: ( 'Firmenkurse, Platzreife und Teamevents mit Golflehrer ' . $coach_name . ( $city ? ' in ' . $city : '' ) . '.' );
$c_seo_tail  = ' Jetzt bei Firmengolf anfragen.';
$c_seo_desc  = rtrim( mb_substr( wp_strip_all_tags( (string) $c_seo_desc ), 0, 150 - mb_strlen( $c_seo_tail ) ) ) . $c_seo_tail;
add_filter( 'pre_get_document_title', function () use ( $c_seo_title ) { return $c_seo_title; } );
add_action( 'wp_head', function () use ( $c_seo_title, $c_seo_desc, $pid, $cover, $coach_name, $coach_title, $city, $website ) {
	$GLOBALS['fge_seo_meta_done'] = true;
	echo '<meta name="description" content="' . esc_attr( $c_seo_desc ) . '">' . "\n";
	echo '<meta property="og:type" content="profile">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $c_seo_title ) . '">' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $c_seo_desc ) . '">' . "\n";
	echo '<meta property="og:url" content="' . esc_url( get_permalink( $pid ) ) . '">' . "\n";
	if ( $cover ) { echo '<meta property="og:image" content="' . esc_url( $cover ) . '">' . "\n"; }
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";

	$person = [
		'@context'    => 'https://schema.org',
		'@type'       => 'Person',
		'name'        => $coach_name,
		'jobTitle'    => $coach_title ?: 'Golflehrer',
		'description' => $c_seo_desc,
		'url'         => get_permalink( $pid ),
	];
	if ( $cover ) { $person['image'] = $cover; }
	if ( $city ) { $person['address'] = [ '@type' => 'PostalAddress', 'addressLocality' => $city, 'addressCountry' => 'DE' ]; }
	if ( $website ) { $person['sameAs'] = [ $website ]; }
	echo '<script type="application/ld+json">' . wp_json_encode( $person ) . '</script>' . "\n";
} );
get_header();
?>
<div class="fge-page" id="fge-main" role="main" tabindex="-1">

	<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => 'events' ] ); ?>

	<div class="fgpp">
		<div class="page-wide">

			<section class="hero">
				<div class="hero-photo" style="background-image:url('<?php echo esc_url( $cover ); ?>')">
					<div class="hero-scrim"></div>
					<?php echo function_exists( 'fge_image_credit_overlay' ) ? fge_image_credit_overlay( $cover_att ) : ''; // Bildnachweis ?>
					<?php if ( $gallery ) : ?>
						<button type="button" class="fg-btn fg-btn-glass gp-gallery-btn" data-gp-open>
							<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
							Galerie
						</button>
					<?php endif; ?>
					<div class="hero-body">
						<div class="hero-id">
							<?php if ( $portrait !== '' ) : ?>
								<div class="hero-monogram hero-logo"><img src="<?php echo esc_url( $portrait ); ?>" alt="Portrait <?php echo esc_attr( $coach_name ); ?>"></div>
							<?php else : ?>
								<div class="hero-monogram"><?php echo esc_html( $mono ); ?></div>
							<?php endif; ?>
							<div class="hero-text">
								<div class="hero-eyebrow">Golflehrer auf Firmengolf</div>
								<h1 class="hero-name"><?php echo esc_html( $coach_name ); ?></h1>
								<div class="hero-meta">
									<?php if ( $coach_title ) : ?><span><?php echo esc_html( $coach_title ); ?></span><?php endif; ?>
									<?php if ( $venue_city ?: $city ) : ?><span class="dot">·</span><span><?php echo esc_html( $venue_city ?: $city ); ?></span><?php endif; ?>
									<?php if ( $events ) : ?><span class="dot">·</span><span><?php echo (int) count( $events ); ?> Event<?php echo count( $events ) === 1 ? '' : 's'; ?></span><?php endif; ?>
								</div>
							</div>
						</div>
					</div>
				</div>
			</section>

			<section class="section">
				<div class="section-head"><div><div class="eyebrow">Über <?php echo esc_html( $first ?: 'mich' ); ?></div><h2>Wer dein Team <em>trainiert</em></h2></div></div>
				<div class="about">
					<div class="about-main">
						<?php if ( $quali_line !== '' ) : ?>
							<p style="font-size:13.5px;color:var(--ink-500);margin-bottom:14px;"><?php echo esc_html( $quali_line ); ?></p>
						<?php endif; ?>
						<?php if ( $desc !== '' ) : ?>
							<?php foreach ( preg_split( '/\n\s*\n/', trim( $desc ) ) as $para ) : ?><p><?php echo esc_html( trim( $para ) ); ?></p><?php endforeach; ?>
						<?php endif; ?>
						<?php if ( $about !== '' ) : ?>
							<?php foreach ( preg_split( '/\n\s*\n/', trim( $about ) ) as $para ) : ?><p><?php echo esc_html( trim( $para ) ); ?></p><?php endforeach; ?>
						<?php endif; ?>
						<?php if ( $desc === '' && $about === '' ) : ?>
							<p>Golflehrer mit Fokus auf Firmenkurse und Teamevents.</p>
						<?php endif; ?>
					</div>
					<div class="facts">
						<h4>Auf einen Blick</h4>
						<?php
						$coach_facts = [];
						if ( ! empty( $quali_names ) ) { $coach_facts[] = [ 'Qualifikation', implode( ', ', $quali_names ) ]; }
						if ( $years )                  { $coach_facts[] = [ 'Erfahrung', $years ]; }
						if ( $lang_names )             { $coach_facts[] = [ 'Sprachen', implode( ', ', $lang_names ) ]; }
						if ( $venue_name )             { $coach_facts[] = [ 'Hauptstandort', trim( $venue_name . ( $venue_city ? ', ' . $venue_city : '' ) ) ]; }
						if ( $is_mobile )              { $coach_facts[] = [ 'Mobil', 'Kommt auch zu euch' . ( $mobile_km > 0 ? ', bis ' . $mobile_km . ' km' : '' ) ]; }
						if ( $cap_line )               { $coach_facts[] = [ 'Gruppengröße', $cap_line ]; }
						foreach ( $coach_facts as $f ) : ?>
							<div class="fact-row"><span class="lbl"><?php echo esc_html( $f[0] ); ?></span><span class="val"><?php echo esc_html( $f[1] ); ?></span></div>
						<?php endforeach; ?>
						<?php if ( $website ) : ?><a class="btn btn-ghost btn-sm" style="margin-top:14px;" href="<?php echo esc_url( $website ); ?>" target="_blank" rel="noopener">Website&nbsp;↗</a><?php endif; ?>
					</div>
				</div>
			</section>

			<?php if ( $cf_names ) : ?>
			<section class="section">
				<div class="section-head"><div><div class="eyebrow">Formate</div><h2>Was <?php echo esc_html( $first ?: 'er' ); ?> mit eurem Team <em>macht</em></h2></div></div>
				<div class="evd-poi-grid evd-onsite">
					<?php foreach ( $cf_names as $fmt ) : ?>
						<div class="evd-poi"><div class="evd-onsite-n"><?php echo esc_html( $fmt ); ?></div></div>
					<?php endforeach; ?>
				</div>
			</section>
			<?php endif; ?>

			<?php /* Vertrauen: der Ablauf nimmt die Hürde „unbekannte Person buchen". */ ?>
			<section class="section">
				<div class="section-head"><div><div class="eyebrow">So läuft es</div><h2>Vom ersten Kontakt zum <em>Event</em></h2></div></div>
				<div class="two-col" style="grid-template-columns:repeat(3,1fr);gap:16px;">
					<div class="panel">
						<div class="panel-head"><h3 style="font-size:16px;">1 · Ihr fragt an</h3></div>
						<p style="font-size:14px;color:var(--ink-600);margin:0;">Die Anfrage läuft über Firmengolf, kostenlos und unverbindlich. Wir klären Termin und Gruppengröße.</p>
					</div>
					<div class="panel">
						<div class="panel-head"><h3 style="font-size:16px;">2 · <?php echo esc_html( $first ?: 'Der Pro' ); ?> plant mit euch</h3></div>
						<p style="font-size:14px;color:var(--ink-600);margin:0;">Format, Ablauf und Niveau werden auf euer Team zugeschnitten, vom Schnupperkurs bis zur Platzreife.</p>
					</div>
					<div class="panel">
						<div class="panel-head"><h3 style="font-size:16px;">3 · Ein Preis, eine Rechnung</h3></div>
						<p style="font-size:14px;color:var(--ink-600);margin:0;">Ihr bekommt ein Gesamtangebot über Firmengolf, keine Einzelabrechnungen mit Trainer oder Anlage.</p>
					</div>
				</div>
			</section>

			<?php if ( count( $gallery ) > 1 ) : ?>
			<section class="section">
				<div class="section-head"><div><div class="eyebrow">Eindrücke</div><h2>Aus den <em>Kursen</em></h2></div></div>
				<div class="evd-poi-grid" style="grid-template-columns:repeat(auto-fill,minmax(220px,1fr));">
					<?php foreach ( array_slice( $gallery, 1, 6 ) as $gid ) :
						$gu = wp_get_attachment_image_url( $gid, 'large' );
						if ( ! $gu ) { continue; } ?>
						<img src="<?php echo esc_url( $gu ); ?>" alt="<?php echo esc_attr( get_the_title( $gid ) ?: 'Kursbild ' . $coach_name ); ?>" loading="lazy" style="width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:14px;">
					<?php endforeach; ?>
				</div>
			</section>
			<?php endif; ?>

			<?php if ( ! $events && $is_owner_preview ) : ?>
			<section class="section">
				<div class="section-head"><div><div class="eyebrow">Veranstaltungen</div><h2>Noch keine <em>Events</em></h2></div></div>
				<div class="panel" style="display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap;">
					<p style="margin:0;font-size:14.5px;color:var(--ink-600);max-width:560px;line-height:1.55;">Hier erscheinen deine Event-Angebote, sobald sie freigegeben sind, erst dann wird deine Seite auch öffentlich sichtbar. Dieser Hinweis ist nur für dich.</p>
					<a class="btn btn-brand btn-sm" href="<?php echo esc_url( home_url( '/partnerportal/?tab=angebote&portal_action=new' ) ); ?>">+ Erstes Angebot anlegen</a>
				</div>
			</section>
			<?php endif; ?>

			<?php if ( $events ) : ?>
			<section class="section">
				<div class="section-head"><div><div class="eyebrow">Veranstaltungen</div><h2>Buchbare Events mit <em><?php echo esc_html( $first ?: $coach_name ); ?></em></h2></div></div>
				<div class="gp-events">
					<?php foreach ( $events as $eid ) {
						if ( in_array( (int) $eid, $preview_ids, true ) ) {
							echo '<div class="gp-preview-card">';
							get_template_part( 'template-parts/fge-event-card-v2', null, [ 'id' => (int) $eid ] );
							echo '<span class="gp-preview-tag">In Prüfung, nur für dich sichtbar</span></div>';
							continue;
						}
						get_template_part( 'template-parts/fge-event-card-v2', null, [ 'id' => (int) $eid ] );
					} ?>
				</div>
			</section>
			<?php endif; ?>

			<?php if ( $venue_name ) : ?>
			<section class="section">
				<div class="section-head"><div><div class="eyebrow">Standort</div><h2>Wo <?php echo esc_html( $first ?: 'er' ); ?> <em>unterrichtet</em></h2></div></div>
				<div class="panel" style="display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap;">
					<div>
						<p style="margin:0 0 4px;font-weight:600;"><?php echo esc_html( trim( $venue_name . ( $venue_city ? ', ' . $venue_city : '' ) ) ); ?></p>
						<p style="margin:0;font-size:14px;color:var(--ink-600);">
							<?php echo $is_mobile ? 'Auf Wunsch kommt ' . esc_html( $first ?: 'der Trainer' ) . ' auch zu euch ins Unternehmen' . ( $mobile_km > 0 ? ', bis ' . (int) $mobile_km . ' km.' : '.' ) : 'Kurse und Events finden direkt auf der Anlage statt.'; ?>
						</p>
						<?php if ( '' !== $m( 'coach_venues_note' ) ) : ?>
							<p style="margin:8px 0 0;font-size:13.5px;color:var(--ink-500);">Außerdem: <?php echo esc_html( $m( 'coach_venues_note' ) ); ?></p>
						<?php endif; ?>
					</div>
					<?php if ( $venue_public ) : ?>
						<a class="btn btn-ghost btn-sm" href="<?php echo esc_url( get_permalink( $venue_pid ) ); ?>">Zum Golfplatz →</a>
					<?php endif; ?>
				</div>
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
						<div class="panel-head"><h3 style="font-size:18px;">Anfrage an <?php echo esc_html( $first ?: $coach_name ); ?></h3></div>
						<p style="font-size:14px;color:var(--ink-600,var(--ink-500));margin:0 0 16px;">Plant euren Firmenkurs oder euer Teamevent mit <?php echo esc_html( $coach_name ); ?>, wir holen Verfügbarkeit und Angebot direkt ein.</p>
						<a class="btn btn-brand" href="<?php echo esc_url( add_query_arg( 'anfrage', 'full', $ind_url ) ); ?>">Jetzt anfragen</a>
					</div>
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
				$gallery_items[] = [ 'url' => $u, 'name' => get_the_title( $gid ), 'credit' => function_exists( 'fge_image_credit' ) ? fge_image_credit( $gid ) : '' ];
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
				<span class="gp-lb-credit" data-gp-credit></span>
			</div>
		</div>
	<?php endif; endif; ?>

	<?php get_template_part( 'template-parts/fge-footer' ); ?>
</div>
<?php get_footer();
