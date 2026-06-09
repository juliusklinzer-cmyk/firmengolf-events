/**
 * Event image picker — select photos for one event from the partner's library (FGE_MEDIA.gallery).
 * Writes the ordered selection (cover first) + cover id into hidden inputs submitted with the
 * event form. "+ Foto hochladen" pushes a new photo into the partner library via the gallery REST.
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

	var library  = ( C.gallery || [] ).slice();          // [{id,thumb,...}]
	var selected = csvToIds( picker.getAttribute( 'data-selected' ) ); // ordered ids
	var cover    = parseInt( picker.getAttribute( 'data-cover' ) || '0', 10 ) || 0;

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
		// Hidden ids: cover first, then the rest in selection order.
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
		sync(); render();
	}
	function setCover( id ) {
		if ( selected.indexOf( id ) === -1 ) { selected.push( id ); }
		cover = id;
		sync(); render();
	}

	function render() {
		grid.innerHTML = '';

		if ( ! library.length ) {
			grid.appendChild( el( 'p', 'fge-picker-empty', T.pickEmpty ) );
		}

		library.forEach( function ( p ) {
			var isSel = selected.indexOf( p.id ) !== -1;
			var isCov = cover === p.id;
			var tile = el( 'div', 'fge-picker-item' + ( isSel ? ' is-selected' : '' ) + ( isCov ? ' is-cover' : '' ) );
			tile.style.backgroundImage = "url('" + ( p.large || p.thumb || p.full ) + "')";

			tile.appendChild( el( 'span', 'fge-picker-check', isSel ? '✓' : '' ) );
			if ( isCov ) { tile.appendChild( el( 'span', 'fge-picker-badge', T.pickCoverBadge ) ); }

			tile.addEventListener( 'click', function () { toggle( p.id ); } );

			if ( isSel && ! isCov ) {
				var cb = el( 'button', 'fge-picker-setcover', T.pickCover );
				cb.type = 'button';
				cb.addEventListener( 'click', function ( e ) { e.stopPropagation(); setCover( p.id ); } );
				tile.appendChild( cb );
			}
			grid.appendChild( tile );
		} );

		var add = el( 'button', 'fge-picker-add' );
		add.type = 'button';
		add.innerHTML = '<span class="fge-picker-add-plus">+</span><span>' + T.pickUpload + '</span>';
		add.addEventListener( 'click', pickAndUpload );
		grid.appendChild( add );
	}

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
					if ( res.photo ) { selected.push( res.photo.id ); if ( ! cover ) { cover = res.photo.id; } }
					sync(); render();
				  } ).catch( function ( err ) { alert( '' + err ); } );
			} );
		} );
		inp.click();
	}

	sync();
	render();
} )();
