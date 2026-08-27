<?php
/**
 * Template: Partner-Landingpage „Partner werden" (/golfplatz-partner/)
 *
 * Neu ausgerichtet am 2026-08-28 (Julius): spricht jetzt DREI Zielgruppen an,
 * Golfplätze, Golflehrer und Betreiber von Indoor-Golfanlagen. Kernbotschaften:
 * eigene Pakete frei schnüren (unlimitiert, einmal angelegt, immer anfragbar),
 * keine festen Termine (Anfrage-Prinzip mit Verfügbarkeitsprüfung) und kein
 * internes Ping-Pong (alle Beteiligten inkl. Drittanbieter geben pro Anfrage
 * selbst frei). Sektion 2 (Fakten-Boxen) bewusst unverändert gelassen.
 * Bewusst NICHT in der Haupt-Nav; Einstiege: Footer, Portal-Login, Partner-FAQ.
 * Onboarding-Split nach Zielgruppe folgt separat (alle CTAs zeigen aufs
 * bestehende Onboarding).
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
$check_svg      = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>';

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

// ── Die drei Zielgruppen mit ihren spezifischen Argumenten ────────────────────
$gp_groups = [
	[
		'ic'  => '<path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
		't'    => 'Golfplätze',
		'type' => 'course',
		'sub' => 'Zusatzumsatz an den Tagen, an denen der Platz Luft hat.',
		'pts' => [
			'Firmengruppen kommen meist dienstags bis donnerstags, genau dann, wenn Startzeiten frei sind.',
			'10 bis 100 Personen pro Event, lange im Voraus angefragt und sauber koordiniert.',
			'Gastronomie, Pro und Meetingraum verkauft ihr gleich mit, alles in einem Paket.',
		],
	],
	[
		'ic'  => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/>',
		't'    => 'Golflehrer',
		'type' => 'coach',
		'sub' => 'Firmenkurse als planbare Aufträge, ohne eigene Akquise.',
		'pts' => [
			'Grundlagenkurse, Platzreifekurse und Event-Coaching für ganze Teams statt einzelner Schnupperstunden.',
			'Du hinterlegst deine Leistungen und Konditionen einmal, wir bringen dir die Gruppen.',
			'Ob am Heimatplatz, mobil oder im Studio: du entscheidest, wo du unterrichtest.',
		],
	],
	[
		'ic'  => '<rect x="3" y="4" width="18" height="12" rx="1.5"/><path d="M12 16v4M8 20h8"/><path d="M8 12l2.5-3 2 2L15 8"/>',
		't'    => 'Indoor-Golfanlagen',
		'type' => 'indoor',
		'sub' => 'Firmenkunden füllen genau die Stunden, die sonst leer laufen.',
		'pts' => [
			'Firmenevents finden unter der Woche und tagsüber statt, genau dann haben die meisten Simulatoren freie Kapazität.',
			'Ganzjährig und wetterfest: euer Angebot läuft auch durch, wenn draußen Saisonpause ist.',
			'Vom After-Work an der Box bis zur Firmen-Liga: legt an, was zu eurer Anlage passt.',
		],
	],
];

// ── Beispiel-Bausteine für die Paket-Sektion (frei erfunden erlaubt: es sind
//    Beispiele dafür, WAS Partner anlegen können, keine Bestandsangebote) ─────
$gp_pack_chips = [
	'Schlägerbau-Workshop', 'After-Work an der Range', '9-Loch-Turnier', 'Platzreife-Wochenende',
	'Simulator-Liga', 'Putting-Challenge', 'Mit Verpflegung', 'Ohne Verpflegung',
	'Einfach', 'Gehoben', 'Mit Meetingraum', 'Trackman-Coaching',
];

// Kernfragen, jetzt für alle drei Zielgruppen („kostenlos" bleibt zuerst).
$faq_teaser = [
	[ 'Was kostet die Partnerschaft?', 'Für euch nichts. Ihr bekommt genau euren angegebenen Preis, die Vermittlungsprovision zahlt der Kunde obendrauf. Kein Setup-Preis, keine Gebühren.' ],
	[ 'Vergebt ihr feste Termine für uns?', 'Nein. Jedes Angebot wird mit Wunschterminen angefragt, und ihr prüft die Verfügbarkeit. Nichts wird gebucht, bevor ihr nicht freigegeben habt.' ],
	[ 'Ich bin Golflehrer ohne eigenen Platz, kann ich mitmachen?', 'Ja. Du legst deine Kurse und Konditionen an und entscheidest selbst, wo du unterrichtest, am Heimatplatz, mobil beim Kunden oder in einer Partner-Anlage.' ],
	[ 'Wir betreiben eine Indoor-Anlage, passt das?', 'Sehr gut sogar. Firmengruppen kommen bevorzugt unter der Woche und tagsüber, also genau in den Zeiten, in denen Boxen frei sind. Ihr legt einfach an, was eure Anlage hergibt.' ],
	[ 'Binde ich mich langfristig?', 'Nein, keine langfristige Bindung, faire, kurze Konditionen. Pausieren ist jederzeit möglich.' ],
	[ 'Muss ich exklusiv mit Firmengolf arbeiten?', 'Nein, keine Exklusivität. Ihr vermarktet euer Angebot weiter, wie ihr wollt.' ],
	[ 'Wie schnell bin ich live?', 'In der Regel wenige Werktage nach vollständigem Profil, das Onboarding selbst dauert etwa zehn Minuten.' ],
];
?>
<div class="fge-page" id="fge-main" role="main" tabindex="-1">

<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => '' ] ); ?>

<?php /* ══════════════════ 1. HERO ══════════════════ */ ?>
<section class="mk-hero" aria-label="Partner werden">
	<div class="mk-hero-photo" style="background-image:url('<?php echo esc_url( $img( 'buerodach-golfplatz.jpg' ) ); ?>')">
		<div class="mk-hero-scrim" aria-hidden="true"></div>
		<div class="mk-hero-content">
			<div class="mk-hero-eyebrow">Partner werden · Golfplätze, Golflehrer &amp; Indoor-Anlagen</div>
			<h1 class="mk-hero-title">
				<span class="mk-hero-lead">Firmenkunden für euer Golf-Angebot.</span>
				<span class="mk-hero-lead">Ohne Vertrieb, ohne Fixkosten.</span>
			</h1>
			<p class="mk-hero-sub">
				Firmengolf bringt Unternehmen zu euch: Teamevents, Turniere, Kurse, Simulator-Abende.
				Ihr bekommt euren vollen Preis, die Provision zahlt der Kunde.
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

