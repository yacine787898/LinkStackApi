# sqltest

Petit module PHP autonome pour créer des comptes LinkStack depuis un formulaire HTML, en écrivant directement dans les tables existantes (`users`, `links`) de la même base de données.

## Installation

1. Copier la config :

```bash
cp sqltest/config.example.php sqltest/config.php
```

2. Modifier `sqltest/config.php` (accès DB + `app_url`).

3. Servir le projet (exemple) :

```bash
php -S 0.0.0.0:8080 -t .
```

4. Ouvrir :

- `http://localhost:8080/sqltest/index.php`

## Fonctionnalités

- Formulaire de création de compte :
  - `display_name`
  - `username` optionnel (auto-généré si vide)
  - `email` optionnel (auto `username@local.invalid` si vide)
  - `password` optionnel (auto-généré si vide)
  - `bio`
  - liens via lignes `Titre|https://url`
  - upload avatar optionnel (jpg/png/webp/gif, max 2 Mo)
- Validation (serveur) + CSRF.
- Transaction SQL : rollback si erreur.
- Utilise les mêmes tables que LinkStack.
- Les comptes créés via sqltest sont automatiquement marqués email vérifié (`email_verified_at`).

## Notes

- Le mot de passe auto-généré n'est pas réaffiché (comportement volontaire).
- Les avatars sont sauvegardés dans `assets/img` pour rester compatibles avec la logique existante LinkStack.


## API (optionnel)

Un endpoint JSON est disponible pour créer les comptes à distance :

- `sqltest/api_create_account.php` (avec support avatar via `avatar.type=url|base64`)

Voir la doc dédiée :

- `sqltest/API.md`
