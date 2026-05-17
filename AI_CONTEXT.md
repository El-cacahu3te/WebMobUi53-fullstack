# AI Context for WebMobUI Project

## 1. Stack & configuration détectée

## 0. Exigences fonctionnelles (résumé)

- Créer un sondage (question, options, paramètres) depuis l’interface Vue.
- Gérer le statut : **brouillon** (non votable) puis **actif**.
- Permettre choix **simple** (un seul choix) ou **multiple**.
- Permettre `results_public` : résultats visibles publiquement uniquement si activé (sinon réservés au créateur).
- Permettre une **durée** (date de fin) et empêcher le vote après expiration.
- Proposer un **lien de partage** contenant un token dans l’URL.
- Page de **vote** accessible via token : **requiert authentification**.
- Page **résultats** accessible via token : **public si `results_public`**, sinon restreint.
- Afficher sur la page de vote des **résultats en direct** via polling + aperçu graphique.
- Empêcher le vote lorsque la date de fin est dépassée (affichage clair).
- Garantir côté frontend et API l’**unicité** du vote pour les sondages à choix unique.

- Backend: Laravel projet (`composer.json` avec `laravel/framework` `^12.0`, PHP `^8.2`).

- Frontend: Vue 3 (`vue` `^3.5.18`, `@vitejs/plugin-vue`), Vite 7, Tailwind CSS 4 via `@tailwindcss/vite`.

- Documentation: `README_STEPS.md` documente l’alignement de la fonctionnalité “lien de partage token + accès au sondage via lien” comme **critère rendu #5** (plutôt qu’une “Étape 5” technique dédiée), et la fonctionnalité est couverte techniquement via les pages vote/résultats et les endpoints token.

- API: Laravel Sanctum est activé (`laravel/sanctum` et `HasApiTokens` sur le modèle `User`).
- Base de données: config `config/database.php` par défaut `sqlite`, mais les connexions MySQL, MariaDB et PostgreSQL sont présentes. Le fichier `.env` n’est pas fourni dans le dépôt, seulement `.env.example`.
- Build: `package.json` expose `npm run dev` et `npm run build`. `composer.json` contient aussi `composer run dev` et `php artisan` commands.
- CSS: `resources/css/app.css` importe Tailwind (`@import 'tailwindcss';`).

## 2. Modèles & base de données

### Modèles de sondage

- `app/Models/Poll.php`
  - Champs remplissables : `user_id`, `title`, `question`, `secret_token`, `is_draft`, `allow_multiple_choices`, `allow_vote_change`, `results_public`, `duration`, `started_at`, `ends_at`.
  - Relations : `user()`, `options()`, `votes()`.

- `app/Models/PollOption.php`
  - Champs remplissables : `poll_id`, `label`.
  - Relations : `poll()`, `votes()`.

- `app/Models/PollVote.php`
  - Champs remplissables : `poll_id`, `user_id`, `poll_option_id`.
  - Relations : `poll()`, `user()`, `option()`.

- `app/Models/User.php`
  - Relations ajoutées : `polls()` et `pollVotes()`.
  - Auth model standard avec `HasApiTokens`.

### Migrations fournies

- `database/migrations/2026_04_19_161823_create_polls_table.php`
  - `polls` contient `secret_token` unique, booleans pour brouillon / choix multiple / changement de vote / résultats publics, durée en secondes, timestamps de début/fin.
- `database/migrations/2026_04_19_161825_create_poll_options_table.php`
  - `poll_options` lie une option à un sondage.
- `database/migrations/2026_04_19_161826_create_poll_votes_table.php`
  - `poll_votes` lie vote utilisateur à option et sondage.

### Règles métier détectées

