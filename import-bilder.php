<?php
/**
 * Import Julius' Präfix-Bilder in assets/imagery/pool/.
 * php import-bilder.php <src> <imagery-dir> <dry|write>
 * Ausgabe: TSV (aktion \t quelle \t ziel \t cluster \t info)
 */
[$_, $SRC, $IMG, $MODE] = $argv + [null, null, null, 'dry'];
$POOL = rtrim($IMG, '/') . '/pool';
$MAX  = 1920;

function load(string $p): ?array { // [gd, w, h]
	$info = @getimagesize($p); if (!$info) return null;
	$im = $info[2] == IMAGETYPE_PNG ? @imagecreatefrompng($p) : @imagecreatefromjpeg($p);
	if (!$im) return null;
	if ($info[2] == IMAGETYPE_JPEG && function_exists('exif_read_data')) {
		$ex = @exif_read_data($p); $o = (int) ($ex['Orientation'] ?? 1);
		$deg = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
		if ($deg) { $r = imagerotate($im, $deg, 0); imagedestroy($im); $im = $r; }
	}
	if ($info[2] == IMAGETYPE_PNG) { // auf Weiß flachen
		$w = imagesx($im); $h = imagesy($im); $bg = imagecreatetruecolor($w, $h);
		imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255)); imagecopy($bg, $im, 0, 0, 0, 0, $w, $h); imagedestroy($im); $im = $bg;
	}
	return [$im, imagesx($im), imagesy($im)];
}
function ahash($im, int $w, int $h): string {
	$s = imagecreatetruecolor(8, 8); imagecopyresampled($s, $im, 0, 0, 0, 0, 8, 8, $w, $h);
	$g = []; for ($y = 0; $y < 8; $y++) for ($x = 0; $x < 8; $x++) { $c = imagecolorat($s, $x, $y); $g[] = (($c >> 16 & 255) * 299 + ($c >> 8 & 255) * 587 + ($c & 255) * 114) / 1000; }
	$avg = array_sum($g) / 64; $b = ''; foreach ($g as $v) $b .= $v > $avg ? '1' : '0'; imagedestroy($s); return $b;
}
function ham(string $a, string $b): int { return substr_count(($a ^ $b), "\1"); }
function same(array $a, array $b): bool { return ham($a['hash'], $b['hash']) <= 1 && abs($a['ar'] - $b['ar']) < 0.03; }
function slug(string $s): string { $s = strtolower($s); $s = strtr($s, ['ä'=>'ae','ö'=>'oe','ü'=>'ue','ß'=>'ss']); $s = preg_replace('/[^a-z0-9]+/', '-', $s); return trim(preg_replace('/-+/', '-', $s), '-'); }
function save($im, int $w, int $h, string $dest): void {
	global $MAX; $long = max($w, $h);
	if ($long > $MAX) { $nw = (int) round($w * $MAX / $long); $nh = (int) round($h * $MAX / $long); $r = imagecreatetruecolor($nw, $nh); imagecopyresampled($r, $im, 0, 0, 0, 0, $nw, $nh, $w, $h); }
	else { $r = $im; }
	imagejpeg($r, $dest, 82); imagewebp($r, $dest . '.webp', 80);
	if ($r !== $im) imagedestroy($r);
}
$map = ['platz' => 'golfplatz', 'weihnachtsfeier' => 'indoor', 'office' => 'pool', 'ki' => 'pool', 'bunkerschlag' => 'pool'];
$skip = ['julius-hero.png', 'Afterwork.jpg'];

// 1. Quelle scannen
$src = [];
foreach (scandir($SRC) as $f) {
	$p = "$SRC/$f"; if (!is_file($p) || !preg_match('/\.(jpe?g|png)$/i', $f) || in_array($f, $skip, true)) continue;
	if (!preg_match('/^([a-z]+)-(.+)\.[a-zA-Z]+$/', $f, $m)) { echo "skip-unpraefixiert\t$f\t\t\t\n"; continue; }
	$L = load($p); if (!$L) { echo "fehler\t$f\t\t\t\n"; continue; }
	[$im, $w, $h] = $L; $pre = $map[$m[1]] ?? $m[1]; $rest = slug($m[2]);
	$portrait = $h > $w;
	$target = $portrait ? 'pool-hochformat-' . ($pre === 'pool' ? '' : $pre . '-') . $rest . '.jpg' : $pre . '-' . $rest . '.jpg';
	$tpre = $portrait ? 'pool-hochformat' : $pre;
	$src[] = ['file' => $f, 'path' => $p, 'w' => $w, 'h' => $h, 'ar' => $w / $h, 'hash' => ahash($im, $w, $h), 'target' => $target, 'tpre' => $tpre];
	imagedestroy($im);
}
// 2. Repo-Bestand hashen (pool + imagery root)
$repo = [];
foreach (array_merge(glob("$POOL/*.jpg"), glob("$IMG/*.jpg"), glob("$IMG/*.png")) as $p) {
	$L = load($p); if (!$L) continue; [$im, $w, $h] = $L;
	$repo[] = ['path' => $p, 'file' => basename($p), 'inpool' => str_contains($p, '/pool/'), 'w' => $w, 'h' => $h, 'ar' => $w / $h, 'hash' => ahash($im, $w, $h)]; imagedestroy($im);
}
// 3. Cluster (Union-Find über Quelle)
$n = count($src); $par = range(0, $n - 1);
$find = function ($i) use (&$par, &$find) { return $par[$i] === $i ? $i : ($par[$i] = $find($par[$i])); };
for ($i = 0; $i < $n; $i++) for ($j = $i + 1; $j < $n; $j++) if (same($src[$i], $src[$j])) $par[$find($i)] = $find($j);
$clusters = []; for ($i = 0; $i < $n; $i++) $clusters[$find($i)][] = $i;

