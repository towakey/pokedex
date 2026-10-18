<?php
// usage: php check_gen.php <parsed.json> <max_dex_no>
$parsed = json_decode(file_get_contents($argv[1]), true);
$max = (int)($argv[2] ?? 386);
$inspect = array_slice($argv, 3);
foreach ($inspect as $n) {
    echo "== $n ==\n";
    foreach ($parsed[$n] ?? [] as $e) {
        echo '  ' . json_encode($e['versions']) . ' ' . $e['method'] . ' | ' . substr($e['locText'], 0, 80) . (isset($e['note']) ? ' <<' . substr($e['note'],0,60) . '>>' : '') . "\n";
    }
}
$pd = json_decode(file_get_contents('../pokedex/pokedex.json'), true)['pokedex'];
$en = [];
foreach ($pd as $no => $fs) foreach ($fs as $f) {
    $en[strtolower(str_replace("\u{2019}", "'", $f['name']['eng'] ?? ''))] = $no;
}
$have = [];
$unmapped = [];
foreach (array_keys($parsed) as $n) {
    $k = strtolower(str_replace("\u{2019}", "'", $n));
    if (isset($en[$k])) $have[$en[$k]] = 1; else $unmapped[] = $n;
}
$missing = [];
for ($i = 1; $i <= $max; $i++) if (!isset($have[sprintf('%04d', $i)])) $missing[] = $i;
echo "missing: " . implode(',', $missing) . "\n";
echo "unmapped: " . implode(',', $unmapped) . "\n";