- Sondage créé par défaut actif lorsque le frontend envoie `is_draft = false`; un bug antérieur enregistrait systématiquement en brouillon.
- `vote` autorisé seulement si sondage non brouillon et si `ends_at` n’est pas dépassé.
- Pour un sondage simple, l’API bloque `option_ids` de longueur > 1.
- Si `allow_vote_change` est faux, un second vote est refusé.
- Si `allow_vote_change` est vrai, les anciens votes pour ce sondage sont supprimés et remplacés.
- `results` public accessibles si `results_public` vrai, sinon uniquement propriétaire.
- `ends_at` est recalculé lors du passage du sondage hors brouillon ou lorsque la durée change.

## 3. Auth

### Système en place

- Auth web standard avec `AuthController` : `showRegister`, `register`, `showLogin`, `login`, `logout`.
- `routes/web.php` utilise `auth` middleware pour :
  - `/polls/dashboard`
  - `posts` CRUD (sauf index/show ouverts)
  - `my-profile`
  - `likes` update
  - `tokens` CRUD partiel
- `routes/api.php` protège les endpoints API de sondages par `auth:sanctum` pour index/store/update/delete/vote.
- Un endpoint API `/user` protégé `auth:sanctum` renvoie l’utilisateur authentifié.

### Ce qui ne doit pas être touché

- Le système d’authentification de session classique et les routes `auth` existantes.
- Les endpoints Sanctum/capacités pour `posts` et la gestion des tokens personnels.
- La logique d’authentification utilisateur dans `AuthController`.

## 4. Backend — état actuel

### Routes API existantes

#### `routes/api.php`

- `GET /api/user` → retourne l’utilisateur via auth Sanctum.
- `apiResource('v1/posts', ApiPostController::class)` avec middleware de permissions sur `posts:*`.
- Authentifié (`auth:sanctum`) :
  - `GET /api/v1/foo` → `ApiFooController@show`
  - `POST /api/v1/foo` → `ApiFooController@store`
  - `GET /api/v1/polls` → `ApiPollController@index`
  - `POST /api/v1/polls` → `ApiPollController@store`
  - `DELETE /api/v1/polls/{id}` → `ApiPollController@remove`
  - `PUT /api/v1/polls/{id}` → `ApiPollController@update`
  - `POST /api/v1/polls/{token}/vote` → `ApiPollController@vote`
- Public :
  - `GET /api/v1/polls/{token}` → `ApiPollController@show`
  - `GET /api/v1/polls/{token}/results` → `ApiPollController@results`

### Ce qui est implémenté

- Dashboard API de sondages de l’utilisateur connecté (`index`).
- CRUD backend complet : création (`store`), mise à jour (`update`), suppression (`remove`).
- Endpoint de vote avec gestion choix unique/multiple, changement de vote, invalidation de vote sur date de fin.
- Endpoint public de lecture d’un sondage par token (`show`) et de résultats (`results`).
- Génération d’un `secret_token` unique pour partage.
- Gestion de la publication/durée dans `store()` et `update()` : `is_draft`, `started_at`, `ends_at`.
- Correction de vote via relation (`$poll->votes()->create(...)`) pour éviter `poll_id` null.

### Ce qui manque / probable gap

- Pas de validation de droits plus fine côté API pour le propriétaire au-delà de `user_id` sur `update`/`remove`.
- Pas de endpoints explicites pour gestion des options séparément : les options sont gérées dans `options` comme tableau à `store`/`update`.
- Pas de tests automatisés de bout en bout pour la création / vote / résultats.
- Pas de contrainte DB unique sur vote utilisateur + sondage / option pour garantir unique à 100% au niveau DB.

### Pattern de réponse API

- Structure JSON mixte :
  - `return $poll;` ou `return $poll->load('options')` pour renvoyer des modèles.
  - `response()->json(['message' => '...'], status)` pour erreurs et succès.
- Pas d’enveloppe uniforme (`data`, `errors`, etc.) visible : retour direct de modèle ou message.

## 5. Frontend — état actuel

### Composants Vue existants

