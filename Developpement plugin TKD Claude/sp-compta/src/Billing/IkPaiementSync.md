# Billing/IkPaiementSync.php

**Rôle** : même principe que [SponsorPaiementSync.php](SponsorPaiementSync.md) — cocher "payé" sur une ligne IK (entraîneur/mois) bascule automatiquement le montant dans les Dépenses. Appelé depuis [Admin/IkScreen.php](../Admin/IkScreen.md)`::handleTogglePaye()`.

## Différence avec SponsorPaiementSync : pas de formulaire, une grille

`SponsorPaiementSync::sync()` est appelé à chaque sauvegarde d'un sponsor (un formulaire = une entité). Ici il n'y a pas de formulaire d'édition — [IkScreen.php](../Admin/IkScreen.md) affiche une grille entraîneur × mois où chaque case à cocher se soumet seule (`onchange="this.form.submit()"`). D'où deux méthodes explicites plutôt qu'un `sync()` unique : `marquerPaye()` et `demarquerPaye()`, chacune appelée directement avec les identifiants (trainerId, année, mois) plutôt qu'un objet déjà chargé.

## Idempotence : pas de dépense en double

`marquerPaye()` ne crée une dépense **que si** aucune ligne `IkPaiement` n'existe encore pour ce couple (trainer, année, mois) **ou** que la ligne existante n'est pas encore `paye()`. Recocher une case déjà payée (double-clic, re-soumission) ne génère jamais de seconde dépense.

## Ce qui se passe si on décoche "payé" ensuite

**Rien sur la dépense.** `demarquerPaye()` repasse juste `paye` à faux (voir `IkPaiement::sansPaiement()`) — la dépense déjà créée n'est **jamais supprimée automatiquement**, exactement comme `SponsorPaiementSync`. Si elle a été cochée par erreur, le bureau doit supprimer la dépense manuellement depuis l'onglet Dépenses.

## Dépense générée

`categorie`/`sousCategorie` = `'62'` / `'deplacements'` (voir [Accounting/Categories.php](../Accounting/Categories.md) — "Déplacements (IK, essence, péage...)" sous "62 - Autres services extérieurs"), `detail` = `"Indemnites kilometriques <nom entraineur> - <mois> <annee>"`, `date` = date du jour où la case a été cochée (pas la date du mois concerné — c'est la date du *paiement*, pas celle des cours).

## Pas d'exercice actif → pas de dépense possible

`marquerPaye()` retourne `null` si `ExerciceRepository::active()` est `null` — même contrainte que `DepenseScreen`/`SponsorScreen`. [IkScreen.php](../Admin/IkScreen.md) désactive les cases à cocher (sauf celles déjà payées, affichées en lecture seule) tant qu'aucun exercice n'est actif.

## En cas de bug

- Dépense dupliquée → vérifier que `findByTrainerPeriode()` retrouve bien la ligne existante (clé `UNIQUE (trainer_id, annee, mois)` en base, voir [Database.php](../Database.md)) avant de créer.
- Montant de la dépense différent de celui affiché dans le tableau → le montant est passé en paramètre par [IkScreen.php](../Admin/IkScreen.md) (calculé à l'affichage), pas recalculé ici — un montant erroné vient donc du calcul côté écran ou de [SpBuildReader.php](../Integration/SpBuildReader.md), pas de cette classe.

## Tests

Pas de test dédié pour l'instant — voir note dans [Entity/IkPaiement.md](../Entity/IkPaiement.md#tests).
