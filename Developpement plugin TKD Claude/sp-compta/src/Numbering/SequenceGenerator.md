# Numbering/SequenceGenerator.php

**Rôle** : compteur atomique générique, clé → dernière valeur. Base de la numérotation légale des factures (séquentielle, sans trou — voir [spec-fonctionnelle-module-compta.md](../../../md/spec-fonctionnelle-module-compta.md)).

## Comment ça marche

Une seule requête `INSERT ... ON DUPLICATE KEY UPDATE valeur = valeur + 1` : MySQL verrouille la ligne le temps de l'opération, ce qui rend l'incrément atomique même en cas d'accès concurrent (2 membres du bureau qui créent une facture en même temps) sans transaction explicite à gérer côté PHP. `next('facture_2026')` renvoie 1 la première fois, 2 la suivante, etc. — chaque `cle` a son propre compteur indépendant.

## En cas de bug

- Deux documents avec le même numéro → ne peut normalement pas arriver grâce à `UNIQUE KEY numero` sur les tables `devis`/`facture` ([Database.php](../Database.php)) en plus de ce générateur ; si ça arrive quand même, le bug est dans l'appelant (ex. `DocumentNumeroGenerator` appelé deux fois pour le même document, ou `numero` codé en dur ailleurs).
- Compteur qui saute des valeurs → normal si un `INSERT` de document échoue après l'appel à `next()` (ex. validation refusée) : le numéro consommé n'est pas "rendu". Comportement volontaire (une numérotation légale doit être croissante, pas forcément strictement continue en cas d'échec technique) — à ne changer qu'après consultation.

## Tests

[SequenceGeneratorTest.php](../../tests/Unit/Numbering/SequenceGeneratorTest.php).
