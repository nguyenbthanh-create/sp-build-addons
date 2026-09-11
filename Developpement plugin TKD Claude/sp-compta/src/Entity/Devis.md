# Entity/Devis.php

**Rôle** : objet-valeur immuable représentant un devis, avec ses lignes ([LigneDocument.php](LigneDocument.php)). `total()` fait la somme des lignes — jamais stocké.

## Statuts

Constantes `STATUT_*` : `brouillon` → `envoye` → `accepte` | `refuse` ; un devis `accepte` peut ensuite passer à `facture` via [Billing/DevisToFactureConverter.php](../Billing/DevisToFactureConverter.md) (bascule automatique décrite dans les doléances). Cette classe ne valide **pas** les transitions (ex. rien n'empêche de construire un `Devis` avec `statut: 'facture'` directement) — la garde métier est dans `DevisToFactureConverter`, pas ici (voir sa doc pour la règle exacte).

## `numero` et `withNumero()`

`numero` est vide (`''`) tant que le devis n'a pas été enregistré une première fois — [DevisRepository::insert()](../Repository/DevisRepository.php) l'assigne via [Numbering/DocumentNumeroGenerator.php](../Numbering/DocumentNumeroGenerator.md) et renvoie l'objet mis à jour avec `withNumero()`. Ne jamais construire un `Devis` avec un `numero` choisi à la main en dehors des tests.

## En cas de bug

- Total incorrect → vérifier les lignes elles-mêmes ([LigneDocument.md](LigneDocument.md)), pas cette classe.
- Numéro vide après sauvegarde → le bug est dans `DevisRepository`, pas ici (voir [DevisRepository.md](../Repository/DevisRepository.md)).

## Tests

Pas de test dédié pour l'entité seule. Exercée par [DevisRepositoryTest.php](../../tests/Unit/Repository/DevisRepositoryTest.php).
