<?php
/**
 * Individuelle Events — rebuilt nach Design (Individual.jsx).
 * Hero · Veranstaltungstyp · Budget-Rechner · Golf-Erfahrung · Nacht-Event · Foto-CTA · FAQ.
 * Budget-Rechner + Anfrage-Wizard laufen als JS-Insel (assets/js/fge-individual.js);
 * Preise kommen aus fge_bc_config() (Backend: Events → Budget-Rechner).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// SEO: Title + Meta + OpenGraph für die Geldseite „Individuelle Events".
$ie_title = 'Individuelles Firmen-Golfevent planen | Firmengolf';
$ie_desc  = 'Plant euer individuelles Firmen-Golfevent: Teamevent, Incentive, Sommerfest oder Kundenturnier, passend zu Budget und Gruppe. Budget-Rechner und Anfrage in wenigen Minuten.';
add_filter( 'pre_get_document_title', function () use ( $ie_title ) { return $ie_title; } );
add_action( 'wp_head', function () use ( $ie_title, $ie_desc ) {
	$GLOBALS['fge_seo_meta_done'] = true;
	echo '<meta name="description" content="' . esc_attr( $ie_desc ) . '">' . "\n";
	echo '<meta property="og:type" content="website">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $ie_title ) . '">' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $ie_desc ) . '">' . "\n";
	echo '<meta property="og:url" content="' . esc_url( get_permalink() ) . '">' . "\n";
	$ie_og_img = function_exists( 'fge_default_og_image_url' ) ? fge_default_og_image_url() : '';
	if ( $ie_og_img ) { echo '<meta property="og:image" content="' . esc_url( $ie_og_img ) . '">' . "\n"; }
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
} );

$bc = function_exists( 'fge_bc_config' ) ? fge_bc_config() : null;

$img = static function ( $name ) {
	return esc_url( fge_get_placeholder_image_url( $name ) );
};

// Inline-SVG-Pfade für die Service-Chip-Icons (entsprechen BcIcon im Design).
$bc_icon_paths = [
	'catering' => '<path d="M5 8h12v4a6 6 0 0 1-12 0z"/><path d="M17 9h2a2 2 0 0 1 0 4h-2M5 21h12"/>',
	'coaching' => '<path d="M5 21V4M5 4l11 2-3 4 3 4-11 2"/>',
	'bed'      => '<path d="M3 18v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6"/><path d="M3 14h18M7 10V7a1 1 0 0 1 1-1h3v4"/>',
	'bus'      => '<rect x="4" y="4" width="16" height="13" rx="2"/><path d="M4 11h16M8 17v2M16 17v2"/><circle cx="8" cy="14" r="1"/><circle cx="16" cy="14" r="1"/>',
	'show'     => '<path d="M13 2L4 14h6l-1 8 9-12h-6z"/>',
	'cam'      => '<rect x="3" y="7" width="18" height="13" rx="2"/><circle cx="12" cy="13.5" r="3.2"/><path d="M8 7l1.5-3h5L16 7"/>',
];
$bc_svg = static function ( $name ) use ( $bc_icon_paths ) {
	$inner = $bc_icon_paths[ $name ] ?? '';
	return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
};

$start_services = ( $bc && isset( $bc['start']['services'] ) ) ? (array) $bc['start']['services'] : [];
$start_range    = ( $bc && isset( $bc['start']['range'] ) ) ? $bc['start']['range'] : '€€';
$start_type     = ( $bc && isset( $bc['start']['type'] ) ) ? $bc['start']['type'] : '';
$start_parts    = ( $bc && isset( $bc['start']['participants'] ) ) ? (int) $bc['start']['participants'] : 30;

// Veranstaltungstyp-Kacheln
$type_tiles = [
	[ 't' => 'Sommerfest',    'sub' => 'Der Abend unter freiem Himmel', 'img' => 'event-summer.jpg',   'occasion' => 'Sommerfest' ],
	[ 't' => 'Firmenturnier', 'sub' => 'Pokale, Flights & Siegerehrung', 'img' => 'event-corporate.jpg', 'occasion' => 'Firmenturnier' ],
	[ 't' => 'Teamevent',     'sub' => 'Spielerisch zusammenwachsen',    'img' => 'event-team.jpg',      'occasion' => 'Teamevent' ],
	[ 't' => 'Kundenevent',   'sub' => 'Golf, Dinner & echte Gespräche', 'img' => 'event-toast.jpg',     'occasion' => 'Kundenevent' ],
];

// Golf-Erfahrung
$exp_levels = [
	[ 'level' => 1, 'badge' => 'Einsteiger', 't' => 'Erste Erfahrungen', 'img' => 'erfahrung-korb.jpg',
		'b' => 'Noch nie einen Schläger gehalten? Genau richtig. PGA-Coach, Leih-Ausrüstung und die ersten Schwünge auf der Range — locker, ohne Druck.',
		'meta' => [ 'PGA-Coach', 'Schläger gestellt', 'Range & Putting' ] ],
	[ 'level' => 2, 'badge' => 'Auffrischer', 't' => 'Schon mal gespielt', 'img' => 'erfahrung-sand.jpg',
		'b' => 'Ein paar Runden Erfahrung? Wir frischen den Schwung auf, gehen ins Kurzspiel und spielen danach gemeinsam entspannte 9 Loch.',
		'meta' => [ 'Kurzspiel-Training', '9 Loch', 'Gemischte Flights' ] ],
	[ 'level' => 3, 'badge' => 'Fortgeschritten', 't' => 'Fortgeschrittene Golfer', 'img' => 'erfahrung-inselgruen.jpg',
		'b' => 'Platzreife in der Tasche? Volle 18 Loch im Turnierformat mit Flights, Live-Scoring und Siegerehrung bei Sonnenuntergang.',
		'meta' => [ '18 Loch', 'Live-Scoring', 'Siegerehrung' ] ],
];

$faqs = [
	[ 'q' => 'Wie viel kostet ein individuelles Event?', 'a' => 'Sehr unterschiedlich. Ein abendliches Sommerfest für 80 Personen liegt typischerweise bei €15.000–€25.000, ein zweitägiges Strategie-Offsite für 20 Personen bei €30.000–€50.000. Wir gehen das nach der Anfrage transparent durch und du bekommst ein vollständiges Angebot mit allen Posten.' ],
	[ 'q' => 'Wie viel Vorlauf brauchen wir?', 'a' => 'Idealerweise 3 Monate für mehrtägige Formate, 6–8 Wochen für eintägige. Kurzfristiger geht oft auch — das hängt vom Datum und der Region ab.' ],
	[ 'q' => 'Können wir eigene Locations einbringen?', 'a' => 'Ja. Wenn ihr eine Wunsch-Location habt, sprechen wir mit dem Platz und integrieren das ins Konzept. Wir arbeiten mit über 180 Plätzen direkt zusammen — und können neue Partner anfragen.' ],
	[ 'q' => 'Was, wenn wir noch keinen festen Plan haben?', 'a' => 'Genau dafür gibt es uns. Sag uns Anlass, ungefähre Gruppe und Region — wir schicken zwei bis drei sehr unterschiedliche Konzept-Vorschläge zurück, aus denen du wählst.' ],
	[ 'q' => 'Kommt jemand von euch vor Ort?', 'a' => 'Bei Events ab 30 Personen oder mehrtägigen Formaten — ja, immer. Ein Firmengolf-Host ist vor Ort und stimmt sich mit dem Platz ab.' ],
	[ 'q' => 'Was, wenn wir das Event verschieben müssen?', 'a' => 'Bis 30 Tage vorher kostenlos. Danach gestaffelt — die genauen Konditionen schreiben wir in jedes Angebot rein. Wir sind kulant und finden Lösungen.' ],
];

$nacht_preset = wp_json_encode( [
	'occasion' => 'Sommerfest',
	'notes'    => 'Interesse am Nacht-Event (Flutlicht).',
	'services' => [ 'Flutlicht / Nacht-Event', 'DJ', 'Bar & Drinks' ],
] );

get_header();
?>
<div class="fge-page">

	<?php
	$mbar_action = '<a class="ev-msearch ev-maction" href="#budget"><span>Event anfragen</span><span class="ev-maction-arrow"><svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg></span></a>';
	get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => 'individuelle-events', 'mbar_action' => $mbar_action ] );
	?>

	<?php /* ── Hero ── */ ?>
	<section class="ind-hero">
		<div class="ind-hero-photo" style="background-image:url('<?php echo $img( 'hero-meer.jpg' ); ?>');">
			<div class="ind-hero-scrim"></div>
			<div class="ind-hero-content">
				<div class="mk-hero-eyebrow">Individuelle Events</div>
				<h1 class="ind-hero-title">
					Euer Firmenevent — auf dem <em class="mk-italic">Golfplatz</em>.
				</h1>
				<p class="ind-hero-sub">
					Vom Teamevent bis zum Sommerfest, vom Turnier bis zur Incentive-Reise: Wir planen jeden
					Veranstaltungstyp auf dem passenden Platz. Sag uns kurz, was ihr vorhabt — wir machen den Rest.
				</p>
				<div class="mk-hero-ctas">
					<button type="button" class="fg-btn-cta fg-btn-lg" data-rw-open="full">
						Anfrage starten <span class="fg-arrow"><?php echo fge_icon_arrow_up_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					</button>
					<a class="fg-btn-ghost-light" href="#budget">Budget berechnen →</a>
				</div>
			</div>
		</div>
	</section>

	<?php /* ── Veranstaltungstyp ── */ ?>
	<section class="iv-section">
		<div class="iv-head">
			<div class="mk-eyebrow">Veranstaltungstyp</div>
			<h2 class="mk-h2">Wählt euren Veranstaltungstyp</h2>
			<p class="mk-sub">Jeder Veranstaltungstyp findet auf dem Golfplatz statt — als Location, die garantiert in Erinnerung bleibt.</p>
		</div>
		<div class="iv-tiles">
			<?php foreach ( $type_tiles as $tile ) :
				$preset = wp_json_encode( [ 'occasion' => $tile['occasion'] ] );
			?>
				<button type="button" class="iv-tile" data-rw-open="full" data-rw-intro
				        data-rw-preset="<?php echo esc_attr( $preset ); ?>">
					<span class="iv-tile-img" style="background-image:url('<?php echo $img( $tile['img'] ); ?>')"></span>
					<span class="iv-tile-scrim"></span>
					<span class="iv-tile-label">
						<span>
							<span class="iv-tile-t"><?php echo esc_html( $tile['t'] ); ?></span>
							<span class="iv-tile-sub" style="display:block;"><?php echo esc_html( $tile['sub'] ); ?></span>
						</span>
						<span class="iv-tile-arrow"><?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					</span>
				</button>
			<?php endforeach; ?>
		</div>
	</section>

	<?php /* ── Budget-Rechner ── */ ?>
	<section class="bcalc-wrap" id="budget">
		<div class="bcalc" id="bcalc">
			<div class="bcalc-head">
				<div class="mk-eyebrow" style="color:var(--fairway-700);">Budget-Rechner</div>
				<h2 class="mk-h2">Was kostet euer Event? <span class="mk-italic">Sofort</span> geschätzt.</h2>
				<p class="mk-sub">Stell ein paar Eckdaten ein und sieh in Echtzeit einen realistischen Richtwert — ganz unverbindlich, bevor wir gemeinsam ins Detail gehen.</p>
			</div>

			<?php if ( $bc ) : ?>
			<div class="bc-controls">
				<div class="bc-field">
					<span class="bc-flabel">Teilnehmende</span>
					<div class="bc-stepper">
						<button type="button" class="bc-step-btn" data-bc-step="-2" aria-label="Weniger">
							<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14"/></svg>
						</button>
						<span class="bc-step-val" id="bc-participants"><?php echo esc_html( (string) $start_parts ); ?></span>
						<button type="button" class="bc-step-btn" data-bc-step="2" aria-label="Mehr">
							<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
						</button>
					</div>
				</div>
				<div class="bc-field">
					<span class="bc-flabel">Veranstaltungstyp</span>
					<select class="bc-select" id="bc-type">
						<?php foreach ( $bc['types'] as $t ) : ?>
							<option value="<?php echo esc_attr( $t['id'] ); ?>" <?php selected( $start_type, $t['id'] ); ?>><?php echo esc_html( $t['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="bc-field">
					<span class="bc-flabel">Preisniveau</span>
					<div class="bc-seg">
						<?php foreach ( $bc['ranges'] as $r ) : ?>
							<button type="button" class="bc-seg-btn<?php echo $start_range === $r['id'] ? ' on' : ''; ?>" data-range="<?php echo esc_attr( $r['id'] ); ?>"><?php echo esc_html( $r['id'] ); ?></button>
						<?php endforeach; ?>
					</div>
				</div>
				<div class="bc-field">
					<span class="bc-flabel">Dauer</span>
					<div class="bc-stepper" style="align-items:center;">
						<span class="bc-step-val" id="bc-duration" style="min-width:0;font-size:16px;font-weight:500;">Halbtag+</span>
					</div>
				</div>
			</div>

			<div class="bc-services">
				<span class="bc-services-l">Gewünschte Services</span>
				<?php foreach ( $bc['services'] as $s ) :
					$on = in_array( $s['id'], $start_services, true );
				?>
					<button type="button" class="bc-chip<?php echo $on ? ' on' : ''; ?>" data-id="<?php echo esc_attr( $s['id'] ); ?>">
						<span class="bc-chip-ic"><?php echo $bc_svg( $s['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span><?php echo esc_html( $s['label'] ); ?>
					</button>
				<?php endforeach; ?>
			</div>

			<div class="bc-result">
				<div class="bc-break">
					<div class="bc-break-h">Kostenaufschlüsselung</div>
					<div class="bc-break-list" id="bc-break-list"></div>
				</div>
				<div class="bc-donut" id="bc-donut"></div>
				<div class="bc-total">
					<div class="bc-total-h">Gesamtbudget · Richtwert</div>
					<div class="bc-total-num" id="bc-total">€0</div>
					<div class="bc-total-meta" id="bc-total-meta"></div>
					<button type="button" class="fg-btn-ink lg bc-total-cta" id="bc-request">
						Unverbindliches Angebot anfragen <span class="fg-arrow"><?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					</button>
					<p class="bc-total-note">Unverbindlicher Schätzwert. Das finale Angebot stellen wir nach kurzer Rücksprache zusammen — transparent, mit allen Posten.</p>
				</div>
			</div>
			<?php else : ?>
				<p class="mk-sub" style="text-align:center;">Der Budget-Rechner ist gerade nicht verfügbar.</p>
			<?php endif; ?>
		</div>
	</section>

	<?php /* ── Golf-Erfahrung ── */ ?>
	<section class="iv-section">
		<div class="iv-head">
			<div class="mk-eyebrow">Golf-Erfahrung</div>
			<h2 class="mk-h2">Vom ersten Schwung bis zur <span class="mk-italic">Stammrunde</span></h2>
			<p class="mk-sub">In jedem Team spielt jemand zum ersten Mal — und jemand seit Jahren. Wir stellen jedes Event so zusammen, dass alle Spaß haben, egal auf welchem Level.</p>
		</div>
		<div class="iv-exp-grid">
			<?php foreach ( $exp_levels as $x ) : ?>
				<article class="iv-exp">
					<div class="iv-exp-photo" style="background-image:url('<?php echo $img( $x['img'] ); ?>')">
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

	<?php /* ── Nacht-Event ── */ ?>
	<section class="ind-night">
		<div class="ind-night-photo" style="background-image:url('<?php echo $img( 'golfplatz-luftaufnahme-2.jpg' ); ?>')"></div>
		<div class="ind-night-scrim"></div>
		<div class="ind-night-glow"></div>
		<div class="ind-night-inner">
			<div class="ind-night-badge">
				<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
				Spezial · nur bei Firmengolf
			</div>
			<h2 class="ind-night-h">Das <span class="mk-italic">Nacht-Event</span>.<br>Wir machen die Nacht zum Tag.</h2>
			<p class="ind-night-sub">
				Wir leuchten einen ganzen Golfplatz aus und verwandeln ihn in eine Bühne — Flutlicht-Parcours,
				Live-DJ, Food &amp; Drinks unter freiem Himmel. Ein Firmenevent der etwas anderen Art,
				das euer Team garantiert nicht vergisst.
			</p>
			<div class="ind-night-points">
				<div class="ind-night-point"><span class="ind-night-n">01</span>Ausgeleuchteter Flutlicht-Parcours</div>
				<div class="ind-night-point"><span class="ind-night-n">02</span>Live-DJ, Licht &amp; Sound</div>
				<div class="ind-night-point"><span class="ind-night-n">03</span>Food, Drinks &amp; Bar bis tief in die Nacht</div>
			</div>
			<button type="button" class="fg-btn-ink lg ind-night-cta" data-rw-open="full" data-rw-preset="<?php echo esc_attr( $nacht_preset ); ?>">
				Nacht-Event anfragen <span class="fg-arrow"><?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			</button>
		</div>
	</section>

	<?php /* ── Foto-CTA ── */ ?>
	<section class="mk-section ind-launch" id="anfrage">
		<div class="ind-launch-card">
			<div class="ind-launch-bg" style="background-image:url('<?php echo $img( 'golfplatz-fairway.jpg' ); ?>')"></div>
			<div class="ind-launch-scrim"></div>
			<div class="ind-launch-content">
				<div class="mk-eyebrow" style="color:var(--fairway-300);">Bereit?</div>
				<h2 class="ind-launch-h">Erzählt uns von eurem Event.</h2>
				<p class="ind-launch-p">
					Geführte Anfrage in fünf kurzen Schritten — ca. zwei Minuten, unverbindlich.
					Ein Ansprechpartner, ein Angebot, eine Rechnung.
				</p>
				<div class="ind-launch-ctas">
					<button type="button" class="fg-btn-ink lg" data-rw-open="full" style="background:var(--paper-100);color:var(--fairway-900);">
						Anfrage starten <span class="fg-arrow" style="background:var(--fairway-200);"><?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					</button>
					<button type="button" class="ind-launch-quick" data-rw-open="quick">Schnell-Anfrage in 30 Sekunden →</button>
				</div>
			</div>
		</div>
	</section>

	<?php /* ── FAQ ── */ ?>
	<section class="mk-section faq-section">
		<div class="faq-shell">
			<div class="faq-aside">
				<div class="mk-eyebrow">FAQ</div>
				<h2 class="mk-h2" style="margin-top:8px;font-size:36px;">Was wir oft gefragt werden.</h2>
				<p class="mk-sub">Antworten auf das, was bei individuellen Events am häufigsten unklar ist.</p>
				<div class="faq-cta">
					<a class="fg-btn-ghost" href="<?php echo esc_url( home_url( '/kontakt/' ) ); ?>">
						Etwas anderes fragen <?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</a>
				</div>
			</div>
			<ul class="faq-list">
				<?php foreach ( $faqs as $i => $faq ) : ?>
					<li class="faq-item" id="faq-<?php echo esc_attr( (string) $i ); ?>">
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

	<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>

<script>
(function () {
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
