/* eslint-disable */
// =============================================================
// Partner Onboarding — all step components.
// Each exports as window.<Name> so the router can resolve them.
// =============================================================

const $useState = React.useState;

// ---------- Field primitives ----------
function Field({ label, hint, children, full, error }) {
  return (
    <label className={'ob-field ' + (full ? 'full ' : '') + (error ? 'has-error ' : '')}>
      <span className="ob-field-label">{label}</span>
      {children}
      {hint && <span className="ob-field-hint">{hint}</span>}
    </label>
  );
}
function TextInput(props) { return <input className="ob-input" {...props} />; }
function TextArea(props)  { return <textarea className="ob-input" rows={3} {...props} />; }
function Select({ children, ...rest }) {
  return <select className="ob-input" {...rest}>{children}</select>;
}

function StepHeader({ chapter, eyebrow, title, intro, lead, maxWidth }) {
  return (
    <header className="ob-step-head" style={maxWidth ? { maxWidth } : undefined}>
      {eyebrow && <div className="ob-eyebrow">{eyebrow}</div>}
      <h1 className={'ob-step-title ' + (intro ? 'big ' : '')}>{title}</h1>
      {lead && <p className="ob-step-lead">{lead}</p>}
    </header>
  );
}

// ---------- Icon glyphs (Lucide-style, line) ----------
function Icon({ name, size = 28 }) {
  const p = {
    'driving-range':   <><path d="M3 21h18"/><path d="M9 21V8l5-5 5 5v13"/><path d="M5 21V13l4-2"/></>,
    'putting':         <><circle cx="12" cy="12" r="3"/><circle cx="12" cy="12" r="9"/></>,
    'short-game':      <><path d="M4 20h16"/><path d="M8 20l4-12 4 12"/><circle cx="12" cy="8" r="1.5"/></>,
    'short-course':    <><path d="M3 12c4-6 14-6 18 0"/><path d="M3 20h18"/><circle cx="6" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></>,
    'course-9':        <><path d="M4 18h16"/><path d="M8 18V8l4-4 4 4v10"/></>,
    'course-18':       <><path d="M3 18h18"/><path d="M7 18V9l3-3 3 3v9"/><path d="M14 18v-6l3-3"/></>,
    'course-27':       <><path d="M2 19h20"/><path d="M5 19v-5l2.5-2.5 2.5 2.5v5"/><path d="M12 19v-7l2.5-2.5 2.5 2.5v7"/><path d="M19 19v-4l1-1"/></>,
    'leading-course':  <><path d="M7 21V4l8 2.6L7 9.2"/><path d="M4 21h7"/><path d="M17.4 3.2l.7 1.5 1.6.2-1.2 1.1.3 1.6-1.4-.8-1.4.8.3-1.6-1.2-1.1 1.6-.2z"/></>,
    'links-course':    <><path d="M3 20h18"/><path d="M3 16c2.5-2.2 5-2.2 7.5 0s5 2.2 7.5 0"/><path d="M14 16V7l4 1.4L14 9.8"/></>,
    'pitch-putt':      <><path d="M3 20h18"/><path d="M16 20V8l4 1.4L16 11"/><path d="M4 17c2-5.5 6-6.5 9-4" strokeDasharray="0.5 2.4"/><circle cx="4" cy="17" r="1.3"/></>,
    'mini-golf':       <><path d="M12 21V10"/><path d="M9 21h6"/><circle cx="12" cy="8.6" r="1.2"/><path d="M12 8.6l4.6-2.6M12 8.6l-4.6 2.6M12 8.6l2.6 4.6M12 8.6L9.4 4"/><circle cx="6" cy="20" r="1"/></>,
    'clubs':           <><path d="M6 21l6-14"/><path d="M14 21l4-10"/><circle cx="12" cy="5" r="2"/><circle cx="18" cy="9" r="1.6"/></>,
    'balls':           <><circle cx="8"  cy="14" r="3"/><circle cx="16" cy="14" r="3"/><circle cx="12" cy="9"  r="3"/></>,
    'coach':           <><circle cx="12" cy="7" r="3"/><path d="M5 21c0-4 3-6 7-6s7 2 7 6"/></>,
    'meeting':         <><rect x="3" y="6" width="18" height="12" rx="2"/><path d="M8 18v2M16 18v2"/></>,
    'restaurant':      <><path d="M7 3v6c0 1 1 2 2 2v10"/><path d="M5 3h4M15 3v18M17 3c0 4-2 6-2 8"/></>,
    'terrace':         <><path d="M3 18h18"/><path d="M5 18V8h14v10"/><path d="M9 18V12h6v6"/></>,
    'parking':         <><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 17V8h4a3 3 0 1 1 0 6H9"/></>,
    'shuttle':         <><rect x="3" y="6" width="18" height="10" rx="2"/><path d="M3 12h18"/><circle cx="8" cy="18" r="1.5"/><circle cx="16" cy="18" r="1.5"/></>,
    'indoor':          <><rect x="3" y="4" width="18" height="14" rx="1"/><path d="M3 18v2M21 18v2M9 8l6 6M15 8l-6 6"/></>,
    'weather':         <><path d="M8 16a4 4 0 1 1 0-8 5 5 0 0 1 10 1 3 3 0 0 1 0 6H8z"/><path d="M9 19l-1 2M12 19l-1 2M15 19l-1 2"/></>,
    'branding':        <><path d="M4 21V7a2 2 0 0 1 2-2h8l4 4v12"/><path d="M14 5v4h4"/></>,
    'tournament':      <><path d="M7 4h10v4a5 5 0 0 1-10 0V4z"/><path d="M12 13v4M9 21h6"/></>,
    'shower':          <><path d="M12 4v3"/><circle cx="12" cy="9" r="2"/><path d="M8 13l-1 4M12 13l-1 4M16 13l-1 4"/></>,
    'cart':            <><path d="M3 17h11l3-5h4"/><path d="M3 7h6v5"/><circle cx="7" cy="19" r="2"/><circle cx="17" cy="19" r="2"/></>,
    'trolley':         <><path d="M6 21V5a2 2 0 0 1 4 0v16"/><path d="M10 8l8 1.5L10 12"/><circle cx="6" cy="21" r="1.4"/></>,
    'wifi':            <><path d="M4.5 12.5a10 10 0 0 1 15 0"/><path d="M8 16a5 5 0 0 1 8 0"/><circle cx="12" cy="19" r="1"/></>,
    'pro-shop':        <><path d="M4 9h16l-1 11H5z"/><path d="M8 9a4 4 0 0 1 8 0"/></>,
    'coffee':          <><path d="M5 8h11v4a5 5 0 0 1-10 0z"/><path d="M16 9h2a2 2 0 0 1 0 4h-2"/><path d="M5 21h12"/></>,
    'drinks':          <><path d="M5 4h14l-7 8z"/><path d="M12 12v6"/><path d="M8 21h8"/></>,
    'grill':           <><circle cx="12" cy="9" r="6"/><path d="M8.5 15l-2 5M15.5 15l2 5M12 15v5"/></>,
    'accessible':      <><circle cx="12" cy="4" r="1.6"/><path d="M7 8h7"/><path d="M11 8v5h3l3 6"/><path d="M14 13a4.5 4.5 0 1 1-5-4.3"/></>,
    'beamer':          <><rect x="3" y="8" width="14" height="9" rx="2"/><circle cx="9" cy="12.5" r="2.5"/><path d="M17 11l4-2v6l-4-2"/></>,
    'screen':          <><rect x="3" y="4" width="18" height="12" rx="1"/><path d="M12 16v4M8 20h8"/></>,
    'mic':             <><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3M8 21h8"/></>,
    'flipchart':       <><path d="M5 3h14M6 4v12M18 4v12M5 16h14"/><path d="M12 16v5"/></>,
    'plate':           <><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/></>,
    'wine':            <><path d="M8 3h8l-1 6a3 3 0 0 1-6 0z"/><path d="M12 12v6M9 21h6"/></>,

    // formats
    'intro-golf':      <><circle cx="12" cy="12" r="9"/><path d="M9 12l2 2 4-4"/></>,
    'team-challenge':  <><circle cx="9" cy="8" r="3"/><circle cx="17" cy="10" r="2.5"/><path d="M3 21c0-3 3-5 6-5s6 2 6 5"/><path d="M13 21c0-2 2-3 4-3s4 1 4 3"/></>,
    'putting-challenge': <><circle cx="12" cy="12" r="3"/><circle cx="12" cy="12" r="8"/><path d="M12 4v2M12 18v2M4 12h2M18 12h2"/></>,
    'range-training':  <><path d="M3 18h18"/><path d="M7 18v-6M11 18V8M15 18v-4M19 18v-9"/></>,
    'short-challenge': <><path d="M5 18l5-8 4 4 5-6"/><circle cx="19" cy="8" r="1.5"/></>,
    'turnier-9':       <><path d="M9 3h6v4a3 3 0 0 1-6 0z"/><path d="M12 11v6M8 19h8"/></>,
    'turnier-18':      <><path d="M8 3h8v4a4 4 0 0 1-8 0z"/><path d="M12 11v6M7 19h10"/></>,
    'offsite':         <><rect x="3" y="9" width="18" height="11" rx="1"/><path d="M9 9V5h6v4"/><path d="M8 14h8"/></>,
    'sommerfest':      <><circle cx="12" cy="12" r="3"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.5 5.5l2 2M16.5 16.5l2 2M5.5 18.5l2-2M16.5 7.5l2-2"/></>,
    'kunden':          <><circle cx="12" cy="8" r="3"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/><path d="M16 4h4v4"/></>,
    'health':          <><path d="M12 21s-7-4-7-10a4 4 0 0 1 7-3 4 4 0 0 1 7 3c0 6-7 10-7 10z"/></>,
    'azubi':           <><path d="M2 9l10-5 10 5-10 5z"/><path d="M6 12v4c0 2 3 4 6 4s6-2 6-4v-4"/></>,
    'custom':          <><circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2"/></>,
    'networking':      <><circle cx="7" cy="8" r="3"/><circle cx="17" cy="8" r="3"/><path d="M2.5 20c0-2.8 2-4.5 4.5-4.5s4.5 1.7 4.5 4.5"/><path d="M12.5 20c0-2.8 2-4.5 4.5-4.5s4.5 1.7 4.5 4.5"/></>,
    'afterwork':       <><path d="M3 18h18"/><path d="M7 18a5 5 0 0 1 10 0"/><path d="M12 4v3M5.2 7.2l1.6 1.6M18.8 7.2l-1.6 1.6M3 12h2M19 12h2"/></>,
    'charity':         <><path d="M12 9.2c-1.1-2.1-4.2-1.5-4.2 1 0 2.1 4.2 4.3 4.2 4.3s4.2-2.2 4.2-4.3c0-2.5-3.1-3.1-4.2-1z"/><path d="M3 20c2-1.8 4.6-1.8 6.5-.8l2.5.9 6-2.3"/></>,
    'nacht-event':     <><path d="M20 13.5A8 8 0 1 1 10.5 4a6.2 6.2 0 0 0 9.5 9.5z"/><path d="M18 3.5l.6 1.4 1.4.6-1.4.6-.6 1.4-.6-1.4-1.4-.6 1.4-.6z"/></>,
  };
  return (
    <svg viewBox="0 0 24 24" width={size} height={size} fill="none" stroke="currentColor"
         strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
      {p[name] || <circle cx="12" cy="12" r="9" />}
    </svg>
  );
}

