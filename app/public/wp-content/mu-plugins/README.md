# mu-plugins Olthem

Dossier des plugins obligatoires (must-use) de l'installation WordPress headless Olthem. Chaque fichier est chargé automatiquement par WordPress, sans activation manuelle.

---

## Vue d'ensemble

```
mu-plugins/
├── olthem-db.php          — Structure BDD + versioning + migrations
├── olthem-auth.php        — Authentification + tokens Bearer + endpoints /auth/*
├── olthem-api-rest.php    — API REST admin + soumission formulaires
├── olthem-admin.php       — Interface back-office WP (ateliers, email templates, newsletters)
└── olthem-headless.php    — CPT, champs ACF, CORS, expositions REST contenu
```

---

## Fichiers

### `olthem-db.php`

Responsabilité unique : créer et migrer les tables personnalisées.

- Définit la constante `OLTHEM_DB_VERSION` (actuellement `1.6.0`).
- Crée les tables via `dbDelta()` (idempotent).
- Applique les migrations incrémentales (ALTER TABLE) à chaque mise à jour de version.
- Seed les templates email par défaut si la table est vide.
- Hook : `after_switch_theme` + vérification à chaque requête via `olthem_maybe_run_migration()`.

### `olthem-auth.php`

Responsabilité unique : authentification et tokens API.

- Restreint la connexion WP à l'email uniquement.
- Génère, valide et révoque des tokens Bearer personnalisés (stockés dans `wp_usermeta`).
- Gère le reset de mot de passe avec envoi d'email depuis le template `reset_password`.

**Endpoints REST :**

| Méthode | Route | Description |
|---------|-------|-------------|
| `POST` | `/olthem/v1/auth/register` | Inscription d'un nouvel utilisateur |
| `POST` | `/olthem/v1/auth/login` | Connexion (retourne un token) |
| `GET` | `/olthem/v1/auth/me` | Profil de l'utilisateur connecté |
| `PUT` | `/olthem/v1/auth/me` | Mise à jour du profil |
| `GET` | `/olthem/v1/auth/me/ateliers` | Ateliers de l'utilisateur connecté |
| `PUT` | `/olthem/v1/auth/me/ateliers/{id}` | Mise à jour d'un atelier personnel |
| `POST` | `/olthem/v1/auth/logout` | Révocation du token |
| `POST` | `/olthem/v1/auth/check-username` | Vérification disponibilité du nom d'utilisateur |
| `POST` | `/olthem/v1/auth/forgot-password` | Envoi du lien de réinitialisation |
| `POST` | `/olthem/v1/auth/reset-password` | Réinitialisation du mot de passe |

### `olthem-api-rest.php`

Endpoints REST pour le tableau de bord admin et la soumission de formulaires.

- Toutes les routes `/admin/*` exigent un token Bearer d'un utilisateur avec `is_admin = 1`.

**Endpoints REST :**

| Méthode | Route | Description |
|---------|-------|-------------|
| `GET` | `/olthem/v1/admin/overview` | Tableau de bord (compteurs, derniers inscrits) |
| `GET` | `/olthem/v1/admin/users` | Liste paginée/filtrée des utilisateurs |
| `PUT` | `/olthem/v1/admin/users/{id}` | Modification d'un utilisateur |
| `DELETE` | `/olthem/v1/admin/users/{id}` | Suppression d'un utilisateur |
| `GET` | `/olthem/v1/admin/ateliers` | Liste paginée/filtrée des ateliers |
| `PUT` | `/olthem/v1/admin/ateliers/{id}` | Modification d'un atelier |
| `DELETE` | `/olthem/v1/admin/ateliers/{id}` | Suppression d'un atelier |
| `POST` | `/olthem/v1/forms/submit` | Soumission d'une entrée de formulaire builder |

### `olthem-admin.php`

Responsabilité unique : interface back-office WordPress.

