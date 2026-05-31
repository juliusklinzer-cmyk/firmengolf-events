<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Multi-step form handling ───────────────────────────────────────────────────
$ind_submitted = false;
$ind_errors    = [];
$ind_step      = 1;
$ind_data      = [
	'occasion'      => 'Sommerfest',
	'size'          => '',
	'when'          => '',
	'flex'          => 'flexibel',
	'duration'      => 'Halbtag',
	'region'        => 'Ganz Deutschland',
	'budget'        => '',
	'catering'      => '',
	'coaching'      => '',
	'overnight'     => '',
	'transport'     => '',
	'branding'      => '',
	'photo'         => '',
	'indoor'        => '',
	'accessibility' => '',
	'notes'         => '',
	'first_name'    => '',
	'last_name'     => '',
	'company'       => '',
	'role'          => '',
	'email'         => '',
	'phone'         => '',
	'contact_pref'  => 'Mail',
	'referral'      => '',
];

if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['ind_step'] ) ) {
	$posted_step = (int) $_POST['ind_step'];

	// Restore all data from hidden fields
	foreach ( array_keys( $ind_data ) as $key ) {
		if ( isset( $_POST[ 'ind_' . $key ] ) ) {
			$ind_data[ $key ] = sanitize_text_field( wp_unslash( $_POST[ 'ind_' . $key ] ) );
		}
	}

	if ( $posted_step === 1 && check_admin_referer( 'fge_ind_step_1', 'fge_ind_nonce' ) ) {
		if ( empty( $ind_data['occasion'] ) || empty( $ind_data['size'] ) || empty( $ind_data['when'] ) ) {
			$ind_errors[] = 'Bitte fülle alle Pflichtfelder aus.';
			$ind_step = 1;
		} else {
			$ind_step = 2;
		}
	} elseif ( $posted_step === 2 && check_admin_referer( 'fge_ind_step_2', 'fge_ind_nonce' ) ) {
		$ind_step = 3;
	} elseif ( $posted_step === 3 && check_admin_referer( 'fge_ind_step_3', 'fge_ind_nonce' ) ) {
		if ( empty( $ind_data['first_name'] ) || empty( $ind_data['last_name'] ) || empty( $ind_data['company'] ) || empty( $ind_data['email'] ) || ! is_email( $ind_data['email'] ) ) {
			$ind_errors[] = 'Bitte fülle alle Pflichtfelder korrekt aus.';
			$ind_step = 3;
		} elseif ( ! isset( $_POST['ind_consent'] ) ) {
			$ind_errors[] = 'Bitte stimme der Verarbeitung deiner Daten zu.';
			$ind_step = 3;
		} else {
			// Generate reference number
			$ref_num = 'FG-26-' . wp_rand( 100000, 999999 );

			// Build extras list
			$extras = array_filter( [
				$ind_data['catering']     ? 'Catering'       : '',
				$ind_data['coaching']     ? 'Coaching'       : '',
				$ind_data['overnight']    ? 'Übernachtung'   : '',
				$ind_data['transport']    ? 'Transport'      : '',
				$ind_data['branding']     ? 'Branding'       : '',
				$ind_data['photo']        ? 'Fotografie'     : '',
				$ind_data['indoor']       ? 'Indoor-Backup'  : '',
				$ind_data['accessibility'] ? 'Barrierefrei'  : '',
			] );

			// Send notification email to admin
			$admin_email = get_option( 'admin_email' );
			$subject     = 'Neue Individualanfrage: ' . esc_html( $ind_data['first_name'] . ' ' . $ind_data['last_name'] ) . ' · ' . $ref_num;
			$body        = "Neue Individualanfrage über die Website.\n\n"
				. "Vorgangs-Nr.: $ref_num\n\n"
				. "Anlass:    {$ind_data['occasion']}\n"
				. "Gruppe:    {$ind_data['size']} Personen · {$ind_data['duration']}\n"
				. "Zeitraum:  {$ind_data['when']} · {$ind_data['flex']}\n"
				. "Region:    {$ind_data['region']}\n"
				. "Budget:    {$ind_data['budget']}\n"
				. "Extras:    " . ( $extras ? implode( ', ', $extras ) : '–' ) . "\n"
				. "Notizen:   {$ind_data['notes']}\n\n"
				. "Ansprechpartner:\n"
				. "{$ind_data['first_name']} {$ind_data['last_name']} · {$ind_data['company']} · {$ind_data['role']}\n"
				. "E-Mail: {$ind_data['email']}\n"
				. "Tel: {$ind_data['phone']}\n"
				. "Kontakt per: {$ind_data['contact_pref']}\n"
				. "Herkunft: {$ind_data['referral']}\n";
			wp_mail( $admin_email, $subject, $body );

			// Send confirmation to requester
			wp_mail(
				$ind_data['email'],
				'Deine Anfrage bei Firmengolf — ' . $ref_num,
				"Hallo {$ind_data['first_name']},\n\ndeine Anfrage ist bei uns eingegangen. Wir melden uns innerhalb eines Werktags zurück.\n\nVorgangs-Nr.: $ref_num\n\nBis gleich,\nDein Firmengolf-Team"
			);

			$ind_submitted = true;
			$ind_ref       = $ref_num;
		}
	} elseif ( isset( $_POST['ind_back'] ) && $posted_step > 1 ) {
		$ind_step = max( 1, $posted_step - 1 );
	} else {
		$ind_step = $posted_step ?: 1;
	}
}

