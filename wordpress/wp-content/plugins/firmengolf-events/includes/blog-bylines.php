<?php
/**
 * Blog-Bylines: Autorenname + Portrait pro Beitrag (Julius, 2026-08-10).
 *
 * Autoren werden als Post-Meta (_fge_author_name) gepflegt, NICHT als WP-User:
 * die Redaktion (Marie Bauer, Sophie Theres Berchtold) braucht keine Logins,
 * und Gravatar ist sitewide deaktiviert. Bilder: aktuell Platzhalter aus der
 * Marken-Bildwelt, echte Portraits liefert Julius nach.
 *
 * Enthält eine einmalige Migration (Option-Flag): verteilt die Autoren
 * rotierend auf alle Beiträge und streut die Veröffentlichungsdaten
 * gleichmäßig über Januar bis Anfang August 2026 (Reihenfolge bleibt).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Autoren-Katalog: Name → Rolle + Portrait (Platzhalter aus der Bildwelt). */
function fge_blog_authors(): array {
	return [
		'Julius Klinzer'           => [ 'role' => 'Gründer, Firmengolf', 'img' => 'gruender-julius-klinzer.jpg' ],
		'Marie Bauer'              => [ 'role' => 'Redaktion, Firmengolf', 'img' => 'golferin-portrait.png' ],
		'Sophie Theres Berchtold'  => [ 'role' => 'Redaktion, Firmengolf', 'img' => 'golferin-stehend.png' ],
	];
}

/**
 * Byline eines Beitrags: ['name','role','img'] (img = fertige URL).
 * Fallback: WP-Autor mit Julius-Portrait (alle Alt-Beiträge liefen unter Julius).
 */
function fge_blog_author( int $post_id ): array {
	$authors = fge_blog_authors();
	$name    = (string) get_post_meta( $post_id, '_fge_author_name', true );
	if ( '' === $name || ! isset( $authors[ $name ] ) ) {
		$post = get_post( $post_id );
		$name = $post ? (string) get_the_author_meta( 'display_name', (int) $post->post_author ) : '';
		if ( ! isset( $authors[ $name ] ) ) {
			$name = 'Julius Klinzer';
		}
	}
	$a = $authors[ $name ];
	return [
		'name' => $name,
		'role' => $a['role'],
		'img'  => function_exists( 'fge_get_placeholder_image_url' ) ? fge_get_placeholder_image_url( $a['img'] ) : '',
	];
}

add_action( 'init', static function () {
	if ( get_option( 'fge_blog_bylines_migration' ) ) {
		return;
	}
	update_option( 'fge_blog_bylines_migration', '1', true );

	$posts = get_posts( [
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'ASC',
	] );
	if ( ! $posts ) {
		return;
	}
	$names = array_keys( fge_blog_authors() );
	$start = strtotime( '2026-01-15 09:00:00' );
	$end   = strtotime( '2026-08-05 09:00:00' );
	$n     = count( $posts );
	$stepS = $n > 1 ? (int) ( ( $end - $start ) / ( $n - 1 ) ) : 0;
	foreach ( $posts as $i => $p ) {
		update_post_meta( $p->ID, '_fge_author_name', $names[ $i % count( $names ) ] );
		$ts = $start + $i * $stepS;
		wp_update_post( [
			'ID'            => $p->ID,
			'post_date'     => gmdate( 'Y-m-d H:i:s', $ts ),
			'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $ts - HOUR_IN_SECONDS ),
			'edit_date'     => true,
		] );
	}
}, 25 );
