# Entity/Fournisseur.php

**Rôle** : objet-valeur immuable représentant un fournisseur (le "tier" des dépenses, voir [spec-fonctionnelle-module-compta.md](../../../md/spec-fonctionnelle-module-compta.md)). Pas de logique, pas d'accès base — seulement des données + accesseurs.

## Convention

Immuable : pas de setters. Pour changer l'id après un insert, `withId()` renvoie une **nouvelle** instance (voir usage dans [FournisseurRepository.php](../Repository/FournisseurRepository.php)`::insert()`). Même patron à reproduire pour les futures entités (`Client`, `Depense`, `Recette`, `Devis`, `Facture`, `Sponsor`).

## En cas de bug

Cette classe ne contient aucune règle métier — un bug de valeur incorrecte vient forcément de l'appelant (formulaire admin, repository), pas d'ici.

## Tests

Pas de test dédié (aucune logique). Exercée indirectement par [FournisseurRepositoryTest.php](../../tests/Unit/Repository/FournisseurRepositoryTest.php).
