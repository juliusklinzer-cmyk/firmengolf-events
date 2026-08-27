<?php
/**
 * Globale FAQ-Komponente (Design-Linie, Julius 2026-08-28): EINE Optik für alle
 * FAQ-Sektionen der Seite, exakt das Karten-Muster der Eventliste/Landingpages
 * (weiße Karten, Chevron dreht bei offen, animierte Antwort).
 *
 * Args:
 *  - items:    array von [ 'q' => string, 'a' => string ] ODER [ 'q' => ..., 'a_html' => bereits escaptes HTML ]
 *  - ul_class: optionale Zusatzklassen für die <ul> (z. B. eigenes Spacing)
 *
 * Das Toggle-JS wird pro Seite nur EINMAL ausgegeben und bindet delegiert,
 * damit mehrere FAQ-Blöcke auf einer Seite ohne Doppel-Handler funktionieren.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$faq_items = $args['items'] ?? [];
$ul_class  = trim( 'faq-list faq-anim cty-faq-cards ' . (string) ( $args['ul_class'] ?? '' ) );
if ( ! $faq_items ) {
	return;
}
?>
<ul class="<?php echo esc_attr( $ul_class ); ?>">
	<?php foreach ( $faq_items as $faq ) : ?>
		<li class="faq-item">
			<button class="faq-q" type="button" aria-expanded="false">
				<span><?php echo esc_html( (string) ( $faq['q'] ?? '' ) ); ?></span>
				<span class="faq-toggle cty-faq-chev" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
				</span>
			</button>
			<div class="faq-a">
				<div class="faq-a-in"><?php
					if ( isset( $faq['a_html'] ) ) {
						echo wp_kses_post( (string) $faq['a_html'] );
					} else {
						echo esc_html( (string) ( $faq['a'] ?? '' ) );
					}
				?></div>
			</div>
		</li>
	<?php endforeach; ?>
</ul>
<?php if ( empty( $GLOBALS['fge_faq_js_done'] ) ) : $GLOBALS['fge_faq_js_done'] = true; ?>
<script>
document.addEventListener('click', function (e) {
	var btn = e.target.closest ? e.target.closest('.fge-page .faq-list .faq-q') : null;
	if (!btn) { return; }
	var item = btn.closest('.faq-item');
	var open = item.classList.toggle('open');
	btn.setAttribute('aria-expanded', open ? 'true' : 'false');
});
</script>
<?php endif; ?>
