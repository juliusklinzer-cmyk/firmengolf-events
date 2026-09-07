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
	'catering'  => '<path d="M5 8h12v4a6 6 0 0 1-12 0z"/><path d="M17 9h2a2 2 0 0 1 0 4h-2M5 21h12"/>',
	'coaching'  => '<path d="M5 21V4M5 4l11 2-3 4 3 4-11 2"/>',
	'bed'       => '<path d="M3 18v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6"/><path d="M3 14h18M7 10V7a1 1 0 0 1 1-1h3v4"/>',
	'bus'       => '<rect x="4" y="4" width="16" height="13" rx="2"/><path d="M4 11h16M8 17v2M16 17v2"/><circle cx="8" cy="14" r="1"/><circle cx="16" cy="14" r="1"/>',
	'show'      => '<path d="M13 2L4 14h6l-1 8 9-12h-6z"/>',
	'cam'       => '<rect x="3" y="7" width="18" height="13" rx="2"/><circle cx="12" cy="13.5" r="3.2"/><path d="M8 7l1.5-3h5L16 7"/>',
	'trophy'    => '<path d="M8 21h8M12 17v4M7 4h10v4a5 5 0 0 1-10 0z"/><path d="M5 4H3v2a3 3 0 0 0 3 3M19 4h2v2a3 3 0 0 1-3 3"/>',
	'target'    => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4"/><circle cx="12" cy="12" r="1"/>',
	'flag'      => '<path d="M5 21V4l9 2.5L5 9"/><circle cx="17" cy="17" r="3"/>',
	'club'      => '<path d="M7 3v11M7 14a3 3 0 1 0 0 6 3 3 0 0 0 0-6zM7 8l9-4"/>',
	'clipboard' => '<rect x="6" y="4" width="12" height="17" rx="2"/><path d="M9 4V3h6v1M9 10h6M9 14h6M9 18h4"/>',
	'star'      => '<path d="M12 3l2.6 5.3 5.8.8-4.2 4.1 1 5.8L12 16.8 6.8 19l1-5.8L3.6 9.1l5.8-.8z"/>',
	'drink'     => '<path d="M5 4h14l-6 8v6M13 18h-2M9 22h6M19 4l-2 4"/>',
	'music'     => '<circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/><path d="M9 18V5l12-2v13"/>',
	'room'      => '<path d="M3 21h18M5 21V6l8-3v18M13 21V9l6 2v10M8 9h1M8 13h1M8 17h1"/>',
	'gift'      => '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M5 12v9h14v-9"/><path d="M12 8S10.5 3 8 4.5 9.5 8 12 8zM12 8s1.5-5 4-3.5S14.5 8 12 8z"/>',
	'tag'       => '<path d="M3 3h8l10 10-8 8L3 11z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
	'calendar'  => '<rect x="3" y="4.5" width="18" height="16" rx="2.5"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4"/>',
	'bulb'      => '<path d="M9 18h6M10 21h4M12 3a6 6 0 0 0-4 10.5c.7.7 1 1.5 1 2.5h6c0-1 .3-1.8 1-2.5A6 6 0 0 0 12 3z"/>',
	'ball'      => '<circle cx="12" cy="12" r="9"/><circle cx="9" cy="10" r="1"/><circle cx="14" cy="9" r="1"/><circle cx="12" cy="14" r="1"/><circle cx="15" cy="14" r="1"/>',
];
$bc_svg = static function ( $name ) use ( $bc_icon_paths ) {
	$inner = $bc_icon_paths[ $name ] ?? '';
	return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
};

$start_range    = ( $bc && isset( $bc['start']['range'] ) ) ? $bc['start']['range'] : '€€';
$start_type     = ( $bc && isset( $bc['start']['type'] ) ) ? $bc['start']['type'] : '';
$start_parts    = ( $bc && isset( $bc['start']['participants'] ) ) ? (int) $bc['start']['participants'] : 30;

