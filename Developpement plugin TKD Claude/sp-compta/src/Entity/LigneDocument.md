# Entity/LigneDocument.php

**Rôle** : objet-valeur immuable représentant une ligne de devis ou de facture (désignation, quantité, prix unitaire). **Partagé entre `Devis` et `Facture`** — les deux tables (`devis_ligne`, `facture_ligne`) ont exactement la même forme, pas de raison d'avoir deux classes identiques.

## `total()` toujours calculé, jamais stocké

`total()` fait `quantite * prixUnitaire` à chaque appel — pas de champ `total` mutable qui pourrait se désynchroniser après une modification de quantité ou de prix. La base de données ne stocke pas non plus de colonne `total` (voir [Database.md](../Database.md)), pour la même raison : une seule source de vérité.

## En cas de bug

Un total incorrect vient forcément de `quantite`/`prixUnitaire` mal saisis en amont — cette classe ne peut pas produire un total incohérent avec ses propres valeurs.

## Tests

Pas de test dédié (une seule opération arithmétique triviale). Exercée indirectement par [DevisRepositoryTest.php](../../tests/Unit/Repository/DevisRepositoryTest.php) et [FactureRepositoryTest.php](../../tests/Unit/Repository/FactureRepositoryTest.php).
