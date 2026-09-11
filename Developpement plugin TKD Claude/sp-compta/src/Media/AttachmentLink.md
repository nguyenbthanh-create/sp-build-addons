# Media/AttachmentLink.php

**Rôle** : affiche la valeur stockée dans `justificatif`/`fichier_contrat` — soit comme un lien cliquable vers le fichier réel (nouvel upload, voir [AttachmentUploader.php](AttachmentUploader.md)), soit comme texte brut (ancienne référence texte libre saisie avant le 08/09/2026, ex. `"ticket-2026-05"`). Utilisé par [DepenseScreen](../Admin/DepenseScreen.md), [RecetteScreen](../Admin/RecetteScreen.md) et [SponsorScreen](../Admin/SponsorScreen.md), en liste et dans le formulaire de modification ("fichier actuel").

## Comment la distinction est faite

`ctype_digit($value)` (la valeur ne contient que des chiffres) **et** `get_post_type((int) $value) === 'attachment'` (un attachment existe réellement avec cet id) — les deux conditions ensemble, pas seulement la première : un ancien texte purement numérique par coïncidence (improbable mais possible) ne doit pas être confondu avec un id d'attachment inexistant.

## En cas de bug

- Un fichier fraîchement uploadé n'affiche pas de lien → vérifier que la valeur enregistrée en base est bien l'id numérique retourné par `AttachmentUploader::handle()` (voir sa doc), pas une chaîne vide ou une URL.
- Une ancienne référence texte s'affiche sans lien → normal, c'est le comportement voulu (voir ci-dessus) — pas un bug.
- Lien cassé (fichier supprimé de la médiathèque après coup) → `get_post_type()` retournerait `false` pour un attachment supprimé, donc `render()` afficherait la valeur brute (l'id numérique) en texte — pas un lien mort silencieux, mais pas très lisible non plus ; comportement acceptable pour l'instant, à améliorer si ça devient gênant en pratique.

## Tests

[AttachmentLinkTest.php](../../tests/Unit/Media/AttachmentLinkTest.php).
