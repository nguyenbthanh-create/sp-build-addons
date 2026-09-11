# Correctif.md — journal des bugs corrigés

Répertoire des erreurs réelles rencontrées pendant le développement de `sp-compta`, avec leur cause racine, pour ne pas les refaire. À consulter avant toute modification touchant à l'activation du plugin, au déploiement, ou au référentiel comptable — ce sont les zones où les bugs ci-dessous se sont produits.

## Règles à retenir (extraites des incidents ci-dessous)

1. **Ne jamais déclarer un paramètre `string` pour une valeur qui peut provenir d'une clé de tableau PHP numérique.** PHP convertit automatiquement `'60' => ...` en clé `int(60)` — un `foreach` dessus donne un `int`, jamais un `string`, même si le code source écrit la clé entre guillemets. Sous `declare(strict_types=1)` (actif partout dans ce plugin), ça lève une `TypeError` fatale. Accepter `int|string` et caster `(string)` en interne dès que la valeur peut venir d'une clé de tableau.
2. **Après l'ajout de nouveaux fichiers/dossiers, toujours redéployer le dossier `src/` complet**, pas seulement les fichiers modifiés — un outil de synchronisation FTP peut silencieusement ignorer les nouveaux dossiers si on ne fait glisser que les fichiers changés.
3. **En cas de page blanche/coupée en pleine page (formulaire tronqué, tableau absent) sans message d'erreur visible** : activer immédiatement `WP_DEBUG` + `WP_DEBUG_DISPLAY` + `WP_DEBUG_LOG` dans `wp-config.php` plutôt que de deviner — les erreurs PHP fatales sont silencieuses par défaut en production, et le symptôme visuel (page qui s'arrête net) est la signature typique d'une erreur fatale survenue en cours de rendu.
4. **Toute nouvelle colonne/table doit incrémenter `Database::DB_VERSION`** (voir [src/Database.md](src/Database.md)) — sinon `maybeUpgrade()` ne la propage jamais sur les sites déjà activés.
5. **Ne jamais faire dépendre l'exécution du plugin de `composer install`/`vendor/`** — ce projet se déploie par copie de fichiers (FTP), sans étape de build, comme sp_build. `vendor/` est réservé au développement (tests, phpcs), jamais au runtime.

## Incidents

### 08/09/2026 — TypeError fatale sur `Categories::libelleCategorie()` (clés de tableau castées en int)

**Symptôme** : pages Dépenses/Recettes coupées net après le champ Fournisseur/Client — menu déroulant Catégorie vide (une seule option `--`), aucun champ ni tableau après.

**Cause racine** : `Categories::DEPENSE`/`RECETTE` utilisent des clés comme `'60'`, `'74'` — PHP les convertit automatiquement en `int` dans le tableau réel. `renderForm()` itère avec `foreach (Categories::DEPENSE as $code => $definition)`, donc `$code` est un `int`. `Categories::libelleCategorie(array $groupe, string $code)` était déclarée avec un paramètre `string` strict → `TypeError` fatale non attrapée, invisible sans `WP_DEBUG` actif.

**Correctif** : `libelleCategorie()`/`libelleSousCategorie()` acceptent `int|string $code` et castent systématiquement en `(string)` en première ligne. `categorieDeSousCategorie()` caste aussi sa valeur de retour. Tests de non-régression ajoutés dans [CategoriesTest.php](tests/Unit/Accounting/CategoriesTest.php) (appel direct avec un `int` littéral, vérification que le retour est bien `is_string()`).

**Piste de diagnostic suivie avant de trouver la vraie cause** (gardée ici pour ne pas la reparcourir inutilement la prochaine fois) : d'abord soupçonné un déploiement partiel (dossier `Accounting/` jamais uploadé) — écarté après vérification octet pour octet du fichier serveur, identique au fichier source. La bonne piste n'est apparue qu'après activation de `WP_DEBUG_LOG`, qui a donné la trace d'appel exacte. **Leçon : demander le log d'erreur PHP dès le premier symptôme de page coupée, plutôt que d'enchaîner des hypothèses de déploiement.**

### 08/09/2026 — `ExerciceRepository` sans possibilité de suppression

**Symptôme** : aucun moyen de supprimer un exercice comptable de test depuis Paramètres.

**Cause racine** : oubli, pas une contrainte réglementaire (seules les factures ont une obligation légale de non-suppression, voir [FactureRepository.md](src/Repository/FactureRepository.md)) — `ExerciceRepository` n'avait simplement jamais eu de méthode `delete()`.

**Correctif** : ajout de `ExerciceRepository::delete()` + [Billing/ExerciceDeletionGuard.php](src/Billing/ExerciceDeletionGuard.md), qui refuse la suppression si l'exercice a la moindre donnée rattachée (dépense/recette/sponsor/devis/facture) — jamais de suppression forcée.

### 07/09/2026 — Activation du plugin en erreur fatale (`vendor/autoload.php` introuvable)

**Symptôme** : "L'extension n'a pas pu être activée, car elle a déclenché une erreur fatale" à l'activation.

**Cause racine** : `sp-compta.php` chargeait les classes via `require __DIR__ . '/vendor/autoload.php'` (autoload Composer), mais `composer install` n'a jamais été exécuté (ni sur l'environnement de développement, ni sur le serveur) — `vendor/` n'existe pas.

**Correctif** : remplacement par un autoloader natif (`spl_autoload_register` directement dans `sp-compta.php`, résout `SpCompta\...` vers `src/...`) — plus aucune dépendance à Composer au runtime, cohérent avec le déploiement "sans outillage" du projet (voir règle 5 ci-dessus).

**Incohérence corrigée au passage** : `composer.json` déclarait `"php": ">=7.4"` alors que tout le code utilise le *constructor property promotion* (PHP 8.0+). Corrigé à `">=8.0"`. N'a pas été la cause du bug ci-dessus (le serveur tourne en PHP 8.4), mais aurait pu induire en erreur sur un hébergement plus ancien.

## Comment ajouter une entrée ici

Un nouveau bug corrigé de la même ampleur (erreur fatale, comportement silencieusement faux, donnée perdue) mérite une entrée : symptôme observé, cause racine trouvée, correctif appliqué, et si une fausse piste a été suivie avant de trouver la vraie cause, la garder brièvement — c'est souvent ce qui fait gagner le plus de temps la fois suivante.
