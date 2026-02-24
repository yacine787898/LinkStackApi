<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = '';
if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
    $token = trim($matches[1]);
}

$configuredToken = (string) ($config['api_token'] ?? '');
if ($configuredToken === '' || $token === '' || !hash_equals($configuredToken, $token)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode((string) $raw, true);
if (!is_array($data)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'invalid_json']);
    exit;
}

$linksText = '';
if (isset($data['links']) && is_array($data['links'])) {
    $lines = [];
    foreach ($data['links'] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $title = trim((string) ($entry['title'] ?? ''));
        $url = trim((string) ($entry['url'] ?? ''));
        if ($title !== '' && $url !== '') {
            $lines[] = $title . '|' . $url;
        }
    }
    $linksText = implode("\n", $lines);
} else {
    $linksText = (string) ($data['links_text'] ?? '');
}

$result = create_sqltest_account($pdo, $config, [
    'display_name' => (string) ($data['display_name'] ?? ''),
    'username' => (string) ($data['username'] ?? ''),
    'email' => (string) ($data['email'] ?? ''),
    'password' => (string) ($data['password'] ?? ''),
    'bio' => (string) ($data['bio'] ?? ''),
    'links' => $linksText,
], null);

if (!$result['ok']) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'error' => 'validation_or_creation_failed',
        'details' => $result['errors'],
    ]);
    exit;
}

http_response_code(201);
echo json_encode([
    'ok' => true,
    'user_id' => $result['user_id'],
    'public_url' => $result['public_url'],
]);
