<?php
/**
 * Dienstleister vor dem Angebot anfragen (Julius, 15.10.2026, Entwurf „Anfragen“).
 *
 * Shuttle, Catering, Fotograf usw. werden wie Plätze angefragt: aufnehmen,
 * per Mail oder Telefon anfragen, nachfassen, Rückmeldung mit Preisen
 * festhalten. Sie liegen in derselben Pipeline-Tabelle wie die Plätze, mit
 * kind = 'dl'. fge_venues_get() liefert sie nur auf ausdrücklichen Wunsch,
 * damit Platz-Absagen, Reservierungen und Aufgaben sie nie erfassen.
 *
 * Beauftragt werden sie wie bisher über die Positionen des angenommenen
 * Angebots (fge_xs_notify_providers_on_accept). Hier kommen nur die Absagen
 * an angefragte, aber nicht gebuchte Dienstleister dazu.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Stichwörter je Art, um passende Leistungen aus dem Bedarf vorzuschlagen. */
function fge_provider_type_keywords(): array {
	return [
		'pro'      => [ 'golflehrer', 'pro', 'kurs', 'training' ],
		'shuttle'  => [ 'shuttle', 'transfer', 'bus' ],
		'catering' => [ 'essen', 'lunch', 'dinner', 'frühstück', 'catering', 'buffet', 'grill', 'getränk', 'menü' ],
		'foto'     => [ 'foto', 'video' ],
		'technik'  => [ 'technik', 'beamer', 'branding', 'musik', 'dj' ],
	];
}

/** Welche Leistungen einen Dienstleister fragen: passend zur Art, sonst die Art selbst. */
function fge_provider_suggest_items( int $req, int $provider_id ): array {
	$type  = (string) get_post_meta( $provider_id, '_fge_provider_type', true );
	$words = fge_provider_type_keywords()[ $type ] ?? [];
	$out   = [];
	if ( function_exists( 'fge_cc_need_services' ) ) {
		foreach ( fge_cc_need_services( $req ) as $sv ) {
			foreach ( $words as $w ) {
				if ( false !== mb_stripos( $sv['label'], $w ) ) {
					$out[] = $sv['label'];
					break;
				}
			}
		}
	}
	if ( ! $out ) {
		$out[] = (string) ( fge_provider_types()[ $type ] ?? 'Leistung' );
	}
	return array_values( array_unique( $out ) );
}

/** Schlüssel einer Leistungszeile aus dem Label. */
function fge_provider_item_key( string $label ): string {
	return 'dl_' . substr( md5( mb_strtolower( trim( $label ) ) ), 0, 10 );
}

/** Dienstleister in die Anfrage aufnehmen. Liefert die Zeilen-ID, 0 wenn schon drin. */
function fge_provider_row_add( int $req, int $provider_id ): int {
	global $wpdb;
	if ( $req <= 0 || 'firmengolf_provider' !== get_post_type( $provider_id ) ) {
		return 0;
	}
	$wpdb->query( $wpdb->prepare(
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		'INSERT IGNORE INTO ' . fge_venues_table() . ' (request_id, partner_id, status, created_at, kind, price_basis) VALUES (%d, %d, %s, %s, %s, %s)',
		$req,
		$provider_id,
		'idee',
		current_time( 'mysql' ),
		'dl',
		'pauschal'
	) );
	$id = (int) $wpdb->insert_id;
	if ( $id > 0 ) {
		foreach ( fge_provider_suggest_items( $req, $provider_id ) as $label ) {
			fge_venue_item_set( $id, fge_provider_item_key( $label ), [ 'label' => $label, 'price_basis' => 'pauschal' ] );
		}
		fge_activity_add( $req, 'venue', sprintf( '%s als Dienstleister aufgenommen', get_the_title( $provider_id ) ) );
	}
	return $id;
}

/** Kontaktmail eines Dienstleisters, leer ohne gültige. */
function fge_provider_email( int $provider_id ): string {
	$mail = (string) get_post_meta( $provider_id, '_fge_provider_email', true );
	return is_email( $mail ) ? $mail : '';
}

