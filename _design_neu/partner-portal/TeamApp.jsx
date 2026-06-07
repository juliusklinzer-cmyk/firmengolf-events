/* eslint-disable */
/* GC München West — Ansprechpartner verwalten (Team.html) */

const { useState: useStateTeam } = React;

const ROLE_OPTIONS = [
  "Clubmanager", "Geschäftsführer", "Vorstand", "Präsident", "Schatzmeister",
  "Sekretariat", "Rezeption", "Mitgliederverwaltung", "Buchhaltung",
  "Head Pro", "Golfprofessional", "Golflehrer", "Golfschule",
  "Sportwart", "Spielleitung", "Turnierleitung", "Marshal", "Starter",
  "Head Greenkeeper", "Greenkeeper", "Course Manager",
  "Gastronomiebetreiber", "Restaurantleitung", "Eventmanager",
  "Pro Shop Mitarbeiter", "Caddiemaster", "Cart Verantwortlicher",
  "Jugendwart", "Captain", "Mannschaftsführer", "Sonstige",
];

const TeamApp = () => {
  const seed = (window.COURSE_TEAM || []).map(t => ({
    id: t.id, name: t.name, email: t.email || "", role: t.role || "Sonstige", owner: !!t.you,
  }));
  const [people, setPeople] = useStateTeam(seed);
  const [editing, setEditing] = useStateTeam(null); // id or 'new'
  const [draft, setDraft] = useStateTeam({ name: "", email: "", role: "Golf-Pro" });

  const startNew = () => { setDraft({ name: "", email: "", role: "Golf-Pro" }); setEditing("new"); };
  const startEdit = (p) => { setDraft({ name: p.name, email: p.email, role: p.role }); setEditing(p.id); };
  const cancel = () => setEditing(null);
  const valid = draft.name.trim() && /.+@.+\..+/.test(draft.email);

  const save = () => {
    if (!valid) return;
    if (editing === "new") {
      setPeople(ps => [...ps, { id: "p" + Date.now(), ...draft, owner: false }]);
      window.fgToast(draft.name + " hinzugefügt");
    } else {
      setPeople(ps => ps.map(p => p.id === editing ? { ...p, ...draft } : p));
      window.fgToast("Änderungen gespeichert");
    }
    setEditing(null);
  };
  const remove = (p) => {
    setPeople(ps => ps.filter(x => x.id !== p.id));
    window.fgToast(p.name + " entfernt");
  };

  const initials = (name) => name.split(" ").map(w => w[0]).slice(0, 2).join("").toUpperCase();

  return (
    <div>
      <TopNav activeTab="team" />
      <div className="page-wide">
        <div className="page-head page-head-row">
          <div>
            <div className="eyebrow">Konto</div>
            <h1>Deine <em>Ansprechpartner</em></h1>
            <p>Diese Personen kannst du bei der Termin-Freigabe für ein Angebot einbinden. Sie werden bei Anfragen über die gewünschten Termine benachrichtigt.</p>
          </div>
          <button className="btn btn-brand" onClick={startNew}><Icon name="plus" size={15} sw={2} /> Person hinzufügen</button>
        </div>

        <div className="team-mgmt">
          {people.map(p => (
            <div className="tm-row" key={p.id}>
              <span className="tm-av">{initials(p.name)}</span>
              <div className="tm-main">
                <div className="tm-name">{p.name}{p.owner && <span className="tm-owner">Kontoinhaber</span>}</div>
                <div className="tm-sub">{p.role} · {p.email || "keine E-Mail"}</div>
              </div>
              <div className="tm-actions">
                <button className="btn btn-ghost btn-sm" onClick={() => startEdit(p)}><Icon name="edit" size={14} /> Bearbeiten</button>
                {!p.owner && (
                  <button className="btn btn-quiet btn-sm" onClick={() => remove(p)}><Icon name="x" size={14} /> Entfernen</button>
                )}
              </div>
            </div>
          ))}
          {people.length === 0 && <div className="panel" style={{ textAlign: "center", color: "var(--ink-500)" }}>Noch keine Ansprechpartner. Füge die erste Person hinzu.</div>}
        </div>

        <p className="tm-legal">Personenbezogene Daten werden ausschließlich zur Bearbeitung von Firmenanfragen verwendet und nicht an Dritte weitergegeben (Art. 6 Abs. 1 lit. b/f DSGVO).</p>

        <Footer />
      </div>

      {editing && (
        <div className="ad-modal-scrim rel-overlay" onClick={cancel}>
          <div className="rel-sheet" style={{ maxWidth: 440 }} onClick={e => e.stopPropagation()}>
            <div className="rel-bar">
              <span className="t">{editing === "new" ? "Person hinzufügen" : "Person bearbeiten"}</span>
              <button className="mail-close" onClick={cancel}><Icon name="x" size={16} /></button>
            </div>
            <div className="rel-body">
              <label className="tm-field"><span>Name</span>
                <input className="rel-input" value={draft.name} onChange={e => setDraft(d => ({ ...d, name: e.target.value }))} placeholder="Vor- und Nachname" autoFocus /></label>
              <label className="tm-field"><span>E-Mail</span>
                <input className="rel-input" type="email" value={draft.email} onChange={e => setDraft(d => ({ ...d, email: e.target.value }))} placeholder="name@golfclub.de" /></label>
              <label className="tm-field"><span>Rolle</span>
                <select className="rel-input" value={draft.role} onChange={e => setDraft(d => ({ ...d, role: e.target.value }))}>
                  {ROLE_OPTIONS.map(r => <option key={r} value={r}>{r}</option>)}
                </select></label>
            </div>
            <div className="rel-foot">
              <button className="btn btn-ghost" onClick={cancel}>Abbrechen</button>
              <button className="btn btn-brand" disabled={!valid} onClick={save}><Icon name="check" size={14} sw={2.5} /> Speichern</button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

const teamRoot = ReactDOM.createRoot(document.getElementById("app"));
teamRoot.render(<TeamApp />);
