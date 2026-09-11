# Entity/Exercice.php

**Rôle** : objet-valeur immuable représentant un exercice comptable (onglet Paramètres — "date de début et de fin d'exercice... solde du compte au début de l'exercice"). Un seul exercice a `actif() === true` à la fois, contrainte appliquée par [ExerciceRepository::activate()](../Repository/ExerciceRepository.php), pas par cette classe.

## En cas de bug

Aucune logique ici — vérifier l'appelant ([ExerciceRepository.php](../Repository/ExerciceRepository.php)).

## Tests

Pas de test dédié. Exercée indirectement par [ExerciceRepositoryTest.php](../../tests/Unit/Repository/ExerciceRepositoryTest.php).
