<?php

declare(strict_types=1);

const SQLTEST_MAX_AVATAR_BYTES = 2097152;

/**
 * Titre saisi -> méta LinkStack
 */
function resolve_link_metadata_from_title(string $title): array
{
    $normalized = mb_strtolower(trim($title));

    $map = [
        'instagram' => ['button_name' => 'instagram', 'display_title' => 'Instagram'],
        'facebook' => ['button_name' => 'facebook', 'display_title' => 'Facebook'],
        'whatsapp' => ['button_name' => 'whatsapp', 'display_title' => 'WhatsApp'],
        'x' => ['button_name' => 'twitter', 'display_title' => 'X'],
        'snapchat' => ['button_name' => 'snapchat', 'display_title' => 'Snapchat'],
        'telegram' => ['button_name' => 'telegram', 'display_title' => 'Telegram'],
        'tiktok' => ['button_name' => 'tiktok', 'display_title' => 'TikTok'],
        'paypal' => ['button_name' => 'paypal', 'display_title' => 'PayPal'],
        'spotify' => ['button_name' => 'spotify', 'display_title' => 'Spotify'],
        'deezer' => ['button_name' => 'deezer', 'display_title' => 'Deezer'],
        'discord' => ['button_name' => 'discord', 'display_title' => 'Discord'],
        'github' => ['button_name' => 'github', 'display_title' => 'GitHub'],
        'gitlab' => ['button_name' => 'gitlab', 'display_title' => 'GitLab'],
        'messenger' => ['button_name' => 'messenger', 'display_title' => 'Messenger'],
        'pinterest' => ['button_name' => 'pinterest', 'display_title' => 'Pinterest'],
        'pintrest' => ['button_name' => 'pinterest', 'display_title' => 'Pinterest'],
        'linkedin' => ['button_name' => 'linkedin', 'display_title' => 'LinkedIn'],
        'reddit' => ['button_name' => 'reddit', 'display_title' => 'Reddit'],
        'steam' => ['button_name' => 'steam', 'display_title' => 'Steam'],
        'twitch' => ['button_name' => 'twitch', 'display_title' => 'Twitch'],
        'youtube' => ['button_name' => 'youtube', 'display_title' => 'YouTube'],
        'email' => ['button_name' => 'default email', 'display_title' => 'Adresse email'],
        'adresse email' => ['button_name' => 'default email', 'display_title' => 'Adresse email'],
        'tel' => ['button_name' => 'phone', 'display_title' => 'Téléphone'],
        'telephone' => ['button_name' => 'phone', 'display_title' => 'Téléphone'],
        'téléphone' => ['button_name' => 'phone', 'display_title' => 'Téléphone'],
    ];

    return $map[$normalized] ?? ['button_name' => 'littlelink-custom', 'display_title' => trim($title)];
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

        $meta = resolve_link_metadata_from_title($title);

        if (!valid_link_url_for_button($url, $meta['button_name'])) {
            $errors[] = "URL invalide pour {$title}: {$url}";
            continue;
        }

        $parsedLinks[] = [
            'title' => $meta['display_title'],
            'url' => $url,
            'order' => $order++,
            'button_name' => $meta['button_name'],
        ];
    }

    if (count($parsedLinks) > $maxLinks) {
        $errors[] = 'Trop de liens dans le formulaire.';
    }

    return [$parsedLinks, $errors];
}

function validate_avatar_image_binary(string $binary): array
{
    if (strlen($binary) > SQLTEST_MAX_AVATAR_BYTES) {
        throw new RuntimeException('Avatar trop volumineux (max 2 Mo).');
    }

    $mime = finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $binary) ?: '';
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Type d\'avatar non autorisé.');
    }

    return ['mime' => $mime, 'extension' => $allowed[$mime]];
}

function store_avatar_binary(int $userId, string $binary, string $extension): void
{
    $targetDir = realpath(__DIR__ . '/../assets/img');
    if ($targetDir === false) {
        throw new RuntimeException('Dossier assets/img introuvable.');
    }

    $filename = $userId . '_' . time() . '.' . $extension;
    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $filename;

    if (file_put_contents($targetPath, $binary) === false) {
        throw new RuntimeException('Échec de sauvegarde de l\'avatar.');
    }
}

function download_binary_from_url(string $url): string
{
    if (!valid_http_url($url)) {
        throw new RuntimeException('avatar.url doit être une URL http(s).');
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if (!is_string($body) || $httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException('Téléchargement avatar.url impossible.');
        }

        if ($contentType !== '' && stripos($contentType, 'image/') !== 0) {
            throw new RuntimeException('avatar.url doit pointer vers une image.');
        }

        return $body;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 10,
            'follow_location' => 1,
            'max_redirects' => 3,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    if (!is_string($body)) {
        throw new RuntimeException('Téléchargement avatar.url impossible.');
    }

    return $body;
}

function process_api_avatar_payload(?array $avatarPayload): ?array
{
    if ($avatarPayload === null) {
        return null;
    }

    $type = mb_strtolower(trim((string) ($avatarPayload['type'] ?? 'none')));
    $value = (string) ($avatarPayload['value'] ?? '');

    if ($type === 'none') {
        return null;
    }

    if (!in_array($type, ['url', 'base64'], true)) {
        throw new RuntimeException('avatar.type invalide (none|url|base64).');
    }

    if ($value === '') {
        throw new RuntimeException('avatar.value requis.');
    }

    $binary = '';
    if ($type === 'url') {
        $binary = download_binary_from_url($value);
    } else {
        $raw = preg_replace('/^data:image\/[a-zA-Z0-9.+-]+;base64,/', '', $value);
        $decoded = base64_decode((string) $raw, true);
        if ($decoded === false) {
            throw new RuntimeException('avatar base64 invalide.');
        }
        $binary = $decoded;
    }

    $imageMeta = validate_avatar_image_binary($binary);

    return [
        'binary' => $binary,
        'extension' => $imageMeta['extension'],
    ];
}

function process_uploaded_avatar_file(?array $avatarFile): ?array
{
    if ($avatarFile === null || ($avatarFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($avatarFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Erreur upload avatar.');
    }

    $tmpName = (string) ($avatarFile['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('Fichier avatar invalide.');
    }

    $binary = file_get_contents($tmpName);
    if (!is_string($binary)) {
        throw new RuntimeException('Lecture avatar impossible.');
    }

    $imageMeta = validate_avatar_image_binary($binary);

    return [
        'binary' => $binary,
        'extension' => $imageMeta['extension'],
    ];
}

function create_sqltest_account(PDO $pdo, array $config, array $payload, ?array $avatarFile = null, ?array $avatarPayload = null): array
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

    $finalAvatar = null;
    try {
        if ($avatarPayload !== null) {
            $finalAvatar = process_api_avatar_payload($avatarPayload);
        } else {
            $finalAvatar = process_uploaded_avatar_file($avatarFile);
        }
    } catch (Throwable $avatarError) {
        $errors[] = $avatarError->getMessage();
    }

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

        $insertUser = $pdo->prepare('INSERT INTO users (name, email, email_verified_at, password, littlelink_name, littlelink_description, role, block, created_at, updated_at) VALUES (:name, :email, NOW(), :password, :littlelink_name, :littlelink_description, :role, :block, NOW(), NOW())');
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

        if ($finalAvatar !== null) {
            store_avatar_binary($userId, $finalAvatar['binary'], $finalAvatar['extension']);
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
