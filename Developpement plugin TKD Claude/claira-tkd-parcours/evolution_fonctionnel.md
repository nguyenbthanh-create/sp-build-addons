# Évolution Fonctionnelle - Claira TKD Parcours

Ce document répertorie à chaque étape de modification les fonctionnalités validées et leur état d'avancement.

---

## Etape 1 : Socle initial du Plugin (V1.0.0)
- [x] **Enregistrement CPT & Taxonomies** : Création du type de contenu `tkd_grade` et de la taxonomie `tkd_age_group` (`Baby`, `Enfant`, `Adolescent`, `Adulte`).
- [x] **Shortcode Public `[claira_tkd_parcours]`** : Affichage initial des grades et gestion de l'ouverture d'une modale au clic.
- [x] **Shortcode Admin Front-End `[claira_tkd_admin]`** : Formulaire permettant aux gestionnaires d'ajouter, modifier et supprimer des grades directement sur le site sans passer par le back-office WordPress.
- [x] **Gestion des Ressources** : Champs d'administration pour charger jusqu'à 3 liens/fichiers téléchargeables et une vidéo.

---

## Etape 2 : Alignement avec le PDF Programme Technique (V1.1.0)
- [x] **Restructuration du contenu des Grades** : Remplacement du champ texte générique par 3 champs distincts alignés avec le PDF :
  1. *Techniques Bras (Isolées)*
  2. *Techniques Jambes (Isolées)*
  3. *Poomsae*
- [x] **Affichage en Liste Verticale** : Suppression des colonnes par tranche d'âge au profit d'un affichage linéaire/vertical trié par rang Keup (du 16e keup au 1er keup et Pooms), identique à la présentation du PDF.
- [x] **Refonte Graphique Dark Mode** : Adaptation CSS complète aux couleurs du PDF (fond anthracite/noir `#111`, typographie claire, bordures de cartes colorées aux teintes des ceintures, modale moderne avec accents rouges et jaunes).

---

## Etape 3 : Intégration Vidéo Interactive en Modale (V1.2.0)
- [x] **Lecteur Vidéo Intégré (Share Link / Embed)** : Prise en charge automatique des liens de partage YouTube (`youtu.be`, `youtube.com/watch`, `shorts`), Vimeo, et fichiers vidéo direct (`.mp4`, etc.) avec affichage automatique en iFrame ou lecteur HTML5 responsive 16:9 dans la modale.
- [x] **Gestion du son à la fermeture** : Coupure automatique du flux vidéo / mise en pause lors de la fermeture de la modale pour éviter que la bande sonore ne continue en arrière-plan.

---

## Etape 4 : Interface d'Administration en Mode Sombre (V1.3.0)
- [x] **Style Sombre Admin (`[claira_tkd_admin]`)** : Refonte CSS complète du formulaire d'administration front-end et du tableau de gestion des grades (fond sombre `#111`, champs d'édition sombres avec contour `#ffd600` au focus, boutons rouge `#ed1c24` et tableau contrasté).

---

## Etape 5 : Upload de Vidéo Locale & Priorité CSS Dark (V1.4.0)
- [x] **Upload Direct de Fichier Vidéo** : Remplacement du champ numérique par un champ `<input type="file" accept="video/*">` avec `enctype="multipart/form-data"`. Upload automatique des vidéos MP4/WebM dans la Médiathèque WordPress avec raccordement au grade.
- [x] **Priorité CSS Dark Mode (Anti-thème)** : Ajout des directives `!important` et sélecteurs de haute spécificité pour forcer le Mode Dark de l'interface admin face aux thèmes WordPress (ex. SportPress).

---

## Etape 6 : Pastilles Bicolores Dynamiques (V1.5.0)
- [x] **Détection Automatique des Ceintures Bicolores** : Correction des sélecteurs CSS et préservation de l'intitulé complet (ex: `Jaune / Orange`) sans tronquer la seconde couleur. Application du dégradé bicolore 50%/50% sur l'indicateur visuel.

---

## Etape 7 : Filtrage par Tranche d'Âge & Ergonomie d'Édition (V1.6.0)
- [x] **Paramètre de Shortcode `age="..."`** : Support des paramètres `[claira_tkd_parcours age="Baby"]`, `[claira_tkd_parcours age="Enfant"]`, `[claira_tkd_parcours age="Adolescent"]`, `[claira_tkd_parcours age="Adulte"]` pour afficher les ressources par catégorie d'âge isolée.
- [x] **Réinitialisation Automatique après Édition (PRG Pattern)** : Redirection propre après enregistrement/modification pour vider le formulaire et repasser instantanément en mode "Ajouter un grade", accompagné d'un bouton `+ Saisir un autre grade`.




---

## Idée à étudier plus tard (06/10/2026) : fiches A7 de tirage au sort pour les passages de grade
Constat : le candidat doit connaître le programme **rétroactivement** (un 5e keup qui vise le 4e doit maîtriser du 10e au 5e), mais la grille du juge (module Passages de grade de sp_build) ne montre que le programme d'un grade. Décision du 06/10/2026 : **ne pas tout numériser** — le cahier technique imprimé est posé devant le jury, qui interroge au hasard ; le téléphone sert seulement à noter (au besoin une épreuve transverse « Révision des programmes antérieurs », possible sans code).
- [ ] **Bouton « Fiches de tirage A7 »** dans ce plugin (il a déjà le contenu, la charte et les couleurs de ceinture, bicolores comprises) : 8 cartes par A4, à plastifier (pochettes A7 80 × 111 mm), tirées au sort par le candidat.
- Points à trancher avant de coder :
  - **Une carte par grade, ou deux** (paquet « Poomsae » + paquet « Techniques » — préférence : deux, cartes moins chargées, correspond à la pratique « poomsae du grade + poomsae antérieur tiré au sort »).
  - **Tirage à l'aveugle** : couleur de ceinture au recto seulement, verso identique (logo) ou blanc (évite l'alignement recto-verso) ; le jury trie face visible et retire les grades au-dessus du candidat.
  - **Deux jeux distincts Enfant / Ado-Adulte** (programmes différents) : repère visible (pastille « E » / « A » ou bordure).
  - **Lisibilité** : ~9 pt minimum après plastification ; vérifier la longueur réelle des contenus de chaque grade.
  - **Grades inclus** : keups seulement (Dan hors club ; Poom à décider).
  - En option : QR code vers la vidéo du grade, repères de coupe.
- À vérifier au passage : la grille du juge prend le programme du **grade visé** — confirmer que le contenu d'une fiche Parcours « N keup » est bien ce qu'il faut savoir *pour obtenir* ce grade (sinon décalage d'un grade).
