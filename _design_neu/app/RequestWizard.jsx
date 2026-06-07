/* eslint-disable */
// =============================================================
// RequestWizard — the single, unified request surface.
// Used by every entry point:
//   • "Anfrage starten"            → full mode
//   • "Schnell-Anfrage in 30 Sek." → quick mode
//   • Veranstaltungstyp-Kacheln    → full mode + warm intro
//   • "Dieses Event anfragen"      → full mode + removable event context
//   • Budget-Rechner / Golfplatz   → full mode + preset
// Presets are pre-selected AND deactivatable (event card ✕, chips,
// service toggles). Exposes window.RequestWizard.
// =============================================================
(function () {
  const { useState, useEffect, useRef } = React;
  const A = window.A || ((p) => p);
  const Arrow = window.ArrowGlyph;
  const Check = window.CheckGlyph;

  // ---- date helpers (calendar) ----
  const MONTHS_DE = ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
  const WD_DE = ['Mo','Di','Mi','Do','Fr','Sa','So'];
  const WD_FULL = ['So','Mo','Di','Mi','Do','Fr','Sa'];
  const DAYPARTS = ['Ganztägig', 'Früher halber Tag', 'Mittag halber Tag', 'After Work'];
  const fmtNiceDate = (d) => WD_FULL[d.getDay()] + ', ' + d.getDate() + '. ' + MONTHS_DE[d.getMonth()] + ' ' + d.getFullYear();

  // ---- service catalogue ----
  const SERVICE_GROUPS = [
    { group: 'Sport & Programm', items: ['Golflehrer / Coaching', 'Schnupperkurs', 'Firmenturnier', 'Putting-Challenge'] },
    { group: 'Gastronomie',      items: ['Frühstück', 'Lunch', 'Abendessen', 'Bar & Drinks'] },
    { group: 'Technik & Show',   items: ['Eventtechnik: Bühne + Personal', 'DJ', 'Licht & Sound', 'Flutlicht / Nacht-Event'] },
    { group: 'Foto & Content',   items: ['Fotograf', 'Content-Team für Social'] },
    { group: 'Branding & Merch', items: ['Branding & Banner', 'Individuelle Artikel', 'Pokale & Preise'] },
    { group: 'Logistik',         items: ['Meetingraum', 'Shuttle / Transport', 'Übernachtung', 'Schlechtwetter-Alternative'] },
  ];

  // ---- canonical occasion list + synonym normalisation ----
  const OCCASIONS = ['Schnupperkurs', 'Teamevent', 'Firmenturnier', 'Kundenevent', 'Networking',
    'After-Work Golf', 'Sommerfest', 'Offsite & Incentive', 'Gesundheitstag', 'Charity-Event',
    'Nacht-Event', 'Etwas anderes'];
  function normalizeOccasion(v) {
    if (!v) return '';
    const s = String(v).toLowerCase();
    if (/team/.test(s)) return 'Teamevent';
    if (/turnier|cup/.test(s)) return 'Firmenturnier';
    if (/kunde/.test(s)) return 'Kundenevent';
    if (/network/.test(s)) return 'Networking';
    if (/after.?work/.test(s)) return 'After-Work Golf';
    if (/schnupper/.test(s)) return 'Schnupperkurs';
    if (/nacht|flutlicht/.test(s)) return 'Nacht-Event';
    if (/sommerfest|fest/.test(s)) return 'Sommerfest';
    if (/offsite|incentive|strateg/.test(s)) return 'Offsite & Incentive';
    if (/gesundheit|health/.test(s)) return 'Gesundheitstag';
    if (/charity|spende/.test(s)) return 'Charity-Event';
    // already canonical?
    const exact = OCCASIONS.find(o => o.toLowerCase() === s);
    return exact || 'Etwas anderes';
  }

  // ---- map an event's "includes"/"tags" to pre-selected service toggles ----
  const SVC_RULES = [
    [/coach|pga|training|schwung|briefing|aufwärm/, 'Golflehrer / Coaching'],
    [/putting/, 'Putting-Challenge'],
    [/scramble|turnier|shotgun|scoring|flight|leaderboard|18 loch|9 loch/, 'Firmenturnier'],
    [/frühstück/, 'Frühstück'],
    [/mittag|lunch/, 'Lunch'],
    [/dinner|abendessen|tasting|sommelier|private dining/, 'Abendessen'],
    [/aperitif|\bbar\b|drink|sekt|wein|getränk|empfang/, 'Bar & Drinks'],
    [/flutlicht|nacht/, 'Flutlicht / Nacht-Event'],
    [/\bdj\b/, 'DJ'],
    [/bühne|eventtechnik|licht & sound/, 'Eventtechnik: Bühne + Personal'],
    [/foto/, 'Fotograf'],
    [/pokal|troph|sachpreis|\bpreise\b/, 'Pokale & Preise'],
    [/meeting|breakout|workshop|\braum\b|räume/, 'Meetingraum'],
    [/shuttle|transfer|transport|anfahrt/, 'Shuttle / Transport'],
    [/übernach|hotel|nächte|schloss/, 'Übernachtung'],
    [/indoor-backup|schlechtwetter|backup/, 'Schlechtwetter-Alternative'],
  ];
  function mapIncludesToServices(arr) {
    const hay = (arr || []).join(' · ').toLowerCase();
    const out = [];
    SVC_RULES.forEach(([re, svc]) => { if (re.test(hay) && !out.includes(svc)) out.push(svc); });
    return out;
  }

  function mapDuration(d) {
    if (!d) return 'Halbtag';
    const s = d.toLowerCase();
    if (/nächte|tage|mehrtäg/.test(s)) return /3|mehr/.test(s) ? 'Mehrtägig' : '2 Tage';
    if (/ganzt/.test(s)) return 'Ganztag';
    return 'Halbtag';
  }

  const FULL_STEPS = ['Anlass', 'Eckdaten', 'Leistungen', 'Budget', 'Kontakt'];
  const CONTACT = { name: 'Jonas Bredow', role: 'Head of Events', photo: 'assets/imagery/avatar-2.jpg' };
  const OCC_PHRASE = { 'Offsite & Incentive': 'Offsite', 'Etwas anderes': 'Firmenevent' };

  function blankForm(preset) {
    const base = {
      eventRef: null,
      occasion: '', goal: '',
      size: '40', region: 'Süd', duration: 'Halbtag', flex: 'flexibel',
      wishes: [{ date: null, daypart: 'Ganztägig' }],
      services: [],
      budget: '€10.000 – €20.000',
      company: '', city: '', website: '',
      firstName: '', lastName: '', role: '', email: '', phone: '',
      contactPref: 'E-Mail', notes: '',
    };
    const p = preset || {};
    // derive from an event link first…
    if (p.eventRef) {
      const e = p.eventRef;
      base.occasion = normalizeOccasion(e.formatLabel);
      if (e.region) base.region = e.region;
      if (e.groupMin) base.size = String(e.groupMin);
      if (e.duration) base.duration = mapDuration(e.duration);
      const svc = mapIncludesToServices([...(e.includes || []), ...(e.tags || [])]);
      if (svc.length) base.services = svc;
    } else {
      // sensible neutral defaults for a generic start
      base.services = [];
    }
    const merged = Object.assign(base, p);
    merged.occasion = p.occasion ? normalizeOccasion(p.occasion) : merged.occasion;
    if (!merged.wishes || !merged.wishes.length) merged.wishes = [{ date: null, daypart: 'Ganztägig' }];
    return merged;
  }

  // ---- small controls ----
  function Toggle({ on, onClick, children }) {
    return (
      <button type="button" className={'ind-toggle ' + (on ? 'on' : '')} onClick={onClick}>
        <span className="ind-toggle-dot" /><span>{children}</span>
      </button>
    );
  }
  function Chips({ options, value, onChange }) {
    return (
      <div className="ind-chip-group">
        {options.map(o => (
          <button key={o} type="button" className={'ind-pchip ' + (value === o ? 'on' : '')}
                  onClick={() => onChange(o)}>{o}</button>
        ))}
      </div>
    );
  }
  function L({ children, required, hint }) {
    return (
      <span className="ind-flabel">{children}
        {required && <span className="ind-required">*</span>}
        {hint && <span className="ind-flabel-hint">{hint}</span>}
      </span>
    );
  }

  // ---- daypart dropdown ----
  function DaypartSelect({ value, onChange }) {
    const [open, setOpen] = useState(false);
    const ref = useRef();
    useEffect(() => {
      if (!open) return;
      const onDoc = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
      document.addEventListener('mousedown', onDoc);
      return () => document.removeEventListener('mousedown', onDoc);
    }, [open]);
    return (
      <div className="fg-dp" ref={ref}>
        <button type="button" className={'fg-input fg-dp-btn ' + (open ? 'open' : '')} onClick={() => setOpen(o => !o)}>
          <span>{value}</span>
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M6 9l6 6 6-6"/></svg>
        </button>
        {open && (
          <ul className="fg-dp-menu" role="listbox">
            {DAYPARTS.map(d => (
              <li key={d} role="option" aria-selected={d === value}
                  className={'fg-dp-item ' + (d === value ? 'on' : '')}
                  onClick={() => { onChange(d); setOpen(false); }}>
                <span>{d}</span>
                {d === value && <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M20 6L9 17l-5-5"/></svg>}
              </li>
            ))}
          </ul>
        )}
      </div>
    );
  }

  // ---- calendar date picker ----
  function DatePicker({ value, onPick, placeholder }) {
    const [open, setOpen] = useState(false);
    const today = new Date(); today.setHours(0, 0, 0, 0);
    const [view, setView] = useState(new Date(today.getFullYear(), today.getMonth(), 1));
    const ref = useRef();
    useEffect(() => {
      if (!open) return;
      const onDoc = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
      document.addEventListener('mousedown', onDoc);
      return () => document.removeEventListener('mousedown', onDoc);
    }, [open]);
    const y = view.getFullYear(), m = view.getMonth();
    const startDow = (new Date(y, m, 1).getDay() + 6) % 7;
    const daysInMonth = new Date(y, m + 1, 0).getDate();
    const cells = [];
    for (let i = 0; i < startDow; i++) cells.push(null);
    for (let d = 1; d <= daysInMonth; d++) cells.push(new Date(y, m, d));
    const sameDay = (a, b) => a && b && a.getTime() === b.getTime();
    const selDate = value ? value.dateObj : null;
    return (
      <div className="fg-datewrap" ref={ref}>
        <button type="button" className={'fg-input fg-datebtn ' + (value ? '' : 'muted')} onClick={() => setOpen(o => !o)}>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"><rect x="3" y="4.5" width="18" height="16" rx="2.5"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4"/></svg>
          <span>{value ? value.label : (placeholder || 'Datum wählen')}</span>
        </button>
        {open && (
          <div className="fg-cal">
            <div className="fg-cal-head">
              <button type="button" className="fg-cal-nav" onClick={() => setView(new Date(y, m - 1, 1))}
                      disabled={y === today.getFullYear() && m === today.getMonth()} aria-label="Vorheriger Monat">‹</button>
              <span className="fg-cal-title">{MONTHS_DE[m]} {y}</span>
              <button type="button" className="fg-cal-nav" onClick={() => setView(new Date(y, m + 1, 1))} aria-label="Nächster Monat">›</button>
            </div>
            <div className="fg-cal-grid">
              {WD_DE.map(w => <span key={w} className="fg-cal-wd">{w}</span>)}
              {cells.map((d, i) => d ? (
                <button key={i} type="button"
                        className={'fg-cal-day ' + (sameDay(d, selDate) ? 'on ' : '') + (sameDay(d, today) ? 'today ' : '')}
                        disabled={d < today}
                        onClick={() => { onPick({ dateObj: d, label: fmtNiceDate(d), iso: d.toISOString().slice(0, 10) }); setOpen(false); }}>
                  {d.getDate()}
                </button>
              ) : <span key={i} />)}
            </div>
          </div>
        )}
      </div>
    );
  }

  // ---- the removable event-context card ----
  function EventCtx({ form, onRemove, compact }) {
    const e = form.eventRef;
    if (!e) return null;
    return (
      <div className={'rw-ctx ' + (compact ? 'compact' : '')}>
        {e.heroImage && <span className="rw-ctx-img" style={{ backgroundImage: `url('${A(e.heroImage)}')` }} />}
        <div className="rw-ctx-body">
          <div className="rw-ctx-eyebrow">Deine Anfrage zu diesem Event</div>
          <div className="rw-ctx-title">{e.title}</div>
          <div className="rw-ctx-meta">
            {e.formatLabel} · {e.venue}{e.priceLabel ? ' · ' + e.priceLabel : ''}
          </div>
        </div>
        <button type="button" className="rw-ctx-x" onClick={onRemove} title="Ohne Event-Bezug anfragen">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          Lösen
        </button>
      </div>
    );
  }

  function RequestWizard({ preset, mode: initialMode, intro, onClose }) {
    const [mode, setMode] = useState(initialMode || 'full');
    const [phase, setPhase] = useState(intro && (initialMode || 'full') !== 'quick' ? 'intro' : 'form');
    const [step, setStep] = useState(0);
    const [sent, setSent] = useState(false);
    const [form, setForm] = useState(() => blankForm(preset));
    const set = (k, v) => setForm(s => ({ ...s, [k]: v }));
    const toggleSvc = (n) => setForm(s => ({ ...s, services: s.services.includes(n) ? s.services.filter(x => x !== n) : [...s.services, n] }));

    // wish-date helpers
    const setWish = (i, patch) => setForm(s => ({ ...s, wishes: s.wishes.map((w, j) => j === i ? { ...w, ...patch } : w) }));
    const addWish = () => setForm(s => s.wishes.length < 3 ? { ...s, wishes: [...s.wishes, { date: null, daypart: 'Ganztägig' }] } : s);
    const removeWish = (i) => setForm(s => ({ ...s, wishes: s.wishes.filter((_, j) => j !== i) }));

    const ev = form.eventRef;
    const detachEvent = () => set('eventRef', null);

    // size clamp when tied to an event
    const setSize = (v) => {
      let n = v;
      if (ev) { const num = parseInt(v, 10); if (!isNaN(num)) n = String(Math.max(ev.groupMin || 1, Math.min(ev.groupMax || 999, num))); }
      set('size', n);
    };

    useEffect(() => {
      const prev = document.body.style.overflow;
      document.body.style.overflow = 'hidden';
      const onKey = (e) => { if (e.key === 'Escape') onClose(); };
      window.addEventListener('keydown', onKey);
      return () => { document.body.style.overflow = prev; window.removeEventListener('keydown', onKey); };
    }, []);

    const total = FULL_STEPS.length;
    const isLast = step === total - 1;

    const stepValid = () => {
      if (mode === 'quick') return form.firstName && form.email && form.occasion;
      if (step === 0) return !!form.occasion;
      if (step === 4) return form.company && form.firstName && form.lastName && form.email;
      return true;
    };

    const next = () => { if (isLast) submit(); else setStep(s => s + 1); };
    const submit = () => setSent(true);

    const wishLabels = () => form.wishes.filter(w => w.date).map(w => w.date.label + ' · ' + w.daypart);
    const ref = ev ? ('FG-26-' + ev.id.toUpperCase() + '-' + String(Math.floor(Math.random() * 9000) + 1000))
                   : ('FG-26-' + Math.floor(100000 + Math.random() * 900000));

    // ---------- SUCCESS ----------
    if (sent) {
      return (
        <div className="rw-overlay" role="dialog" aria-modal="true" aria-label="Anfrage gesendet">
          <Top onClose={onClose} mode={mode} setMode={null} hideShortcut />
          <div className="rw-stage">
            <div className="rw-success">
              <div className="fg-success-mark"><Check size={22} /></div>
              <div className="mk-eyebrow">Anfrage eingegangen</div>
              <h1 className="rw-success-h">Danke — deine Anfrage ist angekommen.</h1>
              <p className="rw-success-p">
                {ev
                  ? <>Eure Anfrage geht an <strong>{ev.venue}</strong> und an uns. Der Platz stimmt eure Wunschtermine
                      intern ab — sobald ein Termin bestätigt ist, koordinieren wir alles Weitere und melden uns
                      innerhalb von 48 Stunden. Eine Bestätigung ist an <strong>{form.email || '—'}</strong> unterwegs.</>
                  : <>Deine Anfrage liegt jetzt bei uns — bei einem individuellen Event übernehmen wir die Planung
                      persönlich und stimmen den passenden Golfplatz für dich ab. Eine Bestätigung ist gerade per Mail
                      an <strong>{form.email || '—'}</strong> unterwegs.</>}
              </p>
              <div className="rw-receipt">
                {ev && <div><span>Event</span><span>{ev.title}</span></div>}
                {ev && <div><span>Platz</span><span>{ev.venue}</span></div>}
                <div><span>Anlass</span><span>{form.occasion || '—'}</span></div>
                {wishLabels().length > 0 && <div><span>Wunschtermine</span><span>{wishLabels().join(' · ')}</span></div>}
                <div><span>Gruppe</span><span>{form.size} Personen</span></div>
                <div><span>Unternehmen</span><span>{form.company || '—'}</span></div>
                <div><span>Status</span><span><span className="ob-pill-status">In Bearbeitung</span></span></div>
                <div><span>Vorgangs-Nr.</span><span className="mono">{ref}</span></div>
              </div>
              <div className="rw-success-ctas">
                <button className="fg-btn-brand" onClick={onClose}>Schließen</button>
                <a className="fg-btn-ghost" href="#/events" onClick={(e) => { onClose(); go('#/events', e); }}>
                  Veranstaltungstypen ansehen <Arrow size={12} />
                </a>
              </div>
            </div>
          </div>
        </div>
      );
    }

    // ---------- QUICK MODE ----------
    if (mode === 'quick') {
      const valid = stepValid();
      return (
        <div className="rw-overlay" role="dialog" aria-modal="true" aria-label="Schnell-Anfrage">
          <Top onClose={onClose} mode={mode} setMode={setMode} />
          <div className="rw-stage">
            <div className="rw-screen">
              <div className="rw-eyebrow">Schnell-Anfrage · 30 Sekunden</div>
              <h1 className="rw-h">Das Wichtigste — wir klären den Rest persönlich.</h1>
              <p className="rw-lead">Du willst nicht durch alle Schritte? Völlig okay. Gib uns die Basics, wir melden uns mit Rückfragen.</p>

              <div className="rw-form">
                {ev && <EventCtx form={form} onRemove={detachEvent} />}
                <div className="rw-field">
                  <L required>Anlass</L>
                  <Chips options={['Sommerfest', 'Firmenturnier', 'Teamevent', 'Kundenevent', 'Offsite & Incentive', 'Nacht-Event', 'Etwas anderes']}
                         value={form.occasion} onChange={(v) => set('occasion', v)} />
                </div>
                <div className="rw-row">
                  <div className="rw-field">
                    <L>Teilnehmerzahl</L>
                    <div className="ind-input-row">
                      <input className="fg-input" type="number" min="1" value={form.size} onChange={(e) => setSize(e.target.value)} placeholder="40" />
                      <span className="ind-input-suffix">Personen</span>
                    </div>
                  </div>
                  <div className="rw-field">
                    <L>Gewünschter Zeitraum</L>
                    <input className="fg-input" value={form.when || ''} onChange={(e) => set('when', e.target.value)} placeholder="z.B. Juli 2026" />
                  </div>
                </div>
                <div className="rw-row">
                  <div className="rw-field">
                    <L required>Vor- &amp; Nachname</L>
                    <input className="fg-input" value={form.firstName}
                           onChange={(e) => { const v = e.target.value; const [f, ...r] = v.split(' '); set('firstName', f); set('lastName', r.join(' ')); }}
                           placeholder="Vor- und Nachname" />
                  </div>
                  <div className="rw-field">
                    <L required>E-Mail</L>
                    <input className="fg-input" type="email" value={form.email} onChange={(e) => set('email', e.target.value)} placeholder="name@firma.de" />
                  </div>
                </div>
                <div className="rw-field">
                  <L>Firma</L>
                  <input className="fg-input" value={form.company} onChange={(e) => set('company', e.target.value)} placeholder="Musterfirma GmbH" />
                </div>
                <div className="rw-field">
                  <L>Was habt ihr vor?</L>
                  <textarea className="fg-input" rows={3} value={form.notes} onChange={(e) => set('notes', e.target.value)}
                            placeholder="Ein, zwei Sätze zu Ziel, Stimmung, Wünschen." />
                </div>
              </div>
            </div>
          </div>
          <div className="rw-foot rw-foot-quick">
            <button className="rw-switch" onClick={() => { setMode('full'); setStep(0); }}>Lieber ausführlich anfragen</button>
            <button className="fg-btn-brand lg" disabled={!valid} onClick={submit}>
              Anfrage senden <span className="fg-arrow"><Arrow /></span>
            </button>
          </div>
        </div>
      );
    }

    // ---------- WARM INTRO (from a Veranstaltungstyp tile) ----------
    if (phase === 'intro' && mode === 'full') {
      const occ = OCC_PHRASE[form.occasion] || form.occasion || 'Firmenevent';
      return (
        <div className="rw-overlay" role="dialog" aria-modal="true" aria-label="Anfrage starten">
          <Top onClose={onClose} mode={mode} setMode={null} hideShortcut />
          <div className="rw-stage">
            <div className="rw-screen rw-intro">
              <div className="rw-eyebrow">Schön, dass du da bist</div>
              <h1 className="rw-h">Toll — ihr plant ein <span className="mk-italic">{occ}</span> für euer Team.</h1>
              <p className="rw-lead">Lass uns kurz ein paar Infos sammeln. Danach meldet sich {CONTACT.name} persönlich bei dir — meist noch am selben Werktag, mit ersten Ideen und einem Richtpreis.</p>
              <div className="rw-intro-contact">
                <img src={A(CONTACT.photo)} alt={CONTACT.name} />
                <div>
                  <div className="rw-intro-c-name">{CONTACT.name}</div>
                  <div className="rw-intro-c-role">{CONTACT.role} · Firmengolf</div>
                  <div className="rw-intro-c-note">„Ich kümmere mich persönlich um deine Anfrage.“</div>
                </div>
              </div>
              <ul className="rw-intro-steps">
                <li>Ein paar Eckdaten — keine zwei Minuten</li>
                <li>Persönliche Rückmeldung statt Funnel</li>
                <li>Unverbindlich und kostenlos</li>
              </ul>
            </div>
          </div>
          <div className="rw-foot rw-foot-quick">
            <button className="rw-switch" onClick={onClose}>Abbrechen</button>
            <button className="fg-btn-brand lg" onClick={() => setPhase('form')}>
              Los geht's <span className="fg-arrow"><Arrow /></span>
            </button>
          </div>
        </div>
      );
    }

    // ---------- FULL MODE ----------
    const valid = stepValid();

    return (
      <div className="rw-overlay" role="dialog" aria-modal="true" aria-label="Event anfragen">
        <Top onClose={onClose} mode={mode} setMode={setMode} />

        <div className="rw-stage">
          <div className="rw-screen" key={step}>
            {step === 0 && (
              <>
                <div className="rw-eyebrow">Schritt 1 · {FULL_STEPS[0]}</div>
                <h1 className="rw-h">{ev ? 'Schön — ihr interessiert euch für dieses Event.' : "Worum geht's bei eurem Event?"}</h1>
                <p className="rw-lead">{ev ? 'Wir haben schon ein paar Dinge für euch vorausgefüllt. Passt alles an, wie ihr möchtet.' : 'Wähl den nächstpassenden Anlass — wir verfeinern alles im Gespräch.'}</p>
                <div className="rw-form">
                  {ev && <EventCtx form={form} onRemove={detachEvent} />}
                  <div className="rw-field">
                    {ev && <L>Anlass</L>}
                    <Chips options={OCCASIONS} value={form.occasion} onChange={(v) => set('occasion', v)} />
                  </div>
                  <div className="rw-field">
                    <L hint="Ein Satz reicht">Was wollt ihr erreichen?</L>
                    <input className="fg-input" value={form.goal} onChange={(e) => set('goal', e.target.value)}
                           placeholder="z.B. Team zusammenbringen · Kunden begeistern · Mitarbeitende belohnen" />
                  </div>
                </div>
              </>
            )}

            {step === 1 && (
              <>
                <div className="rw-eyebrow">Schritt 2 · {FULL_STEPS[1]}</div>
                <h1 className="rw-h">Wann, wo und mit wie vielen?</h1>
                <p className="rw-lead">Genau müssen die Angaben jetzt nicht sein — wir prüfen die Verfügbarkeit gemeinsam.</p>
                <div className="rw-form">
                  {ev && <EventCtx form={form} onRemove={detachEvent} compact />}
                  <div className="rw-row">
                    <div className="rw-field">
                      <L required>Teilnehmerzahl</L>
                      <div className="ind-input-row">
                        <input className="fg-input" type="number" min="1" value={form.size} onChange={(e) => setSize(e.target.value)} placeholder="40" />
                        <span className="ind-input-suffix">Personen</span>
                      </div>
                      {ev && <span className="rw-help">min. {ev.groupMin} · max. {ev.groupMax} an diesem Platz</span>}
                    </div>
                    <div className="rw-field"><L>Dauer</L>
                      <Chips options={['Halbtag', 'Ganztag', '2 Tage', 'Mehrtägig']} value={form.duration} onChange={(v) => set('duration', v)} />
                    </div>
                  </div>

                  <div className="rw-field">
                    <L hint={ev ? 'Der Platz prüft alle Wünsche' : 'Bis zu drei — wir prüfen alle'}>Wunschtermine</L>
                    <div className="fg-wishes">
                      {form.wishes.map((w, i) => (
                        <div className="fg-wish" key={i}>
                          <span className="fg-wish-n">{i + 1}</span>
                          <DatePicker value={w.date} onPick={(v) => setWish(i, { date: v })} placeholder="Datum wählen" />
                          <DaypartSelect value={w.daypart} onChange={(v) => setWish(i, { daypart: v })} />
                          {form.wishes.length > 1 && (
                            <button className="fg-wish-x" type="button" onClick={() => removeWish(i)} aria-label="Termin entfernen">
                              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            </button>
                          )}
                        </div>
                      ))}
                    </div>
                    {form.wishes.length < 3 && <button className="fg-addwish" type="button" onClick={addWish}>+ Terminwunsch hinzufügen</button>}
                  </div>

                  <div className="rw-row">
                    <div className="rw-field"><L>Wie flexibel beim Datum?</L>
                      <Chips options={['fix', '± 1 Woche', 'flexibel', 'noch offen']} value={form.flex} onChange={(v) => set('flex', v)} />
                    </div>
                    <div className="rw-field"><L>Region</L>
                      <Chips options={['Ganz Deutschland', 'Nord', 'Ost', 'Süd', 'West', 'International']} value={form.region} onChange={(v) => set('region', v)} />
                    </div>
                  </div>
                </div>
              </>
            )}

            {step === 2 && (
              <>
                <div className="rw-eyebrow">Schritt 3 · {FULL_STEPS[2]}</div>
                <h1 className="rw-h">Was soll dabei sein?</h1>
                <p className="rw-lead">{ev ? 'Aus diesem Event haben wir schon einiges vorausgewählt — ändert es nach Belieben.' : 'Mehrfachauswahl — alles kombinierbar. Nur ein Startpunkt, festlegen musst du dich nicht.'}</p>
                <div className="rw-form">
                  {ev && <EventCtx form={form} onRemove={detachEvent} compact />}
                  <div className="ind-svc-pick">
                    {SERVICE_GROUPS.map(g => (
                      <div className="ind-svc-pick-group" key={g.group}>
                        <div className="ind-svc-pick-h">{g.group}</div>
                        <div className="ind-toggles">
                          {g.items.map(it => <Toggle key={it} on={form.services.includes(it)} onClick={() => toggleSvc(it)}>{it}</Toggle>)}
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              </>
            )}

            {step === 3 && (
              <>
                <div className="rw-eyebrow">Schritt 4 · {FULL_STEPS[3]}</div>
                <h1 className="rw-h">Was wäre euer Budget-Rahmen?</h1>
                <p className="rw-lead">Nur eine Richtschnur — wir verhandeln nicht nach oben.</p>
                <div className="rw-form">
                  <div className="ind-budget-grid">
                    {[
                      { v: 'Unter €5.000', hint: 'Kleinere Halbtags-Formate' },
                      { v: '€5.000 – €10.000', hint: 'Eintägig für 20–40 Gäste' },
                      { v: '€10.000 – €20.000', hint: 'Premium-Eintages-Events' },
                      { v: '€20.000 – €50.000', hint: 'Mehrtägig oder größere Gruppen' },
                      { v: 'Über €50.000', hint: 'Incentive-Reisen, Großformate' },
                      { v: 'Noch unklar', hint: 'Wir gehen es gemeinsam durch' },
                    ].map(o => (
                      <button key={o.v} type="button" className={'ind-budget-card ' + (form.budget === o.v ? 'on' : '')} onClick={() => set('budget', o.v)}>
                        <span className="ind-budget-v">{o.v}</span><span className="ind-budget-h">{o.hint}</span>
                      </button>
                    ))}
                  </div>
                  <div className="rw-field" style={{ marginTop: 8 }}>
                    <L>Was ist euch wichtig?</L>
                    <textarea className="fg-input" rows={4} value={form.notes} onChange={(e) => set('notes', e.target.value)}
                              placeholder="Stimmung, Hintergrund, besondere Wünsche — alles was hilft." />
                  </div>
                </div>
              </>
            )}

            {step === 4 && (
              <>
                <div className="rw-eyebrow">Schritt 5 · {FULL_STEPS[4]}</div>
                <h1 className="rw-h">Wer seid ihr — und wie erreichen wir dich?</h1>
                <p className="rw-lead">Letzter Schritt. Danach melden wir uns innerhalb eines Werktags.</p>
                <div className="rw-form">
                  <div className="rw-row">
                    <div className="rw-field"><L required>Unternehmen</L>
                      <input className="fg-input" value={form.company} onChange={(e) => set('company', e.target.value)} placeholder="Musterfirma GmbH" /></div>
                    <div className="rw-field"><L>Ort</L>
                      <input className="fg-input" value={form.city} onChange={(e) => set('city', e.target.value)} placeholder="München" /></div>
                  </div>
                  <div className="rw-row">
                    <div className="rw-field"><L required>Vorname</L>
                      <input className="fg-input" value={form.firstName} onChange={(e) => set('firstName', e.target.value)} placeholder="Vorname" /></div>
                    <div className="rw-field"><L required>Nachname</L>
                      <input className="fg-input" value={form.lastName} onChange={(e) => set('lastName', e.target.value)} placeholder="Nachname" /></div>
                  </div>
                  <div className="rw-row">
                    <div className="rw-field"><L required>E-Mail</L>
                      <input className="fg-input" type="email" value={form.email} onChange={(e) => set('email', e.target.value)} placeholder="name@firma.de" /></div>
                    <div className="rw-field"><L>Telefon</L>
                      <input className="fg-input" type="tel" value={form.phone} onChange={(e) => set('phone', e.target.value)} placeholder="+49 …" /></div>
                  </div>
                  <div className="rw-field"><L>Bevorzugte Kontaktart</L>
                    <Chips options={['E-Mail', 'Telefon', 'Egal']} value={form.contactPref} onChange={(v) => set('contactPref', v)} />
                  </div>
                  <label className="ind-consent">
                    <input type="checkbox" defaultChecked required />
                    <span>Ich stimme der Verarbeitung meiner Daten zur Bearbeitung der Anfrage gemäß
                      {' '}<a href="#/datenschutz" onClick={(e) => { onClose(); go('#/datenschutz', e); }}>Datenschutzerklärung</a> zu.</span>
                  </label>
                </div>
              </>
            )}
          </div>
        </div>

        <div className="rw-foot">
          <div className="rw-foot-inner">
            <div className="rw-steps" aria-label={'Schritt ' + (step + 1) + ' von ' + total}>
              {FULL_STEPS.map((label, i) => (
                <div key={i} className={'rw-step ' + (i < step ? 'done ' : '') + (i === step ? 'on' : '')}>
                  <span className="rw-step-bar" />
                  <span className="rw-step-label">{label}</span>
                </div>
              ))}
            </div>
            <div className="rw-stepcount">Schritt {step + 1} von {total} · {FULL_STEPS[step]}</div>
            <div className="rw-nav">
              <button className="rw-back" onClick={() => step === 0 ? onClose() : setStep(s => s - 1)}>
                {step === 0 ? 'Abbrechen' : '← Zurück'}
              </button>
              <button className="fg-btn-brand lg" disabled={!valid} onClick={next}>
                {isLast ? 'Anfrage senden' : 'Weiter'} <span className="fg-arrow"><Arrow /></span>
              </button>
            </div>
          </div>
        </div>
      </div>
    );
  }

  function Top({ onClose, mode, setMode, hideShortcut }) {
    return (
      <header className="rw-top">
        <div className="rw-top-brand">
          <img src={A('assets/logo/firmengolf-wordmark.png')} alt="Firmengolf" />
          <span className="rw-top-title">Event anfragen</span>
        </div>
        <div className="rw-top-actions">
          {!hideShortcut && setMode && (
            <button className="rw-shortcut" onClick={() => setMode(mode === 'quick' ? 'full' : 'quick')}>
              {mode === 'quick' ? 'Ausführliche Anfrage' : 'Schnell-Anfrage in 30 Sek.'}
            </button>
          )}
          <button className="rw-close" onClick={onClose} aria-label="Schließen">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>
      </header>
    );
  }

  window.RequestWizard = RequestWizard;
})();
