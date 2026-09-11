# Front/SaisieRapideShortcode.php

**Rôle** : implémente la demande du 10/09/2026 — un formulaire front-end léger, mobile-first, pour saisir une dépense en quelques secondes en déplacement (montant, catégorie, photo du justificatif via l'appareil photo du téléphone).

**Depuis le 11/09/2026, ce n'est plus un shortcode public.** Jusque-là `[sp_compta_saisie_rapide]` pointait directement ici ; désormais le shortcode public du même nom est géré par [SaisieRapideCombineeShortcode.php](SaisieRapideCombineeShortcode.md), qui assemble ce formulaire et celui des recettes sur une seule page avec un bouton pour basculer de l'un à l'autre (demande utilisateur : passer de Dépense à Recette "intuitivement", sans se souvenir de deux URLs différentes). Cette classe reste responsable du formulaire Dépense lui-même (`render()`) et de son enregistrement (`handleSave()`), simplement plus de sa propre inscription en tant que shortcode WordPress ni de son propre manifest PWA (un seul manifest désormais, porté par la classe combinée).

## Pourquoi front-end et pas wp-admin

wp-admin n'est pas pensé pour du tactile/mobile — trop de chrome, pas optimisé pour une saisie à une main en déplacement. Cette page est volontairement minimale (un seul écran, pas de liste, pas de recherche) : elle ne fait qu'**ajouter** une dépense, jamais modifier/supprimer — pour ça, `DepenseScreen` (wp-admin) reste l'outil de référence.

## Réutilisation totale de la logique métier, aucune duplication

Le constructeur prend un `DepenseScreen` **déjà construit** (celui de wp-admin, voir [Plugin.php](../Plugin.php)`::bootFront()`) et appelle directement `$depenseScreen->saveFromRequest($_POST, $_FILES)` dans `handleSave()` — exactement la même validation, la même dérivation catégorie/sous-catégorie, le même upload de justificatif que l'écran admin (voir [DepenseScreen.md](../Admin/DepenseScreen.md)). Cette classe n'ajoute **aucune** logique de sauvegarde propre, uniquement un formulaire HTML différent et le glue WordPress (nonce, capacité, redirection).

## PWA : manifest + service worker

Depuis le 11/09/2026, cette classe n'en porte plus — voir [SaisieRapideCombineeShortcode.md](SaisieRapideCombineeShortcode.md) pour le manifest unique (icône SVG générée, service worker via [ServiceWorker.md](ServiceWorker.md)) qui couvre désormais les deux formulaires.

## Mode de paiement (ajouté le 11/09/2026)

Champ visible (pas dans le `<details>`), juste après la catégorie : `<select name="mode_paiement">` avec les mêmes options que l'écran admin, via [DepenseScreen::modesPaiement()](../Admin/DepenseScreen.md) (nouvelle méthode publique exposant la constante `MODES_PAIEMENT`, jusque-là privée) — pas de liste dupliquée à maintenir à deux endroits. Aucun changement côté `saveFromRequest()` : ce champ était déjà lu et validé (valeur hors liste ignorée), il manquait seulement dans ce formulaire.

## Champs repoussés dans un `<details>` (pas de JavaScript)

Date, fournisseur et détail sont dans un `<details>`/`<summary>` replié par défaut — natif HTML, aucun JS requis, et les champs repliés sont **quand même soumis** normalement (un `<details>` fermé n'empêche pas la soumission de son contenu). La date par défaut (`gmdate('Y-m-d')`, même convention que [DepenseScreen](../Admin/DepenseScreen.md)) est donc bien envoyée même si le bureau ne déplie jamais cette section.

## Création uniquement, jamais modification/suppression

Pas de champ `id` dans le formulaire — `saveFromRequest()` (côté `DepenseScreen`) crée toujours une nouvelle dépense. Corriger une saisie depuis un téléphone se fait plus tard depuis wp-admin.

## Confirmation après enregistrement (`sp_compta_saved=depense`)

`handleSave()` redirige avec `sp_compta_saved=depense` (pas juste `=1`) — nécessaire depuis que ce formulaire partage sa page avec celui des recettes ([SaisieRapideCombineeShortcode.md](SaisieRapideCombineeShortcode.md)) : sans cette distinction, enregistrer une dépense aurait aussi affiché à tort le message de confirmation "Recette enregistrée" dans l'autre onglet. `renderForm()` n'affiche son message que si la valeur vaut exactement `'depense'`.

## En cas de bug

- Le formulaire affiche "Accès réservé" à un membre du bureau qui devrait y avoir accès → vérifier sa capacité `sp_compta_manager` (voir [Capabilities.md](../Capabilities.md)), pas cette classe.
- Le manifest n'apparaît pas dans les outils de dev du navigateur → voir [SaisieRapideCombineeShortcode.md](SaisieRapideCombineeShortcode.md#en-cas-de-bug), plus géré ici.
- Upload de la photo qui échoue → voir [AttachmentUploader.md](../Media/AttachmentUploader.md), pas cette classe (le comportement est identique à l'écran admin).
- Redirection après enregistrement qui atterrit sur la page d'accueil au lieu de la page de saisie → vérifier que `redirect_to` est bien soumis et que `wp_validate_redirect()` ne le rejette pas (il refuse toute URL hors du site, par sécurité).
- Message "Dépense enregistrée" absent après un enregistrement pourtant réussi → vérifier que la redirection porte bien `sp_compta_saved=depense` et pas une autre valeur.

## Tests

[SaisieRapideShortcodeTest.php](../../tests/Unit/Front/SaisieRapideShortcodeTest.php) — couvre les gardes d'accès (capacité, exercice actif) ; le rendu HTML détaillé et le comportement du manifest ne sont pas testés unitairement (comme pour les écrans admin, voir [FournisseurScreen.md](../Admin/FournisseurScreen.md)), à vérifier manuellement sur le site.
