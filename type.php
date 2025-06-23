<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

try {
    $type1 = isset($_GET['type1']) ? $_GET['type1'] : null;
    $type2 = isset($_GET['type2']) ? $_GET['type2'] : null;
    $region = isset($_GET['region']) ? $_GET['region'] : null;

    $result = [];

    if (!$region) {
        throw new Exception('リージョンを指定してください');
    }

    $validRegions = [
        'global' => ['全国図鑑', 'global', ['global']],
        'kanto' => ['カントー図鑑', 'red_green_blue_pikachu', ['red', 'green', 'blue', 'pikachu']],
        'johto' => ['ジョウト図鑑', 'gold_silver_crystal', ['gold', 'silver', 'crystal']],
        'hoenn' => ['ホウエン図鑑', 'ruby_sapphire_emerald', ['ruby', 'sapphire', 'emerald']],
        'kanto_frlg' => ['カントー図鑑', 'firered_leafgreen', ['firered', 'leafgreen']],
        'sinnoh' => ['シンオウ図鑑', 'diamond_pearl_platinum', ['diamond', 'pearl', 'platinum']],
        'johto_hgss' => ['ジョウト図鑑', 'heartgold_soulsilver', ['heartgold', 'soulsilver']],
        'unova_bw' => ['イッシュ図鑑', 'black_white', ['black', 'white']],
        'unova_b2w2' => ['イッシュ図鑑', 'black2_white2', ['black2', 'white2']],
        'central_kalos' => ['セントラルカロス図鑑', 'x_y', ['x', 'y']],
        'coast_kalos' => ['コーストカロス図鑑', 'x_y', ['x', 'y']],
        'mountain_kalos' => ['マウンテンカロス図鑑', 'x_y', ['x', 'y']],
        'alola_sm' => ['アローラ図鑑', 'sun_moon', ['sun', 'moon']],
        'alola_usum' => ['アローラ図鑑', 'ultrasun_ultramoon', ['ultrasun', 'ultramoon']],
        'galar' => ['ガラル図鑑', 'sword_shield', ['sword', 'shield']],
        'crown_tundra' => ['カンムリ雪原図鑑', 'sword_shield', ['sword', 'shield']],
        'isle_of_armor' => ['ヨロイ島図鑑', 'sword_shield', ['sword', 'shield']],
        'hisui' => ['ヒスイ図鑑', 'legendsarceus', ['legendsarceus']],
        'paldea' => ['パルデア図鑑', 'scarlet_violet', ['scarlet', 'violet']],
        'kitakami' => ['キタカミ図鑑', 'scarlet_violet', ['scarlet', 'violet']],
        'blueberry' => ['ブルーベリー図鑑', 'scarlet_violet', ['scarlet', 'violet']]
    ];

    if ($region && !isset($validRegions[$region])) {
        throw new Exception('無効なリージョンが指定されました');
    }

    // jsonファイルの読み込み
    $jsonFile = __DIR__ . '/type/type.json';
    $json = file_get_contents($jsonFile);
    $typeData = json_decode($json, true);

    // 対象ゲームバージョン（リージョンに紐づく）を取得
    $version = $validRegions[$region][1];

    // 指定バージョンのタイプ相性データのみ抽出
    $filteredTypes = [];
    if (isset($typeData['type']) && is_array($typeData['type'])) {
        $filteredTypes = array_values(array_filter($typeData['type'], function ($entry) use ($version) {
            $versions = [];
            if (isset($entry['game_version']) && is_array($entry['game_version'])) {
                $versions = array_merge($versions, $entry['game_version']);
            }
            // JSON には誤字キー "geme_version" が存在するためこちらも確認
            if (isset($entry['geme_version']) && is_array($entry['geme_version'])) {
                $versions = array_merge($versions, $entry['geme_version']);
            }
            return in_array($version, $versions, true);
        }));
    }

    // type1 / type2 が指定されている場合の倍率計算
    // 参考: pokedex.ts L312-L323 のロジック
    $responseData = [];
    if ($type1 !== null || $type2 !== null) {
        // 互換テーブル（攻撃 -> 防御 -> 倍率）は 1 つあれば十分
        $compatTable = $filteredTypes[0]['type'] ?? [];
        $rates = [];
        foreach ($compatTable as $atkType => $defRates) {
            // 単タイプ
            if ($type2 === null || $type2 === '') {
                $rate = isset($defRates[$type1]) ? (float)$defRates[$type1] : 1.0;
            } else {
                // 複合タイプは掛け算
                $rate1 = isset($defRates[$type1]) ? (float)$defRates[$type1] : 1.0;
                $rate2 = isset($defRates[$type2]) ? (float)$defRates[$type2] : 1.0;
                $rate  = $rate1 * $rate2;
            }
            $rates[$atkType] = (string)$rate; // 文字列にしておく
        }
        $responseData = [
            'defense_type1' => $type1,
            'defense_type2' => $type2,
            'rates'         => $rates,
            'game_version'  => $version
        ];
    } else {
        $responseData = [
            'update'       => $typeData['update'] ?? null,
            'game_version' => $version,
            'type'         => $filteredTypes
        ];
    }

    // 結果をJSONで出力
    echo json_encode([
        'success' => true,
        'data'    => $responseData,
    ]);
    return;
}
catch (Exception $e) {
    // エラーレスポンス
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

?>