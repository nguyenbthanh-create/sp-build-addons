# Admin/DepenseScreen.php

**Rôle** : écran "Dépenses". Même patron que [FournisseurScreen.php](FournisseurScreen.php), avec deux différences : dépend d'un **exercice actif** ([ExerciceRepository::active()](../Repository/ExerciceRepository.md)) et propose un menu déroulant fournisseur ([FournisseurRepository](../Repository/FournisseurRepository.md)).

## Garde "aucun exercice actif"

`render()` s'arrête après un message si `exerciceRepository->active()` retourne `null` — pas de formulaire affiché, impossible de créer une dépense sans exercice. Voir [ParametresScreen.md](ParametresScreen.md) pour créer/activer un exercice au préalable.

## `exercice_id` : champ caché, pas ré-assigné automatiquement

Le formulaire porte un champ caché `exercice_id`, pré-rempli avec l'exercice **de la dépense existante** en modification, ou avec l'exercice actif par défaut en création. Choix volontaire : si le bureau modifie une ancienne dépense après avoir changé d'exercice actif, elle ne doit **pas** basculer silencieusement vers le nouvel exercice — l'historique reste attaché à son exercice d'origine.

## Catégorie : référentiel officiel à deux niveaux (depuis le 08/09/2026)

Un seul `<select name="sous_categorie">` regroupé en `<optgroup>` par code CERFA (`"60 - Achat"`, `"61 - Services extérieurs"`...), chaque `<option>` étant une sous-catégorie ([Accounting/Categories::DEPENSE](../Accounting/Categories.md)) — pas deux menus déroulants dépendants en JavaScript. Le formulaire ne soumet **que** la sous-catégorie choisie ; `saveFromRequest()` en déduit le code catégorie via `Categories::categorieDeSousCategorie()`, pour que les deux restent toujours cohérents entre eux (impossible d'avoir une catégorie sans rapport avec sa sous-catégorie). Une sous-catégorie inconnue (formulaire trafiqué) vide les deux champs plutôt que de stocker une valeur arbitraire.

## `modesPaiement()` : liste exposée publiquement (ajouté le 11/09/2026)

`MODES_PAIEMENT` reste la constante privée de référence, mais `DepenseScreen::modesPaiement()` (statique) l'expose pour être réutilisée ailleurs sans dupliquer la liste — utilisé par [SaisieRapideShortcode](../Front/SaisieRapideShortcode.md) pour proposer le même champ mode de paiement en saisie rapide qu'en admin.

## Colonne Fournisseur dans la liste (ajouté le 10/09/2026)

`renderList()` précharge tous les fournisseurs en un tableau `id => nom` avant la boucle (une seule requête `fournisseurRepository->all()`, pas une par ligne) plutôt que d'appeler `find()` pour chaque dépense — reste correct même si le même fournisseur revient sur plusieurs lignes. Le nom résolu entre aussi dans la recherche ([Search::matches()](Search.md)), pas seulement affiché.

## Recherche (ajouté le 08/09/2026)

`renderSearchBox()` + filtre `$_GET['s']` traité par [Search::matches()](Search.md) dans `renderList()`, sur date/montant/catégorie (libellé, pas la clé technique)/détail.

## Justificatif : upload réel vers la médiathèque (depuis le 08/09/2026)

`<input type="file" name="justificatif">` (le `<form>` porte `enctype="multipart/form-data"`, indispensable pour qu'un fichier soit transmis — voir [AttachmentUploader.md](../Media/AttachmentUploader.md)). `saveFromRequest()` recharge la dépense existante via `repository->find()` quand `id` est fourni, pour connaître le justificatif **déjà enregistré** avant d'appeler `AttachmentUploader::handle()` — indispensable pour ne pas effacer le fichier existant quand on modifie une dépense sans en choisir un nouveau. Affiché en liste et dans le formulaire ("Fichier actuel : ...") via [AttachmentLink::render()](../Media/AttachmentLink.md).

## Scénarios BDD couverts (voir [DepenseScreenTest.php](../../tests/Unit/Admin/DepenseScreenTest.php))

Mêmes principes que [FournisseurScreen.md](FournisseurScreen.md), plus :

```gherkin
Scenario: mode de paiement invalide ignore
  Given une requete avec mode_paiement = "bitcoin" (hors liste)
  When saveFromRequest() est appelee
  Then la depense est enregistree avec mode_paiement vide plutot que la valeur invalide

Scenario: categorie deduite de la sous-categorie choisie
  Given une requete avec sous_categorie = "fourniture_bureau"
  When saveFromRequest() est appelee
  Then la depense porte sous_categorie = "fourniture_bureau" ET categorie = "60" (deduite)

Scenario: sous-categorie invalide ignoree
  Given une requete avec sous_categorie hors de toute categorie connue
  When saveFromRequest() est appelee
  Then categorie et sous_categorie sont tous les deux enregistres vides

Scenario: conservation du justificatif sans nouveau fichier
  Given une depense existante avec un justificatif deja enregistre
  When saveFromRequest() est appelee sans fichier soumis
  Then le justificatif existant est conserve, pas efface
```

## En cas de bug

Voir [FournisseurScreen.md](FournisseurScreen.md#en-cas-de-bug). Spécifique : une dépense "disparaît" de la liste après modification → vérifier qu'elle n'a pas changé d'`exercice_id` involontairement (le champ caché doit toujours porter l'exercice d'origine, pas l'actif courant). Catégorie vide après enregistrement alors qu'une option était bien sélectionnée → vérifier que la clé de la sous-catégorie soumise existe encore dans [Categories::DEPENSE](../Accounting/Categories.md) (elle a pu être renommée/supprimée depuis).
