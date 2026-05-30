<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

// ── URLs ────────────────────────────────────────────────────────────────────
$get_page_url = static function( string $slug, string $fallback = '#' ): string {
	$page = get_page_by_path( $slug );
	return $page ? (string) get_permalink( $page->ID ) : $fallback;
};

$url_events  = (string) get_post_type_archive_link( 'firmengolf_event' );
$url_ind     = $get_page_url( 'individuelle-events', home_url( '/individuelle-events/' ) );
$url_kontakt = $get_page_url( 'kontakt', home_url( '/kontakt/' ) );
$url_blog    = home_url( '/blog/' );

// ── Featured events (up to 4 published) ────────────────────────────────────
$featured_events = fge_get_featured_events( 4 );

// ── Latest blog posts ───────────────────────────────────────────────────────
$blog_posts = get_posts( [
	'post_type'      => 'post',
	'post_status'    => 'publish',
	'numberposts'    => 3,
	'orderby'        => 'date',
	'order'          => 'DESC',
] );

// ── Images ──────────────────────────────────────────────────────────────────
$img = static function( string $name ) use ( &$img ): string {
	return fge_get_placeholder_image_url( $name );
};

// Arrow SVG (inline, reused)
$arrow_svg = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17L17 7M9 7h8v8"/></svg>';
$arrow_right = '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg>';
$check_svg  = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>';
?>
<div class="fge-page">

<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => '' ] ); ?>

<?php /* ══════════════════ 1. HERO ══════════════════ */ ?>
<section class="mk-hero" aria-label="Hero">
	<div class="mk-hero-photo" style="background-image:url('<?php echo esc_url( $img( 'hero-fairway-wide.jpg' ) ); ?>')">
		<div class="mk-hero-scrim" aria-hidden="true"></div>
		<div class="mk-hero-content">
			<div class="mk-hero-eyebrow">Firmenevents · Golf für Unternehmen</div>
			<h1 class="mk-hero-title">
				Bringt euer Team raus aus dem Büro und rein in <em class="mk-italic">Bewegung</em>.
			</h1>
			<p class="mk-hero-sub">
				Vom Schnupperkurs bis zum Firmenturnier — kuratierte Golf-Formate auf Partnerplätzen
				in ganz Deutschland. Eine Anfrage, eine Rechnung, ein Ansprechpartner.
			</p>
			<div class="mk-hero-ctas">
				<a class="fg-btn-cta fg-btn-lg" href="<?php echo esc_url( $url_events ); ?>">
					Events entdecken
					<span class="fg-arrow"><?php echo $arrow_right; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				</a>
				<a class="fg-btn-ghost-light" href="<?php echo esc_url( $url_ind ); ?>">
					Individuelles Event planen →
				</a>
			</div>
		</div>
	</div>

	<div class="mk-hero-floating" aria-hidden="true">
		<div class="mk-floating-thumb" style="background-image:url('<?php echo esc_url( $img( 'event-team.jpg' ) ); ?>')"></div>
		<div>
			<div class="mk-floating-chip">12 Sommer-Slots im Juni</div>
			<div class="mk-floating-meta">Hamburg · München · Berlin · Köln</div>
		</div>
	</div>

	<div class="home-quicksearch" role="search" aria-label="Events filtern">
		<a class="home-qs-cell" href="<?php echo esc_url( $url_events ); ?>">
			<div class="fg-cell-label">Format</div>
			<div class="fg-cell-value">Alle Formate</div>
		</a>
		<div class="fg-cell-divider" aria-hidden="true"></div>
		<a class="home-qs-cell" href="<?php echo esc_url( $url_events ); ?>">
			<div class="fg-cell-label">Region</div>
			<div class="fg-cell-value">Ganz Deutschland</div>
		</a>
		<div class="fg-cell-divider" aria-hidden="true"></div>
		<a class="home-qs-cell" href="<?php echo esc_url( $url_events ); ?>">
			<div class="fg-cell-label">Wann</div>
			<div class="fg-cell-value fg-muted">Zeitraum wählen</div>
		</a>
		<div class="fg-cell-divider" aria-hidden="true"></div>
		<a class="home-qs-cell" href="<?php echo esc_url( $url_events ); ?>">
			<div class="fg-cell-label">Gruppe</div>
			<div class="fg-cell-value fg-muted">Personen</div>
		</a>
		<a class="fg-search-btn" href="<?php echo esc_url( $url_events ); ?>" aria-label="Events suchen">
			<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
		</a>
	</div>
