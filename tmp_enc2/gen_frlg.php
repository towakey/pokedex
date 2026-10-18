<?php
// FRLG encounter.json generator
require __DIR__ . '/core.php';

$enMap = loadEnMap(__DIR__ . '/../pokedex/pokedex.json');

function tradeNoteJp_frlg($note) {
    $out = [];
    if (!preg_match_all('/trade(?:\s+in\s+([A-Za-z, ]+?))?\s+is for the player\'s\s+(?:(female|male)\s+)?([A-Za-z\'\. -]+?)[\.\n]/i', $note, $m, PREG_SET_ORDER)) return '';
    global $enMap;
    $VW = ['firered' => 'ファイアレッド', 'leafgreen' => 'リーフグリーン', 'fire' => 'ファイアレッド'];
    foreach ($m as $t) {
        $vers = [];
        if (trim($t[1] ?? '') !== '') {
            $vs = preg_split('/\s*(?:,|\band\b)\s*/', trim($t[1]));
            $vers = array_filter(array_map(function ($v) use ($VW) {
                return $VW[strtolower(trim($v))] ?? null;
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
    'vermap' => ['FR' => 'firered', 'LG' => 'leafgreen'],
    'verorder' => ['firered', 'leafgreen'],
    'game_version' => 'FireRed_LeafGreen',
    'region_key' => 'kanto',
    'locmap' => require __DIR__ . '/loc_kanto.php',
    'water_routes' => [19, 20, 21],
    'phrase_map' => [
        'outside' => '外', 'entrance' => '入り口', 'interior' => '内部', 'lower' => '下層',
        'upper' => '上層', 'rooftop' => '屋上', 'summit' => '頂上',
        'bottom floor' => '最下層', 'top floor' => '最上層',
    ],
    'method_handler' => function (&$methods, $e, $name, $versions, $locJp, $areas, $note, $enMap, $cfg) {
        $type = null; $locs = $locJp; $mnote = '';
        $m = $e['method'];
        if ($m === 'Walking') {
            if (preg_match('/^Roaming/i', $e['locText'])) {
                $methods[] = mkMethod('野生（徘徊）', $versions, ['カントー地方を徘徊'], '');
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
        } elseif ($m === 'Rock Smash') {
            $type = '野生（いわくだき）';
        } elseif (preg_match('/^Fishing(?: \((.*)\))?$/', $m, $fm)) {
            $rodmap = [
                'any Rod' => '', 'Old & Good Rod' => '・ボロのつりざお／いいつりざお',
                'Good & Super Rod' => '・いいつりざお／すごいつりざお', 'Good & Super Rods' => '・いいつりざお／すごいつりざお',
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
            $tn = tradeNoteJp_frlg($note);
            if ($tn !== '') $mnote = $tn;
        } elseif ($m === 'Text') {
            $lt = $e['locText'];
            $locs = [];
            if (preg_match('/^Evolve ([A-Z][^,]+?)(,.*| or .*)?$/u', $lt, $em)) {
                $type = '進化のみ';
                $mnote = jpnName(trim($em[1]), $enMap) . 'から進化';
                if (isset($em[2]) && preg_match('/trade/i', $em[2])) $mnote .= '（または他ソフトから交換）';
            } elseif (preg_match('/^Trade from (.*?) or breed (?:a |an )?([A-Za-z\'\. ♀♂-]+?) traded/i', $lt, $tm)) {
                $methods[] = mkMethod('他ソフトから交換', $versions, [], '');
                $methods[] = mkMethod('タマゴ孵化', $versions, [], 'タマゴから孵化（親：' . jpnName(trim($tm[2]), $enMap) . '・交換のみ）');
                return;
            } elseif (preg_match('/^Trade from .*evolve it there.*trade it back/is', $lt)) {
                $type = '他ソフトから交換';
                $mnote = '他ソフト内で進化させてから交換';
            } elseif (preg_match('/^Trade from (.+)$/', $lt)) {
                $type = '他ソフトから交換';
            } elseif (preg_match('/^Breed (?:a |an )?(.+)$/', $lt, $bm)) {
                $type = 'タマゴ孵化';
                $raw = trim($bm[1]);
                $raw = preg_replace('/\btrad\w*\s+from\b.*$/i', '', $raw);
                $raw = preg_replace('/,?\s*and evolve the resulting.*$/i', '', $raw);
                $parents = preg_split('/\s*(?:,|\bor\b|\band\b)\s*/i', $raw);
                $pj = implode('・', array_filter(array_map(function ($p) use ($enMap) {
                    $p = preg_replace('/^(a|an)\s+/i', '', trim($p));
                    if ($p === '' || !preg_match('/^[A-Z]/', $p)) return null;
                    return jpnName($p, $enMap);
                }, $parents)));
                $mnote = 'タマゴから孵化（親：' . $pj . '）';
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
            } elseif (stripos($note, 'evolves by trading') !== false && $type === 'NPC交換') {
                $mnote = $mnote !== '' ? "$mnote。交換後すぐ進化する" : '交換後すぐ進化する';
            } elseif (preg_match('/(Mysticticket|Auroraticket|Old Sea Map|event)/i', $note) && $type === '固定シンボル') {
                $mnote = $mnote !== '' ? "$mnote。イベントアイテム限定" : 'イベントアイテム限定';
            }
        }
        $methods[] = mkMethod($type, $versions, $locs, $mnote);
    },
];

$out = buildEncounter(__DIR__ . '/FRLG_locations.html.parsed.json', $cfg, $enMap);
echo count($out) . " pokemon\n";
writeEncounter($out, $cfg, __DIR__ . '/../pokedex/FireRed_LeafGreen/encounter.json');
echo "written\n";
