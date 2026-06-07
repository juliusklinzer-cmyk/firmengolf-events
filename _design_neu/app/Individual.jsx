/* eslint-disable */
// =============================================================
// Individuelle Events — rebuilt, simpler (naboo-inspired).
// Sections: Hero · Veranstaltungstyp · Budget-Rechner ·
// Golf-Erfahrung · Nacht-Event · Foto-CTA. All events take place
// on a golf course. Opens window.RequestWizard on request.
// =============================================================
var { useState, useMemo } = React;

// ---------- small inline icons for the budget chips ----------
function BcIcon({ name, size = 18 }) {
  const p = {
    catering: <><path d="M5 8h12v4a6 6 0 0 1-12 0z"/><path d="M17 9h2a2 2 0 0 1 0 4h-2M5 21h12"/></>,
    coaching: <><path d="M5 21V4M5 4l11 2-3 4 3 4-11 2"/></>,
    bed:      <><path d="M3 18v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6"/><path d="M3 14h18M7 10V7a1 1 0 0 1 1-1h3v4"/></>,
    bus:      <><rect x="4" y="4" width="16" height="13" rx="2"/><path d="M4 11h16M8 17v2M16 17v2"/><circle cx="8" cy="14" r="1"/><circle cx="16" cy="14" r="1"/></>,
    show:     <><path d="M13 2L4 14h6l-1 8 9-12h-6z"/></>,
    cam:      <><rect x="3" y="7" width="18" height="13" rx="2"/><circle cx="12" cy="13.5" r="3.2"/><path d="M8 7l1.5-3h5L16 7"/></>,
    arrow:    <><path d="M5 12h14M13 5l7 7-7 7"/></>,
  };
  return (
    <svg viewBox="0 0 24 24" width={size} height={size} fill="none" stroke="currentColor"
         strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">{p[name]}</svg>
  );
}

// ---------- budget model ----------
const BC_TYPES = [
  { id: 'teamevent',   label: 'Teamevent',        pp: 150, wiz: 'Teamevent' },
  { id: 'turnier',     label: 'Firmenturnier',        pp: 290, wiz: 'Firmenturnier' },
  { id: 'kundenevent', label: 'Kundenevent',          pp: 215, wiz: 'Kundenevent' },
  { id: 'offsite',     label: 'Offsite & Incentive',  pp: 430, wiz: 'Incentive-Reise' },
  { id: 'sommerfest',  label: 'Sommerfest & Dinner',  pp: 185, wiz: 'Sommerfest' },
  { id: 'gesundheit',  label: 'Gesundheitstag',       pp: 140, wiz: 'Gesundheitstag' },
];
const BC_RANGE = { '€': 0.82, '€€': 1, '€€€': 1.45 };
const BC_SERVICES = [
  { id: 'catering',     label: 'Catering',           cat: 'catering',     icon: 'catering', pp: 62,  wiz: 'Lunch' },
  { id: 'coaching',     label: 'Coaching & Programm', cat: 'programm',     icon: 'coaching', pp: 48,  wiz: 'Golflehrer / Coaching' },
  { id: 'uebernachtung',label: 'Übernachtung',        cat: 'uebernachtung',icon: 'bed',      pp: 155, wiz: 'Übernachtung' },
  { id: 'transport',    label: 'Transport & Shuttle', cat: 'transport',    icon: 'bus',      pp: 38,  wiz: 'Shuttle / Transport' },
  { id: 'technik',      label: 'Technik & Show',      cat: 'technik',      icon: 'show',     flat: 3200, wiz: 'Eventtechnik: Bühne + Personal' },
  { id: 'foto',         label: 'Foto & Content',      cat: 'foto',         icon: 'cam',      flat: 1400, wiz: 'Fotograf' },
];
const BC_CAT = {
  venue:        { label: 'Golfplatz & Greenfee', color: '#2C5036' },
  programm:     { label: 'Programm & Coaching',  color: '#6E9A5E' },
  catering:     { label: 'Catering',             color: '#C9B488' },
  uebernachtung:{ label: 'Übernachtung',         color: '#B45A37' },
  transport:    { label: 'Transport',            color: '#6C736E' },
  technik:      { label: 'Technik & Show',       color: '#3F6B49' },
  foto:         { label: 'Foto & Content',       color: '#D8C9A6' },
};
const fmt = (n) => new Intl.NumberFormat('de-DE').format(Math.round(n / 50) * 50);

