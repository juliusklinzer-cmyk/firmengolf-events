<?php
/**
 * Angebots-Automatik: Sobald ein Termin bestätigt ist, wird automatisch ein
 * Angebot erzeugt (Event-Preis + Kundenwünsche als Posten) und dem Kunden per
 * Mail mit Annehmen/Ablehnen-Link geschickt. Der Kunde hat einen Magic-Link
 * (/angebot/<token>/), der zugleich als read-only Status-Seite dient.
 *
 * Eine Statusquelle: fge_request_set_status() schreibt _fge_request_status an
 * allen Übergängen und feuert fge_request_status_changed.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Eine Statusquelle ─────────────────────────────────────────────────────────

/** Setzt den Lebenszyklus-Status einer Anfrage (validiert, mit Zeitstempel + Hook). */
function fge_request_set_status( int $req, string $status ): void {
	if ( ! in_array( $status, fge_get_statuses( 'request' ), true ) ) {
		return;
	}
	$old = (string) get_post_meta( $req, '_fge_request_status', true );
	if ( $old === $status ) {
		return;
	}
	update_post_meta( $req, '_fge_request_status', $status );
	update_post_meta( $req, '_fge_last_status_change', current_datetime()->format( 'Y-m-d H:i:s' ) );
	do_action( 'fge_request_status_changed', $req, $status, $old );
}

// ── Kunden-Magic-Link ─────────────────────────────────────────────────────────

/** Kunden-Token, lazy erzeugt. */
function fge_request_customer_token( int $req ): string {
	$t = (string) get_post_meta( $req, '_fge_customer_token', true );
	if ( '' === $t ) {
		$t = bin2hex( random_bytes( 20 ) );
		update_post_meta( $req, '_fge_customer_token', $t );
	}
	return $t;
}