<?php /* ══════════════════ 2. FAKTEN (bewusst unverändert, Julius 28.08.) ══════════════════ */ ?>
<div class="home-facts gp-facts" aria-label="Firmengolf für Partner in Zahlen">
	<?php
	$facts = [
		[ 'ic' => '<path d="M5 21V4l9 2.5L5 9"/><circle cx="17" cy="17" r="3"/>',
		  't' => (string) $partner_count, 'b' => 'Partnerplätze von München bis Hamburg' ],
		[ 'ic' => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M8 2v4M16 2v4M3 10h18"/>',
		  't' => (string) $event_count, 'b' => 'buchbare Event-Angebote' ],
		[ 'ic' => '<circle cx="12" cy="12" r="9"/><path d="M9 12l2 2 4-4"/>',
		  't' => '0 € Fixkosten', 'b' => 'Provision zahlt der Kunde' ],
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

<?php /* ══════════════════ 3. DIE DREI ZIELGRUPPEN ══════════════════ */ ?>
<section class="mk-section cty-reveal" aria-label="Für wen Firmengolf gebaut ist">
	<div class="mk-section-head">
		<div class="mk-eyebrow">Für wen das gebaut ist</div>
		<h2 class="mk-h2">Drei Wege, mit Firmenkunden zu <em class="mk-italic">verdienen</em>.</h2>
		<p class="mk-sub">Ob 18 Löcher, Trainerstunden oder Simulator-Boxen: Unternehmen suchen genau euer Angebot, sie wissen nur noch nicht, dass es euch gibt.</p>
	</div>
	<div class="gp-aud-grid">
		<?php foreach ( $gp_groups as $g ) : ?>
			<div class="gp-aud">
				<span class="gp-aud-ic" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><?php echo $g['ic']; // phpcs:ignore WordPress.Security.EscapeOutput ?></svg></span>
				<h3 class="gp-aud-t"><?php echo esc_html( $g['t'] ); ?></h3>
				<p class="gp-aud-sub"><?php echo esc_html( $g['sub'] ); ?></p>
				<ul class="gp-aud-pts">
					<?php foreach ( $g['pts'] as $pt ) : ?>
						<li><?php echo $check_svg; // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php echo esc_html( $pt ); ?></span></li>
					<?php endforeach; ?>
				</ul>
				<?php // Deeplink je Zielgruppe: startet den passenden Wizard ohne Typ-Wahl (Plan 8b). ?>
			<a class="gp-aud-cta" href="<?php echo esc_url( add_query_arg( 'ob_type', $g['type'] ?? 'course', $url_onboarding ) ); ?>">Jetzt Partner werden <?php echo $arrow_right; // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
			</div>
		<?php endforeach; ?>
	</div>
</section>

<?php /* ══════════════════ 4. EIGENE PAKETE SCHNÜREN ══════════════════ */ ?>
<section class="mk-section mk-band cty-reveal" aria-label="Eigene Pakete schnüren">
	<div class="gp-pack-grid">
		<div class="gp-pack-text">
			<div class="mk-eyebrow">Euer Angebot, eure Regeln</div>
			<h2 class="mk-h2">Schnürt euer Paket so, wie ihr es euch <em class="mk-italic">vorstellt</em>.</h2>
			<p class="mk-sub">
				Vom Schlägerbau-Workshop bis zum Platzreife-Wochenende, mit oder ohne Verpflegung,
				einfach oder gehoben: Ihr legt an, was zu euch passt, so viele Angebote ihr wollt.
				Einmal angelegt, kann jedes Paket jederzeit von Unternehmen angefragt werden.
			</p>
			<ul class="gp-pack-pts">
				<li><?php echo $check_svg; // phpcs:ignore WordPress.Security.EscapeOutput ?><span>Unbegrenzt viele Pakete, komplett frei gestaltet und bepreist</span></li>
				<li><?php echo $check_svg; // phpcs:ignore WordPress.Security.EscapeOutput ?><span>Änderungen und Pausieren jederzeit im Partnerportal</span></li>
				<li><?php echo $check_svg; // phpcs:ignore WordPress.Security.EscapeOutput ?><span>Ihr bekommt euren vollen Preis, die Provision zahlt der Kunde</span></li>
			</ul>
			<a class="fg-btn-brand" href="<?php echo esc_url( $url_onboarding ); ?>">Erstes Paket anlegen</a>
		</div>
		<div class="gp-pack-chips" aria-hidden="true">
			<?php foreach ( $gp_pack_chips as $chip ) : ?>
				<span class="gp-pack-chip"><?php echo esc_html( $chip ); ?></span>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<?php /* ══════════════════ 5. TERMIN-PRINZIP: ANGEFRAGT STATT VERPLANT ══════════════════ */ ?>
<section class="mk-section cty-reveal" aria-label="So laufen Termine">
	<div class="mk-section-head">
		<div class="mk-eyebrow">Keine festen Termine, kein Terminstress</div>
		<h2 class="mk-h2">Jede Veranstaltung wird <em class="mk-italic">angefragt</em>, nichts wird über euren Kopf hinweg gebucht.</h2>
		<p class="mk-sub">Ihr blockt keine Startzeiten auf Verdacht. Unternehmen fragen mit Wunschterminen an, und ihr prüft die Verfügbarkeit, pro Anfrage, mit einem Klick.</p>
	</div>
	<div class="mk-steps-grid">
		<?php
		$termin_steps = [
			[ '01', 'Anfrage kommt mit Wunschterminen.', 'Das Unternehmen nennt bis zu drei Termine. Ihr seht Gruppe, Paket und Zeitraum auf einen Blick im Portal und per E-Mail.' ],
			[ '02', 'Jeder gibt nur seinen Teil frei.', 'Pro, Gastronomie, Sekretariat, auch externe Dienstleister: Alle Beteiligten können als Ansprechpartner hinterlegt werden und bestätigen selbst, welcher Termin bei ihnen passt.' ],
			[ '03', 'Kein internes Ping-Pong.', 'Niemand muss allen hinterhertelefonieren. Aus den Freigaben entsteht der Termin, der für alle passt, und das Unternehmen bekommt schnell eine verbindliche Zusage.' ],
		];
		foreach ( $termin_steps as $step ) : ?>
			<div class="mk-step">
				<div class="mk-step-n"><?php echo esc_html( $step[0] ); ?></div>
				<h3 class="mk-step-t"><?php echo esc_html( $step[1] ); ?></h3>
				<p class="mk-step-b"><?php echo esc_html( $step[2] ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>
</section>

<?php /* ══════════════════ 6. ANSPRECHPARTNER ══════════════════ */ ?>
<section class="mk-section mk-band cty-reveal" aria-label="Dein Ansprechpartner">
	<div class="gp-contact">
		<div class="gp-contact-portrait" role="img" aria-label="Julius Klinzer, Gründer von Firmengolf" style="background-image:url('<?php echo esc_url( $img( 'gruender-julius-klinzer.jpg' ) ); ?>')"></div>
		<div class="gp-contact-body">
			<div class="mk-eyebrow">Dein Ansprechpartner</div>
			<h2 class="mk-h2" style="font-size:clamp(26px,3.2vw,34px);">„Ruf mich einfach an, ich zeige dir in zehn Minuten, wie es für euch aussieht."</h2>
			<p class="mk-sub" style="margin-top:10px;">Julius Klinzer, Gründer von Firmengolf. Kein Callcenter, kein Vertriebsteam, du sprichst direkt mit dem, der die Plattform gebaut hat.</p>
			<div class="gp-contact-ctas">
				<a class="fg-btn-cta" href="tel:<?php echo esc_attr( $c['phone_tel'] ); ?>"><?php echo esc_html( $c['phone_display'] ); ?></a>
				<a class="fg-btn-ghost" href="mailto:<?php echo esc_attr( $c['email_partner'] ); ?>"><?php echo esc_html( $c['email_partner'] ); ?></a>
			</div>
		</div>
	</div>
</section>

<?php /* ══════════════════ 7. FAQ-TEASER ══════════════════ */ ?>
<section class="mk-section cty-reveal" aria-label="Häufige Fragen">
	<div class="mk-section-head">
		<div class="mk-eyebrow">Häufige Fragen</div>
		<h2 class="mk-h2">Die Fragen, die uns Partner zuerst stellen.</h2>
	</div>
	<?php
	get_template_part( 'template-parts/fge-faq', null, [
		'items' => array_map( static fn( $f ) => [ 'q' => $f[0], 'a' => $f[1] ], $faq_teaser ),
	] );
	?>
	<p style="margin-top:22px;">
		<a class="fg-btn-ghost" href="<?php echo esc_url( $url_faq ); ?>">Alle Fragen in der Partner-FAQ <?php echo $arrow_right; // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
	</p>
</section>

<?php /* ══════════════════ 8. ABSCHLUSS-CTA ══════════════════ */ ?>
<section class="mk-cta" aria-label="Partner werden">
	<div class="mk-cta-inner">
		<div class="mk-eyebrow" style="color:rgba(251,250,246,0.65)">Kostenlos &amp; unverbindlich</div>
		<h2 class="mk-cta-h">Euer Angebot kann diese Woche noch <em class="mk-italic">live</em> sein.</h2>
		<div class="mk-cta-ctas">
			<a class="fg-btn-ink fg-btn-lg" href="<?php echo esc_url( $url_onboarding ); ?>" style="background:var(--paper-100);color:var(--fairway-900)">Jetzt Partner werden</a>
			<a class="mk-cta-mail" href="mailto:<?php echo esc_attr( $c['email_partner'] ); ?>"><?php echo esc_html( $c['email_partner'] ); ?></a>
		</div>
	</div>
</section>

<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>

<script>
/* Sanftes Einblenden der Sektionen (gleiches Muster wie Startseite/City-Seiten):
   greift nur unter html.cty-io, ohne JS oder mit reduzierter Bewegung ist alles sofort sichtbar. */
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
/* FAQ-Toggle kommt aus der globalen Komponente (template-parts/fge-faq.php). */
</script>

<?php get_footer();
