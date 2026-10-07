/*
 * Control Center: Verhalten des Grundgerüsts (Design vom 07.10.2026).
 *
 * Globale Suche, Bestätigungsdialog mit Folgen-Vorschau, Reiter der
 * Anfrage-Detailseite, Menü auf dem Telefon. Alles ist eine Verbesserung:
 * ohne dieses Skript funktionieren Formulare und confirm() wie bisher.
 */
(function () {
	'use strict';
	window.fgeCC = true;

	// ── Bestätigungsdialog ───────────────────────────────────────────────
	var dlg = null;

	function previewFor(btn) {
		var form = btn.form || btn.closest('form');
		if (!form) { return null; }
		var next = form.nextElementSibling;
		return next && next.classList.contains('cc-preview') ? next : null;
	}

	function askFirst(btn, ev) {
		if (!dlg || typeof dlg.showModal !== 'function' || btn.dataset.ccOk === '1') { return; }
		var text = btn.dataset.ccConfirm || '';
		var prev = previewFor(btn);
		if (!text && !prev) { return; }
		ev.preventDefault();
		dlg.querySelector('[data-cc-dialog-title]').textContent = (btn.textContent || 'Aktion').trim() + '?';
		var body = dlg.querySelector('[data-cc-dialog-body]');
		body.innerHTML = '';
		if (text) {
			var p = document.createElement('p');
			p.textContent = text;
			body.appendChild(p);
		}
		if (prev) {
			var clone = prev.cloneNode(true);
			clone.classList.add('cc-preview--dialog');
			body.appendChild(clone);
		}
		var gap = prev && prev.querySelector('.is-gap');
		dlg.querySelector('[data-cc-dialog-ok]').textContent = gap ? 'Trotzdem ausführen' : 'Bestätigen';
		dlg.returnValue = '';
		dlg.onclose = function () {
			if (dlg.returnValue !== 'ok') { return; }
			btn.dataset.ccOk = '1';
			var form = btn.form || btn.closest('form');
			if (form && form.requestSubmit) { form.requestSubmit(btn); } else { btn.click(); }
		};
		dlg.showModal();
	}

	function initDialog() {
		dlg = document.querySelector('[data-cc-dialog]');
		// Bisherige confirm()-Knöpfe übernehmen: Text auslesen, Inline-Handler weg.
		document.querySelectorAll('button[onclick*="confirm("]').forEach(function (b) {
			var m = /confirm\((["'])([\s\S]*)\1\)/.exec(b.getAttribute('onclick') || '');
			if (m) {
				var msg = m[2].replace(/\\(["'])/g, '$1');
				if (m[1] === '"') {
					// JSON-kodiert aus PHP (wp_json_encode): Umlaute als ä.
					try { msg = JSON.parse('"' + m[2] + '"'); } catch (err) { /* Rohtext behalten */ }
				}
				b.dataset.ccConfirm = msg;
				b.removeAttribute('onclick');
			}
		});
		document.addEventListener('click', function (e) {
			var b = e.target.closest ? e.target.closest('button[type="submit"], button:not([type])') : null;
			if (!b || b.closest('[data-cc-dialog]')) { return; }
			askFirst(b, e);
		});
	}

	// ── Globale Suche ────────────────────────────────────────────────────
	function initSearch() {
		var box = document.querySelector('[data-cc-gsearch]');
		if (!box) { return; }
		var input = box.querySelector('input');
		var list = box.querySelector('.cc-gsearch-list');
		var timer = 0, seq = 0, active = -1;

		function close() { list.hidden = true; active = -1; }
		function render(items) {
			list.innerHTML = '';
			if (!items.length) {
				var none = document.createElement('p');
				none.className = 'cc-gsearch-none';
				none.textContent = 'Nichts gefunden.';
				list.appendChild(none);
			}
			items.forEach(function (it) {
				var a = document.createElement('a');
				a.className = 'cc-gsearch-item';
				a.href = it.url;
				var main = document.createElement('span');
				main.className = 'cc-gsearch-main';
				var t = document.createElement('strong');
				t.textContent = it.title;
				var s = document.createElement('span');
				s.textContent = it.sub;
				main.appendChild(t);
				main.appendChild(s);
				var k = document.createElement('span');
				k.className = 'cc-gsearch-kind';
				k.textContent = it.kind;
				a.appendChild(main);
				a.appendChild(k);
				list.appendChild(a);
			});
			list.hidden = false;
		}
		input.addEventListener('input', function () {
			clearTimeout(timer);
			var q = input.value.trim();
			if (q.length < 2) { close(); return; }
			timer = setTimeout(function () {
				var my = ++seq;
				var url = box.dataset.endpoint + '?action=fge_cc_search&nonce=' + encodeURIComponent(box.dataset.nonce) + '&q=' + encodeURIComponent(q);
				fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
					if (my === seq && j && j.success) { render(j.data || []); }
				}).catch(close);
			}, 180);
		});
		input.addEventListener('keydown', function (e) {
			var items = list.querySelectorAll('.cc-gsearch-item');
			if (e.key === 'Escape') { close(); input.blur(); return; }
			if (!items.length || list.hidden) { return; }
			if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
				e.preventDefault();
				active = (active + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
				items.forEach(function (x, i) { x.classList.toggle('is-active', i === active); });
			} else if (e.key === 'Enter') {
				e.preventDefault();
				location.href = items[active >= 0 ? active : 0].href;
			}
		});
		document.addEventListener('click', function (e) { if (!box.contains(e.target)) { close(); } });
		document.addEventListener('keydown', function (e) {
			if (e.key === '/' && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName)) {
				e.preventDefault();
				input.focus();
			}
		});
	}

	// ── Reiter der Anfrage ───────────────────────────────────────────────
	function initRequestTabs() {
		var bar = document.querySelector('[data-cc-rtabs]');
		if (!bar) { return; }
		var key = 'fgeCCtab:' + bar.dataset.ccRtabs;
		function show(id) {
			var found = false;
			bar.querySelectorAll('[data-cc-rtab]').forEach(function (b) {
				var on = b.dataset.ccRtab === id;
				found = found || on;
				b.classList.toggle('is-on', on);
				b.setAttribute('aria-selected', on ? 'true' : 'false');
			});
			if (!found) { return false; }
			document.querySelectorAll('[data-cc-rpane]').forEach(function (p) { p.hidden = p.dataset.ccRpane !== id; });
			try { sessionStorage.setItem(key, id); } catch (e) { /* privat */ }
			return true;
		}
		bar.addEventListener('click', function (e) {
			var b = e.target.closest('[data-cc-rtab]');
			if (b) { show(b.dataset.ccRtab); }
		});
		var start = (location.hash || '').replace('#', '');
		if (!start) { try { start = sessionStorage.getItem(key) || ''; } catch (e) { start = ''; } }
		if (!start || !show(start)) { show('prozess'); }
	}

	// ── Menü auf dem Telefon ─────────────────────────────────────────────
	function initNav() {
		var t = document.querySelector('[data-cc-navtoggle]');
		if (!t) { return; }
		t.addEventListener('click', function () {
			var open = document.body.classList.toggle('cc-nav-open');
			t.setAttribute('aria-expanded', open ? 'true' : 'false');
		});
	}

	function init() {
		initDialog();
		initSearch();
		initRequestTabs();
		initNav();
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
}());
