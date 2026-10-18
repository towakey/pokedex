<?php
// Legends: Arceus encounter.json generator
// Source: altissimo Supplementary-Series/Legends/Legends-Arceus/locations.html
require __DIR__ . '/core.php';

$enMap = loadEnMap(__DIR__ . '/../pokedex/pokedex.json');

$locJp = [
    'Jubilife Village' => 'コトブキムラ',
    'Obsidian Fieldlands' => '黒曜の原野',
    'Crimson Mirelands' => '紅蓮の湿地',
    'Cobalt Coastlands' => '群青の海岸',
    'Coronet Highlands' => '天冠の山麓',
    'Alabaster Icelands' => '純白の凍土',
    'Ancient Retreat' => '古の隠れ里',
    'Alabsater Icelands' => '純白の凍土',
];
$subJp = [
    'Galaxy Hall' => 'ギンカン団本部',
    'Snowpoint Temple' => 'キッサキ神殿',
    'Hall of Origin' => 'はじまりの間',
    'Valor Cavern' => '真心の空洞',
    'Acuity Cavern' => '英知の空洞',
    'Verity Cavern' => '真実の空洞',
    'Lava Dome Sanctum' => '溶岩ドームの奥地',
    'Moonview Arena' => '月見の舞台',
    'Scarlet Bog' => '紅い沼',
    'Ramanas Island' => 'バサギリ島',
    'Turnback Cave' => '戻りの洞窟',
    'Seaside Hollow' => '海辺の小洞穴',
    'Bonechill Wastes' => 'こおりざけの荒野',
    'Wayward Cave' => '迷いの洞窟',
    'Solaceon Ruins' => 'ズイの遺跡',
    'Temple of Sinnoh' => 'シンオウ神殿',
    'water north of Sand\'s Reach' => '砂の手間の北の海',
];
$typeMap = [
    'Wild' => '野生（フィールド）',
    'Static' => '固定シンボル',
    'Static alpha' => '固定シンボル（親分）',
    'Air' => '野生（空中）',
    'Water' => '野生（水上）',
    'Shaking trees' => '野生（揺れる木）',
    'Shaking ores' => '野生（揺れる岩）',
    'Shaking boxes' => '野生（揺れる箱）',
    'Mass outbreak' => '大量発生',
    'Massive mass outbreak' => '大大大発生',
    'Space-time distortion' => '野生（時空の歪み）',
    'Gift' => 'もらう',
];

function laTranslateNote($note) {
    $note = trim(preg_replace('/\s+/', ' ', $note));
    if ($note === '' || $note === 'N/A') return '';
    if (preg_match('/can only be found in space-time distortions after seeing the credits/i', $note))
        return 'エンディング後に時空の歪みでのみ出現';
    if (preg_match('/gift \w+ can either be obtained in Galaxy Hall as the player\'s starter/i', $note))
        return 'ギンカン団本部で御三家として入手、またはエンディング後にラベン博士から入手';
    return null; // unknown -> caller logs
}

$h = file_get_contents(__DIR__ . '/la_locations.html');
$rows = preg_split('/<tr[^>]*>/', $h);
array_shift($rows); array_shift($rows); // header

