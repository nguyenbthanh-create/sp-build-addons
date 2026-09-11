# Entity/Sponsor.php

**Rôle** : objet-valeur immuable représentant un sponsor. `typePaiement` vaut `'numeraire'`, `'nature'` ou `'competence'` (chaîne libre, validation en couche formulaire — même choix que [Client::type()](Client.md)). `contratSigne` pilote l'affichage du bouton export PDF du contrat côté admin (pas encore implémenté).

## `contratPaye` / `recetteId` (ajoutés le 08/09/2026)

Deux champs distincts de `contratSigne` — un contrat peut être signé sans être encore payé. `contratPaye` déclenche la bascule automatique en recette ([Billing/SponsorPaiementSync.php](../Billing/SponsorPaiementSync.md)) ; `recetteId` (nullable) mémorise la recette déjà générée pour ce sponsor, pour que la bascule ne se fasse **qu'une seule fois** (pas de doublon à chaque modification du sponsor). `withRecetteId()` suit le même patron immuable que `withId()`.

## En cas de bug

Aucune logique ici — vérifier l'appelant ([SponsorRepository.php](../Repository/SponsorRepository.php)).

## Tests

Pas de test dédié. Exercée indirectement par [SponsorRepositoryTest.php](../../tests/Unit/Repository/SponsorRepositoryTest.php).
