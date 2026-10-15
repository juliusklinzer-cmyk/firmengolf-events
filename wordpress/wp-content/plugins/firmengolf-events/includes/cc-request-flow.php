<?php
/**
 * Anfragen im Control Center nach dem Entwurf „Anfragen" aus Claude Design
 * (Julius, 15.10.2026): Liste als Tabelle mit Reitern, Detail mit sechs
 * Schritten Bedarf, Anfragen, Kalkulation, Angebot, Termin, Abrechnung.
 *
 * Etappe A: nur Umzug ins neue Layout. Alle Formulare und Aktionen sind die
 * bestehenden aus cc-core.php, cc-venues-ui.php und cc-offer-options.php.
 * Einzige neue Aktion ist das Bearbeiten des Bedarfs, und die schreibt nur in
 * Felder, die es im WordPress-Admin schon gibt.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Die sechs Schritte des Ablaufs. */
function fge_cc_flow_steps(): array {
	return [
		1 => 'Bedarf',
		2 => 'Anfragen',
		3 => 'Kalkulation',
		4 => 'Angebot',
		5 => 'Termin',
		6 => 'Abrechnung',
	];
}

/**
 * Wo steht eine Anfrage im Ablauf.
 *
 * step: aktueller Schritt 1 bis 6, 7 heißt alles erledigt.
 * label/tone: Status-Pille. bucket: Reiter der Liste (offen, kunde, gebucht, fertig).
 */
function fge_cc_flow( int $req ): array {
	static $cache = [];
	if ( isset( $cache[ $req ] ) ) {
		return $cache[ $req ];
	}
	$status = (string) get_post_meta( $req, '_fge_request_status', true );
	$offer  = (string) get_post_meta( $req, '_fge_offer_status', true );
	$sent   = '1' === (string) get_post_meta( $req, '_fge_offer_sent', true );
	$venues = function_exists( 'fge_venues_get' ) ? fge_venues_get( $req ) : [];
	$by     = array_count_values( array_map( static fn( $v ) => (string) $v['status'], $venues ) );
	$yes    = ( $by['zugesagt'] ?? 0 ) + ( $by['gewaehlt'] ?? 0 );
	$final  = function_exists( 'fge_rr_final_index' ) ? fge_rr_final_index( $req ) : 0;

	// Fortschritt ohne Rücksicht auf verloren, damit ein verlorener Vorgang
	// dort stehen bleibt, wo er aufgehört hat.
	if ( 'accepted' === $offer || ( function_exists( 'fge_request_is_booked' ) && fge_request_is_booked( $req ) ) ) {
		$date = fge_cc_event_date( $req );
		$step = $date > 0 && $date < fge_cc_today() ? 6 : 5;
	} elseif ( $sent ) {
		$step = 4;
	} elseif ( $yes > 0 || $final > 0 ) {
		$step = 3;
	} elseif ( $venues || (int) get_post_meta( $req, '_fge_need_done', true ) > 0 || ! in_array( $status, [ '', 'neu', 'eingangsbestaetigung_gesendet' ], true ) ) {
		$step = 2;
	} else {
		$step = 1;
	}

	if ( 'abgeschlossen' === $status ) {
		$out = [ 7, 'Abgeschlossen', 'neutral', 'fertig' ];
	} elseif ( in_array( $status, [ 'event_durchgefuehrt', 'rechnung_in_lexoffice_erstellt' ], true ) ) {
		$out = [ 6, 'event_durchgefuehrt' === $status ? 'Durchgeführt' : 'Rechnung geschrieben', 'neutral', 'fertig' ];
	} elseif ( 'verloren' === $status ) {
		$out = [ $step, 'Verloren', 'bad', 'fertig' ];
	} elseif ( 5 <= $step ) {
		$out = 6 === $step ? [ 6, 'Event vorbei', 'warn', 'gebucht' ] : [ 5, 'Gebucht', 'good', 'gebucht' ];
	} elseif ( 'declined' === $offer || 'angebot_abgelehnt' === $status ) {
		$out = [ 4, 'Abgelehnt', 'bad', 'offen' ];
	} elseif ( 'angebot_rueckfrage' === $status ) {
		$out = [ 4, 'Rückfrage', 'warn', 'offen' ];
	} elseif ( $sent ) {
		$out = [ 4, 'Beim Kunden', 'brand', 'kunde' ];
	} elseif ( 'nicht_verfuegbar' === $status ) {
		$out = [ $step, 'Nicht verfügbar', 'bad', 'offen' ];
	} elseif ( 3 === $step ) {
		$out = [ 3, 'Kalkulation', 'brand', 'offen' ];
	} elseif ( 2 === $step ) {
		$asked = ( $by['angefragt'] ?? 0 ) + ( $by['abgesagt'] ?? 0 );
		$out   = $asked > 0 ? [ 2, 'Angefragt', 'warn', 'offen' ] : [ 2, 'Plätze anfragen', 'brand', 'offen' ];
	} else {
		$out = [ 1, 'Neu', 'warn', 'offen' ];
	}

	$cache[ $req ] = [
		'step'   => (int) $out[0],
		'label'  => (string) $out[1],
		'tone'   => (string) $out[2],
		'bucket' => (string) $out[3],
		'venues' => $venues,
		'count'  => $by,
	];
	return $cache[ $req ];
}

// ── Liste ────────────────────────────────────────────────────────────────────

