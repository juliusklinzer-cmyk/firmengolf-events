/**
 * Weihnachtsfeier-Seite: Google-Karte mit allen Golfsimulatoren in Deutschland
 * (Daten aus includes/simulatoren-data.php). Klaro-gegated, Google ruft nach der
 * Einwilligung fgeSimMapInit auf. Alle Simulatoren = Orange; Anlagen, die bei
 * Firmengolf Events anbieten, groß mit Ring. Klick öffnet ein InfoWindow mit Ort,
 * Boxen, System und Website.
 */
function fgeSimMakePin( color, size, ring ) {
	// Weißer Rand plus feine dunkle Kontur: hebt den Pin auch vom hellen Kartengrün ab.
	var svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40">'
		+ ( ring ? '<circle cx="20" cy="20" r="19" fill="none" stroke="' + color + '" stroke-opacity="0.45" stroke-width="2"/>' : '' )
		+ '<circle cx="20" cy="20" r="16" fill="none" stroke="#0E1310" stroke-opacity="0.22" stroke-width="1.2"/>'
		+ '<circle cx="20" cy="20" r="14.5" fill="' + color + '" stroke="#FFFFFF" stroke-width="2.5"/>'
		+ '<path d="M17 27.5V12.5" stroke="#FFFFFF" stroke-width="2.2" stroke-linecap="round"/>'
		+ '<path d="M18.2 13l7.3 2.8-7.3 2.8z" fill="#FFFFFF"/>'
		+ '</svg>';
	return {
		url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent( svg ),
		scaledSize: new google.maps.Size( size, size ),
		anchor: new google.maps.Point( size / 2, size / 2 ),
	};
}

function fgeSimEsc( s ) {
	return String( s || '' ).replace( /[&<>"']/g, function ( c ) {
		return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
	} );
}

