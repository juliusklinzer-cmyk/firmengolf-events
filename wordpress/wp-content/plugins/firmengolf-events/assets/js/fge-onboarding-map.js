/**
 * Onboarding location picker (Google Maps).
 * Loaded only on the location slide when FGE_GMAPS_API_KEY is configured.
 * The Maps API script calls window.fgeObMapInit when ready (callback=fgeObMapInit).
 *
 * Flow: the place search fills address fields, Bundesland, pin and place_id.
 * Typing the address manually geocodes it and drops the pin automatically —
 * unless the user has placed the pin by hand (drag/click), which locks it.
 */
(function () {
	'use strict';

	function byId(id) { return document.getElementById(id); }

	// Google long_name → option keys of the fge_federal_state select.
	var STATE_KEYS = {
		'Baden-Württemberg': 'baden_wuerttemberg',
		'Bayern': 'bayern',
		'Berlin': 'berlin',
		'Brandenburg': 'brandenburg',
		'Bremen': 'bremen',
		'Hamburg': 'hamburg',
		'Hessen': 'hessen',
		'Mecklenburg-Vorpommern': 'mecklenburg_vorpommern',
		'Niedersachsen': 'niedersachsen',
		'Nordrhein-Westfalen': 'nordrhein_westfalen',
		'Rheinland-Pfalz': 'rheinland_pfalz',
		'Saarland': 'saarland',
		'Sachsen': 'sachsen',
		'Sachsen-Anhalt': 'sachsen_anhalt',
		'Schleswig-Holstein': 'schleswig_holstein',
		'Thüringen': 'thueringen'
	};

	function fgeObMapInit() {
		var mapEl = byId('fge_map');
		if (!mapEl || typeof google === 'undefined' || !google.maps) { return; }

		var saved = window.FGE_OB_MAP || {};
		var hasCoords = !!(saved.lat && saved.lng);
		var center = hasCoords
			? { lat: parseFloat(saved.lat), lng: parseFloat(saved.lng) }
			: { lat: 51.1657, lng: 10.4515 }; // Geographic centre of Germany.

		var map = new google.maps.Map(mapEl, {
			center: center,
			zoom: hasCoords ? 16 : 6,
			mapTypeControl: false,
			streetViewControl: false,
			fullscreenControl: false,
			gestureHandling: 'cooperative'
		});

		var marker = new google.maps.Marker({
			map: map,
			position: center,
			draggable: true,
			visible: hasCoords
		});

		var latEl = byId('fge_latitude');
		var lngEl = byId('fge_longitude');
		var placeEl = byId('fge_google_place_id');

		// Once the user positions the pin by hand, typing in the address
		// fields must not move it again (it may sit on the clubhouse door).
		var pinLocked = false;

		function setPin(lat, lng, recenter) {
			marker.setPosition({ lat: lat, lng: lng });
			marker.setVisible(true);
			if (latEl) { latEl.value = lat.toFixed(7); }
			if (lngEl) { lngEl.value = lng.toFixed(7); }
			if (recenter) {
				map.panTo({ lat: lat, lng: lng });
				if (map.getZoom() < 15) { map.setZoom(16); }
			}
		}

		marker.addListener('dragend', function (e) {
			pinLocked = true;
			setPin(e.latLng.lat(), e.latLng.lng(), false);
		});
		map.addListener('click', function (e) {
			pinLocked = true;
			setPin(e.latLng.lat(), e.latLng.lng(), false);
		});

		// ── 1) Place search: fills address, Bundesland, pin and place_id ──
		var search = byId('fge_map_search');
		if (search && google.maps.places && google.maps.places.Autocomplete) {
			var ac = new google.maps.places.Autocomplete(search, {
				fields: ['place_id', 'geometry', 'address_components'],
				componentRestrictions: { country: 'de' }
			});
			ac.addListener('place_changed', function () {
				var place = ac.getPlace();
				if (!place || !place.geometry || !place.geometry.location) { return; }
				var loc = place.geometry.location;
				pinLocked = false; // Explicit selection resets any manual pin.
				setPin(loc.lat(), loc.lng(), true);
				fillAddress(place.address_components || []);
				if (placeEl) { placeEl.value = place.place_id || ''; }
			});
			// Enter inside the search box must not submit the wizard form.
			search.addEventListener('keydown', function (e) {
				if (e.key === 'Enter') { e.preventDefault(); }
			});
		}

		// ── 2) Manual entry: geocode the typed address once it is complete ──
		var geocoder = google.maps.Geocoder ? new google.maps.Geocoder() : null;
		var debounce = null;

		function fieldVal(id) {
			var el = byId(id);
			return el ? el.value.trim() : '';
		}

		function geocodeFields() {
			if (!geocoder || pinLocked) { return; }
			var street = fieldVal('fge_street');
			var zip = fieldVal('fge_postal_code');
			var city = fieldVal('fge_city');
			if (!street || !zip || !city) { return; }
			var addr = (street + ' ' + fieldVal('fge_house_number')).trim() + ', ' + zip + ' ' + city + ', Deutschland';
			geocoder.geocode({ address: addr, region: 'de' }, function (results, status) {
				if (status !== 'OK' || !results || !results[0] || pinLocked) { return; }
				var loc = results[0].geometry.location;
				setPin(loc.lat(), loc.lng(), true);
				fillState(results[0].address_components || []);
			});
		}

		['fge_street', 'fge_house_number', 'fge_postal_code', 'fge_city'].forEach(function (id) {
			var el = byId(id);
			if (!el) { return; }
			el.addEventListener('input', function () {
				clearTimeout(debounce);
				debounce = setTimeout(geocodeFields, 900);
			});
		});

		function comp(components, type) {
			for (var i = 0; i < components.length; i++) {
				if (components[i].types.indexOf(type) >= 0) { return components[i].long_name; }
			}
			return '';
		}

		function fillState(components) {
			var key = STATE_KEYS[comp(components, 'administrative_area_level_1')] || '';
			var sel = byId('fge_federal_state');
			if (key && sel) { sel.value = key; }
		}

		function fillAddress(components) {
			var route = comp(components, 'route');
			var num = comp(components, 'street_number');
			var zip = comp(components, 'postal_code');
			var city = comp(components, 'locality') || comp(components, 'postal_town');
			if (route && byId('fge_street')) { byId('fge_street').value = route; }
			if (num && byId('fge_house_number')) { byId('fge_house_number').value = num; }
			if (zip && byId('fge_postal_code')) { byId('fge_postal_code').value = zip; }
			if (city && byId('fge_city')) { byId('fge_city').value = city; }
			fillState(components);
		}
	}

	window.fgeObMapInit = fgeObMapInit;
})();
