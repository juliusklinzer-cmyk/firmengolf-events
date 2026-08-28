<?php
/**
 * Template: Landingpage „Golflehrer-Partner werden" (/golflehrer-partner/)
 *
 * Typspezifische Schwester der gebündelten Partner-Seite (Julius, 28.08.,
 * Punkt 6): SEO schadet nicht, der Haupt-Funnel läuft aber indirekt über die
 * Endkunden-Landingpages. Deshalb fokussiert und schlank, CTA deeplinkt mit
 * ?ob_type=coach direkt in den Golflehrer-Wizard. Nicht in der Haupt-Nav.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$img            = static fn( string $n ): string => fge_get_placeholder_image_url( $n );
$url_onboarding = add_query_arg( 'ob_type', 'coach', home_url( '/partner-onboarding/' ) );
$url_faq        = ( $p = get_page_by_path( 'partner-faq' ) ) ? (string) get_permalink( $p->ID ) : home_url( '/partner-faq/' );
$c              = fge_company();
$arrow_right    = '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg>';

$coach_faq = [
	[ 'Was kostet mich das als Golflehrer?', 'Nichts. Du bekommst genau deinen angegebenen Preis, die Vermittlungsprovision zahlt der Kunde obendrauf. Kein Setup-Preis, keine Gebühren, keine Bindung.' ],
	[ 'Ich habe keinen eigenen Platz, geht das trotzdem?', 'Ja. Du gibst an, wo du unterrichtest. Ist die Anlage schon Firmengolf-Partner, sparst du dir die Beschreibung. Wenn nicht, beschreibst du sie selbst und kannst sogar große Events inklusive Verpflegung anbieten.' ],
	[ 'Brauche ich eine bestimmte Lizenz?', 'Nein. Wir fragen deine Qualifikation ab und zeigen sie in deinem Profil, sie ist aber keine Voraussetzung.' ],
	[ 'Wie läuft die Abrechnung?', 'Nach dem Event stellst du deine Rechnung an Firmengolf, nicht an das Unternehmen. Wir prüfen, zahlen dich aus und rechnen mit dem Kunden ab. Ein Rechnungsempfänger, ein Zahlungsziel.' ],
	[ 'Muss ich Termine freihalten?', 'Nein. Jede Anfrage kommt mit Wunschterminen, und du bestätigst nur, was dir passt. Nichts wird gebucht, bevor du nicht freigegeben hast.' ],
];
?>
<div class="fge-page" id="fge-main" role="main" tabindex="-1">

<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => '' ] ); ?>

<section class="mk-hero" aria-label="Golflehrer-Partner werden">
	<div class="mk-hero-photo" style="background-image:url('<?php echo esc_url( $img( 'buerodach-golfplatz.jpg' ) ); ?>')">
		<div class="mk-hero-scrim" aria-hidden="true"></div>
		<div class="mk-hero-content">
			<div class="mk-hero-eyebrow">Für Golflehrer &amp; Golf-Pros</div>
			<h1 class="mk-hero-title">
				<span class="mk-hero-lead">Erweitere dein Angebot</span>
				<span class="mk-hero-lead">mit Firmenevents.</span>
			</h1>
			<p class="mk-hero-sub">
				Unternehmen suchen Golflehrer für Schnupperkurse, Platzreife und Teamevents.
				Wir bringen dir die Gruppen, du unterrichtest. Ohne Akquise, ohne Fixkosten.
			</p>
			<div class="mk-hero-ctas">
				<a class="fg-btn-cta fg-btn-lg" href="<?php echo esc_url( $url_onboarding ); ?>">
					Kostenlos Partner werden
					<span class="fg-arrow"><?php echo $arrow_right; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				</a>
				<a class="fg-btn-ghost-light" href="<?php echo esc_url( $url_faq ); ?>">
					Erst Fragen klären →
				</a>
			</div>
		</div>
	</div>
</section>

<section class="mk-section cty-reveal" aria-label="Warum Firmengolf für Golflehrer">
	<div class="mk-section-head">
		<div class="mk-eyebrow">Warum das für dich funktioniert</div>
		<h2 class="mk-h2">Firmenkurse sind planbare <em class="mk-italic">Aufträge</em>.</h2>
		<p class="mk-sub">Statt einzelner Schnupperstunden unterrichtest du ganze Teams, lange im Voraus angefragt und sauber koordiniert.</p>
	</div>
	<div class="gp-aud-grid">
		<?php
		$coach_points = [
			[ 't' => 'Gruppen statt Einzelstunden', 'sub' => 'Schnupperkurse, Platzreife und Event-Coaching für 10 bis 40 Personen, angefragt von Unternehmen, nicht von Laufkundschaft.' ],
			[ 't' => 'Du bestimmst, wo', 'sub' => 'Am Heimatplatz, mobil beim Unternehmen oder im Studio. Du hinterlegst einmal, was du anbietest, und entscheidest bei jeder Anfrage neu.' ],
			[ 't' => 'Eine Rechnung, keine Akquise', 'sub' => 'Du stellst deine Rechnung an Firmengolf, wir rechnen mit dem Kunden ab. Kein Angebots-Ping-Pong, kein Hinterhertelefonieren.' ],
		];
		foreach ( $coach_points as $pt ) : ?>
			<div class="gp-aud">
				<h3 class="gp-aud-t"><?php echo esc_html( $pt['t'] ); ?></h3>
				<p class="gp-aud-sub"><?php echo esc_html( $pt['sub'] ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>
</section>

<section class="mk-section mk-band cty-reveal" aria-label="So funktioniert es">
	<div class="mk-section-head">
		<div class="mk-eyebrow">So funktioniert es</div>
		<h2 class="mk-h2">In drei Schritten zum ersten <em class="mk-italic">Firmenkurs</em>.</h2>
	</div>
	<div class="gp-aud-grid">
		<?php
		$coach_steps = [
			[ 't' => '1 · Profil anlegen', 'sub' => 'Etwa zehn Minuten: wer du bist, wo du unterrichtest, was du anbietest. Dein Profil wird deine öffentliche Visitenkarte mit Foto und deiner Geschichte.' ],
			[ 't' => '2 · Formate und Konditionen hinterlegen', 'sub' => 'Vom Schnupperkurs für Teams bis zur Platzreife über mehrere Termine. Du legst Netto-Preise fest, der Kunde sieht den Endpreis.' ],
			[ 't' => '3 · Anfragen bestätigen und loslegen', 'sub' => 'Jede Anfrage kommt mit Wunschterminen. Du bestätigst, unterrichtest und stellst deine Rechnung an Firmengolf.' ],
		];
		foreach ( $coach_steps as $st ) : ?>
			<div class="gp-aud">
				<h3 class="gp-aud-t"><?php echo esc_html( $st['t'] ); ?></h3>
				<p class="gp-aud-sub"><?php echo esc_html( $st['sub'] ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>
</section>

<section class="mk-section cty-reveal" aria-label="Formate">
	<div class="mk-section-head">
		<div class="mk-eyebrow">Deine Möglichkeiten</div>
		<h2 class="mk-h2">Was du anbieten <em class="mk-italic">kannst</em>.</h2>
		<p class="mk-sub">Ein Auszug aus den Formaten, die Unternehmen bei Golflehrern anfragen. Du wählst aus, was zu dir passt, und legst eigene Pakete an, so kreativ du willst.</p>
	</div>
	<div class="gp-pack-chips">
		<?php foreach ( array_slice( array_values( fge_catalog_coach_formats() ), 0, 10 ) as $chip ) : ?>
			<span class="gp-pack-chip"><?php echo esc_html( $chip ); ?></span>
		<?php endforeach; ?>
	</div>
</section>

<section class="mk-section cty-reveal" aria-label="Häufige Fragen">
	<div class="mk-section-head">
		<div class="mk-eyebrow">Häufige Fragen</div>
		<h2 class="mk-h2">Die Fragen, die uns Golflehrer zuerst stellen.</h2>
	</div>
	<?php
	get_template_part( 'template-parts/fge-faq', null, [
		'items' => array_map( static fn( $f ) => [ 'q' => $f[0], 'a' => $f[1] ], $coach_faq ),
	] );
	?>
	<p style="margin-top:22px;">
		<a class="fg-btn-ghost" href="<?php echo esc_url( $url_faq ); ?>">Alle Fragen in der Partner-FAQ <?php echo $arrow_right; // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
	</p>
</section>

<section class="mk-cta" aria-label="Partner werden">
	<div class="mk-cta-inner">
		<div class="mk-eyebrow" style="color:rgba(251,250,246,0.65)">Kostenlos &amp; unverbindlich</div>
		<h2 class="mk-cta-h">Dein erster Firmenkurs ist eine <em class="mk-italic">Anfrage</em> entfernt.</h2>
		<div class="mk-cta-ctas">
			<a class="fg-btn-ink fg-btn-lg" href="<?php echo esc_url( $url_onboarding ); ?>" style="background:var(--paper-100);color:var(--fairway-900)">Jetzt Partner werden</a>
			<a class="mk-cta-mail" href="mailto:<?php echo esc_attr( $c['email_partner'] ); ?>"><?php echo esc_html( $c['email_partner'] ); ?></a>
		</div>
	</div>
</section>

<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>

<script>
(function () {
	if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches && 'IntersectionObserver' in window) {
		document.documentElement.classList.add('cty-io');
		var gpIo = new IntersectionObserver(function (entries) {
			entries.forEach(function (e) {
				if (e.isIntersecting) { e.target.classList.add('is-in'); gpIo.unobserve(e.target); }
			});
		}, { rootMargin: '0px 0px -8% 0px' });
		document.querySelectorAll('.cty-reveal').forEach(function (el) { gpIo.observe(el); });
	}
}());
</script>

<?php get_footer();
