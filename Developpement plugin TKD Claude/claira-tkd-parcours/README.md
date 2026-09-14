# Claira TKD Parcours

Plugin WordPress pour afficher un parcours de progression taekwondo avec contenus texte, ressources téléchargeables et vidéos.

## Fonctionnalités

- Shortcode public : `[claira_tkd_parcours]`
- Shortcode « cahier de révision » imprimable : `[claira_tkd_parcours_tableau age="Enfant"]`
- Page d'administration dans le back-office WordPress (menu « TKD Parcours ») : ajout, édition, suppression et import en masse des grades
- Type de contenu `tkd_grade` (sans écran d'édition natif WordPress — géré uniquement via la page d'administration du plugin)
- Taxonomie : tranches d'âge
- Modal ludique responsive pour chaque grade
- Chaque grade est cliquable directement, sans hotspots SVG
- Stockage de contenu texte et ressources téléchargeables
- Vidéo via URL + option de fichier vidéo téléchargeable
- Import en masse du référentiel technique (Enfant, Ado, Adulte) en un clic

## Installation

1. Copier le dossier `claira-tkd-parcours` dans `wp-content/plugins/`
2. Activer le plugin depuis le menu Extensions de WordPress
3. Ouvrir le menu « TKD Parcours » dans le back-office pour importer et gérer les grades

## Utilisation

### Page publique

Ajouter la balise suivante dans une page ou un article :

```php
[claira_tkd_parcours]
```

### Cahier de révision imprimable

Ajouter la balise suivante pour afficher un tableau complet (une ligne par grade, format « cahier de révision ») imprimable ou téléchargeable en PDF depuis le navigateur (Ctrl+P) :

```php
[claira_tkd_parcours_tableau age="Enfant"]
[claira_tkd_parcours_tableau age="Adolescent"]
```

Ce tableau remplace les anciens fichiers statiques `technique_enfant.html` et `technique_adoadulte.html` : il est généré à la volée depuis les grades enregistrés, donc toujours à jour sans manipulation supplémentaire.

### Gestion des grades (back-office)

La gestion des grades se fait entièrement depuis le menu **TKD Parcours** du back-office WordPress (réservé aux comptes ayant la capacité `edit_posts`), qui permet de :

- importer ou mettre à jour en une fois tous les grades du référentiel technique
- ajouter un nouveau grade
- modifier un grade existant
- supprimer un grade
- renseigner le texte de révision (techniques bras, jambes, poomsae)
- ajouter des ressources téléchargeables
- renseigner une URL vidéo
- envoyer ou remplacer un fichier vidéo local

## Structure du plugin

- `claira-tkd-parcours.php` : point d'entrée principal
- `includes/post-types.php` : enregistre le post type et la taxonomie (sans interface d'édition native)
- `includes/shortcodes.php` : affiche le parcours public
- `includes/import.php` : référentiel technique, rangs keup officiels par tranche d'âge et import en masse des grades
- `includes/print-view.php` : shortcode « cahier de révision » imprimable (`[claira_tkd_parcours_tableau]`)
- `includes/admin-page.php` : page d'administration « TKD Parcours » dans le back-office (menu, formulaire, liste, import)
- `includes/enqueue.php` : charge CSS et JS
- `assets/css/style.css` : styles du plugin
- `assets/js/script.js` : gestion du modal

## Notes

- L'accès public au parcours est libre, sans filtrage par profil.
- Le plugin est conçu pour être responsive et fonctionnel sur PC, tablette et smartphone.
- Les vidéos sont principalement gérées par URL, avec une option de fichier vidéo téléchargeable.
- À faire plus tard : aligner le style visuel de la page d'administration sur le style général de sp-build.
