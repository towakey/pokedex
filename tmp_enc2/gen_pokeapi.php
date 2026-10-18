<?php
// PokeAPI CSV -> encounter.json generator (wild encounters only)
// usage: php gen_pokeapi.php
require __DIR__ . '/core.php';

$dir = __DIR__;

function csv_rows($file) {
    $fh = fopen($file, 'r');
    $head = fgetcsv($fh);
    $rows = [];
    while (($r = fgetcsv($fh)) !== false) {
        $rows[] = array_combine($head, $r);
    }
    fclose($fh);
    return $rows;
}

// --- load tables ---
$encounters = csv_rows("$dir/encounters.csv");
$slots = csv_rows("$dir/encounter_slots.csv");
$methods = csv_rows("$dir/encounter_methods.csv");
$areas = csv_rows("$dir/location_areas.csv");
$locations = csv_rows("$dir/locations.csv");
$locnames = csv_rows("$dir/location_names.csv");
$versions = csv_rows("$dir/versions.csv");
$pokemon = csv_rows("$dir/pokemon.csv");

$enMap = loadEnMap(__DIR__ . '/../pokedex/pokedex.json');
$pd = json_decode(file_get_contents(__DIR__ . '/../pokedex/pokedex.json'), true)['pokedex'];

// pokemon_id -> species_id
$pid2spec = [];
foreach ($pokemon as $p) $pid2spec[$p['id']] = $p['species_id'];
// species_id -> dex no + jpn name (from pokedex.json entries keyed by national no)
$spec2no = [];
foreach ($pd as $no => $forms) $spec2no[$no] = $no; // globalNo keys are dex numbers

// slot -> method id
$slot2method = [];
foreach ($slots as $s) $slot2method[$s['id']] = $s['encounter_method_id'];
$methodName = [];
foreach ($methods as $m) $methodName[$m['id']] = $m['identifier'];

// area -> location_id + area identifier
$area2loc = [];
$areaIdent = [];
foreach ($areas as $a) {
    $area2loc[$a['id']] = $a['location_id'];
    $areaIdent[$a['id']] = $a['identifier'];
}
// location names: prefer ja(11) > ja-Hrkt(1) > en(9)
$locName = [];
foreach ($locnames as $ln) {
    $lid = $ln['location_id'];
    $lang = $ln['local_language_id'];
    if ($lang == '11' || ($lang == '1' && !isset($locName[$lid])) || ($lang == '9' && !isset($locName[$lid]))) {
        $locName[$lid] = $ln['name'];
    }
}
// version id -> repo version key
$VERID_TO_REPO = [
    'black-2' => 'black2', 'white-2' => 'white2',
    'ultra-sun' => 'ultrasun', 'ultra-moon' => 'ultramoon',
    'black' => 'black', 'white' => 'white',
    'x' => 'x', 'y' => 'y',
    'sun' => 'sun', 'moon' => 'moon',
    'sword' => 'sword', 'shield' => 'shield',
];
$verName = [];
foreach ($versions as $v) {
    $verName[$v['id']] = $VERID_TO_REPO[$v['identifier']] ?? $v['identifier'];
}

$METHOD_JP = [
    'walk' => '野生（草むら）',
    'dark-grass' => '野生（濃い草むら）',
    'grass-spots' => '野生（揺れる草むら）',
    'cave-spots' => '野生（ほこり・どうくつスポット）',
    'bridge-spots' => '野生（橋の影スポット）',
    'surf-spots' => '野生（波紋スポット）',
    'super-rod-spots' => '野生（釣り・泡スポット）',
    'old-rod' => '野生（釣り・ボロのつりざお）',
    'good-rod' => '野生（釣り・いいつりざお）',
    'super-rod' => '野生（釣り・すごいつりざお）',
    'surf' => '野生（水上）',
    'rock-smash' => '野生（いわくだき）',
    'headbutt' => '野生（ずつき）',
    'yellow-flowers' => '野生（きいろいはな）',
    'purple-flowers' => '野生（むらさきのはな）',
    'red-flowers' => '野生（あかいはな）',
    'rough-terrain' => '野生（どろかき）',
    'gift' => 'もらう',
    'gift-egg' => 'もらう（タマゴ）',
    'only-one' => '固定シンボル',
];

// area identifier -> JP qualifier
function areaJp($ident) {
    if ($ident === '' || $ident === null) return '';
    $ident = preg_replace('/^unknown-area-\d+$/', '', $ident);
    if ($ident === '') return '';
    if (preg_match('/^(\d)f$/', $ident, $m)) return '（' . $m[1] . 'F）';
    if (preg_match('/^b(\d)f$/', $ident, $m)) return '（B' . $m[1] . 'F）';
    if (preg_match('/^([a-d])$/', $ident, $m)) return '（エリア' . strtoupper($m[1]) . '）';
    if (preg_match('/^(.*)-area$/', $ident, $m)) return '（' . $m[1] . 'エリア）';
    $map = [
        'west-gate' => '西ゲート', 'east-gate' => '東ゲート',
        'victory-road-gate' => 'ゲート',
        'weather-institute' => 'てんきけんきゅうじょ',
        'game-freak-hq-1f' => 'ゲームフリーク1F',
        'inner' => '内部', 'outer' => '外', 'inside' => '内部', 'forest' => '森',
        'room' => '部屋', 'outside' => '外', 'cave' => '洞窟', 'entrance' => '入口',
        'basement' => '地下', 'summit' => '頂上', 'interior' => '内部',
        'surf' => '水上', 'fishing' => '釣り', 'walk' => '', 'grass' => '',
    ];
    foreach ($map as $k => $v) {
        if (preg_match('/\b' . $k . '\b/i', $ident)) return $v === '' ? '' : '（' . $v . '）';
    }
    if (preg_match('/-(\d)f$/', $ident, $m)) return '（' . $m[1] . 'F）';
    return '（' . $ident . '）';
}

