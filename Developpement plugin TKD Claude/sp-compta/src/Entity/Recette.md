# Entity/Recette.php

**Rôle** : objet-valeur immuable représentant une ligne de recette (entrée d'argent sur le compte bancaire). Symétrique de [Depense.php](Depense.php).

## Différence avec Depense

`provenance` est un champ texte libre (ex. "Cotisation", "Buvette") ; `clientId` est optionnel et ne se remplit que lorsque la recette correspond au règlement d'une facture — les deux peuvent coexister (provenance = libellé affiché, clientId = lien structuré si connu).

## `categorie` / `sousCategorie`

Même référentiel à deux niveaux que [Depense.md](Depense.md#categorie--souscategorie-référentiel-à-deux-niveaux-depuis-le-08092026), avec [Accounting/Categories::RECETTE](../Accounting/Categories.md).

## En cas de bug

Aucune logique ici — vérifier l'appelant ([RecetteRepository.php](../Repository/RecetteRepository.php)).

## Tests

Pas de test dédié. Exercée indirectement par [RecetteRepositoryTest.php](../../tests/Unit/Repository/RecetteRepositoryTest.php).
