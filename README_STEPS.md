# Créer une application de sondage (Laravel + Vue.js)

## Introduction

Ce README retrace chaque fonctionalité implémentées et leur fonctionnement. 


## Boutons
    Mettre un bouton pour accéder directemenet à la page polls/dasboard dans le header via resources/views/components/default-layout.blade.php dans 

## Étape 1 : Architecture Backend + API de base

### Modèles Eloquent

#### `Poll.php`
- Ajout de `$fillable` avec tous les champs nécessaires :
  - `user_id` : lie le sondage à son créateur
  - `title`, `question` : contenu du sondage
  - `secret_token` : token unique dans l'URL de partage, permet d'identifier le sondage 
sans exposer son id. Les authentifiés peuvent voter, les non-authentifiés peuvent 
consulter les résultats si publics.
  - `is_draft` : booléen pour savoir si le sondage est en brouillon ou publié
  - `allow_multiple_choices` : autorise ou non plusieurs réponses
  - `allow_vote_change` : autorise ou non la modification d'un vote
  - `results_public` : contrôle si les résultats sont visibles avant la fin
  - `duration` : durée en minutes/heures du sondage après lancement
  - `started_at`, `ends_at` : dates de début et fin calculées au lancement
- Relations définies :
  - `user()` → BelongsTo User (le créateur)
  - `options()` → HasMany PollOption (les choix du sondage)
  - `votes()` → HasMany PollVote (tous les votes)

#### `PollOption.php`
- Ajout de `$fillable` : `['poll_id', 'label']`
  - `label` est le texte de l'option (ex: "Oui", "Non", "Peut-être")
- Relations :
  - `poll()` → BelongsTo Poll
  - `votes()` → HasMany PollVote (pour compter les votes par option)

#### `PollVote.php`
- Ajout de `$fillable` : `['poll_id', 'user_id', 'poll_option_id']`
  - `poll_id` : référence directe au sondage (évite des jointures)
  - `user_id` : qui a voté (pour empêcher le double vote)
  - `poll_option_id` : quelle option a été choisie
- Relations :
  - `poll()` → BelongsTo Poll
  - `user()` → BelongsTo User
  - `option()` → BelongsTo PollOption (clé étrangère explicite `poll_option_id`)

---

### Migrations

#### `poll_options`
- `id` auto-increment
- `poll_id` foreignId → contrainte sur `polls` avec cascade delete
  - Si un sondage est supprimé, ses options sont supprimées automatiquement
- `label` string : texte de l'option
- `timestamps()`

#### Pourquoi cascade delete ?
Pour ne pas laisser des options orphelines en base si le sondage parent est supprimé. C'est une règle d'intégrité référentielle.

---

### Routes API (`/api/v1/`)

#### Routes protégées (auth:sanctum requis)
| Méthode | Route | Action | Pourquoi protégée |
|---|---|---|---|
| GET | `/polls` | Liste des sondages de l'utilisateur | Chaque user voit ses propres sondages |
| POST | `/polls` | Créer un sondage | Doit être authentifié pour créer |
| GET | `/polls/{id}` | Détail d'un sondage | Propriétaire uniquement |
| PUT | `/polls/{id}` | Modifier un sondage | Propriétaire uniquement |
| DELETE | `/polls/{id}` | Supprimer un sondage | Propriétaire uniquement |
| POST | `/polls/{id}/vote` | Voter sur un sondage | Doit être connecté pour voter |
| GET | `/polls/{id}/results` | Voir les résultats | Conditionnel selon `results_public` |

#### Routes publiques (pas d'auth)
| Méthode | Route | Action | Pourquoi publique |
|---|---|---|---|
| GET | `/polls/token/{token}` | Accéder au sondage via lien partagé | Le vote public passe par ce token |

---

### Controller `ApiPollController`

- **Pourquoi un controller dédié API ?** Pour séparer la logique API JSON de la logique web classique. Chaque méthode retourne `response()->json()`.
- **Versioning `/api/v1/`** : permet de faire évoluer l'API sans casser les clients existants.
- **Logique de vote** :
  - Vérifie que le sondage n'est pas en draft
  - Vérifie que le sondage n'est pas expiré (`ends_at`)
  - Vérifie si l'utilisateur a déjà voté
  - Si `allow_vote_change` = true → on met à jour le vote existant
  - Si `allow_multiple_choices` = true → on accepte plusieurs `poll_option_id`
  - Sinon → on refuse le double vote


  ## Étape 2 — Dashboard (liste des sondages)

