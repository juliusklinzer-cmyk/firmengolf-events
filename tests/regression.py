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
    ok = r.status_code == 200 and '"success":true' in r.text
    check(ok, 'Anfrage über die Eventseite angelegt')
    if not ok:
        # Nie auf eine fremde Anfrage zurückfallen (28.09.: ein Fehllauf schrieb in den Klon von FG-26-166).
        sys.exit(f'ABBRUCH: Anfrage nicht angelegt, HTTP {r.status_code}: {r.text[:400]}')
    ref = r.json()['data']['ref']
    req = int(wp(f"echo (int) fge_request_by_ref('{ref}');") or 0)
    if req <= 0 or wp(f"echo get_post_meta({req}, '_fge_company_name', true);") != company:
        sys.exit(f'ABBRUCH: Anfrage {ref} nicht gefunden oder falsche Firma (req={req})')
    ms = mails(clear=True)
    check(any('kunde' in m['to'] and 'zwei Werktagen' in m['text'] for m in ms), 'Eingangsbestätigung mit „zwei Werktagen"')
    check(any('events@' in m['to'] and '/control/anfragen/?req=' in m['html'] for m in ms), 'Interne Mail verlinkt ins Control Center')
    return req

def pipeline_two_places(req):
    h = get(f'/control/anfragen/?req={req}').text
    check('Angefragt' in text(h) and 'Wunschtermine' in text(h), 'Cockpit zeigt Block „Angefragt"')
    # Bedarf (seit 1.9.304): Plätze erst nach „Bedarf ist geklärt", offene Fragen sperren.
    check(not [x for x in forms(h) if x['action'] == 'fge_cc_venue_add'], 'Plätze erst nach geklärtem Bedarf')
    post_form(find_form(h, 'fge_cc_question_add'), {'question': 'Vegetarisch gewünscht?'})
    h = get(f'/control/anfragen/?req={req}').text
    need = find_form(h, 'fge_cc_need_save')
    notes = {k: ('Grillbuffet' if 'abendessen' in k else '') for k in need['fields'] if k.startswith('note[')}
    r = post_form(need, {'need_done': '1', 'save_only': None, **notes})
    check('need_blocked' in r.url, 'Offene Frage sperrt „Bedarf ist geklärt"')
    h = get(f'/control/anfragen/?req={req}').text
    post_form(find_form(h, 'fge_cc_question_toggle'))
    h = get(f'/control/anfragen/?req={req}').text
    r = post_form(find_form(h, 'fge_cc_need_save'), {'need_done': '1', 'save_only': None})
    check('need_done' in r.url, 'Bedarf geklärt')
    h = get(f'/control/anfragen/?req={req}').text
    for pid in ('1613', '1200'):
        post_form(find_form(h, 'fge_cc_venue_add'), {'partner_id': pid})
    # Bedarf bestimmt die Anfrage (1.9.306): Sonderwunsch landet in noch nicht
    # angefragten Plätzen, Entfernen nimmt ihn wieder raus, Chips schalten je Platz.
    items = lambda: wp(f"foreach (fge_venues_get({req}) as $v) {{ echo $v['partner_id'], ':', implode('|', array_map(fn($i) => $i['label'], $v['items'])), ';'; }}")
    h = get(f'/control/anfragen/?req={req}').text
    post_form(find_form(h, 'fge_cc_need_save'), {'add_custom': 'Feuerwerk TEST', 'save_only': None})
    check(items().count('Feuerwerk TEST') == 2, 'Neue Leistung im Bedarf geht an beide Platz-Entwürfe')
    h = get(f'/control/anfragen/?req={req}').text
    check('noch niemand angefragt' not in text(h), 'Kein Hinweis auf fehlende Leistung, solange Entwürfe sie haben')
    chip = [x for x in forms(h) if x['action'] == 'fge_cc_provider_item' and x['fields'].get('label') == 'Feuerwerk TEST']
    check(len(chip) == 2, 'Golfplatz-Entwurf zeigt Leistungen als Chips')
    post_form(chip[0])
    check(items().count('Feuerwerk TEST') == 1, 'Chip wählt Leistung bei einem Platz ab')
    h = get(f'/control/anfragen/?req={req}').text
    post_form(find_form(h, 'fge_cc_need_save'), {'remove_one': 'Feuerwerk TEST', 'save_only': None})
    check('Feuerwerk TEST' not in items() and 'note[feuerwerk test]' not in get(f'/control/anfragen/?req={req}').text, 'Entfernen im Bedarf nimmt Leistung überall raus')
    h = get(f'/control/anfragen/?req={req}').text
    for f in [x for x in forms(h) if x['action'] == 'fge_cc_venue_ask' and x['fields'].get('how') == 'mail']:
        post_form(f)
    ms = mails(clear=True)
    check(len(ms) == 2 and all('Sagt uns bitte' in m['text'] and 'Termin' in m['text'] for m in ms), 'Preisanfragen mit Terminliste an beide Plätze')
    check(not any('Regressions GmbH' in m['text'] for m in ms), 'Preisanfrage ohne Firmenname')
    if notes:
        check(all('Abendessen: Grillbuffet' in m['text'] for m in ms), 'Notiz zur Leistung in der Preisanfrage')
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
    a, b = [r['fields']['venue_id'] for r in reps]
    return a, b

