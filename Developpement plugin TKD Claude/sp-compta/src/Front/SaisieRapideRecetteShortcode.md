# Front/SaisieRapideRecetteShortcode.php

**Rôle** : pendant de [SaisieRapideShortcode.php](SaisieRapideShortcode.md) pour les recettes. Demandé par l'utilisateur le 11/09/2026 ("un grand oui") — jusque-là seule la Dépense avait une saisie rapide front-end.

**N'est pas non plus un shortcode public** (depuis le même jour) : assemblée avec la version Dépense par [SaisieRapideCombineeShortcode.php](SaisieRapideCombineeShortcode.md), le vrai `[sp_compta_saisie_rapide]`, qui gère le bouton de bascule entre les deux et le manifest PWA unique.

## Différences avec la version Dépense

- Réutilise [RecetteScreen::saveFromRequest()](../Admin/RecetteScreen.md) au lieu de `DepenseScreen`, et `Categories::RECETTE` au lieu de `Categories::DEPENSE` — même principe de réutilisation totale, aucune logique de sauvegarde propre à cette classe.
- Champs visibles : montant, catégorie, mode de paiement (via [RecetteScreen::modesPaiement()](../Admin/RecetteScreen.md), même méthode publique que côté Dépense), photo du justificatif.
- Champs repliés dans le `<details>` : date, **provenance** (champ texte libre propre aux recettes, ex. "Cotisation", "Buvette" — n'existe pas côté Dépense) et **client lié** (équivalent du fournisseur côté Dépense).
- `handleSave()` redirige avec `sp_compta_saved=recette` (et `renderForm()` n'affiche son message de confirmation que pour cette valeur précise) — même raison que côté Dépense, voir [SaisieRapideShortcode.md](SaisieRapideShortcode.md#confirmation-après-enregistrement-sp_compta_saveddepense).

## Pour le reste, voir [SaisieRapideShortcode.md](SaisieRapideShortcode.md)

Même gestion des gardes d'accès (capacité, exercice actif), même raison d'être front-end plutôt que wp-admin, même limite volontaire (création uniquement, jamais modification/suppression depuis cet écran).

## Tests

[SaisieRapideRecetteShortcodeTest.php](../../tests/Unit/Front/SaisieRapideRecetteShortcodeTest.php) — mêmes scénarios que la version Dépense (accès, exercice actif, présence des champs montant/mode_paiement).
