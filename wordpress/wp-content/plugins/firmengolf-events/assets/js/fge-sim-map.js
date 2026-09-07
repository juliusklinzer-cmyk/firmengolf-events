/**
 * Weihnachtsfeier-Seite: Google-Karte mit allen Golfsimulatoren in Deutschland
 * (Daten aus includes/simulatoren-data.php). Klaro-gegated, Google ruft nach der
 * Einwilligung fgeSimMapInit auf. Alle Simulatoren = Orange; Anlagen, die bei
 * Firmengolf Events anbieten, groß mit Ring. Klick öffnet ein InfoWindow mit Ort,
 * Boxen, System und Website.
 */
function fgeSimMakePin( color, size, ring ) {
	var svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40">'
		+ ( ring ? '<circle cx="20" cy="20" r="19" fill="none" stroke="' + color + '" stroke-opacity="0.4" stroke-width="2"/>' : '' )
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
	// Farblogik (Julius, 07.09.): Simulatoren immer Orange, bei Firmengolf buchbare groß mit Ring.
	// Golfplätze bleiben Blau (Partner) und Grün (übrige), siehe fge-city-map.js.
	var pins   = { featured: fgeSimMakePin( '#E08A2B', 46, true ), plain: fgeSimMakePin( '#E08A2B', 28, false ) };

	places.forEach( function ( p ) {
		var pos    = { lat: Number( p.lat ), lng: Number( p.lng ) };
		var marker = new google.maps.Marker( {
			position: pos,
			map: map,
			title: p.name,
			icon: p.featured ? pins.featured : pins.plain,
			zIndex: p.featured ? 20 : 10,
		} );
		bounds.extend( pos );
		marker.addListener( 'click', function () {
			var html = '<div class="fge-sim-info">'
				+ '<strong>' + fgeSimEsc( p.name ) + '</strong>'
				+ '<div class="fge-sim-info-meta">' + fgeSimEsc( p.ort ) + ( p.meta ? ' · ' + fgeSimEsc( p.meta ) : '' ) + ( p.approx ? ' · Lage ungefähr' : '' ) + '</div>'
				+ ( p.featured ? '<div class="fge-sim-info-tag">Bei Firmengolf buchbar</div>' : ( p.event ? '<div class="fge-sim-info-tag fge-sim-info-tag--soft">Eventlocation</div>' : '' ) )
				+ '<div class="fge-sim-info-links">'
				+ ( p.website ? '<a href="' + fgeSimEsc( p.website ) + '" target="_blank" rel="noopener nofollow">Website</a>' : '' )
				+ '<a href="' + fgeSimEsc( data.anfrage || '#anfrage' ) + '">Hier feiern, anfragen</a>'
				+ '</div></div>';
			info.setContent( html );
			info.open( { map: map, anchor: marker } );
		} );
	} );
	if ( places.length > 1 ) {
		map.fitBounds( bounds, 40 );
	}
};