def add_package(req, venue_id):
    h = get(f'/control/anfragen/?req={req}').text
    post_form(find_form(h, 'fge_cc_pkg_add'), {'venue_id': venue_id})

def send_offer(req, overrides=None):
    h = get(f'/control/anfragen/?req={req}').text
    post_form(find_form(h, 'fge_cc_offer_send_options'), overrides or {})

def accept_all(link, option='A', date=None):
    """Annehmen wie ein Kunde: alle angebotenen Zusatzleistungen bleiben gewählt."""
    C = requests.Session(); c = C.get(link).text; f = forms(c)[0]
    data = [('fge_offer_token', f['fields']['fge_offer_token']), ('fge_offer_nonce', f['fields']['fge_offer_nonce']), ('fge_offer_agb', '1'), ('fge_offer_action', 'accept'), ('fge_offer_option', option)]
    dates = re.findall(r'name="fge_offer_date_' + option + r'" value="(\d+)"', c)
    if date or dates:
        data.append(('fge_offer_date_' + option, date or dates[0]))
    for n, v in re.findall(r'name="(fge_offer_extras(?:_' + option + r')?\[\])" value="(\d+)"', c):
        data.append((n, v))
    C.post(link, data=data, allow_redirects=True)

def scenario_accept():
    print('\n[1] Optionen-Angebot, Kunde nimmt Option B mit Termin 2 an')
    req = new_request('Regressions GmbH (TEST)', 'kunde-regi@example.org', 'Holzgerlingen', ['2026-11-18', '2026-11-25', ''], [{'label': 'Abendessen', 'source': 'platz'}])
    a, b = pipeline_two_places(req)
    add_package(req, a)
    add_package(req, b)
    send_offer(req)
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
    check('Gebucht' in main_text(h)[:900], 'Cockpit zeigt Gebucht')
    return req

def scenario_decline():
    print('\n[2] Kunde lehnt ab, Plätze bekommen die Freigabe')
    req = new_request('Regressions AG (TEST)', 'kunde-regi2@example.org', 'Stuttgart', ['2026-12-02', '', ''], [], pax='6')
    a, b = pipeline_two_places(req)
    add_package(req, a)
    send_offer(req)
    ms = mails(clear=True)
    offer = [m for m in ms if 'kunde-regi2' in m['to']]
    check(offer and 'Option A' not in offer[0]['text'], 'Eine Option ohne Optionsüberschrift')
    link = wp(f'echo fge_offer_link({req});')
    C = requests.Session(); c = C.get(link).text; f = forms(c)[0]
    C.post(link, data=[('fge_offer_token', f['fields']['fge_offer_token']), ('fge_offer_nonce', f['fields']['fge_offer_nonce']), ('fge_offer_message', 'Nein.'), ('fge_offer_action', 'decline')], allow_redirects=True)
    ms = mails(clear=True)
    check(sum(1 for m in ms if 'Termin wird frei' in m['subject']) == 2, 'Freigabe an beide Plätze nach Ablehnung')
    h = get(f'/control/anfragen/?req={req}').text
    check('Abgelehnt' in main_text(h)[:400], 'Cockpit zeigt Abgelehnt')
    return req

