<?php
// RSE encounter.json generator
require __DIR__ . '/core.php';

$enMap = loadEnMap(__DIR__ . '/../pokedex/pokedex.json');

function tradeNoteJp_rse($note, $VERWORD) {
    $out = [];
    if (!preg_match_all('/trade(?:\s+in\s+([A-Za-z, ]+?))?\s+is for the player\'s\s+(?:(female|male)\s+)?([A-Za-z\'\. -]+?)[\.\n]/i', $note, $m, PREG_SET_ORDER)) return '';
    global $enMap;
    foreach ($m as $t) {
        $vers = [];
        if (trim($t[1] ?? '') !== '') {
            $vs = preg_split('/\s*(?:,|\band\b)\s*/', trim($t[1]));
            $vers = array_filter(array_map(function ($v) use ($VERWORD) {
                return $VERWORD[strtolower(trim($v))] ?? null;
            }, $vs));
        }
        $partner = trim($t[3]);
        $p = $partner;
        $info = $enMap[strtolower(str_replace('’', "'", $partner))] ?? null;
        if ($info) $p = $info['jpn'];
        if (($t[2] ?? '') === 'female') $p = 'メスの' . $p;
        if (($t[2] ?? '') === 'male') $p = 'オスの' . $p;
        $label = $vers ? implode('・', $vers) : '全バージョン';
        $out[] = $label . '：' . $p . ' を渡す';
    }
    return implode('／', $out);
}

$cfg = [
    'vermap' => ['R' => 'ruby', 'S' => 'sapphire', 'E' => 'emerald'],
    'verorder' => ['ruby', 'sapphire', 'emerald'],
    'game_version' => 'Ruby_Sapphire_Emerald',
    'region_key' => 'hoenn',
    'locmap' => require __DIR__ . '/loc_hoenn.php',
    'water_routes' => [105, 106, 107, 108, 109, 122, 124, 125, 126, 127, 128, 129, 130, 131, 132, 133, 134],
    'phrase_map' => [
        'underwater' => '水中', 'outside' => '外', 'summit' => '頂上', 'interior' => '内部',
        'exterior' => '外', 'rooftop' => '屋上', 'entrance' => '入り口',
    ],
    'method_handler' => function (&$methods, $e, $name, $versions, $locJp, $areas, $note, $enMap, $cfg) {
        $type = null; $locs = $locJp; $mnote = '';
        $m = $e['method'];
        if ($m === 'Walking') {
            if (preg_match('/^Roaming/i', $e['locText'])) {
                $rn = '';
                if (preg_match('/only if (\w+) is selected/i', $e['locText'], $rm)) {
                    $rn = 'エメラルド：' . ($rm[1] === 'Red' ? '「あか」を選択した場合のみ（ラティアス）' : '「あお」を選択した場合のみ（ラティオス）');
                }
                $methods[] = mkMethod('野生（徘徊）', $versions, ['ホウエン地方を徘徊'], $rn);
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
        } elseif ($m === 'Underwater') {
            $type = '野生（水中）';
        } elseif ($m === 'Rock Smash') {
            $type = '野生（いわくだき）';
        } elseif (preg_match('/^Fishing(?: \((.*)\))?$/', $m, $fm)) {
            $rodmap = [
                'any Rod' => '', 'Old & Good Rods' => '・ボロのつりざお／いいつりざお',
                'Good & Super Rods' => '・いいつりざお／すごいつりざお',
                'Super Rod' => '・すごいつりざお', 'Good Rod' => '・いいつりざお', 'Old Rod' => '・ボロのつりざお',
            ];
            $type = '野生（釣り' . ($rodmap[$fm[1] ?? ''] ?? '') . '）';
        } elseif ($m === 'Static') {
            $type = '固定シンボル';
        } elseif ($m === 'Gift') {
            $type = 'もらう';
        } elseif ($m === 'In-Game Trade') {
            $type = 'NPC交換';
            $tn = tradeNoteJp_rse($note, ['ruby' => 'ルビー', 'sapphire' => 'サファイア', 'emerald' => 'エメラルド']);
            if ($tn !== '') $mnote = $tn;
        } elseif ($m === 'Text') {
            $lt = $e['locText'];
            $locs = [];
            if (preg_match('/^Evolve ([A-Z][^,]+?)(,.*| or .*)?$/u', $lt, $em)) {
                $type = '進化のみ';
                $mnote = jpnName(trim($em[1]), $enMap) . 'から進化';
                if (isset($em[2]) && preg_match('/trad|transfer/i', $em[2])) $mnote .= '（または他ソフトから交換・転送）';
            } elseif (preg_match('/^Breed (?:a |an )?(.+)$/', $lt, $bm)) {
                $type = 'タマゴ孵化';
                $raw = trim($bm[1]);
                $fromTrade = (bool)preg_match('/trad/i', $raw);
                $raw = preg_replace('/\btrad\w*\s+from\b.*$/i', '', $raw);
                $parents = preg_split('/\s*(?:,|\bor\b|\band\b)\s*/i', $raw);
                $pj = implode('・', array_filter(array_map(function ($p) use ($enMap) {
                    $p = preg_replace('/\b(traded?|obtainable|found|available)\b.*$/i', '', $p);
                    $p = preg_replace('/^(a|an)\s+/i', '', trim($p));
                    if ($p === '' || !preg_match('/^[A-Z]/', $p)) return null;
                    return jpnName($p, $enMap);
                }, $parents)));
                $mnote = 'タマゴから孵化（親：' . $pj . '）';
                if ($fromTrade) $mnote .= '（親は交換のみ）';
            } elseif (preg_match('/^Trade from (.+)$/', $lt)) {
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
                $mnote = $mnote !== '' ? "$mnote。$gn" : $gn;
            }
        }
        $methods[] = mkMethod($type, $versions, $locs, $mnote);
    },
];

$out = buildEncounter(__DIR__ . '/RSE_locations.html.parsed.json', $cfg, $enMap);
echo count($out) . " pokemon\n";
writeEncounter($out, $cfg, __DIR__ . '/../pokedex/Ruby_Sapphire_Emerald/encounter.json');
echo "written\n";
