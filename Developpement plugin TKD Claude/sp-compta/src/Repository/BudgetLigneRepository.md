# Repository/BudgetLigneRepository

## Rôle

Accès SQL à la table `sp_compta_budget_ligne` (18/11/2026). Entité : [Entity/BudgetLigne.md](../Entity/BudgetLigne.md).

## API

- `forProjet(int)` : lignes du projet, dans l'ordre de saisie.
- `remplacer(int, BudgetLigne[])` : supprime puis réécrit toutes les lignes du projet **dans une transaction** (tout ou rien). L'ordre suit le tableau reçu. Renvoie les lignes avec leur id.
- `deleteForProjet(int)` : appelée à la suppression d'un projet ([ProjetScreen](../Admin/ProjetScreen.md)).

Pour enregistrer des lignes depuis une autre extension, passer par [ProjetsApi](../Integration/ProjetsApi.md), qui valide les lignes et recalcule les totaux du projet. N'appeler `remplacer()` directement que pour ce qui ne concerne pas les totaux.

## Scénarios (tests : `tests/Unit/Repository/BudgetLigneRepositoryTest.php`)

```gherkin
Scénario: des lignes enregistrées sont relues dans le même ordre
Scénario: remplacer les lignes efface les anciennes
Scénario: les lignes d'un autre projet ne sont pas touchées
```

## En cas de bug

- **Table absente** : `DB_VERSION` 1.5.0 n'a pas été appliquée. Recharger une page wp-admin (`Database::maybeUpgrade()`).
- **Lignes en double** : `remplacer()` a été appelée sans passer par la transaction. Vérifier que la table est bien en InnoDB.
