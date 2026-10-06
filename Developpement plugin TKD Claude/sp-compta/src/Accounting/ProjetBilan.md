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

## Scénarios (tests : `tests/Unit/Accounting/ProjetBilanTest.php`)

```gherkin
Scénario: seules les lignes rattachées au projet sont additionnées
Scénario: prévu 300 € de dépenses / 100 € de recettes, réalisé 250 / 80 → écart de résultat +30
Scénario: sans budget saisi, le bilan l'indique (a_un_budget = faux)
Scénario: fonctionnement courant + projets = résultat de la saison
Scénario: les montants saisis en français ou en anglais sont lus
```

## En cas de bug

- Total d'un projet faux : vérifier `projet_id` des lignes en base (colonne ajoutée en 1.4.0) et qu'on passe bien toutes les lignes **de la saison du projet**.
- Écart incohérent : le prévu vient de `budget_depenses` / `budget_recettes` du projet (0 = pas de prévu).
