<?php
header('Content-Type: application/json; charset=utf-8');

class PokedexAPI {
    private $db;
    private $response;
    private $validRegions;

    public function __construct() {
        $this->db = new SQLite3('pokedex.db');
        $this->response = [
            'status' => 'success',
            'data' => null,
            'message' => 'This API is the Pokédex API.'
        ];
        
        // 有効な地方図鑑のリスト
        $this->validRegions = [
            'kanto' => 'カントー図鑑',
            'johto' => 'ジョウト図鑑',
            'hoenn' => 'ホウエン図鑑',
            'sinnoh' => 'シンオウ図鑑',
            'unova_bw' => 'イッシュ図鑑',
            'unova_b2w2' => 'イッシュ図鑑',
            'central_kalos' => 'セントラルカロス図鑑',
            'coast_kalos' => 'コーストカロス図鑑',
            'mountain_kalos' => 'マウンテンカロス図鑑',
            'alola_sm' => 'アローラ図鑑',
            'alola_usum' => 'アローラ図鑑',
            'galar' => 'ガラル図鑑',
            'crown_tundra' => 'カンムリ雪原図鑑',
            'isle_of_armor' => 'ヨロイ島図鑑',
            'hisui' => 'ヒスイ図鑑',
            'paldea' => 'パルデア図鑑',
            'kitakami' => 'キタカミ図鑑',
            'blueberry' => 'ブルーベリー図鑑'
        ];
    }

    public function handleRequest() {
        $region = isset($_GET['region']) ? $_GET['region'] : null;
        $no = isset($_GET['no']) ? $_GET['no'] : null;
        $mode = isset($_GET['mode']) ? $_GET['mode'] : 'index'; // デフォルト値をindexに設定

        // modeの値を検証
        if ($mode !== 'index' && $mode !== 'details') {
            $this->response['status'] = 'error';
            $this->response['error'] = 'Invalid mode parameter. Valid modes are: index, details';
            return;
        }

        if (!$region) {
            $this->response['error'] = 'Region parameter is required';
            $this->response['valid_regions'] = array_merge(['global'], array_keys($this->validRegions));
            return;
        }

        if ($region === 'global') {
            $this->handleGlobalPokedex(['no' => $no, 'mode' => $mode]);
            return;
        }

        if (!array_key_exists($region, $this->validRegions)) {
            $this->response['error'] = 'Invalid region';
            $this->response['valid_regions'] = array_merge(['global'], array_keys($this->validRegions));
            return;
        }

        $this->handleLocalPokedex(['region' => $region, 'no' => $no, 'mode' => $mode]);
    }

    private function handleLocalPokedex($params) {
        $region = $params['region'];
        $no = $params['no'] ?? null;
        $mode = $params['mode'] ?? 'index';

        // モードのバリデーションチェック（念のため）
        if ($mode !== 'index' && $mode !== 'details') {
            $this->response['status'] = 'error';
            $this->response['error'] = 'Invalid mode parameter. Valid modes are: index, details';
            return;
        }

        if (!isset($this->validRegions[$region])) {
            throw new Exception("Invalid region: {$region}");
        }

        // modeによって処理を分岐
        if ($mode === 'details') {
            // 詳細情報を返す場合はno必須
            if (!$no) {
                $this->response['error'] = 'No parameter is required for detail mode';
                return;
            }
            $this->getDetailedPokemonInfo($region, $no);
        } else {
            // indexモードの場合はリスト表示
            $this->getPokemonList($region, $no);
        }

        $this->response['region_name'] = $this->validRegions[$region];
    }

    // リスト表示用のメソッド
    private function getPokemonList($region, $no = null) {
        // リスト表示では必要最小限の情報のみ取得
        $query = "SELECT l.no, l.globalNo, 
                 p.jpn, p.eng,
                 l.type1, l.type2
                  FROM $region l
                  LEFT JOIN pokedex p ON l.globalNo = p.no";

        if ($no) {
            $query .= " WHERE l.no = :no";
        }
        
        $query .= " ORDER BY CAST(l.no AS INTEGER)";
        
        $stmt = $this->db->prepare($query);
        if ($no) {
            $stmt->bindValue(':no', $no, SQLITE3_TEXT);
        }

        $result = $stmt->execute();
        $data = [];

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            // リスト表示では技情報は含めない
            $data[] = $row;
        }

