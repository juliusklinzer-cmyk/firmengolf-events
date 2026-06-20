<?php
/**
 * Template: AGB (Kunden, B2B).
 * Basis-Fassung – vor Live-Gang von Anwalt/DSB prüfen lassen (kein Rechtsrat).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$co   = fge_company();
$addr = $co['hq_street'] . ', ' . $co['hq_zip'] . ' ' . $co['hq_city'];
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
			Diese Allgemeinen Geschäftsbedingungen („AGB") gelten für die Vermittlung und Organisation von
			Firmen-Golfevents zwischen der <?php echo esc_html( $co['legal_name'] ); ?> (Marke „<?php echo esc_html( $co['brand'] ); ?>")
			und ihren Kunden.
		</p>

		<h2>§ 1 Geltungsbereich, Vertragspartner</h2>
		<p>
			(1) Diese AGB gelten für alle Verträge über die Vermittlung und Organisation von Firmen-Golfevents
			zwischen der <?php echo esc_html( $co['legal_name'] ); ?>, <?php echo esc_html( $addr ); ?> („Firmengolf", „wir")
			und dem Kunden.<br>
			(2) Unsere Angebote richten sich ausschließlich an Unternehmer i. S. d. § 14 BGB, juristische Personen
			des öffentlichen Rechts und öffentlich-rechtliche Sondervermögen. Verträge mit Verbrauchern kommen nicht zustande.<br>
			(3) Es gelten ausschließlich diese AGB. Abweichende Bedingungen des Kunden werden nicht Vertragsbestandteil,
			auch wenn wir ihnen nicht ausdrücklich widersprechen.
		</p>

		<h2>§ 2 Unsere Rolle und Leistungen</h2>
		<p>
			(1) Firmengolf erbringt Vermittlungs-, Organisations-, Koordinations- und Abrechnungsleistungen. Wir stellen
			passende Event-Formate zusammen, vermitteln die beteiligten Leistungsträger (z. B. Golfplätze, Golflehrer,
			Shuttle-/Transportunternehmen, Gastronomie) und koordinieren den Ablauf.<br>
			(2) Die eigentlichen Eventleistungen vor Ort (Platznutzung, Training, Transport, Verpflegung u. a.) werden von den
			jeweiligen Partnern (Leistungsträgern) eigenverantwortlich erbracht. Firmengolf ist insoweit nicht Veranstalter
			vor Ort und nicht Erbringer dieser Leistungen.<br>
			(3) Die Abrechnung erfolgt gebündelt über Firmengolf (eine Rechnung), auch wenn einzelne Leistungen von Partnern
			erbracht werden.<br>
			(4) Bei den über unsere Plattform gelisteten Golfplatz-Events handeln wir als Vermittler; bei individuell
			angefragten Events zusätzlich als Organisator und Planer.
		</p>

		<h2>§ 3 Vertragsschluss</h2>
		<p>
			(1) Unsere Angebote sind freibleibend. Mit einer Anfrage gibt der Kunde noch kein verbindliches Angebot ab.<br>
			(2) Auf Basis der Anfrage erstellen wir ein individuelles Angebot. Der Vertrag kommt zustande, wenn der Kunde das
			Angebot annimmt (Buchung) und wir die Buchung in Textform bestätigen (Buchungsbestätigung), spätestens mit
			Durchführung des Events.<br>
			(3) Maßgeblich für Leistungsumfang und Preis ist die jeweilige Buchungsbestätigung.
		</p>

		<h2>§ 4 Leistungsumfang, Teilnehmerzahl, Änderungen</h2>
		<p>
			(1) Der Leistungsumfang ergibt sich aus dem jeweiligen Angebot bzw. der Buchungsbestätigung. Die Teilnehmerzahl
			ist im Angebot als Spanne („von–bis Personen") angegeben.<br>
			(2) Änderungen der Teilnehmerzahl innerhalb der angegebenen Spanne sind möglich; der Preis kann sich entsprechend
			anpassen. Wesentliche Abweichungen außerhalb der Spanne bedürfen der Abstimmung und können zu Preis- bzw.
			Leistungsänderungen führen.<br>
			(3) Geringfügige, dem Kunden zumutbare Änderungen des Ablaufs oder der eingesetzten Anlagen/Partner aus
			organisatorischen Gründen bleiben vorbehalten und stellen keinen Mangel dar.
		</p>

		<h2>§ 5 Preise und Zahlung</h2>
		<p>
			(1) Alle Preise verstehen sich netto zzgl. der gesetzlichen Umsatzsteuer.<br>
			(2) Bei einem Auftragswert über 5.000 € (netto) sind wir berechtigt, bei Buchung eine Anzahlung von bis zu 50 % zu verlangen.<br>
			(3) Die Schlussrechnung ist innerhalb von 14 Tagen nach Leistungserbringung ohne Abzug zur Zahlung fällig.<br>
			(4) Bei Zahlungsverzug gelten die gesetzlichen Verzugsregelungen (§§ 286, 288 BGB).
		</p>

		<h2>§ 6 Mitwirkung des Kunden</h2>
		<p>
			Der Kunde stellt rechtzeitig die für die Durchführung erforderlichen Informationen bereit (insbesondere
			Teilnehmerzahl, Ansprechpartner, besondere Anforderungen) und benennt einen verantwortlichen Ansprechpartner.
			Für die Richtigkeit der Angaben ist der Kunde verantwortlich.
		</p>

		<h2>§ 7 Stornierung / Rücktritt durch den Kunden</h2>
		<p>
			(1) Der Kunde kann den Vertrag vor dem Eventtermin in Textform stornieren. Es fallen folgende pauschalierten
			Stornokosten (in % des Auftragswerts) an:
		</p>
		<ul>
			<li>mehr als 42 Tage vor dem Termin: 10 %</li>
			<li>42–29 Tage vorher: 25 %</li>
			<li>28–15 Tage vorher: 50 %</li>
			<li>14–7 Tage vorher: 75 %</li>
			<li>weniger als 7 Tage vorher oder Nichterscheinen: 90 %</li>
		</ul>
		<p>
			(2) Bereits angefallene oder verbindlich zugesagte Stornokosten der Leistungsträger (z. B. Golfplatz, Hotel,
			Shuttle) werden mindestens in deren Höhe weiterberechnet, soweit sie die Pauschale übersteigen.<br>
			(3) Dem Kunden bleibt der Nachweis vorbehalten, dass kein oder ein wesentlich geringerer Schaden entstanden ist.
			Uns bleibt der Nachweis eines höheren konkreten Schadens vorbehalten.
		</p>

		<h2>§ 8 Verschiebung, Wetter und höhere Gewalt</h2>
		<p>
			(1) Golf findet überwiegend im Freien statt. Bei Gewitter oder vergleichbarer Gefahrenlage kann das Event aus
			Sicherheitsgründen unterbrochen und am selben Tag verschoben bzw. fortgesetzt werden; daraus entstehen keine Ansprüche.<br>
			(2) Muss ein Event aus Gründen, die wir oder der Kunde nicht zu vertreten haben (insbesondere höhere Gewalt,
			behördliche Anordnungen, Unwetter), vollständig abgesagt werden, vereinbaren wir vorrangig einen Ersatztermin.
			Ein Anspruch auf Rückzahlung besteht nur, soweit bereits gezahlte Beträge auf konkret nicht erbrachte und nicht
			stornokostenpflichtige Leistungen entfallen.<br>
			(3) Eine weitergehende Haftung für die Nichtdurchführung aus den in Absatz 1 und 2 genannten Gründen ist ausgeschlossen.
		</p>

		<h2>§ 9 Absage oder Änderung durch Firmengolf / Leistungsträger</h2>
		<p>
			(1) Wir sind berechtigt, ein Event abzusagen oder anzupassen, wenn die Durchführung aus von uns nicht zu
			vertretenden Gründen (insbesondere Ausfall eines Leistungsträgers, höhere Gewalt) unmöglich oder unzumutbar wird.<br>
			(2) In diesem Fall bemühen wir uns um eine gleichwertige Alternative oder einen Ersatztermin. Bereits gezahlte
			Beträge für nicht erbrachte Leistungen werden erstattet. Eine darüber hinausgehende Haftung besteht nach Maßgabe von § 11.
		</p>

		<h2>§ 10 Reise- und Übernachtungsleistungen</h2>
		<p>
			Sofern ein Event Reise-, Transfer- oder Übernachtungsleistungen umfasst, werden diese als Leistungen Dritter
			vermittelt; insoweit gelten ergänzend die Bedingungen der jeweiligen Anbieter.
		</p>

		<h2>§ 11 Haftung</h2>
		<p>
			(1) Wir haften unbeschränkt für Schäden aus der Verletzung des Lebens, des Körpers oder der Gesundheit, die auf
			einer Pflichtverletzung von uns oder unseren Erfüllungsgehilfen beruhen, sowie für Schäden aus Vorsatz und grober Fahrlässigkeit.<br>
			(2) Bei einfacher Fahrlässigkeit haften wir nur bei Verletzung einer wesentlichen Vertragspflicht (Kardinalpflicht)
			und begrenzt auf den vertragstypischen, vorhersehbaren Schaden.<br>
			(3) Firmengolf haftet für die ordnungsgemäße Vermittlung, Organisation und sorgfältige Auswahl der Leistungsträger.
			Für die Leistungserbringung der Partner vor Ort (z. B. Trainingsdurchführung, Transport, Platzzustand, Verpflegung)
			haften die jeweiligen Leistungsträger selbst; insoweit ist unsere Haftung – außer in den Fällen des Absatzes 1 – ausgeschlossen.<br>
			(4) Im Übrigen ist die Haftung ausgeschlossen.
		</p>

		<h2>§ 12 Teilnahme, gesundheitliche Eignung</h2>
		<p>
			(1) Die Teilnahme an den sportlichen Aktivitäten erfolgt auf eigene Verantwortung der Teilnehmer. Jeder Teilnehmer
			ist für seine gesundheitliche Eignung selbst verantwortlich.<br>
			(2) Den Sicherheitshinweisen und Anweisungen des Personals vor Ort ist Folge zu leisten. Der Kunde weist die Teilnehmer hierauf hin.
		</p>

		<h2>§ 13 Datenschutz</h2>
		<p>
			Informationen zur Verarbeitung personenbezogener Daten finden Sie in unserer
			<a href="<?php echo esc_url( home_url( '/datenschutz/' ) ); ?>">Datenschutzerklärung</a>.
		</p>

		<h2>§ 14 Schlussbestimmungen</h2>
		<p>
			(1) Es gilt das Recht der Bundesrepublik Deutschland unter Ausschluss des UN-Kaufrechts.<br>
			(2) Ausschließlicher Gerichtsstand ist – soweit gesetzlich zulässig – <?php echo esc_html( $co['hq_city'] ); ?>.<br>
			(3) Änderungen und Ergänzungen bedürfen der Textform.<br>
			(4) Sollte eine Bestimmung unwirksam sein, bleibt die Wirksamkeit der übrigen Bestimmungen unberührt.
		</p>
	</div>
</article>

<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>
<?php get_footer();
