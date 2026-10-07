"""
Testdaten für den Handtest über MailHog (nur localhost!).

Räumt alle Anfragen samt Platz-Pipeline, Verlauf und Mail-Protokoll weg und legt
die wichtigsten Fälle neu an, jeweils über die echten Formulare (Eventseite,
Wizard, Kurz-Anfrage, Budget-Rechner). Dadurch liegen auch die Eingangsmails in
MailHog, wie bei einer echten Anfrage.

Aufruf (docker compose läuft):  python3 tests/testdaten.py
"""
import json, os, re, subprocess, sys, time
import requests

sys.path.insert(0, os.path.dirname(__file__))
from fge_driver import BASE, MH  # noqa

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
if 'localhost' not in BASE:
    sys.exit('Nur lokal erlaubt.')


def wp(code):
    r = subprocess.run(['docker', 'compose', 'exec', '-T', 'wordpress', 'wp', 'eval', code, '--allow-root'],
                       cwd=ROOT, capture_output=True, text=True)
    return r.stdout.strip()


def reset():
    print('Alte Anfragen entfernen …')
    print(wp(r'''
        $ids = get_posts( [ 'post_type' => 'firmengolf_request', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ] );
        foreach ( $ids as $id ) { wp_delete_post( $id, true ); }
        global $wpdb;
        foreach ( [ 'fge_request_venues', 'fge_request_venue_items', 'fge_request_venue_dates', 'fge_request_responses', 'fge_activity', 'fge_mail_log' ] as $t ) {
            $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}{$t}" );
        }
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_fge\_rl\_%' OR option_name LIKE '\_transient\_timeout\_fge\_rl\_%'" );
        echo count( $ids ) . ' Anfragen gelöscht';
    '''))
    requests.delete(MH + '/api/v1/messages')


def partner_events():
    """Drei freigegebene Events des lokalen Testpartners Golfpark Weidenhof (358).

    Der Muster-Partner (Demo) taugt nicht: für ihn gehen bewusst keine Mails raus.
    Das vorhandene Schnupperkurs-Event wird freigegeben, zwei weitere entstehen als
    Kopie vorhandener Platzhalter-Events. Mehrfacher Aufruf legt nichts doppelt an.
    """
    out = wp(r"""
        $pid = 358;
        update_post_meta( 1102, '_fge_event_status', 'freigegeben' );
        $make = function ( int $src, string $slug, string $title, array $addons ) use ( $pid ): int {
            $have = get_page_by_path( $slug, OBJECT, 'firmengolf_event' );
            if ( $have ) { return (int) $have->ID; }
            $p   = get_post( $src );
            $new = wp_insert_post( [ 'post_type' => 'firmengolf_event', 'post_status' => 'publish', 'post_title' => $title,
                'post_name' => $slug, 'post_content' => $p->post_content, 'post_excerpt' => $p->post_excerpt ] );
            foreach ( get_post_meta( $src ) as $k => $vals ) {
                if ( in_array( $k, [ '_edit_lock', '_edit_last', '_fge_views_count', '_fge_requests_count', '_wp_old_slug' ], true ) ) { continue; }
                foreach ( $vals as $v ) { add_post_meta( $new, $k, maybe_unserialize( $v ) ); }
            }
            foreach ( get_object_taxonomies( 'firmengolf_event' ) as $tax ) {
                wp_set_object_terms( $new, wp_get_object_terms( $src, $tax, [ 'fields' => 'ids' ] ), $tax );
            }
            update_post_meta( $new, '_fge_assigned_partner_id', $pid );
            update_post_meta( $new, '_fge_provider_type', 'golfplatz_partner' );
            update_post_meta( $new, '_fge_event_status', 'freigegeben' );
            update_post_meta( $new, '_fge_city', 'Pinneberg' );
            update_post_meta( $new, '_fge_public_golfclub_name', 'Golfpark Weidenhof' );
            update_post_meta( $new, '_fge_event_location', 'Golfpark Weidenhof, Mühlenstraße 140, 25421 Pinneberg' );
            update_post_meta( $new, '_fge_event_addons', $addons );
            return (int) $new;
        };
        $a = $make( 417, 'firmenturnier-am-weidenhof-test', 'Firmenturnier am Weidenhof (TEST)', [ 'Live-Scoring', 'Gebrandete Abschläge', 'Fotograf' ] );
        $b = $make( 416, 'afterwork-golf-mit-grillabend-weidenhof-test', 'Afterwork-Golf mit Grillabend am Weidenhof (TEST)', [ 'Grillabend', 'Getränkepauschale' ] );
        echo 1102 . ' ' . get_post_field( 'post_name', 1102 ) . "
" . $a . ' firmenturnier-am-weidenhof-test' . "
" . $b . ' afterwork-golf-mit-grillabend-weidenhof-test';
    """)
    print('Partner-Events (Golfpark Weidenhof):\n' + out)
    return [line.split(' ', 1) for line in out.splitlines()]


