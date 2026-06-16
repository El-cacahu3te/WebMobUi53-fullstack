# Guide de présentation orale — Application de sondage Laravel + Vue.js

---

## PARTIE 1 — Présentation (≈ 10 min)

---

### 1A — Démo des fonctionnalités (max 5 min)

Suivre cet ordre exact. Chaque étape enchaîne naturellement sur la suivante.

**1. Dashboard** (`/polls/dashboard`)
- Montrer la liste avec les badges Brouillon / Actif / Terminé
- Montrer le responsive : réduire la fenêtre → table devient cards mobiles

**2. Créer un sondage actif**
- Cliquer "+ Nouveau sondage"
- Remplir : titre, question, 3 options (en ajouter une dynamiquement)
- Montrer les 4 toggles (choix multiples, modification du vote, résultats publics, brouillon)
- Durée : 7 jours — montrer que le champ se désactive si brouillon coché
- Soumettre → redirection dashboard → sondage "Actif"

**3. Lien de partage**
- Cliquer "Copier" → feedback "✓ Copié" 2 secondes
- Ouvrir l'URL dans un onglet privé (non connecté) → redirigé vers login (vote protégé)
- Se connecter → page de vote s'affiche

**4. Voter**
- Sélectionner une option (radio ou checkbox selon config)
- Cliquer "Voter" → feedback succès → redirection automatique vers résultats après 2s

**5. Résultats en direct**
- Montrer les barres de progression animées
- Ouvrir DevTools → Network → montrer les requêtes toutes les 5s (polling)
- Badge "● En direct" / "Actualisation..."
- Ouvrir un second onglet, voter à nouveau → les barres se mettent à jour sans rechargement

**6. Cas d'erreur**
- Retourner sur la page de vote → "Vous avez déjà voté"
- Montrer un sondage avec résultats privés → "🔒 Résultats privés"

---

### 1B — Architecture du projet

#### Pourquoi plusieurs apps Vue distinctes plutôt qu'une SPA ?

Le projet utilise **une app Vue par page Blade** plutôt qu'un router Vue global. Ce choix est cohérent avec l'architecture Laravel existante : le routage est géré par Laravel (Blade + web.php), Vue gère uniquement l'interactivité de chaque page. Ça évite de recréer un système de routing côté client alors qu'on en a déjà un côté serveur.

Chaque page Blade charge son propre entrypoint JS via Vite :
```
poll-dashboard.js  → AppPollDashboard.vue
poll-create.js     → AppPollCreate.vue  → PollForm mode="create"
poll-edit.js       → AppPollEdit.vue    → PollForm mode="edit"
poll-vote.js       → AppPollVote.vue
poll-results.js    → AppPollResults.vue
```

#### Composants Vue et leur rôle

| Composant | Rôle | Pourquoi ce découpage |
|---|---|---|
| `AppPollDashboard.vue` | Point de montage du dashboard, initialise le store | Reçoit les polls via `data-props` Blade (eager loading) |
| `PollTable.vue` | Affiche la liste, gère suppression et copie de lien | Séparé pour ne pas surcharger AppPollDashboard |
| `PollForm.vue` | Formulaire unifié create + edit via prop `mode` | Évite la duplication : la logique est identique, seul l'URL d'envoi change |
| `AppPollCreate.vue` / `AppPollEdit.vue` | Points de montage minimalistes | Délèguent tout à PollForm, servent juste de colle entre Blade et Vue |
| `AppPollVote.vue` | Page de vote publique (via token) | Chargement du sondage, gestion radio/checkbox, soumission |
| `AppPollResults.vue` | Résultats en temps réel | Polling, graphique barres, badge statut |
| `AlertMessage.vue` | Composant d'alerte réutilisable | Utilisé dans tous les formulaires et pages — 4 types : error/success/warning/info |

#### Composables et leur rôle

**`useFetchApi`** (fourni par le prof, adapté)
- Centralise toute la logique HTTP : headers, timeout (5s), gestion erreurs JSON/réseau, AbortController
- Expose `fetchApi`, `get`, `post`, `put`, `del`
- Les composants l'importent avec `useFetchApi()` sans argument → l'URL de base vient de `bootstrap.js`

