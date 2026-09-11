# Évolution — SP Compta

Pistes d'évolution connues : ce qui est prévu ou envisagé mais pas encore fait, avec la date à laquelle chaque piste a été identifiée.

## Identifié au 10/09/2026 (section « Reste à faire » de CLAUDE.md)

- **Écrans Devis / Facture** : plus complexes que les CRUD déjà faits (sélection client, lignes dynamiques, calcul de total, bouton « transformer en facture »). Pas encore commencés. Le modèle visuel du devis attend un document de référence de l'utilisateur (« type centre de loisirs »).
- **Textes légaux des documents PDF** (mention TVA, numérotation légale affichée...) : à valider document par document au moment de construire chaque export, pas avant. Une proposition de gabarit visuel a été faite, réponse en attente.
- ~~Export vers le formulaire CERFA lui-même~~ — **abandonné, décision utilisateur du 11/09/2026** : les soldes du club restent sous les 10 000 €, largement sous le seuil (23 000 € de subventions publiques cumulées) qui rendrait ce formulaire obligatoire. Le référentiel `Categories.php` reste structuré comme le CERFA (bonne pratique de lisibilité comptable), mais l'export du formulaire lui-même ne sera pas construit.
- ~~Saisie rapide pour les Recettes~~ — **fait le 11/09/2026**, voir `REALISATION.md`.

## Identifié au 11/09/2026 — piste ouverte sur le rapport AG

Le rapport AG ([Reporting/RapportAgGenerator.php](src/Reporting/RapportAgGenerator.md)) compare aujourd'hui les totaux recettes/dépenses de l'exercice actuel à l'exercice précédent (un seul graphique). Piste non demandée pour l'instant, à envisager si le besoin se confirme : un second graphique de répartition par catégorie CERFA (dépenses ou recettes), ou une comparaison sur plusieurs exercices (pas seulement N-1) si le club accumule plusieurs années d'historique.

## Dépendance externe

- La spécification fonctionnelle complète du module est censée être dans `../md/spec-fonctionnelle-module-compta.md`. **Ce fichier est absent sur cet ordinateur** (voir [../JOURNAL.md](../JOURNAL.md)) — à récupérer avant toute évolution majeure pour vérifier qu'elle reste cohérente avec la spec d'origine.

## Comment tenir ce fichier à jour

Ajouter une entrée datée dès qu'une idée d'amélioration apparaît (demande utilisateur, limite constatée en testant), même non urgente. Quand un point ici est réalisé, le déplacer/dupliquer dans `REALISATION.md` avec sa date de réalisation.