// group: pokemon_no|method_en -> locations per version
$groups = [];
foreach ($encounters as $e) {
    $verId = $e['version_id'];
    $ver = $verName[$verId] ?? null;
    if (!$ver) continue;
    $areaId = $e['location_area_id'];
    $locId = $area2loc[$areaId] ?? null;
    if ($locId === null) continue;
    $locNameJp = $locName[$locId] ?? ('loc' . $locId);
    $locNameJp = strtr($locNameJp, ['０'=>'0','１'=>'1','２'=>'2','３'=>'3','４'=>'4','５'=>'5','６'=>'6','７'=>'7','８'=>'8','９'=>'9']);
    $aq = areaJp($areaIdent[$areaId] ?? '');
    $mid = $slot2method[$e['encounter_slot_id']] ?? null;
    $men = $methodName[$mid] ?? 'walk';
    $spec = $pid2spec[$e['pokemon_id']] ?? null;
    if ($spec === null) continue;
    $no = sprintf('%04d', $spec);
    $key = $no . '|' . $men . '|' . $locNameJp . $aq;
    $groups[$key]['ver'][$ver] = true;
    $groups[$key]['no'] = $no;
    $groups[$key]['method'] = $men;
    $groups[$key]['loc'] = $locNameJp . $aq;
}

// convert to per-pokemon methods merging versions
$byMon = [];
foreach ($groups as $g) {
    $vers = array_keys($g['ver']);
    $byMon[$g['no']][$g['method']][$g['loc']]['vers'] = array_merge(
        $byMon[$g['no']][$g['method']][$g['loc']]['vers'] ?? [], $vers
    );
}

// version groups
$verGroups = [
    'Black_White' => ['key' => 'unova', 'vers' => ['black', 'white']],
    'Black2_White2' => ['key' => 'unova', 'vers' => ['black2', 'white2']],
    'X_Y' => ['key' => 'kalos', 'vers' => ['x', 'y']],
];

// cave/inside keyword heuristic for "walk" type
function isCaveJp($loc) {
    return preg_match('/洞窟|どうくつ|トンネル|井戸|塔|タワー|遺跡|神殿|山|山脈|研究所|発電所|屋敷|地下水|下水道|工場|倉庫|ビル|墓地|内部|洞窟内部/', $loc);
}

foreach ($verGroups as $gameVer => $vg) {
    $out = [];
    foreach ($byMon as $no => $mm) {
        $methods = [];
        foreach ($mm as $men => $locs) {
            $type = $METHOD_JP[$men] ?? '野生（' . $men . '）';
            // split locations by version signature
            $sig = [];
            foreach ($locs as $loc => $info) {
                $vs = array_values(array_intersect($vg['vers'], array_unique($info['vers'])));
                if (!$vs) continue;
                $k = implode(',', $vs);
                if (!isset($sig[$k])) $sig[$k] = ['vers' => $vs, 'locs' => []];
                $sig[$k]['locs'][] = $loc;
            }
            foreach ($sig as $s) {
                $t = $type;
                if ($men === 'walk' && isCaveJp(implode('', $s['locs']))) {
                    // split cave vs grass
                    $grass = []; $cave = [];
                    foreach ($s['locs'] as $l) { if (isCaveJp($l)) $cave[] = $l; else $grass[] = $l; }
                    if ($grass) $methods[] = mkMethod('野生（草むら）', $s['vers'], $grass, '');
                    if ($cave) $methods[] = mkMethod('野生（洞窟・屋内）', $s['vers'], $cave, '');
                    continue;
                }
                $methods[] = mkMethod($t, $s['vers'], $s['locs'], '');
            }
        }
        if (!$methods) continue;
        $info = null;
        foreach ($pd[$no] ?? [] as $f) { $info = $f['name']['jpn'] ?? null; break; }
        if (!$info) continue;
        $out[$no] = ['globalNo' => $no, 'name' => $info, 'methods' => $methods];
    }
    // merge same type+locs
    foreach ($out as $no => $p) $out[$no]['methods'] = mergeMethods($p['methods'], $vg['vers']);
    ksort($out);
    $json = [
        'update' => '20261018',
        'game_version' => $gameVer,
        'encounter' => [$vg['key'] => $out],
    ];
    file_put_contents(__DIR__ . "/../pokedex/$gameVer/encounter.json",
        json_encode($json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "$gameVer: " . count($out) . " pokemon\n";
}
