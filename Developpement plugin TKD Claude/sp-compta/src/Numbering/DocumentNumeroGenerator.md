# Numbering/DocumentNumeroGenerator.php

**Rôle** : formate un numéro de document lisible (`F2026-0001`, `D2026-0001`) à partir du compteur atomique de [SequenceGenerator.php](SequenceGenerator.php). Un `DocumentNumeroGenerator` par type de document, instancié avec son préfixe (`'F'` pour facture, `'D'` pour devis) — voir câblage dans [Repository/FactureRepository.php](../Repository/FactureRepository.php) et [Repository/DevisRepository.php](../Repository/DevisRepository.php).

## Format

`{PREFIXE}{ANNEE}-{SEQUENCE sur 4 chiffres}`. La séquence repart à 1 à chaque nouvelle année (clé `{prefixe}_{annee}` dans `sp_compta_sequence`), pas à chaque exercice comptable — l'année utilisée est celle de la date du document (`date_creation` pour un devis, `date_emission` pour une facture), pas la date du jour.

**Rappel légal** (voir [spec-fonctionnelle-module-compta.md](../../../md/spec-fonctionnelle-module-compta.md)) : ce format couvre la mécanique de génération, pas le texte légal affiché sur le document — celui-ci reste à valider document par document.

## En cas de bug

- Numéro avec la mauvaise année → vérifier la valeur `$annee` passée par l'appelant (le repository), pas cette classe qui ne fait que formater.
- Séquence qui ne repart pas à 1 en changeant d'année → le bug est dans [SequenceGenerator.php](SequenceGenerator.md), pas ici (la clé `{prefixe}_{annee}` doit être différente d'une année à l'autre).

## Tests

[DocumentNumeroGeneratorTest.php](../../tests/Unit/Numbering/DocumentNumeroGeneratorTest.php).
