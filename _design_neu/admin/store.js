/* eslint-disable */
// =============================================================
// Admin store — seeds from window.SITE_DATA, adds mock operational
// data (partners, events, requests w/ multi-party coordination, users),
// persists to localStorage. Vanilla; exposes window.ADMIN.
// =============================================================
window.ADMIN = (function () {
  const KEY = 'firmengolf-admin-v3';
  const listeners = new Set();
  const D = window.SITE_DATA || {};

  // ---- partners (golf courses) ----
  const seedPartners = () => ([
    { id: 'p1', name: 'Golfclub Hamburg-Wendlohe', city: 'Hamburg', region: 'Nord', holes: 27,
      contact: 'Markus Kühn', role: 'Clubmanager', email: 'kontakt@gc-wendlohe.de', phone: '+49 40 55 93 0',
      address: 'Tangstedter Landstr. 360, 22417 Hamburg', status: 'aktiv', rating: 4.8, joined: '2024-03-12' },
    { id: 'p2', name: 'GC Köln-Hahnwald', city: 'Köln', region: 'West', holes: 18,
      contact: 'Petra Scommer', role: 'Eventleitung', email: 'info@gc-hahnwald.de', phone: '+49 221 35 12 0',
      address: 'Golfplatz 1, 50996 Köln', status: 'aktiv', rating: 4.7, joined: '2024-05-02' },
    { id: 'p3', name: 'Golfpark München-Riem', city: 'München', region: 'Süd', holes: 18,
      contact: 'Andreas Bauer', role: 'Inhaber', email: 'event@gp-riem.de', phone: '+49 89 99 28 0',
      address: 'Grasweg 4, 81829 München', status: 'aktiv', rating: 4.6, joined: '2024-08-19' },
    { id: 'p4', name: 'GC Berlin-Wannsee', city: 'Berlin', region: 'Ost', holes: 18,
      contact: 'Claudia Reh', role: 'Sekretariat', email: 'office@gc-wannsee.de', phone: '+49 30 80 60 0',
      address: 'Golfweg 22, 14109 Berlin', status: 'pausiert', rating: 4.5, joined: '2024-09-30' },
    { id: 'p5', name: 'Schloss Lüdersburg', city: 'Lüneburg', region: 'Nord', holes: 36,
      contact: 'Dr. Felix Mohr', role: 'Resort-Direktor', email: 'events@luedersburg.de', phone: '+49 4139 69 70',
      address: 'Lüdersburger Str. 21, 21379 Lüdersburg', status: 'aktiv', rating: 4.9, joined: '2024-02-14' },
    { id: 'p6', name: 'Golfanlage Frankfurt-Niederrad', city: 'Frankfurt', region: 'West', holes: 18,
      contact: 'Sabine Wirth', role: 'Clubmanagerin', email: 'kontakt@golf-niederrad.de', phone: '+49 69 66 62 0',
      address: 'Schwarzwaldstr. 100, 60528 Frankfurt', status: 'in-pruefung', rating: 0, joined: '2026-05-29' },
    { id: 'p7', name: 'GC Stuttgart-Solitude', city: 'Stuttgart', region: 'Süd', holes: 18,
      contact: 'Thomas Vogel', role: 'Head-Pro', email: 'info@gc-solitude.de', phone: '+49 711 69 51 0',
      address: 'Solitude 1, 71229 Leonberg', status: 'aktiv', rating: 4.7, joined: '2024-06-21' },
    { id: 'p8', name: 'GC München West', city: 'München', region: 'Süd', holes: 18,
      contact: 'Sabine Roth', role: 'Inhaberin', email: 'event@gc-muenchen-west.de', phone: '+49 8123 93 0',
      address: 'Münchner Str. 57, 85452 Eichenried', status: 'aktiv', rating: 4.8, joined: '2024-04-08' },
    { id: 'p9', name: 'GC Köln-Refrath', city: 'Köln', region: 'West', holes: 18,
      contact: 'Jörg Hansen', role: 'Clubmanager', email: 'info@gc-refrath.de', phone: '+49 2204 63 14',
      address: 'Steinacher Weg 1, 51427 Bergisch Gladbach', status: 'in-pruefung', rating: 0, joined: '2026-05-22' },
  ]);

  // venue (from SITE_DATA) -> partner
  const VENUE_PARTNER = {
    'Hamburg-Wendlohe': 'p1', 'Köln-Hahnwald': 'p2', 'München-Riem': 'p3',
    'Berlin-Wannsee': 'p4', 'Schloss Lüdersburg': 'p5', 'Frankfurt-Niederrad': 'p6',
    'Stuttgart-Solitude': 'p7', 'München-Eichenried': 'p8', 'GC München West': 'p8', 'Köln-Refrath': 'p9',
  };
  // events Firmengolf plans itself on request (not a fixed partner offer)
  const OWN_EVENTS = new Set(['e5', 'e8', 'e10']);
  // per-event traffic (views / bookings) + status
  const EV_STATS = {
    e1: { views: 1240, bookings: 18, status: 'aktiv' },
    e2: { views: 980,  bookings: 7,  status: 'aktiv' },
    e3: { views: 760,  bookings: 12, status: 'aktiv' },
    e4: { views: 540,  bookings: 5,  status: 'aktiv' },
    e5: { views: 410,  bookings: 3,  status: 'auf-anfrage' },
    e6: { views: 1520, bookings: 31, status: 'aktiv' },
    e7: { views: 330,  bookings: 4,  status: 'entwurf' },
    e8: { views: 290,  bookings: 2,  status: 'auf-anfrage' },
    e9: { views: 670,  bookings: 9,  status: 'aktiv' },
    e10:{ views: 1180, bookings: 6,  status: 'auf-anfrage' },
    e11:{ views: 880,  bookings: 14, status: 'aktiv' },
    e12:{ views: 450,  bookings: 6,  status: 'aktiv' },
    e13:{ views: 412,  bookings: 9,  status: 'aktiv' },
    e14:{ views: 1240, bookings: 14, status: 'aktiv' },
    e15:{ views: 689,  bookings: 7,  status: 'aktiv' },
    e16:{ views: 234,  bookings: 3,  status: 'aktiv' },
    e17:{ views: 156,  bookings: 12, status: 'aktiv' },
  };

  // ---- users (WordPress-like roles) ----
  const seedUsers = () => ([
    { id: 'u1', name: 'Julius Klinzer', email: 'julius@firmengolf.de', role: 'superadmin', org: 'firmengolf', status: 'aktiv', lastActive: 'gerade eben' },
    { id: 'u2', name: 'Lena Hoffmann',  email: 'lena@firmengolf.de',   role: 'admin',      org: 'firmengolf', status: 'aktiv', lastActive: 'vor 2 Std.' },
    { id: 'u3', name: 'Jonas Bredow',   email: 'jonas@firmengolf.de',  role: 'redakteur',  org: 'firmengolf', status: 'aktiv', lastActive: 'gestern' },
    { id: 'u4', name: 'Markus Kühn',    email: 'kontakt@gc-wendlohe.de', role: 'platzmanager', org: 'p1', status: 'aktiv', lastActive: 'vor 3 Tagen' },
    { id: 'u5', name: 'Petra Scommer',  email: 'info@gc-hahnwald.de',  role: 'platzmanager', org: 'p2', status: 'aktiv', lastActive: 'vor 1 Tag' },
    { id: 'u6', name: 'Andreas Bauer',  email: 'event@gp-riem.de',     role: 'platzmanager', org: 'p3', status: 'eingeladen', lastActive: '—' },
    { id: 'u7', name: 'Claudia Reh',    email: 'office@gc-wannsee.de', role: 'platzmanager', org: 'p4', status: 'deaktiviert', lastActive: 'vor 3 Wochen' },
  ]);

  // ---- requests with multi-party scheduling coordination ----
  // status: neu | in-pruefung | angebot | gewonnen | verloren
  const seedRequests = () => ([
    {
      id: 'FG-26-001', kind: 'Event-Anfrage', event: 'Das große Firmenturnier', eventType: 'Firmenturnier',
      company: 'Quartz Labs GmbH', contact: 'Lena Hoffmann', email: 'lena@quartzlabs.de', phone: '+49 40 22 33 44',
      city: 'Hamburg', group: 24, daypart: 'Ganztägig', budget: '€20.000 – €50.000', status: 'neu',
      created: '2026-05-28', value: 7680, services: ['Catering', 'Fotograf', 'Pokale & Preise'],
      note: 'Jubiläum, gerne mit Branding.', partnerId: 'p2', deadline: '30. Mai 2026', overdue: true, takenOver: false,
      wishDates: [
        { id: 'd1', date: 'Do, 18. Juni 2026', slot: 'Ganztägig', course: { resp: 'confirmed', votes: { pro: 'confirmed', office: 'confirmed', owner: 'pending' } } },
        { id: 'd2', date: 'Fr, 19. Juni 2026', slot: 'Ganztägig', course: { resp: 'pending',   votes: { pro: 'pending', office: 'pending', owner: 'pending' } } },
      ],
      parties: [
        { kind: 'firma',      name: 'Quartz Labs GmbH', who: 'Lena Hoffmann', status: 'proposed', when: 'vor 4 Tagen' },
        { kind: 'platz',      name: 'GC Köln-Hahnwald', who: 'Petra Scommer', status: 'partial', done: 2, total: 3, when: 'vor 2 Tagen', overdue: true },
        { kind: 'firmengolf', name: 'Firmengolf',       who: 'Du', status: 'waiting' },
      ],
    },
    {
      id: 'FG-26-002', kind: 'Individuelles Event', event: 'Sommerfest unter Flutlicht', eventType: 'Sommerfest',
      company: 'Werkstatt 4', contact: 'Sandra Klein', email: 's.klein@werkstatt4.de', phone: '+49 221 55 66 77',
      city: 'Köln', group: 80, daypart: 'After Work', budget: 'Über €50.000', status: 'in-pruefung',
      created: '2026-05-27', value: 24000, services: ['DJ', 'Food Trucks', 'Flutlicht / Nacht-Event'],
      note: 'Nacht-Event-Interesse.', partnerId: null, ownPlanned: true, deadline: '2. Juni 2026', overdue: false, takenOver: false,
      wishDates: [
        { id: 'd1', date: 'Sa, 11. Juli 2026', slot: 'After Work', course: { resp: 'na', votes: {} } },
      ],
      parties: [
        { kind: 'firma',      name: 'Werkstatt 4', who: 'Sandra Klein', status: 'proposed', when: 'vor 5 Tagen' },
        { kind: 'firmengolf', name: 'Firmengolf', who: 'Du (Planung)', status: 'active', when: 'vor 3 Tagen' },
      ],
    },
    {
      id: 'FG-26-003', kind: 'Event-Anfrage', event: 'Bewegung statt PowerPoint', eventType: 'Gesundheitstag',
      company: 'Hartmann GmbH', contact: 'Matthias Reuter', email: 'm.reuter@hartmann.de', phone: '+49 69 11 22 33',
      city: 'Frankfurt', group: 30, daypart: 'Ganztägig', budget: '€5.000 – €10.000', status: 'angebot',
      created: '2026-05-25', value: 4350, services: ['Healthy Lunch', 'Physio'],
      note: 'BGM-Abrechnung nötig.', partnerId: 'p6', deadline: '28. Mai 2026', overdue: false, takenOver: false,
      wishDates: [
        { id: 'd1', date: 'Mi, 24. Juni 2026', slot: 'Ganztägig', course: { resp: 'confirmed', votes: { pro: 'confirmed', office: 'confirmed', owner: 'confirmed' } }, final: true },
      ],
      parties: [
        { kind: 'firma',      name: 'Hartmann GmbH', who: 'Matthias Reuter', status: 'proposed', when: 'vor 6 Tagen' },
        { kind: 'platz',      name: 'Golfanlage Frankfurt-Niederrad', who: 'Sabine Wirth', status: 'done', done: 3, total: 3, when: 'vor 4 Tagen' },
        { kind: 'firmengolf', name: 'Firmengolf', who: 'Du', status: 'active', when: 'vor 3 Tagen' },
      ],
    },
    {
      id: 'FG-26-004', kind: 'Event-Anfrage', event: 'Networking-Runde am Wannsee', eventType: 'Networking',
      company: 'North Studio', contact: 'Julia Brandt', email: 'jb@northstudio.io', phone: '+49 30 99 88 77',
      city: 'Berlin', group: 16, daypart: 'Mittag, halber Tag', budget: '€5.000 – €10.000', status: 'gewonnen',
      created: '2026-05-20', value: 3120, services: ['Aperitif'],
      note: '', partnerId: 'p4', deadline: '22. Mai 2026', overdue: false, takenOver: false,
      wishDates: [
        { id: 'd1', date: 'Do, 2. Juli 2026', slot: 'Mittag', course: { resp: 'confirmed', votes: { pro: 'confirmed', office: 'confirmed', owner: 'confirmed' } }, final: true },
      ],
      parties: [
        { kind: 'firma',      name: 'North Studio', who: 'Julia Brandt', status: 'proposed', when: 'vor 12 Tagen' },
        { kind: 'platz',      name: 'GC Berlin-Wannsee', who: 'Claudia Reh', status: 'done', done: 3, total: 3, when: 'vor 10 Tagen' },
        { kind: 'firmengolf', name: 'Firmengolf', who: 'Du', status: 'done', when: 'vor 9 Tagen' },
      ],
    },
    {
      id: 'FG-26-005', kind: 'Individuelles Event', event: 'Strategie-Offsite', eventType: 'Offsite',
      company: 'Steinblick AG', contact: 'Tobias Vogt', email: 't.vogt@steinblick.de', phone: '+49 711 44 55 66',
      city: 'Stuttgart', group: 18, daypart: 'Ganztägig', budget: '€20.000 – €50.000', status: 'neu',
      created: '2026-05-29', value: 9720, services: ['Meetingraum', 'Übernachtung', 'Catering'],
      note: '2 Tage, 1 Nacht.', partnerId: 'p7', deadline: '3. Juni 2026', overdue: false, takenOver: false,
      wishDates: [
        { id: 'd1', date: 'KW 30 (20.–24. Juli)', slot: 'Ganztägig', course: { resp: 'pending', votes: { pro: 'pending', office: 'pending', owner: 'pending' } } },
        { id: 'd2', date: 'KW 32 (3.–7. Aug.)',   slot: 'Ganztägig', course: { resp: 'pending', votes: { pro: 'pending', office: 'pending', owner: 'pending' } } },
      ],
      parties: [
        { kind: 'firma',      name: 'Steinblick AG', who: 'Tobias Vogt', status: 'proposed', when: 'vor 3 Tagen' },
        { kind: 'platz',      name: 'GC Stuttgart-Solitude', who: 'Thomas Vogel', status: 'pending', done: 0, total: 3, when: '—' },
        { kind: 'firmengolf', name: 'Firmengolf', who: 'Du', status: 'waiting' },
      ],
    },
    {
      id: 'FG-26-006', kind: 'Event-Anfrage', event: 'First Swings — nach Feierabend', eventType: 'After-Work Golf',
      company: 'Pixelhof', contact: 'Nora Lang', email: 'nora@pixelhof.de', phone: '',
      city: 'Berlin', group: 10, daypart: 'After Work', budget: 'Unter €5.000', status: 'verloren',
      created: '2026-05-18', value: 790, services: [],
      note: 'Budget zu knapp.', partnerId: 'p4', deadline: '20. Mai 2026', overdue: false, takenOver: false,
      wishDates: [
        { id: 'd1', date: 'Di, 16. Juni 2026', slot: 'After Work', course: { resp: 'declined', votes: { pro: 'declined', office: 'declined', owner: 'declined' } } },
      ],
      parties: [
        { kind: 'firma',      name: 'Pixelhof', who: 'Nora Lang', status: 'proposed', when: 'vor 14 Tagen' },
        { kind: 'platz',      name: 'GC Berlin-Wannsee', who: 'Claudia Reh', status: 'declined', when: 'vor 13 Tagen' },
        { kind: 'firmengolf', name: 'Firmengolf', who: 'Du', status: 'closed' },
      ],
    },
  ]);

  function fresh() {
    return {
      profile: { name: 'Julius Klinzer', role: 'Super-Admin', email: 'julius@firmengolf.de', initials: 'JK', avatar: null },
      events: (D.events || []).map(e => {
        const st = EV_STATS[e.id] || { views: 0, bookings: 0, status: 'aktiv' };
        const own = OWN_EVENTS.has(e.id);
        return {
          id: e.id, slug: e.slug, title: e.title, format: e.format, formatLabel: e.formatLabel,
          venue: e.venue, region: e.region, duration: e.duration,
          groupMin: e.groupMin, groupMax: e.groupMax,
          pricePerPerson: e.pricePerPerson || null, pricePerGroup: e.pricePerGroup || null,
          rating: e.rating, reviews: e.reviews, heroImage: e.heroImage,
          owner: own ? 'firmengolf' : 'partner',
          partnerId: own ? null : (VENUE_PARTNER[e.venue] || null),
          views: st.views, bookings: st.bookings, status: st.status,
        };
      }),
      partners: seedPartners(),
      users: seedUsers(),
      requests: seedRequests(),
      posts: (D.posts || []).map(p => ({ slug: p.slug, title: p.title, tag: p.tag, author: p.author, date: p.date, published: true, featured: !!p.featured })),
    };
  }

  let state = load();

  function load() {
    try {
      const raw = localStorage.getItem(KEY);
      if (raw) {
        const s = JSON.parse(raw);
        if (!s.profile) s.profile = fresh().profile;
        if (!s.users) s.users = seedUsers();
        return s;
      }
    } catch {}
    return fresh();
  }
  function persist() { try { localStorage.setItem(KEY, JSON.stringify(state)); } catch {} ; listeners.forEach(fn => fn(state)); }

  return {
    get: () => state,
    subscribe: (fn) => { listeners.add(fn); return () => listeners.delete(fn); },
    update: (coll, id, patch) => {
      const keyField = coll === 'posts' ? 'slug' : 'id';
      state = { ...state, [coll]: state[coll].map(x => x[keyField] === id ? { ...x, ...patch } : x) };
      persist();
    },
    add: (coll, item) => { state = { ...state, [coll]: [item, ...state[coll]] }; persist(); },
    remove: (coll, id) => { state = { ...state, [coll]: state[coll].filter(x => x.id !== id) }; persist(); },
    reset: () => { state = fresh(); persist(); },
    setProfile: (patch) => { state = { ...state, profile: { ...state.profile, ...patch } }; persist(); },
    partnerName: (id) => (state.partners.find(p => p.id === id) || {}).name || '—',
    formats: D.formats || [],
    onRequestFormats: D.onRequestFormats || [],
  };
})();
