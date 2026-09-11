# Entity/Depense.php

**Rôle** : objet-valeur immuable représentant une ligne de dépense. `date` est une chaîne `YYYY-MM-DD` (pas d'objet `DateTime`, cohérent avec le stockage `DATE` MySQL et le reste du plugin — pas de valeur ajoutée à typer plus fort ici). `montant` est positif par convention (le signe dépense/recette est porté par la table, pas par le nombre).

## Relation

`fournisseurId` référence `sp_compta_fournisseur.id` (voir [Fournisseur.php](Fournisseur.php)) — clé logique, pas de contrainte SQL `FOREIGN KEY` (voir [Database.md](../Database.md)).

## `categorie` / `sousCategorie` (référentiel à deux niveaux, depuis le 08/09/2026)

`categorie` porte un code CERFA (`'60'`, `'61'`...), `sousCategorie` la ligne précise sous ce code (`'fourniture_bureau'`...) — voir [Accounting/Categories.php](../Accounting/Categories.md) pour le référentiel complet. Les deux sont toujours cohérents entre eux : c'est [DepenseScreen](../Admin/DepenseScreen.md) qui garantit ça à la sauvegarde (le formulaire ne soumet que la sous-catégorie, la catégorie est dérivée), pas cette classe.

## En cas de bug

Aucune logique ici — vérifier l'appelant ([DepenseRepository.php](../Repository/DepenseRepository.php)).

## Tests

Pas de test dédié. Exercée indirectement par [DepenseRepositoryTest.php](../../tests/Unit/Repository/DepenseRepositoryTest.php).
