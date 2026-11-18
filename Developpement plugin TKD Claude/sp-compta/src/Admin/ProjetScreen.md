# Admin/ProjetScreen

## Rôle

Onglet **« Projets »** du menu Trésorerie (entre « Indemnités Km » et « Solde »), demandé le 06/10/2026 : isoler en fin de saison, pour l'AG, ce qu'ont coûté ou rapporté les projets (fête de Noël, matériel, stage…) par rapport au fonctionnement courant, avec comparaison **prévu / réalisé**. Calculs : [Accounting/ProjetBilan](../Accounting/ProjetBilan.md) uniquement.

## Écrans

1. **Liste** (saison active, ou `?exercice_id=` via le sélecteur « Saison » s'il y a plusieurs exercices) :
   - trois encadrés : résultat du **fonctionnement courant (hors projets)**, résultat des **projets**, **résultat de la saison** ;
   - tableau : Projet · Nature · Statut · Recettes (réalisé / prévu) · Dépenses (réalisé / prévu) · Résultat réalisé · Résultat prévu · Écart, avec une ligne de total ;
   - formulaire d'ajout / modification (`?edit=`) : nom, nature, budget prévu (dépenses, recettes — montants au format français acceptés), responsable, dates, description, statut.
2. **Fiche d'un projet** (`?projet=ID`) : tableau prévu / réalisé / écart (recettes, dépenses, résultat), mouvements rattachés (case « Détacher »), mouvements de la saison encore en fonctionnement courant (case « Rattacher »), filtrés par période (par défaut les dates du projet) — pour rattacher des dépenses saisies avant la création du projet sans les modifier une à une.

**Lignes de budget (18/11/2026)** : quand un projet a des lignes saisies dans TKD Projets (voir [Integration/ProjetsApi](../Integration/ProjetsApi.md)), sa fiche affiche en plus le tableau **« Budget par catégorie »** (prévu, réalisé, écart, avec les catégories hors budget). Dans le formulaire, le budget prévu s'affiche alors **en lecture seule**, car c'est la somme des lignes, et `saveFromRequest()` ne le change pas.

## Actions (admin-post, nonce `sp_compta_projet_nonce`, capacité `sp_compta_manager`)

- `sp_compta_save_projet` → `saveFromRequest()` : nom obligatoire ; nature / statut hors liste → « autre » / « en cours » ; **un projet existant ne change jamais de saison**.
- `sp_compta_delete_projet` → `deleteFromRequest()` : les dépenses et recettes du projet **sont conservées** et reviennent en fonctionnement courant. Ses lignes de budget sont supprimées, puis le projet.
- `sp_compta_rattacher_projet` → `rattacherFromRequest()` : rattache les cases cochées « Rattacher », détache les cases « Détacher » — toujours limité à la saison du projet, et un « Détacher » n'agit que sur des lignes réellement rattachées à ce projet.

## Scénarios (tests : `tests/Unit/Admin/ProjetScreenTest.php`)

```gherkin
Scénario: créer un projet avec un budget saisi à la française (450,00 / 1 200)
Scénario: un projet sans nom n'est pas créé
Scénario: nature et statut inconnus → « autre » / « en cours »
Scénario: un projet existant ne change pas de saison
Scénario: rattacher puis détacher — seules les lignes de la saison du projet sont touchées
Scénario: supprimer un projet conserve ses dépenses (revenues en fonctionnement courant)
```

Lignes de budget :

```gherkin
Scénario: un projet avec des lignes garde les totaux calculés, même si le formulaire en envoie d'autres
Scénario: supprimer un projet supprime ses lignes de budget
```

## En cas de bug

- « Aucun exercice actif » : activer une saison dans Paramètres.
- Un mouvement n'apparaît pas dans « Rattacher » : il est déjà rattaché à un autre projet, ou hors de la période filtrée (bouton « Toute la saison »).
- Totaux différents du rapport AG : impossible si les deux passent par `ProjetBilan` — vérifier qu'on regarde la même saison.