def scenario_tools():
    print('\n[3] Sammelanfrage, Follow-up, keine Antwort, Extra Posten, Senden-Dialog, Abschluss')
    req = new_request('Regressions KG (TEST)', 'kunde-regi3@example.org', 'Stuttgart', ['2026-12-09', '2026-12-16', ''], [], pax='8')
    h = get(f'/control/anfragen/?req={req}').text
    post_form(find_form(h, 'fge_cc_need_save'), {'need_done': '1', 'save_only': None})
    h = get(f'/control/anfragen/?req={req}').text
    for pid in ('1613', '1200'):
        post_form(find_form(h, 'fge_cc_venue_add'), {'partner_id': pid})
    h = get(f'/control/anfragen/?req={req}').text
    bulk = find_form(h, 'fge_cc_venue_ask_bulk')
    ids = re.findall(r'name="venue_ids\[\]" value="(\d+)"', bulk['html'])
    post_form(bulk, {'venue_ids[]': ids, 'mail_subject': 'Eigener Betreff (TEST)', 'mail_intro': 'Eigene Einleitung (TEST).'})
    ms = mails(clear=True)
    check(len(ms) == 2 and all(m['subject'] == 'Eigener Betreff (TEST)' and 'Eigene Einleitung (TEST).' in m['text'] for m in ms), 'Sammelanfrage mit eigenem Betreff und Text an beide')
    h = get(f'/control/anfragen/?req={req}').text
    fus = sorted([x for x in forms(h) if x['action'] == 'fge_cc_venue_followup'], key=lambda f: int(f['fields']['venue_id']))
    a, b = fus[0]['fields']['venue_id'], fus[1]['fields']['venue_id']
    post_form(fus[0])
    ms = mails(clear=True)
    check(len(ms) == 1 and ms[0]['subject'].startswith('Kurze Nachfrage'), 'Follow-up an einen Platz')
    h = get(f'/control/anfragen/?req={req}').text
    check('Follow-up am' in main_text(h), 'Zeile zeigt Follow-up am')
    post_form(find_form(h, 'fge_cc_venue_noanswer', f'value="{b}"'))
    h = get(f'/control/anfragen/?req={req}').text
    check(not mails(clear=True) and 'Keine Antwort nach Anfrage' in main_text(h), 'Keine Antwort ohne Mail an den Platz')
    rep = [x for x in forms(h) if x['action'] == 'fge_cc_venue_reply' and x['fields']['venue_id'] == a][0]
    post_form(rep, {'answer': 'zusagt', 'price': '40,00', 'price_gross': '1', 'price_basis': 'person', 'date_avail[1]': '1', 'date_avail[2]': '0',
                    'extra_label[]': 'Feuerwerk (TEST)', 'extra_price[]': '450', 'extra_gross[]': '0', 'extra_basis[]': 'pauschal'})
    mails(clear=True)
    h = get(f'/control/anfragen/?req={req}').text
    check('Feuerwerk (TEST)' in main_text(h), 'Extra Posten steht in Rückmeldung und Kalkulation')
    # Dienstleister: neu anlegen, anfragen, Rückmeldung, ins Angebot.
    post_form(find_form(h, 'fge_cc_provider_create'), {'p_name': 'Regi Shuttle (TEST)', 'p_type': 'shuttle', 'p_contact': 'Sam Fahrer', 'p_region': 'Stuttgart', 'p_email': 'shuttle-regi3@example.org', 'p_phone': ''})
    h = get(f'/control/anfragen/?req={req}').text
    check('Regi Shuttle (TEST)' in main_text(h), 'Dienstleister angelegt und in der Anfrage')
    ask = [x for x in forms(h) if x['action'] == 'fge_cc_venue_ask' and x['fields'].get('how') == 'mail']
    post_form(ask[0])
    ms = mails(clear=True)
    check(len(ms) == 1 and 'shuttle-regi3' in ms[0]['to'] and ms[0]['subject'].startswith('Anfrage für ein Firmenevent') and 'Regressions KG' not in ms[0]['text'], 'Anfrage an den Dienstleister, ohne Firmenname')
    h = get(f'/control/anfragen/?req={req}').text
    prow = ask[0]['fields']['venue_id']
    rep = [x for x in forms(h) if x['action'] == 'fge_cc_venue_reply' and x['fields']['venue_id'] == prow][0]
    keys = [k[len('item_price['):-1] for k in rep['fields'] if k.startswith('item_price[')]
    post_form(rep, {'answer': 'zusagt', 'date_avail[1]': '1', 'date_avail[2]': '1', **{f'item_price[{k}]': '300' for k in keys}, **{f'item_available[{k}]': '1' for k in keys}, **{f'item_basis[{k}]': 'pauschal' for k in keys}, **{f'item_gross[{k}]': '0' for k in keys}})
    check(not mails(clear=True), 'Rückmeldung des Dienstleisters ohne Mail an ihn')
    add_package(req, a)
    h = get(f'/control/anfragen/?req={req}').text
    pk = find_form(h, 'fge_cc_pkg_save')
    post_form(pk, {'add': f'all:{prow}'})
    h = get(f'/control/anfragen/?req={req}').text
    pk = find_form(h, 'fge_cc_pkg_save')
    check('Feuerwerk (TEST)' in pk['html'] and 'Regi Shuttle (TEST)' in pk['html'], 'Angebot A mit Extra Posten und Dienstleister-Position')
    sale = [v for k, v in pk['fields'].items() if k.endswith('[sale]')]
    check('360' in sale, 'Verkauf aus Einkauf plus 20 Prozent (300 netto zu 360)')
    h = get(f'/control/anfragen/?req={req}').text
    dl = wp("echo wp_date('Y-m-d', time() + 10 * DAY_IN_SECONDS);")
    send_offer(req, {'send_to': 'andere-regi3@example.org', 'send_deadline': dl, 'send_note': 'Persönliche Zeile (TEST).', 'send_remind': None})
    ms = mails(clear=True)
    offer = [m for m in ms if m['subject'].startswith('Euer Angebot')]
    check(offer and 'andere-regi3' in offer[0]['to'] and 'Persönliche Zeile (TEST).' in offer[0]['text'], 'Angebot an anderen Empfänger mit persönlicher Nachricht')
    check('Feuerwerk (TEST)' in offer[0]['text'] if offer else False, 'Extra Posten im Angebot')
    meta = wp(f"echo get_post_meta({req},'_fge_offer_remind_off',true) . '|' . wp_date('Y-m-d', (int) get_post_meta({req},'_fge_offer_deadline',true));")
    check(meta == '1|' + dl, 'Frist aus dem Dialog, Erinnerung aus')
    link = wp(f'echo fge_offer_link({req});')
    accept_all(link, 'A')
    ms = mails(clear=True)
    check(any('shuttle-regi3' in m['to'] and 'Auftragsbestätigung' in m['subject'] for m in ms), 'Dienstleister bekommt nach der Annahme den Auftrag')
    out = wp(f"$s=(array)get_post_meta({req},'_fge_offer_snapshot',true); $s['date']='Mo, 12.10.2026'; update_post_meta({req},'_fge_offer_snapshot',$s); echo get_post_meta({req},'_fge_offer_status',true) . '|' . fge_cc_auto_close() . '|' . get_post_meta({req},'_fge_request_status',true);")
    check(out.startswith('accepted|') and out.endswith('|abgeschlossen'), 'Am Tag nach dem Event automatisch abgeschlossen (' + out + ')')
    mails(clear=True)
    return req

