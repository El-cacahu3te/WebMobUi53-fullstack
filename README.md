# Intégration Sanctum SPA (Vue.js)

## Principe

Les composants Vue.js sont embarqués dans des pages Blade (même domaine). L'authentification se fait via le **cookie de session Laravel** — pas de Bearer token nécessaire côté front.

Les clients externes (mobile, scripts) peuvent continuer à utiliser les **Bearer tokens** Sanctum, les deux mécanismes coexistent sur les mêmes routes API.

---

## Configuration Sanctum

### `bootstrap/app.php`

Le middleware `EnsureFrontendRequestsAreStateful` a été ajouté au groupe `api`. Sans lui, Sanctum ignore la session Laravel sur les routes API et retourne systématiquement un 401.

```php
$middleware->prependToGroup('api', EnsureFrontendRequestsAreStateful::class);
```

### `config/sanctum.php`

Les domaines stateful sont déjà préconfigurés par défaut pour le développement local (`localhost`, `127.0.0.1:8000`, etc.). Aucune modification nécessaire en dev.

En production, définir dans `.env` :
```
SANCTUM_STATEFUL_DOMAINS=mondomaine.com
```

---

## Sécurité CSRF

Les requêtes mutantes (POST, PUT, PATCH, DELETE) requièrent le token CSRF. Laravel expose ce token via le cookie **`XSRF-TOKEN`** (non HttpOnly, lisible par JS).

### `resources/js/bootstrap.js`

Ce fichier est importé en premier dans chaque entrypoint Vue. Il lit le cookie `XSRF-TOKEN` et l'injecte dans les headers par défaut du composable `useFetchApi` via `setDefaultHeaders`.

```js
import { setDefaultHeaders } from './composables/useFetchApi';

function getXsrfToken() {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
  return match ? decodeURIComponent(match[1]) : null;
}

const xsrf = getXsrfToken();
if (xsrf) {
  setDefaultHeaders({ 'X-XSRF-TOKEN': xsrf });
}
```

Ce fichier configure aussi l'URL de base de l'API :

```js
setDefaultBaseUrl('/api/v1');
```

Ainsi les composants Vue appellent simplement `useFetchApi()` sans répéter l'URL de base, et les composants restent agnostiques du framework — toute la logique Laravel est isolée dans `bootstrap.js`.

---

## Protection SameSite

Le cookie de session est configuré en `SameSite=Strict` pour bloquer nativement les attaques CSRF cross-site.

Dans `.env` :
```
SESSION_SAME_SITE=strict
```

---

## Composable `useFetchApi`

Le composable `resources/js/composables/useFetchApi.js` gère les appels API avec :
- Header `Accept: application/json` → garantit une réponse JSON (pas de redirection) en cas de 401
- Header `X-Requested-With: XmlHttpRequest` → identifie la requête comme AJAX
- Header `X-XSRF-TOKEN` → injecté via `bootstrap.js` au démarrage

---

## Routes protégées

Les pages Blade hébergeant les SPA Vue sont dans le groupe `auth` de `routes/web.php` — un utilisateur non connecté est redirigé vers la page de login avant même de charger la page.

---

## Plusieurs apps Vue dans un même layout Blade

### Slot `scripts` dans le layout

Le layout `default-layout.blade.php` expose un slot optionnel `scripts` dans le `<head>`, après le CSS global :

```blade
@isset($scripts)
    {{ $scripts }}
@endisset
```

Chaque page Blade qui embarque une app Vue y injecte son entrypoint Vite via ce slot :

```blade
<x-default-layout>
    <x-slot:scripts>
        @vite(['resources/js/poll-dashboard-integrated.js'])
    </x-slot>

    <div id="app"></div>
</x-default-layout>
```

Ainsi chaque page charge uniquement son propre bundle JS — pas de JS chargé globalement pour toutes les pages.

### Layout standalone minimal

Pour une app Vue qui ne doit pas embarquer le header/footer Laravel, un layout dédié
`vue-app-layout.blade.php` est disponible. Il conserve uniquement :
- les métadonnées HTML (`title`, `description`, `viewport`)
- le CSS global via `@vite(['resources/css/app.css'])`
- le slot `scripts` pour injecter l'entrypoint Vite de la page

Exemple :

```blade
<x-vue-app-layout>
    <x-slot:title>
        Mon app Vue
    </x-slot>

    <x-slot:scripts>
        @vite(['resources/js/mon-app.js'])
    </x-slot>

    <div id="app"></div>
</x-vue-app-layout>
```

Le composant n'ajoute ni conteneur, ni navigation, ni footer, pour laisser l'app Vue
piloter entièrement sa mise en page.

### `vite.config.js`

Chaque entrypoint Vue est déclaré dans les `input` de Vite pour être compilé en bundle séparé :

```js
laravel({
    input: [
        'resources/css/app.css',
        'resources/js/poll-dashboard.js',
        'resources/js/poll-dashboard-integrated.js',
    ],
}),
```

### Entrypoints

Chaque entrypoint Vue importe `bootstrap.js` avant le montage de l'app :

```js
import './bootstrap';
import { createApp } from 'vue';
import App from './AppPollDashboardIntegrated.vue';
createApp(App).mount('#app');
```
