<?php
/**
 * Template: Landingpage „Indoor-Partner werden" (/indoor-partner/)
 *
 * Typspezifische Schwester der gebündelten Partner-Seite (Julius, 28.08.,
 * Punkt 6). Kernargument: Firmenkunden kommen unter der Woche und tagsüber,
 * genau dann haben Simulatoren freie Kapazität. Indoor ist Winter- und
 * Ganzjahresangebot, KEIN Schlechtwetter-Wording (Entscheidung 5).
 * CTA deeplinkt mit ?ob_type=indoor. Nicht in der Haupt-Nav.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$img            = static fn( string $n ): string => fge_get_placeholder_image_url( $n );
$url_onboarding = add_query_arg( 'ob_type', 'indoor', home_url( '/partner-onboarding/' ) );
$url_faq        = ( $p = get_page_by_path( 'partner-faq' ) ) ? (string) get_permalink( $p->ID ) : home_url( '/partner-faq/' );
$c              = fge_company();
$arrow_right    = '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg>';

$indoor_faq = [
	[ 'Was kostet uns die Partnerschaft?', 'Nichts. Ihr bekommt genau euren angegebenen Preis, die Vermittlungsprovision zahlt der Kunde obendrauf. Kein Setup-Preis, keine Gebühren, keine Bindung.' ],
	[ 'Unsere Anlage läuft teilweise unbemannt, geht das?', 'Ja. Ihr gebt im Profil an, wie Betreuung bei Firmenevents läuft, inklusive, gegen Aufpreis oder gar nicht. Firmen sehen vorab, was sie erwartet.' ],
	[ 'Wie viele Boxen brauchen wir mindestens?', 'Es gibt kein Minimum. Schon mit zwei Boxen funktionieren After-Work-Formate, mit vier und mehr laufen Turniere mit Rotation.' ],
	[ 'Müssen wir feste Slots freihalten?', 'Nein. Jede Anfrage kommt mit Wunschterminen, ihr bestätigt nur, was in euren Betrieb passt. Exklusivbuchungen legt ihr selbst fest.' ],
	[ 'Unser Golfclub hat einen Indoor-Bereich, brauchen wir ein zweites Profil?', 'Nein. Golfclubs, die schon Partner sind, pflegen Indoor direkt in ihrem Partnerportal im Reiter Indoor-Golf und legen dort Indoor-Angebote an.' ],
];
?>
<div class="fge-page" id="fge-main" role="main" tabindex="-1">

<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => '' ] ); ?>

<section class="mk-hero" aria-label="Indoor-Partner werden">
	<div class="mk-hero-photo" style="background-image:url('<?php echo esc_url( $img( 'buerodach-golfplatz.jpg' ) ); ?>')">
		<div class="mk-hero-scrim" aria-hidden="true"></div>
		<div class="mk-hero-content">
			<div class="mk-hero-eyebrow">Für Indoor-Golfanlagen &amp; Simulator-Lounges</div>
			<h1 class="mk-hero-title">
				<span class="mk-hero-lead">Macht eure Anlage zur</span>
				<span class="mk-hero-lead">Eventlocation für Unternehmen.</span>
			</h1>
			<p class="mk-hero-sub">
				Firmenkunden kommen unter der Woche und tagsüber, genau dann, wenn eure Boxen
				sonst frei sind. Ihr bekommt euren vollen Preis, die Provision zahlt der Kunde.
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

<section class="mk-section cty-reveal" aria-label="Warum Firmengolf für Indoor-Anlagen">
	<div class="mk-section-head">
		<div class="mk-eyebrow">Warum das für euch funktioniert</div>
		<h2 class="mk-h2">Firmenkunden füllen die Stunden, die sonst <em class="mk-italic">leer</em> laufen.</h2>
		<p class="mk-sub">Dienstag, 15 Uhr: für Privatkunden tote Zeit, für Firmenevents die Lieblingszeit.</p>
	</div>
	<div class="gp-aud-grid">
		<?php
		$indoor_points = [
			[ 't' => 'Auslastung unter der Woche', 'sub' => 'Teamevents, After-Work und Kundenabende finden werktags statt. Genau die Zeiten, in denen Simulatoren freie Kapazität haben.' ],
			[ 't' => 'Euer Ganzjahresgeschäft', 'sub' => 'Golf geht auch im Januar. Indoor ist ein eigenständiges Winter- und Ganzjahresangebot, mit Hochsaison, wenn draußen Nebensaison ist.' ],
			[ 't' => 'Das Leaderboard verkauft', 'sub' => 'Simulator-Turnier mit Live-Leaderboard über alle Boxen, Longest Drive, Closest to Pin: Formate, die aus Boxen-Miete ein Firmenevent machen.' ],
		];
		foreach ( $indoor_points as $pt ) : ?>
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
		<h2 class="mk-h2">In drei Schritten zum ersten <em class="mk-italic">Firmenevent</em>.</h2>
	</div>
	<div class="gp-aud-grid">
		<?php
		$indoor_steps = [
			[ 't' => '1 · Anlage anlegen', 'sub' => 'Etwa zehn Minuten: Boxen, Systeme, Räume, Gastronomie, Öffnungszeiten. Alles, was eure Anlage für Firmengruppen kann.' ],
			[ 't' => '2 · Angebote schnüren', 'sub' => 'Vom After-Work an der Box bis zur Weihnachtsfeier mit Turnier. Ihr legt Netto-Preise fest, pro Box und Stunde oder als Pauschale.' ],
			[ 't' => '3 · Anfragen bestätigen', 'sub' => 'Jede Anfrage kommt mit Wunschterminen, ihr bestätigt, was passt. Nach dem Event rechnet ihr direkt mit Firmengolf ab.' ],
		];
		foreach ( $indoor_steps as $st ) : ?>
			<div class="gp-aud">
				<h3 class="gp-aud-t"><?php echo esc_html( $st['t'] ); ?></h3>
				<p class="gp-aud-sub"><?php echo esc_html( $st['sub'] ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>
</section>

<section class="mk-section cty-reveal" aria-label="Formate">
	<div class="mk-section-head">
		<div class="mk-eyebrow">Eure Möglichkeiten</div>
		<h2 class="mk-h2">Was ihr anbieten <em class="mk-italic">könnt</em>.</h2>
		<p class="mk-sub">Ein Auszug aus den Formaten, die Unternehmen bei Indoor-Anlagen anfragen. Ihr wählt aus, was zu eurer Anlage passt, und legt eigene Pakete an.</p>
	</div>
	<div class="gp-pack-chips">
		<?php foreach ( array_values( fge_catalog_indoor_formats() ) as $chip ) : ?>
			<span class="gp-pack-chip"><?php echo esc_html( $chip ); ?></span>
		<?php endforeach; ?>
	</div>
</section>

<section class="mk-section cty-reveal" aria-label="Häufige Fragen">
	<div class="mk-section-head">
		<div class="mk-eyebrow">Häufige Fragen</div>
		<h2 class="mk-h2">Die Fragen, die uns Indoor-Betreiber zuerst stellen.</h2>
	</div>
	<?php
	get_template_part( 'template-parts/fge-faq', null, [
		'items' => array_map( static fn( $f ) => [ 'q' => $f[0], 'a' => $f[1] ], $indoor_faq ),
	] );
	?>
	<p style="margin-top:22px;">
		<a class="fg-btn-ghost" href="<?php echo esc_url( $url_faq ); ?>">Alle Fragen in der Partner-FAQ <?php echo $arrow_right; // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
	</p>
</section>

<section class="mk-cta" aria-label="Partner werden">
	<div class="mk-cta-inner">
		<div class="mk-eyebrow" style="color:rgba(251,250,246,0.65)">Kostenlos &amp; unverbindlich</div>
		<h2 class="mk-cta-h">Eure Boxen können diese Woche noch <em class="mk-italic">buchbar</em> sein.</h2>
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
