# Admin/SoldeScreen.php

**Rôle** : implémente la doléance du 08/09/2026 — *"onglet Solde qui calcule le total des dépenses/recettes et le solde par rapport au solde de compte en banque renseigné dans Paramètres"*. Écran **en lecture seule** (aucun formulaire, aucune action `admin_post_*`) — affiche le solde initial de l'exercice actif, le total des recettes, le total des dépenses, le solde actuel, une répartition par catégorie pour chacun, et (ajouté le 08/09/2026, suite à une demande complémentaire) une liste unique de **tous les mouvements** (dépenses + recettes fusionnés, triés par date décroissante) avec recherche — voir [Search.md](Search.md).

## `registerHooks()` vide, volontairement

[Plugin.php](../Plugin.php)`::bootAdmin()` appelle `registerHooks()` génériquement sur tous les écrans du tableau `$screens` — cet écran n'a rien à y faire (pas de formulaire à traiter), mais la méthode doit exister pour respecter le contrat implicite utilisé par `bootAdmin()`.

## Calcul (méthodes statiques, testables sans rendu HTML)

- `totalMontant(array $lignes): float` — somme des `montant()` d'une liste de dépenses ou de recettes.
- `solde(float $soldeInitial, float $totalRecettes, float $totalDepenses): float` — `soldeInitial + totalRecettes - totalDepenses`.
- `repartitionHierarchique(array $lignes, array $groupe): array` — regroupe par code catégorie CERFA (`categorie()`) puis par sous-catégorie à l'intérieur (`sousCategorie()`), avec un total à chaque niveau. `$groupe` est `Categories::DEPENSE` ou `Categories::RECETTE`, utilisé pour résoudre les libellés (`Categories::libelleCategorie()`/`libelleSousCategorie()`) — voir [Accounting/Categories.php](../Accounting/Categories.md). Remplace (08/09/2026) `totauxParCategorie()`, à plat, qui ne distinguait pas les deux niveaux.
- `mouvements(array $depenses, array $recettes): array` — fusionne les deux listes en une seule, triée par date décroissante ; le montant d'une dépense est rendu **négatif** dans le tableau retourné (une recette reste positive), pour qu'une liste combinée se lise directement comme un relevé (entrées/sorties). Ce signe n'existe que dans ce tableau de sortie — `Depense::montant()` lui-même reste toujours positif (voir [Entity/Depense.md](../Entity/Depense.md)).
- `filtrerParType(array $mouvements, string $type): array` (ajouté le 10/09/2026) — ne garde que les mouvements dont `type` vaut `'Depense'` ou `'Recette'` ; `$type` vide ne filtre rien (tous les mouvements passent). Appliqué **avant** `Search::matches()` dans `renderMouvements()`, pas après — filtrer d'abord par type réduit la liste sur laquelle la recherche texte travaille ensuite.

Toutes séparées de `render()` pour rester testables indépendamment de tout rendu HTML — même logique que la séparation `saveFromRequest()`/`handleSave()` des autres écrans (voir [FournisseurScreen.md](FournisseurScreen.md)), appliquée ici au calcul plutôt qu'à la sauvegarde.

## Dépendance à un exercice actif

Même garde que [DepenseScreen.md](DepenseScreen.md#garde-aucun-exercice-actif) — sans exercice actif, affiche un message et s'arrête.

## Scénarios BDD couverts (voir [SoldeScreenTest.php](../../tests/Unit/Admin/SoldeScreenTest.php))

```gherkin
Scenario: solde positif
  Given un solde initial de 100, 50 de recettes, 30 de depenses
  When solde() est appele
  Then le resultat est 120

Scenario: total d'une liste vide
  Given aucune ligne
  When totalMontant() est appele
  Then le resultat est 0.0

Scenario: repartition hierarchique par categorie puis sous-categorie
  Given trois depenses, deux avec le meme code categorie mais des sous-categories differentes
  When repartitionHierarchique() est appele
  Then le total du code additionne les trois lignes, et chaque sous-categorie a son propre total distinct

Scenario: fusion des mouvements triee par date
  Given une depense et une recette plus recente
  When mouvements() est appele
  Then le mouvement le plus recent vient en premier, et le montant de la depense est negatif

Scenario: filtre par type de mouvement
  Given un melange de depenses et de recettes
  When filtrerParType() est appele avec "Recette"
  Then seules les recettes sont gardees

Scenario: aucun filtre applique
  Given un melange de depenses et de recettes
  When filtrerParType() est appele avec une chaine vide
  Then tous les mouvements sont gardes
```

## Recherche (ajouté le 08/09/2026)

Même mécanisme que les autres écrans (voir [Search.md](Search.md)), sur la liste "Mouvements de l'exercice" uniquement — pas sur les tableaux de répartition par catégorie (peu de lignes, faible intérêt à les filtrer).

## Filtre par type (ajouté le 10/09/2026)

Liste déroulante `<select name="type">` à côté du champ de recherche texte, dans `renderSearchBox()` — options "Tous les types" (valeur vide), "Depense", "Recette". La valeur est lue depuis `$_GET['type']` dans `render()` et **validée par whitelist** contre `self::TYPES_MOUVEMENT` (une valeur inconnue/absente retombe silencieusement sur `''`, donc aucun filtre) avant d'être transmise à `renderMouvements()`. Le filtrage lui-même (`filtrerParType()`) est appliqué sur le tableau déjà fusionné par `mouvements()`, **avant** la boucle de recherche texte — les deux filtres (type + recherche) se combinent (ex. type=Recette + recherche="cotisation" ne montre que les recettes contenant "cotisation"). Le lien "Reinitialiser" n'apparaît que si l'un des deux filtres (`$term` ou `$typeFilter`) est actif.

## En cas de bug

- Solde incorrect → vérifier séparément `totalMontant()` sur les dépenses et les recettes (isolable sans passer par l'écran), avant de suspecter `solde()` (une seule soustraction, peu de risque d'erreur).
- Catégorie absente de la répartition → une dépense/recette avec `categorie() === ''` (catégorie non renseignée) apparaît sous la clé `''`, affichée "Non categorise" — normal, pas un bug.
- Sous-catégorie affichée "Non categorise" alors qu'un code catégorie est bien renseigné → `sousCategorie()` est vide sur cette ligne — peut arriver sur d'anciennes données saisies avant le référentiel à deux niveaux (08/09/2026), qui n'avaient qu'une catégorie à plat.
- Montant d'une dépense affiché positif dans "Mouvements" → vérifier `mouvements()`, pas `renderMouvements()` — le signe est appliqué au moment de la fusion, pas à l'affichage.
- Message "Aucun mouvement de type ... pour cet exercice" alors que des données existent → normal si le filtre type est actif et qu'aucune ligne de ce type n'existe pour l'exercice ; vérifier `$typeFilter` avant de suspecter `filtrerParType()`.
- Le `<select>` de type revient toujours sur "Tous les types" après soumission → vérifier que `$_GET['type']` est bien dans `self::TYPES_MOUVEMENT` (valeur en dur `'Depense'`/`'Recette'`, sensible à la casse) — toute autre valeur est ignorée par la whitelist dans `render()`.
