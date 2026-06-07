/* eslint-disable */
// =============================================================
// Geo helper — distance search without any external API.
//  • Venue → coordinates map (for the current demo venues)
//  • PLZ (2-digit prefix) → representative coordinates for all of DE
//  • A few city names → coordinates for quick autocomplete
//  • Haversine distance (Luftlinie) — runs in microseconds in-browser
// Replace the venue map with real lat/lng once partner addresses exist.
// =============================================================
window.GEO = (function () {
  // --- demo venue coordinates (approx) ---
  const VENUES = {
    'Hamburg-Wendlohe':   [53.63, 9.95],
    'Köln-Hahnwald':      [50.87, 6.97],
    'München-Riem':       [48.13, 11.69],
    'Berlin-Wannsee':     [52.42, 13.16],
    'Petersberg, Südtirol':[46.36, 11.46],
    'Stuttgart-Solitude': [48.79, 9.10],
    'Frankfurt-Niederrad':[50.08, 8.63],
    'Schloss Lüdersburg': [53.30, 10.58],
    'München-Eichenried': [48.25, 11.83],
    'Köln-Refrath':       [50.95, 7.08],
    'GC München West':    [48.15, 11.45],
  };

  // --- PLZ 2-digit prefix → representative point (covers all of Germany) ---
  const PLZ2 = {
    '01':[51.05,13.74],'02':[51.18,14.43],'03':[51.76,14.33],'04':[51.34,12.37],
    '06':[51.48,11.97],'07':[50.93,11.59],'08':[50.72,12.49],'09':[50.83,12.92],
    '10':[52.52,13.40],'12':[52.46,13.43],'13':[52.57,13.32],'14':[52.40,13.07],
    '15':[52.34,14.55],'16':[52.84,13.80],'17':[53.43,13.07],'18':[54.09,12.13],
    '19':[53.63,11.41],'20':[53.55,9.99],'21':[53.46,10.20],'22':[53.59,9.93],
    '23':[53.87,10.69],'24':[54.32,10.12],'25':[54.13,8.86],'26':[53.14,8.21],
    '27':[53.55,8.58],'28':[53.08,8.80],'29':[52.93,10.06],'30':[52.37,9.74],
    '31':[52.15,9.95],'32':[52.20,8.62],'33':[51.72,8.76],'34':[51.31,9.49],
    '35':[50.58,8.68],'36':[50.55,9.68],'37':[51.53,9.93],'38':[52.27,10.52],
    '39':[52.13,11.63],'40':[51.23,6.78],'41':[51.19,6.44],'42':[51.26,7.15],
    '44':[51.51,7.47],'45':[51.46,7.01],'46':[51.66,6.62],'47':[51.43,6.76],
    '48':[52.10,7.62],'49':[52.28,8.05],'50':[50.94,6.96],'51':[50.95,7.01],
    '52':[50.78,6.08],'53':[50.74,7.10],'54':[49.75,6.64],'55':[49.99,8.25],
    '56':[50.36,7.59],'57':[50.87,8.02],'58':[51.36,7.47],'59':[51.57,7.69],
    '60':[50.11,8.68],'61':[50.33,8.74],'63':[50.10,8.96],'64':[49.87,8.65],
    '65':[50.08,8.24],'66':[49.24,6.99],'67':[49.44,8.16],'68':[49.49,8.47],
    '69':[49.40,8.69],'70':[48.78,9.18],'71':[48.89,9.20],'72':[48.52,9.05],
    '73':[48.71,9.65],'74':[49.14,9.22],'75':[48.89,8.70],'76':[49.01,8.40],
    '77':[48.47,7.95],'78':[47.99,8.46],'79':[47.99,7.84],'80':[48.14,11.58],
    '81':[48.11,11.60],'82':[47.95,11.34],'83':[47.86,12.12],'84':[48.57,12.16],
    '85':[48.27,11.43],'86':[48.37,10.90],'87':[47.73,10.31],'88':[47.78,9.61],
    '89':[48.40,9.99],'90':[49.45,11.08],'91':[49.59,11.01],'92':[49.32,12.11],
    '93':[49.01,12.10],'94':[48.57,13.43],'95':[50.05,11.85],'96':[49.89,10.89],
    '97':[49.79,9.95],'98':[50.68,10.92],'99':[50.98,11.03],
  };

  // --- common cities for autocomplete ---
  const CITIES = {
    'berlin':[52.52,13.40],'hamburg':[53.55,9.99],'münchen':[48.14,11.58],'muenchen':[48.14,11.58],
    'köln':[50.94,6.96],'koeln':[50.94,6.96],'frankfurt':[50.11,8.68],'stuttgart':[48.78,9.18],
    'düsseldorf':[51.23,6.78],'duesseldorf':[51.23,6.78],'dortmund':[51.51,7.47],'essen':[51.46,7.01],
    'leipzig':[51.34,12.37],'bremen':[53.08,8.80],'dresden':[51.05,13.74],'hannover':[52.37,9.74],
    'nürnberg':[49.45,11.08],'nuernberg':[49.45,11.08],'duisburg':[51.43,6.76],'bochum':[51.48,7.22],
    'wuppertal':[51.26,7.15],'bielefeld':[52.02,8.53],'bonn':[50.74,7.10],'münster':[51.96,7.63],
    'karlsruhe':[49.01,8.40],'mannheim':[49.49,8.47],'augsburg':[48.37,10.90],'wiesbaden':[50.08,8.24],
    'mönchengladbach':[51.19,6.44],'mainz':[49.99,8.25],'kiel':[54.32,10.12],'aachen':[50.78,6.08],
    'freiburg':[47.99,7.84],'regensburg':[49.01,12.10],'lübeck':[53.87,10.69],'erfurt':[50.98,11.03],
    'rostock':[54.09,12.13],'kassel':[51.31,9.49],'potsdam':[52.40,13.07],'saarbrücken':[49.24,6.99],
  };

  function coordsForVenue(venue) {
    if (VENUES[venue]) return VENUES[venue];
    // try prefix match
    const key = Object.keys(VENUES).find(k => venue && venue.indexOf(k) === 0);
    return key ? VENUES[key] : null;
  }

  // Resolve a free-text query (PLZ or city) → { lat, lng, label } or null
  function resolve(q) {
    if (!q) return null;
    const t = q.trim().toLowerCase();
    const plz = t.match(/\b(\d{5})\b/) || t.match(/^(\d{2,5})$/);
    if (plz) {
      const code = plz[1].padStart(5, '0').slice(0, 5);
      const p2 = PLZ2[code.slice(0, 2)];
      if (p2) return { lat: p2[0], lng: p2[1], label: code };
    }
    // city name
    const cityKey = Object.keys(CITIES).find(c => t.indexOf(c) === 0 || c.indexOf(t) === 0);
    if (cityKey && t.length >= 2) {
      const c = CITIES[cityKey];
      const nice = cityKey.charAt(0).toUpperCase() + cityKey.slice(1);
      return { lat: c[0], lng: c[1], label: nice };
    }
    return null;
  }

  function suggest(q, limit) {
    if (!q || q.trim().length < 1) return [];
    const t = q.trim().toLowerCase();
    const out = [];
    // PLZ
    if (/^\d{2,5}$/.test(t)) {
      const p2 = PLZ2[t.slice(0, 2)];
      if (p2) out.push({ label: t.length >= 5 ? t : t + '… (PLZ ' + t + ')', kind: 'plz' });
    }
    Object.keys(CITIES).forEach(c => {
      if (out.length >= (limit || 6)) return;
      if (c.indexOf(t) === 0) {
        const nice = c.charAt(0).toUpperCase() + c.slice(1);
        if (!out.some(o => o.label === nice)) out.push({ label: nice, kind: 'city' });
      }
    });
    return out.slice(0, limit || 6);
  }

  function distKm(a, b) {
    if (!a || !b) return null;
    const R = 6371;
    const dLat = (b[0] - a[0]) * Math.PI / 180;
    const dLng = (b[1] - a[1]) * Math.PI / 180;
    const la1 = a[0] * Math.PI / 180, la2 = b[0] * Math.PI / 180;
    const x = Math.sin(dLat / 2) ** 2 + Math.cos(la1) * Math.cos(la2) * Math.sin(dLng / 2) ** 2;
    return Math.round(2 * R * Math.asin(Math.sqrt(x)));
  }

  return { coordsForVenue, resolve, suggest, distKm };
})();
