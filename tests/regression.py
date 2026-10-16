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
    import hashlib
    ak = 'note[' + hashlib.md5('abendessen'.encode()).hexdigest() + ']'
    notes = {k: ('Grillbuffet' if k == ak else '') for k in need['fields'] if k.startswith('note[')}
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
    check('Feuerwerk TEST' not in items() and 'Feuerwerk TEST</strong>' not in get(f'/control/anfragen/?req={req}').text, 'Entfernen im Bedarf nimmt Leistung überall raus')
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
    plan = wp(f'echo implode(",", array_map(fn($s)=>$s["label"], fge_offer_reminder_plan({req})));')
    check(plan == 'Erinnerung,Letzte Erinnerung', 'Zwei Erinnerungen geplant (' + plan + ')')
    wp(f'fge_send_offer_reminder({req}, true);')
    ms = mails(clear=True)
    check(any('kunde-regi' in m['to'] and 'Letzte Erinnerung' in m['subject'] and 'letzte Tag' in m['text'] for m in ms), 'Letzte Erinnerung am Fristtag')
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
    # Seit 1.9.307 nur noch die Vortags-Info, und die erst am Tag vor dem Event.
    check(not any('kunde-regi' in m['to'] for m in ms), 'Keine Mail an den Kunden nach Platzdetails')
    check(any('events@' in m['to'] and 'Vortags-Info an Kunde und Platz geht am' in m['text'] for m in ms), 'Interne Mail nennt den Tag der Vortags-Info')
    h = get(f'/control/anfragen/?req={req}').text
    early = [x for x in forms(h) if x['action'] == 'fge_cc_day_info']
    check(len(early) == 1 and not [x for x in forms(h) if x['action'] == 'fge_cc_day_plan'], 'Vor dem Vortag nur der Knopf „Infos zum Eventtag"')
    mails(clear=True)
    r = post_form(early[0])
    ms = mails(clear=True)
    check('day_info_early' in r.url and len(ms) == 1 and 'kunde-regi' in ms[0]['to'] and 'Alles für euren Eventtag' in ms[0]['subject'], 'Infos zum Eventtag von Hand nur an den Kunden')
    check(wp(f"echo get_post_meta({req}, '_fge_day_info_sent', true);") == '', 'Vortags-Info bleibt danach geplant')
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
    h = get(f'/control/anfragen/?req={req}').text
    post_form(find_form(h, 'fge_cc_status', contains='value="verloren"'))
    h = get(f'/control/anfragen/?req={req}').text
    check('Verloren' in main_text(h)[:900], 'Als verloren abgelegt aus dem Kopf der Anfrage')
    post_form(find_form(h, 'fge_cc_status', contains='in_uebernahme'))
    check(wp(f"echo get_post_meta({req}, '_fge_request_status', true);") == 'in_uebernahme', 'Wieder aufgenommen')
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
    # Vortags-Info läuft automatisch am Tag vor dem Event, an Kunde, Platz und intern.
    mails(clear=True)
    wp(f"$s=(array)get_post_meta({req},'_fge_offer_snapshot',true); $s['date']=wp_date('D, d.m.Y', strtotime('+1 day', current_datetime()->getTimestamp())); update_post_meta({req},'_fge_offer_snapshot',$s); fge_event_day_info_run();")
    ms = mails(clear=True)
    check(any('Morgen ist es so weit' in m['subject'] for m in ms) and any('events@' in m['to'] and 'Vortags-Info verschickt' in m['subject'] for m in ms), 'Vortags-Info am Vortag automatisch (' + str(len(ms)) + ' Mails)')
    out = wp(f"$s=(array)get_post_meta({req},'_fge_offer_snapshot',true); $s['date']='Mo, 12.10.2026'; update_post_meta({req},'_fge_offer_snapshot',$s); echo get_post_meta({req},'_fge_offer_status',true) . '|' . fge_cc_auto_close() . '|' . get_post_meta({req},'_fge_request_status',true);")
    check(out.startswith('accepted|') and out.endswith('|event_durchgefuehrt'), 'Am Tag nach dem Event automatisch durchgeführt (' + out + ')')
    h = get(f'/control/anfragen/?req={req}').text
    post_form(find_form(h, 'fge_cc_invoice', contains='value="set"'), {'invoice_number': 'RE-TEST-1', 'invoice_date': '2026-09-01', 'invoice_due': ''})
    out = wp(f"echo get_post_meta({req},'_fge_invoice_date',true),'|',get_post_meta({req},'_fge_invoice_due',true);")
    check(out == '2026-09-01|2026-09-15', 'Rechnung gestellt mit Datum, Fälligkeit 14 Tage (' + out + ')')
    h = get('/control/finanzen/?tab=ueber').text
    check('RE-TEST-1' in h and 'Überfällig' in h, 'Finanzen zeigt die überfällige Rechnung')
    h = get('/control/').text
    check('RE-TEST-1' in h and 'Tage überfällig' in h, 'Heute zeigt die Zahlung unter Zahlungen')
    mails(clear=True)
    r = post_form(find_form(get('/control/finanzen/?tab=ueber').text, 'fge_cc_invoice', contains='value="remind"'))
    ms = mails(clear=True)
    check('inv_reminded' in r.url and any('Zahlungserinnerung zur Rechnung RE-TEST-1' in m['subject'] and 'andere-regi3' in m['to'] and '15.09.2026' in m['text'] for m in ms), 'Zahlungserinnerung an den Angebotsempfänger mit Fälligkeit')
    check('—' not in ''.join(m['text'] for m in ms) and '–' not in ''.join(m['text'] for m in ms), 'Zahlungserinnerung ohne Gedankenstriche')
    h = get(f'/control/anfragen/?req={req}').text
    post_form(find_form(h, 'fge_cc_invoice', contains='value="paid"'))
    out = wp(f"echo get_post_meta({req}, '_fge_request_status', true),'|',get_post_meta({req},'_fge_invoice_paid_at',true),'|',get_post_meta({req},'_fge_invoice_reminded',true);")
    check(out.startswith('abgeschlossen|20') and out.count('|20') == 2, 'Bezahlt, abgeschlossen, Zahlung und Erinnerung vermerkt (' + out + ')')
    check('RE-TEST-1' in get('/control/finanzen/?tab=bezahlt').text, 'Finanzen führt die Rechnung als bezahlt')
    mails(clear=True)
    return req