**`bootstrap.js`** (importé en premier dans chaque entrypoint)
- Configure deux choses globales avant que Vue monte :
  1. `setDefaultBaseUrl('/api/v1')` → tous les appels partiront de `/api/v1`
  2. `setDefaultHeaders({ 'X-XSRF-TOKEN': ... })` → lit le cookie XSRF de Laravel
- C'est le point de configuration unique : si l'URL de l'API change, on ne la modifie qu'ici

**`usePollStore`**
- Contient un `ref([])` déclaré **en dehors** de la fonction exportée → état partagé en mémoire
- `AppPollDashboard` initialise via `setPolls(props.polls)`, `PollTable` lit et supprime via `deletePoll`
- Sans store, il faudrait passer `polls` en prop de Dashboard → Table, et `$emit('delete')` de Table → Dashboard : prop drilling inutile pour deux composants qui partagent le même état

**`usePolling`**
- Prend une fonction `fn`, un intervalle (défaut 5000ms) et un flag `immediate`
- Dans `onMounted` : appel immédiat si `immediate=true`, puis `setInterval`
- Dans `onUnmounted` : `clearInterval` → pas de fuite mémoire si le composant est détruit
- Le composant sait *quoi* faire (`fetchResults`), le composable sait *quand* le faire

---

### 1C — Gestion des échanges front–back

#### Flux de données au chargement du dashboard

```
Laravel (PollDashboardController)
  → eager load : $user->polls()->with('options')->get()
  → passe en JSON via data-props sur le div#app
  → AppPollDashboard reçoit via defineProps({ polls })
  → setPolls(props.polls) initialise le store
  → PollTable lit polls depuis le store, affiche
```
**Pourquoi eager loading ?** Évite N+1 requêtes. Sans `with('options')`, chaque sondage déclencherait une requête SQL supplémentaire pour charger ses options.

#### Flux d'un appel API depuis un composant

```
Composant appelle useFetchApi()
  → hérite de defaultBaseUrl='/api/v1' (posé par bootstrap.js)
  → hérite de X-XSRF-TOKEN (posé par bootstrap.js)
  → credentials: 'same-origin' → le cookie de session Laravel est envoyé
  → Laravel valide le token CSRF + la session → autorise la requête
```

#### Flux de création d'un sondage

```
PollForm.submit()
  → construit payload : { ...form, options: [...], duration: jours * 86400 }
  → POST /api/v1/polls
  → ApiPollController::store()
      → valide les données (422 si erreur)
      → génère secret_token = bin2hex(random_bytes(16))
      → crée Poll + chaque PollOption via $poll->options()->create()
      → retourne Poll avec options (201)
  → PollForm reçoit succès → window.location.href = '/polls/dashboard'
```

#### Flux d'un vote

```
AppPollVote.submitVote()
  → POST /api/v1/polls/{token}/vote avec { option_ids: [id] }
  → ApiPollController::vote()
      → vérifie is_draft → 404 si brouillon
      → vérifie ends_at → 403 si expiré
      → vérifie unicité : PollVote existe déjà ?
          → si oui et allow_vote_change=false → 403
          → si oui et allow_vote_change=true → supprime anciens votes
      → crée PollVote via $poll->votes()->create()
      → 201
  → Frontend : feedback succès → setTimeout 2s → redirect résultats
```

#### Gestion des erreurs 422 (validation Laravel)

Quand Laravel retourne une erreur 422, la réponse JSON contient :
```json
{ "errors": { "question": ["Le champ est requis."], "options": ["Min 2 options."] } }
```
`PollForm` stocke ça dans `fieldErrors` (ref) → chaque champ affiche son erreur sous lui. Séparé de `alertMsg` qui affiche l'erreur globale.

---

### 1D — Stack technique

