<?php
if (defined('QACHEM_BOOTSTRAPPED')) { return; }
define('QACHEM_BOOTSTRAPPED', true);

$config = require __DIR__ . '/config.php';
date_default_timezone_set($config['app']['timezone'] ?? 'Asia/Kuala_Lumpur');

$production = !empty($config['app']['production']);
error_reporting(E_ALL);
ini_set('display_errors', $production ? '0' : '1');

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$timeout = (int)($config['app']['session_timeout'] ?? 28800);
if (!empty($_SESSION['last_activity']) && time() - (int)$_SESSION['last_activity'] > $timeout) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
    }
    session_destroy();
    session_start();
    $_SESSION['flash_error'] = 'Your session expired. Please sign in again.';
}
$_SESSION['last_activity'] = time();

function qachem_config(?string $section = null) {
    global $config;
    return $section === null ? $config : ($config[$section] ?? null);
}

function db_connect(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) { return $pdo; }
    $db = qachem_config('db');
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $db['host'], $db['port'], $db['name'], $db['charset']);
    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function current_user(): ?array {
    if (empty($_SESSION['badge'])) { return null; }
    return [
        'badge' => (string)$_SESSION['badge'],
        'name' => (string)($_SESSION['name'] ?? $_SESSION['badge']),
        'level' => (int)($_SESSION['level'] ?? 1),
    ];
}
function request_wants_json(): bool {
    $uri=(string)($_SERVER['REQUEST_URI']??'');
    $accept=(string)($_SERVER['HTTP_ACCEPT']??'');
    return str_contains($uri,'/api/') || str_contains(strtolower($accept),'application/json');
}
function require_login(): array {
    $u = current_user();
    if (!$u) {
        if(request_wants_json()) json_response(['success'=>false,'message'=>'Authentication required.'],401);
        $_SESSION['flash_error'] = 'Please sign in to continue.';
        header('Location: login.php'); exit;
    }
    return $u;
}
function require_level(int $level): array {
    $u = require_login();
    if ($u['level'] < $level) {
        if(request_wants_json()) json_response(['success'=>false,'message'=>'You do not have permission to perform this action.'],403);
        http_response_code(403);
        exit('You do not have permission to perform this action.');
    }
    return $u;
}
function user_can(string $permission, ?array $user = null): bool {
    $user = $user ?? current_user();
    if (!$user) { return false; }
    $minimum = ['view'=>0, 'tracking_plan'=>2, 'edit'=>2, 'rawform'=>3, 'line_preset'=>3, 'generate_coa'=>3, 'tracking_send'=>3, 'team'=>9];
    return isset($minimum[$permission]) && (int)$user['level'] >= $minimum[$permission];
}
function require_permission(string $permission): array {
    $user = require_login();
    if (!user_can($permission, $user)) {
        if (request_wants_json()) json_response(['success'=>false,'message'=>'You do not have permission to perform this action.'],403);
        http_response_code(403);
        exit('You do not have permission to perform this action.');
    }
    return $user;
}
function access_label(int $level): string {
    return $level >= 9 ? 'Administrator' : ($level >= 3 ? 'Editor' : ($level === 2 ? 'Planner' : 'Viewer'));
}
function json_response(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
function json_input(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true);
    return is_array($data) ? $data : [];
}
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
    return $_SESSION['csrf_token'];
}
function verify_csrf(?string $token = null): void {
    $token = $token ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''));
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        json_response(['success' => false, 'message' => 'Invalid or expired security token. Refresh the page and try again.'], 419);
    }
}
function table_exists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}
function flash(string $key): ?string {
    $v = $_SESSION[$key] ?? null;
    unset($_SESSION[$key]);
    return $v;
}
