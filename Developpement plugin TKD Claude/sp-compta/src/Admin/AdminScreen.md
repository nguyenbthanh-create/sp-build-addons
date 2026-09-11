# Admin/AdminScreen.php

**Rôle** : contrat commun à tout onglet admin du plugin (`slug()` pour l'URL/`?page=`, `title()` pour le `<h1>`, `menuLabel()` pour le libellé dans le menu wp-admin, `render()` pour l'affichage). Implémenté par [FournisseurScreen.php](FournisseurScreen.php), et par chaque futur écran (Client, Dépense, Recette, Sponsor, Paramètres, Devis, Facture).

## En cas de bug

Interface pure, aucune logique — un bug d'affichage vient forcément de la classe qui l'implémente.

## Tests

Pas de test dédié (interface).