        $this->response['data'] = $data;
    }

    // 詳細情報表示用のメソッド
    private function getDetailedPokemonInfo($region, $no) {
        // 詳細情報を取得
        $query = "SELECT l.*, 
                 p.jpn, p.eng, p.ger, p.fra, p.kor, p.chs, p.cht,
                 p.classification, p.height, p.weight
                 FROM $region l
                 LEFT JOIN pokedex p ON l.globalNo = p.no
                 WHERE l.no = :no";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':no', $no, SQLITE3_TEXT);

        $result = $stmt->execute();
        $data = [];

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            // 技情報を取得
            $globalNo = $row['globalNo'];
            $form = $row['form'];
            
            $waza_query = "SELECT learn_type, level, waza_name 
                          FROM waza 
                          WHERE region = :region 
                          AND no = :no
                          AND global_no = :global_no
                          AND (
                              CASE 
                                  WHEN substr(:form, 1, 2) = 'メガ' THEN form = :form
                                  ELSE form = :form OR form = ''
                              END
                          )";
            
            $waza_stmt = $this->db->prepare($waza_query);
            $waza_stmt->bindValue(':region', $region, SQLITE3_TEXT);
            $waza_stmt->bindValue(':no', $no, SQLITE3_TEXT);
            $waza_stmt->bindValue(':global_no', $globalNo, SQLITE3_TEXT);
            $waza_stmt->bindValue(':form', $form, SQLITE3_TEXT);
            
            $waza_result = $waza_stmt->execute();
            
            // 技データ整理用の配列
            $initial_moves = [];
            $remember_moves = [];
            $evolution_moves = [];
            $level_moves = [];  // レベル技は一時的にここに格納
            $machine_moves = [];
            
            while ($waza_row = $waza_result->fetchArray(SQLITE3_ASSOC)) {
                $learn_type = $waza_row['learn_type'];
                
                // 学習タイプに応じて適切な配列に格納
                switch ($learn_type) {
                    case 'initial':
                        $initial_moves[] = $waza_row['waza_name'];
                        break;
                    case 'remember':
                        $remember_moves[] = $waza_row['waza_name'];
                        break;
                    case 'evolution':
                        $evolution_moves[] = $waza_row['waza_name'];
                        break;
                    case 'level':
                        // レベル技は [レベル, 技名] の形式で一時保存
                        $level_moves[] = [$waza_row['level'], $waza_row['waza_name']];
                        break;
                    case 'machine':
                        $machine_moves[] = $waza_row['waza_name'];
                        break;
                }
            }
            
            // レベル技をレベル順にソート
            usort($level_moves, function($a, $b) {
                return $a[0] - $b[0];
            });
            
            // レベル技をフォーマット
            $formatted_level_moves = [];
            foreach ($level_moves as $move) {
                $level = $move[0];
                $name = $move[1];
                $formatted_level_moves[] = [
                    'level' => (int)$level,
                    'name' => $name
                ];
            }
            
            // 最終的な技リスト形式
            $waza_list = [
                '' => $initial_moves,
                '思い出し' => $remember_moves,
                '進化時' => $evolution_moves,
                'lvup' => $formatted_level_moves,
                'わざマシン' => $machine_moves
            ];
            
            $row['waza_list'] = $waza_list;
            $data[] = $row;
        }

        $this->response['data'] = $data;
    }

    private function handleGlobalPokedex($params) {
        $no = $params['no'] ?? null;
        $mode = $params['mode'] ?? 'index';

        // モードのバリデーションチェック（念のため）
        if ($mode !== 'index' && $mode !== 'details') {
            $this->response['status'] = 'error';
            $this->response['error'] = 'Invalid mode parameter. Valid modes are: index, details';
            return;
        }

        // 詳細モードの場合
        if ($mode === 'details') {
            if (!$no) {
                $this->response['error'] = 'No parameter is required for detail mode';
                return;
            }

            // グローバル図鑑の詳細情報を取得
            $query = "SELECT * FROM pokedex WHERE no = :no";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':no', $no, SQLITE3_TEXT);
            
            $result = $stmt->execute();
            $data = [];

            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $data[] = $row;
            }
            
            $this->response['data'] = $data;
        } else {
            // インデックスモードの場合、リスト表示
            if ($no) {
                // noが指定されている場合は特定のポケモンのみ表示
                $query = "SELECT no, jpn, eng FROM pokedex WHERE no = :no";
                $stmt = $this->db->prepare($query);
                $stmt->bindValue(':no', $no, SQLITE3_TEXT);
            } else {
                // 全ポケモンリスト表示
                $query = "SELECT no, jpn, eng FROM pokedex ORDER BY CAST(no AS INTEGER)";
                $stmt = $this->db->prepare($query);
            }
            
            $result = $stmt->execute();
            $data = [];

            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $data[] = $row;
            }
            
            $this->response['data'] = $data;
        }

        $this->response['status'] = 'success';
    }

    public function getResponse() {
        return $this->response;
    }
}

$api = new PokedexAPI();
$api->handleRequest();

$response = $api->getResponse();
echo json_encode($response, JSON_UNESCAPED_UNICODE);