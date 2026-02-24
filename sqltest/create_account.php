<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!verify_csrf($_POST['csrf_token'] ?? null)) {
    $errors[] = 'Token CSRF invalide.';
}

$displayName = trim((string) ($_POST['display_name'] ?? ''));
$usernameInput = trim((string) ($_POST['username'] ?? ''));
$emailInput = trim((string) ($_POST['email'] ?? ''));
$passwordInput = (string) ($_POST['password'] ?? '');
$bio = trim((string) ($_POST['bio'] ?? ''));
$linksInput = trim((string) ($_POST['links'] ?? ''));

if ($displayName === '' || mb_strlen($displayName) > 255) {
    $errors[] = 'Le nom affiché est requis (max 255 caractères).';
}

if ($bio !== '' && mb_strlen($bio) > (int) $config['max_bio_length']) {
    $errors[] = 'La bio dépasse la taille maximale autorisée.';
}

$username = $usernameInput !== '' ? normalize_username($usernameInput) : generate_username($displayName);
if ($username === '') {
    $errors[] = 'Username invalide.';
}

if ($emailInput !== '' && !filter_var($emailInput, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email invalide.';
}

if ($passwordInput !== '' && mb_strlen($passwordInput) < 12) {
    $errors[] = 'Le mot de passe doit faire au moins 12 caractères.';
}

$parsedLinks = [];
if ($linksInput !== '') {
    $lines = preg_split('/\r\n|\r|\n/', $linksInput) ?: [];
    $order = 1;

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        $parts = explode('|', $line, 2);
        if (count($parts) !== 2) {
            $errors[] = "Ligne de lien invalide: {$line}";
            continue;
        }

        $title = trim($parts[0]);
        $url = trim($parts[1]);

        if ($title === '' || mb_strlen($title) > 255) {
            $errors[] = "Titre de lien invalide: {$line}";
            continue;
        }

        if (!valid_http_url($url)) {
            $errors[] = "URL de lien invalide (http/https uniquement): {$url}";
            continue;
        }

        $parsedLinks[] = [
            'title' => $title,
            'url' => $url,
            'order' => $order++,
        ];
    }
}

if (count($parsedLinks) > (int) $config['max_links']) {
    $errors[] = 'Trop de liens dans le formulaire.';
}

if (!empty($errors)) {
    $_SESSION['sqltest_errors'] = $errors;
    $_SESSION['sqltest_old'] = $_POST;
    header('Location: index.php');
    exit;
}

try {
    $pdo->beginTransaction();

    $baseUsername = $username;
    while (true) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE littlelink_name = :u');
        $stmt->execute(['u' => $username]);
        if ((int) $stmt->fetchColumn() === 0) {
            break;
        }
        $username = substr($baseUsername, 0, 43) . '-' . strtolower(bin2hex(random_bytes(3)));
    }

    $email = strtolower($emailInput);
    if ($email === '') {
        $email = $username . '@local.invalid';
        while (true) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :e');
            $stmt->execute(['e' => $email]);
            if ((int) $stmt->fetchColumn() === 0) {
                break;
            }
            $email = $username . '+' . strtolower(bin2hex(random_bytes(3))) . '@local.invalid';
        }
    } else {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :e');
        $stmt->execute(['e' => $email]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new RuntimeException('Cet email existe déjà.');
        }
    }

    $passwordPlain = $passwordInput !== '' ? $passwordInput : bin2hex(random_bytes(16));
    $passwordHash = password_hash($passwordPlain, PASSWORD_BCRYPT);

    $insertUser = $pdo->prepare('INSERT INTO users (name, email, password, littlelink_name, littlelink_description, role, block, created_at, updated_at) VALUES (:name, :email, :password, :littlelink_name, :littlelink_description, :role, :block, NOW(), NOW())');
    $insertUser->execute([
        'name' => $displayName,
        'email' => $email,
        'password' => $passwordHash,
        'littlelink_name' => $username,
        'littlelink_description' => $bio !== '' ? $bio : null,
        'role' => 'user',
        'block' => 'no',
    ]);

    $userId = (int) $pdo->lastInsertId();

    $buttonId = 1;
    $buttonStmt = $pdo->query('SELECT id FROM buttons ORDER BY id ASC LIMIT 1');
    if ($buttonStmt !== false) {
        $buttonValue = $buttonStmt->fetchColumn();
        if ($buttonValue !== false) {
            $buttonId = (int) $buttonValue;
        }
    }

    if (!empty($parsedLinks)) {
        $insertLink = $pdo->prepare('INSERT INTO links (link, title, `order`, click_number, up_link, user_id, button_id, created_at, updated_at, custom_css, custom_icon) VALUES (:link, :title, :ord, 0, :up_link, :user_id, :button_id, NOW(), NOW(), :custom_css, :custom_icon)');

        foreach ($parsedLinks as $link) {
            $insertLink->execute([
                'link' => $link['url'],
                'title' => $link['title'],
                'ord' => $link['order'],
                'up_link' => 'no',
                'user_id' => $userId,
                'button_id' => $buttonId,
                'custom_css' => '',
                'custom_icon' => 'fa-external-link',
            ]);
        }
    }

    if (isset($_FILES['avatar_file']) && is_array($_FILES['avatar_file']) && ($_FILES['avatar_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $tmpName = (string) $_FILES['avatar_file']['tmp_name'];
        $size = (int) $_FILES['avatar_file']['size'];

        if ($size > 2 * 1024 * 1024) {
            throw new RuntimeException('Avatar trop volumineux (max 2 Mo).');
        }

        $mime = mime_content_type($tmpName) ?: '';
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];

        if (!isset($allowed[$mime])) {
            throw new RuntimeException('Type d\'avatar non autorisé.');
        }

        $targetDir = realpath(__DIR__ . '/../assets/img');
        if ($targetDir === false) {
            throw new RuntimeException('Dossier assets/img introuvable.');
        }

        $filename = $userId . '_' . time() . '.' . $allowed[$mime];
        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($tmpName, $targetPath)) {
            throw new RuntimeException('Échec de sauvegarde de l\'avatar.');
        }
    }

    $pdo->commit();

    $_SESSION['sqltest_success'] = [
        'user_id' => $userId,
        'public_url' => rtrim((string) $config['app_url'], '/') . '/@' . $username,
    ];
    header('Location: index.php');
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['sqltest_errors'] = ['Erreur: ' . $e->getMessage()];
    $_SESSION['sqltest_old'] = $_POST;
    header('Location: index.php');
    exit;
}
