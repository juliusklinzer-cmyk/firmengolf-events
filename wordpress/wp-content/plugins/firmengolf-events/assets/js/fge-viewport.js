/**
 * Sichtbare Viewport-Maße für alle Vollbild-Flächen (Anfrage-Wizard, Dialoge,
 * Onboarding-Fuß).
 *
 * Warum: Auf iOS ist der Layout-Viewport größer als der sichtbare Bereich.
 * `position: fixed` und `inset: 0` rechnen mit dem großen Viewport, die
 * Safari-Leiste unten liegt darüber. Ein Fuß mit „Weiter" landet dadurch hinter
 * der Leiste und ist nicht antippbar. `100dvh` allein reicht nicht, weil Safari
 * den Wert bei fixierten Overlays nicht zuverlässig nachzieht, solange die Seite
 * dahinter gesperrt ist.
 *
 * Lösung: Die echten Maße der visualViewport-API landen als Custom Properties auf
 * <html>. Das CSS nutzt sie mit `100dvh` bzw. `0px` als Fallback, ohne JS bleibt
 * also alles wie vorher.
 *
 *   --fg-vvh  sichtbare Höhe
 *   --fg-vvt  Abstand der sichtbaren Fläche zur Oberkante des Layout-Viewports
 *   --fg-vvb  Abstand der sichtbaren Fläche zur Unterkante des Layout-Viewports
 *             (für fixierte Leisten mit `bottom`)
 *
 * Nebeneffekt mit Absicht: Bei offener Tastatur schrumpft die sichtbare Höhe, der
 * Fuß rutscht über die Tastatur statt darunter.
 *
 * (Julius, 2026-08-28)
 */
(function () {
	'use strict';

	var root = document.documentElement;
	var vv = window.visualViewport || null;
	var pending = 0;

	/* Tastatur offen? Dann NICHT nachziehen (Julius, 28.08. abends): sonst
	   schrumpfen Vollbild-Wizards auf den Bereich über der Tastatur, springen
	   hoch und geben den Blick auf die Seite dahinter frei. Die Tastatur soll
	   sich wie in einer nativen App einfach über den Wizard legen; nach dem
	   Blur liefert der nächste visualViewport-resize wieder echte Maße. */
	function editableFocused() {
		var a = document.activeElement;
		if (!a) { return false; }
		var t = a.tagName;
		return t === 'INPUT' || t === 'TEXTAREA' || t === 'SELECT' || a.isContentEditable === true;
	}

	function apply() {
		pending = 0;

		if (editableFocused()) { return; }

		var h = vv ? vv.height : window.innerHeight;
		var t = vv ? vv.offsetTop : 0;
		if (!h) { return; }

		// Bezugsrahmen für `position: fixed` ist der Layout-Viewport. Der größere
		// der beiden Werte ist auf iOS der richtige (innerHeight zählt die Fläche
		// unter den Browserleisten mit).
		var layout = Math.max(root.clientHeight || 0, window.innerHeight || 0, h + t);

		root.style.setProperty('--fg-vvh', Math.round(h) + 'px');
		root.style.setProperty('--fg-vvt', Math.round(t) + 'px');
		root.style.setProperty('--fg-vvb', Math.max(0, Math.round(layout - t - h)) + 'px');
	}

	function sync() {
		if (pending) { return; }
		if (window.requestAnimationFrame) { pending = window.requestAnimationFrame(apply); }
		else { apply(); }
	}

	// Erster Lauf synchron, nicht über requestAnimationFrame: rAF feuert in
	// Hintergrund-Tabs nicht, die Variablen blieben dort bis zum Tabwechsel leer.
	apply();

	if (vv) {
		vv.addEventListener('resize', sync);
		vv.addEventListener('scroll', sync);
	}
	window.addEventListener('resize', sync);
	window.addEventListener('orientationchange', sync);
	// Nach dem Verlassen eines Feldes frisch messen: die Tastatur fährt gerade
	// ein, der letzte resize danach bringt wieder die vollen Maße.
	document.addEventListener('focusout', function () { setTimeout(sync, 250); });
	// Zurück-Navigation aus dem bfcache liefert sonst veraltete Maße.
	window.addEventListener('pageshow', sync);
	document.addEventListener('DOMContentLoaded', sync);
	// Nachziehen, sobald ein im Hintergrund geladener Tab sichtbar wird.
	document.addEventListener('visibilitychange', function () {
		if (!document.hidden) { apply(); }
	});
})();
