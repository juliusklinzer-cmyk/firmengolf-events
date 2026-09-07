/**
 * City-Seiten: Google-Karte mit den Golfplätzen der Region (Verzeichnisdaten).
 * Wird Klaro-gegated geladen; Google ruft nach Einwilligung fgeCityMapInit auf.
 * Partnerplätze = große Marken-blaue Marker, übrige = dezente Mint-Punkte.
 *
 * Zwei Modi (2026-08-20, Julius):
 *  - PANEL-Modus (City-Seiten, wenn #fge-gpx-panel existiert): Pin-Klick wählt den
 *    Platz aus (Pin wird groß mit Ring), die Anzeigetafel unter/neben der Karte
 *    zeigt ihn (Foto bei Partnern), Liste und Mobile-Pills laufen synchron.
 *    Kein Google-InfoWindow.
 *  - INFO-Modus (Event-/Partner-Seiten, kein Panel): bisheriges Verhalten mit
 *    InfoWindow, unverändert.
 */
/* Verbindung Karte ↔ Liste/Panel: Registry wird von fgeCityMapInit gefüllt. */
var fgeGpMap = null;
var fgeGpMarkers = {};
var fgeGpxSelectedId = null;

function fgeGpxPanelMode() {
	return !! document.getElementById( 'fge-gpx-panel' );
}

/* Pin = Kreis mit Golffahne (Inline-SVG): Mint für alle Plätze, Markenblau für
   Partner; die ausgewählte Variante bekommt einen Auswahl-Ring. */