def unthrottle():
    """Das IP-Limit (8 Anfragen in 10 Minuten) würde den Lauf sonst stoppen."""
    wp(r"""global $wpdb; $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_fge\_rl\_%' OR option_name LIKE '\_transient\_timeout\_fge\_rl\_%'" ); wp_cache_flush();""")


def event_tokens(slug):
    p = requests.get(f'{BASE}/firmenevents/{slug}/').text
    nonce = re.search(r"nonce:\s*'([a-f0-9]{10})'", p).group(1)
    ft = re.search(r"var fgeFt = '([^']+)'", p).group(1)
    return nonce, ft


def modal(label, slug, event_id, f):
    nonce, ft = event_tokens(slug)
    time.sleep(5)  # Zeitfalle des Spam-Schutzes
    data = {'action': 'fge_modal_anfrage', 'nonce': nonce, 'fge_ft': ft, 'fge_js': ft[::-1], 'fge_hp': '',
            'event_id': event_id, 'consent': '1', 'contact_pref': 'E-Mail', 'diet': '', 'notes': '', 'partnercode': '',
            'date2': '', 'date3': '', 'wishes': '[]', **f}
    if isinstance(data['wishes'], list):
        data['wishes'] = json.dumps(data['wishes'])
    unthrottle()
    r = requests.post(BASE + '/wp-admin/admin-ajax.php', data=data)
    report(label, r)


def wizard(label, f):
    p = requests.get(BASE + '/individuelle-events/').text
    cfg = json.loads(re.search(r'var FGE_IND = (\{.*?\});', p).group(1))
    time.sleep(5)
    data = {'action': 'fge_general_request', 'nonce': cfg['nonce'], 'fge_ft': cfg['ft'], 'fge_js': cfg['ft'][::-1],
            'consent': '1', 'goal': '', 'place': '', 'budget': '', 'when': '', 'flex': '', 'startzeit': '', 'duration': '',
            'experience': '', 'date2': '', 'date3': '', 'city': '', 'phone': '', 'contact_pref': 'E-Mail', 'diet': '',
            'notes': '', 'services': '', 'partnercode': '', **f}
    unthrottle()
    r = requests.post(BASE + '/wp-admin/admin-ajax.php', data=data)
    report(label, r)


def budget_lead(label, f):
    p = requests.get(BASE + '/individuelle-events/').text
    cfg = json.loads(re.search(r'var FGE_IND = (\{.*?\});', p).group(1))
    time.sleep(5)
    data = {'action': 'fge_budget_unlock', 'nonce': cfg['bcNonce'], 'fge_ft': cfg['ft'], 'fge_js': cfg['ft'][::-1], **f}
    unthrottle()
    r = requests.post(BASE + '/wp-admin/admin-ajax.php', data=data)
    report(label, r)


CREATED = []


