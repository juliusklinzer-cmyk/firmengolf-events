/**
 * Onboarding location picker (Google Maps).
 * Loaded only on the location slide when FGE_GMAPS_API_KEY is configured.
 * The Maps API script calls window.fgeObMapInit when ready (callback=fgeObMapInit).
 */
(function () {
	'use strict';

	function byId(id) { return document.getElementById(id); }

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
			setPin(e.latLng.lat(), e.latLng.lng(), false);
		});
		map.addListener('click', function (e) {
			setPin(e.latLng.lat(), e.latLng.lng(), false);
		});

		// Address autocomplete → drops the pin and fills the address fields.
		var search = byId('fge_map_search');
		if (search && google.maps.places && google.maps.places.Autocomplete) {
			var ac = new google.maps.places.Autocomplete(search, {
				fields: ['geometry', 'address_components'],
				componentRestrictions: { country: 'de' }
			});
			ac.addListener('place_changed', function () {
				var place = ac.getPlace();
				if (!place || !place.geometry || !place.geometry.location) { return; }
				var loc = place.geometry.location;
				setPin(loc.lat(), loc.lng(), true);
				fillAddress(place.address_components || []);
			});
			// Enter inside the search box must not submit the wizard form.
			search.addEventListener('keydown', function (e) {
				if (e.key === 'Enter') { e.preventDefault(); }
			});
		}

		function comp(components, type) {
			for (var i = 0; i < components.length; i++) {
				if (components[i].types.indexOf(type) >= 0) { return components[i].long_name; }
			}
			return '';
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
		}
	}

	window.fgeObMapInit = fgeObMapInit;
})();
