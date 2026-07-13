<?php
/**
 * Template: Partner-Landingpage „Für Golfplätze" (/golfplatz-partner/)
 *
 * Verkaufsseite für den Akquise-Funnel (Funnel-Audit 2026-07-12, Paket A):
 * Erst-Landing für kalt angesprochene Club-Manager (Mail/LinkedIn). Bewusst
 * NICHT in der Haupt-Nav (die bleibt kundenseitig, Julius) — Einstiege sind
 * Footer, Partnerportal-Login und die Partner-FAQ. Baut komplett auf dem
 * mk-*-Designsystem der Startseite auf, Zahlen sind echte Live-Werte.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$img            = static fn( string $n ): string => fge_get_placeholder_image_url( $n );
$url_onboarding = home_url( '/partner-onboarding/' );
$url_faq        = ( $p = get_page_by_path( 'partner-faq' ) ) ? (string) get_permalink( $p->ID ) : home_url( '/partner-faq/' );
$c              = fge_company();
$arrow_right    = '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg>';

// Echte Zahlen statt Marketing-Behauptungen (Prozess-Audit #4: keine Fakes).
$partner_count = (int) wp_count_posts( 'firmengolf_partner' )->publish;
$event_ids     = get_posts( [
	'post_type'     => 'firmengolf_event',
	'post_status'   => 'publish',
	'numberposts'   => -1,
	'fields'        => 'ids',
	'no_found_rows' => true,
] );
$event_count   = function_exists( 'fge_event_is_public' )
	? count( array_filter( $event_ids, 'fge_event_is_public' ) )
	: count( $event_ids );

// Kernfragen aus der Partner-FAQ, „kostenlos" zuerst (Audit: stand zugeklappt auf Platz 2).
$faq_teaser = [
	[ 'Was kostet die Partnerschaft?', 'Nichts. Kein Setup-Preis, keine Gebühren — wir arbeiten provisionsbasiert, ihr zahlt nur, wenn über uns gebucht wird.' ],
	[ 'Binde ich mich langfristig?', 'Nein, keine langfristige Bindung, faire, kurze Konditionen. Pausieren ist jederzeit möglich.' ],
	[ 'Muss ich exklusiv mit Firmengolf arbeiten?', 'Nein, keine Exklusivität. Ihr vermarktet euren Platz weiter, wie ihr wollt.' ],
	[ 'Wie schnell ist mein Platz live?', 'In der Regel wenige Werktage nach vollständigem Profil — das Onboarding selbst dauert etwa zehn Minuten.' ],
];
?>
<div class="fge-page" id="fge-main" role="main" tabindex="-1">

<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => '' ] ); ?>

<?php /* ══════════════════ 1. HERO ══════════════════ */ ?>
<section class="mk-hero" aria-label="Für Golfplätze">
	<?php // Bürodach-Golfgrün zwischen Bürotürmen (Julius, 2026-07-13): Motiv verbindet Firmenwelt + Golf. ?>
	<div class="mk-hero-photo" style="background-image:url('<?php echo esc_url( $img( 'buerodach-golfplatz.jpg' ) ); ?>')">
		<div class="mk-hero-scrim" aria-hidden="true"></div>
		<div class="mk-hero-content">
			<div class="mk-hero-eyebrow">Für Golfplätze · Partner werden</div>
			<h1 class="mk-hero-title">
				<span class="mk-hero-lead">Firmenkunden für euren Platz.</span>
				<span class="mk-hero-lead">Ohne Vertrieb, ohne Fixkosten.</span>
			</h1>
			<p class="mk-hero-sub">
				Firmengolf bringt Unternehmen auf Golfplätze: Teamevents, Turniere, Platzreife-Kurse.
				Kein Setup-Preis, provisionsbasiert — ihr zahlt nur, wenn über uns gebucht wird.
			</p>
			<div class="mk-hero-ctas">
				<a class="fg-btn-cta fg-btn-lg" href="<?php echo esc_url( $url_onboarding ); ?>">
					Platz kostenlos anbieten
					<span class="fg-arrow"><?php echo $arrow_right; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				</a>
				<a class="fg-btn-ghost-light" href="<?php echo esc_url( $url_faq ); ?>">
					Erst Fragen klären →
				</a>
			</div>
		</div>
	</div>
</section>

