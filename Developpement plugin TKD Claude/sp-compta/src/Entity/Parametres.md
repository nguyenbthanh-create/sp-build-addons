# Entity/Parametres.php

**Rôle** : objet-valeur immuable représentant les paramètres globaux de l'association (SIRET, siège social, nom, logo — "onglet Paramètres" des doléances). **Singleton logique** : une seule ligne existe jamais en base, voir [ParametresRepository.md](../Repository/ParametresRepository.md) pour la garantie.

## En cas de bug

Aucune logique ici — vérifier l'appelant ([ParametresRepository.php](../Repository/ParametresRepository.php)).

## Tests

Pas de test dédié. Exercée indirectement par [ParametresRepositoryTest.php](../../tests/Unit/Repository/ParametresRepositoryTest.php).