def scenario_partner():
    print('\n[4] Partner und Events prüfen im Control Center')
    pid = int(wp('$id=wp_insert_post(["post_type"=>"firmengolf_partner","post_status"=>"publish","post_title"=>"Regressions-Platz (TEST)"]); foreach(["_fge_partner_type"=>"course","_fge_partner_status"=>"in_pruefung","_fge_city"=>"Teststadt","_fge_main_contact_name"=>"Tina Test","_fge_main_contact_email"=>"platz-regi@example.org","_fge_main_contact_phone"=>"0400 123","_fge_submitted_at"=>current_time("mysql")] as $k=>$v) update_post_meta($id,$k,$v); echo $id;'))
    eid = int(wp(f'$e=wp_insert_post(["post_type"=>"firmengolf_event","post_status"=>"publish","post_title"=>"Regressions-Event (TEST)"]); update_post_meta($e,"_fge_assigned_partner_id",{pid}); update_post_meta($e,"_fge_event_status","zur_pruefung"); update_post_meta($e,"_fge_event_type","teamevent"); echo $e;'))
    try:
        h = get('/control/partner/').text
        check('Regressions-Platz (TEST)' in h and 'Neue Partner zur Freigabe' in h, 'Liste zeigt neuen Partner zur Freigabe')
        h = get(f'/control/partner/?pid={pid}&pruefen=1').text
        check('fge_cc_preview=1' in h and 'Automatisch geprüft' in h, 'Prüfansicht mit Webansicht und Checks')
        f = find_form(h, 'fge_cc_partner_review')
        mails(clear=True)
        r = post_form(f, {'decision': 'rueckfragen', 'message': 'Bitte noch drei Fotos hochladen.'})
        ms = mails(clear=True)
        check('p_asked' in r.url and any('platz-regi' in m['to'] and 'drei Fotos' in m['text'] for m in ms), 'Rückfrage mit deinem Text an den Partner')
        h = get(f'/control/partner/?pid={pid}&pruefen=1').text
        check('Deine Rückfrage' in h, 'Rückfrage bleibt sichtbar')
        r = post_form(find_form(h, 'fge_cc_partner_review'), {'decision': 'aktiv'})
        check('p_needcheck' in r.url, 'Freigabe nur mit drei Haken')
        h = get(f'/control/partner/?pid={pid}&pruefen=1').text
        r = post_form(find_form(h, 'fge_cc_partner_review'), {'decision': 'aktiv', 'check[0]': '1', 'check[1]': '1', 'check[2]': '1'})
        ms = mails(clear=True)
        st = wp(f'echo get_post_meta({pid},"_fge_partner_status",true),"|",get_post_meta({pid},"_fge_partner_since",true);')
        check(st.startswith('aktiv|20') and any('platz-regi' in m['to'] and 'live' in m['subject'] for m in ms), 'Freigegeben, Willkommensmail, Partner seit gesetzt (' + st + ')')
        # Event: Rückfrage, dann mit Anpassung freigeben.
        h = get(f'/control/events/').text
        f = find_form(h, 'fge_cc_event_review', contains=f'value="{eid}"')
        r = post_form(f, {'decision': 'rueckfrage', 'message': 'Wie viele Simulatoren gibt es?'})
        ms = mails(clear=True)
        check('e_asked' in r.url and any('platz-regi' in m['to'] and 'Simulatoren' in m['text'] for m in ms) and wp(f'echo get_post_meta({eid},"_fge_event_status",true);') == 'zur_pruefung', 'Event-Rückfrage, Event bleibt in Prüfung')
        h = get(f'/control/events/').text
        check('Rückfrage offen' in h, 'Events-Liste zeigt Rückfrage offen')
        f = find_form(h, 'fge_cc_event_review', contains=f'value="{eid}"')
        r = post_form(f, {'decision': 'freigegeben', 'title': 'Regressions-Event angepasst (TEST)', 'price': '50'})
        ms = mails(clear=True)
        out = wp(f'echo get_post_meta({eid},"_fge_event_status",true),"|",get_the_title({eid}),"|",get_post_meta({eid},"_fge_price_gross",true);')
        check(out.startswith('freigegeben|Regressions-Event angepasst (TEST)|50') and any('Freigegeben' in m['subject'] for m in ms), 'Event mit Anpassung freigegeben (' + out + ')')
        # Audit: Speichern ohne Preiseingabe darf den Preis nicht löschen.
        wp(f'delete_post_meta({eid},"_fge_price_gross"); update_post_meta({eid},"_fge_price_amount",125);')
        h = get(f'/control/events/?edit={eid}').text
        post_form(find_form(h, 'fge_cc_event_save', contains=f'value="{eid}"'), {'price': ''})
        check(wp(f'echo get_post_meta({eid},"_fge_price_amount",true);') == '125', 'Event speichern ohne Preis lässt den Preis stehen')
        h = get(f'/control/partner/?pid={pid}').text
        post_form(find_form(h, 'fge_cc_partner_pstatus'), {'status': 'pausiert'})
        check(wp(f'echo get_post_meta({eid},"_fge_event_status",true);') == 'pausiert', 'Partner pausiert nimmt Events offline')
        h = get(f'/control/partner/?pid={pid}&tab=st').text
        post_form(find_form(h, 'fge_cc_partner_save'), {'note': 'Nur vormittags anrufen'})
        check(wp(f'echo get_post_meta({pid},"_fge_internal_note",true);') == 'Nur vormittags anrufen', 'Stammdaten speichern')
    finally:
        wp(f'wp_delete_post({eid},true); wp_delete_post({pid},true); global $wpdb; $wpdb->delete(fge_verzeichnis_table(),["partner_id"=>{pid},"ist_partner"=>1]);')

