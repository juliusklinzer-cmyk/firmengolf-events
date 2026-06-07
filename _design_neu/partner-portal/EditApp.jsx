/* eslint-disable */
/* GC München West — Offer detail/edit page (Angebot.html) */

const { useState: useStateEdit } = React;

// All services a course can include in an offer (dropdown in "Inkludierte Leistungen").
const SERVICE_CATALOGUE = [
  "Shuttle-Service", "Schnupperkurs", "Meetingraum 2 Stunden", "Meetingraum (ganzer Tag)",
  "Kaffee & Kuchen", "Lunch", "Abendessen", "Begrüßungsgetränk",
  "Greenfee Range & Übungsanlage", "Leihschläger & Bälle", "PGA-Coaching",
  "9-Loch-Runde", "18-Loch-Turnier", "Putting-Challenge",
  "Halfway-Verpflegung", "Urkunde & Foto-Erinnerung", "Übernachtung",
];

const DAYFLOW_EXAMPLE =
`Wir holen euch um 9:00 Uhr direkt in eurer Firma ab.

Treffpunkt ist der Pro-Shop — dort begrüßen wir euch und stellen euch Platz und Anlage kurz vor.

9:30 Uhr: Es geht los mit dem ersten Teil des Schnupperkurses auf der Range (ca. 1 Stunde) mit unseren PGA-Pros.

Anschließend steht euch der Meetingraum für eure Besprechung zur Verfügung.

Mittags: gemeinsames Lunch mit Vor- und Nachspeise auf der Terrasse.

Am Nachmittag folgt der zweite Teil des Schnupperkurses — jetzt geht's aufs Grün.

Zum Abschluss bringt euch unser Shuttle wieder bequem nach Hause.`;

// Contacts pre-registered during onboarding (managed in Team.html).
const REGISTERED_CONTACTS = (window.COURSE_TEAM || [])
  .filter(t => !t.you)
  .map(t => ({ role: t.role, name: t.name, email: t.email || "" }));

// pick offer by ?c= param, fallback to first published
const getOffer = () => {
  const id = new URLSearchParams(location.search).get("c");
  return CATEGORIES.find(c => c.id === id && c.status !== "empty")
      || CATEGORIES.find(c => c.id === id)
      || CATEGORIES[0];
};

const Field = ({ label, hint, children, help }) => (
  <div className="field">
    <div className="field-label">{label}</div>
    {children}
    {help && <div className="field-help-inline">{help}</div>}
  </div>
);

const Include = ({ children }) => (
  <div className="include-row">
    <span className="check"><Icon name="check" size={11} sw={2.5} /></span>
    <span>{children}</span>
    <button className="x"><Icon name="x" size={14} /></button>
  </div>
);

const WEEKDAYS = ["Mo", "Di", "Mi", "Do", "Fr", "Sa", "So"];

