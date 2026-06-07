/* eslint-disable */
// =============================================================
// Admin shell — sidebar nav, topbar, hash routing, dashboard.
// Sections live in AdminSections.jsx (window.ADMIN_SECTIONS).
// =============================================================
const { useState, useEffect, useMemo } = React;

const NAV = [
  { id: 'dashboard', label: 'Übersicht', icon: 'grid' },
  { id: 'partners',  label: 'Partnerplätze', icon: 'pin', badge: (s) => s.partners.filter(p => p.status === 'in-pruefung').length },
  { id: 'events',    label: 'Firmenevents', icon: 'flag' },
  { id: 'requests',  label: 'Anfragen',  icon: 'inbox', badge: (s) => s.requests.filter(r => r.status === 'neu' || r.overdue).length },
  { id: 'users',     label: 'Benutzer',  icon: 'users' },
  { id: 'blog',      label: 'Magazin',   icon: 'doc' },
];

function AIcon({ name, size = 19 }) {
  const p = {
    grid:  <><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></>,
    inbox: <><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.5 5.5h13l3.5 6.5v6a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-6z"/></>,
    flag:  <><path d="M5 21V4M5 4l11 2-3 4 3 4-11 2"/></>,
    pin:   <><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></>,
    mail:  <><rect x="2.5" y="4.5" width="19" height="15" rx="2.5"/><path d="M3 6.5l9 6 9-6"/></>,
    gift:  <><rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M5 12v9h14v-9"/><path d="M12 8S10.5 3 8 4.5 9.5 8 12 8zM12 8s1.5-5 4-3.5S14.5 8 12 8z"/></>,
    doc:   <><path d="M4 5a2 2 0 0 1 2-2h8l6 6v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z"/><path d="M14 3v6h6"/><path d="M8 13h8M8 17h5"/></>,
    users: <><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></>,
    logout:<><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/></>,
    search:<><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></>,
    mailicon: <><rect x="2.5" y="4.5" width="19" height="15" rx="2.5"/><path d="M3 6.5l9 6 9-6"/></>,
  };
  return <svg viewBox="0 0 24 24" width={size} height={size} fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">{p[name]}</svg>;
}
window.AIcon = AIcon;

function StatusPill({ status }) {
  const map = {
    'neu':         ['Neu', 'st-new'],
    'in-pruefung': ['In Prüfung', 'st-review'],
    'angebot':     ['Angebot raus', 'st-offer'],
    'gewonnen':    ['Gewonnen', 'st-won'],
    'verloren':    ['Verloren', 'st-lost'],
    'aktiv':       ['Aktiv', 'st-won'],
    'pausiert':    ['Pausiert', 'st-paused'],
    'auf-anfrage': ['Auf Anfrage', 'st-review'],
    'entwurf':     ['Entwurf', 'st-lost'],
    'eingeladen':  ['Eingeladen', 'st-review'],
    'deaktiviert': ['Deaktiviert', 'st-lost'],
    'offen':       ['Offen', 'st-new'],
    'beantwortet': ['Beantwortet', 'st-won'],
    'demo':        ['Demo geplant', 'st-offer'],
  };
  const [label, cls] = map[status] || [status, 'st-new'];
  return <span className={'ad-pill ' + cls}>{label}</span>;
}
window.StatusPill = StatusPill;

function money(n) { return '€' + (n || 0).toLocaleString('de-DE'); }
window.adMoney = money;

// Lightweight toast for mocked actions (reminders, invites, saves).
window.adToast = function (msg) {
  let host = document.getElementById('ad-toast-host');
  if (!host) { host = document.createElement('div'); host.id = 'ad-toast-host'; document.body.appendChild(host); }
  const el = document.createElement('div');
  el.className = 'ad-toast';
  el.textContent = '✓  ' + msg;
  host.appendChild(el);
  requestAnimationFrame(() => el.classList.add('in'));
  setTimeout(() => { el.classList.remove('in'); setTimeout(() => el.remove(), 280); }, 2600);
};