- Colonnes personnalisées dans la liste des utilisateurs (`ID`, `remember`, `newsletter`, `isAdmin`).
- Champs Olthem sur les profils utilisateurs.
- Menu **Olthem** avec trois sous-menus :
  - **Ateliers** — tableau des demandes d'ateliers avec changement de statut et géocodage Nominatim.
  - **Email Templates** — CRUD complet (créer, modifier, supprimer les templates d'emails transactionnels).
  - **Newsletters** — composition et envoi d'une newsletter aux abonnés, historique avec modification/suppression.

### `olthem-headless.php`

Responsabilité : adapter WordPress au mode headless (front JS découplé).

- Enregistre le Custom Post Type `olthem_thematique`.
- Définit les champs ACF locaux (groupes `thematique`, `atelier`, `options`).
- Configure les en-têtes CORS pour autoriser les requêtes depuis le domaine front.
- Expose le contenu ACF dans les réponses REST (`wp/v2/olthem_thematique`, etc.).

---

## Tables BDD

| Table | Description |
|-------|-------------|
| `wp_olthem_users` | Comptes utilisateurs propriétaires (hors `wp_users`) |
| `wp_olthem_ateliers` | Demandes de programmation d'ateliers |
| `wp_olthem_email_templates` | Templates d'emails transactionnels (CRUD depuis back-office) |
| `wp_olthem_newsletters` | Historique des newsletters envoyées |

### `wp_olthem_users` — colonnes principales

`id`, `username`, `last_name`, `first_name`, `email`, `password`, `remember`, `newsletter`, `is_admin`, `created_at`

### `wp_olthem_ateliers` — colonnes principales

`id`, `user_id`, `thematic_id`, `mundaneum`, `institution`, `address`, `city`, `postal_code`, `is_registered_user`, `last_name`, `first_name`, `email`, `phone`, `start_date`, `end_date`, `valid_date`, `participants_count`, `share_contact`, `latitude`, `longitude`, `created_at`

### `wp_olthem_email_templates` — colonnes

`id`, `name`, `event_key` (unique), `subject`, `body`, `created_at`

**Jetons disponibles dans le corps d'un template :**

| Jeton | Valeur injectée |
|-------|-----------------|
| `[first_name]` | Prénom du destinataire |
| `[last_name]` | Nom de famille du destinataire |
| `[USERNAME]` | Nom d'utilisateur |
| `[LIEU]` | Lieu de l'atelier |
| `[THEMATIQUE]` | Titre de la thématique |
| `[start_date]` | Date de début souhaitée |
| `[end_date]` | Date de fin souhaitée |
| `[participants_count]` | Nombre de participants |
| `[email]` | Adresse email de contact |
| `[phone]` | Téléphone de contact |
| `[RESET_LINK]` | URL de réinitialisation du mot de passe |

### `wp_olthem_newsletters` — colonnes

`id`, `subject`, `body`, `recipients_count`, `sent_at`

---

## Système de versioning BDD

La version courante est définie dans `olthem-db.php` :

```php
define( 'OLTHEM_DB_VERSION', '1.6.0' );
```

À chaque requête WordPress, `olthem_maybe_run_migration()` compare `olthem_db_version` (option WP) avec `OLTHEM_DB_VERSION`. Si les valeurs diffèrent, `olthem_create_tables()` est réexécuté — ce qui déclenche `dbDelta()` (ajout de colonnes manquantes) et les blocs `ALTER TABLE` conditionnels.

**Pour créer une migration :** incrémenter `OLTHEM_DB_VERSION` et ajouter un bloc `ALTER TABLE` conditionnel dans `olthem_create_tables()`.

---

## Dépendances entre fichiers

```
olthem-db.php
    ↑ utilisé par olthem-auth.php, olthem-api-rest.php, olthem-admin.php

olthem-auth.php
    ↑ olthem_get_bearer_token() / olthem_get_user_from_bearer_token()
      utilisés par olthem-api-rest.php et olthem-admin.php

olthem-headless.php
    — indépendant des autres mu-plugins
```