// ---------- Selectable cards (icon grid) ----------
function CardGrid({ items, value, onToggle, columns = 3 }) {
  return (
    <div className="ob-cards" style={{ gridTemplateColumns: `repeat(${columns}, 1fr)` }}>
      {items.map(it => {
        const on = value.includes(it.id);
        return (
          <button
            key={it.id}
            type="button"
            className={'ob-card ' + (on ? 'on' : '')}
            onClick={() => onToggle(it.id)}>
            <span className="ob-card-ico"><Icon name={it.icon} /></span>
            <span className="ob-card-l">{it.label}</span>
          </button>
        );
      })}
    </div>
  );
}

// ---------- Single-select card grid (one primary choice) ----------
function SingleCardGrid({ items, value, onSelect, columns = 3 }) {
  return (
    <div className="ob-cards" style={{ gridTemplateColumns: `repeat(${columns}, 1fr)` }}>
      {items.map(it => (
        <button
          key={it.id}
          type="button"
          className={'ob-card ' + (value === it.id ? 'on' : '')}
          onClick={() => onSelect(it.id)}>
          <span className="ob-card-ico"><Icon name={it.icon} /></span>
          <span className="ob-card-l">{it.label}</span>
        </button>
      ))}
    </div>
  );
}

// ============================================================
// STEP — Golf offering type (first form question)
// ============================================================
const GOLF_TYPES = [
  { id: 'course-18',  label: '18-Loch-Platz',     icon: 'course-18' },
  { id: 'course-27',  label: '27-Loch-Platz',     icon: 'course-27' },
  { id: 'leading',    label: 'Leading Course',    icon: 'leading-course' },
  { id: 'links',      label: 'Links-Platz',       icon: 'links-course' },
  { id: 'course-9',   label: '9-Loch-Platz',      icon: 'course-9' },
  { id: 'indoor-sim', label: 'Indoor-Simulator',  icon: 'indoor' },
  { id: 'range',      label: 'Driving-Range',     icon: 'driving-range' },
  { id: 'short',      label: 'Kurzplatz',         icon: 'short-course' },
  { id: 'pitch-putt', label: 'Pitch & Putt',      icon: 'pitch-putt' },
  { id: 'mini-golf',  label: 'Mini-Golf',         icon: 'mini-golf' },
];

function StepGolfType({ data, update }) {
  return (
    <div className="ob-step ob-step-wide">
      <StepHeader
        title="Was beschreibt dein Golfangebot am besten?"
        lead="Wähl die Art, die euren Platz am besten beschreibt. Anlagen und Veranstaltungstypen erfassen wir gleich noch im Detail."
      />
      <SingleCardGrid
        items={GOLF_TYPES}
        value={data.golfType}
        onSelect={(id) => update({ golfType: id })}
        columns={4}
      />
    </div>
  );
}
window.StepGolfType = StepGolfType;

// ============================================================
// STEP 1 — Welcome (chapter intro)
// ============================================================
function StepWelcome({ data }) {
  return (
    <div className="ob-intro">
      <div className="ob-intro-text">
        <div className="ob-eyebrow">Schritt 1 · Erzähl uns von deinem Platz</div>
        <h1 className="ob-step-title big">
          Richte deinen Golfplatz als <span className="ob-italic">Eventlocation</span> ein.
        </h1>
        <p className="ob-intro-lead">
          In ein paar Schritten erfassen wir die wichtigsten Informationen, damit Unternehmen
          deinen Golfplatz für Firmenevents anfragen können. Du kannst zwischendurch jederzeit
          speichern und später weitermachen.
        </p>
        <ul className="ob-intro-list">
          <li><span className="ob-intro-dot" /> Basisdaten und Standort</li>
          <li><span className="ob-intro-dot" /> Hauptkontakt + dein Login fürs Partnerportal</li>
          <li><span className="ob-intro-dot" /> Was ihr für Firmenevents anbieten könnt</li>
          <li><span className="ob-intro-dot" /> Verfügbarkeit, Preis &amp; Medien</li>
        </ul>
        <div className="ob-intro-meta">Dauer ungefähr 10 Minuten · keine Verpflichtung</div>
      </div>
      <div className="ob-intro-art" aria-hidden>
        <ArtPlatzScene />
      </div>
    </div>
  );
}
window.StepWelcome = StepWelcome;

// Welcome illustration — real clubhouse photo (on-brand: photographic, not illustrated)
function ArtPlatzScene() {
  return (
    <div className="ob-art-photo">
      <img src="assets/imagery/onboarding-clubhouse.jpg"
           alt="Golfschläger am Tee mit Ball, kurz vor dem Abschlag" />
    </div>
  );
}

// ============================================================
// STEP 2 — Basics
// ============================================================
function StepBasics({ data, update }) {
  return (
    <div className="ob-step">
      <StepHeader
        title="Wie heißt euer Golfplatz?"
        lead="Diese Angaben erscheinen später öffentlich auf deinem Partnerprofil. Du kannst alles jederzeit ändern."
        maxWidth={680}
      />
      <div className="ob-form">
        <Field label="Öffentlicher Golfplatzname" hint="So findet er sich in der Suche">
          <TextInput placeholder="z. B. Golfclub Hamburg-Wendlohe"
                     value={data.publicName}
                     onChange={(e) => update({ publicName: e.target.value })} />
        </Field>
        <Field label="Rechtlicher Betreibername" hint="Für die Rechnung und das Impressum">
          <TextInput placeholder="z. B. GC Hamburg-Wendlohe e.V."
                     value={data.legalName}
                     onChange={(e) => update({ legalName: e.target.value })} />
        </Field>
        <Field label="Website" full>
          <TextInput type="url" placeholder="https://…"
                     value={data.website}
                     onChange={(e) => update({ website: e.target.value })} />
        </Field>
        <Field label="Öffentliche Kurzbeschreibung" full hint="2–3 Sätze. Du kannst das später noch ausbauen.">
          <TextArea placeholder="z. B. 18-Loch-Anlage am Stadtrand, gemütliche Clubhaus-Terrasse, Driving Range mit 30 Plätzen…"
                    value={data.shortDesc}
                    onChange={(e) => update({ shortDesc: e.target.value })} />
        </Field>
      </div>
    </div>
  );
}
window.StepBasics = StepBasics;

