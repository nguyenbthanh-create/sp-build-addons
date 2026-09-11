# Admin/ClientScreen.php

**Rôle** : écran "Clients", même patron que [FournisseurScreen.php](FournisseurScreen.php) (voir sa doc pour la séparation `handleSave()`/`saveFromRequest()` et les règles de sécurité communes). Seule différence structurelle : le champ `type` (select `particulier`/`collectivite`).

## Spécificité : validation du `type`

`saveFromRequest()` vérifie `in_array($request['type'] ?? '', self::TYPES, true)` avant d'utiliser la valeur soumise — si un POST est trafiqué avec une valeur hors liste, on retombe silencieusement sur `'particulier'` plutôt que de stocker une valeur arbitraire en base.

## Recherche (ajouté le 08/09/2026)

Même mécanisme que [FournisseurScreen.md](FournisseurScreen.md#recherche-ajouté-le-08092026) via [Search::matches()](Search.md), sur nom/ville/email/téléphone.

## Scénarios BDD couverts (voir [ClientScreenTest.php](../../tests/Unit/Admin/ClientScreenTest.php))

Mêmes scénarios que [FournisseurScreen.md](FournisseurScreen.md#scénarios-bdd-couverts-voir-fournisseurscreentestphp), plus :

```gherkin
Scenario: type invalide ignore
  Given une requete de formulaire avec type = "admin" (valeur hors liste)
  When saveFromRequest() est appelee
  Then le client est enregistre avec le type par defaut "particulier"
```

## En cas de bug

Voir [FournisseurScreen.md](FournisseurScreen.md#en-cas-de-bug). Spécifique à cet écran : un type toujours "particulier" alors qu'on a choisi "collectivite" → vérifier que la valeur du `<select>` (`renderForm()`) correspond exactement aux entrées de `self::TYPES` (sensible à la casse).
