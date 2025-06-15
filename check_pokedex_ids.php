<?php
/**
 * check_pokedex_ids.php
 *
 * 指定した地方ポケモン図鑑 JSON に含まれる ID が、
 * マスターの pokedex.json に存在するかを検証するスクリプトです。
 *
 * 1. `$regionFiles` 配列にチェックしたいファイルのパスを列挙してください。
 * 2. コメントアウトを外すだけで検査対象を切り替えられます。
 * 3. CLI から `php check_pokedex_ids.php` を実行してください。
 */

// --------------------- 設定ここから ---------------------
$baseDir = __DIR__;
$masterFile = $baseDir . DIRECTORY_SEPARATOR . 'pokedex' . DIRECTORY_SEPARATOR . 'pokedex.json';

$regionFiles = [
    // チェック対象の地方ファイルをここに追加してください。
    // コメントアウトを外すと検査対象になります。
    // $baseDir . DIRECTORY_SEPARATOR . 'pokedex' . DIRECTORY_SEPARATOR . 'Scarlet_Violet' . DIRECTORY_SEPARATOR . 'Scarlet_Violet.json',
    // $baseDir . DIRECTORY_SEPARATOR . 'pokedex' . DIRECTORY_SEPARATOR . 'Sword_Shield' . DIRECTORY_SEPARATOR . 'Sword_Shield.json',
    // $baseDir . DIRECTORY_SEPARATOR . 'pokedex' . DIRECTORY_SEPARATOR . 'LegendsArceus' . DIRECTORY_SEPARATOR . 'LegendsArceus.json',
    // $baseDir . DIRECTORY_SEPARATOR . 'pokedex' . DIRECTORY_SEPARATOR . 'UltraSun_UltraMoon' . DIRECTORY_SEPARATOR . 'UltraSun_UltraMoon.json',
    // $baseDir . DIRECTORY_SEPARATOR . 'pokedex' . DIRECTORY_SEPARATOR . 'Sun_Moon' . DIRECTORY_SEPARATOR . 'Sun_Moon.json',
    // $baseDir . DIRECTORY_SEPARATOR . 'pokedex' . DIRECTORY_SEPARATOR . 'x_y' . DIRECTORY_SEPARATOR . 'x_y.json',
    // $baseDir . DIRECTORY_SEPARATOR . 'pokedex' . DIRECTORY_SEPARATOR . 'Black2_White2' . DIRECTORY_SEPARATOR . 'Black2_White2.json',
    // $baseDir . DIRECTORY_SEPARATOR . 'pokedex' . DIRECTORY_SEPARATOR . 'HeartGold_SoulSilver' . DIRECTORY_SEPARATOR . 'HeartGold_SoulSilver.json',
    // $baseDir . DIRECTORY_SEPARATOR . 'pokedex' . DIRECTORY_SEPARATOR . 'Ruby_Sapphire_Emerald' . DIRECTORY_SEPARATOR . 'Ruby_Sapphire_Emerald.json',
    // $baseDir . DIRECTORY_SEPARATOR . 'pokedex' . DIRECTORY_SEPARATOR . 'FireRed_LeafGreen' . DIRECTORY_SEPARATOR . 'FireRed_LeafGreen.json',
    // $baseDir . DIRECTORY_SEPARATOR . 'pokedex' . DIRECTORY_SEPARATOR . 'Gold_Silver_Crystal' . DIRECTORY_SEPARATOR . 'Gold_Silver_Crystal.json',
    $baseDir . DIRECTORY_SEPARATOR . 'pokedex' . DIRECTORY_SEPARATOR . 'Red_Green_Blue_Pikachu' . DIRECTORY_SEPARATOR . 'Red_Green_Blue_Pikachu.json',
];
// --------------------- 設定ここまで ---------------------

// 出力ファイル (タイムスタンプ付き)
$timestamp  = date('Ymd_His');
// $outputFile = $baseDir . DIRECTORY_SEPARATOR . 'check_pokedex_ids_result_' . $timestamp . '.txt';
$outputFile = $baseDir . DIRECTORY_SEPARATOR . 'check_pokedex_ids_result.txt';
$fp = fopen($outputFile, 'w');
if ($fp === false) {
    fwrite(STDERR, "[Error] 出力ファイルを作成できません: {$outputFile}\n");
    exit(1);
}

/**
 * 画面とファイルの両方に出力するヘルパー
 *
 * @param string $message 出力メッセージ（改行込み）
 * @return void
 */
function out(string $message): void
{
    global $fp;
    echo $message;
    fwrite($fp, $message);
}


/**
 * 多次元配列を再帰的に探索して 'id' キーの値を収集する。
 *
 * @param mixed $data 入力データ
 * @param array $ids  収集した ID を格納する配列 (参照渡し)
 * @return void
 */
function collectIds($data, array &$ids): void
{
    if (is_array($data)) {
        if (isset($data['id']) && is_string($data['id'])) {
            $ids[] = $data['id'];
        }
        foreach ($data as $value) {
            collectIds($value, $ids);
        }
    }
}

// マスター JSON を読み込む
if (!is_file($masterFile)) {
    fwrite(STDERR, "[Error] マスター pokedex.json が見つかりません: {$masterFile}\n");
    exit(1);
}
$masterJson = json_decode(file_get_contents($masterFile), true);
if ($masterJson === null) {
    fwrite(STDERR, "[Error] マスター pokedex.json を JSON として解析できません。\n");
    exit(1);
}

// マスター ID リストを作成
$masterIds = [];
collectIds($masterJson['pokedex'] ?? $masterJson, $masterIds);
$masterIds = array_unique($masterIds);
$masterSet = array_flip($masterIds); // 高速検索用

out("==== マスター ID 数: " . count($masterIds) . " 件 ====" . PHP_EOL);

foreach ($regionFiles as $filePath) {
    if (!is_file($filePath)) {
        fwrite(STDERR, "[Warning] 地方ファイルが見つかりません: {$filePath}\n");
        continue;
    }

    $json = json_decode(file_get_contents($filePath), true);
    if ($json === null) {
        fwrite(STDERR, "[Warning] JSON 解析失敗: {$filePath}\n");
        continue;
    }

    $regionIds = [];
    collectIds($json['pokedex'] ?? $json, $regionIds);
    $regionIds = array_unique($regionIds);

    // マスターに存在しない ID を抽出
    $missing = array_values(array_filter($regionIds, fn($id) => !isset($masterSet[$id])));

    out(PHP_EOL . "=== " . basename($filePath) . " ===" . PHP_EOL);
    out("検出 ID 数: " . count($regionIds) . " 件" . PHP_EOL);

    if (empty($missing)) {
        out("すべての ID がマスターに存在します。" . PHP_EOL);
    } else {
        out("マスターに存在しない ID (" . count($missing) . " 件):" . PHP_EOL);
        foreach ($missing as $id) {
            out("  - {$id}" . PHP_EOL);
        }
    }
}

out("\n結果を {$outputFile} に出力しました。\n");

fclose($fp);

?>
