# Entity/Projet

## Rôle

Projet de la saison (fête de Noël, achat de matériel, stage, compétition…) : une **étiquette analytique** posée sur des dépenses et des recettes, **indépendante de la catégorie CERFA**. Une dépense de la fête de Noël reste « 60 – Achats », mais elle est en plus rattachée au projet « Fête de Noël 2026 ». Les deux lectures coexistent sans double comptage. Demande utilisateur du 06/10/2026 : isoler en fin de saison, pour l'AG, ce qu'ont coûté les projets « hybrides » par rapport au fonctionnement courant.

Table `sp_compta_projet` (voir [Database.md](../Database.md), `DB_VERSION` 1.4.0). Les dépenses / recettes s'y rattachent par leur colonne `projet_id` (NULL = fonctionnement courant).

## Règles (validées par l'utilisateur)

- **Un projet = une saison** (`exercice_id`) : il ne change jamais d'exercice (voir `ProjetScreen::saveFromRequest()`). Projet sur deux saisons → un projet par saison.
- **Une dépense / recette = un seul projet** au plus (un ticket mixte se saisit en deux lignes).
- **Budget prévu facultatif** (`budget_depenses`, `budget_recettes`) : sans budget, seul le réalisé est affiché ; `aUnBudget()` le dit.
- **Statut** : `en_cours` (proposé à la saisie) ou `cloture` (n'est plus proposé, reste dans les bilans et le rapport AG).
- **Natures** : `evenement`, `investissement`, `stage`, `competition`, `autre` (`Projet::NATURES`, avec libellés).

## API

`id()`, `exerciceId()`, `nom()`, `nature()` / `natureLabel()`, `budgetDepenses()`, `budgetRecettes()`, `budgetNet()` (= recettes − dépenses prévues), `aUnBudget()`, `responsable()`, `dateDebut()` / `dateFin()` (Y-m-d ou null), `description()`, `statut()` / `statutLabel()`, `estEnCours()`, `withId()`.

## En cas de bug

- Projet absent du menu « Projet » d'une dépense : il est clôturé, ou il appartient à une autre saison que la dépense (voir [Admin/ProjetChamp.md](../Admin/ProjetChamp.md)).
- Montants prévus à 0 alors qu'ils ont été saisis : format du montant (voir `ProjetBilan::montantSaisi()`).
