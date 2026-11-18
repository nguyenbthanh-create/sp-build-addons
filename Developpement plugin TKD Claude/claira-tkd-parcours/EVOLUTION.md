# Evolution log

## 2026-11-18 — QCM Dan : les deux dates de création de la FFTDA (v1.14.6, pas encore en ligne)

Des fiches trouvées sur Internet donnent le 1er septembre 1995 comme date de création. Les quiz officiels ont en fait deux questions, avec deux dates :
- « Qu'est-il arrivé le 1er septembre 1995 ? » (historique fédéral, n° 10) : le CNT se transforme en FFTDA ;
- « Quand et où la FFTDA a-t-elle été fondée selon l'article 1er des statuts ? » (statuts, n° 1) : le 14 octobre 1994, à la Préfecture de Police de Paris.

La première n'était pas trouvée par « création FFTDA », car elle ne contient aucun mot de création.

Changements dans `assets/js/qcm-dan.js` :
- nouvel objet `COMPLEMENTS` pour ajouter des éléments à une question sans toucher `data/qcm-dan.js`, qui est régénéré depuis les PDF :
  - mots-clés cachés (poids 4) ;
  - encadré « 💡 À retenir » (`.claira-qcm-note` dans `assets/css/qcm-dan.css`) ;
- les deux questions ont des mots-clés (création, fondation, naissance, date) et la même note : deux dates, et comment les distinguer d'après l'énoncé.

Vérifié hors WordPress :
- « création FFTDA », « date de création de la FFTDA » et « naissance FFTDA » donnent les deux questions en tête, avec la note ;
- « DAC » est inchangé.

Ajout du même jour : les annales 2e Dan 2022/2023 (PDF « QCM 2e Dan MAJ sept 2023 », énoncés sans réponses) posent « Quelle est la date de création de la FFTDA ? » dans la partie historique. La note l'indique : réponse attendue, le 1er septembre 1995 ; le 14 octobre 1994 seulement si l'énoncé cite les statuts.

## 2026-11-18 — QCM Dan : « date de création de la FFTDA » trouvée (v1.14.5, test puis prod le 18/11/2026 vers 13 h 30)

