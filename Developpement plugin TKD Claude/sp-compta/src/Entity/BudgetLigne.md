# Entity/BudgetLigne

## Rôle

Ligne du **budget prévu** d'un [projet](Projet.md). Ajoutée le 18/11/2026 pour la « Gestion de projets » de TKD Projets, qui affiche le prévu, le réalisé et l'écart **par catégorie**.

Table `sp_compta_budget_ligne` (voir [Database.md](../Database.md), `DB_VERSION` 1.5.0).

## Règles

- **Sens** : `depense` ou `recette` (`BudgetLigne::SENS`).
- **Catégorie** : un code CERFA du [référentiel](../Accounting/Categories.md) du même sens (`'60'`, `'62'`, `'74'`…). Le réalisé d'une ligne est la somme des mouvements du projet **de cette catégorie**. On ne coche rien de plus à la saisie d'une dépense.
- **Libellé** : texte libre (« Location de la salle », « Trophées »). Plusieurs lignes peuvent partager une catégorie : leur prévu s'additionne.
- **Montant** prévu : positif, en euros.
- Quand un projet a des lignes, ses totaux `budget_depenses` / `budget_recettes` valent **la somme des lignes**. Ces totaux sont tenus à jour par [ProjetsApi](../Integration/ProjetsApi.md). Le rapport AG, qui lit ces totaux, reste donc juste sans changement.

## API

`id()`, `projetId()`, `sens()`, `estDepense()`, `categorie()`, `libelle()`, `montant()`, `ordre()`, `withId()`.

## En cas de bug

- **Une ligne n'a pas de réalisé** : les dépenses du projet sont classées dans une autre catégorie. Elles apparaissent alors en « hors budget » dans le bilan par catégorie.
- **Les totaux du projet ne correspondent pas aux lignes** : les lignes n'ont pas été enregistrées par `ProjetsApi::enregistrerLignesBudget()`, qui recalcule les totaux.
