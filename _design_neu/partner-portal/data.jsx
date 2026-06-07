/* eslint-disable */
/* Mock data for GC München West partner dashboard. */

const COURSE = {
  name: "GC München West",
  nameItalic: "West",
  monogram: "MW",
  loc: "Eichenried bei München",
  founded: 1991,
  holes: 18,
  par: 72,
  length: "6.184 m",
  greenfeeFrom: 95,
  memberSince: "März 2024",
  contact: "Sabine Roth",
  contactRole: "Inhaberin & Eventmanagerin",
  about: [
    "Eingebettet zwischen alten Eichen und den Auen der Isar liegt unser 18-Loch-Platz, gerade einmal 25 Minuten vom Münchner Stadtkern entfernt. Wer hier ankommt, lässt das Büro nicht nur räumlich hinter sich — er findet auch wieder zu sich.",
    "Wir empfangen Firmenteams das ganze Jahr über: von der ersten Schwung-Erfahrung im Schnupperkurs bis zum klassischen 18-Loch-Firmen-Cup. Unser Clubhaus mit Terrasse fasst bis zu 80 Gäste, gekocht wird offen, regional und mit Liebe.",
  ],
  facts: [
    { lbl: "Golfplatz",         val: "18-Loch-Platz" },
    { lbl: "Driving Range",     val: "32 Abschläge" },
    { lbl: "Restaurant",        val: "80 Plätze · Terrasse" },
    { lbl: "Parken",            val: "Kostenlos, 120 Plätze" },
    { lbl: "Anreise",           val: "S2 Markt Schwaben · 25 Min" },
  ],
};

const CATEGORIES = [
  {
    id: "schnupper",
    cat: "Schnupperkurs",
    icon: "sparkles",
    status: "published",
    title: "Erster Schwung — Schnupperkurs für Teams",
    sub: "3 Stunden auf der Range mit unseren Pros — Schläger, Bälle und gute Laune inklusive.",
    img: "design-system/imagery/hero-fairway-wide.jpg",
    price: 89,
    unit: "p.P.",
    duration: "3 Std.",
    group: "6–24",
    views: 412,
    bookings: 9,
  },
  {
    id: "turnier",
    cat: "Firmenturnier",
    icon: "trophy",
    status: "published",
    title: "18-Loch Firmen-Cup",
    sub: "Klassisches Stableford-Turnier mit Startergeschenk, Halfway-Verpflegung und Siegerehrung.",
    img: "design-system/imagery/hero-meadow.jpg",
    price: 145,
    unit: "p.P.",
    duration: "Ganztags",
    group: "24–80",
    views: 1240,
    bookings: 14,
  },
  {
    id: "team",
    cat: "Teamevent",
    icon: "users",
    status: "in-pruefung",
    title: "Team auf dem Platz",
    sub: "Halbtägig: Coaching, Mini-Wettkampf und gemeinsames Lunch auf der Terrasse.",
    img: "design-system/imagery/event-team.jpg",
    price: 119,
    unit: "p.P.",
    duration: "5 Std.",
    group: "8–40",
    views: 689,
    bookings: 7,
  },
  {
    id: "afterwork",
    cat: "After-Work Golf",
    icon: "moon",
    status: "draft",
    title: "Feierabend 9-Loch",
    sub: "Kurze Runde nach Büroschluss — Donnerstags ab 17 Uhr, mit Snack & einem Hugo zum Ausklang.",
    img: "design-system/imagery/venue-meadow.jpg",
    price: 65,
    unit: "p.P.",
    duration: "2,5 Std.",
    group: "4–16",
    views: 0,
    bookings: 0,
  },
  {
    id: "offsite",
    cat: "Offsite / Meeting + Golf",
    icon: "briefcase",
    status: "paused",
    title: "Tagung & Tee",
    sub: "Tagungsraum bis 30 Personen am Vormittag, anschließend Schnupperrunde und gemeinsames Abendessen.",
    img: "design-system/imagery/hero-fairway-wide.jpg",
    price: 189,
    unit: "p.P.",
    duration: "Ganztags",
    group: "12–30",
    views: 234,
    bookings: 3,
  },
  {
    id: "hospitality",
    cat: "Kundenevent / Hospitality",
    icon: "sparkles",
    status: "empty",
  },
  {
    id: "coaching",
    cat: "Coaching",
    icon: "target",
    status: "published",
    title: "Pro-Stunde mit Markus",
    sub: "Einzel- oder 2er-Coaching auf der Range. Videoanalyse auf Wunsch.",
    img: "design-system/imagery/event-team.jpg",
    price: 75,
    unit: "p.P.",
    duration: "60 Min.",
    group: "1–2",
    views: 156,
    bookings: 12,
  },
];

