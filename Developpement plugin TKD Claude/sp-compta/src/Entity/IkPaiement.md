# Entity/IkPaiement.php

**Rôle** : objet-valeur immuable représentant le *statut de paiement* d'une indemnité kilométrique pour un entraîneur sp_build donné, sur un mois donné. Ne stocke **ni** le nombre d'AR **ni** le km — ces deux valeurs restent toujours recalculées à l'affichage depuis [Integration/SpBuildReader.php](../Integration/SpBuildReader.md), jamais figées ici, pour ne jamais désynchroniser d'une correction faite plus tard côté sp_build (ex: un cours annulé après coup).

`trainerId` référence l'id de la table `sp_cal_trainers` de sp_build — pas de clé étrangère (plugins/bases séparés), juste un entier mémorisé tel quel.

## `withPaiement()` / `sansPaiement()`

Même patron immuable que `Sponsor::withRecetteId()`. `withPaiement($montant, $date, $depenseId)` fige le montant réellement versé et la dépense liée au moment du paiement (le tarif €/km peut changer plus tard sans réécrire l'historique). `sansPaiement()` repasse `paye` à faux **sans jamais effacer** `depenseId`/`montantVerse` — la dépense déjà créée reste visible dans son historique (cf. [Billing/IkPaiementSync.php](../Billing/IkPaiementSync.md)).

## En cas de bug

Aucune logique ici — vérifier l'appelant ([IkPaiementSync.php](../Billing/IkPaiementSync.md) ou [Repository/IkPaiementRepository.php](../Repository/IkPaiementRepository.md)).

## Tests

Pas de test dédié pour l'instant (implémenté hors cycle TDD strict, le 16/09/2026, faute de PHP/WordPress exécutable dans cette session — à couvrir au prochain passage sur ce module, voir [IkPaiementSync.md](../Billing/IkPaiementSync.md#tests)).
