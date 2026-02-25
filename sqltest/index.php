<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$errors = $_SESSION['sqltest_errors'] ?? [];
$success = $_SESSION['sqltest_success'] ?? null;
$oldInput = $_SESSION['sqltest_old'] ?? [];
unset($_SESSION['sqltest_errors'], $_SESSION['sqltest_success'], $_SESSION['sqltest_old']);

if (!empty($oldInput)) {
    $_POST = $oldInput;
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>SQLTest - Création de comptes LinkStack</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 30px auto; padding: 0 16px; }
        input, textarea { width: 100%; padding: 10px; margin: 4px 0 12px; box-sizing: border-box; }
        label { font-weight: 700; display: block; }
        .card { border: 1px solid #ddd; border-radius: 8px; padding: 16px; }
        .error { background: #ffe8e8; border: 1px solid #ffb4b4; color: #8b0000; padding: 10px; margin-bottom: 12px; }
        .success { background: #e8ffe8; border: 1px solid #9ad39a; color: #1f6d1f; padding: 10px; margin-bottom: 12px; }
        button { background: #2d6cdf; color: white; border: 0; border-radius: 6px; padding: 10px 16px; cursor: pointer; }
        .hint { color: #555; font-size: 14px; }
    </style>
</head>
<body>
    <h1>Création de compte LinkStack (sqltest)</h1>
    <p class="hint">Ce formulaire écrit directement dans la base LinkStack (tables <code>users</code> et <code>links</code>).</p>

    <?php if (!empty($errors)): ?>
        <div class="error">
            <strong>Erreurs :</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success">
            Compte créé avec succès. ID utilisateur : <strong><?= e((string) $success['user_id']) ?></strong><br>
            URL publique : <a href="<?= e($success['public_url']) ?>" target="_blank" rel="noopener"><?= e($success['public_url']) ?></a>
        </div>
    <?php endif; ?>

    <div class="card">
        <form action="create_account.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <label for="display_name">Nom affiché *</label>
            <input id="display_name" name="display_name" required maxlength="255" value="<?= e(old('display_name')) ?>">

            <label for="username">Username / slug (optionnel)</label>
            <input id="username" name="username" maxlength="50" placeholder="ex: monpseudo" value="<?= e(old('username')) ?>">

            <label for="email">Email (optionnel)</label>
            <input id="email" name="email" type="email" maxlength="255" placeholder="ex: user@example.com" value="<?= e(old('email')) ?>">

            <label for="password">Mot de passe (optionnel)</label>
            <input id="password" name="password" type="text" minlength="12" maxlength="255" placeholder="Laissez vide pour auto-génération" value="<?= e(old('password')) ?>">

            <label for="bio">Bio (optionnel)</label>
            <textarea id="bio" name="bio" maxlength="500" rows="3"><?= e(old('bio')) ?></textarea>

            <label for="links">Liens (1 par ligne au format: <code>Titre|https://url</code>)</label>
            <textarea id="links" name="links" rows="8" placeholder="Site|https://example.com&#10;Docs|https://docs.example.com"><?= e(old('links')) ?></textarea>
            <p class="hint">Maximum <?= (int) $config['max_links'] ?> liens. Ordre = ordre des lignes.</p>
            <p class="hint">Titres reconnus en sites prédéfinis : Instagram, Facebook, WhatsApp, X, Snapchat, Telegram, TikTok, PayPal, Spotify, Deezer, Discord, GitHub, GitLab, Messenger, Pinterest/Pintrest, LinkedIn, Reddit, Steam, Twitch, YouTube.</p>
            <p class="hint">Blocs spéciaux : <code>Adresse email|mailto:mail@domaine.com</code> (ou email brut) et <code>Téléphone|tel://+213555555555</code>.</p>

            <label for="avatar_file">Avatar (optionnel)</label>
            <input id="avatar_file" name="avatar_file" type="file" accept="image/png,image/jpeg,image/webp,image/gif">
            <p class="hint">Image sauvegardée dans <code>assets/img</code> pour compatibilité LinkStack.</p>

            <button type="submit">Créer le compte</button>
        </form>
    </div>
</body>
</html>
