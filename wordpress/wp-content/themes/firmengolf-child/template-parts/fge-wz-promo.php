<?php
/**
 * Wertschätzungs-Teaser (Julius, 2026-08-11): wird auf Format-, Stadt- und
 * Format×Stadt-Landingpages eingebunden. Abgerundeter Container im Stil der
 * App-Promo (fmt-promo), Link auf /wertschaetzung/.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="mk-section fge-wz-teaser" aria-label="Wertschätzungspaket">
	<div class="fmt-promo">
		<div>
			<div class="mk-eyebrow">Mitarbeiter auszeichnen</div>
			<h2 class="mk-h2" style="font-size:30px;">Schenke deinen besten Mitarbeitern eine Aufmerksamkeit, die bleibt.</h2>
			<p class="mk-sub">Das Wertschätzungspaket: vom Golf-Grundlagenkurs mit persönlichem Empfang bis zur DGV-Platzreife als exklusiver Networking-Kurs. Persönlich zugestellt, im Namen eures Unternehmens.</p>
		</div>
		<a class="fg-btn-brand" href="<?php echo esc_url( home_url( '/wertschaetzung/' ) ); ?>">Wertschätzungspaket entdecken →</a>
	</div>
</section>
