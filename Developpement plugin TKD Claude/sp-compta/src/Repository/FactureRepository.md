# Repository/FactureRepository.php

**Rôle** : CRUD pour facture + ses lignes, numérotation automatique à la création. Symétrique de [DevisRepository.php](DevisRepository.php) — mêmes garanties et mêmes choix (lignes en "supprimer puis réinsérer", numéro assigné uniquement à l'insertion via [DocumentNumeroGenerator](../Numbering/DocumentNumeroGenerator.md)).

## Différence volontaire avec DevisRepository

**Pas de méthode `delete()`.** Une facture émise ne se supprime jamais (obligation légale de continuité de numérotation) — pour annuler une facture, charger via `find()`, appeler `withStatut(Facture::STATUT_ANNULEE)`, puis `save()`. Si un besoin de suppression apparaît un jour (ex. brouillon jamais envoyé), il faudra l'ajouter explicitement avec une garde sur le statut — ne pas la rajouter par simple souci de symétrie avec `DevisRepository`.

## Scénarios BDD couverts (voir [FactureRepositoryTest.php](../../tests/Unit/Repository/FactureRepositoryTest.php))

Mêmes scénarios que [DevisRepository.md](DevisRepository.md#scénarios-bdd-couverts-voir-devisrepositorytestphp) (numérotation à la création, numéros distincts, lecture avec lignes, remplacement de lignes sans duplication), avec le préfixe `F` au lieu de `D`, moins le scénario de suppression (qui n'existe pas ici).

## En cas de bug

Voir [DevisRepository.md](DevisRepository.md#en-cas-de-bug) — mêmes causes probables, `facture_id` remplaçant `devis_id` dans les lignes.
