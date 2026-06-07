/* eslint-disable */
/* GC München West — Partner Profile / Owner Dashboard */

const { useState } = React;

// ---------- HERO ----------
const Hero = ({ minimal }) => (
  <section className={`hero ${minimal ? "is-minimal" : ""}`}>
    <div
      className="hero-photo"
      style={{ backgroundImage: "url(design-system/imagery/hero-meadow.jpg)" }}
    >
      <div className="hero-scrim"></div>

      <div className="hero-top">
        <div className="hero-status">
          <span className="dot"></span>
          Live auf Firmengolf · seit März 2024
        </div>
        <div className="hero-actions">
          <a className="hero-btn" href="../index.html#/events" target="_blank" rel="noopener">
            <Icon name="external" size={14} />
            Öffentliches Profil
          </a>
          <a className="hero-btn solid" href="Platz.html">
            <Icon name="edit" size={14} />
            Profil bearbeiten
          </a>
        </div>
      </div>

      <div className="hero-body">
        <div className="hero-id">
          <div className="hero-monogram">{COURSE.monogram}</div>
          <div className="hero-text">
            <div className="hero-eyebrow">Dein Platz auf Firmengolf</div>
            <h1 className="hero-name">
              GC München <em>West</em>
            </h1>
            <div className="hero-meta">
              <span><Icon name="pin" size={14} /> {COURSE.loc}</span>
              <span className="dot">·</span>
              <span><Icon name="flag" size={14} /> {COURSE.holes} Loch · Par {COURSE.par}</span>
              <span className="dot">·</span>
              <span><Icon name="star" size={14} sw={0} style={{ fill: "currentColor" }} /> 4,8 (32 Bewertungen)</span>
            </div>
          </div>
        </div>

        <div className="hero-cta-card">
          <div className="lbl">Nächste Buchung</div>
          <div className="val">Mi 5. Juni — Kerber & Söhne</div>
          <a href="Anfragen.html">
            Zur Anfrage <Icon name="chevronRight" size={14} />
          </a>
        </div>
      </div>
    </div>

    {/* Minimal variant fallback (rendered only when .hero.is-minimal hides .hero-photo) */}
    <div className="hero-mini">
      <div className="hero-monogram">{COURSE.monogram}</div>
      <div className="hero-text">
        <div className="hero-eyebrow">Dein Platz auf Firmengolf</div>
        <h1 className="hero-name">GC München <em>West</em></h1>
        <div className="hero-meta">
          <span><Icon name="pin" size={14} /> {COURSE.loc}</span>
          <span className="dot">·</span>
          <span><Icon name="flag" size={14} /> {COURSE.holes} Loch · Par {COURSE.par}</span>
        </div>
      </div>
    </div>
  </section>
);

// ---------- STATS ----------
const StatCard = ({ s }) => (
  <div className="stat">
    <div className="stat-lbl">{s.lbl}</div>
    <div className="stat-val">
      {s.val}{s.unit && <span className="unit">{s.unit}</span>}
    </div>
    <div className="stat-foot">
      <span className={`stat-delta ${s.deltaSign}`}>
        <Icon name={s.deltaSign === "up" ? "arrowUp" : "arrowUp"} size={11} style={{ transform: s.deltaSign === "down" ? "rotate(180deg)" : "none" }} />
        {s.delta}%
      </span>
      <span>{s.note}</span>
    </div>
    <div className="stat-spark">
      <Sparkline d={
        s.id === "views" ? "M0,30 C12,28 18,18 30,14 C42,10 52,18 64,12 C72,8 80,6 88,2" :
        s.id === "requests" ? "M0,28 C10,24 22,22 32,18 C44,14 56,20 66,10 C76,4 82,8 88,6" :
        s.id === "bookings" ? "M0,32 C12,30 24,26 36,20 C46,16 56,12 66,14 C76,16 82,8 88,4" :
                              "M0,8 C12,10 24,14 36,16 C46,18 56,22 66,20 C76,18 82,24 88,28"
      } />
    </div>
  </div>
);

