/* eslint-disable */
// =============================================================
// CareerPage — Careers / "join us" page. Route: #/karriere
// Hero · Werte · Benefits · O-Ton · Gallery · Offene Stellen
// (filterable) · Bewerbungsprozess · CTA. Exposes window.CareerPage.
// =============================================================
var { useState: useCarState } = React;

const CAR_VALUES = [
  { n: '01', t: 'Unbeschwert', b: 'Wir nehmen die Arbeit ernst, uns selbst nicht zu sehr. Gute Laune ist hier kein Nice-to-have.' },
  { n: '02', t: 'Eigenverantwortung', b: 'Kurze Wege, echtes Vertrauen. Du gestaltest deinen Bereich — niemand schaut dir über die Schulter.' },
  { n: '03', t: 'Nahbar & echt', b: 'Kein Konzern-Sprech, keine Politik. Wir sagen, was wir denken — freundlich und direkt.' },
  { n: '04', t: 'Wirkung', b: 'Wir bringen tausende Menschen raus in Bewegung. Was du baust, spürt man am nächsten Wochenende.' },
];

const CAR_PERKS = [
  { ic: 'flag',    t: 'Golf als Benefit', s: 'Logisch. Schläger, Coaching und freie Runden auf unseren Partnerplätzen.' },
  { ic: 'sun',     t: '30 Tage Urlaub', s: 'Plus den Tag nach dem Sommerfest frei — versprochen.' },
  { ic: 'home',    t: 'Hybrid & flexibel', s: 'München-Office mit Terrasse, oder von zuhause. Du entscheidest, wo du am besten bist.' },
  { ic: 'book',    t: 'Lernbudget', s: '1.500 € im Jahr für Kurse, Konferenzen, Bücher — oder die Platzreife.' },
  { ic: 'heart',   t: 'Mental Health', s: 'Zugang zu Coaching & Beratung, weil draußen sein nicht alles heilt.' },
  { ic: 'users',   t: 'Team-Tage draußen', s: 'Wir testen, was wir verkaufen — regelmäßig gemeinsam auf dem Platz.' },
];

const CAR_TEAMS = ['Alle', 'Engineering', 'Partnerships', 'Events', 'Marketing', 'Operations'];
const CAR_JOBS = [
  { title: 'Senior Frontend Engineer (m/w/d)', team: 'Engineering', type: 'Vollzeit', loc: 'München / Remote' },
  { title: 'Backend Engineer — Buchungssystem (m/w/d)', team: 'Engineering', type: 'Vollzeit', loc: 'München / Remote' },
  { title: 'Partner Manager Golfplätze (m/w/d)', team: 'Partnerships', type: 'Vollzeit', loc: 'Hybrid · Süddeutschland' },
  { title: 'Event Coordinator (m/w/d)', team: 'Events', type: 'Vollzeit', loc: 'München' },
  { title: 'Performance Marketing Manager (m/w/d)', team: 'Marketing', type: 'Vollzeit', loc: 'München / Remote' },
  { title: 'Content & Social Lead (m/w/d)', team: 'Marketing', type: 'Teilzeit möglich', loc: 'Remote' },
  { title: 'Customer Success Manager (m/w/d)', team: 'Operations', type: 'Vollzeit', loc: 'München' },
  { title: 'Werkstudent:in People & Operations (m/w/d)', team: 'Operations', type: 'Werkstudent', loc: 'München' },
];

const CAR_STEPS = [
  { t: 'Bewerbung', b: 'Lebenslauf reicht — kein Anschreiben-Roman. Ein, zwei Sätze, warum du Lust hast, genügen.' },
  { t: 'Kennenlernen', b: 'Ein lockeres Video-Gespräch. Wir lernen uns kennen, du fragst uns Löcher in den Bauch.' },
  { t: 'Schnuppern', b: 'Eine kleine Aufgabe aus deinem echten Alltag — oder ein halber Tag mit dem Team.' },
  { t: 'Eine Runde Golf', b: 'Optional, aber beliebt: 9 Loch mit dem Team. Können egal — Hauptsache, es passt menschlich.' },
];