$hero_img_url = fge_get_placeholder_image_url( 'golfplatz-meerblick.jpg' );
$events_url   = (string) get_post_type_archive_link( 'firmengolf_event' );
$blog_url     = home_url( '/blog/' );

$occasions = [
	'Sommerfest', 'Firmenturnier', 'Team-Building', 'Kundenevent',
	'Offsite', 'Incentive-Reise', 'Charity-Event', 'Gesundheitstag',
	'Hochzeit / Privat', 'Etwas anderes',
];
$flex_opts     = [ 'fix', '± 1 Woche', 'flexibel', 'noch offen' ];
$duration_opts = [ 'Vormittag', 'Nachmittag', 'Halbtag', 'Ganztag', '2 Tage', 'Mehrtägig' ];
$region_opts   = [ 'Ganz Deutschland', 'Nord', 'Ost', 'Süd', 'West', 'International' ];
$budget_opts   = [
	[ 'v' => 'Unter €5.000',      'h' => 'Kleinere Halbtags-Formate' ],
	[ 'v' => '€5.000 – €10.000',  'h' => 'Eintägig für 20–40 Gäste' ],
	[ 'v' => '€10.000 – €20.000', 'h' => 'Premium-Eintages-Events' ],
	[ 'v' => '€20.000 – €50.000', 'h' => 'Mehrtägig oder größere Gruppen' ],
	[ 'v' => 'Über €50.000',      'h' => 'Incentive-Reisen, Großformate' ],
	[ 'v' => 'Noch unklar',       'h' => 'Wir gehen es gemeinsam durch' ],
];
$contact_opts  = [ 'Mail', 'Telefon', 'Egal' ];

$faqs = [
	[
		'q' => 'Wie viel kostet ein individuelles Event?',
		'a' => 'Sehr unterschiedlich. Ein abendliches Sommerfest für 80 Personen liegt typischerweise bei €15.000–€25.000, ein zweitägiges Strategie-Offsite für 20 Personen bei €30.000–€50.000. Wir gehen das nach der Anfrage transparent durch und du bekommst ein vollständiges Angebot mit allen Posten.',
	],
	[
		'q' => 'Wie viel Vorlauf brauchen wir?',
		'a' => 'Idealerweise 3 Monate für mehrtägige Formate, 6–8 Wochen für eintägige. Kurzfristiger geht oft auch — das hängt vom Datum und der Region ab.',
	],
	[
		'q' => 'Können wir eigene Locations einbringen?',
		'a' => 'Ja. Wenn ihr eine Wunsch-Location habt, sprechen wir mit dem Platz und integrieren das ins Konzept. Wir arbeiten mit über 180 Plätzen direkt zusammen — und können neue Partner anfragen.',
	],
	[
		'q' => 'Was, wenn wir noch keinen festen Plan haben?',
		'a' => 'Genau dafür gibt es uns. Sag uns Anlass, ungefähre Gruppe und Region — wir schicken zwei bis drei sehr unterschiedliche Konzept-Vorschläge zurück, aus denen du wählst.',
	],
	[
		'q' => 'Kommt jemand von euch vor Ort?',
		'a' => 'Bei Events ab 30 Personen oder mehrtägigen Formaten — ja, immer. Ein Firmengolf-Hostess oder -Host ist vor Ort und stimmt sich mit dem Platz ab.',
	],
	[
		'q' => 'Was, wenn wir das Event verschieben müssen?',
		'a' => 'Bis 30 Tage vorher kostenlos. Danach gestaffelt — die genauen Konditionen schreiben wir in jedes Angebot rein. Wir sind kulant und finden Lösungen.',
	],
];

