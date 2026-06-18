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