### Ce qui a été fait
- Enrichissement de `PollTable.vue` : affichage du titre, question tronquée, badge de statut, date de fin, lien de partage, boutons Éditer / Supprimer
- Enrichissement de `AppPollDashboard.vue` : en-tête avec bouton "Nouveau sondage"
- Badge de statut calculé côté client (Brouillon / Actif / Terminé) via `getPollStatus()`
- Lien de partage affiché uniquement si le sondage est lancé (pas brouillon)
- Bouton "Copier" avec feedback visuel "✓ Copié !" pendant 2 secondes
- `copiedId` (ref) pour cibler uniquement le bouton de la ligne copiée
- Fallback `execCommand('copy')` si `navigator.clipboard` indisponible (HTTP / vieux navigateurs)

### Pourquoi ces choix
- `ref(null)` pour `copiedId` plutôt qu'un booléen : permet de gérer N lignes indépendamment
- Statut calculé côté client : évite un champ redondant en DB, les données nécessaires (`is_draft`, `ends_at`) sont déjà présentes
- `shareLink()` séparée de `copyLink()` : séparation des responsabilités, `shareLink` peut être réutilisée ailleurs (ex: page de vote)
- `setTimeout` pour réinitialiser `copiedId` : non-bloquant, ne gèle pas le thread JS

## Étape 3 — Création / Édition / Suppression sondage + options

### Ce qui a été fait
- `PollForm.vue` : formulaire unifié create/edit avec options dynamiques (ajout/suppression, minimum 2)
- Initialisation brouillon à `true` par défaut à la création
- Affichage des erreurs de validation Laravel (422) par champ via `fieldErrors`
- Durée désactivée si sondage en brouillon (UI uniquement, la valeur est quand même sauvegardée)
- Redirection vers dashboard après succès
- `AppPollCreate.vue` / `AppPollEdit.vue` : points de montage distincts alimentés par Blade via `data-props`

### Pourquoi ces choix
- **`reactive()` pour le form** : les champs sont liés entre eux, plus naturel qu'un `ref` par champ
- **`fieldErrors` séparé de `error`** : distinction entre erreur globale et erreurs par champ, plus propre à l'affichage
- **Pas de Pinia** : le formulaire est local à la page, pas besoin de store global
- **Brouillon par défaut** : règle métier confirmée — un sondage ne doit jamais être actif sans action explicite du créateur

## Étape 4 et 5— Lien de partage + Page de vote + Page de résultats

## Ce qui a été fait

### 1. **Pages Blade de vote et résultats**

- `resources/views/polls/vote.blade.php` : page de vote publique (via token)
- `resources/views/polls/results.blade.php` : page de résultats publique (via token)
- Utilisation du layout existant `x-vue-app-layout` pour cohérence avec l'app
- Points de montage Vue : `poll-vote` et `poll-results`

### 2. **Composants Vue**

- `AppPollVote.vue` :
    
    - Charge le sondage via `GET /api/v1/polls/{token}`
    - Affiche titre, question, liste des options
    - Radio buttons (choix unique) ou checkboxes (choix multiple)
    - Envoie le vote via `POST /api/v1/polls/{token}/vote` (protégé `auth:sanctum`)
    - Désactive le formulaire si brouillon ou terminé
    - Feedback succès et gestion des erreurs
- `AppPollResults.vue` :
    
    - Charge sondage + résultats via `GET /api/v1/polls/{token}` et `GET /api/v1/polls/{token}/results`
    - Affiche barres de progression Tailwind avec comptages
    - Affiche nombre total de votes
    - Respecte le flag `results_public` (confidentiel sauf si créateur)
    - Polling automatique via `usePolling()` pour rafraîchissement temps réel

### 3. **Points d'entrée Vue**

- `resources/js/poll-vote.js` : initialise `AppPollVote.vue`
- `resources/js/poll-results.js` : initialise `AppPollResults.vue`

### 4. **Dashboard amélioré**

- `PollTable.vue` :
    - Badge statut dynamique (Brouillon / Actif / Terminé)
    - Lien de partage visible **uniquement si sondage actif**
    - Bouton "Copier" avec Clipboard API
    - Bouton "Voter" visible seulement si actif
    - Bouton "Résultats" visible si non brouillon

