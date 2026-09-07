<?php
/**
 * Golfsimulatoren in Deutschland (Top-50-Recherche, Julius via Codex, 02.09.2026).
 * Statische Fakten für die Simulator-Karte auf der Weihnachtsfeier-Seite:
 * Name, Ort, Adresse, Website, Boxen, System, Event-Stufe (A = Eventlocation,
 * B = eventfähig, C/D = Kleingruppe/Training), Gastro-Level (1 bis 4).
 * Koordinaten per Nominatim (OSM) aus der Adresse, 07.09.2026. Kein Partner-
 * Status, keine Bewertung: nur belegte Angaben (siehe docs/plan-simulatoren.md).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @return array<int,array<string,mixed>> */
function fge_simulatoren(): array {
	return [
		[ 'name' => 'Golf Loft 44', 'ort' => 'Ebersbach an der Fils', 'plz' => '73061', 'bundesland' => 'Baden-Württemberg', 'adresse' => 'Hauptstraße 44A, 73061 Ebersbach an der Fils', 'website' => 'https://www.golfloft44.de/', 'bays' => '5', 'system' => 'TrackMan iO', 'stufe' => 'B', 'gastro' => '3', 'geplant' => false, 'lat' => 48.714496, 'lng' => 9.528203 ],
		[ 'name' => 'ForeYou Indoor Golf & Eventlocation', 'ort' => 'Karlsdorf-Neuthard', 'plz' => '76689', 'bundesland' => 'Baden-Württemberg', 'adresse' => 'Im Ochsenstall 30a, 76689 Karlsdorf-Neuthard', 'website' => 'https://www.foreyou.golf/', 'bays' => '5', 'system' => 'TrackMan', 'stufe' => 'A', 'gastro' => '4', 'geplant' => false, 'lat' => 49.149871, 'lng' => 8.550691 ],
		[ 'name' => 'Indoor Golf Mannheim 77', 'ort' => 'Mannheim', 'plz' => '68309', 'bundesland' => 'Baden-Württemberg', 'adresse' => 'Cecil-Taylor-Ring 12-18, 68309 Mannheim', 'website' => 'https://www.indoorgolf-mannheim-77.de/', 'bays' => '1', 'system' => 'TrackMan', 'stufe' => 'B', 'gastro' => '3', 'geplant' => false, 'lat' => 49.515211, 'lng' => 8.549779 ],
		[ 'name' => 'Seven Tees Brunnthal', 'ort' => 'Brunnthal', 'plz' => '85649', 'bundesland' => 'Bayern', 'adresse' => 'Eugen-Sänger-Ring 4, 85649 Brunnthal', 'website' => 'https://seventees.golf/', 'bays' => '9', 'system' => '4x TrackMan 4 + 5x TrackMan iO', 'stufe' => 'A', 'gastro' => '3', 'geplant' => false, 'lat' => 48.040339, 'lng' => 11.663912 ],
		[ 'name' => 'chigolf - The Movement Code', 'ort' => 'Hohenbrunn', 'plz' => '85662', 'bundesland' => 'Bayern', 'adresse' => 'Dorfstraße 4, 85662 Hohenbrunn', 'website' => 'https://www.chigolf.de/', 'bays' => '2', 'system' => '1x Uneekor EYE XO + 1x TruGolf', 'stufe' => 'D', 'gastro' => '1', 'geplant' => false, 'lat' => 48.047355, 'lng' => 11.702011 ],
		[ 'name' => 'Indoor Golf Rottal', 'ort' => 'Johanniskirchen', 'plz' => '84381', 'bundesland' => 'Bayern', 'adresse' => 'Habach 13, 84381 Johanniskirchen', 'website' => 'https://www.indoor-golf-rottal.com/', 'bays' => '1', 'system' => 'TrackMan laut Partner-/Verzeichnisquelle', 'stufe' => 'D', 'gastro' => '1', 'geplant' => false, 'lat' => 48.566893, 'lng' => 12.972112 ],
		[ 'name' => 'PATO Golf München', 'ort' => 'München', 'plz' => '80802', 'bundesland' => 'Bayern', 'adresse' => 'Königinstraße 34, 80802 München', 'website' => 'https://golf.patoclub.de/de', 'bays' => '4', 'system' => 'Garmin R50', 'stufe' => 'B', 'gastro' => '4', 'geplant' => false, 'lat' => 48.155379, 'lng' => 11.589382 ],
		[ 'name' => 'Seven Tees München', 'ort' => 'München', 'plz' => '80807', 'bundesland' => 'Bayern', 'adresse' => 'Taunusstraße 23, 80807 München', 'website' => 'https://www.seventees.de/', 'bays' => '7', 'system' => 'TrackMan', 'stufe' => 'B', 'gastro' => '4', 'geplant' => false, 'lat' => 48.189248, 'lng' => 11.577545 ],
		[ 'name' => 'Fairway Lounge Neustadt/WN', 'ort' => 'Neustadt an der Waldnaab', 'plz' => '92660', 'bundesland' => 'Bayern', 'adresse' => 'Knorrstraße 21, 92660 Neustadt/WN', 'website' => 'https://fairway-lounge.de/', 'bays' => '1', 'system' => 'Uneekor EYE XO', 'stufe' => 'D', 'gastro' => '1', 'geplant' => false, 'lat' => 49.733193, 'lng' => 12.170528 ],
		[ 'name' => 'Birdies Indoor Golf Nürnberg', 'ort' => 'Nürnberg', 'plz' => '90441', 'bundesland' => 'Bayern', 'adresse' => 'Nimrodstraße 10, Bau 3-4, 90441 Nürnberg', 'website' => 'https://www.birdiesgolf.de/', 'bays' => '6', 'system' => 'TrackMan iO', 'stufe' => 'B', 'gastro' => '2', 'geplant' => false, 'lat' => 49.426122, 'lng' => 11.057196 ],
		[ 'name' => 'Clubhouse Nürnberg', 'ort' => 'Nürnberg', 'plz' => '90431', 'bundesland' => 'Bayern', 'adresse' => 'Sigmundstraße 147, 90431 Nürnberg', 'website' => 'https://www.clubhouse-nuernberg.de/', 'bays' => '7', 'system' => 'TrackMan', 'stufe' => 'A', 'gastro' => '4', 'geplant' => false, 'lat' => 49.447027, 'lng' => 11.011237 ],
		[ 'name' => 'Seven Tees Olching', 'ort' => 'Olching', 'plz' => '82140', 'bundesland' => 'Bayern', 'adresse' => 'Johann-G.-Gutenberg-Straße 15, 82140 Olching', 'website' => 'https://www.seventees.de/', 'bays' => '3', 'system' => 'TrackMan', 'stufe' => 'C', 'gastro' => '3', 'geplant' => false, 'lat' => 48.197816, 'lng' => 11.332658 ],
		[ 'name' => 'Shank Brothers', 'ort' => 'Prutting', 'plz' => '83134', 'bundesland' => 'Bayern', 'adresse' => 'Ried 2, 83134 Prutting', 'website' => 'https://shank-brothers.com/', 'bays' => '', 'system' => 'TrackMan iO', 'stufe' => 'B', 'gastro' => '1', 'geplant' => false, 'lat' => 47.874740, 'lng' => 12.172002 ],
		[ 'name' => 'Tap Inn Golf Lounge Wasserburg', 'ort' => 'Wasserburg am Inn', 'plz' => '83512', 'bundesland' => 'Bayern', 'adresse' => 'Im Hag 8, 83512 Wasserburg am Inn', 'website' => 'https://www.tapinn.de/', 'bays' => '1', 'system' => 'TrackMan', 'stufe' => 'C', 'gastro' => '1', 'geplant' => false, 'lat' => 48.063150, 'lng' => 12.230428 ],
		[ 'name' => 'EvoGolf Berlin', 'ort' => 'Berlin', 'plz' => '10557', 'bundesland' => 'Berlin', 'adresse' => 'Heidestraße 46, 10557 Berlin', 'website' => 'https://www.evogolf.de/', 'bays' => '6', 'system' => 'TrackMan iO', 'stufe' => 'A', 'gastro' => '4', 'geplant' => false, 'lat' => 52.531159, 'lng' => 13.366992 ],
		[ 'name' => 'Golftempel Berlin', 'ort' => 'Berlin', 'plz' => '', 'bundesland' => 'Berlin', 'adresse' => 'Vulkanstraße 3, 10367 Berlin', 'website' => 'https://golftempel.de/', 'bays' => '1', 'system' => 'Hersteller nicht ausdrücklich genannt', 'stufe' => 'D', 'gastro' => '4', 'geplant' => false, 'lat' => 52.524188, 'lng' => 13.486182 ],
		[ 'name' => 'Indoor Golf Club Berlin / Golfzentrum Berlin Schöneberg', 'ort' => 'Berlin', 'plz' => '10829', 'bundesland' => 'Berlin', 'adresse' => 'Wilhelm-Kabus-Straße 34, Halle 10A, 10829 Berlin', 'website' => 'https://www.golfzentrumberlin.de/', 'bays' => '9', 'system' => '1x TrackMan iO + 3x TrackMan 4 + 5x Garmin R50', 'stufe' => 'A', 'gastro' => '2', 'geplant' => false, 'lat' => 52.476394, 'lng' => 13.360888 ],
		[ 'name' => 'Kim Golf & Essen / Kim Screen Golf Berlin', 'ort' => 'Berlin', 'plz' => '10555', 'bundesland' => 'Berlin', 'adresse' => 'Alt-Moabit 62, 10555 Berlin', 'website' => 'https://www.kimscreengolf.de/', 'bays' => '3', 'system' => 'Screen Golf / Full-HD-Automatik', 'stufe' => 'B', 'gastro' => '4', 'geplant' => false, 'lat' => 52.523964, 'lng' => 13.328290 ],
		[ 'name' => 'Nate Danner Golf - Indoor Studio Reinickendorf', 'ort' => 'Berlin', 'plz' => '13403', 'bundesland' => 'Berlin', 'adresse' => 'Triftstraße 37-38, 13509 Berlin', 'website' => 'https://www.natedannergolf.com/', 'bays' => '2', 'system' => 'TrackMan', 'stufe' => 'D', 'gastro' => '1', 'geplant' => false, 'lat' => 52.586869, 'lng' => 13.316169 ],
		[ 'name' => 'Nate Danner Golf - Indoor Studio Schöneberg', 'ort' => 'Berlin', 'plz' => '10777', 'bundesland' => 'Berlin', 'adresse' => 'Eresburgstraße 24-29, Raum 201, 12103 Berlin', 'website' => 'https://www.natedannergolf.com/', 'bays' => '1', 'system' => 'TrackMan', 'stufe' => 'D', 'gastro' => '1', 'geplant' => false, 'lat' => 52.497472, 'lng' => 13.342724 ],
		[ 'name' => 'Eagleswing Indoor Golf Studio', 'ort' => 'Hamburg', 'plz' => '22159', 'bundesland' => 'Hamburg', 'adresse' => 'Berner Allee 24, 22159 Hamburg', 'website' => 'https://eagleswing.de/', 'bays' => '1', 'system' => 'Foresight Sports Falcon', 'stufe' => 'D', 'gastro' => '1', 'geplant' => false, 'lat' => 53.628514, 'lng' => 10.132241 ],
		[ 'name' => 'Golf in a Box Hamburg – Niendorf', 'ort' => 'Hamburg', 'plz' => '22457', 'bundesland' => 'Hamburg', 'adresse' => 'Modering 1a, 22457 Hamburg', 'website' => 'https://golf-in-a-box.de/', 'bays' => '1', 'system' => 'TrackMan iO', 'stufe' => 'D', 'gastro' => '1', 'geplant' => false, 'lat' => 53.638011, 'lng' => 9.923773 ],
		[ 'name' => 'Golf in a Box Hamburg – Rotherbaum', 'ort' => 'Hamburg', 'plz' => '20146', 'bundesland' => 'Hamburg', 'adresse' => 'Laufgraben 16, 20146 Hamburg', 'website' => 'https://golf-in-a-box.de/', 'bays' => '1', 'system' => 'TrackMan iO', 'stufe' => 'D', 'gastro' => '1', 'geplant' => false, 'lat' => 53.565995, 'lng' => 9.977005 ],
		[ 'name' => 'Green Grid Golf Club', 'ort' => 'Hamburg', 'plz' => '22303', 'bundesland' => 'Hamburg', 'adresse' => 'Jarrestraße 80, 22303 Hamburg', 'website' => 'https://green-grid-golf.de/', 'bays' => '1', 'system' => 'TrackMan', 'stufe' => 'D', 'gastro' => '1', 'geplant' => false, 'lat' => 53.586079, 'lng' => 10.030461 ],
		[ 'name' => 'HIO Fitting Hamburg', 'ort' => 'Hamburg', 'plz' => '20146', 'bundesland' => 'Hamburg', 'adresse' => 'Laufgraben 16, 20146 Hamburg', 'website' => 'https://hio-fitting.de/hamburg/', 'bays' => '1', 'system' => 'TrackMan iO', 'stufe' => 'C', 'gastro' => '1', 'geplant' => false, 'lat' => 53.565995, 'lng' => 9.977005 ],
		[ 'name' => 'LAZY GOLF Hamburg', 'ort' => 'Hamburg', 'plz' => '22177', 'bundesland' => 'Hamburg', 'adresse' => 'Fabriciusstraße 85, 22177 Hamburg', 'website' => 'https://www.lazygolf.de/', 'bays' => '1', 'system' => 'TrackMan iO', 'stufe' => 'D', 'gastro' => '3', 'geplant' => false, 'lat' => 53.601447, 'lng' => 10.062250 ],
		[ 'name' => 'Tee It Up Hamburg', 'ort' => 'Hamburg', 'plz' => '22419', 'bundesland' => 'Hamburg', 'adresse' => 'Oehleckerring 25, 22419 Hamburg', 'website' => 'https://www.teeitupgolf.de/', 'bays' => '5', 'system' => 'Golfzon TwoVisionNX', 'stufe' => 'A', 'gastro' => '3', 'geplant' => true, 'lat' => 53.662420, 'lng' => 10.004952 ],
		[ 'name' => 'TrackMe Beach Hamburg Golf-Loft', 'ort' => 'Hamburg', 'plz' => '22049', 'bundesland' => 'Hamburg', 'adresse' => 'Alter Teichweg 220, 22049 Hamburg', 'website' => 'https://trackme.de/beach', 'bays' => '2', 'system' => 'TrackMan 4', 'stufe' => 'A', 'gastro' => '4', 'geplant' => false, 'lat' => 53.588343, 'lng' => 10.074375 ],
		[ 'name' => 'TrackMe Hamburg', 'ort' => 'Hamburg', 'plz' => '22761', 'bundesland' => 'Hamburg', 'adresse' => 'Beerenweg 3, 22761 Hamburg', 'website' => 'https://trackme.de/', 'bays' => '9', 'system' => 'TrackMan 4', 'stufe' => 'A', 'gastro' => '4', 'geplant' => false, 'lat' => 53.566438, 'lng' => 9.920686 ],
		[ 'name' => 'RUFF Golf Dreieich', 'ort' => 'Dreieich', 'plz' => '63303', 'bundesland' => 'Hessen', 'adresse' => 'Frankfurter Straße 151 C, 63303 Dreieich', 'website' => 'https://ruffgolf.eu/de/', 'bays' => '6', 'system' => 'TrackMan 4', 'stufe' => 'A', 'gastro' => '4', 'geplant' => false, 'lat' => 50.033333, 'lng' => 8.697524 ],
		[ 'name' => 'Indoor Golf & Lounge Kronberg', 'ort' => 'Kronberg', 'plz' => '61476', 'bundesland' => 'Hessen', 'adresse' => 'Dieselstraße 4, 61476 Kronberg', 'website' => 'https://www.indoorgolf-kronberg.de/', 'bays' => '3', 'system' => 'TrackMan', 'stufe' => 'A', 'gastro' => '3', 'geplant' => false, 'lat' => 50.177523, 'lng' => 8.548842 ],
		[ 'name' => 'MatchPlay Indoor Golf Göttingen', 'ort' => 'Göttingen', 'plz' => '37077', 'bundesland' => 'Niedersachsen', 'adresse' => 'Werner-von-Siemens-Straße 1, 37077 Göttingen', 'website' => 'https://www.matchplaygolf.de/', 'bays' => '8', 'system' => 'TrackMan iO', 'stufe' => 'B', 'gastro' => '4', 'geplant' => false, 'lat' => 51.558881, 'lng' => 9.930841 ],
		[ 'name' => 'APEX Indoor Golf Bonn', 'ort' => 'Bonn', 'plz' => '53229', 'bundesland' => 'Nordrhein-Westfalen', 'adresse' => 'Am Weidenbach 23, 53229 Bonn', 'website' => 'https://www.apexig.de/', 'bays' => '4', 'system' => '1x TrackMan iO + 3x TrackMan 4', 'stufe' => 'B', 'gastro' => '3', 'geplant' => false, 'lat' => 50.743701, 'lng' => 7.157914 ],
		[ 'name' => 'RUFF Golf Düsseldorf', 'ort' => 'Düsseldorf', 'plz' => '40547', 'bundesland' => 'Nordrhein-Westfalen', 'adresse' => 'Fritz-Vomfelde-Straße 34, 40547 Düsseldorf', 'website' => 'https://ruffgolf.eu/de/', 'bays' => '7', 'system' => 'TrackMan', 'stufe' => 'A', 'gastro' => '3', 'geplant' => false, 'lat' => 51.240505, 'lng' => 6.736883 ],
		[ 'name' => 'Seven Tees Köln / Hürth', 'ort' => 'Hürth', 'plz' => '50354', 'bundesland' => 'Nordrhein-Westfalen', 'adresse' => 'An der Hasenkaule 10, 50354 Hürth', 'website' => 'https://www.seventees.de/', 'bays' => '4', 'system' => 'TrackMan', 'stufe' => 'A', 'gastro' => '3', 'geplant' => false, 'lat' => 50.878082, 'lng' => 6.908146 ],
		[ 'name' => 'GOLF LIFE Langenfeld', 'ort' => 'Langenfeld', 'plz' => '40764', 'bundesland' => 'Nordrhein-Westfalen', 'adresse' => 'Hans-Böckler-Str. 42, 40764 Langenfeld', 'website' => 'https://www.golf-life.de/', 'bays' => '', 'system' => 'TrackMan / FlightScope im Fitting', 'stufe' => 'D', 'gastro' => '1', 'geplant' => false, 'lat' => 51.120730, 'lng' => 6.930310 ],
		[ 'name' => 'Team Spieckerhoff Indoorgolf', 'ort' => 'Langenfeld', 'plz' => '40764', 'bundesland' => 'Nordrhein-Westfalen', 'adresse' => 'Kurfürstenweg 22, 40764 Langenfeld', 'website' => 'https://team-spieckerhoff.de/', 'bays' => '2', 'system' => 'aboutGolf', 'stufe' => 'C', 'gastro' => '2', 'geplant' => false, 'lat' => 51.126086, 'lng' => 6.970838 ],
		[ 'name' => 'KINETO Indoor Golf & Bar Meerbusch', 'ort' => 'Meerbusch', 'plz' => '40667', 'bundesland' => 'Nordrhein-Westfalen', 'adresse' => 'Am Meerkamp 21, 40667 Meerbusch', 'website' => 'https://www.kineto.golf/', 'bays' => '5', 'system' => 'TrackMan', 'stufe' => 'B', 'gastro' => '3', 'geplant' => false, 'lat' => 51.247580, 'lng' => 6.690135 ],
		[ 'name' => 'Indoor-Golf-Ruhrpott', 'ort' => 'Mülheim an der Ruhr', 'plz' => '45475', 'bundesland' => 'Nordrhein-Westfalen', 'adresse' => 'Langekamp 9, 45475 Mülheim an der Ruhr', 'website' => 'https://trackrange.golf/indoor-golf-ruhrpott/', 'bays' => '10', 'system' => 'TrackMan 4', 'stufe' => 'C', 'gastro' => '3', 'geplant' => false, 'lat' => 51.453905, 'lng' => 6.881518 ],
		[ 'name' => 'Next Golf Münster', 'ort' => 'Münster', 'plz' => '48153', 'bundesland' => 'Nordrhein-Westfalen', 'adresse' => 'Hafenstraße 64, 48153 Münster', 'website' => 'https://next-golf.de/', 'bays' => '2', 'system' => 'TrackMan iO', 'stufe' => 'A', 'gastro' => '3', 'geplant' => false, 'lat' => 51.950681, 'lng' => 7.634464 ],
		[ 'name' => 'GOLFINN | Indoor-Golf Loft Wuppertal', 'ort' => 'Wuppertal', 'plz' => '42369', 'bundesland' => 'Nordrhein-Westfalen', 'adresse' => 'Rosenthalstraße 12, 42369 Wuppertal', 'website' => 'https://www.golf-inn.de/', 'bays' => '4', 'system' => 'Golfsimulatoren - Hersteller nicht öffentlich genannt', 'stufe' => 'B', 'gastro' => '1', 'geplant' => false, 'lat' => 51.212481, 'lng' => 7.200170 ],
		[ 'name' => 'GOLFHUB Indoor Golf Lounge Chemnitz', 'ort' => 'Chemnitz', 'plz' => '09125', 'bundesland' => 'Sachsen', 'adresse' => 'Reichenhainer Straße 154, 09125 Chemnitz', 'website' => 'https://www.golfhub.de/', 'bays' => '3', 'system' => 'TrackMan', 'stufe' => 'B', 'gastro' => '3', 'geplant' => false, 'lat' => 50.803983, 'lng' => 12.937769 ],
		[ 'name' => 'GO! Indoor Golfclub Dresden – Waldschlösschen', 'ort' => 'Dresden', 'plz' => '01099', 'bundesland' => 'Sachsen', 'adresse' => 'Am Brauhaus 8, 01099 Dresden', 'website' => 'https://golf-offensive.de/indoor', 'bays' => '4', 'system' => 'TrackMan', 'stufe' => 'A', 'gastro' => '2', 'geplant' => true, 'lat' => 51.068387, 'lng' => 13.778213 ],
		[ 'name' => 'Golf At Your Best Leipzig', 'ort' => 'Leipzig', 'plz' => '04357', 'bundesland' => 'Sachsen', 'adresse' => 'Volbedingstraße 2, 04357 Leipzig', 'website' => 'https://www.golfatyourbest.com/', 'bays' => '1', 'system' => 'TrackMan', 'stufe' => 'C', 'gastro' => '2', 'geplant' => false, 'lat' => 51.362574, 'lng' => 12.406014 ],
		[ 'name' => 'Eisen Sieben Indoor Golf', 'ort' => 'Glinde', 'plz' => '21509', 'bundesland' => 'Schleswig-Holstein', 'adresse' => 'Biedenkamp 3D, 21509 Glinde', 'website' => 'https://eisen-sieben.com/', 'bays' => '8', 'system' => 'TrackMan', 'stufe' => 'A', 'gastro' => '3', 'geplant' => false, 'lat' => 53.546811, 'lng' => 10.229877 ],
		[ 'name' => 'Pitching Zone Lübeck', 'ort' => 'Lübeck', 'plz' => '23560', 'bundesland' => 'Schleswig-Holstein', 'adresse' => 'Borsigstraße 1, 23560 Lübeck', 'website' => 'https://pitching-zone.de/', 'bays' => '7', 'system' => 'TrackMan', 'stufe' => 'A', 'gastro' => '3', 'geplant' => false, 'lat' => 53.845806, 'lng' => 10.677267 ],
		[ 'name' => 'RUFF Indoor-Golf Ostsee', 'ort' => 'Scharbeutz', 'plz' => '23684', 'bundesland' => 'Schleswig-Holstein', 'adresse' => 'Blauenkrog 12, 23684 Scharbeutz', 'website' => 'https://ruffgolf.eu/de/', 'bays' => '5', 'system' => 'TrackMan', 'stufe' => 'A', 'gastro' => '4', 'geplant' => false, 'lat' => 54.040941, 'lng' => 10.658009 ],
		[ 'name' => 'Birdie Box Hamburg', 'ort' => 'Schenefeld', 'plz' => '22869', 'bundesland' => 'Schleswig-Holstein', 'adresse' => 'Dannenkamp 19, 22869 Schenefeld', 'website' => 'https://birdiebox.hamburg/', 'bays' => '2', 'system' => 'TrackMan iO', 'stufe' => 'C', 'gastro' => '1', 'geplant' => false, 'lat' => 53.595298, 'lng' => 9.829251 ],
		[ 'name' => 'Indoor Golf Erfurt', 'ort' => 'Erfurt', 'plz' => '99094', 'bundesland' => 'Thüringen', 'adresse' => 'Motzstraße 8, 99094 Erfurt', 'website' => 'https://indoor-golf-erfurt.de/', 'bays' => '2', 'system' => 'TrackMan', 'stufe' => 'B', 'gastro' => '2', 'geplant' => false, 'lat' => 50.961121, 'lng' => 11.013922 ],
		[ 'name' => 'Indoor Golf Oberhof', 'ort' => 'Oberhof', 'plz' => '98559', 'bundesland' => 'Thüringen', 'adresse' => 'Crawinkler Straße 1, 98559 Oberhof', 'website' => 'https://indoor-golf-oberhof.de/', 'bays' => '', 'system' => 'TrackMan', 'stufe' => 'C', 'gastro' => '2', 'geplant' => false, 'lat' => 50.707070, 'lng' => 10.724405 ],
	];
}

