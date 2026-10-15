<?php
/**
 * Oberfläche der Platz-Pipeline im Anfrage-Cockpit.
 *
 * Mehrere Plätze je Anfrage: aufnehmen, anfragen, Antwort festhalten,
 * Preise nebeneinander vergleichen, einen nehmen, den übrigen absagen.
 * Datenschicht liegt in cc-venues.php, die Mails in cc-venue-mails.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Seitenpanel von rechts (Design 15.10.2026). Ein <dialog>, damit Fokus,
 * Escape und Hintergrund-Sperre vom Browser kommen. Geöffnet über einen Knopf
 * mit data-cc-drawer-open="ID", ohne Skript bleibt der Inhalt unerreichbar,
 * deshalb liegt jeder Weg dorthin auch als normaler Link oder Formular vor.
 */
function fge_cc_drawer_open( string $id, string $title, string $sub = '', bool $auto = false ): void {
	echo '<dialog class="cc-drawer" id="' . esc_attr( $id ) . '" aria-labelledby="' . esc_attr( $id ) . '-t"' . ( $auto ? ' data-cc-drawer-auto' : '' ) . '>';
	echo '<div class="cc-drawer-head"><div><h2 id="' . esc_attr( $id ) . '-t">' . esc_html( $title ) . '</h2>';
	if ( '' !== $sub ) {
		echo '<p>' . esc_html( $sub ) . '</p>';
	}
	echo '</div><button type="button" class="cc-drawer-x" data-cc-drawer-close aria-label="Schließen">×</button></div>';
	// Fokus auf den Inhalt statt auf das X, sonst steht beim Öffnen ein Ring darum.
	echo '<div class="cc-drawer-body" tabindex="-1" autofocus>';
}

function fge_cc_drawer_close(): void {
	echo '</div></dialog>';
}

/**
 * Mail an einen oder mehrere Plätze, vor dem Senden lesbar und änderbar.
 * Betreff und Einleitung sind frei, Termine, Eckdaten und Leistungen setzt
 * die Mail selbst ein, die Anrede je Platz auch.
 */
function fge_cc_mail_drawer( int $req, string $id, string $title, string $action, array $fields, array $to, array $defaults, string $ok_label ): void {
	fge_cc_drawer_open( $id, $title, 'Wunschtermine, Eckdaten und Leistungen setzt die Mail selbst ein.' );
	echo '<form class="cc-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '"><input type="hidden" name="request_id" value="' . (int) $req . '">';
	wp_nonce_field( $action . '_' . $req );
	foreach ( $fields as $k => $v ) {
		foreach ( (array) $v as $one ) {
			echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( (string) $one ) . '">';
		}
	}
	echo '<div class="cc-tolist"><span class="cc-label">An</span>';
	foreach ( $to as [ $name, $mail ] ) {
		echo '<span><strong>' . esc_html( $name ) . '</strong> · ' . esc_html( '' !== $mail ? $mail : 'keine Adresse hinterlegt' ) . '</span>';
	}
	echo '</div>';
	echo '<label class="cc-field cc-field--wide"><span>Betreff</span><input type="text" name="mail_subject" value="' . esc_attr( $defaults['subject'] ) . '"></label>';
	echo '<label class="cc-field cc-field--wide"><span>Einleitung nach der Anrede</span><textarea name="mail_intro" rows="5">' . esc_textarea( $defaults['intro'] ) . '</textarea></label>';
	if ( count( $to ) > 1 ) {
		echo '<p class="cc-muted">Die Anrede wird je Platz eingesetzt.</p>';
	}
	echo '<div class="cc-drawer-foot"><button type="button" class="cc-btn" data-cc-drawer-close>Abbrechen</button><button type="submit" class="cc-btn cc-btn--primary">' . esc_html( $ok_label ) . '</button></div>';
	echo '</form>';
	fge_cc_drawer_close();
}

/** Entfernung Kundenstandort zu Platz in km, -1 ohne Koordinaten. */
function fge_cc_venue_km( int $req, int $pid ): float {
	static $anchors = [];
	if ( ! isset( $anchors[ $req ] ) ) {
		$anchors[ $req ] = function_exists( 'fge_request_geo_anchor' ) ? fge_request_geo_anchor( $req ) : [ 'source' => 'none' ];
	}
	$a = $anchors[ $req ];
	if ( 'none' === ( $a['source'] ?? 'none' ) || ! function_exists( 'fge_geo_distance' ) || $pid <= 0 ) {
		return -1.0;
	}
	$lat = (float) get_post_meta( $pid, '_fge_latitude', true );
	$lng = (float) get_post_meta( $pid, '_fge_longitude', true );
	if ( ( 0.0 === $lat || 0.0 === $lng ) && function_exists( 'fge_geo_lookup_plz' ) ) {
		$plz = (string) get_post_meta( $pid, '_fge_postal_code', true );
		$geo = '' !== $plz ? fge_geo_lookup_plz( $plz ) : null;
		[ $lat, $lng ] = $geo ? [ (float) $geo[0], (float) $geo[1] ] : [ 0.0, 0.0 ];
	}
	return ( 0.0 !== $lat && 0.0 !== $lng ) ? fge_geo_distance( (float) $a['lat'], (float) $a['lng'], $lat, $lng ) : -1.0;
}

