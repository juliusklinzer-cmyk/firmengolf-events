"""
Regressionslauf über die echte Oberfläche (localhost:8080 + MailHog 8025).

Deckt den Soll-Prozess ab: Anfrage über die Eventseite, Cockpit, Plätze aufnehmen,
Preisanfrage, Antworten je Wunschtermin, Kalkulation, Angebot mit Optionen, Annahme
mit Option und Termin, Detailabfrage des Platzes, Absage-Pfad mit Freigaben.

Aufruf (docker compose läuft):  python3 tests/regression.py
Legt einen Admin „audit-bot" an und räumt am Ende alle Testdaten wieder weg.
"""
import subprocess, sys, time, os, re, json
sys.path.insert(0, os.path.dirname(__file__))
from fge_driver import *  # noqa

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
def wp(code):
    return subprocess.run(['docker', 'compose', 'exec', '-T', 'wordpress', 'wp', 'eval', code, '--allow-root'], cwd=ROOT, capture_output=True, text=True).stdout.strip()

FAILS = []
def check(cond, label):
    print(('  ok   ' if cond else '  FAIL ') + label)
    if not cond:
        FAILS.append(label)

def new_request(company, email, city, dates, wishes, pax='7'):
    p = requests.get(BASE + '/firmenevents/golf-teamevent-in-stuttgart/').text
    nonce = re.search(r"nonce:\s*'([a-f0-9]{10})'", p).group(1); ft = re.search(r"var fgeFt = '([^']+)'", p).group(1)
    time.sleep(5)
    data = {'action': 'fge_modal_anfrage', 'nonce': nonce, 'fge_ft': ft, 'fge_js': ft[::-1], 'fge_hp': '', 'event_id': 428, 'group_size': pax,
            'first_name': 'Regi', 'last_name': 'Test', 'email': email, 'company': company, 'phone': '0711 000', 'city': city,
            'experience': 'Überwiegend Anfänger', 'starttime': 'After-Work', 'contact_pref': 'E-Mail', 'consent': '1',
            'date1': dates[0], 'date2': dates[1] if len(dates) > 1 else '', 'date3': dates[2] if len(dates) > 2 else '', 'notes': '', 'wishes': json.dumps(wishes)}
    r = requests.post(BASE + '/wp-admin/admin-ajax.php', data=data)
    check(r.status_code == 200 and '"success":true' in r.text, 'Anfrage über die Eventseite angelegt')
    ms = mails(clear=True)
    check(any('kunde' in m['to'] and 'zwei Werktagen' in m['text'] for m in ms), 'Eingangsbestätigung mit „zwei Werktagen"')
    check(any('events@' in m['to'] and '/control/anfragen/?req=' in m['html'] for m in ms), 'Interne Mail verlinkt ins Control Center')
    h = get('/control/anfragen/').text
    return max(int(x) for x in re.findall(r'req=(\d+)', h))

def pipeline_two_places(req):
    h = get(f'/control/anfragen/?req={req}').text
    check('Angefragt' in text(h) and 'Wunschtermine' in text(h), 'Cockpit zeigt Block „Angefragt"')
    for pid in ('1613', '1200'):
        post_form(find_form(h, 'fge_cc_venue_add'), {'partner_id': pid})
    h = get(f'/control/anfragen/?req={req}').text
    for f in [x for x in forms(h) if x['action'] == 'fge_cc_venue_ask' and x['fields'].get('how') == 'mail']:
        post_form(f)
    ms = mails(clear=True)
    check(len(ms) == 2 and all('Sagt uns bitte je Termin' in m['text'] for m in ms), 'Preisanfragen mit Terminliste an beide Plätze')
    check(not any('Regressions GmbH' in m['text'] for m in ms), 'Preisanfrage ohne Firmenname')
    h = get(f'/control/anfragen/?req={req}').text
    reps = sorted([x for x in forms(h) if x['action'] == 'fge_cc_venue_reply'], key=lambda f: int(f['fields']['venue_id']))
    keys = [k for k in reps[0]['fields'] if k.startswith('item_available[')]
    k0 = keys[0][len('item_available['):-1] if keys else ''
    extra_a = {f'item_price[{k0}]': '25,00'} if k0 else {}
    extra_b = {f'item_available[{k0}]': None} if k0 else {}
    post_form(reps[0], {'price': '33,00', 'price_gross': '1', 'price_basis': 'person', 'date_avail[1]': '1', 'date_avail[2]': '1', 'note': 'A', **extra_a})
    post_form(reps[1], {'price': '29,00', 'price_gross': '1', 'price_basis': 'person', 'date_avail[1]': '0', 'date_avail[2]': '1', 'note': 'B', **extra_b})
    ms = mails(clear=True)
    check(len(ms) == 2 and all('bitte reservieren bis' in m['subject'] for m in ms), 'Bestätigung mit Reservierungsbitte automatisch nach erster Zusage')
    h = get(f'/control/anfragen/?req={req}').text
    for f in [x for x in forms(h) if x['action'] == 'fge_cc_venue_calc']:
        post_form(f)
    h = get(f'/control/anfragen/?req={req}').text
    of = find_form(h, 'fge_cc_offer_options_save')
    a, b = [r['fields']['venue_id'] for r in reps]
    return h, of, a, b

