/* eslint-disable */
/* GC München West — Anfragen (request management + multi-party scheduling) */

const { useState: useStateReq } = React;

const REQ_FILTERS = [
  { id: "alle",         label: "Alle" },
  { id: "neu",          label: "Neu" },
  { id: "bearbeitung",  label: "In Abstimmung" },
  { id: "bestaetigt",   label: "Bestätigt" },
  { id: "abgelehnt",    label: "Abgelehnt" },
  { id: "abgeschlossen", label: "Abgeschlossen" },
];
const REQ_STATUS_LABEL = {
  neu: "Neu", bearbeitung: "In Abstimmung", bestaetigt: "Bestätigt", abgelehnt: "Abgelehnt", abgeschlossen: "Abgeschlossen",
};
const reqNo = (r) => "FG-26-" + String(r.id).padStart(3, "0");
const copyReqNo = (r) => {
  const no = reqNo(r);
  if (navigator.clipboard) navigator.clipboard.writeText(no).catch(() => {});
  window.fgToast("Anfragenummer " + no + " kopiert");
};
const teamById = (id) => COURSE_TEAM.find(t => t.id === id) || { name: id, initials: "?", avatar: "default", role: "" };
const YOU = COURSE_TEAM.find(t => t.you) || COURSE_TEAM[0];

// Save a confirmed date to the calendar via a downloadable .ics file.
const MONTHS_DE = { Januar: 0, Februar: 1, "März": 2, April: 3, Mai: 4, Juni: 5, Juli: 6, August: 7, September: 8, Oktober: 9, November: 10, Dezember: 11 };
function parseDE(str) {
  const m = str.match(/(\d{1,2})\.\s*([A-Za-zä]+)\s*(\d{4})/);
  if (!m || MONTHS_DE[m[2]] == null) return null;
  return new Date(Date.UTC(+m[3], MONTHS_DE[m[2]], +m[1], 8, 0, 0));
}
function saveTermin(r, wishDate) {
  const dt = parseDE(wishDate.date);
  const pad = n => String(n).padStart(2, "0");
  const fmtICS = d => `${d.getUTCFullYear()}${pad(d.getUTCMonth() + 1)}${pad(d.getUTCDate())}T${pad(d.getUTCHours())}${pad(d.getUTCMinutes())}00Z`;
  if (dt) {
    const end = new Date(dt.getTime() + 4 * 3600 * 1000);
    const ics = [
      "BEGIN:VCALENDAR", "VERSION:2.0", "PRODID:-//Firmengolf//Partner//DE", "BEGIN:VEVENT",
      `UID:${r.id || r.company}-${wishDate.id}@firmengolf`, `DTSTAMP:${fmtICS(new Date())}`,
      `DTSTART:${fmtICS(dt)}`, `DTEND:${fmtICS(end)}`,
      `SUMMARY:${r.eventType} — ${r.company}`,
      `DESCRIPTION:${r.participants} Personen · ${wishDate.slot} · Firmengolf-Event`,
      "LOCATION:GC München West", "END:VEVENT", "END:VCALENDAR",
    ].join("\r\n");
    const url = URL.createObjectURL(new Blob([ics], { type: "text/calendar" }));
    const a = document.createElement("a");
    a.href = url; a.download = `Termin-${r.company.replace(/\s+/g, "-")}.ics`;
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  }
  window.fgToast("Termin gespeichert — " + wishDate.date);
}

// ---- build mutable coordination state from the data ----
function initCoord() {
  const m = {};
  REQUESTS.forEach(r => {
    const dates = {};
    (r.wishDates || []).forEach(d => { dates[d.id] = { ...d.responses }; });
    m[r.id] = { dates, finalDateId: r.finalDateId || null, alts: (r.alternatives || []).slice(), handedOff: r.status === "bestaetigt" };
  });
  return m;
}

// who has fully responded = no "pending" left on any date
function hasResponded(coordReq, contactId) {
  const ids = Object.keys(coordReq.dates);
  if (!ids.length) return true;
  return ids.every(did => (coordReq.dates[did][contactId] || "pending") !== "pending");
}

