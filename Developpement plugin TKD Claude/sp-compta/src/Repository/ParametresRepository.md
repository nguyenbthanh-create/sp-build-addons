# Repository/ParametresRepository.php

**Rôle** : accès à la ligne unique de paramètres. Pas de `find(int $id)` ni `all()` comme les autres repositories — volontaire, il n'y a jamais qu'une seule ligne.

## Garantie "singleton"

`save()` ignore l'id porté par l'objet `Parametres` passé en argument : il regarde s'il existe déjà une ligne en base (`get()`), et si oui, force la mise à jour de **cette** ligne quel que soit ce que l'appelant croyait être l'id. Impossible de créer une deuxième ligne par erreur.

## Scénarios BDD couverts (voir [ParametresRepositoryTest.php](../../tests/Unit/Repository/ParametresRepositoryTest.php))

```gherkin
Scenario: aucun parametre configure au depart
  Given aucun parametre n'a jamais ete enregistre
  When on les demande
  Then rien n'est retourne

Scenario: premier enregistrement
  Given aucune ligne de parametres en base
  When on enregistre des parametres
  Then ils sont desormais retournes par get()

Scenario: modification sans duplication
  Given des parametres deja enregistres
  When on les enregistre a nouveau avec des valeurs differentes
  Then get() retourne les nouvelles valeurs et une seule ligne existe toujours en base
```

## En cas de bug

- Deux lignes en base malgré tout → seule façon possible : un `INSERT` direct en base hors de ce repository (migration manuelle, requête SQL ad hoc) — pas un bug de cette classe, `save()` ne peut pas produire ce cas par construction.
- Anciennes valeurs qui reviennent → vérifier qu'aucun appel direct à `$wpdb->insert()` sur cette table ne contourne `save()` ailleurs dans le plugin.
