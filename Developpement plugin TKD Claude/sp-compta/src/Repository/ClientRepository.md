# Repository/ClientRepository.php

**Rôle** : CRUD complet pour la table client. Même patron et mêmes garanties que [FournisseurRepository.php](FournisseurRepository.php) — s'y référer pour le détail des scénarios BDD (enregistrement, recherche, introuvable, update sans duplication, suppression), reproduits à l'identique dans [ClientRepositoryTest.php](../../tests/Unit/Repository/ClientRepositoryTest.php) avec un client à la place d'un fournisseur.

## Spécificité

Colonne `type` en plus (`particulier`/`collectivite`, voir [Client.md](../Entity/Client.md)).

## En cas de bug

Mêmes pistes que [FournisseurRepository.md](FournisseurRepository.md#en-cas-de-bug) : vérifier `id()` avant `save()` pour insert/update, cohérence `columns()`/`formats()` (8 colonnes ici, pas 6), et `hydrate()` face au schéma de [Database.php](../Database.php)`::clientSchema()`.