- `resources/js/AppPollDashboard.vue`
  - Charge les props `polls`, `loginUrl`, `username` depuis un `div` blade.
  - Monte `PollTable`.

- `resources/js/AppPollCreate.vue`
  - Monte `PollForm` en mode `create`.

- `resources/js/AppPollEdit.vue`
  - Monte `PollForm` en mode `edit` avec `initialPoll` injecté depuis Blade.

- `resources/js/components/PollForm.vue`
  - Formulaire dynamique de création/édition de sondage.
- **Création** : sondage en brouillon par défaut (`is_draft = true`) afin qu’un sondage n’entre en actif que lorsqu’il est explicitement publié.

  - **Durée** : affichée en jours (conversion ×86400 secondes). Désactivée si brouillon, activée si actif.
  - Gère les options dynamiques (ajout/suppression) et les paramètres `is_draft`, `allow_multiple_choices`, `allow_vote_change`, `results_public`, `duration`.
  - Message clair : « La durée ne s'applique que si le sondage est actif ».
  - Utilise `useFetchApi('/api/v1')` pour POST/PUT avec `credentials: 'same-origin'` (Sanctum cookies).

- `resources/js/components/PollTable.vue`
  - Affiche un tableau de sondages.
  - Bouton suppression par ligne.
  - Présente `id`, `title`, `question`, `is_draft`, `started_at`, `ends_at`.
  - Style scoped minimal pour bouton rouge.

### Frontend store / state

- `resources/js/stores/usePollStore.js`
  - Composable réactif custom basé sur `ref([])`.
  - Fournit `setPolls`, `deletePoll`.
  - Utilise `useFetchApi` pour DELETE.
  - N’est pas une vraie installation Pinia ; pas de package `pinia` dans `package.json`.

### Composables détectés

- `resources/js/composables/useFetchApi.js` — gestion HTTP `fetch` avec base URL `/api/v1`, timeout, parsing JSON, erreurs.
  - **Correction Sanctum** : `credentials: 'same-origin'` ajouté pour que cookies de session/XSRF soient envoyés.
- `resources/js/composables/useFetchJson.js` — wrapper `fetch` plus simple.
- `resources/js/composables/useHashRoute.js` — routeur hash navigateur custom, mais il n’est pas clairement utilisé dans le code chargé.
- `resources/js/composables/useJsonStorage.js` — sauvegarde de données réactives dans `localStorage`.
- `resources/js/composables/usePolling.js` — intervalle de polling automatique.

### Vue Router / architecture SPA

- Aucune configuration explicite de Vue Router.
- L’application est montée dans plusieurs pages Blade distinctes : `polls.dashboard`, `polls.create`, `polls.edit`.
- Chaque page Vue a son propre point de montage (`poll-dashboard.js`, `poll-create.js`, `poll-edit.js`).
- La navigation est majoritairement gérée par routes Laravel côté serveur.

### Points frontend notables

- `resources/js/poll-dashboard.js` monte l’app Vue et passe les données initiales via `data-props` dans Blade.
- `resources/js/poll-create.js` et `resources/js/poll-edit.js` existent pour les pages de création et d’édition de sondage.
- `resources/js/components/PollForm.vue` est le composant central pour la création/édition.
- Il y a un package `usePolling.js`, mais aucun composant de polling automatique visible dans le code de sondage actuel.
- `resources/css/app.css` est importé dans Vite ; Tailwind est disponible mais l’UI de sondage actuelle utilise surtout des classes utilitaires simples.

## 6. Décisions implicites détectées

- Architecture mixte Laravel Blade + Vue : Blade pour pages et insertion d’une application Vue là où nécessaire.
- Vue utilisée de manière légère, sans router ni Pinia officiel.
- API versionnée comme `/api/v1/...` pour les ressources publiques/privées.
- `PollController` API traite les options comme un tableau imbriqué plutôt que ressource séparée.
- L’UI côté sondage renvoie les données initiales du contrôleur Laravel au composant Vue.
- Usage de `secret_token` dans les polls comme identifiant public pour lecture/vote.

