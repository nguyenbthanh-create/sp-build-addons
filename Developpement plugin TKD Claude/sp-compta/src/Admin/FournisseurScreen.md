# Admin/FournisseurScreen.php

**Rôle** : premier écran admin complet du plugin (onglet "Fournisseurs") — liste + formulaire d'ajout/modification + suppression. Sert de **patron de référence** pour les écrans CRUD simples à venir (`Client`, `Depense`, `Recette`, `Sponsor`) : même structure à reproduire (`render()`, `renderForm()`, `renderList()`, `handleSave()`/`handleDelete()` + leurs pendants testables `saveFromRequest()`/`deleteFromRequest()`).

## Pourquoi `saveFromRequest()`/`deleteFromRequest()` sont séparées de `handleSave()`/`handleDelete()`

`handleSave()` et `handleDelete()` sont les vrais points d'entrée WordPress (accrochés sur `admin_post_*`) : elles font la vérification de nonce (`check_admin_referer`), la vérification de capacité, puis terminent par `wp_safe_redirect()` + `exit`. Un `exit` ne peut pas être testé proprement en PHPUnit. **Toute la logique intéressante** (sanitisation des champs, décision insert/update, appel au repository) est donc dans `saveFromRequest()`/`deleteFromRequest()`, qui prennent un tableau (`$_POST`/`$_GET` ou un tableau de test) et ne font ni redirection ni `exit` — directement testables (voir [FournisseurScreenTest.php](../../tests/Unit/Admin/FournisseurScreenTest.php)).

## Recherche (ajouté le 08/09/2026)

`renderSearchBox()` + filtre `$_GET['s']` traité par [Search::matches()](Search.md) dans `renderList()` — recherche "contient", insensible à la casse, sur nom/contact/email/téléphone. Filtre appliqué en PHP sur la liste déjà chargée, pas en SQL (voir [Search.md](Search.md#pourquoi-un-filtre-php-en-mémoire-plutôt-quune-requête-sql-like)).

## Sécurité

- `render()`, `handleSave()`, `handleDelete()` vérifient chacune `current_user_can(Capabilities::MANAGE_COMPTA)` — le menu masque déjà l'accès aux non-autorisés (voir [Menu.md](Menu.md)), mais un accès direct par URL (page ou `admin-post.php`) doit être bloqué indépendamment.
- `check_admin_referer(self::NONCE)` sur les deux actions — protection CSRF standard WordPress, pairée avec `wp_nonce_field()` (formulaire) et `wp_nonce_url()` (lien de suppression).
- Sanitisation systématique en entrée (`sanitize_text_field`, `sanitize_email`, `sanitize_textarea_field` + `wp_unslash`) ; échappement systématique en sortie (`esc_html`, `esc_attr`, `esc_url`, `esc_textarea`) — jamais de valeur utilisateur injectée brute dans le HTML généré.

## Scénarios BDD couverts (voir [FournisseurScreenTest.php](../../tests/Unit/Admin/FournisseurScreenTest.php))

```gherkin
Scenario: creation depuis le formulaire
  Given une requete de formulaire sans id, avec un nom et un email
  When saveFromRequest() est appelee
  Then un nouveau fournisseur est cree en base avec ces valeurs

Scenario: modification depuis le formulaire
  Given un fournisseur existant et une requete avec son id et un nouveau nom
  When saveFromRequest() est appelee
  Then le fournisseur existant est mis a jour, aucun doublon n'est cree

Scenario: suppression
  Given un fournisseur existant et une requete avec son id
  When deleteFromRequest() est appelee
  Then le fournisseur n'existe plus

Scenario: suppression sans id valide
  Given une requete sans id (ou id 0)
  When deleteFromRequest() est appelee
  Then rien n'est supprime et false est retourne
```

## En cas de bug

- Formulaire qui n'enregistre rien → vérifier `saveFromRequest()` isolément (testable sans navigateur) avant de suspecter le nonce/la capacité.
- "Vous n'êtes pas autorisé à accéder à cette page" → capacité `sp_compta_manager` manquante pour l'utilisateur connecté (voir [Capabilities.md](../Capabilities.md)), pas un bug de cet écran.
- Erreur de nonce ("Es-tu sûr de vouloir faire ça ?") → formulaire soumis après expiration de session, ou lien de suppression copié/réutilisé — comportement WordPress normal, pas un bug.
- Champ non affiché/non enregistré après ajout d'une colonne → il faut modifier `renderForm()` (affichage) ET `saveFromRequest()` (sanitisation + passage au constructeur `Fournisseur`) — un oubli de l'un des deux est la cause la plus fréquente.