def cleanup(reqs):
    # Auch liegengebliebene Testanfragen früherer Läufe (Firma mit „Regressions" und „(TEST)").
    ids = ','.join(str(r) for r in reqs) or '0'
    print(wp(f'global $wpdb; $more=$wpdb->get_col("SELECT p.ID FROM {{$wpdb->posts}} p JOIN {{$wpdb->postmeta}} m ON m.post_id=p.ID AND m.meta_key=\'_fge_company_name\' WHERE p.post_type=\'firmengolf_request\' AND m.meta_value LIKE \'Regressions%(TEST)%\'"); foreach(array_unique(array_merge([{ids}], array_map("intval",$more))) as $r){{ if($r<=0) continue; foreach(fge_venues_get($r,"all") as $v){{ $wpdb->delete(fge_venue_dates_table(),["venue_id"=>(int)$v["id"]]); fge_venue_delete((int)$v["id"]); }} $wpdb->delete($wpdb->prefix."fge_request_responses",["request_id"=>$r]); $wpdb->delete(fge_activity_table(),["request_id"=>$r]); if(function_exists("fge_mail_log_table")) $wpdb->delete(fge_mail_log_table(),["request_id"=>$r]); wp_delete_post($r,true); }} foreach([1613,1200] as $p){{ update_post_meta($p,"_fge_partner_status","stammdaten"); }} foreach(get_posts(["post_type"=>"firmengolf_provider","post_status"=>"any","numberposts"=>-1,"s"=>"(TEST)"]) as $pp){{ if(false!==strpos($pp->post_title,"(TEST)")) wp_delete_post($pp->ID,true); }} echo "Testdaten entfernt";'))

if __name__ == '__main__':
    subprocess.run(['docker', 'compose', 'exec', '-T', 'wordpress', 'wp', 'user', 'create', 'audit-bot', 'audit-bot@example.org', '--role=administrator', '--user_pass=Audit-Bot-2026!', '--allow-root'], cwd=ROOT, capture_output=True)
    requests.delete(MH + '/api/v1/messages')
    if not login():
        sys.exit('Login fehlgeschlagen')
    reqs = []
    try:
        reqs.append(scenario_accept())
        reqs.append(scenario_decline())
        reqs.append(scenario_tools())
    finally:
        cleanup(reqs)
        wp('require_once ABSPATH."wp-admin/includes/user.php"; $u=get_user_by("login","audit-bot"); if($u) wp_delete_user($u->ID);')
    print('\nERGEBNIS:', 'grün' if not FAILS else f'{len(FAILS)} Fehler: ' + '; '.join(FAILS))
    sys.exit(1 if FAILS else 0)
