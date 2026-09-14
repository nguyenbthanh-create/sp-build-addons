# Evolution log

## 2026-09-14 — Cahier de révision imprimable généré depuis la base

- Nouveau shortcode `[claira_tkd_parcours_tableau age="Enfant"]` (`includes/print-view.php`) : reproduit le rendu des anciens fichiers statiques `technique_enfant.html` / `technique_adoadulte.html` (tableau complet, pastilles de couleur par ceinture, bilingue coréen/français), mais généré à la volée depuis les grades enregistrés en base plutôt que maintenu à la main dans deux fichiers HTML séparés.
- Objectif : ces deux fichiers servaient de "cahier de révision" téléchargeable/imprimable pour les élèves (mise en place avant que le plugin n'existe). Plutôt que de les regénérer automatiquement à chaque modification (ce qui aurait recréé une seconde source de vérité à synchroniser, avec des questions d'écriture disque côté hébergeur), le même rendu est maintenant produit directement par le plugin à chaque affichage : une seule source de vérité, toujours à jour.
- Extraction de `includes/import.php` : la liste des rangs keup par tranche d'âge (précédemment dupliquée dans `admin-page.php`) devient `claira_tkd_get_keup_options_by_age()`, réutilisée à la fois par le formulaire d'admin et par le tri des lignes du tableau imprimable.
- Ajout d'un rendu spécifique pour l'impression (`@media print` dans `assets/css/style.css`) : fond blanc et texte noir à l'impression, plutôt que le thème sombre de l'affichage à l'écran.
- Un grade dont le champ "Techniques jambes" est vide alors que "Techniques bras" est rempli est affiché sur une cellule fusionnée (comme les grades de révision globale / Poom dans les fichiers d'origine) — heuristique simple basée sur les données existantes, pas de nouveau champ dédié.

## 2026-09-12 — Correction : page blanche après validation d'un formulaire admin

- Bug : après avoir déplacé la gestion des grades dans une page d'admin WordPress (menu « TKD Parcours »), valider un import ou un ajout/édition menait à une page blanche.
- Cause : `wp-admin/admin.php` envoie déjà le HTML de l'en-tête (`admin-header.php`) avant d'appeler le callback de la page. Le traitement du formulaire (et son `wp_safe_redirect()`) se faisait dans ce callback, donc trop tard : les en-têtes HTTP étaient déjà envoyés, la redirection échouait silencieusement et `exit` coupait la page en plein milieu.
- Correction : le traitement du formulaire (`claira_tkd_process_admin_page_form()`) est déplacé sur le hook `load-{hook}` de la page, qui se déclenche avant tout envoi de HTML. Le message d'erreur éventuel (cas où on ne redirige pas) est transmis au rendu de la page via `claira_tkd_admin_page_message()`.

## 2026-09-12 — Gestion des grades entièrement dans le back-office

- Désactivation de l'interface d'édition native WordPress pour `tkd_grade` (`show_ui => false`) et de l'UI native de la taxonomie `tkd_age_group`, qui coexistaient de façon confuse avec le formulaire personnalisé du plugin.
- Suppression de `includes/meta-boxes.php`, devenu inutilisable (plus d'écran d'édition natif pour y accrocher la meta box).
- `includes/admin-frontend.php` renommé en `includes/admin-page.php` et transformé en page d'administration WordPress classique, enregistrée sous un nouveau menu de premier niveau **TKD Parcours** (`add_menu_page`), avec le même contenu qu'avant (import, ajout/édition, liste des grades) mais accessible uniquement depuis le back-office.
- Le shortcode front-end `[claira_tkd_admin]` est retiré : la gestion des grades n'est plus accessible depuis le site public, seul `[claira_tkd_parcours]` (affichage) reste front-end.
- Correction incidente : le script qui adapte la liste des rangs keup selon la tranche d'âge sélectionnée référençait une variable hors de portée (`wp_footer` en dehors de la fonction du formulaire) et ne fonctionnait donc jamais ; il est maintenant généré directement dans la page d'administration.
- Note pour une prochaine évolution : appliquer le style CSS général de sp-build à cette page d'administration.

## 2026-09-12 — Import en masse du référentiel technique

- Ajout de `includes/import.php` : référentiel de progression technique (Enfant, Ado/Adulte) extrait des tableaux HTML `technique_enfant.html` / `technique_adoadulte.html`, et fonction `claira_tkd_run_bulk_import()`.
- Ajout d'un bouton "Importer / Mettre à jour les grades" dans `[claira_tkd_admin]` qui crée ou met à jour tous les grades en une seule action, au lieu d'une saisie manuelle grade par grade.
- Les grades sont retrouvés par (tranche d'âge + rang keup) ; les grades Ado et Adulte partagent le même poste car le référentiel est identique pour ces deux tranches.
- Correction du libellé de rang keup `II Poom` → `Poom` pour la tranche Enfant, pour correspondre au référentiel réel.

## 2026-07-28 — Clickable grade interface

- Removed SVG hotspot overlay and replaced it with direct clickable grade buttons.
- Each grade is rendered as its own `button` element in `includes/shortcodes.php`.
- Modal behavior is managed by `assets/js/script.js` only.
- Removed obsolete hotspot files and documentation references.