/** Betreff und Einleitung der Dienstleister-Anfrage. */
function fge_provider_request_defaults( int $req, bool $followup = false ): array {
	$f = fge_venue_mail_facts( $req );
	if ( $followup ) {
		return [
			'subject' => 'Kurze Nachfrage: Anfrage für ein Firmenevent (' . $f['ref'] . ')',
			'intro'   => 'ich wollte kurz nachhaken, ob unsere Anfrage bei euch angekommen ist. Hier noch einmal alles auf einen Blick.',
		];
	}
	return [
		'subject' => 'Anfrage für ein Firmenevent ' . ( $f['dates'] ? 'am ' . $f['dates'][0] . ' ' : '' ) . '(' . $f['ref'] . ')',
		'intro'   => 'wir planen ein Firmenevent und würden gern bei euch anfragen.',
	];
}

/**
 * Anfrage an einen Dienstleister. Wie die Platz-Mail im Ton unter Kollegen,
 * ohne Firmennamen des Kunden (vor der Buchung bleibt er anonym).
 */
function fge_provider_send_request( int $req, int $row_id, array $opts = [] ): bool {
	$row = fge_venue_get( $row_id );
	if ( ! $row || 'dl' !== (string) ( $row['kind'] ?? '' ) ) {
		return false;
	}
	$pid = (int) $row['partner_id'];
	$to  = fge_provider_email( $pid );
	if ( '' === $to ) {
		return false;
	}
	$f        = fge_venue_mail_facts( $req );
	$co       = function_exists( 'fge_company' ) ? fge_company() : [];
	$followup = ! empty( $opts['followup'] );
	$defaults = fge_provider_request_defaults( $req, $followup );
	$subject  = trim( (string) ( $opts['subject'] ?? '' ) ) ?: $defaults['subject'];
	$intro    = trim( (string) ( $opts['intro'] ?? '' ) ) ?: $defaults['intro'];
	$contact  = trim( (string) get_post_meta( $pid, '_fge_provider_contact', true ) );
	$greet    = '' !== $contact ? 'Hallo ' . explode( ' ', $contact )[0] . ',' : 'Hallo,';

	$rowfn  = static fn( string $k, string $v ): string => '' === trim( $v ) ? '' : '<tr><td style="padding:5px 16px 5px 0;color:#555;white-space:nowrap;vertical-align:top;"><strong>' . esc_html( $k ) . '</strong></td><td style="padding:5px 0;color:#1a1a1a;">' . esc_html( $v ) . '</td></tr>';
	$labels = function_exists( 'fge_request_wish_date_labels' ) ? fge_request_wish_date_labels( $req ) : [];
	$place  = (string) get_post_meta( $req, '_fge_company_city', true ) ?: (string) get_post_meta( $req, '_fge_desired_region', true );
	$start  = trim( (string) get_post_meta( $req, '_fge_start_time', true ) );
	$rows   = $rowfn( 'Gruppe', $f['pax'] > 0 ? $f['pax'] . ' Personen' : '' )
		. $rowfn( 1 === count( $labels ) ? 'Termin' : 'Termine', implode( ', ', $labels ) )
		. $rowfn( 'Start', $start )
		. $rowfn( 'Region', $place );

	$list = '';
	foreach ( fge_venue_items_get( $row_id ) as $it ) {
		$note  = function_exists( 'fge_need_note' ) ? fge_need_note( $req, (string) $it['label'] ) : '';
		$list .= '<li style="margin-bottom:4px;">' . esc_html( (string) $it['label'] ) . ( '' !== $note ? ': ' . esc_html( $note ) : '' ) . '</li>';
	}
	$intro_h = implode( '', array_map( static fn( $p ) => '<p style="margin:0 0 16px;">' . nl2br( esc_html( $p ) ) . '</p>', preg_split( '/\R{2,}/', $intro ) ) );
	$content = '
		<p style="margin:0 0 16px;">' . esc_html( $greet ) . '</p>
		' . $intro_h . '
		<p style="margin:0 0 4px;font-weight:600;">Eckdaten</p>
		<table style="width:100%;border-collapse:collapse;font-size:14px;line-height:1.5;margin:0 0 16px;">' . $rows . '</table>
		' . ( '' !== $list ? '<p style="margin:0 0 4px;font-weight:600;">Angefragt ist</p><ul style="margin:0 0 16px;padding-left:20px;">' . $list . '</ul>' : '' ) . '
		<p style="margin:0 0 16px;">Eine kurze Antwort auf diese Mail genügt: ' . ( count( $labels ) > 1 ? 'welche Termine gehen' : 'geht der Termin' ) . ', und was kostet es bei euch (netto oder brutto, pauschal oder pro Person)?</p>
		<p style="margin:0;">Danke euch und viele Grüße<br>' . esc_html( (string) ( $co['managing_director'] ?? 'Firmengolf' ) ) . '</p>
	';
	$headers = [ 'Content-Type: text/html; charset=UTF-8' ];
	if ( ! empty( $co['email_owner'] ) && is_email( $co['email_owner'] ) ) {
		$headers[] = 'Bcc: ' . $co['email_owner'];
	}
	fge_mail_log_context( $req, $followup ? 'provider_followup' : 'provider_request' );
	$ok = (bool) wp_mail( $to, $subject, fge_email_wrap( $subject, $content ), $headers );
	fge_mail_log_context_clear();

	if ( $ok && $followup ) {
		fge_venue_update( $row_id, [ 'followup_at' => current_time( 'mysql' ) ] );
		fge_activity_add( $req, 'venue', sprintf( 'Nachfrage an %s gesendet', get_the_title( $pid ) ) );
	} elseif ( $ok ) {
		fge_venue_update( $row_id, [ 'status' => 'angefragt', 'channel' => 'mail', 'asked_at' => current_time( 'mysql' ) ] );
		fge_activity_add( $req, 'venue', sprintf( 'Anfrage an %s gesendet', get_the_title( $pid ) ) );
	}
	return $ok;
}

