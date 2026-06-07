/* eslint-disable */
// =============================================================
// Golfplätze — venue map + list (#/golfplaetze) and a single
// venue page (#/golfplatz/<slug>) showing all offers at that
// course plus an "individuelles Event" CTA.
// Map is a self-drawn SVG of Germany (no external tiles); pins
// and the outline share one lat/lng projection so they line up.
// =============================================================
var { useState, useMemo, useEffect, useRef } = React;

// ---------- projection (equirectangular, cos-corrected via H) ----------
const VN_W = 420, VN_H = 570;
const VN_LNG0 = 5.8, VN_LNG1 = 15.1, VN_LAT0 = 47.2, VN_LAT1 = 55.1;
function vnProject(lng, lat) {
  return [
    (lng - VN_LNG0) / (VN_LNG1 - VN_LNG0) * VN_W,
    (VN_LAT1 - lat) / (VN_LAT1 - VN_LAT0) * VN_H,
  ];
}
// percentage position (so pins align with the viewBox-scaled SVG at any size)
function vnPct(lng, lat) {
  return [
    (lng - VN_LNG0) / (VN_LNG1 - VN_LNG0) * 100,
    (VN_LAT1 - lat) / (VN_LAT1 - VN_LAT0) * 100,
  ];
}
const vnOnMap = (v) => v.lat >= VN_LAT0 && v.lat <= VN_LAT1 && v.lng >= VN_LNG0 && v.lng <= VN_LNG1;

// ---------- Germany outline (lng,lat), clockwise from NW coast ----------
const DE_BORDER = [
  [7.0,53.3],[8.0,53.72],[8.5,53.55],[8.5,54.02],[8.9,54.4],[8.62,54.9],[9.42,54.83],
  [10.0,54.38],[10.8,54.02],[11.5,54.2],[12.4,54.32],[13.4,54.12],[13.8,54.12],[14.2,53.9],
  [14.4,53.3],[14.12,52.96],[14.6,52.5],[14.64,52.34],[14.76,52.07],[14.75,51.5],[15.04,51.28],
  [14.6,51.05],[14.3,50.9],[13.9,50.7],[13.3,50.6],[12.9,50.42],[12.5,50.35],[12.2,50.1],
  [12.4,49.8],[12.6,49.4],[13.4,48.95],[13.84,48.77],[13.5,48.57],[13.0,47.85],[12.9,47.72],
  [13.05,47.48],[12.2,47.62],[11.6,47.58],[11.4,47.45],[10.9,47.47],[10.45,47.55],[10.18,47.37],
  [10.17,47.27],[9.9,47.55],[9.18,47.66],[8.9,47.65],[8.6,47.8],[8.4,47.6],[7.7,47.55],
  [7.58,47.9],[7.8,48.5],[8.0,48.9],[8.23,48.97],[7.9,49.05],[7.4,49.18],[6.8,49.16],
  [6.36,49.46],[6.13,49.6],[6.4,50.0],[6.2,50.5],[6.02,50.76],[5.87,51.05],[6.1,51.18],
  [6.0,51.45],[6.22,51.5],[6.4,51.83],[6.7,52.0],[7.03,52.23],[7.07,52.65],[6.7,52.65],
  [7.2,53.0],[7.0,53.3],
];
const DE_PATH = DE_BORDER.map((p, i) => {
  const [x, y] = vnProject(p[0], p[1]);
  return (i === 0 ? 'M' : 'L') + x.toFixed(1) + ' ' + y.toFixed(1);
}).join(' ') + ' Z';

// ---------- icons ----------
function VnIcon({ name, size = 16 }) {
  const p = {
    pin:    <><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></>,
    flag:   <><path d="M4 21V4M4 4l9 2.5L4 10"/><path d="M13 6.5L20 8l-7 1.5"/></>,
    search: <><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></>,
    close:  <><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></>,
    locate: <><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/></>,
    holes:  <><circle cx="12" cy="18" r="3"/><path d="M12 15V4l6 2-6 2"/></>,
    star:   <><path d="M12 3l2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.9 6.8 19.1l1-5.8L3.5 9.2l5.9-.9z"/></>,
    arrow:  <><path d="M5 12h14M13 5l7 7-7 7"/></>,
    back:   <><path d="M19 12H5M11 18l-6-6 6-6"/></>,
  };
  return (
    <svg viewBox="0 0 24 24" width={size} height={size} fill="none" stroke="currentColor"
         strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">{p[name]}</svg>
  );
}

