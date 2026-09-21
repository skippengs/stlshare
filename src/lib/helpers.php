<?php

function config(?string $key = null)
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config.php';
    }
    if ($key === null) {
        return $config;
    }
    return $config[$key] ?? null;
}

/** Escape user-supplied data for HTML output. Use on every echoed value. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function base_url(string $path = ''): string
{
    return '/' . ltrim($path, '/');
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_take(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function current_user(): ?array
{
    static $user = null;
    static $loaded = false;
    if ($loaded) {
        return $user;
    }
    $loaded = true;
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = Database::get()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        flash_set('error', 'Please log in to continue.');
        redirect('/login');
    }
    if ($user['status'] === 'pending') {
        redirect('/pending');
    }
    if ($user['status'] !== 'approved') {
        session_destroy();
        flash_set('error', 'Your account is not active. Contact the admin.');
        redirect('/login');
    }
    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
    return $user;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || $token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        echo 'Invalid or expired form submission (CSRF check failed). Go back and try again.';
        exit;
    }
}

/** For JSON/raw-body endpoints called via fetch(), which send the token as a header instead of a form field. */
function csrf_verify_header(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || $token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        exit;
    }
}

function human_filesize(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    $size = (float) $bytes;
    while ($size >= 1024 && $i < count($units) - 1) {
        $size /= 1024;
        $i++;
    }
    return round($size, 1) . ' ' . $units[$i];
}

function user_quota_bytes(array $user): int
{
    if ($user['storage_quota_bytes'] !== null) {
        return (int) $user['storage_quota_bytes'];
    }
    $stmt = Database::get()->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $stmt->execute(['default_quota_bytes']);
    $row = $stmt->fetch();
    return $row ? (int) $row['setting_value'] : 2 * 1024 * 1024 * 1024;
}

function user_used_bytes(int $userId): int
{
    $stmt = Database::get()->prepare('SELECT COALESCE(SUM(file_size_bytes), 0) AS used FROM models WHERE owner_user_id = ?');
    $stmt->execute([$userId]);
    return (int) $stmt->fetch()['used'];
}

function view(string $name, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require __DIR__ . '/../views/' . $name . '.php';
}

function render(string $name, array $data = [], string $layout = 'layout'): void
{
    $user = current_user();
    $data['user'] = $data['user'] ?? $user;
    ob_start();
    view($name, $data);
    $content = ob_get_clean();
    view($layout, ['content' => $content, 'user' => $user, 'pageTitle' => $data['pageTitle'] ?? config('app')['name']]);
}
