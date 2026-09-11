# Reporting/RapportAgGenerator.php

**Rôle** : génère la page HTML imprimable du rapport financier présentable en Assemblée Générale — même principe que le reçu PDF/HTML de `tkd-cotisations` (page autonome, bouton "Imprimer / PDF", pas de dépendance externe). Ajoutée le 11/09/2026 en réponse directe à une demande utilisateur : un export "communicable lors de l'AG", avec une comparaison à l'exercice précédent.

## Contenu du rapport

Solde initial, totaux recettes/dépenses, résultat net, solde de clôture ; répartition recettes/dépenses par catégorie CERFA (réutilise [Admin/SoldeScreen::repartitionHierarchique()](../Admin/SoldeScreen.php), aucune logique de regroupement dupliquée) ; liste des sponsors avec total ; et, si un exercice précédent est connu, un diagramme en barres comparant recettes/dépenses des deux exercices.

## Pourquoi un SVG généré en PHP, pas une librairie de graphiques

Cohérent avec le reste du projet ("pas d'outillage", pas de build, pas de CDN externe — voir `CLAUDE.md`) : `svgComparaisonBarres()` calcule directement les rectangles proportionnellement au montant maximum et les sérialise en `<svg>` inline. Aucun script JS requis, ce qui fonctionne aussi bien à l'écran qu'à l'impression (contrairement à un graphique rendu par une librairie JS, qui ne s'imprime pas toujours correctement).

## Classe pure : tous les chiffres sont déjà calculés par l'appelant

`render()` ne fait aucun accès base de données — [Admin/RapportExerciceScreen.php](../Admin/RapportExerciceScreen.md) calcule les totaux et répartitions puis les passe en paramètres. Rend la classe testable sans fixture de base de données, et sépare clairement "aller chercher les données" de "les mettre en forme".

## Pas de comparaison si aucun exercice précédent

Quand `$totalRecettesPrecedent`/`$totalDepensesPrecedent`/`$labelExercicePrecedent` valent `null` (premier exercice suivi, ou exercice précédent supprimé), `render()` affiche une note ("Aucun exercice antérieur enregistré...") à la place du graphique plutôt que de tenter un calcul avec des valeurs manquantes.

## Division par zéro évitée

`svgComparaisonBarres()` prend `max(...)` sur les quatre montants pour l'échelle ; si tous valent zéro (exercice tout juste créé, aucun mouvement), la valeur plancher `1.0` est utilisée à la place pour éviter une division par zéro — le graphique s'affiche alors avec des barres à zéro plutôt que de planter.

## Scénarios BDD couverts (voir [RapportAgGeneratorTest.php](../../tests/Unit/Reporting/RapportAgGeneratorTest.php))

```gherkin
Scenario: le graphique de comparaison affiche les quatre montants
  Given des montants pour l'exercice precedent et l'exercice actuel
  When svgComparaisonBarres() est appelee
  Then le SVG contient les quatre montants formates

Scenario: pas de division par zero quand tous les montants sont a zero
  Given un exercice sans aucun mouvement
  When svgComparaisonBarres() est appelee
  Then le SVG est genere sans NAN ni INF

Scenario: comparaison affichee quand un exercice precedent est connu
  Given un exercice avec des totaux d'exercice precedent fournis
  When render() est appelee
  Then le rapport contient le graphique SVG, pas la note d'absence

Scenario: note affichee a la place du graphique en l'absence d'exercice precedent
  Given le tout premier exercice suivi (aucun precedent)
  When render() est appelee sans totaux precedents
  Then le rapport contient la note, aucun SVG
```

## En cas de bug

Un montant manquant/faux dans le rapport → vérifier le calcul en amont dans [RapportExerciceScreen](../Admin/RapportExerciceScreen.md) (totaux, répartition), pas cette classe qui ne fait qu'afficher ce qu'on lui donne. Un graphique visuellement écrasé (toutes les barres à la même hauteur) → vérifier que `$max` n'est pas resté à sa valeur plancher alors que de vrais montants existent (indiquerait un calcul en amont incorrect, tous les montants passés à zéro par erreur).