<?php /* ══════════════════ 2. FAKTEN ══════════════════ */ ?>
<div class="home-facts" aria-label="Firmengolf für Plätze in Zahlen">
	<?php
	$facts = [
		[ 'ic' => '<path d="M5 21V4l9 2.5L5 9"/><circle cx="17" cy="17" r="3"/>',
		  't' => (string) $partner_count, 'b' => 'Partnerplätze von München bis Hamburg' ],
		[ 'ic' => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M8 2v4M16 2v4M3 10h18"/>',
		  't' => (string) $event_count, 'b' => 'buchbare Event-Angebote' ],
		[ 'ic' => '<circle cx="12" cy="12" r="9"/><path d="M9 12l2 2 4-4"/>',
		  't' => '0 € Fixkosten', 'b' => 'rein provisionsbasiert' ],
		[ 'ic' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		  't' => '1 Werktag', 'b' => 'Antwort auf jede Frage' ],
	];
	foreach ( $facts as $f ) : ?>
		<div class="home-fact">
			<span class="home-fact-ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><?php echo $f['ic']; // phpcs:ignore WordPress.Security.EscapeOutput -- statische SVGs ?></svg></span>
			<div class="home-fact-txt">
				<div class="home-fact-t"><?php echo esc_html( $f['t'] ); ?></div>
				<div class="home-fact-b"><?php echo esc_html( $f['b'] ); ?></div>
			</div>
		</div>
	<?php endforeach; ?>
</div>

