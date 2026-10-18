<?php
// RotomLabs rl.json -> encounter.json generator
// Usage: php gen_rotomlabs.php <config-key>
require __DIR__ . '/core.php';

$cfgs = [
    'Sun_Moon' => [
        'hubs' => ['sun-moon'],
        'game_version' => 'Sun_Moon',
        'region_key' => 'alola',
        'vermap' => ['sun' => 'sun', 'moon' => 'moon'],
        'verorder' => ['sun', 'moon'],
        'locmap' => require __DIR__ . '/loc_alola.php',
    ],
    'UltraSun_UltraMoon' => [
        'hubs' => ['ultra-sun-ultra-moon'],
        'game_version' => 'UltraSun_UltraMoon',
        'region_key' => 'alola',
        'vermap' => ['ultra-sun' => 'ultrasun', 'ultra-moon' => 'ultramoon', 'sun' => 'ultrasun', 'moon' => 'ultramoon'],
        'verorder' => ['ultrasun', 'ultramoon'],
        'locmap' => require __DIR__ . '/loc_alola.php',
    ],
    'Sword_Shield' => [
        'hubs' => ['sword-shield', 'the-isle-of-armor', 'the-crown-tundra'],
        'game_version' => 'Sword_Shield',
        'region_key' => 'galar',
        'vermap' => ['sword' => 'sword', 'shield' => 'shield'],
        'verorder' => ['sword', 'shield'],
        'locmap' => require __DIR__ . '/loc_galar.php',
    ],
    'Scarlet_Violet' => [
        'hubs' => ['scarlet-violet', 'the-teal-mask', 'the-indigo-disk'],
        'game_version' => 'Scarlet_Violet',
        'region_key' => 'paldea',
        'vermap' => ['scarlet' => 'scarlet', 'violet' => 'violet'],
        'verorder' => ['scarlet', 'violet'],
        'locmap' => require __DIR__ . '/loc_paldea.php',
    ],
    'LegendsZA' => [
        'hubs' => ['legends-z-a', 'mega-dimension'],
        'game_version' => 'LegendsZA',
        'region_key' => 'kalos',
        'vermap' => ['legends-z-a' => 'legendsza', 'z-a' => 'legendsza', 'mega-dimension' => 'legendsza'],
        'verorder' => ['legendsza'],
        'locmap' => require __DIR__ . '/loc_lumiose.php',
    ],
];

// method slug -> JP type. null => skip
$methodMap = [
    'gift' => 'もらう', 'in-game-trade' => 'NPC交換', 'interact' => '固定シンボル',
    'walk' => '野生（草むら）', 'surf' => '野生（水上）', 'fishing' => '野生（釣り）',
    'sos' => '野生（乱入バトル）', 'island-scan' => '野生（島スキャン）',
    'ambush' => '野生（飛び出し）', 'poke-pelago' => 'ポケリゾート',
    'random-encounter' => '野生（シンボルなし）', 'overworld' => '野生（シンボル）',
    'ground' => '野生（地上シンボル）', 'flying' => '野生（空中シンボル）',
    'berry-tree' => '野生（きのみの木）', 'sharpedo-surf' => '野生（水上・シャワードスキー）',
    'dynamax-adventure' => 'ダイマックスアドベンチャー',
    'fixed-spawn' => '固定シンボル（定点出現）',
    'hyperspace' => '野生（異次元ミアレ）',
    'spawn-cave' => '野生（洞窟・屋内）', 'spawn-underground' => '野生（洞窟・屋内）',
    'spawn-lake' => '野生（水上）', 'spawn-ocean' => '野生（水上）',
    'spawn-riverside' => '野生（水上）', 'spawn-cavewater' => '野生（水上）',
    'spawn-swamp' => '野生（水上）',
];
// spawn-* biome types all => 野生（フィールド）

$cfgKey = $argv[1] ?? null;
$cfg = $cfgs[$cfgKey] ?? null;
if (!$cfg) { fwrite(STDERR, "usage: gen_rotomlabs.php Sun_Moon|UltraSun_UltraMoon|Sword_Shield|Scarlet_Violet|LegendsZA\n"); exit(1); }

