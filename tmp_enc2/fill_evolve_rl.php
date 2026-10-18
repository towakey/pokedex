<?php
// Fill 進化のみ entries for dex pokemon missing from encounter.json (rotomlabs games)
require __DIR__ . '/core.php';

$games = [
    'Sun_Moon' => ['alola', ['sun', 'moon']],
    'UltraSun_UltraMoon' => ['alola', ['ultrasun', 'ultramoon']],
    'Sword_Shield' => ['galar', ['sword', 'shield']],
    'Scarlet_Violet' => ['paldea', ['scarlet', 'violet']],
    'LegendsZA' => ['kalos', ['legendsza']],
];

$enMap = loadEnMap(__DIR__ . '/../pokedex/pokedex.json');
$no2jpn = [];
foreach ($enMap as $inf) $no2jpn[$inf['no']] = $inf['jpn'];

// national evolution fallback: target globalNo -> pre-evolution globalNo (pokedex.db)
$natPrev = [];
$db = new SQLite3(__DIR__ . '/../pokedex.db');
$res = $db->query('SELECT globalNo, evol FROM pokedex_evol');
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $pre = substr($row['evol'], 0, 4);
    $natPrev[$row['globalNo']][$pre] = 1;
}
$db->close();

foreach ($games as $g => [$region, $versions]) {
    $dir = __DIR__ . "/../pokedex/$g";
    $dex = json_decode(file_get_contents("$dir/$g.json"), true);
    $enc = json_decode(file_get_contents("$dir/encounter.json"), true);
    $evo = json_decode(file_get_contents("$dir/evolve.json"), true)['evolve'];
    $evoRegion = array_key_first($evo);

    // dex globalNos
    $dexNos = [];
    foreach ($dex['pokedex'] as $r => $mons) {
        foreach ($mons as $dk => $forms) {
            foreach ($forms as $key => $f) {
                $no = explode('_', $key)[0];
                $dexNos[$no] = 1;
            }
        }
    }
    $encNos = array_fill_keys(array_keys($enc['encounter'][$region]), 1);
    $missing = array_diff_key($dexNos, $encNos);
    echo "== $g: dex=" . count($dexNos) . " enc=" . count($encNos) . " missing=" . count($missing) . "\n";

    // build local->global + target->source maps (all sub-regions)
    $loc2g = [];
    $src = []; // target globalNo -> [source globalNo, method]
    foreach ($evo as $rk => $elist) {
        foreach ($elist['進化先'] ?? [] as $local => $e) {
            $loc2g[$rk][$local] = str_pad($e['globalNo'], 4, '0', STR_PAD_LEFT);
        }
    }
    foreach ($evo as $rk => $elist) {
        foreach ($elist['進化先'] ?? [] as $local => $e) {
            foreach ($e as $form => $targets) {
                if ($form === 'globalNo' || !is_array($targets)) continue;
                foreach ($targets as $t) {
                    foreach ($t as $tLocal => $cond) {
                        $tg = $loc2g[$rk][$tLocal] ?? null;
                        if ($tg) $src[$tg] = str_pad($e['globalNo'], 4, '0', STR_PAD_LEFT) . '|' . $cond;
                    }
                }
            }
        }
    }
    $extra = (require __DIR__ . '/extra_rotomlabs.php')[$g] ?? [];
    $unresolved = [];
    foreach (array_keys($missing) as $no) {
        $from = null;
        $cond = '';
        if (isset($src[$no])) {
            [$from, $cond] = explode('|', $src[$no]);
        } elseif (isset($natPrev[$no])) {
            $from = array_key_first($natPrev[$no]);
        }
        if ($from !== null) {
            $note = ($no2jpn[$from] ?? "#$from") . 'から進化';
            if (preg_match('/通信|TRADE/i', $cond)) $note .= '（通信交換）';
            $enc['encounter'][$region][$no] = [
                'globalNo' => str_pad((string)$no, 4, '0', STR_PAD_LEFT),
                'name' => $no2jpn[$no] ?? "#$no",
                'methods' => [mkMethod('進化のみ', $versions, [], $note)],
            ];
        } elseif (isset($extra[$no])) {
            $methods = [];
            foreach ($extra[$no] as [$type, $locs, $note, $vers]) {
                $methods[] = mkMethod($type, $vers ?? $versions, $locs, $note);
            }
            $enc['encounter'][$region][$no] = [
                'globalNo' => str_pad((string)$no, 4, '0', STR_PAD_LEFT),
                'name' => $no2jpn[$no] ?? "#$no",
                'methods' => $methods,
            ];
        } else {
            $unresolved[] = $no . ':' . ($no2jpn[$no] ?? '?');
        }
    }
    ksort($enc['encounter'][$region]);
    file_put_contents("$dir/encounter.json", json_encode($enc, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "  filled; unresolved: " . implode(', ', $unresolved) . "\n";
}