const STATS = [
  { id: "views",    lbl: "Profilaufrufe",        val: "3.412",    unit: "",       delta: 18,  deltaSign: "up",   note: "vs. letzter Monat" },
  { id: "requests", lbl: "Anfragen / Monat",     val: "27",       unit: "",       delta: 9,   deltaSign: "up",   note: "5 neu seit gestern" },
  { id: "bookings", lbl: "Buchungen 2026",       val: "45",       unit: "",       delta: 31,  deltaSign: "up",   note: "Ziel: 60" },
  { id: "revenue",  lbl: "Umsatz 2026",          val: "48.6",     unit: "T€",     delta: 4,   deltaSign: "down", note: "vs. Vorjahres-Lauf" },
];

const INBOX = [
  {
    id: 1,
    company: "Allianz Tech Hub",
    initials: "AT",
    avatar: "sand",
    isNew: true,
    eventType: "Teamevent",
    msg: "30 Personen, Mittwoch 12. Juni — wäre der Nachmittag noch frei?",
    when: "vor 12 Min.",
    status: "Neu",
    statusColor: "green",
  },
  {
    id: 2,
    company: "Voltari GmbH",
    initials: "VG",
    avatar: "green",
    isNew: true,
    eventType: "Firmenturnier",
    msg: "Anfrage für unseren Sales Kick-Off im Juli — 64 Teilnehmer.",
    when: "vor 1 Std.",
    status: "Neu",
    statusColor: "green",
  },
  {
    id: 3,
    company: "Kerber & Söhne",
    initials: "KS",
    avatar: "clay",
    isNew: false,
    eventType: "Schnupperkurs",
    msg: "Termin vom 4. Juni bestätigt — Rechnung schon raus?",
    when: "gestern",
    status: "In Bearbeitung",
    statusColor: "default",
  },
  {
    id: 4,
    company: "Bavaria Robotics",
    initials: "BR",
    avatar: "default",
    isNew: false,
    eventType: "Coaching",
    msg: "Können wir noch eine 2. Trainerstunde dazu buchen?",
    when: "vor 2 Tagen",
    status: "Bestätigt",
    statusColor: "default",
  },
];

const REVIEWS = [
  {
    id: 1,
    company: "SAP Innovation Lab",
    stars: 5,
    quote: "Ein wirklich gelungener Tag draußen. Sabine und ihr Team haben sich um alles gekümmert — wir konnten einfach Team sein.",
    author: "Maria H., People Ops",
    date: "Mai 2026",
    event: "Teamevent",
  },
  {
    id: 2,
    company: "Voltari GmbH",
    stars: 5,
    quote: "Vom ersten Anruf bis zur Siegerehrung lief alles entspannt. Unsere Sales-Truppe redet noch heute davon.",
    author: "Lukas R., VP Sales",
    date: "April 2026",
    event: "Firmenturnier",
  },
];

// The golf course's own contacts. When a request comes in, each gets an email
// with a link to respond. The "you" flag marks whoever is logged into the portal.
const COURSE_TEAM = [
  { id: "sr", name: "Sabine Roth",      role: "Inhaberin",   initials: "SR", avatar: "green", you: true, email: "sabine.roth@gc-muenchen-west.de" },
  { id: "mp", name: "Markus Pfeiffer",  role: "Golf-Pro",    initials: "MP", avatar: "clay", email: "pro@gc-muenchen-west.de" },
  { id: "os", name: "Team Sekretariat", role: "Sekretariat", initials: "OS", avatar: "sand", email: "office@gc-muenchen-west.de" },
];

