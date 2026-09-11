# Front/SaisieRapideShortcode.php

**Rôle** : implémente la demande du 10/09/2026 — une page front-end légère, mobile-first, pour saisir une dépense en quelques secondes en déplacement (montant, catégorie, photo du justificatif via l'appareil photo du téléphone), avec un manifest PWA léger pour l'ajouter à l'écran d'accueil. Shortcode `[sp_compta_saisie_rapide]`, à placer sur n'importe quelle page WordPress (créer une page "Saisie rapide" et y coller le shortcode).

## Pourquoi front-end et pas wp-admin

wp-admin n'est pas pensé pour du tactile/mobile — trop de chrome, pas optimisé pour une saisie à une main en déplacement. Cette page est volontairement minimale (un seul écran, pas de liste, pas de recherche) : elle ne fait qu'**ajouter** une dépense, jamais modifier/supprimer — pour ça, `DepenseScreen` (wp-admin) reste l'outil de référence.

## Réutilisation totale de la logique métier, aucune duplication

Le constructeur prend un `DepenseScreen` **déjà construit** (celui de wp-admin, voir [Plugin.php](../Plugin.php)`::bootFront()`) et appelle directement `$depenseScreen->saveFromRequest($_POST, $_FILES)` dans `handleSave()` — exactement la même validation, la même dérivation catégorie/sous-catégorie, le même upload de justificatif que l'écran admin (voir [DepenseScreen.md](../Admin/DepenseScreen.md)). Cette classe n'ajoute **aucune** logique de sauvegarde propre, uniquement un formulaire HTML différent et le glue WordPress (nonce, capacité, redirection).

## PWA : manifest + service worker (ajouté le 10/09/2026)

`maybeRenderHead()` injecte un `<link rel="manifest">` en **data URI** (`start_url`/`scope` = `"."`, donc pas besoin de connaître à l'avance l'URL de la page — fonctionne quelle que soit la page WordPress où le shortcode est placé) + les meta tags iOS (`apple-mobile-web-app-capable`...) + un `<script>` inline qui enregistre [ServiceWorker::url()](ServiceWorker.md) via `navigator.serviceWorker.register()` — uniquement sur les pages qui contiennent réellement le shortcode (`has_shortcode()` sur le contenu du post courant, jamais sur le reste du site).

**Historique** : la première version (08-10/09/2026) se limitait au manifest, sans service worker, pour rester "légère" — sans lui, pas de bannière d'installation automatique sur Chrome/Android (l'ajout à l'écran d'accueil restait possible via le menu du navigateur). Le service worker a été ajouté séparément une fois le besoin de la bannière automatique confirmé — voir [ServiceWorker.md](ServiceWorker.md) pour pourquoi il n'alourdit pas la solution (pas de règle de réécriture WordPress, pas de cache).

## Icône : SVG généré, pas de fichier image

`iconDataUri()` génère un petit carré navy avec "SP" en data URI — pas de logo du club fourni à ce stade. À remplacer facilement par le vrai logo une fois disponible (voir aussi `logo_url` dans [ParametresScreen](../Admin/ParametresScreen.md), pas encore branché ici).

## Mode de paiement (ajouté le 11/09/2026)

Champ visible (pas dans le `<details>`), juste après la catégorie : `<select name="mode_paiement">` avec les mêmes options que l'écran admin, via [DepenseScreen::modesPaiement()](../Admin/DepenseScreen.md) (nouvelle méthode publique exposant la constante `MODES_PAIEMENT`, jusque-là privée) — pas de liste dupliquée à maintenir à deux endroits. Aucun changement côté `saveFromRequest()` : ce champ était déjà lu et validé (valeur hors liste ignorée), il manquait seulement dans ce formulaire.

## Champs repoussés dans un `<details>` (pas de JavaScript)

Date, fournisseur et détail sont dans un `<details>`/`<summary>` replié par défaut — natif HTML, aucun JS requis, et les champs repliés sont **quand même soumis** normalement (un `<details>` fermé n'empêche pas la soumission de son contenu). La date par défaut (`gmdate('Y-m-d')`, même convention que [DepenseScreen](../Admin/DepenseScreen.md)) est donc bien envoyée même si le bureau ne déplie jamais cette section.

## Création uniquement, jamais modification/suppression

Pas de champ `id` dans le formulaire — `saveFromRequest()` (côté `DepenseScreen`) crée toujours une nouvelle dépense. Corriger une saisie depuis un téléphone se fait plus tard depuis wp-admin.

## En cas de bug

- Le formulaire affiche "Accès réservé" à un membre du bureau qui devrait y avoir accès → vérifier sa capacité `sp_compta_manager` (voir [Capabilities.md](../Capabilities.md)), pas cette classe.
- Le manifest n'apparaît pas dans les outils de dev du navigateur → vérifier que la page contient bien le shortcode dans son contenu (`has_shortcode()`), et que c'est une page singulière (`is_singular()` — ne fonctionne pas sur une page d'accueil de type liste d'articles).
- Upload de la photo qui échoue → voir [AttachmentUploader.md](../Media/AttachmentUploader.md), pas cette classe (le comportement est identique à l'écran admin).
- Redirection après enregistrement qui atterrit sur la page d'accueil au lieu de la page de saisie → vérifier que `redirect_to` est bien soumis et que `wp_validate_redirect()` ne le rejette pas (il refuse toute URL hors du site, par sécurité).

## Tests

[SaisieRapideShortcodeTest.php](../../tests/Unit/Front/SaisieRapideShortcodeTest.php) — couvre les gardes d'accès (capacité, exercice actif) ; le rendu HTML détaillé et le comportement du manifest ne sont pas testés unitairement (comme pour les écrans admin, voir [FournisseurScreen.md](../Admin/FournisseurScreen.md)), à vérifier manuellement sur le site.
