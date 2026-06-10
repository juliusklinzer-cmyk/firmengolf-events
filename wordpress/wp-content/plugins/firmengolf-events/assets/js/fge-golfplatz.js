/* Golf-course page gallery lightbox. Opened by [data-gp-open]; images from data-images. */
( function () {
	'use strict';
	var box = document.querySelector( '[data-gp-lightbox]' );
	var openers = document.querySelectorAll( '[data-gp-open]' );
	if ( ! box || ! openers.length ) { return; }

	var images = [];
	try { images = JSON.parse( box.getAttribute( 'data-images' ) || '[]' ); } catch ( e ) {}
	if ( ! images.length ) { return; }

	var img  = box.querySelector( '[data-gp-img]' );
	var cur  = box.querySelector( '[data-gp-cur]' );
	var name = box.querySelector( '[data-gp-name]' );
	var i = 0;

	function show( n ) {
		i = ( n + images.length ) % images.length;
		var it = images[ i ];
		var url = ( it && typeof it === 'object' ) ? it.url : it;
		img.src = url;
		img.alt = ( it && it.name ) ? it.name : '';
		if ( name ) { name.textContent = ( it && it.name ) ? it.name : ''; }
		if ( cur ) { cur.textContent = String( i + 1 ); }
	}
	function open( start ) {
		show( start || 0 );
		box.hidden = false;
		document.body.style.overflow = 'hidden';
	}
	function close() {
		box.hidden = true;
		document.body.style.overflow = '';
	}

	openers.forEach( function ( b ) { b.addEventListener( 'click', function () { open( 0 ); } ); } );
	box.querySelector( '[data-gp-next]' ).addEventListener( 'click', function ( e ) { e.stopPropagation(); show( i + 1 ); } );
	box.querySelector( '[data-gp-prev]' ).addEventListener( 'click', function ( e ) { e.stopPropagation(); show( i - 1 ); } );
	box.querySelector( '[data-gp-close]' ).addEventListener( 'click', close );
	box.addEventListener( 'click', function ( e ) { if ( e.target === box ) { close(); } } );
	document.addEventListener( 'keydown', function ( e ) {
		if ( box.hidden ) { return; }
		if ( e.key === 'Escape' ) { close(); }
		else if ( e.key === 'ArrowRight' ) { show( i + 1 ); }
		else if ( e.key === 'ArrowLeft' ) { show( i - 1 ); }
	} );
} )();