Retour d'un adhérent : à l'examen, il a eu la question de la date de création de la FFTDA. La page https://www.fftda.fr/fr/99-historique-federal.html était déjà couverte par le quiz officiel « Historique fédéral » ; la question des statuts (« Quand et où la FFTDA a-t-elle été fondée… ») était aussi dans les données. Le problème venait de la recherche : « date de création de la FFTDA » ne trouvait rien.
- `assets/js/qcm-dan.js` :
  - création, créée, créé, fondée, fondé, fondation… sont ramenés à un même mot (« fond » seul n'est pas touché) ;
  - « date » et « dates » sont ignorés : aucune question ne les emploie, elles disent « quand » ou « en quelle année » ;
  - poids de l'énoncé passé de 3 à 4 : à égalité, la question qui parle du sujet passe devant celle qui ne le cite que dans une réponse.
- **Vérifié hors WordPress** : « date de création de la FFTDA » et « FFTDA fondée » donnent en tête la question des statuts (réponse : 14 octobre 1994, Préfecture de Police de Paris) ; « DAC » et « âge DAC » inchangés.
- Le PDF « QCM 1er Dan MAJ sept 2023 » (annales 2022/2023 du Module B, 30 énoncés sans réponses) ne contient pas cette question ; ses thèmes sont déjà couverts par les quiz officiels.

## 2026-11-18 — QCM Dan : recherches sans résultat gardées (v1.14.3, en prod le 18/11/2026)

Demande de l'utilisateur après l'essai sur iPhone : repérer ce que les adhérents cherchent sans le trouver.
- **Page du site** : une recherche sans résultat est envoyée une fois par visite (texte, mode, thème) à `POST claira-tkd/v1/qcm/sans-resultat` (`noterSansResultat()` dans `assets/js/qcm-dan.js`). Le tableau de bord n'envoie rien.
- **Serveur** (`includes/qcm-dan.php`) :
  - écriture réservée aux comptes du club (en-tête `X-WP-Nonce`) et aux adhérents Ado / Adulte non expirés ;
  - pour les adhérents, un tri rapide est fait sur le jeton (forme, `eid`, `exp`), puis la session est vérifiée auprès de sp_build (`spcal/v1/eleve/me`), avec mise en cache 6 h ;
  - 40 envois par heure et par personne ; 300 textes au plus, regroupés sans tenir compte de la casse ni des espaces ;
  - option `claira_tkd_qcm_recherches_vides`. Ni nom ni adresse IP ne sont gardés.
- **Tableau de bord QCM Dan** : section « Recherches sans résultat » avec la recherche, le nombre de fois, la dernière fois, et la colonne « Aujourd'hui ». Celle-ci refait la recherche avec les questions actuelles : par exemple, « DAC durée » trouve maintenant 2 questions. Boutons Chercher, Retirer et Tout effacer.
- **Vérifié** :
  - hors WordPress : 11 contrôles du serveur, envoi depuis la page, tableau ;
  - sur le test : anonyme et faux jeton refusés (401), adhérent fictif QCM TEST accepté (200), lignes visibles dans le tableau de bord.
- Le site de test est lent par moments (1,5 à 21 s sur une requête simple), sans lien avec ce module.

## 2026-11-18 — QCM Dan : retours de l'essai sur iPhone (v1.14.2, en prod le 18/11/2026)

- **Sigles à points** : les quiz écrivent « D.A.C. », « D.I.F. », « C.Q.P. M.A.M. », « D.E.J.E.P.S. ». La normalisation en faisait des lettres isolées, donc « DAC » ne trouvait pas « Quelle est la durée de la formation D.A.C. ? » (16 h, Filière de formation n° 6). `normaliser()` lit désormais un sigle à points comme un mot, sans « lookbehind » pour rester compatible avec les anciens Safari ; le surlignage suit.
- **Croix d'effacement** dans le champ de recherche : elle vide le champ et les résultats, et garde le clavier ouvert. La croix native de Safari est masquée pour éviter d'en avoir deux.
- **Examen blanc** : le survol reste collé sous le doigt sur téléphone et encadrait en rouge la proposition suivante. Le survol est réservé à la souris (`@media (hover: hover) and (pointer: fine)`), le focus est retiré, et la nouvelle question arrive avec une animation de 0,25 s (désactivée si l'utilisateur limite les animations).
- **Rappels** (sans changement) : les réponses sont mélangées, mais seul l'ordre des lettres change. Le texte de la bonne réponse est toujours celui de la FFTDA (dans les PDF, c'est B dans 64 % des cas). « Tous les mots » (par défaut) exige chaque mot, ce qui affine ; « Au moins un mot » en accepte un seul.
- Recherches sans résultat : voir 1.14.3 ci-dessus. L'ajout de questions propres au club n'est pas décidé.

## 2026-11-17 — QCM Dan : accès depuis l'application et retour (v1.14.1, en prod le 18/11/2026)

- **Application `/app/` (sp_build `e911bb6`)** : bouton « 🎓 Réviser le QCM Dan » sur l'Accueil, sous « Ma fiche complète », pour les adhérents Ado / Adulte dont l'adhésion n'est pas « expire » (même règle que `qcm-acces.js`). L'adresse vient de `claira_tkd_qcm_page_url()`.
- **iPhone** : le lien s'ouvre dans la même fenêtre. Le périmètre de l'application installée (`scope` « / ») couvre tout le site : la page reste dans l'application et y lit `spcal_jwt`. Aucun jeton dans l'adresse.
- **« ← Retour à l'application »** (`assets/js/qcm-dan.js`, `boutonRetour()`) en haut de la page : affiché en mode autonome (application installée, sans bouton Précédent), ou si l'on arrive depuis `/app/` (mémorisé pour l'onglet, `sessionStorage` « claira_qcm_depuis_app »).
- Le mode autonome n'a pas pu être simulé : à vérifier sur un vrai téléphone.

## 2026-11-17 — QCM Dan réservé aux adhérents (v1.14.0)

- **Accès** (décision de l'utilisateur du 17/11/2026) : la page `[claira_qcm_dan]` et son entrée de menu ne s'affichent qu'aux adhérents **actifs, à jour de leur adhésion** (statut ≠ « expire »), de **catégorie Ado / Adulte**, et **connectés à l'application du club `/app/`** sur l'appareil.
  - Les adhérents n'ont pas de compte WordPress : `assets/js/qcm-acces.js`, imprimé dans `<head>` de chaque page, lit la session de l'application (`localStorage` « spcal_jwt ») et interroge sp_build (`GET spcal/v1/eleve/me`, en-tête `X-Spcal-Jwt`). Le résultat est gardé 6 h par session (`claira_qcm_acces`).
  - Les comptes WordPress du club (`edit_posts`, `sp_gestion_adhesions` ou `sp_cal_voir_planning`) ont toujours accès, reconnus côté serveur. Un simple client de la boutique n'est pas reconnu.
- **Menu** : toute entrée de menu qui mène à une page contenant `[claira_qcm_dan]` reçoit la classe `claira-qcm-reserve` (filtre `wp_nav_menu_objects`). Elle est cachée par CSS tant que `<html>` n'a pas la classe `claira-qcm-ok`. Il n'y a rien à régler dans l'éditeur de menu.
- **Page** : sans accès, elle affiche « Réservé aux adhérents du club » avec un lien vers l'application (page qui contient `[sp_cal_app]`). Les liens d'examen `?examen=…` sont soumis à la même règle. Le tableau de bord n'est pas concerné.
- **Limite** : les questions (`data/qcm-dan.js`) restent téléchargeables, car elles viennent de PDF publics de la FFTDA. C'est une réservation de confort. Sur iPhone, une application installée sur l'écran d'accueil ne partage pas sa session avec Safari : l'accès depuis l'application elle-même est l'étape suivante (onglet ou lien dans `/app/` pour les Ado / Adulte).
- **Testé hors WordPress** (banc d'essai, API de sp_build simulée), avec 7 cas : visiteur, Ado à jour, adhésion expirée, Enfant, Adulte « expire bientôt », session invalide, compte du club. Entrée de menu et page conformes dans chaque cas. PHPCS et PHPStan sans erreur.

## 2026-11-17 — QCM Dan : révision du Module B (v1.13.0)

- **Données** : `data/qcm-dan.js` (`window.ClairaQcmDonnees`, pas de .json : refusé par le `.htaccess` du plugin), extrait des 32 quiz « Module B » de la FFTDA (fftda.fr > Réglementation), soit 724 questions à 4 réponses avec la source (article de règlement). La bonne réponse n'est indiquée que par du texte vert dans les PDF : l'outil `outils/qcm-dan/` lit la couleur (voir son `LISEZMOI.md` pour régénérer). Chaque question a exactement une bonne réponse ; plusieurs vérifiées à la main contre les PDF.
- **Tableau de bord : TKD Parcours → QCM Dan** (droit `edit_posts`). Champ de recherche : un ou plusieurs mots (tous les mots / au moins un mot), "expression exacte" entre guillemets, ou question entière collée (classement par pertinence, 15 au plus). Recherche sans accents ni majuscules, pluriels et fautes de frappe tolérés, variantes Poumse / Poomsae / Pumse, Kyorugi / combat… Chaque question affiche ses 4 réponses, la bonne en vert, la source et le lien vers le PDF FFTDA. Filtre par thème (un thème sans mot = toutes ses questions).
- **Examen blanc sous plusieurs formes**, à partir des questions cochées ou d'un **tirage au sort** (nombre au choix, 30 ou 15, thèmes au choix) : impression du sujet (cases à cocher, en-tête nom / date) avec ou sans **corrigé** (grille des lettres + réponses et sources) ; **projection** plein écran en salle (une question à la fois, Espace pour révéler la réponse, flèches, chronomètre) ; test en ligne sur place ; **enregistrement d'un examen en ligne** (option `claira_tkd_qcm_examens`) dont le lien `?examen=CODE` ouvre la même série sur la page du site avec chronomètre et correction. Ordre des réponses (et des questions) mélangeable : dans les PDF, la bonne réponse est B dans 64 % des cas.
- **Site : `[claira_qcm_dan]`** (attributs `titre`, `sous_titre`, `questions`, `duree`). Onglets Rechercher (réponses affichées), Examen blanc (30 questions en 15 min, ou 15 pour les 5e-6e Dan, comme sur IzziDan ; réponses mélangées ; correction des erreurs avec la source), Réviser par thème (correction immédiate ; questions ratées gardées sur l'appareil, `localStorage`).
- Tout tourne dans le navigateur (`assets/js/qcm-dan.js`, sans bibliothèque ni CDN) ; CSS et JS chargés seulement sur la page du shortcode et la page QCM Dan.
- Testé hors WordPress (banc d'essai PHP avec fonctions WP simulées, PHP 8.2) : recherche (mots, expression, question collée, fautes de frappe), sélection, tirage, impression, projection, examen en ligne et correction, lien d'examen enregistré, révision par thème, affichage téléphone sans défilement horizontal. PHPCS et PHPStan sans erreur. Enregistrement / suppression d'un examen vérifiés sur le site de test.
- **Mise en production le 17/11/2026** (`3c727bb`), vérifiée en ligne : TKD Parcours → QCM Dan (724 questions) et page publique « Révision QCM Dan » (`/revision-qcm-dan/`, pas encore dans le menu).

## 2026-10-23 — Vidéothèque (v1.11.0)

- Nouveau shortcode `[claira_tkd_videotheque]` (`includes/videotheque.php`), page **publique** : toutes les vidéos du club en vignettes, groupées par thème (**Poomsae, Techniques, Combat (Kyorugi), Self-défense (Hosinsul), Casse, Autres**), avec filtres par thème (pastilles), par grade (liste groupée par tranche d'âge) et recherche (sans tenir compte des accents). Sous chaque vidéo : les grades où elle est au programme, groupés par tranche d'âge avec la pastille de ceinture. Charte du site (titre Montserrat + trait rouge, cartes blanches, pastilles à contour noir / rouge actif). Attributs facultatifs `titre`, `sous_titre`, `theme` (une page par thème si besoin).
- **Vidéos des grades reprises automatiquement** : le lien vidéo de chaque grade alimente la vidéothèque (thème Poomsae) ; un même lien présent sur plusieurs grades ne donne qu'**une** vignette (au 23/10/2026 : 11 vidéos Kukkiwon pour 34 grades). Rangement dans l'ordre de la progression (19e keup → Poom/Dan), puis par titre.
- **Titres repris de YouTube / Vimeo** (oEmbed, sans clé d'API), une fois par vidéo, gardés dans l'option `claira_tkd_vt_titres` ; « TAEGEUK 1JANG » devient « Taegeuk 1 Jang », l'auteur Kukkiwon est abrégé. Au plus 3 demandes par affichage de la page publique (les autres vidéos ont un titre de secours — le poomsae du grade — jusqu'au prochain affichage) ; la page d'administration complète tous les titres manquants. Un échec est retenté au plus une fois par jour.
- **Page légère et sans traceur avant lecture** : vignettes `i.ytimg.com` en chargement différé ; le lecteur `youtube-nocookie.com` n'est créé qu'au clic, dans une fenêtre (modale claire existante), puis retiré à la fermeture. `script.js` : `closeModal()` émet l'évènement `claira-tkd-fermeture` avant de recharger les iframes, sinon la vidéo se serait relancée en autoplay fenêtre fermée.
- **Administration : TKD Parcours → Vidéothèque** (droit `edit_posts`, comme la page Grades). Type de contenu `tkd_video` (sans écran natif) : lien YouTube/Vimeo, titre (facultatif), thème, grades concernés, description courte, ordre d'affichage (0 = automatique), « masquer ». Liste de toutes les vidéos avec vignette et source ; une vidéo venant d'un grade se « Personnalise » (fiche créée avec le même lien : titre, thème, grades en plus, ordre, masquer) ; supprimer la personnalisation la remet en automatique. Une seule fiche par vidéo (refus d'un doublon). Les grades qui ont déjà le lien dans leur fiche apparaissent cochés et grisés.
- Les fiches de grade, le schéma et le cahier de révision sont inchangés (leur lecteur YouTube habituel reste en place). Les fichiers vidéo hébergés sur le site (champ « Fichier vidéo local » d'un grade) ne sont pas repris dans la vidéothèque — aucun n'existe à ce jour.
- Testé hors WordPress (page de démonstration avec le HTML produit, vrais `style.css` / `script.js`) : vignettes, filtres thème / grade / recherche, message « aucune vidéo », ouverture et lecture, fermeture (Échap, ×, fond) qui arrête la vidéo, retour du focus, affichage téléphone (2 colonnes, sans défilement horizontal). Le PHP n'a pas pu être exécuté ici (pas de PHP sur ce poste) : à passer dans `outils\verifier.ps1` sur le PC du travail ; premier affichage réel sur le site.
- **Mise en production le 23/10/2026** (`28e083c`), vérifiée en ligne : administration (11 vidéos, titres YouTube) et page publique « Vidéothèque Poomsae » (`/videotheque-poomsae/`). **Correction le jour même** (`307c521`) : ordre de progression faussé par les grades Baby (numérotation de keup propre à chaque tranche d'âge) → `claira_tkd_vt_comparer_progression()` compare dans la première tranche d'âge commune (Ado, Adulte, Enfant, Baby) ; grades de même nom dans une tranche d'âge suffixés de leur rang (« Rouge / Noire (Yi Poom) »). Ordre vérifié en ligne : Taegeuk 1 → 8, Koryo, Keumgang, Taebaek.

## 2026-09-30 — Cahier de révision : titres par tranche d'âge et bouton A3 selon la charte (v1.10.0)

- `[claira_tkd_parcours_tableau]` : le titre devient la seule tranche d'âge (« Enfant », « Ado / adulte ») en Montserrat fin majuscules avec le trait rouge du site ; le sous-titre précise l'âge concerné (« De 7 à 11 ans », « À partir de 11 ans révolus »). Le filet rouge sous l'en-tête est remplacé par le trait de la charte.
- Nouveaux attributs facultatifs `titre` et `sous_titre` pour changer ces textes page par page sans toucher au code, ex. `[claira_tkd_parcours_tableau age="Enfant" sous_titre="De 7 à 10 ans"]`.
- Bouton d'impression : « Imprimer / PDF (A3) », pastille à contour noir (charte du site) au lieu du bouton rouge avec émoji ; fonctionnement de l'impression A3 inchangé.

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
