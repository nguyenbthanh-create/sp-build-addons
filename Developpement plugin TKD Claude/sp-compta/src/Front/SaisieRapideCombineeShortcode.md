# Front/SaisieRapideCombineeShortcode.php

**Rôle** : le shortcode public `[sp_compta_saisie_rapide]`, depuis le 11/09/2026. Assemble [SaisieRapideShortcode.php](SaisieRapideShortcode.md) (Dépense) et [SaisieRapideRecetteShortcode.php](SaisieRapideRecetteShortcode.md) (Recette) sur une seule page, avec deux boutons pour basculer instantanément de l'un à l'autre.

## Pourquoi ce changement

Avant, Dépense et Recette étaient deux shortcodes distincts (`[sp_compta_saisie_rapide]` et `[sp_compta_saisie_rapide_recette]`), chacun pensé pour sa propre page installable séparément sur l'écran d'accueil. Demande utilisateur le 11/09/2026, après avoir testé : un moyen "intuitif" de passer de l'un à l'autre, sans avoir à se souvenir de deux URLs ou à naviguer entre deux pages. Cette classe remplace les deux entrées séparées par une seule page à deux onglets.

## Bascule en JavaScript pur, pas de rechargement de page

Les deux formulaires sont **tous les deux présents dans le HTML dès le chargement** (chacun via l'appel direct à `$this->depenseShortcode->render()`/`$this->recetteShortcode->render()`, aucune requête Ajax) ; seul celui qui n'est pas actif porte l'attribut `hidden`. Cliquer sur un bouton d'onglet bascule uniquement quel panneau est `hidden`, via un petit script inline (pas de dépendance externe, cohérent avec le reste du projet — voir `CLAUDE.md`). Chaque formulaire garde son propre `<form>` et poste vers sa propre action `admin_post_*` : aucune fusion de la logique de sauvegarde, uniquement de l'affichage.

## Onglet actif après enregistrement (ajouté le 11/09/2026)

Sans précaution, enregistrer une recette puis être redirigé vers la page rouvrirait par défaut l'onglet Dépense — le message de confirmation "Recette enregistrée" resterait caché derrière l'onglet inactif, invisible sans clic supplémentaire. `render()` lit `$_GET['sp_compta_saved']` (`'depense'` ou `'recette'`, voir [SaisieRapideShortcode.md](SaisieRapideShortcode.md#confirmation-après-enregistrement-sp_compta_saveddepense)) pour ouvrir directement le bon onglet après la redirection.

## Manifest PWA unique

Un seul `<link rel="manifest">`/service worker pour la page entière (icône SVG générée, nom "Trésorerie") — remplace les deux manifests distincts qui existaient avant le 11/09/2026 sur les deux anciens shortcodes séparés. `maybeRenderHead()` reprend le même principe que l'ancien code : injection uniquement sur les pages qui contiennent réellement `[sp_compta_saisie_rapide]` (`has_shortcode()`), jamais sur le reste du site.

## Scénarios BDD couverts (voir [SaisieRapideCombineeShortcodeTest.php](../../tests/Unit/Front/SaisieRapideCombineeShortcodeTest.php))

```gherkin
Scenario: les deux formulaires sont presents, Depense actif par defaut
  Given aucun parametre indiquant un enregistrement recent
  When render() est appelee
  Then les deux panneaux sont dans le HTML, seul celui de la Recette porte hidden

Scenario: l'onglet Recette se rouvre apres l'enregistrement d'une recette
  Given sp_compta_saved=recette dans l'URL (redirection post-enregistrement)
  When render() est appelee
  Then le panneau Recette est visible (pas de hidden), celui de la Depense l'est
```

## En cas de bug

- Le manifest n'apparaît pas dans les outils de dev du navigateur → vérifier que la page contient bien `[sp_compta_saisie_rapide]` dans son contenu (`has_shortcode()`), et que c'est une page singulière (`is_singular()` — ne fonctionne pas sur une page d'accueil de type liste d'articles).
- Les deux formulaires apparaissent superposés/mal alignés → CSS du bascule (`.sp-compta-tabs*`) dans cette classe, CSS de chaque formulaire (`.sp-compta-saisie-rapide`) dans les deux classes d'origine — vérifier lequel des deux est en cause avant de corriger.
- Cliquer sur un onglet ne fait rien → vérifier dans la console navigateur que le script inline s'est bien exécuté (pas d'erreur JS ailleurs sur la page qui l'aurait interrompu) ; ce script ne dépend d'aucune librairie externe.
- Message de confirmation invisible après un enregistrement pourtant réussi → voir la section "Onglet actif après enregistrement" ci-dessus, et vérifier que la redirection porte bien la bonne valeur `sp_compta_saved`.

## Saisie jamais perdue et reconnexion (06/10/2026)

Suite aux déconnexions de l'application installée (voir [Auth/SessionLongue.md](../Auth/SessionLongue.md)) :
- **Non connecté** : `render()` n'affiche plus les deux formulaires mais un écran « Connexion nécessaire » avec un bouton vers `wp_login_url()` qui **ramène à la page de saisie**.
- **Brouillon** (JavaScript) : chaque champ (montant, catégorie, projet, mode, date, fournisseur/client, détail…) est mémorisé dans le `localStorage` du téléphone pendant la saisie (clé `sp_compta_brouillon_depense` / `_recette`) et remis en place au rechargement, avec un message et un bouton « Effacer » ; l'onglet Recette se rouvre si c'est une recette. Brouillon effacé quand l'enregistrement est confirmé (`sp_compta_saved=depense|recette`). La **photo** ne peut pas être conservée par un navigateur.
- **Avant chaque envoi** : appel AJAX `sp_compta_saisie_jeton` (`ajaxJeton()` / `jetons()`) qui renvoie des jetons de formulaire neufs si la session est ouverte (la page a pu rester ouverte des heures : un jeton expiré faisait échouer l'envoi). Session fermée → rien n'est envoyé, message « Session expirée : votre saisie est gardée… Se reconnecter ». Compte sans accès → message dédié. Réponse illisible / réseau incertain → envoi classique (le serveur tranche).
- Constantes `NONCE` / `ACTION_SAVE` de `SaisieRapideShortcode` et `SaisieRapideRecetteShortcode` rendues publiques pour cela.

Scénarios ajoutés : `it_shows_a_login_button_instead_of_the_forms_when_logged_out`, `it_hands_out_fresh_form_tokens_to_a_treasury_account`, `it_refuses_tokens_to_an_account_without_treasury_access`. Le comportement JavaScript (brouillon, restauration, session expirée, envoi avec jeton neuf, effacement après confirmation) a été vérifié dans un navigateur avec un serveur simulé.