// ============================================================
// STEP 3 — Location
// ============================================================
function StepLocation({ data, update }) {
  const states = ['Baden-Württemberg', 'Bayern', 'Berlin', 'Brandenburg', 'Bremen', 'Hamburg', 'Hessen', 'Mecklenburg-Vorpommern', 'Niedersachsen', 'Nordrhein-Westfalen', 'Rheinland-Pfalz', 'Saarland', 'Sachsen', 'Sachsen-Anhalt', 'Schleswig-Holstein', 'Thüringen'];
  return (
    <div className="ob-step">
      <StepHeader
        title="Wo befindet sich dein Platz?"
        lead="Wir nutzen die Adresse für die Karte und die Anfahrtsbeschreibung. Genaue Anfahrt geht erst nach Bestätigung an Kunden raus."
        maxWidth={680}
      />
      <div className="ob-form">
        <div className="ob-row ob-row-3-1">
          <Field label="Straße">
            <TextInput value={data.street} onChange={(e) => update({ street: e.target.value })} placeholder="Straßenname" />
          </Field>
          <Field label="Hausnummer">
            <TextInput value={data.houseNo} onChange={(e) => update({ houseNo: e.target.value })} placeholder="123" />
          </Field>
        </div>
        <div className="ob-row ob-row-1-3">
          <Field label="PLZ">
            <TextInput value={data.zip} onChange={(e) => update({ zip: e.target.value })} placeholder="20359" maxLength={5} />
          </Field>
          <Field label="Ort">
            <TextInput value={data.city} onChange={(e) => update({ city: e.target.value })} placeholder="Hamburg" />
          </Field>
        </div>
        <Field label="Bundesland" full>
          <Select value={data.state} onChange={(e) => update({ state: e.target.value })}>
            <option value="">Bitte wählen…</option>
            {states.map(s => <option key={s} value={s}>{s}</option>)}
          </Select>
        </Field>
        <Field label="Google-Maps-Link" full hint="Optional. Hilft uns, den Pin exakt zu setzen.">
          <TextInput type="url" placeholder="https://maps.google.com/…"
                     value={data.mapsUrl}
                     onChange={(e) => update({ mapsUrl: e.target.value })} />
        </Field>
      </div>
      <div className="ob-map-preview" aria-hidden>
        <div className="ob-map-grid" />
        <div className="ob-map-pin" />
        <div className="ob-map-tag">
          {(data.street || 'Straße') + ' ' + (data.houseNo || '')}, {(data.zip || 'PLZ') + ' ' + (data.city || 'Ort')}
        </div>
      </div>
    </div>
  );
}
window.StepLocation = StepLocation;

// ============================================================
// STEP — Anfahrt & Location
// ============================================================
function StepArrival({ data, update }) {
  const a = data.arrival || {};
  const set = (k, v) => update({ arrival: { ...a, [k]: v } });
  return (
    <div className="ob-step">
      <StepHeader
        title="Anfahrt & Location"
        lead="Wie kommen Gäste zu euch? Diese Angaben helfen Firmen bei der Planung — du kannst sie jederzeit anpassen."
        maxWidth={680}
      />
      <div className="ob-form">
        <Field label="Mit dem Auto" full hint="Fahrzeit ab Stadtzentrum oder Autobahn.">
          <TextInput value={a.car || ''} onChange={(e) => set('car', e.target.value)} placeholder="z. B. 15 Min ab Stadtzentrum" />
        </Field>
        <Field label="Parken" full hint="Anzahl und Art der Parkplätze.">
          <TextInput value={a.parking || ''} onChange={(e) => set('parking', e.target.value)} placeholder="z. B. 100 kostenfreie Parkplätze" />
        </Field>
        <YesNoField
          label="Ladestation für E-Autos vorhanden?"
          hint="Wird Gästen mit E-Auto als Hinweis angezeigt."
          value={!!a.eStation} onChange={(v) => set('eStation', v)} />
        <Field label="Mit der Bahn" full hint="Nächste Station und Gehweg.">
          <TextInput value={a.train || ''} onChange={(e) => set('train', e.target.value)} placeholder="z. B. S2 Petershausen/Riem, 10 Gehminuten" />
        </Field>
        <Field label="Shuttle-Service" full hint="Falls ihr einen Transfer anbietet.">
          <TextInput value={a.shuttle || ''} onChange={(e) => set('shuttle', e.target.value)} placeholder="z. B. Abholung nach Absprache" />
        </Field>
      </div>
    </div>
  );
}
window.StepArrival = StepArrival;

// ============================================================
// STEP 4 — Main Contact + Login
// ============================================================
function StepMainContact({ data, update }) {
  return (
    <div className="ob-step">
      <StepHeader
        title="Wer ist der Hauptkontakt?"
        lead="Diese Person wird mit dem Platz verknüpft und bekommt einen Login fürs Partnerportal. Wenn die E-Mail-Adresse bereits existiert, verbinden wir das bestehende Konto."
        maxWidth={680}
      />
      <div className="ob-form">
        <div className="ob-row">
          <Field label="Vorname">
            <TextInput value={data.mc_firstName} onChange={(e) => update({ mc_firstName: e.target.value })} placeholder="Lena" />
          </Field>
          <Field label="Nachname">
            <TextInput value={data.mc_lastName} onChange={(e) => update({ mc_lastName: e.target.value })} placeholder="Hoffmann" />
          </Field>
        </div>
        <Field label="Rolle im Club" full>
          <TextInput value={data.mc_role} onChange={(e) => update({ mc_role: e.target.value })} placeholder="z. B. Clubmanager:in, Eventleitung" />
        </Field>
        <div className="ob-row">
          <Field label="E-Mail" hint="Wird zum Login.">
            <TextInput type="email" value={data.mc_email} onChange={(e) => update({ mc_email: e.target.value })} placeholder="name@golfclub.de" />
          </Field>
          <Field label="Telefon">
            <TextInput type="tel" value={data.mc_phone} onChange={(e) => update({ mc_phone: e.target.value })} placeholder="+49 …" />
          </Field>
        </div>
        <div className="ob-info-box">
          <div className="ob-info-l">Login wird so erstellt</div>
          <div className="ob-info-v">
            Du bekommst eine E-Mail an <strong>{data.mc_email || '— deine Mail —'}</strong> mit einem Link, um dein Passwort zu setzen.
            Mit diesem Login verwaltest du euer Partnerprofil und Anfragen.
          </div>
        </div>
      </div>
    </div>
  );
}
window.StepMainContact = StepMainContact;

// ============================================================
// STEP 5 — Additional Contacts (skippable)
// ============================================================
const CONTACT_ROLES = [
  "Clubmanager", "Geschäftsführer", "Vorstand", "Präsident", "Schatzmeister",
  "Sekretariat", "Rezeption", "Mitgliederverwaltung", "Buchhaltung",
  "Head Pro", "Golfprofessional", "Golflehrer", "Golfschule",
  "Sportwart", "Spielleitung", "Turnierleitung", "Marshal", "Starter",
  "Head Greenkeeper", "Greenkeeper", "Course Manager",
  "Gastronomiebetreiber", "Restaurantleitung", "Eventmanager",
  "Pro Shop Mitarbeiter", "Caddiemaster", "Cart Verantwortlicher",
  "Jugendwart", "Captain", "Mannschaftsführer", "Sonstige",
];

