# Reporting/ExerciceExportateur.php

**Rôle** : construit le tableau exhaustif d'un exercice (dépenses, recettes, sponsors, devis, factures avec leurs lignes) prêt pour `wp_json_encode()` — la "sauvegarde brute" proposée par [Admin/RapportExerciceScreen.php](../Admin/RapportExerciceScreen.md). Ajoutée le 11/09/2026 suite à un état des lieux utilisateur constatant l'absence de toute fonctionnalité d'export.

## Pourquoi JSON et pas CSV/Excel

L'objectif annoncé est une sauvegarde "pure" à garder de côté en cas de plantage du site — pas un document de présentation (voir [RapportAgGenerator.md](RapportAgGenerator.md) pour ça). Les devis/factures ont des lignes imbriquées (désignation/quantité/prix), ce qu'un CSV représente mal sans aplatir la structure. JSON est natif à PHP (`wp_json_encode()`), ne demande aucune extension serveur (contrairement à un ZIP de plusieurs CSV), et reste directement exploitable pour une restauration manuelle si jamais nécessaire.

## Noms résolus, pas seulement les identifiants

Chaque dépense/recette/devis/facture garde son `fournisseur_id`/`client_id` **et** un `fournisseur_nom`/`client_nom` résolu à l'export (via un index construit une fois sur `Fournisseur[]`/`Client[]` passés en paramètre) — une sauvegarde n'a pas de sens si elle ne reste lisible qu'en recroisant une autre table. Un id sans fournisseur/client associé donne un nom vide, jamais une erreur.

## Classe pure, aucun accès base de données

Ne prend que des entités déjà chargées (`Depense[]`, `Recette[]`...) — c'est [RapportExerciceScreen](../Admin/RapportExerciceScreen.md) qui va les chercher via les repositories `forExercice()`/`all()`. Rend la classe testable sans fixture de base de données.

## Scénarios BDD couverts (voir [ExerciceExportateurTest.php](../../tests/Unit/Reporting/ExerciceExportateurTest.php))

```gherkin
Scenario: nom du fournisseur resolu sur une depense
  Given une depense liee a un fournisseur connu
  When toArray() est appelee
  Then la depense exportee porte fournisseur_id ET fournisseur_nom

Scenario: nom vide quand aucun fournisseur n'est lie
  Given une depense sans fournisseur
  When toArray() est appelee
  Then fournisseur_nom vaut '' plutot que de deviner un nom

Scenario: nom du client resolu sur une recette
  Given une recette liee a un client connu
  When toArray() est appelee
  Then la recette exportee porte client_id ET client_nom

Scenario: metadonnees de l'exercice reprises telles quelles
  Given un exercice avec ses champs
  When toArray() est appelee
  Then id/date_debut/solde_initial/actif se retrouvent sous 'exercice'
```

## En cas de bug

Un fichier téléchargé illisible/tronqué → le problème est côté [RapportExerciceScreen::handleExportJson()](../Admin/RapportExerciceScreen.md) (en-têtes HTTP, `exit` manquant), pas cette classe, qui ne fait que construire un tableau PHP.
