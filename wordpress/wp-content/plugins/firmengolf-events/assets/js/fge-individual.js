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

	// Distinkte, markennahe Farbpalette — jede gewählte Leistung bekommt nach ihrer
	// Position in der Typ-Liste eine eigene Farbe (innerhalb eines Events alle verschieden).
	var SVC_PALETTE = [
		'#2C5036', '#C9B488', '#C77D4A', '#5E8A65', '#6C736E', '#B45A37',
		'#D8B26A', '#3D6A47', '#8AA98F', '#9C5A45', '#4A5A8A', '#A8894E',
		'#6E5AA0', '#B0A48C', '#7A8B6A', '#D2693E'
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
					+ '<span class="bc-break-amt">€' + fmt(row.amount) + '</span>';
				breakList.appendChild(r);
			});
			renderDonut(res.rows, res.total);
			var empty = res.total <= 0;
			totalNum.textContent = '€' + fmt(res.total);
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
				budget: '€' + lo.toLocaleString('de-DE') + ' – €' + hi.toLocaleString('de-DE'),
				notes: 'Über den Budget-Rechner geschätzt: ' + res.type.label + ', ' + state.participants
					+ ' Personen, Preisniveau ' + state.range + ' — Richtwert ca. €' + fmt(res.total) + '.'
			});
		});

		applyType();
	}

	/* =================================================================
	 * REQUEST WIZARD
	 * ================================================================= */
	var Wizard = (function () {
		var FULL_STEPS = ['Anlass', 'Eckdaten', 'Leistungen', 'Budget', 'Kontakt'];
		var SERVICE_GROUPS = [
			{ group: 'Sport & Programm', items: ['Golflehrer / Coaching', 'Schnupperkurs', 'Firmenturnier', 'Putting-Challenge'] },
			{ group: 'Gastronomie', items: ['Frühstück', 'Lunch', 'Abendessen', 'Bar & Drinks'] },
			{ group: 'Technik & Show', items: ['Eventtechnik: Bühne + Personal', 'DJ', 'Licht & Sound', 'Flutlicht / Nacht-Event'] },
			{ group: 'Foto & Content', items: ['Fotograf', 'Content-Team für Social'] },
			{ group: 'Branding & Merch', items: ['Branding & Banner', 'Individuelle Artikel', 'Pokale & Preise'] },
			{ group: 'Logistik', items: ['Meetingraum', 'Shuttle / Transport', 'Übernachtung', 'Schlechtwetter-Alternative'] }
		];
		var BUDGETS = [
			{ v: 'Unter €5.000', h: 'Kleinere Halbtags-Formate' },
			{ v: '€5.000 – €10.000', h: 'Eintägig für 20–40 Gäste' },
			{ v: '€10.000 – €20.000', h: 'Premium-Eintages-Events' },
			{ v: '€20.000 – €50.000', h: 'Mehrtägig oder größere Gruppen' },
			{ v: 'Über €50.000', h: 'Incentive-Reisen, Großformate' },
			{ v: 'Noch unklar', h: 'Wir gehen es gemeinsam durch' }
		];
		var CONTACT = { name: 'Jonas Bredow', role: 'Head of Events' };

		var overlay = null, S = null;

		function blank(preset) {
			var f = {
				occasion: 'Sommerfest', goal: '', size: '40', region: '', place: '',
				budget: '€10.000 – €20.000', when: '', flex: 'flexibel', duration: 'Halbtag',
				date1: '', date2: '', date3: '', services: ['Lunch', 'Golflehrer / Coaching'],
				company: '', city: '', firstName: '', lastName: '', role: '', email: '', phone: '',
				contactPref: 'E-Mail', notes: '', consent: false
			};
			if (preset) for (var k in preset) if (preset.hasOwnProperty(k)) f[k] = preset[k];
			return f;
		}

		function chips(field, options) {
			return '<div class="ind-chip-group">' + options.map(function (o) {
				return '<button type="button" class="ind-pchip' + (S.form[field] === o ? ' on' : '')
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
		// Optionaler „Konkreter Golfplatz"-Dropdown (nur wenn Plätze übergeben wurden).
		function placeField() {
			var places = CFG.places || [];
			if (!places.length) return '';
			var opts = '<option value="">Kein bestimmter Platz</option>' + places.map(function (p) {
				return '<option value="' + esc(p) + '"' + (S.form.place === p ? ' selected' : '') + '>' + esc(p) + '</option>';
			}).join('');
			return '<div class="rw-field">' + label('Konkreter Golfplatz?', false, 'Optional')
				+ '<select class="fg-input" data-field="place">' + opts + '</select></div>';
		}

		function topBar() {
			var shortcut = (S.phase === 'form' && S.mode !== 'success') ?
				'<button class="rw-shortcut" data-act="toggle-mode">'
				+ (S.mode === 'quick' ? 'Ausführliche Anfrage' : 'Schnell-Anfrage in 30 Sek.') + '</button>' : '';
			return '<header class="rw-top"><div class="rw-top-brand"><span class="rw-top-title">Event anfragen</span></div>'
				+ '<div class="rw-top-actions">' + shortcut
				+ '<button class="rw-close" data-act="close" aria-label="Schließen"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div></header>';
		}

		function screenIntro() {
			var occ = S.form.occasion || 'Firmenevent';
			return '<div class="rw-stage"><div class="rw-screen rw-intro">'
				+ '<div class="rw-eyebrow">Schön, dass du da bist</div>'
				+ '<h1 class="rw-h">Toll — ihr plant ein <span class="mk-italic">' + esc(occ) + '</span> für euer Team.</h1>'
				+ '<p class="rw-lead">Lass uns kurz ein paar Infos sammeln. Danach meldet sich ' + esc(CONTACT.name)
				+ ' persönlich bei dir — meist noch am selben Werktag, mit ersten Ideen und einem Richtpreis.</p>'
				+ '<div class="rw-intro-contact"><div><div class="rw-intro-c-name">' + esc(CONTACT.name) + '</div>'
				+ '<div class="rw-intro-c-role">' + esc(CONTACT.role) + ' · Firmengolf</div>'
				+ '<div class="rw-intro-c-note">„Ich kümmere mich persönlich um deine Anfrage."</div></div></div>'
				+ '<ul class="rw-intro-steps"><li>Ein paar Eckdaten — keine zwei Minuten</li>'
				+ '<li>Persönliche Rückmeldung statt Funnel</li><li>Unverbindlich und kostenlos</li></ul>'
				+ '</div></div>'
				+ '<div class="rw-foot rw-foot-quick"><button class="rw-back" data-act="close">Abbrechen</button>'
				+ '<button class="fg-btn-brand lg" data-act="intro-start">Los geht\'s <span class="fg-arrow">' + ARROW + '</span></button></div>';
		}

		function screenQuick() {
			var h = '<div class="rw-stage"><div class="rw-screen">'
				+ '<div class="rw-eyebrow">Schnell-Anfrage · 30 Sekunden</div>'
				+ '<h1 class="rw-h">Das Wichtigste — wir klären den Rest persönlich.</h1>'
				+ '<p class="rw-lead">Du willst nicht durch alle Schritte? Völlig okay. Gib uns die Basics, wir melden uns mit Rückfragen.</p>'
				+ '<div class="rw-form">'
				+ '<div class="rw-field">' + label('Anlass', true) + chips('occasion', ['Sommerfest', 'Firmenturnier', 'Teamevent', 'Kundenevent', 'Offsite', 'Nacht-Event', 'Etwas anderes']) + '</div>'
				+ '<div class="rw-row"><div class="rw-field">' + label('Teilnehmerzahl')
				+ '<div class="ind-input-row">' + input('size', 'type="number" min="1"', '40') + '<span class="ind-input-suffix">Personen</span></div></div>'
				+ '<div class="rw-field">' + label('Gewünschter Zeitraum') + input('when', '', 'z.B. Juli 2026') + '</div></div>'
				+ '<div class="rw-row"><div class="rw-field">' + label('Vor- & Nachname', true) + input('firstName', 'required', 'Vor- und Nachname') + '</div>'
				+ '<div class="rw-field">' + label('E-Mail', true) + input('email', 'type="email" required', 'name@firma.de') + '</div></div>'
				+ '<div class="rw-field">' + label('Firma') + input('company', '', 'Musterfirma GmbH') + '</div>'
				+ '<div class="rw-field">' + label('Was habt ihr vor?') + '<textarea class="fg-input" data-field="notes" rows="3" placeholder="Ein, zwei Sätze zu Ziel, Stimmung, Wünschen.">' + esc(S.form.notes) + '</textarea></div>'
				+ '<label class="ind-consent"><input type="checkbox" data-field="consent"' + (S.form.consent ? ' checked' : '') + '><span>Ich stimme der Verarbeitung meiner Daten zur Bearbeitung der Anfrage gemäß Datenschutzerklärung zu.</span></label>'
				+ '</div></div></div>'
				+ '<div class="rw-foot rw-foot-quick"><button class="rw-switch" data-act="to-full">Lieber ausführlich anfragen</button>'
				+ '<button class="fg-btn-brand lg" data-act="submit">Anfrage senden <span class="fg-arrow">' + ARROW + '</span></button></div>';
			return h;
		}

		function fullStepBody(step) {
			if (step === 0) {
				return '<div class="rw-eyebrow">Schritt 1 · ' + FULL_STEPS[0] + '</div>'
					+ '<h1 class="rw-h">Worum geht\'s bei eurem Event?</h1>'
					+ '<p class="rw-lead">Wähl den nächstpassenden Anlass — wir verfeinern alles im Gespräch.</p>'
					+ '<div class="rw-form"><div class="rw-field">'
					+ chips('occasion', ['Sommerfest', 'Firmenturnier', 'Teamevent', 'Kundenevent', 'Offsite', 'Incentive-Reise', 'Charity-Event', 'Gesundheitstag', 'Nacht-Event', 'Etwas anderes'])
					+ '</div><div class="rw-field">' + label('Was wollt ihr erreichen?', false, 'Ein Satz reicht')
					+ input('goal', '', 'z.B. Team zusammenbringen · Kunden begeistern · Mitarbeitende belohnen') + '</div></div>';
			}
			if (step === 1) {
				return '<div class="rw-eyebrow">Schritt 2 · ' + FULL_STEPS[1] + '</div>'
					+ '<h1 class="rw-h">Wann, wo und mit wie vielen?</h1>'
					+ '<p class="rw-lead">Genau müssen die Angaben jetzt nicht sein.</p>'
					+ '<div class="rw-form"><div class="rw-row"><div class="rw-field">' + label('Teilnehmerzahl', true)
					+ '<div class="ind-input-row">' + input('size', 'type="number" min="1"', '40') + '<span class="ind-input-suffix">Personen</span></div></div>'
					+ '<div class="rw-field">' + label('Gewünschter Zeitraum') + input('when', '', 'z.B. Juli 2026 · KW 28') + '</div></div>'
					+ '<div class="rw-field">' + label('Bis zu drei Wunschtermine', false, 'Optional')
					+ '<div class="rw-row rw-row-3">' + input('date1', '', '1. Termin') + input('date2', '', '2. Termin') + input('date3', '', '3. Termin') + '</div></div>'
					+ '<div class="rw-row"><div class="rw-field">' + label('Wie flexibel beim Datum?') + chips('flex', ['fix', '± 1 Woche', 'flexibel', 'noch offen']) + '</div>'
					+ '<div class="rw-field">' + label('Dauer') + chips('duration', ['Halbtag', 'Ganztag', '2 Tage', 'Mehrtägig']) + '</div></div>'
					+ '<div class="rw-field">' + label('Wo soll euer Event stattfinden?')
					+ chips('region', ['In der Nähe / Bundesland', 'Mitte Deutschlands', 'In den Alpen', 'Stadtnah', 'Am Meer / Sylt', 'In Europa', 'An einem besonderen Ort', 'Noch offen'])
					+ '</div>' + placeField() + '</div>';
			}
			if (step === 2) {
				var groups = SERVICE_GROUPS.map(function (g) {
					return '<div class="ind-svc-pick-group"><div class="ind-svc-pick-h">' + esc(g.group) + '</div><div class="ind-toggles">'
						+ g.items.map(function (it) {
							return '<button type="button" class="ind-toggle' + (S.form.services.indexOf(it) >= 0 ? ' on' : '')
								+ '" data-svc="' + esc(it) + '"><span class="ind-toggle-dot"></span><span>' + esc(it) + '</span></button>';
						}).join('') + '</div></div>';
				}).join('');
				return '<div class="rw-eyebrow">Schritt 3 · ' + FULL_STEPS[2] + '</div>'
					+ '<h1 class="rw-h">Was soll dabei sein?</h1>'
					+ '<p class="rw-lead">Mehrfachauswahl — alles kombinierbar. Nur ein Startpunkt, festlegen musst du dich nicht.</p>'
					+ '<div class="rw-form"><div class="ind-svc-pick">' + groups + '</div></div>';
			}
			if (step === 3) {
				var cards = BUDGETS.map(function (o) {
					return '<button type="button" class="ind-budget-card' + (S.form.budget === o.v ? ' on' : '')
						+ '" data-chip="budget" data-val="' + esc(o.v) + '"><span class="ind-budget-v">' + esc(o.v)
						+ '</span><span class="ind-budget-h">' + esc(o.h) + '</span></button>';
				}).join('');
				return '<div class="rw-eyebrow">Schritt 4 · ' + FULL_STEPS[3] + '</div>'
					+ '<h1 class="rw-h">Was wäre euer Budget-Rahmen?</h1>'
					+ '<p class="rw-lead">Nur eine Richtschnur — wir verhandeln nicht nach oben.</p>'
					+ '<div class="rw-form"><div class="ind-budget-grid">' + cards + '</div>'
					+ '<div class="rw-field" style="margin-top:8px;">' + label('Was ist euch wichtig?')
					+ '<textarea class="fg-input" data-field="notes" rows="4" placeholder="Stimmung, Hintergrund, besondere Wünsche — alles was hilft.">' + esc(S.form.notes) + '</textarea></div></div>';
			}
			// step 4
			return '<div class="rw-eyebrow">Schritt 5 · ' + FULL_STEPS[4] + '</div>'
				+ '<h1 class="rw-h">Wer seid ihr — und wie erreichen wir dich?</h1>'
				+ '<p class="rw-lead">Letzter Schritt. Danach melden wir uns innerhalb eines Werktags.</p>'
				+ '<div class="rw-form"><div class="rw-row"><div class="rw-field">' + label('Unternehmen', true) + input('company', 'required', 'Musterfirma GmbH') + '</div>'
				+ '<div class="rw-field">' + label('Ort') + input('city', '', 'München') + '</div></div>'
				+ '<div class="rw-row"><div class="rw-field">' + label('Vorname', true) + input('firstName', 'required', 'Vorname') + '</div>'
				+ '<div class="rw-field">' + label('Nachname', true) + input('lastName', 'required', 'Nachname') + '</div></div>'
				+ '<div class="rw-row"><div class="rw-field">' + label('E-Mail', true) + input('email', 'type="email" required', 'name@firma.de') + '</div>'
				+ '<div class="rw-field">' + label('Telefon') + input('phone', 'type="tel"', '+49 …') + '</div></div>'
				+ '<div class="rw-field">' + label('Bevorzugte Kontaktart') + chips('contactPref', ['E-Mail', 'Telefon', 'Egal']) + '</div>'
				+ '<label class="ind-consent"><input type="checkbox" data-field="consent"' + (S.form.consent ? ' checked' : '') + '>'
				+ '<span>Ich stimme der Verarbeitung meiner Daten zur Bearbeitung der Anfrage gemäß Datenschutzerklärung zu.</span></label></div>';
		}

		function screenFull() {
			var segs = FULL_STEPS.map(function (lbl, i) {
				var active = i <= S.step;
				return '<div class="rw-seg' + (active ? ' is-active' : '') + '">'
					+ '<div class="rw-seg-bar"><div class="rw-seg-fill" style="width:' + (active ? 100 : 0) + '%"></div></div>'
					+ '<span class="rw-seg-label">' + esc(lbl) + '</span></div>';
			}).join('');
			var isLast = S.step === FULL_STEPS.length - 1;
			return '<div class="rw-stage"><div class="rw-screen">' + fullStepBody(S.step) + '</div></div>'
				+ '<div class="rw-foot"><div class="rw-progress">' + segs + '</div>'
				+ '<div class="rw-nav"><button class="rw-back" data-act="back">' + (S.step === 0 ? 'Abbrechen' : '← Zurück') + '</button>'
				+ '<button class="fg-btn-brand lg" data-act="next">' + (isLast ? 'Anfrage senden' : 'Weiter') + ' <span class="fg-arrow">' + ARROW + '</span></button></div></div>';
		}

		function screenSuccess(resp) {
			return '<div class="rw-stage"><div class="rw-success">'
				+ '<div class="fg-success-mark"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg></div>'
				+ '<div class="mk-eyebrow">Anfrage eingegangen</div>'
				+ '<h1 class="rw-success-h">Danke — deine Anfrage ist angekommen.</h1>'
				+ '<p class="rw-success-p">Deine Anfrage liegt jetzt bei uns. Bei einem individuellen Event übernehmen wir die Planung persönlich und stimmen den passenden Golfplatz für dich ab. Eine Bestätigung ist gerade per Mail an <strong>' + esc(resp.email || S.form.email || '—') + '</strong> unterwegs.</p>'
				+ '<div class="rw-receipt">'
				+ '<div><span>Anlass</span><span>' + esc(resp.occasion || S.form.occasion) + '</span></div>'
				+ '<div><span>Gruppe</span><span>' + esc((resp.size || S.form.size) + ' Personen') + '</span></div>'
				+ '<div><span>Unternehmen</span><span>' + esc(resp.company || S.form.company || '—') + '</span></div>'
				+ '<div><span>Status</span><span><span class="ob-pill-status">In Bearbeitung</span></span></div>'
				+ '<div><span>Vorgangs-Nr.</span><span class="mono">' + esc(resp.ref || '') + '</span></div></div>'
				+ '<div class="rw-success-ctas"><button class="fg-btn-brand" data-act="close">Schließen</button></div>'
				+ '</div></div>';
		}

		function render(successResp) {
			var body;
			if (S.phase === 'success') body = topBar() + screenSuccess(successResp || {});
			else if (S.phase === 'intro') body = topBar() + screenIntro();
			else if (S.mode === 'quick') body = topBar() + screenQuick();
			else body = topBar() + screenFull();
			overlay.innerHTML = body;
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
			if (S.mode === 'quick') return S.form.firstName && S.form.email && S.form.occasion && S.form.consent;
			if (S.step === 0) return !!S.form.occasion;
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
			body.set('occasion', f.occasion); body.set('goal', f.goal); body.set('size', f.size);
			body.set('region', f.region); body.set('place', f.place || ''); body.set('budget', f.budget); body.set('when', f.when);
			body.set('flex', f.flex); body.set('duration', f.duration);
			body.set('date1', f.date1); body.set('date2', f.date2); body.set('date3', f.date3);
			body.set('company', f.company); body.set('city', f.city);
			body.set('first_name', f.firstName); body.set('last_name', f.lastName);
			body.set('role', f.role); body.set('email', f.email); body.set('phone', f.phone);
			body.set('contact_pref', f.contactPref); body.set('notes', f.notes);
			body.set('consent', f.consent ? '1' : '');
			body.set('services', (f.services || []).join('||'));
			if (S.source) body.set('source', S.source);

			fetch(CFG.ajaxUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
				.then(function (r) { return r.json(); })
				.then(function (j) {
					S.sending = false;
					if (j && j.success) { S.phase = 'success'; render(j.data); }
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
				S.form[t.getAttribute('data-chip')] = t.getAttribute('data-val');
				render();
				return;
			}
			if (t.hasAttribute('data-svc')) {
				collect();
				var it = t.getAttribute('data-svc');
				var i = S.form.services.indexOf(it);
				if (i >= 0) S.form.services.splice(i, 1); else S.form.services.push(it);
				t.classList.toggle('on');
				return;
			}
			var act = t.getAttribute('data-act');
			if (act === 'close') { close(); return; }
			if (act === 'toggle-mode') { collect(); S.mode = (S.mode === 'quick' ? 'full' : 'quick'); S.step = 0; render(); return; }
			if (act === 'to-full') { collect(); S.mode = 'full'; S.step = 0; render(); return; }
			if (act === 'intro-start') { S.phase = 'form'; render(); return; }
			if (act === 'back') { collect(); if (S.step === 0) { close(); } else { S.step--; render(); } return; }
			if (act === 'next') {
				collect();
				if (!valid()) { flashInvalid(); return; }
				if (S.step === FULL_STEPS.length - 1) { submit(t); } else { S.step++; render(); }
				return;
			}
			if (act === 'submit') { collect(); if (!valid()) { flashInvalid(); return; } submit(t); return; }
		}

		function flashInvalid() {
			overlay.querySelectorAll('[data-field]').forEach(function (inp) {
				if (inp.required && !inp.value) inp.classList.add('fg-input-err');
			});
			var first = overlay.querySelector('.fg-input-err');
			if (first) first.focus();
		}

		function onKey(e) { if (e.key === 'Escape') close(); }

		function open(mode, preset, intro, source) {
			if (!overlay) {
				overlay = el('div', 'rw-overlay');
				overlay.setAttribute('role', 'dialog');
				overlay.setAttribute('aria-modal', 'true');
				document.body.appendChild(overlay);
				overlay.addEventListener('click', onClick);
			}
			S = { mode: mode || 'full', phase: (intro && mode !== 'quick') ? 'intro' : 'form', step: 0, sending: false, source: source || '', form: blank(preset) };
			document.body.style.overflow = 'hidden';
			window.addEventListener('keydown', onKey);
			overlay.hidden = false;
			render();
		}
		function close() {
			if (overlay) overlay.hidden = true;
			document.body.style.overflow = '';
			window.removeEventListener('keydown', onKey);
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
	ready(function () {
		initCalc();
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
	});

	window.FGEWizard = Wizard;
})();