function StepContacts({ data, update }) {
  const list = data.contacts || [];
  const setList = (next) => update({ contacts: next });
  const add = () => setList([...list, { name: "", role: "", email: "" }]);
  const remove = (i) => setList(list.filter((_, j) => j !== i));
  const edit = (i, patch) => setList(list.map((c, j) => (j === i ? { ...c, ...patch } : c)));

  return (
    <div className="ob-step">
      <StepHeader
        title="Möchtest du weitere Ansprechpartner hinzufügen?"
        lead="Du kannst diesen Schritt überspringen und später ergänzen. Diese Personen kannst du später bei der Termin-Freigabe für ein Event einbinden."
        maxWidth={720}
      />

      {list.length > 0 ? (
        <div className="ob-contacts-list">
          {list.map((c, i) => (
            <div key={i} className="ob-contact-card">
              <div className="ob-contact-head">
                <span className="ob-contact-tag">{c.role || "Ansprechpartner"}</span>
                <button type="button" className="ob-contact-remove" onClick={() => remove(i)}>Entfernen</button>
              </div>
              <Field label="Name" full>
                <TextInput value={c.name} onChange={(e) => edit(i, { name: e.target.value })} placeholder="Vor- und Nachname" />
              </Field>
              <div className="ob-row">
                <Field label="Rolle">
                  <Select value={c.role} onChange={(e) => edit(i, { role: e.target.value })}>
                    <option value="">Rolle wählen…</option>
                    {CONTACT_ROLES.map((r) => <option key={r} value={r}>{r}</option>)}
                  </Select>
                </Field>
                <Field label="E-Mail">
                  <TextInput type="email" value={c.email} onChange={(e) => edit(i, { email: e.target.value })} placeholder="name@golfclub.de" />
                </Field>
              </div>
            </div>
          ))}
        </div>
      ) : null}

      <button type="button" className="ob-add-contact" onClick={add}>
        <span className="ob-add-contact-ic">+</span> Ansprechpartner hinzufügen
      </button>
    </div>
  );
}
window.StepContacts = StepContacts;

// ============================================================
// STEP 6 — Chapter 2 Intro
// ============================================================
function StepChapter2() {
  return (
    <div className="ob-intro">
      <div className="ob-intro-text">
        <div className="ob-eyebrow">Schritt 2 · Was du anbieten kannst</div>
        <h1 className="ob-step-title big">
          Was macht euren Platz zur <span className="ob-italic">Eventlocation</span>?
        </h1>
        <p className="ob-intro-lead">
          Wir möchten Unternehmen genau passende Vorschläge machen. Dazu erfassen wir,
          welche Infrastruktur ihr habt, wie groß die Gruppen sein können und welche Veranstaltungstypen
          ihr abdeckt.
        </p>
        <ul className="ob-intro-list">
          <li><span className="ob-intro-dot" /> Infrastruktur (Range, Greens, Clubhaus, Räume)</li>
          <li><span className="ob-intro-dot" /> Kapazitäten pro Bereich</li>
          <li><span className="ob-intro-dot" /> Veranstaltungstypen, die ihr unterstützt</li>
        </ul>
      </div>
      <div className="ob-intro-art" aria-hidden>
        <div className="ob-art-photo">
          <img src="assets/imagery/onboarding-carts.jpg"
               alt="Golfcarts in einer Reihe am Clubhaus im Abendlicht" />
        </div>
      </div>
    </div>
  );
}
window.StepChapter2 = StepChapter2;

function ArtClubhouseScene() {
  return (
    <div className="ob-art-club">
      <div className="ob-art-sky" />
      <div className="ob-art-hill-2" />
      <div className="ob-art-house">
        <div className="ob-art-roof" />
        <div className="ob-art-wall" />
        <div className="ob-art-window" />
        <div className="ob-art-door" />
      </div>
      <div className="ob-art-ground" />
    </div>
  );
}

// ============================================================
// STEP 7 — Infrastructure (icon cards)
// ============================================================
function StepInfrastructure({ data, update }) {
  const groups = [
    { cat: 'Auf dem Platz', items: [
      { id: 'course-18',      label: '18-Loch-Platz',          icon: 'course-18' },
      { id: 'course-9',       label: '9-Loch-Platz',           icon: 'course-9' },
      { id: 'abc-platz',      label: 'A-B-C Platz',            icon: 'course-27' },
      { id: 'short-course',   label: 'Kurzplatz',              icon: 'short-course' },
      { id: 'driving-range',  label: 'Driving Range',          icon: 'driving-range' },
      { id: 'range-covered',  label: 'Überdachte Driving Range', icon: 'driving-range' },
      { id: 'range-heated',   label: 'Beheizte Abschlagplätze', icon: 'driving-range' },
      { id: 'range-flood',    label: 'Flutlicht Range',        icon: 'nacht-event' },
      { id: 'trackman',       label: 'TrackMan Range',         icon: 'indoor' },
      { id: 'toptracer',      label: 'Toptracer Range',        icon: 'indoor' },
      { id: 'short-game',     label: 'Kurzspielbereich',       icon: 'short-game' },
      { id: 'practice-bunker',label: 'Übungsbunker',           icon: 'short-game' },
      { id: 'indoor',         label: 'Indoor Simulator',       icon: 'indoor' },
      { id: 'barrierefrei',   label: 'Barrierearme Anlage',    icon: 'accessible' },
    ]},
    { cat: 'Im Clubhaus', items: [
      { id: 'meeting-room',   label: 'Meetingraum',            icon: 'meeting' },
      { id: 'seminar',        label: 'Seminarraum',            icon: 'meeting' },
      { id: 'conference',     label: 'Konferenzraum',          icon: 'meeting' },
      { id: 'workshop',       label: 'Workshopraum',           icon: 'meeting' },
      { id: 'eventroom',      label: 'Eventraum',              icon: 'meeting' },
      { id: 'golf-shop',      label: 'Golf-Shop',              icon: 'pro-shop' },
      { id: 'shower',         label: 'Duschen & Umkleiden',    icon: 'shower' },
    ]},
    { cat: 'Tagungstechnik', items: [
      { id: 'beamer',         label: 'Beamer',                 icon: 'beamer' },
      { id: 'screen',         label: 'Bildschirm',             icon: 'screen' },
      { id: 'mic',            label: 'Mikrofonanlage',         icon: 'mic' },
      { id: 'wifi',           label: 'WLAN',                   icon: 'wifi' },
      { id: 'flipchart',      label: 'Flipchart',              icon: 'flipchart' },
      { id: 'whiteboard',     label: 'Whiteboard',             icon: 'flipchart' },
      { id: 'moderation',     label: 'Moderationsmaterial',    icon: 'branding' },
      { id: 'catering-area',  label: 'Cateringfläche',         icon: 'plate' },
    ]},
    { cat: 'Golfschule', items: [
      { id: 'coach',          label: 'Golflehrer',             icon: 'coach' },
      { id: 'trial-course',   label: 'Schnupperkurs',          icon: 'intro-golf' },
      { id: 'platzreife',     label: 'Platzreifekurs',         icon: 'intro-golf' },
      { id: 'company-course', label: 'Firmenkurs',             icon: 'team-challenge' },
      { id: 'advanced-course',label: 'Fortgeschrittenenkurs',  icon: 'range-training' },
      { id: 'rental-clubs',   label: 'Leihschläger',           icon: 'clubs' },
      { id: 'range-balls',    label: 'Range-Bälle',            icon: 'balls' },
    ]},
  ];
  const toggle = (id) => {
    update(d => ({ ...d, infra: d.infra.includes(id) ? d.infra.filter(x => x !== id) : [...d.infra, id] }));
  };
  return (
    <div className="ob-step ob-step-wide">
      <StepHeader
        title="Was ist auf eurem Platz alles vorhanden und möglich?"
        lead="Wähl alles aus, was vor Ort verfügbar ist. Du kannst die Auswahl später jederzeit anpassen."
      />
      {groups.map(g => (
        <div className="ob-cat" key={g.cat}>
          <div className="ob-cat-h">{g.cat}</div>
          <CardGrid items={g.items} value={data.infra} onToggle={toggle} columns={4} />
        </div>
      ))}
    </div>
  );
}
window.StepInfrastructure = StepInfrastructure;

// ============================================================
// STEP — Gastronomie (own step, after infrastructure)
// ============================================================
const GASTRO_ITEMS = [
  { id: 'restaurant',      label: 'Restaurant',          icon: 'restaurant' },
  { id: 'club-restaurant', label: 'Clubrestaurant',      icon: 'restaurant' },
  { id: 'bistro',          label: 'Bistro',              icon: 'coffee' },
  { id: 'cafe',            label: 'Café',                icon: 'coffee' },
  { id: 'bar',             label: 'Bar',                 icon: 'drinks' },
  { id: 'halfway',         label: 'Halfway-Verpflegung', icon: 'restaurant' },
  { id: 'terrace',         label: 'Terrasse',            icon: 'terrace' },
  { id: 'outdoor',         label: 'Außenbereich',        icon: 'terrace' },
  { id: 'lounge',          label: 'Lounge Bereich',      icon: 'drinks' },
  { id: 'catering',        label: 'Catering',            icon: 'restaurant' },
  { id: 'breakfast',       label: 'Frühstück',           icon: 'coffee' },
  { id: 'lunch',           label: 'Lunch',               icon: 'restaurant' },
  { id: 'dinner',          label: 'Abendessen',          icon: 'restaurant' },
  { id: 'bbq',             label: 'BBQ',                 icon: 'grill' },
  { id: 'drinks-flat',     label: 'Getränkepauschale',   icon: 'drinks' },
  { id: 'coffee-break',    label: 'Kaffeepause',         icon: 'coffee' },
];