## 7. Écarts avec les exigences du prof

### Fonctionnalités manquantes ou incomplètes

1. Dashboard :
   - Implémenté partiellement via `polls.dashboard` et `PollTable`.
   - Manque pagination/filtrage, mais la liste est présente.

2. CRUD complet d'un sondage :
   - Backend existe pour create/update/delete.
   - Frontend dispose maintenant de pages de création (`polls.create`) et d’édition (`polls.edit`) avec `PollForm.vue`.

3. Gestion des options :
   - Backend gère les options par `options[]` en payload.
   - Frontend dispose à présent d’une interface d’ajout/suppression d’options.

4. Paramètres avancés :
   - Champs backend présents (`is_draft`, `allow_multiple_choices`, `allow_vote_change`, `results_public`, `duration`, `ends_at`).
   - L’UI de création/édition expose ces paramètres.

5. Lien de partage via token :
   - Backend génère `secret_token` et endpoints publics existent pour consultation.
   - Frontend dispose maintenant de pages de vote et de résultats par token.
   - Règle : le lien reste affiché uniquement pour le créateur dans le dashboard privé ; le destinataire doit être authentifié pour voter.

6. Vote par utilisateur authentifié uniquement :
   - Backend oblige déjà `auth:sanctum` pour `POST /api/v1/polls/{token}/vote` → ✅ conforme.
   - Frontend a désormais une page/composant de vote fonctionnelle.

7. Affichage conditionnel :
   - Backend a des checks sur état brouillon et date de fin.
   - L’UI expose partiellement ces états dans les pages vote/résultats, mais des améliorations UX restent possibles.

8. Polling régulier + aperçu graphique :
   - Composable `usePolling` existe mais n’est pas utilisé pour les résultats de sondage.
   - Aucun composant graphique visible.

9. Gestion des erreurs frontend :
   - `useFetchApi` gère les erreurs côté réseau.
   - Il n’y a pas d’affichage utilisateur d’erreurs dans l’UI sondage.

10. Interface responsive, mobile first :
   - Le seul composant Vue de sondage est un tableau responsive minimal.
   - Pas de preuve claire d’une interface mobile-first complète.

11. Endpoints JSON versionnés :
   - Implémenté correctement avec `/api/v1/...`.

12. Auth existante :
   - Implémentée via Laravel session et Sanctum ; bien intégré.

### Contraintes techniques

- Laravel >=12 : validé.
- Vue >=3.4 : validé.
- DB supportée : SQLite est le défaut ; MySQL/PostgreSQL sont configurables.
- Modèles/migrations : fournis et utilisés.
- README : présent avec instructions d’installation.
- Architecture frontend : mixte Blade + Vue, pas totalement SPA mais cohérente.
- Composants / composables : présents.
- Stores Pinia : absent. Le projet utilise un store Vue custom `usePollStore`.

## 8. Spécifications confirmées par le prof

1. **Vote** : L'authentification est **obligatoire**. Aucun vote public n'est autorisé. Le endpoint `/api/v1/polls/{token}/vote` doit rester protégé par `auth:sanctum`.

2. **Lien de partage** : Affiché **uniquement dans le dashboard privé** du créateur. Le créateur voit le lien (avec le token), le copie et l'envoie manuellement. Le destinataire reçoit le lien, accède à la page de vote **seulement s'il est authentifié**.

3. **Route de tokens** : Maintenir la séparation existante. `TokenController` gère les tokens API Sanctum. Les tokens de sondage (`secret_token` sur le modèle `Poll`) passent par les endpoints de `ApiPollController`, pas besoin d'une route supplémentaire.

4. **Store Vue** : Un store Vue custom est **accepté**. Pinia officiel n'est pas obligatoire. Le projet continuera avec `usePollStore` custom.

