# Front/ServiceWorker.php

**Rôle** : sert `/sw.js` à la racine du site (quel que soit le chemin choisi pour la page de saisie rapide), pour déclencher la bannière d'installation automatique de Chrome/Android sur [SaisieRapideShortcode](SaisieRapideShortcode.md). Ajouté le 10/09/2026 suite à une question de l'utilisateur — voir [correctif.md](../../correctif.md) pour l'historique "pourquoi pas dès le départ".

## Pourquoi une interception directe sur `init`, pas une règle de réécriture WordPress

Une règle de réécriture (`add_rewrite_rule`) nécessite un flush (`flush_rewrite_rules()`) pour être active, typiquement fait à l'activation du plugin — mais un plugin déjà activé avant l'ajout de cette classe ne rejouerait jamais ce flush automatiquement. Comparer directement `$_SERVER['REQUEST_URI']` sur le hook `init` évite ce piège : ça marche immédiatement sur un site déjà actif, sans réactivation ni flush.

## `matchesRequest()` séparée de `maybeServe()`

Même principe que la séparation `saveFromRequest()`/`handleSave()` des écrans admin (voir [FournisseurScreen.md](../Admin/FournisseurScreen.md)) : `maybeServe()` fait le vrai travail WordPress (`header()`, `echo`, `exit`) et n'est pas testable unitairement ; `matchesRequest()` ne contient que la comparaison de chemin, testable indépendamment.

## Service worker volontairement vide (aucun cache, aucun mode hors-ligne)

`contents()` enregistre les événements `install`/`activate`/`fetch` mais ne met **rien** en cache — chaque requête réseau se comporte exactement comme si aucun service worker n'était présent. Suffisant pour satisfaire les critères techniques d'installabilité de Chrome (manifest + service worker + HTTPS), sans le risque d'un cache qui se désynchronise (page figée sur une ancienne version après une mise à jour du plugin). Si un vrai mode hors-ligne devient un besoin, ce sera un choix délibéré et testé séparément, pas un effet de bord de ce fichier.

## Portée (`Service-Worker-Allowed`)

Calculée depuis `home_url('/')`, pas codée en dur à `/` — reste correct si WordPress est installé dans un sous-dossier (`exemple.fr/mon-site/`).

## Scénarios BDD couverts (voir [ServiceWorkerTest.php](../../tests/Unit/Front/ServiceWorkerTest.php))

```gherkin
Scenario: correspondance sur /sw.js
  Given une requete pour /sw.js
  When matchesRequest() est appelee
  Then elle correspond

Scenario: correspondance avec parametres de requete
  Given une requete pour /sw.js?ver=2 (cache-busting)
  When matchesRequest() est appelee
  Then elle correspond quand meme (seul le chemin compte, pas la chaine de requete)

Scenario: chemin sans rapport
  Given une requete pour une autre page du site
  When matchesRequest() est appelee
  Then elle ne correspond pas
```

## En cas de bug

- `/sw.js` retourne une page 404 ou le contenu normal du site → vérifier que `ServiceWorker::registerHooks()` est bien appelé (voir [Plugin.php](../Plugin.php)`::bootFront()`), et que rien d'autre (cache de page, CDN) n'intercepte la requête avant WordPress.
- La bannière d'installation n'apparaît toujours pas après ce correctif → Chrome exige HTTPS (sauf `localhost`) et n'affiche la bannière qu'après une interaction significative de l'utilisateur avec le site (pas au premier chargement) — comportement normal du navigateur, pas un bug de cette classe.
- Contenu périmé affiché après une mise à jour du plugin → ne devrait jamais arriver puisque rien n'est mis en cache (voir ci-dessus) ; si ça arrive quand même, le cache vient d'ailleurs (navigateur, CDN), pas de ce service worker.
