# Repository/DevisRepository.php

**Rôle** : CRUD pour devis + ses lignes, numérotation automatique à la création.

## Points clés

- **`numero` assigné à la création, jamais par l'appelant** : `insert()` appelle `DocumentNumeroGenerator::next()` avec l'année de `dateCreation()` (voir [DocumentNumeroGenerator.md](../Numbering/DocumentNumeroGenerator.md)) — impossible de choisir/deviner un numéro depuis l'extérieur.
- **Lignes : stratégie "supprimer puis réinsérer"** à chaque `save()` (y compris `update()`) — plus simple et plus sûr qu'un diff ligne à ligne pour un outil à faible volume (quelques lignes par devis, pas de concurrence). Conséquence : l'`id` d'une ligne peut changer après une modification du devis, ne jamais s'appuyer sur un id de ligne stable entre deux `save()`.
- **`delete()` supprime aussi les lignes** — pas de contrainte `FOREIGN KEY ... ON DELETE CASCADE` (voir [Database.md](../Database.md)), donc le nettoyage des lignes orphelines est fait explicitement ici, pas par la base.

## Scénarios BDD couverts (voir [DevisRepositoryTest.php](../../tests/Unit/Repository/DevisRepositoryTest.php))

```gherkin
Scenario: creation d'un devis avec numerotation automatique
  Given un devis avec deux lignes, sans numero
  When il est enregistre pour la premiere fois
  Then il recoit un numero au format D{annee}-{sequence}, ses lignes sont persistees, et son total est la somme des lignes

Scenario: numeros distincts pour deux devis
  Given un premier devis deja enregistre en 2026
  When un second devis est enregistre la meme annee
  Then son numero a une sequence incrementee par rapport au premier

Scenario: retrouver un devis avec ses lignes
  Given un devis enregistre avec ses lignes
  When il est recherche par son id
  Then les memes lignes reviennent, dans l'ordre d'origine

Scenario: modification sans duplication de lignes
  Given un devis enregistre avec une ligne
  When il est resauvegarde avec une ligne differente
  Then l'ancienne ligne n'existe plus et seule la nouvelle est presente

Scenario: suppression en cascade
  Given un devis enregistre avec des lignes
  When il est supprime
  Then le devis et toutes ses lignes ont disparu
```

## En cas de bug

- Numéro dupliqué → ne peut pas venir de ce repository seul (voir garanties dans [DocumentNumeroGenerator.md](../Numbering/DocumentNumeroGenerator.md) + contrainte `UNIQUE` SQL) — vérifier qu'aucun code n'insère directement dans la table `devis` en contournant `save()`.
- Lignes en double après une modification → vérifier que `update()` appelle bien `delete()` sur `ligneTable` **avant** de réinsérer, pas après.
- Total incohérent → toujours recalculé depuis les lignes hydratées ([Devis::total()](../Entity/Devis.md)), donc un total faux signifie des lignes mal enregistrées, pas un problème de calcul.
