<?php
// Paldea + Kitakami + Blueberry (SV/TM/ID) location map
$m = [
    'alfornada-cavern' => 'ベイク空洞',
    'area-zero' => 'エリアゼロ',
    'area-zero-underdepths' => 'ゼロの大空洞',
    'artazon' => 'ボウルタウン',
    'asado-desert' => 'ロースト砂漠',
    'cabo-poco' => 'コサジタウン',
    'cascarrafa' => 'マリナードタウン',
    'casseroya-lake' => 'オージャの湖',
    'cortondo' => 'セルクルタウン',
    'dalizapa-passage' => 'プルピケ山道',
    'east-paldean-sea' => '東パルデア海',
    'glaseado-mountain' => 'ナッペ山',
    'great-crater-of-paldea' => 'パルデアの大穴',
    'inlet-grotto' => '入り江のほら穴',
    'levincia' => 'ハッコウシティ',
    'mesagoza' => 'テーブルシティ',
    'naranja-academy' => 'オレンジアカデミー',
    'navi-squads-base' => 'チーム・シーギャングのアジト',
    'north-paldean-sea' => '北パルデア海',
    'poco-path' => 'コサジの小道',
    'pokemon-league' => 'ポケモンリーグ',
    'schedar-squads-base' => 'チーム・メラドーレのアジト',
    'socarrat-trail' => 'オコゲ林道',
    'south-paldean-sea' => '南パルデア海',
    'tagtree-thicket' => 'しるしの木立ち',
    'tera-raid-battles' => 'テラレイドバトル',
    'uva-academy' => 'グレープアカデミー',
    'west-paldean-sea' => '西パルデア海',
    // Kitakami (Teal Mask)
    'apple-hills' => 'アップルヒルズ',
    'chilling-waterhead' => 'ひやみず洞',
    'crystal-pool' => 'てらす池',
    'dreaded-den' => '恐れ穴',
    'fellhorn-gorge' => '鬼角峡谷',
    'infernal-pass' => '地獄谷',
    'kitakami-hall' => 'キタカミセンター',
    'kitakami-road' => 'キタカミ街道',
    'kitakami-wilds' => 'キタカミ原生地域',
    'loyalty-plaza' => 'ともっこプラザ',
    'mossfell-confluence' => '落合川原',
    'mossui-town' => 'スイリョクタウン',
    'oni-mountain' => '鬼が山',
    'onis-maw' => '鬼歯空洞',
    'paradise-barrens' => '楽土の荒地',
    'revelers-road' => '舞出山道',
    'timeless-woods' => 'とこしえの森',
    'wistful-fields' => 'フジが原',
    // Blueberry (Indigo Disk)
    'blueberry-academy' => 'ブルーベリー学園',
    'canyon-biome' => 'キャニオンエリア',
    'central-plaza' => 'センタースクエア',
    'chargestone-cavern' => 'でんきいしのほらあな',
    'coastal-biome' => 'コーストエリア',
    'polar-biome' => 'ポーラエリア',
    'polar-plaza' => 'ポーラスクエア',
    'savanna-biome' => 'サバンナエリア',
    'savanna-plaza' => 'サバンナスクエア',
    'torchlit-labyrinth' => 'ともしびの迷路',
];
// province sub-areas: east-province-area-one => 東1番エリア
$dirs = ['east' => '東', 'west' => '西', 'south' => '南', 'north' => '北'];
$nums = ['one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6];
foreach ($dirs as $d => $dj) {
    foreach ($nums as $n => $nj) {
        $m["{$d}-province-area-{$n}"] = "{$dj}{$nj}番エリア";
        $m["{$d}-province|{$n}"] = "{$dj}{$nj}番エリア"; // indigo-disk area refs
    }
    $m["{$d}-province"] = "{$dj}エリア";
}
// indigo-disk area slugs like "east-province-area-one"
foreach ($dirs as $d => $dj) foreach ($nums as $n => $nj) $m["area-{$d}-province-area-{$n}"] = "{$dj}{$nj}番エリア";
$m['__area__'] = [
    'League Club Room' => 'リーグ部の部室',
];
return $m;