const Stats = () => (
  <div className="stats">
    {STATS.map(s => <StatCard key={s.id} s={s} />)}
  </div>
);

// ---------- offer status lifecycle ----------
const OFFER_STATUS = {
  published:     { label: "Veröffentlicht", cls: "published" },
  "in-pruefung": { label: "In Prüfung",     cls: "pruefung" },
  paused:        { label: "Pausiert",       cls: "paused" },
  draft:         { label: "Entwurf",        cls: "draft" },
};
function offerActions(status) {
  switch (status) {
    case "published":   return [["pause", "Pausieren", "clock"], ["preview", "Vorschau", "eye"], ["remove", "Entfernen", "x"]];
    case "paused":      return [["activate", "Reaktivieren", "check"], ["preview", "Vorschau", "eye"], ["remove", "Entfernen", "x"]];
    case "in-pruefung": return [["preview", "Vorschau", "eye"], ["remove", "Zurückziehen", "x"]];
    case "draft":       return [["publish", "Veröffentlichen", "check"], ["remove", "Entfernen", "x"]];
    default:            return [["remove", "Entfernen", "x"]];
  }
}

// ---------- CATEGORY CARD ----------
const CatCard = ({ c, menuOpen, onMenu, onAction }) => {
  if (c.status === "empty") {
    return (
      <a className="cat is-empty" href={`Angebot.html?c=${c.id}&new=1`}>
        <div className="empty-icon"><Icon name="plus" size={24} sw={2} /></div>
        <div className="cat-cat-chip"><Icon name={c.icon} size={13} /> {c.cat}</div>
        <div className="cat-title">Noch kein Angebot</div>
        <div className="cat-sub">Lade ein erstes Angebot hoch, damit Firmen dich in dieser Kategorie buchen können.</div>
        <span className="btn btn-brand btn-sm">
          <Icon name="plus" size={14} sw={2} /> Angebot erstellen
        </span>
      </a>
    );
  }

  const st = OFFER_STATUS[c.status] || OFFER_STATUS.draft;
  return (
    <div className="cat">
      <div className="cat-photo" style={{ backgroundImage: `url(${c.img})` }}>
        <div className="cat-cat-chip"><Icon name={c.icon} size={13} /> {c.cat}</div>
        <div className={`cat-status ${st.cls}`}>
          <span className="dot"></span>
          {st.label}
        </div>
        <button className="cat-menu-btn" onClick={(e) => { e.preventDefault(); onMenu(menuOpen ? null : c.id); }} aria-label="Aktionen">
          <Icon name="more" size={16} />
        </button>
        {menuOpen && (
          <div className="cat-menu" onClick={(e) => e.preventDefault()}>
            <a className="cat-menu-item" href={`Angebot.html?c=${c.id}`}><Icon name="edit" size={14} /> Bearbeiten</a>
            {offerActions(c.status).map(([act, label, icon]) => (
              <button key={act} className={"cat-menu-item " + (act === "remove" ? "danger" : "")}
                onClick={() => onAction(c.id, act)}>
                <Icon name={icon} size={14} /> {label}
              </button>
            ))}
          </div>
        )}
      </div>
      <div className="cat-body">
        <div className="cat-title">{c.title}</div>
        <div className="cat-sub">{c.sub}</div>
        <div className="cat-stats">
          <span className="chip"><Icon name="clock" size={11} /> {c.duration}</span>
          <span className="chip"><Icon name="users" size={11} /> {c.group} Pers.</span>
          {c.status === "published" && (
            <span className="chip"><Icon name="eye" size={11} /> {c.views.toLocaleString("de-DE")}</span>
          )}
        </div>
        <div className="cat-foot">
          <div className="cat-price">
            <span className="from">ab</span>
            {c.price} €<span className="unit">/{c.unit}</span>
          </div>
          <a className="cat-edit" href={`Angebot.html?c=${c.id}`}>
            Bearbeiten <Icon name="chevronRight" size={13} />
          </a>
        </div>
      </div>
    </div>
  );
};