<?php /* ══════════════════ 3. SO FUNKTIONIERT'S FÜR PLÄTZE ══════════════════ */ ?>
<section class="mk-section mk-steps mk-band" aria-label="So werdet ihr Partner">
	<div class="mk-section-head">
		<div class="mk-eyebrow">So funktioniert's</div>
		<h2 class="mk-h2">Drei Schritte bis zur ersten Anfrage.</h2>
		<p class="mk-sub">Ihr braucht keine Software und keinen eigenen Vertrieb — nur einen Platz und einen Ansprechpartner.</p>
	</div>
	<div class="mk-steps-grid">
		<?php
		$steps = [
			[ '01', 'Profil anlegen.',            'Etwa zehn Minuten im Onboarding: Platz, Leistungen, Fotos. Unverbindlich, jederzeit speicher- und fortsetzbar.' ],
			[ '02', 'Wir prüfen, ihr geht live.',  'Freischaltung in wenigen Werktagen. Öffentlich sichtbar werdet ihr mit eurem ersten Event-Angebot — das ist in einer Minute angelegt.' ],
			[ '03', 'Anfragen entscheiden.',       'Passende Firmenanfragen landen im Portal und per E-Mail. Ihr entscheidet pro Anfrage — die Kundenkommunikation übernehmen wir.' ],
		];
		foreach ( $steps as $step ) : ?>
			<div class="mk-step">
				<div class="mk-step-n"><?php echo esc_html( $step[0] ); ?></div>
				<h3 class="mk-step-t"><?php echo esc_html( $step[1] ); ?></h3>
				<p class="mk-step-b"><?php echo esc_html( $step[2] ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>
</section>

<?php /* ══════════════════ 4. WAS IHR DAVON HABT ══════════════════ */ ?>
<section class="mk-section" aria-label="Vorteile für euren Platz">
	<div class="mk-section-head">
		<div class="mk-eyebrow">Warum es sich lohnt</div>
		<h2 class="mk-h2">Zusatzumsatz an den Tagen, an denen der Platz <em class="mk-italic">Luft</em> hat.</h2>
	</div>
	<div class="mk-steps-grid">
		<?php
		$benefits = [
			[ 'Planbare Gruppen unter der Woche', 'Firmenevents finden meist dienstags bis donnerstags statt — genau dann, wenn Startzeiten frei sind. Gruppen von 10 bis 100 Personen, lange im Voraus geplant.' ],
			[ 'Ihr behaltet die Kontrolle', 'Eure Preise, eure Verfügbarkeit, eure Entscheidung pro Anfrage. Keine Exklusivität, keine Laufzeit, pausieren jederzeit möglich.' ],
			[ 'Wir übernehmen den Rest', 'Anfragen bündeln, Termine mit Platz, Pro und Gastro abstimmen, Kunden betreuen, eine saubere Abrechnung pro Event. Ihr konzentriert euch auf den Platz.' ],
		];
		foreach ( $benefits as $b ) : ?>
			<div class="mk-step">
				<h3 class="mk-step-t" style="font-size:22px;"><?php echo esc_html( $b[0] ); ?></h3>
				<p class="mk-step-b"><?php echo esc_html( $b[1] ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>
</section>

<?php /* ══════════════════ 5. ANSPRECHPARTNER ══════════════════ */ ?>
<section class="mk-section mk-band" aria-label="Dein Ansprechpartner">
	<div class="gp-contact">
		<div class="gp-contact-portrait" role="img" aria-label="Julius Klinzer, Gründer von Firmengolf" style="background-image:url('<?php echo esc_url( $img( 'gruender-julius-klinzer.jpg' ) ); ?>')"></div>
		<div class="gp-contact-body">
			<div class="mk-eyebrow">Dein Ansprechpartner</div>
			<h2 class="mk-h2" style="font-size:clamp(26px,3.2vw,34px);">„Ruf mich einfach an — ich zeige dir in zehn Minuten, wie es für euren Platz aussieht."</h2>
			<p class="mk-sub" style="margin-top:10px;">Julius Klinzer, Gründer von Firmengolf. Kein Callcenter, kein Vertriebsteam — du sprichst direkt mit dem, der die Plattform gebaut hat.</p>
			<div class="gp-contact-ctas">
				<a class="fg-btn-cta" href="tel:<?php echo esc_attr( $c['phone_tel'] ); ?>"><?php echo esc_html( $c['phone_display'] ); ?></a>
				<a class="fg-btn-ghost" href="mailto:<?php echo esc_attr( $c['email_partner'] ); ?>"><?php echo esc_html( $c['email_partner'] ); ?></a>
			</div>
		</div>
	</div>
</section>

<?php /* ══════════════════ 6. FAQ-TEASER ══════════════════ */ ?>
<section class="mk-section" aria-label="Häufige Fragen">
	<div class="mk-section-head">
		<div class="mk-eyebrow">Häufige Fragen</div>
		<h2 class="mk-h2">Die vier Fragen, die jeder Platz zuerst stellt.</h2>
	</div>
	<ul class="faq-list" style="margin-top:24px;">
		<?php foreach ( $faq_teaser as $faq ) : ?>
			<li class="faq-item">
				<button class="faq-q" type="button" aria-expanded="false">
					<span><?php echo esc_html( $faq[0] ); ?></span>
					<span class="faq-toggle" aria-hidden="true">+</span>
				</button>
				<div class="faq-a"><?php echo esc_html( $faq[1] ); ?></div>
			</li>
		<?php endforeach; ?>
	</ul>
	<p style="margin-top:22px;">
		<a class="fg-btn-ghost" href="<?php echo esc_url( $url_faq ); ?>">Alle 20 Fragen in der Partner-FAQ <?php echo $arrow_right; // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
	</p>
</section>

<?php /* ══════════════════ 7. ABSCHLUSS-CTA ══════════════════ */ ?>
<section class="mk-cta" aria-label="Partner werden">
	<div class="mk-cta-inner">
		<div class="mk-eyebrow" style="color:rgba(251,250,246,0.65)">Kostenlos &amp; unverbindlich</div>
		<h2 class="mk-cta-h">Euer Platz kann diese Woche noch <em class="mk-italic">live</em> sein.</h2>
		<div class="mk-cta-ctas">
			<a class="fg-btn-ink fg-btn-lg" href="<?php echo esc_url( $url_onboarding ); ?>" style="background:var(--paper-100);color:var(--fairway-900)">Platz anbieten</a>
			<a class="mk-cta-mail" href="mailto:<?php echo esc_attr( $c['email_partner'] ); ?>"><?php echo esc_html( $c['email_partner'] ); ?></a>
		</div>
	</div>
</section>

<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>

<style>
/* Ansprechpartner-Block: einziges Layout dieser Seite ohne fertige mk-Klasse. */
.gp-contact { display: grid; grid-template-columns: 280px minmax(0, 1fr); gap: 40px; align-items: center; max-width: 960px; margin: 0 auto; }
.gp-contact-portrait { width: 280px; aspect-ratio: 4 / 5; border-radius: 20px; background: var(--ink-200) center/cover no-repeat; box-shadow: var(--shadow-sm); }
.gp-contact-ctas { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 22px; }
@media (max-width: 720px) {
	.gp-contact { grid-template-columns: 1fr; gap: 24px; }
	.gp-contact-portrait { width: min(280px, 70vw); margin: 0 auto; }
	.gp-contact-body { text-align: center; }
	.gp-contact-ctas { justify-content: center; }
}
</style>

<script>
document.querySelectorAll('.fge-page .faq-q[aria-expanded]').forEach(function (btn) {
	btn.addEventListener('click', function () {
		var item = btn.closest('.faq-item');
		var open = item.classList.toggle('open');
		btn.setAttribute('aria-expanded', open ? 'true' : 'false');
		var tog = btn.querySelector('.faq-toggle');
		if (tog) tog.textContent = open ? '−' : '+';
	});
});
</script>

<?php get_footer();
