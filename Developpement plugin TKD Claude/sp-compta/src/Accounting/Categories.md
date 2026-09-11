# Accounting/Categories.php

**Rôle** : référentiel comptable officiel à deux niveaux (code catégorie + sous-catégorie), utilisé par les menus déroulants Dépense/Recette et par la répartition hiérarchique de l'onglet Solde. Transcrit directement du tableau CERFA "Compte de résultat" fourni par l'utilisateur (liste officielle confirmée le 08/09/2026, incluant les codes 86/87), utilisé pour les demandes de subvention associative.

## Structure

`DEPENSE`/`RECETTE` sont des tableaux `code => ['label' => ..., 'sous_categories' => [cle => libelle]]`. `code` est le code du plan comptable association (`'60'` à `'68'` + `'86'` pour les dépenses, `'70'` à `'79'` + `'87'` pour les recettes) — jamais un nom arbitraire, pour rester aligné avec le formulaire CERFA officiel si l'export vers ce formulaire est ajouté un jour.

**Chaque code a au moins une sous-catégorie**, y compris ceux que le CERFA laisse sans détail (`65`, `67`, `68`, `76`, `78`, `79`, et `63` qui n'a qu'une ligne) — une sous-catégorie "miroir" du libellé du code est ajoutée pour que le menu déroulant (une seule liste de sous-catégories groupées par `<optgroup>`, voir [Admin/DepenseScreen.md](../Admin/DepenseScreen.md)) reste toujours sélectionnable.

## Codes 86/87 (contributions volontaires en nature)

`86 - Emploi des contributions volontaires en nature` (dépenses) et `87 - Contributions volontaires en nature` (recettes) — bénévolat, dons en nature, mise à disposition gratuite. Ce ne sont normalement pas des mouvements d'argent réels sur le compte bancaire, mais l'utilisateur les a explicitement demandés dans la liste officielle du 08/09/2026 (utile pour le compte-rendu financier CERFA, où ces lignes s'équilibrent en dépense et en recette) — inclus tels quels, en confiance dans l'usage qu'en fera le bureau.

## Sponsors privés (`74` → `sponsors_prives`)

Pas une ligne officielle du CERFA (qui ne détaille que les subventions publiques sous `74`) — ajoutée à la demande de l'utilisateur, qui classe déjà ses sponsors privés sous ce code dans son suivi. C'est la sous-catégorie utilisée par [Billing/SponsorPaiementSync.php](../Billing/SponsorPaiementSync.md) pour la recette générée automatiquement depuis un sponsor.

## API publique

- `Categories::DEPENSE` / `Categories::RECETTE` — les deux référentiels.
- `categorieDeSousCategorie(array $groupe, string $sousCategorieKey): ?string` — retrouve le code parent (`'74'`) d'une sous-catégorie (`'commune'`), `null` si elle n'existe dans aucun code du groupe. Utilisé côté sauvegarde : le formulaire ne soumet que la sous-catégorie choisie, le code catégorie est **toujours dérivé** de cette réponse, jamais saisi séparément (évite toute incohérence code/sous-catégorie).
- `libelleCategorie(array $groupe, int|string $code): string` — `"74 - Subvention d'exploitation"`.
- `libelleSousCategorie(array $groupe, int|string $code, string $sousCategorieKey): string` — libellé de la sous-catégorie seule.

## ⚠️ Piège PHP : les clés `'60'`, `'74'`... deviennent des `int`

Bug réel rencontré et corrigé le 08/09/2026. Les clés de tableau qui ressemblent à un entier décimal (`'60' => [...]`) sont **automatiquement converties en `int` par PHP** dans le tableau réellement construit — `Categories::DEPENSE` a donc des clés `int` (`60`, `61`...) à l'exécution, jamais `string`, même si le code source les écrit entre guillemets. Un `foreach (Categories::DEPENSE as $code => $definition)` donne donc un `$code` de type `int`. Avec `declare(strict_types=1)` (actif dans tout ce plugin), passer cet `int` à une fonction déclarée `string $code` lève une `TypeError` fatale — **c'est exactement ce qui rendait les pages Dépenses/Recettes blanches après l'ajout du référentiel à deux niveaux**, `renderForm()` appelant `libelleCategorie()` avec le `$code` du `foreach`. Corrigé en acceptant `int|string $code` et en castant systématiquement en `(string)` dès l'entrée de `libelleCategorie()`/`libelleSousCategorie()`, et à la sortie de `categorieDeSousCategorie()`. **Ne jamais redéclarer un paramètre `$code` en `string` strict ici** sans repasser par ce cast — la régression reviendrait silencieusement.

## En cas de bug

- Page blanche/coupée sur Dépenses ou Recettes → vérifier en premier `wp-content/debug.log` (ou activer `WP_DEBUG_DISPLAY`) pour une `TypeError` sur `libelleCategorie()`/`libelleSousCategorie()` — voir le piège ci-dessus, cause la plus probable si quelqu'un a retiré le cast `int|string`.
- Une dépense/recette enregistrée avec une catégorie vide → `categorieDeSousCategorie()` a retourné `null`, ce qui signifie que la clé de sous-catégorie soumise n'existe dans aucun code du groupe (formulaire trafiqué, ou clé renommée/supprimée ici après coup) — vérifier l'appelant ([DepenseScreen](../Admin/DepenseScreen.md)/[RecetteScreen](../Admin/RecetteScreen.md)) avant de suspecter cette classe.
- Anciennes dépenses/recettes affichant une clé brute au lieu d'un libellé → normal pour les données saisies avant le 08/09/2026 avec l'ancienne liste provisoire (ex. `'deplacements'`, `'sponsoring'`) — ces clés n'existent plus dans le nouveau référentiel, elles s'affichent telles quelles au lieu d'un libellé traduit ; pas une perte de données, juste un affichage dégradé pour l'historique.

## Tests

[CategoriesTest.php](../../tests/Unit/Accounting/CategoriesTest.php).
