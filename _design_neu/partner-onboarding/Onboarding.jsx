/* eslint-disable */
// =============================================================
// Partner Onboarding — main shell.
// 12 Schritte gegliedert in 3 Kapitel, je mit Intro-Slide.
// Layout:  [Topbar]
//          [Stage — current step renders here]
//          [Progress bar segments] [Zurück · Weiter]
// State persists in localStorage between sessions.
// =============================================================

const { useState, useEffect, useMemo } = React;

// ---------- Step manifest ----------
// id: unique key | kind: 'intro' | 'form' | 'review' | 'success'
// chapter: 1-3, intro: chapter intro (no progress fill on this slide)
const STEPS = [
  // Chapter 1 — "Erzähl uns von deinem Platz"
  { id: 'intro-1',   chapter: 1, kind: 'intro',  component: 'StepWelcome' },
  { id: 'golftype',  chapter: 1, kind: 'form',   component: 'StepGolfType',  label: 'Golfangebot' },
  { id: 'basics',    chapter: 1, kind: 'form',   component: 'StepBasics',    label: 'Basisdaten' },
  { id: 'location',  chapter: 1, kind: 'form',   component: 'StepLocation',  label: 'Standort' },
  { id: 'arrival',   chapter: 1, kind: 'form',   component: 'StepArrival',   label: 'Anfahrt & Location' },
  { id: 'main',      chapter: 1, kind: 'form',   component: 'StepMainContact', label: 'Hauptkontakt' },
  { id: 'contacts',  chapter: 1, kind: 'form',   component: 'StepContacts',  label: 'Weitere Ansprechpartner', skippable: true },

  // Chapter 2 — "Was du anbieten kannst"
  { id: 'intro-2',   chapter: 2, kind: 'intro',  component: 'StepChapter2' },
  { id: 'infra',     chapter: 2, kind: 'form',   component: 'StepInfrastructure', label: 'Infrastruktur' },
  { id: 'gastro',    chapter: 2, kind: 'form',   component: 'StepGastronomy', label: 'Gastronomie' },
  { id: 'capacity',  chapter: 2, kind: 'form',   component: 'StepCapacity',  label: 'Kapazitäten' },
  { id: 'formats',   chapter: 2, kind: 'form',   component: 'StepFormats',   label: 'Veranstaltungstypen' },

  // Chapter 3 — "Verfügbarkeit, Preis & Medien"
  { id: 'intro-3',   chapter: 3, kind: 'intro',  component: 'StepChapter3' },
  { id: 'avail',     chapter: 3, kind: 'form',   component: 'StepAvailability', label: 'Verfügbarkeit' },
  { id: 'pricing',   chapter: 3, kind: 'form',   component: 'StepPricing',   label: 'Preis & Abrechnung' },
  { id: 'media',     chapter: 3, kind: 'form',   component: 'StepMedia',     label: 'Medien' },
  { id: 'review',    chapter: 3, kind: 'review', component: 'StepReview',    label: 'Prüfung' },

  // Final
  { id: 'success',   chapter: 0, kind: 'success', component: 'StepSuccess' },
];

const STORAGE_KEY = 'firmengolf-onboarding-v1';

// ---------- Initial data ----------
const initialData = () => ({
  // golf offering type (primary)
  golfType: '',
  // basics
  publicName: '', legalName: '', website: '', shortDesc: '',
  // location
  street: '', houseNo: '', zip: '', city: '', state: '', mapsUrl: '',
  // arrival & location
  arrival: { car: '', parking: '', eStation: false, train: '', shuttle: '' },
  // main contact
  mc_firstName: '', mc_lastName: '', mc_role: '', mc_email: '', mc_phone: '',
  // additional contacts
  contacts: [],
  // infrastructure (set of ids)
  infra: [],
  // capacities
  cap: { min: '', max: '', range: '', putting: '', short: '', meeting: '', restaurantIn: '', restaurantOut: '', coaches: '', parking: '' },
  // formats (set of ids)
  formats: [],
  // availability
  avail: {
    weekdays: ['Di', 'Mi', 'Do'],
    weekends: false,
    evenings: false,
    leadTime: '14',
    seasonFrom: 'März',
    seasonTo: 'Oktober',
    individualCheck: true,
  },
  // pricing
  pricing: { markup: '20', vat: 'yes', method: 'Gesammelt — eine Rechnung an Firmengolf', bank: 'yes', note: '' },
  // media
  media: { logo: null, hero: null, gallery: [], rightsConfirmed: false, rightsNote: '' },
});

