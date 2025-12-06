<?php
/**
 * タグ管理ページ設定ファイル
 * .envファイルから認証情報を読み込む
 */

declare(strict_types=1);

// .envファイルを読み込む
function loadEnv(string $path): array {
    $env = [];
    if (file_exists($path)) {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // コメント行をスキップ
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            // KEY=VALUE形式をパース
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, " \t\n\r\0\x0B\"'");
                $env[$key] = $value;
            }
        }
    }
    return $env;
}

// プロジェクトルートの.envを読み込む
$envPath = dirname(dirname(dirname(__DIR__))) . '/.env';
$env = loadEnv($envPath);

// 認証情報
define('TAGS_ADMIN_USER', $env['TAGS_ADMIN_USER'] ?? 'admin');
define('TAGS_ADMIN_PASS', $env['TAGS_ADMIN_PASS'] ?? 'password');

// サイトトップURL（リダイレクト用）
define('SITE_TOP_URL', $env['NUXT_PUBLIC_APP_BASE_URL'] ?? '/');

// DBパス
define('TAGS_DB_PATH', __DIR__ . '/tags.db');

// セッション設定
function initSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// ログイン状態チェック
function isLoggedIn(): bool {
    initSession();
    return isset($_SESSION['tags_admin_logged_in']) && $_SESSION['tags_admin_logged_in'] === true;
}

// ログイン処理
function login(string $user, string $pass): bool {
    if ($user === TAGS_ADMIN_USER && $pass === TAGS_ADMIN_PASS) {
        initSession();
        $_SESSION['tags_admin_logged_in'] = true;
        $_SESSION['tags_admin_user'] = $user;
        return true;
    }
    return false;
}

// ログアウト処理
function logout(): void {
    initSession();
    session_destroy();
}

// HTMLエスケープ
function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
