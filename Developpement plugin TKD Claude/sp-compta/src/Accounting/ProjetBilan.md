# Accounting/ProjetBilan

## Rôle

**Seule source** des calculs « prévu / réalisé » des projets et de la séparation fonctionnement courant / projets. Classe pure (ni base de données ni WordPress) : utilisée par l'écran [Projets](../Admin/ProjetScreen.md) et par le [rapport AG](../Reporting/RapportAgGenerator.md) — ne jamais recalculer ces montants ailleurs.

## Conventions

- Montants positifs en entrée (comme en base).
- **Résultat (net) = recettes − dépenses** : négatif = le projet a coûté au club.
- **Écart = réalisé − prévu** (recettes, dépenses, résultat). Un écart de résultat positif = mieux que prévu.

## API

- `pour(Projet, Depense[], Recette[])` → `recettes`, `depenses`, `net`, `nb_mouvements`, `a_un_budget`, `budget_recettes`, `budget_depenses`, `budget_net`, `ecart_recettes`, `ecart_depenses`, `ecart_net` (+ `projet`). On lui passe **toutes** les lignes de la saison : seules celles dont `projetId()` correspond sont comptées.
- `pourTous(Projet[], …)` : idem pour chaque projet.
- `repartition(Depense[], Recette[])` → `fonctionnement_*` (lignes sans projet) et `projets_*` (lignes avec projet). `fonctionnement_net + projets_net` = résultat net de la saison.
- `montantSaisi(mixed)` : « 1 250,50 », « 1250.5 », « 450 € » → float positif ; vide, illisible ou négatif → 0.

- `parCategorie(Projet, BudgetLigne[], Depense[], Recette[])` (18/11/2026) → une ligne par sens et par catégorie : `sens`, `categorie`, `libelle_categorie`, `libelles` (libellés des lignes prévues), `prevu`, `realise`, `ecart`, `hors_budget`. Une catégorie qui a des mouvements mais aucune ligne prévue apparaît en « hors budget » (prévu 0). Les mouvements sans catégorie apparaissent en « Non catégorisé ». Ordre : les dépenses puis les recettes, par code croissant. Voir [Entity/BudgetLigne](../Entity/BudgetLigne.md).
- `totauxLignes(BudgetLigne[])` → `depenses`, `recettes` : les totaux prévus du projet quand il a des lignes.

## Scénarios (tests : `tests/Unit/Accounting/ProjetBilanTest.php`)

```gherkin
Scénario: seules les lignes rattachées au projet sont additionnées
Scénario: prévu 300 € de dépenses / 100 € de recettes, réalisé 250 / 80 → écart de résultat +30
Scénario: sans budget saisi, le bilan l'indique (a_un_budget = faux)
Scénario: fonctionnement courant + projets = résultat de la saison
Scénario: les montants saisis en français ou en anglais sont lus
```

Bilan par catégorie (tests : `tests/Unit/Accounting/ProjetBilanParCategorieTest.php`) :

```gherkin
Scénario: chaque catégorie prévue est comparée aux mouvements du projet de cette catégorie
Scénario: une dépense d'une catégorie non prévue apparaît « hors budget »
Scénario: les dépenses sont listées avant les recettes
Scénario: les totaux prévus sont la somme des lignes
```

## En cas de bug

- Total d'un projet faux : vérifier `projet_id` des lignes en base (colonne ajoutée en 1.4.0) et qu'on passe bien toutes les lignes **de la saison du projet**.
- Écart incohérent : le prévu vient de `budget_depenses` / `budget_recettes` du projet (0 = pas de prévu).
