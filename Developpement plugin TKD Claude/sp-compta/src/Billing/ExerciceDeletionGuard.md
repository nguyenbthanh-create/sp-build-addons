# Billing/ExerciceDeletionGuard.php

**Rôle** : seul point d'entrée pour supprimer un exercice comptable. Répond à une question posée le 08/09/2026 : l'absence de suppression d'exercice n'était **pas une contrainte réglementaire** (contrairement à la numérotation des factures, voir [FactureRepository.md](../Repository/FactureRepository.md)) — c'était un oubli. Ce garde comble l'oubli sans risquer de perdre de vraies données comptables.

## Règle

`delete()` ne supprime l'exercice **que s'il n'a aucune donnée rattachée** — aucune dépense, recette, sponsor, devis ou facture pointant vers son `exercice_id`. Sinon, ne supprime rien et retourne `false`. Pas de suppression en cascade, pas de confirmation à outrepasser : un exercice avec des données ne peut être supprimé qu'après avoir supprimé/déplacé ses données une par une (aucun raccourci proposé, volontairement — supprimer en masse des lignes comptables ne doit jamais être un clic accidentel).

## Pourquoi 5 repositories en dépendance

`hasData()` doit vérifier **toutes** les tables rattachées à un exercice (`Depense`, `Recette`, `Sponsor`, `Devis`, `Facture`) — c'est le rôle naturel d'un service `Billing/` qui coordonne plusieurs entités, plutôt que d'ajouter cette connaissance à `ExerciceRepository` lui-même (qui reste un CRUD simple, sans savoir que d'autres tables existent — voir le même principe dans [DevisToFactureConverter.md](DevisToFactureConverter.md)).

## Scénarios BDD couverts (voir [ExerciceDeletionGuardTest.php](../../tests/Unit/Billing/ExerciceDeletionGuardTest.php))

```gherkin
Scenario: suppression d'un exercice vide
  Given un exercice sans aucune donnee rattachee
  When delete() est appele
  Then l'exercice est supprime et true est retourne

Scenario: refus de supprimer un exercice avec des depenses
  Given un exercice avec au moins une depense
  When delete() est appele
  Then rien n'est supprime et false est retourne

Scenario: refus de supprimer un exercice avec une facture
  Given un exercice avec au moins une facture
  When delete() est appele
  Then rien n'est supprime et false est retourne
```

## En cas de bug

- Un exercice avec des données a été supprimé quand même → vérifier qu'aucun code n'appelle `ExerciceRepository::delete()` directement en contournant ce guard (voir [ExerciceRepository.md](../Repository/ExerciceRepository.md)).
- Un exercice vide refuse d'être supprimé → vérifier `hasData()` sur chacune des 5 tables individuellement (une méthode `forExercice()` mal câblée sur la mauvaise table est la cause la plus probable).
