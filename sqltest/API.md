# SQLTest API

Permet de créer des comptes LinkStack depuis un autre site/app (JSON + Bearer token).

## Endpoint

`POST /sqltest/api_create_account.php`

## Authentification

Configurer un token dans `sqltest/config.php` :

```php
'api_token' => 'un-token-long-et-secret',
```

Puis envoyer :

```http
Authorization: Bearer un-token-long-et-secret
Content-Type: application/json
```

## Corps JSON

### Format recommandé (`links` tableau)

```json
{
  "display_name": "Mon User",
  "username": "monuser",
  "email": "monuser@example.com",
  "password": "MotDePasseSuperLong123!",
  "bio": "Bio du profil",
  "links": [
    { "title": "Site web", "url": "https://meneeto.com" },
    { "title": "Instagram", "url": "https://instagram.com/username" },
    { "title": "Facebook", "url": "https://facebook.com/userxxxxxx" },
    { "title": "Téléphone", "url": "tel://+213555555555" },
    { "title": "Adresse email", "url": "mailto:me@example.com" }
  ],
  "avatar": {
    "type": "url",
    "value": "https://exemple.com/avatar.jpg"
  }
}
```

### Avatar via base64

```json
{
  "display_name": "Mon User",
  "avatar": {
    "type": "base64",
    "value": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA..."
  }
}
```

### Alternative (`links_text`)

```json
{
  "display_name": "Mon User",
  "links_text": "Site web|https://meneeto.com\nInstagram|https://instagram.com/username\nTéléphone|tel://+213555555555"
}
```

## Titres reconnus comme sites prédéfinis

- Instagram, Facebook, WhatsApp, X, Snapchat, Telegram, TikTok, PayPal, Spotify, Deezer, Discord, GitHub, GitLab, Messenger, Pinterest (ou Pintrest), LinkedIn, Reddit, Steam, Twitch, YouTube.
- `Adresse email` (ou `Email`) -> bouton LinkStack email (`default email`) ; URL acceptée : `mailto:` ou email brut.
- `Téléphone` (ou `Tel`) -> bouton LinkStack phone (`phone`) ; URL acceptée : schéma `tel://`.
- Tout autre titre -> lien personnalisé (`littlelink-custom`).

## Réponses

### Succès
`201`

```json
{
  "ok": true,
  "user_id": 123,
  "public_url": "https://votre-domaine.tld/@monuser"
}
```

### Erreurs
- `401` : token manquant/invalide
- `422` : JSON invalide ou validation/création échouée

## cURL

```bash
curl -X POST "https://votre-domaine.tld/sqltest/api_create_account.php" \
  -H "Authorization: Bearer un-token-long-et-secret" \
  -H "Content-Type: application/json" \
  -d '{
    "display_name": "Mon User",
    "links": [
      {"title":"Site web","url":"https://meneeto.com"},
      {"title":"Instagram","url":"https://instagram.com/username"},
      {"title":"Téléphone","url":"tel://+213555555555"}
    ],
    "avatar": {"type":"url","value":"https://exemple.com/avatar.jpg"}
  }'
```

## Notes importantes

- Les comptes créés via `/sqltest` (formulaire et API) sont automatiquement marqués comme **email vérifié** (`email_verified_at` rempli à la création).
- Taille avatar max : 2 Mo.