function StepGastronomy({ data, update }) {
  const toggle = (id) => {
    update(d => ({ ...d, infra: d.infra.includes(id) ? d.infra.filter(x => x !== id) : [...d.infra, id] }));
  };
  return (
    <div className="ob-step ob-step-wide">
      <StepHeader
        title="Was bietet ihr gastronomisch an?"
        lead="Wähl alles aus, was ihr für Firmenevents bereitstellen könnt. Du kannst die Auswahl später jederzeit anpassen."
      />
      <div className="ob-cat">
        <CardGrid items={GASTRO_ITEMS} value={data.infra} onToggle={toggle} columns={4} />
      </div>
    </div>
  );
}
window.StepGastronomy = StepGastronomy;

// ============================================================
// STEP 8 — Capacity (stepper-style inputs)
// ============================================================
// Each optional capacity row maps to the infra item that must be selected
// in Step 7 for the question to appear. Min/Max are always asked.
const CAP_ROWS = [
  { key: 'range',     infra: 'driving-range', label: 'Kapazität Driving Range', hint: 'Wie viele Abschlagplätze?' },
  { key: 'indoor',    infra: 'indoor',        label: 'Kapazität Indoor Simulator', hint: 'Personen gleichzeitig.' },
  { key: 'meeting',   infra: 'meeting-room',  label: 'Kapazität Meetingraum',    hint: 'Sitzplätze.' },
  { key: 'seminar',   infra: 'seminar',       label: 'Kapazität Seminarraum',    hint: 'Sitzplätze.' },
  { key: 'conference',infra: 'conference',    label: 'Kapazität Konferenzraum',  hint: 'Sitzplätze.' },
  { key: 'workshop',  infra: 'workshop',      label: 'Kapazität Workshopraum',   hint: 'Sitzplätze.' },
  { key: 'eventroom', infra: 'eventroom',     label: 'Kapazität Eventraum',      hint: 'Personen.' },
  { key: 'restaurant',infra: 'restaurant',    label: 'Kapazität Restaurant',     hint: 'Sitzplätze.' },
  { key: 'terrace',   infra: 'terrace',       label: 'Kapazität Terrasse',       hint: 'Sitzplätze draußen.' },
  { key: 'outdoor',   infra: 'outdoor',       label: 'Kapazität Außenbereich',    hint: 'Personen.' },
  { key: 'lounge',    infra: 'lounge',        label: 'Kapazität Lounge Bereich', hint: 'Personen.' },
  { key: 'trial',     infra: 'trial-course',  label: 'Max. Teilnehmer Schnupperkurs', hint: 'Pro Kurs.' },
  { key: 'platzreife',infra: 'platzreife',    label: 'Max. Teilnehmer Platzreifekurs', hint: 'Pro Kurs.' },
  { key: 'company',   infra: 'company-course',label: 'Max. Teilnehmer Firmenkurs',   hint: 'Pro Kurs.' },
];

function StepCapacity({ data, update }) {
  const cap = data.cap;
  const set = (k, v) => update({ cap: { ...cap, [k]: v } });
  const infra = data.infra || [];
  const rows = CAP_ROWS.filter(r => infra.includes(r.infra));

  return (
    <div className="ob-step">
      <StepHeader
        title="Wie viele Gäste passen wo?"
        lead="Wie groß sind eure Bereiche? Das hilft uns, bei individuellen Anfragen sofort zu erkennen, ob ihr dafür in Frage kommt. Mindest- und Maximal-Teilnehmerzahl sind Pflicht — für eure ausgewählten Bereiche fragen wir die Kapazität ab."
        maxWidth={680}
      />

      <div className="ob-cap-list">
        <CapStepper label="Teilnehmer-Minimum" hint="Ab wie vielen Gästen lohnt sich ein Event?" value={cap.min} onChange={v => set('min', v)} required />
        <CapStepper label="Teilnehmer-Maximum" hint="Größte Gruppe, die ihr realistisch betreuen könnt." value={cap.max} onChange={v => set('max', v)} required />

        {rows.length > 0 && <div className="ob-cap-divider">Kapazitäten deiner Bereiche</div>}
        {rows.map(r => (
          <CapStepper key={r.key} label={r.label} hint={r.hint} value={cap[r.key]} onChange={v => set(r.key, v)} />
        ))}
      </div>

      {rows.length === 0 && (
        <p className="ob-cap-note">
          Du hast in den Anlagen noch keine Bereiche mit eigener Kapazität ausgewählt (z. B. Driving Range,
          Meetingraum, Restaurant). Geh einen Schritt zurück, falls du welche ergänzen möchtest.
        </p>
      )}
    </div>
  );
}
window.StepCapacity = StepCapacity;

function CapStepper({ label, hint, value, onChange, required }) {
  const n = parseInt(value, 10) || 0;
  return (
    <div className="ob-cap-row">
      <div className="ob-cap-text">
        <div className="ob-cap-l">
          {label}
          {required && <span className="ob-required"> *</span>}
        </div>
        {hint && <div className="ob-cap-h">{hint}</div>}
      </div>
      <div className="ob-stepper">
        <button type="button" className="ob-stepper-btn" onClick={() => onChange(String(Math.max(0, n - 1)))} aria-label="weniger">−</button>
        <input
          className="ob-stepper-input"
          type="text"
          inputMode="numeric"
          value={value}
          onChange={(e) => {
            const v = e.target.value.replace(/[^\d]/g, '');
            onChange(v);
          }}
          placeholder="0"
        />
        <button type="button" className="ob-stepper-btn" onClick={() => onChange(String(n + 1))} aria-label="mehr">+</button>
      </div>
    </div>
  );
}

// ============================================================
// STEP 9 — Formats (icon cards)
// ============================================================
function StepFormats({ data, update }) {
  const items = [
    { id: 'schnupperkurs',  label: 'Schnupperkurs',        icon: 'intro-golf' },
    { id: 'teamevent',      label: 'Teamevent',            icon: 'team-challenge' },
    { id: 'firmenturnier',  label: 'Firmenturnier',        icon: 'turnier-18' },
    { id: 'kundenevent',    label: 'Kundenevent',          icon: 'kunden' },
    { id: 'networking',     label: 'Networking',           icon: 'networking' },
    { id: 'afterwork',      label: 'After-Work Golf',      icon: 'afterwork' },
    { id: 'sommerfest',     label: 'Sommerfest',           icon: 'sommerfest' },
    { id: 'offsite',        label: 'Offsite & Incentive',  icon: 'offsite' },
    { id: 'gesundheitstag', label: 'Gesundheitstag',       icon: 'health' },
    { id: 'charity',        label: 'Charity-Event',        icon: 'charity' },
    { id: 'nacht-event',    label: 'Nacht-Event',          icon: 'nacht-event' },
  ];
  const toggle = (id) => {
    update(d => ({ ...d, formats: d.formats.includes(id) ? d.formats.filter(x => x !== id) : [...d.formats, id] }));
  };
  return (
    <div className="ob-step ob-step-wide">
      <StepHeader
        title="Welche Veranstaltungstypen könnt ihr abdecken?"
        lead="Wähle alles, was ihr regelmäßig oder bei Bedarf anbieten könnt. Mehr Veranstaltungstypen = mehr passende Anfragen."
      />
      <CardGrid items={items} value={data.formats} onToggle={toggle} columns={4} />
      <p className="ob-cat-note">
        Können später noch ergänzt werden. Das sind die Veranstaltungstypen, die ihr im
        Partner-Portal später einzeln als buchbare Angebote anlegen könnt.
      </p>
    </div>
  );
}
window.StepFormats = StepFormats;

// ============================================================
// STEP 10 — Chapter 3 Intro
// ============================================================
function StepChapter3() {
  return (
    <div className="ob-intro">
      <div className="ob-intro-text">
        <div className="ob-eyebrow">Schritt 3 · Verfügbarkeit, Preis &amp; Medien</div>
        <h1 className="ob-step-title big">
          Fast geschafft — jetzt die <span className="ob-italic">Rahmenbedingungen</span>.
        </h1>
        <p className="ob-intro-lead">
          Verfügbarkeit, Aufschlag und Bilder — danach prüfst du alles und reichst dein
          Profil bei uns ein. Wir melden uns innerhalb eines Werktags zurück.
        </p>
        <ul className="ob-intro-list">
          <li><span className="ob-intro-dot" /> Verfügbarkeit &amp; Vorlauf</li>
          <li><span className="ob-intro-dot" /> Preis-Aufschlag &amp; Abrechnung</li>
          <li><span className="ob-intro-dot" /> Logo, Titelbild &amp; Galerie</li>
          <li><span className="ob-intro-dot" /> Zusammenfassung &amp; Einreichung</li>
        </ul>
      </div>
      <div className="ob-intro-art" aria-hidden>
        <div className="ob-art-photo">
          <img src="assets/imagery/onboarding-hole.jpg"
               alt="Golfball im Loch auf dem Grün, von oben" />
        </div>
      </div>
    </div>
  );
}
window.StepChapter3 = StepChapter3;

