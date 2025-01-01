<?php
header('Content-Type: application/json; charset=utf-8');

class PokedexAPI {
    private $db;
    private $response;

    public function __construct() {
        $this->db = new SQLite3('pokedex.db');
        $this->response = [
            'status' => 'success',
            'data' => null,
            'message' => ''
        ];
    }

    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $params = $_GET;

        // デバッグ情報をレスポンスに追加
        $this->response['debug'] = [
            'original_path' => $path,
            'request_uri' => $_SERVER['REQUEST_URI']
        ];

        try {
            switch ($path) {
                case '/pokedex/global':
                    $this->handleGlobalPokedex($params);
                    break;
                case '/pokedex/local':
                    $this->handleLocalPokedex($params);
                    break;
                case '/pokedex/search':
                    $this->handleSearch($params);
                    break;
                default:
                    throw new Exception("Invalid endpoint: {$path}");
            }
        } catch (Exception $e) {
            $this->response['status'] = 'error';
            $this->response['message'] = $e->getMessage();
        }

        echo json_encode($this->response, JSON_UNESCAPED_UNICODE);
    }

    private function handleGlobalPokedex($params) {
        $id = $params['id'] ?? null;
        $query = '';

        if ($id) {
            $query = "SELECT * FROM pokedex WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        } else {
            $query = "SELECT * FROM pokedex";
            $stmt = $this->db->prepare($query);
        }

        $result = $stmt->execute();
        $data = [];

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $data[] = $row;
        }

        $this->response['data'] = $data;
    }

    private function handleLocalPokedex($params) {
        $region = $params['region'] ?? null;
        $id = $params['id'] ?? null;

        if (!$region) {
            throw new Exception('Region parameter is required');
        }

        $query = '';
        if ($id) {
            $query = "SELECT * FROM $region WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        } else {
            $query = "SELECT * FROM $region";
            $stmt = $this->db->prepare($query);
        }

        $result = $stmt->execute();
        $data = [];

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $data[] = $row;
        }

        $this->response['data'] = $data;
    }

    private function handleSearch($params) {
        $keyword = $params['keyword'] ?? null;
        $region = $params['region'] ?? 'pokedex';

        if (!$keyword) {
            throw new Exception('Keyword parameter is required');
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
    }
}

$api = new PokedexAPI();
$api->handleRequest();
?>