// ---------- helper ----------
function loadState() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return null;
    return JSON.parse(raw);
  } catch { return null; }
}
function saveState(state) {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
  } catch {}
}

// ---------- Topbar ----------
function Topbar({ onSave, onHelp }) {
  return (
    <header className="ob-topbar">
      <a className="ob-brand" href="index.html" aria-label="Firmengolf Startseite">
        <img src="assets/logo/firmengolf-wordmark.png" alt="Firmengolf" />
      </a>
      <div className="ob-top-actions">
        <button className="ob-top-pill" onClick={onHelp}>Noch Fragen?</button>
        <button className="ob-top-pill" onClick={onSave}>Speichern &amp; beenden</button>
      </div>
    </header>
  );
}

// ---------- Footer-nav (progress + buttons) ----------
function FooterNav({ stepIndex, totalSteps, canContinue, onBack, onContinue, onSkip, skippable, isLast, isIntro }) {
  // Chapter-progress: 3 segments with headings (matches the Event-Anfrage stepper)
  const chapters = [
    { c: 1, label: 'Dein Platz' },
    { c: 2, label: 'Dein Angebot' },
    { c: 3, label: 'Verfügbarkeit & Preis' },
  ];
  const segments = chapters.map(({ c, label }) => {
    const formStepsInChapter = STEPS.map((s, i) => ({ ...s, i })).filter(s => s.chapter === c && s.kind !== 'intro' && s.kind !== 'success');
    const total = formStepsInChapter.length;
    let done = 0;
    let active = false;
    for (const s of formStepsInChapter) {
      if (s.i < stepIndex) done += 1;
      else if (s.i === stepIndex) active = true;
    }
    const ratio = total === 0 ? 0 : (active ? (done + 0.5) / total : done / total);
    return { label, ratio: Math.max(0, Math.min(1, ratio)), done: ratio >= 1, active };
  });
  const activeChapter = segments.find(s => s.active) || (segments.filter(s => s.done).pop());

  return (
    <footer className="ob-footer">
      <div className="ob-foot-inner">
        <div className="ob-prog" aria-label="Fortschritt">
          {segments.map((s, i) => (
            <div key={i} className={'ob-prog-seg ' + (s.done ? 'done ' : '') + (s.active ? 'on' : '')}>
              <span className="ob-prog-bar"><span className="ob-prog-fill" style={{ width: (s.ratio * 100).toFixed(1) + '%' }} /></span>
              <span className="ob-prog-label">{s.label}</span>
            </div>
          ))}
        </div>
        {activeChapter && <div className="ob-prog-count">{activeChapter.label}</div>}
        <div className="ob-nav">
          <button
            className="ob-btn-text"
            onClick={onBack}
            disabled={stepIndex === 0}>
            Zurück
          </button>
          <div className="ob-nav-right">
            {skippable && (
              <button className="ob-btn-text" onClick={onSkip}>
                Überspringen
              </button>
            )}
            <button
              className="ob-btn-primary"
              onClick={onContinue}
              disabled={!canContinue}>
              {isLast ? 'Zur Prüfung einreichen' : isIntro ? 'Start' : 'Weiter'}
            </button>
          </div>
        </div>
      </div>
    </footer>
  );
}

// ---------- Save toast ----------
function Toast({ msg, onDone }) {
  useEffect(() => {
    const t = setTimeout(onDone, 2400);
    return () => clearTimeout(t);
  }, [msg]);
  return <div className="ob-toast">{msg}</div>;
}

// ---------- Help drawer ----------
function HelpDrawer({ open, onClose }) {
  if (!open) return null;
  return (
    <div className="ob-help-scrim" onClick={onClose}>
      <aside className="ob-help" onClick={(e) => e.stopPropagation()}>
        <button className="ob-help-close" onClick={onClose} aria-label="Schließen">×</button>
        <div className="ob-help-eyebrow">Hilfe</div>
        <h2 className="ob-help-h">Wir sind erreichbar.</h2>
        <p className="ob-help-p">
          Wenn du an einer Stelle hängen bleibst — schreib uns kurz oder ruf an.
          Wir helfen dir durch den Prozess und beantworten alle Fragen zur Partnerschaft.
        </p>
        <div className="ob-help-channels">
          <a href="mailto:partner@firmengolf.de" className="ob-help-channel">
            <span className="ob-help-l">Partner-Team</span>
            <span className="ob-help-v">partner@firmengolf.de</span>
          </a>
          <a href="tel:+494012345678" className="ob-help-channel">
            <span className="ob-help-l">Telefon</span>
            <span className="ob-help-v">+49 40 123 456 78</span>
          </a>
        </div>
        <div className="ob-help-foot">
          <span className="ob-help-l">Antwortzeit</span>
          <span className="ob-help-v-sm">Innerhalb eines Werktags · Mo–Fr 09–18 Uhr</span>
        </div>
      </aside>
    </div>
  );
}