$enMap = loadEnMap(__DIR__ . '/../pokedex/pokedex.json');
$no2jpn = [];
foreach ($enMap as $en => $info) $no2jpn[$info['no']] = $info['jpn'];
// slug/form -> national no via rotomlabs lookups
$rlSpecies = json_decode(file_get_contents(__DIR__ . '/rl_species.json'), true);
$rlSpeciesByBase = [];
foreach ($rlSpecies as $k => $v) { [$b] = explode('/', $k); $rlSpeciesByBase[$b] = $rlSpeciesByBase[$b] ?? $v; }
// manual slug fixes (species slug -> no)
$slugFix = [
    'nidoran-f' => '0032', 'nidoran-m' => '0029', 'farfetchd' => '0083', 'mr-mime' => '0122',
    'ho-oh' => '0250', 'mime-jr' => '0439', 'porygon-z' => '0474', 'jangmo-o' => '0782',
    'hakamo-o' => '0783', 'kommo-o' => '0784', 'tapu-koko' => '0785', 'tapu-lele' => '0786',
    'tapu-bulu' => '0787', 'tapu-fini' => '0788', 'sirfetchd' => '0865', 'mr-rime' => '0866',
    'type-null' => '0772', 'flabebe' => '0669', 'meowstic' => '0678', 'basculin' => '0550',
    'deoxys' => '0386', 'wormadam' => '0413', 'giratina' => '0487', 'shaymin' => '0492',
    'tornadus' => '0641', 'thundurus' => '0642', 'landorus' => '0645', 'keldeo' => '0647',
    'meloetta' => '0648', 'pumpkaboo' => '0710', 'gourgeist' => '0711', 'oricorio' => '0741',
    'lycanroc' => '0745', 'wishiwashi' => '0746', 'minior' => '0774', 'mimikyu' => '0778',
    'toxtricity' => '0849', 'eiscue' => '0875', 'indeedee' => '0876', 'zacian' => '0888',
    'zamazenta' => '0889', 'urshifu' => '0892', 'calyrex' => '0898', 'basculegion' => '0902',
    'enamorus' => '0905', 'oinkologne' => '0916', 'maushold' => '0925', 'squawkabilly' => '0931',
    'palafin' => '0964', 'tatsugiri' => '0978', 'dudunsparce' => '0982', 'poltchageist' => '1012',
    'sinistcha' => '1013', 'okidogi' => '1014', 'munkidori' => '1015', 'fezandipiti' => '1016',
    'ogerpon' => '1017', 'terapagos' => '1024', 'ursaluna' => '0901', 'kleavor' => '0900',
    'mrmime' => '0122', 'sirfetchd' => '0865',
    'bayleef' => '0153', 'meganium' => '0154', 'croconaw' => '0159', 'feraligatr' => '0160',
    'pignite' => '0499', 'quilladin' => '0651', 'braixen' => '0654', 'frogadier' => '0657',
    'genesect' => '0649', 'zeraora' => '0807', 'hoopa' => '0720', 'melmetal' => '0809',
    'darkrai' => '0491', 'magearna' => '0801',
];

$out = []; // no => methods[]
$unknownPokemon = []; $unknownLoc = []; $unknownMethod = []; $unknownNote = [];
$noteMap = [
    'Only in part of this terrain' => null,
    'normal' => null, 'legendary' => null,
    'Requires a bike upgrade to reach' => '自転車強化が必要',
    'Story encounter' => 'ストーリーイベント',
    'Gift: an Egg containing an Eevee from a woman at the Pokemon Nursery' => 'ポケモン保育所でイーブイのタマゴをもらう',
    'Trade: Regina, who moves to a different place on the Isle of Armor each day and offers a random Pokemon from her list' => 'レジーナとの交換（日替わりでヨロイ島内を移動）',
    'After beating the Lurking Steel Titan' => 'はぐれ鋼鉄のヌシ撃破後',
    'Gift: an Egg from Jacq at a picnic on Reveler\'s Road, holding a random Sinnoh first partner Pokemon' => '舞出山道のピクニックでジニアからタマゴをもらう（シンオウ御三家）',
    'Trade: a League Club coach\'s Pokemon, offered on their third invitation' => 'リーグ部コーチとの交換（3回目の招待）',
    'After boosting the Canyon Biome\'s biodiversity' => 'キャニオンエリアの生物多様性向上後',
    'After boosting the Polar Biome\'s biodiversity' => 'ポーラエリアの生物多様性向上後',
    'After boosting the Coastal Biome\'s biodiversity' => 'コーストエリアの生物多様性向上後',
    'After boosting the Savanna Biome\'s biodiversity' => 'サバンナエリアの生物多様性向上後',
    'After the main story' => 'メインストーリー後',
];

$addMethod = function (&$out, $no, $jpn, $type, $versions, $locs, $note) use ($cfg) {
    if (!$versions || !$type) return;
    $out[$no] = $out[$no] ?? ['globalNo' => $no, 'name' => $jpn, 'methods' => []];
    $out[$no]['methods'][] = mkMethod($type, $versions, $locs, $note);
};

