<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!verify_csrf($_POST['csrf_token'] ?? null)) {
    $_SESSION['sqltest_errors'] = ['Token CSRF invalide.'];
    $_SESSION['sqltest_old'] = $_POST;
    header('Location: index.php');
    exit;
}

$result = create_sqltest_account($pdo, $config, [
    'display_name' => (string) ($_POST['display_name'] ?? ''),
    'username' => (string) ($_POST['username'] ?? ''),
    'email' => (string) ($_POST['email'] ?? ''),
    'password' => (string) ($_POST['password'] ?? ''),
    'bio' => (string) ($_POST['bio'] ?? ''),
    'links' => (string) ($_POST['links'] ?? ''),
], (isset($_FILES['avatar_file']) && is_array($_FILES['avatar_file'])) ? $_FILES['avatar_file'] : null);

if (!$result['ok']) {
    $_SESSION['sqltest_errors'] = $result['errors'];
    $_SESSION['sqltest_old'] = $_POST;
    header('Location: index.php');
    exit;
}

$_SESSION['sqltest_success'] = [
    'user_id' => $result['user_id'],
    'public_url' => $result['public_url'],
];

header('Location: index.php');
exit;
