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
		return 'Firmenevents auf dem Golfplatz | Teamevents, Turniere & Incentives | Firmengolf';
	}
	return $title;
} );
add_action( 'wp_head', function () {
	if ( ! is_front_page() ) {
		return;
	}
	$GLOBALS['fge_seo_meta_done'] = true;
	$desc = 'Firmenevents auf Deutschlands schönsten Golfplätzen: Teamevents, Firmenturniere, Schnupperkurse und Incentives. Passenden Platz finden und schnell anfragen.';
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

// Marken-Entität: Organization auf allen Seiten, WebSite auf der Startseite (für Google Knowledge + KI).
add_action( 'wp_head', function () {
	$org = [
		'@context'    => 'https://schema.org',
		'@type'       => 'Organization',
		'name'        => 'Firmengolf',
		'url'         => home_url( '/' ),
		'description' => 'Firmenevents auf Golfplätzen in ganz Deutschland: Teamevents, Firmenturniere, Schnupperkurse und Incentives. Eine Anfrage, ein Ansprechpartner, eine Rechnung.',
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
			'desc'    => $desc !== '' ? $desc : 'Firmenevents auf Golfplätzen in ganz Deutschland: Teamevents, Turniere und Incentives. Eine Anfrage, ein Ansprechpartner, eine Rechnung.',
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
			'desc'    => 'Tipps, Ideen und Praxis rund um Firmenevents auf dem Golfplatz: Teamevents, Turniere, Incentives und Corporate Benefits.',
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

	// Anfrage-Wizard (JS-Insel) — auf Individuelle-Events (inkl. Budget-Rechner) und der allgemeinen Anfrage-Seite.
	if ( is_page( [ 'individuelle-events', 'event-anfrage' ] ) ) {
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
			// Budget-Rechner-Config nur auf der Individuelle-Events-Seite; sonst null (Wizard läuft trotzdem).
			'bc'      => ( is_page( 'individuelle-events' ) && function_exists( 'fge_bc_config' ) ) ? fge_bc_config() : null,
			// Golfplatz-Namen für den optionalen „Konkreter Platz"-Dropdown.
			'places'  => function_exists( 'fge_get_public_place_names' ) ? fge_get_public_place_names() : [],
		] );
	}
} );

// ── Cookie-Consent: Klaro! (self-hosted, kein Drittanbieter-CDN) ─────────────
// Blockt einwilligungspflichtige Einbettungen (Google Maps) bis zur Zustimmung.
// Banner mit gleichwertigem „Ablehnen" (DSGVO/TTDSG). Konfig als JSON ins Frontend.
function fge_klaro_config(): array {
	$icon = '<svg viewBox="0 0 24 24" fill="none" stroke="#2C5036" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2a10 10 0 1 0 9.8 12 3.4 3.4 0 0 1-4.3-4.3A3.4 3.4 0 0 1 12.3 5.4 2 2 0 0 1 12 2z"/><circle cx="9.5" cy="10" r="1" fill="#2C5036" stroke="none"/><circle cx="14.5" cy="14" r="1" fill="#2C5036" stroke="none"/><circle cx="9.5" cy="15" r="1" fill="#2C5036" stroke="none"/></svg>';
	$notice = '<span class="fge-cc-head">' . $icon . 'Diese Webseite verwendet Cookies</span>'
		. '<span class="fge-cc-body">Wir verwenden Cookies und ähnliche Technologien, um die Nutzung unserer Website zu analysieren, Funktionen anzubieten und Inhalte wie Karten, Videos, unseren Terminkalender oder Newsletter-Formulare einzubinden. Manche Dienste übertragen dabei Daten an Anbieter wie Google oder HubSpot. Du entscheidest selbst, was geladen wird, und kannst deine Wahl jederzeit über „Cookie-Einstellungen" im Footer ändern.</span>';

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
					'description' => 'Hier entscheidest du, welche Dienste wir einbinden dürfen. Technisch notwendige Funktionen laufen immer. Alles andere – Statistik, Marketing und externe Inhalte – laden wir nur mit deiner Einwilligung.',
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
				'hubspotmeetings'  => [ 'title' => 'HubSpot Terminkalender', 'description' => 'Eingebetteter Terminbuchungs-Kalender von HubSpot. Setzt Cookies und überträgt Daten an HubSpot.' ],
				'hubspotcta'       => [ 'title' => 'HubSpot CTA / Tracking', 'description' => 'Marketing- und CTA-Elemente von HubSpot inkl. Nutzungs-Tracking.' ],
				'kitnewsletter'    => [ 'title' => 'Newsletter (Kit)', 'description' => 'Anmeldeformular unseres Newsletter-Anbieters Kit (ehem. ConvertKit). Setzt Cookies und überträgt Daten an Kit.' ],
			],
		],
		'services'               => [
			[ 'name' => 'wordpress',       'title' => 'WordPress (technisch notwendig)', 'purposes' => [ 'functional' ],     'required' => true,  'default' => true ],
			[ 'name' => 'googleanalytics', 'title' => 'Google Analytics',                 'purposes' => [ 'statistics' ],     'default'  => false, 'cookies' => [ '/^_ga.*/', '/^_gid$/' ] ],
			[ 'name' => 'googlemaps',      'title' => 'Google Maps',                      'purposes' => [ 'external-media' ], 'default'  => false ],
			[ 'name' => 'hubspotmeetings', 'title' => 'HubSpot Terminkalender',           'purposes' => [ 'external-media' ], 'default'  => false ],
			[ 'name' => 'hubspotcta',      'title' => 'HubSpot CTA / Tracking',           'purposes' => [ 'marketing' ],      'default'  => false ],
			[ 'name' => 'kitnewsletter',   'title' => 'Newsletter (Kit)',                 'purposes' => [ 'marketing' ],      'default'  => false ],
		],
	];
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
	wp_add_inline_style( 'fge-klaro-custom', '.klaro{--green1:#2C5036;--green2:#24412c;}' );
	wp_enqueue_script( 'fge-klaro', $base . 'klaro.js', [], $cver, true );
	wp_add_inline_script( 'fge-klaro', 'window.klaroConfig = ' . wp_json_encode( fge_klaro_config() ) . ';', 'before' );
} );

// Klaro per data-config automatisch initialisieren lassen.
add_filter( 'script_loader_tag', function ( $tag, $handle ) {
	if ( 'fge-klaro' === $handle ) {
		$tag = str_replace( ' src=', ' defer data-config="klaroConfig" src=', $tag );
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

// Google Analytics 4 – nur wenn Mess-ID gesetzt (Konstante FGE_GA4_ID oder Option fge_ga4_id).
// Klaro-verwaltet: lädt erst nach Einwilligung in „Statistik". Bis dahin inaktiv.
add_action( 'wp_head', function () {
	$ga = defined( 'FGE_GA4_ID' ) ? FGE_GA4_ID : (string) get_option( 'fge_ga4_id', '' );
	if ( ! $ga ) {
		return;
	}
	echo '<script type="text/plain" data-type="application/javascript" data-name="googleanalytics" data-src="https://www.googletagmanager.com/gtag/js?id=' . esc_attr( $ga ) . '"></script>' . "\n";
	echo '<script type="text/plain" data-type="application/javascript" data-name="googleanalytics">'
		. 'window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config",' . wp_json_encode( $ga ) . ');'
		. '</script>' . "\n";
}, 20 );
