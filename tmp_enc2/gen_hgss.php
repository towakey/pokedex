<?php
// HGSS encounter.json generator
require __DIR__ . '/core.php';

$enMap = loadEnMap(__DIR__ . '/../pokedex/pokedex.json');

$cfg = [
    'vermap' => ['HG' => 'heartgold', 'SS' => 'soulsilver'],
    'verorder' => ['heartgold', 'soulsilver'],
    'game_version' => 'HeartGold_SoulSilver',
    'region_key' => 'johto',
    'locmap' => require __DIR__ . '/loc_johto.php',
    'water_routes' => [19, 20, 21, 40, 41],
    'name_map' => [
        'shellos (west)' => 'shellos', 'shellos (east)' => 'shellos',
        'gastrodon (west)' => 'gastrodon', 'gastrodon (east)' => 'gastrodon',
    ],
    'phrase_map' => [
        "lugia's room" => 'ルギアの部屋', 'outside' => '外', 'summit' => '頂上',
        'interior' => '内部', 'exterior' => '外', 'entrance' => '入り口',
        'lower' => '下層', 'upper' => '上層', 'basement' => '地下', 'rooftop' => '屋上',
        'outside and 2f' => '外・2F', '1f front' => '1F表', 'odd egg' => 'ふしぎなタマゴ',
        'east' => '東', 'west' => '西', 'north' => '北', 'south' => '南',
        'east gate' => '東ゲート', 'west gate' => '西ゲート',
        'storm' => 'あらし', 'shore' => '岸辺', 'area 1' => 'エリア1', 'area 2' => 'エリア2',
        'area 3' => 'エリア3', 'area 4' => 'エリア4', 'area 5' => 'エリア5',
        'area 6' => 'エリア6', 'peak' => '頂上', 'desert' => 'さばく', 'wasteland' => 'あれち',
        'meadow' => 'はらっぱ', 'savannah' => 'サバンナ', 'swamp' => 'しつげん',
        'marshland' => 'しつち', 'rocky beach' => 'いそべ', 'forest' => 'もり',
        'mountainside' => 'やまおく', 'plains' => 'へいげん',
    ],
    'method_handler' => function (&$methods, $e, $name, $versions, $locJp, $areas, $note, $enMap, $cfg) {
        $type = null; $locs = $locJp; $mnote = '';
        $m = $e['method'];
        if ($m === 'Walking') {
            if (preg_match('/^Roaming/i', $e['locText'])) {
                $region = in_array($name, ['Latias', 'Latios']) ? 'カントー地方を徘徊' : 'ジョウト地方を徘徊';
                $methods[] = mkMethod('野生（徘徊）', $versions, [$region], '');
                return;
            }
            $groups = ['野生（草むら）' => [], '野生（洞窟・屋内）' => []];
            foreach ($locJp as $i => $l) {
                $a = $areas[$i] ?? 'grass';
                $groups[($a === 'cave' || $a === 'building') ? '野生（洞窟・屋内）' : '野生（草むら）'][] = $l;
            }
            foreach ($groups as $t => $ls) {
                if ($ls) $methods[] = mkMethod($t, $versions, $ls, '');
            }
            return;
        } elseif ($m === 'Surfing') {
            $type = '野生（水上）';
        } elseif (preg_match('/^Headbutt(.*)$/', $m, $hm)) {
            $q = trim($hm[1]);
            $type = '野生（ずつき）';
            if (preg_match('/Group A/', $q) && !preg_match('/B/', $q)) $type = '野生（ずつき・グループA）';
            elseif (preg_match('/Group B/', $q) && !preg_match('/A/', $q)) $type = '野生（ずつき・グループB）';
            elseif (preg_match('/Groups A and B/', $q)) $type = '野生（ずつき・グループA・B）';
            elseif (preg_match('/Special/', $q)) $type = '野生（ずつき・特別）';
        } elseif ($m === 'Rock Smash') {
            $type = '野生（いわくだき）';
        } elseif ($m === 'Pokéwalker') {
            $type = 'ポケウォーカー';
        } elseif (preg_match('/^Fishing(?: \((.*)\))?$/', $m, $fm)) {
            $rodmap = [
                'any Rod' => '', 'Old & Good Rod' => '・ボロのつりざお／いいつりざお',
                'Old & Good Rods' => '・ボロのつりざお／いいつりざお',
                'Good & Super Rod' => '・いいつりざお／すごいつりざお',
                'Good & Super Rods' => '・いいつりざお／すごいつりざお',
                'Super Rod' => '・すごいつりざお', 'Good Rod' => '・いいつりざお', 'Old Rod' => '・ボロのつりざお',
            ];
            $type = '野生（釣り' . ($rodmap[$fm[1] ?? ''] ?? '') . '）';
        } elseif ($m === 'Static') {
            $type = '固定シンボル';
        } elseif ($m === 'Gift') {
            $type = 'もらう';
            if (stripos($e['locText'], 'egg') !== false) $mnote = 'タマゴ';
        } elseif ($m === 'Purchase') {
            $type = '景品交換';
        } elseif ($m === 'In-Game Trade') {
            $type = 'NPC交換';
            if (preg_match_all('/trade(?:\s+in\s+([A-Za-z, ]+?))?\s+is for the player\'s\s+(?:(female|male)\s+)?([A-Za-z\'\. -]+?)[\.\n]/i', $note, $tm, PREG_SET_ORDER)) {
                $out = [];
                $VW = ['heartgold' => 'ハートゴールド', 'soulsilver' => 'ソウルシルバー', 'heart' => 'ハートゴールド', 'soul' => 'ソウルシルバー'];
                foreach ($tm as $t) {
                    $vers = [];
                    if (trim($t[1] ?? '') !== '') {
                        $vers = array_filter(array_map(function ($v) use ($VW) { return $VW[strtolower(trim($v))] ?? null; }, preg_split('/\s*(?:,|\band\b)\s*/', trim($t[1]))));
                    }
                    $partner = trim($t[3]);
                    $info = $enMap[strtolower(str_replace('’', "'", $partner))] ?? null;
                    $p = $info ? $info['jpn'] : $partner;
                    if (($t[2] ?? '') === 'female') $p = 'メスの' . $p;
                    if (($t[2] ?? '') === 'male') $p = 'オスの' . $p;
                    $out[] = ($vers ? implode('・', $vers) : '全バージョン') . '：' . $p . ' を渡す';
                }
                if ($out) $mnote = implode('／', $out);
            }
        } elseif ($m === 'Text') {
            $lt = $e['locText'];
            $locs = [];
            if (preg_match('/^Evolve ([A-Z][^,]+?)(,.*| or .*)?$/u', $lt, $em)) {
                $type = '進化のみ';
                $mnote = jpnName(trim($em[1]), $enMap) . 'から進化';
                if (isset($em[2]) && preg_match('/trad|transfer/i', $em[2])) $mnote .= '（または他ソフトから交換・転送）';
            } elseif (preg_match('/^Trade from (.*?) or breed (?:a |an )?([A-Za-z\'\. ♀♂-]+?) traded/i', $lt, $tm)) {
                $methods[] = mkMethod('他ソフトから交換', $versions, [], '');
                $methods[] = mkMethod('タマゴ孵化', $versions, [], 'タマゴから孵化（親：' . jpnName(trim($tm[2]), $enMap) . '・交換のみ）');
                return;
            } elseif (preg_match('/^Breed (?:a |an )?(.+)$/', $lt, $bm)) {
                $type = 'タマゴ孵化';
                $raw = trim($bm[1]);
                $raw = preg_replace('/\bor\s+trad\w+\b.*$/i', '', $raw);
                $raw = preg_replace('/\btrad\w*\s+from\b.*$/i', '', $raw);
                $raw = preg_replace('/\btrad\w+\s+it\b.*$/i', '', $raw);
                $raw = preg_replace('/,?\s*and evolve the resulting.*$/i', '', $raw);
                $parents = preg_split('/\s*(?:,|\bor\b|\band\b)\s*/i', $raw);
                $pj = implode('・', array_filter(array_map(function ($p) use ($enMap) {
                    $p = preg_replace('/^(a|an)\s+/i', '', trim($p));
                    return ($p === '' || !preg_match('/^[A-Z]/', $p)) ? null : jpnName($p, $enMap);
                }, $parents)));
                $mnote = 'タマゴから孵化（親：' . $pj . '）';
            } elseif (preg_match('/^(Trade|Transfer) from/i', $lt)) {
                $type = '他ソフトから交換';
            } elseif (preg_match('/only way to (?:obtain|get)/i', $lt)) {
                $type = 'イベント';
                $versions = $cfg['verorder'];
                $mnote = 'イベント配布（通常プレイでは入手不可）';
            }
        }
        if (!$type) return;
        if ($note !== '') {
            if (preg_match('/Only (one|two|three)/i', $note, $om)) {
                $n = ['one' => 1, 'two' => 2, 'three' => 3][strtolower($om[1])];
                $gn = "{$n}体のみ";
                if (stripos($note, 'will not return') !== false) $gn .= '（倒す・逃げると再出現しない）';
                $mnote = $mnote !== '' ? $mnote . '。' . $gn : $gn;
            } elseif (stripos($note, 'evolves by trading') !== false && $type === 'NPC交換') {
                $mnote = $mnote !== '' ? "$mnote。交換後すぐ進化する" : '交換後すぐ進化する';
            } elseif (preg_match('/(event|ticket)/i', $note) && $type === '固定シンボル') {
                $mnote = $mnote !== '' ? "$mnote。イベントアイテム限定" : 'イベントアイテム限定';
            }
        }
        $methods[] = mkMethod($type, $versions, $locs, $mnote);
    },
];

$out = buildEncounter(__DIR__ . '/HGSS_locations.html.parsed.json', $cfg, $enMap);
echo count($out) . " pokemon\n";
writeEncounter($out, $cfg, __DIR__ . '/../pokedex/HeartGold_SoulSilver/encounter.json');
echo "written\n";
