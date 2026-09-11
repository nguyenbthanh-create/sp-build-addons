# Billing/SponsorPaiementSync.php

**Rôle** : implémente la doléance du 08/09/2026 — *"quand on coche [contrat payé], ça bascule automatiquement dans la partie recette"*. Seul point d'entrée pour cette bascule ; appelé depuis [Admin/SponsorScreen.php](../Admin/SponsorScreen.md)`::saveFromRequest()` après chaque sauvegarde d'un sponsor.

## Idempotence : pas de doublon à chaque modification

`sync()` ne crée une recette **que si** `contratPaye()` est vrai **et** `recetteId()` est encore `null`. Une fois la recette créée, son id est mémorisé sur le sponsor (`Sponsor::withRecetteId()`) — donc ressauvegarder le même sponsor (même avec `contrat_paye` toujours coché) ne génère jamais de seconde recette.

## Ce qui se passe si on décoche "contrat payé" ensuite

**Rien.** La recette déjà créée n'est **jamais supprimée automatiquement** — c'est une donnée comptable réelle, potentiellement déjà rapprochée avec un relevé bancaire ; la supprimer silencieusement suite à une case décochée serait dangereux. Si une recette a été créée par erreur, le bureau doit la supprimer manuellement depuis l'onglet Recettes.

## Recette générée

`provenance` = nom du sponsor, `categorie`/`sousCategorie` = `'74'` / `'sponsors_prives'` (voir [Accounting/Categories.php](../Accounting/Categories.md) — "Sponsors privés" sous "74 - Subvention d'exploitation"), `detail` mentionne explicitement qu'elle a été générée automatiquement — pour que le bureau comprenne d'où elle vient en la retrouvant dans la liste des recettes.

## Scénarios BDD couverts (voir [SponsorPaiementSyncTest.php](../../tests/Unit/Billing/SponsorPaiementSyncTest.php))

```gherkin
Scenario: bascule d'un sponsor paye sans recette liee
  Given un sponsor avec contrat_paye a vrai, sans recette liee
  When sync() est appele
  Then une recette est creee avec le montant et la date du sponsor
  And le sponsor est mis a jour avec l'id de cette recette

Scenario: pas de doublon au second appel
  Given un sponsor deja synchronise (recette_id renseigne)
  When sync() est appele a nouveau
  Then aucune nouvelle recette n'est creee

Scenario: sponsor non paye
  Given un sponsor avec contrat_paye a faux
  When sync() est appele
  Then aucune recette n'est creee
```

## En cas de bug

- Recette dupliquée à chaque modification du sponsor → vérifier que le formulaire ([SponsorScreen.php](../Admin/SponsorScreen.md)) transmet bien un champ caché `recette_id` avec la valeur existante, sinon chaque sauvegarde repart d'un sponsor "sans recette liée" du point de vue de `sync()`.
- Aucune recette créée alors que la case est cochée → vérifier `contratPaye()` sur l'objet réellement passé à `sync()` (pas un objet obsolète construit avant la case cochée).
