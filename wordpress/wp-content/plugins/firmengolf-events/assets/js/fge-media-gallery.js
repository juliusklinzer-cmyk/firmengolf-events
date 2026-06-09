/**
 * Airbnb-style partner photo gallery widget.
 * Talks to the firmengolf/v1 REST routes; config comes from FGE_MEDIA (localized).
 * Renders: empty state, upload modal (with per-file progress), filled grid
 * (first = Titelfoto, "…" menu, drag & drop reorder, "+ Weitere"), and a logo uploader.
 */
( function () {
	'use strict';

	var C = window.FGE_MEDIA;
	if ( ! C || ! C.partnerId ) {
		return;
	}
	var L = C.limits, T = C.i18n;

	var state = {
		gallery: ( C.gallery || [] ).slice(),
		logo: C.logo || null,
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
	function pickFiles( cb ) {
		var inp = document.createElement( 'input' );
		inp.type = 'file';
		inp.accept = L.mimes.join( ',' );
		inp.multiple = true;
		inp.style.display = 'none';
		document.body.appendChild( inp );
		inp.addEventListener( 'change', function () {
			var files = Array.prototype.slice.call( inp.files || [] );
			document.body.removeChild( inp );
			if ( files.length ) { cb( files ); }
		} );
		inp.click();
	}

	var ICON_CAMERA = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 4l1.5 2.5h3A1.5 1.5 0 0 1 20.5 8v9A1.5 1.5 0 0 1 19 18.5H5A1.5 1.5 0 0 1 3.5 17V8A1.5 1.5 0 0 1 5 6.5h3L9.5 4z"/><circle cx="12" cy="12" r="3.2"/></svg>';
	var ICON_DOTS = '<svg viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="19" cy="12" r="1.7"/></svg>';

	// ── Gallery rendering ─────────────────────────────────────────────────────
	var root = document.querySelector( '[data-fge-gallery]' );

	function render() {
		if ( ! root ) { return; }
		root.innerHTML = '';
		if ( ! state.gallery.length ) {
			root.appendChild( renderEmpty() );
			return;
		}
		root.appendChild( renderHint() );
		root.appendChild( renderGrid() );
	}

	function renderEmpty() {
		var box = el( 'div', 'fge-gallery-empty' );
		box.appendChild( el( 'div', 'fge-gallery-empty-ico', ICON_CAMERA ) );
		var btn = el( 'button', 'fge-btn fge-btn-light', T.addPhotos );
		btn.type = 'button';
		btn.addEventListener( 'click', function () { pickFiles( openModal ); } );
		box.appendChild( btn );
		return box;
	}

	function renderHint() {
		var need = L.recommendedMin - state.gallery.length;
		var wrap = el( 'div', 'fge-gallery-hints' );
		wrap.appendChild( el( 'p', 'fge-gallery-hint', T.reorderHint ) );
		var rec = el( 'p', 'fge-gallery-rec' );
		if ( need > 0 ) {
			rec.textContent = T.recommend.replace( '%d', need );
			rec.dataset.kind = 'warn';
		} else {
			rec.textContent = T.recommendOk;
			rec.dataset.kind = 'ok';
		}
		wrap.appendChild( rec );
		return wrap;
	}

	function renderGrid() {
		var grid = el( 'div', 'fge-gallery-grid' );
		state.gallery.forEach( function ( photo, i ) {
			grid.appendChild( renderItem( photo, i === 0 ) );
		} );
		var add = el( 'button', 'fge-gallery-add' );
		add.type = 'button';
		add.innerHTML = '<span class="fge-gallery-add-plus">+</span><span>' + T.addMore + '</span>';
		add.addEventListener( 'click', function () { pickFiles( openModal ); } );
		grid.appendChild( add );
		return grid;
	}

	function renderItem( photo, isCover ) {
		var item = el( 'div', 'fge-gallery-item' + ( isCover ? ' is-cover' : '' ) );
		item.setAttribute( 'draggable', 'true' );
		item.dataset.id = photo.id;
		item.style.backgroundImage = "url('" + ( photo.large || photo.thumb || photo.full ) + "')";

		if ( isCover ) {
			item.appendChild( el( 'span', 'fge-gallery-badge', T.coverBadge ) );
		}

		var menuBtn = el( 'button', 'fge-gallery-menu-btn', ICON_DOTS );
		menuBtn.type = 'button';
		menuBtn.addEventListener( 'click', function ( e ) {
			e.stopPropagation();
			toggleMenu( item, photo, isCover );
		} );
		item.appendChild( menuBtn );

		// Drag & drop reorder.
		item.addEventListener( 'dragstart', function () { state.dragId = photo.id; item.classList.add( 'dragging' ); } );
		item.addEventListener( 'dragend', function () { item.classList.remove( 'dragging' ); } );
		item.addEventListener( 'dragover', function ( e ) { e.preventDefault(); } );
		item.addEventListener( 'drop', function ( e ) {
			e.preventDefault();
			moveBefore( state.dragId, photo.id );
		} );
		return item;
	}

	function toggleMenu( item, photo, isCover ) {
		var open = item.querySelector( '.fge-gallery-pop' );
		closeMenus();
		if ( open ) { return; }
		var pop = el( 'div', 'fge-gallery-pop' );
		if ( ! isCover ) {
			var mk = el( 'button', 'fge-gallery-pop-item', T.menuCover );
			mk.type = 'button';
			mk.addEventListener( 'click', function () { closeMenus(); setCover( photo.id ); } );
			pop.appendChild( mk );
		}
		var rm = el( 'button', 'fge-gallery-pop-item is-danger', T.menuRemove );
		rm.type = 'button';
		rm.addEventListener( 'click', function () { closeMenus(); removePhoto( photo.id ); } );
		pop.appendChild( rm );
		item.appendChild( pop );
	}
	function closeMenus() {
		var ps = root.querySelectorAll( '.fge-gallery-pop' );
		Array.prototype.forEach.call( ps, function ( p ) { p.parentNode.removeChild( p ); } );
	}
	document.addEventListener( 'click', closeMenus );

	// ── Gallery actions ─────────────────────────────────────────────────────
	function commitOrder() {
		var ids = state.gallery.map( function ( p ) { return p.id; } );
		render();
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
			render();
		} ).catch( function ( err ) { alert( '' + err ); } );
	}
	function idx( id ) {
		for ( var i = 0; i < state.gallery.length; i++ ) { if ( state.gallery[ i ].id === id ) { return i; } }
		return -1;
	}

	// ── Upload modal ──────────────────────────────────────────────────────────
	function openModal( files ) {
		var pending = []; // { file, url, status: 'queued'|'up'|'done'|'error', error }
		var uploading = false;

		var backdrop = el( 'div', 'fge-gm-backdrop' );
		var modal = el( 'div', 'fge-gm-modal' );
		backdrop.appendChild( modal );

		var head = el( 'div', 'fge-gm-head' );
		var closeBtn = el( 'button', 'fge-gm-x', '✕' ); closeBtn.type = 'button';
		var title = el( 'div', 'fge-gm-title' );
		title.innerHTML = '<strong>' + T.modalTitle + '</strong><span class="fge-gm-sub"></span>';
		var addBtn = el( 'button', 'fge-gm-add', '+' ); addBtn.type = 'button';
		head.appendChild( closeBtn ); head.appendChild( title ); head.appendChild( addBtn );

		var grid = el( 'div', 'fge-gm-grid' );
		var foot = el( 'div', 'fge-gm-foot' );
		var cancel = el( 'button', 'fge-btn fge-btn-quiet', T.cancel ); cancel.type = 'button';
		var go = el( 'button', 'fge-btn fge-btn-dark', T.upload ); go.type = 'button';
		foot.appendChild( cancel ); foot.appendChild( go );

		modal.appendChild( head ); modal.appendChild( grid ); modal.appendChild( foot );
		document.body.appendChild( backdrop );

		function close() { if ( backdrop.parentNode ) { backdrop.parentNode.removeChild( backdrop ); } }
		function sub() {
			var done = pending.filter( function ( p ) { return p.status === 'done'; } ).length;
			var s = title.querySelector( '.fge-gm-sub' );
			s.textContent = uploading
				? T.uploaded.replace( '%1$d', done ).replace( '%2$d', pending.length )
				: T.selected.replace( '%d', pending.length );
		}
		function addFiles( list ) {
			list.forEach( function ( file ) {
				var err = checkFile( file, L.gallery );
				pending.push( { file: file, url: URL.createObjectURL( file ), status: err ? 'error' : 'queued', error: err } );
			} );
			renderGM();
		}
		function renderGM() {
			grid.innerHTML = '';
			pending.forEach( function ( p, i ) {
				var cell = el( 'div', 'fge-gm-item' + ( p.status === 'error' ? ' is-error' : '' ) );
				cell.style.backgroundImage = "url('" + p.url + "')";
				if ( ! uploading && p.status !== 'done' ) {
					var x = el( 'button', 'fge-gm-rm', '🗑'); x.type = 'button';
					x.addEventListener( 'click', function () { pending.splice( i, 1 ); renderGM(); } );
					cell.appendChild( x );
				}
				if ( p.status === 'up' ) { cell.appendChild( el( 'span', 'fge-gm-status fge-gm-spin' ) ); }
				if ( p.status === 'done' ) { cell.appendChild( el( 'span', 'fge-gm-status fge-gm-check', '✓' ) ); }
				if ( p.status === 'error' ) { cell.appendChild( el( 'span', 'fge-gm-err', p.error ) ); }
				grid.appendChild( cell );
			} );
			sub();
			go.disabled = uploading || ! pending.some( function ( p ) { return p.status === 'queued'; } );
		}
		function doUpload() {
			uploading = true; renderGM();
			var queue = pending.filter( function ( p ) { return p.status === 'queued'; } );
			var chain = Promise.resolve();
			queue.forEach( function ( p ) {
				chain = chain.then( function () {
					p.status = 'up'; renderGM();
					return uploadPhoto( p.file ).then( function ( res ) {
						p.status = 'done';
						state.gallery = res.gallery || state.gallery;
						renderGM();
					} ).catch( function ( err ) {
						p.status = 'error'; p.error = '' + err; renderGM();
					} );
				} );
			} );
			chain.then( function () {
				uploading = false;
				render();
				if ( pending.every( function ( p ) { return p.status === 'done'; } ) ) { close(); }
				else { renderGM(); }
			} );
		}

		closeBtn.addEventListener( 'click', close );
		cancel.addEventListener( 'click', close );
		backdrop.addEventListener( 'click', function ( e ) { if ( e.target === backdrop && ! uploading ) { close(); } } );
		addBtn.addEventListener( 'click', function () { pickFiles( addFiles ); } );
		go.addEventListener( 'click', doUpload );

		addFiles( files );
	}

	// ── Logo uploader ─────────────────────────────────────────────────────────
	function renderLogo() {
		var host = document.querySelector( '[data-fge-logo]' );
		if ( ! host ) { return; }
		host.innerHTML = '';
		host.appendChild( el( 'div', 'fge-logo-label', T.logoLabel ) );
		host.appendChild( el( 'div', 'fge-logo-hint', T.logoHint ) );
		var zone = el( 'div', 'fge-logo-zone' + ( state.logo ? ' filled' : '' ) );
		if ( state.logo ) {
			var prev = el( 'span', 'fge-logo-preview' );
			prev.style.backgroundImage = "url('" + ( state.logo.thumb || state.logo.full ) + "')";
			zone.appendChild( prev );
		}
		var btn = el( 'button', 'fge-btn fge-btn-light', state.logo ? T.logoReplace : T.logoChoose );
		btn.type = 'button';
		btn.addEventListener( 'click', function () {
			pickFiles( function ( files ) {
				var f = files[ 0 ];
				var err = checkFile( f, L.logo );
				if ( err ) { alert( err ); return; }
				btn.disabled = true;
				uploadLogo( f ).then( function ( res ) {
					state.logo = res.logo; renderLogo();
				} ).catch( function ( e ) { alert( '' + e ); btn.disabled = false; } );
			} );
		} );
		zone.appendChild( btn );
		host.appendChild( zone );
	}

	// ── Init ────────────────────────────────────────────────────────────────
	renderLogo();
	render();
} )();