def report(label, r):
    ok = r.status_code == 200 and '"success":true' in r.text
    ref = ''
    try:
        ref = (r.json().get('data') or {}).get('ref', '')
    except Exception:
        pass
    CREATED.append((label, ref, ok))
    print(('  ok   ' if ok else '  FEHLER ') + (ref or '      ') + '  ' + label + ('' if ok else '  ' + r.text[:200]))


def cases():
    """Alle Fälle in fester Reihenfolge, je ein Aufruf."""
    pe = None

    def partner(i):
        nonlocal pe
        if pe is None:
            pe = partner_events()
        return pe[i]

    return [
        ('Klein, ohne Extras: Teamevent Hamburg, 6 Personen, ein Termin', lambda: (
        modal('Klein, ohne Extras: Teamevent Hamburg, 6 Personen, ein Termin', 'golf-teamevent-in-hamburg', 416, {
            'group_size': '6', 'first_name': 'Klara', 'last_name': 'Klein', 'email': 'klara.klein@example.org',
            'company': 'Kleinbüro Nord (TEST)', 'phone': '040 111 222', 'city': 'Hamburg',
            'experience': 'Alle Anfänger', 'starttime': 'Vormittag', 'date1': '2026-11-06'})
        )),
        ('Groß mit allem: Firmen-Golfturnier Hamburg, 48 Personen, Extras, Sonderwünsche, Partnercode', lambda: (
        modal('Groß mit allem: Firmen-Golfturnier Hamburg, 48 Personen, Extras, Sonderwünsche, Partnercode', 'firmen-golfturnier-in-hamburg', 417, {
            'group_size': '48', 'first_name': 'Gregor', 'last_name': 'Groß', 'email': 'gregor.gross@example.org',
            'company': 'Großhandel Elbe AG (TEST)', 'phone': '0171 4455667', 'city': 'Hamburg',
            'experience': 'Gemischt', 'starttime': 'Ganztägig', 'contact_pref': 'Telefon',
            'date1': '2026-11-13', 'date2': '2026-11-20', 'date3': '2026-11-27',
            'diet': '4x vegetarisch, 1x vegan, 1x glutenfrei',
            'notes': 'Sonderwunsch: Siegerehrung mit Pokalen für die drei besten Flights, Longest Drive und Nearest to the Pin. '
                     'Unser Vorstand kommt erst ab 15 Uhr dazu. Rechnung bitte an die Zentrale mit Kostenstelle 4711.',
            'partnercode': 'DGVTEST',
            'wishes': [{'label': 'Abendessen', 'source': 'platz'}, {'label': 'Getränkepauschale', 'source': 'platz'},
                       {'label': 'Live-Scoring', 'source': 'platz'}, {'label': 'Gebrandete Abschläge', 'source': 'platz'},
                       {'label': 'Fotograf', 'source': 'platz'}, {'label': 'Shuttle', 'source': 'firmengolf'}]})
        )),
        ('Indoor: Indoor Team Golf Hamburg, 10 Personen, Preis pro Box und Stunde', lambda: (
        modal('Indoor: Indoor Team Golf Hamburg, 10 Personen, Preis pro Box und Stunde', 'indoor-team-golf-in-hamburg', 2082, {
            'group_size': '10', 'first_name': 'Ines', 'last_name': 'Indoor', 'email': 'ines.indoor@example.org',
            'company': 'Simulatix GmbH (TEST)', 'phone': '040 999 000', 'city': 'Hamburg',
            'experience': 'Gemischt', 'starttime': 'After-Work', 'date1': '2026-11-19', 'date2': '2026-11-26',
            'notes': 'Gern mit kleinem Turnier am Ende.',
            'wishes': [{'label': 'Getränke & Snacks', 'source': 'platz'}, {'label': 'Turniermodus mit Preisen', 'source': 'platz'}]})
        )),
        ('Weihnachtsfeier Hamburg, 25 Personen, Dezember, Extras und Essenswünsche', lambda: (
        modal('Weihnachtsfeier Hamburg, 25 Personen, Dezember, Extras und Essenswünsche', 'weihnachtsfeier-mit-golf-in-hamburg', 1015, {
            'group_size': '25', 'first_name': 'Wiebke', 'last_name': 'Winter', 'email': 'wiebke.winter@example.org',
            'company': 'Winterhaus Consulting (TEST)', 'phone': '040 2412 2412', 'city': 'Hamburg',
            'experience': 'Überwiegend Anfänger', 'starttime': 'Abends', 'date1': '2026-12-04', 'date2': '2026-12-11',
            'diet': '3x vegetarisch', 'notes': 'Bitte mit Glühwein-Empfang und Wichteln am Ende.',
            'wishes': [{'label': 'Abendessen', 'source': 'platz'}, {'label': 'Getränkepauschale', 'source': 'platz'},
                       {'label': 'Live-Musik oder DJ', 'source': 'platz'}, {'label': 'Shuttle-Service', 'source': 'platz'}]})
        )),
        ('Platzreife Hamburg, 4 Personen, ohne Extras', lambda: (
        modal('Platzreife Hamburg, 4 Personen, ohne Extras', 'platzreifekurs-in-hamburg', 418, {
            'group_size': '4', 'first_name': 'Paul', 'last_name': 'Platz', 'email': 'paul.platz@example.org',
            'company': 'Platzhirsch Software (TEST)', 'phone': '', 'city': 'Norderstedt',
            'experience': 'Alle Anfänger', 'starttime': 'Wochenende', 'date1': '2026-11-14', 'date2': '2026-11-21'})
        )),
        ('Partner-Event ohne Sonderwünsche: Golf-Schnupperkurs für Teams, 8 Personen', lambda: (
        modal('Partner-Event ohne Sonderwünsche: Golf-Schnupperkurs für Teams, 8 Personen', partner(0)[1], int(partner(0)[0]), {
            'group_size': '8', 'first_name': 'Sven', 'last_name': 'Sonne', 'email': 'sven.sonne@example.org',
            'company': 'Sonnenschein Media (TEST)', 'phone': '040 123 456', 'city': 'Pinneberg',
            'experience': 'Alle Anfänger', 'starttime': 'Vormittag', 'date1': '2026-11-10', 'date2': '2026-11-17'})
        )),
        ('Partner-Event mit Sonderwünschen: Firmenturnier am Weidenhof, 30 Personen', lambda: (
        modal('Partner-Event mit Sonderwünschen: Firmenturnier am Weidenhof, 30 Personen', partner(1)[1], int(partner(1)[0]), {
            'group_size': '30', 'first_name': 'Tanja', 'last_name': 'Turnier', 'email': 'tanja.turnier@example.org',
            'company': 'Turnierwerk KG (TEST)', 'phone': '0151 2223334', 'city': 'Hamburg',
            'experience': 'Überwiegend Golfer mit Handicap', 'starttime': 'Mittags', 'contact_pref': 'Egal',
            'date1': '2026-11-12', 'date2': '2026-11-19', 'date3': '2026-11-26',
            'diet': '2x vegetarisch, 1x laktosefrei',
            'notes': 'Wir möchten Kunden einladen. Bitte Firmenlogo auf den Tee-Markern und eine kurze Rede des Clubpräsidenten.',
            'wishes': [{'label': 'Live-Scoring', 'source': 'platz'}, {'label': 'Gebrandete Abschläge', 'source': 'platz'},
                       {'label': 'Abendessen', 'source': 'platz'}, {'label': 'Shuttle', 'source': 'firmengolf'}]})
        )),
        ('Partner-Event mit Extra: Afterwork-Golf mit Grillabend, 12 Personen', lambda: (
        modal('Partner-Event mit Extra: Afterwork-Golf mit Grillabend, 12 Personen', partner(2)[1], int(partner(2)[0]), {
            'group_size': '12', 'first_name': 'Ali', 'last_name': 'Abend', 'email': 'ali.abend@example.org',
            'company': 'Feierabend Bau GmbH (TEST)', 'phone': '0160 7778889', 'city': 'Hamburg',
            'experience': 'Gemischt', 'starttime': 'After-Work', 'date1': '2026-11-11',
            'notes': 'Zwei Kollegen kommen mit dem Rollstuhl, bitte barrierearm planen.',
            'wishes': [{'label': 'Grillabend', 'source': 'platz'}, {'label': 'Getränkepauschale', 'source': 'platz'}]})
        )),
        ('Wizard, allgemeine Anfrage: Sommerfest Stuttgart, 35 Personen, Services, Budget, Partnercode', lambda: (
        wizard('Wizard, allgemeine Anfrage: Sommerfest Stuttgart, 35 Personen, Services, Budget, Partnercode', {
            'occasion': 'Sommerfest', 'goal': 'Team zusammenbringen', 'size': '35', 'region': 'Stuttgart', 'place': 'Golfplatz',
            'budget': '5.000 bis 10.000 €', 'when': 'Juni oder Juli', 'flex': 'flexibel', 'startzeit': 'Nachmittags',
            'duration': 'Halber Tag', 'experience': 'Gemischt', 'date1': '2027-06-18', 'date2': '2027-07-02',
            'company': 'Sommerwiese AG (TEST)', 'city': 'Stuttgart', 'first_name': 'Sabine', 'last_name': 'Sommer',
            'email': 'sabine.sommer@example.org', 'phone': '0711 556677', 'contact_pref': 'Telefon',
            'diet': '5x vegetarisch', 'notes': 'Familien sind eingeladen, Kinderprogramm wäre schön.',
            'services': 'Schnupperkurs||Grill||Bar & Drinks||Shuttle / Transport||Fotobox', 'partnercode': 'DGVTEST'})
        )),
        ('Kurz-Anfrage (Budget-Weg): Weihnachtsfeier München, Rückruf heute', lambda: (
        wizard('Kurz-Anfrage (Budget-Weg): Weihnachtsfeier München, Rückruf heute', {
            'source': 'budget', 'occasion': 'Weihnachtsfeier', 'size': '20', 'region': 'München', 'date1': '2026-12-10',
            'budget': 'ca. 3.000 € gesamt (netto, aus dem Rechner)', 'first_name': '', 'last_name': '',
            'email': 'kurz.anfrage@example.org', 'phone': '089 777 888', 'contact_pref': 'Telefon',
            'notes': 'Rückruf heute gewünscht.\n\nIndoor wäre ideal.'})
        )),
        ('Budget-Rechner Lead: Firmenturnier, 40 Personen', lambda: (
        budget_lead('Budget-Rechner Lead: Firmenturnier, 40 Personen', {
            'email': 'budget.lead@example.org', 'type': 'Firmenturnier', 'participants': '40', 'days': '',
            'range': 'mittel', 'services': '18-Loch-Turnier, Abendveranstaltung / Dinner, Siegerehrung & Preise', 'total': '6200'})
        )),
    ]

def main():
    args = sys.argv[1:]
    all_cases = cases()
    if not args or args[0] in ('-h', '--help', 'liste'):
        print('Aufruf: python3 tests/testdaten.py <Nr.>   legt NUR diesen Fall an (vorher alles leeren)')
        print('        python3 tests/testdaten.py alle    legt alle Fälle an')
        print('        python3 tests/testdaten.py leeren  löscht nur alle Anfragen und MailHog\n')
        for i, (label, _) in enumerate(all_cases, 1):
            print(f'  {i:2d}  {label}')
        return
    reset()
    if args[0] == 'leeren':
        return
    pick = range(1, len(all_cases) + 1) if args[0] == 'alle' else [int(x) for x in args]
    print('Neue Testanfragen über die echten Formulare …')
    for i in pick:
        all_cases[i - 1][1]()
    print('\nFertig:', sum(1 for c in CREATED if c[2]), 'von', len(CREATED), 'angelegt.')
    print('Mails liegen in MailHog: http://localhost:8025')


if __name__ == '__main__':
    main()