// ---------------- list item ----------------
const ReqItem = ({ r, cr, on, onClick }) => {
  const total = COURSE_TEAM.length;
  const done = COURSE_TEAM.filter(t => hasResponded(cr, t.id)).length;
  const showProg = (r.status === "neu" || r.status === "bearbeitung") && Object.keys(cr.dates).length > 0;
  return (
    <button className={`req-item ${on ? "on" : ""}`} onClick={onClick}>
      <span className={`av ${r.avatar}`}>{r.initials}</span>
      <span className="ri-main">
        <span className="ri-co">{r.company}</span>
        <span className="ri-meta">{r.eventType} · {r.participants} Pers. · {(r.wishDates && r.wishDates.length > 1) ? `${r.wishDates.length} Wunschtermine` : r.date}</span>
        <span className="ri-msg">{r.msg}</span>
        {showProg && <span className="ri-meta" style={{ color: done === total ? "var(--fairway-700)" : "var(--ink-500)", fontWeight: 600, marginTop: 6 }}>
          {done === total ? "✓ Alle haben reagiert" : `${done} von ${total} haben reagiert`}
        </span>}
      </span>
      <span className="ri-right">
        <span className="ri-when">{r.when}</span>
        <span className={`spill s-${r.status}`}>{r.status === "neu" && <span className="dot"></span>}{REQ_STATUS_LABEL[r.status]}</span>
      </span>
    </button>
  );
};

// ---------------- per-date vote chips ----------------
const VoteChip = ({ contactId, status }) => {
  const t = teamById(contactId);
  const mark = status === "confirmed" ? <Icon name="check" size={11} sw={2.6} />
    : status === "declined" ? <Icon name="x" size={11} sw={2.6} />
    : <Icon name="clock" size={11} />;
  return (
    <span className={`vote ${status}`}>
      <span className={`va ${t.avatar}`}>{t.initials}</span>
      {t.name.split(" ")[0]}
      <span className="vmark">{mark}</span>
    </span>
  );
};