function fge_request_by_customer_token( string $token ): int {
	if ( '' === $token ) {
		return 0;
	}
	$q = get_posts( [
		'post_type'   => 'firmengolf_request',
		'post_status' => [ 'publish', 'draft' ], // Papierkorb-Anfragen sind über den Link nicht mehr erreichbar
		'numberposts' => 1,
		'fields'      => 'ids',
		'meta_key'    => '_fge_customer_token', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value'  => $token, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	] );
	return $q ? (int) $q[0] : 0;
}

/** Öffentliche Angebots-/Status-URL für den Kunden. */
function fge_offer_link( int $req ): string {
	return home_url( '/angebot/' . fge_request_customer_token( $req ) . '/' );
}

// ── Angebot bauen ─────────────────────────────────────────────────────────────

/** Snapshot des Angebots aus Event-Preis + bepreisten Positionen + offenen Wünschen. */
function fge_build_offer_snapshot( int $req, int $date_index ): array {
	$event_id = (int) get_post_meta( $req, '_fge_assigned_event_id', true );
	$pax      = (int) get_post_meta( $req, '_fge_expected_participants', true );
	// Pauschalen auf die TATSÄCHLICH angefragte Personenzahl umlegen (Julius,
	// 02.09.): weniger Personen als das Maximum → höherer p.P.-Preis, sonst
	// würde die Pauschale (z. B. Golflehrer 250 €) nur anteilig eingesammelt.
	$pricing = ( $event_id && function_exists( 'fge_event_pricing_for_pax' ) )
		? fge_event_pricing_for_pax( $event_id, $pax )
		: ( ( $event_id && function_exists( 'fge_event_pricing' ) ) ? fge_event_pricing( $event_id ) : [ 'gross' => 0, 'unit' => '' ] );

	$includes = $event_id ? get_post_meta( $event_id, '_fge_event_includes', true ) : [];
	$includes = is_array( $includes ) ? $includes : array_filter( preg_split( '/\r\n|\r|\n/', (string) $includes ) );

	// Wünsche mit bepreister Position tauchen als Extras auf, nicht mehr als offener Wunsch.
	$g = function_exists( 'fge_xs_uncovered_wishes' )
		? fge_xs_uncovered_wishes( $req )
		: ( function_exists( 'fge_request_wish_groups' ) ? fge_request_wish_groups( $req ) : [ 'platz' => [], 'firmengolf' => [] ] );

	$gross = (float) ( $pricing['gross'] ?? 0 );
	$unit  = (string) ( $pricing['unit'] ?? '' );

	// Telefonisch vereinbarter Preis übersteuert den Event-Standardpreis (Platzhalter-Events).
	$override = (float) get_post_meta( $req, '_fge_offer_base_override', true );
	if ( $override > 0 ) {
		$gross = $override;
		$unit  = 'person' === (string) get_post_meta( $req, '_fge_offer_base_override_unit', true ) ? 'pro Person' : 'pauschal';
	}
	$total = ( 'pro Person' === $unit && $pax > 0 ) ? $gross * $pax : $gross;

	// Extras: NUR Verkaufspreis, Basis und Zeilenindex (src). Einkauf, Marge und
	// Dienstleister-Kontakt bleiben bewusst außerhalb des Snapshots — die Kundenseite
	// rendert ausschließlich hieraus und kann die Marge damit nicht leaken.
	$extras = [];
	if ( function_exists( 'fge_xs_priced' ) ) {
		foreach ( fge_xs_priced( $req ) as $src => $item ) {
			$extras[] = [
				'label' => (string) $item['label'],
				'price' => fge_xs_sale_price( $item ),
				'basis' => (string) $item['basis'],
				'src'   => (int) $src,
			];
		}
	}

	$co       = function_exists( 'fge_company' ) ? fge_company() : [];
	$location = $event_id ? (string) get_post_meta( $event_id, '_fge_event_location', true ) : '';
	if ( '' === $location ) {
		$location = (string) get_post_meta( $req, '_fge_company_city', true );
	}
	// Angebotstext je Anfrage (Julius, 17.09.2026): Veranstaltungsort, Ablauf und
	// Leistungsliste aus „Schritt 2" übersteuern die generischen Event-Angaben, damit
	// bei Platzhalter-Events der echte Platz und das vereinbarte Programm im Angebot stehen.
	$loc_override = trim( (string) get_post_meta( $req, '_fge_offer_location', true ) );
	if ( '' !== $loc_override ) {
		$location = $loc_override;
	}
	$inc_override = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) get_post_meta( $req, '_fge_offer_includes', true ) ) ) ) );
	if ( ! empty( $inc_override ) ) {
		$includes = $inc_override;
	}

	// Partnercode-Rabatt (Multiplikator): Prozent auf die Netto-Zwischensumme aus Event und
	// allen Extras, beim Versand eingefroren. Schlüssel fehlt bei Anfragen ohne Code.
	$discount = function_exists( 'fge_pc_snapshot_block' ) ? fge_pc_snapshot_block( $req ) : null;
	if ( null !== $discount ) {
		$net_all = $total;
		foreach ( $extras as $x ) {
			$net_all += 'person' === $x['basis'] ? $x['price'] * max( 1, $pax ) : $x['price'];
		}
		$discount['amount'] = round( $net_all * $discount['percent'] / 100, 2 );
	}

	$snap = [
		'event_title'       => $event_id ? get_the_title( $event_id ) : ( (string) get_post_meta( $req, '_fge_event_type', true ) ?: 'Firmen-Event' ),
		'date'              => (string) get_post_meta( $req, '_fge_preferred_date_' . $date_index, true ),
		'location'          => $location,
		'schedule'          => trim( (string) get_post_meta( $req, '_fge_offer_schedule', true ) ),
		'participants'      => $pax,
		'price_gross'       => $gross,
		'price_unit'        => $unit,
		'price_total'       => $total,
		'extras'            => $extras,
		'vat_percent'       => defined( 'FGE_VAT_PERCENT' ) ? (int) FGE_VAT_PERCENT : 19,
		'includes'          => array_values( array_filter( array_map( 'strval', (array) $includes ) ) ),
		'wishes_platz'      => array_values( (array) ( $g['platz'] ?? [] ) ),
		'wishes_firmengolf' => array_values( (array) ( $g['firmengolf'] ?? [] ) ),
		'contact_name'      => (string) ( $co['managing_director'] ?? 'Firmengolf' ),
		'contact_phone'     => (string) ( $co['phone_display'] ?? '' ),
		'contact_email'     => (string) ( $co['email_events'] ?? '' ),
	];
	if ( null !== $discount ) {
		$snap['discount'] = $discount;
	}
	return $snap;
}

