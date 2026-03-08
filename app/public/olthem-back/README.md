# Olthem Back API

Backend externe pour le site Olthem. Cette API sert les donnees metier et la logique admin.
WordPress reste le shell front et le proxy HTTP.

## 1) Objectif

- DB externe `olthem`
- Tables: `utilisateurs`, `ateliers`, `post`
- Auth utilisateur + role admin
- API compatible avec le theme actuel (blog overlay, sections, mode AdminTool)

## 2) Structure

- `src/app.js`: config Express + routes
- `src/modules/auth`: login/logout/me
- `src/modules/content`: sections one-page
- `src/modules/posts`: blog public + admin CRUD
- `src/modules/ateliers`: ateliers publics + admin CRUD
- `src/modules/users`: admin gestion utilisateurs
- `prisma/schema.prisma`: schema DB
- `prisma/seed.js`: donnees de depart

## 3) Installation

Dans `olthem-back`:

1. `npm.cmd install`
2. copier `.env.example` en `.env`
3. adapter `DATABASE_URL` (db `olthem`)
4. `npm.cmd run prisma:generate`
5. `npm.cmd run prisma:migrate -- --name init`
6. `npm.cmd run seed`
7. `npm.cmd run dev`

L API demarre sur `http://localhost:4000` par defaut.

## 4) Endpoints principaux

Public:

- `GET /health`
- `GET /content/sections`
- `GET /blog`
- `GET /blog/:slug`
- `GET /ateliers`
- `GET /ateliers/:slug`

Auth:

- `POST /auth/login`
- `POST /auth/logout`
- `GET /auth/me`

Admin (role `admin` requis):

- `GET /admin/utilisateurs`
- `PATCH /admin/utilisateurs/:id`
- `POST /admin/posts`
- `PATCH /admin/posts/:id`
- `DELETE /admin/posts/:id`
- `POST /admin/ateliers`
- `PATCH /admin/ateliers/:id`
- `DELETE /admin/ateliers/:id`

## 5) Compte admin seed

Le seed cree (ou met a jour) un admin via:

- `ADMIN_SEED_EMAIL`
- `ADMIN_SEED_PASSWORD`

A changer en production.

## 6) Connexion avec WordPress

Dans ton theme, le proxy WordPress appelle les routes du backend externe.
Le front utilise deja:

- `/auth/me`
- `/content/sections`
- `/blog`
- `/blog/:slug`

Il suffit de pointer l URL backend dans l env WordPress:

- `OLTHEM_EXTERNAL_API_BASE`

## 7) Notes securite

- Controle de role fait cote serveur (pas seulement dans le menu front)
- Mots de passe hashes (bcrypt)
- Token JWT stockable en cookie HttpOnly
- Ajouter HTTPS + rotation secret + logs audit pour production
