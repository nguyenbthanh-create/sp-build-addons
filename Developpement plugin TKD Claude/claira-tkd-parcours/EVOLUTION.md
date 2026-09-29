# Evolution log

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
