# Admin/SponsorScreen.php

**Rôle** : écran "Sponsors". Même patron que [DepenseScreen.php](DepenseScreen.md) (garde "aucun exercice actif", `exercice_id` en champ caché non ré-assigné). Pas de tiers lié (ni fournisseur ni client) — juste `nom`, `montant`, `date`, `type_paiement` (select) et deux cases à cocher : `contrat_signe` et `contrat_paye`.

## Case à cocher `contrat_signe`/`contrat_paye`

Un champ non coché n'est **pas envoyé** dans `$_POST` par le navigateur (comportement HTML standard) — `saveFromRequest()` utilise `!empty(...)` plutôt que `isset()`, pour que "absent du tableau" et "présent à `'0'`" soient tous deux traités comme "non coché".

## Bascule automatique vers Recettes (ajouté le 08/09/2026)

Cocher "Contrat payé" bascule automatiquement le sponsor en recette — délégué à [Billing/SponsorPaiementSync.php](../Billing/SponsorPaiementSync.md)`::sync()`, appelé à la fin de `saveFromRequest()` sur le sponsor tout juste enregistré. Le champ caché `recette_id` (comme `exercice_id`) porte l'id de la recette déjà liée, pour que `sync()` sache qu'il ne doit rien recréer sur les sauvegardes suivantes — voir [SponsorPaiementSync.md](../Billing/SponsorPaiementSync.md) pour l'idempotence et ce qui se passe si on décoche la case ensuite (rien : la recette existante n'est jamais supprimée automatiquement).

## Recherche (ajouté le 08/09/2026)

Même mécanisme que [DepenseScreen.md](DepenseScreen.md#recherche-ajouté-le-08092026), sur nom/montant/date/type de paiement.

## Pas encore d'export PDF du contrat

Le bouton "export PDF du contrat à signer" mentionné dans les doléances n'est pas encore implémenté sur cet écran — seule la case `contrat_signe` existe pour l'instant (suivi manuel).

## Fichier du contrat signé : upload réel (ajouté le 08/09/2026)

`fichierContrat` existait dans l'entité `Sponsor` et le repository depuis l'incrément 2, mais **n'était jamais exposé dans le formulaire** avant cet ajout — un oubli comparable à celui de la suppression d'exercice (voir [correctif.md](../../correctif.md)), repéré en construisant l'upload réel plutôt que par un bug signalé. Même mécanisme que [DepenseScreen.md](DepenseScreen.md#justificatif--upload-réel-vers-la-médiathèque-depuis-le-08092026) : `<input type="file" name="fichier_contrat">`, `enctype="multipart/form-data"`, [AttachmentUploader](../Media/AttachmentUploader.md) avec conservation du fichier existant si aucun nouveau n'est choisi.

## Scénarios BDD couverts (voir [SponsorScreenTest.php](../../tests/Unit/Admin/SponsorScreenTest.php))

```gherkin
Scenario: creation d'un sponsor
  Given une requete de formulaire avec nom, montant, date et type_paiement valides
  When saveFromRequest() est appelee
  Then un nouveau sponsor est cree avec ces valeurs

Scenario: type de paiement invalide ignore
  Given une requete avec type_paiement = "cheque-cadeau" (hors liste)
  When saveFromRequest() est appelee
  Then le sponsor est enregistre avec le type par defaut "numeraire"

Scenario: case a cocher non cochee
  Given une requete sans la cle contrat_signe (case non cochee cote navigateur)
  When saveFromRequest() est appelee
  Then contratSigne() vaut false

Scenario: contrat paye bascule automatiquement en recette
  Given une requete avec contrat_paye coche, sans recette_id
  When saveFromRequest() est appelee
  Then le sponsor est cree ET une recette liee est creee (voir SponsorPaiementSync.md)

Scenario: conservation du fichier contrat sans nouveau fichier
  Given un sponsor existant avec un fichier_contrat deja enregistre
  When saveFromRequest() est appelee sans fichier soumis
  Then le fichier existant est conserve, pas efface
```

## En cas de bug

Voir [DepenseScreen.md](DepenseScreen.md#en-cas-de-bug). Spécifique : `contratSigne()` toujours `false` après avoir coché la case → vérifier que le `<input type="checkbox">` porte bien `value="1"` dans `renderForm()`, et que rien n'utilise `isset()` au lieu de `!empty()` dans `saveFromRequest()`.
