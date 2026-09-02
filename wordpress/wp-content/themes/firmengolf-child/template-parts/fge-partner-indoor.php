<?php
/**
 * Öffentliche Indoor-Partnerseite (Partner-Typ indoor, Persona-Audit D1).
 * Stellt eine LOCATION vor: Boxen, Systeme, Event-Features, Räume, Gastro,
 * Öffnungszeiten, Events, Anfahrt. Nutzt dieselben .fgpp-Komponenten wie
 * Platz- und Coach-Seite (Design-Linie: keine Namensraum-Kopien).
 * Sichtbarkeit gated wie die Platz-Seite in fge_block_non_public_partners().
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pid = (int) get_the_ID();
$m   = static fn( string $k ): string => (string) get_post_meta( $pid, '_fge_' . $k, true );

$name = $m( 'public_golfclub_name' ) ?: get_the_title();
$city = $m( 'city' );
$desc = $m( 'public_short_description' );

// Anlagentyp (indoor-kind) als lesbares Label.
$kind_label = function_exists( 'fge_catalog_indoor_kinds' )
	? ( fge_catalog_indoor_kinds()[ $m( 'indoor_kind' ) ] ?? '' )
	: '';

// Technik (_fge_indoor_sim) inkl. bisher toter Felder features/box_max.
$sim      = get_post_meta( $pid, '_fge_indoor_sim', true );
$sim      = is_array( $sim ) ? $sim : [];
$boxes    = absint( $sim['boxes'] ?? 0 );
$max_pers = absint( $sim['max_persons'] ?? 0 );
$box_max  = absint( $sim['box_max'] ?? 0 );
$sys_names = [];
foreach ( (array) ( $sim['systems'] ?? [] ) as $sid ) {
	if ( 'other' === $sid ) {
		$sys_names[] = (string) ( $sim['systems_other'] ?? '' ) ?: 'Weitere Systeme';
	} elseif ( function_exists( 'fge_catalog_indoor_systems' ) && isset( fge_catalog_indoor_systems()[ $sid ] ) ) {
		$sys_names[] = fge_catalog_indoor_systems()[ $sid ];
	}
}
$feature_names = [];
foreach ( (array) ( $sim['features'] ?? [] ) as $fid ) {
	if ( function_exists( 'fge_catalog_indoor_features' ) && isset( fge_catalog_indoor_features()[ $fid ] ) ) {
		$feature_names[] = fge_catalog_indoor_features()[ $fid ];
	}
}
$staff_label = function_exists( 'fge_catalog_indoor_staffing' )
	? ( fge_catalog_indoor_staffing()[ (string) ( $sim['staffing'] ?? '' ) ] ?? '' )
	: '';
$lefthand = [ 'all' => 'Ja, in allen Boxen', 'some' => 'In einzelnen Boxen', 'no' => 'Nein' ][ (string) ( $sim['lefthand'] ?? '' ) ] ?? '';

// Räume & Drumherum (bisher tote Slide indoor-spaces).
$space_names = [];
foreach ( (array) get_post_meta( $pid, '_fge_indoor_spaces', true ) as $sp ) {
	foreach ( function_exists( 'fge_catalog_indoor_infra_groups' ) ? fge_catalog_indoor_infra_groups() : [] as $grp ) {
		if ( isset( $grp[ $sp ] ) ) {
			$space_names[] = $grp[ $sp ];
		}
	}
}
foreach ( (array) get_post_meta( $pid, '_fge_indoor_spaces_custom', true ) as $sp ) {
	if ( '' !== trim( (string) $sp ) ) {
		$space_names[] = (string) $sp;
	}
}
$area = $m( 'indoor_area' );

// Gastro-Kacheln (Top-50-Katalog).
$gastro_names = [];
foreach ( (array) get_post_meta( $pid, '_fge_indoor_gastro', true ) as $gid ) {
	if ( function_exists( 'fge_catalog_indoor_gastro' ) && isset( fge_catalog_indoor_gastro()[ $gid ] ) ) {
		$gastro_names[] = fge_catalog_indoor_gastro()[ $gid ];
	}
}

$open_note = $m( 'indoor_open_note' );
$season    = $m( 'season' );

$gallery   = function_exists( 'fge_partner_gallery_ids' ) ? fge_partner_gallery_ids( $pid ) : [];
$cover_att = function_exists( 'fge_partner_cover_id' ) ? fge_partner_cover_id( $pid ) : 0;
$cover     = $cover_att > 0
	? (string) wp_get_attachment_image_url( $cover_att, '2048x2048' )
	: ( function_exists( 'fge_get_placeholder_image_url' ) ? fge_get_placeholder_image_url( 'onboarding-indoor-lounge.jpg' ) : '' );
$logo_id  = (int) $m( 'logo_attachment_id' );
$logo_img = $logo_id > 0 ? (string) wp_get_attachment_image_url( $logo_id, 'thumbnail' ) : '';
$mono     = strtoupper( mb_substr( $name, 0, 2 ) );

$pois = function_exists( 'fge_partner_arrival_pois' ) ? fge_partner_arrival_pois( $pid ) : [];
if ( '' !== $m( 'indoor_entrance_note' ) ) {
	$pois['Eingang'] = $m( 'indoor_entrance_note' );
}

$events           = function_exists( 'fge_partner_public_event_ids' ) ? fge_partner_public_event_ids( $pid ) : [];
$is_owner_preview = is_user_logged_in() && function_exists( 'fge_user_can_manage_partner' ) && fge_user_can_manage_partner( $pid );
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
	$events = array_values( array_unique( array_merge( array_map( 'intval', $events ), array_map( 'intval', $own ) ) ) );
}
$ind_url = ( $ip = get_page_by_path( 'individuelle-events' ) ) ? get_permalink( $ip->ID ) : home_url( '/individuelle-events/' );

// SEO: Sport-/Freizeit-Location statt Golfplatz.
$i_seo_title = $name . ': Indoor Golf für Firmenevents' . ( $city ? ' in ' . $city : '' ) . ' | Firmengolf';
$i_seo_desc  = $desc ?: ( 'Firmenevents, After-Work und Weihnachtsfeiern im Indoor Golf bei ' . $name . ( $city ? ' in ' . $city : '' ) . '.' );
$i_seo_tail  = ' Jetzt bei Firmengolf anfragen.';
$i_seo_desc  = rtrim( mb_substr( wp_strip_all_tags( (string) $i_seo_desc ), 0, 150 - mb_strlen( $i_seo_tail ) ) ) . $i_seo_tail;
add_filter( 'pre_get_document_title', function () use ( $i_seo_title ) { return $i_seo_title; } );
add_action( 'wp_head', function () use ( $i_seo_title, $i_seo_desc, $pid, $cover, $name, $city ) {
	$GLOBALS['fge_seo_meta_done'] = true;
	echo '<meta name="description" content="' . esc_attr( $i_seo_desc ) . '">' . "\n";
	echo '<meta property="og:type" content="website">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $i_seo_title ) . '">' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $i_seo_desc ) . '">' . "\n";
	echo '<meta property="og:url" content="' . esc_url( get_permalink( $pid ) ) . '">' . "\n";
	if ( $cover ) { echo '<meta property="og:image" content="' . esc_url( $cover ) . '">' . "\n"; }
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	$biz = [
		'@context'    => 'https://schema.org',
		'@type'       => 'SportsActivityLocation',
		'name'        => $name,
		'description' => $i_seo_desc,
		'url'         => get_permalink( $pid ),
	];
	if ( $cover ) { $biz['image'] = $cover; }
	if ( $city ) { $biz['address'] = [ '@type' => 'PostalAddress', 'addressLocality' => $city, 'addressCountry' => 'DE' ]; }
	echo '<script type="application/ld+json">' . wp_json_encode( $biz ) . '</script>' . "\n";
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
					<div class="hero-body">
						<div class="hero-id">
							<?php if ( $logo_img !== '' ) : ?>
								<div class="hero-monogram hero-logo"><img src="<?php echo esc_url( $logo_img ); ?>" alt="<?php echo esc_attr( $name ); ?> Logo"></div>
							<?php else : ?>
								<div class="hero-monogram"><?php echo esc_html( $mono ); ?></div>
							<?php endif; ?>
							<div class="hero-text">
								<div class="hero-eyebrow">Indoor Golf auf Firmengolf</div>
								<h1 class="hero-name"><?php echo esc_html( $name ); ?></h1>
								<div class="hero-meta">
									<?php if ( $city ) : ?><span><?php echo esc_html( $city ); ?></span><?php endif; ?>
									<?php if ( $kind_label ) : ?><span class="dot">·</span><span><?php echo esc_html( $kind_label ); ?></span><?php endif; ?>
									<?php if ( 'Ganzjährig' === $season ) : ?><span class="dot">·</span><span>Ganzjährig geöffnet</span><?php endif; ?>
								</div>
							</div>
						</div>
					</div>
				</div>
			</section>

			<section class="section">
				<div class="section-head"><div><div class="eyebrow">Über die Location</div><h2>Golf, der <em>drinnen</em> stattfindet</h2></div></div>
				<div class="two-col">
					<div class="panel">
						<?php if ( $desc !== '' ) : ?>
							<p style="font-size:15.5px;line-height:1.65;color:var(--ink-800);margin:0;"><?php echo esc_html( $desc ); ?></p>
						<?php else : ?>
							<p style="font-size:15px;line-height:1.6;color:var(--ink-600);margin:0;">Simulator-Golf für Teams: schlagen, messen, gemeinsam jubeln, bei jedem Wetter und zu jeder Jahreszeit.</p>
						<?php endif; ?>
						<?php if ( $open_note !== '' ) : ?>
							<p style="font-size:14px;line-height:1.6;color:var(--ink-600);margin:14px 0 0;"><strong style="color:var(--ink-900);">Öffnungszeiten:</strong> <?php echo esc_html( $open_note ); ?></p>
						<?php endif; ?>
					</div>
					<div class="panel">
						<h4>Auf einen Blick</h4>
						<?php
						$facts = array_filter( [
							$boxes > 0 ? [ 'Simulator-Boxen', (string) $boxes ] : null,
							$sys_names ? [ 'Systeme', implode( ', ', $sys_names ) ] : null,
							$max_pers > 0 ? [ 'Eventgröße', 'bis ' . $max_pers . ' Personen' ] : null,
							$box_max > 0 ? [ 'Pro Box', 'bis ' . $box_max . ' Personen' ] : null,
							$staff_label ? [ 'Betrieb', $staff_label ] : null,
							$lefthand ? [ 'Linkshänder', $lefthand ] : null,
							$area ? [ 'Fläche', $area ] : null,
						] );
						foreach ( $facts as $f ) : ?>
							<div class="fact-row"><span class="lbl"><?php echo esc_html( $f[0] ); ?></span><span class="val"><?php echo esc_html( $f[1] ); ?></span></div>
						<?php endforeach; ?>
					</div>
				</div>
			</section>

			<?php if ( $feature_names ) : ?>
			<section class="section">
				<div class="section-head"><div><div class="eyebrow">Event-Features</div><h2>Was die Software <em>kann</em></h2></div></div>
				<div class="evd-poi-grid evd-onsite">
					<?php foreach ( $feature_names as $fn ) : ?>
						<div class="evd-poi"><div class="evd-onsite-n"><?php echo esc_html( $fn ); ?></div></div>
					<?php endforeach; ?>
				</div>
			</section>
			<?php endif; ?>

			<?php if ( $space_names || $gastro_names ) : ?>
			<section class="section">
				<div class="section-head"><div><div class="eyebrow">Vor Ort</div><h2>Räume, Gastro und <em>Drumherum</em></h2></div></div>
				<div class="two-col">
					<?php if ( $space_names ) : ?>
					<div class="panel">
						<h4>Räume &amp; Aktivitäten</h4>
						<?php foreach ( $space_names as $sn ) : ?>
							<div class="fact-row"><span class="val" style="margin-top:0;"><?php echo esc_html( $sn ); ?></span></div>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>
					<?php if ( $gastro_names ) : ?>
					<div class="panel">
						<h4>Gastronomie</h4>
						<?php foreach ( $gastro_names as $gn ) : ?>
							<div class="fact-row"><span class="val" style="margin-top:0;"><?php echo esc_html( $gn ); ?></span></div>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>
				</div>
			</section>
			<?php endif; ?>

			<?php if ( $gallery ) : ?>
			<section class="section" id="galerie">
				<div class="section-head"><div><div class="eyebrow">Eindrücke</div><h2>So sieht es bei <em>uns</em> aus</h2></div></div>
				<div class="gal-grid">
					<?php foreach ( array_slice( $gallery, 0, 6 ) as $gi => $att ) : ?>
						<img src="<?php echo esc_url( (string) wp_get_attachment_image_url( (int) $att, 'large' ) ); ?>" alt="<?php echo esc_attr( $name . ' Foto ' . ( $gi + 1 ) ); ?>" loading="lazy">
					<?php endforeach; ?>
				</div>
			</section>
			<?php endif; ?>

			<?php if ( $events ) : ?>
			<section class="section">
				<div class="section-head"><div><div class="eyebrow">Veranstaltungen</div><h2>Buchbare Events bei <em><?php echo esc_html( $name ); ?></em></h2></div></div>
				<div class="ev-grid4" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;">
					<?php foreach ( array_slice( $events, 0, 8 ) as $eid ) {
						if ( function_exists( 'fge_event_is_public' ) && ! fge_event_is_public( (int) $eid ) && ! $is_owner_preview ) {
							continue;
						}
						get_template_part( 'template-parts/fge-event-card-v2', null, [ 'id' => (int) $eid ] );
					} ?>
				</div>
			</section>
			<?php else : ?>
			<section class="section">
				<div class="section-head"><div><div class="eyebrow">Veranstaltungen</div><h2>Euer Event, euer <em>Zuschnitt</em></h2></div></div>
				<div class="panel" style="display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap;">
					<p style="margin:0;font-size:15px;line-height:1.6;color:var(--ink-700);max-width:560px;">Konkrete Angebote sind in Vorbereitung. Beschreib uns einfach euer Wunsch-Event, wir stellen es gemeinsam mit der Location zusammen.</p>
					<a class="btn btn-brand" href="<?php echo esc_url( $ind_url ); ?>">Event anfragen</a>
				</div>
			</section>
			<?php endif; ?>

			<?php if ( $pois ) : ?>
			<section class="section">
				<div class="section-head"><div><div class="eyebrow">Anfahrt</div><h2>So kommt ihr <em>hin</em></h2></div></div>
				<div class="evd-poi-grid">
					<?php foreach ( $pois as $pl => $pv ) : ?>
						<div class="evd-poi"><div class="evd-poi-l"><?php echo esc_html( $pl ); ?></div><div class="evd-onsite-n"><?php echo esc_html( $pv ); ?></div></div>
					<?php endforeach; ?>
				</div>
			</section>
			<?php endif; ?>

			<section class="section">
				<div class="panel" style="display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap;background:var(--fairway-100);border-color:var(--fairway-200);">
					<div>
						<p style="margin:0 0 4px;font-weight:600;font-size:17px;color:var(--ink-900);">Firmenevent bei <?php echo esc_html( $name ); ?>?</p>
						<p style="margin:0;font-size:14.5px;line-height:1.55;color:var(--ink-700);">Eine Anfrage, ein Ansprechpartner: Termin, Ablauf und Verpflegung klären wir für euch.</p>
					</div>
					<a class="btn btn-brand" href="<?php echo esc_url( $ind_url ); ?>">Jetzt anfragen</a>
				</div>
			</section>

		</div>
	</div>
</div>
<?php get_footer(); ?>
