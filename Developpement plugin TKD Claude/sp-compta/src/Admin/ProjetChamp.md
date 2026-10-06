# Admin/ProjetChamp

## Rôle

Champ « Projet (facultatif) » partagé par les formulaires **Dépense** et **Recette** (wp-admin) et par les deux **saisies rapides** (téléphone, shortcode `[sp_compta_saisie_rapide]`) : une seule façon d'afficher la liste, une seule façon de valider la valeur reçue. Placé entre Fournisseur/Client et Catégorie dans les formulaires admin, juste après la catégorie dans la saisie rapide.

## API

- `options(?ProjetRepository, exerciceId, ?selectionne)` : projets « en cours » de la saison + le projet déjà rattaché s'il est clôturé (sinon modifier la ligne le ferait perdre en silence).
- `select(Projet[], ?selectionne, id)` : balise `<select name="projet_id">` avec « — Fonctionnement courant (aucun projet) — » ; **chaîne vide s'il n'y a aucun projet** (le champ n'apparaît pas tant qu'aucun projet n'existe).
- `resoudre(?ProjetRepository, request, exerciceId, ?existant)` :
  - champ absent de la requête → garde `$existant` (un formulaire sans le champ n'efface jamais un rattachement) ;
  - champ vide → `null` (fonctionnement courant) ;
  - sinon le projet doit exister **et** être de la même saison que la ligne, sinon `null`.
- `noms(?ProjetRepository, exerciceId)` : index id ⇒ nom pour la colonne « Projet » des listes.

## Scénarios (tests : `tests/Unit/Admin/DepenseScreenTest.php`)

```gherkin
Scénario: une dépense est rattachée à un projet de sa saison
Scénario: un projet d'une autre saison est ignoré
Scénario: modifier une dépense sans le champ « projet » garde son projet
Scénario: choisir « fonctionnement courant » détache la dépense
```

## En cas de bug

- Champ invisible : aucun projet « en cours » dans la saison active (le créer dans l'onglet Projets), ou `ProjetRepository` non passé à l'écran (voir `Plugin::bootAdmin()` / `bootFront()`).
- Rattachement perdu à la modification : vérifier que le formulaire envoie bien `projet_id` ou ne l'envoie pas du tout (jamais une valeur vide par erreur).