function CarIco({ name }) {
  const p = {
    flag:  <><path d="M5 21V4l9 2.5L5 9"/><circle cx="17" cy="17" r="3"/></>,
    sun:   <><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></>,
    home:  <><path d="M3 11l9-7 9 7"/><path d="M5 10v10h14V10"/><path d="M9 20v-6h6v6"/></>,
    book:  <><path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z"/><path d="M19 3v18"/></>,
    heart: <><path d="M20.8 5.6a5 5 0 0 0-7.1 0L12 7.3l-1.7-1.7a5 5 0 1 0-7.1 7.1L12 21l8.8-8.3a5 5 0 0 0 0-7.1z"/></>,
    users: <><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/></>,
  };
  return <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">{p[name]}</svg>;
}

function CareerPage() {
  const [team, setTeam] = useCarState('Alle');
  const A = window.A;
  const jobs = CAR_JOBS.filter(j => team === 'Alle' || j.team === team);
  const mailto = (subject) => 'mailto:jobs@firmengolf.de?subject=' + encodeURIComponent(subject);

  return (
    <div className="career" data-screen-label="Karriere">
      {/* HERO */}
      <section className="car-hero">
        <div className="car-hero-photo" style={{ backgroundImage: `url('${A('assets/imagery/event-team.jpg')}')` }}>
          <div className="car-hero-scrim" />
          <div className="car-hero-inner">
            <div className="car-hero-eyebrow">Karriere bei Firmengolf</div>
            <h1>Komm ins Team, das andere <em>rausbringt</em>.</h1>
            <p className="car-hero-sub">
              Wir machen Golf für Unternehmen zugänglich — und suchen Leute, die Lust haben, daraus
              das beste Stück Arbeitswoche zu machen. Für sich und für tausende Teams da draußen.
            </p>
            <div className="car-hero-ctas">
              <button className="fg-btn-ink lg" onClick={() => document.getElementById('car-jobs')?.scrollIntoView({ behavior: 'smooth' })}>
                Offene Stellen <span className="fg-arrow"><ArrowGlyph /></span>
              </button>
              <a className="fg-btn-ghost-light" href={mailto('Initiativbewerbung')}>Initiativ bewerben →</a>
            </div>
          </div>
        </div>
      </section>

      {/* VALUES */}
      <section className="car-section">
        <div className="car-head">
          <div className="car-eyebrow">Wie wir ticken</div>
          <h2 className="car-h2">Vier Dinge, die uns <em>ausmachen</em></h2>
          <p className="car-lead">Keine Werte-Plakate fürs Intranet — sondern wie wir wirklich miteinander arbeiten.</p>
        </div>
        <div className="car-values">
          {CAR_VALUES.map(v => (
            <div className="car-value" key={v.n}>
              <div className="car-value-n">{v.n}</div>
              <div className="car-value-t">{v.t}</div>
              <div className="car-value-b">{v.b}</div>
            </div>
          ))}
        </div>
      </section>

      {/* PERKS */}
      <section className="car-section tint">
        <div className="car-inner">
          <div className="car-head">
            <div className="car-eyebrow">Was du bekommst</div>
            <h2 className="car-h2">Benefits, die wir selbst <em>wollen</em> würden</h2>
          </div>
          <div className="car-perks">
            {CAR_PERKS.map((p, i) => (
              <div className="car-perk" key={i}>
                <div className="car-perk-ic"><CarIco name={p.ic} /></div>
                <div>
                  <div className="car-perk-t">{p.t}</div>
                  <div className="car-perk-s">{p.s}</div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* PULL QUOTE (ink) */}
      <section className="car-section ink">
        <div className="car-inner">
          <div className="car-pull">
            <div className="car-eyebrow">O-Ton aus dem Team</div>
            <div className="car-pull-q">
              „Ich bin für ein Projekt gekommen und wegen der Leute geblieben. Selten habe ich ein Team
              erlebt, das so viel Verantwortung gibt — und so wenig Drama macht."
            </div>
            <div className="car-pull-by">Annika Reuß <span>· Product & Engineering, seit 2024 dabei</span></div>
          </div>
          <div className="car-gallery">
            <span className="g g1" style={{ backgroundImage: `url('${A('assets/imagery/event-summer-2.jpg')}')` }} />
            <span className="g" style={{ backgroundImage: `url('${A('assets/imagery/tile-grass.jpg')}')` }} />
            <span className="g" style={{ backgroundImage: `url('${A('assets/imagery/event-toast.jpg')}')` }} />
            <span className="g" style={{ backgroundImage: `url('${A('assets/imagery/event-corporate.jpg')}')` }} />
            <span className="g" style={{ backgroundImage: `url('${A('assets/imagery/hero-meadow.jpg')}')` }} />
          </div>
        </div>
      </section>

      {/* OPEN POSITIONS */}
      <section className="car-section" id="car-jobs">
        <div className="car-head">
          <div className="car-eyebrow">Offene Stellen</div>
          <h2 className="car-h2">Finde deinen <em>Platz</em></h2>
          <p className="car-lead">{CAR_JOBS.length} offene Rollen — und falls nichts dabei ist, freuen wir uns über deine Initiativbewerbung.</p>
        </div>
        <div className="car-jobs-filter">
          {CAR_TEAMS.map(t => (
            <button key={t} className={'car-fbtn ' + (team === t ? 'on' : '')} onClick={() => setTeam(t)}>
              {t}{t !== 'Alle' ? ` (${CAR_JOBS.filter(j => j.team === t).length})` : ''}
            </button>
          ))}
        </div>
        <div className="car-jobs">
          {jobs.map((j, i) => (
            <a className="car-job" key={i} href={mailto('Bewerbung: ' + j.title)}>
              <div>
                <div className="car-job-t">{j.title}</div>
                <div className="car-job-meta"><span>{j.team}</span><span>·</span><span>{j.type}</span><span>·</span><span>{j.loc}</span></div>
              </div>
              <span className="car-job-tag">{j.loc.includes('Remote') ? 'Remote möglich' : j.loc}</span>
              <span className="car-job-arrow"><ArrowGlyph size={14} /></span>
            </a>
          ))}
          {jobs.length === 0 && <div className="car-jobs-empty">In diesem Bereich ist gerade nichts offen — schick uns gern eine Initiativbewerbung.</div>}
        </div>
      </section>

      {/* PROCESS */}
      <section className="car-section tint">
        <div className="car-inner">
          <div className="car-head">
            <div className="car-eyebrow">So läuft's ab</div>
            <h2 className="car-h2">Vom Hallo zum <em>Willkommen</em></h2>
            <p className="car-lead">Schlank, schnell, ehrlich — meist sind es nur zwei bis drei Wochen.</p>
          </div>
          <div className="car-steps">
            {CAR_STEPS.map((s, i) => (
              <div className="car-step" key={i}>
                <div className="car-step-n">{i + 1}</div>
                <div className="car-step-t">{s.t}</div>
                <div className="car-step-b">{s.b}</div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* CTA */}
      <section className="car-cta">
        <div className="car-cta-card" style={{ backgroundImage: `url('${A('assets/imagery/hero-fairway-wide.jpg')}')` }}>
          <div className="car-cta-scrim" />
          <div className="car-cta-inner">
            <h2 className="car-cta-h">Nichts Passendes dabei? Erzähl uns trotzdem von dir.</h2>
            <p className="car-cta-p">Wir wachsen schnell — und gute Leute finden bei uns fast immer einen Platz. Schreib uns, was du kannst und worauf du Lust hast.</p>
            <a className="fg-btn-ink lg" href={mailto('Initiativbewerbung')} style={{ background: 'var(--paper-100)', color: 'var(--fairway-900)' }}>
              Initiativ bewerben <span className="fg-arrow" style={{ background: 'var(--fairway-200)' }}><ArrowGlyph /></span>
            </a>
          </div>
        </div>
      </section>
    </div>
  );
}

window.CareerPage = CareerPage;
