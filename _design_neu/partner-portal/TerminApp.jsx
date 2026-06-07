/* eslint-disable */
/* Firmengolf — Termin-Antwortseite. The page a course contact (e.g. the Pro)
   opens from their email link to respond to an event request.
   Reads ?req=<id>&as=<contactId> from the URL. */

const { useState: useStateTl } = React;

const params = new URLSearchParams(location.search);
const REQ = REQUESTS.find(r => String(r.id) === params.get("req")) || REQUESTS[0];
const ME = COURSE_TEAM.find(t => t.id === params.get("as")) || COURSE_TEAM.find(t => t.id === "mp") || COURSE_TEAM[0];
const FIRST = ME.name.split(" ")[0];

const TerminApp = () => {
  const initial = {};
  (REQ.wishDates || []).forEach(d => { initial[d.id] = d.responses[ME.id] || "pending"; });
  const [votes, setVotes] = useStateTl(initial);
  const [altOpen, setAltOpen] = useStateTl(false);
  const [altDate, setAltDate] = useStateTl("");
  const [altNote, setAltNote] = useStateTl("");
  const [submitted, setSubmitted] = useStateTl(false);

  const set = (did, v) => setVotes(p => ({ ...p, [did]: p[did] === v ? "pending" : v }));
  const anyConfirmed = Object.values(votes).some(v => v === "confirmed");
  const answered = Object.values(votes).every(v => v !== "pending");

  if (submitted) {
    return (
      <div className="tl-page">
        <div className="tl-bar">
          <img src="design-system/logo/firmengolf-wordmark.png" alt="Firmengolf" />
          <span className="ctx">Terminanfrage</span>
        </div>
        <div className="tl-wrap">
          <div className="tl-done">
            <div className="tl-done-ic"><Icon name="check" size={34} sw={2.4} /></div>
            <h2>Danke, {FIRST}!</h2>
            <p>Deine Rückmeldung ist gespeichert. Sobald alle aus dem Team reagiert haben, bestätigt {COURSE_TEAM.find(t => t.you).name.split(" ")[0]} den Termin und Firmengolf kümmert sich um Angebot und Buchung.</p>
            <a className="btn btn-ghost" style={{ marginTop: 28 }} href="Anfragen.html"><Icon name="external" size={14} /> Zur Anfrage im Portal</a>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="tl-page">
      <div className="tl-bar">
        <img src="design-system/logo/firmengolf-wordmark.png" alt="Firmengolf" />
        <span className="ctx">Terminanfrage · GC München West</span>
      </div>
      <div className="tl-wrap">
        <div className="tl-eyebrow">Deine Verfügbarkeit ist gefragt</div>
        <h1 className="tl-h">Hallo {FIRST} — passt einer dieser <em>Termine</em>?</h1>
        <p className="tl-lead">
          {REQ.company} möchte bei euch ein Event ausrichten. Als {ME.role} brauchen wir kurz deine Rückmeldung,
          welche Wunschtermine bei dir gehen. Dauert keine Minute.
        </p>

        <div className="tl-summary">
          <div className="tl-sum-co">{REQ.company}</div>
          <div className="tl-sum-grid">
            <div className="tl-sum-item"><div className="k">Veranstaltungstyp</div><div className="v"><Icon name="flag" size={14} /> {REQ.eventType}</div></div>
            <div className="tl-sum-item"><div className="k">Teilnehmer</div><div className="v"><Icon name="users" size={14} /> {REQ.participants} Personen</div></div>
            <div className="tl-sum-item"><div className="k">Zeitfenster</div><div className="v"><Icon name="clock" size={14} /> {REQ.slot}</div></div>
            <div className="tl-sum-item"><div className="k">Budget</div><div className="v"><Icon name="euro" size={14} /> {REQ.budget}</div></div>
          </div>
          <div className="tl-quote">„{REQ.msg}"</div>
        </div>

        <div className="tl-section-label">Wunschtermine — bitte zu jedem zu- oder absagen</div>
        {(REQ.wishDates || []).map(d => {
          const v = votes[d.id];
          return (
            <div className={`tl-date ${v === "confirmed" ? "confirmed" : v === "declined" ? "declined" : ""}`} key={d.id}>
              <div className="tl-date-top">
                <div>
                  <div className="tl-date-d">{d.date}</div>
                  <div className="tl-date-s">{d.slot}</div>
                </div>
                <div className="tl-date-btns">
                  <button className={`tl-btn yes ${v === "confirmed" ? "on" : ""}`} onClick={() => set(d.id, "confirmed")}>
                    <Icon name="check" size={15} sw={2.4} /> Passt
                  </button>
                  <button className={`tl-btn no ${v === "declined" ? "on" : ""}`} onClick={() => set(d.id, "declined")}>
                    <Icon name="x" size={15} sw={2.4} /> Geht nicht
                  </button>
                </div>
              </div>
            </div>
          );
        })}

        <div className="tl-alt">
          {!altOpen ? (
            <button className="tl-btn" onClick={() => setAltOpen(true)}><Icon name="plus" size={14} sw={2.2} /> Keiner passt? Alternativtermin vorschlagen</button>
          ) : (
            <div>
              <div className="tl-section-label" style={{ marginTop: 8 }}>Dein Alternativvorschlag</div>
              <input value={altDate} onChange={e => setAltDate(e.target.value)} placeholder="z. B. Fr, 21. Juni 2026 — ganztags" />
              <textarea rows={2} value={altNote} onChange={e => setAltNote(e.target.value)} placeholder="Kurze Notiz (optional) — warum dieser Termin besser passt"></textarea>
              <p className="tl-note">Dein Vorschlag geht an Firmengolf, die ihn mit {REQ.company} abstimmen.</p>
            </div>
          )}
        </div>

        <div className="tl-submit">
          <button className="btn btn-brand" style={{ fontSize: 15, padding: "12px 24px" }}
            onClick={() => setSubmitted(true)}
            disabled={!answered && !altOpen}>
            <Icon name="check" size={15} sw={2.4} /> Rückmeldung absenden
          </button>
          {!answered && !altOpen && <span className="tl-note" style={{ margin: 0 }}>Bitte zu jedem Termin antworten — oder eine Alternative vorschlagen.</span>}
          {answered && anyConfirmed && <span className="tl-note" style={{ margin: 0, color: "var(--fairway-700)", fontWeight: 600 }}>Super — du hast mindestens einen Termin zugesagt.</span>}
        </div>
      </div>
    </div>
  );
};

const tlRoot = ReactDOM.createRoot(document.getElementById("app"));
tlRoot.render(<TerminApp />);
