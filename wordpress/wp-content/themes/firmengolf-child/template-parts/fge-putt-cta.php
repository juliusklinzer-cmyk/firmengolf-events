<?php
/**
 * Putt-CTA: dunkle Abschluss-Sektion mit Putt-Moment (Linie zeichnet sich, Ball
 * rollt ein, Fahne schwingt, Button pulst einmal). Gemeinsamer Baustein der
 * Stadt- und Format×Stadt-Landingpages (2026-08-20).
 *
 * Args:
 *  - 'headline_html' => fertiges, escaptes HTML für die H2 (inkl. <em class="mk-italic">)
 *  - 'sub'           => string Untertitel
 *  - 'anfrage_url'   => string Ziel des Buttons
 *  - 'links_html'    => fertiges, escaptes HTML der kleinen Linkzeile darunter ('' = keine)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$args          = $args ?? [];
$headline_html = (string) ( $args['headline_html'] ?? '' );
$cta_sub       = (string) ( $args['sub'] ?? '' );
$cta_url       = (string) ( $args['anfrage_url'] ?? home_url( '/individuelle-events/?anfrage=quick' ) );
$links_html    = (string) ( $args['links_html'] ?? '' );
?>
<section class="mk-cta cty-cta" aria-label="Anfrage">
	<svg class="cty-cta-topo" viewBox="0 0 1440 480" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false">
		<path d="M-40 120 C 260 40, 620 200, 940 120 S 1420 60, 1520 140" fill="none" stroke="currentColor" stroke-width="1.5"/>
		<path d="M-40 240 C 300 160, 680 320, 1020 230 S 1440 190, 1520 260" fill="none" stroke="currentColor" stroke-width="1.5"/>
		<path d="M-40 370 C 320 290, 700 440, 1060 350 S 1460 320, 1520 390" fill="none" stroke="currentColor" stroke-width="1.5"/>
	</svg>
	<div class="mk-cta-inner">
		<h2 class="mk-cta-h"><?php echo $headline_html; // phpcs:ignore WordPress.Security.EscapeOutput -- vom Aufrufer escaptes HTML ?></h2>
		<?php if ( '' !== $cta_sub ) : ?>
		<p class="mk-cta-sub"><?php echo esc_html( $cta_sub ); ?></p>
		<?php endif; ?>
		<div class="cty-putt" id="fge-putt" aria-hidden="true">
			<svg viewBox="0 0 720 150" preserveAspectRatio="xMidYMid meet" focusable="false">
				<path class="cty-putt-line" d="M 26 104 C 210 128, 420 62, 588 92" fill="none"/>
				<ellipse class="cty-putt-hole" cx="596" cy="94" rx="14" ry="5"/>
				<g class="cty-putt-flag">
					<line x1="596" y1="94" x2="596" y2="22"/>
					<path class="cty-putt-pennant" d="M 596 22 L 596 44 L 632 33 Z"/>
				</g>
				<g class="cty-putt-ball" id="fge-putt-ball">
					<ellipse class="cty-putt-ball-shadow" cx="0" cy="7" rx="8" ry="2.6"/>
					<g class="cty-putt-ball-spin">
						<circle cx="0" cy="0" r="7.5"/>
						<circle class="cty-putt-dimple" cx="-2.5" cy="-2" r="1.1"/>
						<circle class="cty-putt-dimple" cx="1.5" cy="1.5" r="1.1"/>
						<circle class="cty-putt-dimple" cx="2.5" cy="-2.5" r="1.1"/>
					</g>
					<animateMotion id="fge-putt-motion" dur="1.35s" begin="indefinite" fill="freeze"
						calcMode="spline" keyPoints="0;1" keyTimes="0;1" keySplines="0.18 0.7 0.25 1"
						path="M 26 96 C 210 120, 420 54, 588 86"/>
				</g>
			</svg>
		</div>
		<div class="mk-cta-ctas">
			<a class="fg-btn-ink fg-btn-lg cty-cta-btn" id="fge-cta-btn" href="<?php echo esc_url( $cta_url ); ?>">Event anfragen</a>
		</div>
		<?php if ( '' !== $links_html ) : ?>
		<div class="cty-cta-cities"><?php echo $links_html; // phpcs:ignore WordPress.Security.EscapeOutput -- vom Aufrufer escaptes HTML ?></div>
		<?php endif; ?>
	</div>
</section>
<script>
/* Putt-Moment: einmalige Sequenz, sobald die Grafik gut sichtbar ist.
   Linie freilegen → Ball anrollen (SMIL) → einlochen → Fahne schwingt → Button-Puls. */
(function () {
	var putt = document.getElementById('fge-putt');
	if (!putt) { return; }
	var motion = document.getElementById('fge-putt-motion');
	var btn = document.getElementById('fge-cta-btn');
	if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window) || !motion || !motion.beginElement) {
		putt.classList.add('is-static');
		return;
	}
	var played = false;
	var io = new IntersectionObserver(function (entries) {
		entries.forEach(function (e) {
			if (!e.isIntersecting || played) { return; }
			played = true;
			io.disconnect();
			putt.classList.add('is-draw');
			setTimeout(function () {
				putt.classList.add('is-rolling');
				try { motion.beginElement(); } catch (err) { putt.classList.add('is-static'); }
			}, 550);
			setTimeout(function () { putt.classList.add('is-holed'); }, 1850);
			setTimeout(function () { if (btn) { btn.classList.add('cty-pulse'); } }, 2150);
		});
	}, { threshold: 0.6 });
	io.observe(putt);
})();
</script>
