<?php

declare(strict_types=1);

/**
 * Titre saisi -> nom interne du bouton LinkStack (table buttons.name)
 */
function resolve_button_name_from_title(string $title): string
{
    $normalized = mb_strtolower(trim($title));

    $map = [
        'instagram' => 'instagram',
        'facebook' => 'facebook',
        'whatsapp' => 'whatsapp',
        'x' => 'twitter',
        'snapchat' => 'snapchat',
        'telegram' => 'telegram',
        'tiktok' => 'tiktok',
        'paypal' => 'paypal',
        'spotify' => 'spotify',
        'deezer' => 'deezer',
        'discord' => 'discord',
        'github' => 'github',
        'gitlab' => 'gitlab',
        'messenger' => 'messenger',
        'pinterest' => 'pinterest',
        'pintrest' => 'pinterest',
        'linkedin' => 'linkedin',
        'reddit' => 'reddit',
        'steam' => 'steam',
        'twitch' => 'twitch',
        'youtube' => 'youtube',
        'email' => 'default email',
        'tel' => 'phone',
    ];

    return $map[$normalized] ?? 'littlelink-custom';
}

function valid_link_url_for_button(string $url, string $buttonName): bool
{
    if ($buttonName === 'phone') {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['tel'], true);
    }

    if ($buttonName === 'default email') {
        if (filter_var($url, FILTER_VALIDATE_EMAIL)) {
            return true;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['mailto'], true);
    }

    return valid_http_url($url);
}

function fetch_button_ids(PDO $pdo): array
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $rows = $pdo->query('SELECT id, name FROM buttons')->fetchAll();
    $cache = [];
    foreach ($rows as $row) {
        $cache[(string) $row['name']] = (int) $row['id'];
    }

    return $cache;
}

function parse_links_input(string $linksInput, int $maxLinks): array
{
    $errors = [];
    $parsedLinks = [];

    if ($linksInput === '') {
        return [$parsedLinks, $errors];
    }

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

        $buttonName = resolve_button_name_from_title($title);

        if (!valid_link_url_for_button($url, $buttonName)) {
            $errors[] = "URL invalide pour {$title}: {$url}";
            continue;
        }

        $parsedLinks[] = [
            'title' => $title,
            'url' => $url,
            'order' => $order++,
            'button_name' => $buttonName,
        ];
    }

    if (count($parsedLinks) > $maxLinks) {
        $errors[] = 'Trop de liens dans le formulaire.';
    }

    return [$parsedLinks, $errors];
}

function create_sqltest_account(PDO $pdo, array $config, array $payload, ?array $avatarFile = null): array
{
    $errors = [];

    $displayName = trim((string) ($payload['display_name'] ?? ''));
    $usernameInput = trim((string) ($payload['username'] ?? ''));
    $emailInput = trim((string) ($payload['email'] ?? ''));
    $passwordInput = (string) ($payload['password'] ?? '');
    $bio = trim((string) ($payload['bio'] ?? ''));
    $linksInput = trim((string) ($payload['links'] ?? ''));

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

    [$parsedLinks, $linkErrors] = parse_links_input($linksInput, (int) $config['max_links']);
    $errors = array_merge($errors, $linkErrors);

    if (!empty($errors)) {
        return ['ok' => false, 'errors' => $errors];
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

        $buttonIds = fetch_button_ids($pdo);
        $fallbackButtonId = $buttonIds['littlelink-custom'] ?? (int) ($pdo->query('SELECT id FROM buttons ORDER BY id ASC LIMIT 1')->fetchColumn() ?: 1);

        if (!empty($parsedLinks)) {
            $insertLink = $pdo->prepare('INSERT INTO links (link, title, `order`, click_number, up_link, user_id, button_id, created_at, updated_at, custom_css, custom_icon) VALUES (:link, :title, :ord, 0, :up_link, :user_id, :button_id, NOW(), NOW(), :custom_css, :custom_icon)');

            foreach ($parsedLinks as $link) {
                $buttonId = $buttonIds[$link['button_name']] ?? $fallbackButtonId;

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

        if ($avatarFile !== null && ($avatarFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $tmpName = (string) $avatarFile['tmp_name'];
            $size = (int) $avatarFile['size'];

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

        return [
            'ok' => true,
            'user_id' => $userId,
            'public_url' => rtrim((string) $config['app_url'], '/') . '/@' . $username,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['ok' => false, 'errors' => ['Erreur: ' . $e->getMessage()]];
    }
}