function ArtMediaScene() {
  return (
    <div className="ob-art-media">
      <div className="ob-art-photo big" />
      <div className="ob-art-photo sm s1" />
      <div className="ob-art-photo sm s2" />
      <div className="ob-art-photo sm s3" />
    </div>
  );
}

// ============================================================
// STEP 11 — Availability
// ============================================================
function StepAvailability({ data, update }) {
  const a = data.avail;
  const set = (k, v) => update({ avail: { ...a, [k]: v } });
  const WEEKDAYS = ['Mo', 'Di', 'Mi', 'Do', 'Fr'];
  const toggleDay = (d) =>
    set('weekdays', a.weekdays.includes(d) ? a.weekdays.filter(x => x !== d) : [...a.weekdays, d]);

  return (
    <div className="ob-step">
      <StepHeader
        title="Wann sind Events bei euch möglich?"
        lead="Die exakte Verfügbarkeit prüfen wir später bei jeder Anfrage einzeln. Hier reichen Grundlagen, damit wir Anfragen vorfiltern können."
        maxWidth={680}
      />
      <div className="ob-form">
        <div className="ob-field full">
          <span className="ob-field-label">Bevorzugte Event-Wochentage</span>
          <div className="ob-day-row">
            {WEEKDAYS.map(d => (
              <button key={d} type="button"
                className={'ob-day ' + (a.weekdays.includes(d) ? 'on' : '')}
                onClick={() => toggleDay(d)}>
                {d}
              </button>
            ))}
          </div>
          <span className="ob-field-hint">Mehrfachauswahl. Du kannst jederzeit weitere Tage freischalten.</span>
        </div>

        <YesNoField
          label="Wochenend-Events möglich?"
          hint="Samstag und/oder Sonntag, ggf. mit Aufschlag."
          value={a.weekends} onChange={v => set('weekends', v)} />
        <YesNoField
          label="Abend-Events möglich?"
          hint="Z. B. Flutlicht-Putting oder Tasting-Abend."
          value={a.evenings} onChange={v => set('evenings', v)} />

        <div className="ob-row">
          <Field label="Mindest-Vorlauf in Tagen" hint="So weit im Voraus müssen Anfragen mindestens kommen.">
            <TextInput type="number" min="0" value={a.leadTime} onChange={(e) => set('leadTime', e.target.value)} />
          </Field>
          <Field label="" hint=" ">
            <div className="ob-row">
              <Field label="Saison von">
                <Select value={a.seasonFrom} onChange={(e) => set('seasonFrom', e.target.value)}>
                  {['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'].map(m => <option key={m}>{m}</option>)}
                </Select>
              </Field>
              <Field label="bis">
                <Select value={a.seasonTo} onChange={(e) => set('seasonTo', e.target.value)}>
                  {['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'].map(m => <option key={m}>{m}</option>)}
                </Select>
              </Field>
            </div>
          </Field>
        </div>

        <YesNoField
          label="Individuelle Verfügbarkeitsprüfung erforderlich?"
          hint="Standardmäßig ja. Wir fragen jede Anfrage individuell bei euch ab — kein Auto-Booking."
          value={a.individualCheck} onChange={v => set('individualCheck', v)} />
      </div>
    </div>
  );
}
window.StepAvailability = StepAvailability;

function YesNoField({ label, hint, value, onChange }) {
  return (
    <div className="ob-field full">
      <span className="ob-field-label">{label}</span>
      <div className="ob-radio-row">
        <button type="button" className={'ob-radio ' + (value === true ? 'on' : '')} onClick={() => onChange(true)}>
          <span className="ob-radio-dot" /> Ja
        </button>
        <button type="button" className={'ob-radio ' + (value === false ? 'on' : '')} onClick={() => onChange(false)}>
          <span className="ob-radio-dot" /> Nein
        </button>
      </div>
      {hint && <span className="ob-field-hint">{hint}</span>}
    </div>
  );
}

// ============================================================
// STEP 12 — Pricing
// ============================================================
function StepPricing({ data, update }) {
  const p = data.pricing;
  const set = (k, v) => update({ pricing: { ...p, [k]: v } });
  return (
    <div className="ob-step">
      <StepHeader
        title="Preis und Abrechnung"
        lead="Du hinterlegst deine Netto-Preise für das Event. Auf dieser Basis berechnet Firmengolf den Verkaufspreis für das Unternehmen."
        maxWidth={680}
      />
      <div className="ob-form">
        <div className="ob-fixed full">
          <div className="ob-fixed-head">
            <span className="ob-fixed-l">Dein Netto-Preis bleibt dein Netto-Preis</span>
          </div>
          <p className="ob-fixed-body">
            Dein hinterlegter Netto-Preis ist der Betrag, den du nach Durchführung des Events an
            Firmengolf abrechnest. Der Firmengolf-Aufschlag wird zusätzlich kalkuliert und
            nicht von deinem Anteil abgezogen.
          </p>
        </div>
        <div className="ob-fixed full">
          <div className="ob-fixed-head">
            <span className="ob-fixed-l">So läuft die Abrechnung</span>
          </div>
          <p className="ob-fixed-body">
            Nach dem Event stellst du deine Leistung direkt an Firmengolf in Rechnung. Das
            Unternehmen erhält die Gesamtrechnung von Firmengolf.
          </p>
        </div>
        <div className="ob-portal-note full">
          <span className="ob-portal-note-ic"><Icon name="meeting" size={18} /></span>
          <div>
            <div className="ob-portal-note-h">Unsere Rechnungsdaten findest du im Portal</div>
            <p>Sobald deine Anfrage bestätigt ist, findest du im Partner-Portal alle Rechnungsdaten von Firmengolf.</p>
          </div>
          <a className="ob-portal-note-btn" href="partner-portal/Profil.html">Im Portal ansehen</a>
        </div>
        <div className="ob-fixed full">
          <div className="ob-fixed-head">
            <span className="ob-fixed-l">Bitte immer angeben</span>
            <span className="ob-fixed-badge">Pflicht</span>
          </div>
          <p className="ob-fixed-body">
            Gib auf jeder Rechnung die jeweilige <strong>Anfragenummer</strong> an, zum Beispiel
            FG-26-001. So können wir deine Rechnung eindeutig dem richtigen Event zuordnen.
          </p>
        </div>
      </div>
    </div>
  );
}
window.StepPricing = StepPricing;

// ============================================================
// STEP 13 — Media (uploads)
// ============================================================
function StepMedia({ data, update }) {
  const m = data.media;
  const set = (k, v) => update({ media: { ...m, [k]: v } });

  const handleLogo = (e) => {
    const f = e.target.files[0];
    if (f) set('logo', { name: f.name, size: f.size });
  };
  const handleHero = (e) => {
    const f = e.target.files[0];
    if (f) set('hero', { name: f.name, size: f.size });
  };
  const handleGallery = (e) => {
    const files = Array.from(e.target.files);
    if (files.length) {
      set('gallery', [...m.gallery, ...files.map(f => ({ name: f.name, size: f.size }))]);
    }
  };

  return (
    <div className="ob-step">
      <StepHeader
        title="Bilder, die euren Platz zeigen."
        lead="Logo und Titelbild sind wichtig, aber du kannst auch ohne weitermachen — wir erinnern dich daran, sobald wir dein Profil prüfen. Die Bildrechte-Bestätigung ist Pflicht."
        maxWidth={680}
      />

      <div className="ob-uploads">
        <UploadRow
          title="Logo"
          hint="PNG oder SVG, transparenter Hintergrund bevorzugt. Erscheint klein neben dem Platznamen."
          value={m.logo}
          onChange={handleLogo}
          aspect="logo"
          onRemove={() => set('logo', null)}
        />
        <UploadRow
          title="Titelbild"
          hint="Querformat, mindestens 1600 px breit. Das große Foto in Suche und Profil."
          value={m.hero}
          onChange={handleHero}
          aspect="hero"
          onRemove={() => set('hero', null)}
        />
        <UploadRow
          title="Galerie"
          hint="Optional. 3–8 Bilder vom Platz, Clubhaus, Range, Events. Drag & Drop möglich."
          value={null}
          onChange={handleGallery}
          aspect="gallery"
          multiple
        />
        {m.gallery.length > 0 && (
          <div className="ob-gallery-list">
            {m.gallery.map((g, i) => (
              <div key={i} className="ob-gallery-item">
                <div className="ob-gallery-thumb" />
                <div className="ob-gallery-name">{g.name}</div>
                <button type="button" className="ob-gallery-remove"
                        onClick={() => set('gallery', m.gallery.filter((_, j) => j !== i))}>
                  Entfernen
                </button>
              </div>
            ))}
          </div>
        )}
      </div>

      <label className="ob-consent">
        <input type="checkbox" checked={!!m.rightsConfirmed}
               onChange={(e) => set('rightsConfirmed', e.target.checked)} />
        <span>
          Ich bestätige, dass ich die Nutzungsrechte an den hochgeladenen Bildern habe und sie Firmengolf zur
          Veröffentlichung im Rahmen der Plattform überlassen darf.
        </span>
      </label>
      <Field label="Bildrechte-Notiz" full hint="Optional. Z. B. Fotograf:in, Quelle, Einschränkungen.">
        <TextInput value={m.rightsNote} onChange={(e) => set('rightsNote', e.target.value)} />
      </Field>
    </div>
  );
}
window.StepMedia = StepMedia;

