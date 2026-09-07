<?php
/**
 * Golfsimulatoren in Deutschland: Julius' Marktanalyse (Akquise-Liste vom 24.08.2026,
 * C:/Users/Julius/outputs/golf_simulatoren_deutschland_akquise, golf_simulatoren_records.json).
 * Nur öffentliche Fakten je Standort: Name, Ort, Adresse, Website, Boxen, System,
 * Eventlocation (Ja/Eingeschränkt/Nein/Geplant, leer = unbekannt), Bar. verified = Status
 * „Aktiv" bzw. online bestätigt, sonst Verzeichnisfund. precision = adresse | plz | ort
 * (Koordinaten per Nominatim/OSM, 07.09.2026; „ort" = Stadtmitte, kein exakter Standort).
 * Interne Bewertungen (Lead-Score, Firmengolf-Fit, Kontaktstatus) bleiben bewusst draußen.
 * Neue Anlagen hier nachtragen, die Karte liest ausschließlich diese Datei.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @return array<int,array<string,mixed>> */
function fge_simulatoren(): array {
	return [
		[ 'name' => 'Wellnesshotel THE GRAND Ahrenshoop', 'ort' => 'Ahrenshoop', 'plz' => '', 'bundesland' => '', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 54.378951, 'lng' => 12.419192 ],
		[ 'name' => 'Quellness & Golf Resort Bad Griesbach', 'ort' => 'Bad Griesbach', 'plz' => '', 'bundesland' => '', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 48.451335, 'lng' => 13.193105 ],
		[ 'name' => 'Golfclub Bayreuth', 'ort' => 'Bayreuth', 'plz' => '', 'bundesland' => '', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 49.944634, 'lng' => 11.574354 ],
		[ 'name' => 'Berliner Golf Club Gatow', 'ort' => 'Berlin', 'plz' => '', 'bundesland' => '', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 52.517389, 'lng' => 13.395131 ],
		[ 'name' => 'Golf-Park Dessau', 'ort' => 'Dessau', 'plz' => '', 'bundesland' => '', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 51.830996, 'lng' => 12.243072 ],
		[ 'name' => 'Golfpark Gudensberg', 'ort' => 'Gudensberg', 'plz' => '', 'bundesland' => '', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 51.187939, 'lng' => 9.367329 ],
		[ 'name' => 'Golf Lounge Resort Hamburg', 'ort' => 'Hamburg', 'plz' => '', 'bundesland' => '', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 53.550172, 'lng' => 10.001316 ],
		[ 'name' => 'Golfclub Herzogenaurach', 'ort' => 'Herzogenaurach', 'plz' => '', 'bundesland' => '', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 49.573817, 'lng' => 10.892697 ],
		[ 'name' => 'Hotel Central Hof', 'ort' => 'Hof', 'plz' => '', 'bundesland' => '', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 50.318751, 'lng' => 11.916299 ],
		[ 'name' => 'Golfclub Bruckmannshof', 'ort' => 'Hünxe', 'plz' => '', 'bundesland' => '', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 51.641458, 'lng' => 6.766032 ],
		[ 'name' => 'PARKHOTEL STUTTGART MESSE-AIRPORT', 'ort' => 'Leinfelden-Echterdingen', 'plz' => '', 'bundesland' => '', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 48.690180, 'lng' => 9.152572 ],
		[ 'name' => 'Indoor Golf Mudau / Golfclub Mudau', 'ort' => 'Mudau', 'plz' => '', 'bundesland' => '', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 49.533912, 'lng' => 9.204830 ],
		[ 'name' => 'Golfpark Neustadt/Harz', 'ort' => 'Neustadt/Harz', 'plz' => '', 'bundesland' => '', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 51.560756, 'lng' => 10.834036 ],
		[ 'name' => 'Golfpark Bostalsee', 'ort' => 'Nohfelden', 'plz' => '', 'bundesland' => '', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 49.587081, 'lng' => 7.143041 ],
		[ 'name' => 'Indoor Golf Marhördt / Golfclub Marhördt', 'ort' => 'Oberrot-Marhördt', 'plz' => '', 'bundesland' => '', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 49.040358, 'lng' => 9.611476 ],
		[ 'name' => 'Schloss Langenstein', 'ort' => 'Orsingen-Nenzingen', 'plz' => '', 'bundesland' => '', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 47.839287, 'lng' => 8.947163 ],
		[ 'name' => 'Golfclub Pfaffing Wasserburger Land', 'ort' => 'Pfaffing', 'plz' => '', 'bundesland' => '', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 48.053862, 'lng' => 12.108915 ],
		[ 'name' => 'Golfclub Grevenmühle', 'ort' => 'Ratingen', 'plz' => '', 'bundesland' => '', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 51.297326, 'lng' => 6.849350 ],
		[ 'name' => 'Golfanlage Schopfheim', 'ort' => 'Schopfheim', 'plz' => '', 'bundesland' => '', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 47.650053, 'lng' => 7.821700 ],
		[ 'name' => 'Golf Club St. Leon-Rot', 'ort' => 'St. Leon-Rot', 'plz' => '', 'bundesland' => '', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 49.267133, 'lng' => 8.614262 ],
		[ 'name' => 'Adventure&Indoor Golf', 'ort' => 'Balingen', 'plz' => '72336', 'bundesland' => 'Baden-Württemberg', 'adresse' => 'Im Rohrbach 30, 72336 Balingen', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'adresse', 'lat' => 48.253998, 'lng' => 8.857460 ],
		[ 'name' => 'Indoor Golf Hohenloher Tor', 'ort' => 'Bretzfeld', 'plz' => '74626', 'bundesland' => 'Baden-Württemberg', 'adresse' => 'Maybachstraße 21, 74626 Bretzfeld', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'adresse', 'lat' => 49.185030, 'lng' => 9.412490 ],
		[ 'name' => 'Der Öschberghof Indoor Golf', 'ort' => 'Donaueschingen', 'plz' => '', 'bundesland' => 'Baden-Württemberg', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 47.953419, 'lng' => 8.495926 ],
		[ 'name' => 'Golf Loft 44', 'ort' => 'Ebersbach an der Fils', 'plz' => '73061', 'bundesland' => 'Baden-Württemberg', 'adresse' => 'Hauptstraße 44A, 73061 Ebersbach an der Fils', 'website' => 'https://www.golfloft44.de/', 'bays' => '5', 'system' => 'TrackMan iO', 'eventlocation' => 'Ja', 'bar' => 'Ja', 'verified' => true, 'precision' => 'adresse', 'lat' => 48.714496, 'lng' => 9.528203 ],
		[ 'name' => '0711Golfschule Esslingen', 'ort' => 'Esslingen', 'plz' => '73728', 'bundesland' => 'Baden-Württemberg', 'adresse' => 'Mettinger Straße 103, 73728 Esslingen', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'adresse', 'lat' => 48.742260, 'lng' => 9.292547 ],
		[ 'name' => 'Pro Marine Gottmadingen', 'ort' => 'Gottmadingen', 'plz' => '78244', 'bundesland' => 'Baden-Württemberg', 'adresse' => 'Zeppelinstraße 32, 78244 Gottmadingen', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'adresse', 'lat' => 47.736868, 'lng' => 8.797347 ],
		[ 'name' => 'myclubmaker Golf+IT', 'ort' => 'Heimsheim', 'plz' => '71296', 'bundesland' => 'Baden-Württemberg', 'adresse' => 'Mittelberg 12, 71296 Heimsheim', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'adresse', 'lat' => 48.821508, 'lng' => 8.862112 ],
		[ 'name' => 'ForeYou Indoor Golf & Eventlocation', 'ort' => 'Karlsdorf-Neuthard', 'plz' => '76689', 'bundesland' => 'Baden-Württemberg', 'adresse' => 'Im Ochsenstall 30a, 76689 Karlsdorf-Neuthard', 'website' => 'https://www.foreyou.golf/', 'bays' => '5', 'system' => 'TrackMan', 'eventlocation' => 'Ja', 'bar' => 'Ja', 'verified' => true, 'precision' => 'adresse', 'lat' => 49.149871, 'lng' => 8.550691 ],
		[ 'name' => 'Golfanlage Kirchheim-Wendlingen-Wernau', 'ort' => 'Kirchheim unter Teck', 'plz' => '', 'bundesland' => 'Baden-Württemberg', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 48.648055, 'lng' => 9.451023 ],
		[ 'name' => 'Indoor Golf Mannheim 77', 'ort' => 'Mannheim', 'plz' => '68309', 'bundesland' => 'Baden-Württemberg', 'adresse' => 'Cecil-Taylor-Ring 12-18, 68309 Mannheim', 'website' => '', 'bays' => '', 'system' => 'TrackMan laut Suchumfeld', 'eventlocation' => '', 'bar' => '', 'verified' => true, 'precision' => 'adresse', 'lat' => 49.515211, 'lng' => 8.549779 ],
		[ 'name' => 'Sportpalast Singen', 'ort' => 'Singen', 'plz' => '78224', 'bundesland' => 'Baden-Württemberg', 'adresse' => 'Georg-Fischer-Straße 39, 78224 Singen', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'adresse', 'lat' => 47.751607, 'lng' => 8.888893 ],
		[ 'name' => 'Sportklamser Ulm', 'ort' => 'Ulm', 'plz' => '', 'bundesland' => 'Baden-Württemberg', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 48.398497, 'lng' => 9.991246 ],
		[ 'name' => 'FLIGHT Alzenau', 'ort' => 'Alzenau', 'plz' => '63755', 'bundesland' => 'Bayern', 'adresse' => 'Gerichtsplatzstraße 75, 63755 Alzenau', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'adresse', 'lat' => 50.057141, 'lng' => 9.060801 ],
		[ 'name' => 'Indoorgolf Alberthausen', 'ort' => 'Bad Kissingen', 'plz' => '', 'bundesland' => 'Bayern', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 50.197495, 'lng' => 10.057326 ],
		[ 'name' => 'Golflounge Bamberg', 'ort' => 'Bamberg', 'plz' => '', 'bundesland' => 'Bayern', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 49.891604, 'lng' => 10.886848 ],
		[ 'name' => 'Seven Tees Brunnthal', 'ort' => 'Brunnthal', 'plz' => '85649', 'bundesland' => 'Bayern', 'adresse' => 'Eugen-Sänger-Ring 4, 85649 Brunnthal', 'website' => 'https://seventees.golf/', 'bays' => '', 'system' => 'TrackMan', 'eventlocation' => '', 'bar' => '', 'verified' => true, 'precision' => 'adresse', 'lat' => 48.040339, 'lng' => 11.663912 ],
		[ 'name' => 'Brute Golf Lounge', 'ort' => 'Burk', 'plz' => '91596', 'bundesland' => 'Bayern', 'adresse' => 'Talstraße 2, 91596 Burk', 'website' => 'https://www.brutegolflounge.de/', 'bays' => '', 'system' => 'TrackMan', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'adresse', 'lat' => 49.156005, 'lng' => 10.510906 ],
		[ 'name' => 'Hotel Residence Starnberger See Indoor Golf', 'ort' => 'Feldafing', 'plz' => '', 'bundesland' => 'Bayern', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 47.946671, 'lng' => 11.290537 ],
		[ 'name' => 'Golf USA - VR Golf Garching', 'ort' => 'Garching', 'plz' => '85748', 'bundesland' => 'Bayern', 'adresse' => 'Freisinger Landstraße 47, 85748 Garching', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'adresse', 'lat' => 48.257300, 'lng' => 11.652917 ],
		[ 'name' => 'Indoorgolf München-West', 'ort' => 'Germering', 'plz' => '', 'bundesland' => 'Bayern', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 48.137358, 'lng' => 11.361434 ],
		[ 'name' => 'Indoor Golf Grafing', 'ort' => 'Grafing', 'plz' => '', 'bundesland' => 'Bayern', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 48.045381, 'lng' => 11.966071 ],
		[ 'name' => 'Tennis Center Keferloh', 'ort' => 'Grasbrunn', 'plz' => '', 'bundesland' => 'Bayern', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 48.078907, 'lng' => 11.743249 ],
		[ 'name' => 'Golf Simulator Gunzenhausen', 'ort' => 'Gunzenhausen', 'plz' => '', 'bundesland' => 'Bayern', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 49.116136, 'lng' => 10.740685 ],
		[ 'name' => 'chigolf - The Movement Code', 'ort' => 'Hohenbrunn', 'plz' => '85662', 'bundesland' => 'Bayern', 'adresse' => 'Dorfstraße 4, 85662 Hohenbrunn', 'website' => 'https://www.chigolf.de/', 'bays' => '', 'system' => 'Simulator / Bewegungsanalyse', 'eventlocation' => '', 'bar' => '', 'verified' => true, 'precision' => 'adresse', 'lat' => 48.047355, 'lng' => 11.702011 ],
		[ 'name' => 'GC Ingolstadt Indoor-Halle', 'ort' => 'Ingolstadt', 'plz' => '', 'bundesland' => 'Bayern', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 48.763016, 'lng' => 11.425040 ],
		[ 'name' => 'Indoor Golf Rottal', 'ort' => 'Johanniskirchen', 'plz' => '84381', 'bundesland' => 'Bayern', 'adresse' => 'Habach 13, 84381 Johanniskirchen', 'website' => 'https://www.indoor-golf-rottal.com/', 'bays' => '', 'system' => 'TrackMan', 'eventlocation' => '', 'bar' => '', 'verified' => true, 'precision' => 'adresse', 'lat' => 48.566893, 'lng' => 12.972112 ],
		[ 'name' => 'Golfpark Schloßgut Lenzfried', 'ort' => 'Kempten', 'plz' => '', 'bundesland' => 'Bayern', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 47.726706, 'lng' => 10.316883 ],
		[ 'name' => 'KA2 Indoor Golf Kulmbach', 'ort' => 'Kulmbach', 'plz' => '', 'bundesland' => 'Bayern', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 50.107125, 'lng' => 11.458181 ],
		[ 'name' => 'Tennispark Lichtenfels', 'ort' => 'Lichtenfels', 'plz' => '96215', 'bundesland' => 'Bayern', 'adresse' => 'Dr.-Hauptmann-Ring 12, 96215 Lichtenfels', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'adresse', 'lat' => 50.150963, 'lng' => 11.066603 ],
		[ 'name' => 'Golfersworld Moosinning', 'ort' => 'Moosinning', 'plz' => '85452', 'bundesland' => 'Bayern', 'adresse' => 'Münchner Straße 57, 85452 Moosinning', 'website' => '', 'bays' => '5', 'system' => '', 'eventlocation' => 'Ja', 'bar' => '', 'verified' => false, 'precision' => 'adresse', 'lat' => 48.270936, 'lng' => 11.771152 ],
		[ 'name' => 'PATO Golf München', 'ort' => 'München', 'plz' => '80802', 'bundesland' => 'Bayern', 'adresse' => 'Königinstraße 34, 80802 München', 'website' => 'https://golf.patoclub.de/de', 'bays' => '4', 'system' => 'Garmin R50', 'eventlocation' => 'Ja', 'bar' => 'Ja', 'verified' => true, 'precision' => 'adresse', 'lat' => 48.155379, 'lng' => 11.589382 ],
		[ 'name' => 'Seven Tees München', 'ort' => 'München', 'plz' => '80807', 'bundesland' => 'Bayern', 'adresse' => 'Taunusstraße 23, 80807 München', 'website' => 'https://seventees.golf/', 'bays' => '7', 'system' => 'TrackMan', 'eventlocation' => 'Ja', 'bar' => 'Ja', 'verified' => true, 'precision' => 'adresse', 'lat' => 48.189248, 'lng' => 11.577545 ],
		[ 'name' => 'intrago Indoor Golf München-Perlach', 'ort' => 'München', 'plz' => '81737', 'bundesland' => 'Bayern', 'adresse' => 'Bayerwaldstraße 9, 81737 München', 'website' => '', 'bays' => '2', 'system' => 'TrackMan', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'adresse', 'lat' => 48.091671, 'lng' => 11.632351 ],
		[ 'name' => 'NEW GOLF LOUNGE Neu-Ulm', 'ort' => 'Neu-Ulm', 'plz' => '89233', 'bundesland' => 'Bayern', 'adresse' => 'Kammer-Krummen-Straße 100, 89233 Neu-Ulm', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'adresse', 'lat' => 48.399924, 'lng' => 10.030790 ],
		[ 'name' => 'Fairway Lounge Neustadt/WN', 'ort' => 'Neustadt an der Waldnaab', 'plz' => '92660', 'bundesland' => 'Bayern', 'adresse' => 'Knorrstraße 21, 92660 Neustadt/WN', 'website' => 'https://fairway-lounge.de/', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => true, 'precision' => 'adresse', 'lat' => 49.733193, 'lng' => 12.170528 ],
		[ 'name' => 'Birdies Indoor Golf Nürnberg', 'ort' => 'Nürnberg', 'plz' => '90441', 'bundesland' => 'Bayern', 'adresse' => 'Nimrodstraße 10, Bau 3-4, 90441 Nürnberg', 'website' => 'https://www.birdiesgolf.de/', 'bays' => '6', 'system' => 'TrackMan iO', 'eventlocation' => '', 'bar' => '', 'verified' => true, 'precision' => 'adresse', 'lat' => 49.426122, 'lng' => 11.057196 ],
		[ 'name' => 'Clubhouse Nürnberg', 'ort' => 'Nürnberg', 'plz' => '90431', 'bundesland' => 'Bayern', 'adresse' => 'Sigmundstraße 147, 90431 Nürnberg', 'website' => 'https://clubhouse-nbg.de/', 'bays' => '7', 'system' => 'TrackMan', 'eventlocation' => '', 'bar' => '', 'verified' => true, 'precision' => 'adresse', 'lat' => 49.447027, 'lng' => 11.011237 ],
		[ 'name' => 'TOUR GREEN Indoor Golf Center', 'ort' => 'Oberhaid', 'plz' => '96173', 'bundesland' => 'Bayern', 'adresse' => 'Bürgermeister-Günthner-Str. 6, 96173 Oberhaid', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => true, 'precision' => 'plz', 'lat' => 49.946385, 'lng' => 10.798756 ],
		[ 'name' => 'Seven Tees Olching', 'ort' => 'Olching', 'plz' => '82140', 'bundesland' => 'Bayern', 'adresse' => 'Johann-G.-Gutenberg-Straße 15, 82140 Olching', 'website' => 'https://seventees.golf/', 'bays' => '3', 'system' => 'TrackMan', 'eventlocation' => 'Ja', 'bar' => 'Ja', 'verified' => true, 'precision' => 'adresse', 'lat' => 48.197816, 'lng' => 11.332658 ],
		[ 'name' => 'Donau Golf Club Passau Indoor Golf', 'ort' => 'Passau', 'plz' => '', 'bundesland' => 'Bayern', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 48.574823, 'lng' => 13.460974 ],
		[ 'name' => 'Shank Brothers', 'ort' => 'Prutting', 'plz' => '83134', 'bundesland' => 'Bayern', 'adresse' => 'Ried 2, 83134 Prutting', 'website' => 'https://shank-brothers.com/', 'bays' => '', 'system' => 'TrackMan iO laut Suchumfeld', 'eventlocation' => '', 'bar' => '', 'verified' => true, 'precision' => 'adresse', 'lat' => 47.874740, 'lng' => 12.172002 ],
		[ 'name' => 'Golfhaus Unterwössen', 'ort' => 'Unterwössen', 'plz' => '', 'bundesland' => 'Bayern', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 47.735789, 'lng' => 12.459423 ],
		[ 'name' => 'Golf Valley', 'ort' => 'Valley', 'plz' => '', 'bundesland' => 'Bayern', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 47.894517, 'lng' => 11.778463 ],
		[ 'name' => 'Tap Inn Golf Lounge Wasserburg', 'ort' => 'Wasserburg am Inn', 'plz' => '83512', 'bundesland' => 'Bayern', 'adresse' => 'Im Hag 8, 83512 Wasserburg am Inn', 'website' => 'https://www.tapinn.de/', 'bays' => '1', 'system' => 'TrackMan', 'eventlocation' => 'Eingeschränkt', 'bar' => 'Ja', 'verified' => true, 'precision' => 'adresse', 'lat' => 48.063150, 'lng' => 12.230428 ],
		[ 'name' => 'Capitol Yard Golf Lounge Berlin', 'ort' => 'Berlin', 'plz' => '10245', 'bundesland' => 'Berlin', 'adresse' => 'Stralauer Allee 2b, 10245 Berlin', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'adresse', 'lat' => 52.501667, 'lng' => 13.448690 ],
		[ 'name' => 'EvoGolf Berlin', 'ort' => 'Berlin', 'plz' => '10557', 'bundesland' => 'Berlin', 'adresse' => 'Heidestraße 46, 10557 Berlin', 'website' => 'https://www.evogolf.de/', 'bays' => '6', 'system' => 'TrackMan', 'eventlocation' => '', 'bar' => '', 'verified' => true, 'precision' => 'adresse', 'lat' => 52.531159, 'lng' => 13.366992 ],
		[ 'name' => 'Golf Performance Institute Berlin', 'ort' => 'Berlin', 'plz' => '10589', 'bundesland' => 'Berlin', 'adresse' => 'Lise-Meitner-Straße 39-41, 10589 Berlin', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'adresse', 'lat' => 52.533936, 'lng' => 13.305877 ],
		[ 'name' => 'Golf Studio Berlin', 'ort' => 'Berlin', 'plz' => '10587', 'bundesland' => 'Berlin', 'adresse' => 'Salzufer 13-14, Aufgang G, 2. OG, 10587 Berlin', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'plz', 'lat' => 52.518488, 'lng' => 13.319460 ],
		[ 'name' => 'Golftempel Berlin', 'ort' => 'Berlin', 'plz' => '', 'bundesland' => 'Berlin', 'adresse' => '', 'website' => 'https://golftempel.de/', 'bays' => '1', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => true, 'precision' => 'adresse', 'lat' => 52.524188, 'lng' => 13.486182 ],
		[ 'name' => 'HohmannGolf Indoorgolf Berlin', 'ort' => 'Berlin', 'plz' => '', 'bundesland' => 'Berlin', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 52.517389, 'lng' => 13.395131 ],
		[ 'name' => 'Indoor Golf Center Berlin Ku’damm', 'ort' => 'Berlin', 'plz' => '', 'bundesland' => 'Berlin', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 52.517389, 'lng' => 13.395131 ],
		[ 'name' => 'Indoor Golf Club Berlin / Golfzentrum Berlin Schöneberg', 'ort' => 'Berlin', 'plz' => '10829', 'bundesland' => 'Berlin', 'adresse' => 'Wilhelm-Kabus-Straße 34, Halle 10A, 10829 Berlin', 'website' => 'https://www.golfzentrumberlin.de/', 'bays' => '11', 'system' => 'TrackMan / Simulatoren', 'eventlocation' => '', 'bar' => '', 'verified' => true, 'precision' => 'adresse', 'lat' => 52.476394, 'lng' => 13.360888 ],
		[ 'name' => 'Kim Golf & Essen / Kim Screen Golf Berlin', 'ort' => 'Berlin', 'plz' => '10555', 'bundesland' => 'Berlin', 'adresse' => 'Alt-Moabit 62, 10555 Berlin', 'website' => '', 'bays' => '', 'system' => 'Screen Golf', 'eventlocation' => '', 'bar' => '', 'verified' => true, 'precision' => 'adresse', 'lat' => 52.523964, 'lng' => 13.328290 ],
		[ 'name' => 'Nate Danner Golf - Indoor Studio Reinickendorf', 'ort' => 'Berlin', 'plz' => '13403', 'bundesland' => 'Berlin', 'adresse' => 'Kienhorststraße 170, 13403 Berlin', 'website' => 'https://www.natedannergolf.com/', 'bays' => '', 'system' => 'Indoor Studio', 'eventlocation' => '', 'bar' => '', 'verified' => true, 'precision' => 'adresse', 'lat' => 52.586869, 'lng' => 13.316169 ],
		[ 'name' => 'Nate Danner Golf - Indoor Studio Schöneberg', 'ort' => 'Berlin', 'plz' => '10777', 'bundesland' => 'Berlin', 'adresse' => 'Bülowstraße 56, 10777 Berlin', 'website' => 'https://www.natedannergolf.com/', 'bays' => '', 'system' => 'TrackMan / Indoor Studio', 'eventlocation' => '', 'bar' => '', 'verified' => true, 'precision' => 'adresse', 'lat' => 52.497472, 'lng' => 13.342724 ],
		[ 'name' => 'ProGolf Berlin', 'ort' => 'Berlin', 'plz' => '10785', 'bundesland' => 'Berlin', 'adresse' => 'Schillstraße 10, 10785 Berlin', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'adresse', 'lat' => 52.503850, 'lng' => 13.350180 ],
		[ 'name' => 'BSV Hamburg Indoor-Golf', 'ort' => 'Hamburg', 'plz' => '22761', 'bundesland' => 'Hamburg', 'adresse' => 'Beerenweg 1D, 22761 Hamburg', 'website' => '', 'bays' => '2', 'system' => 'TrackMan', 'eventlocation' => 'Nein', 'bar' => 'Nein', 'verified' => false, 'precision' => 'adresse', 'lat' => 53.564842, 'lng' => 9.921395 ],
		[ 'name' => 'Eagleswing Indoor Golf Studio', 'ort' => 'Hamburg', 'plz' => '22159', 'bundesland' => 'Hamburg', 'adresse' => 'Berner Allee 24, 22159 Hamburg', 'website' => 'https://eagleswing.de/', 'bays' => '1', 'system' => 'Foresight Sports Falcon', 'eventlocation' => 'Eingeschränkt', 'bar' => 'Nein', 'verified' => true, 'precision' => 'adresse', 'lat' => 53.628514, 'lng' => 10.132241 ],
		[ 'name' => 'Golf in a Box Hamburg, Niendorf', 'ort' => 'Hamburg', 'plz' => '22457', 'bundesland' => 'Hamburg', 'adresse' => 'Modering 1a, 22457 Hamburg', 'website' => 'https://golf-in-a-box.de/', 'bays' => '1', 'system' => 'TrackMan iO', 'eventlocation' => 'Eingeschränkt', 'bar' => 'Nein', 'verified' => true, 'precision' => 'adresse', 'lat' => 53.638011, 'lng' => 9.923773 ],
		[ 'name' => 'Golf in a Box Hamburg, Rotherbaum', 'ort' => 'Hamburg', 'plz' => '20146', 'bundesland' => 'Hamburg', 'adresse' => 'Laufgraben 16, 20146 Hamburg', 'website' => 'https://golf-in-a-box.de/', 'bays' => '1', 'system' => 'TrackMan iO', 'eventlocation' => 'Eingeschränkt', 'bar' => 'Nein', 'verified' => true, 'precision' => 'adresse', 'lat' => 53.565995, 'lng' => 9.977005 ],
		[ 'name' => 'Green Grid Golf Club', 'ort' => 'Hamburg', 'plz' => '22303', 'bundesland' => 'Hamburg', 'adresse' => 'Jarrestraße 80, 22303 Hamburg', 'website' => 'https://green-grid-golf.de/', 'bays' => '2', 'system' => 'TrackMan', 'eventlocation' => '', 'bar' => '', 'verified' => true, 'precision' => 'adresse', 'lat' => 53.586079, 'lng' => 10.030461 ],
		[ 'name' => 'HIO Fitting Hamburg', 'ort' => 'Hamburg', 'plz' => '20146', 'bundesland' => 'Hamburg', 'adresse' => 'Laufgraben 16, 20146 Hamburg', 'website' => 'https://hio-fitting.de/hamburg/', 'bays' => '1', 'system' => 'TrackMan iO', 'eventlocation' => 'Nein', 'bar' => 'Nein', 'verified' => true, 'precision' => 'adresse', 'lat' => 53.565995, 'lng' => 9.977005 ],
		[ 'name' => 'Hamburger Golfakademie', 'ort' => 'Hamburg', 'plz' => '', 'bundesland' => 'Hamburg', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 53.550172, 'lng' => 10.001316 ],
		[ 'name' => 'LAZY GOLF Hamburg', 'ort' => 'Hamburg', 'plz' => '22177', 'bundesland' => 'Hamburg', 'adresse' => 'Fabriciusstraße 85, 22177 Hamburg', 'website' => 'https://www.lazygolf.de/', 'bays' => '3', 'system' => 'TrackMan iO', 'eventlocation' => 'Eingeschränkt', 'bar' => 'Ja', 'verified' => true, 'precision' => 'adresse', 'lat' => 53.601447, 'lng' => 10.062250 ],
		[ 'name' => 'Tee It Up Hamburg', 'ort' => 'Hamburg', 'plz' => '22419', 'bundesland' => 'Hamburg', 'adresse' => 'Langenhorner Chaussee 666, 22419 Hamburg', 'website' => 'https://www.teeitupgolf.de/', 'bays' => '5', 'system' => 'Golfzon TwoVisionNX', 'eventlocation' => 'Geplant', 'bar' => 'Geplant', 'verified' => false, 'precision' => 'adresse', 'lat' => 53.662420, 'lng' => 10.004952 ],
		[ 'name' => 'TrackMe Beach Hamburg Golf-Loft', 'ort' => 'Hamburg', 'plz' => '22049', 'bundesland' => 'Hamburg', 'adresse' => 'Alter Teichweg 220, 22049 Hamburg', 'website' => 'https://trackme.de/beach', 'bays' => '2', 'system' => 'TrackMan 4', 'eventlocation' => 'Ja', 'bar' => 'Ja', 'verified' => true, 'precision' => 'adresse', 'lat' => 53.588343, 'lng' => 10.074375 ],
		[ 'name' => 'TrackMe Hamburg', 'ort' => 'Hamburg', 'plz' => '22761', 'bundesland' => 'Hamburg', 'adresse' => 'Beerenweg 3, 22761 Hamburg', 'website' => 'https://trackme.de/', 'bays' => '9', 'system' => 'TrackMan 4', 'eventlocation' => 'Ja', 'bar' => 'Ja', 'verified' => true, 'precision' => 'adresse', 'lat' => 53.566438, 'lng' => 9.920686 ],
		[ 'name' => 'Indoorgolf Braunfels', 'ort' => 'Braunfels', 'plz' => '', 'bundesland' => 'Hessen', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 50.501725, 'lng' => 8.401282 ],
		[ 'name' => 'RUFF Golf Dreieich', 'ort' => 'Dreieich', 'plz' => '63303', 'bundesland' => 'Hessen', 'adresse' => 'Frankfurter Straße 151, 63303 Dreieich', 'website' => 'https://ruffgolf.eu/de/', 'bays' => '6', 'system' => 'TrackMan', 'eventlocation' => '', 'bar' => '', 'verified' => true, 'precision' => 'adresse', 'lat' => 50.033333, 'lng' => 8.697524 ],
		[ 'name' => 'Sportpark Offenthal', 'ort' => 'Dreieich', 'plz' => '63303', 'bundesland' => 'Hessen', 'adresse' => 'Gutenbergstraße 3, 63303 Dreieich', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'adresse', 'lat' => 49.982576, 'lng' => 8.754437 ],
		[ 'name' => 'Golf Range Karben Indoor Golf', 'ort' => 'Karben', 'plz' => '', 'bundesland' => 'Hessen', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 50.231342, 'lng' => 8.771767 ],
		[ 'name' => 'Indoor Golf & Lounge Kronberg', 'ort' => 'Kronberg', 'plz' => '61476', 'bundesland' => 'Hessen', 'adresse' => 'Dieselstraße 4, 61476 Kronberg', 'website' => '', 'bays' => '3', 'system' => 'TrackMan', 'eventlocation' => 'Ja', 'bar' => 'Ja', 'verified' => true, 'precision' => 'adresse', 'lat' => 50.177523, 'lng' => 8.548842 ],
		[ 'name' => 'Sieben Welten Indoor Golf', 'ort' => 'Künzell', 'plz' => '', 'bundesland' => 'Hessen', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 50.532527, 'lng' => 9.730615 ],
		[ 'name' => 'Indoorgolf Wanfried', 'ort' => 'Wanfried', 'plz' => '', 'bundesland' => 'Hessen', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 51.171400, 'lng' => 10.184162 ],
		[ 'name' => 'Indoor-Golfarena Greifswald', 'ort' => 'Greifswald', 'plz' => '', 'bundesland' => 'Mecklenburg-Vorpommern', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 54.095791, 'lng' => 13.381524 ],
		[ 'name' => 'Flyingolf Rostock', 'ort' => 'Rostock', 'plz' => '18069', 'bundesland' => 'Mecklenburg-Vorpommern', 'adresse' => 'Alter Hafen Nord 216, 18069 Rostock', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'adresse', 'lat' => 54.111551, 'lng' => 12.086086 ],
		[ 'name' => 'Golf Resort Adendorf Indoor Golf', 'ort' => 'Adendorf', 'plz' => '', 'bundesland' => 'Niedersachsen', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 53.281748, 'lng' => 10.439299 ],
		[ 'name' => 'Aloha Sport Club Braunschweig', 'ort' => 'Braunschweig', 'plz' => '38122', 'bundesland' => 'Niedersachsen', 'adresse' => 'Friedrich-Seele-Straße 15, 38122 Braunschweig', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'adresse', 'lat' => 52.238111, 'lng' => 10.494349 ],
		[ 'name' => 'Indoor Golf Duderstadt', 'ort' => 'Duderstadt', 'plz' => '37115', 'bundesland' => 'Niedersachsen', 'adresse' => 'Ziegeleistraße 6, 37115 Duderstadt', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'adresse', 'lat' => 51.507812, 'lng' => 10.255586 ],
		[ 'name' => 'Ball-Haus Reinhausen', 'ort' => 'Gleichen-Reinhausen', 'plz' => '', 'bundesland' => 'Niedersachsen', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 51.469573, 'lng' => 9.981769 ],
		[ 'name' => 'MatchPlay Indoor Golf Göttingen', 'ort' => 'Göttingen', 'plz' => '37077', 'bundesland' => 'Niedersachsen', 'adresse' => 'Werner-von-Siemens-Straße 1, 37077 Göttingen', 'website' => 'https://www.matchplaygolf.de/', 'bays' => '8', 'system' => 'TrackMan iO', 'eventlocation' => 'Ja', 'bar' => '', 'verified' => true, 'precision' => 'adresse', 'lat' => 51.558881, 'lng' => 9.930841 ],
		[ 'name' => 'Golfschule & Pro Shop Hendrik Harms', 'ort' => 'Papenburg', 'plz' => '26871', 'bundesland' => 'Niedersachsen', 'adresse' => 'Gutshofstraße 141, 26871 Papenburg', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'adresse', 'lat' => 53.059565, 'lng' => 7.411021 ],
		[ 'name' => 'Blauer Fasan Indoor-Golf Wiesmoor', 'ort' => 'Wiesmoor', 'plz' => '', 'bundesland' => 'Niedersachsen', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 53.411444, 'lng' => 7.730944 ],
		[ 'name' => 'Hotel Haus Schnepper Indoor Golf', 'ort' => 'Attendorn', 'plz' => '', 'bundesland' => 'Nordrhein-Westfalen', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 51.125054, 'lng' => 7.901099 ],
		[ 'name' => 'Indoor Golf Center Bad Münstereifel', 'ort' => 'Bad Münstereifel', 'plz' => '', 'bundesland' => 'Nordrhein-Westfalen', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 50.554567, 'lng' => 6.763651 ],
		[ 'name' => 'Bielefeld Indoor Range', 'ort' => 'Bielefeld', 'plz' => '', 'bundesland' => 'Nordrhein-Westfalen', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 52.019101, 'lng' => 8.531007 ],
		[ 'name' => 'APEX Indoor Golf Bonn', 'ort' => 'Bonn', 'plz' => '53229', 'bundesland' => 'Nordrhein-Westfalen', 'adresse' => 'Am Weidenbach 23, 53229 Bonn', 'website' => 'https://www.apexig.de/', 'bays' => '3', 'system' => 'TrackMan iO / TrackMan 4', 'eventlocation' => 'Ja', 'bar' => '', 'verified' => true, 'precision' => 'adresse', 'lat' => 50.743701, 'lng' => 7.157914 ],
		[ 'name' => 'Arena 79 Bottrop', 'ort' => 'Bottrop', 'plz' => '', 'bundesland' => 'Nordrhein-Westfalen', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 51.521581, 'lng' => 6.929204 ],
		[ 'name' => 'Indoorgolf Büren', 'ort' => 'Büren', 'plz' => '', 'bundesland' => 'Nordrhein-Westfalen', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 51.552533, 'lng' => 8.559192 ],
		[ 'name' => 'COSMO SPORTS Indoorgolf Düsseldorf', 'ort' => 'Düsseldorf', 'plz' => '', 'bundesland' => 'Nordrhein-Westfalen', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 51.225402, 'lng' => 6.776314 ],
		[ 'name' => 'RUFF Golf Düsseldorf', 'ort' => 'Düsseldorf', 'plz' => '40547', 'bundesland' => 'Nordrhein-Westfalen', 'adresse' => 'Fritz-Vomfelde-Straße 34, 40547 Düsseldorf', 'website' => 'https://ruffgolf.eu/de/', 'bays' => '7', 'system' => 'TrackMan', 'eventlocation' => '', 'bar' => '', 'verified' => true, 'precision' => 'adresse', 'lat' => 51.240505, 'lng' => 6.736883 ],
		[ 'name' => 'Seven Tees Köln / Hürth', 'ort' => 'Hürth', 'plz' => '50354', 'bundesland' => 'Nordrhein-Westfalen', 'adresse' => 'An der Hasenkaule 10, 50354 Hürth', 'website' => 'https://seventees.golf/', 'bays' => '', 'system' => 'TrackMan', 'eventlocation' => '', 'bar' => '', 'verified' => true, 'precision' => 'adresse', 'lat' => 50.878082, 'lng' => 6.908146 ],
		[ 'name' => 'Sweetspot Cologne', 'ort' => 'Kerpen', 'plz' => '50171', 'bundesland' => 'Nordrhein-Westfalen', 'adresse' => 'Haagstraße 19, 50171 Kerpen', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'adresse', 'lat' => 50.852626, 'lng' => 6.626717 ],
		[ 'name' => 'Golfschule Köln', 'ort' => 'Köln', 'plz' => '', 'bundesland' => 'Nordrhein-Westfalen', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 50.938361, 'lng' => 6.959974 ],
		[ 'name' => 'Jordan Golfdom Köln', 'ort' => 'Köln', 'plz' => '', 'bundesland' => 'Nordrhein-Westfalen', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 50.938361, 'lng' => 6.959974 ],
		[ 'name' => 'Kölner Golfclub Indoor Golf', 'ort' => 'Köln', 'plz' => '', 'bundesland' => 'Nordrhein-Westfalen', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 50.938361, 'lng' => 6.959974 ],
		[ 'name' => 'SPORTSPARK Cologne', 'ort' => 'Köln', 'plz' => '51149', 'bundesland' => 'Nordrhein-Westfalen', 'adresse' => 'Poller Weg 1, 51149 Köln', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'adresse', 'lat' => 50.907687, 'lng' => 7.005368 ],
		[ 'name' => 'Sportcenter Kautz Köln', 'ort' => 'Köln', 'plz' => '50939', 'bundesland' => 'Nordrhein-Westfalen', 'adresse' => 'Rhöndorfer Straße 10, 50939 Köln', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'adresse', 'lat' => 50.916202, 'lng' => 6.935212 ],
		[ 'name' => 'GOLF LIFE Langenfeld', 'ort' => 'Langenfeld', 'plz' => '40764', 'bundesland' => 'Nordrhein-Westfalen', 'adresse' => 'Hans-Böckler-Str. 42, 40764 Langenfeld', 'website' => 'https://www.golf-life.de/', 'bays' => '', 'system' => 'TrackMan', 'eventlocation' => '', 'bar' => '', 'verified' => true, 'precision' => 'adresse', 'lat' => 51.120730, 'lng' => 6.930310 ],
		[ 'name' => 'Team Spieckerhoff Indoorgolf', 'ort' => 'Langenfeld', 'plz' => '40764', 'bundesland' => 'Nordrhein-Westfalen', 'adresse' => 'Kurfürstenweg 22, 40764 Langenfeld', 'website' => 'https://team-spieckerhoff.de/', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => true, 'precision' => 'adresse', 'lat' => 51.126086, 'lng' => 6.970838 ],
		[ 'name' => 'KINETO Indoor Golf & Bar Meerbusch', 'ort' => 'Meerbusch', 'plz' => '40667', 'bundesland' => 'Nordrhein-Westfalen', 'adresse' => 'Am Meerkamp 21, 40667 Meerbusch', 'website' => 'https://www.kineto.golf/', 'bays' => '5', 'system' => 'TrackMan iO', 'eventlocation' => 'Ja', 'bar' => 'Ja', 'verified' => true, 'precision' => 'adresse', 'lat' => 51.247580, 'lng' => 6.690135 ],
		[ 'name' => 'Indoor-Golf-Ruhrpott', 'ort' => 'Mülheim an der Ruhr', 'plz' => '45475', 'bundesland' => 'Nordrhein-Westfalen', 'adresse' => 'Langekamp 9, 45475 Mülheim an der Ruhr', 'website' => 'https://trackrange.golf/indoor-golf-ruhrpott/', 'bays' => '10', 'system' => 'TrackMan 4', 'eventlocation' => '', 'bar' => '', 'verified' => true, 'precision' => 'adresse', 'lat' => 51.453905, 'lng' => 6.881518 ],
		[ 'name' => 'Next Golf Münster', 'ort' => 'Münster', 'plz' => '48153', 'bundesland' => 'Nordrhein-Westfalen', 'adresse' => 'Hafenstraße 64, 48153 Münster', 'website' => 'https://next-golf.de/', 'bays' => '', 'system' => 'TrackMan iO', 'eventlocation' => '', 'bar' => '', 'verified' => true, 'precision' => 'adresse', 'lat' => 51.950681, 'lng' => 7.634464 ],
		[ 'name' => 'Golfhalle Münsterland', 'ort' => 'Nordkirchen', 'plz' => '59394', 'bundesland' => 'Nordrhein-Westfalen', 'adresse' => 'Am Gorbach 10, 59394 Nordkirchen', 'website' => '', 'bays' => '4', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'adresse', 'lat' => 51.741025, 'lng' => 7.534418 ],
		[ 'name' => 'GOLFINN | Indoor-Golf Loft Wuppertal', 'ort' => 'Wuppertal', 'plz' => '42369', 'bundesland' => 'Nordrhein-Westfalen', 'adresse' => 'Rosenthalstraße 12, 42369 Wuppertal', 'website' => 'https://www.golf-inn.de/', 'bays' => '2', 'system' => 'Simulatoren / Indoor-Puttinggreen', 'eventlocation' => '', 'bar' => '', 'verified' => true, 'precision' => 'adresse', 'lat' => 51.212481, 'lng' => 7.200170 ],
		[ 'name' => 'GREEN BOX Bad Kreuznach', 'ort' => 'Bad Kreuznach', 'plz' => '55545', 'bundesland' => 'Rheinland-Pfalz', 'adresse' => 'Brückes 72a, 55545 Bad Kreuznach', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'adresse', 'lat' => 49.857452, 'lng' => 7.870432 ],
		[ 'name' => 'Mainzer Golfclub Indoor-Golf', 'ort' => 'Mainz', 'plz' => '', 'bundesland' => 'Rheinland-Pfalz', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 49.999521, 'lng' => 8.273625 ],
		[ 'name' => 'Indoorgolf Maarheide', 'ort' => 'Niederdürenbach', 'plz' => '', 'bundesland' => 'Rheinland-Pfalz', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 50.456651, 'lng' => 7.183243 ],
		[ 'name' => 'Sportpark Weißenthurm', 'ort' => 'Weißenthurm', 'plz' => '', 'bundesland' => 'Rheinland-Pfalz', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 50.414519, 'lng' => 7.455754 ],
		[ 'name' => 'Club.align Golf Saarbrücken', 'ort' => 'Saarbrücken', 'plz' => '66117', 'bundesland' => 'Saarland', 'adresse' => 'Deutschherrnstraße 43, 66117 Saarbrücken', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'adresse', 'lat' => 49.231731, 'lng' => 6.976448 ],
		[ 'name' => 'VYBE Golfstudio Saarbrücken', 'ort' => 'Saarbrücken', 'plz' => '66123', 'bundesland' => 'Saarland', 'adresse' => 'Dudweiler Landstraße 141, 3. Etage, 66123 Saarbrücken', 'website' => 'https://vybegolf.de/', 'bays' => '2', 'system' => 'TrackMan', 'eventlocation' => '', 'bar' => '', 'verified' => true, 'precision' => 'plz', 'lat' => 49.248819, 'lng' => 7.022557 ],
		[ 'name' => 'Indoorgolf Dresden Bannewitz', 'ort' => 'Bannewitz', 'plz' => '', 'bundesland' => 'Sachsen', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 50.994192, 'lng' => 13.722528 ],
		[ 'name' => 'GOLFHUB Indoor Golf Lounge Chemnitz', 'ort' => 'Chemnitz', 'plz' => '09125', 'bundesland' => 'Sachsen', 'adresse' => 'Reichenhainer Straße 154, 09125 Chemnitz', 'website' => 'https://www.golfhub.de/', 'bays' => '3', 'system' => 'TrackMan', 'eventlocation' => 'Ja', 'bar' => 'Ja', 'verified' => true, 'precision' => 'adresse', 'lat' => 50.803983, 'lng' => 12.937769 ],
		[ 'name' => 'Indoor Golf Chemnitz, Erzberger Straße', 'ort' => 'Chemnitz', 'plz' => '', 'bundesland' => 'Sachsen', 'adresse' => 'Erzberger Straße 2, Chemnitz', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'adresse', 'lat' => 50.835447, 'lng' => 12.891398 ],
		[ 'name' => 'Citygolf Center Dresden', 'ort' => 'Dresden', 'plz' => '', 'bundesland' => 'Sachsen', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 51.049329, 'lng' => 13.738144 ],
		[ 'name' => 'GO! Golf-Offensive, Maritim Hotel Dresden', 'ort' => 'Dresden', 'plz' => '01067', 'bundesland' => 'Sachsen', 'adresse' => 'Devrientstraße 10, 01067 Dresden', 'website' => 'https://www.golf-offensive.de/golfsimulator/', 'bays' => '1', 'system' => 'TrackMan', 'eventlocation' => 'Nein', 'bar' => '', 'verified' => true, 'precision' => 'adresse', 'lat' => 51.057436, 'lng' => 13.731851 ],
		[ 'name' => 'GO! Indoor Golfclub Dresden, Waldschlösschen', 'ort' => 'Dresden', 'plz' => '01099', 'bundesland' => 'Sachsen', 'adresse' => 'Waldschlößchenstraße 2, 01099 Dresden', 'website' => 'https://www.golf-offensive.de/', 'bays' => '4', 'system' => 'TrackMan iO', 'eventlocation' => 'Geplant', 'bar' => 'Geplant', 'verified' => false, 'precision' => 'adresse', 'lat' => 51.068387, 'lng' => 13.778213 ],
		[ 'name' => 'Golf At Your Best Leipzig', 'ort' => 'Leipzig', 'plz' => '04357', 'bundesland' => 'Sachsen', 'adresse' => 'Volbedingstraße 2, 04357 Leipzig', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => true, 'precision' => 'adresse', 'lat' => 51.362574, 'lng' => 12.406014 ],
		[ 'name' => 'Golf-Inside24 Leipzig', 'ort' => 'Leipzig', 'plz' => '04347', 'bundesland' => 'Sachsen', 'adresse' => 'Braunstraße 17, 04347 Leipzig', 'website' => '', 'bays' => '', 'system' => 'TrackMan laut Verzeichnisumfeld', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'adresse', 'lat' => 51.367300, 'lng' => 12.429487 ],
		[ 'name' => 'Golfakademie Leipzig', 'ort' => 'Leipzig', 'plz' => '', 'bundesland' => 'Sachsen', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 51.340632, 'lng' => 12.374733 ],
		[ 'name' => 'FIRSTGOLF Magdeburg', 'ort' => 'Magdeburg', 'plz' => '39112', 'bundesland' => 'Sachsen-Anhalt', 'adresse' => 'Halberstädter Str. 17, 39112 Magdeburg', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'adresse', 'lat' => 52.117026, 'lng' => 11.616717 ],
		[ 'name' => 'Eisen Sieben Indoor Golf', 'ort' => 'Glinde', 'plz' => '21509', 'bundesland' => 'Schleswig-Holstein', 'adresse' => 'Biedenkamp 3D, 21509 Glinde', 'website' => 'https://eisen-sieben.com/', 'bays' => '8', 'system' => 'TrackMan', 'eventlocation' => 'Ja', 'bar' => 'Ja', 'verified' => true, 'precision' => 'adresse', 'lat' => 53.546811, 'lng' => 10.229877 ],
		[ 'name' => 'X-Flex Golf Kaltenkirchen', 'ort' => 'Kaltenkirchen', 'plz' => '24568', 'bundesland' => 'Schleswig-Holstein', 'adresse' => 'Porschering 12, 24568 Kaltenkirchen', 'website' => 'https://www.x-flexgolf.de/', 'bays' => '', 'system' => 'TrackMan', 'eventlocation' => '', 'bar' => '', 'verified' => true, 'precision' => 'adresse', 'lat' => 53.828627, 'lng' => 9.970997 ],
		[ 'name' => 'CockayneGolf Indoor Lübeck', 'ort' => 'Lübeck', 'plz' => '', 'bundesland' => 'Schleswig-Holstein', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 53.866444, 'lng' => 10.684738 ],
		[ 'name' => 'Pitching Zone Lübeck', 'ort' => 'Lübeck', 'plz' => '23560', 'bundesland' => 'Schleswig-Holstein', 'adresse' => 'Borsigstraße 1, 23560 Lübeck', 'website' => 'https://pitching-zone.de/', 'bays' => '7', 'system' => 'TrackMan', 'eventlocation' => 'Ja', 'bar' => '', 'verified' => true, 'precision' => 'adresse', 'lat' => 53.845806, 'lng' => 10.677267 ],
		[ 'name' => 'Vegas Bowling und Golf Lübeck', 'ort' => 'Lübeck', 'plz' => '', 'bundesland' => 'Schleswig-Holstein', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 53.866444, 'lng' => 10.684738 ],
		[ 'name' => 'Fitness & Physiotherapie Preetz Indoor Golf', 'ort' => 'Preetz', 'plz' => '', 'bundesland' => 'Schleswig-Holstein', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 54.235923, 'lng' => 10.281835 ],
		[ 'name' => 'RUFF Indoor-Golf Ostsee', 'ort' => 'Scharbeutz', 'plz' => '23684', 'bundesland' => 'Schleswig-Holstein', 'adresse' => 'Blauenkrog 12, 23684 Scharbeutz', 'website' => 'https://ruffgolf.eu/de/', 'bays' => '', 'system' => 'TrackMan', 'eventlocation' => '', 'bar' => '', 'verified' => true, 'precision' => 'adresse', 'lat' => 54.040941, 'lng' => 10.658009 ],
		[ 'name' => 'Birdie Box Hamburg', 'ort' => 'Schenefeld', 'plz' => '22869', 'bundesland' => 'Schleswig-Holstein', 'adresse' => 'Dannenkamp 19, 22869 Schenefeld', 'website' => 'https://birdiebox.hamburg/', 'bays' => '2', 'system' => 'TrackMan iO', 'eventlocation' => 'Nein', 'bar' => 'Nein', 'verified' => false, 'precision' => 'adresse', 'lat' => 53.595298, 'lng' => 9.829251 ],
		[ 'name' => 'GTPC Eisenach', 'ort' => 'Eisenach', 'plz' => '', 'bundesland' => 'Thüringen', 'adresse' => '', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => false, 'precision' => 'ort', 'lat' => 50.974713, 'lng' => 10.319356 ],
		[ 'name' => 'Indoor Golf Erfurt', 'ort' => 'Erfurt', 'plz' => '99094', 'bundesland' => 'Thüringen', 'adresse' => 'Motzstraße 8, 99094 Erfurt', 'website' => '', 'bays' => '', 'system' => '', 'eventlocation' => '', 'bar' => '', 'verified' => true, 'precision' => 'adresse', 'lat' => 50.961121, 'lng' => 11.013922 ],
		[ 'name' => 'Indoor Golf Oberhof', 'ort' => 'Oberhof', 'plz' => '98559', 'bundesland' => 'Thüringen', 'adresse' => 'Crawinkler Straße 1, 98559 Oberhof', 'website' => '', 'bays' => '', 'system' => 'TrackMan laut Suchumfeld', 'eventlocation' => '', 'bar' => '', 'verified' => true, 'precision' => 'adresse', 'lat' => 50.707070, 'lng' => 10.724405 ],
	];
}

