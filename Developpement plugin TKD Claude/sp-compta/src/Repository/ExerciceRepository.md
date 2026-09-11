# Repository/ExerciceRepository.php

**Rôle** : CRUD pour la table exercice, + deux méthodes propres à la règle métier "un seul exercice actif à la fois" :
- `active(): ?Exercice` — l'exercice actif courant, `null` si aucun n'est configuré.
- `activate(int $id): void` — bascule l'exercice actif (désactive l'ancien, active le nouveau). **Deux `UPDATE` distincts, pas une transaction SQL explicite** — acceptable pour un outil à 3 utilisateurs non concurrents sur cette action précise (bascule d'exercice = action rare, en général une fois par an).
- `delete(int $id): bool` — suppression **sans aucune garde ici** (voir [Billing/ExerciceDeletionGuard.php](../Billing/ExerciceDeletionGuard.md), qui est le seul point d'entrée à utiliser en pratique — il vérifie qu'aucune donnée n'est rattachée avant d'appeler cette méthode). Ne jamais appeler `ExerciceRepository::delete()` directement depuis un écran, toujours passer par le guard.

## Scénarios BDD couverts (voir [ExerciceRepositoryTest.php](../../tests/Unit/Repository/ExerciceRepositoryTest.php))

```gherkin
Scenario: aucun exercice actif au depart
  Given aucun exercice n'a ete cree
  When on demande l'exercice actif
  Then rien n'est retourne

Scenario: activer un exercice
  Given deux exercices enregistres, aucun actif
  When on active le premier
  Then il devient actif et le second reste inactif

Scenario: changer d'exercice actif
  Given un premier exercice deja actif
  When on active le second
  Then le second devient actif et le premier redevient inactif
```

Plus les scénarios CRUD de base (save/find/update sans duplication), communs à tous les repositories de ce plugin (voir [FournisseurRepository.md](FournisseurRepository.md)).

## En cas de bug

- Deux exercices actifs en même temps → le bug vient d'un appel direct à `save()` avec `actif: true` au lieu de passer par `activate()`, qui est le seul point garantissant l'exclusivité.
- `active()` retourne le mauvais exercice → vérifier en base qu'un seul exercice a bien `actif = 1` (`SELECT COUNT(*) ... WHERE actif = 1`), pas la requête elle-même (`LIMIT 1` masquerait un doublon silencieusement).
