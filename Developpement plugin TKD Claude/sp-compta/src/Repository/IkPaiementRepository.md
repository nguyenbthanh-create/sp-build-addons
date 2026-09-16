# Repository/IkPaiementRepository.php

**Rôle** : accès SQL pour `IkPaiement`, même patron que [SponsorRepository.php](SponsorRepository.md) (`save()` bascule insert/update selon `id()`). Deux méthodes de lecture spécifiques :

- `findByTrainerPeriode(trainerId, annee, mois)` — utilisée par [IkPaiementSync.php](../Billing/IkPaiementSync.md) pour vérifier l'idempotence avant de créer une dépense.
- `forAnnee(annee)` — retourne un tableau **indexé par `"trainerId_mois"`**, pas par `id`, pour un lookup direct depuis [Admin/IkScreen.php](../Admin/IkScreen.md) pendant la boucle d'affichage (évite une requête par cellule du tableau).

Contrainte `UNIQUE KEY (trainer_id, annee, mois)` en base (voir [Database.php](../Database.md)) — une seule ligne possible par entraîneur/mois, cohérent avec `findByTrainerPeriode()` qui suppose au plus un résultat.

## En cas de bug

- Lookup `forAnnee()` qui ne trouve rien alors qu'une ligne existe → vérifier la clé `"trainerId_mois"` construite côté [IkScreen.php](../Admin/IkScreen.md) (mois sans zéro de tête, ex. `"3_9"` pas `"3_09"`).

## Tests

Pas de test dédié pour l'instant — voir note dans [Entity/IkPaiement.md](../Entity/IkPaiement.md#tests).
