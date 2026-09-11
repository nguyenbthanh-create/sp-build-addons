# Admin/ParametresScreen.php

**Rôle** : écran "Paramètres" — regroupe trois sections distinctes sur une seule page admin, comme décrit dans les doléances ("Paramétrer tout : date de début et de fin d'exercice, siret, siège social... solde du compte au début de l'exercice") :
1. **Association** : SIRET, siège social, nom, logo — un seul formulaire, lié à [ParametresRepository](../Repository/ParametresRepository.md) (singleton).
2. **Exercices comptables** : formulaire de création + liste avec un lien "Activer" par exercice inactif, et un lien "Supprimer" **uniquement pour un exercice sans donnée rattachée** (voir [Billing/ExerciceDeletionGuard.php](../Billing/ExerciceDeletionGuard.md)) — lié à [ExerciceRepository](../Repository/ExerciceRepository.md).
3. **Accès bureau** (ajouté le 08/09/2026) : cases à cocher, une par compte WordPress du site, pour choisir précisément qui a accès au plugin — lié à [Capabilities::syncBureauUsers()](../Capabilities.md).

Cinq actions `admin_post_*` distinctes (une par formulaire/action), toutes protégées par le même nonce `sp_compta_parametres_nonce`.

## Suppression d'exercice (ajouté le 08/09/2026)

Répond à une question : l'absence de suppression était un oubli, pas une contrainte légale (voir [ExerciceDeletionGuard.md](../Billing/ExerciceDeletionGuard.md) pour le détail). `renderExerciceSection()` appelle `exerciceDeletionGuard->hasData()` pour chaque exercice affiché, et n'affiche le lien "Supprimer" que si l'exercice est vide — un exercice avec des données affiche "Non supprimable (données présentes)" à la place, sans lien du tout (pas de suppression forcée possible depuis cet écran).

## Pourquoi cet écran doit exister avant Dépenses/Recettes/Sponsors

`Depense`, `Recette` et `Sponsor` ont tous un `exercice_id` obligatoire. Sans un exercice actif créé ici, les futurs écrans `DepenseScreen`/`RecetteScreen`/`SponsorScreen` n'ont rien à proposer par défaut — **créer et activer un premier exercice sur ce site de test est un préalable** avant de tester ces écrans.

## Méthodes testables (même principe que [FournisseurScreen.md](FournisseurScreen.md#pourquoi-saverequest-deleterequest-sont-separees-de-handlesave-handledelete))

`saveParametresFromRequest()`, `saveExerciceFromRequest()`, `activateExerciceFromRequest()`, `saveAccesBureauFromRequest()` contiennent toute la logique ; `handleSaveParametres()`/`handleSaveExercice()`/`handleActivateExercice()`/`handleSaveAccesBureau()` ne sont que le glue WordPress (nonce, capacité, redirection, `exit`).

## Accès bureau : jamais de verrouillage possible

`renderAccesBureau()` liste **tous** les comptes WordPress du site (`get_users()`), pas seulement ceux qui ont déjà la capacité. Décocher tout le monde et enregistrer n'empêche jamais un administrateur d'accéder à cet écran : `syncBureauUsers()` n'affecte que l'octroi direct par utilisateur, pas l'octroi via le rôle `administrator` fait par `Capabilities::register()` (voir [Capabilities.md](../Capabilities.md#pourquoi-garder-le-rôle-administrateur-en-plus-de-la-liste-bureau)) — un administrateur garde toujours accès à cette page pour se corriger en cas d'erreur.

## Scénarios BDD couverts (voir [ParametresScreenTest.php](../../tests/Unit/Admin/ParametresScreenTest.php))

```gherkin
Scenario: premier enregistrement des parametres association
  Given aucun parametre enregistre
  When saveParametresFromRequest() est appelee avec un SIRET et un nom d'association
  Then les parametres sont crees avec ces valeurs

Scenario: creation d'un exercice
  Given une requete avec des dates de debut/fin et un solde initial
  When saveExerciceFromRequest() est appelee
  Then un nouvel exercice est cree, inactif par defaut

Scenario: activation d'un exercice
  Given deux exercices crees, aucun actif
  When activateExerciceFromRequest() est appelee avec l'id du premier
  Then il devient l'exercice actif

Scenario: assignation de l'acces bureau
  Given une requete avec deux ids d'utilisateurs coches
  When saveAccesBureauFromRequest() est appelee
  Then ces deux utilisateurs ont desormais la capacite sp_compta_manager

Scenario: suppression d'un exercice vide
  Given un exercice sans donnee rattachee
  When deleteExerciceFromRequest() est appelee avec son id
  Then il est supprime
```

## En cas de bug

- Paramètres qui se dupliquent → ne peut pas venir de cet écran (`ParametresRepository::save()` garantit la ligne unique, voir sa doc) — vérifier qu'aucun code ne contourne le repository.
- Nouvel exercice non visible dans la liste après création → `renderExerciceSection()` relit `exerciceRepository->all()` à chaque affichage, donc un exercice manquant signifie qu'il n'a pas été inséré — vérifier `saveExerciceFromRequest()` isolément.
- Bouton "Activer" sans effet → vérifier que `activateExerciceFromRequest()` reçoit bien un `id` positif dans `$_GET` (lien généré par `wp_nonce_url()` dans `renderExerciceSection()`).
- Lien "Supprimer" absent pour un exercice qui semble vide → vérifier `ExerciceDeletionGuard::hasData()` (voir sa doc) sur chacune des 5 tables individuellement, pas cet écran qui ne fait qu'afficher son résultat.
