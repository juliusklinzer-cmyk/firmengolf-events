/* eslint-disable */
// =============================================================
// Admin sections — Partners, Events, Requests (multi-party
// coordination), Users, Blog. Exposed as window.ADMIN_SECTIONS.
// =============================================================
(function () {
  const { useState, useMemo } = React;
  const Icon  = (p) => window.AIcon(p);
  const Pill  = (p) => window.StatusPill(p);
  const money = (n) => window.adMoney(n);
  const toast = (m) => window.adToast(m);
  const SITE = 'index.html';

  const ROLE_LABEL = { superadmin: 'Super-Admin', admin: 'Administrator', redakteur: 'Redakteur', platzmanager: 'Platz-Verwalter' };
  const ROLE_DESC = {
    superadmin: 'Vollzugriff inkl. Benutzer & Abrechnung',
    admin: 'Anfragen, Events, Partner verwalten',
    redakteur: 'Nur Magazin / Inhalte',
    platzmanager: 'Eigener Platz im Partner-Portal',
  };

  function PageHead({ title, sub, children }) {
    return (
      <div className="ad-page-head">
        <div><h1 className="ad-h1">{title}</h1>{sub && <p className="ad-sub">{sub}</p>}</div>
        {children}
      </div>
    );
  }
  function Search({ value, onChange, placeholder }) {
    return (
      <div className="ad-search">
        <Icon name="search" size={16} />
        <input value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder || 'Suchen…'} />
      </div>
    );
  }
  function Tabs({ items, value, onChange }) {
    return (
      <div className="ad-tabs">
        {items.map(([id, label, n]) => (
          <button key={id} className={'ad-tab ' + (value === id ? 'on' : '')} onClick={() => onChange(id)}>
            {label} {n != null && <span className="ad-tab-n">{n}</span>}
          </button>
        ))}
      </div>
    );
  }

  // ============================================================
  // PARTNERS
  // ============================================================
  function Partners({ state }) {
    const [tab, setTab] = useState('all');
    const [region, setRegion] = useState('all');
    const [q, setQ] = useState('');
    const [sel, setSel] = useState(null);

    const regions = ['all', ...Array.from(new Set(state.partners.map(p => p.region)))];
    const list = state.partners.filter(p => {
      if (tab !== 'all' && p.status !== tab) return false;
      if (region !== 'all' && p.region !== region) return false;
      if (q && !((p.name + ' ' + p.city + ' ' + p.contact).toLowerCase().includes(q.toLowerCase()))) return false;
      return true;
    });
    const cur = sel ? state.partners.find(p => p.id === sel) : null;
    const evOf = (pid) => state.events.filter(e => e.partnerId === pid);
    const cnt = (id) => id === 'all' ? state.partners.length : state.partners.filter(p => p.status === id).length;

    const togglePause = (p) => {
      const next = p.status === 'pausiert' ? 'aktiv' : 'pausiert';
      window.ADMIN.update('partners', p.id, { status: next });
      toast(next === 'pausiert'
        ? `${p.name} pausiert — alle ${evOf(p.id).length} Angebote offline`
        : `${p.name} wieder aktiv`);
    };

    return (
      <div className="ad-page">
        <PageHead title="Partnerplätze" sub="Alle Golfplätze auf Firmengolf — Status, Angebote und öffentliches Profil verwalten.">
          <Search value={q} onChange={setQ} placeholder="Platz, Stadt, Kontakt…" />
        </PageHead>

        <div className="ad-filterbar">
          <Tabs value={tab} onChange={setTab} items={[['all','Alle',cnt('all')],['aktiv','Aktiv',cnt('aktiv')],['pausiert','Pausiert',cnt('pausiert')],['in-pruefung','In Prüfung',cnt('in-pruefung')]]} />
          <label className="ad-selwrap">
            <select className="ad-select" value={region} onChange={e => setRegion(e.target.value)}>
              {regions.map(r => <option key={r} value={r}>{r === 'all' ? 'Alle Regionen' : r}</option>)}
            </select>
          </label>
        </div>

        <div className="ad-table">
          <div className="ad-thead ad-pt-grid"><span>Platz</span><span>Region</span><span>Löcher</span><span>Angebote</span><span>Bewertung</span><span>Status</span></div>
          {list.map(p => {
            const evs = evOf(p.id);
            return (
              <button key={p.id} className="ad-trow ad-pt-grid" onClick={() => setSel(p.id)}>
                <span className="ad-cell-main"><span className="ad-cell-t">{p.name}</span><span className="ad-cell-s">{p.city} · {p.contact}</span></span>
                <span className="ad-cell-s">{p.region}</span>
                <span className="ad-cell-s">{p.holes}</span>
                <span className="ad-cell-s">{evs.length}{p.status === 'pausiert' && evs.length ? ' (offline)' : ''}</span>
                <span className="ad-cell-s">{p.rating ? '★ ' + p.rating : '—'}</span>
                <span><Pill status={p.status} /></span>
              </button>
            );
          })}
          {list.length === 0 && <div className="ad-empty">Keine Plätze in dieser Ansicht.</div>}
        </div>

        {cur && (
          <div className="ad-drawer-scrim" onClick={() => setSel(null)}>
            <div className="ad-drawer" onClick={e => e.stopPropagation()}>
              <div className="ad-drawer-top">
                <div>
                  <div className="ad-drawer-eyebrow">Partnerplatz · {cur.region}</div>
                  <h2 className="ad-drawer-h">{cur.name}</h2>
                </div>
                <button className="ad-x" onClick={() => setSel(null)}>✕</button>
              </div>
              <div className="ad-drawer-body">
                <div className="ad-dstatus"><Pill status={cur.status} /></div>
                {cur.status === 'pausiert' && (
                  <div className="ad-banner is-warn"><Icon name="flag" size={15} /> Platz pausiert — alle {evOf(cur.id).length} Angebote sind offline und für Firmen nicht buchbar.</div>
                )}
                <dl className="ad-deflist">
                  <div><dt>Adresse</dt><dd>{cur.address}</dd></div>
                  <div><dt>Anlage</dt><dd>{cur.holes} Löcher</dd></div>
                  <div><dt>Ansprechpartner</dt><dd>{cur.contact} · {cur.role}</dd></div>
                  <div><dt>E-Mail</dt><dd><a href={'mailto:' + cur.email}>{cur.email}</a></dd></div>
                  <div><dt>Telefon</dt><dd>{cur.phone}</dd></div>
                  <div><dt>Bewertung</dt><dd>{cur.rating ? '★ ' + cur.rating : 'noch keine'}</dd></div>
                  <div><dt>Partner seit</dt><dd>{cur.joined}</dd></div>
                </dl>

                <div className="ad-sub-label">Angebote dieses Platzes ({evOf(cur.id).length})</div>
                <div className="ad-minilist">
                  {evOf(cur.id).map(e => (
                    <div className="ad-minirow" key={e.id}>
                      <span>{e.title}</span>
                      <span className="ad-cell-s">{e.bookings} Buchungen</span>
                    </div>
                  ))}
                  {evOf(cur.id).length === 0 && <div className="ad-empty-sm">Noch keine Angebote angelegt.</div>}
                </div>
              </div>
              <div className="ad-drawer-foot">
                <a className="ad-btn-ghost" href={SITE + '#/events'} target="_blank" rel="noopener"><Icon name="search" size={15} /> Öffentliches Profil</a>
                {cur.status === 'in-pruefung'
                  ? <button className="ad-btn" onClick={() => { window.ADMIN.update('partners', cur.id, { status: 'aktiv' }); toast(cur.name + ' freigeschaltet'); }}>Freischalten</button>
                  : <button className={cur.status === 'pausiert' ? 'ad-btn' : 'ad-btn-ghost'} onClick={() => togglePause(cur)}>
                      {cur.status === 'pausiert' ? 'Wieder aktivieren' : 'Platz pausieren'}
                    </button>}
              </div>
            </div>
          </div>
        )}
      </div>
    );
  }

  // ============================================================
  // FIRMENEVENTS
  // ============================================================
  function Events({ state }) {
    const [tab, setTab] = useState('all');
    const [q, setQ] = useState('');
    const [sel, setSel] = useState(null);
    const pById = (id) => state.partners.find(p => p.id === id);
    const eff = (e) => (e.owner === 'partner' && (pById(e.partnerId) || {}).status === 'pausiert') ? 'pausiert' : e.status;
    const price = (e) => e.pricePerPerson ? 'ab €' + e.pricePerPerson + ' p.P.' : (e.pricePerGroup ? money(e.pricePerGroup) + ' / Gruppe' : '—');

    const list = state.events.filter(e => {
      if (tab === 'partner' && e.owner !== 'partner') return false;
      if (tab === 'eigene' && e.owner !== 'firmengolf') return false;
      if (tab === 'entwurf' && e.status !== 'entwurf') return false;
      if (q && !((e.title + ' ' + e.venue + ' ' + e.formatLabel).toLowerCase().includes(q.toLowerCase()))) return false;
      return true;
    });
    const cur = sel ? state.events.find(e => e.id === sel) : null;
    const cnt = (f) => f === 'all' ? state.events.length : f === 'partner' ? state.events.filter(e => e.owner === 'partner').length : f === 'eigene' ? state.events.filter(e => e.owner === 'firmengolf').length : state.events.filter(e => e.status === 'entwurf').length;

    return (
      <div className="ad-page">
        <PageHead title="Firmenevents" sub="Buchbare Partner-Angebote und unsere eigenen, auf Anfrage geplanten Formate.">
          <Search value={q} onChange={setQ} placeholder="Titel, Platz, Typ…" />
        </PageHead>

        <Tabs value={tab} onChange={setTab} items={[['all','Alle',cnt('all')],['partner','Partner-Events',cnt('partner')],['eigene','Eigene (auf Anfrage)',cnt('eigene')],['entwurf','Entwürfe',cnt('entwurf')]]} />

        <div className="ad-table">
          <div className="ad-thead ad-fe-grid"><span>Event</span><span>Typ</span><span>Platz / Owner</span><span>Aufrufe</span><span>Buchungen</span><span>Status</span></div>
          {list.map(e => {
            const es = eff(e);
            return (
              <button key={e.id} className="ad-trow ad-fe-grid" onClick={() => setSel(e.id)}>
                <span className="ad-cell-main">
                  <span className="ad-cell-t">{e.title}</span>
                  <span className="ad-cell-s">{e.owner === 'firmengolf' ? <span className="ad-tagpill is-own">Firmengolf</span> : <span className="ad-tagpill">Partner</span>} {e.formatLabel}</span>
                </span>
                <span className="ad-cell-s">{e.formatLabel}</span>
                <span className="ad-cell-s">{e.owner === 'firmengolf' ? 'Firmengolf (Planung)' : window.ADMIN.partnerName(e.partnerId)}</span>
                <span className="ad-cell-s">{e.views.toLocaleString('de-DE')}</span>
                <span className="ad-cell-s">{e.bookings}</span>
                <span><Pill status={es} /></span>
              </button>
            );
          })}
          {list.length === 0 && <div className="ad-empty">Keine Events in dieser Ansicht.</div>}
        </div>

        {cur && (
          <div className="ad-drawer-scrim" onClick={() => setSel(null)}>
            <div className="ad-drawer" onClick={e => e.stopPropagation()}>
              <div className="ad-drawer-top">
                <div>
                  <div className="ad-drawer-eyebrow">{cur.owner === 'firmengolf' ? 'Eigenes Format · auf Anfrage' : 'Partner-Event'}</div>
                  <h2 className="ad-drawer-h">{cur.title}</h2>
                </div>
                <button className="ad-x" onClick={() => setSel(null)}>✕</button>
              </div>
              <div className="ad-drawer-body">
                <div className="ad-dstatus"><Pill status={eff(cur)} /></div>
                {eff(cur) === 'pausiert' && cur.status !== 'pausiert' && (
                  <div className="ad-banner is-warn"><Icon name="flag" size={15} /> Offline, weil der Platz pausiert ist.</div>
                )}
                <div className="ad-statgrid">
                  <div className="ad-statbox"><div className="v">{cur.views.toLocaleString('de-DE')}</div><div className="l">Aufrufe</div></div>
                  <div className="ad-statbox"><div className="v">{cur.bookings}</div><div className="l">Buchungen</div></div>
                  <div className="ad-statbox"><div className="v">{cur.rating ? '★ ' + cur.rating : '—'}</div><div className="l">{cur.reviews || 0} Bewertungen</div></div>
                </div>
                <dl className="ad-deflist">
                  <div><dt>Veranstaltungstyp</dt><dd>{cur.formatLabel}</dd></div>
                  <div><dt>Platz</dt><dd>{cur.owner === 'firmengolf' ? 'Firmengolf plant individuell' : window.ADMIN.partnerName(cur.partnerId) + ' · ' + cur.venue}</dd></div>
                  <div><dt>Region</dt><dd>{cur.region}</dd></div>
                  <div><dt>Dauer</dt><dd>{cur.duration}</dd></div>
                  <div><dt>Gruppe</dt><dd>{cur.groupMin}–{cur.groupMax} Personen</dd></div>
                  <div><dt>Preis</dt><dd>{price(cur)}</dd></div>
                </dl>
              </div>
              <div className="ad-drawer-foot">
                {cur.slug && <a className="ad-btn-ghost" href={SITE + '#/events/' + cur.slug} target="_blank" rel="noopener"><Icon name="search" size={15} /> Öffentliches Profil</a>}
                <button className={'ad-toggle-btn ' + (cur.status === 'aktiv' || cur.status === 'auf-anfrage' ? 'on' : '')}
                  onClick={() => { const ns = cur.status === 'entwurf' ? 'aktiv' : (cur.status === 'aktiv' ? 'entwurf' : cur.status); window.ADMIN.update('events', cur.id, { status: ns }); toast(ns === 'aktiv' ? 'Event veröffentlicht' : 'Event auf Entwurf gesetzt'); }}>
                  {cur.status === 'entwurf' ? 'Veröffentlichen' : cur.status === 'aktiv' ? 'Auf Entwurf setzen' : 'Auf Anfrage'}
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    );
  }

  // ============================================================
  // ANFRAGEN — multi-party coordination + takeover
  // ============================================================
  const REQ_STAGES = [
    ['all','Alle'],['neu','Neu'],['in-pruefung','In Prüfung'],['angebot','Angebot'],['gewonnen','Gewonnen'],['verloren','Verloren'],
  ];
  const NEXT_STATUS = { 'neu': 'in-pruefung', 'in-pruefung': 'angebot', 'angebot': 'gewonnen' };
  const NEXT_LABEL  = { 'neu': 'In Prüfung nehmen', 'in-pruefung': 'Angebot rausschicken', 'angebot': 'Als gewonnen markieren' };
  const PARTY_ICON = { firma: 'mailicon', platz: 'pin', firmengolf: 'flag' };
  const PARTY_STATUS = {
    proposed: ['Termine vorgeschlagen', 'st-review'],
    partial:  ['Teils abgestimmt', 'st-review'],
    done:     ['Abgestimmt', 'st-won'],
    pending:  ['Wartet auf Antwort', 'st-new'],
    waiting:  ['Wartet', 'st-lost'],
    active:   ['In Arbeit', 'st-offer'],
    declined: ['Abgelehnt', 'st-lost'],
    closed:   ['Geschlossen', 'st-lost'],
  };

  function Requests({ state }) {
    const [tab, setTab] = useState('all');
    const [q, setQ] = useState('');
    const [sel, setSel] = useState(null);

    const list = state.requests.filter(r => {
      if (tab !== 'all' && r.status !== tab) return false;
      if (q && !((r.company + ' ' + r.event + ' ' + r.contact + ' ' + r.city).toLowerCase().includes(q.toLowerCase()))) return false;
      return true;
    });
    const cur = sel ? state.requests.find(r => r.id === sel) : null;
    const cnt = (id) => id === 'all' ? state.requests.length : state.requests.filter(r => r.status === id).length;

    const saveDate = (r, dateId) => {
      const wishDates = r.wishDates.map(d => ({ ...d, final: d.id === dateId }));
      const patch = { wishDates };
      if (r.status === 'neu' || r.status === 'in-pruefung') patch.status = 'angebot';
      window.ADMIN.update('requests', r.id, patch);
      toast('Termin gespeichert — ' + (r.wishDates.find(d => d.id === dateId) || {}).date);
    };
    const takeOver = (r) => { window.ADMIN.update('requests', r.id, { takenOver: true, overdue: false }); toast('Du hast die Koordination übernommen'); };

    return (
      <div className="ad-page">
        <PageHead title="Anfragen" sub="Alle Anfragen zusammengeführt — mit Termin-Abstimmung aller Parteien. Reagiert jemand nicht, übernimmst du.">
          <Search value={q} onChange={setQ} placeholder="Firma, Event, Stadt…" />
        </PageHead>

        <Tabs value={tab} onChange={setTab} items={REQ_STAGES.map(([id, l]) => [id, l, cnt(id)])} />

        <div className="ad-table">
          <div className="ad-thead ad-rq-grid"><span>Firma / Event</span><span>Gruppe</span><span>Abstimmung</span><span>Wert</span><span>Status</span></div>
          {list.map(r => {
            const platz = r.parties.find(p => p.kind === 'platz');
            return (
              <button key={r.id} className="ad-trow ad-rq-grid" onClick={() => setSel(r.id)}>
                <span className="ad-cell-main"><span className="ad-cell-t">{r.company}</span><span className="ad-cell-s">{r.event} · {r.city}</span></span>
                <span className="ad-cell-s">{r.group} Pers.</span>
                <span className="ad-cell-s">
                  {r.overdue && <span className="ad-tagpill is-overdue">Überfällig</span>}
                  {platz ? (platz.total ? `${platz.done || 0}/${platz.total} Platz` : (r.ownPlanned ? 'Eigenplanung' : '—')) : 'Eigenplanung'}
                </span>
                <span className="ad-cell-val">{money(r.value)}</span>
                <span><Pill status={r.status} /></span>
              </button>
            );
          })}
          {list.length === 0 && <div className="ad-empty">Keine Anfragen in dieser Ansicht.</div>}
        </div>

        {cur && (
          <div className="ad-drawer-scrim" onClick={() => setSel(null)}>
            <div className="ad-drawer ad-drawer-wide" onClick={e => e.stopPropagation()}>
              <div className="ad-drawer-top">
                <div>
                  <div className="ad-drawer-eyebrow">{cur.kind} · {cur.id}</div>
                  <h2 className="ad-drawer-h">{cur.company}</h2>
                </div>
                <button className="ad-x" onClick={() => setSel(null)}>✕</button>
              </div>
              <div className="ad-drawer-body">
                <div className="ad-dstatus" style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                  <Pill status={cur.status} />
                  {cur.overdue && !cur.takenOver && <span className="ad-pill st-overdue">Überfällig seit {cur.deadline}</span>}
                  {cur.takenOver && <span className="ad-pill st-offer">Von dir übernommen</span>}
                </div>

                {/* routing — how this request flows */}
                <div className={'ad-banner ' + (cur.kind === 'Individuelles Event' ? 'is-info' : 'is-route')} style={{ marginTop: 16 }}>
                  <Icon name={cur.kind === 'Individuelles Event' ? 'flag' : 'pin'} size={15} />
                  <span>{cur.kind === 'Individuelles Event'
                    ? 'Individuelles Event — Anfrage liegt nur bei Firmengolf. Wir planen und wählen den Platz selbst; die Firma hat eine Eingangsbestätigung per Mail erhalten.'
                    : 'Event-Anfrage — liegt beim Golfplatz zur Terminfreigabe (Platz · Pro · Gastro) und bei uns. Sobald der Club einen Termin per Mail bestätigt, werden wir benachrichtigt.'}</span>
                </div>

                {/* parties coordination */}
                <div className="ad-sub-label">Beteiligte Parteien</div>
                <div className="ad-parties">
                  {cur.parties.map((pt, i) => {
                    const [lbl, cls] = PARTY_STATUS[pt.status] || [pt.status, 'st-new'];
                    return (
                      <div className={'ad-party ' + (pt.overdue ? 'is-overdue' : '')} key={i}>
                        <div className={'ad-party-ic k-' + pt.kind}><Icon name={pt.kind === 'platz' ? 'pin' : pt.kind === 'firmengolf' ? 'flag' : 'mailicon'} size={16} /></div>
                        <div className="ad-party-main">
                          <div className="ad-party-name">{pt.name}{pt.kind === 'platz' && pt.total ? <span className="ad-party-frac">{pt.done || 0}/{pt.total} Personen</span> : null}</div>
                          <div className="ad-party-who">{pt.who}{pt.when ? ' · ' + pt.when : ''}</div>
                        </div>
                        <div className="ad-party-end">
                          <span className={'ad-pill ' + cls}>{lbl}</span>
                          {pt.overdue && <span className="ad-pill st-overdue">Überfällig</span>}
                        </div>
                      </div>
                    );
                  })}
                </div>

                {(cur.overdue && !cur.takenOver) && (
                  <div className="ad-banner is-warn" style={{ marginTop: 6 }}>
                    <Icon name="flag" size={15} />
                    <span>Eine Partei hat nicht rechtzeitig reagiert. Übernimm die Koordination, um direkt mit der Firma einen Termin festzulegen.</span>
                    <button className="ad-btn ad-btn-sm" style={{ marginLeft: 'auto' }} onClick={() => takeOver(cur)}>Übernehmen</button>
                  </div>
                )}

                {/* wish dates */}
                <div className="ad-sub-label">Wunschtermine</div>
                <div className="ad-wishlist">
                  {cur.wishDates.map(d => {
                    const resp = d.course ? d.course.resp : 'na';
                    const respMap = { confirmed: ['Platz verfügbar', 'st-won'], pending: ['Platz wartet', 'st-new'], declined: ['Platz abgelehnt', 'st-lost'], na: ['Firmengolf plant', 'st-review'] };
                    const [rl, rc] = respMap[resp] || respMap.na;
                    return (
                      <div className={'ad-wish ' + (d.final ? 'is-final' : '')} key={d.id}>
                        <div className="ad-wish-main">
                          <div className="ad-wish-d">{d.final && <Icon name="flag" size={13} />}{d.date}</div>
                          <div className="ad-cell-s">{d.slot}</div>
                        </div>
                        <span className={'ad-pill ' + rc}>{rl}</span>
                        {!d.final && cur.status !== 'verloren' && cur.status !== 'gewonnen' && (resp === 'confirmed' || cur.takenOver || cur.ownPlanned) && (
                          <button className="ad-btn-ghost ad-btn-sm" onClick={() => saveDate(cur, d.id)}><Icon name="flag" size={13} /> Termin speichern</button>
                        )}
                        {d.final && <span className="ad-won-note" style={{ fontSize: 13 }}>✓ Festgelegt</span>}
                      </div>
                    );
                  })}
                </div>

                {/* company / wizard data */}
                <div className="ad-sub-label">Anfrage-Details (von der Firma)</div>
                <dl className="ad-deflist">
                  <div><dt>Veranstaltungstyp</dt><dd>{cur.eventType || cur.event}</dd></div>
                  <div><dt>Ansprechpartner</dt><dd>{cur.contact}</dd></div>
                  <div><dt>E-Mail</dt><dd><a href={'mailto:' + cur.email}>{cur.email}</a></dd></div>
                  <div><dt>Telefon</dt><dd>{cur.phone || '—'}</dd></div>
                  <div><dt>Standort</dt><dd>{cur.city}</dd></div>
                  <div><dt>Gruppe</dt><dd>{cur.group} Personen</dd></div>
                  <div><dt>Tageszeit</dt><dd>{cur.daypart}</dd></div>
                  <div><dt>Budget</dt><dd>{cur.budget}</dd></div>
                  <div><dt>Kalk. Wert</dt><dd>{money(cur.value)}</dd></div>
                  <div><dt>Leistungen</dt><dd>{cur.services.length ? cur.services.join(', ') : '—'}</dd></div>
                  <div><dt>Platz</dt><dd>{cur.partnerId ? window.ADMIN.partnerName(cur.partnerId) : 'Firmengolf plant (kein fester Platz)'}</dd></div>
                  {cur.note && <div><dt>Notiz</dt><dd>{cur.note}</dd></div>}
                </dl>
              </div>
              <div className="ad-drawer-foot">
                {cur.status !== 'verloren' && cur.status !== 'gewonnen' && (
                  <button className="ad-btn-ghost" onClick={() => { window.ADMIN.update('requests', cur.id, { status: 'verloren' }); }}>Verloren</button>
                )}
                {NEXT_STATUS[cur.status] && (
                  <button className="ad-btn" onClick={() => window.ADMIN.update('requests', cur.id, { status: NEXT_STATUS[cur.status] })}>{NEXT_LABEL[cur.status]}</button>
                )}
                {cur.status === 'gewonnen' && <span className="ad-won-note">✓ Event gewonnen</span>}
              </div>
            </div>
          </div>
        )}
      </div>
    );
  }

  // ============================================================
  // BENUTZER (WordPress-like)
  // ============================================================
  function Users({ state }) {
    const [tab, setTab] = useState('all');
    const [q, setQ] = useState('');
    const [showNew, setShowNew] = useState(false);

    const list = state.users.filter(u => {
      if (tab === 'firmengolf' && u.org !== 'firmengolf') return false;
      if (tab === 'partner' && u.org === 'firmengolf') return false;
      if (q && !((u.name + ' ' + u.email).toLowerCase().includes(q.toLowerCase()))) return false;
      return true;
    });
    const orgName = (org) => org === 'firmengolf' ? 'Firmengolf' : window.ADMIN.partnerName(org);
    const cnt = (f) => f === 'all' ? state.users.length : f === 'firmengolf' ? state.users.filter(u => u.org === 'firmengolf').length : state.users.filter(u => u.org !== 'firmengolf').length;

    const toggleActive = (u) => {
      const next = u.status === 'deaktiviert' ? 'aktiv' : 'deaktiviert';
      window.ADMIN.update('users', u.id, { status: next });
      toast(next === 'aktiv' ? u.name + ' aktiviert' : u.name + ' deaktiviert');
    };

    return (
      <div className="ad-page">
        <PageHead title="Benutzer" sub="Zugänge für das Firmengolf-Team und die Partnerplätze. Firmen fragen nur an — sie haben keinen Zugang.">
          <button className="ad-btn" onClick={() => setShowNew(true)}><Icon name="users" size={15} /> Benutzer einladen</button>
        </PageHead>

        <div className="ad-filterbar">
          <Tabs value={tab} onChange={setTab} items={[['all','Alle',cnt('all')],['firmengolf','Firmengolf-Team',cnt('firmengolf')],['partner','Platz-Verwalter',cnt('partner')]]} />
          <Search value={q} onChange={setQ} placeholder="Name, E-Mail…" />
        </div>

        <div className="ad-table">
          <div className="ad-thead ad-us-grid"><span>Benutzer</span><span>Rolle</span><span>Zugehörigkeit</span><span>Zuletzt aktiv</span><span>Status</span></div>
          {list.map(u => (
            <div key={u.id} className="ad-trow ad-us-grid">
              <span className="ad-cell-main ad-userrow">
                <span className="ad-uava">{u.name.split(' ').map(n => n[0]).slice(0,2).join('')}</span>
                <span><span className="ad-cell-t">{u.name}</span><span className="ad-cell-s">{u.email}</span></span>
              </span>
              <span><span className={'ad-rolepill r-' + u.role}>{ROLE_LABEL[u.role]}</span></span>
              <span className="ad-cell-s">{orgName(u.org)}</span>
              <span className="ad-cell-s">{u.lastActive}</span>
              <span className="ad-ct-actions">
                <Pill status={u.status} />
                {u.role !== 'superadmin' && (
                  <button className="ad-btn-ghost ad-btn-sm" onClick={() => toggleActive(u)}>{u.status === 'deaktiviert' ? 'Aktivieren' : 'Deaktivieren'}</button>
                )}
              </span>
            </div>
          ))}
          {list.length === 0 && <div className="ad-empty">Keine Benutzer in dieser Ansicht.</div>}
        </div>

        {showNew && <NewUser state={state} onClose={() => setShowNew(false)} />}
      </div>
    );
  }

  function NewUser({ state, onClose }) {
    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [role, setRole] = useState('admin');
    const [org, setOrg] = useState('firmengolf');
    const isPartner = role === 'platzmanager';
    const valid = name.trim() && /.+@.+\..+/.test(email) && (!isPartner || org !== 'firmengolf');

    const create = () => {
      const id = 'u' + Date.now();
      window.ADMIN.add('users', { id, name: name.trim(), email: email.trim(), role, org: isPartner ? org : 'firmengolf', status: 'eingeladen', lastActive: '—' });
      window.adToast('Einladung an ' + email + ' gesendet');
      onClose();
    };

    return (
      <div className="ad-modal-scrim" onClick={onClose}>
        <div className="ad-modal" onClick={e => e.stopPropagation()}>
          <div className="ad-modal-head">
            <h2 className="ad-h2">Neuen Benutzer einladen</h2>
            <button className="ad-x" onClick={onClose}>✕</button>
          </div>
          <div className="ad-modal-body">
            <label className="ad-field"><span>Name</span>
              <input className="ad-input" value={name} onChange={e => setName(e.target.value)} placeholder="Vor- und Nachname" /></label>
            <label className="ad-field"><span>E-Mail</span>
              <input className="ad-input" type="email" value={email} onChange={e => setEmail(e.target.value)} placeholder="name@firma.de" /></label>
            <label className="ad-field"><span>Rolle</span>
              <select className="ad-input" value={role} onChange={e => setRole(e.target.value)}>
                <option value="superadmin">Super-Admin</option>
                <option value="admin">Administrator</option>
                <option value="redakteur">Redakteur (Magazin)</option>
                <option value="platzmanager">Platz-Verwalter</option>
              </select>
              <span className="ad-field-hint">{ROLE_DESC[role]}</span>
            </label>
            <label className="ad-field"><span>Zugehörigkeit</span>
              {isPartner ? (
                <select className="ad-input" value={org === 'firmengolf' ? '' : org} onChange={e => setOrg(e.target.value)}>
                  <option value="">Platz wählen…</option>
                  {state.partners.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
                </select>
              ) : (
                <input className="ad-input" value="Firmengolf (unser Unternehmen)" disabled />
              )}
            </label>
          </div>
          <div className="ad-modal-foot">
            <button className="ad-btn-ghost" onClick={onClose}>Abbrechen</button>
            <button className="ad-btn" disabled={!valid} onClick={create}>Einladung senden</button>
          </div>
        </div>
      </div>
    );
  }

  // ============================================================
  // MAGAZIN
  // ============================================================
  function Blog({ state }) {
    return (
      <div className="ad-page">
        <PageHead title="Magazin" sub="Beiträge entstehen im Code / Redaktionssystem — hier nur aktivieren oder deaktivieren." />
        <div className="ad-table">
          <div className="ad-thead ad-bl-grid"><span>Titel</span><span>Kategorie</span><span>Autor</span><span>Datum</span><span>Sichtbar</span></div>
          {state.posts.map(p => (
            <div key={p.slug} className="ad-trow ad-bl-grid">
              <span className="ad-cell-main"><span className="ad-cell-t">{p.title}</span>{p.featured && <span className="ad-cell-s">★ Top-Story</span>}</span>
              <span className="ad-cell-s">{p.tag}</span>
              <span className="ad-cell-s">{p.author}</span>
              <span className="ad-cell-s">{p.date}</span>
              <span>
                <button className={'ad-toggle ' + (p.published ? 'on' : '')} onClick={() => window.ADMIN.update('posts', p.slug, { published: !p.published })}>
                  <span className="ad-toggle-dot" />
                </button>
              </span>
            </div>
          ))}
        </div>
      </div>
    );
  }

  window.ADMIN_SECTIONS = { Partners, Events, Requests, Users, Blog };
})();
