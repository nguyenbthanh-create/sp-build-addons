# Entity/Client.php

**Rôle** : objet-valeur immuable représentant un client (particulier ou collectivité), destinataire des devis/factures. Même patron que [Fournisseur.php](Fournisseur.php).

## Convention

`type()` vaut `'particulier'` ou `'collectivite'` (chaîne libre, pas d'enum PHP natif — cible PHP >= 7.4, voir [composer.json](../../composer.json)). La validation des valeurs autorisées se fera dans la couche formulaire admin, pas ici.

## En cas de bug

Aucune logique ici — un client mal formé vient de l'appelant ([ClientRepository.php](../Repository/ClientRepository.php) ou du formulaire admin).

## Tests

Pas de test dédié. Exercée indirectement par [ClientRepositoryTest.php](../../tests/Unit/Repository/ClientRepositoryTest.php).
