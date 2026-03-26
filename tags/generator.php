<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

if (isset($_GET['logout'])) {
    logout();
    header('Location: index.php');
    exit;
}

define('DESCRIPTION_TAG_GENERATOR_SKIP_MAIN', true);
define('DESCRIPTION_TAG_GENERATOR_THROW_ERRORS', true);
require_once __DIR__ . '/generate_from_descriptions.php';

function getGeneratorBaseValues(): array {
    $defaults = getDescriptionTagGeneratorDefaultOptions();
    $defaults['output'] = 'pokedex/tags/auto-tags.json';
    return $defaults;
}

function getGeneratorAreaOptions(): array {
    $options = [
        'all' => 'すべて',
        'global' => '全国図鑑 (global)',
    ];

    $configSource = readJsonFile(dirname(__DIR__) . '/config/pokedex_config.json');
    $regions = isset($configSource['regions']) && is_array($configSource['regions'])
        ? $configSource['regions']
        : [];

    ksort($regions, SORT_STRING);
    foreach ($regions as $slug => $regionConfig) {
        if (!is_array($regionConfig)) {
            continue;
        }

        $label = normalizeScalar($regionConfig['display_jpn'] ?? '');
        $options[(string)$slug] = $label !== '' ? $label . ' (' . $slug . ')' : (string)$slug;
    }

    return $options;
}

function normalizeGeneratorFormValues(array $input): array {
    $base = getGeneratorBaseValues();
    $output = trim((string)($input['output'] ?? $base['output']));

    $normalized = normalizeDescriptionTagGeneratorOptions([
        'write_db' => false,
        'dry_run' => false,
        'output' => $output,
        'status' => $input['status'] ?? $base['status'],
        'area' => $input['area'] ?? $base['area'],
        'max_tags' => $input['max_tags'] ?? $base['max_tags'],
        'min_score' => $input['min_score'] ?? $base['min_score'],
        'min_occurrences' => $input['min_occurrences'] ?? $base['min_occurrences'],
        'max_document_frequency' => $input['max_document_frequency'] ?? $base['max_document_frequency'],
    ]);

    $normalized['output'] = $output;
    return $normalized;
}

function validateWebOutputPath(string $path): string {
    $normalized = str_replace('\\', '/', trim($path));
    if ($normalized === '') {
        throw new InvalidArgumentException('保存先パスを入力してください。');
    }

    if (str_contains($normalized, '..')) {
        throw new InvalidArgumentException('保存先パスに .. は使用できません。');
    }

    if (preg_match('~^(?:[A-Za-z]:|/|\\\\)~', $normalized) === 1) {
        throw new InvalidArgumentException('保存先パスはプロジェクト相対パスで指定してください。');
    }

    return $normalized;
}

$formValues = getGeneratorBaseValues();
$areaOptions = [];
$error = '';
$success = '';
$result = null;
$previewEntries = [];
$previewLimit = 40;
$currentAction = '';