$unknownSub = []; $unknownNote = []; $unknownLoc = []; $empty = [];
$out = [];
foreach ($rows as $r) {
    $cells = preg_split('/<td[^>]*>/', $r);
    if (count($cells) < 5) continue;
    $name = trim(strip_tags($cells[2] ?? ''));
    if ($name === '') continue;
    $base = preg_replace('/ \(.+\)$/', '', $name); // drop form suffix
    $key = strtolower(str_replace('’', "'", $base));
    $info = $enMap[$key] ?? null;
    if (!$info) { fwrite(STDERR, "unknown pokemon: $name\n"); continue; }

    $methods = [];
    // Normal (col3) + Special (col4) cells, each holding <strong>Method</strong>: links
    for ($ci = 3; $ci <= 4; $ci++) {
        $cell = $cells[$ci] ?? '';
        if (preg_match('/^\s*N\/A\s*$/', strip_tags($cell))) continue;
        // split into method segments
        $segs = preg_split('/<strong>([^<]+)<\/strong>\s*:/', $cell, -1, PREG_SPLIT_DELIM_CAPTURE);
        // segs: [pre, method, body, method, body...]
        for ($i = 1; $i < count($segs); $i += 2) {
            $mName = trim($segs[$i]);
            $body = $segs[$i + 1] ?? '';
            $type = $typeMap[$mName] ?? null;
            if (!$type) { fwrite(STDERR, "unknown method: $mName\n"); continue; }
            // each entry: <a ...>LocName</a> followed by optional (qualifier)
            preg_match_all('/<a[^>]*>([^<]+)<\/a>\s*(\([^)]*\))?/', $body, $mm, PREG_SET_ORDER);
            $locs = [];
            foreach ($mm as $lm) {
                $en = trim($lm[1]);
                $base = $locJp[$en] ?? null;
                if ($base === null) { $unknownLoc[$en] = 1; $base = $en; }
                $q = isset($lm[2]) ? trim($lm[2], '() ') : '';
                if ($q !== '') {
                    if (isset($subJp[$q])) $base .= '（' . $subJp[$q] . '）';
                    elseif (preg_match('/outbreaks?$/', $q)) { /* outbreak suffix, drop */ }
                    elseif ($q === 'two') $base .= '（2体）';
                    elseif ($q === 'Shiny') $base .= '（色違い）';
                    else { $unknownSub["$en|$q"] = 1; }
                }
                $locs[] = $base;
            }
            $methods[] = mkMethod($type, ['legendsarceus'], $locs, '');
        }
    }
    // Notes cell
    $note = trim(strip_tags($cells[5] ?? ''));
    // Evolve rows: Normal cell is plain "Evolve X" text
    if (!$methods || true) {
        $normal = trim(strip_tags($cells[3]));
        if (preg_match('/^Evolve (.+?)(,.*)?$/u', $normal, $em)) {
            $mnote = jpnName(trim($em[1]), $enMap) . 'から進化';
            if (isset($em[2]) && preg_match('/trad|transfer/i', $em[2])) $mnote .= '（または他ソフトから交換）';
            $methods[] = mkMethod('進化のみ', ['legendsarceus'], [], $mnote);
        }
    }
    if ($note !== '') {
        $jpNote = laTranslateNote($note);
        if ($jpNote === null) {
            // mission/request notes -> generic
            if (preg_match('/(Mission|Request) (\d+): ([^\.]+)/', $note, $mn)) {
                $jpNote = preg_match('/Request/', $note) ? "サブ任務「{$mn[2]}」関連" : "メイン任務「{$mn[2]}」関連";
            } elseif (preg_match('/required to catch/i', $note)) {
                $jpNote = 'メイン任務2での捕獲が必須';
            } elseif (preg_match('/shiny Ponyta/i', $note)) {
                $jpNote = '色違いポニータ（サブ任務）';
            } elseif (preg_match('/after catching all 28 static letters/i', $note)) {
                $jpNote = '28種の固定アンノーン捕獲後';
            } elseif (preg_match('/static (?:alpha )?(\w+)/', $note)) {
                $jpNote = 'サブ任務関連の固定シンボル';
            } else {
                $unknownNote[$note] = 1;
            }
        }
        if ($jpNote && $methods) {
            // attach to last non-evolve method with no note yet
            $ti = null;
            for ($mi = count($methods) - 1; $mi >= 0; $mi--) {
                if ($methods[$mi]['type'] !== '進化のみ' && !isset($methods[$mi]['note'])) { $ti = $mi; break; }
            }
            if ($ti !== null) $methods[$ti]['note'] = $jpNote;
        }
    }
    if (!$methods) $empty[] = $name;
    if (isset($out[$info['no']])) {
        $out[$info['no']]['methods'] = mergeMethods(array_merge($out[$info['no']]['methods'], $methods), ['legendsarceus']);
    } else {
        $out[$info['no']] = ['globalNo' => $info['no'], 'name' => $info['jpn'], 'methods' => $methods];
    }
}
ksort($out);
echo count($out) . " pokemon\n";
if ($empty) echo "empty methods: " . implode(',', $empty) . "\n";
foreach (['unknown locations' => $unknownLoc, 'unknown sub' => $unknownSub, 'unknown notes' => $unknownNote] as $k => $u) {
    if ($u) { echo "== $k ==\n"; foreach ($u as $x => $_) echo "  $x\n"; }
}
$cfg = ['game_version' => 'LegendsArceus', 'region_key' => 'hisui'];
writeEncounter($out, $cfg, __DIR__ . '/../pokedex/LegendsArceus/encounter.json');
echo "written\n";
