/**
 * City-Seiten: Google-Karte mit den Golfplätzen der Region (Verzeichnisdaten).
 * Wird Klaro-gegated geladen; Google ruft nach Einwilligung fgeCityMapInit auf.
 * Partnerplätze = große Marken-blaue Marker, übrige = dezente Navy-Punkte.
 */
window.fgeCityMapInit = function () {
	var el = document.getElementById( 'fge-city-map' );
	if ( ! el || ! window.google || ! google.maps || ! window.FGE_CITY_MAP ) {
		return;
	}
	var data   = window.FGE_CITY_MAP;
	var places = data.places || [];
	el.innerHTML = ''; // Consent-Platzhalter raus

	var map = new google.maps.Map( el, {
		center: { lat: Number( data.lat ), lng: Number( data.lng ) },
		zoom: 10,
		mapTypeControl: false,
		streetViewControl: false,
		fullscreenControl: true,
	} );
	var bounds = new google.maps.LatLngBounds();
	var info   = new google.maps.InfoWindow();

	/* Pin-Form (Tropfen mit Innenkreis, Material-Style); Anker = Pin-Spitze. */
	var PIN = 'M12 2C7.6 2 4 5.6 4 10c0 5.4 8 12.7 8 12.7S20 15.4 20 10c0-4.4-3.6-8-8-8zm0 11a3 3 0 1 1 0-6 3 3 0 0 1 0 6z';

	places.forEach( function ( p ) {
		var pos = { lat: Number( p.lat ), lng: Number( p.lng ) };
		var m   = new google.maps.Marker( {
			map: map,
			position: pos,
			title: p.name,
			icon: {
				path: PIN,
				scale: p.partner ? 1.9 : 1.15,
				anchor: new google.maps.Point( 12, 22.7 ),
				fillColor: p.partner ? '#4279D1' : '#20294D',
				fillOpacity: p.partner ? 1 : 0.6,
				strokeColor: '#FFFFFF',
				strokeWeight: p.partner ? 1.6 : 1,
			},
			zIndex: p.partner ? 20 : 10,
		} );
		m.addListener( 'click', function () {
			// DOM-basiert (kein HTML-String) → Namen aus der DB sind automatisch escaped.
			var box = document.createElement( 'div' );
			box.style.cssText = 'font:600 14px/1.4 Roboto,sans-serif;color:#0E1310;max-width:230px';
			box.appendChild( document.createTextNode( p.name ) );
			if ( p.partner ) {
				var badge = document.createElement( 'span' );
				badge.textContent = 'Partnerplatz';
				badge.style.cssText = 'background:#DCE7F7;color:#283A6E;border-radius:999px;padding:1px 7px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;margin-left:6px;vertical-align:middle';
				box.appendChild( badge );
			}
			var meta = document.createElement( 'div' );
			meta.textContent = p.meta || '';
			meta.style.cssText = 'font-weight:400;color:#6C736E;font-size:12px;margin-top:2px';
			box.appendChild( meta );
			info.setContent( box );
			info.open( map, m );
		} );
		bounds.extend( pos );
	} );

	if ( places.length > 1 ) {
		map.fitBounds( bounds, 40 );
	}
};
