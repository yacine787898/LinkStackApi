# Provisioning API

This API provisions a LinkStack account (user + page + links + avatar) in one request.

## Authentication

Set a token in `.env`:

```dotenv
LINKSTACK_PROVISION_TOKEN=replace-with-a-long-random-token
```

Send it as a Bearer token:

```http
Authorization: Bearer <LINKSTACK_PROVISION_TOKEN>
```

## Endpoint

### `POST /api/provision/accounts`

Content type must be JSON.

#### Request body

```json
{
  "username": "optional-string",
  "email": "optional-string",
  "password": "optional-string",
  "display_name": "string",
  "bio": "optional-string",
  "links": [
    { "title": "string", "url": "https://example.com", "order": 1 }
  ],
  "avatar": {
    "type": "url",
    "value": "https://example.com/avatar.png"
  }
}
```

Notes:
- `username` is optional. If omitted, a unique slug is generated.
- `email` is optional. If omitted, a unique placeholder `@local.invalid` address is generated.
- `password` is optional. If omitted, a random strong password is generated internally.
- `links` supports up to 30 entries.
- URLs must use `http` or `https`.
- `avatar.type` can be `url`, `base64`, or `none`.
- Avatar max payload size is 2 MB.

#### Success response

`201 Created`

```json
{
  "ok": true,
  "user_id": 123456,
  "public_url": "https://your-domain.tld/@username"
}
```

#### Error responses

- `401 Unauthorized`

```json
{ "ok": false, "error": "unauthorized" }
```

- `409 Conflict` (username/email already exists)

```json
{
  "ok": false,
  "error": "conflict",
  "details": {
    "username": ["The username is already in use."]
  }
}
```

- `422 Unprocessable Entity` (validation errors)

```json
{
  "ok": false,
  "error": "validation_failed",
  "details": {
    "display_name": ["The display name field is required."]
  }
}
```

- `500 Internal Server Error`

```json
{ "ok": false, "error": "unexpected_error" }
```

## cURL examples

### Basic create (avatar URL)

```bash
curl -X POST "https://your-domain.tld/api/provision/accounts" \
  -H "Authorization: Bearer $LINKSTACK_PROVISION_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "display_name": "Provisioned User",
    "bio": "Created from external site",
    "links": [
      {"title": "Website", "url": "https://example.com", "order": 1},
      {"title": "Docs", "url": "https://docs.example.com", "order": 2}
    ],
    "avatar": {"type": "url", "value": "https://example.com/avatar.jpg"}
  }'
```

### Base64 avatar

```bash
curl -X POST "https://your-domain.tld/api/provision/accounts" \
  -H "Authorization: Bearer $LINKSTACK_PROVISION_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "display_name": "Provisioned User",
    "avatar": {
      "type": "base64",
      "value": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA..."
    }
  }'
```
