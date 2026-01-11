<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// pokedex_config.jsonを読み込む
$configPath = __DIR__ . '/config/pokedex_config.json';
$pokedexConfig = json_decode(file_get_contents($configPath), true);
$versionMapping = $pokedexConfig['version_mapping'] ?? [];

/**
 * verIDをversion名に変換する関数
 * 
 * @param string $verID バージョンID（例: "01_00", "06_00_1"）
 * @return string バージョン名（例: "red_green_blue_pikachu", "x_y"）
 */
function convertVerIdToVersion($verID, $versionMapping) {
    if (!$verID) {
        return null;
    }
    
    // verIDから3つ目以降の部分を除去（例: "06_00_1" -> "06_00"、"06_00" -> "06_00"）
    $parts = explode('_', $verID);
    $cleanVerID = $verID;
    
    // アンダースコアで区切った要素が3つ以上ある場合のみ、最初の2つを結合
    if (count($parts) >= 3) {
        $cleanVerID = $parts[0] . '_' . $parts[1];
    }
    
    // version_mappingから該当するバージョン名を取得
    if (isset($versionMapping[$cleanVerID]['version'])) {
        return $versionMapping[$cleanVerID]['version'];
    }
    
    // マッピングが見つからない場合はそのまま返す
    return $verID;
}

/**
 * pokedex_config.jsonからリージョン設定を生成する
 * 
 * @param array $pokedexConfig 設定ファイルの配列
 * @return array validRegions形式の配列
 */
function buildValidRegions($pokedexConfig) {
    // 既定のglobalエントリ
    $regions = [
        'global' => ['全国図鑑', 'global', ['global']],
    ];

    $localMapping = $pokedexConfig['local_pokedex_mapping'] ?? [];
    $regionDefs = $pokedexConfig['regions'] ?? [];

    foreach ($regionDefs as $regionKey => $def) {
        if (!is_array($def)) {
            continue;
        }

        $versionKey = $def['version_key'] ?? null;
        if (!$versionKey) {
            continue; // versionキーがないと生成不可
        }
        $pokedexIndex = isset($def['pokedex_index']) ? intval($def['pokedex_index']) : 0;

        // 表示名: display_jpn優先、なければlocal_pokedex_mappingから取得
        $displayName = $def['display_jpn'] ?? null;
        if (!$displayName && isset($localMapping[$versionKey]['pokedex']) && is_array($localMapping[$versionKey]['pokedex'])) {
            $pokedexNames = $localMapping[$versionKey]['pokedex'];
            if (isset($pokedexNames[$pokedexIndex]['jpn'])) {
                $displayName = $pokedexNames[$pokedexIndex]['jpn'];
            } elseif (isset($pokedexNames[0]['jpn'])) {
                $displayName = $pokedexNames[0]['jpn'];
            }
        }
        if (!$displayName) {
            $displayName = $versionKey;
        }

        // バージョン配列: local_pokedex_mapping の version キーから抽出
        $versions = [];
        if (isset($localMapping[$versionKey]['version']) && is_array($localMapping[$versionKey]['version'])) {
            $versions = array_keys($localMapping[$versionKey]['version']);
        }

        $regions[$regionKey] = [$displayName, $versionKey, $versions];
    }

    return $regions;
}

/**
 * SQLiteデータベース操作クラス
 */
class Database {
    private $db;
    private $error = null;

    /**
     * コンストラクタ
     * 
     * @param string $dbPath データベースファイルのパス
     */
    public function __construct($dbPath = 'pokedex.db') {
        try {
            $this->db = new SQLite3($dbPath);
            $this->db->enableExceptions(true);
            // 外部キー制約を有効化
            $this->db->exec('PRAGMA foreign_keys = ON;');
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            throw new Exception("データベース接続エラー: " . $this->error);
        }
    }

    /**
     * クエリを実行し、結果を配列で返す
     * 
     * @param string $query SQLクエリ
     * @param array $params パラメータの連想配列
     * @return array 結果の配列
     */
    public function query($query, $params = []) {
        try {
            $stmt = $this->db->prepare($query);
            
            // パラメータをバインド
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, $this->getParamType($value));
            }
            
            $result = $stmt->execute();
            
            $rows = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $rows[] = $row;
            }
            
            return $rows;
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            throw new Exception("クエリ実行エラー: " . $this->error);
        }
    }

    /**
     * 1行だけ取得するクエリを実行
     * 
     * @param string $query SQLクエリ
     * @param array $params パラメータの連想配列
     * @return array|null 結果の連想配列、見つからない場合はnull
     */
    public function querySingle($query, $params = []) {
        $results = $this->query($query, $params);
        return $results[0] ?? null;
    }

    /**
     * 挿入、更新、削除などのクエリを実行
     * 
     * @param string $query SQLクエリ
     * @param array $params パラメータの連想配列
     * @return bool 成功したかどうか
     */
    public function execute($query, $params = []) {
        try {
            $stmt = $this->db->prepare($query);
            
            // パラメータをバインド
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, $this->getParamType($value));
            }
            
            return $stmt->execute() !== false;
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            throw new Exception("クエリ実行エラー: " . $this->error);
        }
    }

    /**
     * 最後に挿入された行のIDを取得
     * 
     * @return int 最後に挿入された行のID
     */
    public function lastInsertId() {
        return $this->db->lastInsertRowID();
    }

    /**
     * 最後に発生したエラーメッセージを取得
     * 
     * @return string|null エラーメッセージ
     */
    public function getError() {
        return $this->error;
    }

    /**
     * パラメータの型を取得
     * 
     * @param mixed $value パラメータの値
     * @return int SQLite3定数
     */
    private function getParamType($value) {
        if (is_int($value)) {
            return SQLITE3_INTEGER;
        } elseif (is_float($value)) {
            return SQLITE3_FLOAT;
        } elseif (is_string($value)) {
            return SQLITE3_TEXT;
        } elseif (is_bool($value)) {
            return SQLITE3_INTEGER;
        } elseif (is_null($value)) {
            return SQLITE3_NULL;
        } else {
            return SQLITE3_TEXT;
        }
    }

    /**
     * デストラクタ
     */
    public function __destruct() {
        if ($this->db) {
            $this->db->close();
        }
    }
}