function fgeGpMakePin( color, size, ring ) {
	var svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40">'
		+ ( ring ? '<circle cx="20" cy="20" r="19" fill="none" stroke="' + color + '" stroke-opacity="0.35" stroke-width="2"/>' : '' )
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

/* Kleiner Punkt (Deutschlandkarte): Mint mit weißem Rand, ohne Fahne. */
function fgeGpMakeDot( color, size ) {
	var svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><circle cx="10" cy="10" r="7.5" fill="' + color + '" stroke="#FFFFFF" stroke-width="2"/></svg>';
	return {
		url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent( svg ),
		scaledSize: new google.maps.Size( size, size ),
		anchor: new google.maps.Point( size / 2, size / 2 ),
	};
}

window.fgeCityMapInit = function () {
	var el = document.getElementById( 'fge-city-map' );
	if ( ! el || ! window.google || ! google.maps || ! window.FGE_CITY_MAP ) {
		return;
	}
	var data   = window.FGE_CITY_MAP;
	var places = data.places || [];
	// Deutschlandkarte mit allen Plätzen (Sommerfest-Seite): kleine Punkte statt
	// Fahnen, sonst überlagern sich 700 Pins zu einem Teppich.
	var dense  = !! data.dense;
	el.innerHTML = ''; // Consent-Platzhalter raus

	var map = new google.maps.Map( el, {
		center: { lat: Number( data.lat ), lng: Number( data.lng ) },
		zoom: dense ? 6 : 10,
		// Deutschlandkarte: maximal herausgezoomt = ganz Deutschland (Julius, 07.09.).
		minZoom: dense ? 6 : undefined,
		restriction: dense ? { latLngBounds: { north: 55.6, south: 47.0, west: 5.5, east: 15.5 }, strictBounds: false } : undefined,
		mapTypeControl: false,
		streetViewControl: false,
		fullscreenControl: true,
	} );
	var bounds    = new google.maps.LatLngBounds();
	var panelMode = fgeGpxPanelMode();
	var info      = panelMode ? null : new google.maps.InfoWindow();

	var pins = {
		partner:         fgeGpMakePin( '#4279D1', dense ? 30 : 40, false ),
		partnerSelected: fgeGpMakePin( '#4279D1', 54, true ),
		plain:           dense ? fgeGpMakeDot( '#00C896', 11 ) : fgeGpMakePin( '#00C896', 26, false ),
		plainSelected:   fgeGpMakePin( '#00C896', 44, true ),
		// Golfsimulatoren (Weihnachts-/Indoor-Events, Julius 07.09.): warmes Orange, ausserhalb der Tokens weil SVG-Pin.
		sim:             fgeGpMakePin( '#D9731A', 36, false ),
	};

	places.forEach( function ( p ) {
		var pos = { lat: Number( p.lat ), lng: Number( p.lng ) };
		var m   = new google.maps.Marker( {
			map: map,
			position: pos,
			title: p.name,
			icon: p.partner ? pins.partner : ( p.sim ? pins.sim : pins.plain ),
			zIndex: p.partner ? 20 : ( p.sim ? 15 : 10 ),
		} );
		var openInfo = function () {
			if ( ! info ) {
				return;
			}
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
			if ( p.sim ) {
				var sbadge = document.createElement( 'span' );
				sbadge.textContent = 'Indoor-Simulator';
				sbadge.style.cssText = 'background:#FBEBD6;color:#8A4B0A;border-radius:999px;padding:1px 7px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;margin-left:8px;vertical-align:middle';
				box.appendChild( sbadge );
			}
			var meta = document.createElement( 'div' );
			meta.textContent = p.meta || '';
			meta.style.cssText = 'font-weight:400;color:#6C736E;font-size:12px;margin-top:2px';
			box.appendChild( meta );
			if ( p.sim && p.website ) {
				var link = document.createElement( 'a' );
				link.href = p.website; link.target = '_blank'; link.rel = 'noopener nofollow'; link.textContent = 'Website';
				link.style.cssText = 'display:inline-block;margin-top:6px;font-weight:600;font-size:12px;color:#4279D1';
				box.appendChild( link );
			}
			info.setContent( box );
			info.open( map, m );
		};
		m.addListener( 'click', function () {
			if ( panelMode ) {
				fgeGpxSelect( p.id, { pan: false } );
			} else {
				openInfo();
			}
		} );
		fgeGpMarkers[ String( p.id ) ] = { marker: m, pos: pos, place: p, pins: pins, openInfo: openInfo };
		bounds.extend( pos );
	} );

	fgeGpMap = map;
	if ( places.length > 1 ) {
		map.fitBounds( bounds, 40 );
	}

	// Vorauswahl (Partnerplatz) auf der Karte markieren, sobald sie da ist.
	if ( panelMode && fgeGpxSelectedId ) {
		fgeGpxApplyPinState( fgeGpxSelectedId );
	}
};

/* Pin-Zustände: genau einer trägt die Auswahl-Optik. */
function fgeGpxApplyPinState( id ) {
	Object.keys( fgeGpMarkers ).forEach( function ( key ) {
		var e = fgeGpMarkers[ key ];
		var selected = key === String( id );
		e.marker.setIcon( e.place.partner
			? ( selected ? e.pins.partnerSelected : e.pins.partner )
			: ( selected ? e.pins.plainSelected : e.pins.plain ) );
		e.marker.setZIndex( selected ? 40 : ( e.place.partner ? 20 : 10 ) );
	} );
}

/* Zentrale Auswahl: Pin, Anzeigetafel, Liste und Pills laufen synchron. */
function fgeGpxSelect( id, opts ) {
	opts = opts || {};
	var panel = document.getElementById( 'fge-gpx-panel' );
	var data  = window.FGE_CITY_MAP || {};
	var place = null;
	( data.places || [] ).forEach( function ( p ) {
		if ( String( p.id ) === String( id ) ) {
			place = p;
		}
	} );
	if ( ! panel || ! place ) {
		return;
	}
	fgeGpxSelectedId = String( id );

	// Karte: Pin-Optik + optional hinschwenken (Listen-/Pill-Klick).
	if ( fgeGpMap && fgeGpMarkers[ String( id ) ] ) {
		fgeGpxApplyPinState( id );
		if ( opts.pan ) {
			fgeGpMap.panTo( fgeGpMarkers[ String( id ) ].pos );
			if ( fgeGpMap.getZoom() < 11 ) {
				fgeGpMap.setZoom( 11 );
			}
		}
	}

	// Anzeigetafel: kurzer Blend, dann Inhalte tauschen (Foto nur bei Partnern).
	var swap = function () {
		var media = panel.querySelector( '.gpx-panel-media' );
		var img   = panel.querySelector( '.gpx-panel-media img' );
		var name  = panel.querySelector( '.gpx-panel-name' );
		var badge = panel.querySelector( '.gpx-panel-badge' );
		var meta  = panel.querySelector( '.gpx-panel-meta' );
		var note  = panel.querySelector( '.gpx-panel-note' );
		if ( name )  { name.textContent = place.name; }
		if ( badge ) { badge.hidden = ! place.partner; }
		if ( meta )  { meta.textContent = ( place.meta || '' ) + ( place.holes ? ' · ' + ( /^[0-9+\/\s]+$/.test( place.holes ) ? place.holes + ' Löcher' : place.holes ) : '' ); }
		if ( note )  { note.textContent = place.partner
			? 'Partnerplatz im Firmengolf-Netz: Events hier organisieren wir direkt mit dem Club.'
			: 'Noch kein Partnerplatz: auf Anfrage organisieren wir euer Event auch hier.'; }
		if ( media ) {
			if ( place.partner && place.photo && img ) {
				img.src = place.photo;
				img.alt = place.name;
				media.hidden = false;
			} else {
				media.hidden = true;
			}
		}
		panel.classList.toggle( 'gpx-panel--partner', !! place.partner );
	};
	if ( panel.classList.contains( 'gpx-panel--boot' ) ) {
		// Erste Auswahl = Server-Zustand, kein Blend nötig.
		panel.classList.remove( 'gpx-panel--boot' );
		swap();
	} else {
		panel.classList.add( 'is-swapping' );
		setTimeout( function () {
			swap();
			panel.classList.remove( 'is-swapping' );
		}, 170 );
	}

	// Liste (Desktop) synchron markieren. Beim Initial-Boot NICHT scrollen:
	// scrollIntoView zog sonst die ganze Seite beim Laden zur Karten-Sektion
	// (Julius, 2026-08-20), gescrollt wird nur nach echter Nutzer-Aktion.
	document.querySelectorAll( '[data-gpx-id]' ).forEach( function ( n ) {
		var active = n.getAttribute( 'data-gpx-id' ) === String( id );
		n.classList.toggle( 'is-active', active );
		n.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
		if ( active && ! opts.noScroll && n.scrollIntoView ) {
			n.scrollIntoView( { behavior: 'smooth', block: 'nearest', inline: 'nearest' } );
		}
	} );

	// Karussell (Mobil) synchron markieren und, ausser der Wechsel kam vom Wischen
	// selbst, die passende Karte in die Mitte scrollen.
	document.querySelectorAll( '[data-gpx-slide]' ).forEach( function ( s ) {
		s.classList.toggle( 'is-active', s.getAttribute( 'data-gpx-slide' ) === String( id ) );
	} );
	if ( window.fgeGpxCarouselTo ) {
		window.fgeGpxCarouselTo( id, !! opts.fromCarousel || !! opts.noScroll );
	}
}

/* Mobile-Karussell: Wischen waehlt den Platz (die Karte schwenkt mit), Pin-Tipp
   scrollt das Karussell zur Karte. Debounce statt scrollend (Safari-Support). */
(function () {
	var car = null, timer = null, suppress = false;
	function centered() {
		var mid = car.scrollLeft + car.clientWidth / 2, best = null, bd = Infinity;
		car.querySelectorAll( '[data-gpx-slide]' ).forEach( function ( s ) {
			var d = Math.abs( s.offsetLeft + s.offsetWidth / 2 - mid );
			if ( d < bd ) { bd = d; best = s; }
		} );
		return best;
	}
	function boot() {
		car = document.getElementById( 'fge-gpx-carousel' );
		if ( ! car ) { return; }
		car.addEventListener( 'scroll', function () {
			if ( suppress ) { return; }
			clearTimeout( timer );
			timer = setTimeout( function () {
				var s = centered();
				if ( s && ! s.classList.contains( 'is-active' ) ) {
					fgeGpxSelect( s.getAttribute( 'data-gpx-slide' ), { pan: true, fromCarousel: true } );
				}
			}, 150 );
		}, { passive: true } );
	}
	if ( document.readyState !== 'loading' ) { boot(); } else { document.addEventListener( 'DOMContentLoaded', boot ); }
	window.fgeGpxCarouselTo = function ( id, fromCarousel ) {
		if ( ! car || fromCarousel ) { return; }
		var s = car.querySelector( '[data-gpx-slide="' + id + '"]' );
		if ( ! s ) { return; }
		suppress = true;
		s.scrollIntoView( { behavior: 'smooth', block: 'nearest', inline: 'center' } );
		setTimeout( function () { suppress = false; }, 650 );
	};
})();

/* ── INFO-Modus (Event-/Partner-Seiten): bisheriges Verhalten ── */
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
	if ( ! ev.target.closest ) {
		return;
	}
	// PANEL-Modus: Listen-Eintrag oder Mobile-Pill.
	var item = ev.target.closest( '[data-gpx-id]' );
	if ( item ) {
		fgeGpxSelect( item.getAttribute( 'data-gpx-id' ), { pan: true } );
		return;
	}
	// INFO-Modus: alte Kachel-Liste.
	var row = ev.target.closest( '.gpd-row[data-gp-id]' );
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

/* Vorauswahl beim Laden (Partnerplatz, vom Server per data-Attribut gesetzt). */
document.addEventListener( 'DOMContentLoaded', function () {
	var panel = document.getElementById( 'fge-gpx-panel' );
	if ( panel && panel.getAttribute( 'data-initial-id' ) ) {
		fgeGpxSelect( panel.getAttribute( 'data-initial-id' ), { pan: false, noScroll: true } );
	}
} );
