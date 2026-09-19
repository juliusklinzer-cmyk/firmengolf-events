/* Partnercode (Multiplikatoren): ?pc=CODE merken (localStorage, kein Cookie),
   Livecheck gegen fge_pc_check, gemeinsame Feldlogik für Event-Dialog und Wizard.
   Konfiguration kommt aus window.FGE_PC (ajaxUrl, nonce). */
(function () {
	'use strict';
	var KEY = 'fgePartnercode', TTL = 90 * 86400 * 1000;
	var GENERIC = 'Dieser Partnercode ist nicht gültig.';

	function norm(s) {
		s = String(s || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
		return (s.length >= 4 && s.length <= 12) ? s : '';
	}
	function read() {
		try {
			var r = JSON.parse(localStorage.getItem(KEY) || 'null');
			if (!r || !r.code) { return ''; }
			if (Date.now() - (r.ts || 0) > TTL) { localStorage.removeItem(KEY); return ''; }
			return norm(r.code);
		} catch (e) { return ''; }
	}
	function write(code) {
		try {
			if (code) { localStorage.setItem(KEY, JSON.stringify({ code: code, ts: Date.now() })); }
			else { localStorage.removeItem(KEY); }
		} catch (e) {}
	}

	// Link-Parameter übernehmen.
	try {
		var fromUrl = norm(new URLSearchParams(window.location.search).get('pc'));
		if (fromUrl) { write(fromUrl); }
	} catch (e) {}

	function check(code, cb) {
		var c = norm(code);
		if (!c) { cb({ ok: false, message: GENERIC }); return; }
		var cfg = window.FGE_PC || {};
		fetch(cfg.ajaxUrl || '/wp-admin/admin-ajax.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: 'action=fge_pc_check&nonce=' + encodeURIComponent(cfg.nonce || '') + '&code=' + encodeURIComponent(c)
		})
			.then(function (r) { return r.json(); })
			.then(function (j) {
				if (j && j.success && j.data) { cb({ ok: true, code: j.data.code, holder: j.data.holder, percent: j.data.percent }); }
				else { cb({ ok: false, message: (j && j.data && j.data.message) || GENERIC }); }
			})
			.catch(function () { cb({ ok: false, soft: true, message: 'Prüfung gerade nicht möglich, der Code wird beim Senden geprüft.' }); });
	}

	/* Hinweiszeile rendern. res: null (verstecken), {ok:true,...} oder {ok:false,message}. */
	function renderHint(hint, res) {
		if (!hint) { return; }
		hint.classList.remove('is-ok', 'is-err');
		hint.innerHTML = '';
		if (!res) { hint.hidden = true; return; }
		var txt = document.createElement('span');
		if (res.ok) {
			txt.textContent = 'Code von ' + res.holder + ' erkannt: ' + res.percent + ' % Rabatt auf euer Angebot. ';
			hint.classList.add('is-ok');
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'fg-pc-clear';
			btn.setAttribute('data-act', 'pc-clear');
			btn.textContent = 'Entfernen';
			hint.appendChild(txt);
			hint.appendChild(btn);
		} else {
			txt.textContent = res.message || GENERIC;
			if (!res.soft) { hint.classList.add('is-err'); }
			hint.appendChild(txt);
		}
		hint.hidden = false;
	}

	/* Feld verdrahten: Eingabe normalisieren, Livecheck mit Debounce, Entfernen. */
	function wire(input, hint, onResult) {
		if (!input || input.dataset.pcWired) { return; }
		input.dataset.pcWired = '1';
		var t = null;
		function run() {
			var v = norm(input.value);
			if (!input.value.trim()) { renderHint(hint, null); write(''); if (onResult) { onResult(null); } return; }
			if (!v) { renderHint(hint, { ok: false, message: GENERIC }); if (onResult) { onResult({ ok: false }); } return; }
			check(v, function (res) {
				if (res.ok) { write(res.code); }
				renderHint(hint, res);
				if (onResult) { onResult(res); }
			});
		}
		input.addEventListener('input', function () {
			var pos = input.selectionStart;
			input.value = input.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 12);
			try { input.setSelectionRange(pos, pos); } catch (e) {}
			clearTimeout(t);
			t = setTimeout(run, 350);
		});
		if (hint) {
			hint.addEventListener('click', function (e) {
				if (!e.target.closest('[data-act="pc-clear"]')) { return; }
				e.preventDefault();
				input.value = '';
				write('');
				renderHint(hint, null);
				if (onResult) { onResult(null); }
			});
		}
		if (input.value.trim()) { run(); }
	}

	// Versteckte Felder (Kurzformulare) aus dem Speicher füllen.
	function fillHidden() {
		var code = read();
		document.querySelectorAll('input[data-pc-hidden]').forEach(function (i) { i.value = code; });
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', fillHidden); } else { fillHidden(); }

	window.FGEPartnercode = { normalize: norm, get: read, set: function (c) { write(norm(c)); }, clear: function () { write(''); }, check: check, renderHint: renderHint, wire: wire };
})();