</section>

<?php /* ══════════════════ 2. PARTNERS STRIP ══════════════════ */ ?>
<div class="mk-partners" aria-label="Unsere Partner">
	<div class="mk-eyebrow">Schon mit uns unterwegs</div>
	<div class="mk-partners-row">
		<?php
		$partners = [ 'Quartz Labs', 'North Studio', 'Hartmann GmbH', 'Steinblick', 'Halde & Co.', 'Werkstatt 4', 'Bauer & Söhne', 'Pixelhof' ];
		foreach ( $partners as $partner ) :
		?>
			<span class="mk-partner-logo"><?php echo esc_html( $partner ); ?></span>
		<?php endforeach; ?>
	</div>
</div>

<?php /* ══════════════════ 3. HOW IT WORKS ══════════════════ */ ?>
<section class="mk-section mk-steps" aria-label="So funktioniert es">
	<div class="mk-section-head">
		<div class="mk-eyebrow">So funktioniert's</div>
		<h2 class="mk-h2">Drei Schritte. Ein Ansprechpartner.</h2>
		<p class="mk-sub">Wir kümmern uns um Platzwahl, Koordination und Abrechnung. Du kümmerst dich ums Team.</p>
	</div>
	<div class="mk-steps-grid">
		<?php
		$steps = [
			[ '01', 'Du sagst uns, was du planst.',     'Anlass, Gruppe, Zeitraum. Eine Anfrage — mehr brauchen wir nicht.' ],
			[ '02', 'Wir kuratieren passende Plätze.',  'Innerhalb von 24 Stunden bekommst du zwei bis drei Optionen mit Format, Preis und Verfügbarkeit.' ],
			[ '03', 'Du wählst, wir koordinieren.',     'Ein Ansprechpartner, eine Rechnung. Der Platz organisiert vor Ort — du bist nur Gastgeberin.' ],
		];
		foreach ( $steps as $step ) : ?>
			<div class="mk-step">
				<div class="mk-step-n"><?php echo esc_html( $step[0] ); ?></div>
				<h3 class="mk-step-t"><?php echo esc_html( $step[1] ); ?></h3>
				<p class="mk-step-b"><?php echo esc_html( $step[2] ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>
</section>

<?php /* ══════════════════ 4. FEATURED FORMATS ══════════════════ */ ?>
<section class="mk-section" aria-label="Kuratierte Formate">
	<div class="mk-section-head between">
		<div>
			<div class="mk-eyebrow">Kuratierte Formate</div>
			<h2 class="mk-h2">Vom Schnupperkurs bis zum Firmenturnier.</h2>
		</div>
		<a class="fg-btn-ghost" href="<?php echo esc_url( $url_events ); ?>">
			Alle Events ansehen <?php echo $arrow_svg; // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</a>
	</div>
	<div class="home-formats-grid">
		<?php if ( ! empty( $featured_events ) ) : ?>
			<?php foreach ( $featured_events as $i => $event ) :
				$eid         = $event->ID;
				$event_type  = fge_get_event_meta( $eid, 'event_type' );
				$format_label = fge_format_event_type( $event_type );
				$duration    = fge_get_event_meta( $eid, 'duration' );
				$p_max       = (int) fge_get_event_meta( $eid, 'participants_max' );
				$price       = fge_get_event_price_display( $eid );
				$excerpt     = fge_get_event_meta( $eid, 'card_description', $event->post_excerpt );
				$thumb       = has_post_thumbnail( $eid ) ? get_the_post_thumbnail_url( $eid, 'large' ) : $img( 'event-team.jpg' );
				$is_dark     = ( $i % 2 === 1 );
			?>
			<article class="mk-format<?php echo $is_dark ? ' is-dark' : ''; ?>">
				<a href="<?php echo esc_url( get_permalink( $eid ) ); ?>" style="display:contents">
					<div class="mk-format-photo" style="background-image:url('<?php echo esc_url( $thumb ); ?>')">
						<?php if ( $format_label ) : ?>
							<span class="mk-format-tag"><?php echo esc_html( $format_label ); ?></span>
						<?php endif; ?>
						<span class="mk-format-eyebrow">
							<?php echo esc_html( $duration ); ?><?php if ( $p_max ) { echo ' · bis ' . esc_html( (string) $p_max ) . ' Gäste'; } ?>
						</span>
					</div>
					<div class="mk-format-body">
						<h3 class="mk-format-t"><?php echo esc_html( $event->post_title ); ?></h3>
						<?php if ( $excerpt ) : ?>
							<p class="mk-format-desc"><?php echo esc_html( $excerpt ); ?></p>
						<?php endif; ?>
						<div class="mk-format-foot">
							<span class="mk-format-price"><?php echo $price ? esc_html( $price ) : 'Auf Anfrage'; ?></span>
							<span class="mk-format-arrow" aria-hidden="true"><?php echo $arrow_svg; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						</div>
					</div>
				</a>
			</article>
			<?php endforeach; ?>
		<?php else : ?>
			<?php
			// Fallback: static cards when no published events yet
			$static_formats = [
				[ 'label' => 'Schnupperkurs',  'title' => 'Schnupperkurs an einem Nachmittag',    'desc' => 'Einsteigerfreundlich. PGA-Coach, Schläger gestellt, Range-Bälle inklusive.',           'price' => 'ab €89 p.P.',       'img' => 'hero-fairway-wide.jpg', 'dark' => false ],
				[ 'label' => 'Firmenturnier',  'title' => 'Das große Firmenturnier',               'desc' => 'Shotgun-Start, Fotograf, Siegerehrung — wir kümmern uns um alles.',                   'price' => 'ab €320 p.P.',      'img' => 'event-corporate.jpg',  'dark' => true  ],
				[ 'label' => 'Offsite',        'title' => 'Strategie-Offsite Schloss Lüdersburg',  'desc' => 'Workshops im Schloss, nachmittags 9 Loch. Übernachtung inklusive.',                   'price' => 'ab €540 p.P.',      'img' => 'venue-clubhouse.jpg',  'dark' => false ],
				[ 'label' => 'Incentive',      'title' => 'Incentive-Reise Südtirol',              'desc' => 'Drei Tage Dolomiten — Bergblick, private Dinings, Wellness.',                         'price' => 'ab €1.480 p.P.',    'img' => 'hero-mountains.jpg',   'dark' => true  ],
			];
			foreach ( $static_formats as $f ) : ?>
				<article class="mk-format<?php echo $f['dark'] ? ' is-dark' : ''; ?>">
					<div class="mk-format-photo" style="background-image:url('<?php echo esc_url( $img( $f['img'] ) ); ?>')">
						<span class="mk-format-tag"><?php echo esc_html( $f['label'] ); ?></span>
					</div>
					<div class="mk-format-body">
						<h3 class="mk-format-t"><?php echo esc_html( $f['title'] ); ?></h3>
						<p class="mk-format-desc"><?php echo esc_html( $f['desc'] ); ?></p>
						<div class="mk-format-foot">
							<span class="mk-format-price"><?php echo esc_html( $f['price'] ); ?></span>
							<span class="mk-format-arrow" aria-hidden="true"><?php echo $arrow_svg; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						</div>
					</div>
				</article>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
</section>

<?php /* ══════════════════ 5. OCCASIONS GRID ══════════════════ */ ?>
<section class="mk-section" aria-label="Für welchen Anlass?">
	<div class="mk-section-head">
		<div class="mk-eyebrow">Für welchen Anlass?</div>
		<h2 class="mk-h2">Sag uns, was ihr vorhabt — wir kennen das passende Format.</h2>
		<p class="mk-sub">Suche nach dem, was ihr erreichen wollt, nicht nach dem Format-Namen.</p>
	</div>
	<div class="home-occasions">
		<?php
		$occasions = [
			[
				'eyebrow' => 'Onboarding',
				'title'   => 'Neue Mitarbeitende willkommen heißen.',
				'body'    => 'Ein Halbtag Schnupperkurs, der Eis bricht.',
				'url'     => add_query_arg( 'format', 'schnupperkurs', $url_events ),
				'img'     => 'event-summer.jpg',
			],
			[
				'eyebrow' => 'Vertrieb',
				'title'   => 'Kunden und Partner zusammenbringen.',
				'body'    => 'Ganztägiges Firmenturnier oder Networking-Runde.',
				'url'     => add_query_arg( 'format', 'firmenturnier', $url_events ),
				'img'     => 'event-corporate.jpg',
			],
			[
				'eyebrow' => 'Strategie',
				'title'   => 'Raus aus dem Konferenzraum, rein ins Gespräch.',
				'body'    => 'Mehrtägiges Offsite mit Workshop-Räumen.',
				'url'     => add_query_arg( 'format', 'offsite', $url_events ),
				'img'     => 'venue-clubhouse.jpg',
			],
			[
				'eyebrow' => 'HR & BGM',
				'title'   => 'Bewegung in den Arbeitsalltag bringen.',
				'body'    => 'Gesundheitstag, BGM-konform abrechenbar.',
				'url'     => add_query_arg( 'format', 'gesundheitstag', $url_events ),
				'img'     => 'hero-forest.jpg',
			],
			[
				'eyebrow' => 'Top-Performer',
				'title'   => 'Eure besten Leute besonders behandeln.',
				'body'    => 'Incentive-Reise mit Übernachtung und privatem Dinner.',
				'url'     => add_query_arg( 'format', 'incentive', $url_events ),
				'img'     => 'hero-mountains.jpg',
			],
			[
				'eyebrow' => 'Team-Tag',
				'title'   => 'Ein gemeinsamer Nachmittag draußen.',
				'body'    => 'Team-Building mit gemischten Zweier-Teams.',
				'url'     => add_query_arg( 'format', 'team-building', $url_events ),
				'img'     => 'event-team.jpg',
			],
		];
		foreach ( $occasions as $occ ) : ?>
			<a class="home-occ" href="<?php echo esc_url( $occ['url'] ); ?>">
				<div class="home-occ-photo" style="background-image:url('<?php echo esc_url( $img( $occ['img'] ) ); ?>')"></div>
				<div class="home-occ-body">
					<div class="mk-eyebrow" style="color:var(--fairway-700)"><?php echo esc_html( $occ['eyebrow'] ); ?></div>
					<h3 class="home-occ-t"><?php echo esc_html( $occ['title'] ); ?></h3>
					<p class="home-occ-b"><?php echo esc_html( $occ['body'] ); ?></p>
					<div class="home-occ-foot">
						Passende Events ansehen <?php echo $arrow_svg; // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</div>
				</div>
			</a>
		<?php endforeach; ?>
	</div>
</section>

<?php /* ══════════════════ 6. INDIVIDUAL TEASER ══════════════════ */ ?>
<section class="mk-section home-individual" aria-label="Individuelle Events">
	<div class="home-individual-grid">
		<div class="home-ind-photo" style="background-image:url('<?php echo esc_url( $img( 'event-corporate.jpg' ) ); ?>')"></div>
		<div class="home-ind-text">
			<div class="mk-eyebrow">Individuelle Events</div>
			<h2 class="mk-h2">
				Nichts dabei? <em class="mk-italic">Wir planen</em> dein Event nach deinen Ansprüchen.
			</h2>
			<p class="mk-sub">
				Sonderwünsche, eigene Location, mehrtägiges Programm, internationale Gruppe — beschreib uns kurz,
				was du vorhast. Wir bauen das Format für euch und schlagen die passenden Plätze vor.
			</p>
			<div class="home-ind-points">
				<div><?php echo $check_svg; // phpcs:ignore WordPress.Security.EscapeOutput ?><span>Persönliche Beratung in 24 h</span></div>
				<div><?php echo $check_svg; // phpcs:ignore WordPress.Security.EscapeOutput ?><span>Maßgeschneidertes Programm</span></div>
				<div><?php echo $check_svg; // phpcs:ignore WordPress.Security.EscapeOutput ?><span>Ein Ansprechpartner, eine Rechnung</span></div>
			</div>
			<div class="home-ind-ctas">
				<a class="fg-btn-cta" href="<?php echo esc_url( $url_ind ); ?>">
					Event anfragen
					<span class="fg-arrow"><?php echo $arrow_right; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				</a>
				<a class="fg-btn-ghost" href="<?php echo esc_url( $url_kontakt ); ?>">
					Mit uns sprechen
				</a>
			</div>
		</div>
	</div>
</section>

<?php /* ══════════════════ 7. BENEFIT TEASER ══════════════════ */ ?>
<section class="home-benefit" aria-label="Corporate Benefit">
	<div class="home-benefit-inner">
		<div class="home-benefit-eyebrow">Corporate Benefit · firmen.golf</div>
		<h2 class="home-benefit-h">
			Golf als Benefit, den deine Mitarbeitenden <em class="mk-italic">spüren</em>.
		</h2>
		<p class="home-benefit-sub">
			50 € steuerfreier Sachbezug pro Monat, Zugang zu Partnerplätzen, Coaching-Stunden zum Mitarbeiterpreis.
			Bewegung statt Obstkorb — und die HR-Abrechnung läuft sauber.
		</p>
		<div class="home-benefit-ctas">
			<a class="fg-btn-ink fg-btn-lg" href="https://firmen.golf" target="_blank" rel="noopener noreferrer"
			   style="background:var(--paper-100);color:var(--fairway-900)">
				Zum Benefit-Programm
				<span class="fg-arrow" style="background:var(--fairway-200)"><?php echo $arrow_right; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			</a>
			<span class="home-benefit-tag">firmen.golf ↗</span>
		</div>
	</div>
</section>

<?php /* ══════════════════ 8. NUMBERS ══════════════════ */ ?>
<section class="home-numbers" aria-label="Firmengolf in Zahlen">
	<div class="home-numbers-inner">
		<div class="home-numbers-aside">
			<div class="mk-eyebrow" style="color:var(--fairway-300)">In Zahlen</div>
			<h2 class="home-numbers-h">
				Was wir bisher <em class="mk-italic">gebaut</em> haben.
			</h2>
			<p class="home-numbers-p">
				Drei Jahre, ein einziges Versprechen — Firmenevents auf Golfplätzen, die niemand vergisst.
				Stand: Mai 2026.
			</p>
		</div>
		<div class="home-numbers-grid">
			<?php
			$numbers = [
				[ '180+',    'Partnerplätze in Deutschland' ],
				[ '2.400',   'Mitarbeitende im Benefit-Programm' ],
				[ '< 24 h',  'Antwortzeit auf jede Anfrage' ],
				[ '4,9 ★',   'Durchschnittliche Bewertung' ],
			];
			foreach ( $numbers as $n ) : ?>
				<div class="home-num">
					<div class="home-num-v"><?php echo esc_html( $n[0] ); ?></div>
					<div class="home-num-l"><?php echo esc_html( $n[1] ); ?></div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<?php /* ══════════════════ 9. TESTIMONIAL ══════════════════ */ ?>
<section class="mk-section mk-testimonial" aria-label="Kundenstimme">
	<div class="mk-testimonial-grid">
		<div class="mk-testimonial-photo" style="background-image:url('<?php echo esc_url( $img( 'event-team.jpg' ) ); ?>')"></div>
		<blockquote class="mk-testimonial-body">
			<div class="mk-quote-mark" aria-hidden="true">&ldquo;</div>
			<p>Das Team kam aufgeladen zurück, und drei Leute haben am Sonntag direkt wieder gespielt. Die Latte für nächstes Jahr liegt hoch.</p>
			<footer>
				<img src="<?php echo esc_url( $img( 'tile-people.jpg' ) ); ?>" alt="Lena Hoffmann" width="44" height="44">
				<div>
					<div class="mk-tm-name">Lena Hoffmann</div>
					<div class="mk-tm-role">People Lead · Quartz Labs</div>
				</div>
			</footer>
		</blockquote>
	</div>
</section>

<?php /* ══════════════════ 10. BLOG TEASER ══════════════════ */ ?>
<section class="mk-section" aria-label="Aus dem Magazin">
	<div class="mk-section-head between">
		<div>
			<div class="mk-eyebrow">Aus dem Magazin</div>
			<h2 class="mk-h2">Was wir grad denken &amp; schreiben.</h2>
		</div>
		<a class="fg-btn-ghost" href="<?php echo esc_url( $url_blog ); ?>">
			Alle Artikel <?php echo $arrow_svg; // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</a>
	</div>
	<div class="home-blog-grid">
		<?php if ( ! empty( $blog_posts ) ) : ?>
			<?php foreach ( $blog_posts as $post ) :
				$thumb = has_post_thumbnail( $post->ID ) ? get_the_post_thumbnail_url( $post->ID, 'large' ) : $img( 'event-team.jpg' );
				$tags  = wp_get_post_tags( $post->ID, [ 'fields' => 'names' ] );
				$tag   = ! empty( $tags ) ? $tags[0] : '';
				$words = str_word_count( wp_strip_all_tags( $post->post_content ) );
				$read  = max( 1, (int) round( $words / 200 ) ) . ' Min.';
			?>
				<article class="home-blog-card">
					<a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" style="display:contents">
						<div class="home-blog-photo" style="background-image:url('<?php echo esc_url( $thumb ); ?>')"></div>
						<div class="home-blog-body">
							<div class="home-blog-meta">
								<?php if ( $tag ) : ?>
									<span class="home-blog-tag"><?php echo esc_html( $tag ); ?></span>
									<span>·</span>
								<?php endif; ?>
								<span><?php echo esc_html( $read ); ?></span>
							</div>
							<h3 class="home-blog-t"><?php echo esc_html( $post->post_title ); ?></h3>
							<p class="home-blog-x"><?php echo esc_html( wp_trim_words( $post->post_excerpt ?: wp_strip_all_tags( $post->post_content ), 20 ) ); ?></p>
							<div class="home-blog-author">
								<span class="home-blog-author-name"><?php echo esc_html( get_the_author_meta( 'display_name', (int) $post->post_author ) ); ?></span>
								<span class="home-blog-author-date"><?php echo esc_html( get_the_date( 'j. F Y', $post ) ); ?></span>
							</div>
						</div>
					</a>
				</article>
			<?php endforeach; ?>
		<?php else : ?>
			<?php
			$static_posts = [
				[ 'Benefits', 'Warum Golf zum Corporate Benefit passt', 'event-team.jpg', '6 Min.', 'Lena Hoffmann', '14. Mai 2026', '50 € steuerfreier Sachbezug, fittere Mitarbeitende — wir erklären die wichtigsten Punkte.' ],
				[ 'Praxis', 'Die 12-Punkte-Checkliste für dein erstes Firmen-Golfevent', 'event-corporate.jpg', '4 Min.', 'Jonas Bredow', '2. Mai 2026', 'Vom richtigen Zeitfenster bis zum Wetter-Backup — was du im Blick haben solltest.' ],
				[ 'Einsteiger', '"Aber wir können doch alle nicht Golf spielen"', 'tile-grass.jpg', '5 Min.', 'Petra Sailer', '18. April 2026', 'Genau das ist der Punkt. Wie ein Schnupperkurs für absolute Einsteigende funktioniert.' ],
			];
			foreach ( $static_posts as $p ) : ?>
				<article class="home-blog-card">
					<div class="home-blog-photo" style="background-image:url('<?php echo esc_url( $img( $p[2] ) ); ?>')"></div>
					<div class="home-blog-body">
						<div class="home-blog-meta">
							<span class="home-blog-tag"><?php echo esc_html( $p[0] ); ?></span>
							<span>·</span>
							<span><?php echo esc_html( $p[3] ); ?></span>
						</div>
						<h3 class="home-blog-t"><?php echo esc_html( $p[1] ); ?></h3>
						<p class="home-blog-x"><?php echo esc_html( $p[6] ); ?></p>
						<div class="home-blog-author">
							<span class="home-blog-author-name"><?php echo esc_html( $p[4] ); ?></span>
							<span class="home-blog-author-date"><?php echo esc_html( $p[5] ); ?></span>
						</div>
					</div>
				</article>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
</section>

<?php /* ══════════════════ 11. CLOSING CTA ══════════════════ */ ?>
<section class="mk-cta" aria-label="Event anfragen">
	<div class="mk-cta-inner">
		<div class="mk-eyebrow" style="color:rgba(251,250,246,0.65)">Bereit?</div>
		<h2 class="mk-cta-h">
			Lasst uns euer nächstes Event <em class="mk-italic">zusammen</em> planen.
		</h2>
		<p class="mk-cta-sub">
			Antwort innerhalb eines Werktags. Kein Vertriebs-Druck, kein Telefon-Marathon.
			Du beschreibst kurz, was du vorhast — wir kümmern uns um den Rest.
		</p>
		<div class="mk-cta-ctas">
			<a class="fg-btn-ink fg-btn-lg" href="<?php echo esc_url( $url_ind ); ?>"
			   style="background:var(--paper-100);color:var(--fairway-900)">
				Event anfragen
				<span class="fg-arrow" style="background:var(--fairway-200)"><?php echo $arrow_right; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			</a>
			<a class="mk-cta-mail" href="mailto:events@firmengolf.de">events@firmengolf.de</a>
		</div>
	</div>
</section>

<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>
<?php get_footer(); ?>
