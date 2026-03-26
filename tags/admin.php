<?php
/**
 * タグ管理ページ - 管理画面
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

// ログインチェック
if (!isLoggedIn()) {
    header('Location: ' . SITE_TOP_URL);
    exit;
}

// ログアウト処理
if (isset($_GET['logout'])) {
    logout();
    header('Location: index.php');
    exit;
}

// 設定テーブルの初期化とヘルパー関数
function initSettingsTable(PDO $pdo): void {
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS tag_settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ');
}

function getSetting(PDO $pdo, string $key, string $default = ''): string {
    $stmt = $pdo->prepare('SELECT value FROM tag_settings WHERE key = :key');
    $stmt->execute([':key' => $key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['value'] : $default;
}

function setSetting(PDO $pdo, string $key, string $value): void {
    $stmt = $pdo->prepare('
        INSERT OR REPLACE INTO tag_settings (key, value, updated_at) 
        VALUES (:key, :value, CURRENT_TIMESTAMP)
    ');
    $stmt->execute([':key' => $key, ':value' => $value]);
}

// JSONエクスポート処理
if (isset($_GET['export']) && $_GET['export'] === 'json') {
    try {
        $pdo = new PDO('sqlite:' . TAGS_DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        // すべてのタグを取得（承認済みのみ、または全て）+ 投票数
        $exportApprovedOnly = isset($_GET['approved_only']) && $_GET['approved_only'] === '1';
        
        $whereClause = $exportApprovedOnly ? 'WHERE ts.status = "approved"' : '';
        
        $sql = "SELECT 
                    ts.id, ts.area, ts.pokedex_no, ts.tag, ts.status,
                    COALESCE(SUM(CASE WHEN tv.vote_type = 'good' THEN 1 ELSE 0 END), 0) as good_count,
                    COALESCE(SUM(CASE WHEN tv.vote_type = 'bad' THEN 1 ELSE 0 END), 0) as bad_count
                FROM tag_suggestions ts
                LEFT JOIN tag_votes tv ON ts.id = tv.tag_id
                $whereClause
                GROUP BY ts.id
                ORDER BY ts.pokedex_no, ts.area, ts.tag";
        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll();
        
        // tags.jsonの形式に変換（投票数付き）
        $tagsData = [];
        foreach ($rows as $row) {
            $pokedexNo = str_pad((string)$row['pokedex_no'], 4, '0', STR_PAD_LEFT);
            $area = $row['area'];
            
            if (!isset($tagsData[$pokedexNo])) {
                $tagsData[$pokedexNo] = [];
            }
            if (!isset($tagsData[$pokedexNo][$area])) {
                $tagsData[$pokedexNo][$area] = [];
            }
            
            // タグ情報（投票数付き）
            $tagsData[$pokedexNo][$area][] = [
                'name' => $row['tag'],
                'status' => $row['status'],
                'good' => (int)$row['good_count'],
                'bad' => (int)$row['bad_count']
            ];
        }
        
        $output = [
            'update' => date('Ymd'),
            'tags' => $tagsData
        ];
        
        // JSONファイルとしてダウンロード
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="tags.json"');
        echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
        
    } catch (PDOException $e) {
        $error = 'エクスポートエラー: ' . $e->getMessage();
    }
}

// ステータス更新処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $id = (int)($_POST['id'] ?? 0);
    
    // 設定更新
    if ($action === 'update_settings') {
        try {
            $pdo = new PDO('sqlite:' . TAGS_DB_PATH);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            initSettingsTable($pdo);
            
            $badThreshold = max(0, (int)($_POST['bad_threshold'] ?? 3));
            setSetting($pdo, 'bad_threshold', (string)$badThreshold);
            
            header('Location: admin.php?settings_saved=1');
            exit;
        } catch (PDOException $e) {
            $error = 'データベースエラー: ' . $e->getMessage();
        }
    }
    
    // JSONインポート処理
    if ($action === 'import_json') {
        try {
            if (!isset($_FILES['json_file']) || $_FILES['json_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('ファイルのアップロードに失敗しました');
            }
            
            $jsonContent = file_get_contents($_FILES['json_file']['tmp_name']);
            $data = json_decode($jsonContent, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('JSONの解析に失敗しました: ' . json_last_error_msg());
            }
            
            if (!isset($data['tags']) || !is_array($data['tags'])) {
                throw new Exception('無効なJSON形式です（tagsが見つかりません）');
            }
            
            $pdo = new PDO('sqlite:' . TAGS_DB_PATH);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $importMode = $_POST['import_mode'] ?? 'merge';
            $imported = 0;
            $skipped = 0;
            
            $pdo->beginTransaction();
            
            try {
                // 上書きモードの場合は既存データを削除
                if ($importMode === 'replace') {
                    $pdo->exec('DELETE FROM tag_suggestions');
                    $pdo->exec('DELETE FROM tag_votes');
                }
                
                foreach ($data['tags'] as $pokedexNo => $areas) {
                    $no = (int)ltrim($pokedexNo, '0');
                    if ($no <= 0) continue;
                    
                    foreach ($areas as $area => $tags) {
                        foreach ($tags as $tagData) {
                            // 新形式（オブジェクト）と旧形式（文字列）の両方に対応
                            if (is_array($tagData)) {
                                $tagName = $tagData['name'] ?? '';
                                $status = $tagData['status'] ?? 'approved';
                                $goodCount = (int)($tagData['good'] ?? 0);
                                $badCount = (int)($tagData['bad'] ?? 0);
                            } else {
                                $tagName = $tagData;
                                $status = 'approved';
                                $goodCount = 0;
                                $badCount = 0;
                            }
                            
                            if (empty($tagName)) continue;
                            
                            // 重複チェック
                            $checkStmt = $pdo->prepare('SELECT id FROM tag_suggestions WHERE area = :area AND pokedex_no = :no AND tag = :tag');
                            $checkStmt->execute([':area' => $area, ':no' => $no, ':tag' => $tagName]);
                            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($existing) {
                                $skipped++;
                                continue;
                            }
                            
                            // タグを挿入
                            $insertStmt = $pdo->prepare('INSERT INTO tag_suggestions (area, pokedex_no, tag, status) VALUES (:area, :no, :tag, :status)');
                            $insertStmt->execute([':area' => $area, ':no' => $no, ':tag' => $tagName, ':status' => $status]);
                            $tagId = $pdo->lastInsertId();
                            
                            // 投票数を再現（仮想投票として登録）
                            if ($goodCount > 0 || $badCount > 0) {
                                for ($i = 0; $i < $goodCount; $i++) {
                                    $voteStmt = $pdo->prepare('INSERT OR IGNORE INTO tag_votes (tag_id, ip_address, vote_type) VALUES (:tag_id, :ip, "good")');
                                    $voteStmt->execute([':tag_id' => $tagId, ':ip' => 'import_' . $tagId . '_good_' . $i]);
                                }
                                for ($i = 0; $i < $badCount; $i++) {
                                    $voteStmt = $pdo->prepare('INSERT OR IGNORE INTO tag_votes (tag_id, ip_address, vote_type) VALUES (:tag_id, :ip, "bad")');
                                    $voteStmt->execute([':tag_id' => $tagId, ':ip' => 'import_' . $tagId . '_bad_' . $i]);
                                }
                            }
                            
                            $imported++;
                        }
                    }
                }
                
                $pdo->commit();
                header('Location: admin.php?import_success=1&imported=' . $imported . '&skipped=' . $skipped);
                exit;
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            
        } catch (Exception $e) {
            $error = 'インポートエラー: ' . $e->getMessage();
        }
    }
    
    if ($id > 0 && in_array($action, ['approve', 'reject', 'pending', 'delete'])) {
        try {
            $pdo = new PDO('sqlite:' . TAGS_DB_PATH);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            if ($action === 'delete') {
                $stmt = $pdo->prepare('DELETE FROM tag_suggestions WHERE id = :id');
                $stmt->execute([':id' => $id]);
            } else {
                $statusMap = ['approve' => 'approved', 'reject' => 'rejected', 'pending' => 'pending'];
                $status = $statusMap[$action] ?? 'pending';
                $stmt = $pdo->prepare('UPDATE tag_suggestions SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
                $stmt->execute([':status' => $status, ':id' => $id]);
            }
        } catch (PDOException $e) {
            $error = 'データベースエラー: ' . $e->getMessage();
        }
    }
    
    // リダイレクトしてPOSTの再送信を防ぐ
    header('Location: admin.php' . (isset($_GET['filter']) ? '?filter=' . urlencode($_GET['filter']) : ''));
    exit;
}

// フィルター
$filter = $_GET['filter'] ?? 'all';
$searchArea = $_GET['search_area'] ?? '';
$searchTag = $_GET['search_tag'] ?? '';

// DB接続とデータ取得
$tags = [];
$stats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
$error = '';
$badThreshold = 3; // デフォルト値

if (!file_exists(TAGS_DB_PATH)) {
    $error = 'データベースファイルが見つかりません。タグ提案がまだないか、パスを確認してください。';
} else {
    try {
        $pdo = new PDO('sqlite:' . TAGS_DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        // 設定テーブル初期化と設定値取得
        initSettingsTable($pdo);
        $badThreshold = (int)getSetting($pdo, 'bad_threshold', '3');
        
        // 統計情報
        $statsQuery = $pdo->query('SELECT status, COUNT(*) as cnt FROM tag_suggestions GROUP BY status');
        while ($row = $statsQuery->fetch()) {
            $stats[$row['status']] = (int)$row['cnt'];
            $stats['total'] += (int)$row['cnt'];
        }
        
        // タグ一覧を取得
        $where = [];
        $params = [];
        
        if ($filter !== 'all') {
            $where[] = 'status = :status';
            $params[':status'] = $filter;
        }
        
        if ($searchArea !== '') {
            $where[] = 'area LIKE :area';
            $params[':area'] = '%' . $searchArea . '%';
        }
        
        if ($searchTag !== '') {
            $where[] = 'tag LIKE :tag';
            $params[':tag'] = '%' . $searchTag . '%';
        }
        
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // tag_votesテーブルの存在確認と作成
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS tag_votes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tag_id INTEGER NOT NULL,
                ip_address TEXT NOT NULL,
                vote_type TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(tag_id, ip_address)
            )
        ');
        
        // good/bad集計を含むクエリ
        $sql = "SELECT 
                    ts.*,
                    COALESCE(SUM(CASE WHEN tv.vote_type = 'good' THEN 1 ELSE 0 END), 0) as good_count,
                    COALESCE(SUM(CASE WHEN tv.vote_type = 'bad' THEN 1 ELSE 0 END), 0) as bad_count
                FROM tag_suggestions ts
                LEFT JOIN tag_votes tv ON ts.id = tv.tag_id
                $whereSql
                GROUP BY ts.id
                ORDER BY ts.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $tags = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        $error = 'データベースエラー: ' . $e->getMessage();
    }
}

// ステータスラベル
function getStatusLabel(string $status): string {
    switch ($status) {
        case 'pending': return '保留中';
        case 'approved': return '承認済み';
        case 'rejected': return '却下';
        default: return $status;
    }
}

function getStatusColor(string $status): string {
    switch ($status) {
        case 'pending': return '#f59e0b';
        case 'approved': return '#10b981';
        case 'rejected': return '#ef4444';
        default: return '#6b7280';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>タグ管理 - 管理画面</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f3f4f6;
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        .header h1 {
            font-size: 1.5rem;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .header-actions {
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5a67d8;
        }
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        .btn-danger:hover {
            background: #dc2626;
        }
        .btn-success {
            background: #10b981;
            color: white;
        }
        .btn-success:hover {
            background: #059669;
        }
        .btn-warning {
            background: #f59e0b;
            color: white;
        }
        .btn-warning:hover {
            background: #d97706;
        }
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        .btn-secondary:hover {
            background: #4b5563;
        }
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-card .number {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
        }
        .stat-card .label {
            color: #6b7280;
            font-size: 0.9rem;
            margin-top: 4px;
        }
        .filters {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .filters form {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: flex-end;
        }
        .filter-group {
            flex: 1;
            min-width: 150px;
        }
        .filter-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 0.85rem;
            color: #555;
        }
        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
            font-size: 0.85rem;
            text-transform: uppercase;
        }
        tr:hover {
            background: #f9fafb;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            color: white;
        }
        .tag-name {
            font-weight: 500;
            color: #1f2937;
        }
        .area-badge {
            display: inline-block;
            padding: 4px 10px;
            background: #e5e7eb;
            border-radius: 6px;
            font-size: 0.8rem;
            color: #374151;
        }
        .actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }
        .empty-state .icon {
            font-size: 3rem;
            margin-bottom: 16px;
        }
        .error {
            background: #fee2e2;
            color: #dc2626;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .datetime {
            font-size: 0.85rem;
            color: #6b7280;
        }
        @media (max-width: 768px) {
            .table-container {
                overflow-x: auto;
            }
            table {
                min-width: 700px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏷️ タグ管理システム</h1>
            <div class="header-actions">
                <span style="color: #6b7280; margin-right: 10px;">
                    👤 <?= h($_SESSION['tags_admin_user'] ?? 'admin') ?>
                </span>
                <a href="generator.php" class="btn btn-primary">🪄 タグ生成ツール</a>
                <a href="?logout=1" class="btn btn-secondary">ログアウト</a>
            </div>
        </div>
        
        <?php if ($error): ?>
        <div class="error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <div class="stats">
            <div class="stat-card">
                <div class="number"><?= $stats['total'] ?></div>
                <div class="label">総タグ数</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color: #f59e0b;"><?= $stats['pending'] ?></div>
                <div class="label">保留中</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color: #10b981;"><?= $stats['approved'] ?></div>
                <div class="label">承認済み</div>
            </div>
            <div class="stat-card">
                <div class="number" style="color: #ef4444;"><?= $stats['rejected'] ?></div>
                <div class="label">却下</div>
            </div>
        </div>
        
        <!-- 設定セクション -->
        <div class="filters" style="margin-bottom: 20px;">
            <h3 style="margin-bottom: 16px; font-size: 1rem; color: #374151;">⚙️ 表示設定</h3>
            <?php if (isset($_GET['settings_saved'])): ?>
            <div style="background: #d1fae5; color: #065f46; padding: 10px 16px; border-radius: 6px; margin-bottom: 16px;">
                設定を保存しました
            </div>
            <?php endif; ?>
            <form method="POST" action="" style="display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end;">
                <input type="hidden" name="action" value="update_settings">
                <div class="filter-group" style="max-width: 200px;">
                    <label>Bad投票数のしきい値</label>
                    <input type="number" name="bad_threshold" value="<?= $badThreshold ?>" min="0" max="100" style="width: 100%;">
                    <small style="color: #6b7280; font-size: 0.75rem;">
                        この数以上のBad投票があるタグは非表示
                    </small>
                </div>
                <button type="submit" class="btn btn-primary">設定を保存</button>
            </form>
        </div>
        
        <!-- エクスポート・インポートセクション -->
        <div class="filters" style="margin-bottom: 20px;">
            <div style="display: flex; flex-wrap: wrap; gap: 40px;">
                <div style="flex: 1 1 100%;">
                    <h3 style="margin-bottom: 16px; font-size: 1rem; color: #374151;">🪄 説明文タグ生成</h3>
                    <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
                        <a href="generator.php" class="btn btn-primary">
                            説明文タグ生成ツールを開く
                        </a>
                    </div>
                    <small style="color: #6b7280; font-size: 0.75rem; display: block; margin-top: 8px;">
                        説明文からタグ候補を生成して、プレビュー・JSON保存・DB登録を行えます。
                    </small>
                </div>

                <!-- エクスポート -->
                <div style="flex: 1; min-width: 280px;">
                    <h3 style="margin-bottom: 16px; font-size: 1rem; color: #374151;">📤 JSONエクスポート</h3>
                    <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
                        <a href="?export=json" class="btn btn-primary">
                            📥 全タグをエクスポート
                        </a>
                        <a href="?export=json&approved_only=1" class="btn btn-success">
                            ✓ 承認済みのみ
                        </a>
                    </div>
                    <small style="color: #6b7280; font-size: 0.75rem; display: block; margin-top: 8px;">
                        投票数を含むtags.json形式でダウンロード
                    </small>
                </div>
                
                <!-- インポート -->
                <div style="flex: 1; min-width: 320px;">
                    <h3 style="margin-bottom: 16px; font-size: 1rem; color: #374151;">📥 JSONインポート</h3>
                    <?php if (isset($_GET['import_success'])): ?>
                    <div style="background: #d1fae5; color: #065f46; padding: 10px 16px; border-radius: 6px; margin-bottom: 12px;">
                        インポート完了: <?= (int)$_GET['imported'] ?>件追加, <?= (int)$_GET['skipped'] ?>件スキップ
                    </div>
                    <?php endif; ?>
                    <form method="POST" action="" enctype="multipart/form-data" style="display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;">
                        <input type="hidden" name="action" value="import_json">
                        <div class="filter-group" style="flex: 1; min-width: 150px;">
                            <label>JSONファイル</label>
                            <input type="file" name="json_file" accept=".json" required style="width: 100%;">
                        </div>
                        <div class="filter-group" style="min-width: 120px;">
                            <label>インポート方式</label>
                            <select name="import_mode" style="width: 100%;">
                                <option value="merge">マージ（重複スキップ）</option>
                                <option value="replace">全置換（既存削除）</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-warning" onclick="return this.form.import_mode.value === 'replace' ? confirm('既存のタグと投票をすべて削除して置き換えます。よろしいですか？') : true;">
                            📤 インポート
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="filters">
            <form method="GET" action="">
                <div class="filter-group">
                    <label>ステータス</label>
                    <select name="filter">
                        <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>すべて</option>
                        <option value="pending" <?= $filter === 'pending' ? 'selected' : '' ?>>保留中</option>
                        <option value="approved" <?= $filter === 'approved' ? 'selected' : '' ?>>承認済み</option>
                        <option value="rejected" <?= $filter === 'rejected' ? 'selected' : '' ?>>却下</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>エリア検索</label>
                    <input type="text" name="search_area" value="<?= h($searchArea) ?>" placeholder="例: kanto">
                </div>
                <div class="filter-group">
                    <label>タグ検索</label>
                    <input type="text" name="search_tag" value="<?= h($searchTag) ?>" placeholder="例: かわいい">
                </div>
                <button type="submit" class="btn btn-primary">検索</button>
                <a href="admin.php" class="btn btn-secondary">リセット</a>
            </form>
        </div>
        
        <div class="table-container">
            <?php if (empty($tags)): ?>
            <div class="empty-state">
                <div class="icon">📭</div>
                <p>タグが見つかりませんでした</p>
            </div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>エリア</th>
                        <th>図鑑No.</th>
                        <th>タグ</th>
                        <th>👍 Good</th>
                        <th>👎 Bad</th>
                        <th>ステータス</th>
                        <th>作成日時</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tags as $tag): ?>
                    <tr>
                        <td><?= h((string)$tag['id']) ?></td>
                        <td><span class="area-badge"><?= h($tag['area']) ?></span></td>
                        <td>No.<?= str_pad((string)$tag['pokedex_no'], 4, '0', STR_PAD_LEFT) ?></td>
                        <td class="tag-name"><?= h($tag['tag']) ?></td>
                        <td style="text-align: center;">
                            <span style="color: #10b981; font-weight: 600;"><?= (int)$tag['good_count'] ?></span>
                        </td>
                        <td style="text-align: center;">
                            <span style="color: #ef4444; font-weight: 600;"><?= (int)$tag['bad_count'] ?></span>
                        </td>
                        <td>
                            <span class="status-badge" style="background: <?= getStatusColor($tag['status']) ?>">
                                <?= getStatusLabel($tag['status']) ?>
                            </span>
                        </td>
                        <td class="datetime"><?= h($tag['created_at']) ?></td>
                        <td>
                            <div class="actions">
                                <?php if ($tag['status'] !== 'approved'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="id" value="<?= $tag['id'] ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-success btn-sm" title="承認">✓</button>
                                </form>
                                <?php endif; ?>
                                <?php if ($tag['status'] !== 'pending'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="id" value="<?= $tag['id'] ?>">
                                    <input type="hidden" name="action" value="pending">
                                    <button type="submit" class="btn btn-secondary btn-sm" title="保留">○</button>
                                </form>
                                <?php endif; ?>
                                <?php if ($tag['status'] !== 'rejected'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="id" value="<?= $tag['id'] ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn btn-warning btn-sm" title="却下">✗</button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('このタグを削除しますか？');">
                                    <input type="hidden" name="id" value="<?= $tag['id'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn btn-danger btn-sm" title="削除">🗑</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