/** Absage an einen angefragten Dienstleister, der nicht gebraucht wird. */
function fge_provider_send_cancel( int $req, array $row, string $reason ): bool {
	$pid  = (int) $row['partner_id'];
	$mail = fge_provider_email( $pid );
	if ( '' === $mail || ! function_exists( 'fge_xs_provider_cancellation' ) ) {
		return false;
	}
	$labels = array_map( static fn( $it ) => (string) $it['label'], fge_venue_items_get( (int) $row['id'] ) );
	fge_mail_log_context( $req, 'provider_cancel' );
	$ok = fge_xs_provider_cancellation( $req, [
		'label'          => $labels ? implode( ', ', $labels ) : get_the_title( $pid ),
		'provider_name'  => explode( ' ', trim( (string) get_post_meta( $pid, '_fge_provider_contact', true ) ) )[0],
		'provider_email' => $mail,
	], $reason );
	fge_mail_log_context_clear();
	if ( $ok ) {
		fge_venue_update( (int) $row['id'], [ 'status' => 'abgesagt', 'reason' => 'Nicht gebucht' ] );
		fge_activity_add( $req, 'venue', sprintf( 'Absage an %s gesendet', get_the_title( $pid ) ) );
	}
	return $ok;
}

// Annahme: Dienstleister mit Position im gewählten Angebot sind gebucht (die
// Auftragsmail kommt über die Positionen), alle übrigen angefragten bekommen
// eine kurze Absage.
add_action( 'fge_offer_accepted', static function ( int $req ): void {
	// Nur Positionen, die der Kunde auch gewählt hat (Audit 15.10.2026).
	$used = [];
	$sel  = function_exists( 'fge_offer_selected_extras' ) ? fge_offer_selected_extras( $req ) : [];
	foreach ( (array) get_post_meta( $req, '_fge_extra_services', true ) as $i => $x ) {
		if ( ! empty( $x['provider_row'] ) && in_array( (int) ( $x['id'] ?? 0 ) ?: (int) $i, $sel, true ) ) {
			$used[ (int) $x['provider_row'] ] = true;
		}
	}
	foreach ( fge_venues_get( $req, 'dl' ) as $row ) {
		if ( isset( $used[ (int) $row['id'] ] ) ) {
			fge_venue_update( (int) $row['id'], [ 'status' => 'gewaehlt' ] );
		} elseif ( in_array( (string) $row['status'], [ 'angefragt', 'zugesagt' ], true ) ) {
			fge_provider_send_cancel( $req, $row, 'Der Kunde hat sich für eine Variante ohne diese Leistung entschieden.' );
		}
	}
}, 45 );