| Outil | Rôle | Pourquoi |
|---|---|---|
| **Laravel 12** | Backend, API JSON, auth, ORM | Framework du cours, Sanctum intégré |
| **Vue.js 3.4** | Frontend réactif, Composition API | Réactivité fine, `<script setup>` concis |
| **Tailwind CSS** | Style | Pas de CSS custom, classes utilitaires, responsive mobile-first natif |
| **Vite** | Bundler | Ultra-rapide, HMR, intégration Laravel officielle |
| **SQLite** | Base de données | Zero config en dev, fichier local |
| **Sanctum** | Authentification SPA | Léger, basé sur cookies de session, pas d'OAuth2 nécessaire |
| **Concurrently** | Lance PHP + Queue + Vite en parallèle | Un seul `composer run dev` |

**Pas de Pinia** : le store `usePollStore` est un composable Vue natif. Pinia serait adapté pour un grand projet multi-store, ici c'est surdimensionné.

**Pas de Chart.js** : les barres de progression sont en CSS Tailwind pur. Zero dépendance externe, animation fluide via `transition-all duration-500`, suffisant pour ce besoin.

---

## PARTIE 2 — Phase de questions (≈ 10 min)

---

### Questions sur la réactivité

**"Expliquez un cas d'usage de la réactivité dans votre projet."**

> Dans `AppPollResults.vue`, `isExpired` est une `computed` :
> ```js
> const isExpired = computed(() => {
>   if (!results.value?.ends_at) return false
>   return new Date(results.value.ends_at) < new Date()
> })
> ```
> Quand `fetchResults` met à jour `results.value`, Vue détecte le changement et recalcule automatiquement `isExpired`. Le template réagit : le badge passe de "En direct" à "Terminé", le lien de clôture s'affiche. Je n'ai rien à appeler manuellement.

**"Une `computed` aurait-elle aussi fait l'affaire à la place de `usePolling` ?"**

> Non. Une `computed` est réactive aux données Vue — elle se recalcule quand une dépendance réactive change. Elle ne déclenche pas d'effets de bord comme des appels HTTP. `usePolling` utilise `setInterval`, qui est un effet de bord temporel. Ces deux mécanismes sont orthogonaux : `computed` pour dériver des données, `setInterval` pour planifier des actions.

**"Quelle différence entre `ref` et `reactive` ?"**

> `ref` encapsule n'importe quelle valeur (primitive ou objet) dans un wrapper `.value`. `reactive` crée un proxy réactif d'un objet — on accède aux propriétés directement sans `.value`. Dans `PollForm`, j'utilise `reactive` pour le formulaire car c'est un objet dont tous les champs sont liés entre eux — plus naturel qu'un `ref` par champ. J'utilise `ref` pour le tableau `options` car je le remplace entier ou le splice — `ref` donne plus de contrôle sur les mutations.

**"Pourquoi `ref([])` pour `polls` dans le store et pas `reactive` ?"**

> `reactive` ne peut pas être réassigné (`polls = nouvelleValeur` casserait la réactivité). Avec `ref`, `polls.value = data` fonctionne et tous les composants qui lisent `polls.value` se mettent à jour. C'est le pattern standard pour un tableau partagé dans un composable store.

**"Que se passe-t-il si on oublie `onUnmounted` dans `usePolling` ?"**

> `setInterval` continue de tourner en arrière-plan même si le composant est détruit. À chaque tick, il appelle `fetchResults` sur un composant qui n'existe plus — erreur silencieuse, requêtes réseau inutiles, fuite mémoire potentielle. `clearInterval(timer)` dans `onUnmounted` nettoie l'effet de bord quand le composant se démonte.

**"`computed` vs méthode pour `isExpired` ?"**

> Une méthode serait recalculée à chaque re-render, même si `ends_at` n'a pas changé. Une `computed` est mise en cache : elle ne se recalcule que si ses dépendances réactives changent. Ici `results.value.ends_at` est la dépendance — si elle ne change pas entre deux ticks de polling, `isExpired` renvoie la valeur en cache.

---

### Questions sur les choix d'architecture

**"Pourquoi un store plutôt que des props entre Dashboard et PollTable ?"**

