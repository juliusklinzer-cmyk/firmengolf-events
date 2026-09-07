/* =====================================================================
 * Individuelle Events — Budget-Rechner + Anfrage-Wizard (vanilla JS-Insel)
 * Preise/Struktur kommen aus window.FGE_IND.bc (Backend-Option).
 * ===================================================================== */
(function () {
	'use strict';

	var CFG = window.FGE_IND || {};
	var BC  = CFG.bc || {};

	function fmt(n) {
		var step = (BC.round_to && BC.round_to > 0) ? BC.round_to : 50;
		return new Intl.NumberFormat('de-DE').format(Math.round(n / step) * step);
	}
	function find(arr, id) {
		arr = arr || [];
		for (var i = 0; i < arr.length; i++) { if (arr[i].id === id) return arr[i]; }
		return null;
	}
	function el(tag, cls, html) {
		var e = document.createElement(tag);
		if (cls) e.className = cls;
		if (html != null) e.innerHTML = html;
		return e;
	}
	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	var ARROW = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>';
	// Button-Icons im Onboarding-Stil (schlicht, im Button, kein Kreis-Hintergrund).
	var ICO_NEXT = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg>';
	var ICO_SEND = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4z"/></svg>';

	// Distinkte, markennahe Farbpalette — jede gewählte Leistung bekommt nach ihrer
	// Position in der Typ-Liste eine eigene Farbe (innerhalb eines Events alle verschieden).
	var SVC_PALETTE = [
		'#4279D1', '#6E9BDD', '#3768C0', '#009E78', '#4A5A8A', '#6C736E',
		'#9BBAE8', '#6E5AA0', '#C77D4A', '#C9B488', '#9C5A45', '#D8B26A',
		'#A8894E', '#B0A48C', '#D2693E', '#3768C0'
	];
	function svcColor(i) { return SVC_PALETTE[((i % SVC_PALETTE.length) + SVC_PALETTE.length) % SVC_PALETTE.length]; }

	/* =================================================================
	 * BUDGET CALCULATOR
	 * ================================================================= */
	function initCalc() {
		var root = document.getElementById('bcalc');
		if (!root || !BC.types) return;

		var start = BC.start || {};
		var state = {
			participants: start.participants || 30,
			type: start.type || (BC.types[0] && BC.types[0].id),
			range: start.range || '€€',
			services: [] // wird je nach Typ von applyType() gesetzt
		};

		var stepVal   = root.querySelector('#bc-participants');
		var typeSel   = root.querySelector('#bc-type');
		var breakList = root.querySelector('#bc-break-list');
		var donut     = root.querySelector('#bc-donut');
		var totalNum  = root.querySelector('#bc-total');
		var totalMeta = root.querySelector('#bc-total-meta');
		var ctaBtn    = root.querySelector('#bc-request');

		function compute() {
			var type = find(BC.types, state.type) || BC.types[0];
			var rng  = find(BC.ranges, state.range);
			var mult = rng ? rng.mult : 1;
			// Ein Posten je gewählter Leistung, eigene Farbe — in Tagesablauf-Reihenfolge des Typs.
			// Donut UND Aufschlüsselung nutzen dieselben Posten (1:1, gut unterscheidbar).
			var items = [];
			var order = (type.services && type.services.length) ? type.services : state.services;
			order.forEach(function (sid, pos) {
				if (state.services.indexOf(sid) < 0) return;
				var s = find(BC.services, sid);
				if (!s) return;
				var base = (s.flat > 0) ? s.flat : state.participants * s.pp;
				var amt = base * mult;
				if (amt <= 0) return;
				items.push({ label: s.label, color: svcColor(pos), amount: amt });
			});
			var total = items.reduce(function (a, r) { return a + r.amount; }, 0);
			return { rows: items, items: items, total: total, type: type };
		}

		function renderDonut(rows, total) {
			var size = 168, r = (size - 22) / 2, C = 2 * Math.PI * r, off = 0;
			var ns = 'http://www.w3.org/2000/svg';
			var svg = document.createElementNS(ns, 'svg');
			svg.setAttribute('width', size); svg.setAttribute('height', size);
			svg.setAttribute('viewBox', '0 0 ' + size + ' ' + size);
			var track = document.createElementNS(ns, 'circle');
			track.setAttribute('class', 'bc-donut-track');
			track.setAttribute('cx', size / 2); track.setAttribute('cy', size / 2);
			track.setAttribute('r', r); track.setAttribute('fill', 'none'); track.setAttribute('stroke-width', '20');
			svg.appendChild(track);
			rows.forEach(function (row) {
				var frac = total ? row.amount / total : 0, len = frac * C;
				var seg = document.createElementNS(ns, 'circle');
				seg.setAttribute('cx', size / 2); seg.setAttribute('cy', size / 2); seg.setAttribute('r', r);
				seg.setAttribute('fill', 'none'); seg.setAttribute('stroke', row.color);
				seg.setAttribute('stroke-width', '20'); seg.setAttribute('stroke-linecap', 'butt');
				seg.setAttribute('stroke-dasharray', len + ' ' + (C - len));
				seg.setAttribute('stroke-dashoffset', -off);
				svg.appendChild(seg);
				off += len;
			});
			donut.innerHTML = '';
			donut.appendChild(svg);
		}

		function render() {
			var res = compute();
			if (stepVal) stepVal.textContent = state.participants;
			// breakdown — eine Zeile je gewählter Leistung (matcht die Chips)
			breakList.innerHTML = '';
			res.items.forEach(function (row) {
				var r = el('div', 'bc-break-row');
				r.innerHTML = '<span class="bc-break-dot" style="background:' + esc(row.color) + '"></span>'
					+ '<span class="bc-break-name">' + esc(row.label) + '</span>'
					+ '<span class="bc-break-amt">' + fmt(row.amount) + ' €</span>';
				breakList.appendChild(r);
			});
			renderDonut(res.rows, res.total);
			if (donut) {
				donut.setAttribute('role', 'img');
				donut.setAttribute('aria-label', 'Budget-Aufteilung · Gesamt ca. ' + fmt(res.total) + ' €');
			}
			var empty = res.total <= 0;
			totalNum.textContent = fmt(res.total) + ' €';
			totalMeta.textContent = empty
				? 'Wähle mindestens eine Leistung'
				: ('Für ' + state.participants + ' Personen · ' + res.type.label);
			if (ctaBtn) {
				ctaBtn.disabled = empty;
				ctaBtn.style.opacity = empty ? '.5' : '';
				ctaBtn.style.pointerEvents = empty ? 'none' : '';
			}
		}

		// Typ wechseln: nur passende Chips zeigen, Vorauswahl/Pflicht setzen, neu rechnen.
		function applyType() {
			var t = find(BC.types, state.type) || BC.types[0];
			var vis = t.services || [], don = t.default_on || [], req = t.required || [];
			state.services = don.slice();
			req.forEach(function (id) { if (state.services.indexOf(id) < 0) state.services.push(id); });
			root.querySelectorAll('.bc-chip').forEach(function (btn) {
				var id = btn.getAttribute('data-id');
				btn.style.display = (vis.indexOf(id) >= 0) ? '' : 'none';
				btn.classList.toggle('is-locked', req.indexOf(id) >= 0);
				btn.classList.toggle('on', state.services.indexOf(id) >= 0);
			});
			render();
		}

		// steppers
		root.querySelectorAll('[data-bc-step]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var d = parseInt(btn.getAttribute('data-bc-step'), 10) || 0;
				state.participants = Math.max(6, Math.min(250, state.participants + d));
				render();
			});
		});
		// type select
		if (typeSel) typeSel.addEventListener('change', function () { state.type = typeSel.value; applyType(); });
		// range segments
		root.querySelectorAll('.bc-seg-btn').forEach(function (btn) {
			btn.addEventListener('click', function () {
				state.range = btn.getAttribute('data-range');
				root.querySelectorAll('.bc-seg-btn').forEach(function (b) { b.classList.toggle('on', b === btn); });
				render();
			});
		});
		// service chips
		root.querySelectorAll('.bc-chip').forEach(function (btn) {
			btn.addEventListener('click', function () {
				if (btn.classList.contains('is-locked')) return; // Pflicht-Service, nicht abwählbar
				var id = btn.getAttribute('data-id');
				var i = state.services.indexOf(id);
				if (i >= 0) state.services.splice(i, 1); else state.services.push(id);
				btn.classList.toggle('on');
				render();
			});
		});
		// CTA → open wizard prefilled
		var cta = root.querySelector('#bc-request');
		if (cta) cta.addEventListener('click', function () {
			var res = compute();
			var svcWiz = state.services.map(function (id) { var s = find(BC.services, id); return s ? s.wiz : null; })
				.filter(Boolean).filter(function (v, i, a) { return a.indexOf(v) === i; });
			var lo = Math.max(500, Math.round(res.total * 0.85 / 500) * 500);
			var hi = Math.max(lo + 500, Math.round(res.total * 1.15 / 500) * 500);
			// Wizard fragt Budget pro Person: Richtwert auf den passenden Chip mappen (Review 07.09.).
			var pp = state.participants > 0 ? res.total / state.participants : 0;
			var ppChip = pp <= 0 ? 'Noch unklar' : (pp < 50 ? 'Bis 50 € p.P.' : (pp < 100 ? '50 bis 100 € p.P.' : (pp < 200 ? '100 bis 200 € p.P.' : (pp < 500 ? '200 bis 500 € p.P.' : 'Über 500 € p.P.'))));
			Wizard.open('full', {
				occasion: res.type.wiz || 'Golf-Teamevent',
				size: String(state.participants),
				services: svcWiz,
				budget: ppChip,
				notes: 'Über den Budget-Rechner geschätzt: ' + res.type.label + ', ' + state.participants
					+ ' Personen, Preisniveau ' + state.range + ', Richtwert ca. ' + fmt(res.total) + ' € gesamt (' + lo.toLocaleString('de-DE') + ' bis ' + hi.toLocaleString('de-DE') + ' €).'
			}, false, 'budget');
		});

		applyType();
	}

	/* =================================================================
	 * REQUEST WIZARD
	 * ================================================================= */
	var Wizard = (function () {
		var FULL_STEPS = ['Anlass', 'Eckdaten', 'Leistungen', 'Budget', 'Kontakt'];
		/* Gruppen/Reihenfolge/Wording nach Julius (2026-07-06): Mittagessen statt Lunch,
		   Grill + Einfach/Gehoben in der Gastro, Logistik direkt nach Gastronomie,
		   Technik & Show mit neuen Posten. */
		/* Neu sortiert nach Julius (2026-08-27): konkrete Golf-Formate statt Sammelbegriffe,
		   Logistik/Foto/Branding zu „Sonstiges" zusammengelegt, Showtechnik eigener Block. */
		var SERVICE_GROUPS = [
			{ group: 'Sport & Programm', items: ['Grundlagenkurs', 'Platzreifekurs', 'Kurzplatz-Turnier', '9-Loch-Turnier', '18-Loch-Turnier', 'Putting-Challenge', 'Long-Drive-Challenge'] },
			{ group: 'Gastronomie', items: ['Frühstück', 'Mittagessen', 'Abendessen', 'Grill', 'Bar & Drinks', 'Einfach', 'Gehoben'] },
			{ group: 'Sonstiges', items: ['Meetingraum', 'Shuttle / Transport', 'Übernachtung', 'Schlechtwetter-Alternative', 'Fotograf', 'Content-Team für Social Media', 'Branding & Banner', 'Individuelle Artikel', 'Pokale & Preise'] },
			{ group: 'Showtechnik', items: ['Bildschirme mit Veranstaltungsfotos', 'Musik und DJ', 'Bühne mit Licht und Ton', 'Liveband', 'Flutlicht und Nacht-Event', 'Sonstiges'] }
		];
		/* Kleine Zusatzzeile unter einzelnen Kacheln. */
		var SVC_SUBS = { 'Kurzplatz-Turnier': 'ohne Platzreife möglich' };
		/* Icon je Leistung (Kachel-Design wie in der Partnerplatz-Anlage). */
		function svcIcon(it) {
			var svg = function (p) { return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + p + '</svg>'; };
			var s = it.toLowerCase();
			if (s.indexOf('grundlagenkurs') >= 0)                      return svg('<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/>');
			if (s.indexOf('platzreife') >= 0)                          return svg('<path d="M4 12l5 5L20 6"/><path d="M4 19h16"/>');
			if (s.indexOf('long-drive') >= 0)                          return svg('<path d="M3 12h15"/><path d="M13 6l6 6-6 6"/><circle cx="4.5" cy="18.5" r="1.6"/>');
			if (s.indexOf('turnier') >= 0 || s.indexOf('pokale') >= 0) return svg('<path d="M8 21h8M12 17v4"/><path d="M7 4h10v4a5 5 0 0 1-10 0z"/><path d="M7 6H4a3 3 0 0 0 3 4M17 6h3a3 3 0 0 1-3 4"/>');
			if (s.indexOf('putting') >= 0)                             return svg('<path d="M12 16V3"/><path d="M12 4l6 2-6 2"/><path d="M5 19c0-1.4 3.1-2.5 7-2.5s7 1.1 7 2.5-3.1 2.5-7 2.5-7-1.1-7-2.5z"/>');
			if (s.indexOf('frühstück') >= 0)                           return svg('<path d="M4 8h12v6a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4z"/><path d="M16 9h2a2.5 2.5 0 0 1 0 5h-2"/><path d="M7 3v2M11 3v2"/>');
			if (s.indexOf('mittagessen') >= 0 || s === 'einfach')      return svg('<path d="M6 3v5M9 3v5M12 3v5"/><path d="M6 8a3 3 0 0 0 6 0"/><path d="M9 11v10"/><path d="M17 3c-2 2.5-2 6.5 0 9v9"/>');
			if (s.indexOf('abendessen') >= 0 || s === 'gehoben')       return svg('<path d="M12 4a8 8 0 0 1 8 8H4a8 8 0 0 1 8-8z"/><path d="M3 15h18"/><path d="M12 2v2"/>');
			if (s.indexOf('grill') >= 0)                               return svg('<path d="M4 9h16a8 8 0 0 1-16 0z"/><path d="M8 17l-2 4M16 17l2 4M12 17v4"/><path d="M9 4c0 1-1 1-1 2M13 4c0 1-1 1-1 2M17 4c0 1-1 1-1 2"/>');
			if (s.indexOf('bar') >= 0)                                 return svg('<path d="M4 4h16l-8 9z"/><path d="M12 13v6M8 21h8"/>');
			if (s.indexOf('bildschirm') >= 0)                          return svg('<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 21h8M12 16v5"/>');
			if (s.indexOf('musik') >= 0 || s.indexOf('band') >= 0)     return svg('<path d="M9 18V6l10-2v11"/><circle cx="6.5" cy="18" r="2.5"/><circle cx="16.5" cy="15" r="2.5"/>');
			if (s.indexOf('bühne') >= 0)                               return svg('<rect x="9" y="3" width="6" height="9" rx="3"/><path d="M7 9a5 5 0 0 0 10 0"/><path d="M12 14v4"/><path d="M5 21h14"/>');
			if (s.indexOf('flutlicht') >= 0 || s.indexOf('nacht') >= 0) return svg('<rect x="7" y="3" width="10" height="4" rx="1"/><path d="M12 7v14M8 21h8"/><path d="M9 9l-2 3M15 9l2 3"/>');
			if (s.indexOf('fotograf') >= 0)                            return svg('<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7l1.5-3h5L16 7"/><circle cx="12" cy="13" r="3.5"/>');
			if (s.indexOf('content') >= 0)                             return svg('<rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/>');
			if (s.indexOf('branding') >= 0)                            return svg('<path d="M3 3h8l10 10-8 8L3 11z"/><circle cx="7.5" cy="7.5" r="1.3"/>');
			if (s.indexOf('artikel') >= 0)                             return svg('<path d="M8 4 4 7l2 3 2-1v11h8V9l2 1 2-3-4-3a4 4 0 0 1-8 0z"/>');
			if (s.indexOf('meetingraum') >= 0)                         return svg('<rect x="3" y="4" width="18" height="12" rx="1.5"/><path d="M12 16v4M8 20h8"/><path d="M7 8h6M7 11h4"/>');
			if (s.indexOf('shuttle') >= 0)                             return svg('<rect x="3" y="5" width="18" height="11" rx="2"/><path d="M3 11h18"/><circle cx="7.5" cy="18.5" r="1.6"/><circle cx="16.5" cy="18.5" r="1.6"/>');
			if (s.indexOf('übernachtung') >= 0)                        return svg('<path d="M3 18v-8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v8"/><path d="M3 18h18M6 8V6h5v2"/>');
			if (s.indexOf('schlechtwetter') >= 0)                      return svg('<path d="M12 3a8 8 0 0 1 8 8H4a8 8 0 0 1 8-8z"/><path d="M12 11v7a2 2 0 0 1-4 0"/>');
			if (s === 'sonstiges')                                     return svg('<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>');
			return svg('<path d="M12 3l2.2 5.4L20 9l-4.4 3.8L17 19l-5-3-5 3 1.4-6.2L4 9l5.8-.6z"/>');
		}
		/* Budget pro Person statt großer Gesamtsummen (Julius, 27.08.: hohe Beträge
		   schrecken ab, pro Kopf lässt sich besser planen). */
		var BUDGETS = ['Bis 50 € p.P.', '50 bis 100 € p.P.', '100 bis 200 € p.P.', '200 bis 500 € p.P.', 'Über 500 € p.P.', 'Noch unklar'];
		var CONTACT = { name: 'Julius Klinzer', role: 'Gründer' };

		var overlay = null, S = null;

		function blank(preset) {
			var f = {
				occasion: '', goal: '', size: '20', region: '', place: '', experience: '', startzeit: '',
				budget: 'Noch unklar', when: '', flex: 'flexibel', duration: '',
				/* services OHNE Default: der Quick-Modus zeigt keinen Leistungs-Schritt und
				   hat sonst nie gewählte Wünsche mitgesendet (Kern-Audit H1, 2026-07-08). */
				date1: '', date2: '', date3: '', services: [],
				company: '', city: '', firstName: '', lastName: '', email: '', phone: '',
				contactPref: 'E-Mail', diet: '', notes: '', consent: false
			};
			if (preset) for (var k in preset) if (preset.hasOwnProperty(k)) f[k] = preset[k];
			return f;
		}

		// Frühester Wunschtermin: eine Woche ab heute (Julius, 2026-08-10). Kurzfristigere
		// Anfragen sind unrealistisch und erzeugen nur Absagen.
		var MIN_DATE = (function () { var d = new Date(); d.setDate(d.getDate() + 7); return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2); })();

		function fmtDateDE(iso) {
			var p = (iso || '').split('-');
			return p.length === 3 ? p[2] + '.' + p[1] + '.' + p[0] : iso;
		}
		/* iOS Safari erzwingt das min-Attribut bei type=date nicht, und doppelte
		   Wunschtermine prüft kein Browser (Julius, 28.08.). Zu frühe oder
		   doppelte Termine werden geleert, der Hinweis erscheint unter der Zeile. */
		function checkDates() {
			if (!overlay) { return true; }
			var msg = '', seen = {};
			['date1', 'date2', 'date3'].forEach(function (f) {
				var inp = overlay.querySelector('[data-field="' + f + '"]');
				if (!inp || !inp.value) { S.form[f] = ''; return; }
				if (inp.value < MIN_DATE) {
					inp.value = ''; S.form[f] = '';
					msg = msg || ('Wunschtermine brauchen mindestens 7 Tage Vorlauf, der früheste Termin ist der ' + fmtDateDE(MIN_DATE) + '.');
				} else if (seen[inp.value]) {
					inp.value = ''; S.form[f] = '';
					msg = msg || 'Diesen Termin hast du schon gewählt, gib gern einen anderen Ausweichtermin an.';
				} else {
					seen[inp.value] = true; S.form[f] = inp.value;
				}
			});
			var hint = overlay.querySelector('#rw-date-hint');
			if (hint) { hint.textContent = msg; hint.hidden = !msg; }
			return !msg;
		}

		// Anlass-Auswahl: Reihenfolge + Icon-Kacheln (einheitlich mit den Leistungs-Kacheln).
		/* Golf-Bezug in allen Anlass-Namen (Julius, 2026-08-27); die Event-KATEGORIEN
		   auf der Eventliste behalten bewusst die kurzen Namen. */
		// Saison-Reihenfolge (Julius, 07.09.): Indoor Weihnachtsfeier direkt nach dem Teamevent;
		// „Andere Events" als letzte Kachel öffnet ein Freitextfeld.
		var OCC_OTHER = 'Andere Events';
		var OCCASIONS = ['Golf-Teamevent', 'Indoor Weihnachtsfeier', 'After-Work Golf', 'Golf & Workshop', 'Firmen-Golfturnier', 'Golf-Kundenevent', 'Indoor-Golf-Event', 'Nachtgolf-Event', OCC_OTHER];
		var OCC_ICONS = {
			'Golf-Teamevent': '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
			'After-Work Golf': '<path d="M3 18h18"/><path d="M7 18a5 5 0 0 1 10 0"/><path d="M12 4v3M5.2 7.2l1.6 1.6M18.8 7.2l-1.6 1.6M3 12h2M19 12h2"/>',
			'Golf & Workshop': '<rect x="3" y="4" width="18" height="12" rx="1"/><path d="M12 16v4M8 20h8"/><path d="M7 8h10M7 11h6"/>',
			'Firmen-Golfturnier': '<path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0z"/><path d="M7 6H4v2a3 3 0 0 0 3 3M17 6h3v2a3 3 0 0 1-3 3"/>',
			'Golf-Kundenevent': '<circle cx="12" cy="8" r="3"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/><path d="M16 4h4v4"/>',
			'Indoor-Golf-Event': '<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/><path d="M8 12l2.5-3 2 2L15 8"/>',
			'Nachtgolf-Event': '<path d="M20 13.5A8 8 0 1 1 10.5 4a6.2 6.2 0 0 0 9.5 9.5z"/>',
			'Indoor Weihnachtsfeier': '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M5 12v9h14v-9"/><path d="M12 8S10.5 3 8 4.5 9.5 8 12 8zM12 8s1.5-5 4-3.5S14.5 8 12 8z"/>',
			'Andere Events': '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>'
		};
		function occCards() {
			// Preset-Anlässe außerhalb der Standardliste (z. B. Sommerfest von den
			// Themen-Kacheln) als zusätzliche, gewählte Kachel zeigen.
			// Alt-Deeplinks (?anlass=Weihnachtsfeier) auf die Standard-Kachel mappen (Review 07.09.).
			if (S.form.occasion === 'Weihnachtsfeier') S.form.occasion = 'Indoor Weihnachtsfeier';
			var list = OCCASIONS.slice();
			if (S.form.occasion && list.indexOf(S.form.occasion) < 0) list.push(S.form.occasion);
			return '<div class="ind-cards rw-occ-cards">' + list.map(function (o) {
				var on = S.form.occasion === o;
				return '<button type="button" class="ind-card' + (on ? ' on' : '') + '" aria-pressed="' + (on ? 'true' : 'false')
					+ '" data-chip="occasion" data-val="' + esc(o) + '"><span class="ind-card-ico"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (OCC_ICONS[o] || OCC_ICONS[OCC_OTHER]) + '</svg></span><span class="ind-card-l">' + esc(o) + '</span></button>';
			}).join('') + '</div>';
		}

		// Teilnehmerzahl als Stepper: Minus links, Zahl mittig, Plus rechts.
		function sizeStepper() {
			return '<div class="rw-stepper"><button type="button" class="rw-step-btn" data-act="size-dec" aria-label="Weniger Personen">−</button>'
				+ '<div class="rw-stepper-mid"><input class="rw-stepper-val" data-field="size" type="number" min="1" max="999" inputmode="numeric" value="' + esc(S.form.size) + '" aria-label="Teilnehmerzahl"><span class="rw-stepper-unit">Personen</span></div>'
				+ '<button type="button" class="rw-step-btn" data-act="size-inc" aria-label="Mehr Personen">+</button></div>';
		}
		function chips(field, options) {
			return '<div class="ind-chip-group">' + options.map(function (o) {
				var on = S.form[field] === o;
				return '<button type="button" class="ind-pchip' + (on ? ' on' : '') + '" aria-pressed="' + (on ? 'true' : 'false')
					+ '" data-chip="' + esc(field) + '" data-val="' + esc(o) + '">' + esc(o) + '</button>';
			}).join('') + '</div>';
		}
		function input(field, attrs, ph) {
			attrs = attrs || '';
			return '<input class="fg-input" data-field="' + esc(field) + '" value="' + esc(S.form[field]) + '" '
				+ attrs + ' placeholder="' + esc(ph || '') + '">';
		}
		function label(txt, req, hint) {
			return '<span class="ind-flabel">' + esc(txt)
				+ (req ? '<span class="ind-required">*</span>' : '')
				+ (hint ? '<span class="ind-flabel-hint">' + esc(hint) + '</span>' : '') + '</span>';
		}
		// Konkreter Platzwunsch: Suchfeld mit Autocomplete über ALLE deutschen Plätze
		// (Datalist; die Namen kommen aus dem DGV-Verzeichnis). Julius, 2026-07-06.
		function placeField() {
			var places = CFG.places || [];
			if (!places.length) return '';
			var opts = places.map(function (p) { return '<option value="' + esc(p) + '">'; }).join('');
			return '<div class="rw-field">' + label('Wunsch-Golfplatz', false, 'Optional, tippen zum Suchen')
				+ '<input class="fg-input" data-field="place" list="rw-place-list" value="' + esc(S.form.place) + '" placeholder="Golfplatz oder Ort eintippen …" autocomplete="off">'
				+ '<datalist id="rw-place-list">' + opts + '</datalist></div>';
		}

		function topBar() {
			var shortcut = (S.phase === 'form' && S.mode !== 'success') ?
				'<button class="rw-shortcut" data-act="toggle-mode">'
				+ (S.mode === 'quick' ? 'Ausführliche Anfrage' : 'Schnell-Anfrage in 30 Sek.') + '</button>' : '';
			var logo = CFG.logo ? '<img src="' + esc(CFG.logo) + '" alt="Firmengolf" height="24">' : '';
			/* Hilfe-Popover: Anruf + Mail, wie „Noch Fragen?" beim Partner-Onboarding (Julius, 27.08.). */
			var help = '';
			if (CFG.phoneDisplay || CFG.helpEmail) {
				/* Persönliche Kontaktkarte mit Julius-Foto (Julius, 27.08.): wer hier klickt,
				   will direkt Kontakt aufnehmen; Anruf und Mail öffnen sofort. */
				help = '<div class="rw-help">'
					+ '<button class="rw-help-btn" data-act="help" aria-haspopup="true" aria-expanded="false">Hilfe</button>'
					+ '<div class="rw-help-pop" hidden>'
					+ (CFG.juliusImg
						? '<div class="rw-help-person"><img src="' + esc(CFG.juliusImg) + '" alt="' + esc(CONTACT.name) + '" width="46" height="46">'
							+ '<span class="rw-help-person-t"><b>' + esc(CONTACT.name) + '</b><i>' + esc(CONTACT.role) + ' · Firmengolf</i></span></div>'
						: '<div class="rw-help-t">Wir helfen persönlich weiter</div>')
					+ (CFG.phoneDisplay ? '<a class="rw-help-row" href="tel:' + esc(CFG.phoneTel || '') + '">'
						+ '<span class="rw-help-ic"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .8 2.9a2 2 0 0 1-.5 2.1L8.1 10a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.4 1.9.7 2.9.8a2 2 0 0 1 1.6 2z"/></svg></span>'
						+ '<span class="rw-help-txt"><b>Anrufen</b><i>' + esc(CFG.phoneDisplay) + '</i></span></a>' : '')
					+ (CFG.helpEmail ? '<a class="rw-help-row" href="mailto:' + esc(CFG.helpEmail) + '">'
						+ '<span class="rw-help-ic"><svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg></span>'
						+ '<span class="rw-help-txt"><b>E-Mail schreiben</b><i>' + esc(CFG.helpEmail) + '</i></span></a>' : '')
					+ '</div></div>';
			}
			return '<header class="rw-top"><div class="rw-top-brand">' + logo + '<span class="rw-top-title">Event anfragen</span></div>'
				+ '<div class="rw-top-actions">' + shortcut + help
				+ '<button class="rw-close" data-act="close" aria-label="Schließen"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div></header>';
		}

		// Foto-Panel (Driver am Abschlag, wie im Partner-Onboarding) neben dem Einstieg.
		// Bild rechts folgt dem Anlass (Julius, 07.09.); der Kontakt-Schritt zeigt den
		// vollen Schwung: der Ball geht raus, die Anfrage auch. Dateien aus dem Bildpool.
		var OCC_IMGS = {
			'Golf-Teamevent':         'teamevent-gruppe-im-cart.jpg',
			'Indoor Weihnachtsfeier': 'indoor-bier-und-simulator.jpg',
			'After-Work Golf':        'afterwork-anstossen.jpg',
			'Golf & Workshop':        'teamevent-ki-generierte-sicht-aus-dem-meeting-raum-auf-den-golfplatz.jpg',
			'Firmen-Golfturnier':     'turnier-putt-gegenlicht.jpg',
			'Golf-Kundenevent':       'kundenevent-handshake.jpg',
			'Indoor-Golf-Event':      'indoor-golf-bar-und-fun-imi-team.jpg',
			'Nachtgolf-Event':        'nachtevent-flutlicht-gruen.jpg',
			'Sommerfest':             'afterwork-anstossen.jpg',
			'Weihnachtsfeier':        'indoor-bier-und-simulator.jpg'
		};
		var SEND_IMG = 'pool-hochformat-afterwork-basti-duschschwung.jpg';
		function photoSrc(kind) {
			var base = CFG.poolBase || '';
			if (kind === 'send' && base) return base + SEND_IMG;
			var f = OCC_IMGS[S.form.occasion];
			return (f && base) ? base + f : (CFG.introImg || '');
		}
		function photoPanel(kind) {
			var src = photoSrc(kind);
			// Bewusst ohne Overlay-Karte (Julius, 2026-08-20): das Bild bleibt ruhig,
			// der Ansprechpartner kommt gross auf dem Erfolgs-Screen.
			return src ? '<div class="rw-photo" role="img" aria-label="' + (kind === 'send' ? 'Golfer im vollen Schwung' : 'Passendes Motiv zum Anlass') + '" style="background-image:url(\'' + esc(src) + '\')"></div>' : '';
		}

		function screenIntro() {
			var occ = S.form.occasion || 'Firmenevent';
			// Passender Artikel je Anlass (sonst „ein Incentive-Reise").
			var OCC_ARTICLE = { 'Incentive-Reise': 'eine', 'Platzreife': 'eine' };
			var art = OCC_ARTICLE[occ] || 'ein';
			return '<div class="rw-stage"><div class="rw-screen rw-has-photo rw-intro"><div class="rw-main">'
				+ '<div class="rw-eyebrow">Schön, dass du da bist</div>'
				+ '<h2 class="rw-h">Toll, ihr plant ' + art + ' <span class="mk-italic">' + esc(occ) + '</span> für euer Team.</h2>'
				+ '<p class="rw-lead">Lass uns kurz ein paar Infos sammeln. Danach meldet sich ' + esc(CONTACT.name)
				+ ' persönlich bei dir, meist innerhalb eines Werktags, mit ersten Ideen und einem Richtpreis.</p>'
				+ '<div class="rw-intro-contact"><div><div class="rw-intro-c-name">' + esc(CONTACT.name) + '</div>'
				+ '<div class="rw-intro-c-role">' + esc(CONTACT.role) + ' · Firmengolf</div>'
				+ '<div class="rw-intro-c-note">„Ich kümmere mich persönlich um deine Anfrage."</div></div></div>'
				+ '<ul class="rw-intro-steps"><li>Ein paar Eckdaten, keine zwei Minuten</li>'
				+ '<li>Persönliche Rückmeldung statt Funnel</li><li>Unverbindlich und kostenlos</li></ul>'
				+ '</div>' + photoPanel() + '</div></div>'
				+ '<div class="rw-foot"><div class="rw-nav"><button class="rw-btn-text" data-act="close">Abbrechen</button>'
				+ '<button class="rw-btn-primary" data-act="intro-start">Los geht\'s ' + ICO_NEXT + '</button></div></div>';
		}

		/* Durchgehender Fortschrittsbalken im Fuß (Airbnb-Muster, Julius 2026-08-27):
		   zwei Segmente, füllen sich pro Schritt von links. */
		function quickProgress() {
			return '<div class="rw-qprog" aria-hidden="true"><i class="' + (S.step >= 0 ? 'done' : '') + '"></i><i class="' + (S.step >= 1 ? 'done' : '') + '"></i></div>';
		}

		/* Schnell-Anfrage in ZWEI leichten Schritten (Julius, 2026-08-20):
		   1) Anlass + Teilnehmerzahl  2) Kontakt + Nachricht + Consent.
		   Der Weg zur ausfuehrlichen Anfrage lebt oben rechts in der Kopfzeile,
		   der Fuss traegt nur noch Fortschritt und Aktion. */
		function screenQuick() {
			if (S.step === 0) {
				/* Überschrift + kleiner Text auch mobil sichtbar (Revert, Julius 27.08. spät). */
				return '<div class="rw-stage"><div class="rw-screen rw-has-photo"><div class="rw-main">'
					+ '<h2 class="rw-h rw-h--quick">Euer Anlass für die Anfrage.</h2>'
					+ '<p class="rw-lead rw-lead--quick">Wählt den Anlass, den Rest klären wir persönlich. Kostenlos und unverbindlich.</p>'
					+ '<div class="rw-form">'
					+ '<div class="rw-field">' + label('Anlass') + occCards() + '</div>'
					+ (S.form.occasion === OCC_OTHER
						? '<div class="rw-field">' + label('An welches Event hast du gedacht?', false, 'Ein Satz reicht')
							+ input('goal', '', 'z. B. Sommerfest mit Barbecue, Azubi-Tag, Golf und Meeting') + '</div>'
						: '')
					+ '<div class="rw-field">' + label('Teilnehmerzahl') + sizeStepper() + '</div>'
					+ '</div></div>' + photoPanel() + '</div></div>'
					+ '<div class="rw-foot">' + quickProgress() + '<div class="rw-nav rw-nav--quick"><span class="rw-nav-spacer" aria-hidden="true"></span>'
					+ '<button class="rw-btn-primary" data-act="next">Weiter ' + ICO_NEXT + '</button></div></div>';
			}
			return '<div class="rw-stage"><div class="rw-screen rw-has-photo"><div class="rw-main">'
				+ '<h2 class="rw-h rw-h--quick rw-h--quick2">Noch kurz zu euch.</h2>'
				+ '<p class="rw-lead rw-lead--quick">Innerhalb eines Werktags habt ihr konkrete Vorschläge im Postfach.</p>'
				+ '<div class="rw-form">'
				+ '<div class="rw-row"><div class="rw-field">' + label('Vor- & Nachname', true) + input('firstName', 'required', 'Vor- und Nachname') + '</div>'
				+ '<div class="rw-field">' + label('E-Mail', true) + input('email', 'type="email" required', 'name@firma.de') + '</div></div>'
				/* Kontakt wie in der ausführlichen Anfrage (Julius, 28.08.): Telefon
				   optional plus die Kontaktart-Buttons, sonst wäre „Telefon" nicht wählbar. */
				+ '<div class="rw-row"><div class="rw-field">' + label('Firma') + input('company', '', 'Musterfirma GmbH') + '</div>'
				+ '<div class="rw-field">' + label('Telefon') + input('phone', 'type="tel"', '+49 …') + '</div></div>'
				+ '<div class="rw-field">' + label('Bevorzugte Kontaktart') + chips('contactPref', ['E-Mail', 'Telefon', 'Egal']) + '</div>'
				+ '<div class="rw-field">' + label('Was habt ihr vor?') + '<textarea class="fg-input" data-field="notes" rows="2" placeholder="Ein, zwei Sätze zu Ziel, Stimmung, Wünschen.">' + esc(S.form.notes) + '</textarea></div>'
				+ '<label class="ind-consent"><input type="checkbox" data-field="consent"' + (S.form.consent ? ' checked' : '') + '><span>Ich stimme der Verarbeitung meiner Daten zur Bearbeitung der Anfrage gemäß <a href="' + esc(CFG.privacyUrl || '/datenschutz/') + '" target="_blank" rel="noopener">Datenschutzerklärung</a> zu.</span></label>'
				+ '</div></div>' + photoPanel('send') + '</div></div>'
				+ '<div class="rw-foot">' + quickProgress() + '<div class="rw-nav rw-nav--quick"><button class="rw-btn-text" data-act="back">Zurück</button>'
				+ '<button class="rw-btn-primary" data-act="submit">Anfrage senden ' + ICO_SEND + '</button></div></div>';
		}

		function fullStepBody(step) {
			if (step === 0) {
				return '<div class="rw-eyebrow">Schritt 1 · ' + FULL_STEPS[0] + '</div>'
					+ '<h2 class="rw-h">Worum geht\'s bei eurem Event?</h2>'
					+ '<p class="rw-lead">Wähl den nächstpassenden Anlass, wir verfeinern alles im Gespräch.</p>'
					+ '<div class="rw-form"><div class="rw-field">'
					+ occCards()
					+ '</div>'
					/* Freitext nur bei „Andere Events" (Julius, 07.09.; vorher hing das Feld am alten Label „Etwas anderes" und erschien nie) */
					+ (S.form.occasion === OCC_OTHER
						? '<div class="rw-field">' + label('An welches Event hast du gedacht?', false, 'Ein Satz reicht')
							+ input('goal', '', 'z. B. Sommerfest mit Barbecue, Azubi-Tag, Golf und Meeting') + '</div>'
						: '')
					+ '</div>';
			}
			if (step === 1) {
				/* Neu strukturiert nach Julius (27.08. spät): schlanke Sektions-Überschriften
				   statt Doppel-Labels, Wunschort als Freitext statt Region-Chips. */
				return '<div class="rw-eyebrow">Schritt 2 · ' + FULL_STEPS[1] + '</div>'
					+ '<h2 class="rw-h">Die Eckdaten eures Events.</h2>'
					+ '<div class="rw-form">'
					+ '<section class="rw-sec"><div class="rw-sec-h">Teilnehmerzahl</div>'
					+ '<div class="rw-field">' + sizeStepper() + '</div></section>'
					+ '<section class="rw-sec"><div class="rw-sec-h">Wunschtermine</div>'
					+ '<div class="rw-field"><div class="rw-row rw-row-3">' + input('date1', 'type="date" min="' + MIN_DATE + '"', '1. Termin') + input('date2', 'type="date" min="' + MIN_DATE + '"', '2. Termin') + input('date3', 'type="date" min="' + MIN_DATE + '"', '3. Termin') + '</div>'
				+ '<p class="rw-date-hint" id="rw-date-hint" hidden></p></div>'
					+ '<div class="rw-field">' + label('Wie flexibel seid ihr beim Datum?') + chips('flex', ['fix', '± 1 Woche', 'flexibel', 'noch offen']) + '</div>'
					+ '<div class="rw-field">' + label('Gewünschter Startzeitpunkt') + chips('startzeit', ['Morgens', 'Vormittags', 'Mittags', 'After-Work', 'Noch offen']) + '</div></section>'
					+ '<section class="rw-sec"><div class="rw-sec-h">Ort</div>'
					+ '<div class="rw-field">' + label('Wunschort') + input('region', '', 'z. B. Raum München, Bodensee, Sylt …') + '</div>'
					+ placeField() + '</section>'
					+ '<section class="rw-sec"><div class="rw-sec-h">Golf-Erfahrung</div>'
					+ '<div class="rw-field">' + chips('experience', ['Überwiegend Anfänger', 'Gemischt', 'Erfahrene Golfer', 'Weiß noch nicht']) + '</div></section>'
					+ '</div>';
			}
			if (step === 2) {
				/* Kompakte Icon-Pills statt hoher Kachelwand: 26 Optionen in 6 Gruppen waren
				   unübersichtlich (Julius, 27.08.). Icon links, Label rechts, mehr pro Zeile. */
				var groups = SERVICE_GROUPS.map(function (g) {
					return '<section class="rw-sec"><div class="rw-sec-h">' + esc(g.group) + '</div><div class="ind-cards rw-svc-cards">'
						+ g.items.map(function (it) {
							var on = S.form.services.indexOf(it) >= 0;
							return '<button type="button" class="ind-card' + (on ? ' on' : '') + '" aria-pressed="' + (on ? 'true' : 'false')
								+ '" data-svc="' + esc(it) + '"><span class="ind-card-ico">' + svcIcon(it) + '</span><span class="ind-card-l">' + esc(it)
								+ (SVC_SUBS[it] ? '<span class="ind-card-sub">' + esc(SVC_SUBS[it]) + '</span>' : '') + '</span></button>';
						}).join('') + '</div></section>';
				}).join('');
				return '<div class="rw-eyebrow">Schritt 3 · ' + FULL_STEPS[2] + '</div>'
					+ '<h2 class="rw-h">Was soll dabei sein?</h2>'
					+ '<p class="rw-lead">Wählt so viel oder wenig ihr wollt, wir besprechen danach alles im Detail.</p>'
					+ '<div class="rw-form rw-svc-pick">' + groups + '</div>';
			}
			if (step === 3) {
				/* Chips im Site-Design statt großer Budget-Karten (Julius, 27.08.). */
				return '<div class="rw-eyebrow">Schritt 4 · ' + FULL_STEPS[3] + '</div>'
					+ '<h2 class="rw-h">Was wäre euer Budget-Rahmen?</h2>'
					+ '<p class="rw-lead">Ein Richtwert pro Person genügt, so planen wir passgenau.</p>'
					+ '<div class="rw-form"><div class="rw-field">' + label('Budget pro Person') + chips('budget', BUDGETS) + '</div>'
					+ '<div class="rw-field" style="margin-top:8px;">' + label('Verpflegung & Diät', false, 'Optional') + input('diet', '', 'z.B. 5× vegetarisch, 1× vegan, Nussallergie') + '</div>'
					+ '<div class="rw-field" style="margin-top:8px;">' + label('Was ist euch wichtig?')
					+ '<textarea class="fg-input" data-field="notes" rows="4" placeholder="Stimmung, Hintergrund, besondere Wünsche, alles was hilft.">' + esc(S.form.notes) + '</textarea></div></div>';
			}
			// step 4
			return '<div class="rw-eyebrow">Schritt 5 · ' + FULL_STEPS[4] + '</div>'
				+ '<h2 class="rw-h">Wer seid ihr, und wie erreichen wir dich?</h2>'
				+ '<p class="rw-lead">Letzter Schritt. Danach melden wir uns innerhalb eines Werktags.</p>'
				+ '<div class="rw-form"><div class="rw-row"><div class="rw-field">' + label('Unternehmen', true) + input('company', 'required', 'Musterfirma GmbH') + '</div>'
				+ '<div class="rw-field">' + label('Ort') + input('city', 'list="rw-city-list" autocomplete="off"', 'Ort eintippen …') + '<datalist id="rw-city-list"></datalist></div></div>'
				+ '<div class="rw-row"><div class="rw-field">' + label('Vorname') + input('firstName', '', 'Vorname') + '</div>'
				+ '<div class="rw-field">' + label('Nachname', true) + input('lastName', 'required', 'Nachname') + '</div></div>'
				+ '<div class="rw-row"><div class="rw-field">' + label('E-Mail', true) + input('email', 'type="email" required', 'name@firma.de') + '</div>'
				+ '<div class="rw-field">' + label('Telefon') + input('phone', 'type="tel"', '+49 …') + '</div></div>'
				+ '<div class="rw-field">' + label('Bevorzugte Kontaktart') + chips('contactPref', ['E-Mail', 'Telefon', 'Egal']) + '</div>'
				+ '<label class="ind-consent"><input type="checkbox" data-field="consent"' + (S.form.consent ? ' checked' : '') + '>'
				+ '<span>Ich stimme der Verarbeitung meiner Daten zur Bearbeitung der Anfrage gemäß <a href="' + esc(CFG.privacyUrl || '/datenschutz/') + '" target="_blank" rel="noopener">Datenschutzerklärung</a> zu.</span></label></div>';
		}

		function screenFull() {
			/* Durchgehender Fortschrittsbalken ohne Labels (Airbnb-Muster, Julius 27.08.):
			   die Schritt-Info trägt die Eyebrow im Inhalt („Schritt 2 · Eckdaten"). */
			var segs = FULL_STEPS.map(function (lbl, i) {
				return '<i class="' + (i <= S.step ? 'done' : '') + '"></i>';
			}).join('');
			var isLast = S.step === FULL_STEPS.length - 1;
			var body   = S.step === 0
				? '<div class="rw-screen rw-has-photo"><div class="rw-main">' + fullStepBody(0) + '</div>' + photoPanel() + '</div>'
				: '<div class="rw-screen">' + fullStepBody(S.step) + '</div>';
			return '<div class="rw-stage">' + body + '</div>'
				+ '<div class="rw-foot"><div class="rw-qprog" aria-hidden="true">' + segs + '</div>'
				+ '<div class="rw-nav"><button class="rw-btn-text" data-act="back">' + (S.step === 0 ? 'Abbrechen' : 'Zurück') + '</button>'
				+ '<button class="rw-btn-primary" data-act="next">' + (isLast ? 'Anfrage senden ' + ICO_SEND : 'Weiter ' + ICO_NEXT) + '</button></div></div>';
		}

		function screenSuccess(resp) {
			return '<div class="rw-stage"><div class="rw-success">'
				+ '<div class="fg-success-mark"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg></div>'
				+ '<div class="mk-eyebrow">Anfrage eingegangen</div>'
				+ '<h2 class="rw-success-h">Danke, deine Anfrage ist eingegangen.</h2>'
				+ '<p class="rw-success-p">Eine Bestätigung ist gerade per Mail an <strong>' + esc(resp.email || S.form.email || 'k. A.') + '</strong> unterwegs.</p>'
				+ '<div class="rw-care"><img class="rw-care-img" src="' + esc(CFG.juliusImg || '') + '" alt="' + esc(CONTACT.name) + '" width="64" height="64">'
				+ '<div class="rw-care-txt"><div class="rw-care-k">Um deine Anfrage kümmert sich</div>'
				+ '<div class="rw-care-n">' + esc(CONTACT.name) + '</div>'
				+ '<div class="rw-care-r">' + esc(CONTACT.role) + ' · Firmengolf</div>'
				+ (CFG.juliusEmail ? '<a class="rw-care-mail" href="mailto:' + esc(CFG.juliusEmail) + '">' + esc(CFG.juliusEmail) + '</a>' : '')
				+ '<div class="rw-care-note">Wir melden uns innerhalb eines Werktags persönlich bei dir.</div></div></div>'
				+ '<div class="rw-receipt-h">Zusammenfassung deiner Anfrage</div>'
				+ '<div class="rw-receipt">'
				+ '<div><span>Anlass</span><span>' + esc(resp.occasion || S.form.occasion || 'k. A.') + '</span></div>'
				+ '<div><span>Gruppe</span><span>' + esc((resp.size || S.form.size) + ' Personen') + '</span></div>'
				+ (S.form.date1 ? '<div><span>Wunschtermin</span><span>' + esc(S.form.date1 + (S.form.date2 ? ' +' : '')) + '</span></div>' : '')
				+ (S.form.region ? '<div><span>Region</span><span>' + esc(S.form.region) + '</span></div>' : '')
				+ (S.mode === 'full' && S.form.budget ? '<div><span>Budget-Rahmen</span><span>' + esc(S.form.budget) + '</span></div>' : '')
				+ '<div><span>Unternehmen</span><span>' + esc(resp.company || S.form.company || 'k. A.') + '</span></div>'
				+ '<div><span>Status</span><span><span class="ob-pill-status">In Bearbeitung</span></span></div>'
				+ '<div><span>Vorgangs-Nr.</span><span class="mono">' + esc(resp.ref || '') + '</span></div></div>'
				+ '<div class="rw-success-ctas"><button class="rw-btn-primary" data-act="close">Schließen</button></div>'
				+ '<div class="rw-confetti" aria-hidden="true"></div>'
				+ '</div></div>';
		}

		// Kurze Erfolgs-Animation: Konfetti von oben (nutzt die wz-conf-Keyframes).
		function dropConfetti() {
			var host = overlay && overlay.querySelector('.rw-confetti');
			if (!host || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
			var colors = ['#4279D1', '#C9B488', '#009E78', '#D2693E', '#6E9BDD', '#D8B26A'];
			for (var i = 0; i < 60; i++) {
				var c = document.createElement('span');
				c.className = 'wz-conf';
				c.style.left = (Math.random() * 100) + '%';
				c.style.background = colors[i % colors.length];
				c.style.animationDelay = (Math.random() * 0.9) + 's';
				c.style.animationDuration = (1.6 + Math.random() * 1.4) + 's';
				host.appendChild(c);
			}
		}

		var lastScreenKey = '';
		function render(successResp) {
			var body;
			if (S.phase === 'success') body = topBar() + screenSuccess(successResp || {});
			else if (S.phase === 'intro') body = topBar() + screenIntro();
			else if (S.mode === 'quick') body = topBar() + screenQuick();
			else body = topBar() + screenFull();
			overlay.innerHTML = body;
			// Mobile-Kompaktdarstellung der Schnellanfrage (CSS-Hook, siehe fge-frontend.css)
			overlay.classList.toggle('rw-mode-quick', S.phase === 'form' && S.mode === 'quick');
			wireA11y();
			/* Bei Schrittwechsel Fokus auf die Screen-Überschrift — nicht bei Re-Renders
			   im selben Schritt (Chip-Klick), sonst springt der Fokus unter der Maus weg. */
			var key = S.phase + ':' + S.mode + ':' + S.step;
			if (key !== lastScreenKey) {
				lastScreenKey = key;
				var h = overlay.querySelector('.rw-h, .rw-success-h');
				if (h) { h.setAttribute('tabindex', '-1'); h.focus(); }
				if (S.phase === 'success') { dropConfetti(); }
				// Weicher Schritt-Uebergang, nur bei echtem Screen-Wechsel (Chip-Klick
				// re-rendert mit gleichem key und bleibt ruhig).
				var mainEl = overlay.querySelector('.rw-main');
				if (mainEl) { mainEl.classList.add('rw-anim-in'); }
			}
		}

		/* Labels (.ind-flabel sind reine <span>s) programmatisch mit den Feldern verknüpfen. */
		function wireA11y() {
			overlay.querySelectorAll('.rw-field').forEach(function (fld) {
				var lab = fld.querySelector('.ind-flabel');
				if (!lab) return;
				var txt = (lab.childNodes[0] ? lab.childNodes[0].textContent : lab.textContent || '').trim();
				var req = !!lab.querySelector('.ind-required');
				fld.querySelectorAll('input[data-field], select[data-field], textarea[data-field]').forEach(function (inp) {
					if (txt && !inp.getAttribute('aria-label')) inp.setAttribute('aria-label', txt);
					if (req) inp.setAttribute('aria-required', 'true');
				});
			});
		}

		function collect() {
			// read editable fields currently in DOM into S.form
			overlay.querySelectorAll('[data-field]').forEach(function (inp) {
				var f = inp.getAttribute('data-field');
				if (inp.type === 'checkbox') { S.form[f] = inp.checked; return; }
				S.form[f] = inp.value;
			});
		}

		function valid() {
			if (S.mode === 'quick') {
				if (S.step === 0) return !!S.form.occasion;
				return S.form.firstName && S.form.email && S.form.occasion && S.form.consent;
			}
			if (S.step === 0) return !!S.form.occasion;
			if (S.step === 1) return checkDates() && parseInt(S.form.size, 10) > 0; // Pflichtfeld + Termin-Regeln geprüft
			// Vorname ist kein Pflichtfeld mehr (Julius, 2026-08-27).
			if (S.step === 4) return S.form.company && S.form.lastName && S.form.email && S.form.consent;
			return true;
		}

		function submit(btn) {
			collect();
			if (S.sending) return;
			S.sending = true;
			if (btn) { btn.disabled = true; btn.style.opacity = '.6'; }
			var f = S.form;
			var body = new URLSearchParams();
			body.set('action', 'fge_general_request');
			body.set('nonce', CFG.nonce || '');
			body.set('fge_ft', CFG.ft || '');
			body.set('fge_js', (CFG.ft || '').split('').reverse().join(''));
			body.set('occasion', f.occasion); body.set('goal', f.goal); body.set('size', f.size);
			body.set('region', f.region); body.set('place', f.place || ''); body.set('budget', f.budget); body.set('when', f.when);
			body.set('flex', f.flex); body.set('startzeit', f.startzeit); body.set('duration', f.duration); body.set('experience', f.experience);
			body.set('date1', f.date1); body.set('date2', f.date2); body.set('date3', f.date3);
			body.set('company', f.company); body.set('city', f.city);
			body.set('first_name', f.firstName); body.set('last_name', f.lastName);
			body.set('email', f.email); body.set('phone', f.phone);
			body.set('contact_pref', f.contactPref); body.set('diet', f.diet); body.set('notes', f.notes);
			body.set('consent', f.consent ? '1' : '');
			body.set('services', (f.services || []).join('||'));
			if (S.source) body.set('source', S.source);

			fetch(CFG.ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
				.then(function (r) { return r.json(); })
				.then(function (j) {
					S.sending = false;
					if (j && j.success) {
						window.dataLayer = window.dataLayer || [];
						window.dataLayer.push({ event: 'event_anfrage' });
						if (window.gtag && CFG.adsConv) { gtag('event', 'conversion', { send_to: CFG.adsConv }); }
						// Meta-Lead nur mit Einwilligung (fbq existiert erst nach Klaro-Zustimmung);
						// eventID kommt serverseitig aus der Anfrage (Conversions-API-Dedup).
						if (window.fbq && j.data && j.data.fb_event_id) { fbq('track', 'Lead', { content_name: 'Event-Anfrage Wizard' }, { eventID: j.data.fb_event_id }); }
						S.phase = 'success'; render(j.data);
					}
					else { alert((j && j.data && j.data.message) || 'Anfrage konnte nicht gesendet werden.'); if (btn) { btn.disabled = false; btn.style.opacity = ''; } }
				})
				.catch(function () {
					S.sending = false;
					alert('Verbindungsfehler. Bitte später erneut versuchen.');
					if (btn) { btn.disabled = false; btn.style.opacity = ''; }
				});
		}

		function onClick(e) {
			// Offenes Hilfe-Popover schließt bei jedem Klick außerhalb.
			var hp = overlay.querySelector('.rw-help-pop');
			if (hp && !hp.hidden && !e.target.closest('.rw-help')) {
				hp.hidden = true;
				var hb = overlay.querySelector('.rw-help-btn');
				if (hb) { hb.setAttribute('aria-expanded', 'false'); }
			}
			var t = e.target.closest('[data-act], [data-chip], [data-svc]');
			if (!t || !overlay.contains(t)) return;

			if (t.hasAttribute('data-chip')) {
				collect();
				var chipKey  = t.getAttribute('data-chip');
				var chipVal  = t.getAttribute('data-val');
				var chipPrev = S.form[chipKey];
				S.form[chipKey] = chipVal;
				// Anti-Flacker (Julius, 2026-08-20): Auswahl direkt im DOM umschalten statt
				// das komplette Formular neu zu bauen. Voll-Rerender NUR, wenn der Wert die
				// Screen-Struktur aendert (Freitext bei „Andere Events" in Schritt 1, quick und full).
				var structural = S.step === 0 && chipKey === 'occasion'
					&& ( chipVal === OCC_OTHER || chipPrev === OCC_OTHER );
				if (structural) { render(); return; }
				overlay.querySelectorAll('[data-chip="' + chipKey + '"]').forEach(function (b) {
					var on = b.getAttribute('data-val') === chipVal;
					b.classList.toggle('on', on);
					b.setAttribute('aria-pressed', on ? 'true' : 'false');
				});
				if (chipKey === 'occasion') {
					var ph = overlay.querySelector('.rw-photo'), nsrc = photoSrc();
					if (ph && nsrc) { ph.style.backgroundImage = 'url(\'' + nsrc + '\')'; }
				}
				t.classList.remove('just-toggled'); void t.offsetWidth; t.classList.add('just-toggled');
				return;
			}
			if (t.hasAttribute('data-svc')) {
				collect();
				var it = t.getAttribute('data-svc');
				var i = S.form.services.indexOf(it);
				if (i >= 0) S.form.services.splice(i, 1); else S.form.services.push(it);
				t.classList.toggle('on');
				t.setAttribute('aria-pressed', t.classList.contains('on') ? 'true' : 'false');
				t.classList.remove('just-toggled'); void t.offsetWidth; t.classList.add('just-toggled');
				return;
			}
			var act = t.getAttribute('data-act');
			if (act === 'help') {
				var pop = overlay.querySelector('.rw-help-pop');
				if (pop) { pop.hidden = !pop.hidden; t.setAttribute('aria-expanded', pop.hidden ? 'false' : 'true'); }
				return;
			}
			if (act === 'close') { close(); return; }
			if (act === 'toggle-mode') { collect(); S.mode = (S.mode === 'quick' ? 'full' : 'quick'); S.step = 0; render(); return; }
			if (act === 'to-full') { collect(); S.mode = 'full'; S.step = 0; render(); return; }
			if (act === 'intro-start') { S.phase = 'form'; render(); return; }
			if (act === 'size-dec' || act === 'size-inc') {
				collect();
				var cur = parseInt(S.form.size, 10) || 20;
				// Einzelschritte (Julius, 2026-08-20) und NUR das Zahlenfeld aktualisieren:
				// der fruehere Voll-Rerender liess bei jedem Klick das Formular flackern.
				S.form.size = String(Math.max(1, Math.min(999, cur + (act === 'size-inc' ? 1 : -1))));
				var sizeInp = overlay.querySelector('.rw-stepper-val');
				if (sizeInp) { sizeInp.value = S.form.size; } else { render(); }
				return;
			}
			if (act === 'back') { collect(); if (S.step === 0) { close(); } else { S.step--; render(); } return; }
			if (act === 'next') {
				collect();
				if (!valid()) { flashInvalid(); return; }
				if (S.mode === 'quick') { S.step = 1; render(); return; }
				if (S.step === FULL_STEPS.length - 1) { submit(t); } else { S.step++; render(); }
				return;
			}
			if (act === 'submit') { collect(); if (!valid()) { flashInvalid(); return; } submit(t); return; }
		}

		/* Welche Pflichtangaben fehlen? (spiegelt valid()) — auch für Chips/Consent,
		   die kein markierbares Input haben und sonst stumm scheitern würden. */
		function missingMsg() {
			var m = [];
			var add = function (cond, label) { if (!cond) m.push(label); };
			if (S.mode === 'quick') {
				if (S.step === 0) {
					add(S.form.occasion, 'Anlass auswählen');
				} else {
					add(S.form.firstName, 'Vorname');
					add(S.form.email, 'E-Mail');
					add(S.form.consent, 'Zustimmung zur Datenverarbeitung');
				}
			} else if (S.step === 0) {
				add(S.form.occasion, 'Anlass auswählen');
			} else {
				add(S.form.company, 'Firma');
				add(S.form.lastName, 'Nachname');
				add(S.form.email, 'E-Mail');
				add(S.form.consent, 'Zustimmung zur Datenverarbeitung');
			}
			return m.length ? 'Es fehlt noch: ' + m.join(', ') + '.' : 'Bitte fülle die markierten Pflichtfelder aus.';
		}

		function flashInvalid() {
			overlay.querySelectorAll('[data-field]').forEach(function (inp) {
				if (inp.required && !inp.value) { inp.classList.add('fg-input-err'); inp.setAttribute('aria-invalid', 'true'); }
			});
			var box = overlay.querySelector('.rw-error');
			if (!box) {
				box = document.createElement('div');
				box.className = 'rw-error';
				box.setAttribute('role', 'alert');
				var foot = overlay.querySelector('.rw-foot');
				if (foot && foot.parentNode) { foot.parentNode.insertBefore(box, foot); } else { overlay.appendChild(box); }
			}
			box.textContent = missingMsg();
			var first = overlay.querySelector('.fg-input-err');
			if (first) first.focus();
		}

		var lastFocus = null;
		var lockY = 0;
		function focusables() {
			if (!overlay) { return []; }
			return Array.prototype.filter.call(
				overlay.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]):not([type=hidden]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])'),
				function (el) { return el.offsetParent !== null; }
			);
		}
		function onKey(e) {
			if (e.key === 'Escape') { close(); return; }
			if (e.key !== 'Tab') { return; }
			var f = focusables();
			if (!f.length) { return; }
			var first = f[0], last = f[f.length - 1];
			if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
			else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
		}

		function open(mode, preset, intro, source) {
			lastFocus = document.activeElement;
			if (!overlay) {
				overlay = el('div', 'rw-overlay');
				overlay.setAttribute('role', 'dialog');
				overlay.setAttribute('aria-modal', 'true');
				overlay.setAttribute('aria-label', 'Event anfragen');
				document.body.appendChild(overlay);
				overlay.addEventListener('click', onClick);
			/* Wunschtermine prüfen: iOS Safari erzwingt das min-Attribut der
			   Datumsfelder NICHT (Android schon), und Duplikate prüfte niemand
			   (Julius, 28.08.). Zu frühe oder doppelte Termine werden geleert,
			   mit Hinweis unter der Termin-Zeile. */
			overlay.addEventListener('change', function (e) {
				if (!e.target.matches('input[type="date"][data-field]')) { return; }
				checkDates();
			});
			/* Ort-Autovervollständigung (Kontakt-Schritt) über die eigene Orts-Datenbank
			   (fge_geo_suggest), consentfrei ohne Drittanbieter (Julius, 2026-08-27). */
			var cityT = null;
			overlay.addEventListener('input', function (e) {
				if (!e.target.matches('input[data-field="city"]')) { return; }
				var q = e.target.value.trim();
				clearTimeout(cityT);
				if (q.length < 2 || !CFG.ajaxUrl) { return; }
				cityT = setTimeout(function () {
					fetch(CFG.ajaxUrl + '?action=fge_geo_suggest&q=' + encodeURIComponent(q))
						.then(function (r) { return r.json(); })
						.then(function (res) {
							var dl = overlay.querySelector('#rw-city-list');
							if (!dl || !res || !res.success) { return; }
							dl.innerHTML = '';
							(res.data || []).forEach(function (s) {
								var o = document.createElement('option');
								o.value = s.label;
								dl.appendChild(o);
							});
						}).catch(function () {});
				}, 220);
			});
			}
			/* Intro (Begrüßung „Toll, ihr plant ein X …") jetzt auch vor der Schnellanfrage
			   erlaubt: Formate ohne fertige Events landen so persönlich begrüßt im Quick-Flow
			   (Julius, 2026-08-27). */
			S = { mode: mode || 'full', phase: intro ? 'intro' : 'form', step: 0, sending: false, source: source || '', form: blank(preset) };
			/* Positionsfeste Sperre wie beim Event-Modal: die Klassen-Sperre
			   (overflow hidden) überrollt iOS beim Input-Fokus und scrollt die
			   Seite hinter dem Wizard — der Wizard hing dann als Band mitten im
			   Bildschirm, Seite oben und unten sichtbar (Julius-Video 3, 28.08.).
			   position:fixed am body friert die Seite exakt ein, beim Schließen
			   wird die Scrollposition wiederhergestellt. */
			lockY = window.scrollY || window.pageYOffset || 0;
			document.documentElement.classList.add('fg-drawer-lock');
			var bs = document.body.style;
			bs.position = 'fixed'; bs.top = (-lockY) + 'px'; bs.left = '0'; bs.right = '0'; bs.width = '100%';
			window.addEventListener('keydown', onKey);
			overlay.hidden = false;
			render();
			var f = focusables();
			if (f.length) { f[0].focus(); }
		}
		function close() {
			if (overlay) overlay.hidden = true;
			document.documentElement.classList.remove('fg-drawer-lock');
			var bs = document.body.style;
			bs.position = ''; bs.top = ''; bs.left = ''; bs.right = ''; bs.width = '';
			window.scrollTo(0, lockY);
			window.removeEventListener('keydown', onKey);
			if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
		}

		return { open: open, close: close };
	})();

	/* =================================================================
	 * Wire up triggers + init
	 * ================================================================= */
	function ready(fn) {
		if (document.readyState !== 'loading') fn();
		else document.addEventListener('DOMContentLoaded', fn);
	}
	// Anlässe, die per ?anlass= vorgewählt werden dürfen: die Standardliste des
	// Wizards plus die Formate der Landingpages (fge_format_occasion() in PHP).
	var ALLOWED_OCCASIONS = [
		'Golf-Teamevent', 'After-Work Golf', 'Golf & Workshop', 'Firmen-Golfturnier',
		'Golf-Kundenevent', 'Nachtgolf-Event', 'Andere Events', 'Indoor Weihnachtsfeier',
		'Platzreife', 'Incentive-Reise', 'Indoor-Golf-Event', 'Sommerfest', 'Weihnachtsfeier'
	];

	ready(function () {
		initCalc();
		// Deep-Link: CTAs anderer Seiten springen direkt in den Wizard
		// (?anfrage=quick -> 30-Sekunden-Schnellanfrage, ?anfrage=full -> ausfuehrliches Formular).
		var deepParams = new URLSearchParams(window.location.search);
		var deepMode = deepParams.get('anfrage');
		if (deepMode === 'quick' || deepMode === 'full') {
			// ?anlass=Teamevent o. ä. wählt den Anlass vor (z. B. CTA der Format-Landingpages).
			// Nur bekannte Anlässe übernehmen: ein präparierter Link konnte sonst
			// beliebigen Fremdtext als aktive Kachel und bis in die gespeicherte
			// Anfrage tragen (Audit 2026-08-12).
			var deepOccRaw = deepParams.get('anlass');
			var deepOcc = (deepOccRaw && ALLOWED_OCCASIONS.indexOf(deepOccRaw) >= 0) ? deepOccRaw : null;
			// ?intro=1 zeigt vorab den Begrüßungs-Screen („Toll, ihr plant ein X …"),
			// z. B. für Formate ohne fertige Events (Home-Slider, Julius 27.08.).
			Wizard.open(deepMode, deepOcc ? { occasion: deepOcc } : null, deepParams.get('intro') === '1', 'deeplink');
		}
		document.querySelectorAll('[data-rw-open]').forEach(function (btn) {
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				var mode = btn.getAttribute('data-rw-open') || 'full';
				var presetRaw = btn.getAttribute('data-rw-preset');
				var preset = null;
				if (presetRaw) { try { preset = JSON.parse(presetRaw); } catch (err) { preset = null; } }
				var intro = btn.hasAttribute('data-rw-intro');
				var source = btn.getAttribute('data-rw-source') || '';
				Wizard.open(mode, preset, intro, source);
			});
		});
		// Anfrage-Deeplinks (?anfrage=quick|full) abfangen, wo dieses Skript laedt
		// (z. B. Stadt-Landingpages): Wizard als Overlay AUF der Seite oeffnen statt
		// zur Anfrage-Seite zu springen. Nach dem Schliessen bleibt man, wo man war.
		// Modifier-Klicks (neuer Tab etc.) und Downloads bleiben unangetastet.
		document.addEventListener('click', function (e) {
			if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
			var a = e.target.closest ? e.target.closest('a[href*="anfrage="]') : null;
			if (!a) return;
			var mode, occ;
			try {
				var u = new URL(a.href, window.location.href);
				mode = u.searchParams.get('anfrage');
				occ = u.searchParams.get('anlass');
			} catch (err) { return; }
			if (mode !== 'quick' && mode !== 'full') return;
			e.preventDefault();
			var preset = (occ && ALLOWED_OCCASIONS.indexOf(occ) >= 0) ? { occasion: occ } : null;
			var wantIntro = false;
			try { wantIntro = new URL(a.href, window.location.href).searchParams.get('intro') === '1'; } catch (err2) {}
			Wizard.open(mode, preset, wantIntro, 'general_landingpage');
		});
	});

	window.FGEWizard = Wizard;
})();
