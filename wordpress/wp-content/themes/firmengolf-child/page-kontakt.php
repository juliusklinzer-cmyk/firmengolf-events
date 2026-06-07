<?php
/**
 * Template: Kontakt
 * Ported pixel-for-pixel from React Contact.jsx — bespoke ct- and contact- layout.
 * Sections: Hero · Quick channels · Form + sidebar · Callback band · Termin/Besuch · FAQ.
 * Form & Rückruf submit to includes/contact-handler.php (firmengolf_request + Admin-Mail).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$img         = static fn( string $n ): string => fge_get_placeholder_image_url( $n );
$co          = fge_company();
$self_url    = (string) get_permalink();
$url_dsgvo   = home_url( '/datenschutz/' );
$hubspot_url = 'https://meetings.hubspot.com/julius-klinzer/firmengolf_fuer_dein_unternehmen';
$office_addr = $co['office_street'] . ', ' . $co['office_zip'] . ' ' . $co['office_city'];
$maps_url    = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $office_addr );

/* ── State (PRG: Erfolg / Fehler) ── */
$danke = isset( $_GET['kontakt'] ) && $_GET['kontakt'] === 'danke'; // phpcs:ignore WordPress.Security.NonceVerification
$fgref = isset( $_GET['fgref'] ) ? sanitize_text_field( wp_unslash( $_GET['fgref'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$etok  = isset( $_GET['kontakt_err'] ) ? sanitize_text_field( wp_unslash( $_GET['kontakt_err'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$estate = $etok ? get_transient( 'fge_kontakt_err_' . $etok ) : false;
$errors = ( is_array( $estate ) && isset( $estate['errors'] ) ) ? $estate['errors'] : [];
$edata  = ( is_array( $estate ) && isset( $estate['data'] ) ) ? $estate['data'] : [];
$dv     = static fn( string $k ): string => isset( $edata[ $k ] ) ? (string) $edata[ $k ] : '';
$sel_topic = $dv( 'topic_id' ) !== '' ? $dv( 'topic_id' ) : 'event';
$sel_pref  = $dv( 'pref' ) !== '' ? $dv( 'pref' ) : 'Egal';

$cb_danke = isset( $_GET['rueckruf'] ) && $_GET['rueckruf'] === 'danke'; // phpcs:ignore WordPress.Security.NonceVerification
$cb_err   = isset( $_GET['rueckruf_err'] ); // phpcs:ignore WordPress.Security.NonceVerification

/* ── Inline channel icons (Lucide-style, 1.6 stroke) ── */
$cicon = static function ( string $name, int $size = 22 ): string {
	$p = [
		'phone'    => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>',
		'chat'     => '<path d="M21 11.5a8.38 8.38 0 0 1-8.5 8.5 8.5 8.5 0 0 1-3.8-.9L3 21l1.9-5.7A8.38 8.38 0 0 1 4 11.5 8.5 8.5 0 0 1 12.5 3 8.38 8.38 0 0 1 21 11.5z"/>',
		'mail'     => '<rect x="2.5" y="4.5" width="19" height="15" rx="2.5"/><path d="M3 6.5l9 6 9-6"/>',
		'calendar' => '<rect x="3" y="4.5" width="18" height="16" rx="2.5"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4"/>',
		'pin'      => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
		'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'check'    => '<path d="M20 6L9 17l-5-5"/>',
		'a11y'     => '<circle cx="12" cy="4.5" r="1.6"/><path d="M5 8.5c2 .9 4.4 1.4 7 1.4s5-.5 7-1.4M12 9.9v5.1M9 21l3-6 3 6"/>',
	];
	$d = $p[ $name ] ?? '';
	return '<svg viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
};

$topics = [
	'event'   => 'Event anfragen',
	'individ' => 'Individuelles Event',
	'partner' => 'Partnerplatz werden',
	'benefit' => 'Benefit-Programm',
	'press'   => 'Presse',
	'other'   => 'Etwas anderes',
];
$prefs = [ 'Egal', 'E-Mail', 'Telefon', 'WhatsApp' ];

$faqs = [
	[ 'Wie schnell antwortet ihr?', 'Werktags innerhalb eines Arbeitstags, per WhatsApp meist in Minuten. Anfragen von Freitagnachmittag bis Sonntag beantworten wir Montag früh.' ],
	[ 'Ich rede lieber, als zu tippen — geht das?', 'Klar. Ruf direkt an, schreib uns auf WhatsApp, oder fordere oben einen Rückruf an — dann melden wir uns zur gewünschten Zeit bei dir.' ],
	[ 'Ich bin Golfplatz und will Partner werden — wohin?', 'Schreib an partner@visionpunch.de oder starte direkt das Partner-Onboarding über „Partnerportal" oben. Bei größeren Anlagen kommen wir auch persönlich vorbei.' ],
	[ 'Ich habe eine Frage zum Benefit-Programm.', 'Das läuft über firmen.golf — schreib uns trotzdem hier, wir leiten weiter und sorgen, dass du eine Antwort bekommst.' ],
	[ 'Brauche ich ein Konto, um euch zu kontaktieren?', 'Nein. Kein Login, keine Registrierung — Formular, Mail, Anruf oder WhatsApp genügen.' ],
	[ 'Geht das auch auf Englisch?', 'Yes — wir antworten gerne auf Deutsch oder Englisch. Schreib einfach in deiner Sprache.' ],
];
?>
<div class="fge-page">

<?php get_template_part( 'template-parts/fge-nav', null, [ 'active_item' => 'kontakt' ] ); ?>

<?php /* ===== Hero ===== */ ?>
<section class="ct-hero" aria-label="Kontakt">
	<div class="mk-eyebrow">Kontakt</div>
	<h1 class="ct-hero-h">
		Frag uns alles — du landest bei einem <em class="mk-italic">echten Menschen</em>.
	</h1>
	<p class="ct-hero-sub">
		Kein Chatbot, kein Ticketsystem, keine Warteschleife ins Nichts. Wähl den Weg, der dir
		am liebsten ist — wir antworten innerhalb eines Werktags, oft schneller.
	</p>
</section>

<?php /* ===== Quick channels ===== */ ?>
<section class="ct-channels" aria-label="Direkte Kontaktwege">
	<a class="ct-channel" href="tel:<?php echo esc_attr( $co['phone_tel'] ); ?>">
		<span class="ct-channel-ic"><?php echo $cicon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
		<span class="ct-channel-body">
			<span class="ct-channel-l">Anrufen</span>
			<span class="ct-channel-v"><?php echo esc_html( $co['phone_display'] ); ?></span>
			<span class="ct-channel-m">Mo–Fr · 9–18 Uhr</span>
		</span>
		<span class="ct-channel-go"><?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
	</a>
	<a class="ct-channel ct-channel-wa" href="<?php echo esc_url( $co['whatsapp_url'] ); ?>" target="_blank" rel="noopener noreferrer">
		<span class="ct-channel-ic"><?php echo $cicon( 'chat' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
		<span class="ct-channel-body">
			<span class="ct-channel-l">WhatsApp</span>
			<span class="ct-channel-v">Kurz schreiben</span>
			<span class="ct-channel-m">Antwort meist in Minuten</span>
		</span>
		<span class="ct-channel-go"><?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
	</a>
	<a class="ct-channel" href="mailto:<?php echo esc_attr( $co['email_general'] ); ?>">
		<span class="ct-channel-ic"><?php echo $cicon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
		<span class="ct-channel-body">
			<span class="ct-channel-l">E-Mail</span>
			<span class="ct-channel-v"><?php echo esc_html( $co['email_general'] ); ?></span>
			<span class="ct-channel-m">Antwort in einem Werktag</span>
		</span>
		<span class="ct-channel-go"><?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
	</a>
	<a class="ct-channel" href="#callback" data-scroll-focus="cb-phone">
		<span class="ct-channel-ic"><?php echo $cicon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
		<span class="ct-channel-body">
			<span class="ct-channel-l">Rückruf anfordern</span>
			<span class="ct-channel-v">Wir rufen dich an</span>
			<span class="ct-channel-m">Du nennst Zeit &amp; Nummer</span>
		</span>
		<span class="ct-channel-go"><?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
	</a>
</section>

<?php /* ===== Form + sidebar ===== */ ?>
<section class="contact-shell" id="kontaktformular">
	<aside class="contact-form-card">
		<?php if ( $danke ) : ?>
			<div class="contact-success" role="status" aria-live="polite">
				<div class="fg-success-mark"><?php echo $cicon( 'check', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
				<h2 class="contact-form-h">Danke — wir haben dich.</h2>
				<p class="muted" style="margin-top:12px;max-width:420px;">
					Deine Nachricht ist angekommen. Wir melden uns innerhalb eines Werktags.
				</p>
				<?php if ( $fgref !== '' ) : ?>
					<div class="ct-success-ref">
						<span>Vorgangs-Nr.</span>
						<span class="mono"><?php echo esc_html( $fgref ); ?></span>
					</div>
				<?php endif; ?>
				<a class="fg-btn-ghost" href="<?php echo esc_url( $self_url . '#kontaktformular' ); ?>" style="margin-top:24px;">Neue Nachricht</a>
			</div>
		<?php else : ?>
			<div class="mk-eyebrow">Schreib uns</div>
			<h2 class="contact-form-h">Sag uns kurz, worum es geht.</h2>
			<p class="muted" style="margin-top:8px;">
				Ein, zwei Sätze reichen. Pflichtfelder sind mit <span class="ct-req">*</span> markiert.
			</p>

			<?php if ( ! empty( $errors ) ) : ?>
				<div class="fg-input-err" style="margin-top:16px;padding:12px 16px;border:1px solid var(--clay-600);border-radius:var(--radius-md);background:rgba(180,90,55,.06);color:var(--ink-900);font-size:14px;">
					Bitte prüfe deine Eingaben:
					<ul style="margin:6px 0 0;padding-left:18px;">
						<?php foreach ( $errors as $msg ) : ?>
							<li><?php echo esc_html( $msg ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( $self_url ); ?>" novalidate>
				<input type="hidden" name="fge_action" value="kontakt_submit">
				<?php wp_nonce_field( 'fge_kontakt', 'fge_kontakt_nonce' ); ?>
				<input type="text" name="fge_hp_url" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;">
				<input type="hidden" name="fge_kontakt_topic" id="fge-topic-input" value="<?php echo esc_attr( $sel_topic ); ?>">
				<input type="hidden" name="fge_kontakt_pref" id="fge-pref-input" value="<?php echo esc_attr( $sel_pref ); ?>">

				<fieldset class="ct-fieldset">
					<legend class="fg-field-label">Worum geht's?</legend>
					<div class="ct-topic-row" role="radiogroup" aria-label="Thema">
						<?php foreach ( $topics as $tid => $tlabel ) : ?>
							<button type="button" role="radio" aria-checked="<?php echo $sel_topic === $tid ? 'true' : 'false'; ?>"
								class="ct-topic<?php echo $sel_topic === $tid ? ' on' : ''; ?>"
								data-chip="topic" data-val="<?php echo esc_attr( $tid ); ?>"><?php echo esc_html( $tlabel ); ?></button>
						<?php endforeach; ?>
					</div>
				</fieldset>

				<div class="contact-form-grid">
					<div class="fg-field">
						<label class="fg-field-label" for="ct-name">Name <span class="ct-req">*</span></label>
						<input id="ct-name" name="fge_kontakt_name" class="fg-input" placeholder="Vor- und Nachname" required autocomplete="name" value="<?php echo esc_attr( $dv( 'name' ) ); ?>">
					</div>
					<div class="fg-field">
						<label class="fg-field-label" for="ct-company">Firma</label>
						<input id="ct-company" name="fge_kontakt_company" class="fg-input" placeholder="Firmenname" autocomplete="organization" value="<?php echo esc_attr( $dv( 'company' ) ); ?>">
					</div>
					<div class="fg-field">
						<label class="fg-field-label" for="ct-email">E-Mail <span class="ct-req">*</span></label>
						<input id="ct-email" name="fge_kontakt_email" class="fg-input" type="email" placeholder="du@firma.de" required autocomplete="email" aria-describedby="ct-email-hint" value="<?php echo esc_attr( $dv( 'email' ) ); ?>">
						<span class="fg-field-help" id="ct-email-hint">Nur für unsere Antwort — kein Newsletter.</span>
					</div>
					<div class="fg-field">
						<label class="fg-field-label" for="ct-phone">Telefon</label>
						<input id="ct-phone" name="fge_kontakt_phone" class="fg-input" type="tel" inputmode="tel" placeholder="+49 …" autocomplete="tel" value="<?php echo esc_attr( $dv( 'phone' ) ); ?>">
					</div>
					<div class="fg-field fg-field-full">
						<label class="fg-field-label" for="ct-msg">Deine Nachricht <span class="ct-req">*</span></label>
						<textarea id="ct-msg" name="fge_kontakt_message" class="fg-input" rows="5" required placeholder="Anlass, Gruppengröße, Zeitraum — oder einfach deine Frage."><?php echo esc_textarea( $dv( 'message' ) ); ?></textarea>
					</div>

					<fieldset class="ct-fieldset fg-field-full">
						<legend class="fg-field-label">Wie sollen wir antworten?</legend>
						<div class="ct-pref-row" role="radiogroup" aria-label="Bevorzugter Kontaktweg">
							<?php foreach ( $prefs as $o ) : ?>
								<button type="button" role="radio" aria-checked="<?php echo $sel_pref === $o ? 'true' : 'false'; ?>"
									class="ct-pref<?php echo $sel_pref === $o ? ' on' : ''; ?>"
									data-chip="pref" data-val="<?php echo esc_attr( $o ); ?>"><?php echo esc_html( $o ); ?></button>
							<?php endforeach; ?>
						</div>
					</fieldset>

					<label class="contact-consent fg-field-full" for="ct-consent">
						<input id="ct-consent" name="fge_kontakt_consent" type="checkbox" required value="1">
						<span>Ich stimme der Verarbeitung meiner Daten zur Bearbeitung der Anfrage gemäß <a href="<?php echo esc_url( $url_dsgvo ); ?>">Datenschutzerklärung</a> zu.</span>
					</label>
				</div>

				<div class="contact-form-foot">
					<button type="submit" class="fg-btn-brand">
						Nachricht senden <span class="fg-arrow"><?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					</button>
					<span class="fg-rail-note">Antwort innerhalb eines Werktags. Kein Vertriebs-Druck.</span>
				</div>
			</form>
		<?php endif; ?>
	</aside>

	<div class="contact-left">
		<div class="ct-person">
			<span class="ct-person-photo" role="img" aria-label="Julius Klinzer" style="background-image:url('<?php echo esc_url( $img( 'gruender-julius-klinzer-2.jpg' ) ); ?>')"></span>
			<div>
				<div class="ct-person-q">„Anfragen landen direkt bei mir. Ich antworte persönlich — versprochen."</div>
				<div class="ct-person-id">
					<span class="ct-person-name">Julius Klinzer</span>
					<span class="ct-person-role">Gründer · Firmengolf</span>
				</div>
			</div>
		</div>

		<div class="ct-promise">
			<div class="ct-promise-row">
				<span class="ct-promise-ic"><?php echo $cicon( 'clock', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				<div>
					<div class="ct-promise-t">Antwort in einem Werktag</div>
					<div class="ct-promise-m">Freitagnachmittag–Sonntag: Montag früh</div>
				</div>
			</div>
			<div class="ct-promise-row">
				<span class="ct-promise-ic"><?php echo $cicon( 'check', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				<div>
					<div class="ct-promise-t">Unverbindlich &amp; kostenlos</div>
					<div class="ct-promise-m">Erst beraten, dann entscheiden</div>
				</div>
			</div>
			<div class="ct-promise-row">
				<span class="ct-promise-ic"><?php echo $cicon( 'a11y', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				<div>
					<div class="ct-promise-t">So, wie's dir passt</div>
					<div class="ct-promise-m">Mail, Telefon, WhatsApp — oder einfache Sprache auf Wunsch</div>
				</div>
			</div>
		</div>

		<div class="ct-directory">
			<div class="ct-dir-h">Direkt an die richtige Stelle</div>
			<a class="ct-dir-row" href="mailto:<?php echo esc_attr( $co['email_events'] ); ?>"><span>Event-Anfragen</span><span class="ct-dir-v"><?php echo esc_html( $co['email_events'] ); ?></span></a>
			<a class="ct-dir-row" href="mailto:<?php echo esc_attr( $co['email_partner'] ); ?>"><span>Partnerplätze</span><span class="ct-dir-v"><?php echo esc_html( $co['email_partner'] ); ?></span></a>
			<a class="ct-dir-row" href="mailto:<?php echo esc_attr( $co['email_press'] ); ?>"><span>Presse &amp; Medien</span><span class="ct-dir-v"><?php echo esc_html( $co['email_press'] ); ?></span></a>
		</div>
	</div>
</section>

<?php /* ===== Callback band ===== */ ?>
<section class="ct-callback-section" aria-label="Rückruf">
	<div class="ct-callback" id="callback">
		<?php if ( $cb_danke ) : ?>
			<div class="ct-callback-done" role="status">
				<span class="ct-callback-ic done"><?php echo $cicon( 'check', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				<div>
					<h3 class="ct-callback-h">Alles klar — wir rufen dich an.</h3>
					<p class="ct-callback-p">Wir melden uns zur gewünschten Zeit bei dir.</p>
				</div>
			</div>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( $self_url ); ?>">
				<input type="hidden" name="fge_action" value="rueckruf_submit">
				<?php wp_nonce_field( 'fge_rueckruf', 'fge_rueckruf_nonce' ); ?>
				<input type="text" name="fge_hp_url" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;">
				<div class="ct-callback-head">
					<span class="ct-callback-ic"><?php echo $cicon( 'phone', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<div>
						<h3 class="ct-callback-h">Lass dich zurückrufen.</h3>
						<p class="ct-callback-p">Keine Lust zu tippen oder in der Warteschleife zu hängen? Nummer rein, wir melden uns.</p>
						<?php if ( $cb_err ) : ?>
							<p class="ct-callback-p" style="color:#ffd9cc;">Bitte gib eine Telefonnummer an.</p>
						<?php endif; ?>
					</div>
				</div>
				<div class="ct-callback-row">
					<div class="fg-field" style="flex:2;">
						<label class="fg-field-label" for="cb-phone">Deine Nummer</label>
						<input id="cb-phone" name="fge_rueckruf_phone" class="fg-input" type="tel" inputmode="tel" placeholder="+49 …" required>
					</div>
					<div class="fg-field" style="flex:1;">
						<label class="fg-field-label" for="cb-when">Wann passt's?</label>
						<select id="cb-when" name="fge_rueckruf_when" class="fg-input">
							<option>Egal</option>
							<option>Vormittags</option>
							<option>Nachmittags</option>
							<option>Früher Abend</option>
						</select>
					</div>
					<button class="fg-btn-brand ct-callback-btn" type="submit">Rückruf anfordern</button>
				</div>
			</form>
		<?php endif; ?>
	</div>
</section>

<?php /* ===== Termin + Besuch ===== */ ?>
<section class="ct-extra" aria-label="Termin oder Besuch">
	<div class="ct-extra-grid">
		<div class="ct-extra-card">
			<span class="ct-extra-ic"><?php echo $cicon( 'calendar', 24 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			<h3 class="ct-extra-h">Lieber ein fester Termin?</h3>
			<p class="ct-extra-p">Buch dir 15 Minuten für ein lockeres Kennenlern-Gespräch — Video oder Telefon, ganz wie du magst.</p>
			<a class="fg-btn-brand" href="<?php echo esc_url( $hubspot_url ); ?>" target="_blank" rel="noopener noreferrer">
				Termin buchen <span class="fg-arrow"><?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			</a>
		</div>
		<div class="ct-extra-card ct-extra-card-visit">
			<div class="ct-extra-map" aria-hidden="true">
				<div class="ct-extra-map-grid"></div>
				<div class="ct-extra-map-pin"></div>
			</div>
			<div class="ct-extra-map-body">
				<span class="ct-extra-ic"><?php echo $cicon( 'pin', 24 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				<h3 class="ct-extra-h">Komm vorbei.</h3>
				<p class="ct-extra-p"><?php echo esc_html( $co['legal_name'] ); ?> · <?php echo esc_html( $office_addr ); ?> (<?php echo esc_html( $co['office_floor'] ); ?>). Auf einen Kaffee — kurz vorher anrufen, dann ist jemand da.</p>
				<a class="fg-btn-ghost" href="<?php echo esc_url( $maps_url ); ?>" target="_blank" rel="noopener noreferrer">
					Route anzeigen <?php echo fge_icon_arrow_right(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</a>
			</div>
		</div>
	</div>
</section>

<?php /* ===== FAQ ===== */ ?>
<section class="mk-section faq-section" aria-label="Häufige Fragen">
	<div class="faq-shell">
		<div class="faq-aside">
			<div class="mk-eyebrow">FAQ</div>
			<h2 class="mk-h2" style="margin-top:8px;font-size:36px;">Antworten, bevor du fragst.</h2>
			<p class="mk-sub">Vieles klärt sich in einem Satz. Was nicht hier steht — frag einfach, auf dem Weg, der dir passt.</p>
		</div>
		<ul class="faq-list">
			<?php foreach ( $faqs as $i => $faq ) : ?>
				<li class="faq-item" id="ct-faq-<?php echo esc_attr( (string) $i ); ?>">
					<button class="faq-q" type="button" aria-expanded="false">
						<span><?php echo esc_html( $faq[0] ); ?></span>
						<span class="faq-toggle" aria-hidden="true">+</span>
					</button>
					<div class="faq-a"><?php echo esc_html( $faq[1] ); ?></div>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>

<?php get_template_part( 'template-parts/fge-footer' ); ?>

</div><?php /* .fge-page */ ?>

<script>
(function () {
	// Chip radio groups (Thema / Kontaktweg) — toggle .on + sync hidden input
	document.querySelectorAll('.ct-topic-row, .ct-pref-row').forEach(function (group) {
		group.addEventListener('click', function (e) {
			var btn = e.target.closest('[data-chip]');
			if (!btn) return;
			var kind = btn.getAttribute('data-chip');
			group.querySelectorAll('[data-chip]').forEach(function (b) {
				b.classList.remove('on');
				b.setAttribute('aria-checked', 'false');
			});
			btn.classList.add('on');
			btn.setAttribute('aria-checked', 'true');
			var input = document.getElementById(kind === 'topic' ? 'fge-topic-input' : 'fge-pref-input');
			if (input) input.value = btn.getAttribute('data-val');
		});
	});

	// Quick-channel "Rückruf" → scroll to callback + focus phone
	document.querySelectorAll('[data-scroll-focus]').forEach(function (a) {
		a.addEventListener('click', function (e) {
			e.preventDefault();
			document.getElementById('callback')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
			document.getElementById(a.getAttribute('data-scroll-focus'))?.focus();
		});
	});

	// FAQ accordion
	document.querySelectorAll('.faq-q').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var item = btn.closest('.faq-item');
			var isOpen = item.classList.contains('open');
			document.querySelectorAll('.faq-item').forEach(function (el) {
				el.classList.remove('open');
				el.querySelector('.faq-q').setAttribute('aria-expanded', 'false');
				el.querySelector('.faq-toggle').textContent = '+';
			});
			if (!isOpen) {
				item.classList.add('open');
				btn.setAttribute('aria-expanded', 'true');
				btn.querySelector('.faq-toggle').textContent = '–';
			}
		});
	});
})();
</script>
<?php get_footer();