> `AppPollDashboard` et `PollTable` ne sont pas dans une relation parent-enfant directe où les données coulent naturellement. Dashboard initialise les polls (depuis Blade), PollTable les affiche ET déclenche des suppressions. Avec des props, il faudrait : Dashboard passe `polls` → PollTable, PollTable `$emit('delete', id)` → Dashboard, Dashboard filtre et repasse les props. Avec le store, les deux lisent directement `polls.value` et `deletePoll` met à jour la même référence — les deux composants réagissent sans intermédiaire.

**"Pourquoi `PollForm` accepte-t-il un prop `mode` plutôt que deux composants séparés ?"**

> La logique de création et d'édition est identique à 95% : mêmes champs, même validation, même gestion des erreurs, même tableau d'options dynamiques. La seule différence est l'URL d'envoi (`POST /polls` vs `PUT /polls/{id}`). Dupliquer le composant aurait multiplié le code à maintenir. `mode` est un prop simple qui change deux lignes dans `submit()`.

**"Pourquoi `secret_token` dans l'URL de vote plutôt que l'`id` ?"**

> L'`id` est séquentiel et prévisible : quelqu'un peut énumérer tous les sondages en incrémentant `/polls/1/vote`, `/polls/2/vote`... Le `secret_token` est généré par `bin2hex(random_bytes(16))` — 16 octets aléatoires, 32 caractères hexadécimaux, 2^128 combinaisons possibles. Impossible à deviner ou énumérer. C'est le principe du lien de partage par obscurité.

**"Pourquoi supprimer et recréer les options à chaque update plutôt que les modifier ?"**

> Gérer un diff (quelle option a changé de label, laquelle a été supprimée, laquelle a été ajoutée, dans quel ordre) est complexe et source de bugs. La stratégie delete + recreate est simple, fiable, et sans risque de désynchronisation. Elle est acceptable ici parce qu'on bloque la modification des options si le sondage est déjà actif — donc on ne touche jamais aux options quand des votes existent déjà.

**"Pourquoi `$poll->votes()->create()` plutôt que `PollVote::create()` ?"**

> `$poll->votes()->create([...])` utilise la relation Eloquent `HasMany`. Eloquent remplit automatiquement `poll_id` avec l'id du poll parent. `PollVote::create([...])` sans `poll_id` explicite laisse le champ à null — erreur de contrainte NOT NULL en base. La relation garantit l'intégrité sans avoir à penser à passer `poll_id` manuellement.

**"Pourquoi la route résultats est-elle maintenant publique ?"**

> Le cahier des charges dit explicitement : "permettre à une personne non authentifiée ayant reçu ce lien de consulter les résultats si, et seulement si, leur visibilité est publique". La route était dans le groupe `auth`, ce qui bloquait tout accès anonyme. La déplacer hors du groupe `auth` permet à un anonyme d'accéder à la page. La logique d'accès est gérée côté API : `ApiPollController::results()` vérifie `results_public` et retourne 403 si privé — le composant Vue affiche alors "🔒 Résultats privés".

**"Pourquoi `credentials: 'same-origin'` dans `useFetchApi` ?"**

> Sanctum authentifie les SPA via des cookies de session. Par défaut, `fetch()` n'envoie pas les cookies sur les requêtes cross-origin ni même same-origin avec certaines configs. `credentials: 'same-origin'` force l'envoi du cookie de session Laravel à chaque requête. Sans ça, toutes les requêtes authentifiées retournent 401 même si l'utilisateur est connecté.

---

### Questions sur les modifications d'aujourd'hui

**"Vous avez modifié `usePollStore` — pourquoi ?"**

> Deux corrections. D'abord `useFetchApi('/api/v1')` → `useFetchApi()` : passer `/api/v1` en argument répétait une information déjà configurée dans `bootstrap.js` via `setDefaultBaseUrl`. Principe DRY — une seule source de vérité pour l'URL de base. Ensuite, suppression de `deletePolls(ids)` : la fonction était définie mais absente du `return` — jamais exportée, jamais appelée. Du code mort qui induisait en erreur.

