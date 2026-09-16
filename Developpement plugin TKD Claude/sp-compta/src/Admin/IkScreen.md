# Admin/IkScreen.php

**Rôle** : écran "Indemnités Km" — vue trésorerie des indemnités kilométriques des entraîneurs sp_build, avec bascule en Dépense au clic (voir [Billing/IkPaiementSync.php](../Billing/IkPaiementSync.md)). Répond à la doléance du 16/09/2026 : *"pouvoir retrouver toutes les IK de l'année en cours, avec le nombre d'AR par mois par entraîneur et le calcul d'argent, et cocher si le paiement a été effectué"*.

## Rien n'est stocké côté affichage

Le nombre d'AR, le km et le montant affichés dans le tableau sont **recalculés à chaque chargement de la page** depuis [Integration/SpBuildReader.php](../Integration/SpBuildReader.md) — seule la case "payé" (et le montant qui a été versé au moment du clic) est persistée, via [Repository/IkPaiementRepository.php](../Repository/IkPaiementRepository.md). Si le tarif €/km ou les km A/R changent côté sp_build après coup, les lignes déjà payées gardent leur montant historique (figé dans `IkPaiement::montantVerse()`), mais les lignes pas encore payées reflètent immédiatement la nouvelle valeur.

## Filtrage des lignes (même règle que l'email mensuel de sp_build)

Une ligne (entraîneur, mois) n'apparaît que si `nb > 0` **ou** `kmExceptionnels > 0` — pas de lignes à 0 qui polluent le tableau. Les mois futurs de l'année en cours ne sont pas affichés du tout (`break` dès que `$mois > gmdate('n')`).

## Tri et filtre "Km A/R renseigné" (ajoutés le 16/09/2026)

`renderTableau()` construit d'abord **toutes** les lignes dans un tableau (`$lignes`) avant de les afficher, plutôt que d'`echo` directement dans la double boucle mois × entraîneur — c'est ce qui permet de les trier après coup (`usort` sur `[nom, mois]` ou `[mois, nom]` selon le paramètre GET `tri`) sans dupliquer la boucle. Le filtre `masquer_sans_km` (GET, checkbox auto-soumise) retire les entraîneurs dont `km <= 0` **avant** la boucle — ces lignes affichaient toujours 0€ de montant faute de km configuré côté sp_build, ce qui polluait la liste sans action possible depuis cet écran (la config du km se fait côté fiche entraîneur de sp_build). Les deux contrôles vivent dans le même `<form method="get">` que le sélecteur d'année (`renderControles()`) pour rester dans une seule soumission.

Le redirect après un clic sur "payé" utilise `wp_get_referer()` plutôt que de reconstruire l'URL avec la seule `annee` — sinon changer le tri/filtre puis cocher une case aurait silencieusement réinitialisé ces deux choix.

## Case à cocher = formulaire auto-soumis, pas de bouton "Enregistrer"

Chaque case à cocher est son propre `<form>` avec un champ caché `paye` fixé à la valeur **opposée** de l'état actuel, et `onchange="this.form.submit()"` — cliquer la case soumet immédiatement le formulaire, qui bascule l'état stocké. Pas de sélection multiple/bulk : une case = une action, cohérent avec le reste de sp-compta (pas de JS framework, formulaires classiques `admin-post.php`).

## Pourquoi certaines cases sont désactivées

Tant qu'aucun exercice n'est actif, les cases **pas encore cochées** sont désactivées (impossible de créer une dépense sans exercice — voir [IkPaiementSync.md](../Billing/IkPaiementSync.md#pas-dexercice-actif--pas-de-dépense-possible)). Les cases **déjà cochées** restent visibles et cochées (lecture seule) même sans exercice actif, pour ne pas cacher l'historique.

## En cas de bug

- Écran vide → vérifier d'abord `SpBuildReader::disponible()` (message dédié affiché si sp_build absent), puis si des entraîneurs actifs existent côté sp_build (`trainersActifs()`).
- Montant qui ne correspond pas à l'email mensuel de sp_build → voir [SpBuildReader.md](../Integration/SpBuildReader.md#en-cas-de-bug), ce n'est jamais dans ce fichier que se trouve le calcul.
- Case qui ne réagit pas au clic → vérifier que JS n'est pas désactivé côté navigateur (pas de fallback `<noscript>` sur les cases elles-mêmes, contrairement au sélecteur d'année).

## Tests

Pas de test dédié pour l'instant — voir note dans [Entity/IkPaiement.md](../Entity/IkPaiement.md#tests). À couvrir en priorité : le filtrage des lignes (nb=0 et kmExcep=0 exclues), et la désactivation des cases sans exercice actif.
