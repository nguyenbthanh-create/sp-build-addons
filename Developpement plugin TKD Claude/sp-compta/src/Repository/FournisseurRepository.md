# Repository/FournisseurRepository.php

**Rôle** : seul point d'accès SQL pour la table fournisseur. CRUD complet (`save` fait insert ou update selon la présence d'un id, `find`, `delete`, `all`).

## Scénarios BDD couverts (voir [FournisseurRepositoryTest.php](../../tests/Unit/Repository/FournisseurRepositoryTest.php))

```gherkin
Scenario: enregistrer un nouveau fournisseur
  Given un fournisseur qui n'existe pas encore
  When il est sauvegardé
  Then il obtient un id positif

Scenario: retrouver un fournisseur existant
  Given un fournisseur enregistré en base
  When on le recherche par son id
  Then les mêmes données reviennent

Scenario: fournisseur introuvable
  Given aucun fournisseur avec un id donné
  When on le recherche
  Then rien n'est retourné

Scenario: mettre à jour sans dupliquer
  Given un fournisseur déjà enregistré
  When on le sauvegarde à nouveau avec le même id mais un nom différent
  Then seul le nom change, aucune ligne supplémentaire n'est créée

Scenario: supprimer un fournisseur
  Given un fournisseur enregistré
  When il est supprimé
  Then il n'est plus trouvable
```

## Convention

- Le nom de table vient toujours de `Database::tableFournisseur()`, jamais en dur (voir [Database.md](../Database.md)).
- Toute requête avec une valeur variable passe par `$wpdb->prepare()` — jamais de concaténation directe d'une valeur utilisateur dans le SQL.

## En cas de bug

- Doublon en base après un update → vérifier que `Fournisseur::id()` n'est pas `null` au moment de l'appel à `save()` (sinon `insert()` est utilisé au lieu de `update()`).
- Erreur SQL → vérifier `columns()`/`formats()` : le nombre d'éléments doit correspondre exactement (6 colonnes, 6 formats).
- Champ vide au lieu de la vraie valeur → `hydrate()` lit les clés du tableau `$row` retourné par `$wpdb` ; vérifier que le nom de colonne existe bien dans le schéma ([Database.php](../Database.php)).

## À reproduire pour les prochaines entités

Même patron (repository + entité + paire de tests) pour `Client`, `Depense`, `Recette`, `Devis`(+lignes), `Facture`(+lignes), `Sponsor` — voir [spec-fonctionnelle-module-compta.md](../../../md/spec-fonctionnelle-module-compta.md) pour le détail des champs de chacun.