// Start-Typ-Konfig: bestimmt initial sichtbare/vorausgewählte/gesperrte Service-Chips.
// (JS übernimmt das bei jedem Typ-Wechsel; hier nur zur flackerfreien Erstanzeige.)
$bc_start_type_cfg = null;
if ( $bc ) {
	foreach ( $bc['types'] as $bt ) {
		if ( $bt['id'] === $start_type ) {
			$bc_start_type_cfg = $bt;
			break;
		}
	}
	if ( ! $bc_start_type_cfg && ! empty( $bc['types'] ) ) {
		$bc_start_type_cfg = $bc['types'][0];
	}
}
$start_vis = $bc_start_type_cfg['services'] ?? [];
$start_don = $bc_start_type_cfg['default_on'] ?? [];
$start_req = $bc_start_type_cfg['required'] ?? [];

// Veranstaltungstyp-Kacheln
$type_tiles = [
	[ 't' => 'Sommerfest',    'sub' => 'Der Abend unter freiem Himmel', 'img' => 'hero-golfloch-abendlicht.jpg',    'occasion' => 'Sommerfest' ],
	[ 't' => 'Firmenturnier', 'sub' => 'Pokale, Flights & Siegerehrung', 'img' => 'golf-gruen-fahne.jpg',            'occasion' => 'Firmen-Golfturnier' ],
	[ 't' => 'Teamevent',     'sub' => 'Spielerisch zusammenwachsen',    'img' => 'golferinnen-duo-green.png',       'occasion' => 'Golf-Teamevent' ],
	[ 't' => 'Kundenevent',   'sub' => 'Golf, Dinner & echte Gespräche', 'img' => 'pool/kundenevent-handshake.jpg',  'occasion' => 'Golf-Kundenevent' ],
];

// Golf-Erfahrung
$exp_levels = [
	[ 'level' => 1, 'badge' => 'Einsteiger', 't' => 'Erste Erfahrungen', 'img' => 'erfahrung-korb.jpg',
		'b' => 'Noch nie einen Schläger gehalten? Genau richtig. Golflehrer, Leih-Ausrüstung und die ersten Schwünge auf der Range, locker, ohne Druck.',
		'meta' => [ 'Golflehrer', 'Schläger gestellt', 'Range & Putting' ] ],
	[ 'level' => 2, 'badge' => 'Auffrischer', 't' => 'Schon mal gespielt', 'img' => 'erfahrung-sand.jpg',
		'b' => 'Ein paar Runden Erfahrung? Wir frischen den Schwung auf, gehen ins Kurzspiel und spielen danach gemeinsam entspannte 9 Loch.',
		'meta' => [ 'Kurzspiel-Training', '9 Loch', 'Gemischte Flights' ] ],
	[ 'level' => 3, 'badge' => 'Fortgeschritten', 't' => 'Fortgeschrittene Golfer', 'img' => 'erfahrung-inselgruen.jpg',
		'b' => 'Platzreife in der Tasche? Volle 18 Loch im Turnierformat mit Flights, Live-Scoring und Siegerehrung bei Sonnenuntergang.',
		'meta' => [ '18 Loch', 'Live-Scoring', 'Siegerehrung' ] ],
];

