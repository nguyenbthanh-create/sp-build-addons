# Evolution log

## 2026-09-30 — Modification d'un grade directement depuis sa fiche (v1.9.0)

- Page « Apprendre par grade » (`[claira_tkd_parcours]`) : pour les comptes autorisés, le bouton « Modifier ce grade » de la fiche (v1.8.0) passe la fiche en mode édition au lieu d'ouvrir l'administration. Champs modifiables sur place : techniques bras, techniques jambes, poomsae (une technique par ligne, « coréen - français »), âge minimum conseillé, lien vidéo. « Enregistrer » recharge la page et rouvre la fiche avec « Modifications enregistrées. » ; « Annuler » (ou fermer la fiche) demande confirmation s'il y a des changements non enregistrés. Titre, rang, tranche d'âge, fichier vidéo et téléchargements restent dans l'administration (lien en bas du formulaire, avec retour vers la page).
- Nouveau fichier `includes/front-edit.php` : `claira_tkd_save_grade_text_fields()`, nettoyage/enregistrement des champs texte partagé avec le formulaire d'administration (les deux chemins produisent les mêmes données), et `claira_tkd_ajax_front_save_grade()` (admin-ajax, comptes connectés uniquement ; jeton `claira_tkd_front_edit`, droits `edit_posts` + `edit_post` sur le grade, vérification du type `tkd_grade`).
- Testé hors WordPress : gestionnaire d'enregistrement (nettoyage, droits, jeton, mauvais identifiant) et interface (mode édition, annulation, confirmation, message d'erreur, rechargement avec fiche rouverte, affichage mobile). Le premier enregistrement réel se fera sur le site.
- Rappel : les grades Ado et Adulte sont partagés ; modifier un grade depuis l'onglet Ado modifie aussi l'onglet Adulte (ainsi que le schéma et le cahier de révision).

## 2026-09-30 — Bouton « Modifier ce grade » depuis le site (v1.8.0)

- Dans la fiche d'un grade ouverte depuis `[claira_tkd_parcours]` (page « Apprendre par grade »), un bouton « Modifier ce grade » s'affiche pour les seuls comptes autorisés à gérer les grades (droit `edit_posts`, le même que la page d'administration). Il ouvre le formulaire d'administration directement sur ce grade. Les visiteurs ne le voient pas ; il n'apparaît pas dans les fiches du schéma ni du cahier de révision (option `edit_link` de `claira_tkd_render_grade_modal()`).
- La page d'administration affiche alors un bouton « ← Revenir à la page du site » (paramètre `retour`, conservé après l'enregistrement, limité aux adresses du site par `wp_validate_redirect()`).
- Pas de cache de pages sur le site (vérifié le 30/09/2026) : le bouton peut être ajouté côté serveur sans risque d'être servi aux visiteurs. À revoir si un plugin de cache est installé un jour (il doit ignorer les utilisateurs connectés).

## 2026-09-30 — Parcours par grade selon la charte du site (v1.7.0)