window.fgeSimMapInit = function () {
	var el = document.getElementById( 'fge-sim-map' );
	if ( ! el || ! window.google || ! google.maps || ! window.FGE_SIM_MAP ) {
		return;
	}
	var data   = window.FGE_SIM_MAP;
	var places = data.places || [];
	el.innerHTML = ''; // Consent-Platzhalter raus

	var map = new google.maps.Map( el, {
		center: { lat: 51.1, lng: 10.4 },
		zoom: 6,
		mapTypeControl: false,
		streetViewControl: false,
		fullscreenControl: true,
	} );
	var bounds = new google.maps.LatLngBounds();
	var info   = new google.maps.InfoWindow();
	// Farblogik (Julius, 07.09.): Simulatoren immer Orange (kräftig, damit sie auf dem
	// Kartengrün stehen), bei Firmengolf buchbare groß mit Ring. Golfplätze bleiben Blau.
	// Pins wachsen mit dem Zoom (Julius, 07.09.: nah dran waren sie kaum zu sehen).
	var ORANGE = '#D9731A', BLUE = '#4279D1';
	function pinSize( zoom ) { return Math.max( 30, Math.min( 56, 30 + ( zoom - 6 ) * 4.5 ) ); }
	function pinsFor( zoom ) {
		var s = pinSize( zoom );
		return { featured: fgeSimMakePin( ORANGE, s * 1.5, true ), plain: fgeSimMakePin( ORANGE, s, false ), course: fgeSimMakePin( BLUE, s * 1.25, false ) };
	}
	var pins    = pinsFor( map.getZoom() || 6 );
	var markers = [];
	function iconFor( p, set ) { return p.course ? set.course : ( p.featured ? set.featured : set.plain ); }
	map.addListener( 'zoom_changed', function () {
		var set = pinsFor( map.getZoom() || 6 );
		markers.forEach( function ( e ) { e.marker.setIcon( iconFor( e.p, set ) ); } );
	} );

	places.forEach( function ( p ) {
		var pos    = { lat: Number( p.lat ), lng: Number( p.lng ) };
		var marker = new google.maps.Marker( {
			position: pos,
			map: map,
			title: p.name,
			icon: iconFor( p, pins ),
			zIndex: p.featured || p.course ? 20 : 10,
		} );
		markers.push( { marker: marker, p: p } );
		bounds.extend( pos );
		marker.addListener( 'click', function () {
			var html = '<div class="fge-sim-info">'
				+ '<strong>' + fgeSimEsc( p.name ) + '</strong>'
				+ '<div class="fge-sim-info-meta">' + fgeSimEsc( p.ort ) + ( p.meta ? ' · ' + fgeSimEsc( p.meta ) : '' ) + ( p.approx ? ' · Lage ungefähr' : '' ) + '</div>'
				+ ( p.course ? '<div class="fge-sim-info-tag">Weihnachtsfeier buchbar</div>' : ( p.featured ? '<div class="fge-sim-info-tag">Bei Firmengolf buchbar</div>' : ( p.event ? '<div class="fge-sim-info-tag fge-sim-info-tag--soft">Eventlocation</div>' : '' ) ) )
				+ '<div class="fge-sim-info-links">'
				+ ( p.course
					? '<a href="' + fgeSimEsc( p.website ) + '">Zum Angebot</a>'
					: ( p.website ? '<a href="' + fgeSimEsc( p.website ) + '" target="_blank" rel="noopener nofollow">Website</a>' : '' )
					  + '<a href="' + fgeSimEsc( data.anfrage || '#anfrage' ) + '" data-sim-request="' + fgeSimEsc( p.name ) + '" data-sim-ort="' + fgeSimEsc( p.ort ) + '">Hier feiern, anfragen</a>' )
				+ '</div></div>';
			info.setContent( html );
			info.open( { map: map, anchor: marker } );
		} );
	} );
	if ( data.user && data.user.lat && data.user.lng ) {
		// Standort des Besuchers: eigener Marker, Ausschnitt um die nächsten Anlagen
		var u = { lat: Number( data.user.lat ), lng: Number( data.user.lng ) };
		var youSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40"><circle cx="20" cy="20" r="18" fill="#4279D1" fill-opacity="0.18"/><circle cx="20" cy="20" r="8" fill="#4279D1" stroke="#FFFFFF" stroke-width="3"/></svg>';
		new google.maps.Marker( {
			position: u, map: map, title: 'Ihr seid hier', zIndex: 30,
			icon: { url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent( youSvg ), scaledSize: new google.maps.Size( 40, 40 ), anchor: new google.maps.Point( 20, 20 ) },
		} );
		var near = places.map( function ( p ) {
			var dLat = ( Number( p.lat ) - u.lat ) * 111, dLng = ( Number( p.lng ) - u.lng ) * 111 * Math.cos( u.lat * Math.PI / 180 );
			return { p: p, d: Math.sqrt( dLat * dLat + dLng * dLng ) };
		} ).sort( function ( a, b ) { return a.d - b.d; } ).slice( 0, Math.max( 1, Number( data.minNear ) || 5 ) );
		var nb = new google.maps.LatLngBounds();
		nb.extend( u );
		near.forEach( function ( n ) { nb.extend( { lat: Number( n.p.lat ), lng: Number( n.p.lng ) } ); } );
		map.fitBounds( nb, 60 );
		google.maps.event.addListenerOnce( map, 'idle', function () { if ( map.getZoom() > 12 ) { map.setZoom( 12 ); } } );
	} else if ( places.length > 1 ) {
		map.fitBounds( bounds, 40 );
	}
	// InfoWindow-Link → Anfrage-Dialog mit vorausgewählter Location (template-format.php)
	el.addEventListener( 'click', function ( e ) {
		var a = e.target.closest( '[data-sim-request]' );
		if ( a && window.fgeSimRequest ) {
			e.preventDefault();
			window.fgeSimRequest( a.getAttribute( 'data-sim-request' ), a.getAttribute( 'data-sim-ort' ) );
		}
	} );
};
