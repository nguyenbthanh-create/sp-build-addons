# Réalisations — TKD Cotisations

Journal daté de ce qui a été fait sur ce plugin.

## Constat au 11/09/2026

Contrairement à `sp_build` et `sp-compta`, ce plugin **n'a pas d'historique détaillé disponible** : pas de dépôt Git, pas de journal des modifications, pas de `CLAUDE.md`. C'est le plugin le plus ancien des trois (fichiers image `logo.jpg`/`signature.jpg`/`Tampon.jpeg` datés d'avril 2026, code métier antérieur à la mise en place de ce suivi documentaire).

Ce qu'on peut constater à partir du code lui-même :

- **Plugin** : « TKD Cotisations » — gestion des cotisations, paiements et reçus pour le club TKD Claira. Version déclarée dans l'en-tête : `1.1.0`.
- Génère les tables nécessaires à l'activation, gère les tarifs, les cotisations/paiements, propose des écrans admin (Tarifs, Initialisation de saison, Vue globale), un bloc cotisation sur la fiche élève, des emails de rappel, et un reçu PDF.
- Contient un générateur PDF interne, `TkdPDF` (`TkdPDF.php`), en PHP pur (sans librairie externe), annoté « v2 — Version corrigée : gestion des accents, alignement parfait et prénom » — donc au moins une itération de correctifs a déjà eu lieu sur ce générateur, mais sans date connue.
- Dernière modification connue du fichier principal (`cotisations.php`) : **10/09/2026** (date du fichier sur disque), sans détail sur ce qui a changé ce jour-là.

## Pourquoi si peu de détail ici

Ce plugin n'a visiblement pas bénéficié du même suivi (Git + `CLAUDE.md` + journal d'incréments) que `sp_build` et `sp-compta`, mis en place à partir de fin août / début septembre 2026 sur ces deux autres plugins. Rien n'indique une perte d'information : il n'y a simplement jamais eu de journal tenu pour celui-ci.

## 11/09/2026

- **Vue globale** : ajout d'un filtre par saison (jusque-là toujours figée sur la saison courante). Les saisons proposées sont celles ayant déjà des cotisations enregistrées, plus la saison courante même si elle est encore vide. Consulter une saison passée est en lecture seule : le bouton « Gérer » (qui mène à la fiche individuelle, laquelle ne connaît que la saison courante) est masqué pour éviter de saisir un paiement sur la mauvaise saison par erreur.
- **Mode de paiement Pass'Sport** : ajouté à la liste déroulante « Mode de paiement » de la fiche adhérent. Quand il est sélectionné, aucun reçu n'est envoyé par email à l'adhérent — le paiement est enregistré normalement (montant, statut, historique), uniquement en interne. Nécessite une mise à jour de la colonne `mode` (enum) en base ; une migration automatique (`ALTER TABLE ... MODIFY`) a été ajoutée pour les sites déjà installés, elle s'exécute au prochain chargement du plugin, sans réactivation.

## À partir de maintenant

Ajouter une entrée datée ici à chaque modification notable, même petite (ex. « 12/09/2026 — correction du calcul du prorata sur une cotisation en cours de saison »). C'est le seul moyen de ne pas se retrouver dans la même situation dans six mois.
