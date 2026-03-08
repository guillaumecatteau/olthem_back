# Base technique du theme Olthem

Ce theme est prepare pour faire un site WordPress en mode **OnePage SPA**:

- pas de rechargement de page pendant la navigation,
- URL qui change selon la section visible,
- blog affiche en surcouche (overlay) au-dessus de la section `thematiques`,
- mode `AdminTool` reserve aux admins,
- donnees venant d'un backend externe (hors WordPress).

## 1) Fichiers importants

- `index.php`: point d'entree principal du theme.
- `front-page.php`: shell principal de la SPA (page unique).
- `template-parts/layout/header.php`: balises HTML de tete.
- `template-parts/layout/footer.php`: fermeture HTML + hooks WordPress.
- `functions.php`: logique technique du theme (fichier source unique, pas de doublon).
- `js/app.js`: logique SPA (routing, scroll, URL, overlay blog, mode admin).
- `scss/app.css`: styles compiles charges par WordPress.

## 1.c) Pipeline SCSS (explication complete)

### Structure

- `scss/src/`: fichiers source SCSS que tu modifies.
- `scss/`: fichiers CSS generes automatiquement.
- `scss/app.css`: fichier principal charge par WordPress.

### Schema rapide (vue d'ensemble)

```text
scss/src/*.scss
	-> (sass build/watch)
scss/*.css
	-> (enqueue dans functions.php)
navigateur (styles appliques au site)
```

### Fichier principal

Le point d'entree principal est:

- `scss/src/app.scss`

Il importe les partials:

- `scss/src/partials/_variables.scss`
- `scss/src/partials/_base.scss`
- `scss/src/partials/_layout.scss`

Regle simple:

- fichier commencant par `_` = partial (pas de CSS autonome genere)
- fichier sans `_` = entree compilee en CSS autonome

### Comment la compilation fonctionne

Le projet compile un dossier entier:

- source: `scss/src`
- sortie: `scss`

Donc, quand tu ajoutes un nouveau fichier SCSS dans `scss/src`, tu n'as pas besoin de modifier la config du compilateur.

Exemples:

1. Tu ajoutes `scss/src/blog.scss` -> sortie auto `scss/blog.css`.
2. Tu ajoutes `scss/src/partials/_cards.scss` -> pas de CSS genere tant que ce partial n'est pas importe dans une entree (ex: `app.scss`).

### Commandes disponibles

Depuis le dossier du theme `wp-content/themes/olthem`:

1. Installer les dependances:
`npm install`
2. Compiler une fois:
`npm run scss:build`
3. Lancer la compilation continue:
`npm run scss:watch`

Si PowerShell bloque `npm` (ExecutionPolicy), utilise:

- `npm.cmd install`
- `npm.cmd run scss:watch`

### Demarrage automatique dans VS Code

Le projet contient une tache VS Code qui lance automatiquement `scss:watch` a l'ouverture du dossier:

- `.vscode/tasks.json`
- `.vscode/settings.json`

Si VS Code te demande une autorisation, accepte les taches automatiques.

### Fichiers de config relies au pipeline

- `package.json`: scripts `scss:build` et `scss:watch`
- `.npmrc`: force l'installation des `devDependencies` (dont `sass`)

### Bonnes pratiques

1. Modifier uniquement les fichiers dans `scss/src/`.
2. Ne pas editer manuellement `scss/*.css` (ils sont regeneres).
3. Garder `app.scss` comme entree principale du theme.
4. Creer des fichiers `*.scss` sans `_` seulement si tu veux un CSS separe.

## 1.d) Organisation JS modules

Le script principal est maintenant charge en mode `type="module"`.

- `js/app.js`: point d'entree minimal
- `js/modules/init.js`: orchestration globale
- `js/modules/services/api.js`: client API
- `js/modules/services/router.js`: helpers de routing
- `js/modules/ui/render.js`: rendu UI

## 1.e) Pourquoi il reste quelques fichiers a la racine

WordPress impose certains fichiers a la racine d'un theme:

- `index.php` (obligatoire),
- `style.css` (obligatoire, contient l'identite du theme),
- `functions.php` (fortement recommande, charge automatiquement par WordPress).

Donc on ne peut pas garder strictement **seulement** `index.php` a la racine si on veut un theme WordPress standard et stable.

## 2) Comment la navigation fonctionne

Le script intercepte les clics menu avec `data-route` et utilise `history.pushState`.

Cela permet d'aller vers:

- `/header`
- `/projet`
- `/thematiques`
- `/ressources`
- `/ateliers`
- `/partenaires`
- `/blog`
- `/blog/mon-slug`
- `/admin-tool`

Au scroll, un `IntersectionObserver` detecte la section visible et met l'URL a jour avec `history.replaceState`.

## 3) Blog en overlay

Le blog ne remplace pas la page:

- il s'affiche dans un calque dans la section `thematiques`,
- fermeture et retour se font sans rechargement,
- les liens blog ont aussi des URLs partageables.

## 4) AdminTool sans rechargement

Le mode admin est aussi dans la SPA:

- route: `/admin-tool` et sous-routes,
- menu different en mode admin,
- retour au site public sans reload.

Important: la securite doit etre faite dans votre backend externe, pas seulement dans le front.

## 5) Backend externe (hors WordPress)

Le theme utilise un proxy REST WordPress:

- route proxy: `/wp-json/olthem/v1/proxy/<chemin>`
- exemple: `/wp-json/olthem/v1/proxy/blog`

Ce proxy appelle votre API externe. Cela simplifie CORS et centralise les appels.

### Configurer l'URL de l'API externe

Dans l'environnement PHP, definir:

- `OLTHEM_EXTERNAL_API_BASE`

Exemple:

`https://api.votre-domaine.com/`

Si rien n'est defini, le theme utilise `https://api.example.com/` (placeholder).

## 6) Endpoints externes attendus (base)

Le front appelle ces endpoints (via proxy):

- `GET /auth/me`
- `GET /content/sections`
- `GET /blog`
- `GET /blog/:slug`

Vous pouvez les adapter dans `js/app.js` selon votre backend.

## 7) Rewrites WordPress

Le theme ajoute des rewrites pour eviter les 404 sur les URLs SPA:

- sections,
- blog,
- admin-tool.

Si les nouvelles routes ne fonctionnent pas:

1. Ouvrir `Reglages > Permaliens` dans l'admin WordPress.
2. Cliquer sur `Enregistrer` sans rien changer.

Cela force WordPress a regenerer les regles.

## 8) Conseils pour la suite

1. Brancher les vrais endpoints backend.
2. Ajouter un vrai formulaire de login (toujours via API externe).
3. Ajouter la gestion fine des permissions (roles + droits d'action).
4. Ajouter des validations et messages d'erreur plus detailes dans AdminTool.