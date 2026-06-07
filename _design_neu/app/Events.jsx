/* eslint-disable */
// =============================================================
// Events page — a real, lovingly-built marketplace.
// Working filters (format · region · group size), sticky category
// rail, active-filter pills, a curated "Beliebt diese Saison" rail
// that shows only when unfiltered, and richer event cards.
// URL: #/events  or  #/events?format=schnupperkurs
// =============================================================
// (asset helper provided as window.A)
var { useState, useMemo, useEffect, useRef } = React;

const GROUP_BANDS = [
  { id: 'any', label: 'Jede Größe' },
  { id: 's',   label: 'Bis 12',  min: 0,  max: 12 },
  { id: 'm',   label: '12–30',   min: 12, max: 30 },
  { id: 'l',   label: '30–60',   min: 30, max: 60 },
  { id: 'xl',  label: '60+',     min: 60, max: 9999 },
];

// ---------- small icons ----------
function EvIcon({ name, size = 16 }) {
  const p = {
    grid:   <><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></>,
    pin:    <><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></>,
    users:  <><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/></>,
    clock:  <><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></>,
    search: <><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></>,
    close:  <><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></>,
    spark:  <><path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z"/></>,
    locate: <><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/></>,
  };
  return (
    <svg viewBox="0 0 24 24" width={size} height={size} fill="none" stroke="currentColor"
         strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      {p[name]}
    </svg>
  );
}

// ---------- popover dropdown cell ----------
function SearchCell({ icon, label, value, muted, options, onSelect, wide }) {
  const [open, setOpen] = useState(false);
  return (
    <div className={'ev-cell ' + (open ? 'open ' : '') + (wide ? 'wide ' : '')}>
      <button type="button" className="ev-cell-btn" onClick={() => setOpen(o => !o)}>
        <span className="ev-cell-ic"><EvIcon name={icon} /></span>
        <span className="ev-cell-text">
          <span className="ev-cell-label">{label}</span>
          <span className={'ev-cell-value ' + (muted ? 'muted' : '')}>{value}</span>
        </span>
      </button>
      {open && (
        <>
          <div className="ev-pop-backdrop" onClick={() => setOpen(false)} />
          <div className="ev-pop">
            {options.map(o => (
              <button key={o.id} type="button"
                      className={'ev-pop-item ' + (o.active ? 'active' : '')}
                      onClick={() => { onSelect(o.id); setOpen(false); }}>
                <span>{o.label}</span>
                {o.active && <EvIcon name="spark" size={14} />}
              </button>
            ))}
          </div>
        </>
      )}
    </div>
  );
}

