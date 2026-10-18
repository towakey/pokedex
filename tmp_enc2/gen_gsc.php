<?php
// GSC encounter.json generator
require __DIR__ . '/core.php';

$enMap = loadEnMap(__DIR__ . '/../pokedex/pokedex.json');

$VERWORD = ['gold' => '金', 'silver' => '銀', 'crystal' => 'クリスタル'];
$VEREN = ['gold' => 'Gold', 'silver' => 'Silver', 'crystal' => 'Crystal'];

// parse "The in-game trade in X and Y is for the player's Z." sentences → per-version partner
function tradeNoteJp($note, $VERWORD) {
    $out = [];
    if (!preg_match_all('/trade(?:\s+in\s+([A-Za-z, ]+?))?\s+is for the player\'s\s+(?:(female|male)\s+)?([A-Za-z\'\. -]+?)[\.\n]/i', $note, $m, PREG_SET_ORDER)) return '';
    foreach ($m as $t) {
        $vers = [];
        if (trim($t[1] ?? '') !== '') {
            $vs = preg_split('/\s*(?:,|\band\b)\s*/', trim($t[1]));
            $vers = array_filter(array_map(function ($v) use ($VERWORD) {
                $k = strtolower(trim($v));
                return $VERWORD[$k] ?? null;
            }, $vs));
        }
        $partner = trim($t[3]);
        $p = $partner;
        global $enMap;
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
    'vermap' => ['G' => 'gold', 'S' => 'silver', 'C' => 'crystal'],
    'verorder' => ['gold', 'silver', 'crystal'],
    'game_version' => 'Gold_Silver_Crystal',
    'region_key' => 'johto',
    'locmap' => require __DIR__ . '/loc_johto.php',
    'water_routes' => [19, 20, 21, 40, 41],
    'phrase_map' => [
        "lugia's room" => 'ルギアの部屋',
        'outside and 2f' => '外・2F',
        '1f front' => '1F表',
        'odd egg' => 'ふしぎなタマゴ',
        'lower' => '下層',
        'upper' => '上層',
        'basement' => '地下',
        'near' => '近く',
    ],
    'method_handler' => function (&$methods, $e, $name, $versions, $locJp, $areas, $note, $enMap, $cfg) {
        $type = null; $locs = $locJp; $mnote = '';
        $m = $e['method'];
        if ($m === 'Walking') {
            if (preg_match('/^Roaming/i', $e['locText'])) {
                $methods[] = mkMethod('野生（徘徊）', $versions, ['ジョウト地方を徘徊'], '');
                return;
            }
            // split locations by area type into separate methods
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
            if ($q === '(Rare)') $type = '野生（ずつき・レアな木）';
            elseif ($q === '(Common)') $type = '野生（ずつき・通常の木）';
        } elseif ($m === 'Rock Smash') {
            $type = '野生（いわくだき）';
        } elseif (preg_match('/^Fishing(?: \((.*)\))?$/', $m, $fm)) {
            $rodmap = [
                'any Rod' => '', 'Old & Good Rod' => '・ボロのつりざお／いいつりざお',
                'Good & Super Rod' => '・いいつりざお／すごいつりざお',
                'Super Rod' => '・すごいつりざお', 'Good Rod' => '・いいつりざお', 'Old Rod' => '・ボロのつりざお',
            ];
            $type = '野生（釣り' . ($rodmap[$fm[1] ?? ''] ?? '') . '）';
        } elseif ($m === 'Static') {
            $type = '固定シンボル';
        } elseif ($m === 'Gift') {
            $type = 'もらう';
            if (strpos($e['locText'], 'Odd Egg') !== false) $mnote = 'ふしぎなタマゴ（孵化するポケモンはランダム）';
            if (strpos($e['locText'], '(egg)') !== false || preg_match('/\begg\b/i', $e['locText'])) { if ($mnote==='') $mnote = 'タマゴ'; }
        } elseif ($m === 'Purchase') {
            $type = '景品交換';
        } elseif ($m === 'In-Game Trade') {
            $type = 'NPC交換';
            $tn = tradeNoteJp($note, ['gold' => '金', 'silver' => '銀', 'crystal' => 'クリスタル']);
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
                $parents = preg_split('/\s+or\s+/i', trim($bm[1]));
                $pj = implode('・', array_map(function ($p) use ($enMap) { return jpnName(preg_replace("/^(a|an)\s+/i","",trim($p)), $enMap); }, $parents));
                $mnote = 'タマゴから孵化（親：' . $pj . '）';
            } elseif (preg_match('/^Trade from (.+)$/', $lt, $tm)) {
                $type = '他ソフトから交換';
                $locs = [];
            } elseif (preg_match('/only way to (?:obtain|get)/i', $lt)) {
                $type = 'イベント';
                $locs = [];
                $versions = $cfg['verorder'];
                $mnote = 'イベント配布（通常プレイでは入手不可）';
            }
        }
        if (!$type) return;
        // generic note translation
        if ($note !== '') {
            if (preg_match('/Only (one|two|three)/i', $note, $om)) {
                $n = ['one' => 1, 'two' => 2, 'three' => 3][strtolower($om[1])];
                $gn = "{$n}体のみ";
                if (stripos($note, 'will not return') !== false) $gn .= '（倒す・逃げると再出現しない）';
                $mnote = $mnote !== '' ? "$mnote。$gn" : $gn;
            } elseif (stripos($note, 'evolves by trading') !== false && $type === 'NPC交換') {
                $mnote = $mnote !== '' ? "$mnote。交換後すぐ進化する" : '交換後すぐ進化する';
            } elseif (stripos($note, 'Odd Egg') !== false && $type === 'もらう') {
                // already handled
            }
        }
        $methods[] = mkMethod($type, $versions, $locs, $mnote);
    },
];

$out = buildEncounter(__DIR__ . '/GSC_locations.html.parsed.json', $cfg, $enMap);
echo count($out) . " pokemon\n";
writeEncounter($out, $cfg, __DIR__ . '/../pokedex/Gold_Silver_Crystal/encounter.json');
echo "written\n";
