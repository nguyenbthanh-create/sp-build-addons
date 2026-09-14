=== Claira TKD Parcours ===
Contributors: Claira
Tags: taekwondo, progression, grade, parcours, ressources, vidéo, shortcode
Requires at least: 5.0
Tested up to: 6.5
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plugin WordPress pour afficher un parcours de progression taekwondo avec contenus texte, ressources téléchargeables et vidéos URL.

== Description ==

Claira TKD Parcours propose un affichage ludique des grades TKD avec un modal responsive par grade. Le plugin permet de stocker du texte, des fichiers téléchargeables et une URL vidéo (optionnellement un fichier vidéo téléchargeable via ID média).

== Fonctionnalités ==

* Shortcode public : [claira_tkd_parcours]
* Shortcode « cahier de révision » imprimable : [claira_tkd_parcours_tableau age="Enfant"]
* Page d’administration dans le back-office WordPress (menu « TKD Parcours ») : ajout, édition, suppression et import en masse des grades
* Type de contenu personnalisé : tkd_grade (sans écran d’édition natif WordPress)
* Taxonomie : tranches d’âge
* Interface responsive pour PC, tablette, smartphone
* Modal animé pour chaque grade
* Chaque grade est cliquable directement, sans hotspots SVG
* Stockage de texte et ressources téléchargeables
* Vidéo principalement via URL, avec option de fichier vidéo téléchargeable
* Import en masse du référentiel technique (Enfant, Ado, Adulte) en un clic

== Installation ==

1. Copier le dossier `claira-tkd-parcours` dans `wp-content/plugins/`
2. Activer le plugin depuis le menu Extensions de WordPress
3. Ouvrir le menu « TKD Parcours » dans le back-office pour gérer les grades (import, ajout, édition, suppression)

== Utilisation ==

Ajouter le shortcode public dans une page ou un article :

    [claira_tkd_parcours]

La gestion des grades (import, ajout, édition, suppression) se fait exclusivement depuis le menu « TKD Parcours » du back-office WordPress, réservé aux comptes ayant la capacité `edit_posts`.

== Shortcodes ==

[claira_tkd_parcours]
: Affiche l’interface publique de visualisation du parcours.

[claira_tkd_parcours_tableau age="Enfant"]
: Affiche un tableau complet imprimable (« cahier de révision ») pour la tranche d’âge donnée (Enfant, Adolescent ou Adulte), généré en direct depuis les grades enregistrés — remplace les anciens fichiers technique_enfant.html / technique_adoadulte.html maintenus à la main.

== Notes ==

* L’accès public est libre, sans filtrage par âge ni grade.
* Les vidéos sont principalement gérées par URL, mais il est possible de proposer un fichier vidéo téléchargeable via ID de média.
* Le plugin est conçu pour être léger et facile à intégrer dans un site utilisant un thème WordPress standard.
* Le style visuel de la page d’administration doit encore être aligné sur le style général de sp-build (prévu pour une prochaine évolution).
