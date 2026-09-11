# Media/AttachmentUploader.php

**Rôle** : centralise l'upload de fichier réel (justificatif dépense/recette, contrat sponsor) vers la médiathèque WordPress. Une seule classe réutilisée par [DepenseScreen](../Admin/DepenseScreen.md), [RecetteScreen](../Admin/RecetteScreen.md) et [SponsorScreen](../Admin/SponsorScreen.md) — le nom du champ (`'justificatif'` ou `'fichier_contrat'`) est passé en paramètre, rien de spécifique à un écran ici.

## Ce que fait `handle()`

Appelle `media_handle_upload()` (fonction WordPress standard) qui gère tout le cycle : déplacement du fichier temporaire, création de l'attachment dans la médiathèque, génération des métadonnées. Retourne l'**id d'attachment** (converti en chaîne, colonnes `justificatif`/`fichier_contrat` restant `VARCHAR`) — jamais l'URL ni le chemin, pour que le fichier reste retrouvable même si l'URL du site change plus tard.

**Ne perd jamais un fichier déjà enregistré** : si aucun fichier n'est choisi dans le formulaire (champ laissé vide en modification) ou si l'upload échoue (mauvais type de fichier, erreur réseau...), retourne `$existingValue` tel quel plutôt que d'écraser avec une chaîne vide. C'est l'appelant ([DepenseScreen](../Admin/DepenseScreen.md)`::saveFromRequest()`) qui doit lui fournir la valeur déjà enregistrée en base (rechargée via `find()` si on modifie une ligne existante) — cette classe ne connaît aucune des entités du plugin.

## Prérequis HTML : `enctype="multipart/form-data"`

Un formulaire sans cet attribut sur la balise `<form>` n'envoie **aucun fichier** au serveur — `$_FILES` reste vide côté PHP, sans erreur visible. Les trois écrans qui utilisent cette classe doivent avoir cet attribut sur leur `<form>` de saisie.

## Sécurité

`media_handle_upload()` applique déjà les vérifications standard de WordPress (types de fichiers autorisés via le filtre `upload_mimes`, taille maximale du serveur) — pas de vérification supplémentaire ici. La protection CSRF (nonce) est assurée en amont par `check_admin_referer()` dans chaque écran, avant l'appel à cette classe.

## Scénarios BDD couverts (voir [AttachmentUploaderTest.php](../../tests/Unit/Media/AttachmentUploaderTest.php))

```gherkin
Scenario: aucun fichier choisi
  Given $files ne contient pas la cle du champ
  When handle() est appele avec une valeur existante
  Then la valeur existante est retournee inchangee

Scenario: champ present mais vide (formulaire de modification sans nouveau fichier)
  Given $files[champ]['error'] vaut UPLOAD_ERR_NO_FILE
  When handle() est appele avec une valeur existante
  Then la valeur existante est retournee inchangee
```

Le cas "upload reussi" (appel reel a `media_handle_upload()`) n'est pas couvert par un test unitaire — nécessite un vrai fichier passé par une requête HTTP multipart, hors de portée d'un test unitaire WordPress standard (voir [artisanat de test WP](https://make.wordpress.org/core/) sur `wp_handle_sideload` pour simuler un upload sans HTTP réel, piste pour un futur test d'intégration si besoin).

## En cas de bug

- Fichier jamais reçu côté serveur (`$files` toujours vide) → vérifier `enctype="multipart/form-data"` sur le `<form>`, pas cette classe.
- Fichier remplacé par du vide après modification sans changer le fichier → vérifier que l'appelant transmet bien la valeur **déjà en base** (pas une chaîne vide) comme `$existingValue`.
- Upload refusé silencieusement → le type de fichier n'est probablement pas dans la liste autorisée par WordPress (`upload_mimes`) — comportement WordPress standard, pas un bug de cette classe.