**"Vous avez supprimé `getCsrfToken()` de `useFetchApi.js` — c'est risqué ?"**

> Non. Cette fonction avait été ajoutée comme patch quand `bootstrap.js` n'était pas importé dans les pages. `bootstrap.js` lit le même cookie XSRF et appelle `setDefaultHeaders` pour l'injecter. Une fois qu'on importe `bootstrap.js` en premier dans chaque entrypoint, le token est toujours configuré avant tout appel API. Garder `getCsrfToken()` dans `useFetchApi.js` faisait la même chose en double — redondance inutile et divergence par rapport au code du prof.

**"Pourquoi avez-vous ajouté `import './bootstrap'` dans les 4 entrypoints ?"**

> `bootstrap.js` configure l'URL de base et le token CSRF pour tous les appels API. Sans cet import, `useFetchApi()` sans argument n'a pas de `defaultBaseUrl` — les URLs construites sont incorrectes. Et sans le token CSRF, Laravel rejette les POST/PUT/DELETE avec une erreur 419. Seul `poll-dashboard.js` l'importait. Les 4 autres pages font des appels API et en avaient besoin.

**"Pourquoi avoir modifié `composer.json` ?"**

> `artisan serve` utilise `proc_open()` en PHP pour spawner un processus enfant avec le flag `-S`. Sur Windows avec Laravel Herd, le binaire PHP dans le PATH est un fichier `.bat` — `proc_open()` ne peut pas exécuter un `.bat` comme serveur réseau, le processus enfant échoue immédiatement. `php -S 127.0.0.1:8000 -t public public/index.php` est exactement la commande qu'`artisan serve` aurait lancée en interne, mais passée directement à `concurrently` via Node.js qui sait gérer les `.bat` sous Windows.

---

### Modifications live que le prof pourrait demander

Pour chacune, le fichier exact à ouvrir est indiqué.

---

**"Affichez le nombre d'options de chaque sondage dans le dashboard"**

Fichier : `resources/js/components/PollTable.vue`

Dans le `<td>` ou la card mobile, ajouter :
```html
<span class="text-xs text-gray-400">{{ poll.options?.length ?? 0 }} option(s)</span>
```
Fonctionne car `AppPollDashboard` charge les polls avec `with('options')` → `poll.options` est déjà présent.

---

**"Changez l'intervalle de polling de 5s à 10s"**

Fichier : `resources/js/AppPollResults.vue`, ligne :
```js
usePolling(fetchResults, 5000, true)
```
Changer `5000` en `10000`. Une ligne.

---

**"Ajoutez un message 'Aucun vote pour l'instant' quand totalVotes = 0"**

Fichier : `resources/js/AppPollResults.vue`, dans le template, après le bloc "Total votes" :
```html
<p v-if="totalVotes === 0" class="text-sm text-gray-400 text-center py-2">
  Aucun vote pour l'instant.
</p>
```

---

**"Affichez le titre du sondage dans la confirmation de suppression"**

Fichier : `resources/js/components/PollTable.vue`, fonction `delPoll` :
```js
async function delPoll(id) {
  const poll = polls.value.find(p => p.id === id)
  if (!confirm(`Supprimer "${poll?.title || 'ce sondage'}" ?`)) return
  await deletePoll(id)
}
```

---

**"Ajoutez un compteur de sondages dans le titre du dashboard"**

Fichier : `resources/js/AppPollDashboard.vue`, dans le `<h1>` :
```html
<h1 class="text-2xl font-bold text-gray-900">
  Mes sondages
  <span class="text-lg font-normal text-gray-400">({{ polls.length }})</span>
</h1>
```
Importer `polls` du store en haut du `<script setup>` :
```js
const { polls, setPolls } = usePollStore();
```

---

**"Ajoutez un bouton pour accéder aux résultats directement depuis la page de vote"**

Déjà présent dans `AppPollVote.vue` ligne 143 :
```html
<a v-if="!poll.is_draft" :href="`/polls/${props.token}/results`" ...>
  Voir les résultats →
</a>
```
→ Montrer que c'est déjà implémenté.

