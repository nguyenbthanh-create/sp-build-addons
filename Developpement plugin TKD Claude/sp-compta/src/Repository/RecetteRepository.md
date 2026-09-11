# Repository/RecetteRepository.php

**Rôle** : CRUD pour la table recette, + `forExercice(int $exerciceId)`. Symétrique de [DepenseRepository.php](DepenseRepository.php) — mêmes scénarios BDD (save/find/introuvable/update sans duplication/delete/liste par exercice), reproduits dans [RecetteRepositoryTest.php](../../tests/Unit/Repository/RecetteRepositoryTest.php).

## En cas de bug

Voir [DepenseRepository.md](DepenseRepository.md#en-cas-de-bug) — mêmes causes probables, en remplaçant `fournisseur_id` par `client_id`.
