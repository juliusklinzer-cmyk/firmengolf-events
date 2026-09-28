"""Treiber für den Audit-Durchlauf über die echte Oberfläche (localhost:8080) + MailHog."""
import re, json, html, os, sys, time
import requests

BASE = 'http://localhost:8080'
MH = 'http://localhost:8025'
OUT = os.path.join(os.path.dirname(__file__), 'out')
os.makedirs(OUT, exist_ok=True)

S = requests.Session()
S.headers['User-Agent'] = 'Mozilla/5.0 audit-bot'

def login(user='audit-bot', pw='Audit-Bot-2026!'):
    S.get(BASE + '/wp-login.php')
    r = S.post(BASE + '/wp-login.php', data={'log': user, 'pwd': pw, 'wp-submit': 'Anmelden', 'redirect_to': BASE + '/control/', 'testcookie': '1'}, allow_redirects=True)
    ok = 'wordpress_logged_in' in ' '.join(S.cookies.keys())
    print('login', 'ok' if ok else 'FEHLER', r.status_code, r.url)
    return ok

def get(path, save=None):
    r = S.get(BASE + path if path.startswith('/') else path, allow_redirects=True)
    if save:
        open(os.path.join(OUT, save), 'w', encoding='utf-8').write(r.text)
    return r

def text(h):
    h = re.sub(r'<(script|style).*?</\1>', ' ', h, flags=re.S)
    t = re.sub(r'<br\s*/?>', '\n', h)
    t = re.sub(r'</(p|div|li|tr|h[1-6]|dt|dd|summary)>', '\n', t)
    t = re.sub(r'<[^>]+>', ' ', t)
    t = html.unescape(t)
    t = re.sub(r'[ \t]+', ' ', t)
    t = re.sub(r'\n\s*\n+', '\n', t)
    return t.strip()

def forms(h):
    """Alle Formulare: action-Wert (hidden) + Felder (inkl. selects/textareas), Buttons."""
    out = []
    for m in re.finditer(r'<form([^>]*)>(.*?)</form>', h, flags=re.S):
        attrs, body = m.group(1), m.group(2)
        f = {'attrs': attrs, 'fields': {}, 'buttons': [], 'html': body}
        for i in re.finditer(r'<(input|select|textarea)\b([^>]*)>', body):
            tag, a = i.group(1), i.group(2)
            name = re.search(r'name="([^"]*)"', a)
            if not name:
                continue
            name = html.unescape(name.group(1))
            if tag == 'input':
                typ = (re.search(r'type="([^"]*)"', a) or [None, 'text'])[1]
                val = re.search(r'value="([^"]*)"', a)
                val = html.unescape(val.group(1)) if val else ''
                if typ in ('checkbox', 'radio'):
                    if 'checked' in a:
                        f['fields'][name] = val or 'on'
                    else:
                        f['fields'].setdefault(name, None)
                elif typ == 'submit':
                    f['buttons'].append((name, val))
                else:
                    f['fields'][name] = val
            elif tag == 'select':
                sel = re.search(r'<select\b[^>]*name="' + re.escape(name) + r'"[^>]*>(.*?)</select>', body, flags=re.S)
                opts = re.findall(r'<option\b([^>]*)>', sel.group(1)) if sel else []
                chosen = ''
                first = None
                for o in opts:
                    v = re.search(r'value="([^"]*)"', o)
                    v = html.unescape(v.group(1)) if v else ''
                    if first is None:
                        first = v
                    if 'selected' in o:
                        chosen = v
                f['fields'][name] = chosen if chosen != '' else (first or '')
            else:
                ta = re.search(r'<textarea\b[^>]*name="' + re.escape(name) + r'"[^>]*>(.*?)</textarea>', body, flags=re.S)
                f['fields'][name] = html.unescape(ta.group(1)) if ta else ''
        for b in re.finditer(r'<button\b([^>]*)>(.*?)</button>', body, flags=re.S):
            a, label = b.group(1), text(b.group(2))
            n = re.search(r'name="([^"]*)"', a)
            v = re.search(r'value="([^"]*)"', a)
            f['buttons'].append((n.group(1) if n else '', v.group(1) if v else label))
            f.setdefault('labels', []).append(label)
        f['action'] = f['fields'].get('action', '')
        out.append(f)
    return out

