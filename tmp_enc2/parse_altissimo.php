<?php
// Universal parser for altissimo1 locations.html files
// usage: php parse_altissimo.php <file> <table_index> <num_version_cols>
// outputs <file>.parsed.json
$file = $argv[1];
$tableIdx = (int)($argv[2] ?? 0);
$numVer = (int)($argv[3] ?? 3);

$html = file_get_contents($file);

// extract all tables
preg_match_all('/<tbody>(.*?)<\/tbody>/s', $html, $tm);
$body = $tm[1][$tableIdx] ?? $tm[1][0];

preg_match_all('/<tr[^>]*>(.*?)<\/tr>/s', $body, $m);
$rows = $m[1];

$currentPokemon = null;
$activeNotes = []; // [ ['text'=>..,'rows'=>n], ... ]
$data = [];

foreach ($rows as $row) {
    // consume one physical row of each active note's rowspan
    foreach ($activeNotes as $i => $n) {
        $activeNotes[$i]['rows']--;
        if ($activeNotes[$i]['rows'] <= 0) unset($activeNotes[$i]);
    }
    $activeNotes = array_values($activeNotes);
    if (preg_match('/alt="([^"]+)"/', $row, $am)) {
        $currentPokemon = $am[1];
    }
    // version cells: td containing only version letters (uppercase letters/digits)
    preg_match_all('/<td class="([a-z0-9-]+)">\s*([A-Z][A-Za-z0-9]*)\s*<\/td>/', $row, $vm, PREG_SET_ORDER);
    if (!$vm) continue;
    // take last $numVer cells that look like version columns
    $vcells = [];
    foreach ($vm as $v) {
        if (strlen($v[2]) <= 3 && $v[2] !== $currentPokemon) $vcells[] = $v;
    }
    if (count($vcells) < $numVer) continue;
    $vcells = array_slice($vcells, 0, $numVer);
    $applies = [];
    $allLight = true;
    foreach ($vcells as $v) {
        $isLight = (strpos($v[1], 'light') === 0);
        if (!$isLight) $allLight = false;
        $applies[$v[2]] = !$isLight;
    }
    if ($allLight) foreach ($applies as $k => $_) $applies[$k] = true;

    // location cell: last td with text-align:left (may have colspan)
    preg_match_all('/<td class="[^"]*" style="text-align:left;?[^"]*"(?: colspan="\d+")?>(.*?)<\/td>/s', $row, $lm);
    if (!$lm[1]) continue;
    $locHtml = $lm[1][0];

    $rowStart = count($data[$currentPokemon] ?? []);
    $parts = preg_split('/<br\s*\/?>/', $locHtml);
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') continue;
        $method = '';
        $locText = '';
        if (preg_match('/<strong>([^<]+)<\/strong>\s*(\([^)]*\))?\s*:\s*(.*)/s', $part, $mm)) {
            $method = trim($mm[1]) . (isset($mm[2]) && $mm[2] !== '' ? ' ' . trim($mm[2]) : '');
            $locText = $mm[3];
        } else {
            $method = 'Text';
            $locText = $part;
        }
        $locs = [];
        preg_match_all('/<a href="[^"]*?([a-z0-9-]+)\.html[^"]*">([^<]*)<\/a>/', $locText, $am2, PREG_SET_ORDER);
        foreach ($am2 as $a) {
            $locs[] = ['slug' => $a[1], 'en' => trim($a[2])];
        }
        $plain = trim(html_entity_decode(strip_tags($locText)));
        $data[$currentPokemon][] = [
            'versions' => $applies,
            'method' => $method,
            'locations' => $locs,
            'locText' => $plain,
        ];
    }
    // note cells: tds that are not version/name/img/location cells; rowspan governs duration
    if (preg_match_all('/<td class="[^"]*"( rowspan="(\d+)")?>(.*?)<\/td>/s', $row, $tm2, PREG_SET_ORDER)) {
        $last = end($tm2);
        $lastHtml = $last[3];
        $lastText = trim(html_entity_decode(strip_tags($lastHtml)));
        if ($lastText !== '' && strpos($lastHtml, 'text-align:left') === false && strpos($lastHtml, 'img') === false
            && !preg_match('/^\s*[A-Z][A-Za-z0-9]{0,2}\s*$/', $lastText) && $lastText !== $currentPokemon) {
            $activeNotes[] = ['text' => $lastText, 'rows' => (int)($last[2] ?: 1)];
        }
    }
    if ($activeNotes && isset($data[$currentPokemon])) {
        $texts = array_unique(array_column($activeNotes, 'text'));
        $note = implode("\n", $texts);
        $cnt = count($data[$currentPokemon]);
        for ($i = $rowStart; $i < $cnt; $i++) {
            if (!isset($data[$currentPokemon][$i]['note'])) $data[$currentPokemon][$i]['note'] = $note;
        }
    }
}

file_put_contents($file . '.parsed.json', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo basename($file) . ": " . count($data) . " pokemon\n";