def scenario_accept():
    print('\n[1] Optionen-Angebot, Kunde nimmt Option B mit Termin 2 an')
    req = new_request('Regressions GmbH (TEST)', 'kunde-regi@example.org', 'Holzgerlingen', ['2026-11-18', '2026-11-25', ''], [{'label': 'Abendessen', 'source': 'platz'}])
    h, of, a, b = pipeline_two_places(req)
    post_form(of, {f'opt_pos[{a}]': '1', f'opt_pos[{b}]': '2', 'opt_recommended': a, 'opt_recommendation': 'A liegt näher.'})
    h = get(f'/control/anfragen/?req={req}').text
    post_form([x for x in forms(h) if x['action'] == 'fge_cc_offer_send_options'][0])
    ms = mails(clear=True)
    offer = [m for m in ms if 'kunde-regi' in m['to'] and m['subject'].startswith('Euer Angebot')]
    check(len(offer) == 1 and any('pdf' in a for a in offer[0]['atts']), 'Angebotsmail mit PDF')
    check('Option A' in offer[0]['text'] and 'Option B' in offer[0]['text'], 'Zwei Optionen in der Mail')
    link = wp(f'echo fge_offer_link({req});')
    c = requests.get(link).text
    check(c.count('name="fge_offer_option"') == 2 and 'name="fge_offer_date_B"' in c, 'Angebotsseite mit Options- und Termin-Radios')
    C = requests.Session(); c = C.get(link).text; f = forms(c)[0]
    C.post(link, data=[('fge_offer_token', f['fields']['fge_offer_token']), ('fge_offer_nonce', f['fields']['fge_offer_nonce']), ('fge_offer_agb', '1'), ('fge_offer_option', 'B'), ('fge_offer_date_B', '2'), ('fge_offer_action', 'accept')], allow_redirects=True)
    ms = mails(clear=True)
    subj = ' | '.join(m['subject'] for m in ms)
    check('Auftrag steht' in subj and 'Buchung best' in subj, 'Auftrag steht und Buchungsbestätigungen')
    check(any('gc-hammetweil' in m['to'] and 'platz-details/' in m['html'] for m in ms), 'Buchungsbestätigung an Platz B mit Detail-Link')
    check(any('gc-schoenbuch' in m['to'] and 'anderen Platz entschieden' in m['text'] for m in ms), 'Automatische Absage an Platz A mit Katalog-Einladung')
    dl = [re.search(r'(http://localhost:8080/platz-details/[a-f0-9]+/)', m['html']) for m in ms]; dl = [x.group(1) for x in dl if x]
    d = requests.get(dl[0]).text; ff = forms(d)[0]
    data = {k: (v if v is not None else '') for k, v in ff['fields'].items()}
    data.update({'fge_day_start_time': '16:00', 'fge_day_meeting_point': 'Clubhaus', 'fge_day_onsite_name': 'Petra Will', 'fge_day_onsite_phone': '07127 97430', 'fge_day_pro': 'Pro', 'fge_day_bring_along': 'Sportkleidung', 'fge_day_notes': '', 'save': '1'})
    requests.post(dl[0], data=data, allow_redirects=True)
    ms = mails(clear=True)
    check(any('kunde-regi' in m['to'] and 'Ablauf' in m['subject'] for m in ms), 'Ablauf-Info an den Kunden nach Platzdetails')
    h = get(f'/control/anfragen/?req={req}').text
    check('Vorbereitung' in text(h)[:600], 'Cockpit in Phase Vorbereitung')
    return req