/** Preis als Text fürs Angebot. */
function fge_offer_price_text( array $snap ): string {
	$gross = (float) ( $snap['price_gross'] ?? 0 );
	if ( $gross <= 0 ) {
		return 'Auf Anfrage';
	}
	$unit = (string) ( $snap['price_unit'] ?? '' );
	// Exakte Beträge mit zwei Nachkommastellen, kein „ca." (Julius, 17.09.2026).
	$s = number_format_i18n( $gross, 2 ) . ' €' . ( 'pro Person' === $unit ? ' p.P.' : ' gesamt' );
	if ( 'pro Person' === $unit && (int) ( $snap['participants'] ?? 0 ) > 0 ) {
		$s .= ' · ' . number_format_i18n( (float) $snap['price_total'], 2 ) . ' € bei ' . (int) $snap['participants'] . ' Personen';
	}
	return $s;
}

/** Preistext einer Extra-Position (Verkauf netto), z. B. "540,00 € pauschal" / "45,00 € p.P.". */
function fge_offer_extra_price_text( array $extra ): string {
	$p = (float) ( $extra['price'] ?? 0 );
	return number_format_i18n( $p, 2 ) . ' €' . ( 'person' === (string) ( $extra['basis'] ?? '' ) ? ' p.P.' : ' pauschal' );
}

/**
 * Netto-Gesamtsumme über Eventpreis + Extras (Basis für MwSt./Endpreis).
 *
 * @param array      $snap     Angebots-Snapshot.
 * @param array|null $selected src-Indizes der gewählten Extras, null = alle.
 * @return array{net:float,ca:bool} ca = enthält p.P.-Anteile (Summe hängt an der Teilnehmerzahl).
 */
function fge_offer_totals( array $snap, ?array $selected = null ): array {
	$pax   = (int) ( $snap['participants'] ?? 0 );
	$is_pp = 'pro Person' === (string) ( $snap['price_unit'] ?? '' );
	$net   = $is_pp
		? ( $pax > 0 ? (float) ( $snap['price_total'] ?? 0 ) : 0.0 )
		: (float) ( $snap['price_gross'] ?? 0 );
	$ca = $is_pp && $net > 0;
	foreach ( (array) ( $snap['extras'] ?? [] ) as $x ) {
		if ( null !== $selected && ! in_array( (int) ( $x['src'] ?? -1 ), $selected, true ) ) {
			continue;
		}
		$p = (float) ( $x['price'] ?? 0 );
		if ( 'person' === (string) ( $x['basis'] ?? '' ) ) {
			if ( $pax > 0 ) {
				$net += $p * $pax;
				$ca   = true;
			}
		} else {
			$net += $p;
		}
	}
	// Partnercode-Rabatt auf die gewählte Zwischensumme.
	$disc = 0.0;
	if ( ! empty( $snap['discount']['percent'] ) && $net > 0 ) {
		$disc = round( $net * (float) $snap['discount']['percent'] / 100, 2 );
		$net  = round( $net - $disc, 2 );
	}
	return [ 'net' => $net, 'ca' => $ca, 'discount' => $disc ];
}

/** Brutto-Gesamtbetrag inkl. USt als Text, oder '' wenn kein konkreter Gesamtpreis vorliegt. */
function fge_offer_gross_incl_vat_text( array $snap ): string {
	$unit = (string) ( $snap['price_unit'] ?? '' );
	$vat  = (int) ( $snap['vat_percent'] ?? 19 );
	$base = ( 'pro Person' === $unit )
		? ( (int) ( $snap['participants'] ?? 0 ) > 0 ? (float) ( $snap['price_total'] ?? 0 ) : 0.0 )
		: (float) ( $snap['price_gross'] ?? 0 );
	if ( $base <= 0 ) {
		return '';
	}
	return number_format_i18n( round( $base * ( 1 + $vat / 100 ), 2 ), 2 ) . ' € inkl. USt.';
}

// ── Auslöser: Termin bestätigt → Angebot erzeugen + senden ────────────────────

/**
 * Braucht die Anfrage Feinplanung durch Firmengolf, bevor das Angebot rausgeht?
 * Ja, sobald Zusatzleistungen gewählt wurden: der Event-Preis deckt nur das
 * Grundangebot, die Zusätze sind noch unbepreist (Julius, 2026-07-04).
 * Nach der Feinplanung setzt der Admin-Button _fge_offer_review_done → Angebot geht raus.
 */
