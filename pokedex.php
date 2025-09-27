<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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

    // search モード: description テーブルを検索
    if ($mode === 'search') {
        $item = isset($_GET['item']) ? $_GET['item'] : null;
        $word = isset($_GET['word']) ? $_GET['word'] : null;

        // item と word の両方が必要
        if ($item === null || $word === null) {
            throw new Exception('item と word を指定してください');
        }

        // item=description の場合のみ対応
        if ($item === 'description') {
            // description カラムを LIKE 検索
            $searchResults = $db->query(
                "SELECT * FROM local_pokedex_description WHERE description LIKE :word ORDER BY id ASC",
                [
                    ':word' => '%' . $word . '%'
                ]
            );

            // 検索結果にlocal_pokedexの情報を統合
            $integratedResults = [];
            foreach ($searchResults as $result) {
                // local_pokedexテーブルから追加情報を取得
                $localPokedexInfo = $db->querySingle(
                    "SELECT * FROM local_pokedex WHERE id = :id AND version = :version AND pokedex = :pokedex LIMIT 1",
                    [
                        ':id' => $result['id'],
                        ':version' => $result['version'],
                        ':pokedex' => $result['pokedex']
                    ]
                );

                // 結果を統合
                $integratedResult = $result;
                if ($localPokedexInfo) {
                    // local_pokedexの情報を追加
                    $integratedResult['no'] = $localPokedexInfo['no'];
                    $integratedResult['globalNo'] = $localPokedexInfo['globalNo'];
                    $integratedResult['region'] = $localPokedexInfo['pokedex'];
                    $integratedResult['version_info'] = $localPokedexInfo['version'];
                    
                    // ポケモン名を取得
                    $pokedex_name = $db->query("SELECT * FROM pokedex_name WHERE id = :id", [
                        ':id' => $result['id']
                    ]);
                    $name = [];
                    foreach ($pokedex_name as $nameValue) {
                        $name[$nameValue['language']] = $nameValue['name'];
                    }
                    $integratedResult['name'] = $name;
                } else {
                    // local_pokedexに情報がない場合はnullで埋める
                    $integratedResult['no'] = null;
                    $integratedResult['globalNo'] = null;
                    $integratedResult['region'] = $result['pokedex'];
                    $integratedResult['version_info'] = $result['version'];
                    $integratedResult['name'] = [];
                }
                
                $integratedResults[] = $integratedResult;
            } 

            echo json_encode([
                'success' => true,
                'data' => $integratedResults,
                'search_word' => $word,
                'results_count' => count($integratedResults)
            ]);
            exit;
        } else {
            throw new Exception('現在はitem=descriptionのみサポートしています');
        }
    }

 
    $result = [];

    if (!$region && !$no) {
        throw new Exception('リージョンまたはポケモンNoを指定してください');
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

    // 無効なリージョンが指定された場合
    if ($region && !isset($validRegions[$region])) {
        throw new Exception('無効なリージョンが指定されました');
    }

    // description_map モード: ポケモンの図鑑説明をマップ形式で取得
    if ($mode === 'description_map') {
        if (!$region || !$no) {
            throw new Exception('region と no を指定してください');
        }

        if ($region !== 'global') {
            throw new Exception('description_map モードでは region=global のみサポートしています');
        }

        // ポケモンIDを取得（pokedex_dex_mapテーブルから）
        // noパラメータを4桁の文字列にフォーマット
        $globalNoStr = sprintf("%04d", intval($no));

        $dexMapData = $db->query(
            "SELECT id, verID FROM pokedex_dex_map WHERE globalNo = :globalNoStr",
            [':globalNoStr' => $globalNoStr]
        );

        if (empty($dexMapData)) {
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

        // pokedex_dex_mapの結果をポケモンIDごとにまとめる（CSVの行順を維持）
        $mapRowsById = [];
        foreach ($dexMapData as $mapRow) {
            $pokemonId = $mapRow['id'];
            $verIdRaw = isset($mapRow['verID']) ? trim($mapRow['verID']) : '';
            if (!isset($mapRowsById[$pokemonId])) {
                $mapRowsById[$pokemonId] = [];
            }
            if ($verIdRaw !== '') {
                $mapRowsById[$pokemonId][] = $verIdRaw;
            }
        }

        // 各ポケモンIDに対してpokedex_descriptionからデータを取得し、HTML生成スクリプトと同等の構造を整形
        $allDescriptions = [];
        foreach ($mapRowsById as $pokemonId => $verIdGroups) {
            $descriptionsData = $db->query(
                "SELECT verID, language, dex FROM pokedex_description WHERE id = :id ORDER BY verID ASC, language ASC",
                [':id' => $pokemonId]
            );

            $descriptionByVerId = [];
            foreach ($descriptionsData as $desc) {
                $versionIdKey = trim($desc['verID']);
                if ($versionIdKey === '') {
                    continue;
                }
                if (!isset($descriptionByVerId[$versionIdKey])) {
                    $descriptionByVerId[$versionIdKey] = [];
                }
                $descriptionByVerId[$versionIdKey][$desc['language']] = $desc['dex'];
            }

            $groupedDescriptions = [];

            foreach ($verIdGroups as $verGroupRaw) {
                $verGroupKey = trim($verGroupRaw);
                if ($verGroupKey === '') {
                    continue;
                }

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
                ];

                if (isset($descriptionByVerId[$verGroupKey]) && !empty($descriptionByVerId[$verGroupKey])) {
                    $groupEntry['common'] = $descriptionByVerId[$verGroupKey];
                } elseif ($representativeVerId && isset($descriptionByVerId[$representativeVerId])) {
                    $groupEntry['common'] = $descriptionByVerId[$representativeVerId];
                }

                foreach ($verIds as $singleVerId) {
                    if (!isset($descriptionByVerId[$singleVerId])) {
                        continue;
                    }

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

                    $groupEntry[$candidateKey] = $descriptionByVerId[$singleVerId];
                }

                $groupedDescriptions[$groupKey] = $groupEntry;
            }

            if (empty($groupedDescriptions) && !empty($descriptionByVerId)) {
                $groupedDescriptions = $descriptionByVerId;
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
            // 全国図鑑の場合は id または globalNo をそのまま返す
            $existsResult = ($id !== null) ? $id : $no;
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
            $query = "
                SELECT *
                FROM pokedex
                ORDER BY CAST(globalNo AS INTEGER) ASC
            ";
            $rows = $db->query($query);
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