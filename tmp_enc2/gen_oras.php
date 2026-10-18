<?php
// ORAS encounter.json generator
require __DIR__ . '/core.php';

$enMap = loadEnMap(__DIR__ . '/../pokedex/pokedex.json');

$cfg = [
    'vermap' => ['OR' => 'omegaruby', 'AS' => 'alphasapphire'],
    'verorder' => ['omegaruby', 'alphasapphire'],
    'game_version' => 'OmegaRuby_AlphaSapphire',
    'region_key' => 'hoenn',
    'locmap' => require __DIR__ . '/loc_hoenn.php',
    'water_routes' => [105, 106, 107, 108, 109, 122, 124, 125, 126, 127, 128, 129, 130, 131, 132, 133, 134],
    'name_map' => [
        'shellos (west)' => 'shellos', 'shellos (east)' => 'shellos',
        'gastrodon (west)' => 'gastrodon', 'gastrodon (east)' => 'gastrodon',
        'basculin (red)' => 'basculin', 'basculin (blue)' => 'basculin',
        'deerling (spring)' => 'deerling', 'deerling (summer)' => 'deerling',
        'deerling (autumn)' => 'deerling', 'deerling (winter)' => 'deerling',
        'sawsbuck (spring)' => 'sawsbuck', 'sawsbuck (summer)' => 'sawsbuck',
        'sawsbuck (autumn)' => 'sawsbuck', 'sawsbuck (winter)' => 'sawsbuck',
        'meloetts' => 'meloetta', 'meloetta (aria)' => 'meloetta',
    ],
    'phrase_map' => [
        'outside' => '外', 'interior' => '内部', 'exterior' => '外', 'entrance' => '入り口',
        'lower' => '下層', 'upper' => '上層', 'summit' => '頂上', 'basement' => '地下',
        'east' => '東', 'west' => '西', 'north' => '北', 'south' => '南',
        'east side' => '東側', 'west side' => '西側',
        'rear' => '奥', 'top' => '最上層', 'bottom' => '最下層',
        'main entrance' => '正面入り口', 'back entrance' => '裏入り口',
        'west entrance' => '西入り口', 'east entrance' => '東入り口', 'main' => '本洞',
    ],
    'method_handler' => function (&$methods, $e, $name, $versions, $locJp, $areas, $note, $enMap, $cfg) {
        $type = null; $locs = $locJp; $mnote = '';
        $m = $e['method'];
        if ($m === 'Walking') {
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
        } elseif ($m === 'DexNav') {
            $type = '野生（ナビサーチ）';
        } elseif ($m === 'Horde') {
            $type = '野生（群れバトル）';
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
            if (stripos($e['locText'], 'egg') !== false) $mnote = 'タマゴ';
        } elseif ($m === 'In-Game Trade') {
            $type = 'NPC交換';
            if (preg_match_all('/trade(?:\s+in\s+([A-Za-z, ]+?))?\s+is for the player\'s\s+(?:(female|male)\s+)?([A-Za-z\'\. -]+?)[\.\n]/i', $note, $tm, PREG_SET_ORDER)) {
                $out = [];
                $VW = ['omegaruby' => 'オメガルビー', 'alphasapphire' => 'アルファサファイア', 'omega' => 'オメガルビー', 'alpha' => 'アルファサファイア'];
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
            if ($lt === 'The Sky') {
                $methods[] = mkMethod('野生（大空）', $versions, ['大空'], '');
                return;
            }
            if (preg_match('/^Evolve ([A-Z][^,]+?)(,.*| or .*)?$/u', $lt, $em)) {
                $type = '進化のみ';
                $mnote = jpnName(trim($em[1]), $enMap) . 'から進化';
                if (isset($em[2]) && preg_match('/trad|transfer/i', $em[2])) $mnote .= '（または他ソフトから交換・転送）';
            } elseif (preg_match('/^Breed (?:a |an )?(.+)$/', $lt, $bm)) {
                $type = 'タマゴ孵化';
                $raw = trim($bm[1]);
                $raw = preg_replace('/\btrad\w*\s+from\b.*$/i', '', $raw);
                $raw = preg_replace('/\bor trade it\b.*$/i', '', $raw);
                $parents = preg_split('/\s*(?:,|\bor\b|\band\b)\s*/i', $raw);
                $pj = implode('・', array_filter(array_map(function ($p) use ($enMap) {
                    $p = preg_replace('/^(a|an)\s+/i', '', trim($p));
                    return ($p === '' || !preg_match('/^[A-Z]/', $p)) ? null : jpnName($p, $enMap);
                }, $parents)));
                $mnote = 'タマゴから孵化（親：' . $pj . '）';
            } elseif (preg_match('/^(Trade|Transfer) from|^Trade from .* or transfer/i', $lt)) {
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
            } elseif (stripos($note, 'evolves by trading') !== false && $type === 'NPC交換') {
                $mnote = $mnote !== '' ? "$mnote。交換後すぐ進化する" : '交換後すぐ進化する';
            } elseif (preg_match('/(Member Card|Oak\'s Letter|Azure Flute|event)/i', $note) && $type === '固定シンボル') {
                $mnote = $mnote !== '' ? "$mnote。イベントアイテム限定" : 'イベントアイテム限定';
            }
        }
        $methods[] = mkMethod($type, $versions, $locs, $mnote);
    },
];

$out = buildEncounter(__DIR__ . '/ORAS_locations.html.parsed.json', $cfg, $enMap);
echo count($out) . " pokemon\n";
writeEncounter($out, $cfg, __DIR__ . '/../pokedex/OmegaRuby_AlphaSapphire/encounter.json');
echo "written\n";
