# Repository/DepenseRepository.php

**Rôle** : CRUD pour la table dépense, + `forExercice(int $exerciceId)` pour lister les dépenses d'un exercice comptable (nécessaire pour le suivi de balance décrit dans les doléances — "pouvoir un suivi réel de la balance du compte"). `columns()`/`hydrate()` gèrent aussi `sous_categorie` (depuis le 08/09/2026) — voir [Entity/Depense.md](../Entity/Depense.md#categorie--souscategorie-référentiel-à-deux-niveaux-depuis-le-08092026).

## Scénarios BDD couverts (voir [DepenseRepositoryTest.php](../../tests/Unit/Repository/DepenseRepositoryTest.php))

Mêmes scénarios de base que [FournisseurRepository.md](FournisseurRepository.md#scénarios-bdd-couverts-voir-fournisseurrepositorytestphp) (save/find/introuvable/update sans duplication/delete), plus :

```gherkin
Scenario: lister les depenses d'un exercice
  Given plusieurs depenses enregistrees sur des exercices differents
  When on demande les depenses d'un exercice precis
  Then seules les depenses de cet exercice sont retournees, triees de la plus recente a la plus ancienne
```

## En cas de bug

- Montant incorrect (arrondi, decimal tronqué) → format `%f` sur la colonne `montant`, vérifier la valeur `float` transmise avant l'appel `save()`, pas ici.
- Dépense d'un fournisseur supprimé toujours visible → normal, pas de contrainte `FOREIGN KEY` (voir [Database.md](../Database.md)) — `fournisseur_id` devient une référence orpheline, l'affichage doit gérer ce cas (fournisseur introuvable) côté admin, pas ici.
- `forExercice()` retourne des dépenses d'un autre exercice → vérifier l'`exercice_id` réellement stocké en base, pas la logique de la requête (déjà filtrée par `WHERE exercice_id = %d`).