const VN_REGIONS = ['Alle', 'Nord', 'Ost', 'Süd', 'West'];
const vnAvgRating = (evs) => evs.length ? (evs.reduce((s, e) => s + (e.rating || 0), 0) / evs.length) : null;
const vnFromPrice = (evs) => {
  const ps = evs.map(e => e.pricePerPerson).filter(Boolean);
  return ps.length ? Math.min.apply(null, ps) : null;
};

// ---------- location field (PLZ / city + GPS) ----------
function VnLocation({ origin, onSet, onClear, radius, setRadius }) {
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
    if (r) { onSet({ lat: r.lat, lng: r.lng, label: r.label }); setQ(''); setOpen(false); }
  };
  const useGPS = () => {
    if (!navigator.geolocation) return;
    navigator.geolocation.getCurrentPosition(
      (pos) => { onSet({ lat: pos.coords.latitude, lng: pos.coords.longitude, label: 'In deiner Nähe' }); setOpen(false); },
      () => {}, { timeout: 8000 });
  };
  const value = origin ? origin.label + ' · ' + radius + ' km' : 'Ort oder PLZ';

  return (
    <div className={'vn-loc ' + (open ? 'open' : '')} ref={ref}>
      <button type="button" className="vn-loc-btn" onClick={() => setOpen(o => !o)}>
        <span className="vn-loc-ic"><VnIcon name="pin" /></span>
        <span className="vn-loc-text">
          <span className="vn-loc-label">Wo seid ihr?</span>
          <span className={'vn-loc-value ' + (origin ? '' : 'muted')}>{value}</span>
        </span>
        {origin && <span className="vn-loc-x" onClick={(e) => { e.stopPropagation(); onClear(); }} aria-label="Standort löschen"><VnIcon name="close" size={13} /></span>}
      </button>
      {open && (
        <div className="vn-loc-pop">
          <div className="vn-loc-input">
            <VnIcon name="search" size={15} />
            <input autoFocus value={q} onChange={(e) => setQ(e.target.value)}
                   onKeyDown={(e) => { if (e.key === 'Enter') pick(q); }} placeholder="Stadt oder PLZ" />
          </div>
          <button type="button" className="vn-loc-gps" onClick={useGPS}>
            <span className="vn-loc-gps-ic"><VnIcon name="locate" size={15} /></span> Meinen Standort
          </button>
          {sugg.length > 0 && (
            <div className="vn-loc-sugg">
              {sugg.map((s, i) => (
                <button key={i} type="button" className="vn-loc-sug" onClick={() => pick(s.label)}>
                  <span>{s.label}</span><span className="vn-loc-kind">{s.kind === 'plz' ? 'PLZ' : 'Stadt'}</span>
                </button>
              ))}
            </div>
          )}
          {q && sugg.length === 0 && <div className="vn-loc-empty">Tipp eine Stadt oder 5-stellige PLZ.</div>}
          <div className="vn-loc-radius">
            <span className="vn-loc-radius-l">Umkreis</span>
            <div className="vn-loc-radius-row">
              {[25, 50, 100, 200].map(r => (
                <button key={r} className={'vn-loc-radius-b ' + (radius === r ? 'on' : '')} onClick={() => setRadius(r)}>{r} km</button>
              ))}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

// ---------- map pin ----------
function VnPin({ v, x, y, n, selected, hovered, onClick, onHover }) {
  return (
    <button
      className={'vn-pin ' + (selected ? 'sel ' : '') + (hovered ? 'hov' : '')}
      style={{ left: x + '%', top: y + '%' }}
      onClick={(e) => { e.stopPropagation(); onClick(); }}
      onMouseEnter={() => onHover(v.slug)} onMouseLeave={() => onHover(null)}
      aria-label={v.name}>
      <span className="vn-pin-drop"><span className="vn-pin-n">{n}</span></span>
      {(hovered || selected) && <span className="vn-pin-tip">{v.city}</span>}
    </button>
  );
}

// ---------- venue list card ----------
function VnCard({ v, n, evs, dist, active, onEnter, onLeave, onOpen, refCb }) {
  const rating = vnAvgRating(evs);
  const from = vnFromPrice(evs);
  return (
    <article ref={refCb} className={'vn-card ' + (active ? 'active' : '')}
             onMouseEnter={onEnter} onMouseLeave={onLeave} onClick={onOpen} tabIndex={0}
             onKeyDown={(e) => { if (e.key === 'Enter') onOpen(); }}>
      <div className="vn-card-photo" style={{ backgroundImage: `url('${window.A(v.image)}')` }}>
        <span className="vn-card-num">{n}</span>
        {rating != null && <span className="vn-card-rate"><VnIcon name="star" size={12} /> {rating.toFixed(1)}</span>}
      </div>
      <div className="vn-card-body">
        <div className="vn-card-region">{v.region} · {v.city}</div>
        <h3 className="vn-card-name">{v.name}</h3>
        <div className="vn-card-meta">
          <span><VnIcon name="holes" size={13} /> {v.holes} Loch · Par {v.par}</span>
          {dist != null && <><span className="dot">·</span><span>{dist} km</span></>}
        </div>
        <div className="vn-card-foot">
          <span className="vn-card-offers">{evs.length} {evs.length === 1 ? 'Angebot' : 'Angebote'}{from ? ' · ab €' + from + ' p.P.' : ''}</span>
          <span className="vn-card-cta">Platz ansehen <VnIcon name="arrow" size={13} /></span>
        </div>
      </div>
    </article>
  );
}

// ---------- map popover (anchored to selected pin) ----------
function VnPopover({ v, evs, x, y, onClose, onOpen }) {
  const from = vnFromPrice(evs);
  return (
    <div className="vn-pop-card" style={{ left: x + '%', top: y + '%' }} onClick={(e) => e.stopPropagation()}>
      <button className="vn-pop-x" onClick={onClose} aria-label="Schließen"><VnIcon name="close" size={14} /></button>
      <div className="vn-pop-photo" style={{ backgroundImage: `url('${window.A(v.image)}')` }} />
      <div className="vn-pop-body">
        <div className="vn-pop-region">{v.region} · {v.city}</div>
        <div className="vn-pop-name">{v.name}</div>
        <div className="vn-pop-meta">{v.holes} Loch · {evs.length} {evs.length === 1 ? 'Angebot' : 'Angebote'}{from ? ' · ab €' + from : ''}</div>
        <button className="fg-btn-brand vn-pop-go" onClick={onOpen}>Platz ansehen <span className="fg-arrow"><VnIcon name="arrow" size={13} /></span></button>
      </div>
    </div>
  );
}

// ==================== VENUES PAGE ====================
function VenuesPage() {
  const d = window.SITE_DATA;
  const venues = d.venues;
  const [region, setRegion] = useState('Alle');
  const [origin, setOrigin] = useState(null);
  const [radius, setRadius] = useState(100);
  const [selected, setSelected] = useState(null);
  const [hovered, setHovered] = useState(null);
  const listRef = useRef();
  const cardRefs = useRef({});

  const evsOf = (v) => d.eventsForVenue(v.venue);
  const dist = (v) => (origin && window.GEO) ? window.GEO.distKm([origin.lat, origin.lng], [v.lat, v.lng]) : null;

  const list = useMemo(() => {
    let arr = venues.filter(v => region === 'Alle' || v.region === region);
    if (origin) arr = arr.filter(v => { const dk = dist(v); return dk == null || dk <= radius; });
    arr = arr.slice().sort((a, b) => {
      if (origin) return (dist(a) ?? 9e9) - (dist(b) ?? 9e9);
      return evsOf(b).length - evsOf(a).length;
    });
    return arr;
  }, [region, origin, radius]);

  const mapVenues = list.filter(vnOnMap);
  const numberOf = (slug) => list.findIndex(v => v.slug === slug) + 1;

  // scroll the selected card into view inside the list column (no scrollIntoView)
  useEffect(() => {
    if (!selected || !listRef.current) return;
    const card = cardRefs.current[selected];
    if (card) listRef.current.scrollTo({ top: Math.max(0, card.offsetTop - 12), behavior: 'smooth' });
  }, [selected]);

  const openVenue = (slug) => go('#/golfplatz/' + slug);
  const isFiltered = region !== 'Alle' || !!origin;

  return (
    <div data-screen-label="Golfplätze">
      <MobileBar active="golfplaetze">
        <a className="ev-msearch ev-maction" href="#/individuell" onClick={(e) => go('#/individuell', e)}>
          <span>Individuelles Event anfragen</span>
          <span className="ev-maction-arrow"><VnIcon name="arrow" size={14} /></span>
        </a>
      </MobileBar>

      {/* hero */}
      <section className="vn-hero">
        <div className="vn-hero-inner">
          <div className="mk-eyebrow">Partnerplätze</div>
          <h1 className="vn-hero-h">Finde den Golfplatz für <span className="mk-italic">euer</span> Event.</h1>
          <p className="vn-hero-sub">Über 180 Partnerplätze in ganz Deutschland. Such auf der Karte, öffne einen Platz — und sieh alle Formate, die dort möglich sind.</p>
        </div>
        <div className="vn-searchrow">
          <VnLocation origin={origin} onSet={setOrigin} onClear={() => setOrigin(null)} radius={radius} setRadius={setRadius} />
          <div className="vn-regions">
            {VN_REGIONS.map(r => (
              <button key={r} className={'vn-region ' + (region === r ? 'on' : '')} onClick={() => { setRegion(r); setSelected(null); }}>{r}</button>
            ))}
          </div>
          {isFiltered && <button className="vn-reset" onClick={() => { setRegion('Alle'); setOrigin(null); setSelected(null); }}>Zurücksetzen</button>}
        </div>
      </section>

      {/* split: map + list */}
      <section className="vn-split">
        <div className="vn-mapcol">
          <div className="vn-map" onClick={() => setSelected(null)}>
            <svg className="vn-map-svg" viewBox={`0 0 ${VN_W} ${VN_H}`} preserveAspectRatio="xMidYMid meet" aria-hidden="true">
              <path className="vn-map-land" d={DE_PATH} />
            </svg>
            <div className="vn-map-pins">
              {mapVenues.map(v => {
                const [x, y] = vnPct(v.lng, v.lat);
                return <VnPin key={v.slug} v={v} x={x} y={y} n={numberOf(v.slug)}
                              selected={selected === v.slug} hovered={hovered === v.slug}
                              onClick={() => setSelected(v.slug)} onHover={setHovered} />;
              })}
              {selected && (() => {
                const v = list.find(x => x.slug === selected);
                if (!v || !vnOnMap(v)) return null;
                const [x, y] = vnPct(v.lng, v.lat);
                return <VnPopover v={v} evs={evsOf(v)} x={x} y={y} onClose={() => setSelected(null)} onOpen={() => openVenue(v.slug)} />;
              })()}
            </div>
            <div className="vn-map-legend"><span className="vn-legend-pin" /> {mapVenues.length} Plätze auf der Karte</div>
          </div>
        </div>

        <div className="vn-listcol" ref={listRef}>
          <div className="vn-listhead">
            <span className="vn-listcount">{list.length} {list.length === 1 ? 'Golfplatz' : 'Golfplätze'}{origin ? ' · ' + origin.label : ''}</span>
          </div>
          {list.length === 0 ? (
            <div className="vn-empty">
              <div className="vn-empty-h">Hier haben wir noch keinen Platz.</div>
              <p>Erweitere den Umkreis oder wähle eine andere Region — oder lass uns deinen Wunschort wissen.</p>
              <a className="fg-btn-brand" href="#/individuell" onClick={(e) => go('#/individuell', e)}>Event anfragen <span className="fg-arrow"><VnIcon name="arrow" /></span></a>
            </div>
          ) : list.map(v => (
            <VnCard key={v.slug} v={v} n={numberOf(v.slug)} evs={evsOf(v)} dist={dist(v)}
                    active={selected === v.slug || hovered === v.slug}
                    onEnter={() => setHovered(v.slug)} onLeave={() => setHovered(null)}
                    onOpen={() => openVenue(v.slug)}
                    refCb={(el) => { cardRefs.current[v.slug] = el; }} />
          ))}
        </div>
      </section>

      {/* CTA */}
      <section className="vn-cta">
        <div className="vn-cta-inner">
          <div>
            <div className="mk-eyebrow" style={{ color: 'var(--fairway-300)' }}>Kein passender Platz dabei?</div>
            <h2 className="vn-cta-h">Wir finden den richtigen Platz für euch.</h2>
          </div>
          <a className="fg-btn-ink lg" href="#/individuell" onClick={(e) => go('#/individuell', e)}
             style={{ background: 'var(--paper-100)', color: 'var(--fairway-900)' }}>
            Individuelles Event anfragen <span className="fg-arrow" style={{ background: 'var(--fairway-200)' }}><VnIcon name="arrow" /></span>
          </a>
        </div>
      </section>
    </div>
  );
}
window.VenuesPage = VenuesPage;

// ==================== SINGLE VENUE PAGE ====================
function VenuePage({ slug }) {
  const d = window.SITE_DATA;
  const v = d.venues.find(x => x.slug === slug);

  if (!v) {
    return (
      <div className="ev-notfound">
        <div className="mk-eyebrow">404</div>
        <h1 className="display-md">Diesen Platz gibt's nicht.</h1>
        <a className="fg-btn-brand" href="#/golfplaetze" onClick={(e) => go('#/golfplaetze', e)} style={{ marginTop: 24 }}>Alle Golfplätze</a>
      </div>
    );
  }

  const evs = d.eventsForVenue(v.venue);
  const rating = vnAvgRating(evs);

  return (
    <div data-screen-label="Golfplatz-Detail">
      <MobileBar active="golfplaetze">
        <a className="ev-msearch ev-maction" href="#/individuell" onClick={(e) => go('#/individuell', e)}>
          <span>Individuelles Event anfragen</span>
          <span className="ev-maction-arrow"><VnIcon name="arrow" size={14} /></span>
        </a>
      </MobileBar>

      {/* hero */}
      <section className="vd-hero">
        <div className="vd-hero-photo" style={{ backgroundImage: `url('${window.A(v.image)}')` }}>
          <div className="vd-hero-scrim" />
          <div className="vd-hero-content">
            <a className="vd-back" href="#/golfplaetze" onClick={(e) => go('#/golfplaetze', e)}><VnIcon name="back" size={15} /> Alle Golfplätze</a>
            <div className="vd-hero-region">{v.region} · {v.city}</div>
            <h1 className="vd-hero-h">{v.name}</h1>
            <div className="vd-hero-meta">
              <span><VnIcon name="holes" size={15} /> {v.holes} Loch · Par {v.par}</span>
              {rating != null && <><span className="dot">·</span><span><VnIcon name="star" size={14} /> {rating.toFixed(1)}</span></>}
              <span className="dot">·</span>
              <span>{evs.length} {evs.length === 1 ? 'Format' : 'Formate'}</span>
            </div>
          </div>
        </div>
      </section>

      {/* intro + facts */}
      <section className="vd-intro">
        <p className="vd-blurb">{v.blurb}</p>
        <div className="vd-facts">
          <div className="vd-fact"><div className="l">Anlage</div><div className="v">{v.holes} Loch · Par {v.par}</div></div>
          <div className="vd-fact"><div className="l">Region</div><div className="v">{v.region}</div></div>
          <div className="vd-fact"><div className="l">Adresse</div><div className="v">{v.address}</div></div>
          <div className="vd-fact"><div className="l">Formate</div><div className="v">{evs.length} buchbar</div></div>
        </div>
      </section>

      {/* offers */}
      <section className="vd-offers">
        <div className="vd-offers-head">
          <div className="mk-eyebrow">An diesem Platz</div>
          <h2 className="vd-offers-h">Alle Formate auf {v.name}</h2>
        </div>
        {evs.length ? (
          <div className="fg-grid">
            {evs.map(e => <window.EventCard key={e.id} event={e} onClick={() => go('#/events/' + e.slug)} />)}
          </div>
        ) : (
          <p className="vd-noevents">Für diesen Platz sind aktuell keine festen Formate gelistet — aber wir planen jederzeit ein individuelles Event hier.</p>
        )}
      </section>

      {/* individuelles event CTA */}
      <section className="vd-ind">
        <div className="vd-ind-inner">
          <div className="vd-ind-text">
            <div className="mk-eyebrow" style={{ color: 'var(--fairway-700)' }}>Maßgeschneidert</div>
            <h2 className="vd-ind-h">Etwas Eigenes auf {v.city}?</h2>
            <p className="vd-ind-p">Sommerfest, Turnier, Kundentag oder Offsite — erzähl uns kurz, was ihr vorhabt. Wir stimmen Termin und Ablauf direkt mit dem Platz ab.</p>
          </div>
          <a className="fg-btn-brand lg" href="#/individuell" onClick={(e) => go('#/individuell', e)}>
            Individuelles Event anfragen <span className="fg-arrow"><VnIcon name="arrow" /></span>
          </a>
        </div>
      </section>
    </div>
  );
}
window.VenuePage = VenuePage;
