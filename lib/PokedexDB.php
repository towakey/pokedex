<?php
/**
 * キャッシュ取得
 *
 * @param Database $db
 * @param string $cacheKey
 * @param int|null $ttlSeconds
 * @return string|null
 */
function fetchCache($db, $cacheKey, $ttlSeconds = null) {
    $row = $db->querySingle(
        "SELECT payload_json, updated_at FROM pokedex_cache WHERE cache_key = :cache_key",
        [':cache_key' => $cacheKey]
    );

    if (!$row) {
        return null;
    }

    if ($ttlSeconds !== null) {
        $updatedAt = strtotime($row['updated_at'] ?? '');
        if (!$updatedAt || (time() - $updatedAt) > $ttlSeconds) {
            return null;
        }
    }

    return $row['payload_json'] ?? null;
}

/**
 * キャッシュ保存
 *
 * @param Database $db
 * @param string $cacheKey
 * @param string $payloadJson
 * @return void
 */
function saveCache($db, $cacheKey, $payloadJson) {
    $db->execute(
        "INSERT OR REPLACE INTO pokedex_cache (cache_key, payload_json, updated_at) VALUES (:cache_key, :payload_json, :updated_at)",
        [
            ':cache_key' => $cacheKey,
            ':payload_json' => $payloadJson,
            ':updated_at' => date('c')
        ]
    );
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

/**
 * PokedexDB ラッパークラス（低レベルAPI）
 */
class PokedexDB {
    private $db;

    /**
     * @param string $dbPath
     */
    public function __construct($dbPath = 'pokedex.db') {
        $this->db = new Database($dbPath);
    }

    /**
     * @return Database
     */
    public function getDatabase() {
        return $this->db;
    }

    public function query($query, $params = []) {
        return $this->db->query($query, $params);
    }

    public function querySingle($query, $params = []) {
        return $this->db->querySingle($query, $params);
    }

    public function execute($query, $params = []) {
        return $this->db->execute($query, $params);
    }

    public function lastInsertId() {
        return $this->db->lastInsertId();
    }

    public function getError() {
        return $this->db->getError();
    }

    public function fetchCache($cacheKey, $ttlSeconds = null) {
        return fetchCache($this->db, $cacheKey, $ttlSeconds);
    }

    public function saveCache($cacheKey, $payloadJson) {
        return saveCache($this->db, $cacheKey, $payloadJson);
    }
}

?>
