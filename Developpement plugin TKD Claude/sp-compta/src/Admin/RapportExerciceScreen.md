# Admin/RapportExerciceScreen.php

**Rôle** : écran "Rapport / Export", ajouté le 11/09/2026. Pour chaque exercice, liste trois actions : une sauvegarde brute (JSON complet, voir [Reporting/ExerciceExportateur.php](../Reporting/ExerciceExportateur.md)), un export CSV des mouvements pour archivage dans Excel (voir [Reporting/MouvementsCsvExportateur.php](../Reporting/MouvementsCsvExportateur.md), ajouté le même jour à la demande de la trésorière) et un rapport financier présentable pour l'AG (HTML imprimable, voir [Reporting/RapportAgGenerator.php](../Reporting/RapportAgGenerator.md)). Cet écran ne fait que **récupérer les données et les passer** à ces trois classes — aucune logique de mise en forme ou de calcul ici.

## Pourquoi un écran séparé plutôt qu'un bouton sur SoldeScreen

Les deux exports couvrent tout l'historique (tous les exercices, pas seulement l'actif) alors que [SoldeScreen](SoldeScreen.md) ne montre que l'exercice actif — un écran dédié avec la liste complète des exercices est plus clair qu'un bouton isolé sur un écran pensé pour autre chose.

## Les deux actions passent par `admin-post.php`, pas par un affichage direct

`handleExportJson()` et `handleRapportAg()` sont enregistrées comme actions `admin_post_*` (comme tous les autres écrans du plugin), chacune avec sa propre vérification de nonce + capacité. Contrairement aux formulaires de sauvegarde des autres écrans, ce sont ici de simples liens `<a>` (pas de `<form>` POST) : le nonce est porté dans l'URL via `wp_nonce_url()`, exactement comme les liens "Supprimer" des autres écrans.

- `handleExportJson()`/`handleExportCsv()` : positionnent les en-têtes `Content-Type`/`Content-Disposition` (téléchargement forcé) puis `echo`/`exit` — aucun rendu de page wp-admin autour.
- `handleRapportAg()` : `echo`/`exit` directement le HTML complet renvoyé par `RapportAgGenerator::render()` — bypasse volontairement le thème wp-admin (menu, sidebar) pour que la page s'imprime proprement. Ouvert dans un nouvel onglet (`target="_blank"` sur le lien) pour ne pas perdre la liste des exercices.

## Trouver l'exercice précédent

`exercicePrecedent()` cherche l'exercice dont l'id correspond dans la liste triée par `date_debut` décroissante ([ExerciceRepository::all()](../Repository/ExerciceRepository.md)) puis renvoie l'entrée **suivante** dans cette liste (chronologiquement antérieure). Renvoie `null` si l'exercice demandé est le plus ancien connu (premier exercice suivi) — dans ce cas [RapportAgGenerator](../Reporting/RapportAgGenerator.md) affiche une note plutôt qu'un graphique de comparaison.

## En cas de bug

- Bouton "Rapport AG" qui ouvre une page blanche → vérifier `check_admin_referer()`/la capacité `sp_compta_manager`, pas [RapportAgGenerator](../Reporting/RapportAgGenerator.md).
- Comparaison manquante alors qu'un exercice précédent existe bien → vérifier `exercicePrecedent()` : la liste `ExerciceRepository::all()` est triée par `date_debut` décroissante, un exercice avec une date de début incohérente (typo de saisie) fausserait l'ordre et donc la comparaison.
- Fichier JSON qui ne contient pas les devis/factures attendus → vérifier que `DevisRepository::forExercice()`/`FactureRepository::forExercice()` filtrent bien sur le bon `exercice_id`, pas cette classe qui se contente de les transmettre.
