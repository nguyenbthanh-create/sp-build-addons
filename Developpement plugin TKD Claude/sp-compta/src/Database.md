# Database.php

**Rôle** : source unique de vérité pour le schéma SQL. Nom des tables (`table*()`) + création (`createTables()`, via `dbDelta`, idempotent — rejouable sans erreur si la table existe déjà).

## État actuel (incrément 3)

Toutes les tables du modèle de données sont créées : `sp_compta_exercice`, `sp_compta_fournisseur`, `sp_compta_client`, `sp_compta_depense`, `sp_compta_recette`, `sp_compta_sponsor`, `sp_compta_parametres`, `sp_compta_sequence` (compteurs de numérotation, voir [Numbering/SequenceGenerator.md](Numbering/SequenceGenerator.md)), `sp_compta_devis` + `sp_compta_devis_ligne`, `sp_compta_facture` + `sp_compta_facture_ligne`.

`depense.fournisseur_id`, `recette.client_id`, `devis.client_id`, `facture.client_id`, `facture.devis_id` sont des clés étrangères **logiques uniquement** (pas de contrainte `FOREIGN KEY` SQL — choix délibéré, cohérent avec le reste du plugin : `dbDelta` gère mal les contraintes FK et un enregistrement orphelin ne doit jamais bloquer une suppression de fournisseur/client).

**Pas de colonne `total` sur `devis_ligne`/`facture_ligne`** : contrairement à la première version de la spec, ce n'est pas stocké — toujours recalculé (`quantite * prix_unitaire`) côté PHP par [Entity/LigneDocument.php](Entity/LigneDocument.md), pour éviter qu'un total stocké se désynchronise silencieusement de `quantite`/`prix_unitaire` après une modification.

`devis.numero` et `facture.numero` ont une contrainte `UNIQUE` SQL — en plus de la génération séquentielle applicative (voir [Numbering](Numbering/DocumentNumeroGenerator.md)), la base refuse elle-même un doublon.

`sponsor.contrat_paye` (bool) et `sponsor.recette_id` (FK logique nullable) — ajoutés le 08/09/2026 pour la bascule automatique sponsor → recette, voir [Billing/SponsorPaiementSync.md](Billing/SponsorPaiementSync.md).

`depense.sous_categorie` et `recette.sous_categorie` — ajoutés le 08/09/2026 en complément de `categorie`, pour le référentiel comptable à deux niveaux (code catégorie CERFA + sous-catégorie), voir [Accounting/Categories.md](Accounting/Categories.md). `categorie` stocke désormais un code CERFA (`'74'`), plus une clé de catégorie libre comme avant le 08/09/2026 — les anciennes valeurs déjà en base ne matchent plus le nouveau référentiel et s'affichent en clé brute (dégradation gracieuse, pas une perte de données).

## Mise à jour du schéma sur un site déjà actif : `maybeUpgrade()`

`createTables()` seul ne suffit pas une fois le plugin déployé — il n'est appelé qu'à l'activation. `maybeUpgrade()` compare `DB_VERSION` (constante à incrémenter à chaque changement de schéma) à l'option WordPress `sp_compta_db_version` ; si elles diffèrent, il rejoue `createTables()` (sans risque : `dbDelta` n'ajoute que ce qui manque, ne supprime ni ne tronque jamais de données) puis met à jour l'option. Câblé sur `admin_init` dans [Plugin.php](../Plugin.php) — un site déjà actif récupère donc les nouvelles colonnes au prochain chargement d'une page wp-admin, sans réactivation manuelle.

## Convention

- Toujours passer par `table*()` pour obtenir un nom de table — jamais de nom en dur ailleurs dans le plugin (pattern imposé par [sp_build/CLAUDE.md](../../sp_build/CLAUDE.md), ligne "Don't assume a table name from convention").
- `dbDelta()` a des exigences de formatage strictes (2 espaces avant `PRIMARY KEY`, types en majuscules) — ne pas reformater le SQL sans relire la doc WordPress `dbDelta`.
- **Toute nouvelle colonne/table doit s'accompagner d'un incrément de `DB_VERSION`** — sinon `maybeUpgrade()` ne la propage jamais sur les sites déjà activés.

## En cas de bug

- Table non créée après activation → vérifier que `register_activation_hook` dans [sp-compta.php](../sp-compta.php) pointe bien vers `Plugin::activate`, et que `ABSPATH . 'wp-admin/includes/upgrade.php'` est bien accessible (contexte admin uniquement).
- Colonne manquante après une modification de schéma sur un site déjà actif → vérifier que `DB_VERSION` a bien été incrémentée (sinon `maybeUpgrade()` ne fait rien) et qu'une page wp-admin a été rechargée depuis (le hook est sur `admin_init`, pas `init`).

## Tests

Pas de test unitaire dédié (nécessite une vraie base WP, testé indirectement par `FournisseurRepositoryTest::setUp()` qui appelle `createTables()` avant chaque test).
