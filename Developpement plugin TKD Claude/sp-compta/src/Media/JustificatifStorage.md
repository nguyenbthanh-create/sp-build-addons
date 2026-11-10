# Media/JustificatifStorage.php

**Rôle** (10/11/2026) : garder les justificatifs (factures, tickets, contrats de sponsor) hors de la médiathèque publique. Avant, ils allaient dans `uploads/AAAA/MM/`. Chacun s'ouvrait sans connexion par son adresse, facile à deviner (`Facture_F2611….pdf`), et toute la liste était publique à `/wp-json/wp/v2/media`.

## Rangement

- **Dossier** : `uploads/sp-compta/AAAA/MM/`, fermé au web par un `.htaccess` (`Require all denied`, plus `Deny from all` pour Apache 2.2), avec un `index.php` vide. `protegerDossier()` le crée, et peut être rappelée sans risque.
- **Les fichiers restent des attachments WordPress.** Les colonnes `justificatif` et `fichier_contrat` gardent l'**id**, rien ne change en base côté SP Compta. Seuls `_wp_attached_file`, `_wp_attachment_metadata['file']` et le `guid` de l'attachment pointent vers le nouveau dossier.
- **Nouveaux envois** : [AttachmentUploader](AttachmentUploader.md) pose le filtre `upload_dir` (`filtreUploadDir()`) pendant `media_handle_upload()`, y compris pour les vignettes d'image et les aperçus PDF. Il refuse l'envoi plutôt que de déposer dans un dossier public si le dossier fermé n'a pas pu être créé.
- **Marque `_sp_compta_protege`** sur chaque justificatif : ils sont exclus de la médiathèque (grille, liste `upload.php`, REST), où leurs vignettes seraient cassées.

## Consultation

`url($id)` donne `admin-post.php?action=sp_compta_fichier&id=…`, et `servir()` traite la demande :
- si le visiteur n'est pas connecté, il est renvoyé vers la page de connexion ;
- sans la capacité `sp_compta_manager`, il reçoit un 403 ;
- seuls les fichiers dont le chemin réel est sous `uploads/sp-compta/` sont servis : un autre id d'attachment répond 404 ;
- PDF et images s'affichent dans le navigateur (`inline`), les autres types sont téléchargés, et l'en-tête `nosniff` est envoyé.

[AttachmentLink](AttachmentLink.md) affiche ce lien pour un justificatif protégé, et l'adresse d'origine tant qu'un fichier n'est pas encore déplacé.

## Déplacement des anciens justificatifs : `deplacer($id)`

La méthode déplace le fichier principal, ses tailles intermédiaires (`sizes`) et l'`original_image` du même dossier, puis met à jour les métadonnées. Elle renvoie :
- `deplace` : le fichier a été rangé ;
- `deja` : il était déjà protégé ;
- `absent` : aucun fichier sur le disque, rien à protéger ;
- `echec` : le déplacement n'a pas pu se faire. Un fichier déjà présent dans la cible n'est jamais écrasé.

Lancé par [JustificatifMigration](JustificatifMigration.md).

## Scénarios BDD

```gherkin
Scenario: nouveau justificatif
  Given un compte trésorerie enregistre une dépense avec une photo
  Then le fichier est dans uploads/sp-compta/AAAA/MM/
  And son adresse directe répond 403
  And le lien de la liste ouvre le fichier pour ce compte

Scenario: visiteur sans droit
  Given un compte connecté sans sp_compta_manager
  When il ouvre admin-post.php?action=sp_compta_fichier&id=…
  Then il reçoit un 403

Scenario: id d'un autre média
  Given l'id d'une image publique du site
  When un compte trésorerie l'ouvre par ce lien
  Then il reçoit un 404 (seul uploads/sp-compta/ est servi)
```

## En cas de bug

- **Vignette ou lien cassé dans une liste** : le fichier est déplacé mais le lien est l'ancienne adresse. Vérifier que l'écran passe bien par `AttachmentLink::render()`.
- **« Fichier introuvable » sur un justificatif récent** : regarder `_wp_attached_file` de l'attachment (il doit commencer par `sp-compta/`) et la présence du fichier sur le disque.
- **Envoi refusé sans message** : le dossier `uploads/sp-compta/` n'a pas pu être créé (droits FTP).