get_header();
?>
<div class="fge-page">

	<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => 'individuelle-events' ] ); ?>

	<?php /* ── Hero ── */ ?>
	<section class="ind-hero">
		<div class="ind-hero-photo" style="background-image:url('<?php echo esc_url( $hero_img_url ); ?>');">
			<div class="ind-hero-scrim"></div>
			<div class="ind-hero-content">
				<div class="mk-hero-eyebrow">Individuelle Events</div>
				<h1 class="ind-hero-title">
					Wir <em class="mk-italic">planen</em> dein Event nach deinen Ansprüchen.
				</h1>
				<p class="ind-hero-sub">
					Sonderwünsche, eigene Location, mehrtägiges Programm, internationale Gruppe —
					beschreib uns kurz, was du vorhast. Wir bauen das Format für euch.
				</p>
			</div>
		</div>
	</section>

	<?php /* ── Was wir planen können ── */ ?>
	<section class="mk-section" style="max-width:1280px;margin:0 auto;padding:80px 24px 0;">
		<div class="mk-section-head">
			<div class="mk-eyebrow">Was wir planen können</div>
			<h2 class="mk-h2">Vom Kunden-Abend bis zur dreitägigen Incentive-Reise.</h2>
			<p class="mk-sub">
				Manche Anlässe brauchen kein vorgefertigtes Format. Wenn du etwas Eigenes vorhast,
				arbeiten wir mit dir an Programm, Platz und Catering — und übernehmen die Koordination.
			</p>
		</div>
		<div class="ind-tiles">
			<?php
			$tiles = [
				[ 't' => 'Sommerfest mit 200 Gästen',        'b' => 'Mehrere Plätze, Shuttle, Live-Musik, Catering im Festzelt.' ],
				[ 't' => 'Mehrtägiges Strategie-Offsite',     'b' => 'Schloss-Hotels, Meeting-Räume, 9-Loch-Nachmittage.' ],
				[ 't' => 'Internationales Kunden-Wochenende', 'b' => 'Flughafen-Transfer, mehrsprachige Coaches, Dolmetscher.' ],
				[ 't' => 'Charity-Cup mit Sponsoren',          'b' => 'Spendentopf, Auktion, PR-Material, Fotograf.' ],
				[ 't' => 'Wellness- & Gesundheitstage',        'b' => 'BGM-konform, Physio, Mobility, gesunde Küche.' ],
				[ 't' => 'Hochzeit oder Geburtstag',           'b' => 'Eigene Feier auf einem Partnerplatz — wir koordinieren.' ],
			];
			foreach ( $tiles as $tile ) : ?>
				<div class="ind-tile">
					<div class="ind-tile-bar"><?php echo fge_icon_check(); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
					<h3 class="ind-tile-t"><?php echo esc_html( $tile['t'] ); ?></h3>
					<p class="ind-tile-b"><?php echo esc_html( $tile['b'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</section>

	<?php /* ── Process timeline ── */ ?>
	<section class="ind-process-v2">
		<div class="ind-process-shell">
			<div class="ind-process-head">
				<div class="mk-eyebrow">So planen wir mit dir</div>
				<h2 class="ind-process-h">
					Von der Anfrage bis zum <em class="mk-italic">Termin</em> —
					vier Stationen, ein Ansprechpartner.
				</h2>
				<p class="ind-process-lead">
					Kein Ticketsystem, kein Funnel. Du sprichst von Anfang bis Ende mit derselben
					Person bei Firmengolf.
				</p>
			</div>

			<div class="ind-timeline">
				<div class="ind-timeline-rail" aria-hidden="true"></div>
				<?php
				$steps = [
					[ 'n' => '01', 'day' => 'Tag 1',   't' => 'Anfrage',  'b' => 'Du beschreibst Anlass, Gruppe und ungefähren Rahmen — per Formular oder direkt am Telefon.' ],
					[ 'n' => '02', 'day' => '< 24 h',  't' => 'Beratung', 'b' => 'Jonas oder Lena meldet sich zurück. Wir gehen Wünsche und Optionen durch — locker, nicht im Verkaufsmodus.' ],
					[ 'n' => '03', 'day' => 'Tag 3–5', 't' => 'Vorschlag', 'b' => 'Du bekommst ein Konzept mit zwei bis drei Platz-Optionen, Programm und Preis. Schwarz auf weiß.' ],
					[ 'n' => '04', 'day' => 'Vor Ort',  't' => 'Umsetzung', 'b' => 'Wir koordinieren alles bis vor Ort und sind am Event-Tag erreichbar. Eine Rechnung, ein Kontakt.' ],
				];
				foreach ( $steps as $s ) : ?>
					<article class="ind-tstep">
						<div class="ind-tstep-marker">
							<span class="ind-tstep-dot"></span>
							<span class="ind-tstep-n"><?php echo esc_html( $s['n'] ); ?></span>
						</div>
						<div class="ind-tstep-day"><?php echo esc_html( $s['day'] ); ?></div>
						<h3 class="ind-tstep-t"><?php echo esc_html( $s['t'] ); ?></h3>
						<p class="ind-tstep-b"><?php echo esc_html( $s['b'] ); ?></p>
					</article>
				<?php endforeach; ?>
			</div>

			<div class="ind-process-foot">
				<div>
					<div class="ind-process-foot-eyebrow">Versprochen</div>
					<div class="ind-process-foot-h">Antwort innerhalb eines Werktags.</div>
				</div>
				<a class="fg-btn-brand" href="#anfrage">
					Anfrage starten <span class="fg-arrow"><?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				</a>
			</div>
		</div>
	</section>

	<?php /* ── Request form ── */ ?>
	<div class="ind-form-section" id="anfrage">
		<div class="ind-form-card">

			<?php if ( $ind_submitted ) : ?>

				<?php /* Success panel */ ?>
				<div class="ind-success-v2">
					<div class="fg-success-mark"><?php echo fge_icon_check(); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
					<div class="mk-eyebrow">Anfrage eingegangen</div>
					<h2 class="ind-form-h" style="max-width:640px;margin:8px auto 0;">
						Wir freuen uns, dein Event mitzugestalten.
					</h2>
					<p class="muted" style="margin-top:14px;max-width:560px;margin-left:auto;margin-right:auto;">
						Jonas oder Lena meldet sich innerhalb eines Werktags zurück — mit Rückfragen und einem ersten
						Vorschlag. Du bekommst gleich auch eine Bestätigung per Mail an <strong><?php echo esc_html( $ind_data['email'] ); ?></strong>.
					</p>

					<div class="ind-success-receipt">
						<div><span>Anlass</span><span><?php echo esc_html( $ind_data['occasion'] ); ?></span></div>
						<div><span>Gruppe</span><span><?php echo esc_html( $ind_data['size'] . ' Personen · ' . $ind_data['duration'] ); ?></span></div>
						<div><span>Zeitraum</span><span><?php echo esc_html( $ind_data['when'] ); ?></span></div>
						<div><span>Region</span><span><?php echo esc_html( $ind_data['region'] ); ?></span></div>
						<div><span>Status</span><span><span class="ob-pill-status">In Bearbeitung</span></span></div>
						<div><span>Vorgangs-Nr.</span><span class="mono"><?php echo esc_html( $ind_ref ); ?></span></div>
					</div>

					<div class="ind-success-next">
						<div class="ind-next-h">Was als Nächstes passiert</div>
						<ol>
							<li><span class="ind-next-n">1</span> Wir prüfen die Anfrage und suchen 2–3 passende Plätze.</li>
							<li><span class="ind-next-n">2</span> Persönlicher Rückruf zur Abstimmung der Details.</li>
							<li><span class="ind-next-n">3</span> Du bekommst ein vollständiges Konzept mit Preis.</li>
							<li><span class="ind-next-n">4</span> Wir buchen, koordinieren und sind am Event-Tag erreichbar.</li>
						</ol>
					</div>

					<div class="ind-success-ctas">
						<a class="fg-btn-ghost" href="<?php echo esc_url( $events_url ); ?>">Inzwischen Formate ansehen <?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
						<a class="fg-btn-ghost" href="<?php echo esc_url( $blog_url ); ?>">Lesetipps fürs Event-Briefing <?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
					</div>
				</div>

			<?php else : ?>

				<?php /* Multi-step form */ ?>
				<form class="ind-form-v2" method="post" action="<?php echo esc_url( get_permalink() ); ?>#anfrage" novalidate>

					<header class="ind-form-head">
						<div>
							<div class="mk-eyebrow">Anfrage starten</div>
							<h2 class="ind-form-h">Erzähl uns, was du vorhast.</h2>
							<p class="muted" style="margin-top:10px;max-width:560px;">
								Drei Schritte, je rund eine Minute. Alle Angaben sind unverbindlich — wir gehen sie
								danach persönlich mit dir durch.
							</p>
						</div>
						<div class="ind-form-badges">
							<div class="ind-form-badge">
								<span class="ind-form-badge-v">~3 Min.</span>
								<span class="ind-form-badge-l">Ausfülldauer</span>
							</div>
							<div class="ind-form-badge">
								<span class="ind-form-badge-v">&lt; 24 h</span>
								<span class="ind-form-badge-l">Antwortzeit</span>
							</div>
						</div>
					</header>

					<?php /* Step rail */ ?>
					<ol class="ind-step-rail">
						<?php
						$step_labels = [ 1 => 'Dein Event', 2 => 'Anforderungen', 3 => 'Ansprechpartner' ];
						foreach ( $step_labels as $sn => $sl ) :
							$cls = '';
							if ( $ind_step === $sn ) $cls = 'active';
							elseif ( $ind_step > $sn ) $cls = 'done';
						?>
							<li class="ind-step <?php echo esc_attr( $cls ); ?>">
								<span class="ind-step-bullet">
									<?php if ( $ind_step > $sn ) : ?>
										<?php echo fge_icon_check(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
									<?php else : ?>
										<?php echo esc_html( (string) $sn ); ?>
									<?php endif; ?>
								</span>
								<span class="ind-step-l"><?php echo esc_html( $sl ); ?></span>
							</li>
						<?php endforeach; ?>
					</ol>

					<?php if ( $ind_errors ) : ?>
						<div class="fg-notice fg-notice--error" style="padding:12px 16px;background:var(--fairway-50);border:1px solid var(--fairway-300);border-radius:var(--radius-md);margin-bottom:20px;font-size:14px;color:var(--fairway-900);">
							<?php echo esc_html( implode( ' ', $ind_errors ) ); ?>
						</div>
					<?php endif; ?>

					<?php /* Hidden: persist data from prior steps */ ?>
					<?php foreach ( array_keys( $ind_data ) as $key ) : ?>
						<input type="hidden" name="ind_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $ind_data[ $key ] ); ?>">
					<?php endforeach; ?>

					<?php /* ── STEP 1 ── */ ?>
					<?php if ( $ind_step === 1 ) : ?>
						<?php wp_nonce_field( 'fge_ind_step_1', 'fge_ind_nonce' ); ?>
						<input type="hidden" name="ind_step" value="1">

						<div class="ind-form-step">

							<section class="ind-fsec">
								<header class="ind-fsec-head">
									<span class="ind-fsec-n">01</span>
									<div>
										<div class="ind-fsec-l">Anlass</div>
										<h3 class="ind-fsec-t">Worum geht's bei eurem Event?</h3>
										<p class="ind-fsec-lead">Wähl den nächstpassenden Anlass — wir können das im Gespräch verfeinern.</p>
									</div>
								</header>
								<div class="ind-fsec-body">
									<div class="ind-chip-group">
										<?php foreach ( $occasions as $opt ) : ?>
											<button type="button" class="ind-pchip<?php echo $ind_data['occasion'] === $opt ? ' on' : ''; ?>"
											        data-field="ind_occasion" data-value="<?php echo esc_attr( $opt ); ?>">
												<?php echo esc_html( $opt ); ?>
											</button>
										<?php endforeach; ?>
									</div>
									<input type="hidden" name="ind_occasion" id="ind_occasion" value="<?php echo esc_attr( $ind_data['occasion'] ); ?>">
								</div>
							</section>

							<section class="ind-fsec">
								<header class="ind-fsec-head">
									<span class="ind-fsec-n">02</span>
									<div>
										<div class="ind-fsec-l">Gruppe & Zeitraum</div>
										<h3 class="ind-fsec-t">Wann und mit wie vielen?</h3>
										<p class="ind-fsec-lead">Genau müssen die Angaben jetzt nicht sein.</p>
									</div>
								</header>
								<div class="ind-fsec-body" style="display:flex;flex-direction:column;gap:16px;">
									<div class="ind-form-row">
										<div class="fg-field">
											<span class="ind-flabel">Gruppengröße <span class="ind-required" aria-label="Pflichtfeld">*</span></span>
											<div class="ind-input-row">
												<input class="fg-input" type="number" min="1" name="ind_size"
												       value="<?php echo esc_attr( $ind_data['size'] ); ?>" placeholder="40">
												<span class="ind-input-suffix">Personen</span>
											</div>
										</div>
										<div class="fg-field">
											<span class="ind-flabel">Zeitraum <span class="ind-required" aria-label="Pflichtfeld">*</span></span>
											<input class="fg-input" name="ind_when"
											       value="<?php echo esc_attr( $ind_data['when'] ); ?>"
											       placeholder="z.B. Juli 2026 · KW 28 · 12. Juni">
										</div>
									</div>
									<div class="fg-field">
										<span class="ind-flabel">Wie flexibel seid ihr bei Datum? <span class="ind-flabel-hint">Hilft uns, gute Slot-Optionen zu finden</span></span>
										<div class="ind-chip-group">
											<?php foreach ( $flex_opts as $opt ) : ?>
												<button type="button" class="ind-pchip<?php echo $ind_data['flex'] === $opt ? ' on' : ''; ?>"
												        data-field="ind_flex" data-value="<?php echo esc_attr( $opt ); ?>">
													<?php echo esc_html( $opt ); ?>
												</button>
											<?php endforeach; ?>
										</div>
										<input type="hidden" name="ind_flex" id="ind_flex" value="<?php echo esc_attr( $ind_data['flex'] ); ?>">
									</div>
									<div class="fg-field">
										<span class="ind-flabel">Dauer</span>
										<div class="ind-chip-group">
											<?php foreach ( $duration_opts as $opt ) : ?>
												<button type="button" class="ind-pchip<?php echo $ind_data['duration'] === $opt ? ' on' : ''; ?>"
												        data-field="ind_duration" data-value="<?php echo esc_attr( $opt ); ?>">
													<?php echo esc_html( $opt ); ?>
												</button>
											<?php endforeach; ?>
										</div>
										<input type="hidden" name="ind_duration" id="ind_duration" value="<?php echo esc_attr( $ind_data['duration'] ); ?>">
									</div>
								</div>
							</section>

							<section class="ind-fsec">
								<header class="ind-fsec-head">
									<span class="ind-fsec-n">03</span>
									<div>
										<div class="ind-fsec-l">Region</div>
										<h3 class="ind-fsec-t">Wo soll das Event stattfinden?</h3>
									</div>
								</header>
								<div class="ind-fsec-body">
									<div class="ind-chip-group">
										<?php foreach ( $region_opts as $opt ) : ?>
											<button type="button" class="ind-pchip<?php echo $ind_data['region'] === $opt ? ' on' : ''; ?>"
											        data-field="ind_region" data-value="<?php echo esc_attr( $opt ); ?>">
												<?php echo esc_html( $opt ); ?>
											</button>
										<?php endforeach; ?>
									</div>
									<input type="hidden" name="ind_region" id="ind_region" value="<?php echo esc_attr( $ind_data['region'] ); ?>">
								</div>
							</section>

						</div>

						<div class="ind-form-controls">
							<div class="ind-form-controls-l">
								<span class="muted" style="font-size:13px;">Schritt 1 von 3</span>
							</div>
							<div class="ind-form-controls-r">
								<span class="fg-rail-note">Persönlicher Rückruf in 24 h. Kostenlos, unverbindlich.</span>
								<button type="submit" class="fg-btn-brand">
									Weiter <span class="fg-arrow"><?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
								</button>
							</div>
						</div>

					<?php /* ── STEP 2 ── */ ?>
					<?php elseif ( $ind_step === 2 ) : ?>
						<?php wp_nonce_field( 'fge_ind_step_2', 'fge_ind_nonce' ); ?>
						<input type="hidden" name="ind_step" value="2">

						<div class="ind-form-step">

							<section class="ind-fsec">
								<header class="ind-fsec-head">
									<span class="ind-fsec-n">04</span>
									<div>
										<div class="ind-fsec-l">Budget-Rahmen</div>
										<h3 class="ind-fsec-t">Was wäre euer ungefährer Rahmen?</h3>
										<p class="ind-fsec-lead">Nur eine Richtschnur — wir verhandeln nicht nach oben.</p>
									</div>
								</header>
								<div class="ind-fsec-body">
									<div class="ind-budget-grid">
										<?php foreach ( $budget_opts as $opt ) : ?>
											<button type="button"
											        class="ind-budget-card<?php echo $ind_data['budget'] === $opt['v'] ? ' on' : ''; ?>"
											        data-field="ind_budget" data-value="<?php echo esc_attr( $opt['v'] ); ?>">
												<span class="ind-budget-v"><?php echo esc_html( $opt['v'] ); ?></span>
												<span class="ind-budget-h"><?php echo esc_html( $opt['h'] ); ?></span>
											</button>
										<?php endforeach; ?>
									</div>
									<input type="hidden" name="ind_budget" id="ind_budget" value="<?php echo esc_attr( $ind_data['budget'] ); ?>">
								</div>
							</section>

							<section class="ind-fsec">
								<header class="ind-fsec-head">
									<span class="ind-fsec-n">05</span>
									<div>
										<div class="ind-fsec-l">Anforderungen</div>
										<h3 class="ind-fsec-t">Was soll dabei sein?</h3>
										<p class="ind-fsec-lead">Mehrfachauswahl. Wir bauen das Konzept passend dazu.</p>
									</div>
								</header>
								<div class="ind-fsec-body">
									<div class="ind-toggles">
										<?php
										$toggles = [
											'catering'      => 'Catering geplant',
											'coaching'      => 'Coaching für Einsteigende',
											'overnight'     => 'Übernachtung erwünscht',
											'transport'     => 'Transport / Shuttle',
											'branding'      => 'Branding / Banner',
											'photo'         => 'Foto- & Videografie',
											'indoor'        => 'Indoor-Backup nötig',
											'accessibility' => 'Barrierefreiheit wichtig',
										];
										foreach ( $toggles as $field => $label ) : ?>
											<button type="button"
											        class="ind-toggle<?php echo $ind_data[ $field ] ? ' on' : ''; ?>"
											        data-toggle="ind_<?php echo esc_attr( $field ); ?>">
												<span class="ind-toggle-dot"></span>
												<span><?php echo esc_html( $label ); ?></span>
											</button>
											<input type="hidden" name="ind_<?php echo esc_attr( $field ); ?>"
											       id="ind_<?php echo esc_attr( $field ); ?>"
											       value="<?php echo esc_attr( $ind_data[ $field ] ); ?>">
										<?php endforeach; ?>
									</div>
								</div>
							</section>

							<section class="ind-fsec">
								<header class="ind-fsec-head">
									<span class="ind-fsec-n">06</span>
									<div>
										<div class="ind-fsec-l">Sonstiges</div>
										<h3 class="ind-fsec-t">Gibt's etwas, das wir wissen sollten?</h3>
									</div>
								</header>
								<div class="ind-fsec-body">
									<textarea class="fg-input" name="ind_notes" rows="5"
									          placeholder="Sonderwünsche, Hintergrund, Programmideen, schon mal mit uns geplant — alles, was uns hilft, dein Event passgenau aufzusetzen."><?php echo esc_textarea( $ind_data['notes'] ); ?></textarea>
								</div>
							</section>

						</div>

						<div class="ind-form-controls">
							<div class="ind-form-controls-l">
								<button type="submit" name="ind_back" class="fg-btn-ghost">← Zurück</button>
							</div>
							<div class="ind-form-controls-r">
								<span class="fg-rail-note">Persönlicher Rückruf in 24 h. Kostenlos, unverbindlich.</span>
								<button type="submit" class="fg-btn-brand">
									Weiter <span class="fg-arrow"><?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
								</button>
							</div>
						</div>

					<?php /* ── STEP 3 ── */ ?>
					<?php elseif ( $ind_step === 3 ) : ?>
						<?php wp_nonce_field( 'fge_ind_step_3', 'fge_ind_nonce' ); ?>
						<input type="hidden" name="ind_step" value="3">

						<div class="ind-form-step">

							<section class="ind-fsec">
								<header class="ind-fsec-head">
									<span class="ind-fsec-n">07</span>
									<div>
										<div class="ind-fsec-l">Ansprechpartner</div>
										<h3 class="ind-fsec-t">Mit wem dürfen wir sprechen?</h3>
									</div>
								</header>
								<div class="ind-fsec-body" style="display:flex;flex-direction:column;gap:16px;">
									<div class="ind-form-row">
										<div class="fg-field">
											<span class="ind-flabel">Vorname <span class="ind-required">*</span></span>
											<input class="fg-input" name="ind_first_name"
											       value="<?php echo esc_attr( $ind_data['first_name'] ); ?>" placeholder="Lena">
										</div>
										<div class="fg-field">
											<span class="ind-flabel">Nachname <span class="ind-required">*</span></span>
											<input class="fg-input" name="ind_last_name"
											       value="<?php echo esc_attr( $ind_data['last_name'] ); ?>" placeholder="Hoffmann">
										</div>
									</div>
									<div class="ind-form-row">
										<div class="fg-field">
											<span class="ind-flabel">Firma <span class="ind-required">*</span></span>
											<input class="fg-input" name="ind_company"
											       value="<?php echo esc_attr( $ind_data['company'] ); ?>" placeholder="Quartz Labs GmbH">
										</div>
										<div class="fg-field">
											<span class="ind-flabel">Deine Rolle</span>
											<input class="fg-input" name="ind_role"
											       value="<?php echo esc_attr( $ind_data['role'] ); ?>" placeholder="People Lead, Office Manager …">
										</div>
									</div>
									<div class="ind-form-row">
										<div class="fg-field">
											<span class="ind-flabel">E-Mail <span class="ind-required">*</span></span>
											<input class="fg-input" type="email" name="ind_email"
											       value="<?php echo esc_attr( $ind_data['email'] ); ?>" placeholder="lena@firma.de">
										</div>
										<div class="fg-field">
											<span class="ind-flabel">Telefon</span>
											<input class="fg-input" type="tel" name="ind_phone"
											       value="<?php echo esc_attr( $ind_data['phone'] ); ?>" placeholder="+49 …">
										</div>
									</div>
									<div class="fg-field">
										<span class="ind-flabel">Wie sollen wir uns melden?</span>
										<div class="ind-chip-group">
											<?php foreach ( $contact_opts as $opt ) : ?>
												<button type="button"
												        class="ind-pchip<?php echo $ind_data['contact_pref'] === $opt ? ' on' : ''; ?>"
												        data-field="ind_contact_pref" data-value="<?php echo esc_attr( $opt ); ?>">
													<?php echo esc_html( $opt ); ?>
												</button>
											<?php endforeach; ?>
										</div>
										<input type="hidden" name="ind_contact_pref" id="ind_contact_pref" value="<?php echo esc_attr( $ind_data['contact_pref'] ); ?>">
									</div>
									<div class="fg-field">
										<span class="ind-flabel">Wie hast du von uns gehört? <span class="ind-flabel-hint">Optional</span></span>
										<input class="fg-input" name="ind_referral"
										       value="<?php echo esc_attr( $ind_data['referral'] ); ?>"
										       placeholder="Empfehlung, LinkedIn, Google, Veranstaltung …">
									</div>
								</div>
							</section>

							<section class="ind-fsec">
								<header class="ind-fsec-head">
									<span class="ind-fsec-n">08</span>
									<div>
										<div class="ind-fsec-l">Zusammenfassung</div>
										<h3 class="ind-fsec-t">Sieht das richtig aus?</h3>
									</div>
								</header>
								<div class="ind-fsec-body">
									<div class="ind-summary">
										<?php
										$extras_list = array_filter( [
											$ind_data['catering']     ? 'Catering'      : '',
											$ind_data['coaching']     ? 'Coaching'      : '',
											$ind_data['overnight']    ? 'Übernachtung'  : '',
											$ind_data['transport']    ? 'Transport'     : '',
											$ind_data['branding']     ? 'Branding'      : '',
											$ind_data['photo']        ? 'Fotografie'   : '',
											$ind_data['indoor']       ? 'Indoor-Backup' : '',
											$ind_data['accessibility'] ? 'Barrierefrei' : '',
										] );
										$summary_rows = [
											'Anlass'   => $ind_data['occasion'],
											'Gruppe'   => $ind_data['size'] . ' Personen · ' . $ind_data['duration'],
											'Zeitraum' => trim( $ind_data['when'] . ' · ' . $ind_data['flex'], ' · ' ),
											'Region'   => $ind_data['region'],
											'Budget'   => $ind_data['budget'] ?: '–',
											'Extras'   => $extras_list ? implode( ' · ', $extras_list ) : '–',
										];
										foreach ( $summary_rows as $l => $v ) : ?>
											<div class="ind-sum-row">
												<span class="ind-sum-l"><?php echo esc_html( $l ); ?></span>
												<span class="ind-sum-v"><?php echo esc_html( $v ); ?></span>
											</div>
										<?php endforeach; ?>
									</div>
								</div>
							</section>

							<label class="ind-consent">
								<input type="checkbox" name="ind_consent" value="1">
								<span>
									Ich stimme der Verarbeitung meiner Daten zur Bearbeitung der Anfrage gemäß
									<a href="<?php echo esc_url( home_url( '/datenschutz/' ) ); ?>">Datenschutzerklärung</a> zu.
									Wir nehmen dich nicht in einen Verteiler auf.
								</span>
							</label>

						</div>

						<div class="ind-form-controls">
							<div class="ind-form-controls-l">
								<button type="submit" name="ind_back" class="fg-btn-ghost">← Zurück</button>
							</div>
							<div class="ind-form-controls-r">
								<span class="fg-rail-note">Persönlicher Rückruf in 24 h. Kostenlos, unverbindlich.</span>
								<button type="submit" class="fg-btn-brand lg">
									Anfrage senden <span class="fg-arrow"><?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
								</button>
							</div>
						</div>

					<?php endif; ?>

					<?php /* Trust strip */ ?>
					<div class="ind-form-trust">
						<div class="ind-trust-c">
							<span class="ind-trust-v">Persönlich</span>
							<span class="ind-trust-l">Echter Mensch, kein Bot</span>
						</div>
						<div class="ind-trust-c">
							<span class="ind-trust-v">Unverbindlich</span>
							<span class="ind-trust-l">Kein Vertrag, keine Kosten</span>
						</div>
						<div class="ind-trust-c">
							<span class="ind-trust-v">DSGVO</span>
							<span class="ind-trust-l">Daten nur für deine Anfrage</span>
						</div>
						<div class="ind-trust-c">
							<span class="ind-trust-v">&lt; 24 h</span>
							<span class="ind-trust-l">Antwort innerhalb 1 Werktags</span>
						</div>
					</div>

				</form>
			<?php endif; ?>

		</div>
	</div>

	<?php /* ── FAQ ── */ ?>
	<div class="ind-faq">
		<div class="ind-faq-head">
			<div class="mk-eyebrow">FAQ</div>
			<h2 class="mk-h2" style="margin-top:8px;">Was wir oft gefragt werden.</h2>
			<p class="mk-sub">Antworten auf das, was bei individuellen Events am häufigsten unklar ist.</p>
		</div>
		<div class="ind-faq-list">
			<?php foreach ( $faqs as $i => $faq ) : ?>
				<div class="ind-faq-item" id="faq-<?php echo esc_attr( (string) $i ); ?>">
					<button type="button" class="ind-faq-q" aria-expanded="false" aria-controls="faq-a-<?php echo esc_attr( (string) $i ); ?>">
						<span><?php echo esc_html( $faq['q'] ); ?></span>
						<span class="ind-faq-icon" aria-hidden="true">+</span>
					</button>
					<div class="ind-faq-a" id="faq-a-<?php echo esc_attr( (string) $i ); ?>">
						<?php echo esc_html( $faq['a'] ); ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>

<script>
(function () {
	// Chip group / card selection
	document.querySelectorAll('.ind-pchip, .ind-budget-card').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var field = btn.dataset.field;
			var val   = btn.dataset.value;
			if (!field) return;
			// Deselect siblings with same data-field
			document.querySelectorAll('[data-field="' + field + '"]').forEach(function (b) {
				b.classList.remove('on');
			});
			btn.classList.add('on');
			var input = document.getElementById(field);
			if (input) input.value = val;
		});
	});

	// Toggle buttons
	document.querySelectorAll('.ind-toggle').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var target = btn.dataset.toggle;
			var isOn   = btn.classList.toggle('on');
			var input  = document.getElementById(target);
			if (input) input.value = isOn ? '1' : '';
		});
	});

	// FAQ accordion
	document.querySelectorAll('.ind-faq-q').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var item = btn.closest('.ind-faq-item');
			var isOpen = item.classList.contains('open');
			// Close all
			document.querySelectorAll('.ind-faq-item').forEach(function (el) {
				el.classList.remove('open');
				el.querySelector('.ind-faq-q').setAttribute('aria-expanded', 'false');
			});
			if (!isOpen) {
				item.classList.add('open');
				btn.setAttribute('aria-expanded', 'true');
			}
		});
	});
})();
</script>

<?php get_footer(); ?>