// メイン処理
try {
    // データベース接続
    $db = new Database('pokedex.db');
    
    $region = isset($_GET['region']) ? $_GET['region'] : null;
    $no = isset($_GET['no']) ? $_GET['no'] : null;
    // idパラメータ（existsエンドポイントで使用）
    $id = isset($_GET['id']) ? $_GET['id'] : null;
    // 追加: existsエンドポイント用パラメータ
    $mode = isset($_GET['mode']) ? $_GET['mode'] : null;

    // description モード: globalNo と language から図鑑説明を取得
    if ($mode === 'description') {
        $globalNo = isset($_GET['globalNo']) ? $_GET['globalNo'] : null;
        $languageParam = isset($_GET['language']) ? $_GET['language'] : null;

        // globalNo もしくは id のどちらかが必要
        if ($globalNo === null && $id === null) {
            throw new Exception('globalNo または id のいずれかを指定してください');
        }
        if ($languageParam === null) {
            throw new Exception('language を指定してください');
        }

        // ポケモン内部IDを取得
        if ($id !== null) {
            $pokemonId = $id;
        } else {
            // globalNo からポケモンIDを取得
            $row = $db->querySingle(
                "SELECT id FROM pokedex WHERE globalNo = :globalNo LIMIT 1",
                [':globalNo' => $globalNo]
            );

            if (!$row) {
                throw new Exception('指定されたポケモンが見つかりません');
            }
            $pokemonId = $row['id'];
        }

        // 該当する説明文を取得
        $descriptions = $db->query(
            "SELECT ver, version, pokedex, description FROM local_pokedex_description WHERE id = :id AND language = :language ORDER BY ver ASC",
            [
                ':id' => $pokemonId,
                ':language' => $languageParam
            ]
        );

        echo json_encode([
            'success' => true,
            'data' => $descriptions
        ]);
        exit;
    }

    // search モード: pokedex_description テーブルを検索
    if ($mode === 'search') {
        $items = isset($_GET['items']) ? $_GET['items'] : 'description';
        $word = isset($_GET['word']) ? $_GET['word'] : null;
        $language = isset($_GET['language']) ? $_GET['language'] : 'jpn';

        // word が必要
        if ($word === null || trim($word) === '') {
            throw new Exception('word を指定してください');
        }

        // items をカンマ区切りで配列に変換
        $searchItems = array_filter(array_map('trim', explode(',', $items)));
        if (empty($searchItems)) {
            throw new Exception('検索項目を指定してください');
        }

        // 検索クエリを構築
        $conditions = [];
        $params = [':word' => '%' . $word . '%'];
        
        foreach ($searchItems as $item) {
            switch ($item) {
                case 'description':
                    // pokedex_description テーブルで検索
                    $conditions[] = 'description';
                    break;
                case 'name':
                    $conditions[] = 'name';
                    break;
                case 'classification':
                    $conditions[] = 'classification';
                    break;
                default:
                    // 未対応の項目は無視
                    break;
            }
        }

        if (empty($conditions)) {
            throw new Exception('有効な検索項目が指定されていません');
        }

        // 結果を格納する配列（重複排除のためIDをキーに）
        $resultsById = [];

        // description 検索
        if (in_array('description', $conditions)) {
            // 言語指定が 'all' の場合は言語フィルタなし
            if ($language === 'all') {
                $descResults = $db->query(
                    "SELECT id, verID, dex as description FROM pokedex_description WHERE dex LIKE :word ORDER BY id ASC",
                    [
                        ':word' => '%' . $word . '%'
                    ]
                );
            } else {
                $descResults = $db->query(
                    "SELECT id, verID, dex as description FROM pokedex_description WHERE dex LIKE :word AND language = :language ORDER BY id ASC",
                    [
                        ':word' => '%' . $word . '%',
                        ':language' => $language
                    ]
                );
            }

            foreach ($descResults as $result) {
                $id = $result['id'];
                $verID = $result['verID'] ?? '';
                // IDとverIDの組み合わせで一意のキーを作成（複数バージョンを別々に扱う）
                $uniqueKey = $id . '|' . $verID;
                
                if (!isset($resultsById[$uniqueKey])) {
                    $resultsById[$uniqueKey] = [
                        'id' => $id,
                        'matched_fields' => [],
                        'description' => $result['description'],
                        'verID' => $verID
                    ];
                }
                // 重複を避けるため、まだ追加されていない場合のみ追加
                if (!in_array('description', $resultsById[$uniqueKey]['matched_fields'])) {
                    $resultsById[$uniqueKey]['matched_fields'][] = 'description';
                }
            }
        }

        // name 検索
        if (in_array('name', $conditions)) {
            // pokedex_nameテーブルのidカラムで前方一致検索
            // 言語指定が 'all' の場合は言語フィルタなし
            if ($language === 'all') {
                $nameResults = $db->query(
                    "SELECT id, name FROM pokedex_name WHERE name LIKE :word_pattern ORDER BY id ASC",
                    [
                        ':word_pattern' => '%' . $word . '%'
                    ]
                );
            } else {
                $nameResults = $db->query(
                    "SELECT id, name FROM pokedex_name WHERE name LIKE :word_pattern AND language = :language ORDER BY id ASC",
                    [
                        ':word_pattern' => '%' . $word . '%',
                        ':language' => $language
                    ]
                );
            }

            foreach ($nameResults as $result) {
                $id = $result['id'];
                // 名前検索の場合、既存のエントリがあればそれに追加、なければ新規作成
                $found = false;
                foreach ($resultsById as $key => $existingResult) {
                    if ($existingResult['id'] === $id) {
                        $resultsById[$key]['matched_name'] = $result['name'];
                        if (!in_array('name', $resultsById[$key]['matched_fields'])) {
                            $resultsById[$key]['matched_fields'][] = 'name';
                        }
                        $found = true;
                        break;
                    }
                }
                
                // 既存のエントリがない場合は新規作成（verIDなし）
                if (!$found) {
                    $uniqueKey = $id . '|';
                    $resultsById[$uniqueKey] = [
                        'id' => $id,
                        'matched_fields' => ['name'],
                        'matched_name' => $result['name']
                    ];
                }
            }
        }

        // classification 検索
        if (in_array('classification', $conditions)) {
            // 言語指定が 'all' の場合は言語フィルタなし
            if ($language === 'all') {
                $classResults = $db->query(
                    "SELECT id, classification FROM pokedex_classification WHERE classification LIKE :word ORDER BY id ASC",
                    [
                        ':word' => '%' . $word . '%'
                    ]
                );
            } else {
                $classResults = $db->query(
                    "SELECT id, classification FROM pokedex_classification WHERE classification LIKE :word AND language = :language ORDER BY id ASC",
                    [
                        ':word' => '%' . $word . '%',
                        ':language' => $language
                    ]
                );
            }

            foreach ($classResults as $result) {
                $id = $result['id'];
                // 分類検索の場合、既存のエントリがあればそれに追加、なければ新規作成
                $found = false;
                foreach ($resultsById as $key => $existingResult) {
                    if ($existingResult['id'] === $id) {
                        $resultsById[$key]['matched_classification'] = $result['classification'];
                        if (!in_array('classification', $resultsById[$key]['matched_fields'])) {
                            $resultsById[$key]['matched_fields'][] = 'classification';
                        }
                        $found = true;
                        break;
                    }
                }
                
                // 既存のエントリがない場合は新規作成（verIDなし）
                if (!$found) {
                    $uniqueKey = $id . '|';
                    $resultsById[$uniqueKey] = [
                        'id' => $id,
                        'matched_fields' => ['classification'],
                        'matched_classification' => $result['classification']
                    ];
                }
            }
        }

        // 各ポケモンの詳細情報を取得
        $integratedResults = [];
        foreach ($resultsById as $uniqueKey => $baseResult) {
            // 実際のIDを取得（キーから抽出）
            $id = $baseResult['id'];
            $verID = $baseResult['verID'] ?? null;
            
            // verIDをバージョン名に変換
            $verName = null;
            if ($verID) {
                global $versionMapping;
                $verName = convertVerIdToVersion($verID, $versionMapping);
            }
            
            // 最初にlocal_pokedexから情報を取得
            $localInfo = null;
            $fullId = $id;
            
            if ($verName) {
                // バージョン名がある場合は、それに対応するlocal_pokedexを検索
                $localInfo = $db->querySingle(
                    "SELECT id as fullId, pokedex, no, version FROM local_pokedex WHERE id LIKE :id_pattern AND version = :version LIMIT 1",
                    [':id_pattern' => $id . '%', ':version' => $verName]
                );
                
                // local_pokedexから取得した完全なIDを使用
                if ($localInfo) {
                    $fullId = $localInfo['fullId'];
                }
            }
            
            // verIDがないか、該当するlocal_pokedexが見つからない場合は、
            // 基本IDから完全なIDを取得（地方図鑑情報は使わずグローバル扱い）
            if (!$localInfo) {
                // pokedexテーブルから完全なIDを取得
                $pokedexRow = $db->querySingle("SELECT id FROM pokedex WHERE id LIKE :id_pattern LIMIT 1", [':id_pattern' => $id . '%']);
                if ($pokedexRow) {
                    $fullId = $pokedexRow['id'];
                }
            }
            
            // ポケモン名を取得（完全なIDで検索）
            $pokedex_name = $db->query("SELECT * FROM pokedex_name WHERE id = :fullId", [':fullId' => $fullId]);
            $name = [];
            $imageId = null;
            foreach ($pokedex_name as $nameValue) {
                $name[$nameValue['language']] = $nameValue['name'];
                // 画像用のIDを取得（pokedex_nameのidを使用）
                if ($imageId === null && isset($nameValue['id'])) {
                    $imageId = $nameValue['id'];
                }
            }
            $baseResult['name'] = $name;
            $baseResult['imageId'] = $imageId; // pokedex_nameのIDをそのまま使用

            // globalNo を取得（完全なIDで検索）
            $pokedex = $db->querySingle("SELECT globalNo FROM pokedex WHERE id = :fullId LIMIT 1", [':fullId' => $fullId]);
            $baseResult['globalNo'] = $pokedex ? $pokedex['globalNo'] : null;
            
            // local_pokedexの情報を設定
            if ($localInfo) {
                $baseResult['pokedex'] = $localInfo['pokedex'];
                $baseResult['no'] = $localInfo['no'] ?? $baseResult['globalNo'];
                
                // versionをバージョン名に変換
                global $versionMapping;
                $baseResult['ver'] = convertVerIdToVersion($localInfo['version'], $versionMapping);
            } else {
                // verIDがないか、該当するlocal_pokedexがない場合はglobal扱い
                $baseResult['pokedex'] = 'global';
                $baseResult['no'] = $baseResult['globalNo'];
                $baseResult['ver'] = null;
            }

            // description がまだない場合は取得（あいまい検索）
            if (!isset($baseResult['description'])) {
                $desc = $db->querySingle(
                    "SELECT dex FROM pokedex_description WHERE id LIKE :id_pattern AND language = :language LIMIT 1",
                    [':id_pattern' => $id . '%', ':language' => $language]
                );
                $baseResult['description'] = $desc ? $desc['dex'] : '';
            }

            $baseResult['language'] = $language;
            $integratedResults[] = $baseResult;
        }

        echo json_encode([
            'success' => true,
            'data' => $integratedResults,
            'search_word' => $word,
            'search_items' => $searchItems,
            'language' => $language,
            'results_count' => count($integratedResults)
        ]);
        exit;
    }

 
    $result = [];

    if (!$region && !$no) {
        throw new Exception('リージョンまたはポケモンNoを指定してください');
    }

    // 設定ファイルから地域図鑑マッピングを組み立て
    $validRegions = buildValidRegions($pokedexConfig);

    // 無効なリージョンが指定された場合
    if ($region && !isset($validRegions[$region])) {
        throw new Exception('無効なリージョンが指定されました');
    }

    // description_map モード: ポケモンの図鑑説明をマップ形式で取得（pokedex_mapテーブル利用）
    if ($mode === 'description_map') {
        if (!$region || !$no) {
            throw new Exception('region と no を指定してください');
        }

        if ($region !== 'global') {
            throw new Exception('description_map モードでは region=global のみサポートしています');
        }

        // noパラメータを4桁の文字列にフォーマット
        $globalNoStr = sprintf("%04d", intval($no));

        // pokedex_mapから直接取得
        $mapData = $db->query(
            "SELECT id, verID, language, dex FROM pokedex_map WHERE globalNo = :globalNoStr ORDER BY id ASC, verID ASC, language ASC",
            [':globalNoStr' => $globalNoStr]
        );

        if (empty($mapData)) {
            throw new Exception('指定されたポケモンが見つかりません');
        }

        // configファイルからversion_mappingを読み込み
        $configFile = 'config/pokedex_config.json';
        if (!file_exists($configFile)) {
            throw new Exception('設定ファイルが見つかりません');
        }

        $configContent = file_get_contents($configFile);
        $config = json_decode($configContent, true);

        if (!$config || !isset($config['version_mapping'])) {
            throw new Exception('設定ファイルの読み込みに失敗しました');
        }

        $versionMapping = $config['version_mapping'];

        // ポケモンID -> verID(グループ文字列) -> 言語 の形に整形
        $dataById = [];
        foreach ($mapData as $row) {
            $pokemonId = $row['id'];
            $verGroupKey = isset($row['verID']) ? trim($row['verID']) : '';
            $language = $row['language'];
            $dex = $row['dex'];

            if ($verGroupKey === '') {
                continue;
            }

            if (!isset($dataById[$pokemonId])) {
                $dataById[$pokemonId] = [];
            }
            if (!isset($dataById[$pokemonId][$verGroupKey])) {
                $dataById[$pokemonId][$verGroupKey] = [];
            }

            $dataById[$pokemonId][$verGroupKey][$language] = $dex;
        }

        // verIDグループごとに整形
        $allDescriptions = [];
        foreach ($dataById as $pokemonId => $verGroupData) {
            $groupedDescriptions = [];

            foreach ($verGroupData as $verGroupKey => $langData) {
                $verIds = array_values(array_filter(array_map('trim', explode(',', $verGroupKey)), function ($value) {
                    return $value !== '';
                }));

                if (empty($verIds)) {
                    continue;
                }

                $versionNames = [];
                foreach ($verIds as $vid) {
                    if (isset($versionMapping[$vid]) && !empty($versionMapping[$vid]['name_eng'])) {
                        $versionNames[] = $versionMapping[$vid]['name_eng'];
                    } else {
                        $versionNames[] = $vid;
                    }
                }

                $baseGroupKeyParts = array_map(function ($name) {
                    $normalized = trim((string) $name);
                    return $normalized === '' ? 'unknown' : strtolower($normalized);
                }, $versionNames);

                $baseGroupKey = implode('_', $baseGroupKeyParts);
                if ($baseGroupKey === '') {
                    $baseGroupKey = strtolower(str_replace(',', '_', $verGroupKey));
                }

                $groupKey = $baseGroupKey;
                $suffix = 2;
                while (isset($groupedDescriptions[$groupKey])) {
                    $groupKey = $baseGroupKey . '_' . $suffix;
                    $suffix++;
                }

                $representativeVerId = $verIds[count($verIds) - 1];

                $groupEntry = [
                    'raw_ver_group' => $verGroupKey,
                    'ver_ids' => $verIds,
                    'version_names' => $versionNames,
                    'representative_ver_id' => $representativeVerId,
                    'common' => $langData,
                ];

                // 各バージョン名でもアクセスできるようにエイリアスを付与
                foreach ($verIds as $singleVerId) {
                    $singleKeyBase = (isset($versionMapping[$singleVerId]) && !empty($versionMapping[$singleVerId]['name_eng']))
                        ? $versionMapping[$singleVerId]['name_eng']
                        : $singleVerId;

                    $singleKeyNormalized = strtolower(trim((string) $singleKeyBase));
                    if ($singleKeyNormalized === '') {
                        $singleKeyNormalized = strtolower($singleVerId);
                    }

                    $candidateKey = $singleKeyNormalized;
                    $index = 2;
                    while (isset($groupEntry[$candidateKey])) {
                        $candidateKey = $singleKeyNormalized . '_' . $index;
                        $index++;
                    }

                    $groupEntry[$candidateKey] = $langData;
                }

                $groupedDescriptions[$groupKey] = $groupEntry;
            }

            $allDescriptions[$pokemonId] = $groupedDescriptions;
        }

        echo json_encode([
            'success' => true,
            'data' => $allDescriptions, // IDをキーとしたオブジェクト形式
            'globalNo' => sprintf("%04d", intval($no))
        ]);
        exit;
    }


    // exists モード: 指定したglobalNoが地域図鑑に存在するか判定
    if ($mode === 'exists') {
        if (!$region || ($no === null && $id === null)) {
            throw new Exception('region と (no または id) を指定してください');
        }

        if ($region === 'global') {
            // 全国図鑑の場合は各地域での存在を確認し、結果を配列で返す
            $existsResult = [];

            // ポケモンIDを取得（複数の可能性がある）
            if ($id !== null) {
                $pokemonIds = [$id];
            } else {
                // globalNo からポケモンIDの一覧を取得
                $rows = $db->query(
                    "SELECT id FROM pokedex WHERE globalNo = :globalNo",
                    [':globalNo' => $no]
                );

                if (empty($rows)) {
                    // ポケモンが見つからない場合はすべての地域で-1を返す
                    foreach ($validRegions as $regionKey => $regionData) {
                        if ($regionKey !== 'global') {
                            $existsResult[$regionKey] = -1;
                        }
                    }
                } else {
                    $pokemonIds = array_column($rows, 'id');
                }
            }

            if (!empty($pokemonIds)) {
                // 各IDについて各地域での存在確認
                foreach ($pokemonIds as $pokemonId) {
                    $idResults = [];

                    foreach ($validRegions as $regionKey => $regionData) {
                        if ($regionKey === 'global') continue; // global自体はスキップ

                        $pokedexName = $regionData[0];
                        $versionName = $regionData[1];

                        $row = $db->querySingle(
                            "SELECT no FROM local_pokedex WHERE id = :id AND pokedex = :pokedex AND version = :version LIMIT 1",
                            [
                                ':id' => $pokemonId,
                                ':pokedex' => $pokedexName,
                                ':version' => $versionName
                            ]
                        );

                        $idResults[$regionKey] = $row ? intval($row['no']) : -1;
                    }

                    $existsResult[$pokemonId] = $idResults;
                }
            }
        } else {
            // 有効なリージョンは既に検証済みだが、念のためチェック
            if (!isset($validRegions[$region])) {
                throw new Exception('無効なリージョンが指定されました');
            }
            $pokedexName  = $validRegions[$region][0];
            $versionName  = $validRegions[$region][1];

            if ($id !== null) {
                $row = $db->querySingle(
                    "SELECT no FROM local_pokedex WHERE id = :id AND pokedex = :pokedex AND version = :version LIMIT 1",
                    [
                        ':id' => $id,
                        ':pokedex' => $pokedexName,
                        ':version' => $versionName
                    ]
                );
            } else {
                $row = $db->querySingle(
                    "SELECT no FROM local_pokedex WHERE globalNo = :globalNo AND pokedex = :pokedex AND version = :version LIMIT 1",
                    [
                        ':globalNo' => $no,
                        ':pokedex' => $pokedexName,
                        ':version' => $versionName
                    ]
                );
            }
            
            $existsResult = $row ? intval($row['no']) : -1;

        }

        echo json_encode([
            'success' => true,
            'result'  => $existsResult
        ]);
        exit;
    }

    // リージョンが指定されているがNoが指定されていない場合はリスト表示
    if ($region && !$no) {
        if($region === 'global') {
            // 軽量モード: 一覧表示用に必要最低限のカラムのみ取得
            if ($mode === 'global_list_light') {
                $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 0;
                $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
                if ($limit <= 0) {
                    $limit = 2000; // デフォルト最大件数（調整可能）
                }
                if ($offset < 0) {
                    $offset = 0;
                }

                $query = "
                    SELECT 
                        p.id,
                        p.globalNo,
                        p.height,
                        p.weight,
                        (
                            SELECT name 
                            FROM pokedex_name pn 
                            WHERE pn.id = p.id AND pn.language = 'jpn' 
                            LIMIT 1
                        ) AS name_jpn,
                        (
                            SELECT type1 
                            FROM local_pokedex_type t 
                            WHERE t.id = p.id 
                            ORDER BY t.version DESC 
                            LIMIT 1
                        ) AS type1,
                        (
                            SELECT type2 
                            FROM local_pokedex_type t 
                            WHERE t.id = p.id 
                            ORDER BY t.version DESC 
                            LIMIT 1
                        ) AS type2
                    FROM pokedex p
                    ORDER BY CAST(p.globalNo AS INTEGER) ASC
                    LIMIT :limit OFFSET :offset
                ";

                $rows = $db->query($query, [
                    ':limit' => $limit,
                    ':offset' => $offset
                ]);

                $result = [];
                foreach ($rows as $row) {
                    $result[] = [
                        'id'       => $row['id'],
                        'no'       => $row['globalNo'],
                        'globalNo' => $row['globalNo'],
                        'name'     => ['jpn' => $row['name_jpn'] ?? ''],
                        'type1'    => $row['type1'] ?? null,
                        'type2'    => $row['type2'] ?? null,
                        'weight'   => $row['weight'] ?? null,
                        'height'   => $row['height'] ?? null,
                    ];
                }

                echo json_encode([
                    'success'     => true,
                    'data'        => $result,
                    'region'      => 'global',
                    'pagination'  => [
                        'limit'  => $limit,
                        'offset' => $offset,
                        'count'  => count($result)
                    ]
                ]);
                exit;
            }

            // 全データを一括取得（N+1クエリ問題の解決）
            $query = "
                SELECT *
                FROM pokedex
                ORDER BY CAST(globalNo AS INTEGER) ASC
            ";
            $rows = $db->query($query);
            
            // ポケモン名を全件取得
            $all_names = $db->query("SELECT * FROM pokedex_name");
            $names_by_id = [];
            foreach ($all_names as $name_row) {
                if (!isset($names_by_id[$name_row['id']])) {
                    $names_by_id[$name_row['id']] = [];
                }
                $names_by_id[$name_row['id']][$name_row['language']] = $name_row['name'];
            }
            
            // タイプ情報を全件取得
            $all_types = $db->query("SELECT * FROM local_pokedex_type");
            $types_by_id = [];
            foreach ($all_types as $type_row) {
                if (!isset($types_by_id[$type_row['id']])) {
                    $types_by_id[$type_row['id']] = [];
                }
                $types_by_id[$type_row['id']][$type_row['version']] = [
                    'type1' => $type_row['type1'],
                    'type2' => $type_row['type2']
                ];
            }
            
            // 分類を全件取得
            $all_classifications = $db->query("SELECT * FROM pokedex_classification");
            $classifications_by_id = [];
            foreach ($all_classifications as $class_row) {
                if (!isset($classifications_by_id[$class_row['id']])) {
                    $classifications_by_id[$class_row['id']] = [];
                }
                $classifications_by_id[$class_row['id']][$class_row['language']] = $class_row['classification'];
            }
            
            // フォーム情報を全件取得
            $all_forms = $db->query("SELECT * FROM pokedex_form");
            $forms_by_id = [];
            foreach ($all_forms as $form_row) {
                if (!isset($forms_by_id[$form_row['id']])) {
                    $forms_by_id[$form_row['id']] = [];
                }
                $forms_by_id[$form_row['id']][$form_row['language']] = $form_row['form'];
            }
            
            // タマゴグループを全件取得
            $all_eggs = $db->query("SELECT * FROM pokedex_egg");
            $eggs_by_id = [];
            foreach ($all_eggs as $egg_row) {
                if (!isset($eggs_by_id[$egg_row['id']])) {
                    $eggs_by_id[$egg_row['id']] = [];
                }
                $eggs_by_id[$egg_row['id']][] = $egg_row['egg'];
            }
            
            // データをマージ
            $result = [];
            foreach ($rows as $row) {
                $row['no'] = $row['globalNo'];
                
                // 名前をマージ
                $row['name'] = $names_by_id[$row['id']] ?? [];
                
                // タイプを取得（有効なバージョンを末尾から探索）
                $typeFound = false;
                if (isset($types_by_id[$row['id']])) {
                    foreach (array_reverse($validRegions) as $ver) {
                        if (isset($types_by_id[$row['id']][$ver[1]])) {
                            $row['type1'] = $types_by_id[$row['id']][$ver[1]]['type1'];
                            $row['type2'] = $types_by_id[$row['id']][$ver[1]]['type2'];
                            $typeFound = true;
                            break;
                        }
                    }
                }
                if (!$typeFound) {
                    $row['type1'] = null;
                    $row['type2'] = null;
                }
                
                // 分類をマージ
                $row['classification'] = $classifications_by_id[$row['id']] ?? [];
                
                // フォーム情報をマージ
                $row['forms'] = $forms_by_id[$row['id']] ?? [];
                
                // タマゴグループをマージ
                $row['egg'] = $eggs_by_id[$row['id']] ?? [];
                
                // height と weight は既に $row に含まれているのでそのまま使用
                
                // 配列が初期化されていない場合は初期化
                if (!isset($result[$row['globalNo']])) {
                    $result[$row['globalNo']] = [];
                }
                // 配列に追加
                $result[$row['globalNo']][] = $row;
            }
        } else {
            $query = "
                SELECT *
                FROM local_pokedex
                WHERE pokedex = :pokedex
                AND version = :version
                ORDER BY CAST(no AS INTEGER) ASC
            ";
            
            $rows = $db->query($query, [
                ':pokedex' => $validRegions[$region][0],
                ':version' => $validRegions[$region][1]
            ]);
            
            // noをキーに、各ポケモンのデータを配列として保持
            $result = [];
            foreach ($rows as $row) {
                // ポケモン名を取得
                $pokedex_name= $db->query("SELECT * FROM pokedex_name WHERE id = :id", [
                    ':id' => $row['id']
                ]);
                $name = [];
                foreach ($pokedex_name as $value) {
                    $name[$value['language']] = $value['name'];
                }
                $row['name'] = $name;

                // ポケモンタイプを取得
                $pokedex_type= $db->query("SELECT * FROM local_pokedex_type WHERE id = :id AND version = :version", [
                    ':id' => $row['id'],
                    ':version' => $validRegions[$region][1]
                ]);
                foreach ($pokedex_type as $value) {
                    $row['type1'] = $value['type1'];
                    $row['type2'] = $value['type2'];
                }

                // 分類を取得
                $pokedex_classification= $db->query("SELECT * FROM pokedex_classification WHERE id = :id", [
                    ':id' => $row['id']
                ]);
                $classification = [];
                foreach ($pokedex_classification as $value) {
                    $classification[$value['language']] = $value['classification'];
                }
                $row['classification'] = $classification;

                // フォーム情報を取得
                $pokedex_form = $db->query("SELECT * FROM pokedex_form WHERE id = :id", [
                    ':id' => $row['id']
                ]);
                $forms = [];
                foreach ($pokedex_form as $value) {
                    $forms[$value['language']] = $value['form'];
                }
                $row['forms'] = $forms;

                // 高さと重さを取得
                $pokedex= $db->query("SELECT * FROM pokedex WHERE id = :id", [
                    ':id' => $row['id']
                ]);
                foreach ($pokedex as $value) {
                    $row['height'] = $value['height'];
                    $row['weight'] = $value['weight'];
                }

                // ステータスを取得
                $pokedex_status= $db->query("SELECT * FROM local_pokedex_status WHERE id = :id AND version = :version", [
                    ':id' => $row['id'],
                    ':version' => $validRegions[$region][1]
                ]);
                foreach ($pokedex_status as $value) {
                    $row['hp'] = $value['hp'];
                    $row['attack'] = $value['attack'];
                    $row['defense'] = $value['defense'];
                    $row['special_attack'] = $value['special_attack'];
                    $row['special_defense'] = $value['special_defense'];
                    $row['speed'] = $value['speed'];
                }

                // 特性を取得
                $pokedex_abilities= $db->query("SELECT * FROM local_pokedex_ability WHERE id = :id AND version = :version", [
                    ':id' => $row['id'],
                    ':version' => $validRegions[$region][1]
                ]);
                foreach ($pokedex_abilities as $value) {
                    $row['ability1'] = $value['ability1'];
                    $row['ability2'] = $value['ability2'];
                    $row['dream_ability'] = $value['dream_ability'];
                }

                // // 図鑑説明を取得（バージョンでフィルタリング）
                // $versionConditions = [];
                // $params = [':id' => $row['id']];
                // $i = 0;

                // foreach ($validRegions[$region][2] as $version) {
                //     // 通常版
                //     $paramName = ":version{$i}";
                //     $versionConditions[] = $paramName;
                //     $params[$paramName] = $version;
                //     $i++;
                    
                //     // _kanji版
                //     $paramName = ":version{$i}";
                //     $versionConditions[] = $paramName;
                //     $params[$paramName] = $version . '_kanji';
                //     $i++;
                // }

                // $description = [];
                // if (!empty($versionConditions)) {
                //     $query = "SELECT * FROM local_pokedex_description 
                //             WHERE id = :id AND ver IN (" . implode(',', $versionConditions) . ")";
                //     $pokedex_description = $db->query($query, $params);
                    
                //     foreach ($pokedex_description as $value) {
                //         if (!isset($description[$value['ver']])) {
                //             $description[$value['ver']] = [];
                //         }
                //         $description[$value['ver']][$value['language']] = $value['description'];
                //     }
                // }
                // $row['description'] = $description;

                // 配列が初期化されていない場合は初期化
                if (!isset($result[$row['no']])) {
                    $result[$row['no']] = [];
                }
                // 配列に追加
                $result[$row['no']][] = $row;
            }
        }
    } 
    // Noが指定されている場合は詳細情報を取得
    elseif ($no) {
        if($region === 'global') {
            $query = "
                SELECT *
                FROM pokedex
                WHERE globalNo = :globalNo
            ";
            $rows = $db->query($query, [
                ':globalNo' => $no
            ]);
            // print_r($rows);
            $result = [];
            foreach ($rows as $row) {
                $row['no'] = $row['globalNo'];
                // ポケモン名を取得
                $pokedex_name= $db->query("SELECT * FROM pokedex_name WHERE id = :id", [
                    ':id' => $row['id']
                ]);
                $name = [];
                foreach ($pokedex_name as $value) {
                    $name[$value['language']] = $value['name'];
                }
                $row['name'] = $name;

                // ポケモンタイプを取得（有効なバージョンを末尾から探索）
                $typeFound = false;
                foreach (array_reverse($validRegions) as $ver) {
                    $pokedex_type = $db->query("SELECT * FROM local_pokedex_type WHERE id = :id AND version = :version", [
                        ':id' => $row['id'],
                        ':version' => $ver[1]
                    ]);
                    if (!empty($pokedex_type)) {
                        foreach ($pokedex_type as $value) {
                            $row['type1'] = $value['type1'];
                            $row['type2'] = $value['type2'];
                        }
                        $typeFound = true;
                        break;
                    }
                }
                if (!$typeFound) {
                    $row['type1'] = null;
                    $row['type2'] = null;
                }

                // 分類を取得
                $pokedex_classification= $db->query("SELECT * FROM pokedex_classification WHERE id = :id", [
                    ':id' => $row['id']
                ]);
                $classification = [];
                foreach ($pokedex_classification as $value) {
                    $classification[$value['language']] = $value['classification'];
                }
                $row['classification'] = $classification;

                // フォーム情報を取得
                $pokedex_form = $db->query("SELECT * FROM pokedex_form WHERE id = :id", [
                    ':id' => $row['id']
                ]);
                $forms = [];
                foreach ($pokedex_form as $value) {
                    $forms[$value['language']] = $value['form'];
                }
                $row['forms'] = $forms;

                // タマゴグループを取得
                $pokedex_egg = $db->query("SELECT egg FROM pokedex_egg WHERE id = :id", [
                    ':id' => $row['id']
                ]);
                $egg = [];
                foreach ($pokedex_egg as $value) {
                    $egg[] = $value['egg'];
                }
                $row['egg'] = $egg;

                // 高さと重さを取得
                $pokedex= $db->query("SELECT * FROM pokedex WHERE id = :id", [
                    ':id' => $row['id']
                ]);
                foreach ($pokedex as $value) {
                    $row['height'] = $value['height'];
                    $row['weight'] = $value['weight'];
                }

                // 図鑑説明を取得（バージョンでフィルタリング）
                // $versionConditions = [];
                // $params = [':id' => $row['id']];
                // $i = 0;

                // foreach ($validRegions[$region][2] as $version) {
                //     $paramName = ":version{$i}";
                //     $versionConditions[] = $paramName;
                //     $params[$paramName] = $version;
                //     $i++;
                // }

                // $description = [];
                // if (!empty($versionConditions)) {
                //     $query = "SELECT * FROM local_pokedex_description 
                //             WHERE id = :id AND version IN (" . implode(',', $versionConditions) . ")";
                //     $pokedex_description = $db->query($query, $params);
                    
                //     foreach ($pokedex_description as $value) {
                //         if (!isset($description[$value['version']])) {
                //             $description[$value['version']] = [];
                //         }
                //         $description[$value['version']][$value['language']] = $value['description'];
                //     }
                // }
                // $row['description'] = $description;

                // 配列が初期化されていない場合は初期化
                if (!isset($result[$row['globalNo']])) {
                    $result[$row['globalNo']] = [];
                }
                // 配列に追加
                $result[$row['globalNo']][] = $row;
            }
        } else {
            $query = "
                SELECT *
                FROM local_pokedex
                WHERE pokedex = :pokedex
                AND no = :no
                AND version = :version
                ORDER BY CAST(no AS INTEGER) ASC
            ";
            
            $rows = $db->query($query, [
                ':pokedex' => $validRegions[$region][0],
                ':no' => $no,
                ':version' => $validRegions[$region][1]
            ]);
            
            $result = [];
            if ($rows) {
                foreach ($rows as $row) {
                    // ポケモン名を取得
                    $pokedex_name= $db->query("SELECT * FROM pokedex_name WHERE id = :id", [
                        ':id' => $row['id']
                    ]);
                    $name = [];
                    foreach ($pokedex_name as $value) {
                        $name[$value['language']] = $value['name'];
                    }
                    $row['name'] = $name;

                    // ポケモンタイプを取得
                    $pokedex_type= $db->query("SELECT * FROM local_pokedex_type WHERE id = :id AND version = :version", [
                        ':id' => $row['id'],
                        ':version' => $validRegions[$region][1]
                    ]);
                    foreach ($pokedex_type as $value) {
                        $row['type1'] = $value['type1'];
                        $row['type2'] = $value['type2'];
                    }

                    // 分類を取得
                    $pokedex_classification= $db->query("SELECT * FROM pokedex_classification WHERE id = :id", [
                        ':id' => $row['id']
                    ]);
                    $classification = [];
                    foreach ($pokedex_classification as $value) {
                        $classification[$value['language']] = $value['classification'];
                    }
                    $row['classification'] = $classification;

                    // フォーム情報を取得
                    $pokedex_form = $db->query("SELECT * FROM pokedex_form WHERE id = :id", [
                        ':id' => $row['id']
                    ]);
                    $forms = [];
                    foreach ($pokedex_form as $value) {
                        $forms[$value['language']] = $value['form'];
                    }
                    $row['forms'] = $forms;

                    // 高さと重さを取得
                    $pokedex= $db->query("SELECT * FROM pokedex WHERE id = :id", [
                        ':id' => $row['id']
                    ]);
                    foreach ($pokedex as $value) {
                        $row['height'] = $value['height'];
                        $row['weight'] = $value['weight'];
                    }

                    // ステータスを取得
                    $pokedex_status= $db->query("SELECT * FROM local_pokedex_status WHERE id = :id AND version = :version", [
                        ':id' => $row['id'],
                        ':version' => $validRegions[$region][1]
                    ]);
                    foreach ($pokedex_status as $value) {
                        $row['hp'] = $value['hp'];
                        $row['attack'] = $value['attack'];
                        $row['defense'] = $value['defense'];
                        $row['special_attack'] = $value['special_attack'];
                        $row['special_defense'] = $value['special_defense'];
                        $row['speed'] = $value['speed'];
                    }

                    // 特性を取得
                    $pokedex_abilities= $db->query("SELECT * FROM local_pokedex_ability WHERE id = :id AND version = :version", [
                        ':id' => $row['id'],
                        ':version' => $validRegions[$region][1]
                    ]);
                    foreach ($pokedex_abilities as $value) {
                        $row['ability1'] = $value['ability1'];
                        $row['ability2'] = $value['ability2'];
                        $row['dream_ability'] = $value['dream_ability'];
                    }

                    // 特性の説明文を取得（日本語）
                    $abilities = ['ability1', 'ability2', 'dream_ability'];
                    foreach ($abilities as $abilityField) {
                        $abilityName = $row[$abilityField] ?? null;
                        if ($abilityName) {
                            $abilityDescription = null;
                            // 地方に紐づくゲームバージョンを新しい順に探索
                            $searchVersions = $validRegions[$region][1];
                            $abilityRow = $db->querySingle(
                                "SELECT * FROM ability_language WHERE ability = :ability AND version = :version LIMIT 1",
                                [
                                    ':ability' => $abilityName,
                                    ':version' => $searchVersions
                                ]
                            );
                            $row[$abilityField . '_description'] = [($abilityRow['language'] ?? "") => ($abilityRow['description'] ?? "")];
                        } else {
                            $row[$abilityField . '_description'] = ["" => ""];
                        }
                    }

                    // 図鑑説明を取得（バージョンでフィルタリング）
                    $versionConditions = [];
                    $params = [':id' => $row['id']];
                    $i = 0;

                    foreach ($validRegions[$region][2] as $version) {
                        // 通常版
                        $paramName = ":version{$i}";
                        $versionConditions[] = $paramName;
                        $params[$paramName] = $version;
                        $i++;
                        
                        // _kanji版
                        $paramName = ":version{$i}";
                        $versionConditions[] = $paramName;
                        $params[$paramName] = $version . '_kanji';
                        $i++;
                    }

                    $description = [];
                    // すべてのバージョンに対してキーを初期化（空文字列で）
                    foreach ($validRegions[$region][2] as $version) {
                        $description[$version] = ['jpn' => '', 'eng' => ''];
                        $description[$version . '_kanji'] = ['jpn' => '', 'eng' => ''];
                    }
                    
                    if (!empty($versionConditions)) {
                        $query = "SELECT * FROM local_pokedex_description 
                                WHERE id = :id AND ver IN (" . implode(',', $versionConditions) . ")";
                        $pokedex_description = $db->query($query, $params);
                        
                        foreach ($pokedex_description as $value) {
                            $description[$value['ver']][$value['language']] = $value['description'];
                        }
                    }
                    $row['description'] = $description;

                    // 配列が初期化されていない場合は初期化
                    if (!isset($result[$row['no']])) {
                        $result[$row['no']] = [];
                    }
                    // 配列に追加
                    $result[$row['no']][] = $row;
                    
                }
            } else {
                throw new Exception('指定されたポケモンが見つかりません');
            }
        }
    }
    
    // 結果をJSONで出力
    echo json_encode([
        'success' => true,
        'data' => $result,
        'region' => $region ? $validRegions[$region][0] : null
    ]);
    
} catch (Exception $e) {
    // エラーレスポンス (HTTP 200で返すよう変更)
    // http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

?>