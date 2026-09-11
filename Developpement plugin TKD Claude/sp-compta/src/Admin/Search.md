# Admin/Search.php

**Rôle** : filtre "contient" (insensible à la casse, `mb_stripos`) réutilisé par tous les écrans avec liste + recherche ([FournisseurScreen](FournisseurScreen.md), [ClientScreen](ClientScreen.md), [DepenseScreen](DepenseScreen.md), [RecetteScreen](RecetteScreen.md), [SponsorScreen](SponsorScreen.md)), demandé dans les doléances du 08/09/2026 : *"champ de recherche par intitulé, date, ref, montant, ou contient"*.

## Pourquoi un filtre PHP en mémoire plutôt qu'une requête SQL `LIKE`

Volume de données visé : une association, quelques dizaines à quelques centaines de lignes par exercice — chaque écran charge déjà la liste complète via `repository->all()`/`forExercice()` pour l'affichage. Filtrer cette liste déjà chargée avec `Search::matches()` évite d'ajouter une méthode `search()` par repository (donc par table), et reste largement assez rapide à cette échelle. À revoir seulement si le volume de données change d'ordre de grandeur.

## Usage

Chaque écran construit un tableau des champs "cherchables" par ligne (ex. pour une dépense : date, catégorie, détail, montant, nom du fournisseur lié) et appelle `Search::matches($terme, $champs)` — `true` si `$terme` est vide (aucun filtre) ou trouvé comme sous-chaîne d'au moins un champ.

## En cas de bug

- Une ligne qui devrait matcher n'apparaît pas → vérifier que le champ concerné a bien été ajouté au tableau `$haystack` passé par l'écran (ex. le montant doit être caste en `string` implicitement, ce que fait déjà `matches()` — pas la peine de le faire côté appelant).
- Recherche insensible aux accents non garantie → `mb_stripos` est insensible à la casse mais **pas** aux accents (ex. "école" ne matche pas "ecole") — limitation connue, pas un bug.

## Tests

[SearchTest.php](../../tests/Unit/Admin/SearchTest.php).
