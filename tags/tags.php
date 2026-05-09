<?php
/**
 * タグ提案API
 * 
 * GET:  タグ一覧取得
 *   - area: 地域 (必須。global の場合は全地域のタグを取得)
 *   - no: 図鑑番号 (必須)
 * 
 * POST: タグ提案登録
 *   - area: 地域 (必須)
 *   - no: 図鑑番号 (必須)
 *   - tag: タグ名 (必須)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONSリクエストの処理
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// データベースパス
$dbPath = __DIR__ . '/tags.db';

// データベース接続
function getDB($dbPath) {
    $isNewDb = !file_exists($dbPath);
    
    try {
        $db = new PDO('sqlite:' . $dbPath);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // 新規作成時はテーブルを初期化
        if ($isNewDb) {
            $initSql = file_get_contents(__DIR__ . '/init.sql');
            $db->exec($initSql);
        }
        
        // tag_votesテーブルが存在しない場合は作成
        $db->exec('
            CREATE TABLE IF NOT EXISTS tag_votes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tag_id INTEGER NOT NULL,
                ip_address TEXT NOT NULL,
                vote_type TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(tag_id, ip_address),
                FOREIGN KEY (tag_id) REFERENCES tag_suggestions(id) ON DELETE CASCADE
            )
        ');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_tag_votes_tag_id ON tag_votes(tag_id)');
        
        // tag_settingsテーブルが存在しない場合は作成
        $db->exec('
            CREATE TABLE IF NOT EXISTS tag_settings (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');
        
        return $db;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
        exit;
    }
}

// 設定値を取得
function getSetting($db, $key, $default = '') {
    $stmt = $db->prepare('SELECT value FROM tag_settings WHERE key = :key');
    $stmt->execute([':key' => $key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['value'] : $default;
}

// クライアントIPアドレスを取得
function getClientIP() {
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ips = explode(',', $_SERVER[$header]);
            return trim($ips[0]);
        }
    }
    return '0.0.0.0';
}

// レスポンス送信
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// エラーレスポンス送信
function sendError($message, $statusCode = 400) {
    sendResponse(['error' => $message], $statusCode);
}

function normalizeTagValue($value) {
    return mb_strtolower(trim((string)$value), 'UTF-8');
}

function filterCatalogTag($tag, $query) {
    $normalizedQuery = trim((string)$query);
    if ($normalizedQuery === '') {
        return true;
    }

    return mb_stripos((string)$tag, $normalizedQuery, 0, 'UTF-8') !== false;
}

function buildTagCatalog($db, $badThreshold, $query = '') {
    $relationMap = [];

    $approvedRows = $db->query('SELECT tag, area, pokedex_no FROM approved_tags ORDER BY tag, area, pokedex_no')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($approvedRows as $row) {
        if (!filterCatalogTag($row['tag'] ?? '', $query)) {
            continue;
        }

        $area = trim((string)($row['area'] ?? ''));
        $pokedexNo = intval($row['pokedex_no'] ?? 0);
        $tag = trim((string)($row['tag'] ?? ''));
        if ($area === '' || $pokedexNo <= 0 || $tag === '') {
            continue;
        }

        $key = normalizeTagValue($tag) . '::' . $area . '::' . $pokedexNo;
        $relationMap[$key] = [
            'tag' => $tag,
            'area' => $area,
            'pokedex_no' => $pokedexNo,
            'status' => 'approved',
            'good_count' => 0,
            'bad_count' => 0,
            'source' => 'approved'
        ];
    }

    $suggestionRows = $db->query('
        SELECT 
            ts.tag,
            ts.area,
            ts.pokedex_no,
            ts.status,
            COALESCE(SUM(CASE WHEN tv.vote_type = "good" THEN 1 ELSE 0 END), 0) as good_count,
            COALESCE(SUM(CASE WHEN tv.vote_type = "bad" THEN 1 ELSE 0 END), 0) as bad_count
        FROM tag_suggestions ts
        LEFT JOIN tag_votes tv ON ts.id = tv.tag_id
        WHERE ts.status != "rejected"
        GROUP BY ts.id
        ORDER BY ts.tag, ts.area, ts.pokedex_no
    ')->fetchAll(PDO::FETCH_ASSOC);

    foreach ($suggestionRows as $row) {
        if (!filterCatalogTag($row['tag'] ?? '', $query)) {
            continue;
        }

        $area = trim((string)($row['area'] ?? ''));
        $pokedexNo = intval($row['pokedex_no'] ?? 0);
        $tag = trim((string)($row['tag'] ?? ''));
        $badCount = intval($row['bad_count'] ?? 0);
        if ($area === '' || $pokedexNo <= 0 || $tag === '' || $badCount >= $badThreshold) {
            continue;
        }

        $key = normalizeTagValue($tag) . '::' . $area . '::' . $pokedexNo;
        if (isset($relationMap[$key])) {
            continue;
        }

        $relationMap[$key] = [
            'tag' => $tag,
            'area' => $area,
            'pokedex_no' => $pokedexNo,
            'status' => trim((string)($row['status'] ?? 'pending')),
            'good_count' => intval($row['good_count'] ?? 0),
            'bad_count' => $badCount,
            'source' => 'suggestion'
        ];
    }

    $groupMap = [];
    foreach ($relationMap as $relation) {
        $groupKey = normalizeTagValue($relation['tag']);
        if (!isset($groupMap[$groupKey])) {
            $groupMap[$groupKey] = [
                'tag' => $relation['tag'],
                'count' => 0,
                'items' => []
            ];
        }

        $groupMap[$groupKey]['items'][] = $relation;
        $groupMap[$groupKey]['count']++;
    }

    $groups = array_values($groupMap);
    foreach ($groups as &$group) {
        usort($group['items'], function ($left, $right) {
            $areaOrder = strcmp((string)($left['area'] ?? ''), (string)($right['area'] ?? ''));
            if ($areaOrder !== 0) {
                return $areaOrder;
            }

            $numberOrder = intval($left['pokedex_no'] ?? 0) <=> intval($right['pokedex_no'] ?? 0);
            if ($numberOrder !== 0) {
                return $numberOrder;
            }

            return strcmp((string)($left['source'] ?? ''), (string)($right['source'] ?? ''));
        });
    }
    unset($group);

    usort($groups, function ($left, $right) {
        $countOrder = intval($right['count'] ?? 0) <=> intval($left['count'] ?? 0);
        if ($countOrder !== 0) {
            return $countOrder;
        }

        return strcasecmp((string)($left['tag'] ?? ''), (string)($right['tag'] ?? ''));
    });

    return $groups;
}

// 地域一覧
$validAreas = ['global', 'kanto', 'johto', 'hoenn', 'kanto_frlg', 'sinnoh', 'johto_hgss', 'unova', 'unova_bw', 'unova_b2w2', 'kalos', 'central_kalos', 'coast_kalos', 'mountain_kalos', 'hoenn_oras', 'alola', 'alola_sm', 'alola_usum', 'galar', 'crown_tundra', 'isle_of_armor', 'hisui', 'paldea', 'kitakami', 'blueberry', 'lumiose'];

// GETリクエスト処理: タグ一覧取得
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = trim((string)($_GET['mode'] ?? ''));
    if ($mode === 'catalog') {
        $db = getDB($dbPath);

        try {
            $badThreshold = (int)getSetting($db, 'bad_threshold', '3');
            $groups = buildTagCatalog($db, $badThreshold, $_GET['q'] ?? '');
            $relationCount = 0;
            foreach ($groups as $group) {
                $relationCount += count($group['items'] ?? []);
            }

            sendResponse([
                'success' => true,
                'mode' => 'catalog',
                'tags' => $groups,
                'results_count' => count($groups),
                'relation_count' => $relationCount,
                'settings' => [
                    'bad_threshold' => $badThreshold
                ]
            ]);
        } catch (PDOException $e) {
            sendError('Database error: ' . $e->getMessage(), 500);
        }
    }

    $area = $_GET['area'] ?? null;
    $no = $_GET['no'] ?? null;
    
    if (!$area || !$no) {
        sendError('area and no are required');
    }
    
    $no = intval($no);
    $clientIP = getClientIP();
    
    $db = getDB($dbPath);
    
    try {
        if ($area === 'global') {
            // globalの場合は全地域のタグを取得（評価集計付き、却下タグは除外）
            $stmt = $db->prepare('
                SELECT 
                    ts.id,
                    ts.tag, 
                    ts.area, 
                    ts.pokedex_no,
                    ts.status,
                    COALESCE(SUM(CASE WHEN tv.vote_type = "good" THEN 1 ELSE 0 END), 0) as good_count,
                    COALESCE(SUM(CASE WHEN tv.vote_type = "bad" THEN 1 ELSE 0 END), 0) as bad_count,
                    (SELECT vote_type FROM tag_votes WHERE tag_id = ts.id AND ip_address = :ip) as user_vote
                FROM tag_suggestions ts
                LEFT JOIN tag_votes tv ON ts.id = tv.tag_id
                WHERE ts.pokedex_no = :no AND ts.status != "rejected"
                GROUP BY ts.id
                ORDER BY ts.tag
            ');
            $stmt->execute([':no' => $no, ':ip' => $clientIP]);
        } else {
            // 特定地域のタグを取得（評価集計付き、却下タグは除外）
            $stmt = $db->prepare('
                SELECT 
                    ts.id,
                    ts.tag, 
                    ts.area, 
                    ts.pokedex_no,
                    ts.status,
                    COALESCE(SUM(CASE WHEN tv.vote_type = "good" THEN 1 ELSE 0 END), 0) as good_count,
                    COALESCE(SUM(CASE WHEN tv.vote_type = "bad" THEN 1 ELSE 0 END), 0) as bad_count,
                    (SELECT vote_type FROM tag_votes WHERE tag_id = ts.id AND ip_address = :ip) as user_vote
                FROM tag_suggestions ts
                LEFT JOIN tag_votes tv ON ts.id = tv.tag_id
                WHERE ts.area = :area AND ts.pokedex_no = :no AND ts.status != "rejected"
                GROUP BY ts.id
                ORDER BY ts.tag
            ');
            $stmt->execute([':area' => $area, ':no' => $no, ':ip' => $clientIP]);
        }
        
        $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 数値型に変換
        foreach ($tags as &$tag) {
            $tag['id'] = intval($tag['id']);
            $tag['pokedex_no'] = intval($tag['pokedex_no']);
            $tag['good_count'] = intval($tag['good_count']);
            $tag['bad_count'] = intval($tag['bad_count']);
        }
        
        // 承認済みタグも取得
        if ($area === 'global') {
            $stmtApproved = $db->prepare('
                SELECT DISTINCT tag, area, pokedex_no 
                FROM approved_tags 
                WHERE pokedex_no = :no 
                ORDER BY tag
            ');
            $stmtApproved->execute([':no' => $no]);
        } else {
            $stmtApproved = $db->prepare('
                SELECT DISTINCT tag, area, pokedex_no 
                FROM approved_tags 
                WHERE area = :area AND pokedex_no = :no 
                ORDER BY tag
            ');
            $stmtApproved->execute([':area' => $area, ':no' => $no]);
        }
        
        $approvedTags = $stmtApproved->fetchAll(PDO::FETCH_ASSOC);
        
        // 設定値を取得
        $badThreshold = (int)getSetting($db, 'bad_threshold', '3');
        
        sendResponse([
            'success' => true,
            'area' => $area,
            'no' => $no,
            'suggestions' => $tags,
            'approved' => $approvedTags,
            'settings' => [
                'bad_threshold' => $badThreshold
            ]
        ]);
        
    } catch (PDOException $e) {
        sendError('Database error: ' . $e->getMessage(), 500);
    }
}

// POSTリクエスト処理: タグ提案登録
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // JSONボディを取得
    $input = json_decode(file_get_contents('php://input'), true);
    
    $area = $input['area'] ?? null;
    $no = $input['no'] ?? null;
    $tag = $input['tag'] ?? null;
    
    // バリデーション
    if (!$area || !$no || !$tag) {
        sendError('area, no, and tag are required');
    }
    
    if (!in_array($area, $validAreas)) {
        sendError('Invalid area: ' . $area);
    }
    
    $no = intval($no);
    $tag = trim($tag);
    
    if (empty($tag)) {
        sendError('Tag cannot be empty');
    }
    
    if (mb_strlen($tag) > 50) {
        sendError('Tag is too long (max 50 characters)');
    }
    
    $db = getDB($dbPath);
    
    try {
        // 重複チェック（提案済み + 承認済み）
        $stmtCheck = $db->prepare('
            SELECT COUNT(*) as cnt FROM (
                SELECT tag FROM tag_suggestions WHERE area = :area AND pokedex_no = :no AND tag = :tag
                UNION
                SELECT tag FROM approved_tags WHERE area = :area AND pokedex_no = :no AND tag = :tag
            )
        ');
        $stmtCheck->execute([':area' => $area, ':no' => $no, ':tag' => $tag]);
        $result = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        if ($result['cnt'] > 0) {
            sendError('This tag already exists');
        }
        
        // 登録
        $stmt = $db->prepare('
            INSERT INTO tag_suggestions (area, pokedex_no, tag) 
            VALUES (:area, :no, :tag)
        ');
        $stmt->execute([':area' => $area, ':no' => $no, ':tag' => $tag]);
        
        sendResponse([
            'success' => true,
            'message' => 'Tag suggestion submitted successfully',
            'tag' => $tag,
            'area' => $area,
            'no' => $no
        ], 201);
        
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) {
            sendError('This tag already exists');
        }
        sendError('Database error: ' . $e->getMessage(), 500);
    }
}

// PUTリクエスト処理: タグ評価登録
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // JSONボディを取得
    $input = json_decode(file_get_contents('php://input'), true);
    
    $tagId = $input['tag_id'] ?? null;
    $voteType = $input['vote_type'] ?? null;
    
    // バリデーション
    if (!$tagId) {
        sendError('tag_id is required');
    }
    
    if (!in_array($voteType, ['good', 'bad'])) {
        sendError('vote_type must be "good" or "bad"');
    }
    
    $tagId = intval($tagId);
    $clientIP = getClientIP();
    
    $db = getDB($dbPath);
    
    try {
        // タグが存在するか確認
        $stmtCheck = $db->prepare('SELECT id FROM tag_suggestions WHERE id = :id');
        $stmtCheck->execute([':id' => $tagId]);
        if (!$stmtCheck->fetch()) {
            sendError('Tag not found', 404);
        }
        
        // 既存の投票を確認
        $stmtExisting = $db->prepare('
            SELECT id, vote_type FROM tag_votes 
            WHERE tag_id = :tag_id AND ip_address = :ip
        ');
        $stmtExisting->execute([':tag_id' => $tagId, ':ip' => $clientIP]);
        $existingVote = $stmtExisting->fetch(PDO::FETCH_ASSOC);
        
        if ($existingVote) {
            if ($existingVote['vote_type'] === $voteType) {
                // 同じ評価を再度クリック → 取り消し
                $stmtDelete = $db->prepare('DELETE FROM tag_votes WHERE id = :id');
                $stmtDelete->execute([':id' => $existingVote['id']]);
                
                sendResponse([
                    'success' => true,
                    'action' => 'removed',
                    'message' => 'Vote removed',
                    'tag_id' => $tagId
                ]);
            } else {
                // 異なる評価 → 更新
                $stmtUpdate = $db->prepare('
                    UPDATE tag_votes SET vote_type = :vote_type, created_at = CURRENT_TIMESTAMP 
                    WHERE id = :id
                ');
                $stmtUpdate->execute([':vote_type' => $voteType, ':id' => $existingVote['id']]);
                
                sendResponse([
                    'success' => true,
                    'action' => 'updated',
                    'message' => 'Vote updated',
                    'tag_id' => $tagId,
                    'vote_type' => $voteType
                ]);
            }
        } else {
            // 新規投票
            $stmt = $db->prepare('
                INSERT INTO tag_votes (tag_id, ip_address, vote_type) 
                VALUES (:tag_id, :ip, :vote_type)
            ');
            $stmt->execute([':tag_id' => $tagId, ':ip' => $clientIP, ':vote_type' => $voteType]);
            
            sendResponse([
                'success' => true,
                'action' => 'created',
                'message' => 'Vote registered',
                'tag_id' => $tagId,
                'vote_type' => $voteType
            ], 201);
        }
        
    } catch (PDOException $e) {
        sendError('Database error: ' . $e->getMessage(), 500);
    }
}

// その他のメソッド
sendError('Method not allowed', 405);