/**
 * Karte mit allen Simulatoren (Klaro-gegated wie die City-Karten): Daten
 * lokalisieren, Google-Maps-JS mit Callback fgeSimMapInit einhängen.
 */
function fge_simulatoren_map_enqueue(): void {
	if ( ! function_exists( 'fge_gmaps_api_key' ) || '' === fge_gmaps_api_key() ) {
		return;
	}
	$places = [];
	foreach ( fge_simulatoren() as $s ) {
		if ( (float) $s['lat'] === 0.0 ) {
			continue;
		}
		$places[] = [
			'name'    => $s['name'],
			'ort'     => $s['ort'],
			'lat'     => (float) $s['lat'],
			'lng'     => (float) $s['lng'],
			'event'   => in_array( $s['stufe'], [ 'A', 'B' ], true ),
			'meta'    => trim( ( '' !== $s['bays'] ? $s['bays'] . ' Boxen' : '' ) . ( '' !== $s['system'] ? ( '' !== $s['bays'] ? ', ' : '' ) . $s['system'] : '' ) ),
			'website' => $s['website'],
			'geplant' => (bool) $s['geplant'],
		];
	}
	$src = plugins_url( 'assets/js/fge-sim-map.js', FGE_DIR . 'firmengolf-events.php' );
	wp_enqueue_script( 'fge-sim-map', $src, [], FGE_VERSION, true );
	wp_localize_script( 'fge-sim-map', 'FGE_SIM_MAP', [ 'places' => $places, 'anfrage' => '#anfrage' ] );
	$maps_url = add_query_arg(
		[ 'key' => rawurlencode( fge_gmaps_api_key() ), 'callback' => 'fgeSimMapInit', 'loading' => 'async', 'language' => 'de', 'region' => 'DE' ],
		'https://maps.googleapis.com/maps/api/js'
	);
	wp_enqueue_script( 'google-maps', $maps_url, [ 'fge-sim-map' ], null, true );
}