const EditApp = () => {
  const offer = getOffer();
  const isEmpty = offer.status === "empty";
  const isNew = new URLSearchParams(location.search).get("new") === "1" || isEmpty;

  const [active, setActive] = useStateEdit(["Mo", "Di", "Mi", "Do", "Fr"]);
  const toggleDay = d => setActive(a => a.includes(d) ? a.filter(x => x !== d) : [...a, d]);
  const [published, setPublished] = useStateEdit(offer.status === "published");
  const [showRelease, setShowRelease] = useStateEdit(false);
  const [estatus, setEstatus] = useStateEdit(offer.status === "published" ? "published" : (offer.status || "draft"));
  const [statusMenu, setStatusMenu] = useStateEdit(false);
  React.useEffect(() => {
    const c = (e) => { if (!(e.target.closest && e.target.closest(".estatus-menu-wrap"))) setStatusMenu(false); };
    document.addEventListener("click", c);
    return () => document.removeEventListener("click", c);
  }, []);
  const ESTATUS_META = {
    published: ["Veröffentlicht", "published"], "in-pruefung": ["In Prüfung", "pruefung"],
    paused: ["Pausiert", "paused"], draft: ["Entwurf", "draft"],
  };
  const estatusActions = {
    published:     [["paused", "Pausieren"], ["draft", "Auf Entwurf setzen"]],
    paused:        [["published", "Reaktivieren"]],
    "in-pruefung": [["draft", "Zurückziehen"]],
    draft:         [["in-pruefung", "Zur Prüfung einreichen"]],
  };
  const changeStatus = (s) => {
    setEstatus(s); setStatusMenu(false);
    const lbl = { paused: "pausiert — für Firmen nicht mehr sichtbar", published: "wieder veröffentlicht", draft: "auf Entwurf gesetzt", "in-pruefung": "zur Prüfung eingereicht" }[s];
    window.fgToast("Angebot " + lbl);
  };
  const deleteOffer = () => { window.fgToast("Angebot gelöscht"); setTimeout(() => { location.href = "Profil.html"; }, 700); };
  const [includes, setIncludes] = useStateEdit(["Shuttle-Service", "Schnupperkurs", "Meetingraum 2 Stunden", "Kaffee & Kuchen", "Lunch"]);
  const [addOpen, setAddOpen] = useStateEdit(false);
  const addInclude = (s) => { setIncludes(xs => xs.includes(s) ? xs : [...xs, s]); setAddOpen(false); };
  const removeInclude = (s) => setIncludes(xs => xs.filter(x => x !== s));
  const [priceMode, setPriceMode] = useStateEdit("gesamt"); // 'gesamt' | 'einzel'
  const [gesamtBasis, setGesamtBasis] = useStateEdit("person"); // 'person' | 'pauschal'
  const [gesamtAmount, setGesamtAmount] = useStateEdit("");
  const [lineItems, setLineItems] = useStateEdit([{ label: "Golflehrer", cost: "80" }, { label: "Meetingraum", cost: "50" }]);
  const setItem = (i, k, v) => setLineItems(xs => xs.map((it, j) => j === i ? { ...it, [k]: v } : it));
  const addItem = () => setLineItems(xs => [...xs, { label: "", cost: "" }]);
  const removeItem = (i) => setLineItems(xs => xs.filter((_, j) => j !== i));
  const netSum = priceMode === "gesamt"
    ? Number(gesamtAmount || 0)
    : lineItems.reduce((s, it) => s + Number(it.cost || 0), 0);
  const priceUnit = (priceMode === "gesamt" && gesamtBasis === "person") ? "pro Person" : "gesamt";
  const fmtEur = (n) => "€" + (Math.round(n * 100) / 100).toLocaleString("de-DE");
  const [relDone, setRelDone] = useStateEdit(false);
  const [relMode, setRelMode] = useStateEdit("us"); // 'us' | 'approve'
  const [relPeople, setRelPeople] = useStateEdit([
    { role: "Unser Pro", name: "", email: "" },
    { role: "Gastronomie", name: "", email: "" },
  ]);
  const setPerson = (i, k, v) => setRelPeople(ps => ps.map((p, j) => j === i ? { ...p, [k]: v } : p));
  const addPerson = (role) => setRelPeople(ps => [...ps, { role: role || "Weitere Person", name: "", email: "" }]);
  const removePerson = (i) => setRelPeople(ps => ps.filter((_, j) => j !== i));
  const confirmRelease = () => {
    setPublished(true);
    setRelDone(true);
  };
  const closeRelease = () => { setShowRelease(false); setRelDone(false); };

  const initial = isEmpty ? {
    title: "",
    sub: "",
    price: "",
    duration: "",
    minP: "",
    maxP: "",
    img: "",
  } : {
    title: offer.title,
    sub: offer.sub,
    price: offer.price,
    duration: offer.duration,
    minP: offer.group?.split("–")[0] || "",
    maxP: offer.group?.split("–")[1] || "",
    img: offer.img,
  };

  return (
    <div>
      <TopNav activeTab="angebote" />

      <div className="page-wide edit-shell">
        <div className="crumbs">
          <a href="Profil.html">Übersicht</a>
          <span className="sep">›</span>
          <a href="Profil.html#angebote">Angebote</a>
          <span className="sep">›</span>
          <span className="cur">{offer.cat}</span>
        </div>

        <div className="edit-head">
          <div className="edit-head-left">
            <span className="edit-cat-chip">
              <Icon name={offer.icon} size={13} /> {offer.cat}
            </span>
            <h1 className="edit-title">
              {isNew ? <>Dein <em>Schnupperkurs</em>-Angebot</> : initial.title}
            </h1>
            <div className="edit-status-row">
              {published ? (
                <span className="pill green"><span className="dot"></span> Veröffentlicht</span>
              ) : (
                <span className="pill" style={{ background: "var(--paper-200)", color: "var(--ink-700)" }}>
                  Entwurf — nicht öffentlich
                </span>
              )}
              <span>Zuletzt bearbeitet: gestern um 17:42</span>
              {!isNew && (
                <>
                  <span style={{ opacity: 0.4 }}>·</span>
                  <span>{offer.views?.toLocaleString("de-DE") || 0} Aufrufe</span>
                </>
              )}
            </div>
          </div>
          <div className="edit-actions">
            <a className="btn btn-quiet" href="Profil.html">
              <Icon name="chevronLeft" size={14} /> Zurück
            </a>
            <a className="btn btn-ghost" href="../index.html#/events" target="_blank" rel="noopener">
              <Icon name="eye" size={14} /> Vorschau
            </a>
          </div>
        </div>

        <div className="edit-grid">
          {/* ───────── LEFT (form) ───────── */}
          <div>
            {/* Cover */}
            <div className="form-section">
              <h3>Coverbild</h3>
              <p className="help">Quer-Format 16:9 oder 16:10, mind. 1600 px breit. Dieses Bild erscheint groß auf deinem Angebot.</p>

              <div className="cover-upload" style={ initial.img ? { backgroundImage: `url(${initial.img})` } : {} }>
                {!initial.img && (
                  <div className="cover-empty">
                    <div className="icon"><Icon name="upload" size={22} /></div>
                    <div style={{ fontWeight: 500, color: "var(--ink-900)" }}>Foto hochladen oder hierher ziehen</div>
                    <div style={{ fontSize: 12 }}>JPG / PNG · max. 8 MB</div>
                  </div>
                )}
                {initial.img && (
                  <div className="cover-actions">
                    <button className="btn btn-sm" onClick={() => window.fgToast("Foto tauschen")}><Icon name="upload" size={13} /> Foto tauschen</button>
                    <button className="btn btn-sm" onClick={() => window.fgToast("Zuschneiden")}><Icon name="edit" size={13} /> Zuschneiden</button>
                  </div>
                )}
              </div>
            </div>

            {/* Basics */}
            <div className="form-section">
              <h3>Titel & Beschreibung</h3>
              <p className="help">Wir empfehlen einen Titel, der ein Gefühl verspricht — nicht ein Produkt beschreibt.</p>

              <Field label="Titel" help="Max. 60 Zeichen. Sentence case, keine ALL CAPS.">
                <input className="field-input" defaultValue={initial.title} placeholder="z.B. Erster Schwung — Schnupperkurs für Teams" />
              </Field>
              <Field label="Kurzbeschreibung" help="Max. 160 Zeichen. Wird als Vorschau in Suchergebnissen angezeigt.">
                <textarea className="field-textarea" defaultValue={initial.sub} placeholder="Was erwartet die Gäste in einem Satz?" style={{ minHeight: 80 }} />
              </Field>
            </div>

            {/* Includes + Tagesablauf */}
            <div className="form-section">
              <h3>Inkludierte Leistungen</h3>
              <p className="help">Wähl aus, was in diesem Angebot enthalten ist — vorausgewählt sind die Leistungen aus deiner Anmeldung. Firmen filtern danach.</p>

              <div className="inc-chips">
                {includes.map(s => (
                  <span className="inc-chip" key={s}>
                    <Icon name="check" size={12} sw={2.5} /> {s}
                    <button className="inc-chip-x" onClick={() => removeInclude(s)} aria-label="Entfernen"><Icon name="x" size={12} /></button>
                  </span>
                ))}
                <div className="inc-add-wrap">
                  <button className="inc-add-btn" onClick={() => setAddOpen(o => !o)}>
                    <Icon name="plus" size={14} sw={2.2} /> Leistung hinzufügen
                  </button>
                  {addOpen && (
                    <div className="inc-menu">
                      {SERVICE_CATALOGUE.filter(s => !includes.includes(s)).map(s => (
                        <button key={s} className="inc-menu-item" onClick={() => addInclude(s)}>{s}</button>
                      ))}
                      {SERVICE_CATALOGUE.filter(s => !includes.includes(s)).length === 0 && (
                        <div className="inc-menu-empty">Alle Leistungen schon hinzugefügt.</div>
                      )}
                    </div>
                  )}
                </div>
              </div>

              <div style={{ marginTop: 24 }}>
                <Field label="So läuft der Tag ab" help="Beschreib den Ablauf Schritt für Schritt — von der Ankunft bis zur Heimfahrt. Dieser Text erscheint auf der Event-Seite.">
                  <textarea className="field-textarea" defaultValue={DAYFLOW_EXAMPLE} style={{ minHeight: 240 }} />
                </Field>
              </div>
            </div>

            {/* Preis, Dauer, Teilnehmer */}
            <div className="form-section">
              <h3>Preis, Dauer & Teilnehmer</h3>
              <p className="help">B2B-Netto-Preis für das oben zusammengestellte Event.</p>

              <div className="price-modes">
                <button className={"price-mode " + (priceMode === "gesamt" ? "on" : "")} onClick={() => setPriceMode("gesamt")}>Gesamtpreis</button>
                <button className={"price-mode " + (priceMode === "einzel" ? "on" : "")} onClick={() => setPriceMode("einzel")}>Einzelauflistung</button>
              </div>

              {priceMode === "gesamt" ? (
                <div className="price-gesamt">
                  <Field label="Gesamtpreis für die Veranstaltung (netto)">
                    <div className="field-suffix">
                      <input className="field-input" value={gesamtAmount} onChange={e => setGesamtAmount(e.target.value.replace(/[^\d.,]/g, ''))} placeholder="2400" style={{ paddingRight: 36 }} />
                      <span className="suffix">€</span>
                    </div>
                  </Field>
                  <div className="price-basis">
                    <button className={"price-basis-btn " + (gesamtBasis === "person" ? "on" : "")} onClick={() => setGesamtBasis("person")}>pro Person</button>
                    <button className={"price-basis-btn " + (gesamtBasis === "pauschal" ? "on" : "")} onClick={() => setGesamtBasis("pauschal")}>Pauschal</button>
                  </div>
                </div>
              ) : (
                <div className="price-items">
                  {lineItems.map((it, i) => (
                    <div className="price-item" key={i}>
                      <input className="field-input" value={it.label} onChange={e => setItem(i, "label", e.target.value)} placeholder="Bezeichnung (z. B. Golflehrer)" />
                      <div className="field-suffix price-item-cost">
                        <input className="field-input" value={it.cost} onChange={e => setItem(i, "cost", e.target.value.replace(/[^\d.,]/g, ''))} placeholder="80" style={{ paddingRight: 30 }} />
                        <span className="suffix">€</span>
                      </div>
                      <button className="price-item-x" onClick={() => removeItem(i)} aria-label="Entfernen"><Icon name="x" size={15} /></button>
                    </div>
                  ))}
                  <button className="inc-add-btn" onClick={addItem}><Icon name="plus" size={14} sw={2.2} /> Kosten hinzufügen</button>
                </div>
              )}

              <div className="price-summary">
                <div className="price-sum-row">
                  <span>Netto-Summe</span>
                  <span className="v">{fmtEur(netSum)} <span className="u">{priceUnit === "pro Person" ? "pro Person" : ""}</span></span>
                </div>
                <div className="price-sum-row">
                  <span>+ Vermittlung Firmengolf (20%)</span>
                  <span className="v">{fmtEur(netSum * 0.2)}</span>
                </div>
                <div className="price-sum-row total">
                  <span>Gesamtpreis für das Unternehmen</span>
                  <span className="v">{fmtEur(netSum * 1.2)} <span className="u">{priceUnit === "pro Person" ? "pro Person" : ""}</span></span>
                </div>
              </div>

              <div className="field-row-3" style={{ marginTop: 22 }}>
                <Field label="Gesamtdauer (Stunden)">
                  <input className="field-input" defaultValue={initial.duration} placeholder="z. B. 6 Std." />
                </Field>
                <Field label="Min. Teilnehmer">
                  <input className="field-input" defaultValue={initial.minP} placeholder="6" />
                </Field>
                <Field label="Max. Teilnehmer">
                  <input className="field-input" defaultValue={initial.maxP} placeholder="24" />
                </Field>
              </div>
            </div>

            {/* Verfügbarkeit */}
            <div className="form-section">
              <h3>Verfügbarkeit</h3>
              <p className="help">An welchen Wochentagen und in welchen Zeitfenstern kann dieser Veranstaltungstyp <strong>angefragt</strong> werden?</p>

              <Field label="Wochentage">
                <div className="weekdays">
                  {WEEKDAYS.map(d => (
                    <button key={d} className={`weekday ${active.includes(d) ? "on" : ""}`} onClick={() => toggleDay(d)}>
                      {d}
                    </button>
                  ))}
                </div>
              </Field>

              <Field label="Zeitfenster">
                <div className="time-windows">
                  <div className="time-window">
                    <div>
                      <div style={{ fontSize: 12, color: "var(--ink-500)", marginBottom: 3 }}>Von</div>
                      <input className="field-input" defaultValue="09:00" style={{ padding: "6px 10px" }} />
                    </div>
                    <div>
                      <div style={{ fontSize: 12, color: "var(--ink-500)", marginBottom: 3 }}>Bis</div>
                      <input className="field-input" defaultValue="12:00" style={{ padding: "6px 10px" }} />
                    </div>
                    <button className="x"><Icon name="x" size={14} /></button>
                  </div>
                  <div className="time-window">
                    <div>
                      <div style={{ fontSize: 12, color: "var(--ink-500)", marginBottom: 3 }}>Von</div>
                      <input className="field-input" defaultValue="14:00" style={{ padding: "6px 10px" }} />
                    </div>
                    <div>
                      <div style={{ fontSize: 12, color: "var(--ink-500)", marginBottom: 3 }}>Bis</div>
                      <input className="field-input" defaultValue="17:00" style={{ padding: "6px 10px" }} />
                    </div>
                    <button className="x"><Icon name="x" size={14} /></button>
                  </div>
                </div>
                <button className="btn btn-quiet btn-sm" style={{ marginTop: 10 }} onClick={() => window.fgToast("Zeitfenster hinzugefügt")}>
                  <Icon name="plus" size={13} sw={2} /> Zeitfenster hinzufügen
                </button>
              </Field>

              <Field label="Vorlaufzeit für Anfragen" help="Wie viele Tage vor dem Wunschtermin muss eine Anfrage spätestens eingehen?">
                <select className="field-input">
                  <option>Mindestens 7 Tage</option>
                  <option>Mindestens 14 Tage</option>
                  <option>Mindestens 21 Tage</option>
                  <option>Nach Absprache</option>
                </select>
              </Field>
            </div>
          </div>

          {/* ───────── RIGHT RAIL ───────── */}
          <div className="edit-rail">
            <div className="rail-card">
              <h4>Status</h4>
              <div className="estatus-current">
                <span className={`estatus-pill ${ESTATUS_META[estatus][1]}`}><span className="dot"></span>{ESTATUS_META[estatus][0]}</span>
              </div>
              <div className="estatus-help">
                {estatus === "published" ? "Für alle Firmen sichtbar und anfragbar."
                  : estatus === "in-pruefung" ? "Wird von Firmengolf geprüft — bald sichtbar."
                  : estatus === "paused" ? "Vorübergehend offline — keine neuen Anfragen."
                  : "Nur du siehst dieses Angebot."}
              </div>
              <div className="estatus-menu-wrap" onClick={(e) => e.stopPropagation()}>
                <button className="btn btn-ghost btn-sm estatus-trigger" onClick={() => setStatusMenu(o => !o)}>
                  Status ändern <Icon name="chevronDown" size={14} />
                </button>
                {statusMenu && (
                  <div className="estatus-menu">
                    {estatusActions[estatus].map(([s, label]) => (
                      <button key={s} className="estatus-item" onClick={() => changeStatus(s)}>
                        <Icon name={s === "paused" ? "clock" : s === "published" ? "check" : "edit"} size={14} /> {label}
                      </button>
                    ))}
                    <button className="estatus-item danger" onClick={deleteOffer}>
                      <Icon name="x" size={14} /> Angebot löschen
                    </button>
                  </div>
                )}
              </div>
            </div>

            <div className="rail-card">
              <h4>Live-Vorschau</h4>
              <article className="fg-event ev-card2 preview-eventcard">
                <div className="fg-event-photo" style={ initial.img ? { backgroundImage: `url(${initial.img})` } : { background: "var(--paper-200)" } }>
                  <div className="fg-event-chips">
                    <span className="fg-photo-chip">{offer.cat}</span>
                  </div>
                  <span className="fg-event-heart" aria-hidden>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0E1310" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"><path d="M12 15V3"/><path d="M8 7l4-4 4 4"/><path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7"/></svg>
                  </span>
                </div>
                <div className="fg-event-body">
                  <div className="ev-card2-top">
                    <div className="fg-event-eyebrow">{offer.cat}{initial.duration ? ` · ${initial.duration}` : ""}</div>
                    <div className="fg-event-rating">
                      <Icon name="star" size={13} /> <span>4,8</span>
                    </div>
                  </div>
                  <h3 className="fg-event-title">{initial.title || "Titel deines Angebots"}</h3>
                  <div className="ev-card2-loc">
                    <Icon name="pin" size={13} />
                    <span>GC München West</span>
                    <span className="dot">·</span>
                    <span>{(initial.minP && initial.maxP) ? `${initial.minP}–${initial.maxP} Gäste` : "Teilnehmer"}</span>
                    <span className="dot">·</span>
                    <span>{initial.duration || "Dauer"}</span>
                  </div>
                  <div className="fg-event-foot ev-card2-foot">
                    <div className="fg-event-price">{initial.price ? `ab ${initial.price} €` : "Preis"}<span> /p.P.</span></div>
                    <span className="ev-card2-cta">Ansehen <Icon name="chevronRight" size={13} /></span>
                  </div>
                </div>
              </article>
              <a className="btn btn-quiet btn-sm" style={{ marginTop: 12, padding: "6px 0" }} href="../index.html#/events" target="_blank" rel="noopener">
                <Icon name="external" size={13} /> Auf öffentlichem Profil ansehen
              </a>
            </div>

            <div className="rail-card" style={{ background: "var(--fairway-700)", color: "var(--paper-100)", border: 0 }}>
              <h4 style={{ color: "var(--paper-100)" }}>Tipp vom Team</h4>
              <p style={{ fontSize: 14, lineHeight: 1.5, color: "rgba(251,250,246,0.85)", marginBottom: 12 }}>
                Angebote mit eigenem Foto vom Platz erhalten <strong style={{ color: "var(--paper-100)" }}>3× mehr Anfragen</strong> als solche mit Stock-Bildern.
              </p>
              <a style={{ fontSize: 13, fontWeight: 500, color: "var(--paper-100)", display: "inline-flex", alignItems: "center", gap: 6 }} href="Fotoleitfaden.html">
                Foto-Leitfaden lesen <Icon name="chevronRight" size={13} />
              </a>
            </div>
          </div>
        </div>

        {/* sticky action bar */}
        <div className="edit-actionbar">
          <span className="lbl">
            <span className="dot"></span>
            Ungespeicherte Änderungen
          </span>
          <div className="actions">
            <button className="btn btn-quiet" onClick={() => { location.href = "Profil.html"; }}>Verwerfen</button>
            <button className="btn btn-ghost" onClick={() => window.fgToast("Als Entwurf gespeichert")}>
              <Icon name="save" size={14} /> Als Entwurf speichern
            </button>
            <button className="btn btn-brand" onClick={() => setShowRelease(true)}>
              <Icon name="check" size={14} sw={2.5} /> {published ? "Änderungen veröffentlichen" : "Veröffentlichen"}
            </button>
          </div>
        </div>
      </div>

      {showRelease && (
        <div className="rel-overlay" onClick={closeRelease}>
          <div className="rel-sheet" onClick={e => e.stopPropagation()}>
            {relDone ? (
              <div className="rel-done">
                <div className="rel-done-ic"><Icon name="check" size={34} sw={2.4} /></div>
                <h2 className="rel-done-h">Dein {initial.title || offer.cat}-Angebot wird geprüft.</h2>
                <p className="rel-done-p">Wir schauen kurz drüber und schalten es frei. Du wirst benachrichtigt, sobald dein Event veröffentlicht ist.</p>
                {relMode === "approve" && (
                  <p className="rel-done-note">Bei Anfragen benachrichtigen wir automatisch alle hinterlegten Personen über die gewünschten Termine.</p>
                )}
                <div className="rel-done-actions">
                  <a className="btn btn-ghost" href="Profil.html">Zur Übersicht</a>
                  <button className="btn btn-brand" onClick={closeRelease}>Fertig</button>
                </div>
              </div>
            ) : (
            <React.Fragment>
            <div className="rel-bar">
              <span className="t">Termin-Freigabe einrichten</span>
              <button className="mail-close" onClick={closeRelease}><Icon name="x" size={16} /></button>
            </div>
            <div className="rel-body">
              <h2 className="rel-h">Wer muss den Termin freigeben, wenn eine Anfrage kommt?</h2>
              <p className="rel-lead">Das gilt für dieses Angebot. Du kannst es später jederzeit ändern.</p>

              <button className={"rel-opt " + (relMode === "us" ? "on" : "")} onClick={() => setRelMode("us")}>
                <span className="rel-radio" />
                <span>
                  <span className="rel-opt-t">Nur wir</span>
                  <span className="rel-opt-s">Anfragen kommen direkt bei dir an — du gibst Termine allein frei.</span>
                </span>
              </button>

              <button className={"rel-opt " + (relMode === "approve" ? "on" : "")} onClick={() => setRelMode("approve")}>
                <span className="rel-radio" />
                <span>
                  <span className="rel-opt-t">Ich gebe frei — gemeinsam mit weiteren Personen</span>
                  <span className="rel-opt-s">Pro, Gastronomie oder andere werden bei jeder Anfrage zur Terminfreigabe mit einbezogen.</span>
                </span>
              </button>

              {relMode === "approve" && (
                <div className="rel-people">
                  {REGISTERED_CONTACTS.length > 0 && (
                    <div className="rel-quick">
                      <span className="rel-quick-l">Aus deiner Anmeldung</span>
                      <div className="rel-quick-chips">
                        {REGISTERED_CONTACTS.map((c, i) => {
                          const added = relPeople.some(p => p.email === c.email && c.email);
                          return (
                            <button key={i} className={"rel-chip " + (added ? "added" : "")}
                              onClick={() => { if (!added) setRelPeople(ps => [...ps, { ...c }]); }}>
                              <Icon name={added ? "check" : "plus"} size={13} sw={2.2} />
                              {c.name} <span className="rel-chip-role">· {c.role}</span>
                            </button>
                          );
                        })}
                      </div>
                    </div>
                  )}

                  {relPeople.map((p, i) => (
                    <div className="rel-person" key={i}>
                      <div className="rel-person-head">
                        <input className="rel-role" value={p.role} onChange={e => setPerson(i, "role", e.target.value)} placeholder="Rolle" />
                        <button className="rel-remove" onClick={() => removePerson(i)} aria-label="Entfernen"><Icon name="x" size={14} /></button>
                      </div>
                      <div className="rel-person-fields">
                        <input className="rel-input" value={p.name} onChange={e => setPerson(i, "name", e.target.value)} placeholder="Name" />
                        <input className="rel-input" type="email" value={p.email} onChange={e => setPerson(i, "email", e.target.value)} placeholder="E-Mail-Adresse" />
                      </div>
                    </div>
                  ))}

                  <button className="rel-add" onClick={() => addPerson("Weitere Person")}>
                    <Icon name="plus" size={14} sw={2.2} /> Weitere Person hinzufügen
                  </button>

                  <div className="rel-flow">
                    <div className="rel-flow-h">So läuft's ab</div>
                    <p>Kommt eine Anfrage, werden diese Personen automatisch über die vom Kunden gewünschten Termine benachrichtigt. Jede bestätigt, lehnt ab oder schlägt eine Alternative vor — so findet ihr schnell einen Termin, der allen passt, und spart euch das Hin und Her.</p>
                  </div>

                  <p className="rel-legal">
                    Personenbezogene Daten werden ausschließlich zur Bearbeitung von Firmenanfragen verwendet und nicht an Dritte weitergegeben. Die Personen werden über ihre Einbindung informiert (Art. 6 Abs. 1 lit. b/f DSGVO).
                  </p>
                </div>
              )}
            </div>
            <div className="rel-foot">
              <button className="btn btn-ghost" onClick={closeRelease}>Abbrechen</button>
              <button className="btn btn-brand" onClick={confirmRelease}>
                <Icon name="check" size={14} sw={2.5} /> Veröffentlichen
              </button>
            </div>
            </React.Fragment>
            )}
          </div>
        </div>
      )}
    </div>
  );
};

const editRoot = ReactDOM.createRoot(document.getElementById("app"));
editRoot.render(<EditApp />);