function fge_offer_needs_review( int $req ): bool {
	// Ohne bepreisbaren Inhalt nie ein Auto-Angebot: „Auf Anfrage" wäre sonst verbindlich
	// buchbar (Audit A6). Gilt auch nach „Feinplanung erledigt" (Audit 18.09.2026).
	if ( ! fge_offer_is_priced( $req ) ) {
		return (bool) apply_filters( 'fge_offer_needs_review', true, $req );
	}
	if ( '1' === (string) get_post_meta( $req, '_fge_offer_review_done', true ) ) {
		return false;
	}
	// Wünsche mit bepreister Angebots-Position gelten als erledigt; nur offene halten zurück.
	$g     = function_exists( 'fge_xs_uncovered_wishes' )
		? fge_xs_uncovered_wishes( $req )
		: ( function_exists( 'fge_request_wish_groups' ) ? fge_request_wish_groups( $req ) : [ 'platz' => [], 'firmengolf' => [] ] );
	$needs = ! empty( $g['platz'] ) || ! empty( $g['firmengolf'] );
	return (bool) apply_filters( 'fge_offer_needs_review', $needs, $req );
}

/** Vom Kunden gewählte Zusatzleistungen (src-Indizes); leer = keine (kein „[0]" aus leerem Meta). */
function fge_offer_selected_extras( int $req ): array {
	$raw = get_post_meta( $req, '_fge_offer_extras_selected', true );
	return is_array( $raw ) ? array_values( array_map( 'intval', $raw ) ) : [];
}

/**
 * Ist die Anfrage bepreist (Eventpreis, Preis-Override oder bepreiste Positionen)?
 * Ohne Preis darf kein Angebot raus, sonst wäre „Auf Anfrage" verbindlich buchbar
 * (Audit 18.09.2026: „Angebot jetzt senden" umging das Gate).
 */
function fge_offer_is_priced( int $req ): bool {
	$event_id = (int) get_post_meta( $req, '_fge_assigned_event_id', true );
	$pricing  = ( $event_id > 0 && 'firmengolf_event' === get_post_type( $event_id ) && function_exists( 'fge_event_pricing' ) )
		? fge_event_pricing( $event_id )
		: [ 'gross' => 0 ];
	$base = max( (float) ( $pricing['gross'] ?? 0 ), (float) get_post_meta( $req, '_fge_offer_base_override', true ) );
	if ( $base > 0 ) {
		// Pro-Kopf-Preis ohne Teilnehmerzahl ergibt keine Summe → nicht bepreist.
		$override = (float) get_post_meta( $req, '_fge_offer_base_override', true );
		$is_pp    = $override > 0
			? 'person' === (string) get_post_meta( $req, '_fge_offer_base_override_unit', true )
			: 'pro Person' === (string) ( $pricing['unit'] ?? '' );
		if ( ! $is_pp || (int) get_post_meta( $req, '_fge_expected_participants', true ) > 0 ) {
			return true;
		}
	}
	return function_exists( 'fge_xs_priced' ) && ! empty( fge_xs_priced( $req ) );
}

add_action( 'fge_request_date_confirmed', 'fge_offer_on_date_confirmed', 20, 2 );
function fge_offer_on_date_confirmed( int $req, int $date_index ): void {
	if ( '1' === (string) get_post_meta( $req, '_fge_offer_sent', true ) ) {
		return; // einmalig
	}
	if ( fge_offer_needs_review( $req ) ) {
		// Zusatzleistungen ohne Preis → kein Auto-Angebot. Kunde bekommt eine
		// Termin-Bestätigung, Firmengolf plant die Feinheiten und löst das
		// Angebot danach manuell aus (Metabox „Angebot" in der Anfrage).
		update_post_meta( $req, '_fge_offer_hold', 1 );
		fge_request_set_status( $req, 'in_uebernahme' );
		if ( function_exists( 'fge_send_date_confirmation_email' ) ) {
			fge_send_date_confirmation_email( $req, $date_index );
		}
		return;
	}
	// Atomarer Guard gegen Doppelversand (Portal + Admin gleichzeitig, Audit B6):
	// add_post_meta mit unique=true gewinnt nur einmal.
	if ( ! add_post_meta( $req, '_fge_offer_sent', 1, true ) ) {
		return;
	}
	delete_post_meta( $req, '_fge_offer_hold' );
	$snap = fge_build_offer_snapshot( $req, $date_index );
	update_post_meta( $req, '_fge_offer_snapshot', $snap );
	update_post_meta( $req, '_fge_offer_date_index', $date_index );
	update_post_meta( $req, '_fge_offer_status', 'pending' );
	$days = (int) apply_filters( 'fge_offer_response_days', 7 );
	update_post_meta( $req, '_fge_offer_deadline', time() + $days * DAY_IN_SECONDS );
	update_post_meta( $req, '_fge_offer_sent_at', time() );

	fge_send_offer_email( $req );
	fge_request_set_status( $req, 'angebot_versendet' );
}