$faqs = [
	[ 'q' => 'Wie viel kostet ein individuelles Event?', 'a' => 'Sehr unterschiedlich. Ein abendliches Sommerfest für 80 Personen liegt typischerweise bei €15.000 bis €25.000, ein zweitägiges Strategie-Offsite für 20 Personen bei €30.000 bis €50.000. Wir gehen das nach der Anfrage transparent durch und du bekommst ein vollständiges Angebot mit allen Posten.' ],
	[ 'q' => 'Wie viel Vorlauf brauchen wir?', 'a' => 'Idealerweise 3 Monate für mehrtägige Formate, 6 bis 8 Wochen für eintägige. Kurzfristiger geht oft auch, das hängt vom Datum und der Region ab.' ],
	[ 'q' => 'Können wir eigene Locations einbringen?', 'a' => 'Ja. Wenn ihr eine Wunsch-Location habt, sprechen wir mit dem Platz und integrieren das ins Konzept. Wir organisieren Events deutschlandweit, in Deutschland kommen rund 721 Golfplätze als Eventlocation in Frage, passt einer nicht, nehmen wir einfach den nächsten.' ],
	[ 'q' => 'Was, wenn wir noch keinen festen Plan haben?', 'a' => 'Genau dafür gibt es uns. Sag uns Anlass, ungefähre Gruppe und Region, wir schicken zwei bis drei sehr unterschiedliche Konzept-Vorschläge zurück, aus denen du wählst.' ],
	[ 'q' => 'Kommt jemand von euch vor Ort?', 'a' => 'Bei Events ab 30 Personen oder mehrtägigen Formaten, ja, immer. Ein Firmengolf-Host ist vor Ort und stimmt sich mit dem Platz ab.' ],
	[ 'q' => 'Was, wenn wir das Event verschieben müssen?', 'a' => 'Bis 30 Tage vorher kostenlos. Danach gestaffelt, die genauen Konditionen schreiben wir in jedes Angebot rein. Wir sind kulant und finden Lösungen.' ],
];

$nacht_preset = wp_json_encode( [
	'occasion' => 'Nachtgolf-Event',
	'notes'    => 'Interesse am Nacht-Event (Flutlicht).',
	'services' => [ 'Flutlicht / Nacht-Event', 'DJ', 'Bar & Drinks' ],
] );