/** Die Plätze einer Anfrage als Zeilen, wie im Entwurf „Anfragen". */
function fge_cc_venues_panel( int $req ): void {
	$venues = function_exists( 'fge_venues_get' ) ? fge_venues_get( $req ) : [];
	$chosen = (int) get_post_meta( $req, '_fge_assigned_partner_id', true );
	$msg    = sanitize_key( wp_unslash( $_GET['msg'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$auto   = isset( $_GET['vs'] ) || in_array( $msg, [ 'venue_added', 'venue_dupe', 'venue_exists', 'geo_saved' ], true ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	echo '<div class="cc-block">';
	echo '<div class="cc-block-head"><h3>Golfplätze</h3>';
	echo '<button type="button" class="cc-btn" data-cc-drawer-open="cc-addvenue">Golfplatz hinzufügen</button></div>';
	if ( ! $venues ) {
		echo '<p class="cc-rowempty">Noch kein Golfplatz. Plätze in der Nähe findest du über „Golfplatz hinzufügen".</p>';
	}
	foreach ( $venues as $v ) {
		fge_cc_venue_row( $req, $v );
	}
	// Mehrere Entwürfe auf einmal anfragen (15.10.2026).
	$drafts = array_values( array_filter( $venues, static fn( $v ) => 'idee' === (string) $v['status'] && '' !== fge_cc_partner_email( (int) $v['partner_id'] ) ) );
	if ( count( $drafts ) > 1 ) {
		echo '<div class="cc-stepfoot"><button type="button" class="cc-btn" data-cc-drawer-open="cc-askbulk">Alle ' . (int) count( $drafts ) . ' Entwürfe per Mail anfragen</button></div>';
		fge_cc_mail_drawer( $req, 'cc-askbulk', count( $drafts ) . ' Plätze anfragen', 'fge_cc_venue_ask_bulk',
			[ 'venue_ids[]' => array_map( static fn( $v ) => (int) $v['id'], $drafts ) ],
			array_map( static fn( $v ) => [ (string) $v['title'], fge_cc_partner_email( (int) $v['partner_id'] ) ], $drafts ),
			fge_venue_request_defaults( $req ), 'Anfragen senden' );
	}
	echo '</div>';

	$rest = function_exists( 'fge_venues_to_decline' ) ? fge_venues_to_decline( $req ) : [];
	if ( $chosen > 0 && $rest ) {
		echo '<div class="cc-decline-all">';
		echo '<p>' . (int) count( $rest ) . ' ' . ( 1 === count( $rest ) ? 'Platz wartet' : 'Plätze warten' ) . ' noch auf eine Rückmeldung von dir.</p>';
		fge_cc_button( 'fge_cc_venue_decline_all', $req, 'Allen übrigen absagen', [
			'confirm' => 'Allen übrigen Plätzen eine freundliche Absage schicken?',
		] );
		echo '<p class="cc-muted">Persönlich formuliert, mit dem Hinweis, dass wir wieder anfragen. Einzeln absagen geht je Platz unter „Mehr".</p>';
		echo '</div>';
	}

	$city = (string) get_post_meta( $req, '_fge_company_city', true );
	fge_cc_drawer_open( 'cc-addvenue', 'Golfplatz hinzufügen', 'Plätze sortiert nach Entfernung' . ( '' !== $city ? ' zu ' . $city : '' ), $auto );
	fge_cc_venue_nearby_block( $req );
	fge_cc_venue_add_form( $req, $venues );
	echo '<div class="cc-drawer-foot"><button type="button" class="cc-btn cc-btn--primary" data-cc-drawer-close>Fertig</button></div>';
	fge_cc_drawer_close();
}

/** Eine Zeile: wer, was angefragt, wie ist der Stand, was jetzt. */
function fge_cc_venue_row( int $req, array $v ): void {
	$id      = (int) $v['id'];
	$pid     = (int) $v['partner_id'];
	$status  = (string) $v['status'];
	$dl      = 'dl' === (string) ( $v['kind'] ?? 'platz' );
	$booked  = function_exists( 'fge_request_is_booked' ) && fge_request_is_booked( $req );
	if ( $dl ) {
		// Dienstleister: Kontakt und Art aus dem Verzeichnis, keine Entfernung.
		$mail  = function_exists( 'fge_provider_email' ) ? fge_provider_email( $pid ) : '';
		$stamm = false;
		$phone = (string) get_post_meta( $pid, '_fge_provider_phone', true );
		$sub   = implode( ' · ', array_filter( [ (string) ( fge_provider_types()[ (string) get_post_meta( $pid, '_fge_provider_type', true ) ] ?? 'Dienstleister' ), (string) get_post_meta( $pid, '_fge_provider_region', true ) ] ) );
	} else {
		$mail   = function_exists( 'fge_cc_partner_email' ) ? fge_cc_partner_email( $pid ) : '';
		$stamm  = function_exists( 'fge_partner_is_stammdaten' ) && fge_partner_is_stammdaten( $pid );
		$types  = function_exists( 'fge_catalog_partner_types' ) ? fge_catalog_partner_types() : [];
		$type   = function_exists( 'fge_partner_type' ) ? fge_partner_type( $pid ) : 'course';
		$tlabel = is_array( $types[ $type ] ?? null ) ? (string) ( $types[ $type ]['label'] ?? 'Golfplatz' ) : (string) ( $types[ $type ] ?? 'Golfplatz' );
		$km     = fge_cc_venue_km( $req, $pid );
		$phone  = (string) get_post_meta( $pid, '_fge_event_contact_phone', true ) ?: (string) get_post_meta( $pid, '_fge_main_contact_phone', true );
		$sub    = implode( ' · ', array_filter( [ 'course' === $type ? 'Golfplatz' : $tlabel, (string) get_post_meta( $pid, '_fge_city', true ), $km >= 0 ? number_format_i18n( round( $km ) ) . ' km' : '' ] ) );
	}
	$labels  = function_exists( 'fge_request_wish_date_labels' ) ? fge_request_wish_date_labels( $req ) : [];
	$dates   = function_exists( 'fge_venue_dates_get' ) ? fge_venue_dates_get( $id ) : [];

	// Stand in Worten, wie im Entwurf.
	$meta_cls = '';
	if ( 'idee' === $status ) {
		$meta = 'Noch nicht angefragt';
		$tag  = [ 'Entwurf', 'neutral' ];
	} elseif ( 'angefragt' === $status ) {
		$at   = (int) strtotime( (string) $v['asked_at'] );
		$days = $at > 0 ? (int) floor( ( fge_cc_today() - fge_cc_local_ts( wp_date( 'Y-m-d', $at ) ) ) / DAY_IN_SECONDS ) : 0;
		$how  = [ 'telefon' => 'telefonisch', 'portal' => 'über das Partnerportal' ][ (string) $v['channel'] ] ?? 'per Mail';
		$meta = 'Angefragt ' . $how
			. ( $at > 0 ? ' am ' . wp_date( 'd.m.', $at ) : '' ) . ( $days >= 2 ? ' · seit ' . $days . ' Tagen ohne Antwort' : '' );
		$fu   = (int) strtotime( (string) ( $v['followup_at'] ?? '' ) );
		$meta .= $fu > 0 ? ' · Follow-up am ' . wp_date( 'd.m.', $fu ) : '';
		$meta_cls = $days >= 4 ? ' is-warn' : '';
		$tag  = $fu > 0 ? [ 'Follow-up', 'warn' ] : [ 'Angefragt', 'warn' ];
	} elseif ( in_array( $status, [ 'zugesagt', 'gewaehlt' ], true ) ) {
		$free = [];
		foreach ( $labels as $idx => $label ) {
			if ( 1 === ( $dates[ $idx ]['available'] ?? null ) ) {
				$free[] = $label;
			}
		}
		$alt  = trim( (string) ( $dates[ $labels ? (int) min( array_keys( $labels ) ) : 1 ]['note'] ?? '' ) );
		$meta = 'Frei am ' . ( $free ? implode( ', ', $free ) : 'keinem Wunschtermin' ) . ( '' !== $alt ? ' · Alternative: ' . $alt : '' );
		if ( '' !== (string) ( $v['reserved_until'] ?? '' ) ) {
			$meta .= ' · reserviert bis ' . wp_date( 'd.m.Y', (int) strtotime( (string) $v['reserved_until'] ) );
		}
		$tag = 'gewaehlt' === $status ? [ $booked ? 'Gebucht' : 'Gewählt', 'good' ] : [ 'Zusage', 'good' ];
	} elseif ( 'keine' === $status ) {
		$meta = 'Keine Antwort nach Anfrage ' . ( 'telefon' === (string) $v['channel'] ? 'am Telefon' : 'per Mail' );
		$tag  = [ 'Keine Antwort', 'neutral' ];
	} else {
		$meta = 'Abgesagt' . ( '' !== trim( (string) $v['reason'] ) ? ': ' . trim( (string) $v['reason'] ) : '' );
		$tag  = [ 'Absage', 'bad' ];
	}

	echo '<article class="cc-prow is-' . esc_attr( $status ) . '">';
	echo '<div class="cc-prow-main">';
	echo '<div class="cc-prow-top"><span class="cc-prow-name">' . esc_html( (string) $v['title'] ) . '</span>';
	echo '<span class="cc-muted">' . esc_html( $sub ) . '</span>';
	if ( '' !== $phone ) {
		echo '<a class="cc-prow-tel" href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ) . '">' . esc_html( $phone ) . '</a>';
	}
	if ( $stamm ) {
		echo fge_cc_pill( 'Stammdaten', 'neutral' ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</div>';

	// Angefragte Leistungen als Chips.
	$asked = array_values( array_filter( (array) $v['items'], static fn( $it ) => 'green_fee' !== (string) $it['wish_key'] ) );
	echo '<div class="cc-minichips">';
	if ( $dl && 'idee' === $status ) {
		// Noch nicht angefragt: Leistungen an- und abwählen, wie im Entwurf.
		$on_labels = array_map( static fn( $it ) => mb_strtolower( (string) $it['label'] ), $asked );
		$choices   = array_map( static fn( $it ) => (string) $it['label'], $asked );
		foreach ( function_exists( 'fge_cc_need_services' ) ? fge_cc_need_services( $req ) : [] as $sv ) {
			$choices[] = $sv['label'];
		}
		foreach ( array_unique( $choices ) as $label ) {
			$on = in_array( mb_strtolower( $label ), $on_labels, true );
			fge_cc_button( 'fge_cc_provider_item', $req, $label, [ 'class' => 'cc-minichip cc-minichip--btn' . ( $on ? ' is-on' : ' is-off-soft' ), 'fields' => [ 'venue_id' => $id, 'label' => $label ] ] );
		}
	} else {
		if ( ! $dl ) {
			echo '<span class="cc-minichip is-on">Event und Platznutzung</span>';
		}
		foreach ( $asked as $it ) {
			$off = in_array( $status, [ 'zugesagt', 'gewaehlt' ], true ) && ! (int) $it['available'];
			echo '<span class="cc-minichip' . ( $off ? ' is-off' : ' is-on' ) . '"' . ( $off ? ' title="nicht im Angebot"' : '' ) . '>' . esc_html( (string) $it['label'] ) . '</span>';
		}
	}
	echo '</div>';

	echo '<span class="cc-prow-meta' . esc_attr( $meta_cls ) . '">' . esc_html( $meta ) . '</span>';

	if ( in_array( $status, [ 'zugesagt', 'gewaehlt' ], true ) ) {
		$money = static fn( $p, $gross, $basis ) => number_format_i18n( (float) $p, 2 ) . ' € ' . ( (int) $gross ? 'brutto' : 'netto' ) . ( 'person' === $basis ? ' p. P.' : ' pauschal' );
		echo '<div class="cc-pricebox">';
		if ( ! $dl ) {
			echo '<div><span>Event und Platznutzung</span><span>' . esc_html( (float) $v['price'] > 0 ? $money( $v['price'], $v['price_gross'], $v['price_basis'] ) : 'kein Preis' ) . '</span></div>';
		}
		foreach ( $asked as $it ) {
			if ( ! (int) $it['available'] ) {
				continue;
			}
			echo '<div><span>' . esc_html( (string) $it['label'] ) . '</span><span>' . esc_html( (float) $it['price'] > 0 ? $money( $it['price'], $it['price_gross'], $it['price_basis'] ) : 'kein Preis genannt' ) . '</span></div>';
		}
		if ( '' !== trim( (string) $v['note'] ) ) {
			echo '<p>' . esc_html( (string) $v['note'] ) . '</p>';
		}
		echo '</div>';
	} elseif ( '' !== trim( (string) $v['note'] ) ) {
		echo '<span class="cc-muted">' . esc_html( (string) $v['note'] ) . '</span>';
	}

	if ( '' === $mail ) {
		echo '<span class="cc-warn">Keine Kontaktmail hinterlegt, ' . ( $dl ? 'nur telefonisch erreichbar.' : 'dieser Platz geht nur telefonisch.' ) . '</span>';
	}
	// Kontakt ergänzen nur, wo die Mail fehlt; sonst stünde es an jeder
	// Stammdaten-Zeile und der Platz käme nie aus der Liste heraus.
	if ( '' === $mail && ! $dl ) {
		fge_cc_partner_contact_form( $pid, $req );
	}
	if ( ! $dl && function_exists( 'fge_venue_catalog_status_html' ) ) {
		echo fge_venue_catalog_status_html( $v ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</div>';

	// Rechts: Stand, Hauptaktion, Nebenaktion, Mehr.
	echo '<div class="cc-prow-act">';
	echo fge_cc_pill( $tag[0], $tag[1] ); // phpcs:ignore WordPress.Security.EscapeOutput
	$drawer = 'cc-reply-' . $id;
	$more   = [];
	if ( 'idee' === $status ) {
		if ( '' !== $mail ) {
			echo '<button type="button" class="cc-btn cc-btn--primary" data-cc-drawer-open="cc-ask-' . $id . '">Per Mail anfragen</button>';
		}
		$more[] = [ 'fge_cc_venue_ask', 'Telefonisch angefragt', [ 'venue_id' => $id, 'how' => 'telefon' ], '' ];
		$more[] = [ 'fge_cc_venue_remove', 'Entfernen', [ 'venue_id' => $id ], 'Diesen Platz aus der Liste nehmen?' ];
	} elseif ( 'angefragt' === $status ) {
		echo '<button type="button" class="cc-btn cc-btn--primary" data-cc-drawer-open="' . esc_attr( $drawer ) . '">Rückmeldung erfassen</button>';
		if ( '' !== $mail ) {
			echo '<button type="button" class="cc-btn" data-cc-drawer-open="cc-fu-' . $id . '">Follow-up senden</button>';
		}
		$more[] = [ 'fge_cc_venue_noanswer', 'Keine Antwort', [ 'venue_id' => $id ], (string) $v['title'] . ' auf keine Antwort setzen? Der Platz bekommt keine Mail.' ];
	} else {
		echo '<button type="button" class="cc-btn" data-cc-drawer-open="' . esc_attr( $drawer ) . '">Bearbeiten</button>';
	}
	if ( $dl && in_array( $status, [ 'angefragt', 'zugesagt', 'keine', 'abgesagt' ], true ) && ! $booked ) {
		$more[] = [ 'fge_cc_venue_remove', 'Entfernen', [ 'venue_id' => $id ], 'Diesen Dienstleister aus der Anfrage nehmen? Er bekommt keine Mail.' ];
	}
	if ( ! $dl && in_array( $status, [ 'zugesagt', 'gewaehlt' ], true ) && '' !== $mail && ! $booked ) {
		$sum_at = (string) ( $v['summary_at'] ?? '' ) ?: (string) ( $v['reservation_at'] ?? '' );
		$more[] = [ 'fge_cc_venue_summary', '' !== $sum_at ? 'Bestätigung erneut senden' : 'Bestätigung senden', [ 'venue_id' => $id ], 'Dem Platz per Mail bestätigen, was wir notiert haben (Termine, Preise, offene Punkte), mit der Bitte zu reservieren?' ];
	}
	if ( ! $dl && 'zugesagt' === $status && ! $booked ) {
		$more[] = [ 'fge_cc_venue_choose', 'Diesen Platz nehmen', [ 'venue_id' => $id ], $v['title'] . ' als Platz für diese Anfrage festlegen?' ];
	}
	if ( ! $dl && in_array( $status, [ 'angefragt', 'zugesagt' ], true ) && '' !== $mail ) {
		$more[] = [ 'fge_cc_venue_decline', 'Absagen', [ 'venue_id' => $id ], 'Diesem Platz absagen?' ];
	}
	if ( $more ) {
		echo '<details class="cc-menu"><summary class="cc-btn">Mehr</summary><div class="cc-menu-list">';
		foreach ( $more as [ $action, $label, $fields, $confirm ] ) {
			fge_cc_button( $action, $req, $label, [ 'class' => 'cc-menu-item', 'fields' => $fields, 'confirm' => $confirm ] );
		}
		echo '</div></details>';
	}
	echo '</div>';
	echo '</article>';

	// Antwort ist jederzeit änderbar (Julius, 28.09.: „das müsste ich immer anpassen
	// können"), auch beim gewählten oder schon zugesagten Platz.
	if ( 'idee' === $status && '' !== $mail ) {
		fge_cc_mail_drawer( $req, 'cc-ask-' . $id, 'Anfrage an ' . (string) $v['title'], 'fge_cc_venue_ask', [ 'venue_id' => $id, 'how' => 'mail' ],
			[ [ (string) $v['title'], $mail ] ], $dl ? fge_provider_request_defaults( $req ) : fge_venue_request_defaults( $req ), 'Anfrage senden' );
	}
	if ( 'angefragt' === $status && '' !== $mail ) {
		fge_cc_mail_drawer( $req, 'cc-fu-' . $id, 'Follow-up an ' . (string) $v['title'], 'fge_cc_venue_followup', [ 'venue_id' => $id ],
			[ [ (string) $v['title'], $mail ] ], $dl ? fge_provider_request_defaults( $req, true ) : fge_venue_request_defaults( $req, true ), 'Follow-up senden' );
	}
	if ( 'idee' !== $status ) {
		fge_cc_drawer_open( $drawer, 'Rückmeldung von ' . (string) $v['title'], implode( ' · ', array_filter( [ (string) get_post_meta( $pid, $dl ? '_fge_provider_contact' : '_fge_main_contact_name', true ), $phone ] ) ) );
		fge_cc_venue_reply_form( $req, $v, true );
		fge_cc_drawer_close();
	}
}

/**
 * Antworten des Platzes je Wunschtermin als Pill-Zeile, plus Alternativvorschlag.
 * Erscheint, sobald mindestens ein Termin beantwortet ist oder der Platz zugesagt hat.
 */
function fge_cc_venue_dates_line( int $req, array $v ): void {
	if ( ! function_exists( 'fge_request_wish_date_labels' ) || ! function_exists( 'fge_venue_dates_get' ) ) {
		return;
	}
	$id     = (int) $v['id'];
	$status = (string) $v['status'];
	$labels = fge_request_wish_date_labels( $req );
	$dates  = fge_venue_dates_get( $id );
	if ( ! $labels ) {
		return;
	}
	$answered = array_filter( $dates, static fn( $d ) => null !== $d['available'] );
	if ( ! $answered && ! in_array( $status, [ 'zugesagt', 'gewaehlt' ], true ) ) {
		return;
	}

	echo '<p class="cc-dates"><span class="cc-muted">Termine:</span>';
	foreach ( $labels as $idx => $label ) {
		$avail = $dates[ $idx ]['available'] ?? null;
		if ( 1 === $avail ) {
			$pill = fge_cc_pill( $idx . ' geht', 'good' );
		} elseif ( 0 === $avail ) {
			$pill = fge_cc_pill( $idx . ' geht nicht', 'bad' );
		} else {
			$pill = fge_cc_pill( $idx . ' offen', 'neutral' );
		}
		echo '<span title="' . esc_attr( (string) $label ) . '">' . $pill . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	$first = (int) min( array_keys( $labels ) );
	$alt   = trim( (string) ( $dates[ $first ]['note'] ?? '' ) );
	if ( '' !== $alt ) {
		echo '<span class="cc-dates-alt">Alternativvorschlag: ' . esc_html( $alt ) . '</span>';
	}
	echo '</p>';

	if ( $answered && in_array( $status, [ 'zugesagt', 'gewaehlt' ], true ) && ! fge_venue_free_dates( $id ) ) {
		echo '<p class="cc-warn">Zugesagt, aber kein Wunschtermin frei.</p>';
	}
}

/**
 * Kalkulation je Platz: Einkauf, Netto, Vorschlag mit Aufschlag, Verkaufspreis
 * und wer die Position organisiert. Nur für Plätze, die zugesagt haben.
 */
function fge_cc_venue_calc_form( int $req, array $v, bool $open = false ): void {
	if ( ! function_exists( 'fge_venue_sale_from_cost' ) ) {
		return;
	}
	$id     = (int) $v['id'];
	$markup = (float) ( $v['markup_percent'] ?? 20 );
	$vat    = defined( 'FGE_VAT_PERCENT' ) ? (float) FGE_VAT_PERCENT : 19.0;
	$net_of = static fn( float $cost, bool $gross ): float => $gross ? $cost / ( 1 + $vat / 100 ) : $cost;
	$money  = static fn( float $x ): string => number_format_i18n( $x, 2 ) . ' €';
	$whole  = static fn( float $x ): string => number_format_i18n( $x, 0 ) . ' €';
	$field  = static fn( float $x ): string => $x > 0 ? number_format_i18n( $x, 0 ) : '';
	$pct    = rtrim( rtrim( number_format_i18n( $markup, 2 ), '0' ), ',' );

	$pax = 0;
	if ( function_exists( 'fge_venue_mail_facts' ) ) {
		$pax = (int) fge_venue_mail_facts( $req )['pax'];
	}
	$mult = $pax > 0 ? $pax : 1;
	$sum  = 0.0;

	echo $open ? '<div class="cc-calc">' : '<details class="cc-details cc-calc"><summary>Kalkulation</summary>';
	echo '<form class="cc-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="fge_cc_venue_calc">';
	echo '<input type="hidden" name="request_id" value="' . (int) $req . '">';
	echo '<input type="hidden" name="venue_id" value="' . $id . '">';
	wp_nonce_field( 'fge_cc_venue_calc_' . $req );

	echo '<label class="cc-field cc-field--markup"><span>Aufschlag % (für alle Zeilen)</span><input type="text" name="markup" class="cc-calc-markup" value="' . esc_attr( $pct ) . '" inputmode="decimal"></label>';

	// Die Felder hängen zusammen (Julius, 28.09.): Aufschlag ändert alle Verkaufspreise,
	// Marge je Zeile ändert ihren Verkaufspreis, ein Verkaufspreis ändert seine Marge.
	// Gespeichert werden Aufschlag und Verkaufspreise, die Marge je Zeile ist abgeleitet.
	$margin_of = static fn( float $net, float $sale ): string => $net > 0 && $sale > 0 ? rtrim( rtrim( number_format_i18n( ( $sale / $net - 1 ) * 100, 1 ), '0' ), ',' ) : '';
	echo '<table class="cc-calc-table"><thead><tr>';
	echo '<th>Position</th><th>Einkauf</th><th>Netto</th><th>Marge %</th><th>Verkauf netto</th><th>Organisation</th>';
	echo '</tr></thead><tbody>';

	// Grundpreis: Event und Platznutzung, immer vom Platz organisiert.
	$cost  = (float) $v['price'];
	$gross = (bool) (int) $v['price_gross'];
	$pp    = 'person' === (string) $v['price_basis'];
	$basis = $pp ? 'p.P.' : 'pauschal';
	if ( $cost > 0 ) {
		$sugg = fge_venue_sale_from_cost( $cost, $gross, $markup );
		$sale = (float) $v['sale_price'] > 0 ? (float) $v['sale_price'] : $sugg;
		$sum += $pp ? $sale * $mult : $sale;
		$net = $net_of( $cost, $gross );
		echo '<tr class="cc-calc-row" data-net="' . esc_attr( (string) round( $net, 4 ) ) . '" data-pp="' . ( $pp ? 1 : 0 ) . '">';
		echo '<td>Event und Platznutzung <span class="cc-muted">' . esc_html( $basis ) . '</span></td>';
		echo '<td>' . esc_html( $money( $cost ) . ' ' . ( $gross ? 'brutto' : 'netto' ) ) . '</td>';
		echo '<td>' . esc_html( $money( $net ) ) . '</td>';
		echo '<td><input type="text" class="cc-calc-margin" value="' . esc_attr( $margin_of( $net, $sale ) ) . '" inputmode="decimal" aria-label="Marge Grundpreis"></td>';
		echo '<td><input type="text" name="sale_base" class="cc-calc-sale" value="' . esc_attr( $field( $sale ) ) . '" inputmode="decimal" aria-label="Verkauf Grundpreis netto"></td>';
		echo '<td class="cc-muted">Platz</td>';
		echo '</tr>';
	} else {
		echo '<tr class="is-muted"><td>Event und Platznutzung</td><td colspan="5">kein Preis</td></tr>';
	}

	// Positionen: nur was der Platz anbietet, Green Fee steckt im Grundpreis.
	foreach ( (array) $v['items'] as $it ) {
		$key = (string) $it['wish_key'];
		if ( 'green_fee' === $key || ! (int) $it['available'] ) {
			continue;
		}
		$icost  = (float) $it['price'];
		$igross = (bool) (int) $it['price_gross'];
		$ipp    = 'person' === (string) $it['price_basis'];
		$ibasis = $ipp ? 'p.P.' : 'pauschal';
		if ( $icost <= 0 ) {
			echo '<tr class="is-muted"><td>' . esc_html( (string) $it['label'] ) . '</td><td colspan="5">kein Preis</td></tr>';
			continue;
		}
		$isugg = fge_venue_sale_from_cost( $icost, $igross, $markup );
		$isale = (float) ( $it['sale_price'] ?? 0 ) > 0 ? (float) $it['sale_price'] : $isugg;
		$org   = 'extern' === (string) ( $it['organizer'] ?? 'platz' ) ? 'extern' : 'platz';
		$sum  += $ipp ? $isale * $mult : $isale;
		$inet = $net_of( $icost, $igross );
		echo '<tr class="cc-calc-row" data-net="' . esc_attr( (string) round( $inet, 4 ) ) . '" data-pp="' . ( $ipp ? 1 : 0 ) . '">';
		echo '<td>' . esc_html( (string) $it['label'] ) . ' <span class="cc-muted">' . esc_html( $ibasis ) . '</span></td>';
		echo '<td>' . esc_html( $money( $icost ) . ' ' . ( $igross ? 'brutto' : 'netto' ) ) . '</td>';
		echo '<td>' . esc_html( $money( $inet ) ) . '</td>';
		echo '<td><input type="text" class="cc-calc-margin" value="' . esc_attr( $margin_of( $inet, $isale ) ) . '" inputmode="decimal" aria-label="Marge ' . esc_attr( (string) $it['label'] ) . '"></td>';
		echo '<td><input type="text" name="sale_item[' . esc_attr( $key ) . ']" class="cc-calc-sale" value="' . esc_attr( $field( $isale ) ) . '" inputmode="decimal" aria-label="Verkauf netto ' . esc_attr( (string) $it['label'] ) . '"></td>';
		echo '<td><select name="organizer[' . esc_attr( $key ) . ']" aria-label="Organisation ' . esc_attr( (string) $it['label'] ) . '">';
		echo '<option value="platz"' . selected( 'platz', $org, false ) . '>Platz</option>';
		echo '<option value="extern"' . selected( 'extern', $org, false ) . '>extern</option>';
		echo '</select></td>';
		echo '</tr>';
	}
	echo '</tbody></table>';

	echo '<p class="cc-calc-sum" data-pax="' . (int) $pax . '">Angebotssumme netto ca. <strong class="cc-calc-total">' . esc_html( $whole( $sum ) ) . '</strong>'
		. ( $pax > 0 ? ' bei ' . (int) $pax . ' Teilnehmern' : ' <span class="cc-muted">(ohne Teilnehmerzahl, p.P.-Zeilen einfach gezählt)</span>' )
		. '</p>';
	echo '<p class="cc-muted">Aufschlag, Marge und Verkaufspreis hängen zusammen: ändere eines, die anderen ziehen nach. Verkaufspreise auf volle Euro.</p>';
	echo '<p><button type="submit" class="cc-btn cc-btn--primary">Kalkulation speichern</button></p>';
	echo '</form>' . ( $open ? '</div>' : '</details>' );

	static $script_done = false;
	if ( ! $script_done ) {
		$script_done = true;
		?>
		<script>
		(function(){
			function num(s){ s = String(s || '').replace(/[\s€%]/g, ''); if (s.indexOf(',') !== -1) { s = s.replace(/\./g, '').replace(',', '.'); } var f = parseFloat(s); return isNaN(f) ? 0 : f; }
			function pct(n){ return (Math.round(n * 10) / 10).toLocaleString('de-DE', { maximumFractionDigits: 1 }); }
			function euro(n){ return Math.round(n).toLocaleString('de-DE') + ' €'; }
			function saleFrom(net, m){ return Math.ceil(net * (1 + m / 100) - 0.00001); }
			document.querySelectorAll('.cc-calc form').forEach(function(form){
				var rows = form.querySelectorAll('.cc-calc-row'), markup = form.querySelector('.cc-calc-markup'),
				    total = form.querySelector('.cc-calc-total'), pax = parseInt((form.querySelector('.cc-calc-sum') || {}).dataset ? form.querySelector('.cc-calc-sum').dataset.pax : '0', 10) || 0;
				function sum(){
					var s = 0;
					rows.forEach(function(r){ var sale = num(r.querySelector('.cc-calc-sale').value); s += (r.dataset.pp === '1' && pax > 0) ? sale * pax : sale; });
					if (total) { total.textContent = euro(s); }
				}
				rows.forEach(function(r){
					var net = parseFloat(r.dataset.net) || 0, mi = r.querySelector('.cc-calc-margin'), si = r.querySelector('.cc-calc-sale');
					mi.addEventListener('input', function(){ if (net > 0) { si.value = saleFrom(net, num(mi.value)); sum(); } });
					si.addEventListener('input', function(){ if (net > 0 && num(si.value) > 0) { mi.value = pct((num(si.value) / net - 1) * 100); sum(); } });
				});
				if (markup) {
					markup.addEventListener('input', function(){
						var m = num(markup.value);
						rows.forEach(function(r){ var net = parseFloat(r.dataset.net) || 0; if (net > 0) { r.querySelector('.cc-calc-margin').value = pct(m); r.querySelector('.cc-calc-sale').value = saleFrom(net, m); } });
						sum();
					});
				}
				sum();
			});
		})();
		</script>
		<?php
	}
}

/** Antwort des Platzes festhalten, mit Preis je Position. */
function fge_cc_venue_reply_form( int $req, array $v, bool $bare = false ): void {
	$id  = (int) $v['id'];
	$pid = (int) $v['partner_id'];

	$answered = in_array( (string) $v['status'], [ 'zugesagt', 'gewaehlt', 'abgesagt' ], true );
	if ( ! $bare ) {
		echo '<details class="cc-details cc-venue-reply"><summary>' . ( $answered ? 'Antwort bearbeiten (Termine, Preise, Positionen)' : 'Antwort festhalten' ) . '</summary>';
	}
	echo '<form class="cc-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="fge_cc_venue_reply">';
	echo '<input type="hidden" name="request_id" value="' . (int) $req . '">';
	echo '<input type="hidden" name="venue_id" value="' . $id . '">';
	wp_nonce_field( 'fge_cc_venue_reply_' . $req );

	echo '<p class="cc-radios">';
	$is_no = 'abgesagt' === (string) $v['status'];
	echo '<label class="cc-inline"><input type="radio" name="answer" value="zusagt"' . ( $is_no ? '' : ' checked' ) . '> Zusage</label>';
	echo '<label class="cc-inline"><input type="radio" name="answer" value="absagt"' . ( $is_no ? ' checked' : '' ) . '> Absage</label>';
	echo '</p>';

	// Je Wunschtermin: geht, geht nicht, offen. Der Alternativvorschlag des
	// Platzes landet als Notiz beim ersten Termin-Index.
	$labels = function_exists( 'fge_request_wish_date_labels' ) ? fge_request_wish_date_labels( $req ) : [];
	$dates  = function_exists( 'fge_venue_dates_get' ) ? fge_venue_dates_get( $id ) : [];
	if ( $labels ) {
		$first = (int) min( array_keys( $labels ) );
		echo '<p class="cc-kicker">Wunschtermine</p><div class="cc-dategrid">';
		foreach ( $labels as $idx => $label ) {
			$avail = $dates[ $idx ]['available'] ?? null;
			$name  = 'date_avail[' . (int) $idx . ']';
			echo '<div class="cc-daterow">';
			echo '<span class="cc-daterow-label">' . (int) $idx . '. ' . esc_html( (string) $label ) . '</span>';
			echo '<span class="cc-daterow-opts">';
			echo '<label class="cc-inline"><input type="radio" name="' . esc_attr( $name ) . '" value="1"' . checked( 1, $avail, false ) . '> geht</label>';
			echo '<label class="cc-inline"><input type="radio" name="' . esc_attr( $name ) . '" value="0"' . checked( 0, $avail, false ) . '> geht nicht</label>';
			echo '<label class="cc-inline"><input type="radio" name="' . esc_attr( $name ) . '" value=""' . ( null === $avail ? ' checked' : '' ) . '> offen</label>';
			echo '</span></div>';
		}
		echo '</div>';
		echo '<label class="cc-field cc-field--wide"><span>Alternativvorschlag</span>';
		echo '<input type="text" name="date_alt" value="' . esc_attr( (string) ( $dates[ $first ]['note'] ?? '' ) ) . '" placeholder="Falls kein Wunschtermin passt: was der Platz stattdessen anbietet"></label>';
	}

	$hint = function_exists( 'fge_venue_price_hint' ) ? fge_venue_price_hint( $pid, 'green_fee' ) : '';
	// Dienstleister haben keinen Grundpreis, nur ihre Leistungen.
	$is_dl = 'dl' === (string) ( $v['kind'] ?? 'platz' );
	echo '<div class="cc-fields"' . ( $is_dl ? ' hidden' : '' ) . '>';
	echo '<label class="cc-field"><span>Preis Event und Platznutzung</span>';
	// Gespeicherte Werte vorbelegen, damit Nachtragen nichts leert.
	$cur_price = (float) $v['price'] > 0 ? number_format( (float) $v['price'], 2, ',', '.' ) : '';
	$cur_gross = (int) $v['price_gross'] ? '1' : '0';
	$cur_basis = 'pauschal' === (string) $v['price_basis'] ? 'pauschal' : 'person';
	echo '<input type="text" name="price" value="' . esc_attr( $cur_price ) . '" placeholder="' . esc_attr( '' !== $hint ? $hint : 'z. B. 49,00' ) . '"></label>';
	echo '<label class="cc-field"><span>brutto oder netto</span><select name="price_gross"><option value="1"' . selected( $cur_gross, '1', false ) . '>brutto</option><option value="0"' . selected( $cur_gross, '0', false ) . '>netto</option></select></label>';
	echo '<label class="cc-field"><span>Basis</span><select name="price_basis"><option value="person"' . selected( $cur_basis, 'person', false ) . '>pro Person</option><option value="pauschal"' . selected( $cur_basis, 'pauschal', false ) . '>pauschal</option></select></label>';
	echo '</div>';

	$items = (array) $v['items'];
	$extra = array_values( array_filter( $items, static fn( $it ) => 'green_fee' !== (string) $it['wish_key'] ) );
	if ( $extra ) {
		echo '<p class="cc-kicker">Weitere Positionen</p><div class="cc-itemgrid">';
		foreach ( $extra as $it ) {
			$key   = (string) $it['wish_key'];
			$ihint = function_exists( 'fge_venue_price_hint' ) ? fge_venue_price_hint( $pid, $key ) : '';
			echo '<div class="cc-item">';
			$i_on    = (int) $it['available'] > 0;
			$i_price = (float) $it['price'] > 0 ? number_format( (float) $it['price'], 2, ',', '.' ) : '';
			$i_gross = (int) $it['price_gross'] ? '1' : '0';
			$i_basis = 'pauschal' === (string) $it['price_basis'] ? 'pauschal' : 'person';
			echo '<label class="cc-inline"><input type="checkbox" name="item_available[' . esc_attr( $key ) . ']" value="1"' . ( $i_on ? ' checked' : '' ) . '> ' . esc_html( (string) $it['label'] ) . '</label>';
			echo '<input type="text" name="item_price[' . esc_attr( $key ) . ']" value="' . esc_attr( $i_price ) . '" placeholder="' . esc_attr( '' !== $ihint ? $ihint : 'Preis' ) . '">';
			echo '<select name="item_gross[' . esc_attr( $key ) . ']"><option value="1"' . selected( $i_gross, '1', false ) . '>brutto</option><option value="0"' . selected( $i_gross, '0', false ) . '>netto</option></select>';
			echo '<select name="item_basis[' . esc_attr( $key ) . ']"><option value="person"' . selected( $i_basis, 'person', false ) . '>p.P.</option><option value="pauschal"' . selected( $i_basis, 'pauschal', false ) . '>pauschal</option></select>';
			echo '</div>';
		}
		echo '</div>';
		echo '<p class="cc-muted">Häkchen weg heißt: bietet dieser Platz nicht an.</p>';
	}

	// Extra-Posten, die der Platz zusätzlich anbietet (15.10.2026).
	echo '<p class="cc-kicker">Extra Posten</p><div class="cc-extras" data-cc-clone-wrap>';
	echo '<div class="cc-item cc-extra" data-cc-clone-row>';
	echo '<input type="text" name="extra_label[]" placeholder="z. B. Feuerwerk" aria-label="Extra Posten">';
	echo '<input type="text" name="extra_price[]" placeholder="Preis" inputmode="decimal" aria-label="Preis Extra Posten">';
	echo '<select name="extra_gross[]" aria-label="brutto oder netto"><option value="1">brutto</option><option value="0">netto</option></select>';
	echo '<select name="extra_basis[]" aria-label="Basis"><option value="pauschal">pauschal</option><option value="person">p.P.</option></select>';
	echo '</div></div>';
	echo '<p><button type="button" class="cc-btn cc-btn--dashed" data-cc-clone>Weiteren Posten hinzufügen</button></p>';
	echo '<p class="cc-muted">Extra Posten zählen nur bei einer Zusage. Gespeicherte stehen danach oben bei den Positionen.</p>';

	echo '<label class="cc-field cc-field--wide"><span>Grund bei Absage</span>';
	echo '<input type="text" name="reason" list="cc-reasons" value="' . esc_attr( (string) $v['reason'] ) . '" placeholder="z. B. Termin belegt"></label>';
	echo '<datalist id="cc-reasons">';
	foreach ( fge_venue_reasons() as $r ) {
		echo '<option value="' . esc_attr( $r ) . '"></option>';
	}
	echo '</datalist>';

	echo '<label class="cc-field cc-field--wide"><span>Notiz aus dem Gespräch</span>';
	echo '<textarea name="note" rows="2" placeholder="Was sonst noch gesagt wurde">' . esc_textarea( (string) $v['note'] ) . '</textarea></label>';

	if ( $bare ) {
		echo '<div class="cc-drawer-foot"><button type="button" class="cc-btn" data-cc-drawer-close>Abbrechen</button><button type="submit" class="cc-btn cc-btn--primary">Rückmeldung speichern</button></div>';
		echo '</form>';
		return;
	}
	echo '<p><button type="submit" class="cc-btn cc-btn--primary">Antwort speichern</button></p>';
	echo '</form></details>';
}

/**
 * Hidden-Felder, damit ein GET-Formular auf der Anfrage-Seite bleibt.
 * Browser werfen den Query-Teil der action-URL weg, deshalb wandern req
 * (und ohne Permalinks fge_cc) als eigene Felder mit.
 */
function fge_cc_request_hidden_fields( int $req ): string {
	$url   = fge_cc_request_url( $req );
	$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
	parse_str( $query, $args );
	$out = '';
	foreach ( (array) $args as $k => $val ) {
		$out .= '<input type="hidden" name="' . esc_attr( (string) $k ) . '" value="' . esc_attr( (string) $val ) . '">';
	}
	return $out;
}

/** Basis-URL der Anfrage ohne Query, als action eines GET-Formulars. */
function fge_cc_request_base_url( int $req ): string {
	$url = fge_cc_request_url( $req );
	return (string) strtok( $url, '?' );
}

/** Platz suchen und aufnehmen: Name, Ort oder PLZ, kein Riesen-Select mehr. */
function fge_cc_venue_add_form( int $req, array $venues ): void {
	$have = array_map( static fn( $v ) => (int) $v['partner_id'], $venues );
	$term = isset( $_GET['vs'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		? sanitize_text_field( wp_unslash( (string) $_GET['vs'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		: (string) get_post_meta( $req, '_fge_company_city', true );
	$term = trim( $term );

	echo '<div class="cc-vsearch-wrap">';
	echo '<p class="cc-kicker">Platz suchen</p>';
	echo '<form class="cc-vsearch" method="get" action="' . esc_url( fge_cc_request_base_url( $req ) ) . '">';
	echo fge_cc_request_hidden_fields( $req ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<input type="text" name="vs" value="' . esc_attr( $term ) . '" placeholder="Platz suchen (Name, Ort, PLZ)" aria-label="Platz suchen (Name, Ort, PLZ)">';
	echo '<button type="submit" class="cc-btn">Suchen</button>';
	echo '</form>';

	if ( '' === $term ) {
		echo '<p class="cc-muted">Name, Ort oder PLZ eintippen, dann erscheinen hier die passenden Plätze.</p></div>';
		return;
	}

	$hits = function_exists( 'fge_venue_search_partners' ) ? fge_venue_search_partners( $term, $have, 25 ) : [];
	if ( ! $hits ) {
		echo '<p class="cc-muted">Kein Platz zu „' . esc_html( $term ) . '“ gefunden. Anders schreiben oder oben über die Nähe-Liste aufnehmen.</p></div>';
		return;
	}

	// Entfernung vom selben Standort wie die Nähe-Liste; Treffer ohne Koordinaten
	// nehmen den PLZ-Mittelpunkt, ganz ohne Ort landen sie am Ende.
	$anchor = function_exists( 'fge_request_geo_anchor' ) ? fge_request_geo_anchor( $req ) : [ 'source' => 'none' ];
	if ( 'none' !== ( $anchor['source'] ?? 'none' ) && function_exists( 'fge_geo_distance' ) ) {
		foreach ( $hits as &$h ) {
			$lat = (float) get_post_meta( (int) $h['id'], '_fge_latitude', true );
			$lng = (float) get_post_meta( (int) $h['id'], '_fge_longitude', true );
			if ( ( 0.0 === $lat || 0.0 === $lng ) && '' !== (string) $h['plz'] && function_exists( 'fge_geo_lookup_plz' ) ) {
				$geo = fge_geo_lookup_plz( (string) $h['plz'] );
				[ $lat, $lng ] = $geo ? [ (float) $geo[0], (float) $geo[1] ] : [ 0.0, 0.0 ];
			}
			$h['dist'] = ( 0.0 !== $lat && 0.0 !== $lng ) ? fge_geo_distance( (float) $anchor['lat'], (float) $anchor['lng'], $lat, $lng ) : -1.0;
		}
		unset( $h );
		usort( $hits, static fn( $a, $b ) => ( $a['dist'] < 0 ? INF : $a['dist'] ) <=> ( $b['dist'] < 0 ? INF : $b['dist'] ) );
	}

	echo '<ul class="cc-nearby cc-nearby--search">';
	foreach ( $hits as $h ) {
		$pid  = (int) $h['id'];
		$mail = function_exists( 'fge_cc_partner_email' ) ? fge_cc_partner_email( $pid ) : '';
		$hint = function_exists( 'fge_venue_price_hint' ) ? fge_venue_price_hint( $pid, 'green_fee' ) : '';
		$is_s = 'stammdaten' === $h['status'];

		echo '<li class="cc-nearby-row">';
		echo '<div class="cc-nearby-main">';
		echo '<span class="cc-nearby-name">' . esc_html( $h['title'] ) . '</span>';
		$place = trim( $h['plz'] . ' ' . $h['city'] );
		if ( '' !== $place ) {
			echo '<span class="cc-muted">' . esc_html( $place ) . '</span>';
		}
		if ( '' !== $hint ) {
			echo '<span class="cc-muted">' . esc_html( $hint ) . '</span>';
		}
		echo '</div>';
		$dist = (float) ( $h['dist'] ?? -1 );
		echo '<span class="cc-nearby-dist">' . ( $dist >= 0 ? esc_html( number_format_i18n( round( $dist ) ) . ' km' ) : '' ) . '</span>';
		echo '<span class="cc-nearby-pills">';
		echo fge_cc_pill( $is_s ? 'Stammdaten' : 'Partner', $is_s ? 'neutral' : 'good' ); // phpcs:ignore WordPress.Security.EscapeOutput
		if ( '' === $mail ) {
			echo fge_cc_pill( 'ohne Mail', 'warn' ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</span>';
		echo '<span class="cc-nearby-act">';
		fge_cc_button( 'fge_cc_venue_add', $req, 'Aufnehmen', [ 'fields' => [ 'partner_id' => $pid ] ] );
		echo '</span>';
		echo '</li>';
	}
	echo '</ul></div>';
}

/** Plätze und Simulatoren rund um den Kundenstandort, mit Standort-Feld. */
function fge_cc_venue_nearby_block( int $req ): void {
	$anchor = function_exists( 'fge_request_geo_anchor' ) ? fge_request_geo_anchor( $req ) : [ 'lat' => 0.0, 'lng' => 0.0, 'label' => '', 'source' => 'none', 'candidates' => [] ];
	$saved  = (string) get_post_meta( $req, '_fge_geo_anchor', true );
	$has    = 'none' !== $anchor['source'];

	echo '<div class="cc-nearby-wrap">';
	echo '<p class="cc-kicker">Plätze in der Nähe</p>';

	echo '<form class="cc-vsearch cc-vsearch--geo" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="fge_cc_geo_anchor">';
	echo '<input type="hidden" name="request_id" value="' . (int) $req . '">';
	wp_nonce_field( 'fge_cc_geo_anchor_' . $req );
	echo '<input type="text" name="geo_anchor" value="' . esc_attr( '' !== $saved ? $saved : (string) $anchor['label'] ) . '" placeholder="Kundenstandort (PLZ oder Ort)" aria-label="Kundenstandort (PLZ oder Ort)">';
	echo '<button type="submit" class="cc-btn">Standort setzen</button>';
	echo '</form>';

	if ( ! $has ) {
		echo '<p class="cc-muted">Kein Standort erkannt, bitte PLZ oder Ort eintragen.</p></div>';
		return;
	}

	$src = [ 'anchor' => 'gesetzter Kundenstandort', 'plz' => 'aus der Firmen-PLZ', 'city' => 'aus dem Firmenort' ];
	echo '<p class="cc-muted">Umkreis um ' . esc_html( (string) $anchor['label'] ) . ' (' . esc_html( $src[ $anchor['source'] ] ?? $anchor['source'] ) . ')';
	if ( $anchor['candidates'] ) {
		$labels = array_map( static fn( $c ) => (string) $c['label'], (array) $anchor['candidates'] );
		echo ' <span class="cc-warn-inline">mehrdeutig</span>: ' . esc_html( implode( ', ', array_slice( $labels, 0, 6 ) ) ) . '. Genauer per PLZ setzen.';
	}
	echo '</p>';

	$venues = function_exists( 'fge_venues_get' ) ? fge_venues_get( $req ) : [];
	$have   = array_map( static fn( $v ) => (int) $v['partner_id'], $venues );
	$lat    = (float) $anchor['lat'];
	$lng    = (float) $anchor['lng'];

	$courses = function_exists( 'fge_verzeichnis_nearby' ) ? fge_verzeichnis_nearby( $lat, $lng, 120, 5 ) : [];
	$sims    = function_exists( 'fge_simulatoren_nearby' ) ? fge_simulatoren_nearby( $lat, $lng, 3 ) : [];

	if ( ! $courses && ! $sims ) {
		echo '<p class="cc-muted">Im Umkreis von 120 km ist nichts hinterlegt.</p></div>';
		return;
	}

	echo '<ul class="cc-nearby">';
	foreach ( $courses as $c ) {
		$pid  = (int) $c->partner_id;
		$meta = (int) $c->loecher > 0 ? (int) $c->loecher . ' Löcher' : '';
		fge_cc_nearby_row( $req, (string) $c->name, (string) $c->ort, (float) $c->dist, $meta, $pid, $have, [ 'vid' => (int) $c->id ] );
	}
	foreach ( $sims as $s ) {
		$bits = array_filter( [
			'' !== (string) ( $s['bays'] ?? '' ) ? $s['bays'] . ' Bays' : '',
			(string) ( $s['system'] ?? '' ),
		] );
		$meta = 'Simulator' . ( $bits ? ', ' . implode( ', ', $bits ) : '' );
		$pid  = 0;
		if ( '' !== (string) $s['key'] && function_exists( 'fge_stammdaten_sim_partner_id' ) ) {
			$pid = (int) fge_stammdaten_sim_partner_id( (string) $s['key'] );
		}
		fge_cc_nearby_row( $req, (string) $s['name'], (string) ( $s['ort'] ?? '' ), (float) $s['dist'], $meta, $pid, $have, [ 'sim' => (string) $s['key'] ] );
	}
	echo '</ul></div>';
}

/** Eine Zeile der Nähe-Liste. $fields ist vid oder sim für den Stammdaten-Weg. */
function fge_cc_nearby_row( int $req, string $name, string $ort, float $dist, string $meta, int $pid, array $have, array $fields ): void {
	$in_pipe = $pid > 0 && in_array( $pid, $have, true );
	$stamm   = $pid > 0 && function_exists( 'fge_partner_is_stammdaten' ) && fge_partner_is_stammdaten( $pid );

	echo '<li class="cc-nearby-row' . ( $in_pipe ? ' is-inpipe' : '' ) . '">';
	echo '<div class="cc-nearby-main">';
	echo '<span class="cc-nearby-name">' . esc_html( $name ) . '</span>';
	$sub = trim( $ort . ( '' !== $meta ? ( '' !== $ort ? ', ' : '' ) . $meta : '' ) );
	if ( '' !== $sub ) {
		echo '<span class="cc-muted">' . esc_html( $sub ) . '</span>';
	}
	echo '</div>';
	echo '<span class="cc-nearby-dist">' . esc_html( number_format_i18n( round( $dist ) ) . ' km' ) . '</span>';
	echo '<span class="cc-nearby-pills">';
	if ( $pid > 0 ) {
		echo fge_cc_pill( $stamm ? 'Stammdaten' : 'Partner', $stamm ? 'neutral' : 'good' ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</span>';
	echo '<span class="cc-nearby-act">';
	if ( $in_pipe ) {
		echo '<span class="cc-muted">schon in der Pipeline</span>';
	} elseif ( $pid > 0 ) {
		fge_cc_button( 'fge_cc_venue_add', $req, 'Aufnehmen', [ 'fields' => [ 'partner_id' => $pid ] ] );
	} else {
		$can = ( isset( $fields['vid'] ) && function_exists( 'fge_stammdaten_ensure_from_verzeichnis_row' ) )
			|| ( isset( $fields['sim'] ) && '' !== (string) $fields['sim'] && function_exists( 'fge_stammdaten_ensure_from_simulator' ) );
		if ( $can ) {
			fge_cc_button( 'fge_cc_venue_add_place', $req, 'Aufnehmen', [ 'fields' => $fields ] );
		} else {
			echo '<span class="cc-muted">noch nicht anlegbar</span>';
		}
	}
	echo '</span>';
	echo '</li>';
}

/**
 * Kontakt eines Platzes ergänzen: Name, Mail, Telefon.
 * Wiederverwendbar im Cockpit ($req > 0) und im Plätze-Verzeichnis ($req = 0).
 */
function fge_cc_partner_contact_form( int $pid, int $req = 0 ): void {
	if ( $pid <= 0 ) {
		return;
	}
	$name  = (string) get_post_meta( $pid, '_fge_main_contact_name', true );
	$email = (string) get_post_meta( $pid, '_fge_main_contact_email', true );
	$phone = (string) get_post_meta( $pid, '_fge_main_contact_phone', true );

	echo '<details class="cc-details cc-contact-edit"><summary>Kontakt ergänzen</summary>';
	echo '<form class="cc-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="fge_cc_partner_contact">';
	echo '<input type="hidden" name="partner_id" value="' . (int) $pid . '">';
	echo '<input type="hidden" name="request_id" value="' . (int) $req . '">';
	wp_nonce_field( 'fge_cc_partner_contact_' . $pid );
	echo '<div class="cc-fields">';
	echo '<label class="cc-field"><span>Ansprechpartner</span><input type="text" name="contact_name" value="' . esc_attr( $name ) . '" placeholder="Name"></label>';
	echo '<label class="cc-field"><span>Mail</span><input type="email" name="contact_email" value="' . esc_attr( $email ) . '" placeholder="name@platz.de"></label>';
	echo '<label class="cc-field"><span>Telefon</span><input type="text" name="contact_phone" value="' . esc_attr( $phone ) . '" placeholder="Telefon"></label>';
	echo '</div>';
	echo '<p><button type="submit" class="cc-btn">Kontakt speichern</button></p>';
	echo '</form></details>';
}
