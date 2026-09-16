# Integration/SpBuildReader.php

**Rôle** : seul point de lecture des données sp_build dans tout sp-compta — voir la note d'architecture en tête du [CLAUDE.md](../../CLAUDE.md) racine. Créé pour le module IK (indemnités kilométriques) le 16/09/2026, suite à une décision explicite de l'utilisateur après qu'on lui ait présenté les compromis (lecture directe vs hook de synchronisation vs ressaisie manuelle).

## Pourquoi une classe séparée plutôt que des requêtes disséminées

Toute la surface de couplage à sp_build tient dans ce seul fichier — si sp_build change un jour ses noms de table ou sa structure, il n'y a qu'un endroit à corriger. Aucune autre classe de sp-compta ne doit contenir de `$wpdb->prefix . 'sp_cal_...'` en dur.

## Ce qui est lu comment

| Donnée | Méthode | Comment |
|---|---|---|
| Entraîneurs actifs + leur km A/R | `trainersActifs()` | `SELECT` direct sur `sp_cal_trainers` |
| Km exceptionnels du mois | `kmExceptionnelsParTrainer()` | `SELECT SUM(km) ... GROUP BY` direct sur `sp_cal_km_exceptionnels` |
| Tarif €/km | `tarifKm()` | `get_option('sp_cal_tarif_km')` |
| **Nombre d'AR/mois** | `interventionsParTrainer()` | **Jamais en SQL direct** — passe par `apply_filters('sp_cal_interventions_par_trainer', [], $annee, $mois)`, filtre exposé par `SpCalPro_Notifications::filter_interventions_par_trainer()` côté sp_build |

Le nombre d'AR est calculé par un algorithme non trivial (expansion des créneaux récurrents hebdo/bihebdo/mensuels + recoupement disponibilités/annulations, voir `SpCalPro_DB::get_interventions_par_trainer()`) qui n'a volontairement pas été dupliqué ici — le risque de divergence (et donc d'un montant différent de celui déjà annoncé au bureau par l'email récapitulatif mensuel de sp_build) l'emportait sur la simplicité d'un accès SQL direct.

## Dégradation si sp_build absent

`disponible()` vérifie l'existence de la table `sp_cal_trainers` (`SHOW TABLES LIKE`). Toutes les autres méthodes la consultent en premier et retournent un tableau/valeur vide si elle répond `false` — jamais d'exception, jamais de requête sur une table qui n'existe pas. `interventionsParTrainer()` se dégrade nativement : si sp_build n'a pas enregistré le filtre (plugin inactif), `apply_filters()` renvoie simplement la valeur par défaut `[]`.

## En cas de bug

- Écran IK vide alors que sp_build est actif → vérifier `disponible()` en isolant l'appel ; si `false` alors que la table existe, vérifier le préfixe de table (`$wpdb->prefix`) — un site multisite avec des préfixes différents par sous-site casserait cette hypothèse.
- Nombre d'AR toujours à 0 → vérifier que `SpCalPro_Notifications` est bien instanciée côté sp_build (elle enregistre le filtre dans son constructeur) — si sp_build a été refactoré et que cette classe n'est plus câblée, le filtre disparaît silencieusement.
- Montant différent de l'email récapitulatif mensuel → ne devrait jamais arriver (même filtre, même fonction) ; si ça arrive, comparer `$year`/`$month` passés aux deux endroits, pas la formule.

## Tests

Pas de test dédié pour l'instant — un test PHPUnit isolé nécessiterait de simuler la présence/absence des tables sp_build, à écrire au prochain passage (voir note dans [Entity/IkPaiement.md](../Entity/IkPaiement.md#tests)).