function UploadRow({ title, hint, value, onChange, aspect, multiple, onRemove }) {
  const inputRef = React.useRef();
  return (
    <div className="ob-upload-row">
      <div className="ob-upload-text">
        <div className="ob-upload-l">{title}</div>
        <div className="ob-upload-h">{hint}</div>
      </div>
      <div className={'ob-upload-dropzone ' + (value ? 'filled ' : '') + 'a-' + aspect}>
        {value ? (
          <>
            <div className="ob-upload-preview" />
            <div className="ob-upload-meta">
              <span className="ob-upload-name">{value.name}</span>
              <button type="button" className="ob-upload-remove" onClick={onRemove}>Ersetzen</button>
            </div>
          </>
        ) : (
          <button type="button" className="ob-upload-cta" onClick={() => inputRef.current?.click()}>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"><rect x="3" y="6" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M21 16l-5-5-9 9"/></svg>
            <span>{aspect === 'gallery' ? 'Bilder hinzufügen' : 'Bild hochladen'}</span>
          </button>
        )}
        <input ref={inputRef} type="file" accept="image/*" multiple={multiple} hidden onChange={onChange} />
      </div>
    </div>
  );
}

// ============================================================
// STEP 14 — Review (summary + submit)
// ============================================================
function StepReview({ data, goToStep }) {
  const infraLabels = {
    'course-18': '18-Loch-Platz', 'course-9': '9-Loch-Platz', 'abc-platz': 'A-B-C Platz',
    'short-course': 'Kurzplatz', 'driving-range': 'Driving Range', 'range-covered': 'Überdachte Driving Range',
    'range-heated': 'Beheizte Abschlagplätze', 'range-flood': 'Flutlicht Range', 'trackman': 'TrackMan Range',
    'toptracer': 'Toptracer Range', 'short-game': 'Kurzspielbereich', 'practice-bunker': 'Übungsbunker',
    'indoor': 'Indoor Simulator', 'barrierefrei': 'Barrierearme Anlage',
    'meeting-room': 'Meetingraum', 'seminar': 'Seminarraum', 'conference': 'Konferenzraum',
    'workshop': 'Workshopraum', 'eventroom': 'Eventraum', 'golf-shop': 'Golf-Shop', 'shower': 'Duschen & Umkleiden',
    'beamer': 'Beamer', 'screen': 'Bildschirm', 'mic': 'Mikrofonanlage', 'wifi': 'WLAN',
    'flipchart': 'Flipchart', 'whiteboard': 'Whiteboard', 'moderation': 'Moderationsmaterial', 'catering-area': 'Cateringfläche',
    'restaurant': 'Restaurant', 'club-restaurant': 'Clubrestaurant', 'bistro': 'Bistro', 'cafe': 'Café',
    'bar': 'Bar', 'halfway': 'Halfway-Verpflegung', 'terrace': 'Terrasse', 'outdoor': 'Außenbereich',
    'lounge': 'Lounge Bereich', 'catering': 'Catering', 'breakfast': 'Frühstück', 'lunch': 'Lunch',
    'dinner': 'Abendessen', 'flying-buffet': 'Flying Buffet', 'buffet': 'Buffet', 'menu': 'Menü', 'bbq': 'BBQ',
    'drinks-flat': 'Getränkepauschale', 'coffee-break': 'Kaffeepause', 'snacks': 'Snackstation', 'welcome-drink': 'Welcome Drink',
    'tournament-food': 'Turnierverpflegung', 'round-food': 'Rundenverpflegung', 'lunch-packs': 'Lunchpakete',
    'vegetarian': 'Vegetarische Optionen', 'vegan': 'Vegane Optionen', 'allergy': 'Allergikerfreundlich',
    'regional': 'Regionale Küche', 'fine-dining': 'Fine Dining', 'standing-recep': 'Stehempfang', 'sekt-recep': 'Sektempfang',
    'coach': 'Golflehrer', 'trial-course': 'Schnupperkurs', 'platzreife': 'Platzreifekurs',
    'company-course': 'Firmenkurs', 'advanced-course': 'Fortgeschrittenenkurs', 'rental-clubs': 'Leihschläger', 'range-balls': 'Range-Bälle',
  };
  const formatLabels = {
    'schnupperkurs': 'Schnupperkurs', 'teamevent': 'Teamevent', 'firmenturnier': 'Firmenturnier',
    'kundenevent': 'Kundenevent', 'networking': 'Networking', 'afterwork': 'After-Work Golf',
    'sommerfest': 'Sommerfest', 'offsite': 'Offsite & Incentive', 'gesundheitstag': 'Gesundheitstag',
    'charity': 'Charity-Event', 'nacht-event': 'Nacht-Event',
  };
  const contactKinds = { event: 'Event', gastro: 'Gastronomie', coach: 'Golflehrer', billing: 'Abrechnung' };
  const golfTypeLabels = {
    'course-18': '18-Loch-Platz', 'course-27': '27-Loch-Platz', 'leading': 'Leading Course',
    'links': 'Links-Platz', 'course-9': '9-Loch-Platz', 'indoor-sim': 'Indoor-Simulator',
    'range': 'Driving-Range', 'short': 'Kurzplatz', 'pitch-putt': 'Pitch & Putt', 'mini-golf': 'Mini-Golf',
  };

  return (
    <div className="ob-step ob-step-wide ob-review">
      <StepHeader
        eyebrow="Letzter Schritt"
        title="Prüf deine Angaben — dann reichen wir ein."
        lead="Sieh nochmal über alles drüber, was du eingegeben hast. Du kannst jeden Abschnitt direkt bearbeiten, indem du auf „Bearbeiten“ klickst."
        maxWidth={760}
      />

      <div className="ob-rev-grid">
        <ReviewBlock title="Golfplatz" onEdit={() => goToStep('golftype')}>
          <ReviewRow l="Golfangebot" v={golfTypeLabels[data.golfType] || '—'} />
          <ReviewRow l="Öffentlicher Name" v={data.publicName} />
          <ReviewRow l="Betreiber" v={data.legalName} />
          <ReviewRow l="Website" v={data.website || '—'} />
          <ReviewRow l="Beschreibung" v={data.shortDesc || '—'} multiline />
        </ReviewBlock>

        <ReviewBlock title="Standort" onEdit={() => goToStep('location')}>
          <ReviewRow l="Adresse" v={`${data.street} ${data.houseNo}, ${data.zip} ${data.city}`} />
          <ReviewRow l="Bundesland" v={data.state || '—'} />
        </ReviewBlock>

        <ReviewBlock title="Hauptkontakt" onEdit={() => goToStep('main')}>
          <ReviewRow l="Name" v={`${data.mc_firstName} ${data.mc_lastName}`} />
          <ReviewRow l="Rolle" v={data.mc_role || '—'} />
          <ReviewRow l="E-Mail" v={data.mc_email} />
          <ReviewRow l="Telefon" v={data.mc_phone || '—'} />
        </ReviewBlock>

        <ReviewBlock title="Weitere Ansprechpartner" onEdit={() => goToStep('contacts')}>
          {data.contacts.length === 0 ? (
            <ReviewRow l="" v="Keine weiteren Ansprechpartner hinzugefügt." />
          ) : data.contacts.map((c, i) => (
            <ReviewRow key={i} l={c.role || 'Kontakt'} v={(c.name || '—') + (c.email ? ' · ' + c.email : '')} />
          ))}
        </ReviewBlock>

        <ReviewBlock title="Infrastruktur" onEdit={() => goToStep('infra')}>
          <ReviewRow l="" v={data.infra.map(id => infraLabels[id]).join(' · ') || '—'} multiline />
        </ReviewBlock>

        <ReviewBlock title="Kapazitäten" onEdit={() => goToStep('capacity')}>
          <ReviewRow l="Gruppengröße" v={`${data.cap.min || '?'} – ${data.cap.max || '?'} Gäste`} />
          {data.cap.range && <ReviewRow l="Range" v={data.cap.range + ' Plätze'} />}
          {data.cap.meeting && <ReviewRow l="Meetingraum" v={data.cap.meeting + ' Personen'} />}
          {data.cap.restaurantIn && <ReviewRow l="Restaurant innen" v={data.cap.restaurantIn + ' Personen'} />}
          {data.cap.restaurantOut && <ReviewRow l="Restaurant außen" v={data.cap.restaurantOut + ' Personen'} />}
          {data.cap.coaches && <ReviewRow l="Golflehrer" v={data.cap.coaches} />}
        </ReviewBlock>

        <ReviewBlock title="Veranstaltungstypen" onEdit={() => goToStep('formats')}>
          <ReviewRow l="" v={data.formats.map(id => formatLabels[id]).join(' · ') || '—'} multiline />
        </ReviewBlock>

        <ReviewBlock title="Verfügbarkeit" onEdit={() => goToStep('avail')}>
          <ReviewRow l="Wochentage" v={data.avail.weekdays.join(', ')} />
          <ReviewRow l="Wochenenden" v={data.avail.weekends ? 'Ja' : 'Nein'} />
          <ReviewRow l="Abends" v={data.avail.evenings ? 'Ja' : 'Nein'} />
          <ReviewRow l="Vorlauf" v={data.avail.leadTime + ' Tage'} />
          <ReviewRow l="Saison" v={`${data.avail.seasonFrom} – ${data.avail.seasonTo}`} />
        </ReviewBlock>

        <ReviewBlock title="Preis & Abrechnung" onEdit={() => goToStep('pricing')}>
          <ReviewRow l="Netto-Preise" v="Pro Event hinterlegt" />
          <ReviewRow l="Abrechnung" v="Direkt mit Firmengolf · Anfragenummer angeben" />
        </ReviewBlock>

        <ReviewBlock title="Medien" onEdit={() => goToStep('media')}>
          <ReviewRow l="Logo" v={data.media.logo?.name || 'Noch nicht hochgeladen'} />
          <ReviewRow l="Titelbild" v={data.media.hero?.name || 'Noch nicht hochgeladen'} />
          <ReviewRow l="Galerie" v={data.media.gallery.length + ' Bilder'} />
          <ReviewRow l="Bildrechte" v={data.media.rightsConfirmed ? 'Bestätigt ✓' : 'Noch nicht bestätigt'} />
        </ReviewBlock>
      </div>

      <div className="ob-rev-benefits">
        <div className="ob-rev-benefit">
          <span className="ob-rev-benefit-ic"><Icon name="custom" size={20} /></span>
          <div>
            <div className="ob-rev-benefit-h">Du gestaltest jedes Event selbst</div>
            <p>Nach der Anmeldung legst du jedes Event einzeln an und stellst es online — ganz nach deinen Vorstellungen.</p>
          </div>
        </div>
        <div className="ob-rev-benefit">
          <span className="ob-rev-benefit-ic"><Icon name="tournament" size={20} /></span>
          <div>
            <div className="ob-rev-benefit-h">Bevorzugt bei besonderen Events</div>
            <p>Kommen bei uns Anfragen für besondere Firmenevents rein, wirst du als registrierter Partner bevorzugt angefragt.</p>
          </div>
        </div>
        <div className="ob-rev-benefit">
          <span className="ob-rev-benefit-ic"><Icon name="restaurant" size={20} /></span>
          <div>
            <div className="ob-rev-benefit-h">Eine Rechnung, null Aufwand</div>
            <p>Du, Gastronomie und Shuttle stellen uns alle Leistungen in Rechnung — wir bündeln und rechnen mit dem Unternehmen ab.</p>
          </div>
        </div>
      </div>

      <div className="ob-form" style={{ marginTop: 28 }}>
        <Field label="Willst du uns noch etwas mitteilen?" full hint="Hat etwas gefehlt, gibt es Besonderheiten oder Wünsche? Optional.">
          <TextArea value={data.finalNote || ''} onChange={(e) => update({ finalNote: e.target.value })}
                    placeholder="Dein Hinweis an das Firmengolf-Team…" />
        </Field>
      </div>

      <div className="ob-rev-foot">
        <div className="ob-rev-foot-h">Was passiert nach dem Absenden?</div>
        <p>
          Wir setzen den Status auf <strong>„zur Prüfung“</strong> und melden uns innerhalb eines Werktags.
          Vor der Veröffentlichung gehen wir die Daten persönlich mit dir durch und schalten dein Profil frei.
          Nichts wird automatisch online gestellt.
        </p>
      </div>
    </div>
  );
}
window.StepReview = StepReview;