// ---------------- detail ----------------
const ReqDetail = ({ r, cr, setVote, confirmDate, handOff, openMail, openAlt, sendReminder }) => {
  if (!r) return null;
  const hasCoord = Object.keys(cr.dates).length > 0;
  const total = COURSE_TEAM.length;
  const done = COURSE_TEAM.filter(t => hasResponded(cr, t.id)).length;
  const allDone = done === total;
  const isClosed = r.status === "abgelehnt";

  const availFor = (did) => COURSE_TEAM.filter(t => cr.dates[did][t.id] === "confirmed").length;

  return (
    <div className="req-detail">
      <div className="req-detail-head">
        <button className="req-no" onClick={() => copyReqNo(r)} title="Anfragenummer kopieren">
          <span className="req-no-hash">#{reqNo(r)}</span>
          <Icon name="copy" size={14} />
        </button>
        <div className="req-detail-top">
          <span className={`av ${r.avatar}`}>{r.initials}</span>
          <div style={{ flex: 1 }}>
            <div className="req-detail-co">{r.company}</div>
            <div className="req-detail-sub">{r.contact} · {r.role}</div>
          </div>
          <span className={`spill s-${r.status}`}>{r.status === "neu" && <span className="dot"></span>}{REQ_STATUS_LABEL[r.status]}</span>
        </div>
        <div className="req-contact-row">
          <a href={`mailto:${r.email}`}><Icon name="inbox" size={13} /> {r.email}</a>
          <a href={`tel:${r.phone.replace(/\s/g, "")}`}><Icon name="bell" size={13} /> {r.phone}</a>
        </div>
      </div>

      <div className="req-detail-body">
        {r.dual && (
          <div className="req-dual">
            <Icon name="users" size={16} />
            <span>Diese Anfrage liegt auch bei <b>Firmengolf</b>. Sobald euer Team einen Termin bestätigt, übernehmen wir Angebot &amp; Buchung.</span>
          </div>
        )}

        <div className="req-facts">
          <div className="req-fact"><div className="l">Veranstaltungstyp</div><div className="v"><Icon name="flag" size={14} /> {r.eventType}</div></div>
          <div className="req-fact"><div className="l">Teilnehmer</div><div className="v"><Icon name="users" size={14} /> {r.participants} Personen</div></div>
          <div className="req-fact"><div className="l">Zeitfenster</div><div className="v"><Icon name="clock" size={14} /> {r.slot}</div></div>
          <div className="req-fact"><div className="l">Budget</div><div className="v"><Icon name="euro" size={14} /> {r.budget}</div></div>
        </div>

        <div className="req-section-label">Nachricht</div>
        <div className="req-msg-block">„{r.msg}"</div>

        {/* ---- WUNSCHTERMINE / COORDINATION ---- */}
        {hasCoord && (
          <>
            <div className="coord-head">
              <div className="req-section-label" style={{ margin: 0 }}>Wunschtermine</div>
              <div className="coord-prog"><b>{done}</b> von {total} haben reagiert</div>
            </div>
            <div className="coord-bar"><div className="coord-bar-fill" style={{ width: `${(done / total) * 100}%` }}></div></div>

            <div className="wishdates">
              {r.wishDates.map(d => {
                const isFinal = cr.finalDateId === d.id;
                const youVote = cr.dates[d.id][YOU.id];
                const avail = availFor(d.id);
                const cls = avail === total ? "ok" : avail === 0 ? "no" : "mixed";
                return (
                  <div className={`wishdate ${isFinal ? "final" : ""}`} key={d.id}>
                    <div className="wishdate-top">
                      <div>
                        <div className="wishdate-date">
                          {isFinal && <Icon name="check" size={15} sw={2.6} />}
                          {d.date}
                        </div>
                        <div className="wishdate-slot">{d.slot}</div>
                      </div>
                      <div className={`wishdate-avail ${cls}`}>{avail}/{total} verfügbar</div>
                    </div>

                    <div className="vote-row">
                      {COURSE_TEAM.map(t => <VoteChip key={t.id} contactId={t.id} status={cr.dates[d.id][t.id] || "pending"} />)}
                    </div>

                    {!isClosed && !cr.finalDateId && (
                      <div className="wishdate-you">
                        <span className="lbl">Deine Antwort:</span>
                        <button className={`minibtn yes ${youVote === "confirmed" ? "on" : ""}`} onClick={() => setVote(d.id, "confirmed")}>
                          <Icon name="check" size={13} sw={2.4} /> Verfügbar
                        </button>
                        <button className={`minibtn no ${youVote === "declined" ? "on" : ""}`} onClick={() => setVote(d.id, "declined")}>
                          <Icon name="x" size={13} sw={2.4} /> Nicht
                        </button>
                        {allDone && avail > 0 && (
                          <button className="minibtn confirm" onClick={() => confirmDate(d.id)}>
                            <Icon name="check" size={13} sw={2.6} /> Diesen Termin bestätigen
                          </button>
                        )}
                      </div>
                    )}
                    {isFinal && <div className="wishdate-you"><span className="wishdate-final-tag"><Icon name="check" size={13} sw={2.6} /> Bestätigter Termin — an Firmengolf übergeben</span><button className="minibtn confirm" onClick={() => saveTermin(r, d)}><Icon name="calendar" size={13} /> Termin speichern</button></div>}
                  </div>
                );
              })}
            </div>

            {/* alternatives */}
            {cr.alts.map((a, i) => {
              const by = teamById(a.by);
              return (
                <div className="alt-card" key={i}>
                  <div className="alt-by"><span className={`va vote ${by.avatar}`} style={{ width: 22, height: 22, padding: 0, justifyContent: "center", border: 0 }}>{by.initials}</span> {by.name} schlägt einen Alternativtermin vor</div>
                  <div className="alt-date">{a.date} · {a.slot}</div>
                  <div className="alt-note">„{a.note}"</div>
                  <div className="alt-actions">
                    <button className="btn btn-brand btn-sm" onClick={() => window.fgToast("Alternativtermin an Firmengolf übergeben")}><Icon name="users" size={13} /> An Firmengolf übergeben</button>
                    <button className="btn btn-quiet btn-sm" onClick={() => window.fgToast("Alternative verworfen")}>Verwerfen</button>
                  </div>
                </div>
              );
            })}

            {/* participants */}
            <div className="req-section-label">Beteiligte Ansprechpartner</div>
            <div className="team-list">
              {COURSE_TEAM.map(t => {
                const responded = hasResponded(cr, t.id);
                return (
                  <div className="team-row" key={t.id}>
                    <span className={`av ${t.avatar}`}>{t.initials}</span>
                    <div>
                      <div className="team-name">{t.name}{t.you && <span className="you-tag">Du</span>}</div>
                      <div className="team-role">{t.role}</div>
                    </div>
                    <div className="team-right">
                      <span className={`spill ${responded ? "s-bestaetigt" : "s-angefragt"}`}>{responded ? "Hat reagiert" : "Ausstehend"}</span>
                      {!responded && !t.you && (
                        <button className="team-link" onClick={() => sendReminder(t.name)}><Icon name="bell" size={12} /> Erinnern</button>
                      )}
                      <a className="team-link" href={`Termin.html?req=${r.id}&as=${t.id}`} title="Antwort-Seite öffnen"><Icon name="external" size={12} /> Link</a>
                    </div>
                  </div>
                );
              })}
            </div>
          </>
        )}

        {/* verlauf */}
        <div className="req-section-label">Verlauf</div>
        <div className="req-timeline">
          {r.timeline.map((e, i) => (
            <div className="req-tl" key={i}>
              <span className="node"><Icon name="check" size={11} /></span>
              <div><div className="t">{e.t}</div><div className="w">{e.w}</div></div>
            </div>
          ))}
        </div>

        {/* primary actions */}
        <div className="req-actions">
          {cr.finalDateId && !cr.handedOff && (
            <button className="btn btn-brand" onClick={() => handOff(r)}><Icon name="users" size={14} /> An Firmengolf übergeben</button>
          )}
          {cr.handedOff && (
            <button className="btn btn-brand" onClick={() => window.fgToast("Angebot-Editor geöffnet")}><Icon name="euro" size={14} /> Angebot schreiben</button>
          )}
          {hasCoord && !cr.finalDateId && !isClosed && (
            <button className="btn btn-ghost" onClick={() => sendReminder("alle offenen Personen")}><Icon name="bell" size={14} /> Alle erinnern</button>
          )}
          <button className="btn btn-ghost" onClick={() => openMail(r)}><Icon name="inbox" size={14} /> Mail-Vorschau</button>
          {!isClosed && !cr.finalDateId && (
            <button className="btn btn-quiet" onClick={() => window.fgToast("Anfrage abgelehnt")}><Icon name="x" size={14} /> Ablehnen</button>
          )}
        </div>
      </div>
    </div>
  );
};

