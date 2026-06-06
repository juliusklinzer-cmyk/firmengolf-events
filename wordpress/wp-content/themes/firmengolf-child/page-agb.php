<?php
/**
 * Template: AGB (Gerüst).
 * Struktur als Platzhalter; finaler Rechtstext folgt (Anwalt).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$co = fge_company();
?>
<div class="fge-page">

<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => '' ] ); ?>

<article class="legal" aria-label="Allgemeine Geschäftsbedingungen">
	<div class="legal-head">
		<div class="mk-eyebrow">Rechtliches</div>
		<h1 class="legal-h">Allgemeine Geschäftsbedingungen</h1>
	</div>
	<div class="legal-body">
		<p class="legal-lead">
			Diese Allgemeinen Geschäftsbedingungen („AGB") gelten für alle über Firmengolf vermittelten
			Event-Anfragen und -Buchungen zwischen der <?php echo esc_html( $co['legal_name'] ); ?> und ihren Kunden.
		</p>

		<h2>§ 1 Geltungsbereich</h2>
		<p>
			Diese AGB gelten für alle Verträge, die über die Plattform Firmengolf geschlossen werden.
			Abweichende Bedingungen des Kunden werden nur mit ausdrücklicher schriftlicher Zustimmung Bestandteil des Vertrages.
		</p>

		<h2>§ 2 Vertragspartner</h2>
		<p>
			Der Vertrag über die Vermittlung kommt zwischen dir und der <?php echo esc_html( $co['legal_name'] ); ?> zustande.
			Der Vertrag über die Erbringung der Event-Leistungen kommt zwischen dir und dem jeweiligen Partnerplatz zustande,
			sofern nicht ausdrücklich anders vereinbart.
		</p>

		<h2>§ 3 Buchung &amp; Bestätigung</h2>
		<p>
			Eine Anfrage über das Anfrageformular stellt noch keinen Vertragsabschluss dar.
			Ein verbindlicher Vertrag kommt erst mit der schriftlichen Bestätigung durch Firmengolf oder den Partnerplatz zustande.
		</p>

		<h2>§ 4 Preise &amp; Zahlung</h2>
		<p>
			Alle Preise verstehen sich inklusive der jeweils gültigen gesetzlichen Mehrwertsteuer.
			Die Zahlung erfolgt nach Rechnungsstellung mit einem Zahlungsziel von 14 Tagen.
		</p>

		<h2>§ 5 Stornierung</h2>
		<ul>
			<li>Bis 30 Tage vor Veranstaltung: kostenfrei</li>
			<li>Bis 14 Tage vor Veranstaltung: 50 % des Veranstaltungspreises</li>
			<li>Weniger als 14 Tage vor Veranstaltung: 100 % des Veranstaltungspreises</li>
		</ul>

		<h2>§ 6 Haftung</h2>
		<p>
			Firmengolf haftet für Schäden nur bei Vorsatz oder grober Fahrlässigkeit, sofern keine Verletzung von Leben,
			Körper oder Gesundheit vorliegt oder wesentliche Vertragspflichten betroffen sind.
		</p>

		<h2>§ 7 Schlussbestimmungen</h2>
		<p>
			Es gilt das Recht der Bundesrepublik Deutschland. Erfüllungsort und Gerichtsstand ist
			<?php echo esc_html( $co['hq_city'] ); ?>, soweit gesetzlich zulässig.
		</p>

		<p class="muted" style="margin-top:32px;font-size:13px;">
			Hinweis: Dieser Text ist ein Platzhalter und nicht rechtsverbindlich. Die finalen AGB
			werden noch erstellt und geprüft.
		</p>
	</div>
</article>

<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>
<?php get_footer();
