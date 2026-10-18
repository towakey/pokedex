<?php
$d = json_decode(file_get_contents('GSC_locations.html.parsed.json'), true);
foreach (['Raikou','Entei','Suicune'] as $n) {
    echo "== $n ==\n";
    foreach ($d[$n] ?? [] as $e) echo '  ' . $e['method'] . ' | ' . substr($e['locText'],0,90) . "\n";
}
$pd = json_decode(file_get_contents('../pokedex/pokedex.json'), true)['pokedex'];
$en = [];
foreach ($pd as $no => $fs) foreach ($fs as $f) {
    $e = strtolower(str_replace("\u{2019}", "'", $f['name']['eng'] ?? ''));
    $en[$e] = $no;
}
$have = [];
foreach (array_keys($d) as $n) {
    $k = strtolower(str_replace("\u{2019}", "'", $n));
    $have[$en[$k] ?? '?'] = $n;
}
$missing = [];
for ($i = 1; $i <= 251; $i++) {
    $no = sprintf('%04d', $i);
    if (!isset($have[$no])) $missing[] = $i;
}
echo "missing: " . implode(',', $missing) . "\n";
echo "unmapped names: ";
foreach (array_keys($d) as $n) {
    $k = strtolower(str_replace("\u{2019}", "'", $n));
    if (!isset($en[$k])) echo "$n ";
}
echo "\n";
