<?php
/**
 * Weihnachtsfeier-Kurzanfrage (Julius, 07.09.): Sektion mit Zwei-Schritt-Formular.
 * Läuft über fge_general_request (Nonce, Bot-Fallen, Anfrage-Post, Mails, Meta-Lead).
 * Eingebunden auf der Weihnachtsfeier-Landingpage (template-format.php).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$xr_p       = preg_replace( '/[^a-z0-9-]/', '', (string) ( $args['prefix'] ?? 'xmas' ) ) ?: 'xmas';
$xr_compact = ! empty( $args['compact'] ); // nur das Formular (z. B. im Dialog), ohne Textspalte
$xr_id      = static fn( string $s ): string => $xr_p . '-' . $s;
// Varianten (07.09.): Weihnachtsfeier (Standard) und Sommerfest teilen Aufbau, Endpunkt und JS.
$xr_variants = [
	'weihnachtsfeier' => [
		'occasion'  => 'Weihnachtsfeier',
		'source'    => 'weihnachtsfeier_section',
		'h2'        => 'Plant jetzt eure <em class="mk-italic">Weihnachtsfeier</em> mit uns.',
		'sub'       => 'Indoor an den Simulatoren oder im Clubhaus der Golfanlage: Golf-Challenge, Menü und Getränke aus einer Hand. Ihr nennt uns die Eckdaten, wir schicken ein konkretes Angebot aus eurer Region.',
		'points'    => [ 'Warm und wetterfest, auch ohne Golferfahrung', 'Weihnachtsmenü und Getränkepauschale gleich mitgeplant', 'Konkretes Angebot innerhalb eines Werktags' ],
		'step1'     => 'Eure Feier in Eckdaten',
		'venue_l'   => 'Wo wollt ihr feiern?',
		'venues'    => [ 'Indoor-Simulator' => 'Indoor-Simulator', 'Golfanlage mit Clubhaus' => 'Golfanlage mit Clubhaus', 'Offen, bitte beraten' => 'Offen, bitte beraten' ],
		'cater_l'   => 'Catering',
		'caterings' => [ 'Weihnachtsmenü' => 'Weihnachtsmenü, mehrgängig', 'Buffet' => 'Buffet', 'Fingerfood und Snacks' => 'Fingerfood und Snacks', 'ohne Catering' => 'Ohne Catering' ],
		'drinks_l'  => 'Getränke',
		'drinks'    => [ 'Getränkepauschale' => 'Getränkepauschale', 'nach Verbrauch' => 'Nach Verbrauch', 'später entscheiden' => 'Später entscheiden' ],
		'date_l'    => 'Wunschdatum',
		'note'      => 'Unverbindlich und kostenlos. Im nächsten Schritt nur noch Firma und Kontakt.',
		'done'      => 'Wir melden uns innerhalb eines Werktags mit einem konkreten Angebot für eure Weihnachtsfeier in',
		'lead'      => 'Weihnachtsfeier Kurz-Anfrage',
	],
	'sommerfest' => [
		'occasion'  => 'Sommerfest',
		'source'    => 'sommerfest_section',
		'h2'        => 'Nichts Passendes dabei? Wir planen euer <em class="mk-italic">Sommerfest</em> mit euch.',
		'sub'       => 'Turnier für Könner, Kurzplatz und Schnupperrunde für Einsteiger, danach Barbecue auf der Clubterrasse. Ihr nennt uns die Eckdaten, wir schicken ein konkretes Angebot aus eurer Region, gern schon für 2027.',
		'points'    => [ 'Draußen auf dem Platz, auch ohne Golferfahrung', 'Barbecue, Buffet oder Menü gleich mitgeplant', 'Konkretes Angebot innerhalb eines Werktags' ],
		'step1'     => 'Euer Sommerfest in Eckdaten',
		'venue_l'   => 'Was soll der Tag bringen?',
		'venues'    => [ 'Turnier und Kurzplatz gemischt' => 'Turnier für Könner, Kurzplatz für Einsteiger', 'Schnupperrunde für alle' => 'Schnupperrunde für alle, ohne Vorkenntnisse', 'Firmenturnier' => 'Firmenturnier mit Golferfahrung', 'Offen, bitte beraten' => 'Offen, bitte beraten' ],
		'cater_l'   => 'Essen danach',
		'caterings' => [ 'Barbecue' => 'Barbecue auf der Clubterrasse', 'Buffet' => 'Buffet', 'Menü' => 'Menü, mehrgängig', 'ohne Catering' => 'Ohne Catering' ],
		'drinks_l'  => 'Getränke',
		'drinks'    => [ 'Getränkepauschale' => 'Getränkepauschale', 'nach Verbrauch' => 'Nach Verbrauch', 'später entscheiden' => 'Später entscheiden' ],
		'date_l'    => 'Wunschdatum (2027 möglich)',
		'note'      => 'Unverbindlich und kostenlos. Im nächsten Schritt nur noch Firma und Kontakt.',
		'done'      => 'Wir melden uns innerhalb eines Werktags mit einem konkreten Angebot für euer Sommerfest in',
		'lead'      => 'Sommerfest Kurz-Anfrage',
	],
];
$xr_v   = isset( $xr_variants[ (string) ( $args['variant'] ?? '' ) ] ) ? (string) $args['variant'] : 'weihnachtsfeier';
$xr_cfg = $xr_variants[ $xr_v ];
?>
	<?php /* ── Weihnachtsfeier: eigene Sektion mit Kurz-Anfrage (Julius, 07.09.) ──
		Schritt 1 Eckdaten (Ort, Personen, Catering, Getränke, Wunschdatum, Region),
		Schritt 2 Firma + Kontakt, dann Bestätigung. Geht über den bestehenden
		fge_general_request-Endpunkt (Spam-Fallen, Anfrage-Post, Mails). */ ?>
	<?php
	$xmas_min_date = ( new DateTime( '+7 days', wp_timezone() ) )->format( 'Y-m-d' );
	$xmas_check    = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';
	?>
	<section class="xmas<?php echo ! empty( $args['band'] ) ? ' xmas--band' : ''; ?><?php echo $xr_compact ? ' xmas--compact' : ''; ?>" id="<?php echo esc_attr( $args['id'] ?? 'weihnachtsfeier' ); ?>" aria-label="<?php echo esc_attr( $xr_cfg['occasion'] . ' planen' ); ?>">
		<div class="xmas-inner">
			<?php if ( ! $xr_compact ) : ?>
			<div class="xmas-text">
				<h2 class="mk-h2"><?php echo ! empty( $args['h2'] ) ? wp_kses_post( $args['h2'] ) : wp_kses_post( $xr_cfg['h2'] ); ?></h2>
				<p class="mk-sub"><?php echo esc_html( $xr_cfg['sub'] ); ?></p>
				<div class="xmas-points">
					<?php foreach ( $xr_cfg['points'] as $xr_pt ) : ?>
					<div><?php echo $xmas_check; // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php echo esc_html( $xr_pt ); ?></span></div>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endif; ?>

			<form class="xmas-form" id="<?php echo esc_attr( $xr_id( 'form' ) ); ?>" novalidate autocomplete="on">
				<div class="fg-step-rail" aria-hidden="true">
					<div class="fg-step done" data-xmas-rail="0"></div>
					<div class="fg-step" data-xmas-rail="1"></div>
				</div>

				<div class="xmas-step" data-xmas-step="0">
					<div class="xmas-step-h"><?php echo esc_html( $xr_cfg['step1'] ); ?></div>
					<div class="xmas-grid">
						<div class="fg-field">
							<label class="fg-field-label" for="<?php echo esc_attr( $xr_id( 'venue' ) ); ?>"><?php echo esc_html( $xr_cfg['venue_l'] ); ?></label>
							<select class="fg-input" id="<?php echo esc_attr( $xr_id( 'venue' ) ); ?>" name="venue">
								<?php foreach ( $xr_cfg['venues'] as $xr_val => $xr_lab ) : ?><option value="<?php echo esc_attr( $xr_val ); ?>"><?php echo esc_html( $xr_lab ); ?></option><?php endforeach; ?>
							</select>
						</div>
						<div class="fg-field">
							<label class="fg-field-label" for="<?php echo esc_attr( $xr_id( 'size' ) ); ?>">Wie viele Personen?</label>
							<select class="fg-input" id="<?php echo esc_attr( $xr_id( 'size' ) ); ?>" name="size">
								<option value="8 bis 15 Personen">8 bis 15</option>
								<option value="16 bis 30 Personen" selected>16 bis 30</option>
								<option value="31 bis 50 Personen">31 bis 50</option>
								<option value="51 bis 80 Personen">51 bis 80</option>
								<option value="über 80 Personen">über 80</option>
							</select>
						</div>
						<div class="fg-field">
							<label class="fg-field-label" for="<?php echo esc_attr( $xr_id( 'catering' ) ); ?>"><?php echo esc_html( $xr_cfg['cater_l'] ); ?></label>
							<select class="fg-input" id="<?php echo esc_attr( $xr_id( 'catering' ) ); ?>" name="catering">
								<?php foreach ( $xr_cfg['caterings'] as $xr_val => $xr_lab ) : ?><option value="<?php echo esc_attr( $xr_val ); ?>"><?php echo esc_html( $xr_lab ); ?></option><?php endforeach; ?>
							</select>
						</div>
						<div class="fg-field">
							<label class="fg-field-label" for="<?php echo esc_attr( $xr_id( 'drinks' ) ); ?>"><?php echo esc_html( $xr_cfg['drinks_l'] ); ?></label>
							<select class="fg-input" id="<?php echo esc_attr( $xr_id( 'drinks' ) ); ?>" name="drinks">
								<?php foreach ( $xr_cfg['drinks'] as $xr_val => $xr_lab ) : ?><option value="<?php echo esc_attr( $xr_val ); ?>"><?php echo esc_html( $xr_lab ); ?></option><?php endforeach; ?>
							</select>
						</div>
						<div class="fg-field">
							<label class="fg-field-label" for="<?php echo esc_attr( $xr_id( 'date' ) ); ?>"><?php echo esc_html( $xr_cfg['date_l'] ); ?></label>
							<input class="fg-input fg-date" type="date" id="<?php echo esc_attr( $xr_id( 'date' ) ); ?>" name="date1" min="<?php echo esc_attr( $xmas_min_date ); ?>" required>
						</div>
						<div class="fg-field">
							<label class="fg-field-label" for="<?php echo esc_attr( $xr_id( 'region' ) ); ?>">Stadt oder Region</label>
							<input class="fg-input" type="text" id="<?php echo esc_attr( $xr_id( 'region' ) ); ?>" name="region" placeholder="z. B. München" autocomplete="address-level2" required>
						</div>
					</div>
					<div class="xmas-err" data-xmas-err hidden role="alert"></div>
					<button type="button" class="fg-btn-brand block xmas-cta" data-xmas-next>Jetzt anfragen</button>
					<p class="xmas-note"><?php echo esc_html( $xr_cfg['note'] ); ?></p>
				</div>

				<div class="xmas-step" data-xmas-step="1" hidden>
					<div class="xmas-step-h">Wohin dürfen wir das Angebot schicken?</div>
					<div class="xmas-grid">
						<div class="fg-field fg-field-full">
							<label class="fg-field-label" for="<?php echo esc_attr( $xr_id( 'company' ) ); ?>">Firma</label>
							<input class="fg-input" type="text" id="<?php echo esc_attr( $xr_id( 'company' ) ); ?>" name="company" autocomplete="organization" required>
						</div>
						<div class="fg-field">
							<label class="fg-field-label" for="<?php echo esc_attr( $xr_id( 'name' ) ); ?>">Vor- und Nachname</label>
							<input class="fg-input" type="text" id="<?php echo esc_attr( $xr_id( 'name' ) ); ?>" name="first_name" autocomplete="name" required>
						</div>
						<div class="fg-field">
							<label class="fg-field-label" for="<?php echo esc_attr( $xr_id( 'email' ) ); ?>">E-Mail</label>
							<input class="fg-input" type="email" id="<?php echo esc_attr( $xr_id( 'email' ) ); ?>" name="email" autocomplete="email" required>
						</div>
						<div class="fg-field fg-field-full">
							<label class="fg-field-label" for="<?php echo esc_attr( $xr_id( 'phone' ) ); ?>">Telefon <span class="fg-opt">optional</span></label>
							<input class="fg-input" type="tel" id="<?php echo esc_attr( $xr_id( 'phone' ) ); ?>" name="phone" autocomplete="tel">
						</div>
					</div>
					<label class="xmas-consent">
						<input type="checkbox" id="<?php echo esc_attr( $xr_id( 'consent' ) ); ?>" name="consent" value="1">
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
					<p><?php echo esc_html( $xr_cfg['done'] ); ?> <span data-xmas-region>eurer Region</span>. Vorgangsnummer <strong data-xmas-ref></strong>.</p>
				</div>

				<input type="hidden" name="occasion" value="<?php echo esc_attr( $xr_cfg['occasion'] ); ?>">
				<input type="hidden" name="source" value="<?php echo esc_attr( $xr_cfg['source'] ); ?>">
				<input type="hidden" name="place" value="<?php echo esc_attr( (string) ( $args['place'] ?? '' ) ); ?>">
								<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'fge_general_request' ) ); ?>">
				<input type="text" name="fge_hp" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;">
				<?php echo fge_form_trap_fields(); // phpcs:ignore WordPress.Security.EscapeOutput -- Bot-Fallen ?>
			</form>
		</div>
	</section>
	<script>
	(function () {
		var form = document.getElementById(<?php echo wp_json_encode( $xr_id( 'form' ) ); ?>);
		if (!form || form.dataset.xmasInit) return;
		form.dataset.xmasInit = '1';
		var f = function (name) { return form.elements[name] || null; };
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
			if (n === 1 && f('company')) { f('company').focus({ preventScroll: true }); }
			if (!reduce && !form.closest('.fg-modal')) { var top = form.getBoundingClientRect().top; if (top < 0) form.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
		}
		if (steps[0]) { steps[0].classList.add('is-in'); }
		function err(step, msg) { var box = steps[step].querySelector('[data-xmas-err]'); box.textContent = msg || ''; box.hidden = !msg; }
		function mark(el, bad) { if (el) el.classList.toggle('fg-input-err', !!bad); }
		form.querySelector('[data-xmas-next]').addEventListener('click', function () {
			var date = f('date1'), region = f('region');
			var okDate = !!date.value && (!date.min || date.value >= date.min), okRegion = region.value.trim().length > 1;
			mark(date, !okDate); mark(region, !okRegion);
			if (!okDate || !okRegion) { err(0, !okDate ? 'Bitte ein Wunschdatum mit mindestens 7 Tagen Vorlauf wählen.' : 'Bitte Stadt oder Region angeben.'); return; }
			err(0, ''); showStep(1);
		});
		form.querySelector('[data-xmas-back]').addEventListener('click', function () { err(1, ''); showStep(0); });
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var company = f('company'), name = f('first_name'), email = f('email'), consent = f('consent');
			var okC = company.value.trim() !== '', okN = name.value.trim() !== '', okE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim());
			mark(company, !okC); mark(name, !okN); mark(email, !okE);
			if (!okC || !okN || !okE) { err(1, 'Bitte Firma, Name und eine gültige E-Mail angeben.'); return; }
			if (!consent.checked) { err(1, 'Bitte stimme der Datenverarbeitung zu, um die Anfrage zu senden.'); return; }
			err(1, '');
			var btn = form.querySelector('button[type=submit]'); btn.disabled = true; var orig = btn.textContent; btn.textContent = 'Wird gesendet …';
			var v = function (n) { var el = f(n); return el ? el.value : ''; };
			var place = v('place');
			var services = [v('venue'), v('catering') !== 'ohne Catering' ? v('catering') : '', v('drinks') === 'Getränkepauschale' ? 'Getränkepauschale' : ''].filter(Boolean);
			var body = new URLSearchParams({
				action: 'fge_general_request',
				nonce: v('nonce'), fge_ft: v('fge_ft'), fge_js: v('fge_js'), fge_hp: v('fge_hp'),
				source: v('source'),
				occasion: v('occasion'),
				size: v('size'), date1: v('date1'), region: v('region'), city: v('region'),
				place: place,
				when: v('occasion') === 'Sommerfest' ? 'Sommer, Wunschdatum siehe Termin' : 'Dezember, Wunschdatum siehe Termin',
				services: services.join('||'),
				notes: v('occasion') + ': ' + v('venue') + ', ' + v('size') + ', Essen: ' + v('catering') + ', Getränke: ' + v('drinks') + '.' + (place ? ' Wunsch-Location: ' + place + ' (Anfrage an Firmengolf, nicht an die Location).' : ''),
				company: company.value.trim(), first_name: name.value.trim(), last_name: '', email: email.value.trim(), phone: v('phone'), consent: '1'
			});
			fetch(<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, { method: 'POST', body: body, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (!res || !res.success) { throw new Error((res && res.data && res.data.message) || 'Das hat nicht geklappt. Bitte versuch es gleich noch einmal.'); }
					form.querySelector('[data-xmas-name]').textContent = name.value.trim().split(/\s+/)[0] || 'ihr';
					form.querySelector('[data-xmas-region]').textContent = place || v('region');
					form.querySelector('[data-xmas-ref]').textContent = res.data.ref || '';
					rails.forEach(function (r) { r.classList.add('done'); });
					showStep(2);
					if (window.fbq && res.data && res.data.fb_event_id) { try { fbq('track', 'Lead', { content_name: <?php echo wp_json_encode( $xr_cfg['lead'] ); ?> }, { eventID: res.data.fb_event_id }); } catch (x) {} }
				})
				.catch(function (x) { err(1, x.message); btn.disabled = false; btn.textContent = orig; });
		});
	})();
	</script>