foreach ($cfg['hubs'] as $hub) {
    $data = json_decode(file_get_contents(__DIR__ . "/$hub.rl.json"), true);
    foreach ($data as $locSlug => $L) {
        $locBase = $cfg['locmap'][$locSlug] ?? null;
        if ($locBase === null && preg_match('/^route-(\d+)$/', $locSlug, $rm)) $locBase = "{$rm[1]}番道路";
        // province sub-area slugs like east-province-area-one are already mapped
        if ($locBase === null) { $unknownLoc[$locSlug] = $L['name']; continue; }
        foreach ($L['sections'] as $sec) {
            $secGames = $sec['games'] ?? [];
            foreach ($sec['areas'] ?? [] as $area) {
                $locJp = $locBase;
                if (!($area['is_default_area'] ?? true)) {
                    $an = $area['area_name'];
                    // paldea province: "{loc}-area-one" -> 東1番エリア
                    if (preg_match('/^(east|west|south|north)-province-area-(one|two|three|four|five|six)$/', $area['area_slug'] ?? '', $am)) {
                        $dd = ['east' => '東', 'west' => '西', 'south' => '南', 'north' => '北'][$am[1]];
                        $nn = ['one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6][$am[2]];
                        $locJp = "{$dd}{$nn}番エリア";
                    } else {
                        $q = $cfg['locmap']['__area__'][$an] ?? null;
                        if ($q === null) { $unknownLoc["{$locSlug}|{$an}"] = 1; $q = $an; }
                        if ($q !== '') $locJp .= "（{$q}）";
                    }
                }
                foreach ($area['methods'] ?? [] as $m) {
                    $slug = $m['method_slug'];
                    $type = $methodMap[$slug] ?? null;
                    if ($type === null) {
                        if (strpos($slug, 'spawn-') === 0) $type = '野生（フィールド）';
                        else $unknownMethod[$slug] = 1;
                    }
                    foreach ($m['tables'] ?? [] as $t) {
                        $tGames = [];
                        foreach (($t['rows'] ?? []) as $r) {
                            [$sp] = explode('/', $r['pokemon']);
                            foreach ($r['games'] ?? $secGames as $g) $tGames[$g] = 1;
                        }
                        foreach (($t['rows'] ?? []) as $r) {
                            $pk = $r['pokemon'];
                            [$sp, $form] = explode('/', $pk . '/none');
                            if (isset($rlSpecies[$pk])) $no = str_pad($rlSpecies[$pk], 4, '0', STR_PAD_LEFT);
                            elseif (isset($rlSpeciesByBase[$sp])) $no = str_pad($rlSpeciesByBase[$sp], 4, '0', STR_PAD_LEFT);
                            elseif (isset($slugFix[$sp])) $no = $slugFix[$sp];
                            else { $unknownPokemon[$pk] = 1; continue; }
                            $info = ['no' => $no, 'jpn' => $no2jpn[$no] ?? $sp];
                            $versions = [];
                            foreach ($r['games'] ?? $secGames as $g) {
                                if (isset($cfg['vermap'][$g])) $versions[] = $cfg['vermap'][$g];
                            }
                            if (!$versions) { foreach ($secGames as $g) if (isset($cfg['vermap'][$g])) $versions[] = $cfg['vermap'][$g]; }
                            if (!$versions) $versions = $cfg['verorder'];
                            $versions = array_values(array_unique($versions));
                            usort($versions, function ($a, $b) use ($cfg) { return array_search($a, $cfg['verorder']) - array_search($b, $cfg['verorder']); });
                            $note = $r['note'] ?? '';
                            if ($note !== '') {
                                $nj = [];
                                foreach (preg_split('/\s*·\s*/', $note) as $np) {
                                    if (array_key_exists($np, $noteMap)) { if ($noteMap[$np] !== null) $nj[] = $noteMap[$np]; }
                                    else $unknownNote[$np] = 1;
                                }
                                $note = implode('、', $nj);
                            }
                            if ($type) $addMethod($out, $no, $info['jpn'] ?? $sp, $type, $versions, [$locJp], $note ?? '');
                        }
                    }
                }
            }
        }
    }
}

// merge + sort
foreach ($out as $no => &$e) {
    $e['methods'] = mergeMethods($e['methods'], $cfg['verorder']);
}
ksort($out);
echo count($out) . " pokemon\n";
foreach (['pokemon' => $unknownPokemon, 'locations' => $unknownLoc, 'methods' => $unknownMethod] as $k => $u) {
    if ($u) { echo "== unknown $k ==\n"; foreach ($u as $x => $v) echo "  $x" . (is_string($v) ? " ($v)" : '') . "\n"; }
}
if ($unknownNote) {
    echo "== unknown notes ==\n";
    foreach ($unknownNote as $x => $_) echo "  $x\n";
}
writeEncounter($out, $cfg, __DIR__ . '/../pokedex/' . $cfg['game_version'] . '/encounter.json');
echo "written\n";