// ── Buchungs-KPIs: bei Annahme hochzählen (waren vorher dauerhaft 0, Audit C3) ─
add_action( 'fge_offer_accepted', 'fge_offer_count_booking', 5 );
function fge_offer_count_booking( int $req ): void {
	$event_id = (int) get_post_meta( $req, '_fge_assigned_event_id', true );
	if ( $event_id > 0 ) {
		update_post_meta( $event_id, '_fge_bookings_count', (int) get_post_meta( $event_id, '_fge_bookings_count', true ) + 1 );
	}
	$partner_id = (int) get_post_meta( $req, '_fge_assigned_partner_id', true );
	if ( $partner_id > 0 ) {
		update_post_meta( $partner_id, '_fge_bookings_total', (int) get_post_meta( $partner_id, '_fge_bookings_total', true ) + 1 );
	}
}

// ── Routing: /angebot/<token>/ ────────────────────────────────────────────────

add_action( 'init', static function () {
	add_rewrite_rule( '^angebot/([^/]+)/?$', 'index.php?fge_angebot=$matches[1]', 'top' );
} );
add_filter( 'query_vars', static function ( array $vars ): array {
	$vars[] = 'fge_angebot';
	return $vars;
} );
add_filter( 'template_include', static function ( $template ) {
	if ( get_query_var( 'fge_angebot' ) ) {
		$t = locate_template( 'template-angebot.php' );
		return $t ?: $template;
	}
	return $template;
} );
add_action( 'init', static function () {
	$rules = get_option( 'rewrite_rules' );
	if ( is_array( $rules ) && ! isset( $rules['^angebot/([^/]+)/?$'] ) ) {
		flush_rewrite_rules( false );
	}
}, 99 );

/** Resolve token → Daten fürs Template, oder null. */
function fge_offer_resolve( string $token ): ?array {
	$req = fge_request_by_customer_token( $token );
	if ( $req <= 0 ) {
		return null;
	}
	return [ 'req' => $req ];
}

// ── Annahme / Ablehnung (POST vom /angebot/-Template) ─────────────────────────

