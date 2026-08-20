<?php

if (!defined('ABSPATH')) {
    exit;
}

// Title-Tag-Support: ohne das gibt WordPress in den klassischen Templates keinen
// <title> aus. Damit greifen unsere Seitentitel (pre_get_document_title) erst.
add_action( 'after_setup_theme', function () {
	add_theme_support( 'title-tag' );
} );

// SEO der Startseite: keyword-orientierter Title + Meta + OpenGraph.
add_filter( 'pre_get_document_title', function ( $title ) {
	if ( is_front_page() ) {
		return 'Firmenevents auf dem Golfplatz | Teamevents, Turniere & Workshops | Firmengolf';
	}
	return $title;
} );
add_action( 'wp_head', function () {
	if ( ! is_front_page() ) {
		return;
	}
	$GLOBALS['fge_seo_meta_done'] = true;
	$desc = 'Firmenevents auf Deutschlands schönsten Golfplätzen: Teamevents, Firmenturniere, Platzreife und Workshops. Passenden Platz finden und schnell anfragen.';
	echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
	echo '<meta property="og:type" content="website">' . "\n";
	echo '<meta property="og:title" content="Firmenevents auf dem Golfplatz | Firmengolf">' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
	echo '<meta property="og:url" content="' . esc_url( home_url( '/' ) ) . '">' . "\n";
	$og_img = fge_default_og_image_url();
	if ( $og_img ) {
		echo '<meta property="og:image" content="' . esc_url( $og_img ) . '">' . "\n";
	}
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
}, 1 );

// SEO der Partner-Strecke (Funnel-Audit Paket A): Landingpage + FAQ bekommen
// partner-gerichtete Titles/Descriptions statt der Kunden-Boilerplate.
add_filter( 'pre_get_document_title', function ( $title ) {
	if ( is_page( 'golfplatz-partner' ) ) {
		return 'Golfplatz-Partner werden: Firmenkunden ohne Fixkosten | Firmengolf';
	}
	if ( is_page( 'partner-faq' ) ) {
		return 'Partner-FAQ für Golfplätze: Kosten, Ablauf, Konditionen | Firmengolf';
	}
	return $title;
} );
add_action( 'wp_head', function () {
	if ( is_page( 'golfplatz-partner' ) ) {
		fge_render_seo_meta( [
			'title' => 'Golfplatz-Partner werden | Firmengolf',
			'desc'  => 'Firmenkunden für euren Golfplatz: Teamevents, Turniere, Platzreife. Kein Setup-Preis, für den Platz kostenlos, keine Exklusivität. In wenigen Werktagen live.',
			'url'   => get_permalink(),
		] );
	}
	if ( is_page( 'partner-faq' ) ) {
		fge_render_seo_meta( [
			'title' => 'Partner-FAQ für Golfplätze | Firmengolf',
			'desc'  => 'Alle Antworten für Golfplätze: kostenlos listen, keine Bindung, Provision zahlt der Kunde. Wie Anfragen, Terminabstimmung und Abrechnung bei Firmengolf laufen.',
			'url'   => get_permalink(),
		] );
	}
}, 5 );