// ---------- Dashboard ----------
function Dashboard({ state, goTo }) {
  const openReq = state.requests.filter(r => r.status === 'neu');
  const pipeline = state.requests.filter(r => ['neu','in-pruefung','angebot'].includes(r.status));
  const won = state.requests.filter(r => r.status === 'gewonnen');
  const pipelineValue = pipeline.reduce((s, r) => s + (r.value || 0), 0);
  const wonValue = won.reduce((s, r) => s + (r.value || 0), 0);

  const kpis = [
    { label: 'Offene Anfragen', value: openReq.length, sub: 'brauchen Antwort', accent: 'clay' },
    { label: 'Pipeline-Wert', value: money(pipelineValue), sub: pipeline.length + ' aktive Anfragen' },
    { label: 'Aktive Partnerplätze', value: state.partners.filter(p => p.status === 'aktiv').length, sub: state.partners.length + ' insgesamt' },
    { label: 'Überfällig', value: state.requests.filter(r => r.overdue).length, sub: 'Partei reagiert nicht', accent: 'clay' },
  ];

  const stages = [
    { id: 'neu', label: 'Neu' },
    { id: 'in-pruefung', label: 'In Prüfung' },
    { id: 'angebot', label: 'Angebot' },
    { id: 'gewonnen', label: 'Gewonnen' },
  ];

  return (
    <div className="ad-page">
      <div className="ad-page-head">
        <div>
          <h1 className="ad-h1">Übersicht</h1>
          <p className="ad-sub">Was heute auf dem Tisch liegt — Stand {new Date().toLocaleDateString('de-DE', { day:'numeric', month:'long', year:'numeric' })}.</p>
        </div>
      </div>

      <div className="ad-kpis">
        {kpis.map((k, i) => (
          <div key={i} className={'ad-kpi ' + (k.accent === 'clay' ? 'is-clay' : '')}>
            <div className="ad-kpi-v">{k.value}</div>
            <div className="ad-kpi-l">{k.label}</div>
            <div className="ad-kpi-s">{k.sub}</div>
          </div>
        ))}
      </div>

      <div className="ad-cols">
        <section className="ad-card">
          <div className="ad-card-head">
            <h2 className="ad-h2">Neueste Anfragen</h2>
            <button className="ad-link" onClick={() => goTo('requests')}>Alle ansehen →</button>
          </div>
          <div className="ad-list">
            {state.requests.slice(0, 5).map(r => (
              <button key={r.id} className="ad-row" onClick={() => goTo('requests')}>
                <div className="ad-row-main">
                  <div className="ad-row-t">{r.company}</div>
                  <div className="ad-row-s">{r.event} · {r.group} Pers. · {r.city}</div>
                </div>
                <div className="ad-row-end">
                  <span className="ad-row-val">{money(r.value)}</span>
                  <StatusPill status={r.status} />
                </div>
              </button>
            ))}
          </div>
        </section>

        <section className="ad-card">
          <div className="ad-card-head"><h2 className="ad-h2">Pipeline</h2></div>
          <div className="ad-pipeline">
            {stages.map(st => {
              const items = state.requests.filter(r => r.status === st.id);
              const val = items.reduce((s, r) => s + (r.value || 0), 0);
              return (
                <div key={st.id} className="ad-stage">
                  <div className="ad-stage-top">
                    <span className="ad-stage-l">{st.label}</span>
                    <span className="ad-stage-n">{items.length}</span>
                  </div>
                  <div className="ad-stage-bar"><div className="ad-stage-fill" style={{ width: Math.min(100, items.length * 28) + '%' }} /></div>
                  <div className="ad-stage-val">{money(val)}</div>
                </div>
              );
            })}
          </div>
          <div className="ad-card-head" style={{ marginTop: 24 }}>
            <h2 className="ad-h2">Partner-Onboarding</h2>
            <button className="ad-link" onClick={() => goTo('partners')}>Verwalten →</button>
          </div>
          <div className="ad-list">
            {state.partners.filter(p => p.status === 'in-pruefung').map(p => (
              <button key={p.id} className="ad-row" onClick={() => goTo('partners')}>
                <div className="ad-row-main">
                  <div className="ad-row-t">{p.name}</div>
                  <div className="ad-row-s">{p.city} · {p.contact}</div>
                </div>
                <StatusPill status={p.status} />
              </button>
            ))}
            {state.partners.filter(p => p.status === 'in-pruefung').length === 0 && (
              <div className="ad-empty-sm">Kein offenes Onboarding.</div>
            )}
          </div>
        </section>
      </div>
    </div>
  );
}

