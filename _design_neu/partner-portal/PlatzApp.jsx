/* eslint-disable */
/* GC München West — Platz (course profile), Platz.html */

const PlatzHero = () => (
  <section className="hero">
    <div className="hero-photo" style={{ backgroundImage: "url(design-system/imagery/hero-fairway-wide.jpg)" }}>
      <div className="hero-scrim"></div>
      <div className="hero-top">
        <div className="hero-status"><span className="dot"></span> Öffentlich sichtbar auf Firmengolf</div>
        <div className="hero-actions">
          <a className="hero-btn" href="../index.html#/events" target="_blank" rel="noopener"><Icon name="external" size={14} /> Öffentliches Profil</a>
          <a className="hero-btn solid" onClick={() => window.fgToast("Titelbild ändern")}><Icon name="image" size={14} /> Titelbild ändern</a>
        </div>
      </div>
      <div className="hero-body">
        <div className="hero-id">
          <div className="hero-monogram">{COURSE.monogram}</div>
          <div className="hero-text">
            <div className="hero-eyebrow">Dein Platz auf Firmengolf</div>
            <h1 className="hero-name">GC München <em>West</em></h1>
            <div className="hero-meta">
              <span><Icon name="pin" size={14} /> {COURSE.loc}</span>
              <span className="dot">·</span>
              <span><Icon name="flag" size={14} /> {COURSE.holes} Loch · Par {COURSE.par}</span>
              <span className="dot">·</span>
              <span>Mitglied seit {COURSE.memberSince}</span>
            </div>
          </div>
        </div>
        <a className="hero-cta-card" href="#bewertungen">
          <div className="lbl">Bewertung</div>
          <div className="val">4,8 ★ · 32 Bewertungen</div>
          <span style={{ marginTop: 10, display: "inline-flex", alignItems: "center", gap: 6, fontSize: 13, fontWeight: 500, borderTop: "1px solid rgba(255,255,255,0.22)", paddingTop: 10, width: "100%" }}>
            Alle Bewertungen ansehen <Icon name="chevronRight" size={14} />
          </span>
        </a>
      </div>
    </div>
  </section>
);

const PlatzAbout = () => (
  <section className="section">
    <div className="section-head">
      <div>
        <div className="eyebrow">So sehen dich Firmen</div>
        <h2>Über deinen <em>Platz</em></h2>
        <p>Beschreibung und Eckdaten erscheinen auf deinem öffentlichen Firmengolf-Profil.</p>
      </div>
      <div className="actions">
        <a className="btn btn-ghost" onClick={() => window.fgToast("Beschreibung bearbeiten")}><Icon name="edit" size={14} /> Beschreibung bearbeiten</a>
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

const PlatzGallery = () => (
  <section className="section" id="galerie">
    <div className="section-head">
      <div>
        <div className="eyebrow">Bildergalerie</div>
        <h2>Fotos deines <em>Platzes</em></h2>
        <p>Eigene Fotos vom Platz wirken am stärksten — sie erscheinen auf deinem Profil und in deinen Angeboten.</p>
      </div>
      <div className="actions">
        <a className="btn btn-brand" onClick={() => window.fgToast("Foto hochladen")}><Icon name="upload" size={14} /> Foto hochladen</a>
      </div>
    </div>
    <div className="gallery-grid">
      {GALLERY.map((g, i) => (
        <button className="gallery-item" key={i} style={{ backgroundImage: `url(${g.img})` }} onClick={() => window.fgToast(g.cap)}>
          <span className="gallery-cap">{g.cap}</span>
        </button>
      ))}
      <button className="gallery-item add" onClick={() => window.fgToast("Foto hochladen")}>
        <Icon name="plus" size={22} sw={2} />
        <span style={{ fontSize: 13, fontWeight: 500 }}>Foto hinzufügen</span>
      </button>
    </div>
  </section>
);

const PlatzAmenities = () => (
  <section className="section">
    <div className="section-head">
      <div>
        <div className="eyebrow">Ausstattung</div>
        <h2>Was euch <em>erwartet</em></h2>
      </div>
      <div className="actions">
        <a className="btn btn-ghost" onClick={() => window.fgToast("Ausstattung bearbeiten")}><Icon name="edit" size={14} /> Bearbeiten</a>
      </div>
    </div>
    <div className="amenities">
      {AMENITIES.map((a, i) => (
        <div className="amenity" key={i}>
          <span className="ic-wrap"><Icon name={a.icon} size={20} /></span>
          <div>
            <div className="l">{a.label}</div>
            <div className="s">{a.sub}</div>
          </div>
        </div>
      ))}
    </div>
  </section>
);

const PlatzReviews = () => (
  <section className="section" id="bewertungen">
    <div className="section-head">
      <div>
        <div className="eyebrow">Was Firmen sagen</div>
        <h2>Eure <em>Bewertungen</em></h2>
        <p>Durchschnitt 4,8 von 5 · basierend auf 32 Bewertungen.</p>
      </div>
    </div>
    <div className="two-col">
      <div className="panel">
        {REVIEWS.map(r => (
          <div className="review" key={r.id}>
            <div className="review-head">
              <span className="review-company">{r.company}</span>
              <Stars n={r.stars} />
            </div>
            <p className="review-quote">„{r.quote}"</p>
            <div className="review-foot">
              <span>{r.author}</span><span className="dot">·</span>
              <span>{r.event}</span><span className="dot">·</span>
              <span>{r.date}</span>
            </div>
          </div>
        ))}
      </div>
      <div className="panel">
        <div className="panel-head"><h3 style={{ fontSize: 18 }}>Ansprechpartner</h3></div>
        <div style={{ display: "flex", alignItems: "center", gap: 14, marginBottom: 16 }}>
          <span className="nav-avatar" style={{ width: 48, height: 48, fontSize: 16 }}>SR</span>
          <div>
            <div style={{ fontFamily: "var(--font-display)", fontSize: 17, fontWeight: 500, color: "var(--ink-900)" }}>{COURSE.contact}</div>
            <div style={{ fontSize: 13, color: "var(--ink-500)" }}>{COURSE.contactRole}</div>
          </div>
        </div>
        <div className="facts" style={{ background: "var(--paper-200)" }}>
          <div className="fact-row"><span className="lbl">E-Mail</span><span className="val">events@gc-muenchen-west.de</span></div>
          <div className="fact-row"><span className="lbl">Telefon</span><span className="val">+49 8121 44 0</span></div>
          <div className="fact-row"><span className="lbl">Anreise</span><span className="val">{(COURSE.facts.find(f => f.lbl === "Anreise") || {}).val}</span></div>
        </div>
        <a className="btn btn-ghost btn-sm" style={{ marginTop: 14 }} onClick={() => window.fgToast("Kontaktdaten bearbeiten")}><Icon name="edit" size={13} /> Kontaktdaten bearbeiten</a>
      </div>
    </div>
  </section>
);

const PlatzApp = () => (
  <div>
    <TopNav activeTab="platz" />
    <div className="page-wide">
      <PlatzHero />
      <PlatzAbout />
      <PlatzGallery />
      <PlatzAmenities />
      <PlatzReviews />
      <Footer />
    </div>
  </div>
);

const platzRoot = ReactDOM.createRoot(document.getElementById("app"));
platzRoot.render(<PlatzApp />);
