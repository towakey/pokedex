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
            'message' => ''
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
        $id = isset($_GET['id']) ? $_GET['id'] : null;

        if ($region === 'global') {
            $this->handleGlobalPokedex(['id' => $id]);
            return;
        }

        if (!$region || !array_key_exists($region, $this->validRegions)) {
            $this->response['error'] = 'Invalid region';
            $this->response['valid_regions'] = array_keys($this->validRegions);
            return;
        }

        $this->handleLocalPokedex(['region' => $region, 'id' => $id]);
    }

    private function handleLocalPokedex($params) {
        $region = $params['region'];
        $id = $params['id'] ?? null;
        $form = $params['form'] ?? '';

        if (!isset($this->validRegions[$region])) {
            throw new Exception("Invalid region: {$region}");
        }

        $query = '';
        if ($id) {
            $query = "WITH moves AS (
                        SELECT 
                            w.learn_type,
                            json_group_array(
                                json_object(
                                    'level', w.level,
                                    'waza_name', w.waza_name
                                )
                            ) as moves_by_type
                        FROM waza w 
                        WHERE w.region = '$region'
                            AND w.global_no = :global_no
                            AND (
                                CASE 
                                    WHEN :is_mega = 1 THEN w.form = :form
                                    ELSE w.form = :form OR w.form = ''
                                END
                            )
                        GROUP BY w.learn_type
                    )
                    SELECT l.*, 
                           p.jpn, p.eng, p.ger, p.fra, p.kor, p.chs, p.cht, p.classification, p.height, p.weight,
                           json_object(
                               'initial', (SELECT moves_by_type FROM moves WHERE learn_type = 'initial'),
                               'remember', (SELECT moves_by_type FROM moves WHERE learn_type = 'remember'),
                               'evolution', (SELECT moves_by_type FROM moves WHERE learn_type = 'evolution'),
                               'level', (SELECT moves_by_type FROM moves WHERE learn_type = 'level'),
                               'machine', (SELECT moves_by_type FROM moves WHERE learn_type = 'machine')
                           ) as waza_list
                    FROM $region l
                    LEFT JOIN pokedex p ON l.globalNo = p.id
                    WHERE l.id = :id
                    AND (
                        CASE 
                            WHEN substr(l.form, 1, 2) = 'メガ' THEN l.form = p.jpn
                            ELSE l.form = p.form
                        END
                    )
                    GROUP BY l.id, l.globalNo, l.form
                    ORDER BY CAST(l.id AS INTEGER)";

            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':id', $id, SQLITE3_TEXT);
            $stmt->bindValue(':global_no', $id, SQLITE3_TEXT);
            $stmt->bindValue(':form', $form, SQLITE3_TEXT);
            $stmt->bindValue(':is_mega', substr($form, 0, 2) === 'メガ' ? 1 : 0, SQLITE3_INTEGER);
        } else {
            $query = "WITH moves AS (
                        SELECT 
                            w.learn_type,
                            json_group_array(
                                json_object(
                                    'level', w.level,
                                    'waza_name', w.waza_name
                                )
                            ) as moves_by_type
                        FROM waza w 
                        WHERE w.region = '$region'
                            AND w.global_no = l.globalNo
                            AND (
                                CASE 
                                    WHEN substr(l.form, 1, 2) = 'メガ' THEN w.form = l.form
                                    ELSE w.form = l.form OR w.form = ''
                                END
                            )
                        GROUP BY w.learn_type
                    )
                    SELECT l.*, 
                           p.jpn, p.eng, p.ger, p.fra, p.kor, p.chs, p.cht, p.classification, p.height, p.weight,
                           json_object(
                               'initial', (SELECT moves_by_type FROM moves WHERE learn_type = 'initial'),
                               'remember', (SELECT moves_by_type FROM moves WHERE learn_type = 'remember'),
                               'evolution', (SELECT moves_by_type FROM moves WHERE learn_type = 'evolution'),
                               'level', (SELECT moves_by_type FROM moves WHERE learn_type = 'level'),
                               'machine', (SELECT moves_by_type FROM moves WHERE learn_type = 'machine')
                           ) as waza_list
                    FROM $region l
                    LEFT JOIN pokedex p ON l.globalNo = p.id
                    AND (
                        CASE 
                            WHEN substr(l.form, 1, 2) = 'メガ' THEN l.form = p.jpn
                            ELSE l.form = p.form
                        END
                    )
                    GROUP BY l.id, l.globalNo, l.form
                    ORDER BY CAST(l.id AS INTEGER)";
            $stmt = $this->db->prepare($query);
        }

        $result = $stmt->execute();
        $data = [];

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            // waza_listの各learn_typeのJSONをデコード
            if (isset($row['waza_list'])) {
                $waza_list = json_decode($row['waza_list'], true);
                foreach ($waza_list as $type => $moves) {
                    if ($moves !== null) {
                        $waza_list[$type] = json_decode($moves, true);
                    }
                }
                $row['waza_list'] = $waza_list;
            }
            $data[] = $row;
        }

        $this->response['data'] = $data;
        $this->response['region_name'] = $this->validRegions[$region];
    }

    private function handleGlobalPokedex($params) {
        $id = $params['id'] ?? null;
        $query = '';

        if ($id) {
            $query = "SELECT * FROM pokedex WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        } else {
            $query = "SELECT * FROM pokedex ORDER BY CAST(id AS INTEGER)";
            $stmt = $this->db->prepare($query);
        }

        $result = $stmt->execute();
        $data = [];

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $data[] = $row;
        }

        $this->response['data'] = $data;
        $this->response['status'] = 'success';
    }

    private function handleSearch($params) {
        $keyword = $params['keyword'] ?? null;
        $region = $params['region'] ?? 'pokedex';

        if (!$keyword) {
            throw new Exception('Keyword parameter is required');
        }

        if ($region !== 'pokedex' && !isset($this->validRegions[$region])) {
            throw new Exception('Invalid region specified');
        }

        $query = "SELECT * FROM $region WHERE 
                  id LIKE :keyword OR 
                  form LIKE :keyword OR 
                  type1 LIKE :keyword OR 
                  type2 LIKE :keyword OR 
                  ability1 LIKE :keyword OR 
                  ability2 LIKE :keyword OR 
                  dream_ability LIKE :keyword";

        $stmt = $this->db->prepare($query);
        $keyword = "%$keyword%";
        $stmt->bindValue(':keyword', $keyword, SQLITE3_TEXT);

        $result = $stmt->execute();
        $data = [];

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $data[] = $row;
        }

        $this->response['data'] = $data;
        if ($region !== 'pokedex') {
            $this->response['region_name'] = $this->validRegions[$region];
        }
    }

    public function getResponse() {
        return $this->response;
    }
}

$api = new PokedexAPI();
$api->handleRequest();
echo json_encode($api->getResponse(), JSON_UNESCAPED_UNICODE);