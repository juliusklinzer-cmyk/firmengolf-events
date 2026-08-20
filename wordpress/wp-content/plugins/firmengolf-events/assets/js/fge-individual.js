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
			Wizard.open('full', {
				occasion: res.type.wiz || 'Teamevent',
				size: String(state.participants),
				services: svcWiz,
				budget: lo.toLocaleString('de-DE') + ' bis ' + hi.toLocaleString('de-DE') + ' €',
				notes: 'Über den Budget-Rechner geschätzt: ' + res.type.label + ', ' + state.participants
					+ ' Personen, Preisniveau ' + state.range + ', Richtwert ca. ' + fmt(res.total) + ' €.'
			});
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
		var SERVICE_GROUPS = [
			{ group: 'Sport & Programm', items: ['Golflehrer / Coaching', 'Schnupperkurs', 'Platzreife', 'Firmenturnier', 'Putting-Challenge'] },
			{ group: 'Gastronomie', items: ['Frühstück', 'Mittagessen', 'Abendessen', 'Grill', 'Bar & Drinks', 'Einfach', 'Gehoben'] },
			{ group: 'Logistik', items: ['Meetingraum', 'Shuttle / Transport', 'Übernachtung', 'Schlechtwetter-Alternative'] },
			{ group: 'Technik & Show', items: ['Bildschirme', 'Musik und DJ', 'Bühne mit Licht und Ton', 'Band', 'Flutlicht und Nacht-Event'] },
			{ group: 'Foto & Content', items: ['Fotograf', 'Content-Team für Social'] },
			{ group: 'Branding & Merch', items: ['Branding & Banner', 'Individuelle Artikel', 'Pokale & Preise'] }
		];
		/* Icon je Leistung (Kachel-Design wie in der Partnerplatz-Anlage). */
		function svcIcon(it) {
			var svg = function (p) { return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + p + '</svg>'; };
			var s = it.toLowerCase();
			if (s.indexOf('golflehrer') >= 0)                          return svg('<path d="M6 21V4l11 3.5L6 11"/><path d="M6 21h6"/>');
			if (s.indexOf('schnupper') >= 0)                           return svg('<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/>');
			if (s.indexOf('platzreife') >= 0)                          return svg('<path d="M4 12l5 5L20 6"/><path d="M4 19h16"/>');
			if (s.indexOf('turnier') >= 0 || s.indexOf('pokale') >= 0) return svg('<path d="M8 21h8M12 17v4"/><path d="M7 4h10v4a5 5 0 0 1-10 0z"/><path d="M7 6H4a3 3 0 0 0 3 4M17 6h3a3 3 0 0 1-3 4"/>');
			if (s.indexOf('putting') >= 0)                             return svg('<circle cx="12" cy="17" r="1.4"/><path d="M12 3v10"/><path d="M12 3l6 2-6 2"/>');
			if (s.indexOf('frühstück') >= 0)                           return svg('<path d="M4 8h12v6a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4z"/><path d="M16 9h2a2.5 2.5 0 0 1 0 5h-2"/><path d="M7 3v2M11 3v2"/>');
			if (s.indexOf('mittagessen') >= 0 || s === 'einfach')      return svg('<path d="M5 3v8M8 3v8M6.5 11v10"/><path d="M15 3c-2 2-2 6 0 8v10"/><path d="M15 3h3v8h-3"/>');
			if (s.indexOf('abendessen') >= 0 || s === 'gehoben')       return svg('<path d="M12 4a8 8 0 0 1 8 8H4a8 8 0 0 1 8-8z"/><path d="M3 15h18"/><path d="M12 2v2"/>');
			if (s.indexOf('grill') >= 0)                               return svg('<path d="M4 9h16a8 8 0 0 1-16 0z"/><path d="M8 17l-2 4M16 17l2 4M12 17v4"/><path d="M9 4c0 1-1 1-1 2M13 4c0 1-1 1-1 2M17 4c0 1-1 1-1 2"/>');
			if (s.indexOf('bar') >= 0)                                 return svg('<path d="M4 4h16l-8 9z"/><path d="M12 13v6M8 21h8"/>');
			if (s.indexOf('bildschirm') >= 0)                          return svg('<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 21h8M12 16v5"/>');
			if (s.indexOf('musik') >= 0 || s === 'band')               return svg('<path d="M9 18V6l10-2v11"/><circle cx="6.5" cy="18" r="2.5"/><circle cx="16.5" cy="15" r="2.5"/>');
			if (s.indexOf('bühne') >= 0)                               return svg('<path d="M12 3l4 6H8z"/><path d="M4 21l4-7M20 21l-4-7M12 14v7"/>');
			if (s.indexOf('flutlicht') >= 0 || s.indexOf('nacht') >= 0) return svg('<path d="M21 13A8 8 0 1 1 11 3a6.5 6.5 0 0 0 10 10z"/>');
			if (s.indexOf('fotograf') >= 0)                            return svg('<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7l1.5-3h5L16 7"/><circle cx="12" cy="13" r="3.5"/>');
			if (s.indexOf('content') >= 0)                             return svg('<rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/>');
			if (s.indexOf('branding') >= 0)                            return svg('<path d="M20 12l-8 8-9-9V4h7z"/><circle cx="7.5" cy="7.5" r="1.3"/>');
			if (s.indexOf('artikel') >= 0)                             return svg('<rect x="4" y="8" width="16" height="12" rx="1.5"/><path d="M4 12h16M12 8v12"/><path d="M12 8c-3 0-4-4-1.5-4S12 8 12 8zM12 8c3 0 4-4 1.5-4S12 8 12 8z"/>');
			if (s.indexOf('meetingraum') >= 0)                         return svg('<rect x="3" y="4" width="18" height="12" rx="1.5"/><path d="M12 16v4M8 20h8"/><path d="M7 8h6M7 11h4"/>');
			if (s.indexOf('shuttle') >= 0)                             return svg('<rect x="3" y="5" width="18" height="11" rx="2"/><path d="M3 11h18"/><circle cx="7.5" cy="18.5" r="1.6"/><circle cx="16.5" cy="18.5" r="1.6"/>');
			if (s.indexOf('übernachtung') >= 0)                        return svg('<path d="M3 18v-8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v8"/><path d="M3 18h18M6 8V6h5v2"/>');
			if (s.indexOf('schlechtwetter') >= 0)                      return svg('<path d="M12 3a8 8 0 0 1 8 8H4a8 8 0 0 1 8-8z"/><path d="M12 11v7a2 2 0 0 1-4 0"/>');
			return svg('<path d="M12 3l2.2 5.4L20 9l-4.4 3.8L17 19l-5-3-5 3 1.4-6.2L4 9l5.8-.6z"/>');
		}
		var BUDGETS = [
			{ v: 'Unter 5.000 €', h: 'Kleinere Halbtags-Formate' },
			{ v: '5.000 bis 10.000 €', h: 'Eintägig für 20 bis 40 Gäste' },
			{ v: '10.000 bis 20.000 €', h: 'Premium-Eintages-Events' },
			{ v: '20.000 bis 50.000 €', h: 'Mehrtägig oder größere Gruppen' },
			{ v: 'Über 50.000 €', h: 'Incentive-Reisen, Großformate' },
			{ v: 'Noch unklar', h: 'Wir gehen es gemeinsam durch' }
		];
		var CONTACT = { name: 'Julius Klinzer', role: 'Gründer' };

		var overlay = null, S = null;

		function blank(preset) {
			var f = {
				occasion: '', goal: '', size: '20', region: '', place: '', experience: '', startzeit: '',
				budget: '10.000 bis 20.000 €', when: '', flex: 'flexibel', duration: '',
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

		// Anlass-Auswahl: Reihenfolge + Icon-Kacheln (einheitlich mit den Leistungs-Kacheln).
		var OCCASIONS = ['Teamevent', 'After-Work Golf', 'Workshop', 'Firmenturnier', 'Offsite', 'Kundenevent', 'Nacht-Event', 'Etwas anderes'];
		var OCC_ICONS = {
			'Teamevent': '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
			'After-Work Golf': '<path d="M3 18h18"/><path d="M7 18a5 5 0 0 1 10 0"/><path d="M12 4v3M5.2 7.2l1.6 1.6M18.8 7.2l-1.6 1.6M3 12h2M19 12h2"/>',
			'Workshop': '<rect x="3" y="4" width="18" height="12" rx="1"/><path d="M12 16v4M8 20h8"/><path d="M7 8h10M7 11h6"/>',
			'Firmenturnier': '<path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0z"/><path d="M7 6H4v2a3 3 0 0 0 3 3M17 6h3v2a3 3 0 0 1-3 3"/>',
			'Offsite': '<path d="M3 20l6.5-11 4 6 2-3L21 20z"/>',
			'Kundenevent': '<circle cx="12" cy="8" r="3"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/><path d="M16 4h4v4"/>',
			'Nacht-Event': '<path d="M20 13.5A8 8 0 1 1 10.5 4a6.2 6.2 0 0 0 9.5 9.5z"/>',
			'Etwas anderes': '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>'
		};
		function occCards() {
			// Preset-Anlässe außerhalb der Standardliste (z. B. Sommerfest von den
			// Themen-Kacheln) als zusätzliche, gewählte Kachel zeigen.
			var list = OCCASIONS.slice();
			if (S.form.occasion && list.indexOf(S.form.occasion) < 0) list.push(S.form.occasion);
			return '<div class="ind-cards rw-occ-cards">' + list.map(function (o) {
				var on = S.form.occasion === o;
				return '<button type="button" class="ind-card' + (on ? ' on' : '') + '" aria-pressed="' + (on ? 'true' : 'false')
					+ '" data-chip="occasion" data-val="' + esc(o) + '"><span class="ind-card-ico"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (OCC_ICONS[o] || OCC_ICONS['Etwas anderes']) + '</svg></span><span class="ind-card-l">' + esc(o) + '</span></button>';
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
			return '<div class="rw-field">' + label('Konkreter Platzwunsch?', false, 'Optional, tippen zum Suchen')
				+ '<input class="fg-input" data-field="place" list="rw-place-list" value="' + esc(S.form.place) + '" placeholder="Golfplatz oder Ort eintippen …" autocomplete="off">'
				+ '<datalist id="rw-place-list">' + opts + '</datalist></div>';
		}

		function topBar() {
			var shortcut = (S.phase === 'form' && S.mode !== 'success') ?
				'<button class="rw-shortcut" data-act="toggle-mode">'
				+ (S.mode === 'quick' ? 'Ausführliche Anfrage' : 'Schnell-Anfrage in 30 Sek.') + '</button>' : '';
			var logo = CFG.logo ? '<img src="' + esc(CFG.logo) + '" alt="Firmengolf" height="24">' : '';
			return '<header class="rw-top"><div class="rw-top-brand">' + logo + '<span class="rw-top-title">Event anfragen</span></div>'
				+ '<div class="rw-top-actions">' + shortcut
				+ '<button class="rw-close" data-act="close" aria-label="Schließen"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div></header>';
		}

		// Foto-Panel (Driver am Abschlag, wie im Partner-Onboarding) neben dem Einstieg.
		function photoPanel() {
			var src = CFG.introImg || '';
			if (!src) return '';
			// Trust-Karte auf dem Foto: echter Ansprechpartner + Werktag-Versprechen
			// statt anonymer Bildflaeche (Testvariante 2026-08-20).
			var trust = CFG.juliusImg
				? '<div class="rw-photo-trust"><img src="' + esc(CFG.juliusImg) + '" alt="" loading="lazy">'
					+ '<div><b>Julius Klinzer</b><span>Gründer und euer Ansprechpartner. Ihr hört innerhalb eines Werktags von mir.</span></div></div>'
				: '';
			return '<div class="rw-photo" role="img" aria-label="Golfer am Abschlag" style="background-image:url(\'' + esc(src) + '\')">' + trust + '</div>';
		}

		function screenIntro() {
			var occ = S.form.occasion || 'Firmenevent';
			return '<div class="rw-stage"><div class="rw-screen rw-has-photo rw-intro"><div class="rw-main">'
				+ '<div class="rw-eyebrow">Schön, dass du da bist</div>'
				+ '<h2 class="rw-h">Toll, ihr plant ein <span class="mk-italic">' + esc(occ) + '</span> für euer Team.</h2>'
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

		/* Fortschritts-Punkte der Schnell-Anfrage (2 Schritte). */
		function quickDots() {
			return '<span class="rw-dots" aria-hidden="true"><i class="' + (S.step === 0 ? 'on' : '') + '"></i><i class="' + (S.step === 1 ? 'on' : '') + '"></i></span>';
		}

		/* Schnell-Anfrage in ZWEI leichten Schritten (Julius, 2026-08-20):
		   1) Anlass + Teilnehmerzahl  2) Kontakt + Nachricht + Consent.
		   Der Weg zur ausfuehrlichen Anfrage lebt oben rechts in der Kopfzeile,
		   der Fuss traegt nur noch Fortschritt und Aktion. */
		function screenQuick() {
			if (S.step === 0) {
				return '<div class="rw-stage"><div class="rw-screen rw-has-photo"><div class="rw-main">'
					+ '<h2 class="rw-h rw-h--quick">In 30 Sekunden angefragt.</h2>'
					+ '<p class="rw-lead">Wählt den Anlass, den Rest klären wir persönlich. Kostenlos und unverbindlich.</p>'
					+ '<div class="rw-form">'
					+ '<div class="rw-field">' + label('Anlass', true) + occCards() + '</div>'
					+ '<div class="rw-field">' + label('Teilnehmerzahl') + sizeStepper() + '</div>'
					+ '</div></div>' + photoPanel() + '</div></div>'
					+ '<div class="rw-foot"><div class="rw-nav rw-nav--quick">' + quickDots()
					+ '<button class="rw-btn-primary" data-act="next">Weiter ' + ICO_NEXT + '</button></div></div>';
			}
			var recap = esc(S.form.occasion || 'Event') + ' · ' + esc(S.form.size || '20') + ' Personen';
			return '<div class="rw-stage"><div class="rw-screen rw-has-photo"><div class="rw-main">'
				+ '<button type="button" class="rw-recap" data-act="back" aria-label="Zurück zu Anlass und Teilnehmerzahl">'
				+ '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>'
				+ recap + '</button>'
				+ '<h2 class="rw-h rw-h--quick">Noch kurz zu euch.</h2>'
				+ '<p class="rw-lead">Innerhalb eines Werktags habt ihr konkrete Vorschläge im Postfach.</p>'
				+ '<div class="rw-form">'
				+ '<div class="rw-row"><div class="rw-field">' + label('Vor- & Nachname', true) + input('firstName', 'required', 'Vor- und Nachname') + '</div>'
				+ '<div class="rw-field">' + label('E-Mail', true) + input('email', 'type="email" required', 'name@firma.de') + '</div></div>'
				+ '<div class="rw-field">' + label('Firma') + input('company', '', 'Musterfirma GmbH') + '</div>'
				+ '<div class="rw-field">' + label('Was habt ihr vor?') + '<textarea class="fg-input" data-field="notes" rows="3" placeholder="Ein, zwei Sätze zu Ziel, Stimmung, Wünschen.">' + esc(S.form.notes) + '</textarea></div>'
				+ '<label class="ind-consent"><input type="checkbox" data-field="consent"' + (S.form.consent ? ' checked' : '') + '><span>Ich stimme der Verarbeitung meiner Daten zur Bearbeitung der Anfrage gemäß <a href="' + esc(CFG.privacyUrl || '/datenschutz/') + '" target="_blank" rel="noopener">Datenschutzerklärung</a> zu.</span></label>'
				+ '</div></div>' + photoPanel() + '</div></div>'
				+ '<div class="rw-foot"><div class="rw-nav rw-nav--quick"><button class="rw-btn-text" data-act="back">Zurück</button>' + quickDots()
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
					/* „Was wollt ihr erreichen?" nur bei „Etwas anderes" (Julius, 2026-07-06) */
					+ (S.form.occasion === 'Etwas anderes'
						? '<div class="rw-field">' + label('Was wollt ihr erreichen?', false, 'Ein Satz reicht')
							+ input('goal', '', 'z.B. Team zusammenbringen · Kunden begeistern · Mitarbeitende belohnen') + '</div>'
						: '')
					+ '</div>';
			}
			if (step === 1) {
				return '<div class="rw-eyebrow">Schritt 2 · ' + FULL_STEPS[1] + '</div>'
					+ '<h2 class="rw-h">Wann, wo und mit wie vielen?</h2>'
					+ '<p class="rw-lead">Genau müssen die Angaben jetzt nicht sein.</p>'
					+ '<div class="rw-form"><div class="rw-field">' + label('Teilnehmerzahl', true) + sizeStepper() + '</div>'
					+ '<div class="rw-field">' + label('Bis zu drei Wunschtermine', false, 'Optional')
					+ '<div class="rw-row rw-row-3">' + input('date1', 'type="date" min="' + MIN_DATE + '"', '1. Termin') + input('date2', 'type="date" min="' + MIN_DATE + '"', '2. Termin') + input('date3', 'type="date" min="' + MIN_DATE + '"', '3. Termin') + '</div></div>'
					+ '<div class="rw-field">' + label('Wie flexibel beim Datum?') + chips('flex', ['fix', '± 1 Woche', 'flexibel', 'noch offen']) + '</div>'
					+ '<div class="rw-field">' + label('Gewünschter Startzeitpunkt', false, 'Optional') + chips('startzeit', ['Morgens', 'Vormittags', 'Mittags', 'After-Work', 'Noch offen']) + '</div>'
					+ '<div class="rw-field">' + label('Golf-Erfahrung im Team', false, 'Optional') + chips('experience', ['Überwiegend Anfänger', 'Gemischt', 'Erfahrene Golfer', 'Weiß noch nicht']) + '</div>'
					+ '<div class="rw-field">' + label('Wo soll euer Event stattfinden?')
					+ chips('region', ['In der Nähe', 'Mitte Deutschlands', 'In den Alpen', 'Am Meer / Sylt', 'In Europa', 'An einem besonderen Ort', 'Noch offen'])
					+ '</div>' + placeField() + '</div>';
			}
			if (step === 2) {
				/* Icon-Kacheln wie in der Partnerplatz-Anlage (Julius, 2026-07-06) statt Text-Toggles. */
				var groups = SERVICE_GROUPS.map(function (g) {
					return '<div class="ind-svc-pick-group"><div class="ind-svc-pick-h">' + esc(g.group) + '</div><div class="ind-cards">'
						+ g.items.map(function (it) {
							var on = S.form.services.indexOf(it) >= 0;
							return '<button type="button" class="ind-card' + (on ? ' on' : '') + '" aria-pressed="' + (on ? 'true' : 'false')
								+ '" data-svc="' + esc(it) + '"><span class="ind-card-ico">' + svcIcon(it) + '</span><span class="ind-card-l">' + esc(it) + '</span></button>';
						}).join('') + '</div></div>';
				}).join('');
				return '<div class="rw-eyebrow">Schritt 3 · ' + FULL_STEPS[2] + '</div>'
					+ '<h2 class="rw-h">Was soll dabei sein?</h2>'
					+ '<p class="rw-lead">Sag uns deine Vorstellungen, wir besprechen anschließend alles mit dir und bereiten das Event entsprechend vor.</p>'
					+ '<div class="rw-form"><div class="ind-svc-pick">' + groups + '</div></div>';
			}
			if (step === 3) {
				var cards = BUDGETS.map(function (o) {
					var on = S.form.budget === o.v;
					return '<button type="button" class="ind-budget-card' + (on ? ' on' : '') + '" aria-pressed="' + (on ? 'true' : 'false')
						+ '" data-chip="budget" data-val="' + esc(o.v) + '"><span class="ind-budget-v">' + esc(o.v)
						+ '</span><span class="ind-budget-h">' + esc(o.h) + '</span></button>';
				}).join('');
				return '<div class="rw-eyebrow">Schritt 4 · ' + FULL_STEPS[3] + '</div>'
					+ '<h2 class="rw-h">Was wäre euer Budget-Rahmen?</h2>'
					+ '<p class="rw-lead">Ein Richtwert genügt, so kommen wir direkt mit passenden Angeboten auf euch zu.</p>'
					+ '<div class="rw-form"><div class="ind-budget-grid">' + cards + '</div>'
					+ '<div class="rw-field" style="margin-top:8px;">' + label('Verpflegung & Diät', false, 'Optional') + input('diet', '', 'z.B. 5× vegetarisch, 1× vegan, Nussallergie') + '</div>'
					+ '<div class="rw-field" style="margin-top:8px;">' + label('Was ist euch wichtig?')
					+ '<textarea class="fg-input" data-field="notes" rows="4" placeholder="Stimmung, Hintergrund, besondere Wünsche, alles was hilft.">' + esc(S.form.notes) + '</textarea></div></div>';
			}
			// step 4
			return '<div class="rw-eyebrow">Schritt 5 · ' + FULL_STEPS[4] + '</div>'
				+ '<h2 class="rw-h">Wer seid ihr, und wie erreichen wir dich?</h2>'
				+ '<p class="rw-lead">Letzter Schritt. Danach melden wir uns innerhalb eines Werktags.</p>'
				+ '<div class="rw-form"><div class="rw-row"><div class="rw-field">' + label('Unternehmen', true) + input('company', 'required', 'Musterfirma GmbH') + '</div>'
				+ '<div class="rw-field">' + label('Ort') + input('city', '', 'München') + '</div></div>'
				+ '<div class="rw-row"><div class="rw-field">' + label('Vorname', true) + input('firstName', 'required', 'Vorname') + '</div>'
				+ '<div class="rw-field">' + label('Nachname', true) + input('lastName', 'required', 'Nachname') + '</div></div>'
				+ '<div class="rw-row"><div class="rw-field">' + label('E-Mail', true) + input('email', 'type="email" required', 'name@firma.de') + '</div>'
				+ '<div class="rw-field">' + label('Telefon') + input('phone', 'type="tel"', '+49 …') + '</div></div>'
				+ '<div class="rw-field">' + label('Bevorzugte Kontaktart') + chips('contactPref', ['E-Mail', 'Telefon', 'Egal']) + '</div>'
				+ '<label class="ind-consent"><input type="checkbox" data-field="consent"' + (S.form.consent ? ' checked' : '') + '>'
				+ '<span>Ich stimme der Verarbeitung meiner Daten zur Bearbeitung der Anfrage gemäß <a href="' + esc(CFG.privacyUrl || '/datenschutz/') + '" target="_blank" rel="noopener">Datenschutzerklärung</a> zu.</span></label></div>';
		}

		function screenFull() {
			var segs = FULL_STEPS.map(function (lbl, i) {
				var state = i < S.step ? ' done' : (i === S.step ? ' on' : '');
				return '<div class="rw-seg' + state + '">'
					+ '<div class="rw-seg-bar"><div class="rw-seg-fill" style="width:' + (i <= S.step ? 100 : 0) + '%"></div></div>'
					+ '<span class="rw-seg-label">' + esc(lbl) + '</span></div>';
			}).join('');
			var isLast = S.step === FULL_STEPS.length - 1;
			var body   = S.step === 0
				? '<div class="rw-screen rw-has-photo"><div class="rw-main">' + fullStepBody(0) + '</div>' + photoPanel() + '</div>'
				: '<div class="rw-screen">' + fullStepBody(S.step) + '</div>';
			return '<div class="rw-stage">' + body + '</div>'
				+ '<div class="rw-foot"><div class="rw-progress">' + segs + '</div>'
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
			if (S.step === 1) return parseInt(S.form.size, 10) > 0; // Pflichtfeld jetzt auch geprüft (Kern-Audit N1)
			if (S.step === 4) return S.form.company && S.form.firstName && S.form.lastName && S.form.email && S.form.consent;
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
				// Screen-Struktur aendert (Ziel-Feld bei „Etwas anderes" im Full-Schritt 1).
				var structural = S.mode === 'full' && S.step === 0 && chipKey === 'occasion'
					&& ( chipVal === 'Etwas anderes' || chipPrev === 'Etwas anderes' );
				if (structural) { render(); return; }
				overlay.querySelectorAll('[data-chip="' + chipKey + '"]').forEach(function (b) {
					var on = b.getAttribute('data-val') === chipVal;
					b.classList.toggle('on', on);
					b.setAttribute('aria-pressed', on ? 'true' : 'false');
				});
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
				add(S.form.firstName, 'Vorname');
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
			}
			S = { mode: mode || 'full', phase: (intro && mode !== 'quick') ? 'intro' : 'form', step: 0, sending: false, source: source || '', form: blank(preset) };
			document.body.style.overflow = 'hidden';
			window.addEventListener('keydown', onKey);
			overlay.hidden = false;
			render();
			var f = focusables();
			if (f.length) { f[0].focus(); }
		}
		function close() {
			if (overlay) overlay.hidden = true;
			document.body.style.overflow = '';
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
		'Teamevent', 'After-Work Golf', 'Workshop', 'Firmenturnier', 'Offsite',
		'Kundenevent', 'Nacht-Event', 'Etwas anderes', 'Platzreife', 'Incentive-Reise'
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
			Wizard.open(deepMode, deepOcc ? { occasion: deepOcc } : null, false, 'deeplink');
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
			Wizard.open(mode, preset, false, 'general_landingpage');
		});
	});

	window.FGEWizard = Wizard;
})();