---

**"Faites en sorte que le formulaire bloque la soumission si une option est vide"**

Fichier : `resources/js/components/PollForm.vue`, dans `submit()` avant l'appel API :
```js
const emptyOption = options.value.some(o => o.trim() === '')
if (emptyOption) {
  alertMsg.value = 'Toutes les options doivent être remplies.'
  alertType.value = 'warning'
  return
}
```
(La validation Laravel côté backend le fait déjà via `'options.*' => 'required|string'`, mais ajouter une validation frontend évite un aller-retour réseau.)

---

### Questions théoriques supplémentaires

**"Qu'est-ce que Sanctum et pourquoi pas Passport ?"**

> Sanctum est une solution d'authentification légère de Laravel pour les SPA sur le même domaine. Il utilise les cookies de session existants — pas besoin de générer des tokens OAuth. Passport implémente OAuth2 complet, adapté pour authentifier des applications tierces. Ici, le frontend et le backend sont sur le même domaine, Sanctum suffit largement.

**"Qu'est-ce que `SameSite=Strict` dans `.env` ?"**

> C'est une directive du cookie de session. `Strict` signifie que le cookie n'est envoyé que sur des requêtes provenant du même site. Ça bloque nativement les attaques CSRF cross-site : une page malveillante sur un autre domaine ne peut pas déclencher une requête qui emporte le cookie de session de l'utilisateur.

**"Pourquoi utiliser `bin2hex(random_bytes(16))` pour le token ?"**

> `random_bytes(16)` génère 16 octets cryptographiquement aléatoires via le CSPRNG du système (pas `rand()` qui est prévisible). `bin2hex` les convertit en 32 caractères hexadécimaux. C'est 2^128 combinaisons possibles — impossible à bruteforcer. C'est la méthode recommandée pour les tokens secrets.

**"Qu'est-ce que l'eager loading et pourquoi l'utilisez-vous ?"**

> Quand on charge les polls depuis la base, chaque poll a des options. Sans eager loading, Eloquent ferait une requête SQL par poll pour charger ses options (problème N+1). `with('options')` dans `PollDashboardController` fait une seule requête supplémentaire pour toutes les options en une fois : `SELECT * FROM poll_options WHERE poll_id IN (1, 2, 3, ...)`. Beaucoup plus efficace.

**"Pourquoi `starts_at` et `ends_at` sont-ils calculés au lancement et pas à la création ?"**

> Un sondage peut rester en brouillon plusieurs jours avant d'être lancé. Si on calculait `ends_at = created_at + duration`, un brouillon créé il y a 3 jours avec une durée de 2 jours serait déjà expiré avant même d'être lancé. En calculant au moment où `is_draft` passe à `false`, on s'assure que le décompte démarre à l'activation réelle.

**"Qu'est-ce que le versioning API `/api/v1/` ?"**

> Si on modifie la structure des réponses API dans le futur (renommer un champ, changer le format), les clients qui appellent `/v1/` continuent de fonctionner. On crée `/v2/` pour les changements incompatibles. Bonne pratique même sur un petit projet — ça n'a aucun coût à la mise en place.

---

### Points à anticiper si le prof pousse sur l'IA

Le prof dit explicitement : *"la personne démontre qu'elle maîtrise réellement le code présenté, y compris si des outils d'IA ont été utilisés"*.

Préparer pour chaque fichier clé :
- Être capable de lire n'importe quelle ligne et l'expliquer
- Savoir ce qui existait avant vs ce qui a été ajouté
- Savoir pourquoi chaque choix a été fait (pas juste "ça marche")

Points où montrer la maîtrise active :
- La correction `useFetchApi()` sans argument → tu l'as compris et justifié
- La route résultats déplacée hors auth → tu as identifié l'écart avec le cahier des charges
- La suppression de `getCsrfToken()` → tu as tracé l'exécution avant de supprimer
- `deletePolls` supprimé → tu savais qu'il n'était pas dans le `return`
