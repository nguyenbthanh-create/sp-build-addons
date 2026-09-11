# Entity/Facture.php

**Rôle** : objet-valeur immuable représentant une facture, avec ses lignes ([LigneDocument.php](LigneDocument.php)). Symétrique de [Devis.php](Devis.md) avec deux différences : `devisId` (nullable — renseigné uniquement si la facture vient d'une bascule de devis accepté) et `modeReglement`.

## Statuts

`emise` → `payee`, ou `annulee`. **Une facture émise ne se supprime jamais** (obligation légale de continuité de numérotation, voir [spec-fonctionnelle-module-compta.md](../../../md/spec-fonctionnelle-module-compta.md)) : "annuler" une facture signifie passer son statut à `annulee`, pas appeler `delete()` sur [FactureRepository.php](../Repository/FactureRepository.php). Cette classe ne bloque pas techniquement un `delete()` (pas de garde ici) — la discipline "ne jamais supprimer une facture émise" est une règle d'usage, à faire respecter côté écran admin.

## `numero` et `withNumero()`

Même mécanique que [Devis](Devis.md#numero-et-withnumero) : assigné uniquement par [FactureRepository::insert()](../Repository/FactureRepository.php), jamais choisi à la main.

## En cas de bug

- Total incorrect → vérifier les lignes ([LigneDocument.md](LigneDocument.md)).
- Facture "annulée" mais toujours présente en base → c'est le comportement voulu, pas un bug (voir ci-dessus).

## Tests

Pas de test dédié pour l'entité seule. Exercée par [FactureRepositoryTest.php](../../tests/Unit/Repository/FactureRepositoryTest.php).