$stats = ['neu' => 0, 'kopie-aus-repo' => 0, 'schon-da' => 0, 'dublette-im-praefix' => 0, 'repo-upgrade' => 0, 'hochformat' => 0];
$written = [];
foreach ($clusters as $members) {
	usort($members, fn($a, $b) => ($src[$b]['w'] * $src[$b]['h']) <=> ($src[$a]['w'] * $src[$a]['h']));
	$master = $src[$members[0]];
	// Repo-Treffer
	$hits = array_values(array_filter($repo, fn($r) => same($r, $master)));
	$poolHits = array_values(array_filter($hits, fn($r) => $r['inpool']));
	$hitPre = []; foreach ($poolHits as $r) { if (preg_match('/^([a-z]+(?:-hochformat)?)-/', $r['file'], $mm)) $hitPre[$mm[1]] = $r['file']; }
	$cid = $master['file'];
	// Prefix-Dedup innerhalb des Clusters: je Ziel-Präfix nur EIN Name
	$byPre = [];
	foreach ($members as $i) { $s = $src[$i]; if (isset($byPre[$s['tpre']])) { echo "dublette-im-praefix\t{$s['file']}\t{$s['target']}\t$cid\tbehalten: {$byPre[$s['tpre']]}\n"; $stats['dublette-im-praefix']++; continue; } $byPre[$s['tpre']] = $s['target']; }
	// Quelle der Bytes: bestes Repo-Bild (>=1600) oder Master neu rendern
	$bestRepo = null; foreach ($poolHits as $r) if (max($r['w'], $r['h']) >= 1600 && (!$bestRepo || $r['w'] * $r['h'] > $bestRepo['w'] * $bestRepo['h'])) $bestRepo = $r;
	$rendered = null; // Pfad der ersten neu gerenderten Datei (für Byte-Kopien)
	foreach ($byPre as $tpre => $target) {
		$srcFile = array_values(array_filter($members, fn($i) => $src[$i]['target'] === $target))[0];
		$sf = $src[$srcFile]['file'];
		if ($tpre === 'pool-hochformat') $stats['hochformat']++;
		if (isset($hitPre[$tpre])) { echo "schon-da\t$sf\t{$hitPre[$tpre]}\t$cid\t\n"; $stats['schon-da']++; continue; }
		if (file_exists("$POOL/$target")) { echo "schon-da\t$sf\t$target\t$cid\tName existiert\n"; $stats['schon-da']++; continue; }
		if ($bestRepo) { echo "kopie-aus-repo\t$sf\t$target\t$cid\t{$bestRepo['file']}\n"; $stats['kopie-aus-repo']++;
			if ($MODE === 'write') { copy($bestRepo['path'], "$POOL/$target"); if (file_exists($bestRepo['path'] . '.webp')) copy($bestRepo['path'] . '.webp', "$POOL/$target.webp"); else { $L = load("$POOL/$target"); imagewebp($L[0], "$POOL/$target.webp", 80); imagedestroy($L[0]); } }
			continue; }
		if ($rendered) { echo "neu-kopie\t$sf\t$target\t$cid\t= $rendered\n"; $stats['neu']++; if ($MODE === 'write') { copy("$POOL/$rendered", "$POOL/$target"); copy("$POOL/$rendered.webp", "$POOL/$target.webp"); } continue; }
		echo "neu\t{$master['file']}\t$target\t$cid\t{$master['w']}x{$master['h']}\n"; $stats['neu']++;
		if ($MODE === 'write') { $L = load($master['path']); save($L[0], $L[1], $L[2], "$POOL/$target"); imagedestroy($L[0]); }
		$rendered = $target;
	}
	// Alte kleine Repo-Pool-Dateien desselben Motivs auf die neue Qualität heben (md5-Gleichheit für Dedup)
	$newFile = $rendered ?? null;
	if ($newFile) foreach ($poolHits as $r) if (max($r['w'], $r['h']) < 1600) { echo "repo-upgrade\t{$r['file']}\t$newFile\t$cid\t{$r['w']}x{$r['h']}\n"; $stats['repo-upgrade']++; if ($MODE === 'write') { copy("$POOL/$newFile", $r['path']); copy("$POOL/$newFile.webp", $r['path'] . '.webp'); } }
}
// Verdächtige Nähe (Abstand 2–3) nur melden
for ($i = 0; $i < $n; $i++) for ($j = $i + 1; $j < $n; $j++) { $d = ham($src[$i]['hash'], $src[$j]['hash']); if ($d >= 2 && $d <= 3 && $src[$i]['tpre'] === $src[$j]['tpre'] && abs($src[$i]['ar'] - $src[$j]['ar']) < 0.03) echo "pruefen-aehnlich\t{$src[$i]['file']}\t{$src[$j]['file']}\t\t$d\n"; }
fwrite(STDERR, json_encode($stats) . "\n");