// ---------------- mail preview modal ----------------
const MailModal = ({ r, onClose }) => {
  if (!r) return null;
  return (
    <div className="mail-overlay" onClick={onClose}>
      <div className="mail-sheet" onClick={e => e.stopPropagation()}>
        <div className="mail-bar">
          <span className="t">Mail-Vorschau · an euer Team</span>
          <button className="mail-close" onClick={onClose}><Icon name="x" size={16} /></button>
        </div>
        <div className="mail-meta">
          <div className="row"><span className="k">An</span><span className="v">{COURSE_TEAM.map(t => t.name).join(", ")}</span></div>
          <div className="row"><span className="k">Betreff</span><span className="v">Neue Event-Anfrage: {r.company} — {r.eventType}</span></div>
        </div>
        <div className="mail-body">
          <img className="mail-logo" src="design-system/logo/firmengolf-wordmark.png" alt="Firmengolf" />
          <div className="mail-h">Eine neue Anfrage wartet auf eure Rückmeldung.</div>
          <p className="mail-p">Hallo zusammen,<br /><b>{r.company}</b> möchte bei euch ein Event ausrichten. Bitte gebt eure Verfügbarkeit für die Wunschtermine an — je schneller alle reagieren, desto schneller können wir der Firma ein Angebot machen.</p>
          <div className="mail-card">
            <div className="ev">{r.eventType} · {r.participants} Personen</div>
            <div className="det">
              {(r.wishDates || []).map(d => d.date).join("  ·  ") || r.date}
            </div>
          </div>
          <a className="mail-cta" href={`Termin.html?req=${r.id}&as=mp`} onClick={onClose}>Verfügbarkeit angeben <Icon name="chevronRight" size={16} /></a>
          <p className="mail-foot">Jede Person erhält ihren eigenen Link. Ihr seht gegenseitig, wer schon reagiert hat. Diese Mail kommt automatisch von Firmengolf — bei Fragen antwortet einfach direkt.</p>
        </div>
      </div>
    </div>
  );
};