const CategoryGrid = () => {
  const { useState, useEffect } = React;
  const [offers, setOffers] = useState(CATEGORIES);
  const [menu, setMenu] = useState(null);

  useEffect(() => {
    const close = () => setMenu(null);
    document.addEventListener("click", close);
    return () => document.removeEventListener("click", close);
  }, []);

  const onAction = (id, act) => {
    setMenu(null);
    const o = offers.find(x => x.id === id);
    if (act === "remove") {
      setOffers(os => os.filter(x => x.id !== id));
      window.fgToast((o?.title || "Angebot") + " entfernt");
      return;
    }
    const next = act === "pause" ? "paused"
      : act === "activate" ? "published"
      : act === "publish" ? "in-pruefung"
      : null;
    if (act === "preview") { window.fgToast("Vorschau wird geöffnet"); return; }
    if (next) {
      setOffers(os => os.map(x => x.id === id ? { ...x, status: next } : x));
      const msg = next === "paused" ? "pausiert — für Firmen nicht mehr sichtbar"
        : next === "published" ? "wieder live"
        : "zur Prüfung eingereicht";
      window.fgToast((o?.title || "Angebot") + " " + msg);
    }
  };

  return (
    <section className="section" id="angebote">
      <div className="section-head">
        <div>
          <div className="eyebrow">Pro Veranstaltungstyp ein Angebot</div>
          <h2>Deine <em>Event-Angebote</em></h2>
          <p>Jede Kategorie eigene Konditionen, eigenes Foto, eigene Beschreibung. Firmen sehen genau das auf deinem öffentlichen Profil.</p>
        </div>
        <div className="actions">
          <a className="btn btn-ghost" href="Platz.html#galerie"><Icon name="image" size={14} /> Galerie verwalten</a>
          <a className="btn btn-brand" href="Angebot.html?new=1"><Icon name="plus" size={14} sw={2} /> Neues Angebot</a>
        </div>
      </div>
      <div className="cat-grid">
        {offers.map(c => (
          <div key={c.id} onClick={(e) => e.stopPropagation()}>
            <CatCard c={c} menuOpen={menu === c.id} onMenu={setMenu} onAction={onAction} />
          </div>
        ))}
      </div>
    </section>
  );
};

// ---------- INBOX + REVIEWS ----------
const InboxRow = ({ r }) => (
  <a className="inbox-row" href="Anfragen.html">
    <div className={`inbox-avatar ${r.avatar}`}>{r.initials}</div>
    <div className="inbox-body">
      <div className="inbox-top">
        {r.company}
        {r.isNew && <span className="new">Neu</span>}
        <span style={{ color: "var(--ink-400)", fontWeight: 400, fontSize: 12 }}>·</span>
        <span style={{ color: "var(--ink-500)", fontWeight: 400, fontSize: 13 }}>{r.eventType}</span>
      </div>
      <div className="inbox-sub">„{r.msg}"</div>
    </div>
    <div className="inbox-meta">
      <span>{r.when}</span>
      <span className={`pill ${r.statusColor}`}>
        {r.statusColor === "green" && <span className="dot"></span>}
        {r.status}
      </span>
    </div>
  </a>
);

const Inbox = () => (
  <div className="panel">
    <div className="panel-head">
      <h3>Eingegangene Anfragen</h3>
      <a className="more" href="Anfragen.html">Alle ansehen <Icon name="chevronRight" size={13} /></a>
    </div>
    <div className="inbox-list">
      {INBOX.map(r => <InboxRow key={r.id} r={r} />)}
    </div>
  </div>
);

