# Réalisations — SP Compta

Journal daté de ce qui a été **réellement fait** sur ce plugin. Reconstitué à partir de la section « État d'avancement » de `CLAUDE.md` et des incidents de `correctif.md` (pas d'historique Git sur ce dossier — voir [../JOURNAL.md](../JOURNAL.md)).

## 07/09/2026

- **Incident corrigé** : activation du plugin en erreur fatale (`vendor/autoload.php` introuvable — `composer install` n'avait jamais été lancé). Remplacé par un autoloader natif (`spl_autoload_register`), plus aucune dépendance à Composer au runtime.
- `composer.json` corrigé (`php >=7.4` → `>=8.0`, cohérent avec le code utilisant la *constructor property promotion*).
- **Déployé et activé avec succès sur le site de test OVH** — les 12 tables sont confirmées créées (préfixe `mod237_`), PHP 8.4.22 constaté sur le site (bien au-dessus du minimum requis).
- Incrément 1 (fondations) et Incrément 2 (CRUD simples) considérés acquis à cette date : bootstrap, capacité `sp_compta_manager`, tables + repository + entité + tests pour Fournisseur, Client, Dépense, Recette, Sponsor, Exercice, Paramètres.
- Incrément 3 (facturation) : numérotation automatique (`F2026-0001`/`D2026-0001`), Devis et Facture avec lignes, bascule devis accepté → facture.
- Incrément 4 (écrans admin CRUD) : `AdminScreen`, `Menu`, et six écrans (Fournisseur, Client, Dépense, Recette, Sponsor, Paramètres).

## 08/09/2026

- Incrément 5 (doléances) : mise à jour automatique du schéma sur site déjà déployé (`Database::maybeUpgrade()`), synchronisation sponsor payé → recette automatique, catégories dépense/recette en menu déroulant (liste provisoire), nouvel onglet Solde (lecture seule), recherche « contient » sur les listes, liste des mouvements de l'exercice sur l'écran Solde (ajoutée après un retour utilisateur).
- Incrément 6 : accès bureau — rôle Secrétaire / cases à cocher par compte WordPress pour accorder l'accès à la comptabilité précisément aux membres du bureau, en plus (jamais à la place) de l'administrateur.
- Incrément 7 : suppression d'un exercice comptable possible, avec garde-fou (refus si des données y sont rattachées).
- **Incident corrigé** : `ExerciceRepository` n'avait pas de méthode de suppression (oubli, pas une contrainte légale) — corrigé avec le garde-fou ci-dessus.
- Incrément 8 : référentiel comptable officiel CERFA (catégories/sous-catégories charges 60-68 + 86, produits 70-79 + 87, transcrit du tableau fourni par l'utilisateur) — remplace la liste provisoire de l'incrément 5. Version de schéma incrémentée à `1.2.0`.
- **Incident corrigé (le plus sérieux à ce jour)** : `TypeError` fatale sur `Categories::libelleCategorie()` — les clés de tableau comme `'60'` sont automatiquement converties en `int` par PHP, ce qui cassait net les pages Dépenses/Recettes sans message d'erreur visible. Corrigé en acceptant `int|string` partout où c'est pertinent. Détail complet et fausses pistes explorées dans `correctif.md`.

## 09/09/2026 (date approximative)

- Incrément 9 : upload réel de fichiers vers la médiathèque WordPress (justificatifs de dépense/recette, contrat sponsor), remplaçant les anciens champs texte. *Date non indiquée explicitement dans `CLAUDE.md` — situé entre l'incrément 8 (08/09) et la colonne Fournisseur (10/09), probablement le 09/09.*

## 10/09/2026

- Colonne Fournisseur ajoutée à la liste des Dépenses (nom affiché et recherché, plus seulement l'identifiant).
- Incrément 10 : saisie rapide front-end + PWA mobile (`[sp_compta_saisie_rapide]`) pour ajouter une dépense depuis un téléphone, avec photo.
- Incrément 10 bis (même jour) : bannière d'installation automatique de la PWA (service worker minimal, uniquement pour satisfaire les critères d'installabilité Chrome — aucun mode hors-ligne).

## À faire savoir : ce qui reste en cours (état au 10/09/2026, voir CLAUDE.md « Reste à faire »)

- Écrans Devis/Facture : pas encore commencés.
- Textes légaux exacts des PDF (TVA, numérotation légale) : à valider document par document.
- Export CERFA (le vrai formulaire, pas juste l'alignement sur ses codes) : pas demandé pour l'instant.
- Saisie rapide pour les Recettes : seule la Dépense est couverte à ce jour.

*(Repris à l'identique dans [EVOLUTION.md](EVOLUTION.md) — c'est la liste des évolutions prévues.)*

## 11/09/2026

- Ajout du champ **Mode de paiement** dans la saisie rapide des dépenses (`[sp_compta_saisie_rapide]`), avec les mêmes options que l'écran admin (`DepenseScreen::modesPaiement()`, nouvelle méthode publique). Le champ existait déjà côté traitement (`saveFromRequest()`), il manquait seulement dans ce formulaire.
- **Saisie rapide pour les Recettes** : nouveau shortcode `[sp_compta_saisie_rapide_recette]` ([Front/SaisieRapideRecetteShortcode.php](src/Front/SaisieRapideRecetteShortcode.md)), pendant exact de la version Dépense (montant, catégorie, mode de paiement, photo justificatif visibles ; date/provenance/client repliés). Aucune logique de sauvegarde propre : réutilise `RecetteScreen::saveFromRequest()`.
- **Nouvel écran « Rapport / Export »** ([Admin/RapportExerciceScreen.php](src/Admin/RapportExerciceScreen.md)), suite à un état des lieux utilisateur ayant confirmé l'absence de toute fonctionnalité d'export. Pour chaque exercice, deux exports :
  - **Sauvegarde brute (JSON)** — [Reporting/ExerciceExportateur.php](src/Reporting/ExerciceExportateur.md) : toutes les dépenses/recettes/sponsors/devis/factures de l'exercice, avec les noms de fournisseur/client résolus (pas seulement leurs id). Pensé comme filet de sécurité si le site plante, pas comme document de présentation.
  - **Rapport financier pour l'AG (HTML imprimable)** — [Reporting/RapportAgGenerator.php](src/Reporting/RapportAgGenerator.md) : solde initial, totaux, résultat net, répartition par catégorie CERFA, liste des sponsors, et un graphique de comparaison avec l'exercice précédent (barres SVG générées en PHP, sans dépendance externe, imprime correctement).
- **Décision utilisateur** : l'export vers le formulaire CERFA officiel est abandonné (soldes du club sous les 10 000 €, largement sous le seuil légal de 23 000 € de subventions publiques qui l'imposerait) — voir `EVOLUTION.md`.
- **Export CSV des mouvements** ([Reporting/MouvementsCsvExportateur.php](src/Reporting/MouvementsCsvExportateur.md)), ajouté le même jour à la demande de la trésorière pour archivage dans Excel : une ligne par dépense/recette triée par date, montant signé (négatif pour une dépense), tiers résolu (fournisseur/client/provenance), format pensé pour Excel-FR (point-virgule, BOM UTF-8, virgule décimale) et protégé contre l'injection de formule sur les champs de texte libre.
- **Bascule Dépense/Recette sur la saisie rapide** ([Front/SaisieRapideCombineeShortcode.php](src/Front/SaisieRapideCombineeShortcode.md)), suite à un retour utilisateur après avoir testé la fonctionnalité : les deux formulaires de saisie rapide, jusque-là deux shortcodes/pages séparés, sont désormais réunis sur une seule page avec deux boutons pour passer de l'un à l'autre sans rechargement. Le shortcode `[sp_compta_saisie_rapide]` pointe maintenant vers cette page combinée ; `[sp_compta_saisie_rapide_recette]` n'existe plus en tant que shortcode public (les deux anciens manifests PWA distincts sont fusionnés en un seul).

## 12/09/2026

- Ajout du mode de paiement **« prelevement »** (prélèvement automatique) dans `DepenseScreen::MODES_PAIEMENT` et `RecetteScreen::MODES_PAIEMENT` — les deux constantes sont dupliquées entre les deux écrans (pas de référentiel commun), donc modifiées ensemble. Aucune migration de schéma nécessaire : la colonne `mode_paiement` est un simple `VARCHAR(20)`, pas un ENUM (contrairement à `tkd-cotisations`). Répercuté automatiquement dans la saisie rapide (Dépense/Recette) et l'export CSV des mouvements, qui réutilisent déjà `modesPaiement()`/la valeur brute sans liste dupliquée.

---

*Dernière mise à jour de ce fichier : 12/09/2026. Ce plugin n'a pas d'historique Git — pense-bête à mettre à jour manuellement après chaque session, ou mieux : initialiser Git dessus (voir [../JOURNAL.md](../JOURNAL.md)).*
