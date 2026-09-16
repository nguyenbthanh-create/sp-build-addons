# Admin/ParametresScreen.php

**Rôle** : écran "Paramètres" — regroupe trois sections distinctes sur une seule page admin, comme décrit dans les doléances ("Paramétrer tout : date de début et de fin d'exercice, siret, siège social... solde du compte au début de l'exercice") :
1. **Association** : SIRET, siège social, nom, logo — un seul formulaire, lié à [ParametresRepository](../Repository/ParametresRepository.md) (singleton).
2. **Exercices comptables** : formulaire de création + liste avec un lien "Activer" par exercice inactif, et un lien "Supprimer" **uniquement pour un exercice sans donnée rattachée** (voir [Billing/ExerciceDeletionGuard.php](../Billing/ExerciceDeletionGuard.md)) — lié à [ExerciceRepository](../Repository/ExerciceRepository.md).
3. **Accès bureau** (ajouté le 08/09/2026) : cases à cocher, une par compte WordPress du site, pour choisir précisément qui a accès au plugin — lié à [Capabilities::syncBureauUsers()](../Capabilities.md).

Cinq actions `admin_post_*` distinctes (une par formulaire/action), toutes protégées par le même nonce `sp_compta_parametres_nonce`.

## Suppression d'exercice (ajouté le 08/09/2026)

Répond à une question : l'absence de suppression était un oubli, pas une contrainte légale (voir [ExerciceDeletionGuard.md](../Billing/ExerciceDeletionGuard.md) pour le détail). `renderExerciceSection()` appelle `exerciceDeletionGuard->hasData()` pour chaque exercice affiché, et n'affiche le lien "Supprimer" que si l'exercice est vide — un exercice avec des données affiche "Non supprimable (données présentes)" à la place, sans lien du tout (pas de suppression forcée possible depuis cet écran).

## Correction d'un exercice existant (ajouté le 15/09/2026)

Répond à un cas réel : un solde initial saisi avec une erreur (4315,79 au lieu de 5315,79), sans autre moyen de le corriger qu'une requête SQL directe. `saveExerciceFromRequest()` supportait déjà la mise à jour techniquement (`ExerciceRepository::save()` bascule sur `update()` dès que l'entité a un id), mais rien dans l'écran ne l'exposait — seuls "Activer" et "Supprimer" existaient pour un exercice déjà créé.

- `renderExerciceSection()` prend désormais un `?Exercice $editing` optionnel (lu depuis `$_GET['edit_exercice']` dans `render()`). Le même formulaire sert à la création et à la modification : titre, libellé du bouton et `exercice_id` caché changent selon le cas.
- **Dates verrouillées (`readonly`, pas `disabled`) si `exerciceDeletionGuard->hasData()` est vrai** : décision délibérée, pas juste une précaution cosmétique — décaler les dates d'un exercice qui a déjà des dépenses/recettes rattachées changerait rétroactivement quels mouvements en font partie, un peu partout dans le plugin (Solde, rapports, exports). Le solde initial reste éditable dans tous les cas : c'est la correction qui a motivé cette évolution. `readonly` plutôt que `disabled` est important : un champ `disabled` n'est pas envoyé dans le `$_POST`, ce qui aurait effacé la date en base au lieu de la préserver.
- **`saveExerciceFromRequest()` préserve toujours le statut `actif` de l'exercice existant** en le relisant via `exerciceRepository->find()` avant de reconstruire l'entité — `Exercice::actif` vaut `false` par défaut dans le constructeur, donc sans cette précaution, corriger le solde de l'exercice actif l'aurait silencieusement désactivé à chaque enregistrement.
- Pas de report automatique vers l'exercice suivant : chaque exercice garde un `solde_initial` saisi indépendamment (voir "Pas de report automatique entre exercices" ci-dessous pour le raisonnement).

### Pas de report automatique entre exercices

Question posée par l'utilisateur en marge de cette correction : puisqu'on peut désormais corriger un solde initial, ne faudrait-il pas répercuter automatiquement la correction sur le solde initial de l'exercice suivant (qui a pu être recopié à la main depuis le solde de clôture erroné) ?

Décision : non, pas de cascade automatique. Deux raisons :
1. Le solde initial d'un exercice n'est pas forcément censé être identique au solde de clôture calculé de l'exercice précédent — un rapprochement bancaire, un ajustement du trésorier ou une correction totalement indépendante peuvent légitimement les faire diverger. Une cascade automatique écraserait silencieusement une valeur volontairement différente.
2. Rien dans ce plugin ne modélise de lien formel entre deux exercices successifs (pas de "clôture de saison" comme dans `tkd-cotisations`) — construire ce report reviendrait à ajouter une fonctionnalité bien plus large que la simple correction d'une erreur de saisie.

Si une incohérence de ce type doit être surfacée un jour, une piste plus sûre serait un avertissement non bloquant (comparer le solde de clôture calculé d'un exercice au solde initial du suivant, et signaler l'écart sans jamais écrire dessus) plutôt qu'une écriture automatique — pas implémenté, à discuter si le besoin se confirme.

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

Scenario: correction du solde initial d'un exercice existant
  Given un exercice existant avec un solde initial errone
  When saveExerciceFromRequest() est appelee avec le meme id et la valeur corrigee
  Then le meme exercice est mis a jour, pas duplique

Scenario: la correction du solde ne desactive jamais l'exercice actif
  Given un exercice actif
  When son solde initial est corrige via le formulaire de modification
  Then il reste l'exercice actif
```

## En cas de bug

- Paramètres qui se dupliquent → ne peut pas venir de cet écran (`ParametresRepository::save()` garantit la ligne unique, voir sa doc) — vérifier qu'aucun code ne contourne le repository.
- Nouvel exercice non visible dans la liste après création → `renderExerciceSection()` relit `exerciceRepository->all()` à chaque affichage, donc un exercice manquant signifie qu'il n'a pas été inséré — vérifier `saveExerciceFromRequest()` isolément.
- Bouton "Activer" sans effet → vérifier que `activateExerciceFromRequest()` reçoit bien un `id` positif dans `$_GET` (lien généré par `wp_nonce_url()` dans `renderExerciceSection()`).
- Lien "Supprimer" absent pour un exercice qui semble vide → vérifier `ExerciceDeletionGuard::hasData()` (voir sa doc) sur chacune des 5 tables individuellement, pas cet écran qui ne fait qu'afficher son résultat.
- Un exercice actif se retrouve inactif après une simple correction de son solde initial → régression sur la préservation de `actif` dans `saveExerciceFromRequest()` (elle doit relire l'exercice existant via `find()` avant de reconstruire l'entité) — ne devrait plus se produire, couvert par `it_never_deactivates_the_active_exercice_when_correcting_its_solde`.
- Modifier un exercice avec des données crée un doublon au lieu de le mettre à jour → vérifier que le champ caché `exercice_id` est bien présent et non vide dans le formulaire (`renderExerciceSection()`), et que `saveExerciceFromRequest()` le lit bien sous ce nom.
- Les dates d'un exercice verrouillé changent quand même après enregistrement → vérifier que les champs date utilisent `readonly` et non `disabled` dans le formulaire ; un champ `disabled` n'est pas envoyé dans `$_POST`, ce qui viderait la date au lieu de la préserver.
