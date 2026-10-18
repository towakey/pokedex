<?php
// Lumiose (Legends Z-A) location map
$m = [
    'academie-etoile' => 'エトワール校',
    'autumnal-avenue' => 'オータムアベニュー',
    'aymlis-park' => 'ボンヤーリ公園',
    'bleu-district' => 'ブルー地区',
    'centrico-plaza' => 'セントリコ広場',
    'coulant-waterway' => 'クレ水路',
    'espace-vide' => 'エスパス・ヴィード',
    'estival-avenue' => 'エテアベニュー',
    'hibernal-avenue' => 'イベールアベニュー',
    'jaune-district' => 'ジョーヌ地区',
    'lumiose-city' => 'ミアレシティ',
    'lumiose-museum' => 'ミアレ美術館',
    'lysandre-labs' => 'フラダリラボ',
    'magenta-district' => 'ローズ地区',
    'north-boulevard' => 'ノースサイドストリート',
    'old-building' => '古びたビル',
    'passage-du-palais' => 'パサージュ・デュ・パレ',
    'pokemon-research-lab' => 'ポケモン研究所',
    'rouge-district' => 'ルージュ地区',
    'saison-canal' => 'セゾン運河',
    'saison-canalside' => 'セゾン運河沿い',
    'south-boulevard' => 'サウスサイドストリート',
    'the-sewers' => 'ミアレ地下水道',
    'vernal-avenue' => 'プランタンアベニュー',
    'vert-district' => 'ベール地区',
    // Mega Dimension DLC (hyperspace areas)
    'hyperspace-lumiose' => '異次元ミアレ',
    'hyperspace-desolate-land' => '異次元ミアレ（終わりの大地）',
    'hyperspace-primordial-sea' => '異次元ミアレ（始まりの海）',
    'hyperspace-sky-pillar' => '異次元ミアレ（天空の柱）',
    'hyperspace-disaster-arena' => '異次元ミアレ（災厄のアリーナ）',
    'hyperspace-newmoon-nightmare' => '異次元ミアレ（新月の悪夢）',
    'hyperspace-second-sight-arena' => '異次元ミアレ（千里眼のアリーナ）',
    'hyperspace-infernal-arena' => '異次元ミアレ（地獄のアリーナ）',
    'hyperspace-hunting-grounds' => '異次元ミアレ（狩り場）',
    'hyperspace-sushi-paradise' => '異次元ミアレ（寿司パラダイス）',
    'quasartico-inc' => 'クエーサー社',
];
for ($i = 1; $i <= 20; $i++) $m["wild-zone-$i"] = "{$i}番ワイルドゾーン";
// English area names for district streets/plazas/sectors
foreach (['Bleu' => 'ブルー', 'Jaune' => 'ジョーヌ', 'Magenta' => 'ローズ', 'Rouge' => 'ルージュ', 'Vert' => 'ベール'] as $en => $dj) {
    $m['__area__'][$en . ' Street'] = $dj . '大通り';
    $m['__area__'][$en . ' Plaza'] = $dj . '広場';
}
for ($i = 1; $i <= 12; $i++) $m['__area__']["Sector $i"] = "{$i}番地";
return $m;