### 5. **Routes web**

- `GET /polls/{token}/vote` → `PollController@vote()` (groupe `auth`)
- `GET /polls/{token}/results` → `PollController@results()` (groupe `auth`)

---

## Ce qui a été corrigé

### **Backend — ApiPollController.php**

#### `store()`

- **Avant** : `is_draft` toujours forcé à `true`, impossible de créer actif
- **Après** : accepte `is_draft` depuis le frontend (défaut `false`)
- Si sondage actif + durée définie → `started_at` et `ends_at` calculés immédiatement

#### `update()`

- **Avant** : durée et dates ne se synchronisaient pas
- **Après** : détecte les changements de durée et basculement brouillon ↔ actif
- Recalcule `ends_at = now() + duration` quand nécessaire

#### `vote()`

- **Avant** : utilisation directe `PollVote::create()` → `poll_id = null` (erreur contrainte)
- **Après** : utilise `$poll->votes()->create([...])` (relation Eloquent) → `poll_id` toujours renseigné

### **Frontend — PollForm.vue**

#### Initialisation

- **Création** : `is_draft = false` par défaut (sondage actif immédiatement)
- **Édition** : respecte l'état existant

#### Gestion durée

- **Avant** : durée en minutes, désactivée quand brouillon coché
- **Après** : durée en **jours** (conversion ÷ 86400), toujours visible mais **désactivée si brouillon**
- Message clair : « La durée s'applique uniquement si le sondage est actif »

#### Label brouillon

- Clarifié : « Brouillon (coché = brouillon, décoché = actif) »

### **HTTP — useFetchApi.js**

#### Sanctum auth

- **Avant** : cookies de session non envoyés
- **Après** : ajout `credentials: 'same-origin'` dans `fetch()`
- Garantit que cookies XSRF/session passent à chaque requête

#### Helpers API

- Expose `get`, `post`, `put`, `del` pour simplifier appels depuis composants

---

## Pourquoi ces changements étaient nécessaires

|Problème|Impact|Solution|
|---|---|---|
|Layout manquant|Pages vote/results cassées|Utilisation `x-vue-app-layout`|
|`is_draft` forcé à true|Impossible créer sondage actif|Backend accepte valeur du frontend|
|`ends_at` non recalculé|Sondages paraissaient terminés immédiatement|Recalcul dans `store()` et `update()`|
|`poll_id = null` dans votes|Erreur contrainte DB NOT NULL|Utilisation relation `$poll->votes()->create()`|
|Durée désactivée si brouillon|UX confuse, impossible saisir durée|Durée toujours visible, désactivée si brouillon|
|Cookies Sanctum non envoyés|Erreur 401 "Unauthenticated" à chaque vote|`credentials: 'same-origin'` dans fetch|

---

## Fichiers clés

|Fichier|Rôle|Modifié ?|
|---|---|---|
|`app/Http/Controllers/Api/v1/ApiPollController.php`|Logique API (store, update, vote)|✅ Oui|
|`resources/js/components/PollForm.vue`|Formulaire création/édition|✅ Oui|
|`resources/js/components/PollTable.vue`|Dashboard avec lien de partage|✅ Oui|
|`resources/js/AppPollVote.vue`|Composant de vote public|🆕 Nouveau|
|`resources/js/AppPollResults.vue`|Composant de résultats public|🆕 Nouveau|
|`resources/js/poll-vote.js`|Point d'entrée Vue vote|🆕 Nouveau|
|`resources/js/poll-results.js`|Point d'entrée Vue résultats|🆕 Nouveau|
|`resources/views/polls/vote.blade.php`|Page Blade vote|🆕 Nouveau|
|`resources/views/polls/results.blade.php`|Page Blade résultats|🆕 Nouveau|
|`resources/js/composables/useFetchApi.js`|HTTP helper|✅ Oui|
|`routes/web.php`|Routes vote/results|✅ Oui|

## Étape 6 — Affichage conditionnel (état, droits, date de fin)

### Ce qui a été fait

**`AppPollVote.vue`**

- Sondage en brouillon (`is_draft = true`) → message d'avertissement, formulaire de vote masqué
- Sondage expiré (`ends_at` dépassé, calculé côté client via `computed`) → message "terminé", formulaire masqué, lien vers les résultats
- Erreurs API métier distinguées : déjà voté, sondage fermé, brouillon — chacune avec un message utilisateur lisible
- Vote réussi → redirection automatique vers les résultats après 2s