add_action( 'init', 'fge_offer_handle_post' );
function fge_offer_handle_post(): void {
	$action = sanitize_key( $_POST['fge_offer_action'] ?? '' );
	if ( '' === $action ) {
		return;
	}
	$token = sanitize_text_field( wp_unslash( $_POST['fge_offer_token'] ?? '' ) );
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fge_offer_nonce'] ?? '' ) ), 'fge_offer_' . $token ) ) {
		// Abgelaufene Sitzung nicht still verwerfen (Kern-Audit N5): zurück zur
		// Angebotsseite mit Hinweis, damit der Kunde erneut bestätigen kann.
		$req_n = fge_request_by_customer_token( $token );
		if ( $req_n > 0 ) {
			wp_safe_redirect( fge_offer_link( $req_n ) . '?session=expired' );
			exit;
		}
		return;
	}
	$req = fge_request_by_customer_token( $token );
	if ( $req <= 0 ) {
		return;
	}
	if ( 'pending' === (string) get_post_meta( $req, '_fge_offer_status', true ) ) {
		if ( 'accept' === $action ) {
			// Verbindliche Buchung nur mit AGB-Zustimmung.
			if ( '1' !== (string) ( $_POST['fge_offer_agb'] ?? '' ) ) {
				wp_safe_redirect( fge_offer_link( $req ) . '?agb=1' );
				exit;
			}
			// Atomarer Guard: zwei gleichzeitige Klicks lösten die Buchungsmails doppelt aus (Audit 18.09.).
			$deadline_chk = (int) get_post_meta( $req, '_fge_offer_deadline', true );
			if ( ( $deadline_chk <= 0 || time() <= $deadline_chk ) && ! add_post_meta( $req, '_fge_offer_decided', 1, true ) ) {
				wp_safe_redirect( fge_offer_link( $req ) . '?done=1' );
				exit;
			}
			// Nach Fristablauf ist der Slot nicht mehr garantiert: keine Auto-Buchung,
			// sondern Rückfrage an Firmengolf — Termin prüfen, dann manuell bestätigen (Audit B2).
			$deadline = (int) get_post_meta( $req, '_fge_offer_deadline', true );
			if ( $deadline > 0 && time() > $deadline ) {
				// Idempotent: erneute Klicks lösen keine weitere interne Mail aus (Kern-Audit M5).
				if ( '1' !== (string) get_post_meta( $req, '_fge_offer_expired_query', true ) ) {
					update_post_meta( $req, '_fge_offer_expired_query', '1' );
					update_post_meta( $req, '_fge_offer_query', 'Kunde möchte nach Ablauf der Reservierungsfrist annehmen, bitte Termin prüfen und Buchung manuell bestätigen.' );
					fge_request_set_status( $req, 'angebot_rueckfrage' );
					do_action( 'fge_offer_query', $req, 'Annahme nach Fristablauf, Termin bitte prüfen.' );
				}
				wp_safe_redirect( fge_offer_link( $req ) . '?done=expired' );
				exit;
			}
			// Vom Kunden gewählte Zusatzleistungen festhalten (Checkbox je Position,
			// abgewählte lösen eine Absage an den Dienstleister aus).
			$snap_x = (array) get_post_meta( $req, '_fge_offer_snapshot', true );
			$valid  = array_map( static fn( $x ) => (int) ( $x['src'] ?? -1 ), (array) ( $snap_x['extras'] ?? [] ) );
			if ( ! empty( $valid ) ) {
				$picked = array_map( 'intval', (array) ( $_POST['fge_offer_extras'] ?? [] ) );
				update_post_meta( $req, '_fge_offer_extras_selected', array_values( array_intersect( $valid, $picked ) ) );
			}
			update_post_meta( $req, '_fge_offer_status', 'accepted' );
			update_post_meta( $req, '_fge_offer_accepted_at', current_time( 'mysql' ) ); // fürs Übersichts-Dashboard (Buchungen/Umsatz je Monat)
			fge_request_set_status( $req, 'angebot_angenommen' );
			do_action( 'fge_offer_accepted', $req );
		} elseif ( 'decline' === $action ) {
			if ( ! add_post_meta( $req, '_fge_offer_decided', 1, true ) ) {
				wp_safe_redirect( fge_offer_link( $req ) . '?done=1' );
				exit;
			}
			update_post_meta( $req, '_fge_offer_status', 'declined' );
			fge_request_set_status( $req, 'angebot_abgelehnt' );
			do_action( 'fge_offer_declined', $req );
		} elseif ( 'request' === $action ) {
			// Rückfrage / Änderungswunsch — Angebot bleibt offen (pending), kein Dead-End.
			$msg = mb_substr( sanitize_textarea_field( wp_unslash( $_POST['fge_offer_message'] ?? '' ) ), 0, 2000 );
			if ( '' === trim( $msg ) ) {
				// Leere Rückfragen feuern keine interne Mail (Kern-Audit M5), Kunde bekommt einen Hinweis.
				wp_safe_redirect( fge_offer_link( $req ) . '?err=empty' );
				exit;
			}
			// Höchstens 3 Rückfragen je 10 Minuten (Sicherheits-Audit 18.09.: jede Rückfrage ist eine interne Mail).
			if ( function_exists( 'fge_form_rate_limited' ) && fge_form_rate_limited( 3, 600, 'offer_query_' . $req ) ) {
				wp_safe_redirect( fge_offer_link( $req ) . '?done=query' );
				exit;
			}
			update_post_meta( $req, '_fge_offer_query', $msg );
			fge_request_set_status( $req, 'angebot_rueckfrage' );
			do_action( 'fge_offer_query', $req, $msg );
			wp_safe_redirect( fge_offer_link( $req ) . '?done=query' );
			exit;
		}
	}
	wp_safe_redirect( fge_offer_link( $req ) . '?done=1' );
	exit;
}
