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
    { "title": "site web", "url": "https://meneeto.com" },
    { "title": "Instagram", "url": "https://instagram.com/username" },
    { "title": "Facebook", "url": "https://facebook.com/userxxxxxx" },
    { "title": "Tel", "url": "tel://+213555555555" },
    { "title": "Email", "url": "mailto:me@example.com" }
  ]
}
```

### Alternative (`links_text`)

```json
{
  "display_name": "Mon User",
  "links_text": "site web|https://meneeto.com\nInstagram|https://instagram.com/username\nTel|tel://+213555555555"
}
```

## Titres reconnus comme sites prédéfinis

- instagram, facebook, whatsapp, x, snapchat, telegram, tiktok, paypal, spotify, deezer, discord, github, gitlab, messenger, pinterest (ou pintrest), linkedin, reddit, steam, twitch, youtube.
- `Email` -> bouton LinkStack email (`default email`) ; URL acceptée : `mailto:` ou email brut.
- `Tel` -> bouton LinkStack phone (`phone`) ; URL acceptée : schéma `tel://`.
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
      {"title":"site web","url":"https://meneeto.com"},
      {"title":"Instagram","url":"https://instagram.com/username"},
      {"title":"Tel","url":"tel://+213555555555"}
    ]
  }'
```