**`AppPollResults.vue`**

- Erreur 403 → message "Résultats privés" dédié (au lieu d'un message d'erreur générique)
- Badge statut dynamique : "Actif — résultats en direct" ou "Terminé"
- Polling automatique toutes les 5s — **s'arrête automatiquement** si le sondage est expiré ou si les résultats sont privés (évite des requêtes inutiles)
- Nettoyage de l'intervalle dans `onUnmounted` pour éviter les fuites mémoire

### Pourquoi ces choix techniques

- **`computed` pour `isExpired`** : recalculé automatiquement si `ends_at` change, sans logique manuelle. La date est comparée côté client — suffisant pour l'UX, le backend reste la source de vérité pour bloquer le vote.
- **Distinction 403 vs autres erreurs** : `useFetchApi` expose `err.status`, ce qui permet de séparer "résultats privés" (cas normal) d'une vraie erreur réseau.
- **Arrêt du polling si expiré** : inutile de poller un sondage terminé, ça économise des requêtes sans complexité supplémentaire.
- **`onUnmounted` + `clearInterval`** : bonne pratique Vue — sans ça, l'intervalle continue même si le composant est détruit.


## Consignes générales

Vous développerez une application web en deux parties :

- Backend Laravel : responsable de l'exposition des endpoints JSON utilisés par le frontend
- Frontend Vue.js : responsable de l'affichage et des interactions autour des sondages, utilisable
  sur navigateur et mobile, avec une approche mobile first

L'architecture frontend est libre : il est possible de réaliser soit une seule application Vue.js
couvrant l'ensemble des usages, soit plusieurs applications Vue.js distinctes (par exemple une pour
le dashboard et une pour la consultation, le vote et la visualisation des résultats), à condition
que l'ensemble reste cohérent, maintenable et bien intégré au backend.

Le système d'authentification est déjà en place. Il est externe à l'application Vue.js, hors du
périmètre du travail, et doit être conservé en l'état. Le frontend demandé doit s'intégrer à ce
mécanisme existant, sans le redévelopper ni le modifier.

Un fichier `README_FRONT.md` est fourni à la racine du projet pour documenter l'intégration
frontend existante. Des exemples de fetch vers l'API, d'intégration de plusieurs applications Vue
et d'eager loading sont déjà disponibles dans le code fourni, notamment dans les vues et fichiers
liés aux sondages ainsi que dans certains contrôleurs API. Ces exemples peuvent servir de base de
travail et de référence.

Les modèles sont déjà fournis. Vous devez construire autour de ceux-ci les fonctionnalités utiles au
frontend. Les modèles existants permettent naturellement de représenter plusieurs choix pour un même sondage.
Par conséquent, lorsqu'un sondage est configuré en choix unique, l'unicité du vote doit être
garantie à la fois côté frontend et côté API.

Fonctionnalités attendues :

- afficher la liste des sondages de la personne connectée
- permettre la création, l'édition et la suppression d'un sondage depuis le frontend
- gérer les options d'un sondage
- gérer les paramètres du sondage (brouillon, lancement, choix simple ou multiple, résultats publics,
  dates ou durée)
- permettre au créateur d'obtenir facilement le lien de partage contenant le token
- afficher un sondage accessible via un token
- permettre à une personne authentifiée de voter via ce lien
- empêcher le vote après la date de fin d'un sondage avec durée, avec un affichage clair de cet état
- permettre l'accès anonyme aux résultats uniquement lorsqu'ils sont publics
- afficher les résultats via polling avec un aperçu graphique visualisant leur évolution
- garantir côté frontend et côté API l'unicité du vote pour les sondages à choix unique

Bonus possible :

- permettre de configurer si le vote peut être modifié après soumission
- permettre, si le sondage l'autorise, de modifier un vote déjà soumis

La structure exacte de l'interface est libre, à condition que l'application reste claire,
fonctionnelle et cohérente.

## Évaluation

Chaque partie du projet sera évaluée selon plusieurs catégories. L'évaluation portera sur :

- la qualité du frontend
- le bon fonctionnement des endpoints JSON nécessaires à ce frontend
- la capacité à expliquer, défendre et adapter son code à l'oral