function computeBudget(participants, typeId, range, services) {
  const type = BC_TYPES.find(t => t.id === typeId) || BC_TYPES[0];
  const mult = BC_RANGE[range] || 1;
  const cats = {};
  const add = (cat, amt) => { cats[cat] = (cats[cat] || 0) + amt; };
  add('venue', participants * type.pp * 0.6);
  add('programm', participants * type.pp * 0.4);
  services.forEach(sid => {
    const s = BC_SERVICES.find(x => x.id === sid);
    if (!s) return;
    add(s.cat, s.flat ? s.flat : participants * s.pp);
  });
  const rows = Object.keys(cats)
    .map(cat => ({ cat, ...BC_CAT[cat], amount: cats[cat] * mult }))
    .filter(r => r.amount > 0)
    .sort((a, b) => b.amount - a.amount);
  const total = rows.reduce((s, r) => s + r.amount, 0);
  return { rows, total, type };
}

// ---------- donut ----------
function BudgetDonut({ rows, total, size = 168 }) {
  const r = (size - 22) / 2;
  const C = 2 * Math.PI * r;
  let off = 0;
  return (
    <div className="bc-donut">
      <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`}>
        <circle className="bc-donut-track" cx={size / 2} cy={size / 2} r={r} fill="none" strokeWidth="20" />
        {rows.map((row, i) => {
          const frac = total ? row.amount / total : 0;
          const len = frac * C;
          const seg = (
            <circle key={i} cx={size / 2} cy={size / 2} r={r} fill="none"
              stroke={row.color} strokeWidth="20" strokeLinecap="butt"
              strokeDasharray={`${len} ${C - len}`} strokeDashoffset={-off} />
          );
          off += len;
          return seg;
        })}
      </svg>
    </div>
  );
}

// ---------- Budget-Rechner section ----------
function BudgetRechner({ onRequest }) {
  const [participants, setParticipants] = useState(30);
  const [typeId, setTypeId] = useState('teamevent');
  const [range, setRange] = useState('€€');
  const [services, setServices] = useState(['catering', 'coaching']);

  const step = (d) => setParticipants(p => Math.max(6, Math.min(250, p + d)));
  const toggleSvc = (id) => setServices(s => s.includes(id) ? s.filter(x => x !== id) : [...s, id]);

  const { rows, total, type } = useMemo(
    () => computeBudget(participants, typeId, range, services),
    [participants, typeId, range, services]
  );

  const request = () => {
    const wizServices = services.map(id => (BC_SERVICES.find(s => s.id === id) || {}).wiz).filter(Boolean);
    const lo = Math.round(total * 0.85 / 1000);
    const hi = Math.round(total * 1.15 / 1000);
    onRequest({
      occasion: type.wiz,
      size: String(participants),
      services: wizServices,
      notes: `Über den Budget-Rechner geschätzt: ${type.label}, ${participants} Personen, Preisniveau ${range} — Richtwert ca. €${fmt(total)}.`,
      budget: `€${lo}.000 – €${hi}.000`,
    });
  };

  return (
    <section className="bcalc-wrap" id="budget">
      <div className="bcalc">
        <div className="bcalc-head">
          <div className="mk-eyebrow" style={{ color: 'var(--fairway-700)' }}>Budget-Rechner</div>
          <h2 className="mk-h2">Was kostet euer Event? <span className="mk-italic">Sofort</span> geschätzt.</h2>
          <p className="mk-sub">Stell ein paar Eckdaten ein und sieh in Echtzeit einen realistischen Richtwert — ganz unverbindlich, bevor wir gemeinsam ins Detail gehen.</p>
        </div>

        {/* controls */}
        <div className="bc-controls">
          <div className="bc-field">
            <span className="bc-flabel">Teilnehmende</span>
            <div className="bc-stepper">
              <button className="bc-step-btn" onClick={() => step(-2)} aria-label="Weniger">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><path d="M5 12h14"/></svg>
              </button>
              <span className="bc-step-val">{participants}</span>
              <button className="bc-step-btn" onClick={() => step(2)} aria-label="Mehr">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><path d="M12 5v14M5 12h14"/></svg>
              </button>
            </div>
          </div>
          <div className="bc-field">
            <span className="bc-flabel">Veranstaltungstyp</span>
            <select className="bc-select" value={typeId} onChange={e => setTypeId(e.target.value)}>
              {BC_TYPES.map(t => <option key={t.id} value={t.id}>{t.label}</option>)}
            </select>
          </div>
          <div className="bc-field">
            <span className="bc-flabel">Preisniveau</span>
            <div className="bc-seg">
              {Object.keys(BC_RANGE).map(r => (
                <button key={r} className={'bc-seg-btn ' + (range === r ? 'on' : '')} onClick={() => setRange(r)}>{r}</button>
              ))}
            </div>
          </div>
          <div className="bc-field">
            <span className="bc-flabel">Dauer</span>
            <div className="bc-stepper" style={{ alignItems: 'center' }}>
              <span className="bc-step-val" style={{ minWidth: 0, fontSize: 16, fontWeight: 500 }}>
                {typeId === 'offsite' ? '2–3 Tage' : typeId === 'gesundheit' ? '1 Tag' : 'Halbtag+'}
              </span>
            </div>
          </div>
        </div>

        {/* services */}
        <div className="bc-services">
          <span className="bc-services-l">Gewünschte Services</span>
          {BC_SERVICES.map(s => (
            <button key={s.id} className={'bc-chip ' + (services.includes(s.id) ? 'on' : '')} onClick={() => toggleSvc(s.id)}>
              <span className="bc-chip-ic"><BcIcon name={s.icon} size={16} /></span>{s.label}
            </button>
          ))}
        </div>

        {/* result */}
        <div className="bc-result">
          <div className="bc-break">
            <div className="bc-break-h">Kostenaufschlüsselung</div>
            <div className="bc-break-list">
              {rows.map(row => (
                <div className="bc-break-row" key={row.cat}>
                  <span className="bc-break-dot" style={{ background: row.color }} />
                  <span className="bc-break-name">{row.label}</span>
                  <span className="bc-break-amt">€{fmt(row.amount)}</span>
                </div>
              ))}
            </div>
          </div>

          <BudgetDonut rows={rows} total={total} />

          <div className="bc-total">
            <div className="bc-total-h">Gesamtbudget · Richtwert</div>
            <div className="bc-total-num">€{fmt(total)}</div>
            <div className="bc-total-meta">Für {participants} Personen · {type.label}</div>
            <button className="fg-btn-ink lg bc-total-cta" onClick={request}>
              Unverbindliches Angebot anfragen
              <span className="fg-arrow"><ArrowGlyph /></span>
            </button>
            <p className="bc-total-note">Unverbindlicher Schätzwert. Das finale Angebot stellen wir nach kurzer Rücksprache zusammen — transparent, mit allen Posten.</p>
          </div>
        </div>
      </div>
    </section>
  );
}

// ---------- reusable photo tile ----------
function PhotoTile({ img, title, sub, onClick }) {
  return (
    <button className="iv-tile" onClick={onClick}>
      <span className="iv-tile-img" style={{ backgroundImage: `url('${window.A(img)}')` }} />
      <span className="iv-tile-scrim" />
      <span className="iv-tile-label">
        <span>
          <span className="iv-tile-t">{title}</span>
          {sub && <span className="iv-tile-sub" style={{ display: 'block' }}>{sub}</span>}
        </span>
        <span className="iv-tile-arrow"><BcIcon name="arrow" size={15} /></span>
      </span>
    </button>
  );
}

// Tiles mirror the first event types offered in the request form, so a click
// pre-selects that Veranstaltungstyp in the wizard.
const VART = [
  { t: 'Sommerfest',    sub: 'Der Abend unter freiem Himmel',  img: 'assets/imagery/event-summer-2.jpg', occasion: 'Sommerfest' },
  { t: 'Firmenturnier', sub: 'Pokale, Flights & Siegerehrung',  img: 'assets/imagery/event-corporate.jpg', occasion: 'Firmenturnier' },
  { t: 'Teamevent', sub: 'Spielerisch zusammenwachsen',     img: 'assets/imagery/event-team.jpg',     occasion: 'Teamevent' },
  { t: 'Kundenevent',   sub: 'Golf, Dinner & echte Gespräche',  img: 'assets/imagery/event-toast.jpg',    occasion: 'Kundenevent' },
];

// Golf experience levels — every team mixes beginners and regulars.
const EXP = [
  {
    level: 1, badge: 'Einsteiger', t: 'Erste Erfahrungen',
    b: 'Noch nie einen Schläger gehalten? Genau richtig. PGA-Coach, Leih-Ausrüstung und die ersten Schwünge auf der Range — locker, ohne Druck.',
    meta: ['PGA-Coach', 'Schläger gestellt', 'Range & Putting'],
    img: 'assets/imagery/tile-grass.jpg',
  },
  {
    level: 2, badge: 'Auffrischer', t: 'Schon mal gespielt',
    b: 'Ein paar Runden Erfahrung? Wir frischen den Schwung auf, gehen ins Kurzspiel und spielen danach gemeinsam entspannte 9 Loch.',
    meta: ['Kurzspiel-Training', '9 Loch', 'Gemischte Flights'],
    img: 'assets/imagery/event-summer.jpg',
  },
  {
    level: 3, badge: 'Fortgeschritten', t: 'Fortgeschrittene Golfer',
    b: 'Platzreife in der Tasche? Volle 18 Loch im Turnierformat mit Flights, Live-Scoring und Siegerehrung bei Sonnenuntergang.',
    meta: ['18 Loch', 'Live-Scoring', 'Siegerehrung'],
    img: 'assets/imagery/event-corporate.jpg',
  },
];

// ---------- page ----------
function IndividualPage() {
  const [wizard, setWizard] = useState(null);
  const RequestWizard = window.RequestWizard;
  const open = (mode, preset, intro) => setWizard({ mode: mode || 'full', preset: preset || null, intro: !!intro });

  return (
    <div data-screen-label="Individuelle Events">
      <MobileBar active="anfrage">
        <button className="ev-msearch ev-maction" onClick={() => open('full')}>
          <span>Anfrage starten</span>
          <span className="ev-maction-arrow"><ArrowGlyph size={14} /></span>
        </button>
      </MobileBar>

      {/* Hero */}
      <section className="ind-hero">
        <div className="ind-hero-photo" style={{ backgroundImage: `url('${window.A('assets/imagery/golf-island.jpg')}')` }}>
          <div className="ind-hero-scrim" />
          <div className="ind-hero-content">
            <div className="mk-hero-eyebrow">Individuelle Events</div>
            <h1 className="ind-hero-title">
              Euer Firmenevent — auf dem <span className="mk-italic">Golfplatz</span>.
            </h1>
            <p className="ind-hero-sub">
              Vom Teamevent bis zum Sommerfest, vom Turnier bis zur Incentive-Reise: Wir planen jeden Veranstaltungstyp
              auf dem passenden Platz. Sag uns kurz, was ihr vorhabt — wir machen den Rest.
            </p>
            <div className="mk-hero-ctas">
              <button className="fg-btn-ink lg" onClick={() => open('full')}>
                Anfrage starten
                <span className="fg-arrow"><ArrowGlyph /></span>
              </button>
              <a className="fg-btn-ghost-light" href="#budget"
                 onClick={(e) => { e.preventDefault(); document.getElementById('budget')?.scrollIntoView({ behavior: 'smooth' }); }}>
                Budget berechnen →
              </a>
            </div>
          </div>
        </div>
      </section>

      {/* Veranstaltungstyp */}
      <section className="iv-section">
        <div className="iv-head">
          <div className="mk-eyebrow">Veranstaltungstyp</div>
          <h2 className="mk-h2">Wählt euren Veranstaltungstyp</h2>
          <p className="mk-sub">Jeder Veranstaltungstyp findet auf dem Golfplatz statt — als Location, die garantiert in Erinnerung bleibt.</p>
        </div>
        <div className="iv-tiles">
          {VART.map(v => (
            <PhotoTile key={v.t} img={v.img} title={v.t} sub={v.sub}
              onClick={() => open('full', { occasion: v.occasion }, true)} />
          ))}
        </div>
      </section>

      {/* Budget-Rechner */}
      <BudgetRechner onRequest={(preset) => open('full', preset)} />

      {/* Golf-Erfahrung — every level welcome */}
      <section className="iv-section">
        <div className="iv-head">
          <div className="mk-eyebrow">Golf-Erfahrung</div>
          <h2 className="mk-h2">Vom ersten Schwung bis zur <span className="mk-italic">Stammrunde</span></h2>
          <p className="mk-sub">In jedem Team spielt jemand zum ersten Mal — und jemand seit Jahren. Wir stellen jedes Event so zusammen, dass alle Spaß haben, egal auf welchem Level.</p>
        </div>
        <div className="iv-exp-grid">
          {EXP.map(x => (
            <article className="iv-exp" key={x.t}>
              <div className="iv-exp-photo" style={{ backgroundImage: `url('${window.A(x.img)}')` }}>
                <span className="iv-exp-badge">{x.badge}</span>
              </div>
              <div className="iv-exp-body">
                <div className="iv-exp-dots" aria-hidden="true">
                  {[1, 2, 3].map(n => <span key={n} className={'iv-exp-dot ' + (n <= x.level ? 'on' : '')} />)}
                </div>
                <h3 className="iv-exp-t">{x.t}</h3>
                <p className="iv-exp-b">{x.b}</p>
                <div className="iv-exp-meta">
                  {x.meta.map(m => <span key={m} className="iv-exp-tag">{m}</span>)}
                </div>
              </div>
            </article>
          ))}
        </div>
      </section>

      {/* Nacht-Event — special offer */}
      <section className="ind-night">
        <div className="ind-night-photo" style={{ backgroundImage: `url('${window.A('assets/imagery/venue-meadow.jpg')}')` }} />
        <div className="ind-night-scrim" />
        <div className="ind-night-glow" />
        <div className="ind-night-inner">
          <div className="ind-night-badge">
            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
            Spezial · nur bei Firmengolf
          </div>
          <h2 className="ind-night-h">
            Das <span className="mk-italic">Nacht-Event</span>.<br />Wir machen die Nacht zum Tag.
          </h2>
          <p className="ind-night-sub">
            Wir leuchten einen ganzen Golfplatz aus und verwandeln ihn in eine Bühne — Flutlicht-Parcours,
            Live-DJ, Food &amp; Drinks unter freiem Himmel. Ein Firmenevent der etwas anderen Art,
            das euer Team garantiert nicht vergisst.
          </p>
          <div className="ind-night-points">
            <div className="ind-night-point"><span className="ind-night-n">01</span>Ausgeleuchteter Flutlicht-Parcours</div>
            <div className="ind-night-point"><span className="ind-night-n">02</span>Live-DJ, Licht &amp; Sound</div>
            <div className="ind-night-point"><span className="ind-night-n">03</span>Food, Drinks &amp; Bar bis tief in die Nacht</div>
          </div>
          <button className="fg-btn-ink lg ind-night-cta"
                  onClick={() => open('full', { occasion: 'Sommerfest', notes: 'Interesse am Nacht-Event (Flutlicht).', services: ['Flutlicht / Nacht-Event', 'DJ', 'Bar & Drinks'] })}>
            Nacht-Event anfragen
            <span className="fg-arrow"><ArrowGlyph /></span>
          </button>
        </div>
      </section>

      {/* Foto-CTA */}
      <section className="mk-section ind-launch" id="anfrage">
        <div className="ind-launch-card">
          <div className="ind-launch-bg" style={{ backgroundImage: `url('${window.A('assets/imagery/hero-forest.jpg')}')` }} />
          <div className="ind-launch-scrim" />
          <div className="ind-launch-content">
            <div className="mk-eyebrow" style={{ color: 'var(--fairway-300)' }}>Bereit?</div>
            <h2 className="ind-launch-h">Erzählt uns von eurem Event.</h2>
            <p className="ind-launch-p">
              Geführte Anfrage in fünf kurzen Schritten — ca. zwei Minuten, unverbindlich.
              Ein Ansprechpartner, ein Angebot, eine Rechnung.
            </p>
            <div className="ind-launch-ctas">
              <button className="fg-btn-ink lg" onClick={() => open('full')}
                       style={{ background: 'var(--paper-100)', color: 'var(--fairway-900)' }}>
                Anfrage starten
                <span className="fg-arrow" style={{ background: 'var(--fairway-200)' }}><ArrowGlyph /></span>
              </button>
              <button className="ind-launch-quick" onClick={() => open('quick')}>
                Schnell-Anfrage in 30 Sekunden →
              </button>
            </div>
          </div>
        </div>
      </section>

      {wizard && RequestWizard && (
        <RequestWizard mode={wizard.mode} preset={wizard.preset} intro={wizard.intro} onClose={() => setWizard(null)} />
      )}
    </div>
  );
}

window.IndividualPage = IndividualPage;