get_header();
?>
<div class="fge-page" id="fge-main" role="main" tabindex="-1">

	<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => 'individuelle-events' ] ); ?>

	<?php /* ── Hero ── */ ?>
	<section class="ind-hero">
		<div class="ind-hero-photo" style="background-image:url('<?php echo $img( 'hero-meer.jpg' ); ?>');">
			<div class="ind-hero-scrim"></div>
			<div class="ind-hero-content">
				<div class="mk-hero-eyebrow">Individuelle Events</div>
				<h1 class="ind-hero-title">
					Euer Firmenevent, auf dem <em class="mk-italic">Golfplatz</em>.
				</h1>
				<p class="ind-hero-sub">
					Vom Teamevent bis zum Sommerfest, vom Turnier bis zur Incentive-Reise: Wir planen jeden
					Veranstaltungstyp auf dem passenden Platz. Sag uns kurz, was ihr vorhabt, wir machen den Rest.
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

	<?php /* ── Weihnachtsfeier: eigene Sektion mit Kurz-Anfrage (Julius, 07.09.) ──
		Schritt 1 Eckdaten (Ort, Personen, Catering, Getränke, Wunschdatum, Region),
		Schritt 2 Firma + Kontakt, dann Bestätigung. Geht über den bestehenden
		fge_general_request-Endpunkt (Spam-Fallen, Anfrage-Post, Mails). */ ?>
	<?php
	$xmas_min_date = ( new DateTime( '+7 days', wp_timezone() ) )->format( 'Y-m-d' );
	$xmas_check    = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';
	?>
	<section class="xmas" id="weihnachtsfeier" aria-label="Weihnachtsfeier planen">
		<div class="xmas-inner">
			<div class="xmas-text">
				<h2 class="mk-h2">Plant jetzt eure <em class="mk-italic">Weihnachtsfeier</em> mit uns.</h2>
				<p class="mk-sub">Indoor an den Simulatoren oder im Clubhaus der Golfanlage: Golf-Challenge, Menü und Getränke aus einer Hand. Ihr nennt uns die Eckdaten, wir schicken ein konkretes Angebot aus eurer Region.</p>
				<div class="xmas-points">
					<div><?php echo $xmas_check; // phpcs:ignore WordPress.Security.EscapeOutput ?><span>Warm und wetterfest, auch ohne Golferfahrung</span></div>
					<div><?php echo $xmas_check; // phpcs:ignore WordPress.Security.EscapeOutput ?><span>Weihnachtsmenü und Getränkepauschale gleich mitgeplant</span></div>
					<div><?php echo $xmas_check; // phpcs:ignore WordPress.Security.EscapeOutput ?><span>Konkretes Angebot innerhalb eines Werktags</span></div>
				</div>
			</div>

			<form class="xmas-form" id="xmas-form" novalidate autocomplete="on">
				<div class="fg-step-rail" aria-hidden="true">
					<div class="fg-step done" data-xmas-rail="0"></div>
					<div class="fg-step" data-xmas-rail="1"></div>
				</div>

				<div class="xmas-step" data-xmas-step="0">
					<div class="xmas-step-h">Eure Feier in Eckdaten</div>
					<div class="xmas-grid">
						<div class="fg-field">
							<label class="fg-field-label" for="xmas-venue">Wo wollt ihr feiern?</label>
							<select class="fg-input" id="xmas-venue" name="venue">
								<option value="Indoor-Simulator">Indoor-Simulator</option>
								<option value="Golfanlage mit Clubhaus">Golfanlage mit Clubhaus</option>
								<option value="Offen, bitte beraten">Offen, bitte beraten</option>
							</select>
						</div>
						<div class="fg-field">
							<label class="fg-field-label" for="xmas-size">Wie viele Personen?</label>
							<select class="fg-input" id="xmas-size" name="size">
								<option value="8 bis 15 Personen">8 bis 15</option>
								<option value="16 bis 30 Personen" selected>16 bis 30</option>
								<option value="31 bis 50 Personen">31 bis 50</option>
								<option value="51 bis 80 Personen">51 bis 80</option>
								<option value="über 80 Personen">über 80</option>
							</select>
						</div>
						<div class="fg-field">
							<label class="fg-field-label" for="xmas-catering">Catering</label>
							<select class="fg-input" id="xmas-catering" name="catering">
								<option value="Weihnachtsmenü">Weihnachtsmenü, mehrgängig</option>
								<option value="Buffet">Buffet</option>
								<option value="Fingerfood und Snacks">Fingerfood und Snacks</option>
								<option value="ohne Catering">Ohne Catering</option>
							</select>
						</div>
						<div class="fg-field">
							<label class="fg-field-label" for="xmas-drinks">Getränke</label>
							<select class="fg-input" id="xmas-drinks" name="drinks">
								<option value="Getränkepauschale">Getränkepauschale</option>
								<option value="nach Verbrauch">Nach Verbrauch</option>
								<option value="später entscheiden">Später entscheiden</option>
							</select>
						</div>
						<div class="fg-field">
							<label class="fg-field-label" for="xmas-date">Wunschdatum</label>
							<input class="fg-input fg-date" type="date" id="xmas-date" name="date1" min="<?php echo esc_attr( $xmas_min_date ); ?>" required>
						</div>
						<div class="fg-field">
							<label class="fg-field-label" for="xmas-region">Stadt oder Region</label>
							<input class="fg-input" type="text" id="xmas-region" name="region" placeholder="z. B. München" autocomplete="address-level2" required>
						</div>
					</div>
					<div class="xmas-err" data-xmas-err hidden role="alert"></div>
					<button type="button" class="fg-btn-brand block xmas-cta" data-xmas-next>Jetzt anfragen</button>
					<p class="xmas-note">Unverbindlich und kostenlos. Im nächsten Schritt nur noch Firma und Kontakt.</p>
				</div>

				<div class="xmas-step" data-xmas-step="1" hidden>
					<div class="xmas-step-h">Wohin dürfen wir das Angebot schicken?</div>
					<div class="xmas-grid">
						<div class="fg-field fg-field-full">
							<label class="fg-field-label" for="xmas-company">Firma</label>
							<input class="fg-input" type="text" id="xmas-company" name="company" autocomplete="organization" required>
						</div>
						<div class="fg-field">
							<label class="fg-field-label" for="xmas-name">Vor- und Nachname</label>
							<input class="fg-input" type="text" id="xmas-name" name="first_name" autocomplete="name" required>
						</div>
						<div class="fg-field">
							<label class="fg-field-label" for="xmas-email">E-Mail</label>
							<input class="fg-input" type="email" id="xmas-email" name="email" autocomplete="email" required>
						</div>
						<div class="fg-field fg-field-full">
							<label class="fg-field-label" for="xmas-phone">Telefon <span class="fg-opt">optional</span></label>
							<input class="fg-input" type="tel" id="xmas-phone" name="phone" autocomplete="tel">
						</div>
					</div>
					<label class="xmas-consent">
						<input type="checkbox" id="xmas-consent" name="consent" value="1">
						<span>Ich stimme der Verarbeitung meiner Daten zur Bearbeitung der Anfrage gemäß <a href="<?php echo esc_url( home_url( '/datenschutz/' ) ); ?>" target="_blank" rel="noopener">Datenschutzerklärung</a> zu.</span>
					</label>
					<div class="xmas-err" data-xmas-err hidden role="alert"></div>
					<div class="xmas-foot">
						<button type="button" class="xmas-back" data-xmas-back>Zurück</button>
						<button type="submit" class="fg-btn-brand xmas-cta">Anfrage senden</button>
					</div>
				</div>

				<div class="xmas-step xmas-done" data-xmas-step="2" hidden aria-live="polite">
					<span class="xmas-done-ic" aria-hidden="true"><?php echo $xmas_check; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<div class="xmas-step-h">Danke, <span data-xmas-name>ihr</span>. Eure Anfrage ist bei uns.</div>
					<p>Wir melden uns innerhalb eines Werktags mit einem konkreten Angebot für eure Weihnachtsfeier in <span data-xmas-region>eurer Region</span>. Vorgangsnummer <strong data-xmas-ref></strong>.</p>
				</div>

				<input type="hidden" name="occasion" value="Weihnachtsfeier">
				<input type="hidden" name="source" value="weihnachtsfeier_section">
				<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'fge_general_request' ) ); ?>">
				<input type="text" name="fge_hp" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;">
				<?php echo fge_form_trap_fields(); // phpcs:ignore WordPress.Security.EscapeOutput -- Bot-Fallen ?>
			</form>
		</div>
	</section>
	<script>
	(function () {
		var form = document.getElementById('xmas-form');
		if (!form) return;
		var steps = form.querySelectorAll('[data-xmas-step]');
		var rails = form.querySelectorAll('[data-xmas-rail]');
		var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		function showStep(n) {
			steps.forEach(function (s) {
				var on = s.getAttribute('data-xmas-step') === String(n);
				if (on) { s.hidden = false; requestAnimationFrame(function () { s.classList.add('is-in'); }); }
				else { s.classList.remove('is-in'); s.hidden = true; }
			});
			rails.forEach(function (r) { r.classList.toggle('done', parseInt(r.getAttribute('data-xmas-rail'), 10) <= n); });
			if (n === 1) { var f = form.querySelector('#xmas-company'); if (f) f.focus({ preventScroll: true }); }
			if (!reduce) { var top = form.getBoundingClientRect().top; if (top < 0) form.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
		}
		// Erster Schritt sofort sichtbar (die Crossfade-Klasse setzt sonst erst der Wechsel)
		if (steps[0]) { steps[0].classList.add('is-in'); }
		function err(step, msg) {
			var box = steps[step].querySelector('[data-xmas-err]');
			box.textContent = msg || ''; box.hidden = !msg;
		}
		function mark(el, bad) { el.classList.toggle('fg-input-err', !!bad); }
		form.querySelector('[data-xmas-next]').addEventListener('click', function () {
			var date = form.querySelector('#xmas-date'), region = form.querySelector('#xmas-region');
			var okDate = !!date.value && (!date.min || date.value >= date.min), okRegion = region.value.trim().length > 1;
			mark(date, !okDate); mark(region, !okRegion);
			if (!okDate || !okRegion) { err(0, !okDate ? 'Bitte ein Wunschdatum mit mindestens 7 Tagen Vorlauf wählen.' : 'Bitte Stadt oder Region angeben.'); return; }
			err(0, ''); showStep(1);
		});
		form.querySelector('[data-xmas-back]').addEventListener('click', function () { err(1, ''); showStep(0); });
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var company = form.querySelector('#xmas-company'), name = form.querySelector('#xmas-name'), email = form.querySelector('#xmas-email'), consent = form.querySelector('#xmas-consent');
			var okC = company.value.trim() !== '', okN = name.value.trim() !== '', okE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim());
			mark(company, !okC); mark(name, !okN); mark(email, !okE);
			if (!okC || !okN || !okE) { err(1, 'Bitte Firma, Name und eine gültige E-Mail angeben.'); return; }
			if (!consent.checked) { err(1, 'Bitte stimme der Datenverarbeitung zu, um die Anfrage zu senden.'); return; }
			err(1, '');
			var btn = form.querySelector('button[type=submit]'); btn.disabled = true; var orig = btn.textContent; btn.textContent = 'Wird gesendet …';
			var v = function (id) { var el = form.querySelector('#' + id); return el ? el.value : ''; };
			var services = [v('xmas-venue'), v('xmas-catering') !== 'ohne Catering' ? v('xmas-catering') : '', v('xmas-drinks') === 'Getränkepauschale' ? 'Getränkepauschale' : ''].filter(Boolean);
			var body = new URLSearchParams({
				action: 'fge_general_request',
				nonce: form.querySelector('[name=nonce]').value,
				fge_ft: form.querySelector('[name=fge_ft]').value,
				fge_js: form.querySelector('[name=fge_js]').value,
				fge_hp: form.querySelector('[name=fge_hp]').value,
				source: 'weihnachtsfeier_section',
				occasion: 'Weihnachtsfeier',
				size: v('xmas-size'),
				date1: v('xmas-date'),
				region: v('xmas-region'),
				city: v('xmas-region'),
				when: 'Dezember, Wunschdatum siehe Termin',
				services: services.join('||'),
				notes: 'Weihnachtsfeier: ' + v('xmas-venue') + ', ' + v('xmas-size') + ', Catering: ' + v('xmas-catering') + ', Getränke: ' + v('xmas-drinks') + '.',
				company: company.value.trim(),
				first_name: name.value.trim(),
				last_name: '',
				email: email.value.trim(),
				phone: v('xmas-phone'),
				consent: '1'
			});
			fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', { method: 'POST', body: body, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (!res || !res.success) { throw new Error((res && res.data && res.data.message) || 'Das hat nicht geklappt. Bitte versuch es gleich noch einmal.'); }
					form.querySelector('[data-xmas-name]').textContent = name.value.trim().split(/\s+/)[0] || 'ihr';
					form.querySelector('[data-xmas-region]').textContent = v('xmas-region');
					form.querySelector('[data-xmas-ref]').textContent = res.data.ref || '';
					rails.forEach(function (r) { r.classList.add('done'); });
					showStep(2);
					// Meta-Lead nur mit Einwilligung (fbq existiert erst nach Klaro-Zustimmung); eventID serverseitig (CAPI-Dedup).
					if (window.fbq && res.data && res.data.fb_event_id) { try { fbq('track', 'Lead', { content_name: 'Weihnachtsfeier Kurz-Anfrage' }, { eventID: res.data.fb_event_id }); } catch (x) {} }
				})
				.catch(function (x) { err(1, x.message); btn.disabled = false; btn.textContent = orig; });
		});
	})();
	</script>

	<?php /* ── Veranstaltungstyp ── */ ?>
	<section class="iv-section">
		<div class="iv-head">
			<div class="mk-eyebrow">Veranstaltungstyp</div>
			<h2 class="mk-h2">Wählt euren Veranstaltungstyp</h2>
			<p class="mk-sub">Jeder Veranstaltungstyp findet auf dem Golfplatz statt, als Location, die garantiert in Erinnerung bleibt.</p>
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
				<p class="mk-sub">Stell ein paar Eckdaten ein und sieh in Echtzeit einen realistischen Richtwert, ganz unverbindlich, bevor wir gemeinsam ins Detail gehen.</p>
			</div>

			<?php if ( $bc ) : ?>
			<div class="bc-controls">
				<div class="bc-field">
					<span class="bc-flabel">Teilnehmende</span>
					<div class="bc-stepper">
						<button type="button" class="bc-step-btn" data-bc-step="-2" aria-label="Weniger">
							<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14"/></svg>
						</button>
						<span class="bc-step-val" id="bc-participants" aria-live="polite" aria-label="Teilnehmerzahl"><?php echo esc_html( (string) $start_parts ); ?></span>
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
			</div>

			<div class="bc-services">
				<span class="bc-services-l">Gewünschte Services</span>
				<?php foreach ( $bc['services'] as $s ) :
					$show   = in_array( $s['id'], $start_vis, true );
					$locked = in_array( $s['id'], $start_req, true );
					$on     = $locked || in_array( $s['id'], $start_don, true );
					$cls    = 'bc-chip' . ( $on ? ' on' : '' ) . ( $locked ? ' is-locked' : '' );
				?>
					<button type="button" class="<?php echo esc_attr( $cls ); ?>" data-id="<?php echo esc_attr( $s['id'] ); ?>" data-cat="<?php echo esc_attr( $s['cat'] ); ?>"<?php echo $show ? '' : ' style="display:none;"'; ?>>
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
					<div class="bc-total-h">Gesamtbudget · Richtwert <span style="font-weight:400;">(netto, zzgl. 19&nbsp;% MwSt.)</span></div>
					<div class="bc-total-num" id="bc-total">€0</div>
					<div class="bc-total-meta" id="bc-total-meta"></div>
					<button type="button" class="fg-btn-brand fg-btn-lg bc-total-cta" id="bc-request">
						Unverbindliches Angebot anfragen <span class="fg-arrow"><?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					</button>
					<p class="bc-total-note">Unverbindlicher Schätzwert. Das finale Angebot stellen wir nach kurzer Rücksprache zusammen, transparent, mit allen Posten.</p>
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
			<p class="mk-sub">In jedem Team spielt jemand zum ersten Mal, und jemand seit Jahren. Wir stellen jedes Event so zusammen, dass alle Spaß haben, egal auf welchem Level.</p>
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
		<div class="ind-night-photo" style="background-image:url('<?php echo $img( 'pool/nachtevent-flutlicht-gruen.jpg' ); ?>')"></div>
		<div class="ind-night-scrim"></div>
		<div class="ind-night-glow"></div>
		<div class="ind-night-inner">
			<div class="ind-night-badge">
				<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
				Spezial · nur bei Firmengolf
			</div>
			<h2 class="ind-night-h">Das <span class="mk-italic">Nacht-Event</span>.<br>Wir machen die Nacht zum Tag.</h2>
			<p class="ind-night-sub">
				Wir leuchten einen ganzen Golfplatz aus und verwandeln ihn in eine Bühne, Flutlicht-Parcours,
				Live-DJ, Food &amp; Drinks unter freiem Himmel. Ein Firmenevent der etwas anderen Art,
				das euer Team garantiert nicht vergisst.
			</p>
			<div class="ind-night-points">
				<div class="ind-night-point"><span class="ind-night-n">01</span>Ausgeleuchteter Flutlicht-Parcours</div>
				<div class="ind-night-point"><span class="ind-night-n">02</span>Live-DJ, Licht &amp; Sound</div>
				<div class="ind-night-point"><span class="ind-night-n">03</span>Food, Drinks &amp; Bar bis tief in die Nacht</div>
			</div>
			<?php /* data-rw-intro: erst die Begrüßung „Toll, ihr plant ein Nacht-Event", dann der Anlass-Schritt. */ ?>
			<button type="button" class="fg-btn-ink lg ind-night-cta" data-rw-open="full" data-rw-intro data-rw-preset="<?php echo esc_attr( $nacht_preset ); ?>">
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
					Geführte Anfrage in fünf kurzen Schritten, ca. zwei Minuten, unverbindlich.
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
			<?php get_template_part( 'template-parts/fge-faq', null, [ 'items' => $faqs ] ); ?>
		</div>
	</section>

	<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>

<?php /* FAQ-Toggle kommt aus der globalen Komponente (template-parts/fge-faq.php). */ ?>

<?php get_footer(); ?>
