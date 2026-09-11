# Reporting/MouvementsCsvExportateur.php

**Rôle** : construit le CSV "mouvements" d'un exercice — une ligne par dépense/recette, triées par date. Demandé par l'utilisateur le 11/09/2026 : la trésorière a besoin de ce format pour archiver, en complément du JSON brut ([ExerciceExportateur.md](ExerciceExportateur.md), plutôt pensé pour une restauration technique complète) et du rapport AG ([RapportAgGenerator.md](RapportAgGenerator.md), pensé pour une présentation, pas un archivage tabulaire).

## Choix de format pensés pour Excel (pas juste "un CSV")

- **Point-virgule comme séparateur**, pas la virgule : convention française, et ça évite toute ambiguïté avec la virgule décimale des montants.
- **BOM UTF-8** écrit en premier dans le fichier : sans lui, Excel sur Windows affiche les caractères accentués (é, è, €) de travers à l'ouverture directe du fichier.
- **Montant au format décimal français** (`42,50`, pas `42.50`) : Excel-FR le reconnaît comme un nombre calculable (somme automatique possible), pas comme du texte.
- **Dates reformatées en `jj/mm/aaaa`** (pas le format `aaaa-mm-jj` stocké en base) : format attendu par une utilisatrice française ouvrant le fichier dans Excel.

## Montant signé, une seule colonne

Comme la liste "Mouvements" déjà affichée sur [Admin/SoldeScreen.php](../Admin/SoldeScreen.md) (`SoldeScreen::mouvements()`), une dépense est un montant **négatif** et une recette un montant **positif** dans la même colonne "Montant" — une somme Excel sur toute la colonne donne directement le résultat net de la période, sans distinguer manuellement dépenses et recettes.

## Tiers : fournisseur, client, ou provenance en repli

Colonne "Tiers" : nom du fournisseur résolu pour une dépense, nom du client résolu pour une recette. Si une recette n'a **aucun** client lié (cas courant : cotisation, buvette — voir [Entity/Recette.md](../Entity/Recette.md)), la colonne retombe sur le champ libre `provenance` plutôt que de rester vide.

## Protection contre l'injection de formule Excel/CSV

`champSecurise()` préfixe d'une apostrophe tout champ de texte libre (`detail`, `tiers`/provenance) commençant par `=`, `+`, `-`, `@`, une tabulation ou un retour chariot — ces caractères, en tête de cellule, sont interprétés comme le début d'une formule par Excel/LibreOffice à l'ouverture du fichier (risque connu, dit "CSV injection" : un champ "détail" saisi comme `=CMD|'/c calc'!A1` pourrait exécuter du code sur le poste de la trésorière). L'apostrophe force l'affichage en texte, exactement comme le ferait Excel lui-même en saisie manuelle.

## Classe pure, aucun accès base de données

Comme [ExerciceExportateur](ExerciceExportateur.md), ne prend que des entités déjà chargées — c'est [Admin/RapportExerciceScreen.php](../Admin/RapportExerciceScreen.md) qui les récupère via les repositories et positionne les en-têtes HTTP de téléchargement.

## Scénarios BDD couverts (voir [MouvementsCsvExportateurTest.php](../../tests/Unit/Reporting/MouvementsCsvExportateurTest.php))

```gherkin
Scenario: BOM et entetes en premiere ligne
  Given aucun mouvement
  When toCsv() est appelee
  Then le fichier commence par le BOM UTF-8 puis la ligne d'entetes

Scenario: depense en montant negatif avec le nom du fournisseur resolu
  Given une depense liee a un fournisseur connu
  When toCsv() est appelee
  Then la ligne porte le nom du fournisseur et un montant negatif

Scenario: recette en montant positif avec le nom du client resolu
  Given une recette liee a un client connu
  When toCsv() est appelee
  Then la ligne porte le nom du client et un montant positif

Scenario: repli sur la provenance si aucun client n'est lie
  Given une recette avec une provenance mais sans client
  When toCsv() est appelee
  Then la colonne Tiers affiche la provenance

Scenario: neutralisation d'un caractere de formule en tete de champ libre
  Given un detail de depense commencant par "="
  When toCsv() est appelee
  Then le champ est prefixe d'une apostrophe dans le fichier

Scenario: tri chronologique tous types confondus
  Given une recette et une depense dans le desordre chronologique
  When toCsv() est appelee
  Then le mouvement le plus ancien apparait en premier
```

## En cas de bug

- Accents corrompus à l'ouverture Excel → vérifier que le BOM (`"\xEF\xBB\xBF"`) est bien le tout premier octet écrit, avant même la ligne d'en-têtes.
- Montants qu'Excel traite comme du texte (pas de somme automatique possible) → vérifier le séparateur décimal (`,` attendu par Excel-FR) et l'absence de séparateur de milliers dans `number_format()`.
- Une ligne "Tiers" vide pour une recette qui a pourtant une provenance saisie → vérifier que `clientId()` ne retourne pas un id qui ne correspond à aucun client connu (résolution silencieuse en chaîne vide dans ce cas, volontaire — voir [ExerciceExportateur.md](ExerciceExportateur.md) pour la même convention).