// /partner/ deterministisch auf die Verkaufsseite statt WordPress' Canonical-Raten
// (das landete bisher zufällig auf der FAQ).
add_action( 'template_redirect', function () {
	if ( is_404() && trim( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' ) === 'partner' ) {
		wp_safe_redirect( home_url( '/golfplatz-partner/' ), 301 );
		exit;
	}
}, 1 );

// Marken-Entität: Organization auf allen Seiten, WebSite auf der Startseite (für Google Knowledge + KI).
add_action( 'wp_head', function () {
	$org = [
		'@context'    => 'https://schema.org',
		'@type'       => 'Organization',
		'name'        => 'Firmengolf',
		'url'         => home_url( '/' ),
		'description' => 'Firmenevents auf Golfplätzen in ganz Deutschland: Teamevents, Firmenturniere, Platzreife und Workshops. Eine Anfrage, ein Ansprechpartner, eine Rechnung.',
		'sameAs'      => [
			'https://www.instagram.com/firmengolf/',
			'https://www.facebook.com/Firmengolf',
			'https://www.linkedin.com/company/firmengolf/',
		],
	];
	$logo = function_exists( 'get_site_icon_url' ) ? get_site_icon_url( 512 ) : '';
	if ( $logo ) { $org['logo'] = $logo; }
	echo '<script type="application/ld+json">' . wp_json_encode( $org ) . '</script>' . "\n";

	if ( is_front_page() ) {
		$site = [
			'@context' => 'https://schema.org',
			'@type'    => 'WebSite',
			'name'     => 'Firmengolf',
			'url'      => home_url( '/' ),
		];
		echo '<script type="application/ld+json">' . wp_json_encode( $site ) . '</script>' . "\n";
	}
}, 2 );

// ── Zentrale SEO-Meta-Ausgabe ────────────────────────────────────────────────
// Wiederverwendbarer Renderer für Description + OpenGraph + Twitter. Setzt das
// Flag $GLOBALS['fge_seo_meta_done'], damit der generische Fallback unten nicht
// doppelt ausgibt. Die spezialisierten Templates (Event, City, Partner, …)
// setzen dieses Flag selbst.
// Default-Social-Sharebild (Attachment-ID in Option fge_default_og_image –
// domain-sicher, weil die URL zur Laufzeit aufgelöst wird).
function fge_default_og_image_url(): string {
	$id = (int) get_option( 'fge_default_og_image' );
	if ( ! $id ) {
		return '';
	}
	$url = wp_get_attachment_image_url( $id, 'full' );
	return $url ? $url : '';
}

function fge_render_seo_meta( array $a ): void {
	$GLOBALS['fge_seo_meta_done'] = true;
	$desc = trim( (string) ( $a['desc'] ?? '' ) );
	if ( $desc !== '' ) {
		echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
	}
	echo '<meta property="og:type" content="' . esc_attr( $a['og_type'] ?? 'website' ) . '">' . "\n";
	if ( ! empty( $a['title'] ) ) {
		echo '<meta property="og:title" content="' . esc_attr( $a['title'] ) . '">' . "\n";
	}
	if ( $desc !== '' ) {
		echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
	}
	if ( ! empty( $a['url'] ) ) {
		echo '<meta property="og:url" content="' . esc_url( $a['url'] ) . '">' . "\n";
	}
	$img = ! empty( $a['image'] ) ? $a['image'] : fge_default_og_image_url();
	if ( $img ) {
		echo '<meta property="og:image" content="' . esc_url( $img ) . '">' . "\n";
	}
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
}

// Generiert eine ~155-Zeichen-Description aus Excerpt bzw. Inhalt eines Posts.
function fge_generate_description( $post = null ): string {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	$text = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) );
	$text = trim( preg_replace( '/\s+/', ' ', (string) $text ) );
	if ( mb_strlen( $text ) > 160 ) {
		$text = rtrim( mb_substr( $text, 0, 157 ) ) . '…';
	}
	return $text;
}

// Fallback-SEO für alle Seiten/Posts ohne eigene Template-SEO (z. B. Über uns,
// Kontakt, Impressum, Blog-Liste). Läuft nach den Template-Hooks (Prio 20).
add_action( 'wp_head', function () {
	if ( ! empty( $GLOBALS['fge_seo_meta_done'] ) ) {
		return;
	}
	if ( is_singular() ) {
		$desc = fge_generate_description();
		fge_render_seo_meta( [
			'title'   => get_the_title() . ' | Firmengolf',
			'desc'    => $desc !== '' ? $desc : 'Firmenevents auf Golfplätzen in ganz Deutschland: Teamevents, Turniere und Workshops. Eine Anfrage, ein Ansprechpartner, eine Rechnung.',
			'url'     => get_permalink(),
			'og_type' => 'website',
		] );
	} elseif ( is_home() ) {
		$blog_url = get_permalink( (int) get_option( 'page_for_posts' ) );
		if ( $blog_url ) {
			echo '<link rel="canonical" href="' . esc_url( $blog_url ) . '">' . "\n";
		}
		fge_render_seo_meta( [
			'title'   => 'Blog | Firmengolf',
			'desc'    => 'Tipps, Ideen und Praxis rund um Firmenevents auf dem Golfplatz: Teamevents, Turniere, Workshops und Corporate Benefits.',
			'url'     => $blog_url,
			'og_type' => 'website',
		] );
	}
}, 20 );

// Funktionale/gated Seiten von der Indexierung ausnehmen.
add_action( 'wp_head', function () {
	$noindex = false;
	if ( function_exists( 'fge_noindex_page_slugs' ) && is_page( fge_noindex_page_slugs() ) ) {
		$noindex = true;
	}
	if ( is_page() && in_array( (string) get_page_template_slug(), [ 'template-angebot.php', 'template-termin.php' ], true ) ) {
		$noindex = true;
	}
	if ( $noindex ) {
		echo '<meta name="robots" content="noindex, follow">' . "\n";
	}
}, 1 );

// Autoren- und Datums-Archive dauerhaft auf noindex,follow: dünne, mit dem Blog
// duplizierte Übersichten. Über den wp_robots-Filter, damit nur EIN robots-Tag entsteht.
add_filter( 'wp_robots', function ( array $robots ): array {
	if ( is_author() || is_date() ) {
		$robots['noindex'] = true;
		$robots['follow']  = true;
	}
	return $robots;
} );

// Einheitlicher Marken-Suffix in <title> für Seiten ohne eigenes pre_get_document_title.
add_filter( 'document_title_parts', function ( $parts ) {
	if ( ! is_front_page() ) {
		$parts['site'] = 'Firmengolf';
	}
	return $parts;
} );
add_filter( 'document_title_separator', function () {
	return '|';
} );

// A11y: „Zum Inhalt springen"-Link als erstes fokussierbares Element (Ziel: #fge-main).
add_action( 'wp_body_open', function () {
	echo '<a class="fge-skip-link" href="#fge-main">Zum Inhalt springen</a>';
} );