function fge_cc_page_requests(): void {
	$search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$tab    = sanitize_key( wp_unslash( $_GET['tab'] ?? 'offen' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$tabs   = [ 'offen' => 'Offen', 'kunde' => 'Beim Kunden', 'gebucht' => 'Gebucht', 'fertig' => 'Abgeschlossen', 'alle' => 'Alle' ];
	if ( ! isset( $tabs[ $tab ] ) ) {
		$tab = 'offen';
	}

	$result = fge_cc_query_requests( [], 300 );
	$rows   = [];
	$count  = array_fill_keys( array_keys( $tabs ), 0 );
	foreach ( $result['ids'] as $id ) {
		$data    = fge_get_request_email_data( $id );
		$contact = trim( (string) $data['first_name'] . ' ' . (string) $data['last_name'] );
		$company = (string) get_post_meta( $id, '_fge_company_name', true );
		$f       = fge_cc_request_fact_data( $id );
		if ( '' !== $search && false === mb_stripos( fge_request_number( $id ) . ' ' . $company . ' ' . $contact . ' ' . $f['event'], $search ) ) {
			continue;
		}
		$flow = fge_cc_flow( $id );
		$count[ $flow['bucket'] ]++;
		$count['alle']++;
		if ( 'alle' !== $tab && $flow['bucket'] !== $tab ) {
			continue;
		}
		$rows[] = [ $id, $company ?: ( $contact ?: 'Ohne Firma' ), $contact, $f, $flow ];
	}

	fge_cc_truncation_note( $result, 'Anfragen' );
	echo '<p class="cc-top-sub cc-sub-under">' . esc_html( $count['offen'] . ' offen, ' . $count['kunde'] . ' beim Kunden, ' . $count['gebucht'] . ' gebucht' ) . '</p>';

	echo '<nav class="cc-segs" aria-label="Ansicht">';
	foreach ( $tabs as $k => $label ) {
		$args = [ 'tab' => $k ] + ( '' !== $search ? [ 's' => $search ] : [] );
		echo '<a class="cc-seg' . ( $k === $tab ? ' is-on' : '' ) . '" href="' . esc_url( fge_cc_url( 'anfragen', $args ) ) . '"' . ( $k === $tab ? ' aria-current="page"' : '' ) . '>'
			. '<span>' . esc_html( $label ) . '</span><span class="cc-seg-n">' . (int) $count[ $k ] . '</span></a>';
	}
	echo '</nav>';

	echo '<section class="cc-tablewrap"><table class="cc-table cc-reqtable"><thead><tr>'
		. '<th>Nummer</th><th>Firma</th><th>Event</th><th>Termin</th><th class="cc-num">Pers.</th><th>Status</th><th>Nächster Schritt</th>'
		. '</tr></thead><tbody>';
	foreach ( $rows as [ $id, $company, $contact, $f, $flow ] ) {
		$url   = fge_cc_request_url( $id );
		$date  = fge_cc_event_date( $id );
		$wish  = function_exists( 'fge_request_wish_date_labels' ) ? fge_request_wish_date_labels( $id ) : [];
		$when  = $date > 0 ? wp_date( 'd.m.Y', $date ) : ( $wish ? (string) reset( $wish ) : 'offen' );
		$pax   = function_exists( 'fge_request_pax_current' ) ? fge_request_pax_current( $id ) : (int) $f['pax'];
		$place = (string) get_post_meta( $id, '_fge_company_city', true ) ?: (string) get_post_meta( $id, '_fge_desired_region', true );
		$tasks = 'fertig' === $flow['bucket'] ? [] : fge_cc_tasks( $id );
		$mine  = array_values( array_filter( $tasks, static fn( $t ) => 'me' === $t['who'] ) );
		$next  = $mine[0]['text'] ?? ( $tasks[0]['text'] ?? ( 'fertig' === $flow['bucket'] ? 'Nichts mehr zu tun' : '' ) );

		echo '<tr onclick="location.href=\'' . esc_js( $url ) . '\'">';
		echo '<td class="cc-mono"><a href="' . esc_url( $url ) . '">' . esc_html( fge_request_number( $id ) ) . '</a></td>';
		echo '<td><strong class="cc-ellipsis">' . esc_html( $company ) . '</strong><span class="cc-td-sub">' . esc_html( implode( ' · ', array_filter( [ $contact, $place ] ) ) ) . '</span></td>';
		echo '<td><span class="cc-ellipsis">' . esc_html( $f['event'] ) . '</span></td>';
		echo '<td class="cc-tnum">' . esc_html( $when ) . '</td>';
		echo '<td class="cc-num">' . ( $pax > 0 ? (int) $pax : '' ) . '</td>';
		echo '<td>' . fge_cc_pill( $flow['label'], $flow['tone'] ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<td class="cc-td-next">' . esc_html( $next ) . '</td>';
		echo '</tr>';
	}
	if ( ! $rows ) {
		echo '<tr class="is-empty"><td colspan="7">' . esc_html( '' !== $search ? 'Keine Anfrage zu „' . $search . '“ in dieser Ansicht.' : 'Keine Anfragen in dieser Ansicht.' ) . '</td></tr>';
	}
	echo '</tbody></table></section>';
}

// ── Detail ───────────────────────────────────────────────────────────────────

function fge_cc_page_request( int $req ): void {
	if ( 'firmengolf_request' !== get_post_type( $req ) ) {
		fge_cc_empty( 'Diese Anfrage gibt es nicht.' );
		return;
	}
	$flow = fge_cc_flow( $req );
	$cur  = $flow['step'];

	echo '<div class="cc-backrow"><p class="cc-back"><a href="' . esc_url( fge_cc_url( 'anfragen' ) ) . '">Alle Anfragen</a></p>'
		. '<a class="cc-btn cc-btn--text" href="' . esc_url( get_edit_post_link( $req, 'raw' ) ) . '">Im WordPress öffnen</a></div>';
	fge_cc_message( $req );
	fge_cc_flow_header( $req, $flow );

	// Reiter mit Punkt: grün erledigt, blau jetzt dran, grau folgt.
	echo '<nav class="cc-steptabs" role="tablist" data-cc-rtabs="' . (int) $req . '" data-cc-cur="' . (int) $cur . '">';
	foreach ( fge_cc_flow_steps() as $n => $label ) {
		$state = $n < $cur ? 'done' : ( $n === $cur ? 'now' : 'later' );
		echo '<button type="button" class="cc-steptab is-' . esc_attr( $state ) . ( $n === min( $cur, 6 ) ? ' is-on' : '' ) . '" role="tab" data-cc-rtab="s' . (int) $n . '">'
			. '<span class="cc-steptab-dot" aria-hidden="true"></span>' . esc_html( $label ) . '</button>';
	}
	echo '</nav>';

	$panes = [
		1 => 'fge_cc_flow_pane_need',
		2 => 'fge_cc_flow_pane_partners',
		3 => 'fge_cc_flow_pane_calc',
		4 => 'fge_cc_flow_pane_offer',
		5 => 'fge_cc_flow_pane_date',
		6 => 'fge_cc_flow_pane_billing',
	];
	foreach ( $panes as $n => $fn ) {
		echo '<div class="cc-rpane" data-cc-rpane="s' . (int) $n . '"' . ( $n === min( $cur, 6 ) ? '' : ' hidden' ) . '>';
		$open = $n <= $cur;
		echo '<section class="cc-card cc-stepcard' . ( $open ? '' : ' is-later' ) . '">';
		fge_cc_flow_pane_head( $req, $n, $cur );
		if ( ! $open ) {
			echo '</section></div>';
			continue;
		}
		echo '<div class="cc-stepcard-body">';
		call_user_func( $fn, $req, $cur );
		echo '</div></section></div>';
	}

	// Unter den Schritten: Aufgaben, Verlauf, Mails, Schlummern zum Aufklappen.
	echo '<div class="cc-folds">';
	fge_cc_flow_fold_tasks( $req );
	fge_cc_flow_fold( 'Verlauf', function_exists( 'fge_activity_for_request' ) ? count( fge_activity_for_request( $req, 40 ) ) . ' Einträge' : '', static function () use ( $req ) {
		fge_cc_request_timeline( $req );
	} );
	$mails = function_exists( 'fge_mail_log_for_request' ) ? count( fge_mail_log_for_request( $req, 50 ) ) : 0;
	fge_cc_flow_fold( 'Mails', $mails . ' gesendet', static function () use ( $req ) {
		fge_cc_request_mails( $req );
	} );
	$until = (int) get_post_meta( $req, '_fge_cc_snooze', true );
	fge_cc_flow_fold( 'Schlummern', $until > time() ? 'bis ' . wp_date( 'd.m.Y', $until ) : 'aus', static function () use ( $req ) {
		fge_cc_request_snooze( $req );
	} );
	echo '</div>';
}

/** Kopf: links Firma und Ansprechpartner, rechts was angefragt wurde. */
function fge_cc_flow_header( int $req, array $flow ): void {
	$data = fge_get_request_email_data( $req );
	$name = trim( (string) $data['first_name'] . ' ' . (string) $data['last_name'] );
	$f    = fge_cc_request_fact_data( $req );
	$pax  = function_exists( 'fge_request_pax_current' ) ? fge_request_pax_current( $req ) : (int) $f['pax'];

	echo '<div class="cc-headgrid">';
	echo '<section class="cc-card cc-headcard">';
	echo '<div class="cc-headcard-top"><div class="cc-headcard-title">';
	echo '<span class="cc-head-kicker">Anfrage ' . esc_html( fge_request_number( $req ) . ' · eingegangen ' . get_the_date( 'd.m.Y', $req ) ) . '</span>';
	echo '<h1>' . esc_html( (string) get_post_meta( $req, '_fge_company_name', true ) ?: ( $name ?: 'Ohne Firma' ) ) . '</h1>';
	echo '</div>' . fge_cc_pill( $flow['label'], $flow['tone'] ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<div class="cc-headcard-contact"><span class="cc-label">Ansprechpartner</span><strong>' . esc_html( $name ?: 'ohne Namen' ) . '</strong>';
	echo '<span class="cc-headcard-links">';
	if ( '' !== trim( (string) $data['phone'] ) ) {
		echo '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', (string) $data['phone'] ) ) . '">' . esc_html( (string) $data['phone'] ) . '</a>';
	}
	if ( '' !== trim( (string) $data['contact_email'] ) ) {
		echo '<a href="mailto:' . esc_attr( (string) $data['contact_email'] ) . '">' . esc_html( (string) $data['contact_email'] ) . '</a>';
	}
	echo '</span></div>';
	echo '</section>';

	$place  = (string) get_post_meta( $req, '_fge_company_city', true ) ?: (string) get_post_meta( $req, '_fge_desired_region', true );
	$wishes = array_merge( $f['platz'], $f['fg'] );
	$facts  = [
		'Event'         => $f['event'],
		'Wunschtermine' => '' !== $f['dates'] ? preg_replace( '/\d\. /', '', $f['dates'] ) : 'offen',
		'Personen'      => $pax > 0 ? (string) $pax : 'offen',
		'Start'         => '' !== $f['start'] ? $f['start'] : 'offen',
		'Ort'           => $place,
		'Budget'        => (string) get_post_meta( $req, '_fge_budget_range', true ) ?: 'offen',
		'Leistungen'    => $wishes ? implode( ', ', $wishes ) : 'keine',
	];
	echo '<section class="cc-card cc-headcard"><h2 class="cc-h-small">Angefragt</h2><dl class="cc-factgrid">';
	foreach ( $facts as $k => $v ) {
		if ( '' !== trim( (string) $v ) ) {
			echo '<div><dt>' . esc_html( $k ) . '</dt><dd>' . esc_html( (string) $v ) . '</dd></div>';
		}
	}
	echo '</dl></section>';
	echo '</div>';
}

/** Titelzeile eines Schritts: Name, Stand, ein Satz dazu. */
function fge_cc_flow_pane_head( int $req, int $n, int $cur ): void {
	$titles = [ 1 => 'Bedarf klären', 2 => 'Golfplätze anfragen', 3 => 'Kalkulation', 4 => 'Angebot beim Kunden', 5 => 'Termin', 6 => 'Abrechnung' ];
	$sum    = fge_cc_flow_summary( $req, $n, $cur );
	$tag    = $n < $cur ? [ 'Erledigt', 'good' ] : ( $n === $cur ? [ 'Jetzt dran', 'brand' ] : [ 'Folgt', 'neutral' ] );
	echo '<div class="cc-stepcard-head"><div class="cc-stepcard-title"><h2>' . esc_html( $titles[ $n ] ) . '</h2>'
		. fge_cc_pill( $tag[0], $tag[1] ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	if ( '' !== $sum ) {
		echo '<p class="cc-stepcard-sum">' . esc_html( $sum ) . '</p>';
	}
	echo '</div>';
}

/** Ein Satz je Schritt, je nachdem ob erledigt, dran oder folgt. */
function fge_cc_flow_summary( int $req, int $n, int $cur ): string {
	$flow = fge_cc_flow( $req );
	$by   = $flow['count'];
	$done = $n < $cur;
	$now  = $n === $cur;
	switch ( $n ) {
		case 1:
			$d    = function_exists( 'fge_request_wish_date_labels' ) ? count( fge_request_wish_date_labels( $req ) ) : 0;
			$l    = count( fge_cc_need_services( $req ) );
			$open = count( array_filter( fge_cc_need_questions( $req ), static fn( $q ) => empty( $q['done'] ) ) );
			if ( $done ) {
				return $l . ( 1 === $l ? ' Leistung, ' : ' Leistungen, ' ) . $d . ( 1 === $d ? ' Wunschtermin' : ' Wunschtermine' ) . ( $open ? ', ' . $open . ' Fragen offen' : ', keine offenen Fragen' );
			}
			return $open ? $open . ( 1 === $open ? ' offene Frage' : ' offene Fragen' ) . ' an den Kunden' : 'Alles beisammen? Dann als geklärt markieren.';
		case 2:
			$asked = ( $by['angefragt'] ?? 0 ) + ( $by['zugesagt'] ?? 0 ) + ( $by['gewaehlt'] ?? 0 ) + ( $by['abgesagt'] ?? 0 );
			$yes   = ( $by['zugesagt'] ?? 0 ) + ( $by['gewaehlt'] ?? 0 );
			$open  = $by['angefragt'] ?? 0;
			if ( ! $flow['venues'] ) {
				return 'Golfplätze in der Nähe auswählen und anfragen.';
			}
			$txt = $asked . ' angefragt, ' . $yes . ( 1 === $yes ? ' Zusage' : ' Zusagen' ) . ( $open ? ', ' . $open . ' noch offen' : '' );
			return $now && $open ? $txt . '. Nachfassen oder Rückmeldung erfassen.' : $txt;
		case 3:
			if ( $done ) {
				$at = (int) get_post_meta( $req, '_fge_offer_sent_at', true );
				return 'Angebot ' . ( $at > 0 ? 'am ' . wp_date( 'd.m.Y', $at ) . ' ' : '' ) . 'gesendet';
			}
			return $now ? 'Rückmeldungen kalkulieren und das Angebot zusammenstellen.' : 'Sobald ein Golfplatz zugesagt hat';
		case 4:
			$offer = (string) get_post_meta( $req, '_fge_offer_status', true );
			if ( $done ) {
				$at = (int) strtotime( (string) get_post_meta( $req, '_fge_offer_accepted_at', true ) );
				return 'Angebot angenommen' . ( $at > 0 ? ' am ' . wp_date( 'd.m.Y', $at ) : '' );
			}
			if ( ! $now ) {
				return 'Sobald das Angebot versendet ist';
			}
			if ( 'declined' === $offer ) {
				return 'Der Kunde hat abgelehnt. Neu auflegen oder als verloren ablegen.';
			}
			$dl = (int) get_post_meta( $req, '_fge_offer_deadline', true );
			if ( $dl <= 0 ) {
				return 'Der Kunde entscheidet auf seiner Angebotsseite.';
			}
			$days = (int) floor( ( $dl - fge_cc_today() ) / DAY_IN_SECONDS );
			return 'Antwortfrist ' . wp_date( 'd.m.Y', $dl ) . ( $days >= 0 ? ', noch ' . $days . ( 1 === $days ? ' Tag' : ' Tage' ) : ', abgelaufen' );
		case 5:
			$date = fge_cc_event_date( $req );
			$txt  = $date > 0 ? 'Event am ' . wp_date( 'd.m.Y', $date ) : 'Eventtag steht fest';
			return $done ? $txt . ' durchgeführt' : ( $now ? $txt : 'Sobald der Kunde ein Angebot annimmt' );
		case 6:
			if ( $done ) {
				return 'Abgeschlossen';
			}
			return $now ? 'Rechnung in Lexoffice schreiben und den Vorgang abschließen.' : 'Nach dem Event';
	}
	return '';
}

/** Aufklappbare Karte unter den Schritten. */
function fge_cc_flow_fold( string $title, string $meta, callable $body, bool $open = false ): void {
	echo '<details class="cc-fold"' . ( $open ? ' open' : '' ) . '><summary><span class="cc-fold-title">' . esc_html( $title ) . '</span>'
		. '<span class="cc-fold-meta">' . esc_html( $meta ) . '</span></summary><div class="cc-fold-body">';
	$body();
	echo '</div></details>';
}

function fge_cc_flow_fold_tasks( int $req ): void {
	$tasks = fge_cc_tasks( $req );
	$mine  = count( array_filter( $tasks, static fn( $t ) => 'me' === $t['who'] ) );
	fge_cc_flow_fold( 'Aufgaben', $tasks ? count( $tasks ) . ' offen' . ( $mine ? ', ' . $mine . ' bei dir' : '' ) : 'nichts offen', static function () use ( $req ) {
		fge_cc_request_tasks( $req );
	}, $mine > 0 );
}

// ── Schritt 1: Bedarf ────────────────────────────────────────────────────────

/** Sind Wunschtermine schon in Antworten oder Abstimmungen verbaut? */
function fge_cc_need_dates_locked( int $req ): bool {
	if ( function_exists( 'fge_rr_get' ) && ! empty( fge_rr_get( $req ) ) ) {
		return true;
	}
	foreach ( function_exists( 'fge_venues_get' ) ? fge_venues_get( $req ) : [] as $v ) {
		if ( 'idee' !== (string) $v['status'] ) {
			return true;
		}
	}
	return false;
}

/**
 * Leistungen des Bedarfs: Häkchen aus dem Wizard, Wünsche aus dem Event-Dialog
 * und Freitextwünsche zeilenweise. Dieselben Quellen wie fge_venue_wishlist(),
 * damit der Bedarf genau das zeigt, was die Plätze gefragt werden.
 */
function fge_cc_need_services( int $req ): array {
	$out   = [];
	$wants = fge_catalog_wish_labels();
	foreach ( $wants as $k => $l ) {
		if ( '1' === (string) get_post_meta( $req, '_fge_' . $k, true ) ) {
			$out[ mb_strtolower( $l ) ] = [ 'label' => $l, 'want' => $k, 'src' => 'want' ];
		}
	}
	foreach ( [ '_fge_wishes_platz', '_fge_wishes_firmengolf' ] as $mk ) {
		foreach ( (array) get_post_meta( $req, $mk, true ) as $w ) {
			$w = trim( (string) $w );
			if ( '' !== $w && ! isset( $out[ mb_strtolower( $w ) ] ) ) {
				$key                        = (string) array_search( $w, $wants, true );
				$out[ mb_strtolower( $w ) ] = [ 'label' => $w, 'want' => $key, 'src' => 'wish' ];
			}
		}
	}
	foreach ( fge_cc_need_free_lines( $req ) as $w ) {
		if ( ! isset( $out[ mb_strtolower( $w ) ] ) ) {
			$out[ mb_strtolower( $w ) ] = [ 'label' => $w, 'want' => '', 'src' => 'frei' ];
		}
	}
	return $out;
}

/** Freitextwünsche aus dem Formular, je Zeile oder Komma ein Wunsch. */
function fge_cc_need_free_lines( int $req ): array {
	$extra = (string) get_post_meta( $req, '_fge_additional_wishes', true );
	return array_values( array_filter( array_map( static fn( $w ) => mb_substr( trim( $w ), 0, 120 ), preg_split( '/\r\n|\r|\n|,/', $extra ) ), 'strlen' ) );
}

/** Wer die Leistung liefern kann, als Hinweis in der Zeile. */
function fge_cc_need_who( string $label ): string {
	foreach ( function_exists( 'fge_provider_type_keywords' ) ? fge_provider_type_keywords() : [] as $words ) {
		foreach ( $words as $w ) {
			if ( false !== mb_stripos( $label, $w ) ) {
				return 'Golfplatz oder Dienstleister';
			}
		}
	}
	return 'Golfplatz';
}

/** Positionsschlüssel einer Leistung: Katalogschlüssel, sonst aus dem Text. */
function fge_cc_need_item_key( string $label ): string {
	$k = array_search( $label, fge_catalog_wish_labels(), true );
	return false !== $k ? (string) $k : fge_provider_item_key( $label );
}

/** Offene Fragen an den Kunden, je [id, t, done]. */
function fge_cc_need_questions( int $req ): array {
	return array_values( array_filter( (array) get_post_meta( $req, '_fge_need_questions', true ), 'is_array' ) );
}

/** Prüfpunkte vor „Bedarf ist geklärt", je [Text, erfüllt]. */
function fge_cc_need_checks( int $req ): array {
	$f     = fge_cc_request_fact_data( $req );
	$dates = function_exists( 'fge_request_wish_date_labels' ) ? fge_request_wish_date_labels( $req ) : [];
	$open  = count( array_filter( fge_cc_need_questions( $req ), static fn( $q ) => empty( $q['done'] ) ) );
	return [
		[ 'Mindestens ein Wunschtermin', (bool) $dates ],
		[ 'Personenzahl angegeben', (int) $f['pax'] > 0 ],
		[ 'Event oder Leistung angegeben', '' !== trim( $f['event'] ) || fge_cc_need_services( $req ) ],
		[ $open ? $open . ( 1 === $open ? ' Frage' : ' Fragen' ) . ' an den Kunden offen' : 'Alle Fragen geklärt', 0 === $open ],
	];
}

function fge_cc_flow_pane_need( int $req, int $cur ): void {
	$f      = fge_cc_request_fact_data( $req );
	$msg    = trim( (string) get_post_meta( $req, '_fge_message', true ) );
	$sent   = '1' === (string) get_post_meta( $req, '_fge_offer_sent', true );
	$locked = fge_cc_need_dates_locked( $req );
	$done   = (int) get_post_meta( $req, '_fge_need_done', true ) > 0;
	$flash  = sanitize_key( wp_unslash( $_GET['msg'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	// Nach Hinzufügen, Entfernen oder einer Frage bleibt die Bearbeitung offen.
	$keep   = in_array( $flash, [ 'need_saved', 'need_blocked', 'question_added', 'question_done', 'question_open', 'question_removed' ], true );

	if ( '' !== $msg ) {
		echo '<div class="cc-quote"><span class="cc-label">Nachricht des Kunden</span><p>' . nl2br( esc_html( $msg ) ) . '</p></div>';
	}

	// Geklärt: Zusammenfassung wie im Entwurf, „Anpassen" klappt die Bearbeitung auf.
	if ( $done ) {
		echo '<div class="cc-needsum">';
		fge_cc_need_readonly( $req, $f );
		echo '</div>';
		echo '<details class="cc-neededit"' . ( $keep ? ' open' : '' ) . '><summary class="cc-btn"><span class="cc-neededit-closed">Anpassen</span><span class="cc-neededit-open">Schließen</span></summary>';
	}

	$catering = trim( (string) get_post_meta( $req, '_fge_catering_notes', true ) );
	echo '<dl class="cc-factgrid cc-factgrid--wide">';
	foreach ( [
		'Event'                    => $f['event'],
		'Niveau'                   => $f['level'],
		'Preis auf der Eventseite' => $f['price'],
		'Essenswünsche'            => $catering,
	] as $k => $v ) {
		if ( '' !== trim( (string) $v ) ) {
			echo '<div><dt>' . esc_html( $k ) . '</dt><dd>' . esc_html( (string) $v ) . '</dd></div>';
		}
	}
	echo '</dl>';

	echo '<form class="cc-form cc-needform" id="cc-needform" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="fge_cc_need_save"><input type="hidden" name="request_id" value="' . (int) $req . '">';
	wp_nonce_field( 'fge_cc_need_save_' . $req );
	// Enter in einem Textfeld speichert nur und trifft nicht das erste „×".
	echo '<button type="submit" name="save_only" value="1" class="cc-sr" tabindex="-1" aria-hidden="true">Speichern</button>';

	echo '<div class="cc-fields cc-fields--4">';
	echo '<label class="cc-field"><span>Personen</span><input type="text" inputmode="numeric" name="pax" value="' . ( $f['pax'] > 0 ? (int) $f['pax'] : '' ) . '"></label>';
	echo '<label class="cc-field"><span>Start</span><input type="text" name="start" value="' . esc_attr( (string) get_post_meta( $req, '_fge_start_time', true ) ) . '" placeholder="' . esc_attr( '' !== $f['start'] ? $f['start'] : 'z. B. 14:00' ) . '"></label>';
	echo '<label class="cc-field cc-field--span2"><span>Budget</span><input type="text" name="budget" value="' . esc_attr( (string) get_post_meta( $req, '_fge_budget_range', true ) ) . '" placeholder="z. B. ca. 3.000 €"></label>';
	echo '</div>';

	// Wunschtermine: angefragte Termine sind an die Antworten der Plätze gebunden,
	// freie Felder lassen sich jederzeit füllen.
	echo '<div class="cc-block"><h3>Wunschtermine</h3><div class="cc-dateinputs">';
	for ( $i = 1; $i <= 3; $i++ ) {
		$val = (string) get_post_meta( $req, '_fge_preferred_date_' . $i, true );
		if ( $locked && '' !== trim( $val ) ) {
			echo '<span class="cc-datefixed" title="Schon bei Partnern angefragt">' . esc_html( $val ) . '</span>';
			continue;
		}
		echo '<input type="text" name="date[' . $i . ']" value="' . esc_attr( $val ) . '" placeholder="TT.MM.JJJJ" aria-label="Wunschtermin ' . $i . '">';
	}
	echo '</div>';
	if ( $locked ) {
		echo '<p class="cc-muted">Angefragte Termine sind an die Antworten gebunden. Ein neuer Termin in einem freien Feld geht nur an Partner, die du danach anfragst.</p>';
	}
	echo '</div>';

	// Leistungen: jede Zeile geht genau so in die Anfrage an die Partner.
	$services = fge_cc_need_services( $req );
	$wants    = fge_catalog_wish_labels();
	echo '<div class="cc-block"><h3>Leistungen</h3>';
	if ( ! $services ) {
		echo '<p class="cc-rowempty">Noch keine Leistung. Wähle unten eine aus oder trage einen Sonderwunsch ein.</p>';
	}
	foreach ( $services as $lk => $sv ) {
		echo '<div class="cc-svcrow">';
		echo '<span class="cc-svcrow-label"><strong>' . esc_html( $sv['label'] ) . '</strong><span class="cc-muted">' . esc_html( fge_cc_need_who( $sv['label'] ) ) . '</span></span>';
		echo '<input type="text" name="note[' . esc_attr( $lk ) . ']" value="' . esc_attr( fge_need_note( $req, $sv['label'] ) ) . '" placeholder="Details für die Anfrage, z. B. Menüwunsch, Abholort" aria-label="' . esc_attr( 'Details zu ' . $sv['label'] ) . '">';
		echo '<button type="submit" name="remove_one" value="' . esc_attr( $sv['label'] ) . '" class="cc-svcrow-x" title="Leistung entfernen"><span aria-hidden="true">×</span><span class="cc-sr">' . esc_html( $sv['label'] . ' entfernen' ) . '</span></button>';
		echo '</div>';
	}
	$free = array_filter( $wants, static fn( $l ) => ! isset( $services[ mb_strtolower( $l ) ] ) );
	echo '<div class="cc-svcadd">';
	if ( $free ) {
		echo '<select name="add_want" aria-label="Leistung auswählen" data-cc-autosubmit><option value="">Leistung auswählen</option>';
		foreach ( $free as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
		}
		echo '</select>';
	}
	echo '<input type="text" name="add_custom" placeholder="Sonderwunsch, z. B. Feuerwerk" aria-label="Sonderwunsch">';
	echo '<button type="submit" name="add_btn" value="1" class="cc-btn">Hinzufügen</button>';
	echo '</div>';
	echo '</div>';

	// Offene Fragen an den Kunden: eigene kleine Formulare über das form-Attribut,
	// damit sie mitten im Bedarf stehen können, ohne verschachtelte Formulare.
	$qs = fge_cc_need_questions( $req );
	echo '<div class="cc-block"><h3>Offene Fragen an den Kunden</h3>';
	foreach ( $qs as $q ) {
		$qid = sanitize_key( (string) $q['id'] );
		echo '<div class="cc-qrow' . ( ! empty( $q['done'] ) ? ' is-done' : '' ) . '">';
		echo '<button type="submit" form="cc-q-t-' . esc_attr( $qid ) . '" class="cc-qcheck" aria-label="' . esc_attr( ! empty( $q['done'] ) ? 'Wieder öffnen' : 'Als geklärt abhaken' ) . '">' . ( ! empty( $q['done'] ) ? '✓' : '' ) . '</button>';
		echo '<span class="cc-qtext">' . esc_html( (string) $q['t'] ) . '</span>';
		echo '<button type="submit" form="cc-q-d-' . esc_attr( $qid ) . '" class="cc-btn cc-btn--text">Entfernen</button>';
		echo '</div>';
	}
	echo '<div class="cc-qadd"><input type="text" name="question" form="cc-q-add" placeholder="Neue Frage notieren" aria-label="Neue Frage an den Kunden"><button type="submit" form="cc-q-add" class="cc-btn">Hinzufügen</button></div>';
	echo '</div>';

	// Fuß: Prüfpunkte, Hinweis, „Bedarf ist geklärt" oder „Änderungen speichern".
	$checks = fge_cc_need_checks( $req );
	$ok     = ! array_filter( $checks, static fn( $c ) => ! $c[1] );
	echo '<div class="cc-stepfoot cc-stepfoot--need">';
	echo '<ul class="cc-checks">';
	foreach ( $checks as [ $label, $pass ] ) {
		echo '<li class="' . ( $pass ? 'is-ok' : '' ) . '"><span aria-hidden="true">' . ( $pass ? '✓' : '!' ) . '</span>' . esc_html( $label ) . '</li>';
	}
	echo '</ul>';
	if ( $sent ) {
		echo '<span class="cc-note-warn">Das Angebot ist schon beim Kunden. Änderungen am Bedarf ändern das Angebot nicht.</span>';
	} elseif ( $locked ) {
		echo '<span class="cc-note-warn">Bereits angefragte Partner bekommen Änderungen nicht automatisch. Bei Bedarf erneut anfragen.</span>';
	}
	if ( $done ) {
		echo '<button type="submit" name="need_close" value="1" class="cc-btn cc-btn--primary">Änderungen speichern</button>';
	} else {
		echo '<button type="submit" name="need_done" value="1" class="cc-btn cc-btn--primary"' . ( $ok ? '' : ' title="Erst alle Punkte links erfüllen, ungespeicherte Änderungen zählen mit"' ) . '>Bedarf ist geklärt</button>';
	}
	echo '</div>';
	echo '</form>';
	if ( $done ) {
		echo '</details>';
	}

	foreach ( $qs as $q ) {
		$qid = sanitize_key( (string) $q['id'] );
		foreach ( [ 't' => 'fge_cc_question_toggle', 'd' => 'fge_cc_question_remove' ] as $kind => $action ) {
			echo '<form id="cc-q-' . $kind . '-' . esc_attr( $qid ) . '" class="cc-hiddenform" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
				. '<input type="hidden" name="action" value="' . esc_attr( $action ) . '"><input type="hidden" name="request_id" value="' . (int) $req . '"><input type="hidden" name="qid" value="' . esc_attr( $qid ) . '">';
			wp_nonce_field( $action . '_' . $req );
			echo '</form>';
		}
	}
	echo '<form id="cc-q-add" class="cc-hiddenform" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="fge_cc_question_add"><input type="hidden" name="request_id" value="' . (int) $req . '">';
	wp_nonce_field( 'fge_cc_question_add_' . $req );
	echo '</form>';
}

/** Bedarf als Lesesicht, sobald das Angebot draußen ist. */
function fge_cc_need_readonly( int $req, array $f ): void {
	$labels   = function_exists( 'fge_request_wish_date_labels' ) ? fge_request_wish_date_labels( $req ) : [];
	$services = array_map( static fn( $sv ) => $sv['label'] . ( '' !== fge_need_note( $req, $sv['label'] ) ? ' (' . fge_need_note( $req, $sv['label'] ) . ')' : '' ), fge_cc_need_services( $req ) );
	echo '<dl class="cc-factgrid cc-factgrid--wide">';
	foreach ( [
		'Personen'        => $f['pax'] > 0 ? (string) $f['pax'] : '',
		'Start'           => $f['start'],
		'Budget'          => (string) get_post_meta( $req, '_fge_budget_range', true ),
		'Wunschtermine'   => implode( ' · ', $labels ),
		'Leistungen'      => implode( ', ', $services ),
	] as $k => $v ) {
		if ( '' !== trim( (string) $v ) ) {
			echo '<div><dt>' . esc_html( $k ) . '</dt><dd>' . esc_html( (string) $v ) . '</dd></div>';
		}
	}
	echo '</dl>';
}

add_action( 'admin_post_fge_cc_need_save', static function (): void {
	$req = fge_cc_guard( 'fge_cc_need_save' );
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- fge_cc_guard prüft die Nonce.
	$changes = [];
	$set     = static function ( string $key, string $label, $new ) use ( $req, &$changes ): void {
		$old = (string) get_post_meta( $req, $key, true );
		if ( (string) $new !== $old ) {
			update_post_meta( $req, $key, $new );
			$changes[] = $label . ( '' !== $old ? ' ' . $old . ' → ' : ' ' ) . ( '' !== (string) $new ? $new : 'leer' );
		}
	};
	$before = array_map( 'strval', fge_venue_wishlist( $req ) );

	$pax = absint( wp_unslash( $_POST['pax'] ?? 0 ) );
	$set( '_fge_expected_participants', 'Personen', $pax > 0 ? (string) $pax : '' );
	$set( '_fge_start_time', 'Start', sanitize_text_field( wp_unslash( $_POST['start'] ?? '' ) ) );
	$set( '_fge_budget_range', 'Budget', sanitize_text_field( wp_unslash( $_POST['budget'] ?? '' ) ) );

	if ( isset( $_POST['date'] ) && is_array( $_POST['date'] ) ) {
		$locked = fge_cc_need_dates_locked( $req );
		$raw    = array_map( static fn( $d ) => sanitize_text_field( (string) $d ), wp_unslash( $_POST['date'] ) );
		for ( $i = 1; $i <= 3; $i++ ) {
			if ( ! array_key_exists( $i, $raw ) ) {
				continue;
			}
			// Schon angefragte Termine bleiben, nur freie Felder dürfen dazukommen.
			if ( $locked && '' !== trim( (string) get_post_meta( $req, '_fge_preferred_date_' . $i, true ) ) ) {
				continue;
			}
			$d = trim( (string) $raw[ $i ] );
			// TT.MM.JJJJ wie im Formular bekommt den Wochentag dazu.
			if ( preg_match( '/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $d, $m ) && checkdate( (int) $m[2], (int) $m[1], (int) $m[3] ) ) {
				$d = fge_format_wish_date( sprintf( '%04d-%02d-%02d', $m[3], $m[2], $m[1] ) );
			} elseif ( '' !== $d ) {
				$d = fge_format_wish_date( $d );
			}
			$set( '_fge_preferred_date_' . $i, 'Wunschtermin ' . $i, $d );
		}
	}

	// Leistungen: eine entfernen, aus dem Katalog dazunehmen, Sonderwunsch, Notizen.
	$wants = fge_catalog_wish_labels();
	$label = sanitize_text_field( (string) wp_unslash( $_POST['remove_one'] ?? '' ) );
	if ( '' !== $label ) {
		$k = array_search( $label, $wants, true );
		if ( false !== $k ) {
			update_post_meta( $req, '_fge_' . $k, 0 );
		}
		foreach ( [ '_fge_wishes_platz', '_fge_wishes_firmengolf' ] as $mk ) {
			$list = array_values( array_filter( array_map( 'strval', (array) get_post_meta( $req, $mk, true ) ), 'strlen' ) );
			$keep = array_values( array_filter( $list, static fn( $x ) => mb_strtolower( trim( $x ) ) !== mb_strtolower( $label ) ) );
			if ( count( $keep ) !== count( $list ) ) {
				update_post_meta( $req, $mk, $keep );
			}
		}
		$lines = fge_cc_need_free_lines( $req );
		$keep  = array_values( array_filter( $lines, static fn( $x ) => mb_strtolower( $x ) !== mb_strtolower( $label ) ) );
		if ( count( $keep ) !== count( $lines ) ) {
			update_post_meta( $req, '_fge_additional_wishes', implode( "\n", $keep ) );
		}
		$changes[] = $label . ' raus';
	}
	$add = sanitize_key( wp_unslash( $_POST['add_want'] ?? '' ) );
	if ( '' !== $add && isset( $wants[ $add ] ) && '1' !== (string) get_post_meta( $req, '_fge_' . $add, true ) ) {
		update_post_meta( $req, '_fge_' . $add, 1 );
		$changes[] = $wants[ $add ] . ' dazu';
	}
	$custom = mb_substr( trim( sanitize_text_field( wp_unslash( $_POST['add_custom'] ?? '' ) ) ), 0, 120 );
	if ( '' !== $custom && ! isset( fge_cc_need_services( $req )[ mb_strtolower( $custom ) ] ) ) {
		$k = array_search( mb_strtolower( $custom ), array_map( 'mb_strtolower', $wants ), true );
		if ( false !== $k ) {
			update_post_meta( $req, '_fge_' . $k, 1 );
		} else {
			$list   = array_values( array_filter( array_map( 'strval', (array) get_post_meta( $req, '_fge_wishes_platz', true ) ), 'strlen' ) );
			$list[] = $custom;
			update_post_meta( $req, '_fge_wishes_platz', $list );
		}
		$changes[] = 'Sonderwunsch ' . $custom . ' dazu';
	}
	$notes_in = (array) wp_unslash( $_POST['note'] ?? [] );
	$notes    = [];
	foreach ( fge_cc_need_services( $req ) as $lk => $sv ) {
		$n = mb_substr( trim( sanitize_text_field( (string) ( $notes_in[ $lk ] ?? '' ) ) ), 0, 300 );
		if ( '' === $n && ! isset( $notes_in[ $lk ] ) ) {
			$n = fge_need_note( $req, $sv['label'] );
		}
		if ( '' !== $n ) {
			$notes[ $lk ] = $n;
		}
	}
	$old_notes = (array) get_post_meta( $req, '_fge_need_notes', true );
	if ( $notes !== $old_notes ) {
		update_post_meta( $req, '_fge_need_notes', $notes );
		$changes[] = 'Notizen zu Leistungen';
	}
	$want_done  = ! empty( $_POST['need_done'] );
	$want_close = ! empty( $_POST['need_close'] );
	// phpcs:enable

	// Der Bedarf bestimmt die Anfrage: noch nicht angefragte Partner übernehmen
	// neue Leistungen und verlieren entfernte.
	fge_cc_need_sync_drafts( $req, $before, array_map( 'strval', fge_venue_wishlist( $req ) ) );

	if ( $changes ) {
		fge_activity_add( $req, 'note', 'Bedarf angepasst: ' . implode( ', ', $changes ) );
	}
	if ( $want_done ) {
		// Erst nach dem Speichern prüfen: gerade eingetragene Werte zählen mit.
		if ( array_filter( fge_cc_need_checks( $req ), static fn( $c ) => ! $c[1] ) ) {
			fge_cc_redirect( $req, 'need_blocked' );
		}
		update_post_meta( $req, '_fge_need_done', time() );
		fge_activity_add( $req, 'status', 'Bedarf geklärt' );
		fge_cc_redirect( $req, 'need_done' );
	}
	fge_cc_redirect( $req, $want_close ? 'need_updated' : 'need_saved' );
} );

/**
 * Leistungen, die im Bedarf dazukommen oder rausfliegen, in alle noch nicht
 * angefragten Golfplätze übernehmen. Dienstleister wählen ihre Leistungen
 * selbst über die Chips, angefragte Partner bleiben wie angefragt.
 */
function fge_cc_need_sync_drafts( int $req, array $before, array $after ): void {
	$low     = static fn( array $l ) => array_map( 'mb_strtolower', $l );
	$added   = array_diff( $low( $after ), $low( $before ) );
	$removed = array_diff( $low( $before ), $low( $after ) );
	if ( ! $added && ! $removed ) {
		return;
	}
	global $wpdb;
	foreach ( fge_venues_get( $req, 'platz' ) as $v ) {
		if ( 'idee' !== (string) $v['status'] ) {
			continue;
		}
		foreach ( fge_venue_items_get( (int) $v['id'] ) as $it ) {
			if ( 'green_fee' !== (string) $it['wish_key'] && in_array( mb_strtolower( (string) $it['label'] ), $removed, true ) ) {
				$wpdb->delete( fge_venue_items_table(), [ 'id' => (int) $it['id'] ] );
			}
		}
		foreach ( $after as $label ) {
			if ( in_array( mb_strtolower( $label ), $added, true ) ) {
				fge_venue_item_set( (int) $v['id'], fge_cc_need_item_key( $label ), [ 'label' => $label ] );
			}
		}
	}
}

// Offene Fragen an den Kunden: notieren, abhaken, entfernen.
add_action( 'admin_post_fge_cc_question_add', static function (): void {
	$req  = fge_cc_guard( 'fge_cc_question_add' );
	$text = mb_substr( trim( sanitize_text_field( wp_unslash( $_POST['question'] ?? '' ) ) ), 0, 300 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( '' === $text ) {
		fge_cc_redirect( $req, 'nothing' );
	}
	$qs   = fge_cc_need_questions( $req );
	$qs[] = [ 'id' => substr( md5( $text . microtime() ), 0, 10 ), 't' => $text, 'done' => 0 ];
	update_post_meta( $req, '_fge_need_questions', $qs );
	fge_activity_add( $req, 'note', 'Frage an den Kunden notiert: ' . $text );
	fge_cc_redirect( $req, 'question_added' );
} );

add_action( 'admin_post_fge_cc_question_toggle', static function (): void {
	$req = fge_cc_guard( 'fge_cc_question_toggle' );
	$qid = sanitize_key( wp_unslash( $_POST['qid'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$qs  = fge_cc_need_questions( $req );
	$msg = 'nothing';
	foreach ( $qs as &$q ) {
		if ( (string) $q['id'] === $qid ) {
			$q['done'] = empty( $q['done'] ) ? 1 : 0;
			$msg       = $q['done'] ? 'question_done' : 'question_open';
			if ( $q['done'] ) {
				fge_activity_add( $req, 'note', 'Frage geklärt: ' . (string) $q['t'] );
			}
		}
	}
	unset( $q );
	update_post_meta( $req, '_fge_need_questions', $qs );
	fge_cc_redirect( $req, $msg );
} );

add_action( 'admin_post_fge_cc_question_remove', static function (): void {
	$req = fge_cc_guard( 'fge_cc_question_remove' );
	$qid = sanitize_key( wp_unslash( $_POST['qid'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$qs  = array_values( array_filter( fge_cc_need_questions( $req ), static fn( $q ) => (string) $q['id'] !== $qid ) );
	update_post_meta( $req, '_fge_need_questions', $qs );
	fge_cc_redirect( $req, 'question_removed' );
} );

// ── Schritt 2: Golfplätze anfragen ──────────────────────────────────────────

function fge_cc_flow_pane_partners( int $req, int $cur ): void {
	// Wie im Entwurf: Leistungen aus dem Bedarf, die bei keinem Partner in der Liste stehen.
	if ( 'accepted' !== (string) get_post_meta( $req, '_fge_offer_status', true ) ) {
		$covered = [];
		foreach ( fge_venues_get( $req, 'all' ) as $v ) {
			if ( in_array( (string) $v['status'], [ 'abgesagt', 'keine' ], true ) ) {
				continue;
			}
			foreach ( (array) $v['items'] as $it ) {
				$covered[ mb_strtolower( (string) $it['label'] ) ] = true;
			}
		}
		$missing = array_values( array_filter( array_map( static fn( $sv ) => $sv['label'], fge_cc_need_services( $req ) ), static fn( $l ) => ! isset( $covered[ mb_strtolower( $l ) ] ) ) );
		if ( $missing ) {
			echo '<div class="cc-hint cc-hint--box">Für diese Leistungen ist noch niemand angefragt: <strong>' . esc_html( implode( ', ', $missing ) ) . '</strong></div>';
		}
	}
	if ( function_exists( 'fge_cc_venues_panel' ) ) {
		fge_cc_venues_panel( $req );
	}
	if ( function_exists( 'fge_cc_providers_panel' ) ) {
		fge_cc_providers_panel( $req );
	}
	$by  = fge_cc_flow( $req )['count'];
	$yes = ( $by['zugesagt'] ?? 0 ) + ( $by['gewaehlt'] ?? 0 );
	if ( 2 <= $cur && $cur <= 3 ) {
		echo '<div class="cc-stepfoot">';
		$open = $by['angefragt'] ?? 0;
		if ( ! $yes ) {
			echo '<span class="cc-muted">Mindestens eine Zusage nötig.</span>';
		} elseif ( $open ) {
			echo '<span class="cc-muted">Noch ' . (int) $open . ( 1 === $open ? ' Rückmeldung' : ' Rückmeldungen' ) . ' offen. Du kannst trotzdem schon kalkulieren.</span>';
		}
		echo '<button type="button" class="cc-btn cc-btn--primary"' . ( $yes ? '' : ' disabled' ) . ' data-cc-rtab-go="s3">Weiter zur Kalkulation</button>';
		echo '</div>';
	}
}

// ── Schritt 3: Kalkulation ───────────────────────────────────────────────────

function fge_cc_flow_pane_calc( int $req, int $cur ): void {
	$sent  = '1' === (string) get_post_meta( $req, '_fge_offer_sent', true );
	$yes   = static fn( $v ) => in_array( (string) $v['status'], [ 'zugesagt', 'gewaehlt' ], true );
	$golf  = array_values( array_filter( fge_cc_flow( $req )['venues'], $yes ) );
	$provs = array_values( array_filter( fge_venues_get( $req, 'dl' ), $yes ) );
	$open  = count( array_filter( fge_venues_get( $req, 'all' ), static fn( $v ) => 'angefragt' === (string) $v['status'] ) );

	// Rückmeldungen: was Plätze und Dienstleister zugesagt haben, auf einen Blick.
	if ( $golf || $provs ) {
		$n = count( $golf ) + count( $provs );
		echo '<div class="cc-block"><div class="cc-block-head"><h3>Rückmeldungen</h3><span class="cc-muted">' . (int) $n . ( 1 === $n ? ' Zusage' : ' Zusagen' ) . ( $open ? ' · ' . (int) $open . ' noch offen' : '' ) . '</span></div>';
		foreach ( array_merge( $golf, $provs ) as $v ) {
			fge_cc_flow_reply_summary( $req, $v );
		}
		echo '</div>';
	}

	$accepted = 'accepted' === (string) get_post_meta( $req, '_fge_offer_status', true );
	if ( $accepted || ( $sent && '1' !== (string) get_post_meta( $req, '_fge_offer_options_mode', true ) ) ) {
		echo '<p class="cc-muted">Das Angebot ist verschickt. Den Stand siehst du im Schritt Angebot.</p>';
	} elseif ( ! $sent && function_exists( 'fge_rr_final_index' ) && fge_rr_final_index( $req ) > 0 ) {
		echo '<div class="cc-block"><h3>Termin bestätigt, Angebot senden</h3>';
		fge_cc_phase_offer_ready( $req );
		echo '</div>';
	} elseif ( function_exists( 'fge_cc_packages_panel' ) ) {
		fge_cc_packages_panel( $req );
	}

	// Sonderweg ohne Optionen, mit dem alten Positions-Editor.
	if ( ! $sent && ! ( function_exists( 'fge_rr_final_index' ) && fge_rr_final_index( $req ) > 0 ) ) {
		$wishes = function_exists( 'fge_rr_wish_dates' ) ? fge_rr_wish_dates( $req ) : [];
		echo '<details class="cc-details cc-details--card"><summary>Sonderfall: Termin direkt bestätigen (ohne Angebote A, B, C)</summary>';
		if ( function_exists( 'fge_cc_positions_panel' ) ) {
			fge_cc_positions_panel( $req );
			if ( function_exists( 'fge_cc_provider_datalist' ) ) {
				fge_cc_provider_datalist();
			}
		}
		fge_cc_confirm_date_form( $req, $wishes );
		echo '</details>';
	}
}

/** Eine Zeile „Rückmeldung": frei an welchen Terminen, welche Preise. */
function fge_cc_flow_reply_summary( int $req, array $v ): void {
	$labels = function_exists( 'fge_request_wish_date_labels' ) ? fge_request_wish_date_labels( $req ) : [];
	$free   = [];
	foreach ( function_exists( 'fge_venue_dates_get' ) ? fge_venue_dates_get( (int) $v['id'] ) : [] as $idx => $d ) {
		if ( 1 === $d['available'] && isset( $labels[ $idx ] ) ) {
			$free[] = $labels[ $idx ];
		}
	}
	$money = static fn( $p, $gross, $basis ) => number_format_i18n( (float) $p, 2 ) . ' € ' . ( (int) $gross ? 'brutto' : 'netto' ) . ' ' . ( 'person' === $basis ? 'p. P.' : 'pauschal' );
	echo '<div class="cc-reply">';
	echo '<div class="cc-reply-who"><strong>' . esc_html( (string) $v['title'] ) . '</strong><span class="cc-muted">Frei am ' . esc_html( $free ? implode( ', ', $free ) : 'keinem Wunschtermin' ) . '</span></div>';
	echo '<div class="cc-reply-items">';
	if ( 'dl' !== (string) ( $v['kind'] ?? 'platz' ) && (float) $v['price'] > 0 ) {
		echo '<div><span>Event und Platznutzung</span><span>' . esc_html( $money( $v['price'], $v['price_gross'], $v['price_basis'] ) ) . '</span></div>';
	}
	foreach ( (array) $v['items'] as $it ) {
		if ( 'green_fee' === (string) $it['wish_key'] ) {
			continue;
		}
		$val = ! (int) $it['available'] ? 'nicht möglich' : ( (float) $it['price'] > 0 ? $money( $it['price'], $it['price_gross'], $it['price_basis'] ) : 'kein Preis genannt' );
		echo '<div><span>' . esc_html( (string) $it['label'] ) . '</span><span>' . esc_html( $val ) . '</span></div>';
	}
	if ( '' !== trim( (string) $v['note'] ) ) {
		echo '<p class="cc-muted">' . esc_html( (string) $v['note'] ) . '</p>';
	}
	echo '</div></div>';
}

// ── Schritt 4: Angebot beim Kunden ──────────────────────────────────────────

function fge_cc_flow_pane_offer( int $req, int $cur ): void {
	$offer    = (string) get_post_meta( $req, '_fge_offer_status', true );
	$sent_at  = (int) get_post_meta( $req, '_fge_offer_sent_at', true );
	$deadline = (int) get_post_meta( $req, '_fge_offer_deadline', true );
	$data     = fge_get_request_email_data( $req );
	$net      = function_exists( 'fge_cc_offer_net' ) ? fge_cc_offer_net( $req ) : 0.0;

	// Erinnerung an den Kunden: einmal nach drei Tagen, solange die Frist läuft.
	$rem_days = (int) apply_filters( 'fge_offer_reminder_days', 3 );
	if ( 'accepted' === $offer ) {
		$rem = 'Beendet, Angebot angenommen';
	} elseif ( '1' === (string) get_post_meta( $req, '_fge_offer_remind_off', true ) ) {
		$rem = 'aus, beim Senden abgeschaltet';
	} elseif ( '1' === (string) get_post_meta( $req, '_fge_offer_reminded', true ) ) {
		$rem = 'Erinnerung gesendet';
	} elseif ( $sent_at > 0 && 'pending' === $offer ) {
		$rem = 'Erinnerung am ' . wp_date( 'd.m.Y', $sent_at + $rem_days * DAY_IN_SECONDS ) . ', geplant';
	} else {
		$rem = 'aus';
	}
	$dl_days = $deadline > 0 ? (int) floor( ( $deadline - fge_cc_today() ) / DAY_IN_SECONDS ) : 0;
	echo '<dl class="cc-factgrid cc-factgrid--soft">';
	echo '<div><dt>Online gesendet</dt><dd>' . esc_html( $sent_at > 0 ? wp_date( 'd.m.Y', $sent_at ) . ' an ' . ( function_exists( 'fge_offer_recipient' ) ? fge_offer_recipient( $req ) : (string) $data['contact_email'] ) : 'noch nicht' ) . '</dd></div>';
	echo '<div><dt>Antwortfrist für den Kunden</dt><dd class="' . ( 'pending' === $offer && $deadline > 0 && $dl_days <= 2 ? 'is-bad' : '' ) . '">'
		. esc_html( $deadline > 0 ? wp_date( 'd.m.Y', $deadline ) . ( 'pending' === $offer ? ( $dl_days >= 0 ? ' · noch ' . $dl_days . ( 1 === $dl_days ? ' Tag' : ' Tage' ) : ' · abgelaufen' ) : '' ) : 'keine' ) . '</dd></div>';
	echo '<div><dt>Automatisches Follow-up</dt><dd>' . esc_html( $rem ) . '</dd></div>';
	if ( $net > 0 ) {
		echo '<div><dt>Summe netto</dt><dd>' . esc_html( number_format_i18n( $net, 2 ) . ' €' ) . '</dd></div>';
	}
	echo '</dl>';

	// Die Optionen und was der Kunde damit gemacht hat.
	$snap = (array) get_post_meta( $req, '_fge_offer_snapshot', true );
	$opts = (array) ( $snap['options_all'] ?? ( $snap['options'] ?? [] ) );
	$pick = (string) ( $snap['chosen_option'] ?? '' );
	if ( $opts ) {
		echo '<div class="cc-block">';
		foreach ( $opts as $o ) {
			$key = (string) ( $o['key'] ?? '' );
			if ( 'accepted' === $offer ) {
				$tag = $key === $pick ? [ 'Angenommen', 'good' ] : [ 'Nicht gewählt', 'neutral' ];
			} elseif ( 'declined' === $offer ) {
				$tag = [ 'Abgelehnt', 'bad' ];
			} else {
				$tag = [ 'Offen', 'warn' ];
			}
			$dates = array_map( static fn( $d ) => (string) ( $d['label'] ?? '' ), (array) ( $o['dates'] ?? [] ) );
			echo '<div class="cc-optrow"><div><strong>Option ' . esc_html( $key ) . ' · ' . esc_html( (string) ( $o['title'] ?? '' ) ) . '</strong>'
				. '<span class="cc-muted">' . esc_html( $dates ? 'Termine ' . implode( ', ', $dates ) : '' ) . '</span></div>'
				. fge_cc_pill( $tag[0], $tag[1] ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div>';
	}

	if ( 'accepted' === $offer ) {
		$acc = (int) strtotime( (string) get_post_meta( $req, '_fge_offer_accepted_at', true ) );
		echo '<p class="cc-done">Angenommen' . ( $acc > 0 ? ' am ' . esc_html( wp_date( 'd.m.Y H:i', $acc ) ) : '' ) . '. Buchungsmails sind raus, weiter im Schritt Termin.</p>';
		if ( function_exists( 'fge_offer_link' ) ) {
			echo '<p><a href="' . esc_url( fge_offer_link( $req ) ) . '" target="_blank" rel="noopener">Angebotsseite des Kunden öffnen</a></p>';
		}
	} elseif ( 'declined' === $offer ) {
		fge_cc_phase_offer_declined( $req );
	} else {
		fge_cc_phase_offer_running( $req );
	}
}

// ── Schritt 5: Termin ────────────────────────────────────────────────────────

function fge_cc_flow_pane_date( int $req, int $cur ): void {
	$date   = fge_cc_event_date( $req );
	$pax    = function_exists( 'fge_request_pax_current' ) ? fge_request_pax_current( $req ) : 0;
	$data   = fge_get_request_email_data( $req );
	$status = (string) get_post_meta( $req, '_fge_request_status', true );
	$extras = function_exists( 'fge_booked_extras' ) ? array_filter( array_map( static fn( $x ) => (string) $x['label'], fge_booked_extras( $req ) ) ) : [];

	echo '<dl class="cc-factgrid cc-factgrid--soft">';
	echo '<div><dt>Event</dt><dd>' . esc_html( ( $date > 0 ? fge_cc_date( $date ) : 'Datum offen' ) . ( $pax > 0 ? ' · ' . $pax . ' Personen' : '' ) ) . '</dd></div>';
	echo '<div><dt>Gebucht bei</dt><dd>' . esc_html( (string) $data['partner_title'] ?: 'offen' ) . '</dd></div>';
	echo '<div><dt>Status</dt><dd>' . esc_html( function_exists( 'fge_cc_status_label' ) ? fge_cc_status_label( $status ) : $status ) . '</dd></div>';
	if ( $extras ) {
		echo '<div><dt>Gebuchte Leistungen</dt><dd>' . esc_html( implode( ', ', $extras ) ) . '</dd></div>';
	}
	echo '</dl>';

	if ( function_exists( 'fge_day_fields' ) ) {
		$filled = 0;
		foreach ( array_keys( fge_day_fields() ) as $k ) {
			if ( '' !== trim( (string) get_post_meta( $req, '_fge_' . $k, true ) ) ) {
				$filled++;
			}
		}
		echo '<p class="cc-muted">Eventtag-Felder: ' . (int) $filled . ' von ' . (int) count( fge_day_fields() ) . ' ausgefüllt.</p>';
	}
	if ( $date > 0 && ! in_array( $status, [ 'abgeschlossen', 'verloren' ], true ) ) {
		echo '<p class="cc-muted">Der Status wechselt am ' . esc_html( wp_date( 'd.m.Y', $date + DAY_IN_SECONDS ) ) . ' um 7:00 Uhr automatisch auf Abgeschlossen.</p>';
	}
	fge_cc_phase_booked( $req );
}

// ── Schritt 6: Abrechnung ────────────────────────────────────────────────────

function fge_cc_flow_pane_billing( int $req, int $cur ): void {
	$status = (string) get_post_meta( $req, '_fge_request_status', true );
	if ( 'abgeschlossen' !== $status ) {
		echo '<div class="cc-venue-actions">';
		foreach ( fge_cc_status_actions() as $slug => $slabel ) {
			if ( $slug === $status ) {
				continue;
			}
			fge_cc_button( 'fge_cc_status', $req, $slabel, [
				'class'   => 'abgeschlossen' === $slug ? 'cc-btn cc-btn--primary' : 'cc-btn',
				'fields'  => [ 'status' => $slug ],
				'confirm' => 'Status auf „' . $slabel . '" setzen?',
			] );
		}
		echo '</div>';
	}
	fge_cc_request_lex_tab( $req );
}

// ── Am Tag nach dem Event automatisch abschließen (Julius, 15.10.2026) ───────

/**
 * Läuft mit der Tagesmail um 07:00. Gebuchte Vorgänge, deren Event vor heute
 * lag, gehen auf „abgeschlossen". Der Statuswechsel löst wie von Hand die
 * Danke-Mail mit Bewertungsbitte aus (review-request.php), einmal je Anfrage,
 * aber nur für Events der letzten sieben Tage.
 */
function fge_cc_auto_close(): int {
	$ids = get_posts( [
		'post_type'   => 'firmengolf_request',
		'post_status' => [ 'publish', 'draft', 'private' ],
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_key'    => '_fge_offer_status', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value'  => 'accepted', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	] );
	$n = 0;
	foreach ( $ids as $req ) {
		if ( function_exists( 'fge_is_demo_request' ) && fge_is_demo_request( (int) $req ) ) {
			continue;
		}
		$status = (string) get_post_meta( $req, '_fge_request_status', true );
		if ( in_array( $status, [ 'abgeschlossen', 'verloren' ], true ) ) {
			continue;
		}
		$date = fge_cc_event_date( (int) $req );
		if ( $date <= 0 || $date >= fge_cc_today() ) {
			continue;
		}
		// Altfälle (Event länger als eine Woche her) schließen still: eine Danke-Mail
		// Wochen nach dem Event wirkt wie ein Versehen. Betrifft vor allem den
		// ersten Lauf nach dem Deploy.
		$late = $date < fge_cc_today() - 7 * DAY_IN_SECONDS;
		if ( $late && '1' !== (string) get_post_meta( $req, '_fge_review_mail_sent', true ) ) {
			update_post_meta( $req, '_fge_review_mail_sent', '1' );
			update_post_meta( $req, '_fge_review_mail_skipped', 'auto_close_late' );
		}
		fge_request_set_status( (int) $req, 'abgeschlossen' );
		fge_activity_add( (int) $req, 'system', 'Am Tag nach dem Event automatisch abgeschlossen' . ( $late ? ' (Event lag über eine Woche zurück, ohne Danke-Mail)' : '' ) );
		$n++;
	}
	return $n;
}
add_action( 'fge_cc_daily_digest', 'fge_cc_auto_close', 5 );