Conditions particulières :

- toute triche avérée entraîne la note de `1` et aucune possibilité de remédiation ne sera proposée
- l'oral a un poids important dans l'évaluation afin de contrebalancer l'usage des IA et de vérifier
  la maîtrise réelle du travail rendu

Note maximale : `(nombre de points obtenus / nombre de points maximum) x 5 + 1`

## Critères frontend et endpoints JSON

Les informations ci-dessous sont à titre indicatif et peuvent être adaptées.


### Critères rendu

| # | Critère |
| --- | --- |
| 1 | Affichage d'un dashboard des sondages de la personne connectée
| 2 | Création, édition et suppression d'un sondage depuis le frontend
| 3 | Gestion des options du sondage (ajout, modification, suppression)
| 4 | Gestion des paramètres du sondage (brouillon, choix multiples, résultats publics, durée)
| 5 | Récupération simple du lien de partage contenant le token et affichage d'un sondage accessible via ce lien
| 6 | Soumission d'un vote valide depuis le frontend, avec unicité correctement garantie pour les sondages à choix unique
| 7 | Affichage conditionnel correct selon l'état du sondage, la date de fin et les droits d'accès, y compris l'accès anonyme aux résultats publics
| 8 | Consommation correcte des endpoints JSON par le frontend
| 9 | Gestion correcte des erreurs utilisateur côté frontend
| 10 | Interface lisible, claire, responsive et agréable à utiliser
| 11 | Affichage en temps réel, via polling, des résultats, avec aperçu graphique
| 12 | Le projet est fonctionnel de bout en bout
| 13 | Code lisible, structuré, `README` clair et utilisation correcte du contrôle de version
| 14 | Bon usage des composants Vue, des composables et d'une architecture cohérente du code
| 15 | Nommage, lisibilité et organisation générale du frontend (et routes API backend) soignés

Bonus possible : prise en charge du changement de vote lorsqu'un sondage l'autorise


## Critères présentation

| # | Critère |
| --- | --- |
| 1 | Les informations sont claires et bien présentées
| 2 | Les réponses aux questions sont pertinentes
| 3 | La capacité à modifier le code en direct selon une demande est satisfaisante
| 4 | La compréhension théorique de Vue.js, des échanges frontend/backend et de l'architecture fullstack est bonne
| 5 | La personne démontre qu'elle maîtrise réellement le code présenté, y compris si des outils d'IA ont été utilisés

## Contraintes techniques

- Backend Laravel >= 12.x
- Frontend Vue.js >= 3.4
- Base de données relationnelle (`SQLite`, `MySQL` ou `PostgreSQL`)
- Projet disponible sur GitHub
- Une documentation minimale (`README.md`) doit permettre de tester facilement l'application
- Les modèles et migrations sont fournis, mais les endpoints JSON nécessaires au frontend doivent
  être implémentés
- L'usage de l'IA est autorisé, mais le code rendu doit être compris, maîtrisé et défendable à l'oral
- Les critères liés à l'architecture, au découpage du code, au nommage et à la lisibilité auront une
  importance particulière
- L'usage d'outils d'IA ne dispense pas d'un regard critique : un code trop verbeux, mal structuré ou
  peu cohérent sera pénalisé

## Conseils

- Ne cherchez pas à faire complexe : commencez simple, itérez ensuite.
- Travaillez de manière incrémentale et validez chaque étape.
- Testez tôt et souvent.
- Une fonctionnalité simple mais fiable vaut mieux qu'une fonctionnalité ambitieuse inachevée.
- Structurez clairement les données échangées entre votre frontend et votre API JSON.

## Livrables et rendu

Vous devez fournir :

- l'URL du dépôt GitHub
- un fichier `README.md` clair pour expliquer l'installation et les choix techniques
- Il est possible de mettre à jour le dépôt entre le jour du rendu et l'examen
- Seul le code présent avant l'échéance sera évalué pour le rendu
- Le code ajouté ou modifié après l'échéance ne sera pas évalué pour la note de rendu, mais pourra
  éventuellement aider lors de la présentation orale

Rendu final : au plus tard le dimanche 17 mai 2026 à 23:59:59 UTC (date du commit).

La présentation orale aura lieu lors de la période des examens et sera probablement d'une durée de 20 minutes par étudiant.