// Rich request objects for the Anfragen page (master/detail).
const REQUESTS = [
  {
    id: 1, company: "Allianz Tech Hub", initials: "AT", avatar: "sand",
    contact: "Nadine Brandt", role: "People & Culture",
    email: "n.brandt@allianz-techhub.de", phone: "+49 89 1234 567",
    eventType: "Teamevent", offerId: "team",
    date: "Mi, 12. Juni 2026", slot: "Nachmittag", participants: 30,
    budget: "3.000 – 4.000 €", status: "neu", when: "vor 12 Min.",
    msg: "30 Personen, Mittwoch 12. Juni — wäre der Nachmittag noch frei? Wir suchen etwas, das auch für komplette Anfänger funktioniert. Etwa die Hälfte des Teams hat noch nie einen Schläger gehalten.",
    dual: true,
    wishDates: [
      { id: "d1", date: "Mi, 12. Juni 2026", slot: "Nachmittag", responses: { sr: "confirmed", mp: "confirmed", os: "pending" } },
      { id: "d2", date: "Do, 13. Juni 2026", slot: "Ganztags",   responses: { sr: "confirmed", mp: "declined",  os: "pending" } },
      { id: "d3", date: "Mi, 19. Juni 2026", slot: "Nachmittag", responses: { sr: "declined",  mp: "confirmed", os: "pending" } },
    ],
    alternatives: [
      { by: "mp", date: "Fr, 21. Juni 2026", slot: "Ganztags", note: "An den 13. komme ich nicht weg — der 21. ginge bei mir ganztägig." },
    ],
    finalDateId: null,
    timeline: [
      { t: "Anfrage eingegangen", w: "vor 12 Min." },
      { t: "Team benachrichtigt (3 Personen)", w: "vor 12 Min." },
      { t: "Sabine & Markus haben reagiert", w: "vor 5 Min." },
    ],
  },
  {
    id: 2, company: "Voltari GmbH", initials: "VG", avatar: "green",
    contact: "Lukas Reimann", role: "VP Sales",
    email: "l.reimann@voltari.io", phone: "+49 89 9988 221",
    eventType: "Firmenturnier", offerId: "turnier",
    date: "Fr, 18. Juli 2026", slot: "Ganztags", participants: 64,
    budget: "ca. 9.000 €", status: "neu", when: "vor 1 Std.",
    msg: "Anfrage für unseren Sales Kick-Off im Juli — 64 Teilnehmer. Wir hätten gern ein klassisches Turnier mit Siegerehrung und einem gemeinsamen Abendessen auf der Terrasse.",
    dual: true,
    wishDates: [
      { id: "d1", date: "Fr, 17. Juli 2026", slot: "Ganztags", responses: { sr: "pending", mp: "pending", os: "pending" } },
      { id: "d2", date: "Fr, 18. Juli 2026", slot: "Ganztags", responses: { sr: "pending", mp: "pending", os: "pending" } },
    ],
    alternatives: [],
    finalDateId: null,
    timeline: [
      { t: "Anfrage eingegangen", w: "vor 1 Std." },
      { t: "Team benachrichtigt (3 Personen)", w: "vor 1 Std." },
    ],
  },
  {
    id: 3, company: "Kerber & Söhne", initials: "KS", avatar: "clay",
    contact: "Petra Kerber", role: "Geschäftsführung",
    email: "kerber@kerber-soehne.de", phone: "+49 8121 44 556",
    eventType: "Schnupperkurs", offerId: "schnupper",
    date: "Mi, 4. Juni 2026", slot: "10:00 – 13:00", participants: 18,
    budget: "1.600 €", status: "bearbeitung", when: "gestern",
    msg: "Termin vom 4. Juni bestätigt — ist die Rechnung schon raus? Wir bräuchten sie für die Buchhaltung bis Monatsende.",
    dual: true,
    wishDates: [
      { id: "d1", date: "Mi, 4. Juni 2026", slot: "10:00 – 13:00", responses: { sr: "confirmed", mp: "confirmed", os: "confirmed" } },
    ],
    alternatives: [],
    finalDateId: "d1",
    timeline: [
      { t: "Anfrage eingegangen", w: "vor 6 Tagen" },
      { t: "Angebot gesendet", w: "vor 5 Tagen" },
      { t: "Termin bestätigt", w: "vor 2 Tagen" },
    ],
  },
  {
    id: 4, company: "Bavaria Robotics", initials: "BR", avatar: "default",
    contact: "Tom Engel", role: "Office Management",
    email: "t.engel@bavaria-robotics.de", phone: "+49 89 7766 110",
    eventType: "Coaching", offerId: "coaching",
    date: "Do, 19. Juni 2026", slot: "17:00 – 18:00", participants: 2,
    budget: "150 €", status: "bestaetigt", when: "vor 2 Tagen",
    msg: "Können wir noch eine 2. Trainerstunde dazu buchen? Zwei unserer Kollegen würden gern direkt im Anschluss weitermachen.",
    timeline: [
      { t: "Anfrage eingegangen", w: "vor 4 Tagen" },
      { t: "Angebot gesendet", w: "vor 3 Tagen" },
      { t: "Gebucht & bezahlt", w: "vor 2 Tagen" },
    ],
  },
  {
    id: 5, company: "SAP Innovation Lab", initials: "SI", avatar: "green",
    contact: "Maria Holzer", role: "People Ops",
    email: "maria.holzer@sap-lab.com", phone: "+49 89 5544 332",
    eventType: "Teamevent", offerId: "team",
    date: "Di, 26. August 2026", slot: "Ganztags", participants: 24,
    budget: "ca. 3.200 €", status: "bestaetigt", when: "vor 4 Tagen",
    msg: "Wir würden unseren letztjährigen Teamtag gern wiederholen — gleiche Gruppengröße, gleicher Ablauf. Lässt sich der 26. August reservieren?",
    timeline: [
      { t: "Anfrage eingegangen", w: "vor 6 Tagen" },
      { t: "Angebot gesendet", w: "vor 5 Tagen" },
      { t: "Termin bestätigt", w: "vor 4 Tagen" },
    ],
  },
  {
    id: 6, company: "Lobster Data GmbH", initials: "LD", avatar: "default",
    contact: "Jens Wirth", role: "HR Business Partner",
    email: "j.wirth@lobster-data.de", phone: "+49 89 2211 887",
    eventType: "After-Work Golf", offerId: "afterwork",
    date: "Do, 22. Mai 2026", slot: "ab 17:00", participants: 12,
    budget: "780 €", status: "abgelehnt", when: "vor 1 Woche",
    msg: "Spontane Feierabend-Runde für unser Team — geht das diesen Donnerstag noch?",
    timeline: [
      { t: "Anfrage eingegangen", w: "vor 1 Woche" },
      { t: "Abgelehnt — Termin ausgebucht", w: "vor 6 Tagen" },
    ],
  },
];