// Ablehnung: alle angefragten Dienstleister bekommen Bescheid.
add_action( 'fge_offer_declined', static function ( int $req ): void {
	foreach ( fge_venues_get( $req, 'dl' ) as $row ) {
		if ( in_array( (string) $row['status'], [ 'angefragt', 'zugesagt' ], true ) ) {
			fge_provider_send_cancel( $req, $row, 'Der Kunde hat das Event leider nicht gebucht.' );
		}
	}
}, 45 );

// ── Aktionen ─────────────────────────────────────────────────────────────────

add_action( 'admin_post_fge_cc_provider_add', static function (): void {
	$req = fge_cc_guard( 'fge_cc_provider_add' );
	$pid = absint( $_POST['provider_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	fge_cc_redirect( $req, fge_provider_row_add( $req, $pid ) > 0 ? 'provider_added' : 'venue_dupe' );
} );

// Neuen Dienstleister anlegen und gleich aufnehmen. Gleiche Felder wie im
// Verzeichnis unter /control/dienstleister/.
add_action( 'admin_post_fge_cc_provider_create', static function (): void {
	$req = fge_cc_guard( 'fge_cc_provider_create' );
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- fge_cc_guard prüft die Nonce.
	$name  = mb_substr( trim( sanitize_text_field( wp_unslash( $_POST['p_name'] ?? '' ) ) ), 0, 120 );
	$mail  = sanitize_email( wp_unslash( $_POST['p_email'] ?? '' ) );
	$phone = sanitize_text_field( wp_unslash( $_POST['p_phone'] ?? '' ) );
	$type  = sanitize_key( wp_unslash( $_POST['p_type'] ?? '' ) );
	if ( '' === $name || ( ! is_email( $mail ) && '' === trim( $phone ) ) ) {
		fge_cc_redirect( $req, 'provider_invalid' );
	}
	$pid = wp_insert_post( [ 'post_type' => 'firmengolf_provider', 'post_status' => 'publish', 'post_title' => $name ] );
	if ( ! $pid || is_wp_error( $pid ) ) {
		fge_cc_redirect( $req, 'provider_invalid' );
	}
	update_post_meta( $pid, '_fge_provider_type', isset( fge_provider_types()[ $type ] ) ? $type : 'sonstige' );
	update_post_meta( $pid, '_fge_provider_contact', sanitize_text_field( wp_unslash( $_POST['p_contact'] ?? '' ) ) );
	update_post_meta( $pid, '_fge_provider_region', sanitize_text_field( wp_unslash( $_POST['p_region'] ?? '' ) ) );
	update_post_meta( $pid, '_fge_provider_email', is_email( $mail ) ? $mail : '' );
	update_post_meta( $pid, '_fge_provider_phone', $phone );
	// phpcs:enable
	fge_activity_add( $req, 'system', sprintf( 'Dienstleister %s neu angelegt', $name ) );
	fge_provider_row_add( $req, (int) $pid );
	fge_cc_redirect( $req, 'provider_added' );
} );

// Leistung bei einem noch nicht angefragten Dienstleister an- oder abwählen.
add_action( 'admin_post_fge_cc_provider_item', static function (): void {
	$req   = fge_cc_guard( 'fge_cc_provider_item' );
	$row   = fge_cc_venue_from_post( $req );
	$label = mb_substr( trim( sanitize_text_field( wp_unslash( $_POST['label'] ?? '' ) ) ), 0, 120 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( 'idee' !== (string) $row['status'] || '' === $label ) {
		fge_cc_redirect( $req, 'nothing' );
	}
	// An- oder abwählen über den Text, die Schlüssel stammen aus verschiedenen Quellen.
	global $wpdb;
	$hit = null;
	foreach ( fge_venue_items_get( (int) $row['id'] ) as $it ) {
		if ( 'green_fee' !== (string) $it['wish_key'] && mb_strtolower( (string) $it['label'] ) === mb_strtolower( $label ) ) {
			$hit = $it;
		}
	}
	if ( $hit ) {
		$wpdb->delete( fge_venue_items_table(), [ 'id' => (int) $hit['id'] ] );
	} elseif ( 'dl' === (string) $row['kind'] ) {
		fge_venue_item_set( (int) $row['id'], fge_provider_item_key( $label ), [ 'label' => $label, 'price_basis' => 'pauschal' ] );
	} else {
		fge_venue_item_set( (int) $row['id'], fge_cc_need_item_key( $label ), [ 'label' => $label ] );
	}
	fge_cc_redirect( $req, 'saved' );
} );

// ── Oberfläche: Gruppe „Dienstleister“ im Schritt Anfragen ───────────────────

function fge_cc_providers_panel( int $req ): void {
	$rows = fge_venues_get( $req, 'dl' );
	$msg  = sanitize_key( wp_unslash( $_GET['msg'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$auto = isset( $_GET['ps'] ) || in_array( $msg, [ 'provider_added', 'provider_invalid' ], true ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	echo '<div class="cc-block">';
	echo '<div class="cc-block-head"><h3>Dienstleister</h3><button type="button" class="cc-btn" data-cc-drawer-open="cc-addprovider">Dienstleister hinzufügen</button></div>';
	if ( ! $rows ) {
		echo '<p class="cc-rowempty">Noch kein Dienstleister angefragt. Shuttle, Catering oder Fotograf findest du über „Dienstleister hinzufügen“.</p>';
	}
	foreach ( $rows as $row ) {
		fge_cc_venue_row( $req, $row );
	}
	$drafts = array_values( array_filter( $rows, static fn( $r ) => 'idee' === (string) $r['status'] && '' !== fge_provider_email( (int) $r['partner_id'] ) ) );
	if ( count( $drafts ) > 1 ) {
		echo '<div class="cc-stepfoot"><button type="button" class="cc-btn" data-cc-drawer-open="cc-askbulk-dl">Alle ' . (int) count( $drafts ) . ' Dienstleister per Mail anfragen</button></div>';
		fge_cc_mail_drawer( $req, 'cc-askbulk-dl', count( $drafts ) . ' Dienstleister anfragen', 'fge_cc_venue_ask_bulk',
			[ 'venue_ids[]' => array_map( static fn( $r ) => (int) $r['id'], $drafts ) ],
			array_map( static fn( $r ) => [ (string) $r['title'], fge_provider_email( (int) $r['partner_id'] ) ], $drafts ),
			fge_provider_request_defaults( $req ), 'Anfragen senden' );
	}
	echo '</div>';

	fge_cc_provider_add_drawer( $req, $rows, $auto );
}

/** Seitenpanel: aus dem Verzeichnis wählen oder neu anlegen. */
function fge_cc_provider_add_drawer( int $req, array $rows, bool $auto ): void {
	$have  = array_map( static fn( $r ) => (int) $r['partner_id'], $rows );
	$term  = trim( sanitize_text_field( wp_unslash( $_GET['ps'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$city  = (string) get_post_meta( $req, '_fge_company_city', true ) ?: (string) get_post_meta( $req, '_fge_desired_region', true );
	$types = fge_provider_types();

	fge_cc_drawer_open( 'cc-addprovider', 'Dienstleister hinzufügen', 'Aus dem Verzeichnis wählen oder neu anlegen', $auto );
	echo '<form class="cc-vsearch" method="get" action="' . esc_url( fge_cc_request_base_url( $req ) ) . '">';
	echo fge_cc_request_hidden_fields( $req ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<input type="text" name="ps" value="' . esc_attr( $term ) . '" placeholder="Name, Leistung oder Ort" aria-label="Dienstleister suchen">';
	echo '<button type="submit" class="cc-btn">Suchen</button></form>';

	// Treffer: Suchbegriff in Name, Art oder Region; ohne Suche die aus der Region zuerst.
	$hits = [];
	foreach ( fge_providers_all() as $id ) {
		$v   = fge_provider_values( (int) $id );
		$hay = get_the_title( $id ) . ' ' . ( $types[ $v['provider_type'] ] ?? '' ) . ' ' . $v['provider_region'];
		if ( '' !== $term && false === mb_stripos( $hay, $term ) ) {
			continue;
		}
		$near   = '' !== $city && '' !== $v['provider_region'] && ( false !== mb_stripos( $v['provider_region'], $city ) || false !== mb_stripos( $city, $v['provider_region'] ) );
		$hits[] = [ (int) $id, $v, $near ];
	}
	usort( $hits, static fn( $a, $b ) => (int) $b[2] <=> (int) $a[2] );
	$hits = array_slice( $hits, 0, 30 );

	echo '<ul class="cc-nearby">';
	foreach ( $hits as [ $id, $v, $near ] ) {
		$in = in_array( $id, $have, true );
		echo '<li class="cc-nearby-row' . ( $in ? ' is-inpipe' : '' ) . '"><div class="cc-nearby-main"><span class="cc-nearby-name">' . esc_html( get_the_title( $id ) ) . '</span>';
		echo '<span class="cc-muted">' . esc_html( implode( ' · ', array_filter( [ $types[ $v['provider_type'] ] ?? '', $v['provider_region'], fge_provider_price_text( $id ) ] ) ) ) . '</span></div>';
		echo '<span class="cc-nearby-dist">' . ( $near ? 'in der Region' : '' ) . '</span><span class="cc-nearby-pills">' . ( '' === fge_provider_email( $id ) ? fge_cc_pill( 'ohne Mail', 'warn' ) : '' ) . '</span><span class="cc-nearby-act">'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( $in ) {
			echo '<span class="cc-muted">schon in der Anfrage</span>';
		} else {
			fge_cc_button( 'fge_cc_provider_add', $req, 'Hinzufügen', [ 'fields' => [ 'provider_id' => $id ] ] );
		}
		echo '</span></li>';
	}
	echo '</ul>';
	if ( ! $hits ) {
		echo '<p class="cc-muted">' . esc_html( '' !== $term ? 'Kein Dienstleister zu „' . $term . '“ im Verzeichnis.' : 'Das Verzeichnis ist noch leer.' ) . '</p>';
	}

	$q = rawurlencode( ( '' !== $term ? $term : 'Eventservice' ) . ' ' . $city );
	echo '<form class="cc-form cc-newprovider" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="fge_cc_provider_create"><input type="hidden" name="request_id" value="' . (int) $req . '">';
	wp_nonce_field( 'fge_cc_provider_create_' . $req );
	echo '<div class="cc-block-head"><h3>Neuen Dienstleister anlegen</h3><a href="https://www.google.com/search?q=' . esc_attr( $q ) . '" target="_blank" rel="noopener">Online suchen</a></div>';
	echo '<div class="cc-fields cc-fields--2">';
	echo '<label class="cc-field"><span>Name</span><input type="text" name="p_name" required></label>';
	echo '<label class="cc-field"><span>Leistung</span><select name="p_type">';
	foreach ( $types as $k => $l ) {
		echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
	}
	echo '</select></label>';
	echo '<label class="cc-field"><span>Ansprechpartner</span><input type="text" name="p_contact"></label>';
	echo '<label class="cc-field"><span>Ort oder Region</span><input type="text" name="p_region" value="' . esc_attr( $city ) . '"></label>';
	echo '<label class="cc-field"><span>E-Mail</span><input type="email" name="p_email"></label>';
	echo '<label class="cc-field"><span>Telefon</span><input type="text" name="p_phone"></label>';
	echo '</div>';
	echo '<p class="cc-muted">Name und Mail oder Telefon reichen. Der Eintrag landet auch im Verzeichnis unter Dienstleister.</p>';
	echo '<p><button type="submit" class="cc-btn cc-btn--primary">Anlegen und hinzufügen</button></p>';
	echo '</form>';
	echo '<div class="cc-drawer-foot"><button type="button" class="cc-btn cc-btn--primary" data-cc-drawer-close>Fertig</button></div>';
	fge_cc_drawer_close();
}