const Review = ({ r }) => (
  <div className="review">
    <div className="review-head">
      <span className="review-company">{r.company}</span>
      <Stars n={r.stars} />
    </div>
    <p className="review-quote">„{r.quote}"</p>
    <div className="review-foot">
      <span>{r.author}</span>
      <span className="dot">·</span>
      <span>{r.event}</span>
      <span className="dot">·</span>
      <span>{r.date}</span>
    </div>
  </div>
);

const Reviews = () => (
  <div className="panel">
    <div className="panel-head">
      <h3>Was Firmen sagen</h3>
      <a className="more" href="Platz.html#bewertungen">Alle Bewertungen <Icon name="chevronRight" size={13} /></a>
    </div>
    {REVIEWS.map(r => <Review key={r.id} r={r} />)}
  </div>
);

const InboxAndReviews = () => (
  <section className="section">
    <div className="section-head">
      <div>
        <div className="eyebrow">Was diese Woche passiert</div>
        <h2>Inbox & <em>Bewertungen</em></h2>
      </div>
    </div>
    <div className="two-col">
      <Inbox />
      <Reviews />
    </div>
  </section>
);

// ---------- ABOUT ----------
const About = () => (
  <section className="section">
    <div className="section-head">
      <div>
        <div className="eyebrow">So sehen dich Firmen</div>
        <h2>Über deinen <em>Platz</em></h2>
        <p>Der Text und die Eckdaten erscheinen auf deinem öffentlichen Firmengolf-Profil.</p>
      </div>
      <div className="actions">
        <a className="btn btn-ghost" href="Platz.html"><Icon name="edit" size={14} /> Beschreibung bearbeiten</a>
      </div>
    </div>
    <div className="about">
      <div className="about-main">
        {COURSE.about.map((p, i) => <p key={i}>{p}</p>)}
      </div>
      <div className="facts">
        <h4>Eckdaten</h4>
        {COURSE.facts.map((f, i) => (
          <div className="fact-row" key={i}>
            <span className="lbl">{f.lbl}</span>
            <span className="val">{f.val}</span>
          </div>
        ))}
      </div>
    </div>
  </section>
);

// ---------- ROOT ----------
const TWEAK_DEFAULTS = /*EDITMODE-BEGIN*/{
  "heroVariant": "photo",
  "density": "luftig",
  "accent": "fairway",
  "courseName": "GC München West"
}/*EDITMODE-END*/;

const App = () => {
  const [t, setTweak] = useTweaks(TWEAK_DEFAULTS);
  const minimal = t.heroVariant === "minimal";
  const densityClass = t.density === "kompakt" ? "density-compact" : "density-luftig";
  const accentClass = `accent-${t.accent}`;

  return (
    <div className={`${densityClass} ${accentClass}`}>
      <TopNav activeTab="uebersicht" />

      <div className="page-wide">
        <Hero minimal={minimal} />
        <Stats />
        <CategoryGrid />
        <InboxAndReviews />
        <About />
        <Footer />
      </div>

      <TweaksPanel title="Tweaks">
        <TweakSection label="Layout">
          <TweakRadio
            label="Hero"
            value={t.heroVariant}
            onChange={v => setTweak("heroVariant", v)}
            options={[
              { value: "photo",   label: "Mit Foto" },
              { value: "minimal", label: "Minimal" },
            ]}
          />
          <TweakRadio
            label="Dichte"
            value={t.density}
            onChange={v => setTweak("density", v)}
            options={[
              { value: "luftig",   label: "Luftig" },
              { value: "kompakt",  label: "Kompakt" },
            ]}
          />
        </TweakSection>
        <TweakSection label="Akzent">
          <TweakRadio
            label="Farbe"
            value={t.accent}
            onChange={v => setTweak("accent", v)}
            options={[
              { value: "fairway", label: "Fairway" },
              { value: "clay",    label: "Clay" },
              { value: "sand",    label: "Ink" },
            ]}
          />
        </TweakSection>
      </TweaksPanel>
    </div>
  );
};

const root = ReactDOM.createRoot(document.getElementById("app"));
root.render(<App />);