try {
    $areaOptions = getGeneratorAreaOptions();
} catch (Throwable $throwable) {
    $error = $throwable->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    $currentAction = trim((string)($_POST['action'] ?? ''));

    try {
        $formValues = normalizeGeneratorFormValues($_POST);
        $options = $formValues;

        if ($currentAction === 'preview') {
            $options['output'] = '';
            $result = runDescriptionTagGeneration($options);
            $previewEntries = array_slice($result['entries'], 0, $previewLimit);
            $success = 'タグ候補を生成しました。';
        } elseif ($currentAction === 'download_json') {
            $options['output'] = '';
            $result = runDescriptionTagGeneration($options);
            $fileName = 'auto-tags-' . date('Ymd-His') . '.json';
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            echo json_encode($result['payload'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        } elseif ($currentAction === 'save_json') {
            $options['output'] = validateWebOutputPath($formValues['output']);
            $result = runDescriptionTagGeneration($options);
            $previewEntries = array_slice($result['entries'], 0, $previewLimit);
            $success = 'JSONファイルを保存しました。';
        } elseif ($currentAction === 'write_db') {
            $options['output'] = '';
            $options['write_db'] = true;
            $result = runDescriptionTagGeneration($options);
            $previewEntries = array_slice($result['entries'], 0, $previewLimit);
            $db = $result['summary']['db'] ?? ['inserted' => 0, 'skipped' => 0];
            $success = 'DB登録が完了しました。追加: ' . (int)$db['inserted'] . '件 / スキップ: ' . (int)$db['skipped'] . '件';
        } else {
            throw new InvalidArgumentException('不正な操作です。');
        }
    } catch (Throwable $throwable) {
        $error = $throwable->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>説明文タグ生成ツール</title>
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
            color: #1f2937;
        }
        .container {
            max-width: 1280px;
            margin: 0 auto;
        }
        .header,
        .panel,
        .summary-grid,
        .preview-table-wrapper {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        .header {
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        .header h1 {
            font-size: 1.5rem;
        }
        .header-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            cursor: pointer;
            font-size: 0.95rem;
            transition: background 0.2s ease;
        }
        .btn-primary {
            background: #667eea;
            color: #fff;
        }
        .btn-primary:hover {
            background: #5a67d8;
        }
        .btn-secondary {
            background: #6b7280;
            color: #fff;
        }
        .btn-secondary:hover {
            background: #4b5563;
        }
        .btn-success {
            background: #10b981;
            color: #fff;
        }
        .btn-success:hover {
            background: #059669;
        }
        .btn-warning {
            background: #f59e0b;
            color: #fff;
        }
        .btn-warning:hover {
            background: #d97706;
        }
        .btn-danger {
            background: #ef4444;
            color: #fff;
        }
        .btn-danger:hover {
            background: #dc2626;
        }
        .panel {
            padding: 20px;
            margin-bottom: 20px;
        }
        .panel h2 {
            font-size: 1.1rem;
            margin-bottom: 16px;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }
        .field label {
            display: block;
            font-size: 0.85rem;
            color: #4b5563;
            margin-bottom: 6px;
        }
        .field input,
        .field select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.95rem;
        }
        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        .notice {
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 16px;
        }
        .notice-success {
            background: #d1fae5;
            color: #065f46;
        }
        .notice-error {
            background: #fee2e2;
            color: #b91c1c;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 16px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .summary-card {
            text-align: center;
        }
        .summary-number {
            font-size: 2rem;
            font-weight: 700;
            color: #111827;
        }
        .summary-label {
            margin-top: 6px;
            color: #6b7280;
            font-size: 0.9rem;
        }
        .summary-meta {
            margin-top: 4px;
            color: #9ca3af;
            font-size: 0.78rem;
            word-break: break-all;
        }
        .helper {
            margin-top: 6px;
            color: #6b7280;
            font-size: 0.78rem;
            line-height: 1.5;
        }
        .preview-table-wrapper {
            overflow: hidden;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th,
        td {
            padding: 14px 16px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #f9fafb;
            color: #374151;
            font-size: 0.85rem;
            text-transform: uppercase;
        }
        tr:hover {
            background: #f9fafb;
        }
        .tag-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .tag-chip {
            display: inline-block;
            padding: 6px 10px;
            background: #eef2ff;
            color: #3730a3;
            border-radius: 999px;
            font-size: 0.82rem;
        }
        .empty {
            padding: 32px 20px;
            text-align: center;
            color: #6b7280;
        }
        @media (max-width: 768px) {
            .preview-table-wrapper {
                overflow-x: auto;
            }
            table {
                min-width: 760px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>🪄 説明文タグ生成ツール</h1>
                <div class="helper">説明文データからタグ候補を生成し、プレビュー・JSON出力・DB登録を行えます。</div>
            </div>
            <div class="header-actions">
                <span style="color: #6b7280;">👤 <?= h($_SESSION['tags_admin_user'] ?? 'admin') ?></span>
                <a href="admin.php" class="btn btn-secondary">タグ管理へ戻る</a>
                <a href="?logout=1" class="btn btn-secondary">ログアウト</a>
            </div>
        </div>

        <div class="panel">
            <h2>生成オプション</h2>

            <?php if ($success !== ''): ?>
            <div class="notice notice-success"><?= h($success) ?></div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
            <div class="notice notice-error"><?= h($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="grid">
                    <div class="field">
                        <label for="area">対象エリア</label>
                        <select id="area" name="area">
                            <?php foreach ($areaOptions as $value => $label): ?>
                            <option value="<?= h((string)$value) ?>" <?= $formValues['area'] === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="status">登録ステータス</label>
                        <select id="status" name="status">
                            <option value="pending" <?= $formValues['status'] === 'pending' ? 'selected' : '' ?>>pending</option>
                            <option value="approved" <?= $formValues['status'] === 'approved' ? 'selected' : '' ?>>approved</option>
                            <option value="rejected" <?= $formValues['status'] === 'rejected' ? 'selected' : '' ?>>rejected</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="max_tags">1件あたり最大タグ数</label>
                        <input id="max_tags" type="number" name="max_tags" value="<?= h((string)$formValues['max_tags']) ?>" min="1" max="30">
                    </div>
                    <div class="field">
                        <label for="min_score">最小スコア</label>
                        <input id="min_score" type="number" name="min_score" value="<?= h((string)$formValues['min_score']) ?>" min="1" max="100">
                    </div>
                    <div class="field">
                        <label for="min_occurrences">最小出現回数</label>
                        <input id="min_occurrences" type="number" name="min_occurrences" value="<?= h((string)$formValues['min_occurrences']) ?>" min="1" max="20">
                    </div>
                    <div class="field">
                        <label for="max_document_frequency">最大document frequency</label>
                        <input id="max_document_frequency" type="number" name="max_document_frequency" value="<?= h((string)$formValues['max_document_frequency']) ?>" min="1" max="5000">
                    </div>
                    <div class="field" style="grid-column: 1 / -1;">
                        <label for="output">JSON保存先</label>
                        <input id="output" type="text" name="output" value="<?= h((string)$formValues['output']) ?>" placeholder="pokedex/tags/auto-tags.json">
                        <div class="helper">JSON保存ボタン使用時のみ使います。絶対パスや `..` は使えません。</div>
                    </div>
                </div>

                <div class="actions">
                    <button type="submit" name="action" value="preview" class="btn btn-primary">プレビュー生成</button>
                    <button type="submit" name="action" value="download_json" class="btn btn-success">JSONダウンロード</button>
                    <button type="submit" name="action" value="save_json" class="btn btn-warning">JSONを保存</button>
                    <button type="submit" name="action" value="write_db" class="btn btn-danger" onclick="return confirm('生成したタグを tag_suggestions に登録します。よろしいですか？');">DB登録</button>
                </div>
            </form>
        </div>

        <?php if (is_array($result)): ?>
        <div class="summary-grid">
            <div class="summary-card">
                <div class="summary-number"><?= (int)$result['summary']['groups'] ?></div>
                <div class="summary-label">対象グループ数</div>
            </div>
            <div class="summary-card">
                <div class="summary-number"><?= (int)$result['summary']['total_tags'] ?></div>
                <div class="summary-label">生成タグ総数</div>
            </div>
            <div class="summary-card">
                <div class="summary-number"><?= h((string)$result['summary']['area']) ?></div>
                <div class="summary-label">対象エリア</div>
            </div>
            <div class="summary-card">
                <div class="summary-number"><?= h((string)$result['summary']['status']) ?></div>
                <div class="summary-label">登録ステータス</div>
            </div>
            <div class="summary-card">
                <div class="summary-number"><?= isset($result['summary']['db']['inserted']) ? (int)$result['summary']['db']['inserted'] : 0 ?></div>
                <div class="summary-label">DB追加件数</div>
                <div class="summary-meta">DB登録時のみ反映</div>
            </div>
            <div class="summary-card">
                <div class="summary-number"><?= isset($result['summary']['db']['skipped']) ? (int)$result['summary']['db']['skipped'] : 0 ?></div>
                <div class="summary-label">スキップ件数</div>
                <div class="summary-meta">既存タグや承認済みタグ</div>
            </div>
        </div>

        <div class="panel preview-table-wrapper">
            <h2>生成結果プレビュー <?php if (count($previewEntries) > 0): ?>(先頭<?= $previewLimit ?>件)<?php endif; ?></h2>

            <?php if ($previewEntries === []): ?>
            <div class="empty">表示できるプレビュー結果がありません。</div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>エリア</th>
                        <th>図鑑No.</th>
                        <th>説明文数</th>
                        <th>タグ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($previewEntries as $entry): ?>
                    <tr>
                        <td><?= h((string)$entry['area']) ?></td>
                        <td>No.<?= str_pad((string)$entry['no'], 4, '0', STR_PAD_LEFT) ?></td>
                        <td><?= (int)$entry['description_count'] ?></td>
                        <td>
                            <div class="tag-list">
                                <?php foreach ($entry['tags'] as $tag): ?>
                                <span class="tag-chip"><?= h((string)$tag) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