- `[claira_tkd_parcours]` (page « Apprendre par grade ») quitte le thème sombre pour la charte des pages de sp-build (Palmarès, Top 5, Événements) : carte blanche à liseré rouge, une ligne par grade séparée par un filet, nom en Montserrat majuscules espacées, pastille de ceinture, rang et tranche d'âge en gris, chevron « › » invitant à ouvrir la fiche. Les étoiles s'affichent en ★ (« Orange ★ » au lieu de « Orange (*) »).
- La fiche d'un grade s'y ouvre désormais en version claire (`claira-tkd-modal--light`), comme depuis le schéma et le cahier de révision.
- Fiche claire (toutes pages) : titre et intertitres en Montserrat, petit trait rouge sous le titre, rouge du site (#D4000F).
- Accessibilité : retrait du `role="img"` qui englobait les boutons de la liste (les rendait invisibles aux lecteurs d'écran).

## 2026-09-29 — Impression du tableau de progression sur une feuille A3 (v1.6.2)

- `[claira_tkd_parcours_tableau]` : bouton « Imprimer / PDF (une feuille A3) » au-dessus de chaque tableau ; à l'impression, format A3 paysage, reste de la page masqué, tableau mis à l'échelle pour remplir la feuille (détail dans `CORRECTIONS.md` §10).
- Numéro de version relevé pour forcer les navigateurs à recharger `style.css` : avec l'ancien fichier en cache (même `?ver=1.6.1`), les règles `.is-printing` manquaient — bouton imprimé, tableau limité à 1200 px de large, donc étriqué sur la feuille.

## 2026-09-29 — Cahier de révision : cartes jusqu'à 900 px (v1.6.1)

- Les cartes remplacent le tableau jusqu'à 900 px de large (au lieu de 700 px), pour couvrir les tablettes en portrait où le tableau obligeait à défiler horizontalement. Les réductions de marges et de titre restent réservées aux téléphones (moins de 700 px).

## 2026-09-29 — Cahier de révision : vidéos et version téléphone (v1.6.0)

- `[claira_tkd_parcours_tableau]` : un bouton « ▶ Vidéo » (ou « Ressources » s'il n'y a que des fichiers) apparaît sous la pastille des grades qui ont une vidéo ou un téléchargement ; il ouvre la fiche du grade (`claira_tkd_render_grade_modal()`, thème clair). Les lignes elles-mêmes ne sont pas cliquables : le texte est déjà dans le tableau.
- Sur téléphone (moins de 700 px), le tableau est remplacé par une carte par grade (rang, pastille, techniques bras / jambes, poomsae, bouton vidéo) au lieu d'un tableau à faire défiler horizontalement.
- À l'impression, le tableau complet est toujours utilisé ; cartes et boutons sont masqués.

## 2026-09-29 — Schéma des grades interactif + cahier de révision en style clair (v1.5.0)

- Les pastilles de `[claira_tkd_schema_grades]` sont cliquables : elles ouvrent la fiche du grade (programme technique bras / jambes, poomsae, téléchargements, vidéo), en thème clair. Un grade sans programme saisi (ex. Baby) affiche « bientôt disponible ». Une ligne d'aide (« Cliquez sur un grade… ») s'affiche sous le bandeau (attribut `aide`, vide pour la masquer).
- La fiche d'un grade est extraite dans `claira_tkd_render_grade_modal()` (`includes/shortcodes.php`) et partagée par `[claira_tkd_parcours]` et `[claira_tkd_schema_grades]`. Les techniques y sont affichées en bilingue coréen / français (comme le cahier de révision) au lieu d'un simple texte avec retours à la ligne ; une description globale (champ jambes vide) est titrée « Programme technique ».
- `assets/js/script.js` : ouverture par tout bouton `data-modal` (cartes et pastilles), modales rattachées à `<body>` (pas de rognage par un conteneur Elementor), focus placé sur « Fermer » à l'ouverture et rendu au bouton à la fermeture.
- `[claira_tkd_parcours_tableau]` passe en style clair (même palette que le schéma : fond clair, tableau blanc, en-têtes dorés). Les étoiles s'y affichent en ★ comme sur le schéma (« Orange ★ » au lieu de « ORANGE (*) »).

## 2026-09-28 — Schéma des grades généré depuis la base (v1.4.0)

- Nouveau shortcode `[claira_tkd_schema_grades]` (`includes/schema-view.php`) : reprend la page « Schéma des grades » du site (colonnes Baby / Enfant / Ado & Adulte, pastilles de ceinture, intertitres d'âge minimum), mais générée à chaque affichage depuis les grades enregistrés au lieu d'un bloc HTML de ~60 pastilles écrites à la main. Style adapté au thème clair du site (fond clair, cartes blanches), palette de ceintures inchangée. Styles dans `assets/css/style.css` (préfixe `.claira-tkd-schema`).
- Nouveau champ « Âge minimum conseillé » par grade (méta `_claira_tkd_min_age`, texte libre : `7`, `14+`…), dans le formulaire et la liste de l'admin. Le schéma précise qu'il s'agit d'un conseil : après examen, les entraîneurs peuvent autoriser un grade plus tôt (« âge min. conseillé » au lieu de « requis »).
- Référentiel d'import complété d'après la page du site : grades Baby (19e à 13e), Il Poom Ado/Adulte (14+), âges minimums de tous les grades. L'import ne remplit l'âge minimum que s'il est vide (un âge ajusté dans l'admin n'est pas écrasé), et ne touche pas aux techniques des grades pour lesquels le référentiel n'en fournit pas (Baby, Il Poom Ado/Adulte).
- Libellés des rangs poom harmonisés sur ceux du site : « Il Poom / Yi Poom / Sam Poom » (au lieu de « Poom / Y Poom » et « II Poom »). Une migration unique (`claira_tkd_migrate_poom_labels()`, option `claira_tkd_poom_labels_migrated`) renomme les valeurs déjà en base pour que l'import retrouve les grades existants au lieu de les dupliquer.

## 2026-09-28 — Page d'administration alignée sur le style de sp-build (v1.3.1)

- La page « TKD Parcours » reprend la structure des pages d'admin de sp-build : `wrap sp-cal-wrap`, sections en boîtes `sp-box`, formulaire en `form-table` WordPress (libellés à gauche, champs `regular-text` / `large-text`, aides en `p.description`), liste des grades en `wp-list-table widefat striped`, boutons natifs WordPress (✏️ / 🗑️ en `button-small`, suppression en `sp-btn-del`).
- Les messages (succès / erreur) utilisent désormais les notices WordPress natives (`notice notice-success is-dismissible`, `notice notice-error`), comme dans sp-build.
- Nouveau fichier `assets/css/admin.css`, chargé uniquement sur cette page : copie des règles générales de `sp-build/assets/css/admin.css` (`.sp-cal-wrap`, `.sp-box`, `.sp-btn-del`, `.sp-muted`) pour que le rendu soit identique même si sp-build n'est pas actif, plus les pastilles de ceinture de la liste (auparavant dans un `<style>` inline avec des `!important`).
- Suppression du thème sombre de l'admin dans `assets/css/style.css` (≈180 lignes de règles `.claira-tkd-admin*` en `!important`) : `style.css` ne sert plus qu'au parcours public et au cahier de révision, et n'est plus chargé dans le back-office.
- Version passée à 1.3.1 pour forcer le rechargement des feuilles de style.

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