/**
 * Simulatoren, die bei Firmengolf Events anbieten: aktive Indoor-Partner mit
 * öffentlichen Events, per Namensabgleich mit der Liste (plus Partner, die nicht
 * in der Liste stehen, als eigene Pins).
 *
 * @return array{names: string[], extra: array<int,array<string,mixed>>}
 */
function fge_simulatoren_featured(): array {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$names = [];
	$extra = [];
	$partners = get_posts( [ 'post_type' => 'firmengolf_partner', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '_fge_partner_type', 'meta_value' => 'indoor' ] );
	foreach ( $partners as $pid ) {
		if ( function_exists( 'fge_partner_is_public' ) && ! fge_partner_is_public( (int) $pid ) ) {
			continue;
		}
		$events = get_posts( [ 'post_type' => 'firmengolf_event', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids', 'meta_query' => [ [ 'key' => '_fge_assigned_partner_id', 'value' => (string) $pid ], [ 'key' => '_fge_event_status', 'value' => function_exists( 'fge_public_event_statuses' ) ? fge_public_event_statuses() : [ 'freigegeben' ], 'compare' => 'IN' ] ] ] );
		if ( ! $events ) {
			continue;
		}
		$title = mb_strtolower( trim( (string) get_post_meta( (int) $pid, '_fge_public_golfclub_name', true ) ?: get_the_title( (int) $pid ) ) );
		$names[ $title ] = (int) $pid;
		$lat = (float) get_post_meta( (int) $pid, '_fge_latitude', true );
		$lng = (float) get_post_meta( (int) $pid, '_fge_longitude', true );
		$hit = false;
		foreach ( fge_simulatoren() as $s ) {
			$sn = mb_strtolower( $s['name'] );
			if ( $sn === $title || false !== mb_strpos( $sn, $title ) || false !== mb_strpos( $title, $sn ) ) {
				$hit = true;
				break;
			}
		}
		if ( ! $hit && $lat && $lng ) {
			$extra[] = [ 'name' => get_the_title( (int) $pid ), 'ort' => (string) get_post_meta( (int) $pid, '_fge_city', true ), 'lat' => $lat, 'lng' => $lng, 'website' => (string) get_permalink( (int) $pid ), 'bays' => '', 'system' => '', 'featured' => true ];
		}
	}
	$cache = [ 'names' => array_keys( $names ), 'extra' => $extra ];
	return $cache;
}

/**
 * Karte mit allen Simulatoren (Klaro-gegated wie die City-Karten): Daten
 * lokalisieren, Google-Maps-JS mit Callback fgeSimMapInit einhängen.
 */
function fge_simulatoren_map_enqueue(): void {
	if ( ! function_exists( 'fge_gmaps_api_key' ) || '' === fge_gmaps_api_key() ) {
		return;
	}
	$featured = fge_simulatoren_featured();
	$places   = [];
	foreach ( fge_simulatoren() as $s ) {
		if ( (float) $s['lat'] === 0.0 ) {
			continue;
		}
		$sn   = mb_strtolower( $s['name'] );
		$feat = false;
		foreach ( $featured['names'] as $fn ) {
			if ( $sn === $fn || false !== mb_strpos( $sn, $fn ) || false !== mb_strpos( $fn, $sn ) ) {
				$feat = true;
				break;
			}
		}
		$places[] = [
			'name'     => $s['name'],
			'ort'      => $s['ort'],
			'lat'      => (float) $s['lat'],
			'lng'      => (float) $s['lng'],
			'featured' => $feat,
			'event'    => 'Ja' === $s['eventlocation'],
			'meta'     => trim( ( '' !== $s['bays'] ? $s['bays'] . ' Boxen' : '' ) . ( '' !== $s['system'] ? ( '' !== $s['bays'] ? ', ' : '' ) . $s['system'] : '' ) ),
			'website'  => $s['website'],
			'approx'   => 'ort' === $s['precision'],
		];
	}
	foreach ( $featured['extra'] as $e ) {
		$places[] = [ 'name' => $e['name'], 'ort' => $e['ort'], 'lat' => $e['lat'], 'lng' => $e['lng'], 'featured' => true, 'event' => true, 'meta' => '', 'website' => $e['website'], 'approx' => false ];
	}
	// Golfplätze nur, wenn sie selbst eine Weihnachtsfeier anbieten (Julius, 07.09.:
	// ohne Angebot fallen sie raus, im Winter meist geschlossen). Blauer Pin, Link aufs Event.
	global $wpdb;
	$xmas_events = get_posts( [
		'post_type'   => 'firmengolf_event',
		'post_status' => 'publish',
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_query'  => [
			[ 'key' => '_fge_event_type', 'value' => 'weihnachtsfeier' ],
			[ 'key' => '_fge_assigned_partner_id', 'value' => '0', 'compare' => '>', 'type' => 'NUMERIC' ],
			[ 'key' => '_fge_event_status', 'value' => function_exists( 'fge_public_event_statuses' ) ? fge_public_event_statuses() : [ 'freigegeben' ], 'compare' => 'IN' ],
		],
	] );
	$seen_partner = [];
	foreach ( $xmas_events as $xe ) {
		if ( function_exists( 'fge_event_is_public' ) && ! fge_event_is_public( (int) $xe ) ) {
			continue;
		}
		$pid = (int) get_post_meta( (int) $xe, '_fge_assigned_partner_id', true );
		if ( $pid <= 0 || isset( $seen_partner[ $pid ] ) || ( function_exists( 'fge_partner_type' ) && 'course' !== fge_partner_type( $pid ) ) ) {
			continue;
		}
		$seen_partner[ $pid ] = true;
		$plat = (float) get_post_meta( $pid, '_fge_latitude', true );
		$plng = (float) get_post_meta( $pid, '_fge_longitude', true );
		if ( ! ( $plat && $plng ) && function_exists( 'fge_verzeichnis_table' ) ) {
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT lat, lng FROM ' . fge_verzeichnis_table() . ' WHERE partner_id = %d LIMIT 1', $pid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $row ) {
				$plat = (float) $row->lat;
				$plng = (float) $row->lng;
			}
		}
		if ( ! ( $plat && $plng ) ) {
			continue;
		}
		$places[] = [
			'name'     => (string) get_post_meta( $pid, '_fge_public_golfclub_name', true ) ?: get_the_title( $pid ),
			'ort'      => (string) get_post_meta( $pid, '_fge_city', true ),
			'lat'      => $plat,
			'lng'      => $plng,
			'featured' => false,
			'course'   => true,
			'event'    => true,
			'meta'     => 'Golfanlage mit Clubhaus',
			'website'  => (string) get_permalink( (int) $xe ),
			'approx'   => false,
		];
	}
	$src = plugins_url( 'assets/js/fge-sim-map.js', FGE_DIR . 'firmengolf-events.php' );
	wp_enqueue_script( 'fge-sim-map', $src, [], FGE_VERSION, true );
	// Standort aus der URL (nach Freigabe auf der Seite): Karte zentriert dort und
	// zoomt so, dass mindestens fünf Simulatoren im Bild sind (Julius, 07.09.).
	// Kein weiterer Google-Aufruf, nur die Kartenansicht.
	$u_lat = isset( $_GET['lat'] ) ? (float) $_GET['lat'] : 0.0; // phpcs:ignore WordPress.Security.NonceVerification
	$u_lng = isset( $_GET['lng'] ) ? (float) $_GET['lng'] : 0.0; // phpcs:ignore WordPress.Security.NonceVerification
	$user  = ( $u_lat > 46 && $u_lat < 56 && $u_lng > 5 && $u_lng < 16 ) ? [ 'lat' => $u_lat, 'lng' => $u_lng ] : null;
	wp_localize_script( 'fge-sim-map', 'FGE_SIM_MAP', [ 'places' => $places, 'anfrage' => '#anfrage', 'user' => $user, 'minNear' => 5 ] );
	$maps_url = add_query_arg(
		[ 'key' => rawurlencode( fge_gmaps_api_key() ), 'callback' => 'fgeSimMapInit', 'loading' => 'async', 'language' => 'de', 'region' => 'DE' ],
		'https://maps.googleapis.com/maps/api/js'
	);
	wp_enqueue_script( 'google-maps', $maps_url, [ 'fge-sim-map' ], null, true );
}
