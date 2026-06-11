/**
 * Partner-Medien-Widget: Logo-Slot + Titelbild-Slot + Galerie-Dropzone.
 * Alles inline, ohne Modal: Dateien per Klick oder Drag & Drop, Vorschau sofort,
 * Upload-Fortschritt als Overlay auf der Kachel.
 * Datenmodell unverändert: Titelbild = erstes Galeriebild, Logo = eigene Meta.
 * REST: firmengolf/v1 (upload/delete/reorder/logo); Config aus FGE_MEDIA.
 */
( function () {
	'use strict';

	var C = window.FGE_MEDIA;
	if ( ! C || ! C.partnerId ) {
		return;
	}
	var L = C.limits, T = C.i18n;

	var root     = document.querySelector( '[data-fge-gallery]' );
	var logoHost = document.querySelector( '[data-fge-logo]' );
	if ( ! root ) {
		return;
	}

	var state = {
		gallery: ( C.gallery || [] ).slice(),
		logo: C.logo || null,
		pending: [], // { file, url, status: 'queued'|'up'|'error', error, toFront }
		logoBusy: false,
		dragId: null,
	};

	// ── REST helpers ──────────────────────────────────────────────────────────
	function api( path, opts ) {
		opts = opts || {};
		opts.headers = Object.assign( { 'X-WP-Nonce': C.nonce }, opts.headers || {} );
		return fetch( C.restRoot + path, opts ).then( function ( r ) {
			return r.json().then( function ( j ) {
				if ( ! r.ok ) { throw new Error( ( j && j.message ) || ( 'HTTP ' + r.status ) ); }
				return j;
			} );
		} );
	}
	function uploadPhoto( file ) {
		var fd = new FormData();
		fd.append( 'file', file );
		return api( '/partner/' + C.partnerId + '/gallery', { method: 'POST', body: fd } );
	}
	function deletePhoto( id ) {
		return api( '/partner/' + C.partnerId + '/gallery/' + id, { method: 'DELETE' } );
	}
	function saveOrder( ids ) {
		return api( '/partner/' + C.partnerId + '/gallery/order', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify( { ids: ids } ),
		} );
	}
	function uploadLogo( file ) {
		var fd = new FormData();
		fd.append( 'file', file );
		return api( '/partner/' + C.partnerId + '/logo', { method: 'POST', body: fd } );
	}

	// ── Utils ───────────────────────────────────────────────────────────────
	function human( b ) {
		if ( b >= 1048576 ) { var m = b / 1048576; return ( m % 1 === 0 ? m : m.toFixed( 1 ) ) + ' MB'; }
		return Math.round( b / 1024 ) + ' KB';
	}
	function checkFile( file, max ) {
		if ( L.mimes.indexOf( file.type ) === -1 ) { return T.errBadType; }
		if ( file.size > max ) { return T.errTooLarge + ' (' + human( file.size ) + ', max. ' + human( max ) + ').'; }
		return '';
	}
	function el( tag, cls, html ) {
		var e = document.createElement( tag );
		if ( cls ) { e.className = cls; }
		if ( html != null ) { e.innerHTML = html; }
		return e;
	}
	function pickFiles( cb, multiple ) {
		var inp = document.createElement( 'input' );
		inp.type = 'file';
		inp.accept = L.mimes.join( ',' );
		inp.multiple = !! multiple;
		inp.style.display = 'none';
		document.body.appendChild( inp );
		inp.addEventListener( 'change', function () {
			var files = Array.prototype.slice.call( inp.files || [] );
			document.body.removeChild( inp );
			if ( files.length ) { cb( files ); }
		} );
		inp.click();
	}
	function hasFiles( e ) {
		var t = e.dataTransfer && e.dataTransfer.types;
		return !! t && Array.prototype.indexOf.call( t, 'Files' ) !== -1;
	}
	/** Datei-Drop auf ein Element verdrahten (mit is-dragover Hover-Stil). */
	function wireDrop( node, onFiles ) {
		node.addEventListener( 'dragover', function ( e ) {
			if ( hasFiles( e ) ) { e.preventDefault(); node.classList.add( 'is-dragover' ); }
		} );
		node.addEventListener( 'dragleave', function () { node.classList.remove( 'is-dragover' ); } );
		node.addEventListener( 'drop', function ( e ) {
			if ( ! hasFiles( e ) ) { return; }
			e.preventDefault();
			e.stopPropagation();
			node.classList.remove( 'is-dragover' );
			var files = Array.prototype.slice.call( e.dataTransfer.files || [] );
			if ( files.length ) { onFiles( files ); }
		} );
	}

	var ICON_IMAGE = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>';
	var ICON_CAMERA = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 4l1.5 2.5h3A1.5 1.5 0 0 1 20.5 8v9A1.5 1.5 0 0 1 19 18.5H5A1.5 1.5 0 0 1 3.5 17V8A1.5 1.5 0 0 1 5 6.5h3L9.5 4z"/><circle cx="12" cy="12" r="3.2"/></svg>';
	var ICON_STAR = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>';
	var ICON_TRASH = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>';

	// ── Render ────────────────────────────────────────────────────────────────
	function renderAll() {
		if ( logoHost ) { logoHost.innerHTML = ''; }
		root.innerHTML = '';
		root.appendChild( renderChecklist() );
		root.appendChild( renderSlots() );
		root.appendChild( renderGallery() );
	}

	function renderChecklist() {
		var n    = state.gallery.length;
		var list = el( 'div', 'fge-checklist' );
		list.appendChild( checkItem( !! state.logo, T.checkLogo ) );
		list.appendChild( checkItem( n > 0, T.checkCover ) );
		list.appendChild( checkItem( n >= L.recommendedMin, T.checkPhotos + ' ' + n + '/' + L.recommendedMin ) );
		return list;
	}
	function checkItem( ok, label ) {
		var it = el( 'span', 'fge-check' + ( ok ? ' is-ok' : '' ) );
		it.appendChild( el( 'span', 'fge-check-dot', ok ? '✓' : '' ) );
		it.appendChild( document.createTextNode( label ) );
		return it;
	}

	function renderSlots() {
		var row = el( 'div', 'fge-slots' + ( logoHost ? '' : ' no-logo' ) );
		if ( logoHost ) { row.appendChild( renderLogoSlot() ); }
		row.appendChild( renderCoverSlot() );
		return row;
	}

	function renderLogoSlot() {
		var wrap = el( 'div', 'fge-slot-wrap fge-slot-wrap-logo' );
		wrap.appendChild( el( 'div', 'fge-slot-label', T.slotLogoLabel ) );
		var slot = el( 'div', 'fge-slot fge-slot-logo' + ( state.logo ? ' is-filled' : '' ) );
		slot.setAttribute( 'role', 'button' );
		slot.tabIndex = 0;
		if ( state.logo ) {
			var img = el( 'span', 'fge-slot-img is-contain' );
			img.style.backgroundImage = "url('" + ( state.logo.thumb || state.logo.full ) + "')";
			slot.appendChild( img );
			slot.appendChild( el( 'span', 'fge-slot-swap', T.slotSwap ) );
		} else {
			slot.appendChild( el( 'span', 'fge-slot-ico', ICON_IMAGE ) );
			slot.appendChild( el( 'span', 'fge-slot-t', T.slotLogoEmpty ) );
		}
		if ( state.logoBusy ) { slot.appendChild( el( 'span', 'fge-tile-spin' ) ); }
		var handle = function ( files ) { doLogoUpload( files[ 0 ] ); };
		slot.addEventListener( 'click', function () { if ( ! state.logoBusy ) { pickFiles( handle, false ); } } );
		wireDrop( slot, handle );
		wrap.appendChild( slot );
		wrap.appendChild( el( 'div', 'fge-slot-specs', L.exts + ' · ideal 400 × 400 px · max. ' + human( L.logo ) ) );
		return wrap;
	}

	function renderCoverSlot() {
		var cover = state.gallery[ 0 ] || null;
		var wrap  = el( 'div', 'fge-slot-wrap fge-slot-wrap-cover' );
		wrap.appendChild( el( 'div', 'fge-slot-label', T.slotCoverLabel ) );
		var slot = el( 'div', 'fge-slot fge-slot-cover' + ( cover ? ' is-filled' : '' ) );
		slot.setAttribute( 'role', 'button' );
		slot.tabIndex = 0;
		if ( cover ) {
			var img = el( 'span', 'fge-slot-img' );
			img.style.backgroundImage = "url('" + ( cover.large || cover.thumb || cover.full ) + "')";
			slot.appendChild( img );
			slot.appendChild( el( 'span', 'fge-gallery-badge', T.coverBadge ) );
			slot.appendChild( el( 'span', 'fge-slot-swap', T.slotSwap ) );
		} else {
			slot.appendChild( el( 'span', 'fge-slot-ico', ICON_CAMERA ) );
			slot.appendChild( el( 'span', 'fge-slot-t', T.slotCoverEmpty ) );
		}
		var handle = function ( files ) { queueUploads( [ files[ 0 ] ], true ); };
		slot.addEventListener( 'click', function () { pickFiles( handle, false ); } );
		wireDrop( slot, handle );
		wrap.appendChild( slot );
		wrap.appendChild( el( 'div', 'fge-slot-specs', L.exts + ' · mind. ' + L.coverMinW + ' px breit · max. ' + human( L.gallery ) ) );
		if ( cover && cover.width > 0 && cover.width < L.coverMinW ) {
			wrap.appendChild( el( 'div', 'fge-slot-warn', T.coverMinWarn.replace( '%1$d', cover.width ).replace( '%2$d', L.coverMinW ) ) );
		}
		return wrap;
	}

	function renderGallery() {
		var wrap = el( 'div', 'fge-gal' );
		wrap.appendChild( el( 'div', 'fge-slot-label', T.galleryTitle ) );

		var grid = el( 'div', 'fge-gallery-grid' );

		// Erste Kachel ist immer die Plus-Kachel, auch nach Uploads.
		var add = el( 'button', 'fge-gallery-add' );
		add.type = 'button';
		add.innerHTML = '<span class="fge-gallery-add-plus">+</span><span>' + T.dropHint + '</span>';
		add.addEventListener( 'click', function () {
			pickFiles( function ( fs ) { queueUploads( fs, false ); }, true );
		} );
		grid.appendChild( add );

		state.gallery.slice( 1 ).forEach( function ( photo ) {
			grid.appendChild( renderTile( photo ) );
		} );
		state.pending.forEach( function ( p, i ) {
			grid.appendChild( renderPendingTile( p, i ) );
		} );

		// Auf eine volle Dreierreihe auffüllen, damit das Raster sofort erkennbar ist.
		// Mit jedem Upload wächst das Grid von selbst weiter.
		var total = 1 + state.gallery.slice( 1 ).length + state.pending.length;
		for ( var ph = total; ph < 3; ph++ ) {
			grid.appendChild( el( 'div', 'fge-gallery-ph', ICON_IMAGE ) );
		}

		wireDrop( grid, function ( fs ) { queueUploads( fs, false ); } );
		wrap.appendChild( grid );
		wrap.appendChild( el( 'div', 'fge-slot-specs', L.exts + ' · max. ' + human( L.gallery ) + ' pro Foto' ) );

		var need = L.recommendedMin - state.gallery.length;
		var rec  = el( 'p', 'fge-gallery-rec' );
		if ( need > 0 ) {
			rec.textContent = T.recommend.replace( '%d', need );
			rec.dataset.kind = 'warn';
		} else {
			rec.textContent = T.recommendOk;
			rec.dataset.kind = 'ok';
		}
		wrap.appendChild( el( 'p', 'fge-gallery-hint', T.reorderHint ) );
		wrap.appendChild( rec );
		return wrap;
	}

	function renderTile( photo ) {
		var tile = el( 'div', 'fge-gallery-item' );
		tile.setAttribute( 'draggable', 'true' );
		tile.dataset.id = photo.id;
		tile.style.backgroundImage = "url('" + ( photo.large || photo.thumb || photo.full ) + "')";

		var act = el( 'div', 'fge-tile-actions' );
		var star = el( 'button', 'fge-tile-btn', ICON_STAR );
		star.type = 'button';
		star.title = T.menuCover;
		star.addEventListener( 'click', function ( e ) { e.stopPropagation(); setCover( photo.id ); } );
		var rm = el( 'button', 'fge-tile-btn is-danger', ICON_TRASH );
		rm.type = 'button';
		rm.title = T.menuRemove;
		rm.addEventListener( 'click', function ( e ) { e.stopPropagation(); removePhoto( photo.id ); } );
		act.appendChild( star );
		act.appendChild( rm );
		tile.appendChild( act );

		// Drag & Drop: Reihenfolge innerhalb der Galerie.
		tile.addEventListener( 'dragstart', function () { state.dragId = photo.id; tile.classList.add( 'dragging' ); } );
		tile.addEventListener( 'dragend', function () { tile.classList.remove( 'dragging' ); } );
		tile.addEventListener( 'dragover', function ( e ) { if ( ! hasFiles( e ) ) { e.preventDefault(); } } );
		tile.addEventListener( 'drop', function ( e ) {
			if ( hasFiles( e ) ) { return; }
			e.preventDefault();
			moveBefore( state.dragId, photo.id );
		} );
		return tile;
	}

	function renderPendingTile( p, i ) {
		var tile = el( 'div', 'fge-gallery-item is-pending' + ( p.status === 'error' ? ' is-error' : '' ) );
		tile.style.backgroundImage = "url('" + p.url + "')";
		if ( p.status === 'error' ) {
			tile.appendChild( el( 'span', 'fge-tile-err', p.error ) );
			tile.title = 'Klicken zum Entfernen';
			tile.addEventListener( 'click', function () { state.pending.splice( i, 1 ); renderAll(); } );
		} else {
			tile.appendChild( el( 'span', 'fge-tile-spin' ) );
		}
		return tile;
	}

	// ── Actions ───────────────────────────────────────────────────────────────
	function idx( id ) {
		for ( var i = 0; i < state.gallery.length; i++ ) {
			if ( state.gallery[ i ].id === id ) { return i; }
		}
		return -1;
	}
	function commitOrder() {
		var ids = state.gallery.map( function ( p ) { return p.id; } );
		renderAll();
		saveOrder( ids ).catch( function ( err ) { alert( '' + err ); } );
	}
	function moveBefore( dragId, targetId ) {
		if ( ! dragId || dragId === targetId ) { return; }
		var from = idx( dragId ), to = idx( targetId );
		if ( from < 0 || to < 0 ) { return; }
		var moved = state.gallery.splice( from, 1 )[ 0 ];
		state.gallery.splice( idx( targetId ), 0, moved );
		commitOrder();
	}
	function setCover( id ) {
		var i = idx( id );
		if ( i <= 0 ) { return; }
		var moved = state.gallery.splice( i, 1 )[ 0 ];
		state.gallery.unshift( moved );
		commitOrder();
	}
	function removePhoto( id ) {
		if ( ! window.confirm( T.confirmDelete ) ) { return; }
		deletePhoto( id ).then( function ( res ) {
			state.gallery = res.gallery || [];
			renderAll();
		} ).catch( function ( err ) { alert( '' + err ); } );
	}

	/** Dateien validieren, als Pending-Kacheln zeigen und sequenziell hochladen. */
	function queueUploads( files, toFront ) {
		files.forEach( function ( file ) {
			var err = checkFile( file, L.gallery );
			state.pending.push( {
				file: file,
				url: URL.createObjectURL( file ),
				status: err ? 'error' : 'queued',
				error: err,
				toFront: !! toFront,
			} );
		} );
		renderAll();
		processQueue();
	}

	var processing = false;
	function processQueue() {
		if ( processing ) { return; }
		var next = null;
		for ( var i = 0; i < state.pending.length; i++ ) {
			if ( state.pending[ i ].status === 'queued' ) { next = state.pending[ i ]; break; }
		}
		if ( ! next ) { return; }
		processing = true;
		next.status = 'up';
		renderAll();
		var prevIds = state.gallery.map( function ( p ) { return p.id; } );
		uploadPhoto( next.file ).then( function ( res ) {
			state.gallery = res.gallery || state.gallery;
			if ( next.toFront ) {
				// Neues Bild ermitteln und an Position 1 (Titelbild) setzen.
				var fresh = state.gallery.filter( function ( p ) { return prevIds.indexOf( p.id ) === -1; } );
				if ( fresh.length ) {
					var j = idx( fresh[ fresh.length - 1 ].id );
					if ( j > 0 ) {
						var moved = state.gallery.splice( j, 1 )[ 0 ];
						state.gallery.unshift( moved );
						saveOrder( state.gallery.map( function ( p ) { return p.id; } ) ).catch( function () {} );
					}
				}
			}
			state.pending.splice( state.pending.indexOf( next ), 1 );
		} ).catch( function ( err ) {
			next.status = 'error';
			next.error = '' + err;
		} ).then( function () {
			processing = false;
			renderAll();
			processQueue();
		} );
	}

	function doLogoUpload( file ) {
		var err = checkFile( file, L.logo );
		if ( err ) { alert( err ); return; }
		state.logoBusy = true;
		renderAll();
		uploadLogo( file ).then( function ( res ) {
			state.logo = res.logo;
		} ).catch( function ( e ) {
			alert( '' + e );
		} ).then( function () {
			state.logoBusy = false;
			renderAll();
		} );
	}

	// ── Init ────────────────────────────────────────────────────────────────
	renderAll();
} )();