def scenario_decline():
    print('\n[2] Kunde lehnt ab, Plätze bekommen die Freigabe')
    req = new_request('Regressions AG (TEST)', 'kunde-regi2@example.org', 'Stuttgart', ['2026-12-02', '', ''], [], pax='6')
    h, of, a, b = pipeline_two_places(req)
    post_form(of, {f'opt_pos[{a}]': '1', 'opt_recommended': a})
    h = get(f'/control/anfragen/?req={req}').text
    post_form([x for x in forms(h) if x['action'] == 'fge_cc_offer_send_options'][0])
    ms = mails(clear=True)
    offer = [m for m in ms if 'kunde-regi2' in m['to']]
    check(offer and 'Option A' not in offer[0]['text'], 'Eine Option ohne Optionsüberschrift')
    link = wp(f'echo fge_offer_link({req});')
    C = requests.Session(); c = C.get(link).text; f = forms(c)[0]
    C.post(link, data=[('fge_offer_token', f['fields']['fge_offer_token']), ('fge_offer_nonce', f['fields']['fge_offer_nonce']), ('fge_offer_message', 'Nein.'), ('fge_offer_action', 'decline')], allow_redirects=True)
    ms = mails(clear=True)
    check(sum(1 for m in ms if 'Termin wird frei' in m['subject']) == 2, 'Freigabe an beide Plätze nach Ablehnung')
    h = get(f'/control/anfragen/?req={req}').text
    check('Abgelehnt' in text(h)[:400], 'Cockpit zeigt Abgelehnt')
    return req

def cleanup(reqs):
    # Auch liegengebliebene Testanfragen früherer Läufe (Firma mit „Regressions" und „(TEST)").
    ids = ','.join(str(r) for r in reqs) or '0'
    print(wp(f'global $wpdb; $more=$wpdb->get_col("SELECT p.ID FROM {{$wpdb->posts}} p JOIN {{$wpdb->postmeta}} m ON m.post_id=p.ID AND m.meta_key=\'_fge_company_name\' WHERE p.post_type=\'firmengolf_request\' AND m.meta_value LIKE \'Regressions%(TEST)%\'"); foreach(array_unique(array_merge([{ids}], array_map("intval",$more))) as $r){{ if($r<=0) continue; foreach(fge_venues_get($r) as $v){{ $wpdb->delete(fge_venue_dates_table(),["venue_id"=>(int)$v["id"]]); fge_venue_delete((int)$v["id"]); }} $wpdb->delete($wpdb->prefix."fge_request_responses",["request_id"=>$r]); $wpdb->delete(fge_activity_table(),["request_id"=>$r]); if(function_exists("fge_mail_log_table")) $wpdb->delete(fge_mail_log_table(),["request_id"=>$r]); wp_delete_post($r,true); }} foreach([1613,1200] as $p){{ update_post_meta($p,"_fge_partner_status","stammdaten"); }} echo "Testdaten entfernt";'))

if __name__ == '__main__':
    subprocess.run(['docker', 'compose', 'exec', '-T', 'wordpress', 'wp', 'user', 'create', 'audit-bot', 'audit-bot@example.org', '--role=administrator', '--user_pass=Audit-Bot-2026!', '--allow-root'], cwd=ROOT, capture_output=True)
    requests.delete(MH + '/api/v1/messages')
    if not login():
        sys.exit('Login fehlgeschlagen')
    reqs = []
    try:
        reqs.append(scenario_accept())
        reqs.append(scenario_decline())
    finally:
        cleanup(reqs)
        wp('require_once ABSPATH."wp-admin/includes/user.php"; $u=get_user_by("login","audit-bot"); if($u) wp_delete_user($u->ID);')
    print('\nERGEBNIS:', 'grün' if not FAILS else f'{len(FAILS)} Fehler: ' + '; '.join(FAILS))
    sys.exit(1 if FAILS else 0)