// ---------- location field (PLZ / city autocomplete + GPS + radius) ----------
function LocationCell({ origin, onSet, onClear, radius, setRadius }) {
  const [open, setOpen] = useState(false);
  const [q, setQ] = useState('');
  const ref = useRef();
  const sugg = window.GEO ? window.GEO.suggest(q, 6) : [];

  useEffect(() => {
    if (!open) return;
    const onDoc = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
    document.addEventListener('mousedown', onDoc);
    return () => document.removeEventListener('mousedown', onDoc);
  }, [open]);

  const pick = (text) => {
    const r = window.GEO.resolve(text);
    if (r) { onSet({ lat: r.lat, lng: r.lng, label: r.label, source: 'manual' }); setQ(''); }
  };
  const useGPS = () => {
    if (!navigator.geolocation) return;
    onSet({ pending: true, label: 'Suche Standort…' });
    navigator.geolocation.getCurrentPosition(
      (pos) => onSet({ lat: pos.coords.latitude, lng: pos.coords.longitude, label: 'In deiner Nähe', source: 'gps' }),
      () => onSet(null),
      { enableHighAccuracy: false, timeout: 8000 }
    );
  };

  const originSet = origin && !origin.pending && origin.lat != null;
  const value = origin ? (originSet ? origin.label + ' · ' + radius + ' km' : origin.label) : 'Ort oder PLZ';

  return (
    <div className={'ev-cell ev-cell-loc ' + (open ? 'open ' : '')} ref={ref}>
      <button type="button" className="ev-cell-btn" onClick={() => setOpen(o => !o)}>
        <span className="ev-cell-ic"><EvIcon name="pin" /></span>
        <span className="ev-cell-text">
          <span className="ev-cell-label">Wo</span>
          <span className={'ev-cell-value ' + (origin ? '' : 'muted')}>{value}</span>
        </span>
        {origin && !origin.pending && (
          <span className="ev-cell-x" onClick={(e) => { e.stopPropagation(); onClear(); }} aria-label="Standort löschen">
            <EvIcon name="close" size={13} />
          </span>
        )}
      </button>
      {open && (
        <div className="ev-pop ev-pop-loc">
          <div className="ev-loc-input">
            <EvIcon name="search" size={15} />
            <input autoFocus value={q} onChange={(e) => setQ(e.target.value)}
                   onKeyDown={(e) => { if (e.key === 'Enter') pick(q); }}
                   placeholder="Ort oder PLZ" />
          </div>
          <button type="button" className="ev-loc-gps" onClick={useGPS}>
            <span className="ev-loc-gps-ic"><EvIcon name="locate" size={15} /></span>
            Meinen Standort
          </button>
          {sugg.length > 0 && (
            <div className="ev-loc-sugg">
              {sugg.map((s, i) => (
                <button key={i} type="button" className="ev-pop-item" onClick={() => pick(s.label)}>
                  <span>{s.label}</span>
                  <span className="ev-loc-kind">{s.kind === 'plz' ? 'PLZ' : 'Stadt'}</span>
                </button>
              ))}
            </div>
          )}
          {q && sugg.length === 0 && <div className="ev-loc-empty">Tipp eine Stadt oder 5-stellige PLZ.</div>}

          {/* Radius — built into the location field */}
          <div className="ev-loc-radius">
            <span className="ev-loc-radius-l">Umkreis</span>
            <div className="ev-loc-radius-row">
              {[25, 50, 100, 200].map(r => (
                <button key={r} className={'ev-loc-radius-b ' + (radius === r ? 'on' : '')} onClick={() => setRadius(r)}>
                  {r} km
                </button>
              ))}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

// ---------- share helper (used by cards + detail) ----------
function shareCard(event) {
  const url = window.location.origin + window.location.pathname + '#/events/' + event.slug;
  if (navigator.share) {
    navigator.share({ title: event.title, text: 'Schau dir dieses Firmen-Event an: ' + event.title, url }).catch(() => {});
  } else if (navigator.clipboard) {
    navigator.clipboard.writeText(url);
    if (window.__fgToast) window.__fgToast('Link kopiert ✓');
  }
}

// ---------- enhanced event card ----------
function EventCard({ event, onClick, distance }) {
  const [main, suffix] = priceLine(event);
  return (
    <article className="fg-event ev-card2" onClick={onClick} tabIndex={0}
             onKeyDown={(e) => { if (e.key === 'Enter') onClick(); }}>
      <div className="fg-event-photo" style={{ backgroundImage: `url('${window.A(event.heroImage)}')` }}>
        <div className="fg-event-chips">
          <span className="fg-photo-chip">{event.formatLabel}</span>
        </div>
        <button className="fg-event-heart" aria-label="Event teilen" onClick={(e) => { e.stopPropagation(); shareCard(event); }}>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0E1310" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"><path d="M12 15V3"/><path d="M8 7l4-4 4 4"/><path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7"/></svg>
        </button>
        {distance != null && (
          <span className="ev-distbadge"><EvIcon name="pin" size={12} /> {distance} km</span>
        )}
      </div>
      <div className="fg-event-body">
        <div className="ev-card2-top">
          <div className="fg-event-eyebrow">{event.eyebrow}</div>
          <div className="fg-event-rating">
            <StarGlyph size={13} />
            <span>{event.rating}</span>
          </div>
        </div>
        <h3 className="fg-event-title">{event.title}</h3>
        <div className="ev-card2-loc">
          <EvIcon name="pin" size={13} />
          <span>{event.venue}</span>
          <span className="dot">·</span>
          <span>{event.groupMin}–{event.groupMax} Gäste</span>
          <span className="dot">·</span>
          <span>{event.duration}</span>
        </div>
        {event.tags.includes('Indoor-Backup') && (
          <div className="ev-card2-badges">
            <span className="ev-mini-tag">Indoor-Backup</span>
          </div>
        )}
        <div className="fg-event-foot ev-card2-foot">
          <div className="fg-event-price">ab {main}<span>{suffix}</span></div>
          <span className="ev-card2-cta">Ansehen <ArrowGlyph size={13} /></span>
        </div>
      </div>
    </article>
  );
}

// ---------- featured spotlight rail ----------
function FeaturedRail({ events, onOpen }) {
  if (!events.length) return null;
  return (
    <section className="ev-featured">
      <div className="ev-featured-head">
        <div>
          <div className="mk-eyebrow">Beliebt diese Saison</div>
          <h2 className="ev-featured-h">Die Formate, die gerade am häufigsten gebucht werden.</h2>
        </div>
      </div>
      <div className="ev-featured-grid">
        {events.map((e, i) => {
          const [main, suffix] = priceLine(e);
          return (
            <article key={e.id} className={'ev-spot ' + (i === 0 ? 'is-hero' : '')}
                     onClick={() => onOpen(e)} tabIndex={0}
                     onKeyDown={(ev) => { if (ev.key === 'Enter') onOpen(e); }}>
              <div className="ev-spot-photo" style={{ backgroundImage: `url('${window.A(e.heroImage)}')` }}>
                <div className="ev-spot-scrim" />
                <span className="ev-spot-tag">{e.formatLabel}</span>
                <div className="ev-spot-body">
                  <h3 className="ev-spot-t">{e.title}</h3>
                  <div className="ev-spot-meta">
                    <span>{e.venue}</span>
                    <span className="dot">·</span>
                    <span>ab {main}{suffix}</span>
                    <span className="dot">·</span>
                    <span><StarGlyph size={12} /> {e.rating}</span>
                  </div>
                </div>
              </div>
            </article>
          );
        })}
      </div>
    </section>
  );
}

// ---------- category rail (sticky quick-switch) ----------
function CategoryRail({ value, onChange }) {
  const d = window.SITE_DATA;
  return (
    <div className="ev-catrail">
      <div className="ev-catrail-inner">
        {d.formats.map(f => (
          <button key={f.id}
                  className={'ev-cat ' + (value === f.id ? 'active' : '')}
                  onClick={() => onChange(f.id)}>
            {f.label}
          </button>
        ))}
      </div>
    </div>
  );
}

// ---------- mobile compact search (Airbnb-style pill + bottom sheet) ----------
function MobileSearch({ format, origin, groupBand, apply }) {
  const d = window.SITE_DATA;
  const [open, setOpen] = useState(false);
  const [f, setF] = useState(format);
  const [g, setG] = useState(groupBand);
  const [q, setQ] = useState('');
  const [o, setO] = useState(origin);

  const fmtLabel = (id) => d.formats.find(x => x.id === id)?.label || 'Alle Typen';
  const bandLabel = (id) => GROUP_BANDS.find(b => b.id === id)?.label || 'Jede Größe';
  const sugg = (window.GEO && q) ? window.GEO.suggest(q, 5) : [];

  useEffect(() => {
    document.body.style.overflow = open ? 'hidden' : '';
    return () => { document.body.style.overflow = ''; };
  }, [open]);

  const openSheet = () => { setF(format); setG(groupBand); setO(origin); setQ(''); setOpen(true); };
  const onSearch = () => { apply(f, o, g); setOpen(false); };
  const reset = () => { setF('all'); setG('any'); setO(null); setQ(''); };
  const pickLoc = (text) => { const r = window.GEO.resolve(text); if (r) { setO({ lat: r.lat, lng: r.lng, label: r.label, source: 'manual' }); setQ(''); } };
  const useGPS = () => {
    if (!navigator.geolocation) return;
    navigator.geolocation.getCurrentPosition(
      (pos) => setO({ lat: pos.coords.latitude, lng: pos.coords.longitude, label: 'In deiner Nähe', source: 'gps' }),
      () => {}, { timeout: 8000 });
  };

  const pristine = format === 'all' && !origin && groupBand === 'any';
  const summary = pristine
    ? 'Jetzt suchen'
    : [format !== 'all' && fmtLabel(format),
       origin && origin.label,
       groupBand !== 'any' && bandLabel(groupBand)].filter(Boolean).join(' · ');

  return (
    <>
      <MobileBar active="events">
        <button className="ev-msearch" onClick={openSheet} aria-label="Suche öffnen">
          <EvIcon name="search" size={18} />
          <span className={'ev-msearch-t ' + (pristine ? 'muted' : '')}>{summary}</span>
        </button>
      </MobileBar>

      {open && (
        <div className="ev-sheet-scrim" onClick={() => setOpen(false)}>
          <div className="ev-sheet" onClick={(e) => e.stopPropagation()} role="dialog" aria-label="Event finden">
            <div className="ev-sheet-top">
              <button className="ev-sheet-close" onClick={() => setOpen(false)} aria-label="Schließen">
                <EvIcon name="close" size={18} />
              </button>
              <span className="ev-sheet-title">Event finden</span>
            </div>

            <div className="ev-sheet-body">
              <section className="ev-sheet-card">
                <div className="ev-sheet-q">Was möchtet ihr machen?</div>
                <div className="ev-sheet-chips">
                  {d.formats.map(x => (
                    <button key={x.id} className={'ev-sheet-chip ' + (f === x.id ? 'on' : '')} onClick={() => setF(x.id)}>
                      {x.label}
                    </button>
                  ))}
                </div>
              </section>

              <section className="ev-sheet-card">
                <div className="ev-sheet-q">Wo seid ihr?</div>
                <div className="ev-loc-input ev-loc-input-sheet">
                  <EvIcon name="search" size={16} />
                  <input value={q} onChange={(e) => setQ(e.target.value)}
                         onKeyDown={(e) => { if (e.key === 'Enter') pickLoc(q); }}
                         placeholder="Ort oder PLZ" />
                </div>
                <button type="button" className="ev-loc-gps ev-loc-gps-sheet" onClick={useGPS}>
                  <span className="ev-loc-gps-ic"><EvIcon name="locate" size={15} /></span> Meinen Standort
                </button>
                {sugg.length > 0 && (
                  <div className="ev-sheet-chips" style={{ marginTop: 10 }}>
                    {sugg.map((s, i) => (
                      <button key={i} className="ev-sheet-chip" onClick={() => pickLoc(s.label)}>{s.label}</button>
                    ))}
                  </div>
                )}
                {o && <div className="ev-sheet-picked">Gewählt: <strong>{o.label}</strong> <button onClick={() => setO(null)}>ändern</button></div>}
              </section>

              <section className="ev-sheet-card">
                <div className="ev-sheet-q">Wie groß ist die Gruppe?</div>
                <div className="ev-sheet-chips">
                  {GROUP_BANDS.map(b => (
                    <button key={b.id} className={'ev-sheet-chip ' + (g === b.id ? 'on' : '')} onClick={() => setG(b.id)}>
                      {b.label}
                    </button>
                  ))}
                </div>
              </section>
            </div>

            <div className="ev-sheet-foot">
              <button className="ev-sheet-clear" onClick={reset}>Alle löschen</button>
              <button className="ev-sheet-go" onClick={onSearch}>
                <EvIcon name="search" size={16} /> Suche
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}

// ---------- custom sort dropdown (native select looks like the OS; this is on-brand) ----------
function SortMenu({ value, onChange, options }) {
  const opts = options || [
    { id: 'curated',   label: 'Empfohlen' },
    { id: 'price-asc', label: 'Preis aufsteigend' },
    { id: 'rating',    label: 'Beste Bewertung' },
    { id: 'group',     label: 'Größte Gruppen' },
  ];
  const [open, setOpen] = useState(false);
  const ref = useRef();
  const current = opts.find(o => o.id === value) || opts[0];

  useEffect(() => {
    if (!open) return;
    const onDoc = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
    const onKey = (e) => { if (e.key === 'Escape') setOpen(false); };
    document.addEventListener('mousedown', onDoc);
    document.addEventListener('keydown', onKey);
    return () => { document.removeEventListener('mousedown', onDoc); document.removeEventListener('keydown', onKey); };
  }, [open]);

  return (
    <div className="ev-dd" ref={ref}>
      <button className={'ev-dd-trigger ' + (open ? 'open' : '')} onClick={() => setOpen(o => !o)} aria-haspopup="listbox" aria-expanded={open}>
        <span>{current.label}</span>
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M6 9l6 6 6-6"/></svg>
      </button>
      {open && (
        <ul className="ev-dd-menu" role="listbox">
          {opts.map(o => (
            <li key={o.id} role="option" aria-selected={o.id === value}
                className={'ev-dd-item ' + (o.id === value ? 'on' : '')}
                onClick={() => { onChange(o.id); setOpen(false); }}>
              <span>{o.label}</span>
              {o.id === value && (
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
              )}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

// ---------- mobile: browse by category (rows of up to 5 + "Alle anzeigen") ----------
function AllCard({ label, onClick }) {
  return (
    <button className="ev-allcard" onClick={onClick}>
      <span className="ev-allcard-ic"><EvIcon name="search" size={20} /></span>
      <span className="ev-allcard-t">Alle {label}</span>
      <span className="ev-allcard-go">anzeigen <ArrowGlyph size={13} /></span>
    </button>
  );
}

function CategoryBrowse({ formats, allEvents, onAll, openDetail, isMobile, distances, originSet }) {
  const cats = formats.filter(f => f.id !== 'all');
  return (
    <div className={'ev-catbrowse ' + (isMobile ? '' : 'is-desk')}>
      {cats.map(f => {
        const evs = allEvents.filter(e => e.format === f.id);
        if (!evs.length) return null;
        const shown = isMobile ? evs.slice(0, 5) : evs.slice(0, 4);
        return (
          <section className="ev-catsec" key={f.id}>
            <div className="ev-catsec-head">
              <h3 className="ev-catsec-h">{f.label} <span className="ev-catsec-c">{evs.length}</span></h3>
              <button className="ev-catsec-all" onClick={() => onAll(f.id)}>Alle ansehen →</button>
            </div>
            <div className={isMobile ? 'ev-catrow' : 'ev-catgrid'}>
              {shown.map(e => (
                <EventCard key={e.id} event={e} distance={originSet ? distances[e.id] : null} onClick={() => openDetail(e.slug)} />
              ))}
              {isMobile && <AllCard label={f.label} onClick={() => onAll(f.id)} />}
            </div>
          </section>
        );
      })}

      <div className="ev-inline-cta">
        <div>
          <div className="mk-eyebrow">Kein passender Veranstaltungstyp dabei?</div>
          <h3 className="ev-inline-h">Wir planen dein Event nach deinen Ansprüchen.</h3>
        </div>
        <a className="fg-btn-brand" href="#/individuell" onClick={(e) => go('#/individuell', e)}>
          Individuelles Event anfragen
          <span className="fg-arrow"><ArrowGlyph /></span>
        </a>
      </div>
    </div>
  );
}

// ---------- shared search bar (used by Events hero + Home) ----------
function EventSearchBar({ format, setFormat, origin, setOrigin, radius, setRadius, groupBand, setGroupBand, onSearch }) {
  const d = window.SITE_DATA;
  const fmtLabel = (id) => d.formats.find(f => f.id === id)?.label || 'Alle Typen';
  const bandLabel = (id) => GROUP_BANDS.find(b => b.id === id)?.label || 'Jede Größe';
  return (
    <div className="ev-searchbar">
      <SearchCell
        icon="grid" label="Veranstaltungstyp" value={fmtLabel(format)}
        options={d.formats.map(f => ({ id: f.id, label: f.label, active: f.id === format }))}
        onSelect={setFormat}
      />
      <div className="ev-cell-div" />
      <LocationCell origin={origin} onSet={setOrigin} onClear={() => setOrigin(null)} radius={radius} setRadius={setRadius} />
      <div className="ev-cell-div" />
      <SearchCell
        icon="users" label="Gruppe" value={bandLabel(groupBand)} muted={groupBand === 'any'}
        options={GROUP_BANDS.map(b => ({ id: b.id, label: b.label, active: b.id === groupBand }))}
        onSelect={setGroupBand}
      />
      <button className="ev-search-go" onClick={onSearch} aria-label="Events suchen">
        <EvIcon name="search" size={18} />
        <span>Suchen</span>
      </button>
    </div>
  );
}
window.EventSearchBar = EventSearchBar;
window.GROUP_BANDS = GROUP_BANDS;

function EventsPage({ initialFormat, initialQuery }) {
  const d = window.SITE_DATA;
  const q0 = initialQuery || {};
  const origin0 = (q0.plz || q0.ort) && window.GEO ? window.GEO.resolve(q0.plz || q0.ort) : null;
  const [format, setFormat] = useState(initialFormat || 'all');
  const [origin, setOrigin] = useState(origin0 ? { lat: origin0.lat, lng: origin0.lng, label: origin0.label, source: 'manual' } : null);
  const [radius, setRadius] = useState(q0.radius ? parseInt(q0.radius, 10) : 50); // km, default 50
  const [groupBand, setGroupBand] = useState(q0.group || 'any');
  const [sort, setSort] = useState('curated');
  const [isMobile, setIsMobile] = useState(() => window.matchMedia('(max-width: 720px)').matches);
  const resultsRef = useRef();

  useEffect(() => {
    const mq = window.matchMedia('(max-width: 720px)');
    const on = () => setIsMobile(mq.matches);
    mq.addEventListener ? mq.addEventListener('change', on) : mq.addListener(on);
    return () => { mq.removeEventListener ? mq.removeEventListener('change', on) : mq.removeListener(on); };
  }, []);

  useEffect(() => { if (initialFormat) setFormat(initialFormat); }, [initialFormat]);

  const originSet = origin && !origin.pending && origin.lat != null;
  const isFiltered = format !== 'all' || originSet || groupBand !== 'any';

  // distance per event id (only when an origin is set)
  const distances = useMemo(() => {
    if (!originSet || !window.GEO) return {};
    const o = [origin.lat, origin.lng];
    const map = {};
    d.events.forEach(e => {
      const c = e.coords || window.GEO.coordsForVenue(e.venue);
      map[e.id] = c ? window.GEO.distKm(o, c) : null;
    });
    return map;
  }, [originSet, origin && origin.lat, origin && origin.lng]);

  const filtered = useMemo(() => {
    const band = GROUP_BANDS.find(b => b.id === groupBand);
    let list = d.events.filter(e => {
      if (format !== 'all' && e.format !== format) return false;
      if (band && band.min !== undefined) {
        if (!(e.groupMin <= band.max && e.groupMax >= band.min)) return false;
      }
      if (originSet && radius > 0) {
        const dist = distances[e.id];
        if (dist == null || dist > radius) return false;
      }
      return true;    });
    // When an origin is set, distance is the natural default ordering.
    if (originSet && (sort === 'curated' || sort === 'nearest')) {
      list = list.slice().sort((a, b) => (distances[a.id] ?? 9e9) - (distances[b.id] ?? 9e9));
    } else if (sort === 'price-asc') list = list.slice().sort((a,b) => (a.pricePerPerson || a.pricePerGroup) - (b.pricePerPerson || b.pricePerGroup));
    else if (sort === 'rating')      list = list.slice().sort((a,b) => b.rating - a.rating);
    else if (sort === 'group')       list = list.slice().sort((a,b) => b.groupMax - a.groupMax);
    return list;
  }, [format, groupBand, sort, originSet, radius, distances]);

  const featured = useMemo(() =>
    d.events.filter(e => e.featured).slice(0, 3), []);

  const fmtLabel = (id) => d.formats.find(f => f.id === id)?.label || 'Alle Typen';
  const bandLabel = (id) => GROUP_BANDS.find(b => b.id === id)?.label || 'Jede Größe';

  const scrollToResults = () => resultsRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });

  const clearAll = () => { setFormat('all'); setOrigin(null); setRadius(0); setGroupBand('any'); };

  const applySearch = (f, o, g) => {
    setFormat(f); setOrigin(o); setGroupBand(g);
    setTimeout(scrollToResults, 60);
  };

  const sortOpts = [
    ...(originSet ? [{ id: 'nearest', label: 'Nächste zuerst' }] : [{ id: 'curated', label: 'Empfohlen' }]),
    { id: 'price-asc', label: 'Preis aufsteigend' },
    { id: 'rating',    label: 'Beste Bewertung' },
    { id: 'group',     label: 'Größte Gruppen' },
  ];
  const sortVal = (originSet && sort === 'curated') ? 'nearest' : sort;

  return (
    <div data-screen-label="Events">
      {/* Mobile compact search — replaces the big hero/searchbar on phones */}
      <MobileSearch format={format} origin={origin} groupBand={groupBand} apply={applySearch} />

      {/* Hero with working search */}
      <section className="ev-hero">
        <div className="ev-hero-photo" style={{ backgroundImage: `url('${window.A('assets/imagery/hero-meadow.jpg')}')` }}>
          <div className="ev-hero-scrim" />
          <div className="ev-hero-content">
            <div className="ev-hero-eyebrow">Marketplace · Firmenevents</div>
            <h1 className="ev-hero-title">
              Finde dein nächstes <span className="mk-italic">Firmen-Event</span>.
            </h1>
            <p className="ev-hero-sub">
              Der Golfplatz ist die perfekte Location für euer nächstes Firmenevent — wir bringen
              euer Team in Bewegung. Such nach Ort, Anlass und Gruppengröße.
            </p>
          </div>
        </div>

        <EventSearchBar
          format={format} setFormat={setFormat}
          origin={origin} setOrigin={setOrigin}
          radius={radius} setRadius={setRadius}
          groupBand={groupBand} setGroupBand={setGroupBand}
          onSearch={scrollToResults}
        />
      </section>

      {/* Sticky category quick-switch — desktop only (mobile uses the search sheet + category rows) */}
      <CategoryRail value={format} onChange={setFormat} />

      {/* Mobile, unfiltered → swipeable rows per category. Desktop → flat results grid. */}
      {!isFiltered && isMobile ? (
        <CategoryBrowse
          formats={d.formats}
          allEvents={d.events}
          isMobile={true}
          distances={distances}
          originSet={originSet}
          onAll={(id) => { setFormat(id); setTimeout(scrollToResults, 50); }}
          openDetail={(slug) => go('#/events/' + slug)}
        />
      ) : (
      <section className="fg-grid-section" ref={resultsRef}>
        {/* Active filters + sort on one row */}
        {isFiltered && (
          <div className="ev-filterbar">
            <div className="ev-active">
              {format !== 'all' && (
                <button className="ev-pill" onClick={() => setFormat('all')}>
                  {fmtLabel(format)} <EvIcon name="close" size={12} />
                </button>
              )}
              {originSet && (
                <button className="ev-pill" onClick={() => { setOrigin(null); }}>
                  {origin.label}{radius > 0 ? ' · ' + radius + ' km' : ''} <EvIcon name="close" size={12} />
                </button>
              )}
              {groupBand !== 'any' && (
                <button className="ev-pill" onClick={() => setGroupBand('any')}>
                  {bandLabel(groupBand)} Gäste <EvIcon name="close" size={12} />
                </button>
              )}
              <button className="ev-clear" onClick={clearAll}>Alle zurücksetzen</button>
            </div>
            <div className="ev-sort">
              <span className="fg-cell-label">Sortieren</span>
              <SortMenu value={sortVal} options={sortOpts} onChange={setSort} />
            </div>
          </div>
        )}

        {/* Count directly above the grid */}
        <div className="ev-count-row">
          <div className="fg-grid-count">{filtered.length} {filtered.length === 1 ? 'Event' : 'Events'} gefunden</div>
          {!isFiltered && (
            <div className="ev-sort">
              <span className="fg-cell-label">Sortieren</span>
              <SortMenu value={sortVal} options={sortOpts} onChange={setSort} />
            </div>
          )}
        </div>

        {filtered.length === 0 ? (
          <div className="fg-empty">
            <div className="ev-empty-h">Für diese Kombination haben wir noch keinen passenden Veranstaltungstyp.</div>
            <p>Genau dafür gibt es uns — beschreib kurz, was du dir vorstellst, und wir bauen es.</p>
            <div className="ev-empty-ctas">
              <a className="fg-btn-brand" href="#/individuell" onClick={(e) => go('#/individuell', e)}>
                Individuelles Event anfragen <span className="fg-arrow"><ArrowGlyph /></span>
              </a>
              <button className="fg-btn-ghost" onClick={clearAll}>Filter zurücksetzen</button>
            </div>
          </div>
        ) : (
          <div className="fg-grid">
            {filtered.map(e => (
              <EventCard key={e.id} event={e} distance={originSet ? distances[e.id] : null} onClick={() => go('#/events/' + e.slug)} />
            ))}
          </div>
        )}

        {/* Inline CTA */}
        <div className="ev-inline-cta">
          <div>
            <div className="mk-eyebrow">Kein passender Veranstaltungstyp dabei?</div>
            <h3 className="ev-inline-h">Wir planen dein Event nach deinen Ansprüchen.</h3>
          </div>
          <a className="fg-btn-brand" href="#/individuell" onClick={(e) => go('#/individuell', e)}>
            Individuelles Event anfragen
            <span className="fg-arrow"><ArrowGlyph /></span>
          </a>
        </div>
      </section>
      )}

      {/* Curated rail removed when unfiltered — category browse already covers discovery */}
      {isFiltered === '___never___' && <FeaturedRail events={featured} onOpen={(e) => go('#/events/' + e.slug)} />}

      <TrustStrip />

      <FAQ
        title="Was Firmen vor der Buchung wissen wollen."
        intro="Die häufigsten Fragen unserer Kunden — vor allem von HR und Office Management. Was hier nicht steht: einfach kurz schreiben."
        items={[
          { q: 'Müssen meine Mitarbeitenden Golf spielen können?', a: 'Nein. Unsere Teamevents und After-Work-Formate sind komplett für Einsteigende konzipiert. Es gibt einen PGA-Coach vor Ort, alle Schläger werden gestellt — niemand muss Vorerfahrung mitbringen.' },
          { q: 'Wie kurzfristig können wir buchen?', a: 'Beliebte Termine im Mai–September gehen meist 4–6 Wochen im Voraus weg. Im Frühjahr und Herbst gibt es oft noch Slots innerhalb von 1–2 Wochen. Trag uns gern unverbindlich ein — wir prüfen, was kurzfristig möglich ist.' },
          { q: 'Was passiert bei schlechtem Wetter?', a: 'Gespielt wird bei fast jedem Wetter — und bei Regen oder Wind wird aus der Runde oft erst recht ein Abenteuer, das euer Team noch enger zusammenschweißt. Wo möglich gibt es zusätzlich ein Indoor-Backup (überdachte Range oder Simulator), und für reine Outdoor-Formate großzügige Stornoregeln bis 24 Stunden vor Termin.' },
          { q: 'Wie groß darf die Gruppe sein?', a: 'Die meisten Formate laufen ab 10 bis maximal 30 Personen. Größere Gruppen, Sommerfeste oder Firmenturniere planen wir individuell — sag uns einfach, wie viele ihr seid.' },
          { q: 'Wie wird abgerechnet?', a: 'Eine Sammelrechnung von Firmengolf, mit allen Posten ausgewiesen — Green-Fees, Coaching, Catering, Extras. Das macht es für HR und Buchhaltung einfach.' },
          { q: 'Können wir das Event als Gesundheitsmaßnahme abrechnen?', a: 'Ja — unsere Gesundheitstage sind BGM-konform (§ 3 Nr. 34 EStG) abrechenbar. Wir stellen die nötigen Belege aus.' },
        ]}
      />
    </div>
  );
}
window.EventsPage = EventsPage;
window.EventCard = EventCard;
