<?php
/**
 * Template: Datenschutzerklärung.
 * Inhaltlich auf die tatsächliche Verarbeitung der Seite zugeschnitten.
 * Vor Live-Gang von Anwalt/DSB prüfen lassen (kein Rechtsrat).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$co        = fge_company();
$addr      = $co['hq_street'] . ', ' . $co['hq_zip'] . ' ' . $co['hq_city'];
$mail_ds   = 'datenschutz@visionpunch.de';
$mail_gen  = $co['email_general'];
$mail_jobs = $co['email_jobs'] ?? 'jobs@visionpunch.de';
?>
<div class="fge-page" id="fge-main" role="main" tabindex="-1">

<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => '' ] ); ?>

<article class="legal" aria-label="Datenschutzerklärung">
	<div class="legal-head">
		<div class="mk-eyebrow">Rechtliches</div>
		<h1 class="legal-h">Datenschutz bei Firmengolf</h1>
	</div>
	<div class="legal-body">
		<p class="legal-lead">
			Der Schutz Ihrer personenbezogenen Daten ist uns wichtig. Nachfolgend informieren wir Sie
			über die Verarbeitung personenbezogener Daten auf dieser Website (Art. 13/14 DSGVO).
		</p>

		<h2>1. Verantwortlicher</h2>
		<p>
			<?php echo esc_html( $co['legal_name'] ); ?> (Marke „<?php echo esc_html( $co['brand'] ); ?>")<br>
			<?php echo esc_html( $addr ); ?>, Deutschland<br>
			Vertreten durch den Geschäftsführer: <?php echo esc_html( $co['managing_director'] ); ?><br>
			Telefon: <?php echo esc_html( $co['phone_display'] ); ?> · E-Mail: <a href="mailto:<?php echo esc_attr( $mail_gen ); ?>"><?php echo esc_html( $mail_gen ); ?></a><br>
			Registergericht: <?php echo esc_html( $co['register_court'] . ', ' . $co['register_no'] ); ?> · USt-IdNr.: <?php echo esc_html( $co['ust_id'] ); ?><br>
			Datenschutzanfragen: <a href="mailto:<?php echo esc_attr( $mail_ds ); ?>"><?php echo esc_html( $mail_ds ); ?></a> · weitere Angaben im <a href="<?php echo esc_url( home_url( '/impressum/' ) ); ?>">Impressum</a>.
		</p>

		<h2>2. Datenschutzbeauftragter</h2>
		<p>
			Wir sind gesetzlich nicht zur Bestellung eines Datenschutzbeauftragten verpflichtet.
			Bei Fragen zum Datenschutz wenden Sie sich bitte an den unter Ziffer 1 genannten Verantwortlichen
			bzw. an <a href="mailto:<?php echo esc_attr( $mail_ds ); ?>"><?php echo esc_html( $mail_ds ); ?></a>.
		</p>

		<h2>3. Allgemeines zur Datenverarbeitung</h2>
		<p>
			Wir verarbeiten personenbezogene Daten nur, soweit dies zur Bereitstellung einer funktionsfähigen
			Website sowie unserer Inhalte und Leistungen erforderlich ist. Rechtsgrundlagen sind insbesondere
			Art. 6 Abs. 1 lit. a (Einwilligung), lit. b (Vertrag/vorvertragliche Maßnahmen), lit. c (rechtliche
			Verpflichtung) und lit. f (berechtigtes Interesse) DSGVO.
		</p>

		<h2>4. Ihre Rechte</h2>
		<ul>
			<li>Recht auf Auskunft (Art. 15 DSGVO)</li>
			<li>Recht auf Berichtigung (Art. 16 DSGVO)</li>
			<li>Recht auf Löschung (Art. 17 DSGVO)</li>
			<li>Recht auf Einschränkung der Verarbeitung (Art. 18 DSGVO)</li>
			<li>Recht auf Datenübertragbarkeit (Art. 20 DSGVO)</li>
			<li>Widerspruchsrecht gegen Verarbeitungen auf Grundlage berechtigter Interessen (Art. 21 DSGVO)</li>
			<li>Widerruf erteilter Einwilligungen mit Wirkung für die Zukunft (Art. 7 Abs. 3 DSGVO)</li>
		</ul>
		<p>
			Wenden Sie sich dafür an <a href="mailto:<?php echo esc_attr( $mail_ds ); ?>"><?php echo esc_html( $mail_ds ); ?></a>.
			Sie haben zudem das Recht auf Beschwerde bei einer Aufsichtsbehörde; zuständig ist das Bayerische
			Landesamt für Datenschutzaufsicht (BayLDA), Promenade 18, 91522 Ansbach.
		</p>

		<h2>5. Hosting</h2>
		<p>
			Diese Website wird bei one.com gehostet. Beim Besuch der Website verarbeitet one.com insbesondere
			technische Zugriffsdaten wie IP-Adresse, Datum und Uhrzeit des Zugriffs, aufgerufene Seiten,
			Browserinformationen und Server-Logfiles. Die Verarbeitung erfolgt, um die Website sicher und
			zuverlässig bereitzustellen. Rechtsgrundlage ist Art. 6 Abs. 1 lit. f DSGVO. Mit one.com besteht ein
			Vertrag zur Auftragsverarbeitung gemäß Art. 28 DSGVO.
		</p>

		<h2>6. Cookies &amp; Einwilligungsverwaltung</h2>
		<p>
			Technisch notwendige Cookies setzen wir auf Grundlage von § 25 Abs. 2 TDDDG bzw. Art. 6 Abs. 1 lit. f
			DSGVO ein. Für alle nicht notwendigen Dienste (Statistik, Marketing, eingebettete Inhalte) holen wir
			über unser selbst gehostetes Consent-Tool (Klaro) Ihre Einwilligung ein (§ 25 Abs. 1 TDDDG, Art. 6
			Abs. 1 lit. a DSGVO). Ihre Auswahl können Sie jederzeit über den Link „Cookie-Einstellungen" im Footer
			ändern oder widerrufen.
		</p>

		<h2>7. Kontakt- und Event-Anfragen</h2>
		<p>
			Über unsere Formulare (Event-Anfrage, Kontakt) verarbeiten wir die angegebenen Daten – insbesondere
			Firma, Ansprechpartner, E-Mail, Telefon sowie Angaben zum geplanten Event (z. B. Termin, Gruppengröße,
			Format, Region, Budget, Wünsche). Diese Daten werden gespeichert und zur Bearbeitung an die zuständigen
			Stellen übermittelt. Rechtsgrundlage: Durchführung (vor-)vertraglicher Maßnahmen (Art. 6 Abs. 1 lit. b
			DSGVO) sowie berechtigtes Interesse an der Kommunikation (lit. f). Speicherdauer: nur solange für den
			Zweck erforderlich; geschäftsrelevante Vorgänge unterliegen den gesetzlichen Aufbewahrungsfristen
			(i. d. R. 6 Jahre nach § 257 HGB bzw. 10 Jahre nach § 147 AO), danach erfolgt Löschung.
		</p>

		<h2>8. CRM – HubSpot</h2>
		<p>
			Wir nutzen HubSpot (Anbieter: HubSpot, Inc., USA) zur Bearbeitung von Anfragen, zur Verwaltung von
			Unternehmens- und Kontaktdaten sowie zur Kommunikation mit Interessenten und Partnern. Dabei können
			insbesondere Name, Unternehmen, E-Mail-Adresse, Telefonnummer, Nachricht, Anfrageinhalt und
			Kommunikationsverlauf verarbeitet werden. Rechtsgrundlage ist je nach Anfrage Art. 6 Abs. 1 lit. b DSGVO
			(vorvertragliche Kommunikation) oder Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse an effizienter
			Kundenverwaltung). Mit HubSpot besteht ein Vertrag zur Auftragsverarbeitung gemäß Art. 28 DSGVO. Eine
			Verarbeitung kann auch in den USA erfolgen; die Übermittlung wird durch das EU-US Data Privacy Framework
			bzw. Standardvertragsklauseln (Art. 44 ff. DSGVO) abgesichert.
		</p>

		<h2>9. HubSpot-Terminkalender</h2>
		<p>
			Auf unserer Kontaktseite verlinken wir auf den HubSpot-Terminkalender (Meetings) zur Online-Terminbuchung.
			Es handelt sich um eine einfache Verlinkung – beim bloßen Aufruf unserer Seite werden keine Daten an HubSpot
			übertragen. Erst wenn Sie auf den Link klicken, werden Sie zur HubSpot-Buchungsseite (HubSpot, Inc., USA)
			weitergeleitet, für deren Datenverarbeitung dann HubSpot verantwortlich ist.
		</p>

		<h2>10. Google Maps</h2>
		<p>
			Zur Anzeige von Standorten binden wir Google Maps (Google Ireland Ltd., Dublin, Irland; Google LLC, USA)
			ein. Die Karten werden erst nach Ihrer Einwilligung geladen; dabei wird u. a. Ihre IP-Adresse an Google
			übertragen (ggf. in die USA, gestützt auf das EU-US Data Privacy Framework). Rechtsgrundlage: Art. 6
			Abs. 1 lit. a DSGVO, § 25 Abs. 1 TDDDG.
		</p>

		<h2>11. Google Analytics</h2>
		<p>
			Wir nutzen Google Analytics 4 zur pseudonymen, statistischen Auswertung der Websitenutzung (Google
			Ireland Ltd. / Google LLC, USA) – ausschließlich nach Ihrer Einwilligung (Art. 6 Abs. 1 lit. a DSGVO,
			§ 25 Abs. 1 TDDDG). Dabei werden Cookies gesetzt und Nutzungsdaten ggf. in die USA übertragen
			(Safeguards wie oben). Es besteht ein Auftragsverarbeitungsvertrag.
		</p>

		<h2>12. Newsletter &amp; E-Mail-Marketing (Kit)</h2>
		<p>
			Wir nutzen Kit für den Versand von Newslettern, Informationen zu Firmengolf, Partnerupdates und zur
			Verwaltung von E-Mail-Kontakten. Anbieter ist Kit, Inc. (USA). Dabei können insbesondere Name,
			E-Mail-Adresse, Unternehmen, Interessen, Anmeldezeitpunkt, IP-Adresse, Opt-in-Status sowie Interaktionen
			mit unseren E-Mails verarbeitet werden. Die Verarbeitung erfolgt je nach Nutzung auf Grundlage Ihrer
			Einwilligung gemäß Art. 6 Abs. 1 lit. a DSGVO oder auf Grundlage unseres berechtigten Interesses an einer
			strukturierten Kommunikation gemäß Art. 6 Abs. 1 lit. f DSGVO. Die Newsletter-Anmeldung erfolgt im
			Double-Opt-in-Verfahren; eine Abmeldung ist jederzeit über den Link in jeder E-Mail möglich. Mit Kit
			besteht ein Vertrag zur Auftragsverarbeitung gemäß Art. 28 DSGVO. Eine Übermittlung in die USA wird durch
			das EU-US Data Privacy Framework bzw. Standardvertragsklauseln (Art. 44 ff. DSGVO) abgesichert.
		</p>

		<h2>13. Bewerbungen</h2>
		<p>
			Senden Sie uns eine Bewerbung (z. B. an <a href="mailto:<?php echo esc_attr( $mail_jobs ); ?>"><?php echo esc_html( $mail_jobs ); ?></a>),
			verarbeiten wir Ihre übermittelten Daten ausschließlich zur Durchführung des Bewerbungsverfahrens
			(§ 26 Abs. 1 BDSG i. V. m. Art. 88 DSGVO bzw. Art. 6 Abs. 1 lit. b DSGVO). Kommt kein
			Beschäftigungsverhältnis zustande, löschen wir die Bewerberdaten spätestens 6 Monate nach Abschluss des
			Verfahrens, sofern Sie keiner längeren Speicherung zugestimmt haben.
		</p>

		<h2>14. Social-Media-Profile</h2>
		<p>
			Wir verlinken auf unsere Profile bei Instagram, Facebook und LinkedIn. Es handelt sich um einfache
			Verlinkungen – beim bloßen Aufruf unserer Seite werden keine Daten an diese Netzwerke übertragen. Erst
			beim Klick werden Sie zur jeweiligen Plattform weitergeleitet, für deren Datenverarbeitung die Anbieter
			verantwortlich sind.
		</p>

		<h2>15. Schriftarten</h2>
		<p>
			Schriftarten werden lokal von unserem eigenen Server eingebunden. Eine Verbindung zu Servern Dritter
			(z. B. Google Fonts) findet nicht statt; es werden keine personenbezogenen Daten an Dritte übertragen.
		</p>

		<h2>16. Verschlüsselung</h2>
		<p>
			Diese Website nutzt aus Sicherheitsgründen eine SSL-/TLS-Verschlüsselung (erkennbar an „https://").
		</p>

		<h2>17. Aktualität und Änderung</h2>
		<p>
			Diese Datenschutzerklärung ist aktuell gültig. Durch die Weiterentwicklung der Website oder geänderte
			gesetzliche Vorgaben kann eine Anpassung notwendig werden.
		</p>
	</div>
</article>

<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>
<?php get_footer();
