<?php
/**
 * Template: Partner-FAQ (für Golfplätze)
 * Ported from React PartnerFaq.jsx — generic faq-* classes + real copy.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$url_onboarding = home_url( '/partner-onboarding/' );

$faq_groups = [
	'Einstieg & Aufnahme' => [
		[ 'Wie wird mein Golfplatz Partner bei Firmengolf?', 'Über unser Onboarding hinterlegst du Platz, Kapazitäten, Leistungen und Preise. Wir prüfen das Profil und schalten dich frei.' ],
		[ 'Was kostet die Partnerschaft?', 'Kein Setup-Preis. Wir arbeiten provisionsbasiert — du zahlst nur, wenn über uns gebucht wird.' ],
		[ 'Welche Voraussetzungen muss mein Platz erfüllen?', 'Ein bespielbarer Platz oder eine Übungsanlage und ein Ansprechpartner für die Koordination genügen.' ],
		[ 'Wie lange dauert die Freischaltung?', 'In der Regel wenige Werktage nach vollständigem Profil.' ],
	],
	'Anfragen & Buchungen' => [
		[ 'Wie bekomme ich Anfragen?', 'Passende Firmenanfragen erreichen dich direkt im Partnerportal und per E-Mail.' ],
		[ 'Muss ich jede Anfrage annehmen?', 'Nein. Du entscheidest pro Anfrage über Verfügbarkeit und Zusage.' ],
		[ 'Wer betreut die anfragende Firma?', 'Firmengolf übernimmt die Kommunikation mit dem Kunden — du konzentrierst dich auf den Platz.' ],
	],
	'Termine & Koordination' => [
		[ 'Wie funktioniert die Terminfreigabe?', 'Wir stimmen Wunschtermine mit Platz, Pro und Gastro ab; sobald alle reagiert haben, wird gebucht.' ],
		[ 'Können mehrere Personen vom Platz mitentscheiden?', 'Ja — Platz, Golfpro und Gastronomie können getrennt zu-/absagen.' ],
		[ 'Was passiert, wenn jemand nicht rechtzeitig reagiert?', 'Wir erinnern automatisch und übernehmen bei Überfälligkeit die Nachverfolgung.' ],
		[ 'Kann ich einen Termin in meinen Kalender übernehmen?', 'Ja, bestätigte Termine lassen sich exportieren.' ],
	],
	'Preise & Abrechnung' => [
		[ 'Wie lege ich meine Preise fest?', 'Du hinterlegst deine Einkaufspreise; Firmengolf kalkuliert den Verkaufspreis mit transparentem Aufschlag.' ],
		[ 'Wie läuft die Abrechnung?', 'Eine saubere Abrechnung pro Event — Methode legen wir gemeinsam fest.' ],
		[ 'Wann werde ich ausgezahlt?', 'Nach durchgeführtem Event gemäß vereinbartem Zahlungsziel.' ],
	],
	'Portal & Profil' => [
		[ 'Brauche ich spezielle Software?', 'Nein, das Partnerportal läuft im Browser — kein Download nötig.' ],
		[ 'Kann ich mein Profil und meine Fotos selbst pflegen?', 'Ja, Profil, Galerie und Angebote pflegst du jederzeit selbst.' ],
		[ 'Können mehrere Mitarbeitende Zugang haben?', 'Ja, weitere Kontakte/Rollen lassen sich hinterlegen.' ],
	],
	'Vertrag & Konditionen' => [
		[ 'Binde ich mich langfristig?', 'Nein, keine langfristige Bindung — faire, kurze Konditionen.' ],
		[ 'Kann ich meinen Platz vorübergehend pausieren?', 'Ja. Pausierst du, gehen deine Angebote automatisch offline und kommen bei Reaktivierung zurück.' ],
		[ 'Muss ich exklusiv mit Firmengolf arbeiten?', 'Nein, keine Exklusivität.' ],
	],
];
?>
<div class="fge-page">

<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => '' ] ); ?>

<section class="mk-section" aria-label="Partner-FAQ" style="padding-top:64px;">
	<div class="mk-section-head">
		<div class="mk-eyebrow">Für Golfplätze · Häufige Fragen</div>
		<h1 class="mk-h2" style="font-size:var(--fs-display-md);max-width:780px;">Alles, was ihr über eine Partnerschaft <em class="mk-italic">wissen</em> müsst.</h1>
		<p class="mk-sub" style="max-width:680px;">
			Von der Aufnahme über die Termin-Abstimmung bis zur Abrechnung — hier beantworten wir die Fragen,
			die Golfplätze uns am häufigsten stellen.
		</p>
	</div>

	<?php foreach ( $faq_groups as $cat => $items ) : ?>
		<div style="margin-top:40px;">
			<div class="mk-eyebrow" style="color:var(--fairway-700)"><?php echo esc_html( $cat ); ?></div>
			<ul class="faq-list" style="margin-top:12px;">
				<?php foreach ( $items as $i => $faq ) : ?>
					<li class="faq-item">
						<button class="faq-q" type="button" aria-expanded="false">
							<span><?php echo esc_html( $faq[0] ); ?></span>
							<span class="faq-toggle" aria-hidden="true">+</span>
						</button>
						<div class="faq-a"><?php echo esc_html( $faq[1] ); ?></div>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endforeach; ?>
</section>

<section class="mk-cta" aria-label="Partner werden">
	<div class="mk-cta-inner">
		<div class="mk-eyebrow" style="color:rgba(251,250,246,0.65)">Noch eine Frage offen?</div>
		<h2 class="mk-cta-h">Schreibt uns — wir helfen bei der <em class="mk-italic">Aufnahme</em>.</h2>
		<div class="mk-cta-ctas">
			<a class="fg-btn-ink fg-btn-lg" href="<?php echo esc_url( $url_onboarding ); ?>" style="background:var(--paper-100);color:var(--fairway-900)">Platz anbieten</a>
			<a class="mk-cta-mail" href="mailto:partner@visionpunch.de">partner@visionpunch.de</a>
		</div>
	</div>
</section>

<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>

<script>
document.querySelectorAll('.fge-page .faq-q[aria-expanded]').forEach(function (btn) {
	btn.addEventListener('click', function () {
		var item = btn.closest('.faq-item');
		var open = item.classList.toggle('open');
		btn.setAttribute('aria-expanded', open ? 'true' : 'false');
		var tog = btn.querySelector('.faq-toggle');
		if (tog) tog.textContent = open ? '–' : '+';
	});
});
</script>

<?php get_footer();