/**
 * LCP-Hero-Bild vorladen: die Heros sind CSS-Backgrounds, die der Browser sonst
 * erst nach dem Stylesheet entdeckt → Preload zieht den Download deutlich vor.
 */
add_action( 'wp_head', function () {
	if ( ! function_exists( 'fge_get_placeholder_image_url' ) ) {
		return;
	}
	// Genau das Bild vorladen, das der Hero auch wirklich zeigt. Vorher lief hier
	// pauschal golfplatz-panorama.jpg (183 KB), das seit dem LP-Ausbau auf keiner
	// Format- oder Stadtseite mehr im Hero steht: Der Preload lud eine Datei, die
	// nie verwendet wurde, und das echte LCP-Bild bekam keine Priorität
	// (Audit 2026-08-12, betraf alle Ads-Zielseiten).
	$img       = '';
	$city_slug = (string) get_query_var( 'fge_city' );
	$fmt_slug  = (string) get_query_var( 'fge_format' );
	if ( is_front_page() ) {
		$img = 'hero-golfer-alpen.jpg';
	} elseif ( '' !== $city_slug ) {
		// Stadt- und Format×Stadt-Seiten zeigen beide das Stadtbild.
		$img = 'stadt-' . $city_slug . '.jpg';
	} elseif ( '' !== $fmt_slug ) {
		$formats = function_exists( 'fge_get_event_format_pages' ) ? fge_get_event_format_pages() : [];
		$img     = (string) ( $formats[ $fmt_slug ]['hero_img'] ?? '' );
	}
	// Nur vorladen, was es auch gibt, sonst lieber gar kein Preload.
	if ( '' !== $img && defined( 'FGE_DIR' ) && ! file_exists( FGE_DIR . 'assets/imagery/' . $img ) ) {
		$img = '';
	}
	if ( $img !== '' ) {
		printf(
			'<link rel="preload" as="image" href="%s" fetchpriority="high">' . "\n",
			esc_url( fge_get_placeholder_image_url( $img ) )
		);
	}
}, 2 );