5. **Multi-langue** : **Pas obligatoire** pour les nouveaux écrans. Reste une possibilité, mais le focus est sur la fonctionnalité de sondage.

---

## 9. Correctifs implémentés pour étape 3 → étape 4

### Problème : Sondage figé en brouillon et durée non fonctionnelle

**Symptômes** :
- Sondages toujours créés en brouillon, impossible de les rendre actifs.
- Durée impossible à saisir sans cocher/décocher le brouillon.
- Sondages affichés comme "terminés" alors qu'ils venaient d'être créés.

**Correctifs appliqués** :

#### Backend (`app/Http/Controllers/Api/v1/ApiPollController.php`)

1. **`store()`** :
   - Accepte désormais `is_draft` depuis le frontend (ajout validation).
   - Ne force plus `true` ; utilise la valeur envoyée (défaut `false`).
   - Si sondage actif + durée définie → `started_at` et `ends_at` calculés immédiatement.

2. **`update()`** :
   - Détecte si durée a changé ou si sondage passe de brouillon à actif.
   - Recalcule `ends_at = now() + duration` quand nécessaire.
   - Évite les surécritures inutiles des dates.

3. **`vote()`** :
   - Correction : utilise `$poll->votes()->create([...])` (relation) au lieu de `PollVote::create(...)`.
   - Garantit que `poll_id` est toujours renseigné, évite contrainte NOT NULL.

4. **Vote/résultats frontend** :
   - Pages Blade `polls.vote` et `polls.results` corrigées (`x-vue-app-layout`, vue de fichier renommé).
   - Composants `AppPollVote.vue` et `AppPollResults.vue` exploitent les helpers API `useFetchApi.js`.

#### Frontend (`resources/js/components/PollForm.vue`)

1. **Initialisation** :
   - Création : `is_draft = false` par défaut (sondage actif immédiatement).
   - Édition : respecte l'état existant du sondage.

2. **Durée** :
   - Affichée en **jours** au lieu de minutes (UX plus claire, conversion ÷ 86400).
   - Champ visible en permanence mais **désactivé si brouillon**.
   - Message utilisateur : « La durée s'applique uniquement si le sondage est actif ».
   - Conversion frontend → backend : jours × 86400 = secondes.

3. **Case "Brouillon"** :
   - Label clarifié : « Brouillon (coché = brouillon, décoché = actif) ».
   - Toggle direct sans cocher/décocher paradoxal.

#### HTTP (`resources/js/composables/useFetchApi.js`)

1. **Sanctum auth** :
   - Ajout `credentials: 'same-origin'` dans `fetch()`.
   - Garantit que cookies de session/XSRF sont envoyés à chaque requête.
2. **Helpers API** :
   - `get`, `post`, `put`, `del` exposés pour simplifier les appels depuis `AppPollVote.vue` et `AppPollResults.vue`.

### Résultat

- ✅ Sondages créés **actifs** par défaut.
- ✅ Durée **saisissable** dès la création.
- ✅ Basculement brouillon ↔ actif **fonctionne** directement.
- ✅ Vote fonctionne sans erreur NOT NULL.
- ✅ Dates de fin **calculées correctement** et persistées.

### Étape 4 — Prochaines étapes

- [x] Frontend : page `/vote/{token}` pour voter publiquement (authentifié) implémentée.
- [x] Frontend : page résultats graphiques `/results/{token}` implémentée.
- [x] Frontend : lien de partage manipulable dans le dashboard.
- [x] Frontend : interface de vote avec choix unique/multiple.
- [x] Frontend : affichage du statut sondage dans les pages vote/résultats.
- [ ] Tests : vérifier contrainte unique vote utilisateur + sondage.
- [ ] Tests : intégration complète création → vote → résultats.

---

> Ce fichier est fondé uniquement sur le code présent dans le dépôt et sur les fichiers accessibles. Toute information dépendante de `.env` ou d'un déploiement réel n'a pas pu être confirmée.

