/**
 * Event-Bilder im Editor: großer Titelbild-Slot (16:9) plus Galerie-Reihe wie im
 * Onboarding (Plus-Kachel, Fotos, Platzhalter bis 3). Klick auf einen Slot öffnet
 * die Platz-Bibliothek als Auswahlfenster; dort kann auch hochgeladen werden.
 * Datenmodell unverändert: hidden inputs (Cover-ID + geordnete IDs, Cover zuerst).
 */
( function () {
	'use strict';

	var C = window.FGE_MEDIA;
	if ( ! C || ! C.partnerId ) { return; }
	var T = C.i18n, L = C.limits;

	var picker = document.querySelector( '[data-fge-picker]' );
	if ( ! picker ) { return; }
	var grid       = picker.querySelector( '[data-fge-picker-grid]' );
	var coverInput = picker.querySelector( '[data-fge-picker-cover]' );
	var idsInput   = picker.querySelector( '[data-fge-picker-ids]' );

	function csvToIds( s ) {
		return ( s || '' ).split( ',' ).map( function ( x ) { return parseInt( x, 10 ); } ).filter( function ( x ) { return x > 0; } );
	}

	var library  = ( C.gallery || [] ).slice();
	var selected = csvToIds( picker.getAttribute( 'data-selected' ) );
	var cover    = parseInt( picker.getAttribute( 'data-cover' ) || '0', 10 ) || 0;

	var ICON_CAM = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 4l1.5 2.5h3A1.5 1.5 0 0 1 20.5 8v9A1.5 1.5 0 0 1 19 18.5H5A1.5 1.5 0 0 1 3.5 17V8A1.5 1.5 0 0 1 5 6.5h3L9.5 4z"/><circle cx="12" cy="12" r="3.2"/></svg>';
	var ICON_IMG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>';

	function el( tag, cls, html ) {
		var e = document.createElement( tag );
		if ( cls ) { e.className = cls; }
		if ( html != null ) { e.innerHTML = html; }
		return e;
	}
	function photo( id ) {
		for ( var i = 0; i < library.length; i++ ) { if ( library[ i ].id === id ) { return library[ i ]; } }
		return null;
	}
	function human( b ) {
		if ( b >= 1048576 ) { var m = b / 1048576; return ( m % 1 === 0 ? m : m.toFixed( 1 ) ) + ' MB'; }
		return Math.round( b / 1024 ) + ' KB';
	}

	function sync() {
		if ( cover && selected.indexOf( cover ) === -1 ) { cover = 0; }
		if ( ! cover && selected.length ) { cover = selected[ 0 ]; }
		var ordered = cover ? [ cover ].concat( selected.filter( function ( id ) { return id !== cover; } ) ) : selected.slice();
		idsInput.value   = ordered.join( ',' );
		coverInput.value = cover || 0;
	}

	function toggle( id ) {
		var i = selected.indexOf( id );
		if ( i === -1 ) { selected.push( id ); }
		else {
			selected.splice( i, 1 );
			if ( cover === id ) { cover = selected[ 0 ] || 0; }
		}
		sync(); render(); renderLib();
	}
	function setCover( id ) {
		if ( selected.indexOf( id ) === -1 ) { selected.push( id ); }
		cover = id;
		sync(); render(); renderLib();
	}

	// ── Slot-Ansicht im Formular ──────────────────────────────────────────────
	function render() {
		grid.innerHTML = '';

		// Titelbild-Slot (16:9).
		var cov     = photo( cover );
		var covSlot = el( 'div', 'fge-pick-cover' + ( cov ? ' is-filled' : '' ) );
		covSlot.setAttribute( 'role', 'button' );
		covSlot.tabIndex = 0;
		if ( cov ) {
			covSlot.style.backgroundImage = "url('" + ( cov.large || cov.thumb || cov.full ) + "')";
			covSlot.appendChild( el( 'span', 'fge-picker-badge', T.pickCoverBadge ) );
			covSlot.appendChild( el( 'span', 'fge-pick-swap', 'Tauschen' ) );
		} else {
			covSlot.appendChild( el( 'span', 'fge-pick-ico', ICON_CAM ) );
			covSlot.appendChild( el( 'span', 'fge-pick-t', 'Titelbild wählen' ) );
		}
		covSlot.addEventListener( 'click', function () { openLib( 'cover' ); } );
		grid.appendChild( covSlot );

		// Format-Hinweis wie beim Golfplatz-Bilder-Anlegen.
		var covSpecs = el( 'div', 'fge-slot-specs', L.exts + ' · mind. ' + L.coverMinW + ' px breit · max. ' + human( L.gallery ) );
		covSpecs.style.gridColumn = '1 / -1';
		grid.appendChild( covSpecs );

		// Galerie-Reihe: Plus zuerst, dann Fotos, auf drei Kacheln auffüllen.
		var row = el( 'div', 'fge-pick-row' );
		var add = el( 'button', 'fge-pick-add' );
		add.type = 'button';
		add.innerHTML = '<span class="fge-gallery-add-plus">+</span><span>Fotos wählen</span>';
		add.addEventListener( 'click', function () { openLib( 'gallery' ); } );
		row.appendChild( add );

		var rest = selected.filter( function ( id ) { return id !== cover; } );
		rest.forEach( function ( id ) {
			var p = photo( id );
			if ( ! p ) { return; }
			var tile = el( 'div', 'fge-pick-tile' );
			tile.style.backgroundImage = "url('" + ( p.large || p.thumb || p.full ) + "')";
			var act  = el( 'span', 'fge-pick-actions' );
			var star = el( 'button', 'fge-pick-btn', '★' );
			star.type  = 'button';
			star.title = T.pickCover;
			star.addEventListener( 'click', function ( e ) { e.stopPropagation(); setCover( id ); } );
			var x = el( 'button', 'fge-pick-btn', '×' );
			x.type  = 'button';
			x.title = 'Entfernen';
			x.addEventListener( 'click', function ( e ) { e.stopPropagation(); toggle( id ); } );
			act.appendChild( star );
			act.appendChild( x );
			tile.appendChild( act );
			row.appendChild( tile );
		} );

		for ( var i = 1 + rest.length; i < 3; i++ ) {
			row.appendChild( el( 'div', 'fge-pick-ph', ICON_IMG ) );
		}
		grid.appendChild( row );

		var rowSpecs = el( 'div', 'fge-slot-specs', L.exts + ' · max. ' + human( L.gallery ) + ' pro Foto' );
		rowSpecs.style.gridColumn = '1 / -1';
		grid.appendChild( rowSpecs );
	}

	// ── Bibliotheks-Fenster (Platz-Galerie) ──────────────────────────────────
	var lib = null, libMode = 'gallery', libGrid = null, libTitle = null, libCount = null;

	function buildLib() {
		lib = el( 'div', 'fp-svc-overlay' );
		lib.hidden = true;
		var sheet = el( 'div', 'fp-svc-sheet' );
		var bar   = el( 'div', 'fp-svc-bar' );
		libTitle  = el( 'span', 't', '' );
		var close = el( 'button', 'fp-svc-close', '×' );
		close.type = 'button';
		close.setAttribute( 'aria-label', 'Schließen' );
		close.addEventListener( 'click', closeLib );
		bar.appendChild( libTitle );
		bar.appendChild( close );

		var body = el( 'div', 'fp-svc-body' );
		libGrid  = el( 'div', 'fge-picker-grid' );
		body.appendChild( libGrid );

		var foot = el( 'div', 'fp-svc-foot' );
		libCount = el( 'span', 'fp-svc-count', '' );
		var done = el( 'button', 'fp-btn fp-btn-brand', 'Fertig' );
		done.type = 'button';
		done.addEventListener( 'click', closeLib );
		foot.appendChild( libCount );
		foot.appendChild( done );

		sheet.appendChild( bar );
		sheet.appendChild( body );
		sheet.appendChild( foot );
		lib.appendChild( sheet );
		lib.addEventListener( 'click', function ( e ) { if ( e.target === lib ) { closeLib(); } } );
		document.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Escape' && ! lib.hidden ) { closeLib(); } } );
		document.body.appendChild( lib );
	}
	function openLib( mode ) {
		libMode = mode;
		if ( ! lib ) { buildLib(); }
		renderLib();
		lib.hidden = false;
		document.body.style.overflow = 'hidden';
	}
	function closeLib() {
		if ( lib ) { lib.hidden = true; }
		document.body.style.overflow = '';
	}
	function renderLib() {
		if ( ! lib || ! libGrid ) { return; }
		libTitle.textContent = libMode === 'cover' ? 'Titelbild wählen' : 'Fotos für dieses Event wählen';
		libCount.textContent = selected.length > 0 ? selected.length + ' ausgewählt' : '';
		libGrid.innerHTML = '';

		if ( ! library.length ) {
			libGrid.appendChild( el( 'p', 'fge-picker-empty', T.pickEmpty ) );
		}

		library.forEach( function ( p ) {
			var isSel = selected.indexOf( p.id ) !== -1;
			var isCov = cover === p.id;
			var tile  = el( 'div', 'fge-picker-item' + ( isSel ? ' is-selected' : '' ) );
			tile.style.backgroundImage = "url('" + ( p.large || p.thumb || p.full ) + "')";
			tile.appendChild( el( 'span', 'fge-picker-check', isSel ? '✓' : '' ) );
			if ( isCov ) { tile.appendChild( el( 'span', 'fge-picker-badge', T.pickCoverBadge ) ); }
			tile.addEventListener( 'click', function () {
				if ( libMode === 'cover' ) {
					setCover( p.id );
					closeLib();
				} else {
					toggle( p.id );
				}
			} );
			libGrid.appendChild( tile );
		} );

		var add = el( 'button', 'fge-picker-add' );
		add.type = 'button';
		add.innerHTML = '<span class="fge-picker-add-plus">+</span><span>' + T.pickUpload + '</span>';
		add.addEventListener( 'click', pickAndUpload );
		libGrid.appendChild( add );
	}

	// ── Upload in die Platz-Bibliothek ────────────────────────────────────────
	function pickAndUpload() {
		var inp = document.createElement( 'input' );
		inp.type = 'file'; inp.accept = L.mimes.join( ',' ); inp.multiple = true; inp.style.display = 'none';
		document.body.appendChild( inp );
		inp.addEventListener( 'change', function () {
			var files = Array.prototype.slice.call( inp.files || [] );
			document.body.removeChild( inp );
			files.forEach( function ( f ) {
				if ( L.mimes.indexOf( f.type ) === -1 ) { alert( T.errBadType ); return; }
				if ( f.size > L.gallery ) { alert( T.errTooLarge + ' (' + human( f.size ) + ', max. ' + human( L.gallery ) + ').' ); return; }
				var fd = new FormData(); fd.append( 'file', f );
				fetch( C.restRoot + '/partner/' + C.partnerId + '/gallery', {
					method: 'POST', headers: { 'X-WP-Nonce': C.nonce }, body: fd,
				} ).then( function ( r ) { return r.json().then( function ( j ) { if ( ! r.ok ) { throw new Error( j.message || r.status ); } return j; } ); } )
				  .then( function ( res ) {
					library = res.gallery || library;
					if ( res.photo ) {
						selected.push( res.photo.id );
						if ( ! cover || libMode === 'cover' ) { cover = res.photo.id; }
					}
					sync(); render(); renderLib();
				  } ).catch( function ( err ) { alert( '' + err ); } );
			} );
		} );
		inp.click();
	}

	sync();
	render();
} )();
