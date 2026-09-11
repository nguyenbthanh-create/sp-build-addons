# Admin/RecetteScreen.php

**Rôle** : écran "Recettes". Symétrique de [DepenseScreen.php](DepenseScreen.php) — mêmes garanties (garde "aucun exercice actif", `exercice_id` en champ caché non ré-assigné automatiquement, mode de paiement validé contre une liste), voir sa doc pour le détail. Différence : menu déroulant **client** (optionnel) au lieu de fournisseur, plus un champ `provenance` en texte libre (ex. "Cotisation", "Buvette") qui coexiste avec le client lié — voir [Entity/Recette.md](../Entity/Recette.md) pour pourquoi les deux existent ensemble.

## Scénarios BDD couverts (voir [RecetteScreenTest.php](../../tests/Unit/Admin/RecetteScreenTest.php))

Identiques à [DepenseScreen.md](DepenseScreen.md#scénarios-bdd-couverts-voir-depensescreentestphp), `client_id` remplaçant `fournisseur_id`, et le référentiel [Categories::RECETTE](../Accounting/Categories.md) remplaçant `Categories::DEPENSE` — même mécanique de dérivation catégorie ← sous-catégorie, voir [DepenseScreen.md](DepenseScreen.md#catégorie--référentiel-officiel-à-deux-niveaux-depuis-le-08092026).

## Recherche (ajouté le 08/09/2026)

Même mécanisme que [DepenseScreen.md](DepenseScreen.md#recherche-ajouté-le-08092026), sur date/montant/provenance/catégorie/détail.

## Justificatif : upload réel

Même mécanisme que [DepenseScreen.md](DepenseScreen.md#justificatif--upload-réel-vers-la-médiathèque-depuis-le-08092026) — `enctype="multipart/form-data"`, [AttachmentUploader](../Media/AttachmentUploader.md) avec conservation du fichier existant si aucun nouveau n'est choisi.

## En cas de bug

Voir [DepenseScreen.md](DepenseScreen.md#en-cas-de-bug).