add_action( 'wp_enqueue_scripts', function() {
	wp_enqueue_style(
		'firmengolf-child-style',
		get_stylesheet_uri(),
		[],
		wp_get_theme()->get( 'Version' )
	);
	wp_enqueue_style(
		'fge-frontend',
		plugins_url( 'assets/css/fge-frontend.css', WP_PLUGIN_DIR . '/firmengolf-events/firmengolf-events.php' ),
		[],
		defined( 'FGE_VERSION' ) ? FGE_VERSION : '1'
	);
	// Type-System (Redesign 2026-07): global, bewusst NACH fge-frontend geladen —
	// gewinnt bei gleicher Spezifität (Headline-/Highlight-Regeln).
	wp_enqueue_style(
		'fge-type-system',
		plugins_url( 'assets/css/fge-type-system.css', WP_PLUGIN_DIR . '/firmengolf-events/firmengolf-events.php' ),
		[ 'fge-frontend' ],
		defined( 'FGE_VERSION' ) ? FGE_VERSION : '1'
	);

	// Partner-Portal (neu): gekapseltes Stylesheet, nur auf der Portalseite.
	if ( is_page( 'partnerportal' ) ) {
		wp_enqueue_style(
			'fge-portal',
			plugins_url( 'assets/css/fge-portal.css', WP_PLUGIN_DIR . '/firmengolf-events/firmengolf-events.php' ),
			[ 'fge-frontend' ],
			defined( 'FGE_VERSION' ) ? FGE_VERSION : '1'
		);
	}

	if ( get_query_var( 'fge_termin' ) || get_query_var( 'fge_angebot' ) ) {
		wp_enqueue_style(
			'fge-termin',
			plugins_url( 'assets/css/fge-termin.css', WP_PLUGIN_DIR . '/firmengolf-events/firmengolf-events.php' ),
			[ 'fge-frontend' ],
			defined( 'FGE_VERSION' ) ? FGE_VERSION : '1'
		);
	}

	if ( is_page( 'partner-onboarding' ) ) {
		wp_enqueue_style(
			'fge-onboarding',
			plugins_url( 'assets/css/fge-onboarding.css', WP_PLUGIN_DIR . '/firmengolf-events/firmengolf-events.php' ),
			[ 'fge-frontend' ],
			defined( 'FGE_VERSION' ) ? FGE_VERSION : '1'
		);
	}

	if ( is_singular( 'firmengolf_partner' ) ) {
		// Reuses the portal "Platz" design (.fgpp) so the public page matches it 1:1.
		wp_enqueue_style(
			'fge-portal',
			plugins_url( 'assets/css/fge-portal.css', WP_PLUGIN_DIR . '/firmengolf-events/firmengolf-events.php' ),
			[ 'fge-frontend' ],
			defined( 'FGE_VERSION' ) ? FGE_VERSION : '1'
		);
		wp_enqueue_style(
			'fge-golfplatz',
			plugins_url( 'assets/css/fge-golfplatz.css', WP_PLUGIN_DIR . '/firmengolf-events/firmengolf-events.php' ),
			[ 'fge-portal' ],
			defined( 'FGE_VERSION' ) ? FGE_VERSION : '1'
		);
		wp_enqueue_script(
			'fge-golfplatz',
			plugins_url( 'assets/js/fge-golfplatz.js', WP_PLUGIN_DIR . '/firmengolf-events/firmengolf-events.php' ),
			[],
			defined( 'FGE_VERSION' ) ? FGE_VERSION : '1',
			true
		);
	}

	// Anfrage-Wizard (JS-Insel) — auf Individuelle-Events (inkl. Budget-Rechner), der
	// allgemeinen Anfrage-Seite UND den Stadt-Landingpages: dort öffnen die
	// „Event anfragen"-CTAs den Wizard als Overlay direkt auf der Seite, statt zur
	// Anfrage-Seite zu springen. Nach dem Schließen steht man wieder dort, wo man
	// war (Julius, 2026-08-20). Ohne JS fällt der Link auf die Anfrage-Seite zurück.
	$fge_is_city_lp = '' !== (string) get_query_var( 'fge_city' ); // Stadt- UND Format×Stadt-Seiten
	if ( is_page( [ 'individuelle-events', 'event-anfrage' ] ) || $fge_is_city_lp ) {
		wp_enqueue_script(
			'fge-individual',
			plugins_url( 'assets/js/fge-individual.js', WP_PLUGIN_DIR . '/firmengolf-events/firmengolf-events.php' ),
			[],
			defined( 'FGE_VERSION' ) ? FGE_VERSION : '1',
			true
		);
		wp_localize_script( 'fge-individual', 'FGE_IND', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'fge_general_request' ),
			'ft'      => function_exists( 'fge_form_trap_token' ) ? fge_form_trap_token() : '',
			// Google-Ads-Conversion (send_to) für den Anfrage-Success; leer = kein Call.
			'adsConv' => function_exists( 'fge_gads_send_to' ) ? fge_gads_send_to() : '',
			// Budget-Rechner-Config nur auf der Individuelle-Events-Seite; sonst null (Wizard läuft trotzdem).
			'bc'      => ( is_page( 'individuelle-events' ) && function_exists( 'fge_bc_config' ) ) ? fge_bc_config() : null,
			// Golfplatz-Namen für den optionalen „Konkreter Platz"-Dropdown.
			'places'  => function_exists( 'fge_get_public_place_names' ) ? fge_get_public_place_names() : [],
			// Einstiegsbild des Wizards (Driver am Abschlag, wie im Partner-Onboarding).
			'introImg' => function_exists( 'fge_get_placeholder_image_url' ) ? fge_get_placeholder_image_url( 'onboarding-abschlag.jpg' ) : '',
			// Wizard-Chrome + Erfolgsscreen (Logo, Julius-Portrait, persönliche Mail, Datenschutz-Link).
			'logo'        => function_exists( 'fge_get_logo_url' ) ? fge_get_logo_url() : '',
			'juliusImg'   => function_exists( 'fge_get_placeholder_image_url' ) ? fge_get_placeholder_image_url( 'gruender-julius-klinzer.jpg' ) : '',
			'juliusEmail' => 'julius@firmengolf-events.de',
			'privacyUrl'  => home_url( '/datenschutz/' ),
		] );
	}
} );