// Bookings for the calendar (June 2026). ISO dates so the grid lands them right.
const CAL_MONTH = { year: 2026, month: 5, label: "Juni 2026" }; // month 0-indexed
const BOOKINGS = [
  { date: "2026-06-04", company: "Kerber & Söhne",    eventType: "Schnupperkurs",    participants: 18, time: "10:00", status: "bestaetigt", offerId: "schnupper" },
  { date: "2026-06-05", company: "Voltari GmbH",      eventType: "Firmenturnier",   participants: 64, time: "09:00", status: "bestaetigt", offerId: "turnier" },
  { date: "2026-06-11", company: "Stadtwerke München", eventType: "Offsite",   participants: 22, time: "13:00", status: "option",     offerId: "offsite" },
  { date: "2026-06-12", company: "Allianz Tech Hub",  eventType: "Teamevent", participants: 30, time: "14:00", status: "angefragt",  offerId: "team" },
  { date: "2026-06-12", company: "Privat: M. Stein",   eventType: "Coaching",         participants: 1,  time: "11:00", status: "bestaetigt", offerId: "coaching" },
  { date: "2026-06-18", company: "Hofer & Partner",   eventType: "Schnupperkurs",    participants: 14, time: "10:00", status: "bestaetigt", offerId: "schnupper" },
  { date: "2026-06-19", company: "Bavaria Robotics",  eventType: "Coaching",         participants: 2,  time: "17:00", status: "bestaetigt", offerId: "coaching" },
  { date: "2026-06-24", company: "Rosenheim AG",      eventType: "Firmenturnier",   participants: 48, time: "09:00", status: "option",     offerId: "turnier" },
  { date: "2026-06-26", company: "Wacker Chemie",     eventType: "Teamevent", participants: 26, time: "09:30", status: "bestaetigt", offerId: "team" },
];

// Course gallery (Platz page)
const GALLERY = [
  { img: "design-system/imagery/gallery-1.jpg", cap: "Grün 18 mit Clubhaus" },
  { img: "design-system/imagery/gallery-3.jpg", cap: "Driving Range — 32 Abschläge" },
  { img: "design-system/imagery/gallery-2.jpg", cap: "Bunker an Bahn 7" },
  { img: "design-system/imagery/gallery-5.jpg", cap: "Übungsanlage & Putting-Green" },
  { img: "design-system/imagery/gallery-4.jpg", cap: "Wasserbahn 12" },
  { img: "design-system/imagery/gallery-6.jpg", cap: "Abendstimmung über dem Platz" },
];

// Amenities (Platz page)
const AMENITIES = [
  { icon: "flag",     label: "18-Loch Championship-Platz", sub: "Par 72 · 6.184 m" },
  { icon: "target",   label: "Driving Range",              sub: "32 Abschläge, 12 überdacht" },
  { icon: "coffee",   label: "Restaurant & Terrasse",      sub: "80 Plätze, regionale Küche" },
  { icon: "briefcase",label: "Tagungsraum",                sub: "bis 30 Personen, Beamer & WLAN" },
  { icon: "pin",      label: "Parkplätze",                 sub: "120 kostenlos, direkt am Clubhaus" },
  { icon: "trophy",   label: "Pro-Shop & Verleih",         sub: "Schläger, Trolleys, E-Carts" },
];

Object.assign(window, { COURSE, COURSE_TEAM, CATEGORIES, STATS, INBOX, REVIEWS, REQUESTS, BOOKINGS, CAL_MONTH, GALLERY, AMENITIES });