function ProfileSection({ profile }) {
  const [p, setP] = useState(profile);
  const [saved, setSaved] = useState(false);
  const fileRef = React.useRef();
  const set = (k, v) => { setP(s => ({ ...s, [k]: v })); setSaved(false); };
  const onFile = (e) => {
    const f = e.target.files[0];
    if (f) { const r = new FileReader(); r.onload = () => set('avatar', r.result); r.readAsDataURL(f); }
  };
  const save = () => { window.ADMIN.setProfile(p); setSaved(true); };

  return (
    <div className="ad-page">
      <div className="ad-page-head">
        <div><h1 className="ad-h1">Mein Profil</h1><p className="ad-sub">Dein Name, Foto und deine Kontaktdaten — so erscheinst du im Backend.</p></div>
      </div>
      <div className="ad-profile-card">
        <div className="ad-profile-photo">
          <span className="ad-avatar ad-avatar-lg">
            {p.avatar ? <img src={p.avatar} alt="" /> : <span>{p.initials || 'JK'}</span>}
          </span>
          <div>
            <button className="ad-btn-ghost ad-btn-sm" onClick={() => fileRef.current.click()}>Foto ändern</button>
            {p.avatar && <button className="ad-link ad-link-del" onClick={() => set('avatar', null)}>Entfernen</button>}
            <input ref={fileRef} type="file" accept="image/*" hidden onChange={onFile} />
          </div>
        </div>
        <div className="ad-profile-fields">
          <label className="ad-field"><span>Name</span>
            <input className="ad-input" value={p.name} onChange={(e) => set('name', e.target.value)} /></label>
          <label className="ad-field"><span>Rolle</span>
            <input className="ad-input" value={p.role} onChange={(e) => set('role', e.target.value)} /></label>
          <label className="ad-field"><span>E-Mail</span>
            <input className="ad-input" type="email" value={p.email} onChange={(e) => set('email', e.target.value)} /></label>
          <label className="ad-field"><span>Initialen (ohne Foto)</span>
            <input className="ad-input" maxLength={2} value={p.initials} onChange={(e) => set('initials', e.target.value.toUpperCase())} /></label>
        </div>
        <div className="ad-profile-foot">
          <button className="ad-btn" onClick={save}>Speichern</button>
          {saved && <span className="ad-won-note">✓ Gespeichert</span>}
        </div>
      </div>
    </div>
  );
}

function AdminApp() {
  const [tick, setTick] = useState(0);
  const [route, setRoute] = useState((location.hash.replace('#/', '') || 'dashboard'));
  const state = window.ADMIN.get();

  useEffect(() => window.ADMIN.subscribe(() => setTick(t => t + 1)), []);
  useEffect(() => {
    const onHash = () => setRoute(location.hash.replace('#/', '') || 'dashboard');
    window.addEventListener('hashchange', onHash);
    return () => window.removeEventListener('hashchange', onHash);
  }, []);

  const goTo = (id) => { location.hash = '#/' + id; };
  const S = window.ADMIN_SECTIONS || {};

  let body;
  if (route === 'requests')      body = <S.Requests state={state} />;
  else if (route === 'events')   body = <S.Events state={state} />;
  else if (route === 'partners') body = <S.Partners state={state} />;
  else if (route === 'users')    body = <S.Users state={state} />;
  else if (route === 'blog')     body = <S.Blog state={state} />;
  else if (route === 'profile')  body = <ProfileSection profile={state.profile} />;
  else                           body = <Dashboard state={state} goTo={goTo} />;

  return (
    <div className="ad-shell">
      <aside className="ad-side">
        <div className="ad-brand">
          <img src="assets/logo/firmengolf-wordmark.png" alt="Firmengolf" />
          <span className="ad-brand-tag">Admin</span>
        </div>
        <nav className="ad-nav">
          {NAV.map(n => {
            const badge = n.badge ? n.badge(state) : 0;
            return (
              <button key={n.id} className={'ad-navitem ' + (route === n.id || (route === '' && n.id === 'dashboard') ? 'active' : '')}
                      onClick={() => goTo(n.id)}>
                <AIcon name={n.icon} />
                <span>{n.label}</span>
                {badge > 0 && <span className="ad-nav-badge">{badge}</span>}
              </button>
            );
          })}
        </nav>
        <div className="ad-side-foot">
          <button className={'ad-profile ' + (route === 'profile' ? 'active' : '')} onClick={() => goTo('profile')}>
            <span className="ad-avatar">
              {state.profile.avatar
                ? <img src={state.profile.avatar} alt="" />
                : <span>{state.profile.initials || 'JK'}</span>}
            </span>
            <span className="ad-profile-txt">
              <span className="ad-profile-name">{state.profile.name}</span>
              <span className="ad-profile-role">{state.profile.role}</span>
            </span>
          </button>
          <a className="ad-navitem" href="index.html"><AIcon name="logout" /><span>Zur Website</span></a>
          <button className="ad-reset" onClick={() => { if (confirm('Demo-Daten zurücksetzen?')) window.ADMIN.reset(); }}>Demo zurücksetzen</button>
        </div>
      </aside>
      <main className="ad-main">{body}</main>
    </div>
  );
}

ReactDOM.createRoot(document.getElementById('admin-app')).render(<AdminApp />);