// ---------------- app ----------------
const AnfragenApp = () => {
  const [filter, setFilter] = useStateReq("alle");
  const [selId, setSelId] = useStateReq(REQUESTS[0].id);
  const [coord, setCoord] = useStateReq(initCoord);
  const [mailReq, setMailReq] = useStateReq(null);

  const counts = REQ_FILTERS.reduce((m, f) => {
    m[f.id] = f.id === "alle" ? REQUESTS.length : REQUESTS.filter(r => r.status === f.id).length;
    return m;
  }, {});
  const list = REQUESTS.filter(r => filter === "alle" || r.status === filter);
  const sel = REQUESTS.find(r => r.id === selId);
  const cr = coord[selId];

  const openCount = REQUESTS.filter(r => r.status === "neu" || r.status === "bearbeitung").length;
  const needAttention = REQUESTS.filter(r => {
    const c = coord[r.id];
    return (r.status === "neu" || r.status === "bearbeitung") && !c.finalDateId;
  }).length;

  const setVote = (dateId, status) => {
    setCoord(prev => {
      const next = { ...prev, [selId]: { ...prev[selId], dates: { ...prev[selId].dates } } };
      next[selId].dates[dateId] = { ...next[selId].dates[dateId], [YOU.id]: status };
      return next;
    });
    window.fgToast(status === "confirmed" ? "Als verfügbar markiert" : "Als nicht verfügbar markiert");
  };
  const confirmDate = (dateId) => {
    setCoord(prev => ({ ...prev, [selId]: { ...prev[selId], finalDateId: dateId } }));
    window.fgToast("Termin bestätigt — bereit zur Übergabe an Firmengolf");
  };
  const handOff = (r) => {
    setCoord(prev => ({ ...prev, [selId]: { ...prev[selId], handedOff: true } }));
    window.fgToast("An Firmengolf übergeben — Angebot kann erstellt werden");
  };
  const sendReminder = (who) => window.fgToast("Erinnerung an " + who + " gesendet");

  return (
    <div>
      <TopNav activeTab="anfragen" />
      <div className="page-wide">
        <div className="page-head">
          <div className="eyebrow">Posteingang</div>
          <h1>Deine <em>Anfragen</em></h1>
          <p>Stimmt Wunschtermine im Team ab, schlagt Alternativen vor — und sobald alle reagiert haben, übernimmt Firmengolf Angebot und Buchung.</p>
        </div>

        <div className="mini-stats">
          <div className="mini-stat"><div className="v">{needAttention}</div><div className="l">Brauchen eure Abstimmung</div></div>
          <div className="mini-stat"><div className="v">94 %</div><div className="l">Antwortrate (letzte 30 Tage)</div></div>
          <div className="mini-stat"><div className="v">3,2 Std.</div><div className="l">Ø Reaktionszeit im Team</div></div>
        </div>

        <div className="seg">
          {REQ_FILTERS.map(f => (
            <button key={f.id} className={`seg-btn ${filter === f.id ? "on" : ""}`} onClick={() => setFilter(f.id)}>
              {f.label}<span className="count">{counts[f.id]}</span>
            </button>
          ))}
        </div>

        <div className="req-layout">
          <div className="req-list">
            {list.length === 0 && (
              <div className="panel" style={{ textAlign: "center", color: "var(--ink-500)" }}>Keine Anfragen in dieser Ansicht.</div>
            )}
            {list.map(r => (
              <ReqItem key={r.id} r={r} cr={coord[r.id]} on={sel && sel.id === r.id} onClick={() => setSelId(r.id)} />
            ))}
          </div>
          <ReqDetail
            r={sel} cr={cr}
            setVote={setVote} confirmDate={confirmDate} handOff={handOff}
            openMail={setMailReq} sendReminder={sendReminder}
          />
        </div>

        <Footer />
      </div>
      {mailReq && <MailModal r={mailReq} onClose={() => setMailReq(null)} />}
    </div>
  );
};

const reqRoot = ReactDOM.createRoot(document.getElementById("app"));
reqRoot.render(<AnfragenApp />);
