# Billing/DevisToFactureConverter.php

**Rôle** : implémente la règle métier centrale des doléances — *"On créer les factures de ces devis, qu'on peut basculer automatiquement de devis en facture (...) si la proposition est acceptée"*. Seul point d'entrée pour transformer un devis en facture ; ne jamais dupliquer cette logique ailleurs (ex. dans un écran admin).

## Ce que fait `convert()`

1. Vérifie que le devis a le statut `accepte` — sinon lève une `RuntimeException` (aucune facture créée).
2. Crée une nouvelle `Facture` avec les **mêmes lignes** que le devis (nouvelles lignes en base, pas de partage de lignes entre devis et facture), `devisId` pointant vers le devis d'origine — la traçabilité demandée dans les doléances ("garder la traçabilité").
3. Fait passer le devis au statut `facture` (il reste en base, consultable, mais ne peut plus être reconverti).
4. Retourne la facture créée (avec son numéro déjà assigné par [FactureRepository](../Repository/FactureRepository.md)).

**Pas de transaction SQL explicite** entre la création de la facture et la mise à jour du devis (2 opérations distinctes) — acceptable pour un outil à 3 utilisateurs non concurrents ; en cas d'échec entre les deux, la facture existe mais le devis reste `accepte` (pas de perte de données, juste un statut à corriger manuellement).

## Scénarios BDD couverts (voir [DevisToFactureConverterTest.php](../../tests/Unit/Billing/DevisToFactureConverterTest.php))

```gherkin
Scenario: conversion d'un devis accepte
  Given un devis au statut "accepte" avec des lignes
  When il est converti en facture
  Then une facture est creee avec les memes lignes et un lien vers le devis d'origine
  And le devis passe au statut "facture"

Scenario: refus de convertir un devis non accepte
  Given un devis au statut "brouillon"
  When on tente de le convertir en facture
  Then une exception est levee et aucune facture n'est creee
```

## En cas de bug

- Facture créée sans lien vers son devis → vérifier que `Facture::devisId()` reçoit bien `$devis->id()`, pas un autre id.
- Devis convertible plusieurs fois → vérifier que le devis est bien rechargé (`find()`) avant un second appel à `convert()` — un objet `Devis` obsolète en mémoire avec encore le statut `accepte` contournerait la garde ; la garde vérifie l'objet passé en paramètre, pas l'état réel en base à l'instant T (pas de verrou pessimiste, acceptable vu le volume d'utilisateurs).