// ── Cookie-Consent: Klaro! (self-hosted, kein Drittanbieter-CDN) ─────────────
// Blockt einwilligungspflichtige Einbettungen (Google Maps) bis zur Zustimmung.
// Banner mit gleichwertigem „Ablehnen" (DSGVO/TTDSG). Konfig als JSON ins Frontend.
function fge_klaro_config(): array {
	$icon = '<svg viewBox="0 0 24 24" fill="none" stroke="#4279D1" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2a10 10 0 1 0 9.8 12 3.4 3.4 0 0 1-4.3-4.3A3.4 3.4 0 0 1 12.3 5.4 2 2 0 0 1 12 2z"/><circle cx="9.5" cy="10" r="1" fill="#4279D1" stroke="none"/><circle cx="14.5" cy="14" r="1" fill="#4279D1" stroke="none"/><circle cx="9.5" cy="15" r="1" fill="#4279D1" stroke="none"/></svg>';
	// Nur Dienste listen, die es wirklich gibt (Audit 2026-08-12): HubSpot-Kalender
	// ist ein reiner Link ohne Embed, HubSpot-CTA und Kit existieren gar nicht im
	// Code. Einwilligungen in Geister-Dienste sind wertlos und angreifbar.
	$services = [
		[ 'name' => 'wordpress',       'title' => 'WordPress (technisch notwendig)', 'purposes' => [ 'functional' ],     'required' => true,  'default' => true ],
		[ 'name' => 'googleanalytics', 'title' => 'Google Analytics',                 'purposes' => [ 'statistics' ],     'default'  => false, 'cookies' => [ '/^_ga.*/', '/^_gid$/' ] ],
		[ 'name' => 'googlemaps',      'title' => 'Google Maps',                      'purposes' => [ 'external-media' ], 'default'  => false ],
	];
	if ( fge_gads_id() ) {
		$services[] = [ 'name' => 'googleads', 'title' => 'Google Ads', 'purposes' => [ 'marketing' ], 'default' => false, 'cookies' => [ '/^_gcl.*/' ] ];
	}
	if ( fge_meta_pixel_id() ) {
		// _fbp/_fbc setzt Meta mit domain=.<Basisdomain>; Klaro löscht beim Widerruf
		// je Eintrag [Pattern, Pfad, Domain], daher explizit beide Domain-Varianten.
		// Achtung Klaro 0.7.22: String-Muster gelten nur als Regex, wenn sie mit ^
		// beginnen (die /…/-Schreibweise würde als Literal escaped und nie treffen).
		$host = preg_replace( '/^www\./', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$services[] = [
			'name'     => 'meta-pixel',
			'title'    => 'Meta Pixel',
			'purposes' => [ 'marketing' ],
			'default'  => false,
			'cookies'  => [ '^_fb[pc]$', [ '^_fb[pc]$', '/', '.' . $host ], [ '^_fb[pc]$', '/', $host ] ],
		];
	}
	$notice = '<span class="fge-cc-head">' . $icon . 'Diese Webseite verwendet Cookies</span>'
		. '<span class="fge-cc-body">Wir verwenden Cookies und ähnliche Technologien, um die Nutzung unserer Website zu analysieren und Inhalte wie Karten einzubinden. Manche Dienste übertragen dabei Daten an Google. Du entscheidest selbst, was geladen wird, und kannst deine Wahl jederzeit über „Cookie-Einstellungen" im Footer ändern oder widerrufen.</span>';

	return [
		'version'                => 2,
		'elementID'              => 'klaro',
		'styling'                => [ 'theme' => [ 'light', 'top', 'wide' ] ],
		'noAutoLoad'             => false,
		'htmlTexts'              => true,
		'embedded'               => false,
		'groupByPurpose'         => true,
		'storageMethod'          => 'cookie',
		'cookieName'             => 'fge_consent',
		'cookieExpiresAfterDays' => 180,
		'default'                => false,
		'mustConsent'            => false,
		'acceptAll'              => true,
		'hideDeclineAll'         => false,
		'hideLearnMore'          => false,
		'noticeAsModal'          => true,
		'lang'                   => 'de',
		'translations'           => [
			'de' => [
				'privacyPolicyUrl' => home_url( '/datenschutz/' ),
				'consentModal'     => [
					'title'       => 'Datenschutz-Einstellungen',
					'description' => 'Hier entscheidest du, welche Dienste wir einbinden dürfen. Technisch notwendige Funktionen laufen immer. Alles andere, Statistik, Marketing und externe Inhalte, laden wir nur mit deiner Einwilligung.',
				],
				'consentNotice'    => [
					'description' => $notice,
					'learnMore'   => 'Einstellungen',
				],
				'acceptAll'        => 'Alle akzeptieren',
				'acceptSelected'   => 'Auswahl speichern',
				'decline'          => 'Ablehnen',
				'ok'               => 'Alle akzeptieren',
				'close'            => 'Schließen',
				'save'             => 'Auswahl speichern',
				'purposes'         => [
					'functional'     => 'Notwendig',
					'statistics'     => 'Statistik',
					'marketing'      => 'Marketing',
					'external-media' => 'Externe Medien',
				],
				'service'          => [
					'disableAll'  => [ 'title' => 'Alle Dienste an/aus', 'description' => 'Aktiviert oder deaktiviert alle Dienste auf einmal.' ],
					'required'    => [ 'title' => '(immer aktiv)', 'description' => 'Dieser Dienst ist technisch notwendig und kann nicht deaktiviert werden.' ],
				],
				'wordpress'        => [ 'title' => 'WordPress (technisch notwendig)', 'description' => 'Session- und Sicherheits-Cookies sowie das Speichern deiner Cookie-Auswahl. Ohne diese funktioniert die Seite nicht.' ],
				'googleanalytics'  => [ 'title' => 'Google Analytics', 'description' => 'Statistik zur anonymisierten Auswertung der Websitenutzung. Setzt Cookies und überträgt Daten an Google.' ],
				'googlemaps'       => [ 'title' => 'Google Maps', 'description' => 'Interaktive Karten. Beim Laden wird deine IP-Adresse an Google übertragen.' ],
				'googleads'        => [ 'title' => 'Google Ads', 'description' => 'Conversion-Messung für unsere Google-Werbekampagnen. Setzt Cookies und überträgt Daten an Google.' ],
				'meta-pixel'       => [ 'title' => 'Meta Pixel', 'description' => 'Conversion-Messung für unsere Werbekampagnen auf Facebook und Instagram. Setzt Cookies und überträgt Daten an Meta.' ],
			],
		],
		'services'               => $services,
	];
}

// Google Ads (Kampagnenstart 2026-08, Konto 496-529-6905). Die IDs stehen ohnehin
// öffentlich im HTML, daher hier im Code statt in wp-config; wp-config-Konstanten
// hätten Vorrang (if !defined).
if ( ! defined( 'FGE_GADS_ID' ) ) {
	define( 'FGE_GADS_ID', 'AW-18381979281' );
}
if ( ! defined( 'FGE_GADS_CONVERSION_LABEL' ) ) {
	define( 'FGE_GADS_CONVERSION_LABEL', 'NOwpCMrfnt8cEJH9mr1E' );
}

// Google-Ads-Conversion-ID (AW-…): Konstante FGE_GADS_ID oder Option fge_gads_id.
// Solange leer, existiert weder der Klaro-Dienst „Google Ads" noch ein Ads-Tag.
function fge_gads_id(): string {
	return defined( 'FGE_GADS_ID' ) ? (string) FGE_GADS_ID : (string) get_option( 'fge_gads_id', '' );
}

// „send_to"-Wert fürs Conversion-Event (AW-ID/Label), leer wenn unvollständig.
function fge_gads_send_to(): string {
	$label = defined( 'FGE_GADS_CONVERSION_LABEL' ) ? (string) FGE_GADS_CONVERSION_LABEL : '';
	return ( fge_gads_id() && $label ) ? fge_gads_id() . '/' . $label : '';
}

// Meta Pixel (Datenquelle „FGE Web", Meta-Kampagnen 2026-08). ID steht ohnehin
// öffentlich im HTML, daher im Code; wp-config-Konstante hätte Vorrang.
if ( ! defined( 'FGE_META_PIXEL_ID' ) ) {
	define( 'FGE_META_PIXEL_ID', '1378606913654515' );
}

// Meta-Pixel-ID: Konstante FGE_META_PIXEL_ID oder Option fge_meta_pixel_id.
// Solange leer, existiert weder der Klaro-Dienst „Meta Pixel" noch das Pixel-Tag.
function fge_meta_pixel_id(): string {
	return defined( 'FGE_META_PIXEL_ID' ) ? (string) FGE_META_PIXEL_ID : (string) get_option( 'fge_meta_pixel_id', '' );
}

add_action( 'wp_enqueue_scripts', function () {
	$base = get_stylesheet_directory_uri() . '/assets/klaro/';
	$dir  = get_stylesheet_directory() . '/assets/klaro/';
	$cver  = file_exists( $dir . 'klaro.js' ) ? (string) filemtime( $dir . 'klaro.js' ) : '1';
	// Eigene mtime für klaro-custom.css, sonst bricht eine reine CSS-Änderung den Cache nicht.
	$ccver = file_exists( $dir . 'klaro-custom.css' ) ? (string) filemtime( $dir . 'klaro-custom.css' ) : $cver;
	wp_enqueue_style( 'fge-klaro', $base . 'klaro.css', [], $cver );
	wp_enqueue_style( 'fge-klaro-custom', $base . 'klaro-custom.css', [ 'fge-klaro' ], $ccver );
	// Markenfarbe für Klaro-eigene Elemente (Schalter etc.).
	wp_add_inline_style( 'fge-klaro-custom', '.klaro{--green1:#4279D1;--green2:#3768C0;}' );
	wp_enqueue_script( 'fge-klaro', $base . 'klaro.js', [], $cver, true );
	wp_add_inline_script( 'fge-klaro', 'window.klaroConfig = ' . wp_json_encode( fge_klaro_config() ) . ';', 'before' );
	// Consent Mode v2: Klaro-Entscheidungen (Save/Accept/Decline/Widerruf) sofort als
	// gtag consent-update nachziehen. Fällt ein Signal von granted auf denied zurück,
	// werden die zugehörigen Google-Cookies (_ga*, _gcl*) gelöscht. Pollt auf window.klaro,
	// weil Inline-'after'-Skripte vor dem defer-Klaro laufen.
	wp_add_inline_script( 'fge-klaro', "(function(){function clear(stats,ads){var host=location.hostname.replace(/^www\\./,'');document.cookie.split(';').forEach(function(c){var n=c.split('=')[0].trim();if(!((stats&&/^_ga/.test(n))||(ads&&/^_gcl/.test(n))))return;['','; domain=.'+host,'; domain='+location.hostname].forEach(function(d){document.cookie=n+'=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/'+d;});});}var prev=null;function apply(consents){if(!window.gtag||!window.fgeConsentSignals)return;var sig=window.fgeConsentSignals(consents||{});gtag('consent','update',sig);if(prev){clear(prev.analytics_storage==='granted'&&sig.analytics_storage==='denied',prev.ad_storage==='granted'&&sig.ad_storage==='denied');}prev=sig;}function boot(){if(!window.klaro||!window.klaro.getManager){setTimeout(boot,200);return;}var m=window.klaro.getManager();m.watch({update:function(mgr,evt){if('saveConsents'===evt||'applyConsents'===evt){apply(mgr.consents);}}});if(m.confirmed){apply(m.consents);}}boot();})();", 'after' );
	// Widerruf so einfach wie die Einwilligung (Art. 7 Abs. 3 DSGVO): Klaro blendet
	// im Einstellungs-Modal nach erteilter Einwilligung nur noch „Auswahl speichern"
	// ein, ein Ablehnen-Button fehlt dort komplett (Audit 2026-08-12). Wir hängen
	// ihn selbst in die Button-Zeile und nutzen dafür die Klaro-API.
	wp_add_inline_script( 'fge-klaro', "(function(){function inject(){var row=document.querySelector('.klaro .cookie-modal .cm-footer-buttons');if(!row||row.querySelector('.fge-cm-decline'))return;if(!window.klaro||!window.klaro.getManager)return;var b=document.createElement('button');b.type='button';b.className='cm-btn cm-btn-danger fge-cm-decline';b.textContent='Alle ablehnen';b.addEventListener('click',function(){var m=window.klaro.getManager();m.changeAll(false);m.saveAndApplyConsents();if(window.klaro.close){window.klaro.close();}});row.insertBefore(b,row.firstChild);}var mo=new MutationObserver(inject);function start(){mo.observe(document.body,{childList:true,subtree:true});inject();}if(document.body){start();}else{document.addEventListener('DOMContentLoaded',start);}})();", 'after' );
	// A11y/Agentic: Klaro-Cookie-Dialog bekommt einen barrierefreien Namen (role=dialog war ohne Name).
	wp_add_inline_script( 'fge-klaro', "(function(){function n(){var d=document.getElementById('klaro-cookie-notice')||document.querySelector('.cookie-modal-notice[role=dialog],.cookie-notice[role=dialog]');if(!d)return false;if(!d.getAttribute('aria-label')){d.removeAttribute('aria-labelledby');d.setAttribute('aria-label','Cookie-Hinweis');}return true;}if(n())return;var m=new MutationObserver(function(){if(n())m.disconnect();});var s=function(){m.observe(document.body,{childList:true,subtree:true});};if(document.body){s();}else{document.addEventListener('DOMContentLoaded',s);}})();", 'after' );
} );

// Perf: Manrope-Download des Eltern-Themes (twentytwentyfive) sparen (~52 KB render-blocking),
// das Preset aber ERHALTEN. Die globale Body-Schrift (theme.json styles.typography) zeigt auf
// var:preset|font-family|manrope – entfernt man die Family ganz, fällt ALLE Block-/Standard-
// Inhalte (Blog, Standardseiten) auf Browser-Serif zurück. Daher: fontFace streichen (kein
// Download) und die Family auf unsere bereits sitewide geladene Marken-Schrift (Roboto,
// Redesign 2026-07) umbiegen.
add_filter( 'wp_theme_json_data_theme', function ( $theme_json ) {
	$data = $theme_json->get_data();
	if ( ! empty( $data['settings']['typography']['fontFamilies']['theme'] ) ) {
		foreach ( $data['settings']['typography']['fontFamilies']['theme'] as &$f ) {
			$id = (string) ( ( $f['slug'] ?? '' ) . ( $f['name'] ?? '' ) );
			if ( false !== stripos( $id, 'manrope' ) ) {
				$f['fontFamily'] = '"Roboto", ui-sans-serif, system-ui, -apple-system, sans-serif';
				unset( $f['fontFace'] );
			}
		}
		unset( $f );
		return $theme_json->update_with( $data );
	}
	return $theme_json;
} );

// Klaro per data-config automatisch initialisieren lassen.
add_filter( 'script_loader_tag', function ( $tag, $handle ) {
	if ( 'fge-klaro' === $handle ) {
		$tag = str_replace( ' src=', ' defer data-config="klaroConfig" src=', $tag );
	}
	// Google-Maps-JS (Onboarding-Standort-Slide) hinter Klaro-Einwilligung „Google Maps"
	// legen: lädt erst nach Zustimmung, davor kein IP-Transfer an Google. Klaro tauscht
	// data-src→src und führt den callback=fgeObMapInit dann aus.
	if ( 'google-maps' === $handle ) {
		$tag = preg_replace( '/\stype=([\'"]).*?\1/', '', $tag );
		$tag = str_replace( ' src=', ' type="text/plain" data-type="application/javascript" data-name="googlemaps" data-src=', $tag );
	}
	return $tag;
}, 10, 2 );

// Gravatar vermeiden: lokalen Standard-Avatar ausliefern (kein IP-Transfer an Automattic).
add_filter( 'get_avatar_url', function ( $url ) {
	return get_stylesheet_directory_uri() . '/assets/img/avatar.svg';
}, 10, 1 );

// WordPress-Emoji-Skript (lädt von s.w.org) deaktivieren.
add_action( 'init', function () {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	add_filter( 'emoji_svg_url', '__return_false' );
} );

// ── Google Consent Mode v2 (1.9.107) ─────────────────────────────────────────
// Default-Denied ganz früh im <head>, vor allen Google-Skripten. Eine gespeicherte
// Klaro-Entscheidung (fge_consent-Cookie) wird sofort wieder als consent-update
// angewendet, noch vor DOMContentLoaded. Laufende Änderungen (Banner-Save,
// Widerruf) meldet der Klaro-Watcher (siehe Enqueue oben) nach.
// Signal-Mapping: Statistik→analytics_storage, Marketing→ad_*, Notwendig→functionality_storage.
add_action( 'wp_head', function () {
	$marketing = [];
	foreach ( fge_klaro_config()['services'] as $s ) {
		if ( in_array( 'marketing', $s['purposes'], true ) ) {
			$marketing[] = $s['name'];
		}
	}
	echo '<script>'
		. 'window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}'
		. 'gtag("consent","default",{ad_storage:"denied",ad_user_data:"denied",ad_personalization:"denied",analytics_storage:"denied",functionality_storage:"denied",security_storage:"granted",wait_for_update:500});'
		. 'window.fgeConsentSignals=function(c){var g=function(b){return b?"granted":"denied"};var mkt=' . wp_json_encode( $marketing ) . '.some(function(n){return !!c[n]});'
		. 'return {ad_storage:g(mkt),ad_user_data:g(mkt),ad_personalization:g(mkt),analytics_storage:g(!!c.googleanalytics),functionality_storage:g(!!c.wordpress)};};'
		. '(function(){var m=document.cookie.match(/(?:^|;\s*)fge_consent=([^;]*)/);if(!m)return;try{gtag("consent","update",window.fgeConsentSignals(JSON.parse(decodeURIComponent(m[1]))));}catch(e){}})();'
		. '</script>' . "\n";
}, 0 );

// Google Tag (gtag.js): GA4 und, sobald AW-ID gesetzt, Google Ads. Lädt seit
// Consent Mode v2 IMMER ungated; was gemessen bzw. gespeichert wird, steuern
// ausschließlich die Consent-Signale (Default denied, siehe Prio-0-Block).
add_action( 'wp_head', function () {
	$ga  = defined( 'FGE_GA4_ID' ) ? FGE_GA4_ID : (string) get_option( 'fge_ga4_id', '' );
	$ids = array_values( array_filter( [ $ga, fge_gads_id() ] ) );
	if ( ! $ids ) {
		return;
	}
	echo '<script async src="https://www.googletagmanager.com/gtag/js?id=' . esc_attr( $ids[0] ) . '"></script>' . "\n";
	$conf = 'gtag("js",new Date());';
	foreach ( $ids as $id ) {
		$conf .= 'gtag("config",' . wp_json_encode( $id ) . ');';
	}
	echo '<script>' . $conf . '</script>' . "\n";
}, 20 );

// ── Meta Pixel (1.9.126) ─────────────────────────────────────────────────────
// Lädt AUSSCHLIESSLICH nach Klaro-Einwilligung „Meta Pixel" (type="text/plain",
// Klaro aktiviert das Skript erst bei Zustimmung). Vorher gibt es keinen Aufruf
// an connect.facebook.net und kein _fbp-Cookie. PageView feuert damit direkt
// nach erteilter Einwilligung bzw. auf Folgeseiten beim Laden. Bewusst OHNE das
// <noscript>-Bild aus dem Meta-Snippet (das lüde bedingungslos, an der
// Einwilligung vorbei) und mit abgeschaltetem automatischem erweitertem
// Abgleich (autoConfig false, keine automatische Ereigniserkennung).
// Lead feuert in den beiden Anfrage-Success-Handlern (Event-Modal + Wizard,
// derselbe Auslöser wie die Google-Ads-Conversion) mit serverseitiger event_id
// aus fge_meta_event_id() für die spätere Conversions-API-Deduplizierung.
add_action( 'wp_head', function () {
	$px = fge_meta_pixel_id();
	if ( ! $px ) {
		return;
	}
	$id = wp_json_encode( $px );
	echo '<script type="text/plain" data-type="application/javascript" data-name="meta-pixel">'
		. 'if(!window.fgeFbqBooted){window.fgeFbqBooted=true;'
		. "!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');"
		. 'fbq("set","autoConfig",false,' . $id . ');'
		. 'fbq("init",' . $id . ',{},{autoConfig:false});'
		. 'fbq("track","PageView");'
		. '}'
		. '</script>' . "\n";
}, 21 );

// Kommentare sitewide deaktiviert (Julius, 2026-07-27): Der Blog braucht keine
// Community-Funktion, und Bots posteten Spam direkt an wp-comments-post.php
// (das Theme rendert gar kein Formular). comments_open=false lehnt auch diese
// Direkt-POSTs ab; comments_array blendet Altbestand aus, falls je einer
// freigegeben war. Reaktivierung: Block entfernen.
add_filter( 'comments_open', '__return_false', 20 );
add_filter( 'pings_open', '__return_false', 20 );
add_filter( 'comments_array', '__return_empty_array', 20 );
