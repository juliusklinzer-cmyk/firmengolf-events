<?php
/**
 * Template: Impressum
 * Echte Rechtsdaten aus fge_company() (Plugin: includes/company-info.php).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$co = fge_company();
?>
<div class="fge-page" id="fge-main" role="main" tabindex="-1">

<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => '' ] ); ?>

<article class="legal" aria-label="Impressum">
	<div class="legal-head">
		<div class="mk-eyebrow">Rechtliches</div>
		<h1 class="legal-h">Impressum</h1>
	</div>
	<div class="legal-body">
		<h2>Angaben gemäß § 5 DDG</h2>
		<p>
			<?php echo esc_html( $co['legal_name'] ); ?><br>
			<?php echo esc_html( $co['hq_street'] ); ?><br>
			<?php echo esc_html( $co['hq_zip'] . ' ' . $co['hq_city'] ); ?><br>
			Deutschland
		</p>

		<h2>Vertreten durch</h2>
		<p>Geschäftsführer: <?php echo esc_html( $co['managing_director'] ); ?></p>

		<h2>Kontakt</h2>
		<p>
			Telefon: <a href="tel:<?php echo esc_attr( $co['phone_tel'] ); ?>"><?php echo esc_html( $co['phone_display'] ); ?></a><br>
			E-Mail: <a href="mailto:<?php echo esc_attr( $co['email_general'] ); ?>"><?php echo esc_html( $co['email_general'] ); ?></a>
		</p>

		<h2>Registereintrag</h2>
		<p>
			Eintragung im Handelsregister.<br>
			Registergericht: <?php echo esc_html( $co['register_court'] ); ?><br>
			Registernummer: <?php echo esc_html( $co['register_no'] ); ?>
		</p>

		<h2>Umsatzsteuer-ID</h2>
		<p>Umsatzsteuer-Identifikationsnummer gemäß § 27 a Umsatzsteuergesetz: <?php echo esc_html( $co['ust_id'] ); ?></p>

		<h2>Steuernummer</h2>
		<p>
			<?php echo esc_html( $co['tax_no'] ); ?> (<?php echo esc_html( $co['finanzamt'] ); ?>)<br>
			Finanzamt-Durchwahl: <?php echo esc_html( $co['finanzamt_phone'] ); ?>
		</p>

		<h2>Berufsgenossenschaft</h2>
		<p>
			Verwaltungs-Berufsgenossenschaft (VBG)<br>
			Unternehmensnummer: <?php echo esc_html( $co['vbg_no'] ); ?>
		</p>

		<h2>Verantwortlich für den Inhalt nach § 18 Abs. 2 MStV</h2>
		<p>
			<?php echo esc_html( $co['managing_director'] ); ?><br>
			<?php echo esc_html( $co['legal_name'] ); ?><br>
			<?php echo esc_html( $co['hq_street'] . ', ' . $co['hq_zip'] . ' ' . $co['hq_city'] ); ?>
		</p>

		<h2>EU-Streitschlichtung</h2>
		<p>
			Die Europäische Kommission stellt eine Plattform zur Online-Streitbeilegung (OS) bereit:
			<a href="https://ec.europa.eu/consumers/odr/" target="_blank" rel="noopener noreferrer">https://ec.europa.eu/consumers/odr/</a>.
			Wir sind nicht bereit und nicht verpflichtet, an Streitbeilegungsverfahren vor einer
			Verbraucherschlichtungsstelle teilzunehmen.
		</p>

		<h2>Haftung für Inhalte und Links</h2>
		<p>
			Die Inhalte unserer Seiten wurden mit größter Sorgfalt erstellt. Für die Richtigkeit, Vollständigkeit
			und Aktualität der Inhalte können wir jedoch keine Gewähr übernehmen. Für Inhalte externer Links sind
			ausschließlich deren Betreiber verantwortlich; zum Zeitpunkt der Verlinkung waren keine Rechtsverstöße erkennbar.
		</p>
	</div>
</article>

<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>
<?php get_footer();