def scenario_pages():
    print('\n[5] Übrige Menüpunkte: Heute, Kunden, Partnercodes, Finanzen, Einstellungen')
    for page, needle in [('dashboard', 'Zahlungen'), ('anfragen', ''), ('partner', 'Partner anlegen'), ('events', 'Events'), ('kunden', 'Leads'), ('partnercodes', 'Code anlegen'), ('finanzen', 'Umsatz netto'), ('einstellungen', 'Budgetrechner auf der Website'), ('einstellungen/?bereich=postausgang', 'Nicht zugestellt'), ('einstellungen/?bereich=nutzer', 'Passwort-Link senden'), ('einstellungen/?bereich=verbindungen', 'Outlook-Kalender'), ('einstellungen/?bereich=firmendaten', 'Firmendaten für Angebote'), ('verzeichnis', '')]:
        r = get(f'/control/{page}' + ('' if '?' in page else '/'))
        check(r.status_code == 200 and needle in r.text and 'Diese Seite gibt es nicht' not in r.text, f'Seite {page} ist fertig')
    for old, want in [('rechnungen', '/control/finanzen/'), ('leads', 'ansicht=leads'), ('nutzer', 'bereich=nutzer'), ('postausgang', 'bereich=postausgang'), ('angebote', 'tab=kunde'), ('dienstleister', 'art=dl')]:
        r = get(f'/control/{old}/')
        check(r.status_code == 200 and want in r.url, f'Alte Seite {old} leitet um ({r.url.replace(BASE, "")})')
    nav = re.findall(r'class="cc-nav-item[^"]*"[^>]*><span>([^<]+)</span>', get('/control/').text)
    check(nav == ['Heute', 'Anfragen', 'Partner und Events', 'Kunden', 'Partnercodes', 'Finanzen', 'Einstellungen'], 'Menü wie im Entwurf (' + ', '.join(nav) + ')')
    lead = int(wp('$id=wp_insert_post(["post_type"=>"firmengolf_request","post_status"=>"publish","post_title"=>"Lead (TEST)"]); update_post_meta($id,"_fge_request_type","budget_lead"); update_post_meta($id,"_fge_contact_email","lead-regi@example.org"); update_post_meta($id,"_fge_company_name","Regressions Lead (TEST)"); echo $id;'))
    try:
        check('lead-regi' not in get('/control/anfragen/?tab=alle').text, 'Offener Lead steht nicht in den Anfragen')
        check('Regressions Lead (TEST)' in get('/control/').text, 'Heute zeigt den neuen Lead')
        h = get('/control/kunden/?ansicht=leads').text
        post_form(find_form(h, 'fge_cc_lead', contains='cc-pillselect'), {'to': 'kontaktiert'})
        check(wp(f'echo get_post_meta({lead},"_fge_lead_status",true);') == 'kontaktiert', 'Lead als kontaktiert markiert')
        r = post_form(find_form(get('/control/kunden/?ansicht=leads').text, 'fge_cc_lead', contains='value="umgewandelt"'))
        check('lead_conv' in r.url and wp(f'echo get_post_meta({lead},"_fge_request_type",true);') == 'general_event_request', 'Lead in Anfrage umgewandelt')
        h = get('/control/kunden/?kunde=regressions+lead+(test)').text
        check('Interne Notiz' in h, 'Kunde öffnet das Seitenfenster')
        post_form(find_form(h, 'fge_cc_customer_note'), {'note': 'Zahlt immer pünktlich'})
        check(wp('echo get_option("fge_customer_notes")["regressions lead (test)"] ?? "";') == 'Zahlt immer pünktlich', 'Kundennotiz gespeichert')
    finally:
        wp(f'wp_delete_post({lead},true); global $wpdb; $wpdb->delete(fge_activity_table(),["request_id"=>{lead}]); $n=(array)get_option("fge_customer_notes",[]); unset($n["regressions lead (test)"]); update_option("fge_customer_notes",$n,false);')
    lost = int(wp('$id=wp_insert_post(["post_type"=>"firmengolf_request","post_status"=>"publish","post_title"=>"Verloren (TEST)"]); update_post_meta($id,"_fge_offer_status","pending"); update_post_meta($id,"_fge_company_name","Regressions Verloren (TEST)"); echo $id;'))
    try:
        h = get(f'/control/anfragen/?req={lost}').text
        post_form(find_form(h, 'fge_cc_status', contains='value="verloren"'))
        out = wp(f'echo get_post_meta({lost},"_fge_request_status",true),"|",get_post_meta({lost},"_fge_offer_status",true);')
        check(out == 'verloren|declined', 'Verloren schließt das offene Angebot (' + out + ')')
    finally:
        wp(f'wp_delete_post({lost},true); global $wpdb; $wpdb->delete(fge_activity_table(),["request_id"=>{lost}]);')
    h = get('/control/partnercodes/').text
    r = post_form(find_form(h, 'fge_cc_pc_save'), {'holder': 'Regressions Verband (TEST)', 'code': 'REGTEST', 'eur': '40,00'})
    pc = wp('echo fge_pc_find("REGTEST");')
    check('pc_saved' in r.url and pc not in ('', '0') and wp(f'echo get_post_meta({pc},"_fge_pc_commission_eur",true);') == '40', 'Partnercode angelegt')
    h = get(f'/control/partnercodes/?code={pc}').text
    check('Buchungen pro Monat' in h and 'data-cc-copy="REGTEST"' in h, 'Partnercode zeigt das Detailfeld')
    post_form(find_form(h, 'fge_cc_pc_status'))
    check(wp(f'echo get_post_meta({pc},"_fge_pc_status",true);') == 'pausiert', 'Partnercode pausiert')
    wp(f'wp_delete_post({pc or 0},true);')
    # Budgetrechner: Leistungen je Typ und Preise, danach Zustand zurück.
    backup = wp('echo wp_json_encode(get_option("fge_budget_calc", null));')
    try:
        h = get('/control/einstellungen/?bereich=budgetrechner&typ=teamevent').text
        post_form(find_form(h, 'fge_cc_bc_save'), {'on[]': ['golfkurs', 'mittagessen'], 'price[golfkurs][1]': '44'})
        out = wp('$c=fge_bc_config(); $t=array_column($c["types"],null,"id")["teamevent"]; $g=array_column($c["services"],null,"id")["golfkurs"]; echo implode(",",$t["services"]),"|",$g["pp"][1];')
        check(out == 'golfkurs,mittagessen|44', 'Budgetrechner speichert Leistungen und Preis (' + out + ')')
    finally:
        wp('$b=json_decode(' + json.dumps(backup) + ',true); if(null===$b) delete_option("fge_budget_calc"); else update_option("fge_budget_calc",$b);')
    # Firmendaten: Änderung wirkt auf fge_company(), leeres Feld heißt Wert aus dem Plugin.
    try:
        h = get('/control/einstellungen/?bereich=firmendaten').text
        post_form(find_form(h, 'fge_cc_company_save'), {'co[phone_display]': '+49 (0) 89 999 000'})
        out = wp('echo fge_company()["phone_display"],"|",fge_company()["phone_tel"],"|",count((array)get_option("fge_company_overrides"));')
        check(out == '+49 (0) 89 999 000|+4989999000|1', 'Firmendaten überschreiben nur den geänderten Wert (' + out + ')')
    finally:
        wp('delete_option("fge_company_overrides");')
    # Postausgang: fehlgeschlagene Mail erneut senden.
    mid = wp('global $wpdb; $wpdb->insert(fge_mail_log_table(),["request_id"=>0,"mail_key"=>"","recipient"=>"retry-regi@example.org","subject"=>"Regressions Retry (TEST)","status"=>"failed","error"=>"Test","sent_at"=>current_time("mysql"),"payload"=>wp_json_encode(["to"=>"retry-regi@example.org","subject"=>"Regressions Retry (TEST)","message"=>"Hallo","headers"=>[],"attachments"=>[]])]); echo $wpdb->insert_id;')
    try:
        mails(clear=True)
        h = get('/control/einstellungen/?bereich=postausgang&filter=fehler').text
        post_form(find_form(h, 'fge_cc_mail_retry', contains=f'value="{mid}"'))
        ms = mails(clear=True)
        st = wp(f'echo fge_mail_log_get({mid})["status"] ?? "";')
        check(st == 'resent' and any('retry-regi' in m['to'] for m in ms), 'Fehlgeschlagene Mail erneut gesendet (' + st + ')')
    finally:
        wp(f'global $wpdb; $wpdb->delete(fge_mail_log_table(),["id"=>{mid or 0}]); $wpdb->delete(fge_mail_log_table(),["recipient"=>"retry-regi@example.org"]);')

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
        scenario_partner()
        scenario_pages()
    finally:
        cleanup(reqs)
        wp('require_once ABSPATH."wp-admin/includes/user.php"; $u=get_user_by("login","audit-bot"); if($u) wp_delete_user($u->ID);')
    print('\nERGEBNIS:', 'grün' if not FAILS else f'{len(FAILS)} Fehler: ' + '; '.join(FAILS))
    sys.exit(1 if FAILS else 0)
