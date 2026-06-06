<?php
/**
 * Template: Datenschutzerklärung (Gerüst).
 * Struktur nach DSGVO; finaler Rechtstext folgt (Anwalt/Generator).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$co = fge_company();
?>
<div class="fge-page">

<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => '' ] ); ?>

<article class="legal" aria-label="Datenschutzerklärung">
	<div class="legal-head">
		<div class="mk-eyebrow">Rechtliches</div>
		<h1 class="legal-h">Datenschutzerklärung</h1>
	</div>
	<div class="legal-body">
		<p class="legal-lead">
			Der Schutz deiner persönlichen Daten ist uns wichtig. Wir verarbeiten deine Daten ausschließlich
			auf Grundlage der gesetzlichen Bestimmungen (DSGVO, BDSG, TDDDG).
		</p>

		<h2>1. Verantwortlicher</h2>
		<p>
			<?php echo esc_html( $co['legal_name'] ); ?>, <?php echo esc_html( $co['hq_street'] . ', ' . $co['hq_zip'] . ' ' . $co['hq_city'] ); ?>.<br>
			E-Mail: <a href="mailto:<?php echo esc_attr( $co['email_general'] ); ?>"><?php echo esc_html( $co['email_general'] ); ?></a> · weitere Angaben siehe <a href="<?php echo esc_url( home_url( '/impressum/' ) ); ?>">Impressum</a>.
		</p>

		<h2>2. Welche Daten werden erhoben?</h2>
		<ul>
			<li>Server-Logfiles (IP-Adresse, Zeitstempel, Browser, Betriebssystem)</li>
			<li>Kontaktdaten, die du uns im Anfrage- oder Kontaktformular übermittelst</li>
			<li>Nutzungsdaten für die Bereitstellung der Website</li>
		</ul>

		<h2>3. Zweck der Verarbeitung</h2>
		<ul>
			<li>Beantwortung von Anfragen und Kontaktaufnahme</li>
			<li>Vertragsanbahnung und -abwicklung</li>
			<li>Bereitstellung und Sicherheit der Website</li>
		</ul>

		<h2>4. Cookies</h2>
		<p>
			Wir setzen technisch notwendige Cookies ein, um die Nutzung der Website zu ermöglichen.
			Tracking-Cookies setzen wir nur mit deiner ausdrücklichen Einwilligung.
		</p>

		<h2>5. Deine Rechte</h2>
		<ul>
			<li>Recht auf Auskunft (Art. 15 DSGVO)</li>
			<li>Recht auf Berichtigung (Art. 16 DSGVO)</li>
			<li>Recht auf Löschung (Art. 17 DSGVO)</li>
			<li>Recht auf Einschränkung der Verarbeitung (Art. 18 DSGVO)</li>
			<li>Recht auf Datenübertragbarkeit (Art. 20 DSGVO)</li>
			<li>Widerspruchsrecht (Art. 21 DSGVO)</li>
			<li>Beschwerderecht bei einer Aufsichtsbehörde</li>
		</ul>

		<h2>6. Kontakt zum Datenschutz</h2>
		<p>
			Fragen zur Verarbeitung deiner Daten richtest du an
			<a href="mailto:<?php echo esc_attr( $co['email_general'] ); ?>"><?php echo esc_html( $co['email_general'] ); ?></a>.
		</p>

		<p class="muted" style="margin-top:32px;font-size:13px;">
			Hinweis: Dieser Text ist ein Platzhalter und nicht rechtsverbindlich. Die finale Datenschutzerklärung
			wird noch geprüft und ergänzt.
		</p>
	</div>
</article>

<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>
<?php get_footer();