// ---------- Main App ----------
function OnboardingApp() {
  const restored = loadState();
  const [data, setData] = useState(restored?.data || initialData());
  const [stepIndex, setStepIndex] = useState(restored?.stepIndex ?? 0);
  const [toast, setToast] = useState(null);
  const [helpOpen, setHelpOpen] = useState(false);
  const [submitted, setSubmitted] = useState(restored?.submitted || false);

  // Persist on every change
  useEffect(() => {
    saveState({ data, stepIndex, submitted });
  }, [data, stepIndex, submitted]);

  const step = STEPS[stepIndex];
  const isLast = step.kind === 'review';
  const isIntro = step.kind === 'intro';
  const isSuccess = step.kind === 'success';

  const updateData = (patch) =>
    setData(d => typeof patch === 'function' ? patch(d) : ({ ...d, ...patch }));

  const validateStep = useMemo(() => {
    if (step.kind === 'intro' || step.kind === 'success') return true;
    if (step.skippable) return true;
    if (step.id === 'golftype') return !!data.golfType;
    if (step.id === 'basics')   return !!(data.publicName && data.legalName);
    if (step.id === 'location') return !!(data.street && data.zip && data.city && data.state);
    if (step.id === 'main')     return !!(data.mc_firstName && data.mc_lastName && data.mc_email);
    if (step.id === 'infra')    return data.infra.length > 0;
    if (step.id === 'capacity') return !!(data.cap.min && data.cap.max);
    if (step.id === 'formats')  return data.formats.length > 0;
    if (step.id === 'media')    return !!data.media.rightsConfirmed;
    if (step.id === 'review')   return true;
    return true;
  }, [step, data]);

  const handleContinue = () => {
    if (isLast) {
      setSubmitted(true);
      setStepIndex(STEPS.length - 1);
      window.scrollTo({ top: 0, behavior: 'instant' });
      return;
    }
    if (stepIndex < STEPS.length - 1) {
      setStepIndex(stepIndex + 1);
      window.scrollTo({ top: 0, behavior: 'instant' });
    }
  };
  const handleBack = () => {
    if (stepIndex > 0) {
      setStepIndex(stepIndex - 1);
      window.scrollTo({ top: 0, behavior: 'instant' });
    }
  };
  const handleSkip = () => handleContinue();

  const handleSave = () => {
    saveState({ data, stepIndex, submitted });
    setToast('Fortschritt gespeichert. Wenn du zurückkommst, geht es hier weiter.');
  };

  // Find the right component
  const StepComp = window[step.component] || (() => null);

  // Hide footer on success screen
  return (
    <div className="ob-shell">
      <Topbar onSave={handleSave} onHelp={() => setHelpOpen(true)} />
      <main className={'ob-stage ' + (isIntro ? 'is-intro ' : '') + (isSuccess ? 'is-success ' : '')}
            key={step.id}>
        <StepComp
          data={data}
          update={updateData}
          goToStep={(id) => {
            const i = STEPS.findIndex(s => s.id === id);
            if (i >= 0) setStepIndex(i);
          }}
        />
      </main>
      {!isSuccess && (
        <FooterNav
          stepIndex={stepIndex}
          totalSteps={STEPS.length}
          canContinue={validateStep}
          onBack={handleBack}
          onContinue={handleContinue}
          onSkip={handleSkip}
          skippable={step.skippable}
          isLast={isLast}
          isIntro={isIntro}
        />
      )}
      {toast && <Toast msg={toast} onDone={() => setToast(null)} />}
      <HelpDrawer open={helpOpen} onClose={() => setHelpOpen(false)} />
    </div>
  );
}

ReactDOM.createRoot(document.getElementById('ob-app')).render(<OnboardingApp />);
