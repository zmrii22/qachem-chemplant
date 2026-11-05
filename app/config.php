<?php
require_once __DIR__ . '/compat.php';
$file = __DIR__ . '/config.local.php';
$local = is_file($file) ? require $file : [];
$dbLocal = $local['db'] ?? [];
$appLocal = $local['app'] ?? [];
$env = static function ($key, $fallback) { $value = getenv($key); return $value !== false && $value !== '' ? $value : $fallback; };
return [
 'db' => [
  'host' => $env('QACHEM_DB_HOST', $dbLocal['host'] ?? '127.0.0.1'),
  'port' => $env('QACHEM_DB_PORT', $dbLocal['port'] ?? '3306'),
  'name' => $env('QACHEM_DB_NAME', $dbLocal['name'] ?? 'qachem_demo'),
  'user' => $env('QACHEM_DB_USER', $dbLocal['user'] ?? 'root'),
  'pass' => $env('QACHEM_DB_PASS', $dbLocal['pass'] ?? ''),
  'charset' => 'utf8mb4',
 ],
 'app' => [
  'name' => 'QACHEM', 'timezone' => 'Asia/Singapore',
  'session_timeout' => 28800,
  'production' => filter_var($env('QACHEM_PRODUCTION', $appLocal['production'] ?? '0'), FILTER_VALIDATE_BOOLEAN),
 ],
];