function ReviewBlock({ title, children, onEdit }) {
  return (
    <section className="ob-rev-block">
      <div className="ob-rev-head">
        <h3 className="ob-rev-title">{title}</h3>
        <button type="button" className="ob-rev-edit" onClick={onEdit}>Bearbeiten</button>
      </div>
      <div className="ob-rev-body">{children}</div>
    </section>
  );
}
function ReviewRow({ l, v, multiline }) {
  return (
    <div className={'ob-rev-row ' + (multiline ? 'multi' : '')}>
      {l && <span className="ob-rev-l">{l}</span>}
      <span className="ob-rev-v">{v || '—'}</span>
    </div>
  );
}

// ============================================================
// STEP 15 — Success
// ============================================================
function StepSuccess({ data }) {
  const refNo = 'FG-PARTNER-' + Math.floor(100000 + Math.random() * 900000);
  return (
    <div className="ob-success">
      <div className="ob-success-mark">
        <svg viewBox="0 0 64 64" width="56" height="56" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round">
          <circle cx="32" cy="32" r="28" />
          <path d="M20 33l8 8 16-18" />
        </svg>
      </div>
      <div className="ob-eyebrow">Eingereicht</div>
      <h1 className="ob-step-title big" style={{ maxWidth: 720 }}>
        Danke — dein Golfplatz wurde zur <span className="ob-italic">Prüfung</span> eingereicht.
      </h1>
      <p className="ob-intro-lead" style={{ maxWidth: 600 }}>
        Wir prüfen die Angaben in den nächsten 1–2 Werktagen und melden uns persönlich bei dir,
        wenn etwas fehlt oder unklar ist. In der Zwischenzeit findest du dein Profil im Status
        <strong> „in Prüfung“</strong> im Partnerportal.
      </p>

      <div className="ob-success-receipt">
        <div><span>Platz</span><span>{data.publicName || '—'}</span></div>
        <div><span>Hauptkontakt</span><span>{`${data.mc_firstName} ${data.mc_lastName}`} · {data.mc_email}</span></div>
        <div><span>Status</span><span><span className="ob-pill-status">In Prüfung</span></span></div>
        <div><span>Vorgangs-Nr.</span><span className="ob-mono">{refNo}</span></div>
      </div>

      <div className="ob-success-checklist">
        <div className="ob-checklist-h">Was als Nächstes passiert</div>
        <div className="ob-checklist">
          <div className="ob-check-row done">
            <span className="ob-check-mark">✓</span>
            <div>
              <div className="ob-check-t">Profil eingereicht</div>
              <div className="ob-check-b">Soeben durch dich</div>
            </div>
          </div>
          <div className="ob-check-row active">
            <span className="ob-check-mark">2</span>
            <div>
              <div className="ob-check-t">Firmengolf prüft die Angaben</div>
              <div className="ob-check-b">Innerhalb 1–2 Werktagen</div>
            </div>
          </div>
          <div className="ob-check-row">
            <span className="ob-check-mark">3</span>
            <div>
              <div className="ob-check-t">Onboarding-Call (15 Min.)</div>
              <div className="ob-check-b">Wir gehen offene Fragen durch.</div>
            </div>
          </div>
          <div className="ob-check-row">
            <span className="ob-check-mark">4</span>
            <div>
              <div className="ob-check-t">Profil freigeschaltet</div>
              <div className="ob-check-b">Du bekommst die erste Anfrage.</div>
            </div>
          </div>
        </div>
      </div>

      <div className="ob-success-ctas">
        <a className="ob-btn-primary" href="index.html">Zur Firmengolf-Startseite</a>
        <button type="button" className="ob-btn-text"
                onClick={() => {
                  localStorage.removeItem('firmengolf-onboarding-v1');
                  window.location.reload();
                }}>
          Neuen Platz einrichten
        </button>
      </div>
    </div>
  );
}
window.StepSuccess = StepSuccess;
