# Corrections apportées

Ce document résume les corrections effectuées dans le plugin `claira-tkd-parcours` afin d'éviter les mêmes erreurs pendant le développement.

## 1. Termes de taxonomie par défaut

- Les taxonomies `tkd_age_group` et `tkd_level` n'avaient pas de termes par défaut.
- Correction : ajout de la fonction `claira_tkd_register_default_terms()` dans `includes/post-types.php`.
- `tkd_age_group` contient maintenant : `Baby`, `Enfant`, `Adolescent`, `Adulte`.
- `tkd_level` contient maintenant : `Débutant`, `Intermédiaire`, `Avancé`.

## 2. Tri des niveaux

- Le menu `Niveau` était trié par ordre alphabétique par défaut.
- Correction : ajout de la fonction `claira_tkd_sort_terms_by_order()` dans `includes/admin-frontend.php`.
- Les niveaux sont désormais triés dans l'ordre métier : `Débutant`, `Intermédiaire`, `Avancé`.

## 3. Interface d’administration front-end

- Ajout du shortcode `[claira_tkd_admin]`.
- Le shortcode affiche désormais :
  - création de grade
  - édition de grade
  - suppression de grade
  - sélection de tranche d’âge et niveau
  - champs texte, ressources téléchargeables, URL vidéo, ID média vidéo

## 4. Styles et accessibilité

- Ajout de styles dédiés pour l’interface admin front-end dans `assets/css/style.css`.
- Ajout du style du modal et des boutons du parcours.

## 5. Correction d’envoi du JS

- Le script JavaScript du modal (`assets/js/script.js`) est désormais chargé pour le frontend.
- Correction dans `includes/enqueue.php` pour enregistrer et enqueuer correctement le script.

## 6. Sécurité et validation

- Ajout de la vérification de nonce pour le formulaire admin front-end.
- Vérification de la capacité utilisateur pour restreindre l’accès à l’administration front-end.

## 7. Documentation

- Ajout de `README.md` et `README.txt` pour documenter l’installation et l’utilisation des shortcodes.

## 8. Ordre métier vs ordre alphabétique

- Important : les filtres métier doivent suivre l’ordre fonctionnel attendu (ex. départ en débutant, puis intermédiaire, puis avancé), et non l’ordre alphabétique.
- Utiliser toujours un tri personnalisé quand l’ordre métier est différent de l’ordre lexical.

## 9. Interaction par grade direct

- Le plugin n'utilise plus de hotspots SVG.
- Chaque grade est maintenant rendu comme un bouton cliquable direct dans `includes/shortcodes.php`.
- L'ouverture des modales est gérée uniquement par `assets/js/script.js`.
- Les anciens fichiers de hotspot ont été supprimés.

## 10. Impression du tableau de progression sur une seule feuille A3 (25/09/2026)

- Problème : imprimer/télécharger en PDF `[claira_tkd_parcours_tableau]` coupait le tableau sur deux pages.
- Solution (`includes/print-view.php` + `assets/css/style.css`) : bouton « Imprimer / PDF (une feuille A3) » ; à l'impression, un script pose `@page { size: A3 landscape }` (dynamiquement, pour ne pas imposer l'A3 aux autres pages du site), masque le reste de la page (en-tête/pied du thème) et réduit le tableau (zoom) jusqu'à ce qu'il tienne sur une feuille. Feuille de style d'impression compactée (fond blanc, texte noir, ceintures en couleur exacte).
- Non testé en conditions réelles (pas de runtime WordPress ici) : à vérifier sur le site de test, dans Chrome et Firefox (Firefox ≥ 126 pour `zoom`).
- Ajustement du 25/09/2026 (retour de l'aperçu d'impression) : le tableau tenait bien sur une page mais était décalé à droite et n'occupait que la moitié de la hauteur. Corrections : marges/paddings/largeurs des conteneurs du thème neutralisés à l'impression, facteur d'échelle recherché par dichotomie (il peut maintenant agrandir le tableau, pas seulement le réduire), et règles d'impression appliquées via une classe `.is-printing` posée avant la mesure (la mise en page mesurée est celle de l'impression, plus celle de l'écran).
- Correctif du 25/09/2026 : avec deux tableaux sur la même page (Enfant + Ado/Adulte), l'impression n'isolait que le premier — le second (masqué comme « reste de la page ») sortait vide. Le bouton cliqué désigne maintenant le tableau à imprimer (`window.clairaTkdPrint(this)`) ; sans bouton (Ctrl+P), le premier tableau est utilisé.