def find_form(h, action, contains=None):
    fs = [f for f in forms(h) if f['action'] == action and (contains is None or contains in f['html'])]
    if not fs:
        raise SystemExit(f'FORM FEHLT: {action} (contains={contains})')
    return fs[0]

def post_form(f, overrides=None, save=None):
    data = []
    fields = dict(f['fields'])
    if overrides:
        for k, v in overrides.items():
            fields[k] = v
    for k, v in fields.items():
        if v is None:
            continue
        if isinstance(v, list):
            for x in v:
                data.append((k, x))
        else:
            data.append((k, v))
    r = S.post(BASE + '/wp-admin/admin-post.php', data=data, allow_redirects=True)
    if save:
        open(os.path.join(OUT, save), 'w', encoding='utf-8').write(r.text)
    notice = re.search(r'class="cc-notice[^"]*"[^>]*>(.*?)</', r.text, flags=re.S)
    print(f"POST {f['action']} -> {r.status_code} {r.url.replace(BASE,'')}" + (f" | {text(notice.group(1))[:160]}" if notice else ''))
    return r

def mails(clear=False):
    r = requests.get(MH + '/api/v2/messages?limit=200').json()
    items = []
    for it in r.get('items', []):
        hdr = it['Content']['Headers']
        subj = hdr.get('Subject', [''])[0]
        try:
            from email.header import decode_header, make_header
            subj = str(make_header(decode_header(subj)))
        except Exception:
            pass
        to = ', '.join(hdr.get('To', []))
        body = it['Content']['Body']
        # MIME: html part
        mime = it.get('MIME')
        parts = []
        if mime and mime.get('Parts'):
            def walk(p):
                for x in p:
                    if x.get('MIME') and x['MIME'].get('Parts'):
                        walk(x['MIME']['Parts'])
                    else:
                        b = x['Body']
                        cte = x['Headers'].get('Content-Transfer-Encoding', [''])[0]
                        try:
                            import quopri as _q, base64 as _b
                            if 'quoted' in cte:
                                b = _q.decodestring(b.encode('latin-1', 'replace')).decode('utf-8', 'replace')
                            elif 'base64' in cte:
                                b = _b.b64decode(b).decode('utf-8', 'replace')
                        except Exception:
                            pass
                        parts.append((x['Headers'].get('Content-Type', [''])[0], b))
            walk(mime['Parts'])
        htmlpart = ''
        atts = []
        for ct, b in parts:
            if 'text/html' in ct:
                htmlpart = b
            elif 'text/plain' not in ct:
                atts.append(ct)
        if not htmlpart:
            htmlpart = body
        import quopri, base64
        try:
            enc = hdr.get('Content-Transfer-Encoding', [''])[0]
            if 'quoted' in enc:
                htmlpart = quopri.decodestring(htmlpart.encode()).decode('utf-8', 'replace')
            elif 'base64' in enc:
                htmlpart = base64.b64decode(htmlpart).decode('utf-8', 'replace')
        except Exception:
            pass
        try:
            htmlpart = htmlpart.encode('latin-1').decode('utf-8')
        except Exception:
            pass
        items.append({'to': to, 'subject': subj, 'html': htmlpart, 'text': text(htmlpart), 'atts': atts, 'created': it['Created']})
    items.sort(key=lambda x: x['created'])
    if clear:
        requests.delete(MH + '/api/v1/messages')
    return items

def show_mails(tag, clear=True, full=False, maxlen=900):
    ms = mails(clear=clear)
    print(f"\n=== MAILS [{tag}]: {len(ms)}")
    for m in ms:
        print(f"--- an {m['to']} | {m['subject']} | Anhänge: {m['atts']}")
        print(m['text'][:maxlen] if not full else m['text'])
    return ms

def dump_forms(h):
    for f in forms(h):
        print('FORM', f['action'], {k: v for k, v in f['fields'].items() if k not in ('_wpnonce', '_wp_http_referer')}, f.get('labels'))
