# Repository/SponsorRepository.php

**Rôle** : CRUD pour la table sponsor, + `forExercice(int $exerciceId)`. Mêmes scénarios BDD que [FournisseurRepository.md](FournisseurRepository.md#scénarios-bdd-couverts-voir-fournisseurrepositorytestphp) plus le filtre par exercice (voir [DepenseRepository.md](DepenseRepository.md)), reproduits dans [SponsorRepositoryTest.php](../../tests/Unit/Repository/SponsorRepositoryTest.php).

## Spécificité

`contrat_signe` et `contrat_paye` sont stockés en `TINYINT` (0/1) mais exposés côté PHP comme `bool` (`Sponsor::contratSigne()`/`contratPaye()`) — la conversion se fait dans `columns()` (bool → int) et `hydrate()` (int → bool). `recette_id` (nullable) est géré comme `fournisseur_id`/`client_id` ailleurs dans le plugin — voir [Billing/SponsorPaiementSync.md](../Billing/SponsorPaiementSync.md) pour qui l'écrit et pourquoi.

## En cas de bug

- `contratSigne()` toujours `false` après relecture → vérifier que la colonne `contrat_signe` est bien castée `(bool)` dans `hydrate()`, pas un problème de stockage.
- Pour le reste, voir [FournisseurRepository.md](FournisseurRepository.md#en-cas-de-bug).
