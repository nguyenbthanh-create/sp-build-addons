# Évolution — SP Compta

Pistes d'évolution connues : ce qui est prévu ou envisagé mais pas encore fait, avec la date à laquelle chaque piste a été identifiée.

## Identifié au 10/09/2026 (section « Reste à faire » de CLAUDE.md)

- **Écrans Devis / Facture** : plus complexes que les CRUD déjà faits (sélection client, lignes dynamiques, calcul de total, bouton « transformer en facture »). Pas encore commencés. Le modèle visuel du devis attend un document de référence de l'utilisateur (« type centre de loisirs »).
- **Textes légaux des documents PDF** (mention TVA, numérotation légale affichée...) : à valider document par document au moment de construire chaque export, pas avant. Une proposition de gabarit visuel a été faite, réponse en attente.
- **Export vers le formulaire CERFA lui-même** (pas juste l'alignement sur ses codes de catégories) : pas demandé pour l'instant, mais le référentiel `Categories.php` est structuré pour le permettre le jour où ce sera utile.
- **Saisie rapide pour les Recettes** : seule la Dépense est couverte pour l'instant (cas d'usage principal : un achat en déplacement). Le même patron que la saisie rapide Dépense serait facile à dupliquer.

## Dépendance externe

- La spécification fonctionnelle complète du module est censée être dans `../md/spec-fonctionnelle-module-compta.md`. **Ce fichier est absent sur cet ordinateur** (voir [../JOURNAL.md](../JOURNAL.md)) — à récupérer avant toute évolution majeure pour vérifier qu'elle reste cohérente avec la spec d'origine.

## Comment tenir ce fichier à jour

Ajouter une entrée datée dès qu'une idée d'amélioration apparaît (demande utilisateur, limite constatée en testant), même non urgente. Quand un point ici est réalisé, le déplacer/dupliquer dans `REALISATION.md` avec sa date de réalisation.
