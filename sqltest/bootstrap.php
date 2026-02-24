<?php

declare(strict_types=1);

session_start();

$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    throw new RuntimeException('Fichier sqltest/config.php introuvable. Copiez config.example.php vers config.php.');
}

$config = require $configPath;

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $config['db']['host'],
    (int) $config['db']['port'],
    $config['db']['database'],
    $config['db']['charset'] ?? 'utf8mb4'
);

$pdo = new PDO($dsn, $config['db']['username'], $config['db']['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function old(string $key, string $default = ''): string
{
    return $_POST[$key] ?? $default;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool
{
    return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function normalize_username(string $username): string
{
    $username = mb_strtolower(trim($username));
    $username = preg_replace('/[^\p{L}0-9-_]/u', '', $username) ?? '';

    return mb_substr($username, 0, 50);
}

function generate_username(string $displayName): string
{
    $base = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $displayName);
    $base = $base !== false ? $base : $displayName;
    $base = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9-_]+/', '-', $base), '-'));

    if ($base === '') {
        $base = 'user';
    }

    return mb_substr($base, 0, 50);
}

function valid_http_url(string $url): bool
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }

    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

    return in_array($scheme, ['http', 'https'], true);
}
