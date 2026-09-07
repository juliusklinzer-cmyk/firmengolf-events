<?php
/**
 * Standort-Abfrage (Blur-Dialog wie auf /firmenevents/, Julius 1.9.137): fragt einmal
 * nach dem Standort und leitet mit lat/lng/radius/loc auf $args['target'] weiter.
 * Gleiche Storage-Keys wie die Eventliste (fgeGeoPromptSeen, fgeGeoOff), damit die
 * Frage nur einmal pro Gerät kommt. Nur auf der sauberen URL (ohne Query).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$gp_target = (string) ( $args['target'] ?? home_url( '/firmenevents/' ) );
$gp_title  = (string) ( $args['title'] ?? 'Events in eurer Nähe finden?' );
$gp_text   = (string) ( $args['text'] ?? 'Gebt kurz euren Standort frei, dann zeigen wir nur Events auf Golfplätzen, die ihr gut erreicht.' );
$gp_skip   = (string) ( $args['skip'] ?? 'Alle Events ansehen' );
?>
<div class="ev-geo-scrim" id="fge-geo-prompt" role="dialog" aria-modal="true" aria-labelledby="fge-geo-h" hidden>
	<div class="ev-geo-card">
		<svg class="ev-geo-art" viewBox="0 0 220 128" width="220" height="128" fill="none" aria-hidden="true">
			<circle class="ev-geo-ring ev-geo-ring1" cx="110" cy="68" r="22" stroke="var(--fairway-200)" stroke-width="1.5"/>
			<circle class="ev-geo-ring ev-geo-ring2" cx="110" cy="68" r="42" stroke="var(--ink-200)" stroke-width="1.2" stroke-dasharray="3 5"/>
			<circle class="ev-geo-ring ev-geo-ring3" cx="110" cy="68" r="60" stroke="var(--ink-100)" stroke-width="1" stroke-dasharray="2 6"/>
			<g class="ev-geo-dot ev-geo-dot1">
				<circle cx="64" cy="46" r="4" fill="var(--fairway-600)"/>
				<path d="M64 42v-9m0 0 7 2.6-7 2.6" stroke="var(--fairway-600)" stroke-width="1.6" stroke-linejoin="round" fill="var(--fairway-600)"/>
			</g>
			<g class="ev-geo-dot ev-geo-dot2">
				<circle cx="158" cy="88" r="4" fill="var(--fairway-600)"/>
				<path d="M158 84v-9m0 0 7 2.6-7 2.6" stroke="var(--fairway-600)" stroke-width="1.6" stroke-linejoin="round" fill="var(--fairway-600)"/>
			</g>
			<circle class="ev-geo-dot ev-geo-dot3" cx="146" cy="34" r="3.5" fill="var(--fairway-300)"/>
			<circle class="ev-geo-dot ev-geo-dot4" cx="76" cy="98" r="3.5" fill="var(--fairway-300)"/>
			<g class="ev-geo-pin">
				<path d="M110 88c0-1-14-11.4-14-22a14 14 0 0 1 28 0c0 10.6-14 21-14 22Z" fill="var(--ink-900)"/>
				<circle cx="110" cy="65" r="5.5" fill="#fff"/>
			</g>
		</svg>
		<h2 class="ev-geo-h" id="fge-geo-h"><?php echo esc_html( $gp_title ); ?></h2>
		<p class="ev-geo-p"><?php echo esc_html( $gp_text ); ?></p>
		<button type="button" class="ev-geo-allow" id="fge-geo-allow">Standort verwenden</button>
		<button type="button" class="ev-geo-skip" id="fge-geo-skip"><?php echo esc_html( $gp_skip ); ?></button>
	</div>
</div>
<script>
(function () {
	var prompt = document.getElementById('fge-geo-prompt');
	if (!prompt || !navigator.geolocation) { return; }
	var allow = document.getElementById('fge-geo-allow');
	var skip  = document.getElementById('fge-geo-skip');
	var KEY = 'fgeGeoPromptSeen', OFF = 'fgeGeoOff';
	var cleanUrl = window.location.search === '';
	var storedOff = false, seen = false;
	try { storedOff = !!sessionStorage.getItem(OFF); seen = !!localStorage.getItem(KEY); } catch (err) {}
	function goNear(pos) {
		window.location.href = <?php echo wp_json_encode( $gp_target ); ?>
			+ '?lat=' + pos.coords.latitude.toFixed(5) + '&lng=' + pos.coords.longitude.toFixed(5)
			+ '&radius=50&loc=' + encodeURIComponent('Mein Standort') + '#angebote';
	}
	function hide() { prompt.classList.remove('is-open'); prompt.hidden = true; try { localStorage.setItem(KEY, '1'); } catch (err) {} }
	function show() { prompt.hidden = false; requestAnimationFrame(function () { prompt.classList.add('is-open'); }); allow.focus(); }
	allow.addEventListener('click', function () {
		allow.disabled = true; allow.textContent = 'Standort wird ermittelt …';
		navigator.geolocation.getCurrentPosition(goNear, function (err) {
			allow.disabled = false; allow.textContent = 'Erneut versuchen';
			var p = prompt.querySelector('.ev-geo-p');
			if (!p) { return; }
			p.textContent = (err && err.code === 1)
				? 'Der Standort ist für diese Website blockiert. In Safari: aA links in der Adressleiste antippen, Website-Einstellungen, Standort auf „Fragen" stellen. Danach hier noch einmal tippen.'
				: 'Wir konnten euren Standort gerade nicht ermitteln. Prüft, ob der Browser auf den Standort zugreifen darf, und tippt nochmal.';
		}, { timeout: 20000, maximumAge: 300000 });
	});
	skip.addEventListener('click', hide);
	prompt.addEventListener('click', function (e) { if (e.target === prompt) { hide(); } });
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !prompt.hidden) { hide(); } });
	if (!cleanUrl || storedOff) { return; }
	if (navigator.permissions && navigator.permissions.query) {
		navigator.permissions.query({ name: 'geolocation' }).then(function (st) {
			if (st.state === 'granted') { navigator.geolocation.getCurrentPosition(goNear, function () {}, { timeout: 15000, maximumAge: 300000 }); }
			else if (st.state === 'prompt' && !seen) { setTimeout(show, 500); }
		}).catch(function () { if (!seen) { setTimeout(show, 500); } });
	} else if (!seen) { setTimeout(show, 500); }
})();
</script>
