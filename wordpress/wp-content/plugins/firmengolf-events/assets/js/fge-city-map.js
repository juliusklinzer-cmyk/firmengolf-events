/**
 * City-Seiten: Google-Karte mit den Golfplätzen der Region (Verzeichnisdaten).
 * Wird Klaro-gegated geladen; Google ruft nach Einwilligung fgeCityMapInit auf.
 * Partnerplätze = große Marken-blaue Marker, übrige = dezente Navy-Punkte.
 */
/* Verbindung Karte ↔ Kachel-Liste: Registry wird von fgeCityMapInit gefüllt. */
var fgeGpMap = null;
var fgeGpMarkers = {};
var fgeGpOpenMarker = null;

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

	/* Pin = Kreis mit Golffahne (Inline-SVG): Grün-Türkis für alle Plätze,
	   Markenblau für Partner (Julius, 2026-07-03). */
	function makePin( color, size ) {
		var svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">'
			+ '<circle cx="16" cy="16" r="14.5" fill="' + color + '" stroke="#FFFFFF" stroke-width="2.5"/>'
			+ '<path d="M13 23.5V8.5" stroke="#FFFFFF" stroke-width="2.2" stroke-linecap="round"/>'
			+ '<path d="M14.2 9l7.3 2.8-7.3 2.8z" fill="#FFFFFF"/>'
			+ '</svg>';
		return {
			url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent( svg ),
			scaledSize: new google.maps.Size( size, size ),
			anchor: new google.maps.Point( size / 2, size / 2 ),
		};
	}
	var pinPartner = makePin( '#4279D1', 40 );
	var pinDefault = makePin( '#00C896', 26 );

	places.forEach( function ( p ) {
		var pos = { lat: Number( p.lat ), lng: Number( p.lng ) };
		var m   = new google.maps.Marker( {
			map: map,
			position: pos,
			title: p.name,
			icon: p.partner ? pinPartner : pinDefault,
			zIndex: p.partner ? 20 : 10,
		} );
		var openInfo = function () {
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
		};
		m.addListener( 'click', function () {
			openInfo();
			fgeGpHighlightTile( p.id );
		} );
		fgeGpMarkers[ String( p.id ) ] = { marker: m, pos: pos, openInfo: openInfo };
		bounds.extend( pos );
	} );

	fgeGpMap = map;
	if ( places.length > 1 ) {
		map.fitBounds( bounds, 40 );
	}
};

/* Pin-Klick → zugehörige Kachel aufleuchten lassen und ins Bild scrollen. */
function fgeGpHighlightTile( id ) {
	var row = document.querySelector( '.gpd-row[data-gp-id="' + id + '"]' );
	if ( ! row ) {
		return;
	}
	document.querySelectorAll( '.gpd-row--active' ).forEach( function ( r ) {
		r.classList.remove( 'gpd-row--active' );
	} );
	row.classList.add( 'gpd-row--active' );
	row.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
	setTimeout( function () {
		row.classList.remove( 'gpd-row--active' );
	}, 3500 );
}

/* Kachel-Klick → Karte schwenkt zum Pin, Info öffnet, Pin hüpft kurz.
   Vor der Maps-Einwilligung scrollt der Klick nur zum Consent-Platzhalter. */
function fgeGpFocusMarker( id ) {
	var mapEl = document.getElementById( 'fge-city-map' );
	if ( mapEl ) {
		mapEl.scrollIntoView( { behavior: 'smooth', block: 'center' } );
	}
	var entry = fgeGpMarkers[ String( id ) ];
	if ( ! entry || ! fgeGpMap ) {
		return;
	}
	fgeGpMap.panTo( entry.pos );
	if ( fgeGpMap.getZoom() < 12 ) {
		fgeGpMap.setZoom( 12 );
	}
	entry.openInfo();
	entry.marker.setAnimation( google.maps.Animation.BOUNCE );
	setTimeout( function () {
		entry.marker.setAnimation( null );
	}, 1500 );
}

document.addEventListener( 'click', function ( ev ) {
	var row = ev.target.closest ? ev.target.closest( '.gpd-row[data-gp-id]' ) : null;
	if ( row ) {
		fgeGpFocusMarker( row.getAttribute( 'data-gp-id' ) );
	}
} );
document.addEventListener( 'keydown', function ( ev ) {
	if ( ev.key !== 'Enter' && ev.key !== ' ' ) {
		return;
	}
	var row = ev.target && ev.target.classList && ev.target.classList.contains( 'gpd-row' ) ? ev.target : null;
	if ( row && row.hasAttribute( 'data-gp-id' ) ) {
		ev.preventDefault();
		fgeGpFocusMarker( row.getAttribute( 'data-gp-id' ) );
	}
} );
