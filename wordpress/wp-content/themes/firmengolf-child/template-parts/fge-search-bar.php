<?php
/**
 * Such-/Filterleiste (Pillen-Bar wie auf Startseite und Eventliste), wiederverwendbar.
 * Args:
 *  - action   : Ziel-URL des GET-Formulars (Pflicht)
 *  - prefix   : ID-Präfix, damit mehrere Bars pro Seite möglich sind (Standard 'sb')
 *  - format   : fester Formatwert (hidden) ODER null für das Format-Dropdown
 *  - formats  : [slug => Label] fürs Dropdown (nur ohne festes Format)
 *  - lat/lng/radius/loc/pax : Startwerte (z. B. aus der URL)
 *  - submit   : Button-Text (Standard 'Suchen')
 * Klassen sind die zentralen fg-search-*-Komponenten (DESIGN.md), kein eigener Namensraum.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$sb_action  = (string) ( $args['action'] ?? home_url( '/firmenevents/' ) );
$sb_p       = preg_replace( '/[^a-z0-9-]/', '', (string) ( $args['prefix'] ?? 'sb' ) ) ?: 'sb';
$sb_format  = $args['format'] ?? null;
$sb_formats = (array) ( $args['formats'] ?? [] );
$sb_lat     = (string) ( $args['lat'] ?? '' );
$sb_lng     = (string) ( $args['lng'] ?? '' );
$sb_radius  = (int) ( $args['radius'] ?? 50 ) ?: 50;
$sb_loc     = (string) ( $args['loc'] ?? '' );
$sb_pax     = (int) ( $args['pax'] ?? 0 );
$sb_submit  = (string) ( $args['submit'] ?? 'Suchen' );
$sb_id      = static fn( string $s ): string => $sb_p . '-' . $s;
?>
<form method="get" action="<?php echo esc_url( $sb_action ); ?>" class="fg-search-bar" id="<?php echo esc_attr( $sb_id( 'form' ) ); ?>" role="search" aria-label="Events filtern">
	<div class="fg-search-cell fg-loc-cell" id="<?php echo esc_attr( $sb_id( 'loc-cell' ) ); ?>" tabindex="0" role="button" aria-haspopup="dialog" aria-expanded="false" aria-label="Ort oder PLZ wählen">
		<div class="fg-cell-label">Wo?</div>
		<div class="fg-cell-value" id="<?php echo esc_attr( $sb_id( 'loc-display' ) ); ?>"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
			<input type="text" class="fg-cell-input" id="<?php echo esc_attr( $sb_id( 'loc-input' ) ); ?>" placeholder="Ort oder PLZ" autocomplete="off" value="<?php echo esc_attr( 'Mein Standort' === $sb_loc ? '' : $sb_loc ); ?>">
		</div>
		<input type="hidden" name="lat"    id="<?php echo esc_attr( $sb_id( 'lat' ) ); ?>"     value="<?php echo esc_attr( $sb_lat ); ?>">
		<input type="hidden" name="lng"    id="<?php echo esc_attr( $sb_id( 'lng' ) ); ?>"     value="<?php echo esc_attr( $sb_lng ); ?>">
		<input type="hidden" name="radius" id="<?php echo esc_attr( $sb_id( 'radius' ) ); ?>"  value="<?php echo esc_attr( (string) $sb_radius ); ?>">
		<input type="hidden" name="loc"    id="<?php echo esc_attr( $sb_id( 'loc-val' ) ); ?>" value="<?php echo esc_attr( $sb_loc ); ?>">
		<div class="fg-search-panel fg-loc-panel" id="<?php echo esc_attr( $sb_id( 'loc-panel' ) ); ?>" role="dialog" aria-label="Ort und Umkreis">
			<button type="button" class="fg-loc-gps<?php echo 'Mein Standort' === $sb_loc ? ' on' : ''; ?>" id="<?php echo esc_attr( $sb_id( 'loc-gps' ) ); ?>">
				<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><circle cx="12" cy="12" r="8"/></svg>
				Meinen Standort
			</button>
			<div class="fg-loc-suggest" id="<?php echo esc_attr( $sb_id( 'loc-suggest' ) ); ?>" role="listbox"></div>
			<div class="fg-loc-radius">
				<div class="fg-loc-radius-label">Umkreis</div>
				<div class="fg-loc-radius-btns">
					<?php foreach ( [ 25, 50, 100, 200 ] as $r ) : ?>
						<button type="button" class="fg-loc-rb<?php echo $sb_radius === $r ? ' active' : ''; ?>" data-r="<?php echo (int) $r; ?>"><?php echo (int) $r; ?> km</button>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</div>

	<div class="fg-cell-divider" aria-hidden="true"></div>

	<?php if ( null === $sb_format ) : ?>
	<div class="fg-search-cell fg-format-cell" id="<?php echo esc_attr( $sb_id( 'format-cell' ) ); ?>" tabindex="0" role="button" aria-haspopup="listbox" aria-expanded="false" aria-label="Format wählen">
		<div class="fg-cell-label">Veranstaltungstyp</div>
		<div class="fg-cell-value"><span id="<?php echo esc_attr( $sb_id( 'format-text' ) ); ?>">Alle</span></div>
		<input type="hidden" name="format" id="<?php echo esc_attr( $sb_id( 'format-val' ) ); ?>" value="all">
		<div class="fg-search-panel" id="<?php echo esc_attr( $sb_id( 'format-panel' ) ); ?>" role="listbox">
			<?php foreach ( $sb_formats as $fslug => $flabel ) : ?>
				<button type="button" class="fg-search-panel-opt<?php echo 'all' === $fslug ? ' is-selected' : ''; ?>" data-value="<?php echo esc_attr( $fslug ); ?>" data-label="<?php echo esc_attr( $flabel ); ?>" role="option"><?php echo esc_html( $flabel ); ?></button>
			<?php endforeach; ?>
		</div>
	</div>
	<div class="fg-cell-divider" aria-hidden="true"></div>
	<?php else : ?>
	<input type="hidden" name="format" value="<?php echo esc_attr( (string) $sb_format ); ?>">
	<?php endif; ?>

	<div class="fg-search-cell fg-pax-cell">
		<div class="fg-cell-label">Personen</div>
		<div class="fg-pax-ctrl">
			<button type="button" class="fg-pax-btn" data-fn="dec" aria-label="Weniger Personen"<?php echo $sb_pax <= 0 ? ' disabled' : ''; ?>>−</button>
			<span class="fg-pax-num" id="<?php echo esc_attr( $sb_id( 'pax-display' ) ); ?>"><?php echo $sb_pax > 0 ? esc_html( $sb_pax . ' Pers.' ) : 'Alle'; ?></span>
			<button type="button" class="fg-pax-btn" data-fn="inc" aria-label="Mehr Personen">+</button>
		</div>
		<input type="hidden" name="pax" id="<?php echo esc_attr( $sb_id( 'pax-val' ) ); ?>" value="<?php echo esc_attr( (string) $sb_pax ); ?>">
	</div>

	<button type="submit" class="fg-search-btn" aria-label="Events suchen">
		<span><?php echo esc_html( $sb_submit ); ?></span>
		<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
	</button>
</form>
<script>
(function () {
	'use strict';
	var P = <?php echo wp_json_encode( $sb_p ); ?>;
	var $ = function (s) { return document.getElementById(P + '-' + s); };
	var ajax = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;

	function initDropdown(cell, panel, input, display) {
		if (!cell || !panel) return;
		function open()  { panel.classList.add('is-open');    cell.setAttribute('aria-expanded', 'true'); }
		function close() { panel.classList.remove('is-open'); cell.setAttribute('aria-expanded', 'false'); }
		cell.addEventListener('click', function (e) { if (panel.contains(e.target)) return; panel.classList.contains('is-open') ? close() : open(); });
		cell.addEventListener('keydown', function (e) {
			if ((e.key === 'Enter' || e.key === ' ') && e.target === cell) { e.preventDefault(); panel.classList.contains('is-open') ? close() : open(); }
			if (e.key === 'Escape') { close(); cell.focus(); }
		});
		panel.querySelectorAll('.fg-search-panel-opt').forEach(function (opt) {
			opt.addEventListener('click', function (e) {
				e.stopPropagation();
				if (input) input.value = opt.dataset.value;
				if (display) display.textContent = opt.dataset.label;
				panel.querySelectorAll('.fg-search-panel-opt').forEach(function (o) { o.classList.toggle('is-selected', o.dataset.value === opt.dataset.value); });
				close();
			});
		});
		document.addEventListener('click', function (e) { if (!cell.contains(e.target)) close(); });
	}
	initDropdown($('format-cell'), $('format-panel'), $('format-val'), $('format-text'));

	(function initLocationPicker() {
		var cell = $('loc-cell'), panel = $('loc-panel');
		if (!cell || !panel) return;
		var input = $('loc-input'), suggest = $('loc-suggest'), gps = $('loc-gps');
		var latEl = $('lat'), lngEl = $('lng'), radEl = $('radius'), locEl = $('loc-val');
		function open() { panel.classList.add('is-open'); cell.setAttribute('aria-expanded', 'true'); setTimeout(function () { input && input.focus(); }, 30); }
		function close() { panel.classList.remove('is-open'); cell.setAttribute('aria-expanded', 'false'); }
		if (input) input.addEventListener('focus', open);
		cell.addEventListener('click', function (e) {
			if (panel.contains(e.target)) return;
			if (input && e.target === input) { open(); return; }
			panel.classList.contains('is-open') ? close() : open();
		});
		document.addEventListener('click', function (e) { if (!cell.contains(e.target)) close(); });
		cell.addEventListener('keydown', function (e) {
			if ((e.key === 'Enter' || e.key === ' ') && e.target === cell) { e.preventDefault(); panel.classList.contains('is-open') ? close() : open(); }
			if (e.key === 'Escape') { close(); cell.focus(); }
		});
		function setLocation(lat, lng, label) {
			latEl.value = lat; lngEl.value = lng; locEl.value = label;
			var mine = label === 'Mein Standort';
			if (gps) gps.classList.toggle('on', mine);
			if (input) input.value = mine ? '' : label;
			close();
		}
		var t = null;
		if (input) input.addEventListener('input', function () {
			if (gps && gps.classList.contains('on')) { gps.classList.remove('on'); latEl.value = ''; lngEl.value = ''; locEl.value = ''; }
			var q = input.value.trim(); clearTimeout(t);
			if (q.length < 2) { suggest.innerHTML = ''; return; }
			t = setTimeout(function () {
				fetch(ajax + '?action=fge_geo_suggest&q=' + encodeURIComponent(q))
					.then(function (r) { return r.json(); })
					.then(function (res) {
						suggest.innerHTML = '';
						if (!res || !res.success) return;
						res.data.forEach(function (s) {
							var b = document.createElement('button');
							b.type = 'button'; b.className = 'fg-loc-opt'; b.setAttribute('role', 'option'); b.textContent = s.label;
							b.addEventListener('click', function () { suggest.innerHTML = ''; setLocation(s.lat, s.lng, s.label); });
							suggest.appendChild(b);
						});
					}).catch(function () { suggest.innerHTML = ''; });
			}, 220);
		});
		if (gps) gps.addEventListener('click', function () {
			if (gps.classList.contains('on')) { gps.classList.remove('on'); latEl.value = ''; lngEl.value = ''; locEl.value = ''; return; }
			if (!navigator.geolocation) { return; }
			gps.disabled = true; gps.classList.add('is-loading');
			navigator.geolocation.getCurrentPosition(function (pos) {
				gps.disabled = false; gps.classList.remove('is-loading');
				setLocation(pos.coords.latitude.toFixed(5), pos.coords.longitude.toFixed(5), 'Mein Standort');
			}, function () {
				gps.disabled = false; gps.classList.remove('is-loading');
				gps.classList.add('is-err'); setTimeout(function () { gps.classList.remove('is-err'); }, 2500);
			}, { enableHighAccuracy: false, timeout: 20000, maximumAge: 300000 });
		});
		panel.querySelectorAll('.fg-loc-rb').forEach(function (btn) {
			btn.addEventListener('click', function () {
				panel.querySelectorAll('.fg-loc-rb').forEach(function (b) { b.classList.remove('active'); });
				btn.classList.add('active'); radEl.value = btn.getAttribute('data-r');
			});
		});
	})();

	(function initPax() {
		var display = $('pax-display'), val = $('pax-val');
		if (!display || !val) return;
		var cell = display.closest('.fg-pax-cell');
		var dec = cell.querySelector('[data-fn="dec"]'), inc = cell.querySelector('[data-fn="inc"]');
		function update(next) {
			next = Math.max(0, next);
			val.value = next;
			display.textContent = next > 0 ? next + ' Pers.' : 'Alle';
			if (dec) dec.disabled = next <= 0;
		}
		if (inc) inc.addEventListener('click', function () { update((parseInt(val.value, 10) || 0) + 5); });
		if (dec) dec.addEventListener('click', function () { update(Math.max(0, (parseInt(val.value, 10) || 0) - 5)); });
	})();
})();
</script>
